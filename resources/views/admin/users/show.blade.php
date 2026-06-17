@extends('admin.layouts.app')
@section('title', $user->username)
@section('breadcrumb', 'Users › ' . $user->username)

@section('content')
<div class="dashboard-row">

    {{-- ── Profile Card ──────────────────────────────────────────── --}}
    <div class="card" style="flex:0 0 280px">
        <div style="text-align:center;padding:12px 0">
            <img src="{{ $user->avatar_url }}" style="width:80px;height:80px;border-radius:50%;border:2px solid var(--gold)" alt="">
            <h3 style="margin-top:12px">{{ $user->display_name ?? $user->username }}</h3>
            <p style="color:var(--text3);font-size:13px">Id: {{ 100000+$user->id }}</p>
            <span class="badge {{ $user->is_active ? 'badge-active' : 'badge-expired' }}" style="margin-top:6px">
                {{ $user->is_active ? 'Active' : 'Disabled' }}
            </span>
            <span class="badge badge-room" style="margin-left:4px">{{ strtoupper(str_replace('_',' ',$user->role)) }}</span>
        </div>

        <table style="width:100%;font-size:13px;margin-top:12px">
            @php $profileRows = [
                ['Email',       $user->email ?? '—'],
                ['Phone',       $user->phone ?? '—'],
                ['Country',     $user->country_code ?? '—'],
                ['Level',       'Lv. '.$user->level],
                ['Total Sent',  number_format($user->total_coins_sent ?? 0).' coins'],
                ['Joined',      $user->created_at->format('M d, Y')],
                ['Last Seen',   $user->last_seen_at?->diffForHumans() ?? '—'],
                ['Followers',   number_format($user->followers_count)],
                ['Following',   number_format($user->following_count)],
                ['Total Lives', number_format($user->rooms_count)],
                ['Live Mins',   number_format($user->total_live_minutes ?? 0)],
                ['Video Days',  $user->video_live_days ?? 0],
                ['Audio Days',  $user->audio_live_days ?? 0],
            ]; @endphp
            @foreach($profileRows as $row)
            <tr>
                <td style="color:var(--text3);padding:5px 0">{{ $row[0] }}</td>
                <td style="text-align:right;padding:5px 0;font-weight:600">{{ $row[1] }}</td>
            </tr>
            @endforeach
        </table>

        <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px;text-align:center">
            <div style="background:var(--surface2);border-radius:8px;padding:10px">
                <div style="color:var(--gold);font-size:18px;font-weight:700">{{ number_format($user->coin_balance) }}</div>
                <div style="color:var(--text3);font-size:11px">Coins 🪙</div>
            </div>
            <div style="background:var(--surface2);border-radius:8px;padding:10px">
                <div style="color:var(--info);font-size:18px;font-weight:700">{{ number_format($user->diamond_balance) }}</div>
                <div style="color:var(--text3);font-size:11px">Diamonds 💎</div>
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

    {{-- ── Right Panel ─────────────────────────────────────────────── --}}
    <div style="flex:1;display:flex;flex-direction:column;gap:20px">

        {{-- ── Coin Transactions ──────────────────────────────────── --}}
        <div class="card">
            <div class="card-header"><h3>🪙 Coin Transactions</h3></div>

            {{-- Date filter --}}
            <form method="GET" action="{{ url()->current() }}" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap">
                <div>
                    <label style="color:var(--text3);font-size:11px;display:block;margin-bottom:4px">From</label>
                    <input type="date" name="coin_from" value="{{ request('coin_from') }}"
                        style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:12px">
                </div>
                <div>
                    <label style="color:var(--text3);font-size:11px;display:block;margin-bottom:4px">To</label>
                    <input type="date" name="coin_to" value="{{ request('coin_to') }}"
                        style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:12px">
                </div>
                {{-- preserve other filters --}}
                @foreach(request()->except(['coin_from','coin_to','coin_recharge_page','coin_gift_page','coin_game_page','coin_exchange_page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <button type="submit" class="btn-primary" style="font-size:12px;padding:7px 16px">Filter</button>
                @if(request('coin_from') || request('coin_to'))
                    <a href="{{ url()->current() }}" class="btn-secondary" style="font-size:12px;padding:7px 12px">Clear</a>
                @endif
                @if(request('coin_from') || request('coin_to'))
                    <span style="color:var(--text3);font-size:11px;align-self:center">
                        {{ request('coin_from','—') }} → {{ request('coin_to','—') }}
                    </span>
                @endif
            </form>

            {{-- Tabs --}}
            <div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:0">
                @php $coinTabs = [
                    ['recharge', '💳 Recharge', $coinRecharge->total()],
                    ['gifting',  '🎁 Gifting',  $coinGifting->total()],
                    ['games',    '🎮 Games',     $coinGames->total()],
                    ['exchange', '🔄 From Diamonds', $coinExchange->total()],
                ]; @endphp
                @foreach($coinTabs as $i => $ctab)
                <button
                    onclick="switchCoinTab('{{ $ctab[0] }}')"
                    id="coin-tab-{{ $ctab[0] }}"
                    style="padding:8px 16px;font-size:12px;border:none;background:none;cursor:pointer;
                           border-bottom:2px solid {{ $i === 0 ? 'var(--gold)' : 'transparent' }};
                           color:{{ $i === 0 ? 'var(--gold)' : 'var(--text3)' }}">
                    {{ $ctab[1] }} <span style="color:var(--text3)">({{ number_format($ctab[2]) }})</span>
                </button>
                @endforeach
            </div>

            {{-- Recharge --}}
            <div id="coin-pane-recharge">
                @include('admin.users._txn_table', ['rows' => $coinRecharge, 'type' => 'coin', 'page_param' => 'coin_recharge_page'])
            </div>
            {{-- Gifting --}}
            <div id="coin-pane-gifting" style="display:none">
                @include('admin.users._txn_table', ['rows' => $coinGifting, 'type' => 'coin', 'page_param' => 'coin_gift_page'])
            </div>
            {{-- Games --}}
            <div id="coin-pane-games" style="display:none">
                @include('admin.users._txn_table', ['rows' => $coinGames, 'type' => 'coin', 'page_param' => 'coin_game_page'])
            </div>
            {{-- Exchange (coins received from diamonds) --}}
            <div id="coin-pane-exchange" style="display:none">
                @include('admin.users._txn_table', ['rows' => $coinExchange, 'type' => 'coin', 'page_param' => 'coin_exchange_page'])
            </div>
        </div>

        {{-- ── Diamond Transactions ───────────────────────────────── --}}
        <div class="card">
            <div class="card-header"><h3>💎 Diamond Transactions</h3></div>

            <form method="GET" action="{{ url()->current() }}" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap">
                <div>
                    <label style="color:var(--text3);font-size:11px;display:block;margin-bottom:4px">From</label>
                    <input type="date" name="diamond_from" value="{{ request('diamond_from') }}"
                        style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:12px">
                </div>
                <div>
                    <label style="color:var(--text3);font-size:11px;display:block;margin-bottom:4px">To</label>
                    <input type="date" name="diamond_to" value="{{ request('diamond_to') }}"
                        style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:12px">
                </div>
                @foreach(request()->except(['diamond_from','diamond_to','diamond_gift_page','diamond_reward_page','diamond_exchange_page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <button type="submit" class="btn-primary" style="font-size:12px;padding:7px 16px">Filter</button>
                @if(request('diamond_from') || request('diamond_to'))
                    <a href="{{ url()->current() }}" class="btn-secondary" style="font-size:12px;padding:7px 12px">Clear</a>
                @endif
            </form>

            <div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--border)">
                @php $dTabs = [
                    ['dgifts',   '🎁 Gift Received', $diamondGifts->total()],
                    ['drewards', '🏆 Daily Rewards',  $diamondRewards->total()],
                    ['dexchange','🔄 To Coins',       $diamondExchange->total()],
                ]; @endphp
                @foreach($dTabs as $i => $dtab)
                <button
                    onclick="switchDiamondTab('{{ $dtab[0] }}')"
                    id="diamond-tab-{{ $dtab[0] }}"
                    style="padding:8px 16px;font-size:12px;border:none;background:none;cursor:pointer;
                           border-bottom:2px solid {{ $i === 0 ? 'var(--info)' : 'transparent' }};
                           color:{{ $i === 0 ? 'var(--info)' : 'var(--text3)' }}">
                    {{ $dtab[1] }} <span style="color:var(--text3)">({{ number_format($dtab[2]) }})</span>
                </button>
                @endforeach
            </div>

            <div id="diamond-pane-dgifts">
                @include('admin.users._txn_table', ['rows' => $diamondGifts, 'type' => 'diamond', 'page_param' => 'diamond_gift_page'])
            </div>
            <div id="diamond-pane-drewards" style="display:none">
                @include('admin.users._txn_table', ['rows' => $diamondRewards, 'type' => 'diamond', 'page_param' => 'diamond_reward_page'])
            </div>
            <div id="diamond-pane-dexchange" style="display:none">
                @include('admin.users._txn_table', ['rows' => $diamondExchange, 'type' => 'diamond', 'page_param' => 'diamond_exchange_page'])
            </div>
        </div>

        {{-- ── Past Lives ─────────────────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h3>📺 Past Lives</h3>
                <span style="color:var(--text3);font-size:12px">{{ $pastLives->total() }} sessions total</span>
            </div>

            <form method="GET" action="{{ url()->current() }}" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap">
                <div>
                    <label style="color:var(--text3);font-size:11px;display:block;margin-bottom:4px">From</label>
                    <input type="date" name="live_from" value="{{ request('live_from') }}"
                        style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:12px">
                </div>
                <div>
                    <label style="color:var(--text3);font-size:11px;display:block;margin-bottom:4px">To</label>
                    <input type="date" name="live_to" value="{{ request('live_to') }}"
                        style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:12px">
                </div>
                @foreach(request()->except(['live_from','live_to','lives_page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <button type="submit" class="btn-primary" style="font-size:12px;padding:7px 16px">Filter</button>
                @if(request('live_from') || request('live_to'))
                    <a href="{{ url()->current() }}" class="btn-secondary" style="font-size:12px;padding:7px 12px">Clear</a>
                    <span style="color:var(--text3);font-size:11px;align-self:center">
                        {{ request('live_from','—') }} → {{ request('live_to','—') }}
                    </span>
                @endif
            </form>

            @if($pastLives->isEmpty())
                <p style="color:var(--text3);font-size:13px;text-align:center;padding:24px">No live sessions found.</p>
            @else
            <table style="width:100%;font-size:12px;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['Date','Type','Title','Duration','Viewers','Diamonds','Reward','Status'] as $h)
                        <th style="text-align:left;padding:8px 6px;color:var(--text3);font-weight:600">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($pastLives as $live)
                <tr style="border-bottom:1px solid var(--border);transition:background .15s" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
                    <td style="padding:10px 6px;white-space:nowrap">
                        {{ $live->started_at?->format('M d, Y') ?? '—' }}<br>
                        <span style="color:var(--text3);font-size:11px">{{ $live->started_at?->format('H:i') ?? '' }}</span>
                    </td>
                    <td style="padding:10px 6px">
                        <span style="background:var(--surface2);padding:2px 8px;border-radius:10px;font-size:11px">
                            {{ $live->type === 'audio_board' ? '🎤 Audio Party' : ($live->type === 'video' ? '📹 Video' : '🔊 Audio') }}
                        </span>
                    </td>
                    <td style="padding:10px 6px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $live->title ?? '—' }}
                    </td>
                    <td style="padding:10px 6px">
                        @php
                            $h = intdiv($live->duration_mins, 60);
                            $m = $live->duration_mins % 60;
                        @endphp
                        <span style="{{ $live->duration_mins >= 40 ? 'color:var(--success)' : '' }}">
                            {{ $h > 0 ? "{$h}h {$m}m" : "{$m}m" }}
                        </span>
                    </td>
                    <td style="padding:10px 6px">{{ number_format($live->viewer_count ?? 0) }}</td>
                    <td style="padding:10px 6px">{{ number_format($live->total_gifts_received ?? 0) }} 💎</td>
                    <td style="padding:10px 6px">
                        @if(! $live->reward_eligible)
                            <span style="color:var(--text3);font-size:11px">
                                {{ $live->type !== 'video' ? 'Not video' : '< 40 min' }}
                            </span>
                        @elseif($live->reward_collected)
                            <span style="color:var(--success);font-weight:600">✅ +5,000 💎</span>
                        @elseif($live->show_credit_btn)
                            <button
                                id="reward-btn-{{ $live->id }}"
                                onclick="creditReward({{ $user->id }}, '{{ $live->live_date }}', '{{ $live->id }}')"
                                style="padding:4px 10px;font-size:11px;border-radius:6px;border:none;
                                       background:#f39c12;color:#000;cursor:pointer;font-weight:600">
                                💎 Credit 5K
                            </button>
                        @else
                            <span style="color:var(--text3);font-size:11px" title="Reward credited on another session this day">—</span>
                        @endif
                    </td>
                    <td style="padding:10px 6px">
                        <span class="badge {{ $live->status === 'live' ? 'badge-active' : 'badge-expired' }}">
                            {{ strtoupper($live->status) }}
                        </span>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
            <div style="margin-top:12px;display:flex;align-items:center;gap:8px;font-size:12px">
                @if($pastLives->onFirstPage())
                    <span style="padding:5px 12px;border-radius:6px;background:var(--bg3);color:var(--text3)">← Prev</span>
                @else
                    <a href="{{ $pastLives->previousPageUrl() }}&{{ http_build_query(request()->all()) }}"
                       style="padding:5px 12px;border-radius:6px;background:var(--surface2);color:var(--text);text-decoration:none;border:1px solid var(--border)">← Prev</a>
                @endif
                <span style="color:var(--text3)">Page {{ $pastLives->currentPage() }} of {{ $pastLives->lastPage() }}</span>
                @if($pastLives->hasMorePages())
                    <a href="{{ $pastLives->nextPageUrl() }}&{{ http_build_query(request()->all()) }}"
                       style="padding:5px 12px;border-radius:6px;background:var(--surface2);color:var(--text);text-decoration:none;border:1px solid var(--border)">Next →</a>
                @else
                    <span style="padding:5px 12px;border-radius:6px;background:var(--bg3);color:var(--text3)">Next →</span>
                @endif
            </div>
            @endif
        </div>



        {{-- ── User Frames ─────────────────────────────────────────────── --}}
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <h3>🖼️ Avatar Frames</h3>
                <button onclick="document.getElementById('giveFrameModal').classList.remove('hidden')"
                        class="btn-primary" style="font-size:12px;padding:6px 12px">+ Give Frame</button>
            </div>

            <div id="userFramesList">
                <p style="color:var(--text3);font-size:13px">Loading...</p>
            </div>
        </div>

        {{-- Give Frame Modal --}}
        <div id="giveFrameModal" class="hidden modal-overlay">
            <div class="modal-box" style="max-width:400px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                    <h3>Give Frame</h3>
                    <button onclick="document.getElementById('giveFrameModal').classList.add('hidden')"
                            style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:20px">✕</button>
                </div>
                <select id="frameSelect"
                        style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px;margin-bottom:12px">
                    <option value="">Loading frames...</option>
                </select>
                <button onclick="giveFrame()" class="btn-primary" style="width:100%">Give Frame</button>
            </div>
        </div>

        {{-- ── DM Messages ──────────────────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h3>💬 Messages</h3>
                <span style="color:var(--text3);font-size:12px">{{ $dmMessages->total() }} total</span>
            </div>

            {{-- Conversation filter --}}
            <form method="GET" action="{{ url()->current() }}" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap">
                @foreach(request()->except(['dm_page','dm_conv']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <div>
                    <label style="color:var(--text3);font-size:11px;display:block;margin-bottom:4px">Conversation</label>
                    <select name="dm_conv" onchange="this.form.submit()"
                        style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:6px;font-size:12px;min-width:180px">
                        <option value="">All conversations</option>
                        @foreach($dmConversations as $conv)
                            <option value="{{ $conv->id }}" {{ request('dm_conv') == $conv->id ? 'selected' : '' }}>
                                with {{ $conv->otherUser->username ?? 'Unknown' }} ({{ $conv->messages_count }})
                            </option>
                        @endforeach
                    </select>
                </div>
                @if(request('dm_conv'))
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['dm_conv','dm_page'])) }}"
                       class="btn-secondary" style="font-size:12px;padding:7px 12px">Clear</a>
                @endif
            </form>

            @if($dmMessages->isEmpty())
                <p style="color:var(--text3);font-size:13px;text-align:center;padding:24px">No messages found.</p>
            @else
            {{-- Chat box --}}
            <div style="display:flex;flex-direction:column;gap:10px;max-height:500px;overflow-y:auto;padding:12px;background:var(--bg3);border-radius:10px;border:1px solid var(--border)">
                @foreach($dmMessages->getCollection()->reverse() as $msg)
                    @php $isMine = $msg->sender_id == $user->id; @endphp
                    <div style="display:flex;flex-direction:column;align-items:{{ $isMine ? 'flex-end' : 'flex-start' }}">
                        <div style="font-size:10px;color:var(--text3);margin-bottom:3px;padding:0 4px">
                            @if($isMine)
                                <span style="color:var(--info);font-weight:600">{{ $user->username }}</span>
                                → {{ $msg->conversation?->userTwo?->id == $user->id ? $msg->conversation?->userOne?->username : $msg->conversation?->userTwo?->username }}
                            @else
                                {{ $msg->sender?->username ?? '?' }}
                                → <span style="color:var(--info);font-weight:600">{{ $user->username }}</span>
                            @endif
                            &nbsp;·&nbsp;{{ $msg->created_at?->format('M d, h:i A') }}
                        </div>
                        <div style="max-width:70%;padding:9px 14px;border-radius:{{ $isMine ? '14px 4px 14px 14px' : '4px 14px 14px 14px' }};background:{{ $isMine ? 'var(--primary)' : 'var(--surface2)' }};color:var(--text);font-size:13px;line-height:1.5;word-break:break-word">
                            @if($msg->type === 'image')
                                <span style="opacity:.7">📷 Image</span>
                            @elseif($msg->type === 'voice')
                                <span style="opacity:.7">🎤 Voice message</span>
                            @elseif($msg->type === 'gift')
                                <span style="color:var(--gold)">🎁 Gift{{ $msg->gift ? ': ' . $msg->gift->name : '' }}</span>
                            @else
                                {{ $msg->body }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            {{-- Pagination --}}
            <div style="margin-top:12px;display:flex;align-items:center;gap:8px;font-size:12px">
                @if($dmMessages->onFirstPage())
                    <span style="padding:5px 12px;border-radius:6px;background:var(--bg3);color:var(--text3)">← Prev</span>
                @else
                    <a href="{{ $dmMessages->previousPageUrl() }}&{{ http_build_query(request()->all()) }}"
                       style="padding:5px 12px;border-radius:6px;background:var(--surface2);color:var(--text);text-decoration:none;border:1px solid var(--border)">← Prev</a>
                @endif
                <span style="color:var(--text3)">Page {{ $dmMessages->currentPage() }} of {{ $dmMessages->lastPage() }}</span>
                @if($dmMessages->hasMorePages())
                    <a href="{{ $dmMessages->nextPageUrl() }}&{{ http_build_query(request()->all()) }}"
                       style="padding:5px 12px;border-radius:6px;background:var(--surface2);color:var(--text);text-decoration:none;border:1px solid var(--border)">Next →</a>
                @else
                    <span style="padding:5px 12px;border-radius:6px;background:var(--bg3);color:var(--text3)">Next →</span>
                @endif
            </div>
            @endif
        </div>

        {{-- ── Ban History ─────────────────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h3>🚫 Ban History</h3>
                <button class="btn-sm btn-danger" onclick="document.getElementById('banUserId').value={{ $user->id }};document.getElementById('banModal').classList.remove('hidden')">+ New Ban</button>
            </div>
            @if($user->bans->isEmpty())
                <p style="color:var(--text3);font-size:13px">No bans on record.</p>
            @else
            <table style="width:100%;font-size:12px;border-collapse:collapse">
                <thead><tr style="border-bottom:1px solid var(--border)">
                    @foreach(['Room','Reason','Banned By','Date','Expires'] as $h)
                    <th style="padding:8px 6px;text-align:left;color:var(--text3)">{{ $h }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @foreach($user->bans as $ban)
                <tr style="border-bottom:1px solid var(--border)">
                    <td style="padding:8px 6px">{{ $ban->room_id ?? 'Global' }}</td>
                    <td style="padding:8px 6px">{{ $ban->reason ?? '—' }}</td>
                    <td style="padding:8px 6px">{{ $ban->bannedBy?->username ?? '—' }}</td>
                    <td style="padding:8px 6px">{{ $ban->created_at->format('M d, Y') }}</td>
                    <td style="padding:8px 6px">{{ $ban->expires_at?->format('M d, Y') ?? 'Permanent' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>

    </div>{{-- end right panel --}}
</div>

<script>
function switchCoinTab(tab) {
    ['recharge','gifting','games','exchange'].forEach(t => {
        document.getElementById('coin-pane-' + t).style.display = t === tab ? '' : 'none';
        const btn = document.getElementById('coin-tab-' + t);
        btn.style.borderBottomColor = t === tab ? 'var(--gold)' : 'transparent';
        btn.style.color = t === tab ? 'var(--gold)' : 'var(--text3)';
    });
}
async function creditReward(userId, date, roomId) {
    const btn = document.getElementById('reward-btn-' + roomId);
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = 'Crediting...';
    btn.style.opacity = '0.6';

    try {
        const res = await fetch(`/admin/users/${userId}/credit-reward`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                    || '{{ csrf_token() }}'
            },
            body: JSON.stringify({ date })
        });
        const data = await res.json();

        if (data.success) {
            btn.closest('td').innerHTML =
                '<span style="color:var(--success);font-weight:600">✅ +5,000 💎</span>';
        } else {
            btn.textContent = '⚠️ ' + (data.message || 'Failed');
            btn.style.background = 'var(--danger)';
            btn.style.color = '#fff';
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    } catch (e) {
        btn.textContent = 'Error';
        btn.style.background = 'var(--danger)';
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

function switchDiamondTab(tab) {
    ['dgifts','drewards','dexchange'].forEach(t => {
        document.getElementById('diamond-pane-' + t).style.display = t === tab ? '' : 'none';
        const btn = document.getElementById('diamond-tab-' + t);
        btn.style.borderBottomColor = t === tab ? 'var(--info)' : 'transparent';
        btn.style.color = t === tab ? 'var(--info)' : 'var(--text3)';
    });
}
</script>

<script>
const userId = {{ $user->id }};

// Load user frames
async function loadUserFrames() {
    const res  = await fetch(`/admin/frames/user/${userId}`);
    const data = await res.json();
    const list = document.getElementById('userFramesList');
    if (!data.length) {
        list.innerHTML = '<p style="color:var(--text3);font-size:13px">No frames in inventory.</p>';
        return;
    }
    list.innerHTML = data.map(f => `
        <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border)">
            ${f.thumbnail_url
                ? `<img src="${f.thumbnail_url}" style="width:40px;height:40px;object-fit:contain;border-radius:6px">`
                : `<div style="width:40px;height:40px;background:var(--bg3);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:18px">🖼️</div>`}
            <div style="flex:1">
                <div style="font-size:13px;font-weight:600;color:var(--text)">${f.name}</div>
                <div style="font-size:11px;color:var(--text3)">${f.source === 'admin' ? '🎁 Given by admin' : '🛒 Purchased'}</div>
            </div>
            <button onclick="removeFrame(${f.id})"
                    style="padding:4px 10px;border-radius:6px;border:1px solid #e74c3c;background:transparent;color:#e74c3c;cursor:pointer;font-size:11px">
                Remove
            </button>
        </div>
    `).join('');
}

// Load all frames for dropdown
async function loadAllFrames() {
    const res  = await fetch('/admin/frames/all');
    const data = await res.json();
    const sel  = document.getElementById('frameSelect');
    sel.innerHTML = data.map(f => `<option value="${f.id}">${f.name}${f.price > 0 ? ' ('+f.price+' coins)' : ' (gift)'}</option>`).join('');
}

async function giveFrame() {
    const frameId = document.getElementById('frameSelect').value;
    if (!frameId) return;
    const res = await fetch(`/admin/frames/give/${userId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ frame_id: frameId })
    });
    const data = await res.json();
    alert(data.message);
    document.getElementById('giveFrameModal').classList.add('hidden');
    loadUserFrames();
}

async function removeFrame(frameId) {
    if (!confirm('Remove this frame from user?')) return;
    const res = await fetch(`/admin/frames/remove/${userId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ frame_id: frameId })
    });
    const data = await res.json();
    alert(data.message);
    loadUserFrames();
}

loadUserFrames();
loadAllFrames();
</script>

@endsection