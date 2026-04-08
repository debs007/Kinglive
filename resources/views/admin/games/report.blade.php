@extends('admin.layouts.app')
@section('title', 'Game Reports')
@section('breadcrumb', 'Games › Reports')

@section('content')
<div class="page-header">
    <h2>Game Reports</h2>
    <form method="GET" class="filter-form">
        <select name="game_id">
            <option value="">All Games</option>
            @foreach($games as $g)
            <option value="{{ $g->game_id }}" {{ request('game_id')===$g->game_id?'selected':'' }}>{{ $g->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $from->toDateString() }}">
        <span style="color:var(--text3)">to</span>
        <input type="date" name="to" value="{{ $to->toDateString() }}">
        <button type="submit" class="btn-primary">Apply</button>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card blue">
        <div class="label">Total Sessions</div>
        <div class="value">{{ number_format($summary['total_sessions']) }}</div>
    </div>
    <div class="stat-card amber">
        <div class="label">Coins Spent</div>
        <div class="value">{{ number_format($summary['total_coins_spent']) }}</div>
    </div>
    <div class="stat-card green">
        <div class="label">Coins Won</div>
        <div class="value">{{ number_format($summary['total_coins_won']) }}</div>
    </div>
    <div class="stat-card {{ $summary['house_profit'] >= 0 ? 'green' : 'red' }}">
        <div class="label">House Profit</div>
        <div class="value">{{ number_format($summary['house_profit']) }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Per-Game Breakdown</h3></div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Game ID</th>
                <th>Sessions</th>
                <th>Unique Players</th>
                <th>Coins Spent</th>
                <th>Coins Won</th>
                <th>Net (House)</th>
                <th>RTP %</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report as $row)
            <tr>
                <td><strong>{{ $row->game_id }}</strong></td>
                <td>{{ number_format($row->sessions) }}</td>
                <td>{{ number_format($row->unique_players) }}</td>
                <td class="text-amber">{{ number_format($row->coins_spent) }}</td>
                <td class="text-success">{{ number_format($row->coins_won) }}</td>
                <td class="{{ $row->net >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $row->net >= 0 ? '+' : '' }}{{ number_format($row->net) }}
                </td>
                <td>
                    @if($row->coins_spent > 0)
                        {{ number_format(($row->coins_won / $row->coins_spent) * 100, 1) }}%
                    @else
                        —
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text3)">No game data for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
