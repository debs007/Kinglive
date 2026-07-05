<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index');
    }

    public function update(Request $request)
    {
        $allowed = [
            'platform_name', 'support_email',
            'min_withdrawal', 'diamond_usd_rate',
            'max_viewers', 'pk_duration', 'max_seats',
            'welcome_bonus', 'gift_fee_pct',
            'game_callback_secret', 'game_allowed_domains',
            // App version & update settings
            'app_latest_version', 'app_min_version',
            'app_android_url', 'app_ios_url',
            'app_update_title', 'app_update_message',
            'app_maintenance_mode', 'app_maintenance_message',
            // Agora credentials
            'agora_app_id', 'agora_app_certificate',
            // Salary sheet settings
            'salary_taka_rate_high', 'salary_taka_rate_low',
            'salary_diamond_target', 'salary_video_days_target',
        ];

        foreach ($request->only($allowed) as $key => $value) {
            Setting::set($key, $value);
        }

        // Handle checkboxes explicitly — unchecked sends nothing, we save '0'
        Setting::set('exchange_enabled',    $request->has('exchange_enabled')    ? '1' : '0');
        Setting::set('daily_reward_enabled', $request->has('daily_reward_enabled') ? '1' : '0');

        // Clear agora credentials cache so new values take effect immediately
        if ($request->hasAny(['agora_app_id', 'agora_app_certificate'])) {
            Cache::forget('agora_credentials');
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}