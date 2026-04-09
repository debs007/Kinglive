<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('password');
            $table->string('avatar_url')->nullable();
            $table->string('frame_url')->nullable();
            $table->string('display_name', 100)->nullable();
            $table->text('bio')->nullable();
            $table->string('country_code', 5)->nullable();
            $table->enum('role', ['user', 'host', 'moderator', 'admin', 'super_admin'])->default('user');
            $table->unsignedBigInteger('coin_balance')->default(0);
            $table->unsignedBigInteger('diamond_balance')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('xp')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('device_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'role']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
