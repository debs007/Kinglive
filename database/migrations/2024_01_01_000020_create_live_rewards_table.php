<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('reward_date');
            $table->string('room_id')->nullable();
            $table->integer('amount')->default(5000);
            $table->timestamp('credited_at')->useCurrent();

            // One reward per user per day — enforced at DB level
            $table->unique(['user_id', 'reward_date']);
            $table->index('user_id');
            $table->index('reward_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_rewards');
    }
};
