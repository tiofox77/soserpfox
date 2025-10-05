<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL não permite alterar ENUM diretamente, precisamos recriar a coluna
        DB::statement("ALTER TABLE invoicing_series MODIFY COLUMN document_type ENUM('invoice', 'proforma', 'receipt', 'credit_note', 'debit_note', 'pos', 'purchase', 'advance') COMMENT 'Tipo de documento'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE invoicing_series MODIFY COLUMN document_type ENUM('invoice', 'proforma', 'receipt', 'credit_note', 'debit_note') COMMENT 'Tipo de documento'");
    }
};
