<?php

namespace App\Observers;

use App\Models\Events\Event;
use App\Services\ImmediateNotificationService;
use Illuminate\Support\Facades\DB;

class EventObserver
{
    // Não injetar no construtor, criar quando necessário com tenant_id correto
    
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        try {
            // Buscar TODOS os técnicos ativos do sistema
            $technicians = $this->getAllActiveTechnicians($event->tenant_id);
            
            // Criar serviço com tenant_id correto
            $notificationService = new ImmediateNotificationService($event->tenant_id);
            
            // Enviar notificação de evento criado para todos
            $notificationService->notifyEventCreated($event, $technicians);
            
            \Log::info('EventObserver: Notification triggered', [
                'event_id' => $event->id,
                'event_name' => $event->name,
                'tenant_id' => $event->tenant_id,
                'technicians_count' => count($technicians)
            ]);
        } catch (\Exception $e) {
            \Log::error('EventObserver: Failed to send notification', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        try {
            // Verificar se status mudou para cancelado
            if ($event->isDirty('status') && $event->status === 'cancelled') {
                $technicians = $this->getEventTechnicians($event);
                
                // Criar serviço com tenant_id correto
                $notificationService = new ImmediateNotificationService($event->tenant_id);
                
                $notificationService->notifyEventCancelled($event, $technicians);
                
                \Log::info('EventObserver: Cancellation notification triggered', [
                    'event_id' => $event->id,
                    'tenant_id' => $event->tenant_id,
                    'technicians_count' => count($technicians)
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('EventObserver: Failed to send cancellation notification', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Buscar TODOS os técnicos ativos do sistema
     */
    protected function getAllActiveTechnicians($tenantId): array
    {
        try {
            // Buscar todos os técnicos da tabela events_technicians (já tem phone)
            $technicians = DB::table('events_technicians')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->select('id', 'name', 'email', 'phone', 'user_id')
                ->get()
                ->toArray();
            
            return array_map(function($tech) {
                return (object)$tech;
            }, $technicians);
        } catch (\Exception $e) {
            \Log::error('Error fetching all technicians', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Buscar técnicos vinculados ao evento (para cancelamento)
     * Se não houver técnicos vinculados, retorna todos os técnicos do sistema
     */
    protected function getEventTechnicians(Event $event): array
    {
        try {
            // Buscar IDs dos técnicos vinculados ao evento via events_event_staff
            $staffIds = DB::table('events_event_staff')
                ->where('event_id', $event->id)
                ->pluck('user_id')
                ->toArray();
            
            // Se houver técnicos vinculados, buscar seus dados
            if (!empty($staffIds)) {
                $technicians = DB::table('events_technicians')
                    ->whereIn('user_id', $staffIds)
                    ->where('tenant_id', $event->tenant_id)
                    ->whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->select('id', 'name', 'email', 'phone', 'user_id')
                    ->get()
                    ->toArray();
                
                if (!empty($technicians)) {
                    return array_map(function($tech) {
                        return (object)$tech;
                    }, $technicians);
                }
            }
            
            // Se não houver técnicos vinculados, retorna todos os técnicos do sistema
            return $this->getAllActiveTechnicians($event->tenant_id);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching event technicians', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
