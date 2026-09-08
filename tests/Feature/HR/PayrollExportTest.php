<?php

namespace Tests\Feature\HR;

use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use Tests\TenantTestCase;

class PayrollExportTest extends TenantTestCase
{
    public function test_folha_pode_ser_exportada_para_excel(): void
    {
        $department = Department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Operações',
            'code' => 'OPS-' . uniqid(),
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'EXP-' . uniqid(),
            'first_name' => 'Exportação',
            'last_name' => 'QA',
            'full_name' => 'Exportação QA',
            'department_id' => $department->id,
            'status' => 'active',
            'base_salary' => 150000,
        ]);

        $payroll = Payroll::create([
            'tenant_id' => $this->tenant->id,
            'payroll_number' => 'FP-EXP-' . uniqid(),
            'year' => 2026,
            'month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'total_gross_salary' => 150000,
            'total_allowances' => 0,
            'total_bonuses' => 0,
            'total_deductions' => 4500,
            'total_irt' => 0,
            'total_inss_employee' => 4500,
            'total_inss_employer' => 12000,
            'total_net_salary' => 145500,
            'total_employees' => 1,
            'processed_employees' => 1,
            'status' => 'draft',
        ]);

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'base_salary' => 150000,
            'gross_salary' => 150000,
            'inss_employee' => 4500,
            'inss_employer' => 12000,
            'total_deductions' => 4500,
            'net_salary' => 145500,
            'worked_days' => 22,
            'absence_days' => 0,
            'status' => 'calculated',
        ]);

        // O EXCEL DA FOLHA É O SALÁRIO DE TODA A GENTE numa folha de cálculo:
        // pede a mesma permissão que processar a folha. Sem ela, a rota não
        // abre — e era exactamente uma das que nada guardava.
        $this->comModulo('rh')
            ->comPermissoes('payroll.process')
            ->get(route('hr.payroll.excel', $payroll->id))
            ->assertOk()
            ->assertDownload('folha_2026_09.xlsx');
    }

    /** E sem a permissão, ninguém descarrega a folha de salários de ninguém. */
    public function test_sem_permissao_o_excel_da_folha_nao_abre(): void
    {
        $payroll = Payroll::create([
            'tenant_id' => $this->tenant->id,
            'payroll_number' => 'FP-GUARDA-' . uniqid(),
            'year' => 2026,
            'month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'draft',
        ]);

        $this->comModulo('rh')
            ->get(route('hr.payroll.excel', $payroll->id))
            ->assertForbidden();
    }
}
