<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            GiftSeeder::class,
            CoinPackageSeeder::class,
            GameSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
