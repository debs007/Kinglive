<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Services\GiftService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GiftReportController extends Controller
{
    public function __construct(private readonly GiftService $giftService)
    {
    }

    public function report(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to   = $request->date('to')   ?? now()->endOfDay();

        $summary = [
            'total_transactions' => GiftTransaction::whereBetween('created_at', [$from, $to])->count(),
            'total_coins'        => GiftTransaction::whereBetween('created_at', [$from, $to])->sum('coin_total'),
            'total_diamonds'     => GiftTransaction::whereBetween('created_at', [$from, $to])->sum('diamond_total'),
            'unique_senders'     => GiftTransaction::whereBetween('created_at', [$from, $to])->distinct('sender_id')->count('sender_id'),
        ];

        $topGifts    = $this->giftService->getTopGiftsReport($from, $to);
        $topSenders  = $this->giftService->getTopSenders($from, $to);
        $topReceivers = $this->giftService->getTopReceivers($from, $to);
        $daily       = $this->giftService->getDailySummary($from, $to);

        return view('admin.gifts.report', compact(
            'summary', 'topGifts', 'topSenders', 'topReceivers', 'daily', 'from', 'to'
        ));
    }

    public function manage()
    {
        $gifts = Gift::orderBy('sort_order')->paginate(30);
        return view('admin.gifts.manage', compact('gifts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'svga_url'      => ['required', 'url'],
            'thumbnail_url' => ['required', 'url'],
            'coin_price'    => ['required', 'integer', 'min:1'],
            'diamond_value' => ['required', 'integer', 'min:1'],
            'category'      => ['required', 'string', 'max:50'],
            'rarity'        => ['required', 'in:common,rare,epic,legendary'],
            'sort_order'    => ['nullable', 'integer'],
        ]);

        Gift::create($data);

        return back()->with('success', 'Gift added successfully.');
    }

    public function update(Request $request, int $id)
    {
        $gift = Gift::findOrFail($id);

        $gift->update($request->only([
            'name', 'svga_url', 'thumbnail_url', 'coin_price',
            'diamond_value', 'category', 'rarity', 'is_active', 'sort_order',
        ]));

        return back()->with('success', 'Gift updated successfully.');
    }

    public function destroy(int $id)
    {
        Gift::findOrFail($id)->update(['is_active' => false]);
        return back()->with('success', 'Gift deactivated.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to   = $request->date('to')   ?? now()->endOfDay();

        $filename = "gift_report_{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv";

        return response()->streamDownload(function () use ($from, $to) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Sender', 'Receiver', 'Gift', 'Quantity', 'Coins', 'Diamonds']);

            GiftTransaction::whereBetween('created_at', [$from, $to])
                ->with(['sender:id,username', 'receiver:id,username', 'gift:id,name'])
                ->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->created_at->format('Y-m-d H:i'),
                            $row->sender->username,
                            $row->receiver->username,
                            $row->gift->name,
                            $row->quantity,
                            $row->coin_total,
                            $row->diamond_total,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
