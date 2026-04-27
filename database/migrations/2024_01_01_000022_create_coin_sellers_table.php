<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_sellers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('coin_balance')->default(0); // coins available to sell
            $table->unsignedBigInteger('total_sold')->default(0);   // lifetime coins sold
            $table->boolean('is_active')->default(true);
            $table->string('session_token')->nullable(); // active login token
            $table->timestamps();
        });

        Schema::create('coin_seller_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coin_seller_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('coins');          // coins added to user
            $table->string('type')->default('sale');      // sale | admin_grant
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['coin_seller_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_seller_transactions');
        Schema::dropIfExists('coin_sellers');
    }
};
