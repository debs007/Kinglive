@extends('admin.layouts.app')
@section('title', 'Gift Report')
@section('breadcrumb', 'Economy › Gift Reports')

@section('content')
<div class="page-header">
    <h2>Gift Report</h2>
    <form method="GET" class="filter-form">
        <input type="date" name="from" value="{{ $from->toDateString() }}">
        <span style="color:var(--text3)">to</span>
        <input type="date" name="to" value="{{ $to->toDateString() }}">
        <button type="submit" class="btn-primary">Apply</button>
        <a href="{{ route('admin.gifts.export', request()->query()) }}" class="btn-secondary">Export CSV</a>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card purple"><div class="label">Transactions</div><div class="value">{{ number_format($summary['total_transactions']) }}</div></div>
    <div class="stat-card amber"><div class="label">Total Coins Sent</div><div class="value">{{ number_format($summary['total_coins']) }}</div></div>
    <div class="stat-card blue"><div class="label">Diamonds Given</div><div class="value">{{ number_format($summary['total_diamonds']) }}</div></div>
    <div class="stat-card green"><div class="label">Unique Senders</div><div class="value">{{ number_format($summary['unique_senders']) }}</div></div>
</div>

<div class="card">
    <div class="card-header"><h3>Daily Volume</h3></div>
    <canvas id="dailyChart" height="100"></canvas>
</div>

<div class="dashboard-row">
    <div class="card flex-1">
        <div class="card-header"><h3>Top Gifts</h3></div>
        <table class="admin-table">
            <thead><tr><th>Gift</th><th>Sent</th><th>Coins</th></tr></thead>
            <tbody>
                @foreach($topGifts as $g)
                <tr>
                    <td>
                        <div class="user-cell">
                            <img src="{{ $g->gift->thumbnail_url }}" style="width:32px;height:32px;border-radius:6px" alt="">
                            {{ $g->gift->name }}
                        </div>
                    </td>
                    <td>{{ number_format($g->total_sent) }}</td>
                    <td class="text-amber">{{ number_format($g->total_coins) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card flex-1">
        <div class="card-header"><h3>Top Senders</h3></div>
        <table class="admin-table">
            <thead><tr><th>User</th><th>Gifts</th><th>Coins</th></tr></thead>
            <tbody>
                @foreach($topSenders as $s)
                <tr>
                    <td>
                        <div class="user-cell">
                            <img src="{{ $s->sender->avatar_url }}" class="avatar-sm" alt="">
                            <a href="{{ route('admin.users.show', $s->sender_id) }}">{{ $s->sender->username }}</a>
                        </div>
                    </td>
                    <td>{{ number_format($s->count) }}</td>
                    <td class="text-amber">{{ number_format($s->total_coins) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card flex-1">
        <div class="card-header"><h3>Top Receivers</h3></div>
        <table class="admin-table">
            <thead><tr><th>User</th><th>Gifts</th><th>Diamonds</th></tr></thead>
            <tbody>
                @foreach($topReceivers as $r)
                <tr>
                    <td>
                        <div class="user-cell">
                            <img src="{{ $r->receiver->avatar_url }}" class="avatar-sm" alt="">
                            <a href="{{ route('admin.users.show', $r->receiver_id) }}">{{ $r->receiver->username }}</a>
                        </div>
                    </td>
                    <td>{{ number_format($r->count) }}</td>
                    <td class="text-blue">{{ number_format($r->total_diamonds) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script>
new Chart(document.getElementById('dailyChart'),{
    type:'bar',
    data:{
        labels:@json($daily->pluck('date')),
        datasets:[
            {label:'Coins',data:@json($daily->pluck('coins')),backgroundColor:'rgba(255,215,0,.7)',borderRadius:4,yAxisID:'y'},
            {label:'Transactions',data:@json($daily->pluck('transactions')),type:'line',borderColor:'#9B59B6',tension:.4,yAxisID:'y1'}
        ]
    },
    options:{responsive:true,scales:{
        y:{position:'left',ticks:{color:'#6a5f80'}},
        y1:{position:'right',grid:{drawOnChartArea:false},ticks:{color:'#6a5f80'}}
    },plugins:{legend:{labels:{color:'#a89bc0'}}}}
});
</script>
@endpush
