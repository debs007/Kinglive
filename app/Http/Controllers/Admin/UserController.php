<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\GiftTransaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    public function index(Request $request)
    {
        $search     = trim($request->search ?? '');
        $isIdSearch = $search !== '' && is_numeric($search);

        $query = User::withCount(['rooms', 'followers', 'giftsSent'])
            ->when($search !== '', function ($q) use ($search, $isIdSearch) {
                if ($isIdSearch) {
                    $num    = (int) $search;
                    $realId = $num > 100000 ? $num - 100000 : $num;
                    $q->where(function ($qq) use ($search, $realId) {
                        $qq->where('id', $realId)
                           ->orWhere('username', 'like', "%{$search}%")
                           ->orWhere('email',    'like', "%{$search}%")
                           ->orWhere('phone',    'like', "%{$search}%");
                    });
                } else {
                    $q->where(function ($qq) use ($search) {
                        $qq->where('username', 'like', "%{$search}%")
                           ->orWhere('email',    'like', "%{$search}%")
                           ->orWhere('phone',    'like', "%{$search}%");
                    });
                }
            })
            ->when(!$isIdSearch && $request->role,
                fn ($q, $r) => $q->where('role', $r))
            ->when(!$isIdSearch && $request->has('active'),
                fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->latest();

        $users = $query->paginate(25)->withQueryString();
        return view('admin.users.index', compact('users'));
    }

    public function show(Request $request, int $id)
    {
        $user = User::withCount(['rooms', 'followers', 'following'])
            ->with(['bans.bannedBy:id,username', 'rooms' => fn ($q) => $q->latest()->limit(5)])
            ->findOrFail($id);

        $topGifts = GiftTransaction::where('sender_id', $id)
            ->selectRaw('gift_id, COUNT(*) as count, SUM(coin_total) as total')
            ->with('gift:id,name,thumbnail_url')
            ->groupBy('gift_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Date range filter — default to today
        $from = $request->input('from', now()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';

        $coinHistory = CoinTransaction::where('user_id', $id)
            ->whereBetween('created_at', [$fromDt, $toDt])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // Summary for date range
        $summary = CoinTransaction::where('user_id', $id)
            ->whereBetween('created_at', [$fromDt, $toDt])
            ->selectRaw("
                COUNT(*) as total_tx,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_out
            ")
            ->first();

        return view('admin.users.show', compact(
            'user', 'topGifts', 'coinHistory', 'summary', 'from', 'to'
        ));
    }

    public function updateRole(Request $request, int $id)
    {
        $data = $request->validate(['role' => ['required', 'in:user,host,moderator,admin']]);
        User::findOrFail($id)->update($data);
        return back()->with('success', 'Role updated successfully.');
    }

    public function adjustCoins(Request $request, int $id)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $user = User::findOrFail($id);
        $this->walletService->adminCreditCoins($user, $data['amount'], $data['reason']);
        return back()->with('success', 'Coins adjusted successfully.');
    }

    public function toggleActive(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
        return back()->with('success', 'User account ' . ($user->is_active ? 'activated' : 'deactivated') . '.');
    }
}