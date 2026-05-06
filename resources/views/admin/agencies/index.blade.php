@extends('admin.layouts.app')
@section('title', 'Agencies')
@section('breadcrumb', 'Agencies')

@section('content')

<div class="card-header" style="margin-bottom:20px">
  <div>
    <h3 style="font-size:16px;font-weight:700;color:var(--text)">🏢 Agencies</h3>
    <div style="font-size:12px;color:var(--text3);margin-top:2px">Users must join an agency to go live</div>
  </div>
  <button class="btn-primary" onclick="openModal('createModal')">+ New Agency</button>
</div>

@if(session('success'))
  <div style="background:rgba(39,174,96,.15);border:1px solid rgba(39,174,96,.3);color:#27ae60;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
    ✓ {{ session('success') }}
  </div>
@endif

@if($errors->any())
  <div style="background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:#e74c3c;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
    @foreach($errors->all() as $error)
      <div>✕ {{ $error }}</div>
    @endforeach
  </div>
@endif

<div class="card" style="padding:0">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Agency</th>
        <th>Code</th>
        <th>Members</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($agencies as $agency)
      <tr>
        <td>
          <div class="user-cell">
            @if($agency->logo_url)
              <img src="{{ $agency->logo_url }}" class="avatar-sm">
            @else
              <div class="avatar-sm" style="background:var(--purple);display:flex;align-items:center;justify-content:center;font-size:13px">🏢</div>
            @endif
            <div>
              <div style="font-weight:600;color:var(--text)">{{ $agency->name }}</div>
              @if($agency->description)
                <div style="font-size:11px;color:var(--text3)">{{ Str::limit($agency->description,40) }}</div>
              @endif
              @if($agency->email)
                <div style="font-size:11px;color:var(--text3)">{{ $agency->email }}</div>
              @endif
            </div>
          </div>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <code style="color:var(--gold);font-size:13px;letter-spacing:2px;background:rgba(255,215,0,.08);padding:3px 8px;border-radius:4px">{{ $agency->code }}</code>
            <form action="{{ route('admin.agencies.regenerate', $agency->id) }}" method="POST" style="display:inline">
              @csrf
              <button type="submit" class="btn-sm btn-warning" title="Regenerate" style="padding:2px 6px">↻</button>
            </form>
          </div>
        </td>
        <td>
          <span class="badge badge-live">{{ $agency->members_count }} members</span>
        </td>
        <td>
          <span class="badge {{ $agency->is_active ? 'badge-approved' : 'badge-rejected' }}">
            {{ $agency->is_active ? 'Active' : 'Inactive' }}
          </span>
        </td>
        <td style="color:var(--text3);font-size:12px">{{ $agency->created_at->format('M d, Y') }}</td>
        <td>
          <div class="action-btns">
            <button class="btn-sm btn-info"
                    onclick="editAgency({{ $agency->id }}, '{{ addslashes($agency->name) }}', '{{ addslashes($agency->description ?? '') }}', '{{ $agency->logo_url ?? '' }}', {{ $agency->is_active ? 1 : 0 }})">
              Edit
            </button>
            <form action="{{ route('admin.agencies.destroy', $agency->id) }}" method="POST"
                  onsubmit="return confirm('Delete this agency? All members will lose their agency.')">
              @csrf @method('DELETE')
              <button type="submit" class="btn-sm btn-danger">Delete</button>
            </form>
          </div>
        </td>
        <td>
            {{-- Salary Sheet Download Button (add inside agency row actions) --}}
<div style="margin-top:8px">
    <form method="GET"
          action="{{ route('admin.agencies.salary_sheet', $agency->id) }}"
          style="display:inline-flex;gap:8px;align-items:center">
        <select name="month" style="padding:4px 8px;border-radius:6px;
                background:var(--bg2);border:1px solid var(--border);
                color:var(--text);font-size:12px">
            @foreach(range(1,12) as $m)
                <option value="{{ $m }}"
                    {{ $m == now()->subMonth()->month ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                </option>
            @endforeach
        </select>
        <select name="year" style="padding:4px 8px;border-radius:6px;
                background:var(--bg2);border:1px solid var(--border);
                color:var(--text);font-size:12px">
            @foreach(range(now()->year, now()->year - 2) as $y)
                <option value="{{ $y }}"
                    {{ $y == now()->subMonth()->year ? 'selected' : '' }}>
                    {{ $y }}
                </option>
            @endforeach
        </select>
        <button type="submit"
                style="padding:5px 14px;border-radius:6px;
                       background:var(--accent);color:#fff;
                       border:none;cursor:pointer;font-size:12px;
                       display:inline-flex;align-items:center;gap:5px">
            📥 Salary Sheet
        </button>
    </form>
</div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" style="text-align:center;color:var(--text3);padding:40px">No agencies yet. Create one to get started.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $agencies->links() }}

{{-- Overlay --}}
<div id="modalOverlay" onclick="closeAllModals()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999;backdrop-filter:blur(2px)"></div>

{{-- Create Modal --}}
<div id="createModal"
     style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:480px;max-width:calc(100vw - 40px);background:var(--bg2);
            border:1px solid var(--border);border-radius:12px;z-index:1000;
            box-shadow:0 24px 80px rgba(0,0,0,.7)">
  <form action="{{ route('admin.agencies.store') }}" method="POST">
    @csrf
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:15px;font-weight:700;color:var(--gold)">🏢 Create Agency</div>
      <button type="button" onclick="closeAllModals()"
              style="background:none;border:none;color:var(--text3);font-size:22px;cursor:pointer;line-height:1;padding:0 4px">&times;</button>
    </div>
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
      <div class="form-group" style="margin:0">
        <label>Agency Name *</label>
        <input type="text" name="name" required maxlength="100" placeholder="e.g. Star Agency">
      </div>
      <div class="form-group" style="margin:0">
        <label>Description</label>
        <textarea name="description" rows="2" maxlength="500" placeholder="Short description..."></textarea>
      </div>
      <div class="form-group" style="margin:0">
        <label>Logo URL <span style="color:var(--text3)">(optional)</span></label>
        <input type="url" name="logo_url" placeholder="https://...">
      </div>
      <div style="border-top:1px solid var(--border);padding-top:14px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text3);margin-bottom:10px">Portal Login Credentials</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group" style="margin:0">
            <label>Email</label>
            <input type="email" name="portal_email" placeholder="agency@example.com">
          </div>
          <div class="form-group" style="margin:0">
            <label>Password</label>
            <input type="password" name="portal_password" placeholder="Min 6 chars">
          </div>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:6px">Agency owner logs in at /agency-portal with these credentials</div>
      </div>
      <div style="background:rgba(52,152,219,.08);border:1px solid rgba(52,152,219,.15);border-radius:6px;padding:8px 12px;font-size:12px;color:var(--info)">
        ℹ️ A unique invite code will be auto-generated.
      </div>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px">
      <button type="button" onclick="closeAllModals()" class="btn-secondary">Cancel</button>
      <button type="submit" class="btn-primary">Create Agency</button>
    </div>
  </form>
</div>

{{-- Edit Modal --}}
<div id="editModal"
     style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:480px;max-width:calc(100vw - 40px);background:var(--bg2);
            border:1px solid var(--border);border-radius:12px;z-index:1000;
            box-shadow:0 24px 80px rgba(0,0,0,.7)">
  <form id="editForm" method="POST">
    @csrf @method('PUT')
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:15px;font-weight:700;color:var(--gold)">✏️ Edit Agency</div>
      <button type="button" onclick="closeAllModals()"
              style="background:none;border:none;color:var(--text3);font-size:22px;cursor:pointer;line-height:1;padding:0 4px">&times;</button>
    </div>
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
      <div class="form-group" style="margin:0">
        <label>Agency Name *</label>
        <input type="text" id="editName" name="name" required>
      </div>
      <div class="form-group" style="margin:0">
        <label>Description</label>
        <textarea id="editDesc" name="description" rows="2"></textarea>
      </div>
      {{-- Commission % field --}}
        <div class="form-group">
            <label>Commission Percentage (%)</label>
            <input type="number"
                   name="commission_pct"
                   value="{{ $agency->commission_pct ?? 20 }}"
                   min="0" max="100" step="0.01"
                   placeholder="20">
            <small style="color:var(--text3)">
                % of total diamond amount paid as agency commission. Default: 20%
            </small>
        </div>
      <div class="form-group" style="margin:0">
        <label>Logo URL</label>
        <input type="url" id="editLogo" name="logo_url">
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <input type="checkbox" id="editActive" name="is_active" value="1"
               style="width:16px;height:16px;accent-color:var(--gold);cursor:pointer">
        <label for="editActive" style="font-size:13px;color:var(--text);cursor:pointer">Active</label>
      </div>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px">
      <button type="button" onclick="closeAllModals()" class="btn-secondary">Cancel</button>
      <button type="submit" class="btn-primary">Save Changes</button>
    </div>
  </form>
</div>

<script>
function openModal(id) {
  document.getElementById('modalOverlay').style.display = 'block';
  document.getElementById(id).style.display = 'block';
}
function closeAllModals() {
  document.getElementById('modalOverlay').style.display = 'none';
  document.getElementById('createModal').style.display  = 'none';
  document.getElementById('editModal').style.display    = 'none';
}
function editAgency(id, name, desc, logo, active) {
  document.getElementById('editForm').action     = '/admin/agencies/' + id;
  document.getElementById('editName').value      = name;
  document.getElementById('editDesc').value      = desc;
  document.getElementById('editLogo').value      = logo;
  document.getElementById('editActive').checked  = active === 1;
  openModal('editModal');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllModals(); });
</script>
@endsection
