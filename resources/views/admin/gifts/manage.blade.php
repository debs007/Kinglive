@extends('admin.layouts.app')
@section('title', 'Gift Catalog')
@section('breadcrumb', 'Economy › Gift Catalog')

@section('content')
<div class="page-header">
    <h2>Gift Catalog</h2>
    <button class="btn-primary" onclick="document.getElementById('addGiftModal').classList.remove('hidden')">+ Add Gift</button>
</div>

<div class="card">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Gift</th><th>Category</th><th>Rarity</th>
                <th>Coin Price</th><th>Diamond Value</th>
                <th>Order</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gifts as $gift)
            <tr>
                <td>
                    <div class="user-cell">
                        <img src="{{ $gift->thumbnail_url }}" style="width:36px;height:36px;border-radius:8px;object-fit:cover" alt="">
                        <span style="font-weight:600">{{ $gift->name }}</span>
                    </div>
                </td>
                <td>{{ ucfirst($gift->category) }}</td>
                <td>
                    <span class="badge badge-{{ $gift->rarity === 'legendary' ? 'global' : ($gift->rarity === 'epic' ? 'room' : ($gift->rarity === 'rare' ? 'chat' : 'active')) }}">
                        {{ strtoupper($gift->rarity) }}
                    </span>
                </td>
                <td class="text-amber">🪙 {{ number_format($gift->coin_price) }}</td>
                <td class="text-blue">💎 {{ number_format($gift->diamond_value) }}</td>
                <td>{{ $gift->sort_order }}</td>
                <td>
                    <span class="badge {{ $gift->is_active ? 'badge-active' : 'badge-expired' }}">
                        {{ $gift->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td>
                    <div class="action-btns">
                        <button class="btn-sm btn-info" onclick="editGift({{ $gift->id }}, '{{ addslashes($gift->name) }}', '{{ $gift->svga_url }}', '{{ $gift->thumbnail_url }}', {{ $gift->coin_price }}, {{ $gift->diamond_value }}, '{{ $gift->category }}', '{{ $gift->rarity }}', {{ $gift->sort_order }}, {{ $gift->is_active ? 1 : 0 }})">Edit</button>
                        <form action="{{ route('admin.gifts.destroy', $gift->id) }}" method="POST" style="display:inline"
                              onsubmit="return confirm('Deactivate this gift?')">
                            @csrf @method('DELETE')
                            <button class="btn-sm btn-danger">Deactivate</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text3)">No gifts found. Add one!</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px">{{ $gifts->links() }}</div>
</div>

{{-- Add Gift Modal --}}
<div id="addGiftModal" class="modal hidden">
    <div class="modal-content" style="width:500px">
        <h3>🎁 Add New Gift</h3>
        <form action="{{ route('admin.gifts.store') }}" method="POST">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Golden Rose">
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category">
                        @foreach(['flowers','sweet','love','fun','music','luxury','royal','fantasy','cosmic','special'] as $cat)
                        <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Coin Price *</label>
                    <input type="number" name="coin_price" required min="1" placeholder="10">
                </div>
                <div class="form-group">
                    <label>Diamond Value *</label>
                    <input type="number" name="diamond_value" required min="1" placeholder="8">
                </div>
                <div class="form-group">
                    <label>Rarity *</label>
                    <select name="rarity">
                        <option value="common">Common</option>
                        <option value="rare">Rare</option>
                        <option value="epic">Epic</option>
                        <option value="legendary">Legendary</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="0">
                </div>
            </div>
            <div class="form-group">
                <label>SVGA URL * <small style="color:var(--text3)">(upload to CDN first)</small></label>
                <input type="url" name="svga_url" required placeholder="https://cdn.kinglive.app/gifts/svga/name.svga">
            </div>
            <div class="form-group">
                <label>Thumbnail URL *</label>
                <input type="url" name="thumbnail_url" required placeholder="https://cdn.kinglive.app/gifts/thumb/name.png">
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('addGiftModal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-primary">Add Gift</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Gift Modal --}}
<div id="editGiftModal" class="modal hidden">
    <div class="modal-content" style="width:500px">
        <h3>✏️ Edit Gift</h3>
        <form id="editGiftForm" method="POST">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="editName" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="editCategory">
                        @foreach(['flowers','sweet','love','fun','music','luxury','royal','fantasy','cosmic','special'] as $cat)
                        <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Coin Price</label>
                    <input type="number" name="coin_price" id="editCoinPrice" required min="1">
                </div>
                <div class="form-group">
                    <label>Diamond Value</label>
                    <input type="number" name="diamond_value" id="editDiamondValue" required min="1">
                </div>
                <div class="form-group">
                    <label>Rarity</label>
                    <select name="rarity" id="editRarity">
                        <option value="common">Common</option>
                        <option value="rare">Rare</option>
                        <option value="epic">Epic</option>
                        <option value="legendary">Legendary</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="editSortOrder">
                </div>
            </div>
            <div class="form-group">
                <label>SVGA URL</label>
                <input type="url" name="svga_url" id="editSvgaUrl" required>
            </div>
            <div class="form-group">
                <label>Thumbnail URL</label>
                <input type="url" name="thumbnail_url" id="editThumbnailUrl" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" id="editIsActive">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('editGiftModal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function editGift(id, name, svga, thumb, coinPrice, diamondValue, category, rarity, sortOrder, isActive) {
    document.getElementById('editGiftForm').action = `/admin/gifts/${id}`;
    document.getElementById('editName').value = name;
    document.getElementById('editSvgaUrl').value = svga;
    document.getElementById('editThumbnailUrl').value = thumb;
    document.getElementById('editCoinPrice').value = coinPrice;
    document.getElementById('editDiamondValue').value = diamondValue;
    document.getElementById('editCategory').value = category;
    document.getElementById('editRarity').value = rarity;
    document.getElementById('editSortOrder').value = sortOrder;
    document.getElementById('editIsActive').value = isActive;
    document.getElementById('editGiftModal').classList.remove('hidden');
}
</script>
@endpush
