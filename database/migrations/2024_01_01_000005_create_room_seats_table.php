<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_seats', function (Blueprint $table) {
            $table->id();
            $table->uuid('room_id');
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('seat_index');
            $table->boolean('is_muted')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['room_id', 'seat_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_seats');
    }
};
