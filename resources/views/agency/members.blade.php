@extends('agency.layouts.app')
@section('title', 'Members')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="text-white mb-0">Members</h4>
    <small class="text-muted">{{ $members->total() }} total</small>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th class="text-muted small">#</th>
          <th class="text-muted small">User</th>
          <th class="text-muted small">Level</th>
          <th class="text-muted small">Diamonds</th>
          <th class="text-muted small">Coins</th>
          <th class="text-muted small">Joined</th>
          <th class="text-muted small">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($members as $i => $user)
        <tr>
          <td class="text-muted">{{ $members->firstItem() + $i }}</td>
          <td>
            <div class="d-flex align-items-center gap-2">
              @if($user->avatar_url)
                <img src="{{ $user->avatar_url }}" class="avatar">
              @else
                <div class="avatar d-flex align-items-center justify-content-center"
                     style="background:#2D1B4E;font-size:13px;color:#fff">
                  {{ strtoupper(substr($user->username,0,1)) }}
                </div>
              @endif
              <div>
                <div class="text-white small fw-bold">{{ $user->username }}</div>
                <div class="text-muted" style="font-size:11px">
                  ID: {{ 100000 + $user->id }}
                </div>
              </div>
            </div>
          </td>
          <td><span class="badge" style="background:#6C3483">Lv. {{ $user->level }}</span></td>
          <td style="color:#3498DB">💎 {{ number_format($user->diamond_balance) }}</td>
          <td style="color:#FFD700">🪙 {{ number_format($user->coin_balance) }}</td>
          <td class="text-muted small">{{ $user->created_at->format('M d, Y') }}</td>
          <td>
            <form action="{{ route('agency.members.remove', $user->id) }}"
                  method="POST"
                  onsubmit="return confirm('Remove {{ $user->username }} from agency?')">
              @csrf
              <button class="btn btn-sm btn-outline-danger">Remove</button>
            </form>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="text-center text-muted py-5">No members yet</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($members->hasPages())
    <div class="p-3">{{ $members->links() }}</div>
  @endif
</div>
@endsection
