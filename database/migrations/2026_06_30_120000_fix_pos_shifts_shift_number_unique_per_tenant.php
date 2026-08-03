<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige o índice único de shift_number.
 *
 * O número do turno (POS-{ano}-NNN) é gerado POR TENANT (PosShift usa BelongsToTenant),
 * mas o índice estava UNIQUE global -> dois tenants a abrir o 1.º turno do ano geravam
 * ambos POS-2026-001 e colidiam (SQLSTATE 23000 / 1062).
 *
 * Solução: único composto (tenant_id, shift_number) — mesmo padrão usado no NIF dos clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_pos_shifts', function (Blueprint $table) {
            // Remove o único global (nome gerado pelo Laravel na criação da tabela)
            $table->dropUnique('invoicing_pos_shifts_shift_number_unique');
            // Único por tenant
            $table->unique(['tenant_id', 'shift_number'], 'pos_shifts_tenant_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_pos_shifts', function (Blueprint $table) {
            $table->dropUnique('pos_shifts_tenant_number_unique');
            $table->unique('shift_number', 'invoicing_pos_shifts_shift_number_unique');
        });
    }
};
