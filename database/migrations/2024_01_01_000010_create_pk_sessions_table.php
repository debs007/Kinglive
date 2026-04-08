<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pk_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('room_a_id');
            $table->uuid('room_b_id');
            $table->foreign('room_a_id')->references('id')->on('rooms');
            $table->foreign('room_b_id')->references('id')->on('rooms');
            $table->unsignedBigInteger('score_a')->default(0);
            $table->unsignedBigInteger('score_b')->default(0);
            $table->uuid('winner_room_id')->nullable();
            $table->enum('status', ['pending', 'active', 'ended'])->default('pending');
            $table->unsignedSmallInteger('duration_seconds')->default(300);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pk_sessions');
    }
};
