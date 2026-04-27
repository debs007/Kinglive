@extends('agency.layouts.app')
@section('title', 'Dashboard')

@section('content')
<h4 class="text-white mb-1">Dashboard</h4>
<small class="text-muted">Welcome back, {{ $agency->name }}</small>

<div class="row g-3 mt-2">
  {{-- Stat cards --}}
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div style="font-size:28px">👥</div>
      <div class="fs-2 fw-black text-white mt-1">{{ $memberCount }}</div>
      <div class="text-muted small">Total Members</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div style="font-size:28px">💎</div>
      <div class="fs-2 fw-black mt-1" style="color:#3498DB">
        {{ number_format($totalDiamonds) }}
      </div>
      <div class="text-muted small">Total Diamonds Earned</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div style="font-size:28px">🪙</div>
      <div class="fs-2 fw-black mt-1" style="color:#FFD700">
        {{ number_format($totalCoins) }}
      </div>
      <div class="text-muted small">Total Coins Gifted</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card">
      <div style="font-size:28px">⏳</div>
      <div class="fs-2 fw-black mt-1" style="color:#E74C3C">{{ $pendingCount }}</div>
      <div class="text-muted small">Pending Requests</div>
      @if($pendingCount > 0)
        <a href="{{ route('agency.requests') }}" class="btn btn-sm btn-accent mt-2">
          Review →
        </a>
      @endif
    </div>
  </div>
</div>

{{-- Agency code --}}
<div class="card mt-4 p-4">
  <div class="d-flex align-items-center gap-3">
    @if($agency->logo_url)
      <img src="{{ $agency->logo_url }}" width="56" height="56"
           class="rounded-circle" style="object-fit:cover">
    @else
      <div class="rounded-circle d-flex align-items-center justify-content-center"
           style="width:56px;height:56px;background:#2D1B4E;font-size:24px">🏢</div>
    @endif
    <div>
      <div class="fw-bold text-white fs-5">{{ $agency->name }}</div>
      @if($agency->description)
        <div class="text-muted small">{{ $agency->description }}</div>
      @endif
    </div>
    <div class="ms-auto text-end">
      <div class="text-muted small">Invite Code</div>
      <code class="fs-4" style="color:#FFD700;letter-spacing:4px">{{ $agency->code }}</code>
      <div class="text-muted" style="font-size:11px">Share this code with users to join</div>
    </div>
  </div>
</div>

{{-- Top earners --}}
<div class="card mt-4">
  <div class="card-body">
    <h6 class="text-white mb-3">🏆 Top Earners</h6>
    @forelse($topEarners as $i => $user)
    <div class="d-flex align-items-center gap-3 mb-3">
      <span class="text-muted fw-bold" style="width:24px">#{{ $i+1 }}</span>
      @if($user->avatar_url)
        <img src="{{ $user->avatar_url }}" class="avatar">
      @else
        <div class="avatar d-flex align-items-center justify-content-center"
             style="background:#2D1B4E;color:#fff;font-size:14px">
          {{ strtoupper(substr($user->username,0,1)) }}
        </div>
      @endif
      <div class="flex-grow-1">
        <div class="text-white fw-bold small">{{ $user->username }}</div>
        <div class="text-muted" style="font-size:11px">Lv. {{ $user->level }}</div>
      </div>
      <div class="text-end">
        <div style="color:#3498DB;font-weight:bold">
          💎 {{ number_format($user->diamond_balance) }}
        </div>
      </div>
    </div>
    @empty
      <p class="text-muted text-center py-3">No members yet</p>
    @endforelse
  </div>
</div>
@endsection
