<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('total_coins_sent')->default(0)->after('coin_balance');
            //$table->unsignedTinyInteger('level')->default(1)->after('total_coins_sent');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_coins_sent', 'level']);
        });
    }
};
