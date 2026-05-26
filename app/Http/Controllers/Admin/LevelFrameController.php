<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LevelFrame;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LevelFrameController extends Controller
{
    public function index()
    {
        $frames = LevelFrame::orderBy('min_level')->get();
        return view('admin.level_frames.index', compact('frames'));
    }

    public function uploadUrl(Request $request): JsonResponse
    {
        $ext  = $request->input('type', 'svga') === 'thumbnail' ? 'png' : 'svga';
        $key  = 'level_frames/lv' . $request->input('min_level', 1) . '_' . Str::random(8) . '.' . $ext;

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

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'file_url'   => Storage::disk('s3')->url($key),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'min_level'     => ['required', 'integer', 'min:1'],
            'max_level'     => ['nullable', 'integer'],
            'name'          => ['required', 'string', 'max:100'],
            'svga_url'      => ['required', 'string'],
            'thumbnail_url' => ['nullable', 'string'],
        ]);

        $frame = LevelFrame::create($request->only(
            'min_level', 'max_level', 'name', 'svga_url', 'thumbnail_url'
        ));

        return response()->json($frame, 201);
    }

    public function toggle(int $id)
    {
        $frame = LevelFrame::findOrFail($id);
        $frame->update(['is_active' => ! $frame->is_active]);
        return back()->with('success', 'Frame updated.');
    }

    public function destroy(int $id)
    {
        LevelFrame::findOrFail($id)->delete();
        return back()->with('success', 'Frame deleted.');
    }
}
