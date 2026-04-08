<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('svga_url');
            $table->string('thumbnail_url');
            $table->unsignedInteger('coin_price');
            $table->unsignedInteger('diamond_value');
            $table->string('category', 50)->default('normal');
            $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gifts');
    }
};
