<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\PartyVideo;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartyVideoController extends Controller
{
    private const UPLOAD_COST = 10000;
    private const PLAY_COST   = 5000;

    // ── Get presigned S3 upload URL ───────────────────────────────────────────

    public function uploadUrl(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->coin_balance < self::UPLOAD_COST) {
            return response()->json([
                'message' => 'You need ' . number_format(self::UPLOAD_COST) . ' coins to upload a video.',
            ], 422);
        }

        $ext = $request->input('ext', 'mp4');
        $key = "party_videos/user_{$user->id}_" . Str::random(16) . ".{$ext}";

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
        $videoUrl  = config('filesystems.disks.s3.url') . '/' . $key;

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'video_url'  => $videoUrl,
        ]);
    }

    // ── Confirm upload & deduct coins ─────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validate([
            'video_url'        => ['required', 'url'],
            'thumbnail_url'    => ['nullable', 'url'],
            'title'            => ['nullable', 'string', 'max:100'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'file_size'        => ['nullable', 'integer', 'min:0'],
        ]);

            // Refresh user to get latest balance (important in Octane)
        $user = $user->fresh();

        if ($user->coin_balance < self::UPLOAD_COST) {
            return response()->json([
                'message'  => 'Insufficient coins.',
                'balance'  => $user->coin_balance,
                'required' => self::UPLOAD_COST,
            ], 422);
        }

        $video = DB::transaction(function () use ($user, $data) {
            $user->decrement('coin_balance', self::UPLOAD_COST);
            $newBalance = $user->fresh()->coin_balance;

            CoinTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'party_video_upload',
                'amount'        => -self::UPLOAD_COST,
                'balance_after' => $newBalance,
                'reference'     => 'Party video upload fee',
            ]);

            return PartyVideo::create([
                'user_id'          => $user->id,
                'title'            => $data['title'] ?? 'Party Video',
                'video_url'        => $data['video_url'],
                'thumbnail_url'    => $data['thumbnail_url'] ?? null,
                'duration_seconds' => $data['duration_seconds'] ?? 0,
                'file_size'        => $data['file_size'] ?? 0,
            ]);
        });

        return response()->json([
            'video'        => $this->format($video),
            'coin_balance' => $user->fresh()->coin_balance,
        ], 201);
    }

    // ── List my videos ────────────────────────────────────────────────────────

    public function myVideos(): JsonResponse
    {
        $user   = auth()->user()->fresh();
        $videos = PartyVideo::where('user_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->get();

        return response()->json([
            'videos'       => $videos->map(fn ($v) => $this->format($v)),
            'coin_balance' => $user->coin_balance,
        ]);
    }

    // ── Record play & deduct coins ────────────────────────────────────────────

    public function recordPlay(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $videoId = $request->input('video_id');
        $video   = PartyVideo::where('user_id', $user->id)->findOrFail($videoId);

        if ($user->coin_balance < self::PLAY_COST) {
            return response()->json([
                'message' => 'You need ' . number_format(self::PLAY_COST) . ' coins to play.',
            ], 422);
        }

        DB::transaction(function () use ($user, $video) {
            $user->decrement('coin_balance', self::PLAY_COST);
            $newBalance = $user->fresh()->coin_balance;

            CoinTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'party_video_play',
                'amount'        => -self::PLAY_COST,
                'balance_after' => $newBalance,
                'reference'     => "party_video:{$video->id}",
            ]);
        });

        return response()->json([
            'success'      => true,
            'coin_balance' => $user->fresh()->coin_balance,
        ]);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        PartyVideo::where('user_id', auth()->id())->findOrFail($id)
            ->update(['is_active' => false]);
        return response()->json(['success' => true]);
    }

    private function format(PartyVideo $v): array
    {
        return [
            'id'               => $v->id,
            'title'            => $v->title ?? 'Untitled',
            'video_url'        => $v->video_url,
            'thumbnail_url'    => $v->thumbnail_url,
            'duration_seconds' => $v->duration_seconds,
            'created_at'       => $v->created_at->diffForHumans(),
        ];
    }
}