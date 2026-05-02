@extends('admin.layouts.app')
@section('title', 'Game Reports')
@section('breadcrumb', 'Games › Reports')

@section('content')

<div class="page-header">
    <h2>Game Reports</h2>
    <form method="GET" class="filter-form" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="date" name="from" value="{{ $from }}"
               style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:6px">
        <span style="color:var(--text3)">to</span>
        <input type="date" name="to" value="{{ $to }}"
               style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:6px">
        <button type="submit" class="btn-primary">Apply</button>
        <a href="{{ route('admin.games.report') }}" class="btn-secondary">Reset</a>
    </form>
</div>

{{-- Overall Summary Cards --}}
<div class="dashboard-row" style="gap:16px;margin-bottom:28px">
    <div class="card" style="flex:1;text-align:center;padding:20px">
        <div style="font-size:28px;font-weight:700;color:var(--text)">{{ number_format($summary['total_sessions']) }}</div>
        <div style="color:var(--text3);font-size:12px;margin-top:4px">Total Transactions</div>
    </div>
    <div class="card" style="flex:1;text-align:center;padding:20px">
        <div style="font-size:28px;font-weight:700;color:#e74c3c">{{ number_format($summary['total_coins_spent']) }}</div>
        <div style="color:var(--text3);font-size:12px;margin-top:4px">Total Coins Bet</div>
    </div>
    <div class="card" style="flex:1;text-align:center;padding:20px">
        <div style="font-size:28px;font-weight:700;color:#27ae60">{{ number_format($summary['total_coins_won']) }}</div>
        <div style="color:var(--text3);font-size:12px;margin-top:4px">Total Coins Won</div>
    </div>
    <div class="card" style="flex:1;text-align:center;padding:20px">
        <div style="font-size:28px;font-weight:700;color:{{ $summary['house_profit'] >= 0 ? '#9b59b6' : '#e74c3c' }}">
            {{ $summary['house_profit'] >= 0 ? '+' : '' }}{{ number_format($summary['house_profit']) }}
        </div>
        <div style="color:var(--text3);font-size:12px;margin-top:4px">House Profit</div>
    </div>
</div>

{{-- Game Tabs --}}
<div style="margin-bottom:0">
    <div style="display:flex;gap:4px;border-bottom:2px solid var(--border);margin-bottom:0" id="gameTabs">
        <button class="game-tab active" data-target="tab-all" onclick="switchTab(this,'tab-all')"
            style="padding:10px 20px;border:none;border-bottom:3px solid #9b59b6;background:transparent;color:var(--text);font-weight:600;cursor:pointer;font-size:13px;margin-bottom:-2px">
            All Games
        </button>
        @foreach($report as $row)
        @php
            $gameName = $games->firstWhere('game_id', $row->game_id)?->name ?? ('Game ' . $row->game_id);
            $tabId    = 'tab-' . $row->game_id;
        @endphp
        <button class="game-tab" data-target="{{ $tabId }}" onclick="switchTab(this,'{{ $tabId }}')"
            style="padding:10px 20px;border:none;border-bottom:3px solid transparent;background:transparent;color:var(--text3);font-weight:500;cursor:pointer;font-size:13px;margin-bottom:-2px">
            {{ $gameName }}
        </button>
        @endforeach
    </div>
</div>

{{-- All Games Tab --}}
<div id="tab-all" class="tab-panel">
    <div class="card" style="border-top-left-radius:0;border-top:none;overflow:hidden">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:var(--bg3)">
                    <th style="padding:14px 20px;text-align:left;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;min-width:200px">Game</th>
                    <th style="padding:14px 20px;text-align:center;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Transactions</th>
                    <th style="padding:14px 20px;text-align:center;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Players</th>
                    <th style="padding:14px 20px;text-align:right;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Coins Bet</th>
                    <th style="padding:14px 20px;text-align:right;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Coins Won</th>
                    <th style="padding:14px 20px;text-align:right;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">House Profit</th>
                    <th style="padding:14px 20px;text-align:center;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">RTP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report as $row)
                @php $gameName = $games->firstWhere('game_id', $row->game_id)?->name ?? ('Game ' . $row->game_id); @endphp
                <tr style="border-top:1px solid var(--border)">
                    <td style="padding:16px 20px">
                        <div style="display:flex;align-items:center;gap:12px">
                            @php $gameThumb = $games->firstWhere('game_id', $row->game_id)?->thumbnail_url; @endphp
                            @if($gameThumb)
                            <img src="{{ $gameThumb }}" style="width:40px;height:40px;border-radius:10px;object-fit:cover;flex-shrink:0" alt="">
                            @endif
                            <div>
                                <div style="font-weight:600;font-size:14px;color:var(--text)">{{ $gameName }}</div>
                                <div style="font-size:11px;color:var(--text3);margin-top:2px">ID: {{ $row->game_id }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:16px 20px;text-align:center;font-size:14px;font-weight:600;color:var(--text)">{{ number_format($row->sessions) }}</td>
                    <td style="padding:16px 20px;text-align:center;font-size:14px;color:var(--text)">{{ number_format($row->unique_players) }}</td>
                    <td style="padding:16px 20px;text-align:right;font-size:14px;font-weight:600;color:#e74c3c;white-space:nowrap">{{ number_format($row->coins_spent) }}</td>
                    <td style="padding:16px 20px;text-align:right;font-size:14px;font-weight:600;color:#27ae60;white-space:nowrap">{{ number_format($row->coins_won) }}</td>
                    <td style="padding:16px 20px;text-align:right;font-size:14px;font-weight:700;white-space:nowrap;color:{{ $row->net >= 0 ? '#9b59b6' : '#e74c3c' }}">
                        {{ $row->net >= 0 ? '+' : '' }}{{ number_format($row->net) }}
                    </td>
                    <td style="padding:16px 20px;text-align:center">
                        @if($row->coins_spent > 0)
                        <span style="background:var(--bg3);padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;color:var(--text)">
                            {{ number_format(($row->coins_won / $row->coins_spent) * 100, 1) }}%
                        </span>
                        @else
                        <span style="color:var(--text3)">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:50px;color:var(--text3)">No game data for this period.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Individual Game Tabs --}}
@foreach($report as $row)
@php
    $gameName  = $games->firstWhere('game_id', $row->game_id)?->name ?? ('Game ' . $row->game_id);
    $gameThumb = $games->firstWhere('game_id', $row->game_id)?->thumbnail_url;
    $tabId     = 'tab-' . $row->game_id;
    $rtp       = $row->coins_spent > 0
        ? number_format(($row->coins_won / $row->coins_spent) * 100, 1) . '%'
        : '—';
@endphp
<div id="{{ $tabId }}" class="tab-panel" style="display:none">
    <div class="card" style="border-top-left-radius:0;border-top:none">

        {{-- Game Header --}}
        <div style="display:flex;align-items:center;gap:16px;padding:20px;border-bottom:1px solid var(--border)">
            @if($gameThumb)
            <img src="{{ $gameThumb }}" style="width:60px;height:60px;border-radius:12px;object-fit:cover" alt="">
            @endif
            <div style="flex:1">
                <div style="font-size:18px;font-weight:700;color:var(--text)">{{ $gameName }}</div>
                <div style="font-size:12px;color:var(--text3);margin-top:2px">Game ID: {{ $row->game_id }}</div>
            </div>
            {{-- Mini stats --}}
            <div style="display:flex;gap:24px">
                <div style="text-align:center">
                    <div style="font-size:20px;font-weight:700;color:var(--text)">{{ number_format($row->sessions) }}</div>
                    <div style="font-size:11px;color:var(--text3)">Transactions</div>
                </div>
                <div style="text-align:center">
                    <div style="font-size:20px;font-weight:700;color:#e74c3c">{{ number_format($row->coins_spent) }}</div>
                    <div style="font-size:11px;color:var(--text3)">Coins Bet</div>
                </div>
                <div style="text-align:center">
                    <div style="font-size:20px;font-weight:700;color:#27ae60">{{ number_format($row->coins_won) }}</div>
                    <div style="font-size:11px;color:var(--text3)">Coins Won</div>
                </div>
                <div style="text-align:center">
                    <div style="font-size:20px;font-weight:700;color:{{ $row->net >= 0 ? '#9b59b6' : '#e74c3c' }}">
                        {{ $row->net >= 0 ? '+' : '' }}{{ number_format($row->net) }}
                    </div>
                    <div style="font-size:11px;color:var(--text3)">House Profit</div>
                </div>
                <div style="text-align:center">
                    <div style="font-size:20px;font-weight:700;color:var(--text)">{{ $rtp }}</div>
                    <div style="font-size:11px;color:var(--text3)">RTP</div>
                </div>
            </div>
        </div>

        {{-- Player breakdown for this game --}}
        <div style="padding:16px 20px 8px">
            <h4 style="color:var(--text);font-size:14px;margin:0">Player Breakdown</h4>
        </div>
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:var(--bg3)">
                    <th style="padding:12px 20px;text-align:left;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;min-width:200px">Player</th>
                    <th style="padding:12px 20px;text-align:center;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;width:120px">Transactions</th>
                    <th style="padding:12px 20px;text-align:right;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;width:140px">Coins Bet</th>
                    <th style="padding:12px 20px;text-align:right;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;width:140px">Coins Won</th>
                    <th style="padding:12px 20px;text-align:right;font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;width:140px">Net</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $playerData = \App\Models\CoinTransaction::with('user')
                        ->whereIn('type', ['game_bet', 'game_reward'])
                        ->where('reference', 'like', '%game:' . $row->game_id . ':%')
                        ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                        ->select('user_id', 'type', 'amount')
                        ->get()
                        ->groupBy('user_id');
                @endphp
                @forelse($playerData as $userId => $txns)
                @php
                    $user   = $txns->first()->user;
                    $pBet   = $txns->where('type', 'game_bet')->sum(fn($t) => abs($t->amount));
                    $pWon   = $txns->where('type', 'game_reward')->sum('amount');
                    $pNet   = $pBet - $pWon;
                    $pCount = $txns->count();
                @endphp
                <tr style="border-top:1px solid var(--border)">
                    <td style="padding:14px 20px">
                        <div style="display:flex;align-items:center;gap:12px">
                            @if($user?->avatar_url)
                            <img src="{{ $user->avatar_url }}"
                                 style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid var(--border)" alt="">
                            @else
                            <div style="width:38px;height:38px;border-radius:50%;background:var(--bg3);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:var(--text3);border:2px solid var(--border)">
                                {{ strtoupper(substr($user?->username ?? '?', 0, 1)) }}
                            </div>
                            @endif
                            <div>
                                <div style="font-weight:600;font-size:14px;color:var(--text)">{{ $user?->username ?? 'Unknown' }}</div>
                                <div style="font-size:11px;color:var(--text3);margin-top:2px">ID: {{ ($user?->id ?? 0) + 100000 }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 20px;text-align:center;font-size:14px;font-weight:600;color:var(--text)">{{ $pCount }}</td>
                    <td style="padding:14px 20px;text-align:right;font-size:14px;font-weight:600;color:#e74c3c;white-space:nowrap">{{ number_format($pBet) }}</td>
                    <td style="padding:14px 20px;text-align:right;font-size:14px;font-weight:600;color:#27ae60;white-space:nowrap">{{ number_format($pWon) }}</td>
                    <td style="padding:14px 20px;text-align:right;font-size:14px;font-weight:700;white-space:nowrap;color:{{ $pNet >= 0 ? '#9b59b6' : '#27ae60' }}">
                        {{ $pNet >= 0 ? '+' : '' }}{{ number_format($pNet) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center;padding:30px;color:var(--text3)">No players found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endforeach

<style>
.game-tab { transition: color .2s, border-color .2s; }
.game-tab:hover { color: var(--text) !important; }
.game-tab.active { color: var(--text) !important; border-bottom-color: #9b59b6 !important; }
</style>

<script>
function switchTab(btn, targetId) {
    document.querySelectorAll('.game-tab').forEach(t => {
        t.classList.remove('active');
        t.style.borderBottomColor = 'transparent';
        t.style.color = 'var(--text3)';
    });
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    btn.classList.add('active');
    btn.style.borderBottomColor = '#9b59b6';
    btn.style.color = 'var(--text)';
    document.getElementById(targetId).style.display = 'block';
}
</script>

@endsection