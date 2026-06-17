<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinSeller;
use App\Models\CoinTransaction;
use App\Models\CoinSellerTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CoinSellerController extends Controller
{
    public function index()
    {
        $sellers = CoinSeller::withCount('transactions')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.coin_sellers.index', compact('sellers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'email'           => ['required', 'email', 'unique:coin_sellers,email'],
            'password'        => ['required', 'string', 'min:6'],
            'coins'           => ['nullable', 'integer', 'min:0'],
            'price_per_100k'  => ['nullable', 'numeric', 'min:0'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
        ]);

        CoinSeller::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'coin_balance'    => $request->coins ?? 0,
            'price_per_100k'  => $request->price_per_100k,
            'whatsapp_number' => $request->whatsapp_number,
        ]);

        return back()->with('success', 'Coin seller created successfully.');
    }

    public function addCoins(Request $request, int $id)
    {
        $request->validate([
            'coins' => ['required', 'integer', 'min:1'],
            'note'  => ['nullable', 'string', 'max:255'],
        ]);
        $seller = CoinSeller::findOrFail($id);
        $seller->increment('coin_balance', $request->coins);

        // Record admin stock grant so we can track history per seller
        CoinSellerTransaction::create([
            'coin_seller_id' => $seller->id,
            'user_id'        => null, // given to seller as stock, not a user
            'coins'          => $request->coins,
            'type'           => 'admin_grant',
            'note'           => $request->note ?? 'Admin stock grant by ' . auth()->user()?->username,
        ]);

        return back()->with('success', "Added {$request->coins} coins to {$seller->name}.");
    }

    public function toggleActive(int $id)
    {
        $seller = CoinSeller::findOrFail($id);
        $seller->update(['is_active' => ! $seller->is_active]);
        return back()->with('success', 'Status updated.');
    }

    public function destroy(int $id)
    {
        CoinSeller::findOrFail($id)->delete();
        return back()->with('success', 'Coin seller deleted.');
    }

    public function giveCoinsToUser(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'string'],
            'coins'   => ['required', 'integer', 'min:1'],
            'note'    => ['nullable', 'string', 'max:255'],
        ]);

        $user = is_numeric($request->user_id)
            ? User::findOrFail($request->user_id)
            : User::where('username', $request->user_id)->firstOrFail();
        $user->increment('coin_balance', $request->coins);

        CoinSellerTransaction::create([
            'coin_seller_id' => null,
            'user_id'        => $request->user_id,
            'coins'          => $request->coins,
            'type'           => 'admin_grant',
            'note'           => $request->note ?? 'Admin grant',
        ]);

        return back()->with('success', "Added {$request->coins} coins to {$user->username}.");
    }

    public function sellerGrantSummary(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        // Per-seller totals of admin grants
        $sellers = CoinSeller::withSum([
                'transactions as total_granted' => fn($q) => $q
                    ->where('type', 'admin_grant')
                    ->when($from, fn($q) => $q->where('created_at', '>=',
                        \Carbon\Carbon::parse($from)->startOfDay()))
                    ->when($to,   fn($q) => $q->where('created_at', '<=',
                        \Carbon\Carbon::parse($to)->endOfDay())),
            ], 'coins')
            ->withCount([
                'transactions as grant_count' => fn($q) => $q
                    ->where('type', 'admin_grant')
                    ->when($from, fn($q) => $q->where('created_at', '>=',
                        \Carbon\Carbon::parse($from)->startOfDay()))
                    ->when($to,   fn($q) => $q->where('created_at', '<=',
                        \Carbon\Carbon::parse($to)->endOfDay())),
            ])
            ->orderByDesc('total_granted')
            ->get();

        $grandTotal = $sellers->sum('total_granted');

        // Recent grants per seller for detail
        $recentGrants = CoinSellerTransaction::with(['seller:id,name'])
            ->where('type', 'admin_grant')
            ->whereNotNull('coin_seller_id')
            ->when($from, fn($q) => $q->where('created_at', '>=',
                \Carbon\Carbon::parse($from)->startOfDay()))
            ->when($to,   fn($q) => $q->where('created_at', '<=',
                \Carbon\Carbon::parse($to)->endOfDay()))
            ->when($request->seller_id,
                fn($q) => $q->where('coin_seller_id', $request->seller_id))
            ->latest()
            ->paginate(50);

        return view('admin.coin_sellers.grant_summary',
            compact('sellers', 'grandTotal', 'recentGrants'));
    }

    public function transactions(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');
        $type = $request->input('type'); // all | admin_credit | recharge | adjustment | coin_seller
        $search = $request->input('search');

        // ── ALL admin-related transactions from CoinTransaction table ─────────
        // Includes: admin_credit, recharge (with admin_adjustment reference),
        // live_reward (manual admin credits), coin seller grants
        $query = \App\Models\CoinTransaction::with([
                'user:id,username,display_name,avatar_url',
            ])
            ->where(function ($q) {
                $q->where('type', 'admin_credit')  // WalletService direct credits
                  ->orWhere('type', 'live_reward')  // manual live reward credits
                  ->orWhere(function ($q2) {
                      // admin adjustments (add OR deduct) stored as 'recharge'
                      $q2->where('type', 'recharge')
                         ->where('reference', 'like', 'admin_adjustment:%');
                  })
                  ->orWhere(function ($q2) {
                      // coin seller top-ups stored as 'recharge'
                      $q2->where('type', 'recharge')
                         ->where('reference', 'like', 'coin_seller:%');
                  });
            })
            ->when($from, fn($q) => $q->where('created_at', '>=',
                \Carbon\Carbon::parse($from)->startOfDay()))
            ->when($to,   fn($q) => $q->where('created_at', '<=',
                \Carbon\Carbon::parse($to)->endOfDay()))
            ->when($search, fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('username', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%")
            ));

        // Type sub-filter
        if ($type === 'admin_credit') {
            // Admin additions only (positive amount, admin_adjustment reference)
            $query->where('type', 'recharge')
                  ->where('reference', 'like', 'admin_adjustment:%')
                  ->where('amount', '>', 0);
        } elseif ($type === 'adjustment') {
            // Deductions only (negative amount)
            $query->where('type', 'recharge')
                  ->where('reference', 'like', 'admin_adjustment:%')
                  ->where('amount', '<', 0);
        } elseif ($type === 'live_reward') {
            $query->where('type', 'live_reward');
        } elseif ($type === 'coin_seller') {
            $query->where('type', 'recharge')
                  ->where('reference', 'like', 'coin_seller:%');
        }

        $totalCoinsAll = (clone $query)->sum('amount');
        $totalCountAll = (clone $query)->count();

        $transactions = $query->latest()->paginate(50);
        $sellers      = CoinSeller::orderBy('name')->get(['id', 'name']);

        return view('admin.coin_sellers.transactions',
            compact('transactions', 'sellers', 'totalCoinsAll', 'totalCountAll'));
    }
}