@extends('admin.layouts.app')
@section('title', 'Users')
@section('breadcrumb', 'Users')

@section('content')
<div class="page-header">
    <h2>User Management</h2>
</div>

<div class="card">
    <form method="GET" class="filter-form" style="margin-bottom:16px">
        <input type="text" name="search" placeholder="Search username, email, phone…" value="{{ request('search') }}" style="width:260px">
        <select name="role">
            <option value="">All Roles</option>
            @foreach(['user','host','moderator','admin','super_admin'] as $r)
            <option value="{{ $r }}" {{ request('role')===$r?'selected':'' }}>{{ ucwords(str_replace('_',' ',$r)) }}</option>
            @endforeach
        </select>
        <select name="active">
            <option value="">All Status</option>
            <option value="1" {{ request('active')==='1'?'selected':'' }}>Active</option>
            <option value="0" {{ request('active')==='0'?'selected':'' }}>Disabled</option>
        </select>
        <button type="submit" class="btn-primary">Search</button>
    </form>

    <table class="admin-table">
        <thead>
            <tr><th>User</th><th>Role</th><th>Coins</th><th>Diamonds</th><th>Rooms</th><th>Followers</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td>
                    <div class="user-cell">
                        <img src="{{ $user->avatar_url }}" class="avatar-sm" alt="">
                        <div>
                            <a href="{{ route('admin.users.show', $user->id) }}" style="font-weight:600">{{ $user->username }}</a>
                            <div style="font-size:11px;color:var(--text3)">{{ $user->email ?? $user->phone }}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge {{ $user->role === 'super_admin' ? 'badge-global' : ($user->role === 'admin' ? 'badge-room' : 'badge-active') }}">{{ strtoupper(str_replace('_',' ',$user->role)) }}</span></td>
                <td class="text-amber">{{ number_format($user->coin_balance) }}</td>
                <td class="text-blue">{{ number_format($user->diamond_balance) }}</td>
                <td>{{ number_format($user->rooms_count) }}</td>
                <td>{{ number_format($user->followers_count) }}</td>
                <td class="text-muted">{{ $user->created_at->format('M d, Y') }}</td>
                <td>
                    <span class="badge {{ $user->is_active ? 'badge-active' : 'badge-expired' }}">
                        {{ $user->is_active ? 'Active' : 'Disabled' }}
                    </span>
                </td>
                <td>
                    <div class="action-btns">
                        <a href="{{ route('admin.users.show', $user->id) }}" class="btn-sm btn-info">View</a>
                        <button class="btn-sm btn-danger"
                            onclick="document.getElementById('banUserId').value={{ $user->id }};document.getElementById('banModal').classList.remove('hidden')">
                            Ban
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text3)">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px">{{ $users->withQueryString()->links() }}</div>
</div>

{{-- Quick Ban Modal --}}
<div id="banModal" class="modal hidden">
    <div class="modal-content">
        <h3>🚫 Quick Ban</h3>
        <form action="{{ route('admin.bans.store') }}" method="POST">
            @csrf
            <input type="hidden" name="user_id" id="banUserId">
            <div class="form-group"><label>Type</label>
                <select name="type"><option value="global">Global</option><option value="live">Live Only</option><option value="chat">Chat Only</option></select>
            </div>
            <div class="form-group"><label>Duration</label>
                <select name="duration">
                    <option value="1h">1 Hour</option><option value="1d">1 Day</option><option value="7d">7 Days</option>
                    <option value="30d">30 Days</option><option value="permanent">Permanent</option>
                </select>
            </div>
            <div class="form-group"><label>Reason *</label><input type="text" name="reason" required placeholder="Reason for ban"></div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('banModal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-danger">Apply Ban</button>
            </div>
        </form>
    </div>
</div>
@endsection
