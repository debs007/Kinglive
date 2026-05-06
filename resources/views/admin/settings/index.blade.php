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
    
    {{-- ── Agora Credentials ─────────────────────────────────────────────────────
     Add this section inside your existing settings form in
     resources/views/admin/settings/index.blade.php
     Place it after the App Version section.
──────────────────────────────────────────────────────────────────────────── --}}

<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;align-items:center;gap:10px">
        <span style="font-size:20px">🎙️</span>
        <h3 style="margin:0">Agora Credentials</h3>
    </div>

    <div style="padding:20px">
        <div style="background:rgba(231,76,60,0.1);border:1px solid rgba(231,76,60,0.3);border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start">
            <span style="font-size:16px">⚠️</span>
            <div style="font-size:13px;color:#e74c3c">
                <strong>Keep your App Certificate secret.</strong>
                It is never sent to the frontend — only the App ID is shared with clients.
                Changing these values takes effect immediately for all new token generations.
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label>Agora App ID
                    <small style="color:var(--text3);font-weight:400">(sent to app)</small>
                </label>
                <input type="text"
                       name="agora_app_id"
                       value="{{ \App\Models\Setting::get('agora_app_id', '') }}"
                       placeholder="e.g. a1b2c3d4e5f6..."
                       style="font-family:monospace;font-size:13px">
                <small style="color:var(--text3)">Your Agora project App ID from console.agora.io</small>
            </div>

            <div class="form-group">
                <label>Agora App Certificate
                    <small style="color:var(--text3);font-weight:400">(secret — backend only)</small>
                </label>
                <input type="password"
                       name="agora_app_certificate"
                       value="{{ \App\Models\Setting::get('agora_app_certificate', '') }}"
                       placeholder="App Certificate"
                       style="font-family:monospace;font-size:13px">
                <small style="color:var(--text3)">Used to sign tokens server-side. Never exposed to clients.</small>
            </div>
        </div>

        <div style="margin-top:12px;padding:12px 16px;background:var(--bg3);border-radius:8px;font-size:12px;color:var(--text3)">
            <strong style="color:var(--text)">Current status:</strong>
            @php
                $appId   = \App\Models\Setting::get('agora_app_id', '');
                $cert    = \App\Models\Setting::get('agora_app_certificate', '');
            @endphp
            @if($appId && $cert)
                <span style="color:#27ae60">✓ Credentials configured</span>
                — App ID: <code style="font-size:11px">{{ substr($appId, 0, 8) }}•••••••••••••</code>
            @else
                <span style="color:#e74c3c">✗ Not configured</span>
                — Live streaming will not work until both fields are set.
            @endif
        </div>
    </div>
</div>

{{-- Add this section to resources/views/admin/settings/index.blade.php --}}
{{-- Place it after the Agora credentials section --}}

<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;align-items:center;gap:10px">
        <span style="font-size:20px">💰</span>
        <h3 style="margin:0">Agency Salary Settings</h3>
    </div>
    <div style="padding:20px">
        <p style="color:var(--text3);font-size:13px;margin-bottom:20px">
            Configure salary rates and targets for agency host salary sheets.
            Hosts must meet BOTH diamond and video days targets to get the high rate.
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label>High Rate (BDT per 100K diamonds)</label>
                <input type="number"
                       name="salary_taka_rate_high"
                       value="{{ \App\Models\Setting::get('salary_taka_rate_high', 900) }}"
                       min="0" step="0.01">
                <small style="color:var(--text3)">Paid when both targets are met</small>
            </div>
            <div class="form-group">
                <label>Low Rate (BDT per 100K diamonds)</label>
                <input type="number"
                       name="salary_taka_rate_low"
                       value="{{ \App\Models\Setting::get('salary_taka_rate_low', 450) }}"
                       min="0" step="0.01">
                <small style="color:var(--text3)">Paid when targets are NOT met</small>
            </div>
            <div class="form-group">
                <label>Diamond Target (per month)</label>
                <input type="number"
                       name="salary_diamond_target"
                       value="{{ \App\Models\Setting::get('salary_diamond_target', 300000) }}"
                       min="0" step="1000">
                <small style="color:var(--text3)">Minimum diamonds required for high rate</small>
            </div>
            <div class="form-group">
                <label>Video Days Target (per month)</label>
                <input type="number"
                       name="salary_video_days_target"
                       value="{{ \App\Models\Setting::get('salary_video_days_target', 18) }}"
                       min="0" max="31">
                <small style="color:var(--text3)">Minimum live days required for high rate</small>
            </div>
        </div>

        {{-- Current config summary --}}
        <div style="margin-top:16px;padding:12px 16px;background:var(--bg3);border-radius:8px;font-size:12px;color:var(--text3)">
            <strong style="color:var(--text)">Current logic:</strong>
            If diamonds ≥ {{ number_format(\App\Models\Setting::get('salary_diamond_target', 300000)) }}
            AND live days ≥ {{ \App\Models\Setting::get('salary_video_days_target', 18) }} →
            <span style="color:#27ae60">৳{{ \App\Models\Setting::get('salary_taka_rate_high', 900) }} per 100K</span>
            &nbsp;|&nbsp; Otherwise →
            <span style="color:#e74c3c">৳{{ \App\Models\Setting::get('salary_taka_rate_low', 450) }} per 100K</span>
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
