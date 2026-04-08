<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftTransaction;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $query = Room::with('host:id,username,avatar_url')
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('started_at');

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'live');
        }

        $rooms     = $query->paginate(30)->withQueryString();
        $liveCount = Room::where('status', 'live')->count();

        return view('admin.rooms.index', compact('rooms', 'liveCount'));
    }

    public function show(string $id)
    {
        $room = Room::with([
            'host:id,username,avatar_url,level,is_verified',
            'seats.user:id,username,avatar_url',
            'giftTransactions' => fn ($q) => $q->with('sender:id,username', 'gift:id,name')->latest()->limit(20),
        ])->findOrFail($id);

        $topGifters = GiftTransaction::where('room_id', $id)
            ->selectRaw('sender_id, SUM(coin_total) as total')
            ->with('sender:id,username,avatar_url')
            ->groupBy('sender_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('admin.rooms.show', compact('room', 'topGifters'));
    }

    public function endRoom(string $id)
    {
        $room = Room::findOrFail($id);
        $room->update(['status' => 'ended', 'ended_at' => now()]);

        Redis::publish('admin:end_room', json_encode([
            'room_id' => $id,
            'reason'  => 'Ended by admin',
        ]));

        return back()->with('success', "Stream \"{$room->title}\" ended.");
    }
}
