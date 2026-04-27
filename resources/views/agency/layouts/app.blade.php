<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Agency Portal') — King Live</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --bg: #0A0515; --surface: #1A0A2E; --border: #2D1B4E; --accent: #6C3483; --gold: #FFD700; }
    body { background: var(--bg); color: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .sidebar { width: 240px; min-height: 100vh; background: var(--surface); border-right: 1px solid var(--border); position: fixed; top: 0; left: 0; }
    .sidebar .brand { padding: 24px 20px; border-bottom: 1px solid var(--border); }
    .sidebar .nav-link { color: rgba(255,255,255,.6); padding: 10px 20px; border-radius: 8px; margin: 2px 8px; transition: all .2s; }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(108,52,131,.3); }
    .sidebar .nav-link .bi { margin-right: 8px; font-size: 16px; }
    .main { margin-left: 240px; padding: 28px; min-height: 100vh; }
    .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
    .stat-card { background: linear-gradient(135deg, var(--surface), #2D1B4E); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
    .table { color: #fff; }
    .table td, .table th { border-color: var(--border); vertical-align: middle; }
    .badge-pending { background: rgba(255,165,0,.15); color: #FFA500; border: 1px solid rgba(255,165,0,.3); }
    .badge-approved { background: rgba(39,174,96,.15); color: #27AE60; border: 1px solid rgba(39,174,96,.3); }
    .badge-rejected { background: rgba(231,76,60,.15); color: #E74C3C; border: 1px solid rgba(231,76,60,.3); }
    .form-control, .form-select { background: #2D1B4E; border: 1px solid #4A2F6E; color: #fff; }
    .form-control:focus { background: #3D2B5E; border-color: var(--accent); color: #fff; box-shadow: none; }
    .btn-accent { background: linear-gradient(135deg, var(--accent), #9B59B6); border: none; color: #fff; }
    .btn-accent:hover { opacity: .9; color: #fff; }
    .avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: var(--border); }
  </style>
</head>
<body>

<div class="sidebar d-flex flex-column">
  <div class="brand">
    <div style="font-size:28px">🏢</div>
    <div class="fw-bold mt-1" style="color:#fff">{{ session('agency_name') }}</div>
    <small class="text-muted">Agency Portal</small>
  </div>

  <nav class="flex-grow-1 py-3">
    <a href="{{ route('agency.dashboard') }}"
       class="nav-link d-flex align-items-center {{ request()->routeIs('agency.dashboard') ? 'active' : '' }}">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="{{ route('agency.members') }}"
       class="nav-link d-flex align-items-center {{ request()->routeIs('agency.members') ? 'active' : '' }}">
      <i class="bi bi-people"></i> Members
    </a>
    <a href="{{ route('agency.requests') }}"
       class="nav-link d-flex align-items-center {{ request()->routeIs('agency.requests') ? 'active' : '' }}">
      <i class="bi bi-person-plus"></i> Join Requests
      @php
        $pending = \App\Models\AgencyJoinRequest::where('agency_id', session('agency_id'))
            ->where('status','pending')->count();
      @endphp
      @if($pending > 0)
        <span class="badge bg-warning text-dark ms-auto">{{ $pending }}</span>
      @endif
    </a>
  </nav>

  <div class="p-3 border-top" style="border-color: var(--border) !important">
    <form action="{{ route('agency.logout') }}" method="POST">
      @csrf
      <button class="btn btn-sm btn-outline-secondary w-100">
        <i class="bi bi-box-arrow-left"></i> Logout
      </button>
    </form>
  </div>
</div>

<div class="main">
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
