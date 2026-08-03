<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('invoicing_receipts', 'atcud')) {
            Schema::table('invoicing_receipts', function (Blueprint $table) {
                $table->string('atcud')->nullable()->after('receipt_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoicing_receipts', 'atcud')) {
            Schema::table('invoicing_receipts', function (Blueprint $table) {
                $table->dropColumn('atcud');
            });
        }
    }
};
