<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $user = User::withCount(['followers', 'following', 'rooms'])
            ->findOrFail($id);

        return response()->json([
            ...$user->toProfileArray(),
            'followers_count' => $user->followers_count,
            'following_count' => $user->following_count,
            'rooms_count'     => $user->rooms_count,
            'is_following'    => auth()->user()->isFollowing($id),
        ]);
    }

    public function follow(int $id): JsonResponse
    {
        $user = auth()->user();

        if ($user->id === $id) {
            return response()->json(['message' => 'You cannot follow yourself.'], 422);
        }

        $user->following()->syncWithoutDetaching([$id]);

        return response()->json(['message' => 'Followed successfully.']);
    }

    public function unfollow(int $id): JsonResponse
    {
        auth()->user()->following()->detach($id);

        return response()->json(['message' => 'Unfollowed successfully.']);
    }

    public function rooms(int $id): JsonResponse
    {
        $rooms = Room::where('host_user_id', $id)
            ->where('status', 'ended')
            ->orderByDesc('started_at')
            ->paginate(20);

        return response()->json($rooms);
    }
}
