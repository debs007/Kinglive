<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // frames — master frame catalogue (admin manages this)
        Schema::create('frames', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('svga_url');           // SVGA file in S3
            $table->string('thumbnail_url')->nullable(); // Preview image
            $table->integer('price')->default(0); // 0 = free/gift only
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // user_frames — user's frame inventory
        Schema::create('user_frames', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('frame_id');
            $table->string('source')->default('admin'); // admin | shop
            $table->timestamps();

            $table->unique(['user_id', 'frame_id']); // one entry per frame per user
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_frames');
        Schema::dropIfExists('frames');
    }
};
