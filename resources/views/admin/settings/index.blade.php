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

    <div style="text-align:right;margin-top:4px">
        <button type="submit" class="btn-primary" style="padding:12px 36px;font-size:15px">
            💾 Save Settings
        </button>
    </div>
</form>
@endsection
