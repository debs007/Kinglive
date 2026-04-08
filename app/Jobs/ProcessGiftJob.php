<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\GiftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessGiftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly int    $senderId,
        public readonly int    $receiverId,
        public readonly int    $giftId,
        public readonly string $roomId,
        public readonly int    $quantity,
    ) {
        $this->onQueue('gifts');
    }

    public function handle(GiftService $giftService): void
    {
        $sender = User::findOrFail($this->senderId);

        $giftService->sendGift(
            sender:       $sender,
            giftId:       $this->giftId,
            roomId:       $this->roomId,
            targetUserId: $this->receiverId,
            quantity:     $this->quantity,
        );
    }
}
