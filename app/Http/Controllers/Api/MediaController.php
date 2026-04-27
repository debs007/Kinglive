<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\RoomBackground;
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

    // ── Room Backgrounds ──────────────────────────────────────────────────────

    /** List all active backgrounds (public) */
    public function listBackgrounds(): JsonResponse
    {
        $bgs = RoomBackground::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'image_url']);

        return response()->json($bgs);
    }

    /** Admin: generate presigned upload URL for a background image */
    public function backgroundUploadUrl(Request $request): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $name = Str::slug($request->input('name', Str::random(10)));
        $key  = "backgrounds/{$name}_" . Str::random(6) . ".jpg";
        return $this->generateUploadUrl($key, "image/jpeg");
    }

    /** Admin: save background record after upload */
    public function saveBackground(Request $request): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'image_url' => ['required', 'string'],
        ]);

        $bg = RoomBackground::create([
            'name'       => $request->name,
            'image_url'  => $request->image_url,
            'sort_order' => RoomBackground::max('sort_order') + 1,
        ]);

        return response()->json($bg, 201);
    }

    /** Admin: delete a background */
    public function deleteBackground(int $id): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        RoomBackground::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
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

        $bucket  = config('filesystems.disks.s3.bucket');
        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);

        $presigned = $client->createPresignedRequest($command, '+5 minutes');

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'file_url'   => Storage::disk('s3')->url($key),
            'key'        => $key,
        ]);
    }
}
