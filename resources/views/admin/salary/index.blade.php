@extends('admin.layouts.app')
@section('title', 'Salary Sheet')
@section('breadcrumb', 'Salary Sheet')

@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <h2>💰 Salary Sheet</h2>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
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

        {{-- Download full CSV --}}
        @if($selected)
        <form method="GET" action="{{ route('admin.salary.download') }}">
            <input type="hidden" name="period" value="{{ $selected }}">
            <button type="submit" class="btn-primary" style="display:flex;align-items:center;gap:6px">
                ⬇️ All Agencies CSV
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
        <div>No salary data available yet.</div>
        <div style="font-size:13px;margin-top:8px">Data is captured on the 1st of each month at midnight.</div>
    </div>
@else

{{-- Summary cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:24px">
    @foreach([
        ['Hosts',       $totals['hosts'],      'var(--text)',  ''],
        ['Agencies',    $byAgency->count(),    '#9b59b6',     ''],
        ['Diamonds',    number_format($totals['diamonds']), '#9b59b6', '💎'],
        ['Gross USD',   '$'.number_format($totals['usd'],2), '#27ae60',''],
        ['Commission',  '$'.number_format($totals['commission'],2), '#e74c3c',''],
        ['Net Payable', '$'.number_format($totals['net'],2), '#f39c12', ''],
    ] as [$label, $val, $color, $icon])
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:14px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase">{{ $label }}</div>
        <div style="font-size:22px;font-weight:700;color:{{ $color }};margin-top:4px">{{ $icon }}{{ $val }}</div>
    </div>
    @endforeach
</div>

{{-- Grouped by Agency --}}
@foreach($byAgency as $agencyName => $agencySnapshots)
@php
    $agencyTotals = [
        'diamonds' => $agencySnapshots->sum('diamonds_earned'),
        'usd'      => $agencySnapshots->sum('usd_amount'),
        'commission'=> $agencySnapshots->sum('commission_usd'),
        'net'      => $agencySnapshots->sum('net_usd'),
        'minutes'  => $agencySnapshots->sum('total_live_minutes'),
    ];
@endphp

<div style="margin-bottom:28px">
    {{-- Agency header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px">
        <div>
            <h3 style="color:var(--gold);font-size:15px">
                🏢 {{ $agencyName }}
                <span style="color:var(--text3);font-size:12px;font-weight:400;margin-left:8px">
                    {{ $agencySnapshots->count() }} hosts
                    · 💎{{ number_format($agencyTotals['diamonds']) }}
                    · Net ${{ number_format($agencyTotals['net'], 2) }}
                </span>
            </h3>
        </div>
        {{-- Per-agency download --}}
        <form method="GET" action="{{ route('admin.salary.download_agency') }}">
            <input type="hidden" name="period" value="{{ $selected }}">
            <input type="hidden" name="agency_name" value="{{ $agencyName }}">
            <button type="submit"
                    style="padding:6px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg3);color:var(--text3);cursor:pointer;font-size:12px">
                ⬇️ Download {{ $agencyName }}
            </button>
        </form>
    </div>

    {{-- Agency table --}}
    <div style="overflow-x:auto;border:1px solid var(--border);border-radius:10px">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:var(--bg3);border-bottom:1px solid var(--border)">
                    <th style="padding:8px 12px;text-align:left;color:var(--text3)">#</th>
                    <th style="padding:8px 12px;text-align:left;color:var(--text3)">Host</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--text3)">Streams</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--text3)">Mins</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--text3)">V.Days</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--text3)">A.Days</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--text3)">💎</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--text3)">Gross</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--text3)">Comm.</th>
                    <th style="padding:8px 12px;text-align:right;color:var(--text3)">Net</th>
                </tr>
            </thead>
            <tbody>
                @foreach($agencySnapshots as $i => $s)
                <tr style="border-bottom:1px solid var(--border);{{ $s->net_usd > 0 ? '' : 'opacity:.5' }}">
                    <td style="padding:8px 12px;color:var(--text3)">{{ $i + 1 }}</td>
                    <td style="padding:8px 12px">
                        <div style="font-weight:600;color:var(--text)">{{ $s->display_name ?? $s->username }}</div>
                        <div style="font-size:10px;color:var(--text3)">@{{ $s->username }}{{ $s->phone ? ' · '.$s->phone : '' }}</div>
                    </td>
                    <td style="padding:8px 12px;text-align:right">{{ $s->total_streams }}</td>
                    <td style="padding:8px 12px;text-align:right">{{ number_format($s->total_live_minutes) }}</td>
                    <td style="padding:8px 12px;text-align:right">{{ $s->video_live_days }}</td>
                    <td style="padding:8px 12px;text-align:right">{{ $s->audio_live_days }}</td>
                    <td style="padding:8px 12px;text-align:right;color:#9b59b6;font-weight:600">{{ number_format($s->diamonds_earned) }}</td>
                    <td style="padding:8px 12px;text-align:right;color:#27ae60">${{ number_format($s->usd_amount,2) }}</td>
                    <td style="padding:8px 12px;text-align:right;color:#e74c3c">{{ $s->agency_commission_pct > 0 ? '-$'.number_format($s->commission_usd,2) : '—' }}</td>
                    <td style="padding:8px 12px;text-align:right;color:#f39c12;font-weight:700">${{ number_format($s->net_usd,2) }}</td>
                </tr>
                @endforeach

                {{-- Agency subtotal --}}
                <tr style="background:var(--bg3);font-weight:700;border-top:2px solid var(--border)">
                    <td colspan="2" style="padding:8px 12px;color:var(--text)">Subtotal</td>
                    <td style="padding:8px 12px;text-align:right">{{ $agencySnapshots->sum('total_streams') }}</td>
                    <td style="padding:8px 12px;text-align:right">{{ number_format($agencyTotals['minutes']) }}</td>
                    <td style="padding:8px 12px;text-align:right">{{ $agencySnapshots->sum('video_live_days') }}</td>
                    <td style="padding:8px 12px;text-align:right">{{ $agencySnapshots->sum('audio_live_days') }}</td>
                    <td style="padding:8px 12px;text-align:right;color:#9b59b6">{{ number_format($agencyTotals['diamonds']) }}</td>
                    <td style="padding:8px 12px;text-align:right;color:#27ae60">${{ number_format($agencyTotals['usd'],2) }}</td>
                    <td style="padding:8px 12px;text-align:right;color:#e74c3c">-${{ number_format($agencyTotals['commission'],2) }}</td>
                    <td style="padding:8px 12px;text-align:right;color:#f39c12">${{ number_format($agencyTotals['net'],2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endforeach

@endif
@endsection