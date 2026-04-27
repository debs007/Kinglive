@extends('admin.layouts.app')
@section('title', 'Coin Transactions')
@section('breadcrumb', 'Coin Sellers / Transactions')

@section('content')

<div class="card-header" style="margin-bottom:20px">
  <div>
    <h3 style="font-size:16px;font-weight:700">📊 Coin Seller Transactions</h3>
    <div style="font-size:12px;color:var(--text3);margin-top:2px">All coin distributions across sellers and admin grants</div>
  </div>
  <a href="{{ route('admin.coin_sellers.index') }}" class="btn-secondary">← Back to Sellers</a>
</div>

{{-- Filters --}}
<div class="card" style="padding:14px 18px;margin-bottom:16px">
  <form method="GET" action="{{ route('admin.coin_sellers.transactions') }}"
        style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group" style="margin:0;min-width:180px">
      <label>Filter by Seller</label>
      <select name="seller_id" style="width:100%;background:var(--bg3);border:1px solid var(--border);
              color:var(--text);padding:8px 12px;border-radius:6px;font-size:13px">
        <option value="">All Sellers</option>
        <option value="admin" {{ request('seller_id')=='admin' ? 'selected' : '' }}>Admin Grants</option>
        @foreach($sellers as $s)
          <option value="{{ $s->id }}" {{ request('seller_id')==$s->id ? 'selected' : '' }}>
            {{ $s->name }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:150px">
      <label>Type</label>
      <select name="type" style="width:100%;background:var(--bg3);border:1px solid var(--border);
              color:var(--text);padding:8px 12px;border-radius:6px;font-size:13px">
        <option value="">All Types</option>
        <option value="sale" {{ request('type')=='sale' ? 'selected' : '' }}>Sale</option>
        <option value="admin_grant" {{ request('type')=='admin_grant' ? 'selected' : '' }}>Admin Grant</option>
      </select>
    </div>
    <button type="submit" class="btn-primary">Filter</button>
    <a href="{{ route('admin.coin_sellers.transactions') }}" class="btn-secondary">Reset</a>
  </form>
</div>

{{-- Summary stats --}}
@php
  $totalCoins = $transactions->sum('coins');
@endphp
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px">
  <div class="stat-card amber">
    <div class="label">Total Coins (this page)</div>
    <div class="value">{{ number_format($totalCoins) }}</div>
  </div>
  <div class="stat-card purple">
    <div class="label">Records (this page)</div>
    <div class="value">{{ $transactions->count() }}</div>
  </div>
  <div class="stat-card blue">
    <div class="label">Total Records</div>
    <div class="value">{{ $transactions->total() }}</div>
  </div>
</div>

<div class="card" style="padding:0">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Seller / Source</th>
        <th>User</th>
        <th>Coins</th>
        <th>Type</th>
        <th>Note</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      @forelse($transactions as $tx)
      <tr>
        <td style="color:var(--text3)">{{ $tx->id }}</td>
        <td>
          @if($tx->seller)
            <div style="font-weight:600;color:var(--text)">{{ $tx->seller->name }}</div>
          @else
            <span class="badge badge-active">👑 Admin</span>
          @endif
        </td>
        <td>
          <div class="user-cell">
            @if($tx->user?->avatar_url)
              <img src="{{ $tx->user->avatar_url }}" class="avatar-sm">
            @else
              <div class="avatar-sm" style="background:var(--purple);display:flex;align-items:center;justify-content:center;font-size:12px;color:#fff;font-weight:700">
                {{ strtoupper(substr($tx->user?->username ?? '?', 0, 1)) }}
              </div>
            @endif
            <div>
              <div>{{ $tx->user?->username ?? 'Deleted' }}</div>
              <div style="font-size:11px;color:var(--text3)">ID: {{ 100000 + ($tx->user?->id ?? 0) }}</div>
            </div>
          </div>
        </td>
        <td style="color:var(--gold);font-weight:700">+{{ number_format($tx->coins) }} 🪙</td>
        <td>
          <span class="badge {{ $tx->type === 'admin_grant' ? 'badge-active' : 'badge-pending' }}">
            {{ $tx->type === 'admin_grant' ? 'Admin Grant' : 'Sale' }}
          </span>
        </td>
        <td style="color:var(--text3)">{{ $tx->note ?? '—' }}</td>
        <td style="color:var(--text3);font-size:12px">{{ $tx->created_at->format('M d, Y H:i') }}</td>
      </tr>
      @empty
      <tr>
        <td colspan="7" style="text-align:center;color:var(--text3);padding:40px">
          No transactions found
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $transactions->appends(request()->query())->links() }}
@endsection
