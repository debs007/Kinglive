@extends('admin.layouts.app')
@section('title', 'Banners')
@section('breadcrumb', 'Banners')

@section('content')

<div class="card-header" style="margin-bottom:20px">
  <div>
    <h3 style="font-size:16px;font-weight:700">🖼 Home Screen Banners</h3>
    <div style="font-size:12px;color:var(--text3);margin-top:2px">Banners shown on the app home screen slider</div>
  </div>
  <button class="btn-primary" onclick="openModal('createModal')">+ Add Banner</button>
</div>

@if(session('success'))
  <div style="background:rgba(39,174,96,.15);border:1px solid rgba(39,174,96,.3);color:#27ae60;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
    ✓ {{ session('success') }}
  </div>
@endif

<div class="card" style="padding:0">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Banner</th>
        <th>Title</th>
        <th>Link</th>
        <th>Order</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($banners as $banner)
      <tr>
        <td>
          <img src="{{ $banner->image_url }}" alt="banner"
               style="width:80px;height:45px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
        </td>
        <td style="color:var(--text)">{{ $banner->title ?? '—' }}</td>
        <td style="color:var(--text3);font-size:12px">
          {{ $banner->link_url ? Str::limit($banner->link_url, 30) : '—' }}
        </td>
        <td style="color:var(--text3)">{{ $banner->sort_order }}</td>
        <td>
          <span class="badge {{ $banner->is_active ? 'badge-approved' : 'badge-rejected' }}">
            {{ $banner->is_active ? 'Active' : 'Inactive' }}
          </span>
        </td>
        <td>
          <div class="action-btns">
            <form action="{{ route('admin.banners.toggle', $banner->id) }}" method="POST">
              @csrf
              <button type="submit" class="btn-sm btn-warning">
                {{ $banner->is_active ? 'Deactivate' : 'Activate' }}
              </button>
            </form>
            <form action="{{ route('admin.banners.destroy', $banner->id) }}" method="POST"
                  onsubmit="return confirm('Delete this banner?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn-sm btn-danger">Delete</button>
            </form>
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" style="text-align:center;color:var(--text3);padding:40px">
          No banners yet. Add one to show on the home screen.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $banners->links() }}

{{-- Overlay --}}
<div id="modalOverlay" onclick="closeAllModals()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999;backdrop-filter:blur(2px)"></div>

{{-- Create Modal --}}
<div id="createModal"
     style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:480px;max-width:calc(100vw - 40px);background:var(--bg2);
            border:1px solid var(--border);border-radius:12px;z-index:1000;
            box-shadow:0 24px 80px rgba(0,0,0,.7)">
  <form action="{{ route('admin.banners.store') }}" method="POST">
    @csrf
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:15px;font-weight:700;color:var(--gold)">🖼 Add Banner</div>
      <button type="button" onclick="closeAllModals()"
              style="background:none;border:none;color:var(--text3);font-size:22px;cursor:pointer">&times;</button>
    </div>
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
      <div class="form-group" style="margin:0">
        <label>Image URL * <span style="color:var(--text3)">(CDN URL)</span></label>
        <input type="text" name="image_url" required placeholder="https://cdn.example.com/banner.jpg" style="width:100%">
        <div style="font-size:11px;color:var(--text3);margin-top:4px">Recommended: 16:9 ratio, min 800×450px</div>
      </div>
      <div class="form-group" style="margin:0">
        <label>Title <span style="color:var(--text3)">(optional)</span></label>
        <input type="text" name="title" maxlength="100" placeholder="Banner title" style="width:100%">
      </div>
      <div class="form-group" style="margin:0">
        <label>Link URL <span style="color:var(--text3)">(optional tap action)</span></label>
        <input type="text" name="link_url" maxlength="255" placeholder="https://... or deeplink" style="width:100%">
      </div>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px">
      <button type="button" onclick="closeAllModals()" class="btn-secondary">Cancel</button>
      <button type="submit" class="btn-primary">Add Banner</button>
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
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeAllModals(); });
</script>
@endsection
