<?php

namespace Database\Seeders;

use App\Models\CoinPackage;
use Illuminate\Database\Seeder;

class CoinPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['name' => 'Starter',   'coin_amount' => 100,   'bonus_coins' => 0,    'price_usd' => 0.99,  'store_product_id' => 'coins_100'],
            ['name' => 'Basic',     'coin_amount' => 500,   'bonus_coins' => 50,   'price_usd' => 4.99,  'store_product_id' => 'coins_500'],
            ['name' => 'Popular',   'coin_amount' => 1000,  'bonus_coins' => 150,  'price_usd' => 9.99,  'store_product_id' => 'coins_1000'],
            ['name' => 'Value',     'coin_amount' => 2000,  'bonus_coins' => 400,  'price_usd' => 19.99, 'store_product_id' => 'coins_2000'],
            ['name' => 'Premium',   'coin_amount' => 5000,  'bonus_coins' => 1200, 'price_usd' => 49.99, 'store_product_id' => 'coins_5000'],
            ['name' => 'King Pack', 'coin_amount' => 10000, 'bonus_coins' => 3000, 'price_usd' => 99.99, 'store_product_id' => 'coins_10000'],
        ];

        foreach ($packages as $pkg) {
            CoinPackage::firstOrCreate(
                ['store_product_id' => $pkg['store_product_id']],
                array_merge($pkg, ['is_active' => true])
            );
        }
    }
}
