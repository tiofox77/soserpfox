<?php

/**
 * Seed attendance records for EMP-00001 (Jan-Apr 2026)
 * Run: php scripts/seed_attendance_emp00001.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\HR\Employee;
use App\Models\HR\Attendance;
use Carbon\Carbon;

use App\Models\HR\HRSetting;

$employee = Employee::where('employee_number', 'EMP-00001')->first();

if (!$employee) {
    echo "Employee EMP-00001 not found!\n";
    exit(1);
}

echo "Employee: {$employee->first_name} {$employee->last_name} (ID:{$employee->id}, Tenant:{$employee->tenant_id})\n";

// Read Saturday config from HRSetting
$workOnSaturday   = (bool) HRSetting::get('work_on_saturday', false);
$saturdayHours    = (int) HRSetting::get('saturday_working_hours', 4);
$saturdayCheckOut = Carbon::parse('08:00:00')->addHours($saturdayHours)->format('H:i:s');

echo "Trabalha ao Sábado: " . ($workOnSaturday ? "SIM ({$saturdayHours}h)" : "NÃO") . "\n";

// Delete existing attendance for this employee Jan-Apr 2026 to avoid duplicates
Attendance::where('employee_id', $employee->id)
    ->whereBetween('date', ['2026-01-01', '2026-04-30'])
    ->delete();

echo "Cleared existing records Jan-Apr 2026.\n";

// Configuration per month: [absent_days_indices, late_days_indices]
$monthConfigs = [
    // January 2026: 2 absences, 3 late arrivals
    1 => [
        'absences' => [8, 22],
        'lates'    => [5, 14, 28],
    ],
    // February 2026: 1 absence, 2 late arrivals
    2 => [
        'absences' => [13],
        'lates'    => [3, 24],
    ],
    // March 2026: 3 absences, 4 late arrivals
    3 => [
        'absences' => [6, 17, 30],
        'lates'    => [2, 11, 19, 26],
    ],
    // April 2026: 1 absence, 2 late arrivals
    4 => [
        'absences' => [10],
        'lates'    => [7, 21],
    ],
];

$normalCheckIn  = '08:00:00';
$normalCheckOut = '17:00:00';
$totalCreated   = 0;

foreach ($monthConfigs as $month => $config) {
    $startDate = Carbon::create(2026, $month, 1);
    $endDate   = $startDate->copy()->endOfMonth();

    // For April, seed up to end of month (future days will just be records)
    $currentDate = $startDate->copy();

    while ($currentDate->lte($endDate)) {
        $isSunday   = $currentDate->isSunday();
        $isSaturday = $currentDate->isSaturday();

        // Sunday is always off
        if ($isSunday) {
            $currentDate->addDay();
            continue;
        }

        // Saturday: skip if company doesn't work on Saturday
        if ($isSaturday && !$workOnSaturday) {
            $currentDate->addDay();
            continue;
        }

        // Determine check-out and expected hours for this day
        $dayCheckOut    = $isSaturday ? $saturdayCheckOut : $normalCheckOut;
        $dayExpectHours = $isSaturday ? $saturdayHours : 8;

        $day = $currentDate->day;
        $isAbsent = in_array($day, $config['absences']);
        $isLate   = in_array($day, $config['lates']);

        if ($isAbsent) {
            // Absent day
            Attendance::create([
                'tenant_id'      => $employee->tenant_id,
                'employee_id'    => $employee->id,
                'date'           => $currentDate->format('Y-m-d'),
                'check_in'       => null,
                'check_out'      => null,
                'time_in'        => null,
                'time_out'       => null,
                'hours_worked'   => 0,
                'overtime_hours' => 0,
                'is_late'        => false,
                'late_minutes'   => 0,
                'affects_payroll'=> true,
                'status'         => 'absent',
                'notes'          => 'Falta registada' . ($isSaturday ? ' (Sábado)' : ''),
            ]);
        } elseif ($isLate) {
            // Late arrival (15-45 min late)
            $lateMin = rand(15, 45);
            $checkIn = Carbon::parse($normalCheckIn)->addMinutes($lateMin)->format('H:i:s');
            $hoursWorked = round((Carbon::parse($dayCheckOut)->diffInMinutes(Carbon::parse($checkIn))) / 60, 2);

            Attendance::create([
                'tenant_id'      => $employee->tenant_id,
                'employee_id'    => $employee->id,
                'date'           => $currentDate->format('Y-m-d'),
                'check_in'       => $checkIn,
                'check_out'      => $dayCheckOut,
                'time_in'        => $currentDate->format('Y-m-d') . ' ' . $checkIn,
                'time_out'       => $currentDate->format('Y-m-d') . ' ' . $dayCheckOut,
                'hours_worked'   => $hoursWorked,
                'overtime_hours' => 0,
                'is_late'        => true,
                'late_minutes'   => $lateMin,
                'affects_payroll'=> true,
                'status'         => 'present',
                'notes'          => "Atraso de {$lateMin} minutos" . ($isSaturday ? ' (Sábado)' : ''),
            ]);
        } else {
            // Normal present day
            $overtimeHours = 0;
            $checkOut = $dayCheckOut;
            $hoursWorked = $dayExpectHours;

            // Some weekday days have overtime (random ~20% chance), not on Saturday
            if (!$isSaturday && rand(1, 100) <= 20) {
                $extraMin = rand(60, 180); // 1-3h overtime
                $overtimeHours = round($extraMin / 60, 2);
                $checkOut = Carbon::parse($dayCheckOut)->addMinutes($extraMin)->format('H:i:s');
                $hoursWorked = round($dayExpectHours + $overtimeHours, 2);
            }

            Attendance::create([
                'tenant_id'      => $employee->tenant_id,
                'employee_id'    => $employee->id,
                'date'           => $currentDate->format('Y-m-d'),
                'check_in'       => $normalCheckIn,
                'check_out'      => $checkOut,
                'time_in'        => $currentDate->format('Y-m-d') . ' ' . $normalCheckIn,
                'time_out'       => $currentDate->format('Y-m-d') . ' ' . $checkOut,
                'hours_worked'   => $hoursWorked,
                'overtime_hours' => $overtimeHours,
                'is_late'        => false,
                'late_minutes'   => 0,
                'affects_payroll'=> true,
                'status'         => 'present',
                'notes'          => ($isSaturday ? "Sábado ({$dayExpectHours}h)" : null) . ($overtimeHours > 0 ? " Hora extra: {$overtimeHours}h" : ''),
            ]);
        }

        $totalCreated++;
        $currentDate->addDay();
    }

    // Count stats for this month
    $monthName = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril'][$month];
    $presentCount = Attendance::where('employee_id', $employee->id)
        ->whereYear('date', 2026)->whereMonth('date', $month)
        ->where('status', 'present')->count();
    $absentCount = Attendance::where('employee_id', $employee->id)
        ->whereYear('date', 2026)->whereMonth('date', $month)
        ->where('status', 'absent')->count();
    $lateCount = Attendance::where('employee_id', $employee->id)
        ->whereYear('date', 2026)->whereMonth('date', $month)
        ->where('is_late', true)->count();

    echo "{$monthName}: {$presentCount} presenças, {$absentCount} faltas, {$lateCount} atrasos\n";
}

echo "\nTotal de registos criados: {$totalCreated}\n";
echo "Done!\n";
