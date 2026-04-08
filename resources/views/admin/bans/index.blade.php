@extends('admin.layouts.app')
@section('title', 'Ban Management')
@section('breadcrumb', 'Users › Bans')

@section('content')
<div class="page-header">
    <h2>Ban Management</h2>
    <button class="btn-primary" onclick="document.getElementById('banModal').classList.remove('hidden')">+ New Ban</button>
</div>

<div class="stats-grid">
    <div class="stat-card red"><div class="label">Active Bans</div><div class="value">{{ number_format($stats['total_active']) }}</div></div>
    <div class="stat-card red"><div class="label">Global Bans</div><div class="value">{{ number_format($stats['global_bans']) }}</div></div>
    <div class="stat-card amber"><div class="label">Room Bans</div><div class="value">{{ number_format($stats['room_bans']) }}</div></div>
    <div class="stat-card purple"><div class="label">Permanent</div><div class="value">{{ number_format($stats['permanent_bans']) }}</div></div>
</div>

<div class="card">
    <form method="GET" class="filter-form" style="margin-bottom:16px">
        <input type="text" name="search" placeholder="Search username…" value="{{ request('search') }}" style="width:200px">
        <select name="type">
            <option value="">All Types</option>
            @foreach(['global','room','chat','live'] as $t)
            <option value="{{ $t }}" {{ request('type')===$t?'selected':'' }}>{{ ucfirst($t) }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">All Status</option>
            <option value="active" {{ request('status')==='active'?'selected':'' }}>Active</option>
            <option value="inactive" {{ request('status')==='inactive'?'selected':'' }}>Lifted / Expired</option>
        </select>
        <button type="submit" class="btn-primary">Filter</button>
    </form>

    <table class="admin-table">
        <thead>
            <tr><th>#</th><th>User</th><th>Type</th><th>Reason</th><th>Banned By</th><th>Expires</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse($bans as $ban)
            <tr>
                <td class="text-muted">{{ $ban->id }}</td>
                <td>
                    <div class="user-cell">
                        <img src="{{ $ban->user->avatar_url }}" class="avatar-sm" alt="">
                        <div>
                            <a href="{{ route('admin.users.show', $ban->user_id) }}">{{ $ban->user->username }}</a>
                            <div style="font-size:11px;color:var(--text3)">ID: {{ $ban->user_id }}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-{{ $ban->type }}">{{ strtoupper($ban->type) }}</span></td>
                <td style="max-width:200px;color:var(--text2)">{{ Str::limit($ban->reason, 60) }}</td>
                <td>{{ $ban->bannedBy?->username ?? 'System' }}</td>
                <td style="font-size:12px">
                    @if($ban->expires_at)
                        {{ $ban->expires_at->format('M d, Y H:i') }}<br>
                        <span class="text-muted">{{ $ban->expires_at->diffForHumans() }}</span>
                    @else
                        <span class="text-danger" style="font-weight:600">Permanent</span>
                    @endif
                </td>
                <td>
                    <span class="badge badge-{{ $ban->is_active && !$ban->isExpired() ? 'active' : 'expired' }}">
                        {{ $ban->status_label }}
                    </span>
                </td>
                <td>
                    <div class="action-btns">
                        <a href="{{ route('admin.bans.history', $ban->user_id) }}" class="btn-sm btn-info">History</a>
                        @if($ban->is_active && !$ban->isExpired())
                        <button class="btn-sm btn-warning"
                            onclick="if(confirm('Lift ban for {{ $ban->user->username }}?')){
                                fetch('{{ route('admin.bans.unban') }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'},body:JSON.stringify({user_id:{{ $ban->user_id }},type:'{{ $ban->type }}'})}).then(()=>location.reload())
                            }">Unban</button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text3)">No bans found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px">{{ $bans->links() }}</div>
</div>

{{-- New Ban Modal --}}
<div id="banModal" class="modal hidden">
    <div class="modal-content">
        <h3>🚫 Ban User</h3>
        <form action="{{ route('admin.bans.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>User ID *</label>
                <input type="number" name="user_id" id="banUserId" required placeholder="Enter numeric user ID">
            </div>
            <div class="form-group">
                <label>Ban Type *</label>
                <select name="type" id="banType" onchange="document.getElementById('roomIdField').style.display=this.value==='room'?'block':'none'">
                    <option value="global">Global — cannot use platform</option>
                    <option value="live">Live — cannot stream</option>
                    <option value="chat">Chat — cannot send messages</option>
                    <option value="room">Room — specific room only</option>
                </select>
            </div>
            <div class="form-group" id="roomIdField" style="display:none">
                <label>Room ID</label>
                <input type="text" name="room_id" placeholder="Room UUID">
            </div>
            <div class="form-group">
                <label>Duration *</label>
                <select name="duration">
                    <option value="1h">1 Hour</option>
                    <option value="3h">3 Hours</option>
                    <option value="12h">12 Hours</option>
                    <option value="1d">1 Day</option>
                    <option value="3d">3 Days</option>
                    <option value="7d">7 Days</option>
                    <option value="30d">30 Days</option>
                    <option value="3m">3 Months</option>
                    <option value="6m">6 Months</option>
                    <option value="permanent">Permanent ⚠</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reason *</label>
                <input type="text" name="reason" required placeholder="Reason for ban (shown to user)" maxlength="500">
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('banModal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-danger">Apply Ban</button>
            </div>
        </form>
    </div>
</div>
@endsection
