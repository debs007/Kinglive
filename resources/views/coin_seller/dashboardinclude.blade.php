{{-- Add this section to resources/views/coin_seller/dashboard.blade.php --}}
{{-- Place it after the stats cards and before the recent transactions table --}}

<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="display:flex;align-items:center;gap:10px">
        <span style="font-size:20px">⚙️</span>
        <h3 style="margin:0">My Listing Settings</h3>
    </div>
    <div style="padding:20px">
        <p style="color:var(--text3);font-size:13px;margin-bottom:20px">
            Set your price and WhatsApp number to appear in the buyer's coin market list.
        </p>
        <form method="POST" action="{{ route('coin_seller.update_profile') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="form-group">
                    <label>Price per 100,000 Coins (BDT) *</label>
                    <input type="number"
                           name="price_per_100k"
                           value="{{ $seller->price_per_100k ?? '' }}"
                           step="0.01" min="1"
                           placeholder="e.g. 500"
                           required>
                    <small style="color:var(--text3)">How much in Bangladeshi Taka for 100K coins</small>
                </div>
                <div class="form-group">
                    <label>WhatsApp Number *</label>
                    <input type="text"
                           name="whatsapp_number"
                           value="{{ $seller->whatsapp_number ?? '' }}"
                           placeholder="+8801XXXXXXXXX"
                           required>
                    <small style="color:var(--text3)">Include country code e.g. +8801712345678</small>
                </div>
            </div>
            @if($seller->price_per_100k && $seller->whatsapp_number)
            <div style="background:rgba(39,174,96,0.1);border:1px solid rgba(39,174,96,0.3);border-radius:8px;padding:12px 16px;margin-bottom:16px">
                <div style="font-size:13px;color:#27ae60">
                    ✅ You are <strong>listed</strong> in the coin market at
                    <strong>৳{{ number_format($seller->price_per_100k, 2) }}</strong> per 100K coins
                </div>
            </div>
            @else
            <div style="background:rgba(231,76,60,0.1);border:1px solid rgba(231,76,60,0.3);border-radius:8px;padding:12px 16px;margin-bottom:16px">
                <div style="font-size:13px;color:#e74c3c">
                    ⚠️ Fill in both fields to appear in the buyer's coin market list.
                </div>
            </div>
            @endif
            <button type="submit" class="btn-primary">Save Settings</button>
        </form>
    </div>
</div>
