<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftTransaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function index(Request $request)
    {
        $search  = trim($request->search ?? '');
        $isIdSearch = $search !== '' && is_numeric($search);

        // When searching by numeric ID, skip role/active filters
        // so a specific user is always found regardless of their status
        $query = User::withCount(['rooms', 'followers', 'giftsSent'])
            ->when($search !== '', function ($q) use ($search, $isIdSearch) {
                if ($isIdSearch) {
                    $num    = (int) $search;
                    $realId = $num > 100000 ? $num - 100000 : $num;
                    // Direct ID lookup — no other conditions needed
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
            // Only apply role/active filters when NOT doing an ID search
            ->when(!$isIdSearch && $request->role,
                fn ($q, $r) => $q->where('role', $r))
            ->when(!$isIdSearch && $request->has('active'),
                fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->latest();

        $users = $query->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function creditMissedReward(\Illuminate\Http\Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $date = $request->input('date');
        if (! $date) {
            return response()->json(['success' => false, 'message' => 'Date required'], 422);
        }

        $user      = \App\Models\User::findOrFail($id);
        $rewardKey = "diamond_reward_given:{$user->id}:{$date}";

        // Check not already credited via Redis or DB
        $alreadyCredited = \Illuminate\Support\Facades\Redis::exists($rewardKey)
            || \App\Models\CoinTransaction::where('user_id', $id)
                ->where('type', 'live_reward')
                ->whereDate('created_at', $date)
                ->exists();

        if ($alreadyCredited) {
            return response()->json(['success' => false, 'message' => 'Already credited for this date'], 409);
        }

        // Atomic claim
        try {
            $claimed = \Illuminate\Support\Facades\Redis::set($rewardKey, 1, 'EX', 172800, 'NX');
        } catch (\Exception $e) {
            $claimed = ! \Illuminate\Support\Facades\Redis::exists($rewardKey);
            if ($claimed) \Illuminate\Support\Facades\Redis::setex($rewardKey, 172800, 1);
        }

        if (! $claimed) {
            return response()->json(['success' => false, 'message' => 'Already credited'], 409);
        }

        $user->increment('diamond_balance', 5000);

        \App\Models\CoinTransaction::create([
            'user_id'      => $user->id,
            'type'         => 'live_reward',
            'amount'       => 5000,
            'balance_after'=> $user->fresh()->diamond_balance,
            'reference'    => "live_reward:manual:{$date}:admin:" . auth()->id(),
        ]);

        \Illuminate\Support\Facades\Log::info("Admin " . auth()->id() . " manually credited 5000 diamonds to user {$id} for {$date}");

        return response()->json([
            'success' => true,
            'message' => "5,000 credited to {$user->username} for {$date}",
        ]);
    }


    public function show(Request $request, int $id)
    {
        $user = User::withCount(['rooms', 'followers', 'following'])
            ->with(['bans.bannedBy:id,username'])
            ->findOrFail($id);

        // ── Date filter helpers ────────────────────────────────────────────
        $coinFrom    = $request->input('coin_from');
        $coinTo      = $request->input('coin_to');
        $diamondFrom = $request->input('diamond_from');
        $diamondTo   = $request->input('diamond_to');
        $liveFrom    = $request->input('live_from');
        $liveTo      = $request->input('live_to');

        $dateFilter = function ($query, $from, $to, $col = 'created_at') {
            if ($from) $query->whereDate($col, '>=', $from);
            if ($to)   $query->whereDate($col, '<=', $to);
        };

        // ── Coin Transactions ─────────────────────────────────────────────
        $coinRecharge = \App\Models\CoinTransaction::where('user_id', $id)
            ->whereIn('type', ['recharge', 'admin_adjustment', 'purchase', 'top_up'])
            ->when($coinFrom || $coinTo, fn($q) => $dateFilter($q, $coinFrom, $coinTo))
            ->latest()->paginate(20, ['*'], 'coin_recharge_page');

        $coinGifting = \App\Models\CoinTransaction::where('user_id', $id)
            ->whereIn('type', ['gift', 'gift_sent'])
            ->when($coinFrom || $coinTo, fn($q) => $dateFilter($q, $coinFrom, $coinTo))
            ->latest()->paginate(20, ['*'], 'coin_gift_page');

        // Match the exact type list the app's own TransactionController uses
        // (BaishunGameController::changeBalance writes 'game_bet'/'game_reward';
        // legacy/other paths may also write 'game', 'game_win', 'game_loss')
        $coinGames = \App\Models\CoinTransaction::where('user_id', $id)
            ->whereIn('type', ['game', 'game_win', 'game_loss', 'game_bet', 'game_reward'])
            ->when($coinFrom || $coinTo, fn($q) => $dateFilter($q, $coinFrom, $coinTo))
            ->latest()->paginate(20, ['*'], 'coin_game_page');

        // Coins RECEIVED from diamond→coin exchange (positive amount, type=exchange)
        $coinExchange = \App\Models\CoinTransaction::where('user_id', $id)
            ->where('type', 'exchange')
            ->where('amount', '>', 0) // coin credit side only, not the diamond debit
            ->when($coinFrom || $coinTo, fn($q) => $dateFilter($q, $coinFrom, $coinTo))
            ->latest()->paginate(20, ['*'], 'coin_exchange_page');

        // ── Diamond Transactions ──────────────────────────────────────────
        $diamondGifts = \App\Models\CoinTransaction::where('user_id', $id)
            ->whereIn('type', ['gift_received', 'gift'])
            ->where('amount', '>', 0)
            ->when($diamondFrom || $diamondTo, fn($q) => $dateFilter($q, $diamondFrom, $diamondTo))
            ->latest()->paginate(20, ['*'], 'diamond_gift_page');

        $diamondRewards = \App\Models\CoinTransaction::where('user_id', $id)
            ->where('type', 'live_reward')
            ->when($diamondFrom || $diamondTo, fn($q) => $dateFilter($q, $diamondFrom, $diamondTo))
            ->latest()->paginate(20, ['*'], 'diamond_reward_page');

        // Diamonds SPENT on exchange (negative amount, type=exchange)
        $diamondExchange = \App\Models\CoinTransaction::where('user_id', $id)
            ->where('type', 'exchange')
            ->where('amount', '<', 0) // diamond debit side only, not the coin credit
            ->when($diamondFrom || $diamondTo, fn($q) => $dateFilter($q, $diamondFrom, $diamondTo))
            ->latest()->paginate(20, ['*'], 'diamond_exchange_page');

        // ── Past Lives ────────────────────────────────────────────────────
        $pastLives = \App\Models\Room::where('host_user_id', $id)
            ->where('status', 'ended')
            ->when($liveFrom, fn($q) => $q->whereDate('started_at', '>=', $liveFrom))
            ->when($liveTo,   fn($q) => $q->whereDate('started_at', '<=', $liveTo))
            ->orderByDesc('started_at')
            ->paginate(20, ['*'], 'lives_page');

        // Fetch ALL live_reward transactions once — no N+1 queries
        $allRewards = \App\Models\CoinTransaction::where('user_id', $id)
            ->where('type', 'live_reward')
            ->get(['reference', 'created_at']);

        // Index by room_id and by date (include next day to handle midnight boundary)
        $rewardDates   = [];
        $rewardRoomIds = [];
        foreach ($allRewards as $t) {
            $txnDate = $t->created_at->toDateString();
            $rewardDates[$txnDate] = true;
            // Also mark previous day (stream started before midnight, reward given after)
            $rewardDates[$t->created_at->copy()->subDay()->toDateString()] = true;
            if (preg_match('/live_reward:(?:room|manual):([\w\-]+)/', $t->reference ?? '', $m)) {
                $rewardRoomIds[$m[1]] = true;
            }
        }

        // Also check Redis keys for current-day rewards not yet flushed
        $todayKey = "diamond_reward_given:{$id}:" . now()->toDateString();
        if (\Illuminate\Support\Facades\Redis::exists($todayKey)) {
            $rewardDates[now()->toDateString()] = true;
        }

        // Enrich each room — track which dates had button shown
        $rewardButtonShownDates = [];
        $pastLives->getCollection()->transform(function ($room) use (
            $rewardDates, $rewardRoomIds, &$rewardButtonShownDates
        ) {
            $startedAt    = $room->started_at ?? $room->created_at;
            $endedAt      = $room->ended_at   ?? $room->updated_at;
            $durationMins = $startedAt && $endedAt
                ? (int) $startedAt->diffInMinutes($endedAt) : 0;

            $liveDate = $startedAt?->toDateString();

            // Match by room ID (exact) OR by date (±1 day for midnight boundary)
            $rewardCollected = isset($rewardRoomIds[$room->id])
                || ($liveDate && isset($rewardDates[$liveDate]));

            $eligible         = $durationMins >= 40 && $room->type === 'video';
            $canShowButton    = $eligible && ! $rewardCollected
                && $liveDate && ! in_array($liveDate, $rewardButtonShownDates);

            if ($canShowButton) {
                $rewardButtonShownDates[] = $liveDate;
            }

            $room->duration_mins     = $durationMins;
            $room->reward_collected  = $rewardCollected;
            $room->reward_eligible   = $eligible;
            $room->show_credit_btn   = $canShowButton;
            $room->live_date         = $liveDate;
            return $room;
        });

        // ── Top gifts sent ────────────────────────────────────────────────
        $topGifts = \App\Models\GiftTransaction::where('sender_id', $id)
            ->selectRaw('gift_id, COUNT(*) as count, SUM(coin_total) as total')
            ->with('gift:id,name,thumbnail_url')
            ->groupBy('gift_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // ── DM Messages ──────────────────────────────────────────────────
        $convFilter = $request->input('dm_conv');

        // All conversations this user is part of (for filter dropdown)
        $dmConversations = \App\Models\DmConversation::where('user_one_id', $id)
            ->orWhere('user_two_id', $id)
            ->withCount('messages')
            ->with(['userOne:id,username', 'userTwo:id,username'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($conv) use ($id) {
                $conv->otherUser = $conv->user_one_id == $id
                    ? $conv->userTwo
                    : $conv->userOne;
                return $conv;
            });

        // Messages — filtered by conversation if selected
        $dmMessages = \App\Models\DmMessage::with([
                'sender:id,username,avatar_url',
                'conversation.userOne:id,username',
                'conversation.userTwo:id,username',
                'gift:id,name',
            ])
            ->whereHas('conversation', function ($q) use ($id) {
                $q->where('user_one_id', $id)->orWhere('user_two_id', $id);
            })
            ->when($convFilter, fn($q) => $q->where('conversation_id', $convFilter))
            ->orderByDesc('created_at')
            ->paginate(30, ['*'], 'dm_page');

        return view('admin.users.show', compact(
            'user', 'topGifts',
            'coinRecharge', 'coinGifting', 'coinGames', 'coinExchange',
            'diamondGifts', 'diamondRewards', 'diamondExchange',
            'pastLives',
            'dmMessages', 'dmConversations'
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
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user   = User::findOrFail($id);
        $amount = (int) $data['amount'];
        $reason = $data['reason'] ?? 'Admin adjustment';

        // Update balance
        if ($amount >= 0) {
            $user->increment('coin_balance', $amount);
        } else {
            $user->decrement('coin_balance', abs($amount));
        }

        $user->refresh();

        // Record transaction so it appears in recharge history
        \App\Models\CoinTransaction::create([
            'user_id'      => $user->id,
            'type'         => 'recharge',
            'amount'       => $amount,
            'balance_after'=> $user->coin_balance,
            'reference'    => "admin_adjustment:by:" . auth()->id() . ":{$reason}",
        ]);

        return back()->with('success', 'Coins adjusted. Transaction recorded.');
    }

    public function toggleActive(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "User account {$status}.");
    }
}