<?php

namespace App\Http\Controllers\Examples;

use App\Http\Controllers\Controller;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EXEMPLOS DE USO DAS NOTIFICAÇÕES IMEDIATAS
 * 
 * Este controller contém exemplos práticos de como usar
 * as notificações em seus controllers reais.
 */
class NotificationExampleController extends Controller
{
    /**
     * EXEMPLO 1: Criar evento (automático via Observer)
     * 
     * O Observer EventObserver já envia notificação automaticamente
     * quando Event::create() é chamado
     */
    public function createEvent(Request $request)
    {
        // Validação
        $validated = $request->validate([
            'name' => 'required|string',
            'location' => 'required|string',
            'start_date' => 'required|date',
            'organizer_name' => 'required|string',
        ]);
        
        // Criar evento
        $event = DB::table('events')->insertGetId([
            'tenant_id' => auth()->user()->activeTenant()->id,
            'name' => $validated['name'],
            'location' => $validated['location'],
            'start_date' => $validated['start_date'],
            'organizer_name' => $validated['organizer_name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // ✅ Observer envia notificação automaticamente!
        // Não precisa chamar nada manualmente
        
        return redirect()->back()->with('success', 'Evento criado! Notificações enviadas.');
    }
    
    /**
     * EXEMPLO 2: Designar técnico a um evento (manual)
     * 
     * Como é uma tabela pivot, precisamos chamar manualmente
     */
    public function assignTechnician(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'technician_id' => 'required|exists:users,id',
        ]);
        
        // Buscar dados
        $event = DB::table('events')->where('id', $validated['event_id'])->first();
        $technician = DB::table('users')->where('id', $validated['technician_id'])->first();
        
        // Criar vínculo
        DB::table('event_technicians')->insert([
            'event_id' => $event->id,
            'user_id' => $technician->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // ✅ Enviar notificação manualmente
        NotificationHelper::notifyTechnicianAssigned(
            (object)$event,
            (object)$technician
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Técnico designado! Notificação enviada.'
        ]);
    }
    
    /**
     * EXEMPLO 3: Cancelar evento (automático via Observer)
     * 
     * O Observer detecta mudança de status para 'cancelled'
     */
    public function cancelEvent(Request $request, $eventId)
    {
        $event = DB::table('events')->where('id', $eventId)->first();
        
        if (!$event) {
            return redirect()->back()->with('error', 'Evento não encontrado');
        }
        
        // Atualizar status
        DB::table('events')
            ->where('id', $eventId)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
        
        // ✅ Observer detecta mudança e envia notificação automaticamente!
        
        return redirect()->back()->with('success', 'Evento cancelado! Técnicos notificados.');
    }
    
    /**
     * EXEMPLO 4: Atribuir tarefa (manual)
     */
    public function assignTask(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'assigned_to' => 'required|exists:users,id',
            'due_date' => 'required|date',
            'priority' => 'required|in:low,medium,high',
        ]);
        
        // Criar tarefa
        $taskId = DB::table('tasks')->insertGetId([
            'tenant_id' => auth()->user()->activeTenant()->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'assigned_to' => $validated['assigned_to'],
            'due_date' => $validated['due_date'],
            'priority' => $validated['priority'],
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Buscar dados
        $task = DB::table('tasks')->where('id', $taskId)->first();
        $assignedUser = DB::table('users')->where('id', $validated['assigned_to'])->first();
        
        // ✅ Enviar notificação
        NotificationHelper::notifyTaskAssigned(
            (object)$task,
            (object)$assignedUser
        );
        
        return redirect()->back()->with('success', 'Tarefa atribuída! Usuário notificado.');
    }
    
    /**
     * EXEMPLO 5: Agendar reunião (manual)
     */
    public function scheduleMeeting(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string',
            'location' => 'required|string',
            'scheduled_at' => 'required|date',
            'duration' => 'required|integer|min:15',
            'participant_ids' => 'required|array',
            'participant_ids.*' => 'exists:users,id',
        ]);
        
        // Criar reunião
        $meetingId = DB::table('meetings')->insertGetId([
            'tenant_id' => auth()->user()->activeTenant()->id,
            'subject' => $validated['subject'],
            'location' => $validated['location'],
            'scheduled_at' => $validated['scheduled_at'],
            'duration' => $validated['duration'],
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Vincular participantes
        foreach ($validated['participant_ids'] as $userId) {
            DB::table('meeting_participants')->insert([
                'meeting_id' => $meetingId,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        }
        
        // Buscar dados
        $meeting = DB::table('meetings')->where('id', $meetingId)->first();
        $participants = DB::table('users')
            ->whereIn('id', $validated['participant_ids'])
            ->get()
            ->toArray();
        
        // ✅ Enviar notificação para todos os participantes
        NotificationHelper::notifyMeetingScheduled(
            (object)$meeting,
            array_map(fn($p) => (object)$p, $participants)
        );
        
        return redirect()->back()->with('success', 'Reunião agendada! Participantes notificados.');
    }
    
    /**
     * EXEMPLO 6: Uso direto do Service (avançado)
     */
    public function customNotification(Request $request)
    {
        // Para casos onde você precisa mais controle
        $notificationService = app(\App\Services\ImmediateNotificationService::class);
        
        $event = DB::table('events_events')->where('id', $request->event_id)->first();
        
        // Buscar TODOS os técnicos (já que events_technicians tem phone, email, name)
        $technicians = DB::table('events_technicians')
            ->where('tenant_id', $event->tenant_id)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->select('id', 'name', 'email', 'phone', 'user_id')
            ->get()
            ->toArray();
        
        // Chamada direta ao serviço
        $notificationService->notifyEventCreated(
            (object)$event,
            array_map(fn($t) => (object)$t, $technicians)
        );
        
        return response()->json(['success' => true]);
    }
    
    /**
     * EXEMPLO 7: Teste manual de notificação
     */
    public function testNotification(Request $request)
    {
        // Para testar sem criar dados reais
        $fakeEvent = (object)[
            'id' => 999,
            'name' => 'Evento de Teste',
            'location' => 'Sala Virtual',
            'start_date' => now()->addDay(),
            'organizer_name' => 'Sistema',
        ];
        
        $fakeTechnician = (object)[
            'id' => auth()->id(),
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'phone' => '939729902', // Seu número para teste
        ];
        
        NotificationHelper::notifyTechnicianAssigned($fakeEvent, $fakeTechnician);
        
        return response()->json([
            'success' => true,
            'message' => 'Notificação de teste enviada para ' . $fakeTechnician->phone
        ]);
    }
}
