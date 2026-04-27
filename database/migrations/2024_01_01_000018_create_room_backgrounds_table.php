<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_backgrounds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image_url');   // CDN URL
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Add current_bg_url to rooms table
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('current_bg_url')->nullable()->after('thumbnail_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_backgrounds');
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('current_bg_url');
        });
    }
};
