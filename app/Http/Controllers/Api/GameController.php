<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private readonly GameService $gameService)
    {
    }

    public function index(): JsonResponse
    {
        $games = Game::active()
            ->get(['id', 'game_id', 'name', 'thumbnail_url', 'description', 'min_bet']);

        return response()->json($games);
    }

    public function getUrl(string $gameId): JsonResponse
    {
        $game = Game::where('game_id', $gameId)->firstOrFail();
        $url  = $this->gameService->buildGameUrl($game, auth()->user());

        return response()->json(['url' => $url]);
    }

    public function startSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game_id' => ['required', 'string'],
            'room_id' => ['required', 'string'],
        ]);

        $result = $this->gameService->startSession(
            user:   auth()->user(),
            gameId: $data['game_id'],
            roomId: $data['room_id'],
        );

        return response()->json($result, 201);
    }

    public function endSession(Request $request, int $sessionId): JsonResponse
    {
        $data = $request->validate([
            'game_data'   => ['nullable', 'array'],
            'coins_spent' => ['required', 'integer', 'min:0'],
            'coins_won'   => ['required', 'integer', 'min:0'],
        ]);

        $session = GameSession::where('id', $sessionId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $result = $this->gameService->endSession($session, $data);

        return response()->json($result);
    }
}
