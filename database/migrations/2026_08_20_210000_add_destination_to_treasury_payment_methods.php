<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_payment_methods', function (Blueprint $table) {
            $table->foreignId('default_account_id')->nullable()->after('requires_account')
                ->constrained('treasury_accounts')->nullOnDelete();
            $table->foreignId('default_cash_register_id')->nullable()->after('default_account_id')
                ->constrained('treasury_cash_registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treasury_payment_methods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_cash_register_id');
            $table->dropConstrainedForeignId('default_account_id');
        });
    }
};
