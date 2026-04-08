@extends('admin.layouts.app')
@section('title', 'Withdrawals')
@section('breadcrumb', 'Economy › Withdrawals')

@section('content')
<div class="page-header">
    <h2>Withdrawal Requests</h2>
    <form method="GET" class="filter-form">
        <select name="status">
            <option value="">All Status</option>
            @foreach(['pending','approved','paid','rejected'] as $s)
            <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary">Filter</button>
    </form>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
    <div class="stat-card amber">
        <div class="label">Pending Requests</div>
        <div class="value">{{ number_format($stats['pending_count']) }}</div>
    </div>
    <div class="stat-card red">
        <div class="label">Pending Amount (USD)</div>
        <div class="value">${{ number_format($stats['pending_usd'], 2) }}</div>
    </div>
    <div class="stat-card green">
        <div class="label">Paid Today (USD)</div>
        <div class="value">${{ number_format($stats['paid_today'], 2) }}</div>
    </div>
</div>

<div class="card">
    <table class="admin-table">
        <thead>
            <tr><th>#</th><th>User</th><th>Diamonds</th><th>USD</th><th>Method</th><th>Status</th><th>Requested</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse($withdrawals as $w)
            <tr>
                <td class="text-muted">{{ $w->id }}</td>
                <td>
                    <div class="user-cell">
                        <img src="{{ $w->user->avatar_url }}" class="avatar-sm" alt="">
                        <div>
                            <a href="{{ route('admin.users.show', $w->user_id) }}">{{ $w->user->username }}</a>
                            <div style="font-size:11px;color:var(--text3)">💎 {{ number_format($w->user->diamond_balance) }} remaining</div>
                        </div>
                    </div>
                </td>
                <td class="text-blue">{{ number_format($w->diamond_amount) }}</td>
                <td class="text-success">${{ number_format($w->usd_amount, 2) }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $w->payment_method)) }}</td>
                <td><span class="badge badge-{{ $w->status }}">{{ strtoupper($w->status) }}</span></td>
                <td class="text-muted">{{ $w->created_at->format('M d, Y H:i') }}</td>
                <td>
                    <div class="action-btns">
                        @if($w->status === 'pending')
                            <form action="{{ route('admin.withdrawals.approve', $w->id) }}" method="POST" style="display:inline">
                                @csrf
                                <button class="btn-sm btn-success">Approve</button>
                            </form>
                            <button class="btn-sm btn-danger" onclick="openRejectModal({{ $w->id }})">Reject</button>
                        @elseif($w->status === 'approved')
                            <form action="{{ route('admin.withdrawals.paid', $w->id) }}" method="POST" style="display:inline"
                                  onsubmit="return confirm('Mark as paid?')">
                                @csrf
                                <button class="btn-sm btn-info">Mark Paid</button>
                            </form>
                        @else
                            <span class="text-muted" style="font-size:12px">{{ $w->processed_at?->format('M d') ?? '—' }}</span>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text3)">No withdrawals found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px">{{ $withdrawals->withQueryString()->links() }}</div>
</div>

{{-- Reject Modal --}}
<div id="rejectModal" class="modal hidden">
    <div class="modal-content">
        <h3>❌ Reject Withdrawal</h3>
        <p style="color:var(--text3);font-size:13px;margin-bottom:16px">Diamonds will be refunded to the user automatically.</p>
        <form id="rejectForm" method="POST">
            @csrf
            <div class="form-group">
                <label>Reason for rejection *</label>
                <input type="text" name="note" required placeholder="e.g. Invalid payment details provided">
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('rejectModal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-danger">Reject &amp; Refund</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openRejectModal(id) {
    document.getElementById('rejectForm').action = `/admin/withdrawals/${id}/reject`;
    document.getElementById('rejectModal').classList.remove('hidden');
}
</script>
@endpush
