<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — King Live Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --gold:#FFD700; --gold-dark:#B8860B;
            --purple:#6C3483; --purple-light:#9B59B6;
            --bg:#0f0a1a; --bg2:#1a1030; --bg3:#231540;
            --surface:#1e1535; --surface2:#2a1f48;
            --border:rgba(255,255,255,.08);
            --text:#e8e0f0; --text2:#a89bc0; --text3:#6a5f80;
            --danger:#e74c3c; --success:#27ae60; --info:#3498db; --warn:#f39c12;
            --radius:10px;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--text);font-size:14px;display:flex;min-height:100vh}
        a{color:var(--purple-light);text-decoration:none} a:hover{color:var(--gold)}

        /* ── Sidebar ── */
        .sidebar{width:230px;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;height:100vh;overflow-y:auto;z-index:100}
        .sidebar-logo{padding:24px 20px 16px;border-bottom:1px solid var(--border)}
        .sidebar-logo h1{font-size:18px;font-weight:700;color:var(--gold);letter-spacing:2px;margin-top:4px}
        .sidebar-logo p{font-size:10px;color:var(--text3);letter-spacing:3px}
        .nav-section{padding:12px 12px 0;font-size:10px;text-transform:uppercase;color:var(--text3);letter-spacing:2px;margin-top:8px}
        .nav-item{display:flex;align-items:center;gap:10px;padding:10px 16px;border-radius:8px;margin:2px 8px;color:var(--text2);text-decoration:none;transition:all .15s;font-size:13.5px}
        .nav-item:hover{background:var(--surface2);color:var(--text)}
        .nav-item.active{background:var(--surface2);color:var(--gold);border-left:3px solid var(--gold);padding-left:13px}
        .nav-badge{margin-left:auto;background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px}
        .sidebar-bottom{margin-top:auto;padding:16px;border-top:1px solid var(--border)}
        .admin-user{display:flex;align-items:center;gap:10px}
        .admin-avatar{width:32px;height:32px;border-radius:50%;background:var(--purple);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:var(--gold)}

        /* ── Main ── */
        .main{margin-left:230px;flex:1;display:flex;flex-direction:column;min-height:100vh}
        .topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
        .topbar h2{font-size:16px;font-weight:600}
        .breadcrumb{font-size:12px;color:var(--text3);margin-top:2px}
        .content{padding:24px 28px;flex:1}

        /* ── Cards ── */
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:20px}
        .card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
        .card-header h3{font-size:14px;font-weight:600}

        /* ── Stats grid ── */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}
        .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px}
        .stat-card .label{font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px}
        .stat-card .value{font-size:26px;font-weight:700;margin:6px 0 4px}
        .stat-card.purple .value{color:var(--purple-light)} .stat-card.amber .value{color:var(--gold)}
        .stat-card.green .value{color:var(--success)} .stat-card.red .value{color:var(--danger)}
        .stat-card.blue .value{color:var(--info)}

        /* ── Layout helpers ── */
        .dashboard-row{display:flex;gap:16px;margin-bottom:20px}
        .dashboard-row>.card{margin-bottom:0}
        .flex-1{flex:1} .flex-2{flex:2}

        /* ── Tables ── */
        .admin-table{width:100%;border-collapse:collapse;font-size:13px}
        .admin-table th{padding:8px 12px;text-align:left;color:var(--text3);font-size:11px;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
        .admin-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
        .admin-table tr:last-child td{border-bottom:none}
        .admin-table tbody tr:hover td{background:var(--surface2)}

        /* ── User cell ── */
        .user-cell{display:flex;align-items:center;gap:8px}
        .avatar-sm{width:28px;height:28px;border-radius:50%;object-fit:cover;background:var(--surface2)}

        /* ── Badges ── */
        .badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600}
        .badge-global{background:rgba(231,76,60,.15);color:#e74c3c}
        .badge-room{background:rgba(243,156,18,.15);color:#f39c12}
        .badge-chat{background:rgba(52,152,219,.15);color:#3498db}
        .badge-live{background:rgba(155,89,182,.15);color:#9b59b6}
        .badge-active{background:rgba(39,174,96,.15);color:#27ae60}
        .badge-expired,.badge-lifted{background:rgba(127,127,127,.15);color:#888}
        .badge-video{background:rgba(52,152,219,.15);color:#3498db}
        .badge-audio{background:rgba(155,89,182,.15);color:#9b59b6}
        .badge-audio_board{background:rgba(26,188,156,.15);color:#1abc9c}
        .badge-pk{background:rgba(231,76,60,.15);color:#e74c3c}
        .badge-pending{background:rgba(243,156,18,.15);color:#f39c12}
        .badge-approved{background:rgba(39,174,96,.15);color:#27ae60}
        .badge-paid{background:rgba(39,174,96,.15);color:#27ae60}
        .badge-rejected{background:rgba(231,76,60,.15);color:#e74c3c}
        .live-badge{background:#e74c3c;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;animation:pulse 2s infinite}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}

        /* ── Buttons ── */
        .btn-primary{background:var(--gold);color:#000;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;text-decoration:none;display:inline-block}
        .btn-primary:hover{background:var(--gold-dark);color:#000}
        .btn-secondary{background:var(--surface2);color:var(--text);border:1px solid var(--border);padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block}
        .btn-danger{background:var(--danger);color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px}
        .btn-success{background:var(--success);color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px}
        .btn-sm{padding:4px 10px;border-radius:5px;border:none;cursor:pointer;font-size:12px;text-decoration:none;display:inline-block}
        .btn-sm.btn-info{background:rgba(52,152,219,.2);color:#3498db}
        .btn-sm.btn-danger{background:rgba(231,76,60,.2);color:#e74c3c}
        .btn-sm.btn-warning{background:rgba(243,156,18,.2);color:#f39c12}
        .btn-sm.btn-success{background:rgba(39,174,96,.2);color:#27ae60}
        .action-btns{display:flex;gap:6px;align-items:center}

        /* ── Forms ── */
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:12px;color:var(--text2);margin-bottom:6px}
        .form-group input,.form-group select,.form-group textarea{width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:6px;font-size:13px}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--gold)}
        .filter-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .filter-form input,.filter-form select{padding:6px 10px;width:auto;background:var(--bg3);border:1px solid var(--border);color:var(--text);border-radius:6px;font-size:13px}
        .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
        .page-header h2{font-size:18px;font-weight:700}
        .form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}

        /* ── Modal ── */
        .modal{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;z-index:1000}
        .modal.hidden{display:none}
        .modal-content{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:28px;width:440px;max-width:95vw}
        .modal-content h3{font-size:16px;font-weight:700;margin-bottom:20px}

        /* ── Alerts ── */
        .alert{padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:13px}
        .alert-success{background:rgba(39,174,96,.15);border:1px solid rgba(39,174,96,.3);color:#2ecc71}
        .alert-danger{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:#e74c3c}
        .alert-warning{background:rgba(243,156,18,.15);border:1px solid rgba(243,156,18,.3);color:#f39c12}

        /* ── Misc ── */
        .ban-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}
        .ban-item:last-child{border-bottom:none}
        .ban-details{flex:1} .ban-details strong{display:block;font-size:13px}
        .ban-details small{color:var(--text3);font-size:11px;display:block}
        .text-amber{color:var(--gold)} .text-blue{color:var(--info)}
        .text-success{color:var(--success)} .text-danger{color:var(--danger)}
        .text-muted{color:var(--text3)}
        .pagination{display:flex;gap:4px;margin-top:16px;flex-wrap:wrap}
        .pagination .page-item .page-link{padding:6px 12px;background:var(--surface2);border:1px solid var(--border);color:var(--text2);border-radius:4px;font-size:12px;text-decoration:none}
        .pagination .page-item.active .page-link{background:var(--gold);color:#000;border-color:var(--gold)}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div style="font-size:28px">👑</div>
        <h1>KING LIVE</h1>
        <p>ADMIN PANEL</p>
    </div>

    <div class="nav-section">Overview</div>
    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <span>▣</span> Dashboard
    </a>

    <div class="nav-section">Users</div>
    <a href="{{ route('admin.users.index') }}" class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
        <span>👥</span> Users
    </a>
    <a href="{{ route('admin.bans.index') }}" class="nav-item {{ request()->routeIs('admin.bans.*') ? 'active' : '' }}">
        <span>🚫</span> Bans
        @php $activeBans = \App\Models\UserBan::active()->count() @endphp
        @if($activeBans > 0)<span class="nav-badge">{{ $activeBans }}</span>@endif
    </a>

    <div class="nav-section">Live</div>
    <a href="{{ route('admin.rooms.index') }}" class="nav-item {{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}">
        <span>📺</span> Live Rooms
    </a>
    <a href="{{ route('admin.backgrounds.index') }}" class="nav-item {{ request()->routeIs('admin.backgrounds.*') ? 'active' : '' }}">
        <span>🖼️</span> Backgrounds
    </a>
    <a href="{{ route('admin.frames.index') }}" class="nav-item {{ request()->routeIs('admin.frames.*') ? 'active' : '' }}">
        <span>✨</span> Frames
    </a>
    <a href="{{ route('admin.salary.index') }}" class="nav-item {{ request()->routeIs('admin.salary.*') ? 'active' : '' }}">
        <span>💰</span> Salary Sheet
    </a>

    <div class="nav-section">Economy</div>
    <a href="{{ route('admin.gifts.manage') }}" class="nav-item {{ request()->routeIs('admin.gifts.manage') ? 'active' : '' }}">
        <span>🎁</span> Gift Catalog
    </a>
    <a href="{{ route('admin.gifts.report') }}" class="nav-item {{ request()->routeIs('admin.gifts.report') ? 'active' : '' }}">
        <span>📊</span> Gift Reports
    </a>
    <a href="{{ route('admin.withdrawals.index') }}" class="nav-item {{ request()->routeIs('admin.withdrawals.*') ? 'active' : '' }}">
        <span>💰</span> Withdrawals
        @php $pending = \App\Models\WithdrawalRequest::where('status','pending')->count() @endphp
        @if($pending > 0)<span class="nav-badge">{{ $pending }}</span>@endif
    </a>

    <div class="nav-section">Games</div>
    <a href="{{ route('admin.games.manage') }}" class="nav-item {{ request()->routeIs('admin.games.manage') ? 'active' : '' }}">
        <span>🎮</span> Game Catalog
    </a>
    <a href="{{ route('admin.games.report') }}" class="nav-item {{ request()->routeIs('admin.games.report') ? 'active' : '' }}">
        <span>📈</span> Game Reports
    </a>

    <div class="nav-section">Content</div>
    <a href="{{ route('admin.banners.index') }}" class="nav-item {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
        <span>🖼</span> Banners
    </a>

    <div class="nav-section">Coin Sellers</div>
    <a href="{{ route('admin.coin_sellers.index') }}" class="nav-item {{ request()->routeIs('admin.coin_sellers.*') ? 'active' : '' }}">
        <span>🪙</span> Coin Sellers
    </a>

    <div class="nav-section">Agencies</div>
    <a href="{{ route('admin.agencies.index') }}" class="nav-item {{ request()->routeIs('admin.agencies.*') ? 'active' : '' }}">
        <span>🏢</span> Agencies
    </a>

    <div class="nav-section">System</div>
    <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
        <span>⚙</span> Settings
    </a>
    <a href="/horizon" target="_blank" class="nav-item">
        <span>⚡</span> Horizon
    </a>

    <div class="sidebar-bottom">
        <div class="admin-user">
            <div class="admin-avatar">{{ strtoupper(substr(auth()->user()->username ?? 'A', 0, 1)) }}</div>
            <div>
                <div style="font-size:13px;font-weight:600">{{ auth()->user()->username ?? 'Admin' }}</div>
                <div style="font-size:11px;color:var(--text3)">{{ auth()->user()->role ?? '' }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}" style="margin-top:12px">
            @csrf
            <button type="submit" class="btn-secondary" style="width:100%;font-size:12px">Logout</button>
        </form>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <h2>@yield('title', 'Dashboard')</h2>
            <div class="breadcrumb">King Live Admin › @yield('breadcrumb', 'Dashboard')</div>
        </div>
        <div style="display:flex;align-items:center;gap:16px">
            <span style="font-size:12px;color:var(--text3)">
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#27ae60;margin-right:4px"></span>
                System running
            </span>
        </div>
    </div>

    <div class="content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </div>
</main>

@stack('scripts')
</body>
</html>
