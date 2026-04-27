@extends('admin.layouts.app')
@section('title', 'Coin Sellers')
@section('breadcrumb', 'Coin Sellers')

@section('content')

<div class="card-header" style="margin-bottom:20px">
  <div>
    <h3 style="font-size:16px;font-weight:700">🪙 Coin Sellers</h3>
    <div style="font-size:12px;color:var(--text3);margin-top:2px">Manage coin resellers and give coins to users</div>
  </div>
  <div style="display:flex;gap:10px">
    <a href="{{ route('admin.coin_sellers.transactions') }}" class="btn-secondary">📊 Transactions</a>
    <button class="btn-primary" onclick="openModal('createModal')">+ New Seller</button>
  </div>
</div>

@if(session('success'))
  <div style="background:rgba(39,174,96,.15);border:1px solid rgba(39,174,96,.3);color:#27ae60;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
    ✓ {{ session('success') }}
  </div>
@endif
@if($errors->any())
  <div style="background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:#e74c3c;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
    @foreach($errors->all() as $e)<div>✕ {{ $e }}</div>@endforeach
  </div>
@endif

{{-- Give coins to user directly --}}
<div class="card" style="margin-bottom:20px">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:600;font-size:13px">
    🎁 Give Coins to User (Admin — No Balance Limit)
  </div>
  <form action="{{ route('admin.coin_sellers.give_to_user') }}" method="POST"
        style="padding:16px 18px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    @csrf
    <div class="form-group" style="margin:0;flex:1;min-width:160px">
      <label>User ID or Username</label>
      <input type="text" name="user_id" placeholder="User ID (numbers only)" style="width:100%">
    </div>
    <div class="form-group" style="margin:0;width:140px">
      <label>Coins</label>
      <input type="number" name="coins" min="1" placeholder="Amount" style="width:100%">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:160px">
      <label>Note</label>
      <input type="text" name="note" placeholder="Reason" style="width:100%">
    </div>
    <button type="submit" class="btn-primary">Give Coins</button>
  </form>
</div>

<div class="card" style="padding:0">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Seller</th>
        <th>Balance</th>
        <th>Total Sold</th>
        <th>Transactions</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($sellers as $seller)
      <tr>
        <td>
          <div>
            <div style="font-weight:600;color:var(--text)">{{ $seller->name }}</div>
            <div style="font-size:11px;color:var(--text3)">{{ $seller->email }}</div>
          </div>
        </td>
        <td>
          <span style="color:var(--gold);font-weight:700">🪙 {{ number_format($seller->coin_balance) }}</span>
        </td>
        <td style="color:var(--text2)">{{ number_format($seller->total_sold) }}</td>
        <td><span class="badge badge-live">{{ $seller->transactions_count }}</span></td>
        <td>
          <span class="badge {{ $seller->is_active ? 'badge-approved' : 'badge-rejected' }}">
            {{ $seller->is_active ? 'Active' : 'Inactive' }}
          </span>
        </td>
        <td>
          <div class="action-btns">
            <button class="btn-sm btn-success"
                    onclick="openAddCoins({{ $seller->id }}, '{{ addslashes($seller->name) }}', {{ $seller->coin_balance }})">
              + Coins
            </button>
            <form action="{{ route('admin.coin_sellers.toggle', $seller->id) }}" method="POST">
              @csrf
              <button type="submit" class="btn-sm btn-warning">
                {{ $seller->is_active ? 'Deactivate' : 'Activate' }}
              </button>
            </form>
            <form action="{{ route('admin.coin_sellers.destroy', $seller->id) }}" method="POST"
                  onsubmit="return confirm('Delete this seller?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn-sm btn-danger">Delete</button>
            </form>
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" style="text-align:center;color:var(--text3);padding:40px">
          No coin sellers yet.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $sellers->links() }}

{{-- Overlay --}}
<div id="modalOverlay" onclick="closeAllModals()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999;backdrop-filter:blur(2px)"></div>

{{-- Create Modal --}}
<div id="createModal"
     style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:460px;max-width:calc(100vw - 40px);background:var(--bg2);
            border:1px solid var(--border);border-radius:12px;z-index:1000;
            box-shadow:0 24px 80px rgba(0,0,0,.7)">
  <form action="{{ route('admin.coin_sellers.store') }}" method="POST">
    @csrf
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:15px;font-weight:700;color:var(--gold)">🪙 Create Coin Seller</div>
      <button type="button" onclick="closeAllModals()"
              style="background:none;border:none;color:var(--text3);font-size:22px;cursor:pointer">&times;</button>
    </div>
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
      <div class="form-group" style="margin:0">
        <label>Name *</label>
        <input type="text" name="name" required maxlength="100" placeholder="Seller name" style="width:100%">
      </div>
      <div class="form-group" style="margin:0">
        <label>Login Email *</label>
        <input type="email" name="email" required placeholder="seller@example.com" style="width:100%">
      </div>
      <div class="form-group" style="margin:0">
        <label>Password *</label>
        <input type="password" name="password" required minlength="6" placeholder="Min 6 chars" style="width:100%">
      </div>
      <div class="form-group" style="margin:0">
        <label>Initial Coin Balance</label>
        <input type="number" name="coins" min="0" placeholder="0" style="width:100%">
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Coins available for the seller to distribute</div>
      </div>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px">
      <button type="button" onclick="closeAllModals()" class="btn-secondary">Cancel</button>
      <button type="submit" class="btn-primary">Create Seller</button>
    </div>
  </form>
</div>

{{-- Add Coins Modal --}}
<div id="addCoinsModal"
     style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:380px;max-width:calc(100vw - 40px);background:var(--bg2);
            border:1px solid var(--border);border-radius:12px;z-index:1000;
            box-shadow:0 24px 80px rgba(0,0,0,.7)">
  <form id="addCoinsForm" method="POST">
    @csrf
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:15px;font-weight:700;color:var(--gold)">🪙 Add Coins to Seller</div>
      <button type="button" onclick="closeAllModals()"
              style="background:none;border:none;color:var(--text3);font-size:22px;cursor:pointer">&times;</button>
    </div>
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
      <div style="font-size:13px;color:var(--text2)">
        Seller: <span id="sellerName" style="color:#fff;font-weight:600"></span><br>
        Current balance: <span id="sellerBalance" style="color:var(--gold);font-weight:700"></span> coins
      </div>
      <div class="form-group" style="margin:0">
        <label>Coins to Add *</label>
        <input type="number" name="coins" min="1" required placeholder="Amount" style="width:100%">
      </div>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px">
      <button type="button" onclick="closeAllModals()" class="btn-secondary">Cancel</button>
      <button type="submit" class="btn-primary">Add Coins</button>
    </div>
  </form>
</div>

<script>
function openModal(id) {
  document.getElementById('modalOverlay').style.display = 'block';
  document.getElementById(id).style.display = 'block';
}
function closeAllModals() {
  ['modalOverlay','createModal','addCoinsModal'].forEach(id =>
    document.getElementById(id).style.display = 'none');
}
function openAddCoins(id, name, balance) {
  document.getElementById('addCoinsForm').action = '/admin/coin-sellers/' + id + '/add-coins';
  document.getElementById('sellerName').textContent    = name;
  document.getElementById('sellerBalance').textContent = balance.toLocaleString();
  openModal('addCoinsModal');
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeAllModals(); });
</script>
@endsection
