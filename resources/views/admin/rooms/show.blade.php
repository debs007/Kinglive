@extends('admin.layouts.app')
@section('title', 'Room Detail')
@section('breadcrumb', 'Live › Rooms › Detail')

@section('content')
<div class="page-header">
    <h2>{{ Str::limit($room->title, 60) }}</h2>
    <div class="action-btns">
        @if($room->status === 'live')
        <form action="{{ route('admin.rooms.end', $room->id) }}" method="POST"
              onsubmit="return confirm('End this stream?')">
            @csrf
            <button class="btn-danger">End Stream</button>
        </form>
        @endif
        <a href="{{ route('admin.rooms.index') }}" class="btn-secondary">← Back</a>
    </div>
</div>

<div class="dashboard-row">
    {{-- Room Info --}}
    <div class="card" style="flex:0 0 280px">
        @if($room->thumbnail_url)
        <img src="{{ $room->thumbnail_url }}" style="width:100%;border-radius:8px;margin-bottom:12px;aspect-ratio:16/9;object-fit:cover" alt="">
        @endif
        <table style="width:100%;font-size:13px">
            @foreach([
                ['Host', '<a href="'.route('admin.users.show',$room->host_user_id).'">'.$room->host->username.'</a>'],
                ['Type', '<span class="badge badge-'.$room->type.'">'.strtoupper($room->type).'</span>'],
                ['Status', '<span class="badge badge-'.($room->status==='live'?'active':'expired').'">'.strtoupper($room->status).'</span>'],
                ['Viewers', number_format($room->viewer_count)],
                ['Peak Viewers', number_format($room->peak_viewer_count)],
                ['Total Gifts', number_format($room->total_gifts_received).' coins'],
                ['Seats', $room->seat_count],
                ['Started', $room->started_at?->format('M d, Y H:i') ?? '—'],
                ['Duration', $room->started_at ? $room->started_at->diffForHumans($room->ended_at ?? now(), true) : '—'],
            ] as [$label, $value])
            <tr>
                <td style="color:var(--text3);padding:6px 0;vertical-align:middle">{{ $label }}</td>
                <td style="text-align:right;padding:6px 0">{!! $value !!}</td>
            </tr>
            @endforeach
        </table>
    </div>

    <div style="flex:1;display:flex;flex-direction:column;gap:16px">
        {{-- Top Gifters --}}
        <div class="card">
            <div class="card-header"><h3>Top Gifters</h3></div>
            @if($topGifters->isEmpty())
                <p style="color:var(--text3);font-size:13px">No gifts in this room.</p>
            @else
            <table class="admin-table">
                <thead><tr><th>Rank</th><th>User</th><th>Total Coins</th></tr></thead>
                <tbody>
                    @foreach($topGifters as $i => $g)
                    <tr>
                        <td style="color:{{ $i===0?'var(--gold)':($i===1?'#C0C0C0':($i===2?'#CD7F32':'var(--text3)')) }};font-weight:700">#{{ $i+1 }}</td>
                        <td>
                            <div class="user-cell">
                                <img src="{{ $g->sender->avatar_url }}" class="avatar-sm" alt="">
                                <a href="{{ route('admin.users.show', $g->sender_id) }}">{{ $g->sender->username }}</a>
                            </div>
                        </td>
                        <td class="text-amber">🪙 {{ number_format($g->total) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        {{-- Recent Gifts --}}
        <div class="card">
            <div class="card-header"><h3>Recent Gift Transactions</h3></div>
            <table class="admin-table">
                <thead><tr><th>From</th><th>Gift</th><th>Qty</th><th>Coins</th><th>Time</th></tr></thead>
                <tbody>
                    @foreach($room->giftTransactions as $tx)
                    <tr>
                        <td>
                            <div class="user-cell">
                                <img src="{{ $tx->sender->avatar_url }}" class="avatar-sm" alt="">
                                {{ $tx->sender->username }}
                            </div>
                        </td>
                        <td>{{ $tx->gift->name }}</td>
                        <td>×{{ $tx->quantity }}</td>
                        <td class="text-amber">{{ number_format($tx->coin_total) }}</td>
                        <td class="text-muted">{{ $tx->created_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
