<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyJoinRequest;
use App\Models\GiftTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AgencyPortalController extends Controller
{
    private function agency(): Agency
    {
        return Agency::findOrFail(session('agency_id'));
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function loginForm()
    {
        return view('agency.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $agency = Agency::where('email', $data['email'])->first();

        if (! $agency) {
            return back()->withErrors(['email' => 'No agency found with that email.']);
        }

        if (! $agency->is_active) {
            return back()->withErrors(['email' => 'This agency is inactive.']);
        }

        if (! Hash::check($data['password'], $agency->password)) {
            return back()->withErrors(['email' => 'Incorrect password.']);
        }

        $token = bin2hex(random_bytes(32));
        $agency->update(['session_token' => $token]);

        session()->invalidate();
        session()->regenerate();
        session([
            'agency_id'    => $agency->id,
            'agency_name'  => $agency->name,
            'agency_token' => $token,
        ]);

        return redirect()->route('agency.dashboard');
    }

    public function logout(Request $request)
    {
        $agencyId = session('agency_id');
        if ($agencyId) {
            \App\Models\Agency::where('id', $agencyId)->update(['session_token' => null]);
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('agency.login');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $agency      = $this->agency();
        $memberCount = $agency->members()->count();

        $totalDiamonds = GiftTransaction::whereIn(
            'receiver_id',
            $agency->members()->pluck('id')
        )->sum('diamond_total');

        $totalCoins = GiftTransaction::whereIn(
            'sender_id',
            $agency->members()->pluck('id')
        )->sum('coin_total');

        $pendingCount = AgencyJoinRequest::where('agency_id', $agency->id)
            ->where('status', 'pending')
            ->count();

        $topEarners = User::where('agency_id', $agency->id)
            ->orderByDesc('diamond_balance')
            ->take(5)
            ->get(['id', 'username', 'avatar_url', 'diamond_balance', 'level']);

        return view('agency.dashboard', compact(
            'agency', 'memberCount', 'totalDiamonds',
            'totalCoins', 'pendingCount', 'topEarners'
        ));
    }

    // ── Members ───────────────────────────────────────────────────────────────

    public function members()
    {
        $agency  = $this->agency();
        $members = User::where('agency_id', $agency->id)
            ->orderByDesc('diamond_balance')
            ->paginate(20);

        return view('agency.members', compact('agency', 'members'));
    }

    public function removeMember(int $id)
    {
        $agency = $this->agency();
        User::where('id', $id)
            ->where('agency_id', $agency->id)
            ->update(['agency_id' => null]);

        return back()->with('success', 'Member removed.');
    }

    // ── Join Requests ─────────────────────────────────────────────────────────

    public function requests()
    {
        $agency   = $this->agency();
        $requests = AgencyJoinRequest::where('agency_id', $agency->id)
            ->with('user:id,username,avatar_url,level,diamond_balance')
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('agency.requests', compact('agency', 'requests'));
    }

    public function approve(int $id)
    {
        $agency  = $this->agency();
        $req     = AgencyJoinRequest::where('id', $id)
            ->where('agency_id', $agency->id)
            ->firstOrFail();

        $req->update(['status' => 'approved', 'responded_at' => now()]);

        // Add user to agency
        User::where('id', $req->user_id)->update(['agency_id' => $agency->id]);

        return back()->with('success', 'Request approved. User added to agency.');
    }

    public function reject(int $id)
    {
        $agency = $this->agency();
        AgencyJoinRequest::where('id', $id)
            ->where('agency_id', $agency->id)
            ->update(['status' => 'rejected', 'responded_at' => now()]);

        return back()->with('success', 'Request rejected.');
    }
}
