<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyHostSnapshot;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    // ── Salary sheet index page ────────────────────────────────────────────────

    public function index(Request $request)
    {
        $months = MonthlyHostSnapshot::selectRaw('year, month')
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn ($r) => [
                'label' => Carbon::createFromDate($r->year, $r->month, 1)->format('F Y'),
                'value' => "{$r->year}-{$r->month}",
            ]);

        $selected  = $request->input('period');
        $snapshots = collect();

        if ($selected) {
            [$year, $month] = explode('-', $selected);
        } elseif ($months->isNotEmpty()) {
            [$year, $month] = explode('-', $months->first()['value']);
            $selected = $months->first()['value'];
        } else {
            $year = $month = null;
        }

        if ($year && $month) {
            $snapshots = MonthlyHostSnapshot::where('year', $year)
                ->where('month', $month)
                ->orderBy('agency_name')
                ->orderByDesc('net_usd')
                ->get();
        }

        // Group by agency for display
        $byAgency = $snapshots->groupBy(fn($s) => $s->agency_name ?? 'No Agency');

        $totals = [
            'diamonds'   => $snapshots->sum('diamonds_earned'),
            'usd'        => $snapshots->sum('usd_amount'),
            'commission' => $snapshots->sum('commission_usd'),
            'net'        => $snapshots->sum('net_usd'),
            'minutes'    => $snapshots->sum('total_live_minutes'),
            'hosts'      => $snapshots->count(),
        ];

        return view('admin.salary.index', compact(
            'months', 'snapshots', 'byAgency', 'selected', 'totals'
        ));
    }

    // ── Download full CSV (all agencies) ──────────────────────────────────────

    public function download(Request $request)
    {
        $request->validate(['period' => ['required', 'string']]);
        [$year, $month] = explode('-', $request->period);

        $snapshots = MonthlyHostSnapshot::where('year', $year)
            ->where('month', $month)
            ->orderBy('agency_name')
            ->orderByDesc('net_usd')
            ->get();

        if ($snapshots->isEmpty()) {
            return back()->with('error', 'No data for this period.');
        }

        $label    = Carbon::createFromDate($year, $month, 1)->format('F_Y');
        $filename = "salary_sheet_{$label}.csv";

        return response()->stream(
            fn() => $this->writeCsv($snapshots, $label),
            200,
            [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }

    // ── Download per-agency CSV ────────────────────────────────────────────────

    public function downloadAgency(Request $request)
    {
        $request->validate([
            'period'      => ['required', 'string'],
            'agency_name' => ['required', 'string'],
        ]);

        [$year, $month] = explode('-', $request->period);
        $agencyName     = $request->agency_name;

        $snapshots = MonthlyHostSnapshot::where('year', $year)
            ->where('month', $month)
            ->where(function ($q) use ($agencyName) {
                if ($agencyName === 'No Agency') {
                    $q->whereNull('agency_name');
                } else {
                    $q->where('agency_name', $agencyName);
                }
            })
            ->orderByDesc('net_usd')
            ->get();

        if ($snapshots->isEmpty()) {
            return back()->with('error', 'No data for this agency/period.');
        }

        $label    = Carbon::createFromDate($year, $month, 1)->format('F_Y');
        $safeAgency = preg_replace('/[^a-zA-Z0-9_]/', '_', $agencyName);
        $filename = "salary_{$safeAgency}_{$label}.csv";

        return response()->stream(
            fn() => $this->writeCsv($snapshots, $label, $agencyName),
            200,
            [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }

    // ── CSV writer (shared) ────────────────────────────────────────────────────

    private function writeCsv($snapshots, string $label, ?string $agencyFilter = null): void
    {
        $handle = fopen('php://output', 'w');

        // Title row
        fputcsv($handle, [
            $agencyFilter
                ? "Salary Sheet — {$agencyFilter} — {$label}"
                : "Salary Sheet — All Agencies — {$label}"
        ]);
        fputcsv($handle, []); // blank line

        $currentAgency = null;
        $agencyIndex   = 0;
        $rowNum        = 0;

        foreach ($snapshots as $s) {
            $agency = $s->agency_name ?? 'No Agency';

            // Agency header row
            if ($agency !== $currentAgency) {
                if ($currentAgency !== null) {
                    // Agency subtotal
                    $agencySnaps = $snapshots->filter(
                        fn($x) => ($x->agency_name ?? 'No Agency') === $currentAgency
                    );
                    fputcsv($handle, [
                        '', "Subtotal — {$currentAgency}", '', '', '', '', '',
                        $agencySnaps->sum('total_streams'),
                        $agencySnaps->sum('total_live_minutes'),
                        number_format($agencySnaps->sum('total_live_minutes') / 60, 1),
                        $agencySnaps->sum('video_live_days'),
                        $agencySnaps->sum('audio_live_days'),
                        $agencySnaps->sum('diamonds_earned'),
                        '$' . number_format($agencySnaps->sum('usd_amount'), 2),
                        '$' . number_format($agencySnaps->sum('commission_usd'), 2),
                        '$' . number_format($agencySnaps->sum('net_usd'), 2),
                        '',
                    ]);
                    fputcsv($handle, []); // blank line between agencies
                }

                $currentAgency = $agency;
                $agencyIndex++;
                $rowNum = 0;

                // Agency name header
                fputcsv($handle, ["=== {$agency} ==="]);
                fputcsv($handle, [
                    'No.', 'Username', 'Display Name', 'Email', 'Phone',
                    'Agency', 'Commission %',
                    'Streams', 'Live Mins', 'Live Hours',
                    'V.Days', 'A.Days',
                    'Diamonds', 'Gross USD', 'Commission', 'Net Payable', 'Period',
                ]);
            }

            $rowNum++;
            fputcsv($handle, [
                $rowNum,
                $s->username,
                $s->display_name ?? '',
                $s->email ?? '',
                $s->phone ?? '',
                $agency,
                $s->agency_commission_pct . '%',
                $s->total_streams,
                $s->total_live_minutes,
                number_format($s->total_live_minutes / 60, 1),
                $s->video_live_days,
                $s->audio_live_days,
                $s->diamonds_earned,
                '$' . number_format($s->usd_amount, 2),
                '$' . number_format($s->commission_usd, 2),
                '$' . number_format($s->net_usd, 2),
                $s->period_start . ' to ' . $s->period_end,
            ]);
        }

        // Last agency subtotal
        if ($currentAgency !== null) {
            $agencySnaps = $snapshots->filter(
                fn($x) => ($x->agency_name ?? 'No Agency') === $currentAgency
            );
            fputcsv($handle, [
                '', "Subtotal — {$currentAgency}", '', '', '', '', '',
                $agencySnaps->sum('total_streams'),
                $agencySnaps->sum('total_live_minutes'),
                number_format($agencySnaps->sum('total_live_minutes') / 60, 1),
                $agencySnaps->sum('video_live_days'),
                $agencySnaps->sum('audio_live_days'),
                $agencySnaps->sum('diamonds_earned'),
                '$' . number_format($agencySnaps->sum('usd_amount'), 2),
                '$' . number_format($agencySnaps->sum('commission_usd'), 2),
                '$' . number_format($agencySnaps->sum('net_usd'), 2),
                '',
            ]);
        }

        // Grand total
        fputcsv($handle, []);
        fputcsv($handle, [
            '', 'GRAND TOTAL', '', '', '', '', '',
            $snapshots->sum('total_streams'),
            $snapshots->sum('total_live_minutes'),
            number_format($snapshots->sum('total_live_minutes') / 60, 1),
            $snapshots->sum('video_live_days'),
            $snapshots->sum('audio_live_days'),
            $snapshots->sum('diamonds_earned'),
            '$' . number_format($snapshots->sum('usd_amount'), 2),
            '$' . number_format($snapshots->sum('commission_usd'), 2),
            '$' . number_format($snapshots->sum('net_usd'), 2),
            '',
        ]);

        fclose($handle);
    }
}