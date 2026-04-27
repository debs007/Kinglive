<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            if (! Schema::hasColumn('agencies', 'session_token')) {
                $table->string('session_token')->nullable()->after('is_active');
            }
        });

        Schema::table('coin_sellers', function (Blueprint $table) {
            if (! Schema::hasColumn('coin_sellers', 'session_token')) {
                $table->string('session_token')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('session_token');
        });
        Schema::table('coin_sellers', function (Blueprint $table) {
            $table->dropColumn('session_token');
        });
    }
};
