<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reel;
use App\Models\ReelComment;
use App\Models\ReelLike;
use App\Models\ReelView;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReelController extends Controller
{
    private const DIAMONDS_PER_VIEW = 5;

    // ── 1. Feed — randomised, no repeats per session, latest-weighted ───────
    //
    // How it works:
    //  • Client generates a session_token (UUID) on first open of reels page
    //  • Server stores seen reel IDs in Redis under that token (TTL 2h)
    //  • Each request: fetch a pool of unseen reels, weighted toward latest,
    //    shuffle within weight bands, return 10, mark as seen
    //  • When all reels seen → clear the session so they start fresh
    //  • Client resets token when user navigates away and comes back

    public function feed(Request $request): JsonResponse
    {
        $authId  = auth()->id();
        $token   = $request->input('session_token'); // UUID from client
        $perPage = 10;

        // If no session token, just return latest
        if (! $token) {
            $reels = Reel::with('user:id,username,display_name,avatar_url,is_verified')
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->limit($perPage)
                ->get();

            return response()->json([
                'data'     => $reels->map(fn ($r) => $r->toFeedArray($authId)),
                'has_more' => true,
            ]);
        }

        $seenKey = "reels:seen:{$token}";

        // Get already-seen IDs from Redis
        $seenIds = \Illuminate\Support\Facades\Redis::smembers($seenKey);
        $seenIds = array_map('intval', $seenIds);

        // Total available reels
        $totalReels = Reel::where('is_active', true)->count();

        // If all seen, clear session so they start fresh
        if (count($seenIds) >= $totalReels) {
            \Illuminate\Support\Facades\Redis::del($seenKey);
            $seenIds = [];
        }

        // ── Weighted pool strategy ────────────────────────────────────────
        // Split reels into 3 bands by age, pick from each proportionally:
        //   60% from last 7 days (newest)
        //   25% from last 30 days
        //   15% from older
        // Within each band, shuffle randomly for variety

        $now          = now();
        $sevenDaysAgo  = $now->copy()->subDays(7);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $base = Reel::with('user:id,username,display_name,avatar_url,is_verified')
            ->where('is_active', true)
            ->when(! empty($seenIds), fn ($q) => $q->whereNotIn('id', $seenIds));

        // Band A: last 7 days — 6 reels
        $bandA = (clone $base)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        // Band B: 8–30 days — 3 reels (exclude already picked)
        $pickedIds = $bandA->pluck('id')->toArray();
        $bandB = (clone $base)
            ->where('created_at', '<', $sevenDaysAgo)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereNotIn('id', $pickedIds)
            ->inRandomOrder()
            ->limit(3)
            ->get();

        // Band C: older — 1 reel (fill rest)
        $pickedIds = array_merge($pickedIds, $bandB->pluck('id')->toArray());
        $bandC = (clone $base)
            ->where('created_at', '<', $thirtyDaysAgo)
            ->whereNotIn('id', $pickedIds)
            ->inRandomOrder()
            ->limit(1)
            ->get();

        $batch = $bandA->concat($bandB)->concat($bandC)->shuffle();

        // If bands didn't fill 10, top up from any unseen
        if ($batch->count() < $perPage) {
            $allPicked = $batch->pluck('id')->toArray();
            $topUp = Reel::with('user:id,username,display_name,avatar_url,is_verified')
                ->where('is_active', true)
                ->when(! empty($seenIds), fn ($q) => $q->whereNotIn('id', $seenIds))
                ->whereNotIn('id', $allPicked)
                ->inRandomOrder()
                ->limit($perPage - $batch->count())
                ->get();
            $batch = $batch->concat($topUp)->shuffle();
        }

        // Mark these as seen in Redis (TTL 2 hours — resets when user leaves page)
        if ($batch->isNotEmpty()) {
            $newIds = $batch->pluck('id')->toArray();
            \Illuminate\Support\Facades\Redis::sadd($seenKey, ...$newIds);
            \Illuminate\Support\Facades\Redis::expire($seenKey, 7200); // 2 hours
        }

        $seenAfter = count($seenIds) + $batch->count();
        $hasMore   = $seenAfter < $totalReels;

        return response()->json([
            'data'     => $batch->map(fn ($r) => $r->toFeedArray($authId)),
            'has_more' => $hasMore,
        ]);
    }

    // ── 2. Get presigned upload URL for video ─────────────────────────────────

    public function uploadUrl(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $ext    = $request->input('ext', 'mp4');
        $key    = "reels/user_{$userId}_" . Str::random(16) . ".{$ext}";

        $client = new S3Client([
            'version'     => 'latest',
            'region'      => config('filesystems.disks.s3.region'),
            'credentials' => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        $cmd       = $client->getCommand('PutObject', [
            'Bucket'      => config('filesystems.disks.s3.bucket'),
            'Key'         => $key,
            'ContentType' => "video/{$ext}",
        ]);
        $presigned = $client->createPresignedRequest($cmd, '+30 minutes');

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'video_url'  => Storage::disk('s3')->url($key),
            'key'        => $key,
        ]);
    }

    // ── 3. Get presigned upload URL for thumbnail ─────────────────────────────

    public function thumbnailUploadUrl(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $key    = "reels/thumbs/user_{$userId}_" . Str::random(12) . ".jpg";

        $client = new S3Client([
            'version'     => 'latest',
            'region'      => config('filesystems.disks.s3.region'),
            'credentials' => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        $cmd       = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key'    => $key,
        ]);
        $presigned = $client->createPresignedRequest($cmd, '+15 minutes');

        return response()->json([
            'upload_url'    => (string) $presigned->getUri(),
            'thumbnail_url' => Storage::disk('s3')->url($key),
        ]);
    }

    // ── 4. Create reel after upload ───────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'video_url'     => ['required', 'string'],
            'thumbnail_url' => ['nullable', 'string'],
            'title'         => ['nullable', 'string', 'max:100'],
            'description'   => ['nullable', 'string', 'max:500'],
            'music_title'   => ['nullable', 'string', 'max:100'],
        ]);

        $reel = Reel::create([
            'user_id'       => auth()->id(),
            'video_url'     => $request->video_url,
            'thumbnail_url' => $request->thumbnail_url,
            'title'         => $request->title,
            'description'   => $request->description,
            'music_title'   => $request->music_title,
        ]);

        return response()->json($reel->toFeedArray(auth()->id()), 201);
    }

    // ── 5. Record a view (min 5s enforced on client) ──────────────────────────

    public function view(Reel $reel): JsonResponse
    {
        $userId = auth()->id();

        // One view per user per reel
        $alreadyViewed = ReelView::where('reel_id', $reel->id)
            ->where('user_id', $userId)
            ->exists();

        if (! $alreadyViewed) {
            DB::transaction(function () use ($reel, $userId) {
                ReelView::create(['reel_id' => $reel->id, 'user_id' => $userId]);
                $reel->increment('view_count');

                // Give reel creator 5 diamonds per view
                if ($reel->user_id !== $userId) {
                    \App\Models\User::where('id', $reel->user_id)
                        ->increment('diamond_balance', self::DIAMONDS_PER_VIEW);
                    $reel->increment('diamond_earned', self::DIAMONDS_PER_VIEW);
                }
            });
        }

        return response()->json(['view_count' => $reel->fresh()->view_count]);
    }

    // ── 6. Toggle like ────────────────────────────────────────────────────────

    public function like(Reel $reel): JsonResponse
    {
        $userId  = auth()->id();
        $existed = ReelLike::where('reel_id', $reel->id)
            ->where('user_id', $userId)
            ->first();

        if ($existed) {
            $existed->delete();
            $reel->decrement('like_count');
            $liked = false;
        } else {
            ReelLike::create(['reel_id' => $reel->id, 'user_id' => $userId]);
            $reel->increment('like_count');
            $liked = true;
        }

        return response()->json([
            'liked'      => $liked,
            'like_count' => $reel->fresh()->like_count,
        ]);
    }

    // ── 7. Get comments ───────────────────────────────────────────────────────

    public function comments(Reel $reel): JsonResponse
    {
        $comments = $reel->comments()
            ->with('user:id,username,display_name,avatar_url')
            ->paginate(20);

        return response()->json([
            'data' => $comments->map(fn ($c) => [
                'id'         => $c->id,
                'body'       => $c->body,
                'created_at' => $c->created_at?->toIso8601String(),
                'user'       => [
                    'id'         => $c->user->id,
                    'username'   => $c->user->display_name ?? $c->user->username,
                    'avatar_url' => $c->user->avatar_url,
                ],
            ]),
            'has_more' => $comments->hasMorePages(),
        ]);
    }

    // ── 8. Add comment ────────────────────────────────────────────────────────

    public function comment(Request $request, Reel $reel): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:500']]);

        $comment = ReelComment::create([
            'reel_id' => $reel->id,
            'user_id' => auth()->id(),
            'body'    => $request->body,
        ]);
        $comment->load('user:id,username,display_name,avatar_url');
        $reel->increment('comment_count');

        return response()->json([
            'id'         => $comment->id,
            'body'       => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
            'user'       => [
                'id'         => $comment->user->id,
                'username'   => $comment->user->display_name ?? $comment->user->username,
                'avatar_url' => $comment->user->avatar_url,
            ],
        ], 201);
    }

    // ── 9. Delete my reel ─────────────────────────────────────────────────────

    public function destroy(Reel $reel): JsonResponse
    {
        if ($reel->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $reel->delete();
        return response()->json(['message' => 'Reel deleted.']);
    }

    // ── 10. My reels with stats ───────────────────────────────────────────────

    public function myReels(): JsonResponse
    {
        $authId = auth()->id();
        $reels  = Reel::where('user_id', $authId)
            ->orderByDesc('created_at')
            ->paginate(12);

        return response()->json([
            'data' => $reels->map(fn ($r) => array_merge(
                $r->toFeedArray($authId),
                ['diamond_earned' => $r->diamond_earned]
            )),
            'has_more' => $reels->hasMorePages(),
        ]);
    }

    // ── 11. User reels (public profile) ──────────────────────────────────────

    public function userReels(int $userId): JsonResponse
    {
        $authId = auth()->id();
        $reels  = Reel::where('user_id', $userId)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->paginate(12);

        return response()->json([
            'data'     => $reels->map(fn ($r) => $r->toFeedArray($authId)),
            'has_more' => $reels->hasMorePages(),
        ]);
    }
}