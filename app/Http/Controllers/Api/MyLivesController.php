<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyLivesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'video'); // video | audio
        $page = (int) $request->input('page', 1);
        $user = auth()->user();

        // Map tab type to DB types
        $dbTypes = match($type) {
            'audio' => ['audio', 'audio_board'],
            default => ['video'],
        };

        // This month only
        $startOfMonth = now()->startOfMonth();
        $endOfMonth   = now()->endOfMonth();

        $rooms = Room::where('host_user_id', $user->id)
            ->whereIn('type', $dbTypes)
            ->where('status', 'ended')
            ->whereBetween('started_at', [$startOfMonth, $endOfMonth])
            ->orderByDesc('started_at')
            ->paginate(20, ['*'], 'page', $page);

        $data = $rooms->map(function ($room) {
            $startedAt    = $room->started_at;
            $endedAt      = $room->ended_at ?? $room->updated_at;
            $durationMins = ($startedAt && $endedAt)
                ? (int) $startedAt->diffInMinutes($endedAt)
                : 0;

            return [
                'id'                   => $room->id,
                'title'                => $room->title ?? 'Live',
                'type'                 => $room->type,
                'started_at'           => $startedAt?->toIso8601String(),
                'duration_mins'        => $durationMins,
                'viewer_count'         => $room->viewer_count ?? 0,
                'total_gifts_received' => $room->total_gifts_received ?? 0,
            ];
        });

        // Monthly summary
        $allRooms = Room::where('host_user_id', $user->id)
            ->whereIn('type', $dbTypes)
            ->where('status', 'ended')
            ->whereBetween('started_at', [$startOfMonth, $endOfMonth])
            ->get(['started_at', 'ended_at', 'updated_at', 'total_gifts_received']);

        $totalMins     = 0;
        $totalDiamonds = 0;
        foreach ($allRooms as $r) {
            $s = $r->started_at;
            $e = $r->ended_at ?? $r->updated_at;
            if ($s && $e) $totalMins += (int) $s->diffInMinutes($e);
            $totalDiamonds += (int) ($r->total_gifts_received ?? 0);
        }

        return response()->json([
            'data'     => $data,
            'has_more' => $rooms->hasMorePages(),
            'summary'  => [
                'total_sessions' => $allRooms->count(),
                'total_minutes'  => $totalMins,
                'total_diamonds' => $totalDiamonds,
            ],
        ]);
    }
}
