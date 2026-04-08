<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('room_id');
            $table->foreign('room_id')->references('id')->on('rooms');
            $table->string('game_url');
            $table->string('game_id', 100);
            $table->json('game_data')->nullable();
            $table->unsignedBigInteger('coins_spent')->default(0);
            $table->unsignedBigInteger('coins_won')->default(0);
            $table->enum('status', ['started', 'completed', 'abandoned'])->default('started');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'created_at']);
            $table->index(['user_id', 'game_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
