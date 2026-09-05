<?php

namespace App\Livewire\HR;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceManagement extends Component
{
    use WithPagination, WithFileUploads;

    public $view = 'list'; // 'list' ou 'calendar'
    public $search = '';
    public $dateFilter = '';
    public $employeeFilter = '';
    public $statusFilter = '';
    public $selectedMonth;
    public $selectedYear;
    
    // Modal
    public $showModal = false;
    public $showDetailsModal = false;
    public $showImportModal = false;
    public $editMode = false;
    public $attendanceId;
    
    // Import
    public $importFile;
    public $biometricSystem = 'zkteco';
    
    // Form Fields
    public $employee_id = '';
    public $date = '';
    public $check_in = '';
    public $check_out = '';
    public $notes = '';
    public $status = 'present';
    
    // Details
    public $selectedAttendance;

    protected $rules = [
        'employee_id' => 'required|exists:hr_employees,id',
        'date' => 'required|date',
        'check_in' => 'required',
        'check_out' => 'nullable',
        'notes' => 'nullable|string|max:500',
        'status' => 'required|in:present,absent,late,half_day,sick,vacation',
    ];

    public function mount()
    {
        $this->dateFilter = date('Y-m-d');
        $this->date = date('Y-m-d');
        $this->selectedMonth = date('m');
        $this->selectedYear = date('Y');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function changeView($view)
    {
        $this->view = $view;
    }

    public function changeMonth($direction)
    {
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1);
        if ($direction === 'prev') {
            $date->subMonth();
        } else {
            $date->addMonth();
        }
        $this->selectedMonth = $date->format('m');
        $this->selectedYear = $date->format('Y');
    }

    public function create()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function createForDate($date)
    {
        $this->resetForm();
        $this->date = $date;
        $this->editMode = false;
        $this->showModal = true;
    }

    public function checkIn($employeeId)
    {
        try {
            $today = date('Y-m-d');
            
            // Verificar se já tem registro hoje
            $existing = Attendance::where('employee_id', $employeeId)
                ->whereDate('date', $today)
                ->first();
            
            if ($existing) {
                session()->flash('error', 'Funcionário já registrou presença hoje!');
                return;
            }
            
            Attendance::create([
                'tenant_id' => auth()->user()->activeTenantId(),
                'employee_id' => $employeeId,
                'date' => $today,
                'check_in' => now()->format('H:i:s'),
                'status' => 'present',
            ]);
            
            session()->flash('success', 'Entrada registrada com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao registrar entrada: ' . $e->getMessage());
        }
    }

    public function checkOut($id)
    {
        try {
            $attendance = Attendance::findOrFail($id);
            
            if ($attendance->check_out) {
                session()->flash('error', 'Saída já foi registrada!');
                return;
            }
            
            $checkIn = Carbon::parse($attendance->date . ' ' . $attendance->check_in);
            $checkOut = now();
            $hoursWorked = $checkIn->diffInMinutes($checkOut) / 60;
            
            $attendance->update([
                'check_out' => $checkOut->format('H:i:s'),
                'hours_worked' => round($hoursWorked, 2),
            ]);
            
            session()->flash('success', 'Saída registrada com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao registrar saída: ' . $e->getMessage());
        }
    }

    public function save()
    {
        $this->validate();

        try {
            DB::transaction(function () {
            // Serialize manual entries for this employee, including concurrent clicks.
            Employee::where('tenant_id', activeTenantId())->lockForUpdate()->findOrFail($this->employee_id);
            $existing = Attendance::where('tenant_id', activeTenantId())
                ->where('employee_id', $this->employee_id)->whereDate('date', $this->date)
                ->when($this->editMode, fn ($q) => $q->where('id', '!=', $this->attendanceId))
                ->exists();
            if ($existing) {
                throw new \InvalidArgumentException('Já existe uma presença para este funcionário nesta data. Edite o registo existente.');
            }
            // Calcular horas trabalhadas
            $hoursWorked = null;
            if ($this->check_in && $this->check_out) {
                $checkIn = Carbon::parse($this->date . ' ' . $this->check_in);
                $checkOut = Carbon::parse($this->date . ' ' . $this->check_out);
                $hoursWorked = $checkIn->diffInMinutes($checkOut) / 60;
            }

            $data = [
                'tenant_id' => auth()->user()->activeTenantId(),
                'employee_id' => $this->employee_id,
                'date' => $this->date,
                'check_in' => $this->check_in,
                'check_out' => $this->check_out,
                'hours_worked' => $hoursWorked ? round($hoursWorked, 2) : null,
                'status' => $this->status,
                'notes' => $this->notes,
            ];

            if ($this->editMode) {
                $attendance = Attendance::findOrFail($this->attendanceId);
                $attendance->update($data);
                session()->flash('success', 'Presença atualizada com sucesso!');
            } else {
                Attendance::create($data);
                session()->flash('success', 'Presença registrada com sucesso!');
            }
            });

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $attendance = Attendance::findOrFail($id);
        
        $this->attendanceId = $attendance->id;
        $this->employee_id = $attendance->employee_id;
        $this->shift_id = $attendance->shift_id;
        $this->date = $attendance->date->format('Y-m-d');
        $this->check_in = $attendance->check_in;
        $this->check_out = $attendance->check_out;
        $this->status = $attendance->status;
        $this->notes = $attendance->notes;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function viewDetails($id)
    {
        $this->selectedAttendance = Attendance::with('employee')->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function delete($id)
    {
        try {
            $attendance = Attendance::findOrFail($id);
            $attendance->delete();
            session()->flash('success', 'Registro removido com sucesso!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao remover registro: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showDetailsModal = false;
        $this->resetForm();
    }

    public function openImportModal()
    {
        $this->showImportModal = true;
        $this->importFile = null;
        $this->biometricSystem = 'zkteco';
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->biometricSystem = 'zkteco';
    }

    public function processImport()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'biometricSystem' => 'required|in:zkteco,hikvision',
        ]);

        try {
            $filePath = $this->importFile->getRealPath();
            $tenantId = auth()->user()->activeTenantId();

            // Load spreadsheet
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            // Column mapping per biometric system
            $map = $this->getBiometricColumnMap($this->biometricSystem);

            $imported = 0;
            $skipped = 0;
            $errors = [];
            $headerSkipped = false;

            foreach ($rows as $rowIndex => $row) {
                // Skip header row
                if (!$headerSkipped) {
                    $headerSkipped = true;
                    continue;
                }

                // Skip empty rows
                $empIdentifier = trim($row[$map['employee']] ?? '');
                if (empty($empIdentifier)) continue;

                // Find employee by employee_number or name
                $employee = Employee::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($empIdentifier) {
                        $q->where('employee_number', $empIdentifier)
                          ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), $empIdentifier)
                          ->orWhere('first_name', $empIdentifier);
                    })
                    ->first();

                if (!$employee) {
                    $skipped++;
                    $errors[] = "Linha {$rowIndex}: Funcionário '{$empIdentifier}' não encontrado";
                    continue;
                }

                // Parse date
                $rawDate = $row[$map['date']] ?? '';
                $date = $this->parseImportDate($rawDate);
                if (!$date) {
                    $skipped++;
                    $errors[] = "Linha {$rowIndex}: Data inválida '{$rawDate}'";
                    continue;
                }

                // Parse times
                $checkIn = $this->parseImportTime($row[$map['check_in']] ?? '');
                $checkOut = $this->parseImportTime($row[$map['check_out']] ?? '');

                // Calculate hours worked
                $hoursWorked = null;
                if ($checkIn && $checkOut) {
                    $in = Carbon::parse($date . ' ' . $checkIn);
                    $out = Carbon::parse($date . ' ' . $checkOut);
                    if ($out->lt($in)) $out->addDay();
                    $hoursWorked = round($in->diffInMinutes($out) / 60, 2);
                }

                // Determine status
                $status = 'present';
                if (!$checkIn && !$checkOut) {
                    $status = 'absent';
                }

                // Check for late arrival (> 15 min)
                $isLate = false;
                $lateMinutes = 0;
                $shiftStart = '08:00';
                if ($employee->shift) {
                    $shiftStart = $employee->shift->start_time ?? '08:00';
                }
                if ($checkIn && $checkIn > $shiftStart) {
                    $diff = Carbon::parse($date . ' ' . $shiftStart)->diffInMinutes(Carbon::parse($date . ' ' . $checkIn));
                    if ($diff > 15) {
                        $isLate = true;
                        $lateMinutes = $diff;
                        $status = 'late';
                    }
                }

                // Calculate overtime
                $workingHoursPerDay = (float) \App\Models\HR\HRSetting::get('working_hours_per_day', 8);
                $overtimeHours = ($hoursWorked && $hoursWorked > $workingHoursPerDay) 
                    ? round($hoursWorked - $workingHoursPerDay, 2) 
                    : 0;

                // Create or update attendance record
                Attendance::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'employee_id' => $employee->id,
                        'date' => $date,
                    ],
                    [
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'time_in' => $checkIn,
                        'time_out' => $checkOut,
                        'hours_worked' => $hoursWorked,
                        'overtime_hours' => $overtimeHours,
                        'status' => $status,
                        'is_late' => $isLate,
                        'late_minutes' => $lateMinutes,
                        'affects_payroll' => true,
                        'remarks' => 'Importado via ' . strtoupper($this->biometricSystem),
                    ]
                );

                $imported++;
            }

            $message = "Importação concluída: {$imported} registos importados";
            if ($skipped > 0) {
                $message .= ", {$skipped} ignorados";
            }

            session()->flash('success', $message);
            
            if (!empty($errors) && count($errors) <= 10) {
                session()->flash('import_errors', $errors);
            }

            $this->closeImportModal();

            logger()->info('Importação de presenças concluída', [
                'file' => $this->importFile->getClientOriginalName(),
                'system' => $this->biometricSystem,
                'imported' => $imported,
                'skipped' => $skipped,
            ]);

        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao processar arquivo: ' . $e->getMessage());
            logger()->error('Erro na importação', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Column mapping for biometric systems
     */
    private function getBiometricColumnMap(string $system): array
    {
        return match ($system) {
            'hikvision' => ['employee' => 'A', 'date' => 'B', 'check_in' => 'C', 'check_out' => 'D'],
            default     => ['employee' => 'A', 'date' => 'B', 'check_in' => 'C', 'check_out' => 'D'], // zkteco
        };
    }

    /**
     * Parse date from various import formats
     */
    private function parseImportDate($value): ?string
    {
        if (empty($value)) return null;

        // Numeric Excel serial date
        if (is_numeric($value)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {}
        }

        // Try common date formats
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d'] as $fmt) {
            try {
                $parsed = Carbon::createFromFormat($fmt, trim($value));
                if ($parsed) return $parsed->format('Y-m-d');
            } catch (\Exception $e) {}
        }

        return null;
    }

    /**
     * Parse time from various import formats
     */
    private function parseImportTime($value): ?string
    {
        if (empty($value)) return null;

        // Numeric Excel time fraction (e.g. 0.354167 = 08:30)
        if (is_numeric($value) && (float) $value < 1) {
            $totalMinutes = round((float) $value * 24 * 60);
            $hours = intdiv((int) $totalMinutes, 60);
            $minutes = $totalMinutes % 60;
            return sprintf('%02d:%02d', $hours, $minutes);
        }

        // String time
        $value = trim($value);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
            return substr($value, 0, 5);
        }

        return null;
    }

    private function resetForm()
    {
        $this->attendanceId = null;
        $this->employee_id = '';
        $this->date = date('Y-m-d');
        $this->check_in = '';
        $this->check_out = '';
        $this->status = 'present';
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function getCalendarEvents($start, $end)
    {
        $tenantId = auth()->user()->activeTenantId();
        
        $attendances = Attendance::where('tenant_id', $tenantId)
            ->with('employee')
            ->whereBetween('date', [$start, $end])
            ->get();

        $events = [];
        foreach ($attendances as $attendance) {
            // Definir cor baseada no status
            $color = match($attendance->status) {
                'present' => '#10b981', // Verde
                'absent' => '#ef4444',   // Vermelho
                'late' => '#f59e0b',      // Amarelo
                'half_day' => '#3b82f6', // Azul
                'sick' => '#a855f7',      // Roxo
                'vacation' => '#6366f1',  // Índigo
                default => '#6b7280'      // Cinza
            };

            $statusLabel = match($attendance->status) {
                'present' => 'Presente',
                'absent' => 'Ausente',
                'late' => 'Atrasado',
                'half_day' => 'Meio Período',
                'sick' => 'Doente',
                'vacation' => 'Férias',
                default => 'Outro'
            };

            $events[] = [
                'id' => $attendance->id,
                'title' => $attendance->employee->full_name,
                'start' => $attendance->date->format('Y-m-d') . ' ' . ($attendance->check_in ?? '00:00:00'),
                'end' => $attendance->date->format('Y-m-d') . ' ' . ($attendance->check_out ?? '23:59:59'),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'employee_name' => $attendance->employee->full_name,
                    'employee_number' => $attendance->employee->employee_number,
                    'check_in' => $attendance->check_in ? substr($attendance->check_in, 0, 5) : null,
                    'check_out' => $attendance->check_out ? substr($attendance->check_out, 0, 5) : null,
                    'hours_worked' => $attendance->hours_worked ? number_format($attendance->hours_worked, 1) : '0',
                    'status' => $attendance->status,
                    'status_label' => $statusLabel,
                ],
            ];
        }

        return $events;
    }

    public function render()
    {
        $tenantId = auth()->user()->activeTenantId();
        
        $query = Attendance::where('tenant_id', $tenantId)
            ->with('employee');

        if ($this->search) {
            $query->whereHas('employee', function ($q) {
                $q->where('first_name', 'like', '%' . $this->search . '%')
                  ->orWhere('last_name', 'like', '%' . $this->search . '%')
                  ->orWhere('employee_number', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->dateFilter) {
            $query->whereDate('date', $this->dateFilter);
        }

        if ($this->employeeFilter) {
            $query->where('employee_id', $this->employeeFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $attendances = $query->latest('date')->latest('check_in')->paginate(15);
        
        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        // Dados para o calendário
        $calendarData = collect();
        $calendarStats = ['present' => 0, 'absent' => 0, 'late' => 0, 'half_day' => 0, 'total_hours' => 0];
        if ($this->view === 'calendar') {
            $calQuery = Attendance::where('tenant_id', $tenantId)
                ->whereYear('date', $this->selectedYear)
                ->whereMonth('date', $this->selectedMonth)
                ->with('employee');

            if ($this->employeeFilter) {
                $calQuery->where('employee_id', $this->employeeFilter);
            }

            $allRecords = $calQuery->get();

            $calendarData = $allRecords->groupBy(function($item) {
                return $item->date->format('Y-m-d');
            });

            $calendarStats = [
                'present' => $allRecords->where('status', 'present')->count(),
                'absent'  => $allRecords->where('status', 'absent')->count(),
                'late'    => $allRecords->where('is_late', true)->count(),
                'half_day'=> $allRecords->where('status', 'half_day')->count(),
                'total_hours' => round($allRecords->sum('hours_worked'), 1),
            ];
        }

        $shifts = Shift::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.hr.attendance.attendance', [
            'attendances' => $attendances,
            'employees' => $employees,
            'shifts' => $shifts,
            'calendarData' => $calendarData,
            'calendarStats' => $calendarStats,
        ])->layout('layouts.app', ['title' => 'Gestão de Presenças']);
    }
}
