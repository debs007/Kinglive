<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftTransaction;
use App\Models\Room;
use App\Models\User;
use App\Models\UserBan;
use App\Models\GameSession;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    public function loginForm()
    {
        if (auth()->check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! auth()->attempt($credentials)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        if (! in_array(auth()->user()->role, ['admin', 'super_admin', 'moderator'])) {
            auth()->logout();
            return back()->withErrors(['email' => 'Access denied.']);
        }

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    public function index()
    {
        $today     = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        $stats = [
            'total_users'        => User::count(),
            'active_rooms'       => Room::where('status', 'live')->count(),
            'gifts_today'        => GiftTransaction::where('created_at', '>=', $today)->count(),
            'revenue_today'      => GiftTransaction::where('created_at', '>=', $today)->sum('coin_total'),
            'users_today'        => User::where('created_at', '>=', $today)->count(),
            'pending_withdrawals' => WithdrawalRequest::where('status', 'pending')->count(),
        ];

        $liveRooms = Room::with('host:id,username,avatar_url')
            ->where('status', 'live')
            ->orderByDesc('viewer_count')
            ->limit(20)
            ->get();

        $recentBans = UserBan::with(['user:id,username,avatar_url', 'bannedBy:id,username'])
            ->where('is_active', true)
            ->latest()
            ->limit(8)
            ->get();

        // 7-day chart
        $labels      = [];
        $giftRevenue = [];
        $newUsers    = [];

        for ($i = 6; $i >= 0; $i--) {
            $date          = now()->subDays($i);
            $labels[]      = $date->format('M d');
            $giftRevenue[] = GiftTransaction::whereDate('created_at', $date)->sum('coin_total');
            $newUsers[]    = User::whereDate('created_at', $date)->count();
        }

        $chartData = compact('labels', 'giftRevenue', 'newUsers');

        $gameReports = GameSession::where('status', 'completed')
            ->whereDate('created_at', today())
            ->selectRaw('game_id, COUNT(*) as sessions, COUNT(DISTINCT user_id) as unique_players, SUM(coins_spent) as coins_spent, SUM(coins_won) as coins_won, SUM(coins_spent-coins_won) as net')
            ->groupBy('game_id')
            ->orderByDesc('net')
            ->get();

        $gameList = \App\Models\Game::active()->get(['id', 'name', 'game_id']);

        return view('admin.dashboard', compact(
            'stats', 'liveRooms', 'recentBans', 'chartData', 'gameReports', 'gameList'
        ));
    }

    public function liveRoomsJson(): JsonResponse
    {
        $rooms = Room::with('host:id,username,avatar_url')
            ->where('status', 'live')
            ->orderByDesc('viewer_count')
            ->limit(20)
            ->get(['id', 'title', 'type', 'viewer_count', 'total_gifts_received', 'started_at', 'host_user_id']);

        return response()->json(['count' => $rooms->count(), 'rooms' => $rooms]);
    }
}
