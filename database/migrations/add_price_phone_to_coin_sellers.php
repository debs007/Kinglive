<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coin_sellers', function (Blueprint $table) {
            if (! Schema::hasColumn('coin_sellers', 'price_per_100k')) {
                // Price in BDT per 100,000 coins
                $table->decimal('price_per_100k', 10, 2)->nullable()->after('total_sold');
            }
            if (! Schema::hasColumn('coin_sellers', 'whatsapp_number')) {
                // WhatsApp phone number e.g. +8801XXXXXXXXX
                $table->string('whatsapp_number', 20)->nullable()->after('price_per_100k');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coin_sellers', function (Blueprint $table) {
            $table->dropColumn(['price_per_100k', 'whatsapp_number']);
        });
    }
};
