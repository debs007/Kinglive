<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title','Dashboard') — Coin Seller Portal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:#0A0515;--surface:#1A0A2E;--surface2:#2a1f48;--border:rgba(255,255,255,.08);
          --gold:#FFD700;--purple:#6C3483;--text:#e8e0f0;--text2:#a89bc0;--text3:#6a5f80;
          --success:#27ae60;--danger:#e74c3c;--info:#3498db;--warn:#f39c12}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}
    .sidebar{width:240px;background:var(--surface);border-right:1px solid var(--border);position:fixed;height:100vh;display:flex;flex-direction:column;overflow-y:auto}
    .brand{padding:24px 20px;border-bottom:1px solid var(--border)}
    .brand-icon{font-size:32px}
    .brand-name{font-size:16px;font-weight:700;color:var(--gold);margin-top:4px}
    .brand-sub{font-size:11px;color:var(--text3);letter-spacing:2px}
    .seller-info{padding:16px 20px;border-bottom:1px solid var(--border);background:rgba(108,52,131,.1)}
    .balance-pill{display:inline-flex;align-items:center;gap:6px;background:rgba(255,215,0,.1);
                  border:1px solid rgba(255,215,0,.2);border-radius:20px;padding:6px 12px;font-size:13px;color:var(--gold);font-weight:700}
    nav{flex:1;padding:12px 8px}
    .nav-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:8px;
              color:var(--text2);text-decoration:none;font-size:13.5px;transition:all .15s;margin:2px 0}
    .nav-link:hover,.nav-link.active{background:var(--surface2);color:#fff}
    .nav-link.active{color:var(--gold)}
    .nav-link i{font-size:16px;width:18px}
    .sidebar-bottom{padding:16px;border-top:1px solid var(--border)}
    .main{margin-left:240px;flex:1;padding:28px}
    .page-title{font-size:20px;font-weight:700;margin-bottom:4px}
    .page-sub{font-size:13px;color:var(--text3);margin-bottom:24px}
    .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px}
    .stat{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px}
    .stat-icon{font-size:24px;margin-bottom:8px}
    .stat-value{font-size:26px;font-weight:800;margin-bottom:2px}
    .stat-label{font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px}
    .card{background:var(--surface);border:1px solid var(--border);border-radius:12px;margin-bottom:20px}
    .card-head{padding:16px 20px;border-bottom:1px solid var(--border);font-weight:600;font-size:14px}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th{padding:10px 14px;text-align:left;color:var(--text3);font-size:11px;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)}
    td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
    tr:last-child td{border:none}
    tr:hover td{background:rgba(255,255,255,.02)}
    .avatar{width:32px;height:32px;border-radius:50%;background:var(--surface2);object-fit:cover}
    .avatar-placeholder{width:32px;height:32px;border-radius:50%;background:var(--purple);
                        display:inline-flex;align-items:center;justify-content:center;font-size:13px;color:#fff;font-weight:700}
    .btn{padding:7px 14px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:600;text-decoration:none;display:inline-block}
    .btn-gold{background:var(--gold);color:#000}
    .btn-gold:hover{background:#B8860B;color:#000}
    .btn-outline{background:transparent;border:1px solid var(--border);color:var(--text2)}
    .btn-danger{background:rgba(231,76,60,.2);color:#e74c3c;border:none}
    .badge{padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600}
    .badge-sale{background:rgba(255,215,0,.15);color:var(--gold)}
    .badge-admin{background:rgba(52,152,219,.15);color:var(--info)}
    input,textarea{background:var(--surface2);border:1px solid var(--border);border-radius:6px;
                   padding:8px 12px;color:var(--text);font-size:13px;outline:none}
    input:focus{border-color:var(--gold)}
    .alert-success{background:rgba(39,174,96,.15);border:1px solid rgba(39,174,96,.3);color:#27ae60;
                   padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px}
    .alert-error{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:#e74c3c;
                 padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px}
    .search-input{width:100%;max-width:280px;padding:8px 14px}
    .pagination{display:flex;gap:4px;margin-top:16px;padding:0 4px}
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">
    <div class="brand-icon">🪙</div>
    <div class="brand-name">Coin Seller</div>
    <div class="brand-sub">KING LIVE</div>
  </div>
  <div class="seller-info">
    <div style="font-size:13px;color:var(--text2);margin-bottom:6px">{{ session('coin_seller_name') }}</div>
    <div class="balance-pill">
      🪙 {{ number_format(\App\Models\CoinSeller::find(session('coin_seller_id'))?->coin_balance ?? 0) }} coins
    </div>
  </div>
  <nav>
    <a href="{{ route('coin_seller.dashboard') }}"
       class="nav-link {{ request()->routeIs('coin_seller.dashboard') ? 'active' : '' }}">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="{{ route('coin_seller.users') }}"
       class="nav-link {{ request()->routeIs('coin_seller.users') ? 'active' : '' }}">
      <i class="bi bi-people"></i> Users
    </a>
    <a href="{{ route('coin_seller.transactions') }}"
       class="nav-link {{ request()->routeIs('coin_seller.transactions') ? 'active' : '' }}">
      <i class="bi bi-receipt"></i> Transactions
    </a>
  </nav>
  <div class="sidebar-bottom">
    <form action="{{ route('coin_seller.logout') }}" method="POST">
      @csrf
      <button type="submit" class="btn btn-outline" style="width:100%">
        <i class="bi bi-box-arrow-left"></i> Logout
      </button>
    </form>
  </div>
</aside>

<main class="main">
  @if(session('success'))
    <div class="alert-success">✓ {{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert-error">
      @foreach($errors->all() as $e) <div>✕ {{ $e }}</div> @endforeach
    </div>
  @endif

  @yield('content')
</main>

</body>
</html>
