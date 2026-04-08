<?php

namespace App\Events;

use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserBannedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int     $userId,
        public readonly string  $type,
        public readonly ?string $roomId,
        public readonly string  $reason,
        public readonly ?Carbon $expiresAt,
    ) {}
}
