<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        // Add your game entries here.
        // Replace the URL with your actual game URL.
        $games = [
            [
                'name'          => 'Lucky Spin',
                'game_id'       => 'lucky_spin',
                'description'   => 'Spin the wheel for big prizes!',
                'url'           => env('GAME_LUCKY_SPIN_URL', 'https://games.kinglive.app/lucky-spin'),
                'thumbnail_url' => 'https://cdn.kinglive.app/games/lucky_spin.png',
                'min_bet'       => 10,
                'sort_order'    => 1,
            ],
            [
                'name'          => 'Dice Roll',
                'game_id'       => 'dice_roll',
                'description'   => 'Roll the dice and win coins!',
                'url'           => env('GAME_DICE_ROLL_URL', 'https://games.kinglive.app/dice-roll'),
                'thumbnail_url' => 'https://cdn.kinglive.app/games/dice_roll.png',
                'min_bet'       => 5,
                'sort_order'    => 2,
            ],
        ];

        foreach ($games as $game) {
            Game::firstOrCreate(
                ['game_id' => $game['game_id']],
                array_merge($game, ['is_active' => true])
            );
        }
    }
}
