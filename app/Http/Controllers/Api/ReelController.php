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

    // ── 1. Feed — most viewed first ───────────────────────────────────────────

    public function feed(Request $request): JsonResponse
    {
        $authId = auth()->id();

        $reels = Reel::with('user:id,username,display_name,avatar_url,is_verified')
            ->where('is_active', true)
            ->orderByDesc('view_count')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'data'     => $reels->map(fn ($r) => $r->toFeedArray($authId)),
            'has_more' => $reels->hasMorePages(),
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
