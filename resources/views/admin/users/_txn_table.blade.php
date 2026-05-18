{{-- Reusable transaction table partial --}}
{{-- Variables: $rows (paginator), $type ('coin'|'diamond'), $page_param --}}

@if($rows->isEmpty())
    <p style="color:var(--text3);font-size:13px;text-align:center;padding:20px 0">No transactions found.</p>
@else
<table style="width:100%;font-size:12px;border-collapse:collapse">
    <thead>
        <tr style="border-bottom:1px solid var(--border)">
            <th style="text-align:left;padding:8px 6px;color:var(--text3)">Date</th>
            <th style="text-align:left;padding:8px 6px;color:var(--text3)">Type</th>
            <th style="text-align:right;padding:8px 6px;color:var(--text3)">Amount</th>
            <th style="text-align:right;padding:8px 6px;color:var(--text3)">Balance After</th>
            <th style="text-align:left;padding:8px 6px;color:var(--text3)">Reference</th>
        </tr>
    </thead>
    <tbody>
    @foreach($rows as $txn)
    <tr style="border-bottom:1px solid var(--border)"
        onmouseover="this.style.background='var(--surface2)'"
        onmouseout="this.style.background=''">
        <td style="padding:8px 6px;white-space:nowrap;color:var(--text3)">
            {{ $txn->created_at->format('M d, Y H:i') }}
        </td>
        <td style="padding:8px 6px">
            <span style="background:var(--surface2);padding:2px 8px;border-radius:10px;font-size:11px">
                {{ str_replace('_', ' ', ucfirst($txn->type)) }}
            </span>
        </td>
        <td style="padding:8px 6px;text-align:right;font-weight:700;
            color:{{ $txn->amount >= 0 ? 'var(--success)' : 'var(--danger)' }}">
            {{ $txn->amount >= 0 ? '+' : '' }}{{ number_format($txn->amount) }}
            {{ $type === 'coin' ? '🪙' : '💎' }}
        </td>
        <td style="padding:8px 6px;text-align:right;color:var(--text3)">
            {{ number_format($txn->balance_after ?? 0) }}
        </td>
        <td style="padding:8px 6px;color:var(--text3);font-size:11px;
            max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
            title="{{ $txn->reference }}">
            {{ $txn->reference ?? '—' }}
        </td>
    </tr>
    @endforeach
    </tbody>
</table>
<div style="margin-top:12px;display:flex;align-items:center;gap:8px;font-size:12px">
    @if($rows->onFirstPage())
        <span style="padding:5px 12px;border-radius:6px;background:var(--bg3);color:var(--text3)">← Prev</span>
    @else
        <a href="{{ $rows->previousPageUrl() }}&{{ http_build_query(request()->except('page')) }}"
           style="padding:5px 12px;border-radius:6px;background:var(--surface2);color:var(--text);text-decoration:none;border:1px solid var(--border)">← Prev</a>
    @endif
    <span style="color:var(--text3)">Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</span>
    @if($rows->hasMorePages())
        <a href="{{ $rows->nextPageUrl() }}&{{ http_build_query(request()->except('page')) }}"
           style="padding:5px 12px;border-radius:6px;background:var(--surface2);color:var(--text);text-decoration:none;border:1px solid var(--border)">Next →</a>
    @else
        <span style="padding:5px 12px;border-radius:6px;background:var(--bg3);color:var(--text3)">Next →</span>
    @endif
</div>
@endif