<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\NotificationService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WalletService       $walletService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(Request $request)
    {
        $withdrawals = WithdrawalRequest::with(['user:id,username,avatar_url,diamond_balance', 'reviewedBy:id,username'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'pending_count' => WithdrawalRequest::where('status', 'pending')->count(),
            'pending_usd'   => WithdrawalRequest::where('status', 'pending')->sum('usd_amount'),
            'paid_today'    => WithdrawalRequest::where('status', 'paid')->whereDate('processed_at', today())->sum('usd_amount'),
        ];

        return view('admin.withdrawals.index', compact('withdrawals', 'stats'));
    }

    public function approve(Request $request, int $id)
    {
        $withdrawal = WithdrawalRequest::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'This request has already been processed.');
        }

        $withdrawal->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'admin_note'  => $request->input('note'),
        ]);

        $this->notificationService->notifyWithdrawalUpdate($withdrawal->user_id, 'approved', (float) $withdrawal->usd_amount);

        return back()->with('success', 'Withdrawal approved.');
    }

    public function markPaid(int $id)
    {
        $withdrawal = WithdrawalRequest::findOrFail($id);
        $withdrawal->update(['status' => 'paid', 'processed_at' => now()]);

        $this->notificationService->notifyWithdrawalUpdate($withdrawal->user_id, 'paid', (float) $withdrawal->usd_amount);

        return back()->with('success', 'Marked as paid.');
    }

    public function reject(Request $request, int $id)
    {
        $data       = $request->validate(['note' => ['required', 'string']]);
        $withdrawal = WithdrawalRequest::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'This request has already been processed.');
        }

        $this->walletService->refundWithdrawal($withdrawal, $data['note']);
        $withdrawal->update(['reviewed_by' => auth()->id()]);

        $this->notificationService->notifyWithdrawalUpdate($withdrawal->user_id, 'rejected', (float) $withdrawal->usd_amount);

        return back()->with('success', 'Withdrawal rejected and diamonds refunded.');
    }
}
