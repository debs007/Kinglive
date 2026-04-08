<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GameService
{
    public function startSession(User $user, string $gameId, string $roomId): array
    {
        $game = Game::where('game_id', $gameId)->firstOrFail();

        $session = GameSession::create([
            'user_id' => $user->id,
            'room_id' => $roomId,
            'game_url' => $game->url,
            'game_id'  => $gameId,
            'status'   => 'started',
        ]);

        return [
            'session_id' => $session->id,
            'game_url'   => $this->buildGameUrl($game, $user),
        ];
    }

    public function endSession(GameSession $session, array $data): array
    {
        $coinsSpent = (int) ($data['coins_spent'] ?? 0);
        $coinsWon   = (int) ($data['coins_won'] ?? 0);

        DB::transaction(function () use ($session, $coinsSpent, $coinsWon, $data) {
            $session->update([
                'coins_spent' => $coinsSpent,
                'coins_won'   => $coinsWon,
                'game_data'   => $data['game_data'] ?? null,
                'status'      => 'completed',
                'ended_at'    => now(),
            ]);

            if ($coinsSpent > 0) {
                User::where('id', $session->user_id)->decrement('coin_balance', $coinsSpent);
                CoinTransaction::create([
                    'user_id'      => $session->user_id,
                    'type'         => 'gift_sent',
                    'amount'       => -$coinsSpent,
                    'balance_after' => User::find($session->user_id)->coin_balance,
                    'reference'    => "game:{$session->game_id}",
                ]);
            }

            if ($coinsWon > 0) {
                User::where('id', $session->user_id)->increment('coin_balance', $coinsWon);
                CoinTransaction::create([
                    'user_id'      => $session->user_id,
                    'type'         => 'admin_credit',
                    'amount'       => $coinsWon,
                    'balance_after' => User::find($session->user_id)->coin_balance,
                    'reference'    => "game_win:{$session->game_id}",
                ]);
            }
        });

        return [
            'net_coins' => $coinsWon - $coinsSpent,
            'status'    => 'completed',
        ];
    }

    public function buildGameUrl(Game $game, User $user): string
    {
        $payload = base64_encode(json_encode([
            'user_id'  => $user->id,
            'username' => $user->username,
            'coins'    => $user->coin_balance,
            'ts'       => time(),
            'sig'      => hash_hmac('sha256', "{$user->id}:{$user->coin_balance}:".time(), config('app.key')),
        ]));

        return rtrim($game->url, '/')."?payload={$payload}";
    }

    public function getReport(Carbon $from, Carbon $to, ?string $gameId = null): \Illuminate\Support\Collection
    {
        $query = GameSession::whereBetween('created_at', [$from, $to])
            ->where('status', 'completed')
            ->selectRaw('game_id,
                COUNT(*) as sessions,
                COUNT(DISTINCT user_id) as unique_players,
                SUM(coins_spent) as coins_spent,
                SUM(coins_won) as coins_won,
                SUM(coins_spent - coins_won) as net')
            ->groupBy('game_id')
            ->orderByDesc('net');

        if ($gameId) {
            $query->where('game_id', $gameId);
        }

        return $query->get();
    }
}
