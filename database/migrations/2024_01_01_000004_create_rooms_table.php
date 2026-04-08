<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('host_user_id')->constrained('users');
            $table->string('title', 200);
            $table->string('thumbnail_url')->nullable();
            $table->enum('type', ['video', 'audio', 'audio_board', 'pk']);
            $table->enum('status', ['waiting', 'live', 'ended'])->default('waiting');
            $table->unsignedInteger('viewer_count')->default(0);
            $table->unsignedInteger('max_viewers')->default(10000);
            $table->unsignedTinyInteger('seat_count')->default(8);
            $table->boolean('is_password_protected')->default(false);
            $table->string('password')->nullable();
            $table->string('category', 50)->nullable();
            $table->string('agora_channel_id', 100)->nullable();
            $table->text('agora_token')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('total_gifts_received')->default(0);
            $table->unsignedInteger('peak_viewer_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('host_user_id');
            $table->index(['status', 'viewer_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
