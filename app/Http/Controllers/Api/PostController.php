<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /** GET /posts?page=N — paginated newsfeed, latest first, ALL posts */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $page   = max(1, (int) $request->input('page', 1));

        $posts = Post::with([
                'user:id,username,display_name,avatar_url,is_verified,level',
                'comments.user:id,username,avatar_url',
            ])
            ->orderByDesc('created_at')   // latest first, no date filter
            ->paginate(20, ['*'], 'page', $page);

        $data = $posts->map(fn (Post $post) => $this->formatPost($post, $userId));

        return response()->json([
            'data'         => $data,
            'current_page' => $posts->currentPage(),
            'last_page'    => $posts->lastPage(),
            'has_more'     => $posts->hasMorePages(),
        ]);
    }

    /** GET /posts/user/{userId} — user's posts */
    public function userPosts(string $userId): JsonResponse
    {
        $authId = auth()->id();
        $posts  = Post::with([
                'user:id,username,display_name,avatar_url,is_verified,level',
                'comments.user:id,username,avatar_url',
            ])
            ->where('user_id', $userId)
            ->latest()
            ->paginate(12);

        return response()->json([
            'data'     => $posts->map(fn ($p) => $this->formatPost($p, $authId)),
            'has_more' => $posts->hasMorePages(),
        ]);
    }

    /** POST /posts — create post */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'caption' => ['nullable', 'string', 'max:2000'],
            'media'   => ['nullable', 'array'],
            'media.*' => ['array'],
        ]);

        if (empty($request->caption) && empty($request->media)) {
            return response()->json(['message' => 'Post must have text or media.'], 422);
        }

        $post = Post::create([
            'user_id' => auth()->id(),
            'caption' => $request->caption,
            'media'   => $request->media ?? [],
        ]);

        $post->load('user:id,username,display_name,avatar_url,is_verified,level');

        return response()->json($this->formatPost($post, auth()->id()), 201);
    }

    /** DELETE /posts/{id} */
    public function destroy(int $id): JsonResponse
    {
        $post = Post::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $post->delete();
        return response()->json(['message' => 'Post deleted.']);
    }

    /** POST /posts/{id}/like */
    public function like(int $id): JsonResponse
    {
        $userId = auth()->id();
        $post   = Post::findOrFail($id);

        $existing = PostLike::where('user_id', $userId)
            ->where('post_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('likes_count');
            $liked = false;
        } else {
            PostLike::create(['user_id' => $userId, 'post_id' => $id]);
            $post->increment('likes_count');
            $liked = true;
        }

        return response()->json([
            'liked'       => $liked,
            'likes_count' => $post->fresh()->likes_count,
        ]);
    }

    /** POST /posts/{id}/comments */
    public function comment(Request $request, int $id): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:500']]);

        $post    = Post::findOrFail($id);
        $comment = PostComment::create([
            'user_id' => auth()->id(),
            'post_id' => $id,
            'body'    => $request->body,
        ]);

        $post->increment('comments_count');
        $comment->load('user:id,username,avatar_url');

        return response()->json($comment, 201);
    }

    /** GET /posts/{id}/comments */
    public function comments(int $id): JsonResponse
    {
        $comments = PostComment::where('post_id', $id)
            ->with('user:id,username,avatar_url')
            ->latest()
            ->paginate(20);

        return response()->json($comments);
    }

    /** POST /posts/media-upload-url — presigned URL for post image */
    public function mediaUploadUrl(Request $request): JsonResponse
    {
        $ext = $request->input('ext', 'jpg');
        $key = 'posts/' . Str::random(16) . '.' . $ext;

        $client = new S3Client([
            'version'     => 'latest',
            'region'      => config('filesystems.disks.s3.region'),
            'credentials' => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        $command   = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key'    => $key,
        ]);
        $presigned = $client->createPresignedRequest($command, '+5 minutes');

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'file_url'   => Storage::disk('s3')->url($key),
            'key'        => $key,
        ]);
    }

    private function formatPost(Post $post, int $userId): array
    {
        return [
            'id'             => $post->id,
            'caption'        => $post->caption,
            'media'          => $post->media ?? [],
            'likes_count'    => $post->likes_count,
            'comments_count' => $post->comments_count,
            'is_liked'       => $post->isLikedBy($userId),
            'created_at'     => $post->created_at->diffForHumans(),
            'user'           => $post->user ? [
                'id'           => $post->user->id,
                'username'     => $post->user->username,
                'display_name' => $post->user->display_name,
                'avatar_url'   => $post->user->avatar_url,
                'is_verified'  => $post->user->is_verified,
                'level'        => $post->user->level,
            ] : null,
            'preview_comments' => $post->comments->map(fn ($c) => [
                'id'       => $c->id,
                'body'     => $c->body,
                'username' => $c->user?->username,
                'avatar'   => $c->user?->avatar_url,
            ])->toArray(),
        ];
    }
}