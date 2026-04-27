@extends('admin.layouts.app')
@section('title', 'Room Backgrounds')
@section('breadcrumb', 'Backgrounds')

@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
    <h2>Room Backgrounds</h2>
    <button class="btn-primary" onclick="document.getElementById('uploadModal').classList.remove('hidden')">
        + Upload Background
    </button>
</div>

<div id="alertBox" style="display:none;padding:12px 16px;border-radius:8px;margin-bottom:16px"></div>

@if(session('success'))
<div style="background:#1a3a2a;border:1px solid #27ae60;color:#2ecc71;padding:12px 16px;border-radius:8px;margin-bottom:16px">
    ✅ {{ session('success') }}
</div>
@endif

{{-- Background Grid --}}
<div id="bgGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
    @forelse($backgrounds as $bg)
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;overflow:hidden">
        <div style="position:relative;aspect-ratio:16/9">
            <img src="{{ $bg->image_url }}" alt="{{ $bg->name }}"
                 style="width:100%;height:100%;object-fit:cover">
            @if(!$bg->is_active)
            <div style="position:absolute;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center">
                <span style="color:#e74c3c;font-weight:700;font-size:12px">DISABLED</span>
            </div>
            @endif
        </div>
        <div style="padding:10px 12px">
            <div style="font-weight:600;font-size:13px;color:var(--text)">{{ $bg->name }}</div>
        </div>
        <div style="padding:0 12px 12px;display:flex;gap:8px">
            <form method="POST" action="{{ route('admin.backgrounds.toggle', $bg->id) }}" style="flex:1">
                @csrf
                <button type="submit" style="width:100%;padding:6px;border-radius:6px;border:1px solid var(--border);cursor:pointer;font-size:12px;background:{{ $bg->is_active ? 'var(--bg3)' : '#2d1b4e' }};color:{{ $bg->is_active ? 'var(--text3)' : '#9b59b6' }}">
                    {{ $bg->is_active ? 'Disable' : 'Enable' }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.backgrounds.destroy', $bg->id) }}"
                  onsubmit="return confirm('Delete this background?')">
                @csrf @method('DELETE')
                <button type="submit" style="padding:6px 10px;border-radius:6px;border:1px solid #e74c3c;background:transparent;color:#e74c3c;cursor:pointer;font-size:12px">
                    Delete
                </button>
            </form>
        </div>
    </div>
    @empty
    <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text3)">
        No backgrounds yet. Upload one!
    </div>
    @endforelse
</div>

{{-- Upload Modal --}}
<div id="uploadModal" class="modal hidden">
    <div class="modal-content" style="max-width:460px">
        <h3>📸 Upload Background</h3>
        <p style="color:var(--text3);font-size:13px;margin-bottom:16px">
            Select a JPG/PNG image (max 10MB, 16:9 recommended).
        </p>

        <div class="form-group">
            <label>Background Name *</label>
            <input type="text" id="bgName" placeholder="e.g. Purple Galaxy"
                   style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:6px;font-size:13px">
        </div>

        <div class="form-group" style="margin-top:12px">
            <label>Image File * (JPG/PNG)</label>
            <input type="file" id="bgFile" accept="image/jpeg,image/jpg,image/png"
                   onchange="previewBg(this)"
                   style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:6px;font-size:13px">
        </div>

        <div id="bgPreview" style="display:none;margin-top:12px">
            <img id="bgPreviewImg" style="width:100%;border-radius:8px;aspect-ratio:16/9;object-fit:cover">
        </div>

        <div id="progressWrap" style="display:none;margin-top:12px">
            <div style="background:var(--bg3);border-radius:4px;height:6px;overflow:hidden">
                <div id="progressBar" style="background:#9b59b6;height:100%;width:0%;transition:width .3s"></div>
            </div>
            <div id="progressText" style="font-size:12px;color:var(--text3);margin-top:6px;text-align:center">Preparing…</div>
        </div>

        <div class="form-actions" style="margin-top:20px">
            <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" id="uploadBtn" class="btn-primary" onclick="doUpload()">Upload</button>
        </div>
    </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';

async function doUpload() {
    const name = document.getElementById('bgName').value.trim();
    const file = document.getElementById('bgFile').files[0];

    if (!name) { alert('Please enter a name.'); return; }
    if (!file)  { alert('Please select an image.'); return; }

    document.getElementById('uploadBtn').disabled = true;
    setProgress(true, 10, 'Getting upload URL…');

    try {
        // Step 1 — Get presigned URL from admin route (session auth, no JWT)
        const urlRes = await fetch('{{ route('admin.backgrounds.upload_url') }}', {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  CSRF,
            },
            body: JSON.stringify({ name }),
        });

        const urlData = await urlRes.json();
        if (!urlRes.ok) throw new Error(urlData.message || 'Could not get upload URL');
        setProgress(true, 30, 'Uploading image to CDN…');

        // Step 2 — Upload directly to S3 (presigned PUT, no auth header)
        const s3Res = await fetch(urlData.upload_url, {
            method: 'PUT',
            body:   file,
        });
        if (!s3Res.ok) throw new Error('CDN upload failed (' + s3Res.status + ')');
        setProgress(true, 80, 'Saving…');

        // Step 3 — Save record in DB
        const saveRes = await fetch('{{ route('admin.backgrounds.store') }}', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify({ name, image_url: urlData.file_url }),
        });
        const saveData = await saveRes.json();
        if (!saveRes.ok) throw new Error(saveData.message || 'Failed to save record');

        setProgress(true, 100, 'Done! ✅');
        setTimeout(() => { closeModal(); window.location.reload(); }, 800);

    } catch (err) {
        setProgress(false, 0, '');
        document.getElementById('uploadBtn').disabled = false;
        showAlert('Upload failed: ' + err.message, 'error');
    }
}

function previewBg(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('bgPreviewImg').src = e.target.result;
        document.getElementById('bgPreview').style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}

function setProgress(show, pct, msg) {
    document.getElementById('progressWrap').style.display = show ? 'block' : 'none';
    document.getElementById('progressBar').style.width    = pct + '%';
    document.getElementById('progressText').textContent   = msg;
}

function closeModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    document.getElementById('bgName').value  = '';
    document.getElementById('bgFile').value  = '';
    document.getElementById('bgPreview').style.display  = 'none';
    document.getElementById('uploadBtn').disabled = false;
    setProgress(false, 0, '');
}

function showAlert(msg, type) {
    const box  = document.getElementById('alertBox');
    box.style.display    = 'block';
    box.style.background = type === 'success' ? '#1a3a2a' : '#3a1a1a';
    box.style.border     = '1px solid ' + (type === 'success' ? '#27ae60' : '#e74c3c');
    box.style.color      = type === 'success' ? '#2ecc71' : '#e74c3c';
    box.textContent      = (type === 'success' ? '✅ ' : '❌ ') + msg;
    setTimeout(() => box.style.display = 'none', 5000);
}
</script>
@endsection