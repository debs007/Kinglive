@extends('admin.layouts.app')
@section('title', 'Level Frames')
@section('breadcrumb', 'Level Frames')

@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
        <h2>🎖️ Level Frames</h2>
        <p style="color:var(--text3);font-size:12px;margin-top:4px">Frames unlocked automatically when user reaches the specified level</p>
    </div>
    <button class="btn-primary" onclick="document.getElementById('uploadModal').classList.remove('hidden')">
        + Add Level Frame
    </button>
</div>

@if(session('success'))
<div style="background:#1a3a2a;border:1px solid #27ae60;color:#2ecc71;padding:12px 16px;border-radius:8px;margin-bottom:16px">
    ✅ {{ session('success') }}
</div>
@endif

<div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="border-bottom:2px solid var(--border)">
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">Preview</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">Name</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">Min Level</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">Max Level</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">Status</th>
                <th style="padding:10px 12px;text-align:left;color:var(--text3)">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($frames as $frame)
            <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:10px 12px">
                    @if($frame->thumbnail_url)
                        <img src="{{ $frame->thumbnail_url }}" style="width:48px;height:48px;object-fit:contain">
                    @else
                        <span style="color:var(--text3)">—</span>
                    @endif
                </td>
                <td style="padding:10px 12px;color:var(--text);font-weight:600">{{ $frame->name }}</td>
                <td style="padding:10px 12px">
                    <span style="background:#2D1B4E;color:#9b59b6;padding:3px 10px;border-radius:12px;font-weight:700">
                        Lv.{{ $frame->min_level }}
                    </span>
                </td>
                <td style="padding:10px 12px;color:var(--text3)">
                    {{ $frame->max_level ? 'Lv.'.$frame->max_level : '∞' }}
                </td>
                <td style="padding:10px 12px">
                    <span style="color:{{ $frame->is_active ? '#27ae60' : '#e74c3c' }};font-size:12px">
                        {{ $frame->is_active ? '● Active' : '○ Disabled' }}
                    </span>
                </td>
                <td style="padding:10px 12px;display:flex;gap:8px">
                    <form method="POST" action="{{ route('admin.level_frames.toggle', $frame->id) }}">
                        @csrf
                        <button type="submit"
                                style="padding:5px 10px;border-radius:6px;border:1px solid var(--border);background:var(--bg3);color:var(--text3);cursor:pointer;font-size:11px">
                            {{ $frame->is_active ? 'Disable' : 'Enable' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.level_frames.destroy', $frame->id) }}"
                          onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                style="padding:5px 10px;border-radius:6px;border:1px solid #e74c3c;background:transparent;color:#e74c3c;cursor:pointer;font-size:11px">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="padding:40px;text-align:center;color:var(--text3)">
                    No level frames yet.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Upload Modal --}}
<div id="uploadModal" class="modal hidden">
    <div class="modal-content" style="max-width:480px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3>Add Level Frame</h3>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')"
                    style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:20px">✕</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                    <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Min Level *</label>
                    <input id="minLevel" type="number" min="1" max="178" value="1"
                           style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px">
                </div>
                <div>
                    <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Max Level (optional)</label>
                    <input id="maxLevel" type="number" min="1" max="178" placeholder="leave empty = no limit"
                           style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px">
                </div>
            </div>

            <div>
                <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Frame Name *</label>
                <input id="frameName" type="text" placeholder="e.g. Golden Crown Lv.60"
                       style="width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">SVGA File *</label>
                <input id="svgaFile" type="file" accept=".svga" style="color:var(--text);font-size:12px">
                <div id="svgaProgress" style="display:none;margin-top:6px">
                    <div style="height:4px;background:var(--bg3);border-radius:2px">
                        <div id="svgaBar" style="height:100%;background:#9b59b6;border-radius:2px;width:0%;transition:width .2s"></div>
                    </div>
                    <span id="svgaStatus" style="font-size:11px;color:var(--text3)"></span>
                </div>
            </div>

            <div>
                <label style="color:var(--text3);font-size:12px;display:block;margin-bottom:4px">Thumbnail (PNG/JPG)</label>
                <input id="thumbFile" type="file" accept="image/*" style="color:var(--text);font-size:12px">
                <div id="thumbProgress" style="display:none;margin-top:6px">
                    <div style="height:4px;background:var(--bg3);border-radius:2px">
                        <div id="thumbBar" style="height:100%;background:#3498db;border-radius:2px;width:0%;transition:width .2s"></div>
                    </div>
                    <span id="thumbStatus" style="font-size:11px;color:var(--text3)"></span>
                </div>
            </div>

            <button id="saveBtn" onclick="saveLevelFrame()" class="btn-primary">
                Save Frame
            </button>
        </div>
    </div>
</div>

<script>
async function uploadFile(file, type, barId, statusId, progressId) {
    document.getElementById(progressId).style.display = 'block';
    const minLevel = document.getElementById('minLevel').value;
    const res = await fetch('{{ route('admin.level_frames.upload_url') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ min_level: minLevel, type })
    });
    const { upload_url, file_url } = await res.json();
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = e => {
            if (e.lengthComputable)
                document.getElementById(barId).style.width = (e.loaded/e.total*100)+'%';
        };
        xhr.onload = () => { document.getElementById(statusId).textContent = '✓ Done'; resolve(file_url); };
        xhr.onerror = reject;
        xhr.open('PUT', upload_url);
        xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
        xhr.send(file);
    });
}

async function saveLevelFrame() {
    const name  = document.getElementById('frameName').value.trim();
    const svga  = document.getElementById('svgaFile').files[0];
    const thumb = document.getElementById('thumbFile').files[0];
    const minLv = document.getElementById('minLevel').value;
    const maxLv = document.getElementById('maxLevel').value || null;

    if (!name || !svga) { alert('Name and SVGA file are required.'); return; }

    const btn = document.getElementById('saveBtn');
    btn.disabled = true; btn.textContent = 'Uploading...';

    try {
        const svgaUrl  = await uploadFile(svga, 'svga', 'svgaBar', 'svgaStatus', 'svgaProgress');
        const thumbUrl = thumb ? await uploadFile(thumb, 'thumbnail', 'thumbBar', 'thumbStatus', 'thumbProgress') : null;

        const res = await fetch('{{ route('admin.level_frames.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ min_level: minLv, max_level: maxLv, name, svga_url: svgaUrl, thumbnail_url: thumbUrl })
        });
        if (res.ok) window.location.reload();
        else alert('Save failed.');
    } catch(e) {
        alert('Error: ' + e.message);
        btn.disabled = false; btn.textContent = 'Save Frame';
    }
}
</script>
@endsection
