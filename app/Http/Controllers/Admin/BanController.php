<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBan;
use App\Services\BanService;
use Illuminate\Http\Request;

class BanController extends Controller
{
    public function __construct(private readonly BanService $banService)
    {
    }

    public function index(Request $request)
    {
        $query = UserBan::with(['user:id,username,avatar_url', 'bannedBy:id,username'])
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status === 'active', fn ($q) => $q->active())
            ->when($request->search, function ($q, $s) {
                $q->whereHas('user', fn ($qq) => $qq->where('username', 'like', "%{$s}%"));
            })
            ->latest();

        $bans = $query->paginate(25)->withQueryString();

        $stats = [
            'total_active'   => UserBan::active()->count(),
            'global_bans'    => UserBan::active()->where('type', 'global')->count(),
            'room_bans'      => UserBan::active()->where('type', 'room')->count(),
            'permanent_bans' => UserBan::active()->whereNull('expires_at')->count(),
        ];

        return view('admin.bans.index', compact('bans', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'  => ['required', 'exists:users,id'],
            'reason'   => ['required', 'string', 'max:500'],
            'type'     => ['required', 'in:global,room,chat,live'],
            'duration' => ['required', 'string'],
            'room_id'  => ['nullable', 'string'],
        ]);

        // Admin uses 'admin' guard — auth()->id() returns null, use auth('admin')->id()
        $adminId = auth('admin')->id() ?? 0;

        $this->banService->ban(
            targetUserId: $data['user_id'],
            adminId:      $adminId,
            reason:       $data['reason'],
            duration:     $data['duration'],
            type:         $data['type'],
            roomId:       $data['room_id'] ?? null,
        );

        return back()->with('success', 'User banned successfully.');
    }

    public function unban(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'type'    => ['nullable', 'string'],
        ]);

        $this->banService->unban($data['user_id'], $data['type'] ?? 'global');

        if ($request->expectsJson()) {
            return response()->json(['message' => 'User unbanned.']);
        }

        return back()->with('success', 'User unbanned successfully.');
    }

    public function history(int $userId)
    {
        $user = User::findOrFail($userId);
        $bans = $this->banService->getBanHistory($userId);

        return view('admin.bans.history', compact('user', 'bans'));
    }
}