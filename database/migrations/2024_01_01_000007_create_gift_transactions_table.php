<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users');
            $table->foreignId('receiver_id')->constrained('users');
            $table->foreignId('gift_id')->constrained('gifts');
            $table->uuid('room_id');
            $table->foreign('room_id')->references('id')->on('rooms');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('coin_total');
            $table->unsignedInteger('diamond_total');
            $table->timestamps();

            $table->index(['room_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_transactions');
    }
};
