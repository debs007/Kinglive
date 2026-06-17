@extends('admin.layouts.app')
@section('title', 'Admin Grant Transactions')
@section('breadcrumb', 'Reports / Admin Grants')

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h3 style="font-size:16px;font-weight:700">📊 Admin Grant Transactions</h3>
        <div style="font-size:12px;color:var(--text3);margin-top:2px">
            All coins added or deducted by admin — use 'Admin Credit' to see additions only with sum
        </div>
    </div>
</div>

{{-- Filters --}}
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:16px">
    <form method="GET" action="{{ route('admin.coin_sellers.transactions') }}"
          style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">

        <div style="display:flex;flex-direction:column;gap:4px;min-width:160px">
            <label style="font-size:12px;color:var(--text3)">Transaction Type</label>
            <select name="type" style="background:var(--bg3);border:1px solid var(--border);
                    color:var(--text);padding:8px 12px;border-radius:6px;font-size:13px">
                <option value="">All Admin Transactions</option>
                <option value="admin_credit"  {{ request('type')=='admin_credit'  ? 'selected' : '' }}>Admin Credit (Additions Only)</option>
                <option value="adjustment"    {{ request('type')=='adjustment'    ? 'selected' : '' }}>Admin Deduction (Negative Only)</option>
                <option value="live_reward"   {{ request('type')=='live_reward'   ? 'selected' : '' }}>Live Reward (Manual)</option>
                <option value="coin_seller"   {{ request('type')=='coin_seller'   ? 'selected' : '' }}>Coin Seller Top-up</option>
            </select>
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;min-width:180px">
            <label style="font-size:12px;color:var(--text3)">Search User</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Username or display name..."
                   style="background:var(--bg3);border:1px solid var(--border);color:var(--text);
                          padding:8px 12px;border-radius:6px;font-size:13px">
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;min-width:140px">
            <label style="font-size:12px;color:var(--text3)">From Date</label>
            <input type="date" name="from" value="{{ request('from') }}"
                   style="background:var(--bg3);border:1px solid var(--border);color:var(--text);
                          padding:8px 12px;border-radius:6px;font-size:13px">
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;min-width:140px">
            <label style="font-size:12px;color:var(--text3)">To Date</label>
            <input type="date" name="to" value="{{ request('to') }}"
                   style="background:var(--bg3);border:1px solid var(--border);color:var(--text);
                          padding:8px 12px;border-radius:6px;font-size:13px">
        </div>

        <button type="submit" class="btn-primary">Filter</button>
        <a href="{{ route('admin.coin_sellers.transactions') }}" class="btn-secondary">Reset</a>
    </form>
</div>

{{-- Summary cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:20px">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:14px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase">Total Coins</div>
        <div style="font-size:20px;font-weight:700;color:{{ $totalCoinsAll >= 0 ? 'var(--gold)' : 'var(--danger)' }}">
            {{ $totalCoinsAll >= 0 ? '+' : '' }}{{ number_format($totalCoinsAll) }} 🪙
        </div>
    </div>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:14px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase">Total Records</div>
        <div style="font-size:20px;font-weight:700;color:var(--text)">{{ number_format($totalCountAll) }}</div>
    </div>
    @if(request('from') || request('to'))
    <div style="background:rgba(155,89,182,.1);border:1px solid #9b59b6;border-radius:12px;padding:14px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase">Date Range</div>
        <div style="font-size:13px;font-weight:600;color:#9b59b6;margin-top:4px">
            {{ request('from') ? \Carbon\Carbon::parse(request('from'))->format('M d, Y') : '∞' }}
            →
            {{ request('to') ? \Carbon\Carbon::parse(request('to'))->format('M d, Y') : 'now' }}
        </div>
    </div>
    @endif
</div>

{{-- Table --}}
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead>
            <tr style="background:var(--bg3);border-bottom:1px solid var(--border)">
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">Date</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">User</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3)">Amount</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3)">Balance After</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">Type</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">Reference / Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $tx)
            @php
                // Determine display type
                if ($tx->type === 'admin_credit') {
                    $label = 'Admin Credit'; $color = '#27ae60';
                } elseif (str_contains($tx->reference ?? '', 'admin_adjustment') && $tx->amount > 0 && $tx->type === 'recharge') {
                    $label = 'Admin Credit'; $color = '#27ae60';
                } elseif ($tx->type === 'live_reward') {
                    $label = 'Live Reward'; $color = '#9b59b6';
                } elseif ($tx->type === 'recharge' && str_contains($tx->reference ?? '', 'admin_adjustment')) {
                    $label = 'Adjustment'; $color = $tx->amount >= 0 ? '#3498db' : '#e74c3c';
                } elseif ($tx->type === 'recharge' && str_contains($tx->reference ?? '', 'coin_seller')) {
                    $label = 'Coin Seller'; $color = '#f39c12';
                } else {
                    $label = ucfirst(str_replace('_', ' ', $tx->type)); $color = 'var(--text3)';
                }
            @endphp
            <tr style="border-bottom:1px solid var(--border)"
                onmouseover="this.style.background='var(--bg3)'"
                onmouseout="this.style.background=''">
                <td style="padding:10px 12px;color:var(--text3);white-space:nowrap">
                    {{ $tx->created_at->format('M d, Y') }}<br>
                    <span style="font-size:10px">{{ $tx->created_at->format('H:i:s') }}</span>
                </td>
                <td style="padding:10px 12px">
                    <div style="display:flex;align-items:center;gap:8px">
                        @if($tx->user?->avatar_url)
                            <img src="{{ $tx->user->avatar_url }}"
                                 style="width:28px;height:28px;border-radius:50%;object-fit:cover">
                        @else
                            <div style="width:28px;height:28px;border-radius:50%;background:var(--purple);
                                        display:flex;align-items:center;justify-content:center;
                                        font-size:12px;color:#fff;font-weight:700;flex-shrink:0">
                                {{ strtoupper(substr($tx->user?->username ?? '?', 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <div style="font-weight:600;color:var(--text)">
                                {{ $tx->user?->display_name ?? $tx->user?->username ?? 'Deleted' }}
                            </div>
                            <div style="font-size:11px;color:var(--text3)">
                                @{{ $tx->user?->username ?? '—' }} · ID {{ $tx->user_id }}
                            </div>
                        </div>
                    </div>
                </td>
                <td style="padding:10px 12px;text-align:right;font-weight:700;
                    color:{{ $tx->amount >= 0 ? 'var(--success)' : 'var(--danger)' }};white-space:nowrap">
                    {{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount) }} 🪙
                </td>
                <td style="padding:10px 12px;text-align:right;color:var(--text3)">
                    {{ number_format($tx->balance_after ?? 0) }}
                </td>
                <td style="padding:10px 12px">
                    <span style="background:{{ $color }}22;color:{{ $color }};padding:3px 8px;
                                 border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap">
                        {{ $label }}
                    </span>
                </td>
                <td style="padding:10px 12px;color:var(--text3);font-size:11px;
                           max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="{{ $tx->reference }}">
                    {{ $tx->reference ?? '—' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;color:var(--text3);padding:60px">
                    <div style="font-size:40px;margin-bottom:12px">📭</div>
                    No admin transactions found{{ request()->hasAny(['type','from','to','search']) ? ' for the selected filters' : '' }}.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:14px">
    {{ $transactions->appends(request()->query())->links() }}
</div>

@endsection