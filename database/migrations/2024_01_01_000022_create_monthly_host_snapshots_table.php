<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_host_snapshots', function (Blueprint $table) {
            $table->id();

            // Period
            $table->integer('year');
            $table->integer('month');  // 1-12
            $table->date('period_start'); // e.g. 2026-05-01
            $table->date('period_end');   // e.g. 2026-05-31

            // User info (snapshot at time of reset)
            $table->unsignedBigInteger('user_id');
            $table->string('username');
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Agency info
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->string('agency_name')->nullable();
            $table->decimal('agency_commission_pct', 5, 2)->default(0);

            // Monthly stats (captured before reset)
            $table->integer('diamonds_earned')->default(0);  // diamonds earned this month
            $table->integer('diamond_balance')->default(0);  // wallet balance at reset time
            $table->integer('total_live_minutes')->default(0);
            $table->integer('total_live_hours')->default(0);
            $table->integer('video_live_days')->default(0);
            $table->integer('audio_live_days')->default(0);
            $table->integer('total_streams')->default(0);

            // Salary calculation
            $table->decimal('usd_amount', 10, 2)->default(0);       // diamonds × rate
            $table->decimal('commission_usd', 10, 2)->default(0);   // agency commission
            $table->decimal('net_usd', 10, 2)->default(0);          // after commission

            $table->timestamp('created_at')->useCurrent();

            $table->index(['year', 'month']);
            $table->index('user_id');
            $table->index('agency_id');
            $table->unique(['user_id', 'year', 'month']); // one snapshot per user per month
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_host_snapshots');
    }
};
