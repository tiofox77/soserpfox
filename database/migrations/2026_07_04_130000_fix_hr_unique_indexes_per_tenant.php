<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torna os identificadores do RH únicos POR TENANT (não globais).
 *
 * Todos estes números/códigos são gerados/introduzidos por tenant, mas tinham
 * índice UNIQUE global (mesmo bug do NIF, shift_number e da Oficina):
 *   - hr_employees.employee_number, hr_employees.nif
 *   - hr_departments.code, hr_positions.code
 *   - hr_contracts.contract_number, hr_vacations.vacation_number,
 *     hr_leaves.leave_number, hr_salary_advances.advance_number,
 *     hr_overtime.overtime_number
 *   - hr_settings.key  <-- CRÍTICO: como era global, o 1.º tenant a gravar uma
 *     definição trancava a chave e os restantes tenants não conseguiam gravar (1062).
 *
 * Seguro: uniques globais garantem que não há pares (tenant_id, coluna) duplicados,
 * por isso os índices compostos criam-se sem conflito.
 */
return new class extends Migration
{
    /** [tabela => [ [coluna, nomeAntigo, nomeNovo], ... ] ] */
    private array $map = [
        'hr_employees' => [
            ['employee_number', 'hr_employees_employee_number_unique', 'hr_employees_tenant_number_unique'],
            ['nif', 'hr_employees_nif_unique', 'hr_employees_tenant_nif_unique'],
        ],
        'hr_departments' => [
            ['code', 'hr_departments_code_unique', 'hr_departments_tenant_code_unique'],
        ],
        'hr_positions' => [
            ['code', 'hr_positions_code_unique', 'hr_positions_tenant_code_unique'],
        ],
        'hr_contracts' => [
            ['contract_number', 'hr_contracts_contract_number_unique', 'hr_contracts_tenant_number_unique'],
        ],
        'hr_vacations' => [
            ['vacation_number', 'hr_vacations_vacation_number_unique', 'hr_vacations_tenant_number_unique'],
        ],
        'hr_leaves' => [
            ['leave_number', 'hr_leaves_leave_number_unique', 'hr_leaves_tenant_number_unique'],
        ],
        'hr_salary_advances' => [
            ['advance_number', 'hr_salary_advances_advance_number_unique', 'hr_salary_advances_tenant_number_unique'],
        ],
        'hr_overtime' => [
            ['overtime_number', 'hr_overtime_overtime_number_unique', 'hr_overtime_tenant_number_unique'],
        ],
        'hr_settings' => [
            ['key', 'hr_settings_key_unique', 'hr_settings_tenant_key_unique'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->map as $table => $cols) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($cols) {
                foreach ($cols as [$col, $old, $new]) {
                    $t->dropUnique($old);
                    $t->unique(['tenant_id', $col], $new);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->map as $table => $cols) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($cols) {
                foreach ($cols as [$col, $old, $new]) {
                    $t->dropUnique($new);
                    $t->unique($col, $old);
                }
            });
        }
    }
};
