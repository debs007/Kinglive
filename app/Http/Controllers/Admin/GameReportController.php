<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameReportController extends Controller
{
    public function report(Request $request)
    {
        $from   = $request->input('from', now()->startOfMonth()->toDateString());
        $to     = $request->input('to',   now()->endOfDay()->toDateString());
        $gameId = $request->input('game_id');

        $base = CoinTransaction::whereIn('type', ['game_bet', 'game_reward'])
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->when($gameId, fn ($q) => $q->where('reference', 'like', "%game:{$gameId}:%"));

        // Summary totals
        $summary = [
            'total_sessions'    => (clone $base)->count(),
            'total_coins_spent' => (clone $base)->where('type', 'game_bet')->sum(DB::raw('ABS(amount)')),
            'total_coins_won'   => (clone $base)->where('type', 'game_reward')->sum('amount'),
            'house_profit'      => 0,
        ];
        $summary['house_profit'] = $summary['total_coins_spent'] - $summary['total_coins_won'];

        // Per-game breakdown using PHP to parse reference field
        // Avoids REGEXP_SUBSTR MySQL version issues
        $allRecords = (clone $base)
            ->select('type', 'amount', 'user_id', 'reference')
            ->get();

        $grouped = [];
        foreach ($allRecords as $r) {
            preg_match('/game:(\d+)/', $r->reference ?? '', $m);
            $gid = $m[1] ?? 'unknown';
            if (!isset($grouped[$gid])) {
                $grouped[$gid] = [
                    'game_id'        => $gid,
                    'sessions'       => 0,
                    'unique_players' => [],
                    'coins_spent'    => 0,
                    'coins_won'      => 0,
                ];
            }
            $grouped[$gid]['sessions']++;
            $grouped[$gid]['unique_players'][$r->user_id] = true;
            if ($r->type === 'game_bet')    $grouped[$gid]['coins_spent'] += abs($r->amount);
            if ($r->type === 'game_reward') $grouped[$gid]['coins_won']   += $r->amount;
        }

        $report = collect($grouped)->map(function ($row) {
            $row['unique_players'] = count($row['unique_players']);
            $row['net']            = $row['coins_spent'] - $row['coins_won'];
            return (object) $row;
        })->sortByDesc('coins_spent')->values();

        // Game IDs for filter dropdown — from local Game model
        $games = Game::orderBy('name')->get(['id', 'name', 'game_id']);

        return view('admin.games.report', compact('report', 'games', 'summary', 'from', 'to'));
    }

    public function manage()
    {
        $games = Game::orderBy('sort_order')->paginate(20);
        return view('admin.games.manage', compact('games'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'game_id'       => ['required', 'string', 'unique:games,game_id'],
            'url'           => ['required', 'url'],
            'thumbnail_url' => ['nullable', 'url'],
            'description'   => ['nullable', 'string'],
            'min_bet'       => ['nullable', 'integer', 'min:0'],
            'sort_order'    => ['nullable', 'integer'],
        ]);

        Game::create($data + ['is_active' => true]);

        return back()->with('success', 'Game added successfully.');
    }

    public function update(Request $request, int $id)
    {
        Game::findOrFail($id)->update($request->only([
            'name', 'url', 'thumbnail_url', 'description',
            'min_bet', 'is_active', 'sort_order',
        ]));

        return back()->with('success', 'Game updated.');
    }
}