@extends('admin.layouts.app')
@section('title', 'Live Rooms')
@section('breadcrumb', 'Live › Rooms')

@section('content')
<div class="page-header">
    <h2>Live Rooms</h2>
    <span class="live-badge" id="liveCount">● {{ $liveCount }} LIVE</span>
</div>

<div class="card">
    <div class="card-header">
        <h3>Rooms</h3>
        <form method="GET" class="filter-form">
            <select name="type">
                <option value="">All Types</option>
                @foreach(['video','audio','audio_board','pk'] as $t)
                <option value="{{ $t }}" {{ request('type')===$t?'selected':'' }}>{{ strtoupper($t) }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="" {{ !request('status')?'selected':'' }}>Live</option>
                <option value="ended" {{ request('status')==='ended'?'selected':'' }}>Ended</option>
            </select>
            <button type="submit" class="btn-primary">Filter</button>
        </form>
    </div>

    <table class="admin-table">
        <thead>
            <tr><th>Host</th><th>Title</th><th>Type</th><th>Viewers</th><th>Gifts</th><th>Duration</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse($rooms as $room)
            <tr>
                <td>
                    <div class="user-cell">
                        <img src="{{ $room->host->avatar_url }}" class="avatar-sm" alt="">
                        <a href="{{ route('admin.users.show', $room->host_user_id) }}">{{ $room->host->username }}</a>
                    </div>
                </td>
                <td>{{ Str::limit($room->title, 40) }}</td>
                <td><span class="badge badge-{{ $room->type }}">{{ strtoupper($room->type) }}</span></td>
                <td>👁 {{ number_format($room->viewer_count) }}</td>
                <td>🎁 {{ number_format($room->total_gifts_received) }}</td>
                <td class="text-muted">{{ $room->started_at?->diffForHumans() }}</td>
                <td>
                    <div class="action-btns">
                        <a href="{{ route('admin.rooms.show', $room->id) }}" class="btn-sm btn-info">View</a>
                        @if($room->status === 'live')
                        <form action="{{ route('admin.rooms.end', $room->id) }}" method="POST" style="display:inline"
                              onsubmit="return confirm('End this stream?')">
                            @csrf
                            <button class="btn-sm btn-danger">End</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text3)">No rooms found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px">{{ $rooms->withQueryString()->links() }}</div>
</div>
@endsection
@push('scripts')
<script>
setInterval(()=>{
    fetch('{{ route('admin.api.live-rooms') }}').then(r=>r.json()).then(d=>{
        document.getElementById('liveCount').textContent=`● ${d.count} LIVE`;
    });
},20000);
</script>
@endpush
