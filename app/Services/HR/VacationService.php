<?php

namespace App\Services\HR;

use App\Models\HR\Vacation;
use App\Models\HR\Employee;
use App\Models\HR\Contract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VacationService
{
    /**
     * Calcular dias de férias com base nos meses trabalhados
     * Angola: 22 dias úteis por ano completo
     */
    public function calculateEntitledDays(Employee $employee, int $referenceYear): array
    {
        $contract = $employee->contracts()
            ->where('start_date', '<=', Carbon::create($referenceYear, 12, 31))
            ->where(function ($q) use ($referenceYear) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', Carbon::create($referenceYear, 1, 1));
            })
            ->first();

        if (!$contract) {
            return [
                'entitled_days' => 0,
                'working_months' => 0,
                'calculated_days' => 0,
                'period_start' => null,
                'period_end' => null,
            ];
        }

        // Período aquisitivo (1 ano de trabalho)
        $periodStart = Carbon::parse($contract->start_date);
        $periodEnd = $periodStart->copy()->addYear()->subDay();

        // Se ano de referência específico
        if ($referenceYear) {
            $periodStart = Carbon::create($referenceYear, 1, 1);
            $periodEnd = Carbon::create($referenceYear, 12, 31);
        }

        // Calcular meses trabalhados no período
        $workingMonths = $this->calculateWorkingMonths($contract->start_date, $periodEnd);
        
        // Cálculo proporcional
        $entitledDays = 22; // Base legal Angola
        $calculatedDays = round(($entitledDays / 12) * min($workingMonths, 12));

        return [
            'entitled_days' => $entitledDays,
            'working_months' => min($workingMonths, 12),
            'calculated_days' => $calculatedDays,
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
        ];
    }

    /**
     * Calcular meses trabalhados
     */
    private function calculateWorkingMonths($startDate, $endDate): int
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        return max(0, $start->diffInMonths($end));
    }

    /**
     * Calcular dias úteis entre duas datas (excluindo fins de semana)
     */
    public function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // Contar apenas dias úteis (segunda a sexta)
            if ($currentDate->isWeekday()) {
                $workingDays++;
            }
            $currentDate->addDay();
        }

        return $workingDays;
    }

    /**
     * Calcular valores financeiros das férias
     */
    public function calculateVacationPay(Employee $employee, int $requestedDays): array
    {
        // Tentar buscar contrato ativo
        $contract = $employee->contracts()
            ->where('status', 'active')
            ->first();

        // Se não tiver contrato, usar salário do funcionário
        $baseSalary = $contract ? $contract->base_salary : ($employee->salary ?? 0);

        if ($baseSalary == 0) {
            return [
                'daily_rate' => 0,
                'vacation_pay' => 0,
                'subsidy_amount' => 0,
                'total_amount' => 0,
            ];
        }

        // Taxa diária (salário / 22 dias úteis)
        $dailyRate = $baseSalary / 22;

        // Pagamento de férias
        $vacationPay = $dailyRate * $requestedDays;

        // Subsídio de férias (14º mês) - 50% do salário (mínimo legal Angola)
        $subsidyAmount = $baseSalary * 0.5;

        // Total a receber
        $totalAmount = $vacationPay + $subsidyAmount;

        return [
            'daily_rate' => round($dailyRate, 2),
            'vacation_pay' => round($vacationPay, 2),
            'subsidy_amount' => round($subsidyAmount, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * Criar solicitação de férias
     */
    public function createVacationRequest(array $data): Vacation
    {
        DB::beginTransaction();

        try {
            $employee = Employee::findOrFail($data['employee_id']);

            // Calcular direito a férias
            $entitlement = $this->calculateEntitledDays($employee, $data['reference_year']);

            // Calcular dias úteis solicitados
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            $workingDays = $this->calculateWorkingDays($startDate, $endDate);

            // Validar se tem direito suficiente
            if ($workingDays > $entitlement['calculated_days']) {
                throw new \Exception('Funcionário não tem dias de férias suficientes. Disponível: ' . $entitlement['calculated_days'] . ' dias.');
            }

            // Calcular valores financeiros
            $financials = $this->calculateVacationPay($employee, $workingDays);

            // Gerar número de férias
            $vacationNumber = 'VAC-' . date('Y') . '-' . str_pad(Vacation::count() + 1, 5, '0', STR_PAD_LEFT);

            // Criar registro
            $vacation = Vacation::create([
                'tenant_id' => $data['tenant_id'],
                'employee_id' => $data['employee_id'],
                'vacation_number' => $vacationNumber,
                'reference_year' => $data['reference_year'],
                'period_start' => $entitlement['period_start'],
                'period_end' => $entitlement['period_end'],
                'entitled_days' => $entitlement['entitled_days'],
                'working_months' => $entitlement['working_months'],
                'calculated_days' => $entitlement['calculated_days'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'requested_days' => $endDate->diffInDays($startDate) + 1,
                'working_days' => $workingDays,
                'daily_rate' => $financials['daily_rate'],
                'vacation_pay' => $financials['vacation_pay'],
                'subsidy_amount' => $financials['subsidy_amount'],
                'total_amount' => $financials['total_amount'],
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'replacement_employee_id' => $data['replacement_employee_id'] ?? null,
            ]);

            DB::commit();

            return $vacation;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obter histórico de férias do funcionário
     */
    public function getEmployeeVacationHistory(int $employeeId, ?int $year = null)
    {
        $query = Vacation::where('employee_id', $employeeId)
            ->with(['approvedBy', 'replacementEmployee']);

        if ($year) {
            $query->byYear($year);
        }

        return $query->latest()->get();
    }

    /**
     * Obter dias de férias disponíveis
     */
    public function getAvailableVacationDays(Employee $employee, int $year): array
    {
        $entitlement = $this->calculateEntitledDays($employee, $year);
        
        $usedDays = Vacation::where('employee_id', $employee->id)
            ->byYear($year)
            ->whereIn('status', ['approved', 'in_progress', 'completed'])
            ->sum('working_days');

        $availableDays = $entitlement['calculated_days'] - $usedDays;

        return [
            'entitled' => $entitlement['calculated_days'],
            'used' => $usedDays,
            'available' => max(0, $availableDays),
        ];
    }

    /**
     * Verificar sobreposição de férias
     */
    public function checkOverlap(int $employeeId, $startDate, $endDate, ?int $excludeVacationId = null): bool
    {
        $query = Vacation::where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'in_progress'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            });

        if ($excludeVacationId) {
            $query->where('id', '!=', $excludeVacationId);
        }

        return $query->exists();
    }

    /**
     * Processar início automático de férias
     */
    public function processVacationStarts()
    {
        $today = now()->format('Y-m-d');

        Vacation::where('status', 'approved')
            ->where('start_date', $today)
            ->each(function ($vacation) {
                $vacation->start();
            });
    }

    /**
     * Processar conclusão automática de férias
     */
    public function processVacationEnds()
    {
        $today = now()->format('Y-m-d');

        Vacation::where('status', 'in_progress')
            ->where('end_date', '<', $today)
            ->each(function ($vacation) {
                $vacation->complete();
            });
    }
}
