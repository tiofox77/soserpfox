<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Events\Event;
use App\Models\Events\Venue;
use App\Models\Client;

class EventTestSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1; // Ajuste conforme necessário
        
        // Verificar se já existe venue
        $venue = Venue::where('tenant_id', $tenantId)->first();
        if (!$venue) {
            $venue = Venue::create([
                'tenant_id' => $tenantId,
                'name' => 'Centro de Convenções Principal',
                'address' => 'Av. Principal, 1000',
                'city' => 'Luanda',
                'phone' => '923456789',
                'capacity' => 500,
                'is_active' => true,
            ]);
        }
        
        // Verificar se já existe cliente
        $client = Client::where('tenant_id', $tenantId)->first();
        
        // Criar eventos de teste
        $events = [
            [
                'name' => 'Conferência Anual 2025',
                'description' => 'Conferência anual da empresa',
                'type' => 'conferencia',
                'start_date' => now()->addDays(5)->setTime(9, 0),
                'end_date' => now()->addDays(5)->setTime(18, 0),
                'status' => 'confirmado',
                'phase' => 'pre_producao',
                'expected_attendees' => 200,
            ],
            [
                'name' => 'Show de Música ao Vivo',
                'description' => 'Show com banda local',
                'type' => 'show',
                'start_date' => now()->addDays(10)->setTime(20, 0),
                'end_date' => now()->addDays(10)->setTime(23, 0),
                'status' => 'orcamento',
                'phase' => 'planejamento',
                'expected_attendees' => 300,
            ],
            [
                'name' => 'Casamento Silva & Costa',
                'description' => 'Cerimônia de casamento',
                'type' => 'casamento',
                'start_date' => now()->addDays(15)->setTime(16, 0),
                'end_date' => now()->addDays(15)->setTime(22, 0),
                'status' => 'confirmado',
                'phase' => 'montagem',
                'expected_attendees' => 150,
            ],
            [
                'name' => 'Evento Corporativo',
                'description' => 'Evento de integração empresarial',
                'type' => 'corporativo',
                'start_date' => now()->addDays(3)->setTime(14, 0),
                'end_date' => now()->addDays(3)->setTime(18, 0),
                'status' => 'em_andamento',
                'phase' => 'operacao',
                'expected_attendees' => 100,
            ],
        ];
        
        foreach ($events as $eventData) {
            $event = Event::create([
                'tenant_id' => $tenantId,
                'client_id' => $client?->id,
                'venue_id' => $venue->id,
                'name' => $eventData['name'],
                'description' => $eventData['description'],
                'type' => $eventData['type'],
                'start_date' => $eventData['start_date'],
                'end_date' => $eventData['end_date'],
                'status' => $eventData['status'],
                'phase' => $eventData['phase'],
                'expected_attendees' => $eventData['expected_attendees'],
                'total_value' => rand(5000, 50000),
                'responsible_user_id' => 1,
                'checklist_progress' => rand(0, 100),
            ]);
            
            // Criar checklist inicial
            $event->createDefaultChecklistForPhase($eventData['phase']);
        }
        
        $this->command->info('✅ Eventos de teste criados com sucesso!');
    }
}
