<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add missing column from original migration if not exists
            if (!Schema::hasColumn('users', 'total_streams')) {
                $table->unsignedInteger('total_streams')->default(0);
            }
            if (!Schema::hasColumn('users', 'audio_live_days')) {
                $table->unsignedInteger('audio_live_days')->default(0);
            }
            if (!Schema::hasColumn('users', 'video_live_days')) {
                $table->unsignedInteger('video_live_days')->default(0);
            }
            if (!Schema::hasColumn('users', 'total_live_minutes')) {
                $table->unsignedBigInteger('total_live_minutes')->default(0);
            }
            if (!Schema::hasColumn('users', 'total_live_hours')) {
                $table->unsignedBigInteger('total_live_hours')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'audio_live_days', 'video_live_days',
                'total_live_minutes', 'total_live_hours',
            ]);
        });
    }
};
