<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('video_url');       // CDN url
            $table->string('thumbnail_url')->nullable();
            $table->string('music_title')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->unsignedBigInteger('diamond_earned')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['view_count', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('reel_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reel_id', 'user_id']); // one view per user per reel
        });

        Schema::create('reel_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reel_id', 'user_id']);
        });

        Schema::create('reel_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['reel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_comments');
        Schema::dropIfExists('reel_likes');
        Schema::dropIfExists('reel_views');
        Schema::dropIfExists('reels');
    }
};
