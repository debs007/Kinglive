@extends('agency.layouts.app')
@section('title', 'Join Requests')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="text-white mb-0">Join Requests</h4>
    <small class="text-muted">{{ $requests->total() }} total</small>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th class="text-muted small">User</th>
          <th class="text-muted small">Status</th>
          <th class="text-muted small">Requested</th>
          <th class="text-muted small">Responded</th>
          <th class="text-muted small">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($requests as $req)
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              @if($req->user?->avatar_url)
                <img src="{{ $req->user->avatar_url }}" class="avatar">
              @else
                <div class="avatar d-flex align-items-center justify-content-center"
                     style="background:#2D1B4E;font-size:13px;color:#fff">
                  {{ strtoupper(substr($req->user?->username ?? '?', 0, 1)) }}
                </div>
              @endif
              <div>
                <div class="text-white small fw-bold">
                  {{ $req->user?->username ?? 'Deleted User' }}
                </div>
                <div class="text-muted" style="font-size:11px">
                  ID: {{ 100000 + ($req->user?->id ?? 0) }}
                  &nbsp;·&nbsp; Lv. {{ $req->user?->level ?? 1 }}
                  &nbsp;·&nbsp; 💎 {{ number_format($req->user?->diamond_balance ?? 0) }}
                </div>
              </div>
            </div>
          </td>
          <td>
            <span class="badge badge-{{ $req->status }} px-2 py-1 rounded-pill">
              {{ ucfirst($req->status) }}
            </span>
          </td>
          <td class="text-muted small">
            {{ $req->created_at->diffForHumans() }}
          </td>
          <td class="text-muted small">
            {{ $req->responded_at?->diffForHumans() ?? '—' }}
          </td>
          <td>
            @if($req->status === 'pending')
              <div class="d-flex gap-2">
                <form action="{{ route('agency.requests.approve', $req->id) }}"
                      method="POST">
                  @csrf
                  <button class="btn btn-sm btn-accent">
                    ✓ Approve
                  </button>
                </form>
                <form action="{{ route('agency.requests.reject', $req->id) }}"
                      method="POST">
                  @csrf
                  <button class="btn btn-sm btn-outline-danger">
                    ✕ Reject
                  </button>
                </form>
              </div>
            @else
              <span class="text-muted small">—</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="5" class="text-center text-muted py-5">
            No join requests yet
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($requests->hasPages())
    <div class="p-3">{{ $requests->links() }}</div>
  @endif
</div>
@endsection
