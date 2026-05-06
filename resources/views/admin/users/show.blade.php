@extends('admin.layouts.app')
@section('title', $user->username)
@section('breadcrumb', 'Users › ' . $user->username)

@section('content')
<div class="dashboard-row">
    {{-- Profile Card --}}
    <div class="card" style="flex:0 0 280px">
        <div style="text-align:center;padding:12px 0">
            <img src="{{ $user->avatar_url }}" style="width:80px;height:80px;border-radius:50%;border:2px solid var(--gold)" alt="">
            <h3 style="margin-top:12px">{{ $user->display_name ?? $user->username }}</h3>
            <p style="color:var(--text3);font-size:13px">@{{ $user->username }}</p>
            <span class="badge {{ $user->is_active ? 'badge-active' : 'badge-expired' }}" style="margin-top:6px">
                {{ $user->is_active ? 'Active' : 'Disabled' }}
            </span>
            <span class="badge badge-room" style="margin-left:4px">{{ strtoupper(str_replace('_',' ',$user->role)) }}</span>
        </div>

        <table style="width:100%;font-size:13px;margin-top:12px">
            @foreach([
                ['Email', $user->email ?? '—'],
                ['Phone', $user->phone ?? '—'],
                ['Country', $user->country_code ?? '—'],
                ['Level', "Lv. {$user->level}"],
                ['Joined', $user->created_at->format('M d, Y')],
                ['Last Seen', $user->last_seen_at?->diffForHumans() ?? '—'],
                ['Followers', number_format($user->followers_count)],
                ['Following', number_format($user->following_count)],
                ['Rooms', number_format($user->rooms_count)],
            ] as [$label, $value])
            <tr>
                <td style="color:var(--text3);padding:5px 0">{{ $label }}</td>
                <td style="text-align:right;padding:5px 0">{{ $value }}</td>
            </tr>
            @endforeach
        </table>

        <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px;text-align:center">
            <div style="background:var(--surface2);border-radius:8px;padding:10px">
                <div style="color:var(--gold);font-size:18px;font-weight:700">{{ number_format($user->coin_balance) }}</div>
                <div style="color:var(--text3);font-size:11px">Coins</div>
            </div>
            <div style="background:var(--surface2);border-radius:8px;padding:10px">
                <div style="color:var(--info);font-size:18px;font-weight:700">{{ number_format($user->diamond_balance) }}</div>
                <div style="color:var(--text3);font-size:11px">Diamonds</div>
            </div>
        </div>

        <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
            <form action="{{ route('admin.users.toggle', $user->id) }}" method="POST">
                @csrf
                <button class="btn-secondary" style="width:100%">
                    {{ $user->is_active ? 'Disable Account' : 'Enable Account' }}
                </button>
            </form>
            <form action="{{ route('admin.users.role', $user->id) }}" method="POST" style="display:flex;gap:6px">
                @csrf @method('PUT')
                <select name="role" style="flex:1;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px;border-radius:6px;font-size:12px">
                    @foreach(['user','host','moderator','admin'] as $r)
                    <option value="{{ $r }}" {{ $user->role===$r?'selected':'' }}>{{ ucfirst($r) }}</option>
                    @endforeach
                </select>
                <button class="btn-primary" style="font-size:12px;padding:6px 10px">Set</button>
            </form>
            <form action="{{ route('admin.users.coins', $user->id) }}" method="POST" style="display:flex;gap:6px">
                @csrf
                <input type="number" name="amount" placeholder="±Coins" style="flex:1;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px 8px;border-radius:6px;font-size:12px">
                <input type="hidden" name="reason" value="Admin adjustment">
                <button class="btn-primary" style="font-size:12px;padding:6px 10px">Apply</button>
            </form>
        </div>
    </div>

    <div style="flex:1;display:flex;flex-direction:column;gap:16px">
        {{-- Ban History --}}
        <div class="card">
            <div class="card-header">
                <h3>Ban History</h3>
                <button class="btn-sm btn-danger" onclick="document.getElementById('banUserId').value={{ $user->id }};document.getElementById('banModal').classList.remove('hidden')">+ New Ban</button>
            </div>
            @if($user->bans->isEmpty())
                <p style="color:var(--text3);font-size:13px">No bans on record.</p>
            @else
            <table class="admin-table">
                <thead><tr><th>Type</th><th>Reason</th><th>By</th><th>Expires</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($user->bans as $ban)
                    <tr>
                        <td><span class="badge badge-{{ $ban->type }}">{{ strtoupper($ban->type) }}</span></td>
                        <td>{{ Str::limit($ban->reason, 50) }}</td>
                        <td>{{ $ban->bannedBy?->username ?? 'System' }}</td>
                        <td class="text-muted">{{ $ban->expires_at?->format('M d, Y') ?? 'Permanent' }}</td>
                        <td><span class="badge badge-{{ $ban->is_active && !$ban->isExpired() ? 'active' : 'expired' }}">{{ $ban->status_label }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        {{-- Top Gifts --}}
        @if($topGifts->isNotEmpty())
        <div class="card">
            <div class="card-header"><h3>Top Gifts Sent</h3></div>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                @foreach($topGifts as $g)
                <div style="background:var(--surface2);border-radius:8px;padding:10px;text-align:center;min-width:80px">
                    <img src="{{ $g->gift->thumbnail_url }}" style="width:40px;height:40px;border-radius:6px" alt="">
                    <div style="font-size:12px;margin-top:4px">{{ $g->gift->name }}</div>
                    <div style="color:var(--gold);font-size:11px">×{{ $g->count }}</div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 
  Replace the "Coin History" card in resources/views/admin/users/show.blade.php
  with this entire block.
--}}

{{-- Coin Transaction History --}}
<div class="card" style="margin-top:24px">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:18px">🪙</span>
            <h3 style="margin:0">Coin Transactions</h3>
        </div>
        <div style="font-size:12px;color:var(--text3)">
            Showing: <strong>{{ \Carbon\Carbon::parse($from)->format('M d, Y') }}</strong>
            @if($from !== $to) → <strong>{{ \Carbon\Carbon::parse($to)->format('M d, Y') }}</strong> @endif
        </div>
    </div>

    {{-- Date Range Filter --}}
    <div style="padding:16px 20px;border-bottom:1px solid var(--border)">
        <form method="GET" action="{{ request()->url() }}" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
            <div>
                <label style="display:block;font-size:12px;color:var(--text3);margin-bottom:4px">From</label>
                <input type="date" name="from" value="{{ $from }}"
                       style="padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font-size:13px">
            </div>
            <div>
                <label style="display:block;font-size:12px;color:var(--text3);margin-bottom:4px">To</label>
                <input type="date" name="to" value="{{ $to }}"
                       style="padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font-size:13px">
            </div>
            <button type="submit" style="padding:8px 20px;border-radius:8px;background:var(--accent);color:#fff;border:none;cursor:pointer;font-size:13px;font-weight:600">
                🔍 Filter
            </button>
            <a href="{{ request()->url() }}"
               style="padding:8px 16px;border-radius:8px;border:1px solid var(--border);color:var(--text3);font-size:13px;text-decoration:none">
                Today
            </a>
            <a href="{{ request()->url() }}?from={{ now()->startOfWeek()->toDateString() }}&to={{ now()->toDateString() }}"
               style="padding:8px 16px;border-radius:8px;border:1px solid var(--border);color:var(--text3);font-size:13px;text-decoration:none">
                This Week
            </a>
            <a href="{{ request()->url() }}?from={{ now()->startOfMonth()->toDateString() }}&to={{ now()->toDateString() }}"
               style="padding:8px 16px;border-radius:8px;border:1px solid var(--border);color:var(--text3);font-size:13px;text-decoration:none">
                This Month
            </a>
        </form>
    </div>

    {{-- Summary Cards --}}
    @if($summary)
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--border)">
        <div style="padding:16px 20px;background:var(--bg)">
            <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px">Total Transactions</div>
            <div style="font-size:22px;font-weight:700;color:var(--text);margin-top:4px">{{ number_format($summary->total_tx) }}</div>
        </div>
        <div style="padding:16px 20px;background:var(--bg)">
            <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px">Coins Received</div>
            <div style="font-size:22px;font-weight:700;color:#27ae60;margin-top:4px">+{{ number_format($summary->total_in) }}</div>
        </div>
        <div style="padding:16px 20px;background:var(--bg)">
            <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px">Coins Spent</div>
            <div style="font-size:22px;font-weight:700;color:#e74c3c;margin-top:4px">-{{ number_format($summary->total_out) }}</div>
        </div>
    </div>
    @endif

    {{-- Transactions Table --}}
    @if($coinHistory->isEmpty())
        <div style="padding:40px;text-align:center;color:var(--text3)">
            No transactions found for this date range.
        </div>
    @else
    <div style="overflow-x:auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Reference</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach($coinHistory as $tx)
                <tr>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:6px">
                            @php
                                $icon = match(true) {
                                    str_contains($tx->type, 'gift')       => '🎁',
                                    str_contains($tx->type, 'game')       => '🎮',
                                    str_contains($tx->type, 'purchase')   => '💳',
                                    str_contains($tx->type, 'admin')      => '⚙️',
                                    str_contains($tx->type, 'reward')     => '🏆',
                                    str_contains($tx->type, 'refund')     => '↩️',
                                    default                                => '🪙',
                                };
                            @endphp
                            {{ $icon }}
                            {{ ucwords(str_replace('_', ' ', $tx->type)) }}
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:600;color:{{ $tx->amount >= 0 ? '#27ae60' : '#e74c3c' }}">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount) }}
                        </span>
                    </td>
                    <td style="color:var(--text3)">{{ number_format($tx->balance_after) }}</td>
                    <td style="font-size:12px;color:var(--text3);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        title="{{ $tx->reference }}">
                        {{ Str::limit($tx->reference ?? '—', 40) }}
                    </td>
                    <td style="color:var(--text3);white-space:nowrap;font-size:13px">
                        {{ $tx->created_at->format('M d, Y') }}<br>
                        <span style="font-size:11px">{{ $tx->created_at->format('h:i A') }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($coinHistory->hasPages())
    <div style="padding:16px 20px;border-top:1px solid var(--border)">
        {{ $coinHistory->links() }}
    </div>
    @endif
    @endif
</div>
    </div>
</div>

{{-- Quick Ban Modal --}}
<div id="banModal" class="modal hidden">
    <div class="modal-content">
        <h3>🚫 Ban {{ $user->username }}</h3>
        <form action="{{ route('admin.bans.store') }}" method="POST">
            @csrf
            <input type="hidden" name="user_id" id="banUserId" value="{{ $user->id }}">
            <div class="form-group"><label>Type</label>
                <select name="type" onchange="document.getElementById('roomIdField').style.display=this.value==='room'?'block':'none'">
                    <option value="global">Global</option><option value="live">Live</option>
                    <option value="chat">Chat</option><option value="room">Room</option>
                </select>
            </div>
            <div class="form-group" id="roomIdField" style="display:none">
                <label>Room ID</label><input type="text" name="room_id" placeholder="Room UUID">
            </div>
            <div class="form-group"><label>Duration</label>
                <select name="duration">
                    <option value="1h">1 Hour</option><option value="3h">3 Hours</option>
                    <option value="1d">1 Day</option><option value="7d">7 Days</option>
                    <option value="30d">30 Days</option><option value="permanent">Permanent</option>
                </select>
            </div>
            <div class="form-group"><label>Reason *</label><input type="text" name="reason" required placeholder="Reason for ban"></div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('banModal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-danger">Apply Ban</button>
            </div>
        </form>
    </div>
</div>
@endsection
