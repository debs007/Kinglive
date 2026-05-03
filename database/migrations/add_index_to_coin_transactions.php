<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coin_transactions', function (Blueprint $table) {
            // Only add if index doesn't already exist
            $indexes = collect(\DB::select("SHOW INDEX FROM coin_transactions"))
                ->pluck('Key_name')->toArray();

            if (!in_array('idx_game_type_date', $indexes)) {
                $table->index(['type', 'created_at'], 'idx_game_type_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_game_type_date');
        });
    }
};
