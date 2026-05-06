<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('agencies', function (Blueprint $table) {
            if (!Schema::hasColumn('agencies', 'commission_pct')) {
                $table->decimal('commission_pct', 5, 2)->default(20.00)->after('is_active');
            }
        });
    }
    public function down(): void {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('commission_pct');
        });
    }
};
