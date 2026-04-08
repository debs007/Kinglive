<?php

namespace Database\Seeders;

use App\Models\Gift;
use Illuminate\Database\Seeder;

class GiftSeeder extends Seeder
{
    public function run(): void
    {
        $cdn = rtrim(env('CDN_URL', 'https://cdn.kinglive.app'), '/');

        $gifts = [
            // ── Common ──────────────────────────────────────────────────────
            ['name' => 'Rose',       'coin_price' => 1,    'diamond_value' => 1,    'rarity' => 'common',    'category' => 'flowers',  'sort_order' => 1],
            ['name' => 'Lollipop',   'coin_price' => 5,    'diamond_value' => 4,    'rarity' => 'common',    'category' => 'sweet',    'sort_order' => 2],
            ['name' => 'Heart',      'coin_price' => 10,   'diamond_value' => 8,    'rarity' => 'common',    'category' => 'love',     'sort_order' => 3],
            ['name' => 'Balloon',    'coin_price' => 20,   'diamond_value' => 16,   'rarity' => 'common',    'category' => 'fun',      'sort_order' => 4],
            ['name' => 'Ice Cream',  'coin_price' => 50,   'diamond_value' => 40,   'rarity' => 'common',    'category' => 'sweet',    'sort_order' => 5],
            // ── Rare ─────────────────────────────────────────────────────────
            ['name' => 'Fireworks',  'coin_price' => 100,  'diamond_value' => 82,   'rarity' => 'rare',      'category' => 'special',  'sort_order' => 10],
            ['name' => 'Guitar',     'coin_price' => 199,  'diamond_value' => 165,  'rarity' => 'rare',      'category' => 'music',    'sort_order' => 11],
            ['name' => 'Sports Car', 'coin_price' => 500,  'diamond_value' => 420,  'rarity' => 'rare',      'category' => 'luxury',   'sort_order' => 12],
            ['name' => 'Crown',      'coin_price' => 888,  'diamond_value' => 750,  'rarity' => 'rare',      'category' => 'royal',    'sort_order' => 13],
            // ── Epic ─────────────────────────────────────────────────────────
            ['name' => 'Yacht',      'coin_price' => 1000, 'diamond_value' => 850,  'rarity' => 'epic',      'category' => 'luxury',   'sort_order' => 20],
            ['name' => 'Castle',     'coin_price' => 2000, 'diamond_value' => 1700, 'rarity' => 'epic',      'category' => 'royal',    'sort_order' => 21],
            ['name' => 'Dragon',     'coin_price' => 3000, 'diamond_value' => 2600, 'rarity' => 'epic',      'category' => 'fantasy',  'sort_order' => 22],
            // ── Legendary ────────────────────────────────────────────────────
            ['name' => 'Galaxy',     'coin_price' => 5000, 'diamond_value' => 4350, 'rarity' => 'legendary', 'category' => 'cosmic',   'sort_order' => 30],
            ['name' => 'King Ship',  'coin_price' => 9999, 'diamond_value' => 8750, 'rarity' => 'legendary', 'category' => 'royal',    'sort_order' => 31],
        ];

        foreach ($gifts as $gift) {
            $slug = strtolower(str_replace(' ', '_', $gift['name']));

            Gift::firstOrCreate(
                ['name' => $gift['name']],
                array_merge($gift, [
                    'svga_url'      => "{$cdn}/gifts/svga/{$slug}.svga",
                    'thumbnail_url' => "{$cdn}/gifts/thumb/{$slug}.png",
                    'is_active'     => true,
                ])
            );
        }
    }
}
