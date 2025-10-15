<?php

namespace App\Observers;

use App\Models\HR\Leave;
use App\Models\HR\Attendance;
use App\Services\ImmediateNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LeaveObserver
{
    /**
     * Quando a licença for aprovada, criar registros de presença automaticamente
     */
    public function updated(Leave $leave)
    {
        // Verificar se o status mudou para aprovado
        if ($leave->isDirty('status') && $leave->status === 'approved') {
            $this->createAttendanceRecords($leave);
            
            // Enviar notificação de aprovação
            try {
                $notificationService = new ImmediateNotificationService($leave->tenant_id);
                $notificationService->notifyLeaveApproved($leave);
            } catch (\Exception $e) {
                Log::error('Failed to send leave approved notification', [
                    'leave_id' => $leave->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Verificar se foi cancelada, remover registros de presença
        if ($leave->isDirty('status') && $leave->status === 'cancelled') {
            $this->removeAttendanceRecords($leave);
        }
        
        // Verificar se foi rejeitada, remover registros se existirem
        if ($leave->isDirty('status') && $leave->status === 'rejected') {
            $this->removeAttendanceRecords($leave);
            
            // Enviar notificação de rejeição
            try {
                $notificationService = new ImmediateNotificationService($leave->tenant_id);
                $notificationService->notifyLeaveRejected($leave);
            } catch (\Exception $e) {
                Log::error('Failed to send leave rejected notification', [
                    'leave_id' => $leave->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Quando a licença for deletada, remover registros de presença
     */
    public function deleted(Leave $leave)
    {
        $this->removeAttendanceRecords($leave);
    }

    /**
     * Criar registros de presença para todos os dias úteis da licença
     */
    private function createAttendanceRecords(Leave $leave)
    {
        try {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date);
            $currentDate = $startDate->copy();
            
            $attendanceStatus = $this->getAttendanceStatus($leave);
            
            Log::info("LeaveObserver: Criando registros de presença", [
                'leave_id' => $leave->id,
                'leave_number' => $leave->leave_number,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'status' => $attendanceStatus,
            ]);

            $recordsCreated = 0;

            while ($currentDate->lte($endDate)) {
                // Apenas dias úteis (segunda a sexta)
                if ($currentDate->isWeekday()) {
                    Attendance::updateOrCreate(
                        [
                            'tenant_id' => $leave->tenant_id,
                            'employee_id' => $leave->employee_id,
                            'date' => $currentDate->format('Y-m-d'),
                        ],
                        [
                            'status' => $attendanceStatus,
                            'check_in' => null, // Não tem entrada/saída
                            'check_out' => null,
                            'hours_worked' => 0,
                            'is_late' => false,
                            'late_minutes' => 0,
                            'overtime_hours' => 0,
                            'leave_id' => $leave->id,
                            'notes' => "Licença: {$leave->leave_number} - " . $this->getLeaveTypeLabel($leave->leave_type),
                        ]
                    );
                    $recordsCreated++;
                }
                
                $currentDate->addDay();
            }

            Log::info("LeaveObserver: Registros criados com sucesso", [
                'leave_id' => $leave->id,
                'records_created' => $recordsCreated,
            ]);

        } catch (\Exception $e) {
            Log::error("LeaveObserver: Erro ao criar registros de presença", [
                'leave_id' => $leave->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remover registros de presença relacionados à licença
     */
    private function removeAttendanceRecords(Leave $leave)
    {
        try {
            Log::info("LeaveObserver: Removendo registros de presença", [
                'leave_id' => $leave->id,
                'leave_number' => $leave->leave_number,
            ]);

            $deleted = Attendance::where('leave_id', $leave->id)->delete();

            Log::info("LeaveObserver: Registros removidos", [
                'leave_id' => $leave->id,
                'records_deleted' => $deleted,
            ]);

        } catch (\Exception $e) {
            Log::error("LeaveObserver: Erro ao remover registros de presença", [
                'leave_id' => $leave->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determinar o status de presença baseado no tipo de licença
     */
    private function getAttendanceStatus(Leave $leave): string
    {
        // Licença médica com atestado
        if ($leave->leave_type === 'sick' && $leave->has_medical_certificate) {
            return 'sick_leave'; // Licença médica justificada
        }
        
        // Licença médica sem atestado (ainda aprovada pelo gestor)
        if ($leave->leave_type === 'sick' && !$leave->has_medical_certificate) {
            return 'sick_leave'; // Licença médica
        }
        
        // Licença maternidade
        if ($leave->leave_type === 'maternity') {
            return 'maternity_leave';
        }
        
        // Licença paternidade
        if ($leave->leave_type === 'paternity') {
            return 'paternity_leave';
        }
        
        // Todas as outras (pessoal, luto, sem vencimento, outro)
        return 'on_leave'; // Em licença
    }

    /**
     * Obter label do tipo de licença
     */
    private function getLeaveTypeLabel(string $type): string
    {
        $labels = [
            'sick' => 'Doença',
            'personal' => 'Pessoal',
            'bereavement' => 'Luto',
            'maternity' => 'Maternidade',
            'paternity' => 'Paternidade',
            'unpaid' => 'Sem Vencimento',
            'other' => 'Outro',
        ];

        return $labels[$type] ?? ucfirst($type);
    }
}
