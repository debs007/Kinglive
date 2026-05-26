<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Frame;
use App\Models\User;
use App\Models\UserFrame;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FrameController extends Controller
{
    // ── Frame list page ────────────────────────────────────────────────────────

    public function index()
    {
        $frames = Frame::orderBy('sort_order')->get();
        return view('admin.frames.index', compact('frames'));
    }

    // ── Generate S3 presigned upload URL (same as BackgroundController) ────────

    public function uploadUrl(Request $request): JsonResponse
    {
        $name = Str::slug($request->input('name', Str::random(10)));
        $ext  = $request->input('type', 'svga') === 'thumbnail' ? 'png' : 'svga';
        $key  = "frames/{$name}_" . Str::random(8) . ".{$ext}";

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

    // ── Save frame after upload ────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'svga_url'      => ['required', 'string'],
            'thumbnail_url' => ['nullable', 'string'],
            'price'         => ['nullable', 'integer', 'min:0'],
        ]);

        $frame = Frame::create([
            'name'          => $request->name,
            'svga_url'      => $request->svga_url,
            'thumbnail_url' => $request->thumbnail_url,
            'price'         => $request->price ?? 0,
            'is_active'     => true,
            'sort_order'    => (Frame::max('sort_order') ?? 0) + 1,
        ]);

        return response()->json($frame, 201);
    }

    // ── Toggle active/inactive ─────────────────────────────────────────────────

    public function toggle(int $id)
    {
        $frame = Frame::findOrFail($id);
        $frame->update(['is_active' => ! $frame->is_active]);
        return back()->with('success', 'Frame ' . ($frame->is_active ? 'activated' : 'deactivated') . '.');
    }

    // ── Delete frame ───────────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        Frame::findOrFail($id)->delete();
        return back()->with('success', 'Frame deleted.');
    }

    // ── Give frame to user ─────────────────────────────────────────────────────

    public function giveToUser(Request $request, int $userId): JsonResponse
    {
        $request->validate(['frame_id' => ['required', 'exists:frames,id']]);

        $user  = User::findOrFail($userId);
        $frame = Frame::findOrFail($request->frame_id);

        // Idempotent — ignore if already owned
        UserFrame::firstOrCreate(
            ['user_id' => $userId, 'frame_id' => $frame->id],
            ['source'  => 'admin']
        );

        return response()->json([
            'message' => "Frame \"{$frame->name}\" given to {$user->username}.",
        ]);
    }

    // ── Remove frame from user ─────────────────────────────────────────────────

    public function removeFromUser(Request $request, int $userId): JsonResponse
    {
        $request->validate(['frame_id' => ['required', 'exists:frames,id']]);

        UserFrame::where('user_id', $userId)
            ->where('frame_id', $request->frame_id)
            ->delete();

        // If user currently has this frame applied, clear it
        $frame = Frame::findOrFail($request->frame_id);
        $user  = User::findOrFail($userId);
        if ($user->frame_url === $frame->svga_url) {
            $user->update(['frame_url' => null]);
        }

        return response()->json(['message' => 'Frame removed from user.']);
    }

    // ── List user's frames (for admin user detail page) ────────────────────────

    public function userFrames(int $userId): JsonResponse
    {
        $frames = UserFrame::where('user_id', $userId)
            ->with('frame')
            ->get()
            ->map(fn ($uf) => [
                'id'            => $uf->frame->id,
                'name'          => $uf->frame->name,
                'svga_url'      => $uf->frame->svga_url,
                'thumbnail_url' => $uf->frame->thumbnail_url,
                'source'        => $uf->source,
            ]);

        return response()->json($frames);
    }
}
