<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert type column from ENUM to VARCHAR(50).
     * This avoids ENUM truncation errors when new types are added.
     * All existing values are preserved, new types can be added freely.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE coin_transactions
            MODIFY COLUMN `type` VARCHAR(50) NOT NULL
        ");
    }

    public function down(): void
    {
        // Revert to original ENUM — only safe if no new types exist in data
        DB::statement("
            ALTER TABLE coin_transactions
            MODIFY COLUMN `type` ENUM(
                'purchase',
                'gift_sent',
                'gift_received',
                'withdrawal',
                'admin_credit',
                'refund',
                'welcome_bonus',
                'live_reward',
                'frame_purchase',
                'daily_reward'
            ) NOT NULL
        ");
    }
};
