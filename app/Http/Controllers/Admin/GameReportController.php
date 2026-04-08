<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Services\GameService;
use Illuminate\Http\Request;

class GameReportController extends Controller
{
    public function __construct(private readonly GameService $gameService)
    {
    }

    public function report(Request $request)
    {
        $from   = $request->date('from') ?? now()->startOfMonth();
        $to     = $request->date('to')   ?? now()->endOfDay();
        $gameId = $request->input('game_id');

        $report = $this->gameService->getReport($from, $to, $gameId);
        $games  = Game::active()->get(['id', 'name', 'game_id']);

        $summary = [
            'total_sessions'    => GameSession::whereBetween('created_at', [$from, $to])->count(),
            'total_coins_spent' => GameSession::whereBetween('created_at', [$from, $to])->sum('coins_spent'),
            'total_coins_won'   => GameSession::whereBetween('created_at', [$from, $to])->sum('coins_won'),
            'house_profit'      => GameSession::whereBetween('created_at', [$from, $to])
                ->selectRaw('SUM(coins_spent - coins_won) as profit')->value('profit') ?? 0,
        ];

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
            'thumbnail_url' => ['required', 'url'],
            'description'   => ['nullable', 'string'],
            'min_bet'       => ['nullable', 'integer', 'min:0'],
        ]);

        Game::create($data + ['is_active' => true]);

        return back()->with('success', 'Game added successfully.');
    }

    public function update(Request $request, int $id)
    {
        Game::findOrFail($id)->update($request->only([
            'name', 'url', 'thumbnail_url', 'description', 'min_bet', 'is_active', 'sort_order',
        ]));

        return back()->with('success', 'Game updated.');
    }
}
