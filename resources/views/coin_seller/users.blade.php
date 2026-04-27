@extends('coin_seller.layouts.app')
@section('title','Users')

@section('content')
<div class="page-title">Users</div>
<div class="page-sub">Add coins to user wallets — Balance: <span style="color:var(--gold);font-weight:700">{{ number_format($seller->coin_balance) }} 🪙</span></div>

<div style="margin-bottom:16px">
  <form method="GET" action="{{ route('coin_seller.users') }}" style="display:flex;gap:10px;align-items:center">
    <input type="text" name="search" class="search-input" placeholder="Search by username…"
           value="{{ $search ?? '' }}">
    <button type="submit" class="btn btn-outline">Search</button>
    @if($search)
      <a href="{{ route('coin_seller.users') }}" class="btn btn-outline">Clear</a>
    @endif
  </form>
</div>

{{-- Add coins modal overlay --}}
<div id="overlay" onclick="closeModal()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:99"></div>

<div id="addModal"
     style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:360px;background:var(--surface);border:1px solid var(--border);border-radius:12px;
            z-index:100;box-shadow:0 24px 60px rgba(0,0,0,.6)">
  <div style="padding:18px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <div style="font-weight:700;color:var(--gold)">🪙 Add Coins</div>
    <button onclick="closeModal()" style="background:none;border:none;color:var(--text3);font-size:20px;cursor:pointer">&times;</button>
  </div>
  <form id="addForm" method="POST">
    @csrf
    <div style="padding:18px 20px;display:flex;flex-direction:column;gap:14px">
      <div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:4px">Adding coins to:</div>
        <div id="modalUsername" style="font-weight:700;color:#fff;font-size:15px"></div>
      </div>
      <div>
        <label style="font-size:12px;color:var(--text2);display:block;margin-bottom:6px">Amount *</label>
        <input type="number" name="coins" min="1" max="{{ $seller->coin_balance }}"
               required placeholder="e.g. 1000" style="width:100%">
        <div style="font-size:11px;color:var(--text3);margin-top:4px">
          Max: {{ number_format($seller->coin_balance) }} coins
        </div>
      </div>
      <div>
        <label style="font-size:12px;color:var(--text2);display:block;margin-bottom:6px">Note (optional)</label>
        <input type="text" name="note" maxlength="255" placeholder="e.g. Package A" style="width:100%">
      </div>
    </div>
    <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px">
      <button type="button" onclick="closeModal()" class="btn btn-outline">Cancel</button>
      <button type="submit" class="btn btn-gold">Add Coins</button>
    </div>
  </form>
</div>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>User</th>
        <th>Level</th>
        <th>Coin Balance</th>
        <th>Joined</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      @forelse($users as $user)
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            @if($user->avatar_url)
              <img src="{{ $user->avatar_url }}" class="avatar">
            @else
              <div class="avatar-placeholder">{{ strtoupper(substr($user->username,0,1)) }}</div>
            @endif
            <div>
              <div style="font-weight:600">{{ $user->username }}</div>
              <div style="font-size:11px;color:var(--text3)">ID: {{ 100000 + $user->id }}</div>
            </div>
          </div>
        </td>
        <td><span style="background:rgba(108,52,131,.3);color:#9B59B6;padding:2px 8px;border-radius:4px;font-size:12px">Lv. {{ $user->level }}</span></td>
        <td style="color:var(--gold)">🪙 {{ number_format($user->coin_balance) }}</td>
        <td style="color:var(--text3);font-size:12px">{{ $user->created_at->format('M d, Y') }}</td>
        <td>
          <button class="btn btn-gold"
                  onclick="openAdd({{ $user->id }}, '{{ addslashes($user->username) }}')">
            + Add Coins
          </button>
        </td>
      </tr>
      @empty
      <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:32px">No users found</td></tr>
      @endforelse
    </tbody>
  </table>
  @if($users->hasPages())
    <div style="padding:12px 16px">{{ $users->appends(['search'=>$search])->links() }}</div>
  @endif
</div>

<script>
function openAdd(userId, username) {
  document.getElementById('addForm').action = '/coin-seller/users/' + userId + '/add-coins';
  document.getElementById('modalUsername').textContent = username;
  document.getElementById('overlay').style.display = 'block';
  document.getElementById('addModal').style.display  = 'block';
}
function closeModal() {
  document.getElementById('overlay').style.display = 'none';
  document.getElementById('addModal').style.display  = 'none';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });
</script>
@endsection
