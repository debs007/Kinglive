<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banned_by')->constrained('users');
            $table->string('reason');
            $table->enum('type', ['global', 'room', 'chat', 'live']);
            $table->string('room_id', 36)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'type', 'is_active']);
            $table->index(['user_id', 'room_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bans');
    }
};
