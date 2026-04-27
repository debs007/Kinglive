@extends('coin_seller.layouts.app')
@section('title','Dashboard')

@section('content')
<div class="page-title">Dashboard</div>
<div class="page-sub">Welcome back, {{ $seller->name }}</div>

<div class="stats-row">
  <div class="stat">
    <div class="stat-icon">🪙</div>
    <div class="stat-value" style="color:var(--gold)">{{ number_format($seller->coin_balance) }}</div>
    <div class="stat-label">Available Balance</div>
  </div>
  <div class="stat">
    <div class="stat-icon">📦</div>
    <div class="stat-value" style="color:#9B59B6">{{ number_format($seller->total_sold) }}</div>
    <div class="stat-label">Total Sold (Lifetime)</div>
  </div>
  <div class="stat">
    <div class="stat-icon">📅</div>
    <div class="stat-value" style="color:var(--info)">{{ number_format($soldThisMonth) }}</div>
    <div class="stat-label">Sold This Month</div>
  </div>
  <div class="stat">
    <div class="stat-icon">⚡</div>
    <div class="stat-value" style="color:var(--success)">{{ number_format($soldToday) }}</div>
    <div class="stat-label">Sold Today</div>
  </div>
</div>

<div class="card">
  <div class="card-head">🕐 Recent Transactions</div>
  <table>
    <thead>
      <tr>
        <th>User</th>
        <th>Coins Added</th>
        <th>Note</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      @forelse($recentTx as $tx)
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            @if($tx->user?->avatar_url)
              <img src="{{ $tx->user->avatar_url }}" class="avatar">
            @else
              <div class="avatar-placeholder">{{ strtoupper(substr($tx->user?->username ?? '?',0,1)) }}</div>
            @endif
            <span>{{ $tx->user?->username ?? 'Deleted' }}</span>
          </div>
        </td>
        <td style="color:var(--gold);font-weight:700">+{{ number_format($tx->coins) }} 🪙</td>
        <td style="color:var(--text3)">{{ $tx->note ?? '—' }}</td>
        <td style="color:var(--text3)">{{ $tx->created_at->diffForHumans() }}</td>
      </tr>
      @empty
      <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:32px">No transactions yet</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
