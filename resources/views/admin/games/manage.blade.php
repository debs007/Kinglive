@extends('admin.layouts.app')
@section('title', 'Game Catalog')
@section('breadcrumb', 'Games › Catalog')

@section('content')
<div class="page-header">
    <h2>Game Catalog</h2>
    <button class="btn-primary" onclick="document.getElementById('addGameModal').classList.remove('hidden')">+ Add Game</button>
</div>

<div class="card">
    <table class="admin-table">
        <thead>
            <tr><th>Game</th><th>Game ID</th><th>URL</th><th>Min Bet</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse($games as $game)
            <tr>
                <td>
                    <div class="user-cell">
                        @if($game->thumbnail_url)
                        <img src="{{ $game->thumbnail_url }}" style="width:36px;height:36px;border-radius:8px;object-fit:cover" alt="">
                        @endif
                        <div>
                            <div style="font-weight:600">{{ $game->name }}</div>
                            <div style="font-size:11px;color:var(--text3)">{{ Str::limit($game->description, 40) }}</div>
                        </div>
                    </div>
                </td>
                <td><code style="background:var(--surface2);padding:2px 6px;border-radius:4px;font-size:12px">{{ $game->game_id }}</code></td>
                <td><a href="{{ $game->url }}" target="_blank" style="font-size:12px">{{ Str::limit($game->url, 40) }}</a></td>
                <td>🪙 {{ number_format($game->min_bet) }}</td>
                <td>
                    <span class="badge {{ $game->is_active ? 'badge-active' : 'badge-expired' }}">
                        {{ $game->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td>
                    <div class="action-btns">
                        <button class="btn-sm btn-info" onclick="editGame({{ $game->id }}, '{{ addslashes($game->name) }}', '{{ $game->url }}', '{{ $game->thumbnail_url }}', {{ $game->min_bet }}, {{ $game->is_active ? 1 : 0 }}, {{ $game->sort_order }})">Edit</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text3)">No games yet. Add one!</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px">{{ $games->links() }}</div>
</div>

{{-- Add Game Modal --}}
<div id="addGameModal" class="modal hidden">
    <div class="modal-content" style="width:500px">
        <h3>🎮 Add New Game</h3>
        <form action="{{ route('admin.games.store') }}" method="POST">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required placeholder="Lucky Spin">
                </div>
                <div class="form-group">
                    <label>Game ID * <small style="color:var(--text3)">(unique slug)</small></label>
                    <input type="text" name="game_id" required placeholder="lucky_spin">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Game URL *</label>
                    <input type="url" name="url" required placeholder="https://games.kinglive.app/lucky-spin">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Thumbnail URL</label>
                    <input type="url" name="thumbnail_url" placeholder="https://cdn.kinglive.app/games/lucky_spin.png">
                </div>
                <div class="form-group">
                    <label>Min Bet (coins)</label>
                    <input type="number" name="min_bet" value="0" min="0">
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="0">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="Short description of the game">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('addGameModal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-primary">Add Game</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Game Modal --}}
<div id="editGameModal" class="modal hidden">
    <div class="modal-content" style="width:500px">
        <h3>✏️ Edit Game</h3>
        <form id="editGameForm" method="POST">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="editGameName" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="is_active" id="editGameActive">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>URL</label>
                    <input type="url" name="url" id="editGameUrl" required>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Thumbnail URL</label>
                    <input type="url" name="thumbnail_url" id="editGameThumb">
                </div>
                <div class="form-group">
                    <label>Min Bet</label>
                    <input type="number" name="min_bet" id="editGameMinBet" min="0">
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="editGameOrder">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('editGameModal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function editGame(id, name, url, thumb, minBet, isActive, sortOrder) {
    document.getElementById('editGameForm').action = `/admin/games/${id}`;
    document.getElementById('editGameName').value = name;
    document.getElementById('editGameUrl').value = url;
    document.getElementById('editGameThumb').value = thumb;
    document.getElementById('editGameMinBet').value = minBet;
    document.getElementById('editGameActive').value = isActive;
    document.getElementById('editGameOrder').value = sortOrder;
    document.getElementById('editGameModal').classList.remove('hidden');
}
</script>
@endpush