<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

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
        ];

        foreach ($request->only($allowed) as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}
