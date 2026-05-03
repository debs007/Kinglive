<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameReportController extends Controller
{
    public function report(Request $request)
    {
        // Default to TODAY only — not the whole month
        $from   = $request->input('from', now()->toDateString());
        $to     = $request->input('to',   now()->toDateString());
        $gameId = $request->input('game_id');

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';

        $gameFilter  = $gameId ? " AND reference LIKE :gameRef" : '';
        $bindSummary = ['from' => $fromDt, 'to' => $toDt];
        $bindReport  = ['from' => $fromDt, 'to' => $toDt];

        if ($gameId) {
            $bindSummary['gameRef'] = "%game:{$gameId}:%";
            $bindReport['gameRef']  = "%game:{$gameId}:%";
        }

        // Summary — pure aggregation, no row loading
        $summaryRow = DB::selectOne("
            SELECT
                COUNT(*)                                                         AS total_sessions,
                COALESCE(SUM(CASE WHEN type = 'game_bet'    THEN ABS(amount) ELSE 0 END), 0) AS total_coins_spent,
                COALESCE(SUM(CASE WHEN type = 'game_reward' THEN amount       ELSE 0 END), 0) AS total_coins_won
            FROM coin_transactions
            WHERE type IN ('game_bet','game_reward')
              AND created_at BETWEEN :from AND :to
              AND reference LIKE '%game:%'
              {$gameFilter}
        ", $bindSummary);

        $summary = [
            'total_sessions'    => (int)   ($summaryRow->total_sessions    ?? 0),
            'total_coins_spent' => (float) ($summaryRow->total_coins_spent ?? 0),
            'total_coins_won'   => (float) ($summaryRow->total_coins_won   ?? 0),
            'house_profit'      => 0,
        ];
        $summary['house_profit'] = $summary['total_coins_spent'] - $summary['total_coins_won'];

        // Per-game breakdown — pure SQL grouping using SUBSTRING_INDEX
        $rows = DB::select("
            SELECT
                SUBSTRING_INDEX(SUBSTRING_INDEX(reference, 'game:', -1), ':', 1)  AS game_id,
                COUNT(*)                                                            AS sessions,
                COUNT(DISTINCT user_id)                                             AS unique_players,
                COALESCE(SUM(CASE WHEN type = 'game_bet'    THEN ABS(amount) ELSE 0 END), 0) AS coins_spent,
                COALESCE(SUM(CASE WHEN type = 'game_reward' THEN amount       ELSE 0 END), 0) AS coins_won,
                COALESCE(SUM(CASE WHEN type = 'game_bet'    THEN ABS(amount) ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = 'game_reward' THEN amount       ELSE 0 END), 0) AS net
            FROM coin_transactions
            WHERE type IN ('game_bet','game_reward')
              AND created_at BETWEEN :from AND :to
              AND reference LIKE '%game:%'
              {$gameFilter}
            GROUP BY SUBSTRING_INDEX(SUBSTRING_INDEX(reference, 'game:', -1), ':', 1)
            ORDER BY coins_spent DESC
        ", $bindReport);

        $report = collect($rows);
        $games  = Game::orderBy('name')->get(['id', 'name', 'game_id']);

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