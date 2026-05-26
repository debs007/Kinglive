@extends('admin.layouts.app')
@section('title', 'Frames')
@section('breadcrumb', 'Frames')

@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
    <h2>Avatar Frames</h2>
    <button class="btn-primary" onclick="document.getElementById('uploadModal').classList.remove('hidden')">
        + Upload Frame
    </button>
</div>

<div id="alertBox" style="display:none;padding:12px 16px;border-radius:8px;margin-bottom:16px"></div>

@if(session('success'))
<div style="background:#1a3a2a;border:1px solid #27ae60;color:#2ecc71;padding:12px 16px;border-radius:8px;margin-bottom:16px">
    ✅ {{ session('success') }}
</div>
@endif

{{-- Frame Grid --}}
<div id="frameGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px">
    @forelse($frames as $frame)
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;overflow:hidden">
        {{-- Thumbnail --}}
        <div style="position:relative;aspect-ratio:1/1;background:#0a0a1a;display:flex;align-items:center;justify-content:center">
            @if($frame->thumbnail_url)
                <img src="{{ $frame->thumbnail_url }}" alt="{{ $frame->name }}"
                     style="width:80%;height:80%;object-fit:contain">
            @else
                <span style="color:var(--text3);font-size:12px">No preview</span>
            @endif
            @if(!$frame->is_active)
            <div style="position:absolute;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center">
                <span style="color:#e74c3c;font-weight:700;font-size:12px">DISABLED</span>
            </div>
            @endif
        </div>

        <div style="padding:10px 12px">
            <div style="font-weight:600;font-size:13px;color:var(--text)">{{ $frame->name }}</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">
                @if($frame->price > 0)
                    🪙 {{ number_format($frame->price) }} coins
                @else
                    🎁 Gift only
                @endif
            </div>
        </div>

        <div style="padding:0 12px 12px;display:flex;gap:8px">
            <form method="POST" action="{{ route('admin.frames.toggle', $frame->id) }}" style="flex:1">
                @csrf
                <button type="submit" style="width:100%;padding:6px;border-radius:6px;border:1px solid var(--border);cursor:pointer;font-size:12px;background:{{ $frame->is_active ? 'var(--bg3)' : '#2d1b4e' }};color:{{ $frame->is_active ? 'var(--text3)' : '#9b59b6' }}">
                    {{ $frame->is_active ? 'Disable' : 'Enable' }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.frames.destroy', $frame->id) }}"
                  onsubmit="return confirm('Delete this frame?')">
                @csrf @method('DELETE')
                <button type="submit" style="padding:6px 10px;border-radius:6px;border:1px solid #e74c3c;background:transparent;color:#e74c3c;cursor:pointer;font-size:12px">
                    Delete
                </button>
            </form>
        </div>
    </div>
    @empty
    <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text3)">
        No frames yet. Upload one!
    </div>
    @endforelse
</div>

{{-- Upload Modal --}}
<div id="uploadModal" class="modal hidden">
    <div class="modal-content" style="max-width:480px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3>Upload Frame</h3>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')"
                    style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:20px">✕</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px">
            <div>
                <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Frame Name *</label>
                <input id="frameName" type="text" placeholder="e.g. Golden Crown"
                       style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Price (coins) — 0 for gift-only</label>
                <input id="framePrice" type="number" min="0" value="0"
                       style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">SVGA File *</label>
                <input id="svgaFile" type="file" accept=".svga"
                       style="color:var(--text);font-size:12px">
                <div id="svgaProgress" style="display:none;margin-top:6px">
                    <div style="height:4px;background:var(--bg3);border-radius:2px">
                        <div id="svgaBar" style="height:100%;background:#9b59b6;border-radius:2px;width:0%;transition:width .2s"></div>
                    </div>
                    <span id="svgaStatus" style="font-size:11px;color:var(--text3)">Uploading...</span>
                </div>
            </div>

            <div>
                <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Thumbnail Image (PNG/JPG)</label>
                <input id="thumbFile" type="file" accept="image/*"
                       style="color:var(--text);font-size:12px">
                <div id="thumbProgress" style="display:none;margin-top:6px">
                    <div style="height:4px;background:var(--bg3);border-radius:2px">
                        <div id="thumbBar" style="height:100%;background:#3498db;border-radius:2px;width:0%;transition:width .2s"></div>
                    </div>
                    <span id="thumbStatus" style="font-size:11px;color:var(--text3)">Uploading...</span>
                </div>
            </div>

            <button id="uploadBtn" onclick="uploadFrame()"
                    class="btn-primary" style="margin-top:4px">
                Upload Frame
            </button>
        </div>
    </div>
</div>

<script>
let svgaUrl = null, thumbUrl = null;

async function uploadFile(file, type, barId, statusId, progressId) {
    document.getElementById(progressId).style.display = 'block';
    const name = document.getElementById('frameName').value.trim() || 'frame';

    // Get presigned URL
    const res = await fetch('{{ route('admin.frames.upload_url') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ name, type })
    });
    const { upload_url, file_url } = await res.json();

    // Upload to S3 using XHR for progress
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = e => {
            if (e.lengthComputable) {
                document.getElementById(barId).style.width = (e.loaded / e.total * 100) + '%';
            }
        };
        xhr.onload = () => {
            document.getElementById(statusId).textContent = '✓ Done';
            resolve(file_url);
        };
        xhr.onerror = () => reject(new Error('Upload failed'));
        xhr.open('PUT', upload_url);
        xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
        xhr.send(file);
    });
}

async function uploadFrame() {
    const name  = document.getElementById('frameName').value.trim();
    const price = parseInt(document.getElementById('framePrice').value) || 0;
    const svga  = document.getElementById('svgaFile').files[0];
    const thumb = document.getElementById('thumbFile').files[0];

    if (!name) { alert('Enter a frame name.'); return; }
    if (!svga)  { alert('Select a SVGA file.'); return; }

    const btn = document.getElementById('uploadBtn');
    btn.disabled = true;
    btn.textContent = 'Uploading...';

    try {
        svgaUrl = await uploadFile(svga, 'svga', 'svgaBar', 'svgaStatus', 'svgaProgress');
        if (thumb) {
            thumbUrl = await uploadFile(thumb, 'thumbnail', 'thumbBar', 'thumbStatus', 'thumbProgress');
        }

        // Save to DB
        const save = await fetch('{{ route('admin.frames.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ name, svga_url: svgaUrl, thumbnail_url: thumbUrl, price })
        });

        if (save.ok) {
            window.location.reload();
        } else {
            const err = await save.json();
            alert('Save failed: ' + JSON.stringify(err));
        }
    } catch (e) {
        alert('Upload error: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'Upload Frame';
    }
}
</script>
@endsection