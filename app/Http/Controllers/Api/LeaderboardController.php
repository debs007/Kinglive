<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\GiftTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * GET /leaderboard?type=gifting|agency|income&period=daily|weekly|monthly
     */
    public function index(Request $request): JsonResponse
    {
        $type   = $request->get('type',   'gifting');
        $period = $request->get('period', 'daily');

        $from = match ($period) {
            'weekly'  => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            default   => now()->startOfDay(),
        };

        $data = match ($type) {
            'agency' => $this->agencyLeaderboard($from),
            'income' => $this->incomeLeaderboard($from),
            default  => $this->giftingLeaderboard($from),
        };

        return response()->json($data);
    }

    /** Top gifters (by coins spent on gifts) */
    private function giftingLeaderboard($from): array
    {
        $rows = GiftTransaction::select(
                'sender_id as user_id',
                DB::raw('SUM(coin_total) as total_coins'),
                DB::raw('SUM(diamond_total) as total_diamonds'),
                DB::raw('COUNT(*) as gift_count'),
            )
            ->where('created_at', '>=', $from)
            ->groupBy('sender_id')
            ->orderByDesc('total_coins')
            ->limit(50)
            ->get();

        return $this->attachUserInfo($rows, 'total_coins');
    }

    /** Top earners (by diamonds received) */
    private function incomeLeaderboard($from): array
    {
        $rows = GiftTransaction::select(
                'receiver_id as user_id',
                DB::raw('SUM(diamond_total) as total_diamonds'),
                DB::raw('SUM(coin_total) as total_coins'),
                DB::raw('COUNT(*) as gift_count'),
            )
            ->where('created_at', '>=', $from)
            ->groupBy('receiver_id')
            ->orderByDesc('total_diamonds')
            ->limit(50)
            ->get();

        return $this->attachUserInfo($rows, 'total_diamonds');
    }

    /** Top agencies (sum of diamonds earned by all members) */
    private function agencyLeaderboard($from): array
    {
        $rows = GiftTransaction::select(
                'users.agency_id',
                DB::raw('SUM(gift_transactions.diamond_total) as total_diamonds'),
                DB::raw('COUNT(DISTINCT gift_transactions.receiver_id) as member_count'),
            )
            ->join('users', 'users.id', '=', 'gift_transactions.receiver_id')
            ->whereNotNull('users.agency_id')
            ->where('gift_transactions.created_at', '>=', $from)
            ->groupBy('users.agency_id')
            ->orderByDesc('total_diamonds')
            ->limit(50)
            ->get();

        $agencyIds = $rows->pluck('agency_id')->toArray();
        $agencies  = Agency::whereIn('id', $agencyIds)
            ->get(['id', 'name', 'code', 'logo_url'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($agencies) {
            $agency = $agencies[$row->agency_id] ?? null;
            return [
                'agency_id'      => $row->agency_id,
                'name'           => $agency?->name ?? 'Unknown Agency',
                'code'           => $agency?->code ?? '',
                'logo_url'       => $agency?->logo_url,
                'total_diamonds' => (int) $row->total_diamonds,
                'member_count'   => (int) $row->member_count,
            ];
        })->values()->toArray();
    }

    private function attachUserInfo($rows, string $scoreKey): array
    {
        $userIds = $rows->pluck('user_id')->toArray();
        $users   = User::whereIn('id', $userIds)
            ->get(['id', 'username', 'display_name', 'avatar_url', 'frame_url', 'level', 'agency_id'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($users, $scoreKey) {
            $user = $users[$row->user_id] ?? null;
            return [
                'user_id'        => $row->user_id,
                'username'       => $user?->username ?? 'Unknown',
                'display_name'   => $user?->display_name ?? $user?->username ?? 'Unknown',
                'avatar_url'     => $user?->avatar_url,
                'frame_url'      => $user?->frame_url,
                'level'          => $user?->level ?? 1,
                'agency_id'      => $user?->agency_id,
                'score'          => (int) $row->$scoreKey,
                'total_diamonds' => (int) ($row->total_diamonds ?? 0),
                'total_coins'    => (int) ($row->total_coins ?? 0),
                'gift_count'     => (int) ($row->gift_count ?? 0),
            ];
        })->values()->toArray();
    }
}