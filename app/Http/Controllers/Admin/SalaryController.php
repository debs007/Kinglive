<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyHostSnapshot;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    // ── Salary sheet index page ────────────────────────────────────────────────

    public function index(Request $request)
    {
        // Available months (from snapshots)
        $months = MonthlyHostSnapshot::selectRaw('year, month')
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn ($r) => [
                'label' => \Carbon\Carbon::createFromDate($r->year, $r->month, 1)
                    ->format('F Y'),
                'value' => "{$r->year}-{$r->month}",
            ]);

        // Selected period
        $selected = $request->input('period');
        $snapshots = collect();
        $year = null;
        $month = null;

        if ($selected) {
            [$year, $month] = explode('-', $selected);
            $snapshots = MonthlyHostSnapshot::where('year', $year)
                ->where('month', $month)
                ->orderBy('net_usd', 'desc')
                ->get();
        } elseif ($months->isNotEmpty()) {
            // Default to latest available month
            [$year, $month] = explode('-', $months->first()['value']);
            $snapshots = MonthlyHostSnapshot::where('year', $year)
                ->where('month', $month)
                ->orderBy('net_usd', 'desc')
                ->get();
            $selected = $months->first()['value'];
        }

        $totals = [
            'diamonds'   => $snapshots->sum('diamonds_earned'),
            'usd'        => $snapshots->sum('usd_amount'),
            'commission' => $snapshots->sum('commission_usd'),
            'net'        => $snapshots->sum('net_usd'),
            'minutes'    => $snapshots->sum('total_live_minutes'),
        ];

        return view('admin.salary.index', compact(
            'months', 'snapshots', 'selected', 'totals'
        ));
    }

    // ── Download CSV ───────────────────────────────────────────────────────────

    public function download(Request $request)
    {
        $request->validate(['period' => ['required', 'string']]);
        [$year, $month] = explode('-', $request->period);

        $snapshots = MonthlyHostSnapshot::where('year', $year)
            ->where('month', $month)
            ->orderBy('net_usd', 'desc')
            ->get();

        if ($snapshots->isEmpty()) {
            return back()->with('error', 'No data for this period.');
        }

        $label    = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F_Y');
        $filename = "salary_sheet_{$label}.csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($snapshots) {
            $handle = fopen('php://output', 'w');

            // CSV header row
            fputcsv($handle, [
                'No.',
                'Username',
                'Display Name',
                'Email',
                'Phone',
                'Agency',
                'Commission %',
                'Total Streams',
                'Live Minutes',
                'Live Hours',
                'Video Live Days',
                'Audio Live Days',
                'Diamonds Earned',
                'USD Amount',
                'Commission USD',
                'Net Payable USD',
                'Period',
            ]);

            // Data rows
            foreach ($snapshots as $i => $s) {
                fputcsv($handle, [
                    $i + 1,
                    $s->username,
                    $s->display_name ?? '',
                    $s->email ?? '',
                    $s->phone ?? '',
                    $s->agency_name ?? 'No Agency',
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

            // Totals row
            fputcsv($handle, [
                '', 'TOTAL', '', '', '', '', '',
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
        };

        return response()->stream($callback, 200, $headers);
    }
}
