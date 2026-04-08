<?php

namespace App\Jobs;

use App\Services\BanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoExpireBansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BanService $banService): void
    {
        $count = $banService->expireOldBans();
        Log::info("AutoExpireBans: expired {$count} bans.");
    }
}
