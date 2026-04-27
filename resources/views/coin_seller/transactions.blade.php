@extends('coin_seller.layouts.app')
@section('title','Transactions')

@section('content')
<div class="page-title">Transactions</div>
<div class="page-sub">Your coin sale history</div>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>#</th>
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
          <div style="display:flex;align-items:center;gap:8px">
            @if($tx->user?->avatar_url)
              <img src="{{ $tx->user->avatar_url }}" class="avatar">
            @else
              <div class="avatar-placeholder">{{ strtoupper(substr($tx->user?->username ?? '?',0,1)) }}</div>
            @endif
            <div>
              <div>{{ $tx->user?->username ?? 'Deleted' }}</div>
              <div style="font-size:11px;color:var(--text3)">ID: {{ 100000 + ($tx->user?->id ?? 0) }}</div>
            </div>
          </div>
        </td>
        <td style="color:var(--gold);font-weight:700">+{{ number_format($tx->coins) }} 🪙</td>
        <td><span class="badge badge-{{ $tx->type }}">{{ ucfirst(str_replace('_',' ',$tx->type)) }}</span></td>
        <td style="color:var(--text3)">{{ $tx->note ?? '—' }}</td>
        <td style="color:var(--text3);font-size:12px">{{ $tx->created_at->format('M d, Y H:i') }}</td>
      </tr>
      @empty
      <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:32px">No transactions yet</td></tr>
      @endforelse
    </tbody>
  </table>
  @if($transactions->hasPages())
    <div style="padding:12px 16px">{{ $transactions->links() }}</div>
  @endif
</div>
@endsection
