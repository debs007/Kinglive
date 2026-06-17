@extends('admin.layouts.app')
@section('title', 'Coin Seller Grant Summary')
@section('breadcrumb', 'Coin Sellers / Grant Summary')

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h3 style="font-size:16px;font-weight:700">🏦 Coins Given to Sellers by Admin</h3>
        <div style="font-size:12px;color:var(--text3);margin-top:2px">
            Individual breakdown of how much stock admin has given to each coin seller
        </div>
    </div>
    <a href="{{ route('admin.coin_sellers.transactions') }}" class="btn-secondary">All Transactions</a>
</div>

{{-- Date filter --}}
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:20px">
    <form method="GET" action="{{ route('admin.coin_sellers.grant_summary') }}"
          style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div style="display:flex;flex-direction:column;gap:4px;min-width:160px">
            <label style="font-size:12px;color:var(--text3)">Seller</label>
            <select name="seller_id" style="background:var(--bg3);border:1px solid var(--border);
                    color:var(--text);padding:8px 12px;border-radius:6px;font-size:13px">
                <option value="">All Sellers</option>
                @foreach($sellers as $s)
                    <option value="{{ $s->id }}" {{ request('seller_id')==$s->id ? 'selected' : '' }}>
                        {{ $s->name }}
                    </option>
                @endforeach
            </select>
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
        <a href="{{ route('admin.coin_sellers.grant_summary') }}" class="btn-secondary">Reset</a>
    </form>
</div>

{{-- Grand total --}}
<div style="background:rgba(255,215,0,.08);border:1px solid rgba(255,215,0,.3);border-radius:12px;
            padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px">
    <div style="font-size:36px">🪙</div>
    <div>
        <div style="font-size:12px;color:var(--text3);text-transform:uppercase">Total Coins Given to All Sellers</div>
        <div style="font-size:28px;font-weight:800;color:var(--gold)">{{ number_format($grandTotal) }}</div>
    </div>
</div>

{{-- Per-seller table --}}
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:28px">
    <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:700;font-size:13px">
        Per Seller Summary
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="background:var(--bg3);border-bottom:1px solid var(--border)">
                <th style="padding:10px 14px;text-align:left;color:var(--text3)">Seller</th>
                <th style="padding:10px 14px;text-align:left;color:var(--text3)">Status</th>
                <th style="padding:10px 14px;text-align:right;color:var(--text3)">Current Balance</th>
                <th style="padding:10px 14px;text-align:right;color:var(--text3)">Total Granted</th>
                <th style="padding:10px 14px;text-align:right;color:var(--text3)">Grant Count</th>
                <th style="padding:10px 14px;text-align:left;color:var(--text3)">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sellers as $seller)
            <tr style="border-bottom:1px solid var(--border)"
                onmouseover="this.style.background='var(--bg3)'"
                onmouseout="this.style.background=''">
                <td style="padding:12px 14px">
                    <div style="font-weight:700;color:var(--text)">{{ $seller->name }}</div>
                    <div style="font-size:11px;color:var(--text3)">{{ $seller->email }}</div>
                </td>
                <td style="padding:12px 14px">
                    <span style="background:{{ $seller->is_active ? 'rgba(39,174,96,.15)' : 'rgba(231,76,60,.15)' }};
                                 color:{{ $seller->is_active ? '#27ae60' : '#e74c3c' }};
                                 padding:3px 8px;border-radius:10px;font-size:11px;font-weight:600">
                        {{ $seller->is_active ? '● Active' : '○ Inactive' }}
                    </span>
                </td>
                <td style="padding:12px 14px;text-align:right;font-weight:700;color:var(--gold)">
                    {{ number_format($seller->coin_balance) }} 🪙
                </td>
                <td style="padding:12px 14px;text-align:right;font-weight:700;color:var(--text)">
                    {{ number_format($seller->total_granted ?? 0) }} 🪙
                </td>
                <td style="padding:12px 14px;text-align:right;color:var(--text3)">
                    {{ $seller->grant_count ?? 0 }} times
                </td>
                <td style="padding:12px 14px">
                    <a href="{{ route('admin.coin_sellers.grant_summary') }}?seller_id={{ $seller->id }}&from={{ request('from') }}&to={{ request('to') }}"
                       style="font-size:12px;color:var(--gold);text-decoration:none">
                        View History →
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;color:var(--text3);padding:40px">
                    No coin sellers found.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Recent grants detail --}}
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;overflow:hidden">
    <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;
                justify-content:space-between;align-items:center">
        <span style="font-weight:700;font-size:13px">
            Grant History{{ request('seller_id') ? ' — ' . $sellers->firstWhere('id', request('seller_id'))?->name : '' }}
        </span>
        <span style="font-size:12px;color:var(--text3)">{{ $recentGrants->total() }} records</span>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead>
            <tr style="background:var(--bg3);border-bottom:1px solid var(--border)">
                <th style="padding:10px 14px;text-align:left;color:var(--text3)">Date</th>
                <th style="padding:10px 14px;text-align:left;color:var(--text3)">Seller</th>
                <th style="padding:10px 14px;text-align:right;color:var(--text3)">Coins Granted</th>
                <th style="padding:10px 14px;text-align:left;color:var(--text3)">Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentGrants as $grant)
            <tr style="border-bottom:1px solid var(--border)"
                onmouseover="this.style.background='var(--bg3)'"
                onmouseout="this.style.background=''">
                <td style="padding:10px 14px;color:var(--text3);white-space:nowrap">
                    {{ $grant->created_at->format('M d, Y H:i') }}
                </td>
                <td style="padding:10px 14px;font-weight:600;color:var(--text)">
                    {{ $grant->seller?->name ?? '—' }}
                </td>
                <td style="padding:10px 14px;text-align:right;font-weight:700;color:var(--gold)">
                    +{{ number_format($grant->coins) }} 🪙
                </td>
                <td style="padding:10px 14px;color:var(--text3)">
                    {{ $grant->note ?? '—' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align:center;color:var(--text3);padding:40px">
                    No grant history yet.
                    @if(!request('seller_id'))
                        Grants will appear here after admin adds coins to a seller.
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:12px 14px">
        {{ $recentGrants->appends(request()->query())->links() }}
    </div>
</div>

@endsection