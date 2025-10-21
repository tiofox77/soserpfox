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
        // Adicionar novos tipos ao ENUM da coluna 'type'
        DB::statement("ALTER TABLE `accounting_journals` MODIFY COLUMN `type` ENUM(
            'sale', 
            'purchase', 
            'cash', 
            'bank', 
            'payroll', 
            'adjustment',
            'general',
            'tax',
            'depreciation',
            'miscellaneous',
            'regularization',
            'opening',
            'closing'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para os tipos originais
        DB::statement("ALTER TABLE `accounting_journals` MODIFY COLUMN `type` ENUM(
            'sale', 
            'purchase', 
            'cash', 
            'bank', 
            'payroll', 
            'adjustment'
        ) NOT NULL");
    }
};
