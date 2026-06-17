<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popup_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('image_url');
            $table->string('action_url')->nullable();   // optional deep link or web URL
            $table->string('action_label')->nullable(); // button label e.g. "Learn More"
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable(); // schedule start
            $table->timestamp('ends_at')->nullable();   // schedule end
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_banners');
    }
};
