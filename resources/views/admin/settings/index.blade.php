@extends('admin.layouts.app')
@section('title', 'Platform Settings')
@section('breadcrumb', 'System › Settings')

@section('content')
<form action="{{ route('admin.settings.update') }}" method="POST">
    @csrf

    <div class="dashboard-row">
        <div class="card flex-1">
            <div class="card-header"><h3>Platform</h3></div>
            <div class="form-group">
                <label>Platform Name</label>
                <input type="text" name="platform_name" value="{{ setting('platform_name', 'King Live') }}">
            </div>
            <div class="form-group">
                <label>Support Email</label>
                <input type="email" name="support_email" value="{{ setting('support_email', '') }}">
            </div>
            <div class="form-group">
                <label>Welcome Bonus Coins <small style="color:var(--text3)">(given to new users)</small></label>
                <input type="number" name="welcome_bonus" value="{{ setting('welcome_bonus', 100) }}" min="0">
            </div>
            <div class="form-group">
                <label>Platform Gift Fee % <small style="color:var(--text3)">(platform keeps this % of gift coins)</small></label>
                <input type="number" name="gift_fee_pct" value="{{ setting('gift_fee_pct', 30) }}" min="0" max="100" step="0.1">
            </div>
        </div>

        <div class="card flex-1">
            <div class="card-header"><h3>Live Streaming</h3></div>
            <div class="form-group">
                <label>Max Viewers Per Room</label>
                <input type="number" name="max_viewers" value="{{ setting('max_viewers', 10000) }}" min="1">
            </div>
            <div class="form-group">
                <label>PK Battle Duration (seconds)</label>
                <input type="number" name="pk_duration" value="{{ setting('pk_duration', 300) }}" min="60">
            </div>
            <div class="form-group">
                <label>Max Seats Per Room</label>
                <input type="number" name="max_seats" value="{{ setting('max_seats', 16) }}" min="2" max="32">
            </div>
        </div>
    </div>

    <div class="dashboard-row">
        <div class="card flex-1">
            <div class="card-header"><h3>Diamond Economy</h3></div>
            <div class="form-group">
                <label>Minimum Withdrawal (Diamonds)</label>
                <input type="number" name="min_withdrawal" value="{{ setting('min_withdrawal', 1000) }}" min="1">
            </div>
            <div class="form-group">
                <label>Diamond → USD Rate <small style="color:var(--text3)">(e.g. 0.001 = 1000 diamonds = $1)</small></label>
                <input type="number" name="diamond_usd_rate" value="{{ setting('diamond_usd_rate', 0.001) }}" step="0.0001" min="0.0001">
            </div>
        </div>

        <div class="card flex-1">
            <div class="card-header"><h3>Game Integration</h3></div>
            <div class="form-group">
                <label>Game Callback Secret <small style="color:var(--text3)">(used to verify game events)</small></label>
                <input type="text" name="game_callback_secret" value="{{ setting('game_callback_secret', '') }}" placeholder="Leave blank to auto-generate">
            </div>
            <div class="form-group">
                <label>Allowed Game Domains <small style="color:var(--text3)">(one per line)</small></label>
                <textarea name="game_allowed_domains" rows="4"
                    style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:6px;font-size:13px;resize:vertical">{{ setting('game_allowed_domains', '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- App Version & Force Update --}}
    <div class="dashboard-row">
        <div class="card" style="flex:1">
            <div class="card-header">
                <h3>📱 App Version & Updates</h3>
                <small style="color:var(--text3)">Control what version users must have to use the app</small>
            </div>

            <div class="dashboard-row" style="gap:16px">
                <div class="form-group" style="flex:1">
                    <label>Latest Version <small style="color:var(--text3)">(e.g. 1.2.0)</small></label>
                    <input type="text" name="app_latest_version"
                           value="{{ setting('app_latest_version', '1.0.0') }}"
                           placeholder="1.2.0">
                </div>
                <div class="form-group" style="flex:1">
                    <label>Minimum Required Version <small style="color:var(--text3)">(force update below this)</small></label>
                    <input type="text" name="app_min_version"
                           value="{{ setting('app_min_version', '1.0.0') }}"
                           placeholder="1.0.0">
                </div>
            </div>

            <div class="dashboard-row" style="gap:16px">
                <div class="form-group" style="flex:1">
                    <label>Android Update URL <small style="color:var(--text3)">(Play Store link)</small></label>
                    <input type="text" name="app_android_url"
                           value="{{ setting('app_android_url', '') }}"
                           placeholder="https://play.google.com/store/apps/details?id=...">
                </div>
                <div class="form-group" style="flex:1">
                    <label>iOS Update URL <small style="color:var(--text3)">(App Store link)</small></label>
                    <input type="text" name="app_ios_url"
                           value="{{ setting('app_ios_url', '') }}"
                           placeholder="https://apps.apple.com/app/...">
                </div>
            </div>

            <div class="form-group">
                <label>Update Title <small style="color:var(--text3)">(shown in dialog)</small></label>
                <input type="text" name="app_update_title"
                       value="{{ setting('app_update_title', 'Update Available') }}"
                       placeholder="New Update Available!">
            </div>

            <div class="form-group">
                <label>Update Message <small style="color:var(--text3)">(what's new / why they should update)</small></label>
                <textarea name="app_update_message" rows="3"
                    style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:6px;font-size:13px;resize:vertical"
                    placeholder="We've added exciting new features and fixed bugs. Please update to continue.">{{ setting('app_update_message', '') }}</textarea>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                    <input type="hidden" name="app_maintenance_mode" value="0">
                    <input type="checkbox" name="app_maintenance_mode" value="1"
                           {{ setting('app_maintenance_mode', '0') == '1' ? 'checked' : '' }}
                           style="width:16px;height:16px">
                    <span>🔧 Maintenance Mode <small style="color:var(--text3)">(blocks all app access with maintenance message)</small></span>
                </label>
            </div>

            <div class="form-group">
                <label>Maintenance Message</label>
                <input type="text" name="app_maintenance_message"
                       value="{{ setting('app_maintenance_message', 'We are under maintenance. Please check back soon.') }}"
                       placeholder="We are under maintenance...">
            </div>

            {{-- Preview of what the dialog looks like --}}
            <div style="background:var(--bg3);border-radius:10px;padding:16px;margin-top:8px">
                <div style="font-size:11px;color:var(--text3);margin-bottom:8px">📋 DIALOG PREVIEW</div>
                <div style="background:var(--bg2);border-radius:10px;padding:16px;max-width:300px;margin:0 auto;border:1px solid var(--border)">
                    <div style="font-size:20px;text-align:center;margin-bottom:8px">🚀</div>
                    <div style="font-weight:700;text-align:center;color:var(--text);margin-bottom:6px" id="previewTitle">
                        {{ setting('app_update_title', 'Update Available') }}
                    </div>
                    <div style="font-size:12px;color:var(--text3);text-align:center;margin-bottom:12px" id="previewMsg">
                        {{ setting('app_update_message', 'Please update to continue.') }}
                    </div>
                    <div style="background:#9b59b6;color:white;text-align:center;padding:8px;border-radius:6px;font-size:13px;font-weight:600">
                        Update Now
                    </div>
                    <div style="text-align:center;margin-top:8px;font-size:11px;color:var(--text3)">
                        (Force update: no dismiss button shown)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="text-align:right;margin-top:4px">
        <button type="submit" class="btn-primary" style="padding:12px 36px;font-size:15px">
            💾 Save Settings
        </button>
    </div>
</form>
@endsection
