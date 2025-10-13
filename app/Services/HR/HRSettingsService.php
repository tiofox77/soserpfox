<?php

namespace App\Services\HR;

use App\Models\HR\HRSetting;

class HRSettingsService
{
    /**
     * Obter dias trabalhados por mês
     */
    public function getWorkingDaysPerMonth(): int
    {
        return HRSetting::get('working_days_per_month', 22);
    }

    /**
     * Obter horas de trabalho por dia
     */
    public function getWorkingHoursPerDay(): int
    {
        return HRSetting::get('working_hours_per_day', 8);
    }

    /**
     * Obter total de horas mensais
     */
    public function getMonthlyWorkingHours(): int
    {
        return HRSetting::get('monthly_working_hours', 176);
    }

    /**
     * Obter dias de férias por ano
     */
    public function getVacationDaysPerYear(): int
    {
        return HRSetting::get('vacation_days_per_year', 22);
    }

    /**
     * Obter percentual de subsídio de férias
     */
    public function getVacationSubsidyPercentage(): float
    {
        return HRSetting::get('vacation_subsidy_percentage', 50) / 100;
    }

    /**
     * Obter multiplicador de hora extra por tipo
     */
    public function getOvertimeMultiplier(string $type): float
    {
        $key = match($type) {
            'weekday' => 'overtime_weekday_multiplier',
            'weekend' => 'overtime_weekend_multiplier',
            'holiday' => 'overtime_holiday_multiplier',
            'night' => 'overtime_night_multiplier',
            default => 'overtime_weekday_multiplier',
        };

        return HRSetting::get($key, 1.5);
    }

    /**
     * Obter percentual de subsídio de Natal
     */
    public function getChristmasBonusPercentage(): float
    {
        return HRSetting::get('christmas_bonus_percentage', 100) / 100;
    }

    /**
     * Obter subsídio de alimentação
     */
    public function getMealAllowance(): float
    {
        return HRSetting::get('meal_allowance', 0);
    }

    /**
     * Obter subsídio de transporte
     */
    public function getTransportAllowance(): float
    {
        return HRSetting::get('transport_allowance', 0);
    }

    /**
     * Obter percentual máximo de adiantamento salarial
     */
    public function getMaxSalaryAdvancePercentage(): float
    {
        return HRSetting::get('max_salary_advance_percentage', 50) / 100;
    }

    /**
     * Obter máximo de parcelas para adiantamento
     */
    public function getMaxAdvanceInstallments(): int
    {
        return HRSetting::get('max_advance_installments', 6);
    }

    /**
     * Obter dias de licença por tipo
     */
    public function getLeaveDays(string $type): int
    {
        $key = match($type) {
            'maternity' => 'maternity_leave_days',
            'paternity' => 'paternity_leave_days',
            'bereavement' => 'bereavement_leave_days',
            'marriage' => 'marriage_leave_days',
            default => null,
        };

        if (!$key) {
            return 0;
        }

        return HRSetting::get($key, 0);
    }

    /**
     * Calcular taxa horária baseado no salário mensal
     */
    public function calculateHourlyRate(float $monthlySalary): float
    {
        $monthlyHours = $this->getMonthlyWorkingHours();
        return $monthlySalary / $monthlyHours;
    }

    /**
     * Calcular taxa diária baseado no salário mensal
     */
    public function calculateDailyRate(float $monthlySalary): float
    {
        $workingDays = $this->getWorkingDaysPerMonth();
        return $monthlySalary / $workingDays;
    }

    /**
     * Obter todas as configurações por categoria
     */
    public function getByCategory(string $category): array
    {
        $settings = HRSetting::where('tenant_id', tenant('id'))
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->casted_value;
        }

        return $result;
    }

    /**
     * Obter resumo de todas as configurações
     */
    public function getSummary(): array
    {
        return [
            'general' => [
                'working_days_per_month' => $this->getWorkingDaysPerMonth(),
                'working_hours_per_day' => $this->getWorkingHoursPerDay(),
                'monthly_working_hours' => $this->getMonthlyWorkingHours(),
            ],
            'vacation' => [
                'days_per_year' => $this->getVacationDaysPerYear(),
                'subsidy_percentage' => $this->getVacationSubsidyPercentage() * 100,
            ],
            'overtime' => [
                'weekday_multiplier' => $this->getOvertimeMultiplier('weekday'),
                'weekend_multiplier' => $this->getOvertimeMultiplier('weekend'),
                'holiday_multiplier' => $this->getOvertimeMultiplier('holiday'),
                'night_multiplier' => $this->getOvertimeMultiplier('night'),
            ],
            'payroll' => [
                'christmas_bonus_percentage' => $this->getChristmasBonusPercentage() * 100,
                'meal_allowance' => $this->getMealAllowance(),
                'transport_allowance' => $this->getTransportAllowance(),
                'max_advance_percentage' => $this->getMaxSalaryAdvancePercentage() * 100,
                'max_advance_installments' => $this->getMaxAdvanceInstallments(),
            ],
            'leave' => [
                'maternity_days' => $this->getLeaveDays('maternity'),
                'paternity_days' => $this->getLeaveDays('paternity'),
                'bereavement_days' => $this->getLeaveDays('bereavement'),
                'marriage_days' => $this->getLeaveDays('marriage'),
            ],
        ];
    }
}
