@extends('admin.layouts.app')
@section('title', 'Salary Sheet')
@section('breadcrumb', 'Salary Sheet')

@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <h2>💰 Salary Sheet</h2>

    <div style="display:flex;gap:10px;align-items:center">
        {{-- Period selector --}}
        <form method="GET" action="{{ route('admin.salary.index') }}" style="display:flex;gap:8px;align-items:center">
            <select name="period" onchange="this.form.submit()"
                    style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px">
                @forelse($months as $m)
                    <option value="{{ $m['value'] }}" {{ $selected === $m['value'] ? 'selected' : '' }}>
                        {{ $m['label'] }}
                    </option>
                @empty
                    <option>No data yet</option>
                @endforelse
            </select>
        </form>

        {{-- Download CSV --}}
        @if($selected)
        <form method="GET" action="{{ route('admin.salary.download') }}">
            <input type="hidden" name="period" value="{{ $selected }}">
            <button type="submit" class="btn-primary" style="display:flex;align-items:center;gap:6px">
                ⬇️ Download CSV
            </button>
        </form>
        @endif
    </div>
</div>

@if(session('error'))
<div style="background:#3a1a1a;border:1px solid #e74c3c;color:#e74c3c;padding:12px 16px;border-radius:8px;margin-bottom:16px">
    ❌ {{ session('error') }}
</div>
@endif

@if($snapshots->isEmpty())
    <div style="text-align:center;padding:80px;color:var(--text3)">
        <div style="font-size:48px;margin-bottom:12px">📊</div>
        <div style="font-size:16px">No salary data available yet.</div>
        <div style="font-size:13px;margin-top:8px">Data is captured on the 1st of each month at midnight.</div>
    </div>
@else

{{-- Summary cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:24px">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Hosts</div>
        <div style="font-size:24px;font-weight:700;color:var(--text);margin-top:4px">{{ $snapshots->count() }}</div>
    </div>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Total Diamonds</div>
        <div style="font-size:24px;font-weight:700;color:#9b59b6;margin-top:4px">{{ number_format($totals['diamonds']) }}</div>
    </div>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Gross USD</div>
        <div style="font-size:24px;font-weight:700;color:#27ae60;margin-top:4px">${{ number_format($totals['usd'], 2) }}</div>
    </div>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Commission</div>
        <div style="font-size:24px;font-weight:700;color:#e74c3c;margin-top:4px">${{ number_format($totals['commission'], 2) }}</div>
    </div>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Net Payable</div>
        <div style="font-size:24px;font-weight:700;color:#f39c12;margin-top:4px">${{ number_format($totals['net'], 2) }}</div>
    </div>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Total Live Mins</div>
        <div style="font-size:24px;font-weight:700;color:#3498db;margin-top:4px">{{ number_format($totals['minutes']) }}</div>
    </div>
</div>

{{-- Table --}}
<div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="border-bottom:2px solid var(--border)">
                <th style="padding:10px 12px;text-align:left;color:var(--text3);font-weight:600;white-space:nowrap">#</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3);font-weight:600;white-space:nowrap">Host</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3);font-weight:600;white-space:nowrap">Agency</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3);font-weight:600;white-space:nowrap">Streams</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3);font-weight:600;white-space:nowrap">Live Mins</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3);font-weight:600;white-space:nowrap">V.Days</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3);font-weight:600;white-space:nowrap">A.Days</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3);font-weight:600;white-space:nowrap">💎 Diamonds</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3);font-weight:600;white-space:nowrap">Gross USD</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3);font-weight:600;white-space:nowrap">Commission</th>
                <th style="padding:10px 12px;text-align:right;color:var(--text3);font-weight:600;white-space:nowrap">Net Payable</th>
            </tr>
        </thead>
        <tbody>
            @foreach($snapshots as $i => $s)
            <tr style="border-bottom:1px solid var(--border);{{ $s->net_usd > 0 ? '' : 'opacity:.5' }}">
                <td style="padding:10px 12px;color:var(--text3)">{{ $i + 1 }}</td>
                <td style="padding:10px 12px">
                    <div style="font-weight:600;color:var(--text)">{{ $s->display_name ?? $s->username }}</div>
                    <div style="font-size:11px;color:var(--text3)">@{{ $s->username }}</div>
                    @if($s->email)
                    <div style="font-size:11px;color:var(--text3)">{{ $s->email }}</div>
                    @endif
                    @if($s->phone)
                    <div style="font-size:11px;color:var(--text3)">{{ $s->phone }}</div>
                    @endif
                </td>
                <td style="padding:10px 12px">
                    @if($s->agency_name)
                        <div style="color:var(--text)">{{ $s->agency_name }}</div>
                        <div style="font-size:11px;color:var(--text3)">{{ $s->agency_commission_pct }}% commission</div>
                    @else
                        <span style="color:var(--text3)">—</span>
                    @endif
                </td>
                <td style="padding:10px 12px;text-align:right;color:var(--text)">{{ $s->total_streams }}</td>
                <td style="padding:10px 12px;text-align:right;color:var(--text)">{{ number_format($s->total_live_minutes) }}</td>
                <td style="padding:10px 12px;text-align:right;color:var(--text)">{{ $s->video_live_days }}</td>
                <td style="padding:10px 12px;text-align:right;color:var(--text)">{{ $s->audio_live_days }}</td>
                <td style="padding:10px 12px;text-align:right;color:#9b59b6;font-weight:600">{{ number_format($s->diamonds_earned) }}</td>
                <td style="padding:10px 12px;text-align:right;color:#27ae60">${{ number_format($s->usd_amount, 2) }}</td>
                <td style="padding:10px 12px;text-align:right;color:#e74c3c">{{ $s->agency_commission_pct > 0 ? '-$'.number_format($s->commission_usd, 2) : '—' }}</td>
                <td style="padding:10px 12px;text-align:right;color:#f39c12;font-weight:700">${{ number_format($s->net_usd, 2) }}</td>
            </tr>
            @endforeach

            {{-- Totals row --}}
            <tr style="border-top:2px solid var(--border);background:var(--bg3)">
                <td colspan="3" style="padding:12px;font-weight:700;color:var(--text)">TOTAL</td>
                <td style="padding:12px;text-align:right;font-weight:700;color:var(--text)">{{ $snapshots->sum('total_streams') }}</td>
                <td style="padding:12px;text-align:right;font-weight:700;color:var(--text)">{{ number_format($totals['minutes']) }}</td>
                <td style="padding:12px;text-align:right;font-weight:700;color:var(--text)">{{ $snapshots->sum('video_live_days') }}</td>
                <td style="padding:12px;text-align:right;font-weight:700;color:var(--text)">{{ $snapshots->sum('audio_live_days') }}</td>
                <td style="padding:12px;text-align:right;font-weight:700;color:#9b59b6">{{ number_format($totals['diamonds']) }}</td>
                <td style="padding:12px;text-align:right;font-weight:700;color:#27ae60">${{ number_format($totals['usd'], 2) }}</td>
                <td style="padding:12px;text-align:right;font-weight:700;color:#e74c3c">-${{ number_format($totals['commission'], 2) }}</td>
                <td style="padding:12px;text-align:right;font-weight:700;color:#f39c12">${{ number_format($totals['net'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endif
@endsection
