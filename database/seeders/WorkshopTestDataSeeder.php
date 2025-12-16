<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Service;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderPayment;
use App\Models\Invoicing\Product;

class WorkshopTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = auth()->user()?->activeTenantId() ?? 1;
        $userId = auth()->id() ?? 1;
        
        // Limpar dados existentes
        $this->command->info('🧹 Limpando dados existentes...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('workshop_work_order_payments')->truncate();
        DB::table('workshop_work_order_attachments')->truncate();
        DB::table('workshop_work_order_items')->truncate();
        DB::table('workshop_work_orders')->truncate();
        DB::table('workshop_vehicles')->truncate();
        DB::table('workshop_services')->truncate();
        DB::table('workshop_mechanics')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->command->info('🚗 Criando dados de teste para Workshop...');
        
        // 1. Criar Mecânicos
        $this->command->info('👨‍🔧 Criando mecânicos...');
        $mechanics = $this->createMechanics($tenantId);
        
        // 2. Criar Serviços
        $this->command->info('🔧 Criando serviços...');
        $services = $this->createServices($tenantId);
        
        // 3. Criar Veículos
        $this->command->info('🚙 Criando veículos...');
        $vehicles = $this->createVehicles($tenantId);
        
        // 4. Criar Ordens de Serviço
        $this->command->info('📋 Criando ordens de serviço...');
        $workOrders = $this->createWorkOrders($tenantId, $vehicles, $mechanics);
        
        // 5. Adicionar Itens às OS
        $this->command->info('🛠️ Adicionando itens às OS...');
        try {
            $this->addWorkOrderItems($workOrders, $services);
        } catch (\Exception $e) {
            $this->command->error('Erro ao adicionar itens: ' . $e->getMessage());
            $this->command->error($e->getTraceAsString());
            return;
        }
        
        // 6. Adicionar Pagamentos
        $this->command->info('💰 Adicionando pagamentos...');
        $this->addPayments($workOrders, $userId);
        
        $this->command->info('✅ Dados de teste criados com sucesso!');
        $this->command->info("📊 Resumo:");
        $this->command->info("   - {$mechanics->count()} Mecânicos");
        $this->command->info("   - {$services->count()} Serviços");
        $this->command->info("   - {$vehicles->count()} Veículos");
        $this->command->info("   - {$workOrders->count()} Ordens de Serviço");
    }
    
    private function createMechanics($tenantId)
    {
        $mechanicsData = [
            [
                'name' => 'João Manuel',
                'email' => 'joao.manuel@workshop.ao',
                'phone' => '+244 923 456 789',
                'specialties' => json_encode(['Motor', 'Transmissão', 'Diagnóstico Eletrónico']),
                'hourly_rate' => 2500.00,
                'is_active' => true,
            ],
            [
                'name' => 'Pedro Silva',
                'email' => 'pedro.silva@workshop.ao',
                'phone' => '+244 924 567 890',
                'specialties' => json_encode(['Suspensão', 'Travões', 'Direção']),
                'hourly_rate' => 2000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Carlos Alberto',
                'email' => 'carlos.alberto@workshop.ao',
                'phone' => '+244 925 678 901',
                'specialties' => json_encode(['Ar Condicionado', 'Sistema Elétrico', 'Iluminação']),
                'hourly_rate' => 1800.00,
                'is_active' => true,
            ],
            [
                'name' => 'António José',
                'email' => 'antonio.jose@workshop.ao',
                'phone' => '+244 926 789 012',
                'specialties' => json_encode(['Chaparia', 'Pintura', 'Estofos']),
                'hourly_rate' => 1500.00,
                'is_active' => true,
            ],
            [
                'name' => 'Miguel Santos',
                'email' => 'miguel.santos@workshop.ao',
                'phone' => '+244 927 890 123',
                'specialties' => json_encode(['Pneus', 'Alinhamento', 'Balanceamento']),
                'hourly_rate' => 1200.00,
                'is_active' => true,
            ],
        ];
        
        $mechanics = collect();
        foreach ($mechanicsData as $data) {
            $data['tenant_id'] = $tenantId;
            $mechanics->push(Mechanic::create($data));
        }
        
        return $mechanics;
    }
    
    private function createServices($tenantId)
    {
        $servicesData = [
            // Mecânica - Motor
            ['service_code' => 'SRV-001', 'name' => 'Mudança de Óleo e Filtro', 'category' => 'Mecânica', 'labor_cost' => 5000, 'estimated_hours' => 0.5],
            ['service_code' => 'SRV-002', 'name' => 'Revisão Completa Motor', 'category' => 'Mecânica', 'labor_cost' => 35000, 'estimated_hours' => 4],
            ['service_code' => 'SRV-003', 'name' => 'Troca de Correia Distribuição', 'category' => 'Mecânica', 'labor_cost' => 25000, 'estimated_hours' => 3],
            ['service_code' => 'SRV-004', 'name' => 'Limpeza de Bicos Injetores', 'category' => 'Reparação', 'labor_cost' => 15000, 'estimated_hours' => 2],
            
            // Mecânica - Travões
            ['service_code' => 'SRV-005', 'name' => 'Troca de Pastilhas Travão', 'category' => 'Reparação', 'labor_cost' => 8000, 'estimated_hours' => 1],
            ['service_code' => 'SRV-006', 'name' => 'Troca de Discos Travão', 'category' => 'Reparação', 'labor_cost' => 12000, 'estimated_hours' => 1.5],
            ['service_code' => 'SRV-007', 'name' => 'Sangria Sistema Travões', 'category' => 'Manutenção', 'labor_cost' => 6000, 'estimated_hours' => 0.5],
            
            // Mecânica - Suspensão / Pneus
            ['service_code' => 'SRV-008', 'name' => 'Troca de Amortecedores', 'category' => 'Reparação', 'labor_cost' => 18000, 'estimated_hours' => 2],
            ['service_code' => 'SRV-009', 'name' => 'Alinhamento Direção', 'category' => 'Pneus', 'labor_cost' => 5000, 'estimated_hours' => 0.5],
            ['service_code' => 'SRV-010', 'name' => 'Balanceamento Rodas', 'category' => 'Pneus', 'labor_cost' => 4000, 'estimated_hours' => 0.5],
            
            // Elétrica
            ['service_code' => 'SRV-011', 'name' => 'Diagnóstico Eletrónico', 'category' => 'Elétrica', 'labor_cost' => 10000, 'estimated_hours' => 1],
            ['service_code' => 'SRV-012', 'name' => 'Troca de Bateria', 'category' => 'Elétrica', 'labor_cost' => 3000, 'estimated_hours' => 0.25],
            ['service_code' => 'SRV-013', 'name' => 'Revisão Sistema Elétrico', 'category' => 'Elétrica', 'labor_cost' => 15000, 'estimated_hours' => 2],
            
            // Outros - Ar Condicionado
            ['service_code' => 'SRV-014', 'name' => 'Recarga Ar Condicionado', 'category' => 'Manutenção', 'labor_cost' => 8000, 'estimated_hours' => 1],
            ['service_code' => 'SRV-015', 'name' => 'Limpeza Sistema AC', 'category' => 'Manutenção', 'labor_cost' => 12000, 'estimated_hours' => 1.5],
            
            // Inspeção e Outros
            ['service_code' => 'SRV-016', 'name' => 'Inspeção Geral 30.000 Km', 'category' => 'Inspeção', 'labor_cost' => 20000, 'estimated_hours' => 2],
            ['service_code' => 'SRV-017', 'name' => 'Lavagem Completa + Polimento', 'category' => 'Pintura', 'labor_cost' => 7000, 'estimated_hours' => 2],
        ];
        
        $services = collect();
        foreach ($servicesData as $data) {
            $data['tenant_id'] = $tenantId;
            $data['is_active'] = true;
            $services->push(Service::create($data));
        }
        
        return $services;
    }
    
    private function createVehicles($tenantId)
    {
        $vehiclesData = [
            [
                'plate' => 'LD-25-84-AO',
                'vehicle_number' => 'VH-' . str_pad(1, 5, '0', STR_PAD_LEFT),
                'brand' => 'Toyota',
                'model' => 'Hilux',
                'year' => 2021,
                'color' => 'Branco',
                'vin' => 'JTMRFREV30D123456',
                'engine_number' => '1GR-FE-7890123',
                'fuel_type' => 'Diesel',
                'mileage' => 45000,
                'owner_name' => 'José Eduardo dos Santos',
                'owner_phone' => '+244 923 111 222',
                'owner_email' => 'jose.eduardo@email.ao',
                'insurance_expiry' => now()->addMonths(6),
                'inspection_expiry' => now()->addMonths(3),
            ],
            [
                'plate' => 'LD-30-45-AO',
                'vehicle_number' => 'VH-' . str_pad(2, 5, '0', STR_PAD_LEFT),
                'brand' => 'Mercedes-Benz',
                'model' => 'C-Class',
                'year' => 2020,
                'color' => 'Preto',
                'vin' => 'WDD2050071F234567',
                'engine_number' => 'M274-DE20-456789',
                'fuel_type' => 'Gasolina',
                'mileage' => 35000,
                'owner_name' => 'Maria Silva',
                'owner_phone' => '+244 924 222 333',
                'owner_email' => 'maria.silva@email.ao',
                'insurance_expiry' => now()->addMonths(8),
                'inspection_expiry' => now()->addMonths(4),
            ],
            [
                'plate' => 'LD-15-67-AO',
                'vehicle_number' => 'VH-' . str_pad(3, 5, '0', STR_PAD_LEFT),
                'brand' => 'Land Rover',
                'model' => 'Range Rover Sport',
                'year' => 2022,
                'color' => 'Cinza',
                'vin' => 'SALWA2FE8EA345678',
                'engine_number' => 'P400-MHEV-567890',
                'fuel_type' => 'Diesel',
                'mileage' => 18000,
                'owner_name' => 'Carlos Alberto',
                'owner_phone' => '+244 925 333 444',
                'owner_email' => 'carlos.alberto@email.ao',
                'insurance_expiry' => now()->addYear(),
                'inspection_expiry' => now()->addMonths(10),
            ],
            [
                'plate' => 'LD-40-89-AO',
                'vehicle_number' => 'VH-' . str_pad(4, 5, '0', STR_PAD_LEFT),
                'brand' => 'BMW',
                'model' => 'X5',
                'year' => 2019,
                'color' => 'Azul',
                'vin' => '5UXKR0C53K0456789',
                'engine_number' => 'B58B30-678901',
                'fuel_type' => 'Diesel',
                'mileage' => 62000,
                'owner_name' => 'Ana Paula',
                'owner_phone' => '+244 926 444 555',
                'owner_email' => 'ana.paula@email.ao',
                'insurance_expiry' => now()->addMonths(2),
                'inspection_expiry' => now()->addMonths(1),
            ],
            [
                'plate' => 'LD-55-12-AO',
                'vehicle_number' => 'VH-' . str_pad(5, 5, '0', STR_PAD_LEFT),
                'brand' => 'Volkswagen',
                'model' => 'Amarok',
                'year' => 2023,
                'color' => 'Vermelho',
                'vin' => 'WVW5ZZCD3N1567890',
                'engine_number' => 'DFCA-789012',
                'fuel_type' => 'Diesel',
                'mileage' => 8500,
                'owner_name' => 'Pedro Santos',
                'owner_phone' => '+244 927 555 666',
                'owner_email' => 'pedro.santos@email.ao',
                'insurance_expiry' => now()->addYear()->addMonths(2),
                'inspection_expiry' => now()->addYear(),
            ],
            [
                'plate' => 'LD-70-23-AO',
                'vehicle_number' => 'VH-' . str_pad(6, 5, '0', STR_PAD_LEFT),
                'brand' => 'Nissan',
                'model' => 'Patrol',
                'year' => 2020,
                'color' => 'Dourado',
                'vin' => 'JN1TBNT34U0678901',
                'engine_number' => 'VK56VD-890123',
                'fuel_type' => 'Gasolina',
                'mileage' => 54000,
                'owner_name' => 'Francisco Manuel',
                'owner_phone' => '+244 928 666 777',
                'owner_email' => 'francisco.manuel@email.ao',
                'insurance_expiry' => now()->addMonths(5),
                'inspection_expiry' => now()->addMonths(2),
            ],
        ];
        
        $vehicles = collect();
        foreach ($vehiclesData as $data) {
            $data['tenant_id'] = $tenantId;
            $vehicles->push(Vehicle::create($data));
        }
        
        return $vehicles;
    }
    
    private function createWorkOrders($tenantId, $vehicles, $mechanics)
    {
        $statuses = ['pending', 'in_progress', 'completed', 'delivered'];
        $priorities = ['low', 'normal', 'urgent'];
        
        $workOrders = collect();
        
        // Criar 15 ordens de serviço variadas
        for ($i = 1; $i <= 15; $i++) {
            $vehicle = $vehicles->random();
            $mechanic = $mechanics->random();
            $status = $statuses[array_rand($statuses)];
            $priority = $priorities[array_rand($priorities)];
            
            $receivedDate = now()->subDays(rand(1, 30));
            $scheduledDate = $receivedDate->copy()->addDays(rand(1, 3));
            
            $data = [
                'tenant_id' => $tenantId,
                'order_number' => 'OS-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'vehicle_id' => $vehicle->id,
                'mechanic_id' => null, // Nullable - mecânico será atribuído posteriormente
                'received_at' => $receivedDate,
                'scheduled_for' => $scheduledDate,
                'status' => $status,
                'priority' => $priority,
                'mileage_in' => $vehicle->mileage + rand(100, 1000),
                'problem_description' => $this->getRandomProblem(),
                'diagnosis' => $status !== 'pending' ? $this->getRandomDiagnosis() : null,
                'work_performed' => in_array($status, ['completed', 'delivered']) ? $this->getRandomWorkPerformed() : null,
                'warranty_days' => 90,
            ];
            
            if (in_array($status, ['completed', 'delivered'])) {
                $data['completed_at'] = $receivedDate->copy()->addDays(rand(1, 5));
            }
            
            if ($status === 'delivered') {
                $data['delivered_at'] = $data['completed_at']->copy()->addHours(rand(2, 48));
            }
            
            $workOrders->push(WorkOrder::create($data));
        }
        
        return $workOrders;
    }
    
    private function addWorkOrderItems($workOrders, $services)
    {
        // Desabilitar observers temporariamente
        WorkOrderItem::unsetEventDispatcher();
        WorkOrder::unsetEventDispatcher();
        
        $count = 0;
        foreach ($workOrders as $workOrder) {
            $count++;
            $this->command->info("   Processando OS {$count}/{$workOrders->count()}...");
            
            // Adicionar 2-3 serviços por OS
            $numServices = rand(2, 3);
            $selectedServices = $services->random($numServices);
            
            $laborTotal = 0;
            
            foreach ($selectedServices as $service) {
                WorkOrderItem::create([
                    'work_order_id' => $workOrder->id,
                    'type' => 'service',
                    'service_id' => $service->id,
                    'code' => $service->service_code,
                    'name' => $service->name,
                    'quantity' => 1,
                    'unit_price' => $service->labor_cost,
                    'subtotal' => $service->labor_cost,
                    'hours' => $service->estimated_hours,
                ]);
                
                $laborTotal += $service->labor_cost;
            }
            
            // Calcular manualmente
            $partsTotal = 0;
            $subtotal = $laborTotal + $partsTotal;
            $tax = $subtotal * 0.14; // 14% IVA
            $total = $subtotal + $tax;
            
            $workOrder->update([
                'labor_total' => $laborTotal,
                'parts_total' => $partsTotal,
                'tax' => $tax,
                'total' => $total,
            ]);
        }
    }
    
    private function addPayments($workOrders, $userId)
    {
        $paymentMethods = ['cash', 'transfer', 'card'];
        
        foreach ($workOrders as $workOrder) {
            // 70% das OS têm pagamento
            if (rand(1, 100) <= 70) {
                $totalPaid = 0;
                $numPayments = rand(1, 3); // 1-3 pagamentos parciais
                
                for ($i = 0; $i < $numPayments; $i++) {
                    // Pagar entre 20% e 50% do total restante
                    $remaining = $workOrder->total - $totalPaid;
                    if ($remaining <= 0) break;
                    
                    $paymentAmount = $i === $numPayments - 1 
                        ? $remaining // Último pagamento: paga tudo
                        : $remaining * (rand(20, 50) / 100);
                    
                    WorkOrderPayment::create([
                        'work_order_id' => $workOrder->id,
                        'user_id' => $userId,
                        'payment_date' => now()->subDays(rand(0, 15)),
                        'amount' => $paymentAmount,
                        'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                        'reference' => 'REF-' . strtoupper(substr(md5(rand()), 0, 10)),
                        'notes' => $i === 0 ? 'Pagamento inicial' : ($i === $numPayments - 1 ? 'Pagamento final' : 'Pagamento parcial'),
                    ]);
                    
                    $totalPaid += $paymentAmount;
                }
                
                // Atualizar valor pago na OS
                $workOrder->update(['paid_amount' => $totalPaid]);
            }
        }
    }
    
    private function getRandomProblem()
    {
        $problems = [
            'Veículo apresenta ruído estranho ao travar',
            'Motor fazendo barulho anormal e falhando',
            'Ar condicionado não está gelando',
            'Vibração excessiva no volante em alta velocidade',
            'Luz de advertência do motor acesa no painel',
            'Perda de potência durante aceleração',
            'Vazamento de óleo embaixo do veículo',
            'Dificuldade em dar partida pela manhã',
            'Suspensão fazendo barulho em lombadas',
            'Sistema de travagem com resposta fraca',
            'Faróis com baixa luminosidade',
            'Bateria descarregando rapidamente',
            'Pneus com desgaste irregular',
            'Transmissão com trocas bruscas',
            'Radiador aquecendo muito',
        ];
        
        return $problems[array_rand($problems)];
    }
    
    private function getRandomDiagnosis()
    {
        $diagnoses = [
            'Pastilhas de travão gastas, necessário substituição',
            'Correia de distribuição com desgaste avançado',
            'Gás do ar condicionado com baixa pressão, necessário recarga',
            'Rodas desbalanceadas e alinhamento fora do padrão',
            'Sensor de oxigênio com defeito, necessário substituição',
            'Filtro de ar sujo, sistema de admissão obstruído',
            'Junta do cárter com vazamento',
            'Bateria no fim da vida útil',
            'Buchas de suspensão desgastadas',
            'Sistema hidráulico de travões com ar',
            'Lâmpadas queimadas, necessário troca',
            'Alternador não está carregando corretamente',
            'Pneus com pressão incorreta e desgaste desigual',
            'Óleo de transmissão velho e filtro sujo',
            'Termostato travado, sistema de arrefecimento comprometido',
        ];
        
        return $diagnoses[array_rand($diagnoses)];
    }
    
    private function getRandomWorkPerformed()
    {
        $works = [
            'Substituição de pastilhas e discos de travão. Sangria do sistema hidráulico.',
            'Troca de correia de distribuição e tensores. Revisão completa do motor.',
            'Recarga completa do sistema de ar condicionado. Limpeza do evaporador.',
            'Alinhamento computorizado e balanceamento das 4 rodas.',
            'Substituição do sensor de oxigênio. Reset do sistema de injeção.',
            'Troca de filtro de ar e limpeza do corpo de borboleta.',
            'Substituição da junta do cárter e troca de óleo do motor.',
            'Instalação de bateria nova. Teste do sistema de carga.',
            'Troca de buchas de suspensão dianteira e traseira.',
            'Sangria completa do sistema de travões. Troca de fluido.',
            'Substituição de todas as lâmpadas queimadas.',
            'Troca do alternador. Verificação do sistema elétrico.',
            'Rodízio de pneus e calibração correta de pressão.',
            'Troca de óleo de transmissão e filtro.',
            'Substituição do termostato e fluido de arrefecimento.',
        ];
        
        return $works[array_rand($works)];
    }
}
