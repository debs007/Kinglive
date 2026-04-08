@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')

<div class="stats-grid">
    <div class="stat-card purple">
        <div class="label">Total Users</div>
        <div class="value">{{ number_format($stats['total_users']) }}</div>
    </div>
    <div class="stat-card red">
        <div class="label">Live Rooms Now</div>
        <div class="value">{{ $stats['active_rooms'] }}</div>
    </div>
    <div class="stat-card amber">
        <div class="label">Gifts Today</div>
        <div class="value">{{ number_format($stats['gifts_today']) }}</div>
    </div>
    <div class="stat-card green">
        <div class="label">Revenue Today (coins)</div>
        <div class="value">{{ number_format($stats['revenue_today']) }}</div>
    </div>
</div>

<div class="dashboard-row">
    {{-- Live Rooms --}}
    <div class="card flex-2">
        <div class="card-header">
            <h3>Active Live Rooms</h3>
            <span class="live-badge" id="liveCount">● {{ $stats['active_rooms'] }} LIVE</span>
        </div>
        <table class="admin-table">
            <thead>
                <tr><th>Host</th><th>Title</th><th>Type</th><th>Viewers</th><th>Gifts</th><th>Started</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($liveRooms as $room)
                <tr>
                    <td>
                        <div class="user-cell">
                            <img src="{{ $room->host->avatar_url }}" class="avatar-sm" alt="">
                            <a href="{{ route('admin.users.show', $room->host_user_id) }}">{{ $room->host->username }}</a>
                        </div>
                    </td>
                    <td>{{ Str::limit($room->title, 40) }}</td>
                    <td><span class="badge badge-{{ $room->type }}">{{ strtoupper($room->type) }}</span></td>
                    <td>👁 {{ number_format($room->viewer_count) }}</td>
                    <td>🎁 {{ number_format($room->total_gifts_received) }}</td>
                    <td class="text-muted">{{ $room->started_at?->diffForHumans() }}</td>
                    <td>
                        <div class="action-btns">
                            <a href="{{ route('admin.rooms.show', $room->id) }}" class="btn-sm btn-info">View</a>
                            <form action="{{ route('admin.rooms.end', $room->id) }}" method="POST" style="display:inline"
                                  onsubmit="return confirm('End {{ $room->host->username }}\'s stream?')">
                                @csrf
                                <button class="btn-sm btn-danger">End</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text3)">No live rooms right now.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent Bans --}}
    <div class="card flex-1">
        <div class="card-header">
            <h3>Recent Bans</h3>
            <a href="{{ route('admin.bans.index') }}" class="btn-sm btn-info">View All</a>
        </div>
        @forelse($recentBans as $ban)
        <div class="ban-item">
            <img src="{{ $ban->user->avatar_url }}" class="avatar-sm" alt="">
            <div class="ban-details">
                <strong><a href="{{ route('admin.users.show', $ban->user_id) }}">{{ $ban->user->username }}</a></strong>
                <small>{{ Str::limit($ban->reason, 50) }}</small>
                <small>{{ strtoupper($ban->type) }} · {{ $ban->expires_at?->diffForHumans() ?? 'Permanent' }}</small>
            </div>
            <button class="btn-sm btn-warning"
                onclick="if(confirm('Unban {{ $ban->user->username }}?')){
                    fetch('{{ route('admin.bans.unban') }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'},body:JSON.stringify({user_id:{{ $ban->user_id }}})}).then(()=>location.reload())
                }">Unban</button>
        </div>
        @empty
        <p style="color:var(--text3);font-size:13px;text-align:center;padding:20px">No active bans.</p>
        @endforelse
    </div>
</div>

{{-- Charts --}}
<div class="dashboard-row">
    <div class="card flex-1">
        <div class="card-header"><h3>Gift Revenue — Last 7 Days</h3></div>
        <canvas id="giftChart" height="200"></canvas>
    </div>
    <div class="card flex-1">
        <div class="card-header"><h3>New Users — Last 7 Days</h3></div>
        <canvas id="usersChart" height="200"></canvas>
    </div>
</div>

{{-- Game Reports --}}
<div class="card">
    <div class="card-header">
        <h3>Today's Game Report</h3>
        <a href="{{ route('admin.games.report') }}" class="btn-sm btn-info">Full Report</a>
    </div>
    <table class="admin-table">
        <thead>
            <tr><th>Game</th><th>Sessions</th><th>Players</th><th>Coins Spent</th><th>Coins Won</th><th>House Profit</th></tr>
        </thead>
        <tbody>
            @forelse($gameReports as $row)
            <tr>
                <td><strong>{{ $row->game_id }}</strong></td>
                <td>{{ number_format($row->sessions) }}</td>
                <td>{{ number_format($row->unique_players) }}</td>
                <td class="text-amber">{{ number_format($row->coins_spent) }}</td>
                <td class="text-success">{{ number_format($row->coins_won) }}</td>
                <td class="{{ $row->net > 0 ? 'text-success' : 'text-danger' }}">{{ number_format($row->net) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text3)">No game sessions today.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection

@push('scripts')
<script>
const labels = @json($chartData['labels']);
new Chart(document.getElementById('giftChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Gift Coins',
            data: @json($chartData['giftRevenue']),
            borderColor: '#FFD700',
            backgroundColor: 'rgba(255,215,0,.1)',
            tension: 0.4, fill: true,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: '#6a5f80' } }, y: { ticks: { color: '#6a5f80' } } } }
});
new Chart(document.getElementById('usersChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'New Users',
            data: @json($chartData['newUsers']),
            backgroundColor: 'rgba(108,52,131,.7)',
            borderRadius: 5,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: '#6a5f80' } }, y: { ticks: { color: '#6a5f80' } } } }
});
setInterval(() => {
    fetch('{{ route('admin.api.live-rooms') }}').then(r=>r.json()).then(d=>{
        document.getElementById('liveCount').textContent = `● ${d.count} LIVE`;
    });
}, 30000);
</script>
@endpush
