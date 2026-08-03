<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoicing_series MODIFY COLUMN document_type ENUM('invoice','proforma','receipt','credit_note','debit_note','pos','purchase','advance','transport') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoicing_series MODIFY COLUMN document_type ENUM('invoice','proforma','receipt','credit_note','debit_note','pos','purchase','advance') NULL DEFAULT NULL");
    }
};
