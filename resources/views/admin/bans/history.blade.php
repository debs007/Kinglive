@extends('admin.layouts.app')
@section('title', 'Ban History — ' . $user->username)
@section('breadcrumb', 'Users › Bans › History')

@section('content')
<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
        <img src="{{ $user->avatar_url }}" style="width:40px;height:40px;border-radius:50%" alt="">
        <h2>Ban History — {{ $user->username }}</h2>
    </div>
    <a href="{{ route('admin.users.show', $user->id) }}" class="btn-secondary">← Back to User</a>
</div>

<div class="card">
    @if($bans->isEmpty())
        <p style="text-align:center;padding:40px;color:var(--text3)">No ban history for this user.</p>
    @else
    <table class="admin-table">
        <thead>
            <tr><th>#</th><th>Type</th><th>Reason</th><th>Banned By</th><th>Duration</th><th>Expires</th><th>Status</th><th>Created</th></tr>
        </thead>
        <tbody>
            @foreach($bans as $ban)
            <tr>
                <td class="text-muted">{{ $ban->id }}</td>
                <td><span class="badge badge-{{ $ban->type }}">{{ strtoupper($ban->type) }}</span></td>
                <td>{{ $ban->reason }}</td>
                <td>{{ $ban->bannedBy?->username ?? 'System' }}</td>
                <td class="text-muted">
                    @if($ban->expires_at)
                        {{ $ban->created_at->diffInHours($ban->expires_at) < 24
                            ? $ban->created_at->diffInHours($ban->expires_at) . 'h'
                            : $ban->created_at->diffInDays($ban->expires_at) . 'd' }}
                    @else
                        Permanent
                    @endif
                </td>
                <td class="text-muted">{{ $ban->expires_at?->format('M d, Y H:i') ?? '—' }}</td>
                <td><span class="badge badge-{{ $ban->is_active && !$ban->isExpired() ? 'active' : 'expired' }}">{{ $ban->status_label }}</span></td>
                <td class="text-muted">{{ $ban->created_at->format('M d, Y H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
