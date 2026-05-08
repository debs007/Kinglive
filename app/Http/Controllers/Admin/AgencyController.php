<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\GiftTransaction;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class AgencyController extends Controller
{
    public function index()
    {
        $agencies = Agency::withCount('members')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.agencies.index', compact('agencies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'description'     => ['nullable', 'string', 'max:500'],
            'logo_url'        => ['nullable', 'url'],
            'portal_email'    => ['nullable', 'email'],
            'portal_password' => ['nullable', 'string', 'min:6'],
            'commission_pct'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        Agency::create([
            'name'           => $request->name,
            'description'    => $request->description,
            'logo_url'       => $request->logo_url,
            'code'           => strtoupper(Str::random(8)),
            'email'          => $request->portal_email ?: null,
            'password'       => $request->portal_password
                                    ? \Illuminate\Support\Facades\Hash::make($request->portal_password)
                                    : null,
            'commission_pct' => $request->commission_pct ?? 20.00,
        ]);

        return back()->with('success', 'Agency created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $agency = Agency::findOrFail($id);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:500'],
            'logo_url'       => ['nullable', 'url'],
            'is_active'      => ['boolean'],
            'commission_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $agency->update($data);

        return back()->with('success', 'Agency updated.');
    }

    public function regenerateCode(int $id)
    {
        $agency = Agency::findOrFail($id);
        $agency->update(['code' => strtoupper(Str::random(8))]);
        return back()->with('success', 'Code regenerated: ' . $agency->code);
    }

    public function destroy(int $id)
    {
        Agency::findOrFail($id)->delete();
        return back()->with('success', 'Agency deleted.');
    }

    // ── Salary Sheet CSV Export ───────────────────────────────────────────────

    public function salarySheet(int $id, Request $request): Response
    {
        $agency = Agency::findOrFail($id);

        // Month/year — default to previous month
        $month     = (int) $request->input('month', now()->subMonth()->month);
        $year      = (int) $request->input('year',  now()->subMonth()->year);
        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodEnd   = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // ── Load salary settings ──────────────────────────────────────────────
        $takaRateHigh    = (float) Setting::get('salary_taka_rate_high',    900);  // BDT per 100K if targets met
        $takaRateLow     = (float) Setting::get('salary_taka_rate_low',     450);  // BDT per 100K if targets not met
        $diamondTarget   = (int)   Setting::get('salary_diamond_target',    300000); // min diamonds for high rate
        $videoDaysTarget = (int)   Setting::get('salary_video_days_target', 18);    // min live days for high rate

        // ── Fetch hosts in this agency ────────────────────────────────────────
        $hosts = User::where('agency_id', $agency->id)
            ->where('role', 'user')
            ->select('id', 'username', 'display_name')
            ->get();

        // ── Monthly diamond earnings from gift transactions ──────────────────
        $monthlyDiamonds = GiftTransaction::whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereIn('receiver_id', $hosts->pluck('id'))
            ->selectRaw('receiver_id, SUM(diamond_total) AS diamonds_earned')
            ->groupBy('receiver_id')
            ->get()
            ->keyBy('receiver_id');

        // Sort hosts by diamonds earned desc
        $hosts = $hosts->sortByDesc(fn ($h) =>
            $monthlyDiamonds->get($h->id)?->diamonds_earned ?? 0
        )->values();

        // ── Pre-fetch monthly live stats from rooms table ────────────────────
        // Rules:
        // - Only 'video' type rooms (exclude audio, audio_board)
        // - A session must be >= 40 minutes to qualify
        // - A calendar day counts as 1 max, even if host went live multiple times
        // - Live hours = sum of all video room durations for the month

        $hostIds = $hosts->pluck('id');

        // Step 1: Get all qualifying video rooms (>= 40 mins) for the period
        $qualifyingRooms = Room::whereBetween('started_at', [$periodStart, $periodEnd])
            ->whereNotNull('ended_at')
            ->where('type', 'video')
            ->whereIn('host_user_id', $hostIds)
            ->whereRaw('TIMESTAMPDIFF(MINUTE, started_at, ended_at) >= 40')
            ->selectRaw('
                host_user_id,
                DATE(started_at)                                                    AS live_date,
                TIMESTAMPDIFF(SECOND, started_at, ended_at)                        AS duration_secs
            ')
            ->get();

        // Step 2: Group by host — count distinct days and sum hours
        $roomStats = collect();
        foreach ($qualifyingRooms->groupBy('host_user_id') as $userId => $sessions) {
            $roomStats[$userId] = (object) [
                'host_user_id' => $userId,
                // Distinct calendar days with at least one qualifying session
                'live_days'    => $sessions->pluck('live_date')->unique()->count(),
                // Total hours across all qualifying sessions
                'live_hours'   => round($sessions->sum('duration_secs') / 3600, 1),
            ];
        }

        // ── Build CSV ─────────────────────────────────────────────────────────
        $output = fopen('php://temp', 'r+');

        // UTF-8 BOM for Excel
        fputs($output, "\xEF\xBB\xBF");

        // Settings info at top
        fputcsv($output, ["Salary Sheet — {$agency->name} ({$agency->code}) — {$monthName}"]);
        fputcsv($output, ["Diamond Target: " . number_format($diamondTarget) . " | Video Days Target: {$videoDaysTarget} days"]);
        fputcsv($output, ["High Rate: BDT {$takaRateHigh} per 100K | Low Rate: BDT {$takaRateLow} per 100K"]);
        fputcsv($output, []);

        // Column headers
        fputcsv($output, [
            'Host ID',
            'Host Name',
            'Agency',
            'Code',
            'Month',
            'Diamond Balance',
            'Live Days',
            'Live Hours',
            'Rate Applied',
            'Amount (BDT)',
            'Commission (' . ($agency->commission_pct ?? 20) . '%)',
            'Notes',
        ]);

        $totalDiamonds   = 0;
        $totalAmount     = 0.0;
        $totalCommission = 0.0;
        $commissionPct   = (float) ($agency->commission_pct ?? 20);

        foreach ($hosts as $host) {
            $stats    = $roomStats->get($host->id) ?? null;
            $liveDays = $stats ? (int) $stats->live_days   : 0;
            $liveHrs  = $stats ? (float) $stats->live_hours : 0;
            $diamonds = (int) ($monthlyDiamonds->get($host->id)?->diamonds_earned ?? 0);

            // Determine rate: high only if BOTH targets met
            $meetsTarget = ($diamonds >= $diamondTarget) && ($liveDays >= $videoDaysTarget);
            $rate        = $meetsTarget ? $takaRateHigh : $takaRateLow;
            $amount      = round(($diamonds / 100000) * $rate, 2);
            $commission  = round($amount * $commissionPct / 100, 2);

            // Notes explaining rate
            $notes = '';
            if (! $meetsTarget) {
                $reasons = [];
                if ($diamonds < $diamondTarget)  $reasons[] = 'Low diamonds';
                if ($liveDays < $videoDaysTarget) $reasons[] = 'Low live days';
                $notes = implode(', ', $reasons);
            }

            $totalDiamonds   += $diamonds;
            $totalAmount     += $amount;
            $totalCommission += $commission;

            fputcsv($output, [
                $host->id + 100000,
                $host->display_name ?? $host->username,
                $agency->name,
                $agency->code,
                $monthName,
                $diamonds,
                $liveDays,
                $liveHrs,
                'BDT ' . $rate . '/100K',
                number_format($amount, 2, '.', ''),
                number_format($commission, 2, '.', ''),
                $notes,
            ]);
        }

        // Totals
        fputcsv($output, []);
        fputcsv($output, [
            '', '', '', '', 'TOTAL',
            number_format($totalDiamonds),
            '', '',
            '',
            number_format($totalAmount, 2, '.', ''),
            number_format($totalCommission, 2, '.', ''),
            '',
        ]);
        fputcsv($output, [
            '', '', '', '',
            'Commission Rate: ' . $commissionPct . '%',
            '', '', '', '', '', '', '',
        ]);

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $filename = "salary_{$agency->code}_{$year}_{$month}.csv";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache',
        ]);
    }
}