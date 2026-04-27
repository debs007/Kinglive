<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinSeller;
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
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'unique:coin_sellers,email'],
            'password' => ['required', 'string', 'min:6'],
            'coins'    => ['nullable', 'integer', 'min:0'],
        ]);

        CoinSeller::create([
            'name'         => $request->name,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'coin_balance' => $request->coins ?? 0,
        ]);

        return back()->with('success', 'Coin seller created successfully.');
    }

    public function addCoins(Request $request, int $id)
    {
        $request->validate([
            'coins' => ['required', 'integer', 'min:1'],
        ]);

        $seller = CoinSeller::findOrFail($id);
        $seller->increment('coin_balance', $request->coins);

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

    // ── Give coins directly to user (admin only, no balance limit) ────────────

    public function giveCoinsToUser(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'string'],
            'coins'   => ['required', 'integer', 'min:1'],
            'note'    => ['nullable', 'string', 'max:255'],
        ]);

        // Accept both numeric ID and username
        $user = is_numeric($request->user_id)
            ? User::findOrFail($request->user_id)
            : User::where('username', $request->user_id)->firstOrFail();
        $user->increment('coin_balance', $request->coins);

        CoinSellerTransaction::create([
            'coin_seller_id' => null,  // null = admin
            'user_id'        => $request->user_id,
            'coins'          => $request->coins,
            'type'           => 'admin_grant',
            'note'           => $request->note ?? 'Admin grant',
        ]);

        return back()->with('success', "Added {$request->coins} coins to {$user->username}.");
    }

    // ── Transaction report ────────────────────────────────────────────────────

    public function transactions(Request $request)
    {
        $transactions = CoinSellerTransaction::with([
                'seller:id,name',
                'user:id,username,avatar_url',
            ])
            ->when($request->seller_id, fn($q) =>
                $q->where('coin_seller_id', $request->seller_id))
            ->when($request->type, fn($q) =>
                $q->where('type', $request->type))
            ->latest()
            ->paginate(30);

        $sellers = CoinSeller::orderBy('name')->get(['id', 'name']);

        return view('admin.coin_sellers.transactions',
            compact('transactions', 'sellers'));
    }
}
