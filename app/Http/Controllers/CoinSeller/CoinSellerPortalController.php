<?php

namespace App\Http\Controllers\CoinSeller;

use App\Http\Controllers\Controller;
use App\Models\CoinSeller;
use App\Models\CoinSellerTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CoinSellerPortalController extends Controller
{
    private function seller(): CoinSeller
    {
        return CoinSeller::findOrFail(session('coin_seller_id'));
    }

    public function loginForm()  { return view('coin_seller.auth.login'); }

    public function login(Request $request)
    {
        $data   = $request->validate(['email' => ['required','email'], 'password' => ['required']]);
        $seller = CoinSeller::where('email', $data['email'])->first();

        if (! $seller)             return back()->withErrors(['email' => 'No account found.']);
        if (! $seller->is_active)  return back()->withErrors(['email' => 'Account inactive.']);
        if (! Hash::check($data['password'], $seller->password))
                                   return back()->withErrors(['email' => 'Incorrect password.']);

        $token = bin2hex(random_bytes(32));
        $seller->update(['session_token' => $token]);
        session()->invalidate();
        session()->regenerate();
        session(['coin_seller_id' => $seller->id, 'coin_seller_name' => $seller->name, 'coin_seller_token' => $token]);

        return redirect()->route('coin_seller.dashboard');
    }

    public function logout(Request $request)
    {
        if ($id = session('coin_seller_id'))
            CoinSeller::where('id', $id)->update(['session_token' => null]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('coin_seller.login');
    }

    public function dashboard()
    {
        if (! session('coin_seller_id') || ! session('coin_seller_token'))
            return redirect()->route('coin_seller.login');

        $seller        = $this->seller();
        $soldToday     = $seller->soldToday();
        $soldThisMonth = $seller->soldThisMonth();
        $recentTx      = $seller->transactions()
            ->with('user:id,username,avatar_url')
            ->latest()->limit(10)->get();

        return view('coin_seller.dashboard', compact('seller', 'soldToday', 'soldThisMonth', 'recentTx'));
    }

    // ── Update price & phone ──────────────────────────────────────────────────

    public function updateProfile(Request $request)
    {
        if (! session('coin_seller_id')) return redirect()->route('coin_seller.login');

        $data = $request->validate([
            'price_per_100k'  => ['required', 'numeric', 'min:1'],
            'whatsapp_number' => ['required', 'string', 'max:20'],
        ]);

        $this->seller()->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function users(Request $request)
    {
        if (! session('coin_seller_id')) return redirect()->route('coin_seller.login');
        $seller = $this->seller();
        $search = $request->get('search');
        $realId = null;
        if ($search && is_numeric($search))
            $realId = (int)$search > 100000 ? (int)$search - 100000 : (int)$search;

        $users = User::whereNotIn('role', ['admin', 'super_admin', 'moderator'])
            ->when($search, fn($q) => $realId
                ? $q->where('id', $realId)
                : $q->where('username', 'like', "%$search%"))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('coin_seller.users', compact('seller', 'users', 'search'));
    }

    public function addCoins(Request $request, int $userId)
    {
        $seller = $this->seller();
        $data   = $request->validate(['coins' => ['required','integer','min:1'], 'note' => ['nullable','string','max:255']]);

        if ($seller->coin_balance < $data['coins'])
            return back()->withErrors(['coins' => 'Insufficient coin balance.']);

        $user = User::findOrFail($userId);
        $seller->decrement('coin_balance', $data['coins']);
        $seller->increment('total_sold',   $data['coins']);
        $user->increment('coin_balance',   $data['coins']);

        CoinSellerTransaction::create([
            'coin_seller_id' => $seller->id,
            'user_id'        => $userId,
            'coins'          => $data['coins'],
            'type'           => 'sale',
            'note'           => $data['note'] ?? null,
        ]);

        return back()->with('success', "✓ Added {$data['coins']} coins to {$user->username}.");
    }

    public function transactions(Request $request)
    {
        if (! session('coin_seller_id')) return redirect()->route('coin_seller.login');
        $seller       = $this->seller();
        $transactions = CoinSellerTransaction::where('coin_seller_id', $seller->id)
            ->with('user:id,username,avatar_url')
            ->latest()->paginate(20);
        return view('coin_seller.transactions', compact('seller', 'transactions'));
    }
}
