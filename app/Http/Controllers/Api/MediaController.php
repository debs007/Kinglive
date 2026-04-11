<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Aws\S3\S3Client;

class MediaController extends Controller
{
    /**
     * Generate upload URL for avatar
     */
    public function avatarUploadUrl(Request $request): JsonResponse
    {
        $user = auth()->user();

        $key = "avatars/user_{$user->id}_" . Str::random(8) . ".jpg";

        return $this->generateUploadUrl($key, "image/jpeg");
    }

    /**
     * Generate upload URL for cover
     */
    public function coverUploadUrl(Request $request): JsonResponse
    {
        $user = auth()->user();

        $key = "covers/user_{$user->id}_" . Str::random(8) . ".jpg";

        return $this->generateUploadUrl($key, "image/jpeg");
    }

    /**
     * Generate upload URL for room thumbnail
     */
    public function roomThumbnailUploadUrl(): JsonResponse
    {
        $key = "thumbnails/room_" . Str::random(12) . ".jpg";

        return $this->generateUploadUrl($key, "image/jpeg");
    }

    /**
     * Generate upload URL for SVGA gift file (admin only)
     */
    public function giftSvgaUploadUrl(Request $request): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $name = Str::slug($request->input('name', Str::random(10)));

        $key = "gifts/svga/{$name}.svga";

        return $this->generateUploadUrl($key, "application/octet-stream");
    }

    /**
     * Generate pre-signed upload URL
     */
    private function generateUploadUrl(string $key, string $contentType)
{
    $client = new S3Client([
        'version' => 'latest',
        'region'  => config('filesystems.disks.s3.region'),
        'credentials' => [
            'key'    => config('filesystems.disks.s3.key'),
            'secret' => config('filesystems.disks.s3.secret'),
        ],
    ]);

    $bucket = config('filesystems.disks.s3.bucket');

    $command = $client->getCommand('PutObject', [
        'Bucket' => $bucket,
        'Key' => $key,
        
        
    ]);

    $request = $client->createPresignedRequest($command, '+5 minutes');

    return response()->json([
        'upload_url' => (string) $request->getUri(),
        'file_url'   => Storage::disk('s3')->url($key),
        'key'        => $key
    ]);
}
}