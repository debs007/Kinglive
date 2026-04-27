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

    /** Get current user's followers (for PK invite list) */
    public function myFollowers(): JsonResponse
    {
        $followers = auth()->user()
            ->followers()
            ->select('users.id', 'username', 'avatar_url', 'level')
            ->paginate(50);

        return response()->json($followers);
    }

    /** Get users I have banned (room bans placed by me as host) */
    public function myBannedUsers(): \Illuminate\Http\JsonResponse
    {
        $bans = \App\Models\UserBan::where('banned_by', auth()->id())
            ->where('type', 'room')
            ->where('is_active', true)
            ->with('user:id,username,display_name,avatar_url,level')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($b) => [
                'ban_id'       => $b->id,
                'user_id'      => $b->user_id,
                'username'     => $b->user->username     ?? '',
                'display_name' => $b->user->display_name ?? null,
                'avatar_url'   => $b->user->avatar_url   ?? null,
                'level'        => $b->user->level        ?? 1,
                'room_id'      => $b->room_id,
                'reason'       => $b->reason,
                'banned_at'    => $b->created_at?->toDateTimeString(),
            ]);

        return response()->json($bans);
    }

    /** Unban a user (remove room ban placed by me) */
    public function unbanUser(int $userId): \Illuminate\Http\JsonResponse
    {
        \App\Models\UserBan::where('banned_by', auth()->id())
            ->where('user_id', $userId)
            ->where('type', 'room')
            ->update(['is_active' => false]);

        return response()->json(['ok' => true]);
    }

    /** Get followers of any user */
    public function getUserFollowers(int $id): JsonResponse
    {
        $authUser  = auth()->user();
        $myFollowing = $authUser->following()->pluck('following_id')->toArray();

        $followers = User::find($id)
            ?->followers()
            ->select('users.id', 'username', 'display_name', 'avatar_url', 'level')
            ->get()
            ->map(fn ($u) => [
                'id'           => $u->id,
                'username'     => $u->username,
                'display_name' => $u->display_name,
                'avatar_url'   => $u->avatar_url,
                'level'        => $u->level,
                'is_following' => in_array($u->id, $myFollowing),
            ]);

        return response()->json($followers ?? []);
    }

    /** Get users that a user is following */
    public function getUserFollowing(int $id): JsonResponse
    {
        $authUser    = auth()->user();
        $myFollowing = $authUser->following()->pluck('following_id')->toArray();

        $following = User::find($id)
            ?->following()
            ->select('users.id', 'username', 'display_name', 'avatar_url', 'level')
            ->get()
            ->map(fn ($u) => [
                'id'           => $u->id,
                'username'     => $u->username,
                'display_name' => $u->display_name,
                'avatar_url'   => $u->avatar_url,
                'level'        => $u->level,
                'is_following' => in_array($u->id, $myFollowing),
            ]);

        return response()->json($following ?? []);
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
