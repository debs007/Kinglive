<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PopupBanner;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PopupBannerController extends Controller
{
    public function index()
    {
        $banners = PopupBanner::orderByDesc('created_at')->get();
        return view('admin.popup_banners.index', compact('banners'));
    }

    public function uploadUrl(): JsonResponse
    {
        $key = 'popup_banners/' . Str::random(12) . '.jpg'; // extension doesn't matter for S3

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
        $presigned = $client->createPresignedRequest($command, '+15 minutes');

        // Use CDN URL if configured
        $cdnBase = config('filesystems.disks.s3.cdn_url');
        $fileUrl  = $cdnBase
            ? rtrim($cdnBase, '/') . '/' . $key
            : Storage::disk('s3')->url($key);

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'file_url'   => $fileUrl,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image_url'    => ['required', 'string'],
            'title'        => ['nullable', 'string', 'max:100'],
            'action_url'   => ['nullable', 'string', 'max:500'],
            'action_label' => ['nullable', 'string', 'max:50'],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date'],
        ]);

        $banner = PopupBanner::create([
            'image_url'    => $request->image_url,
            'title'        => $request->title,
            'action_url'   => $request->action_url,
            'action_label' => $request->action_label,
            'starts_at'    => $request->starts_at,
            'ends_at'      => $request->ends_at,
            'is_active'    => false,
        ]);

        return response()->json($banner, 201);
    }

    public function toggle(int $id)
    {
        $banner = PopupBanner::findOrFail($id);

        // Multiple banners can be active at the same time
        // They are shown one by one in the app on launch
        $banner->update(['is_active' => ! $banner->is_active]);
        return back()->with('success', $banner->is_active
            ? 'Popup banner is now LIVE.'
            : 'Popup banner deactivated.');
    }

    public function destroy(int $id)
    {
        PopupBanner::findOrFail($id)->delete();
        return back()->with('success', 'Banner deleted.');
    }
}