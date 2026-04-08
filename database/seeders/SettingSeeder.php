<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'platform_name'        => 'King Live',
            'support_email'        => 'support@kinglive.app',
            'min_withdrawal'       => '1000',
            'diamond_usd_rate'     => '0.001',
            'max_viewers'          => '10000',
            'pk_duration'          => '300',
            'max_seats'            => '16',
            'welcome_bonus'        => '100',
            'gift_fee_pct'         => '30',
            'game_callback_secret' => '',
            'game_allowed_domains' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
