<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create dm_messages FIRST (no foreign key to dm_conversations yet)
        Schema::create('dm_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id'); // no FK yet
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['text', 'image', 'gift'])->default('text');
            $table->text('body')->nullable();
            $table->foreignId('gift_id')->nullable()->constrained('gifts')->nullOnDelete();
            $table->unsignedInteger('gift_quantity')->default(1);
            $table->unsignedInteger('diamond_value')->default(0);
            $table->boolean('is_read')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        // Create dm_conversations SECOND (can now reference dm_messages)
        Schema::create('dm_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_message_id')->nullable()->constrained('dm_messages')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_one')->default(0);
            $table->unsignedInteger('unread_two')->default(0);
            $table->timestamps();

            $table->unique(['user_one_id', 'user_two_id']);
            $table->index('last_message_at');
        });

        // NOW add the FK from dm_messages back to dm_conversations
        Schema::table('dm_messages', function (Blueprint $table) {
            $table->foreign('conversation_id')
                  ->references('id')
                  ->on('dm_conversations')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dm_messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });
        Schema::dropIfExists('dm_conversations');
        Schema::dropIfExists('dm_messages');
    }
};