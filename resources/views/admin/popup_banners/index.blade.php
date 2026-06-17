@extends('admin.layouts.app')
@section('title', 'Popup Banners')
@section('breadcrumb', 'Popup Banners')

@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
        <h2>📢 Popup Banners</h2>
        <p style="color:var(--text3);font-size:12px;margin-top:4px">
            Fullscreen popups shown to users on every app launch, one by one. Multiple can be active.
        </p>
    </div>
    <button class="btn-primary" onclick="document.getElementById('uploadModal').classList.remove('hidden')">
        + New Banner
    </button>
</div>

@if(session('success'))
<div style="background:#1a3a2a;border:1px solid #27ae60;color:#2ecc71;padding:12px 16px;border-radius:8px;margin-bottom:16px">
    ✅ {{ session('success') }}
</div>
@endif

{{-- Active banners count --}}
@php $activeCount = $banners->where('is_active', true)->count(); @endphp
@if($activeCount > 0)
<div style="background:rgba(155,89,182,.15);border:1px solid #9b59b6;border-radius:12px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <span style="font-size:20px">🟢</span>
    <div>
        <div style="color:#9b59b6;font-weight:700;font-size:14px">{{ $activeCount }} Active Popup Banner{{ $activeCount > 1 ? 's' : '' }}</div>
        <div style="color:var(--text3);font-size:12px">Shown to users one by one on every app launch</div>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
    @forelse($banners as $banner)
    <div style="background:var(--bg2);border:{{ $banner->is_active ? '2px solid #9b59b6' : '1px solid var(--border)' }};border-radius:14px;overflow:hidden">

        {{-- Image preview --}}
        <div style="position:relative;aspect-ratio:9/16;background:#0a0618;max-height:200px;overflow:hidden">
            <img src="{{ $banner->image_url }}" alt="banner"
                 style="width:100%;height:100%;object-fit:cover">
            @if($banner->is_active)
            <div style="position:absolute;top:8px;left:8px;background:#9b59b6;color:#fff;font-size:10px;font-weight:700;padding:3px 8px;border-radius:10px">
                🟢 LIVE
            </div>
            @endif
        </div>

        <div style="padding:12px">
            <div style="font-weight:600;color:var(--text);font-size:13px;margin-bottom:4px">
                {{ $banner->title ?? 'No title' }}
            </div>
            @if($banner->action_label)
            <div style="font-size:11px;color:var(--text3);margin-bottom:4px">
                Button: {{ $banner->action_label }}
            </div>
            @endif
            @if($banner->starts_at || $banner->ends_at)
            <div style="font-size:11px;color:var(--text3);margin-bottom:8px">
                📅 {{ $banner->starts_at?->format('M d') ?? '—' }} → {{ $banner->ends_at?->format('M d') ?? '∞' }}
            </div>
            @endif

            <div style="display:flex;gap:8px;margin-top:8px">
                <form method="POST" action="{{ route('admin.popup_banners.toggle', $banner->id) }}" style="flex:1">
                    @csrf
                    <button type="submit" style="width:100%;padding:7px;border-radius:8px;border:none;cursor:pointer;font-size:12px;font-weight:600;background:{{ $banner->is_active ? '#2d1b4e' : '#9b59b6' }};color:{{ $banner->is_active ? '#9b59b6' : '#fff' }}">
                        {{ $banner->is_active ? '⏸ Deactivate' : '▶ Activate' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.popup_banners.destroy', $banner->id) }}"
                      onsubmit="return confirm('Delete this banner?')">
                    @csrf @method('DELETE')
                    <button type="submit" style="padding:7px 12px;border-radius:8px;border:1px solid #e74c3c;background:transparent;color:#e74c3c;cursor:pointer;font-size:12px">
                        🗑
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text3)">
        No banners yet. Create one!
    </div>
    @endforelse
</div>

{{-- Upload Modal --}}
<div id="uploadModal" class="modal hidden">
    <div class="modal-content" style="max-width:500px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3>New Popup Banner</h3>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')"
                    style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:20px">✕</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px">
            <div>
                <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Banner Image * (recommended: portrait 9:16)</label>
                <input id="bannerFile" type="file" accept="image/*"
                       style="color:var(--text);font-size:12px">
                <div id="imgProgress" style="display:none;margin-top:6px">
                    <div style="height:4px;background:var(--bg3);border-radius:2px">
                        <div id="imgBar" style="height:100%;background:#9b59b6;border-radius:2px;width:0%;transition:width .2s"></div>
                    </div>
                    <span id="imgStatus" style="font-size:11px;color:var(--text3)"></span>
                </div>
            </div>

            <div>
                <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Title (optional)</label>
                <input id="bannerTitle" type="text" placeholder="e.g. Special Event!"
                       style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                    <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Action Button Label</label>
                    <input id="actionLabel" type="text" placeholder="e.g. Learn More"
                           style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px">
                </div>
                <div>
                    <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Action URL (optional)</label>
                    <input id="actionUrl" type="text" placeholder="https://..."
                           style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                    <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Show From (optional)</label>
                    <input id="startsAt" type="datetime-local"
                           style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:12px">
                </div>
                <div>
                    <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Show Until (optional)</label>
                    <input id="endsAt" type="datetime-local"
                           style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:12px">
                </div>
            </div>

            <button id="saveBtn" onclick="saveBanner()" class="btn-primary">
                Save Banner
            </button>
        </div>
    </div>
</div>

<script>
async function saveBanner() {
    const file = document.getElementById('bannerFile').files[0];
    if (!file) { alert('Please select an image.'); return; }

    const btn = document.getElementById('saveBtn');
    btn.disabled = true; btn.textContent = 'Uploading...';

    document.getElementById('imgProgress').style.display = 'block';

    try {
        // Get presigned URL
        const res = await fetch('{{ route('admin.popup_banners.upload_url') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const { upload_url, file_url } = await res.json();

        // Upload to S3
        await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.upload.onprogress = e => {
                if (e.lengthComputable)
                    document.getElementById('imgBar').style.width = (e.loaded/e.total*100)+'%';
            };
            xhr.onload = () => { document.getElementById('imgStatus').textContent = '✓ Done'; resolve(); };
            xhr.onerror = reject;
            xhr.open('PUT', upload_url);
            xhr.setRequestHeader('Content-Type', file.type);
            xhr.send(file);
        });

        // Save to DB
        const save = await fetch('{{ route('admin.popup_banners.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({
                image_url:    file_url,
                title:        document.getElementById('bannerTitle').value || null,
                action_label: document.getElementById('actionLabel').value || null,
                action_url:   document.getElementById('actionUrl').value   || null,
                starts_at:    document.getElementById('startsAt').value    || null,
                ends_at:      document.getElementById('endsAt').value      || null,
            })
        });

        if (save.ok) window.location.reload();
        else alert('Failed to save banner.');
    } catch(e) {
        alert('Error: ' + e.message);
        btn.disabled = false; btn.textContent = 'Save Banner';
    }
}
</script>
@endsection