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
        logger('🆕 Método create() chamado - Abrindo modal');
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
        logger('✅ showModal definido como true');
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
            'importFile' => 'required|file|mimes:xlsx,xls|max:5120', // 5MB max
            'biometricSystem' => 'required|in:zkteco,hikvision',
        ]);

        try {
            // TODO: Implementar lógica de importação
            // 1. Ler arquivo Excel usando PhpSpreadsheet
            // 2. Validar estrutura das colunas
            // 3. Mapear dados conforme sistema biométrico
            // 4. Validar se funcionários existem
            // 5. Criar/atualizar registros de presença
            // 6. Retornar estatísticas (importados, erros, duplicados)

            session()->flash('success', 'Funcionalidade de importação será implementada em breve!');
            $this->closeImportModal();
            
            logger()->info('Import solicitado', [
                'file' => $this->importFile->getClientOriginalName(),
                'system' => $this->biometricSystem,
                'size' => $this->importFile->getSize(),
            ]);
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao processar arquivo: ' . $e->getMessage());
            logger()->error('Erro na importação', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
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
        $calendarData = [];
        if ($this->view === 'calendar') {
            $calendarData = Attendance::where('tenant_id', $tenantId)
                ->whereYear('date', $this->selectedYear)
                ->whereMonth('date', $this->selectedMonth)
                ->with('employee')
                ->get()
                ->groupBy(function($item) {
                    return $item->date->format('Y-m-d');
                });
        }

        return view('livewire.hr.attendance.attendance', [
            'attendances' => $attendances,
            'employees' => $employees,
            'calendarData' => $calendarData,
        ])->layout('layouts.app', ['title' => 'Gestão de Presenças']);
    }
}
