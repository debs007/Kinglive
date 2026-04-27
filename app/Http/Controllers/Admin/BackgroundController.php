<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomBackground;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackgroundController extends Controller
{
    public function index()
    {
        $backgrounds = RoomBackground::orderBy('sort_order')->get();
        return view('admin.backgrounds.index', compact('backgrounds'));
    }

    public function uploadUrl(Request $request): JsonResponse
    {
        $name = Str::slug($request->input('name', Str::random(10)));
        $key  = "backgrounds/{$name}_" . Str::random(8) . ".jpg";

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
            'key'        => $key,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'image_url' => ['required', 'string'],
        ]);

        $bg = RoomBackground::create([
            'name'       => $request->name,
            'image_url'  => $request->image_url,
            'is_active'  => true,
            'sort_order' => (RoomBackground::max('sort_order') ?? 0) + 1,
        ]);

        return response()->json($bg, 201);
    }

    public function toggle(int $id)
    {
        $bg = RoomBackground::findOrFail($id);
        $bg->update(['is_active' => ! $bg->is_active]);
        return back()->with('success', 'Background ' . ($bg->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(int $id)
    {
        RoomBackground::findOrFail($id)->delete();
        return back()->with('success', 'Background deleted.');
    }
}