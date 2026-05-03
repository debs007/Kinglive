<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rooms', 'current_bg_url')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->string('current_bg_url', 500)->nullable()->after('ended_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rooms', 'current_bg_url')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('current_bg_url');
            });
        }
    }
};
