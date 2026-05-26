<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_frames', function (Blueprint $table) {
            $table->id();
            $table->integer('min_level');   // frame unlocks at this level
            $table->integer('max_level')->nullable(); // null = no upper bound
            $table->string('name');
            $table->string('svga_url');
            $table->string('thumbnail_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('min_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_frames');
    }
};
