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
        try {
            // Verificar se a tabela existe
            if (!Schema::hasTable('invoicing_series')) {
                return;
            }

            // Obter a definição atual da coluna
            $column = DB::select("SHOW COLUMNS FROM invoicing_series WHERE Field = 'document_type'");
            
            if (empty($column)) {
                return;
            }

            $currentType = $column[0]->Type;
            
            // Verificar se já contém os novos valores (pos, purchase, advance)
            if (str_contains($currentType, 'pos') && str_contains($currentType, 'purchase') && str_contains($currentType, 'advance')) {
                // Já foi atualizado, pular
                return;
            }

            // MySQL não permite alterar ENUM diretamente, precisamos recriar a coluna
            // Remover valores duplicados e adicionar novos valores únicos
            DB::statement("ALTER TABLE invoicing_series MODIFY COLUMN document_type ENUM(
                'invoice', 
                'proforma', 
                'receipt', 
                'credit_note', 
                'debit_note', 
                'pos', 
                'purchase', 
                'advance',
                'FT',
                'FS',
                'FR',
                'NC',
                'ND',
                'GT',
                'FP',
                'VD',
                'GR',
                'GC',
                'RC'
            ) COMMENT 'Tipo de documento'");
            
        } catch (\Exception $e) {
            // Se houver erro (ex: coluna já modificada), apenas registrar e continuar
            \Log::warning('Migration 2025_10_05_141154: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            if (!Schema::hasTable('invoicing_series')) {
                return;
            }
            
            DB::statement("ALTER TABLE invoicing_series MODIFY COLUMN document_type ENUM('invoice', 'proforma', 'receipt', 'credit_note', 'debit_note') COMMENT 'Tipo de documento'");
        } catch (\Exception $e) {
            \Log::warning('Migration 2025_10_05_141154 rollback: ' . $e->getMessage());
        }
    }
};
