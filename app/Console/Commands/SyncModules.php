<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;

class SyncModules extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'modules:sync {--fresh : Recriar todos os módulos}';

    /**
     * The console command description.
     */
    protected $description = 'Sincroniza os módulos do sistema (cria novos, atualiza existentes)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Sincronizando módulos...');
        $this->newLine();

        $modules = $this->getModules();
        $created = 0;
        $updated = 0;

        foreach ($modules as $moduleData) {
            $existing = Module::where('slug', $moduleData['slug'])->first();

            if ($existing) {
                if ($this->option('fresh')) {
                    $existing->update($moduleData);
                    $this->line("  ✏️  Atualizado: {$moduleData['name']}");
                    $updated++;
                } else {
                    $this->line("  ⏭️  Existente: {$moduleData['name']}");
                }
            } else {
                Module::create($moduleData);
                $this->line("  ✅ Criado: {$moduleData['name']}");
                $created++;
            }
        }

        $this->newLine();
        $this->info("📊 Resumo:");
        $this->line("   Criados: {$created}");
        $this->line("   Atualizados: {$updated}");
        $this->line("   Total: " . count($modules));
        $this->newLine();
        $this->info('✅ Sincronização concluída!');

        return Command::SUCCESS;
    }

    /**
     * Get all system modules
     */
    private function getModules(): array
    {
        return [
            [
                'name' => 'Invoicing',
                'slug' => 'invoicing',
                'description' => 'Gestão completa de faturação, clientes, produtos e pagamentos',
                'icon' => 'receipt',
                'is_core' => true,
                'is_active' => true,
                'order' => 1,
                'dependencies' => null,
            ],
            [
                'name' => 'Recursos Humanos',
                'slug' => 'rh',
                'description' => 'Gestão de colaboradores, assiduidade e processamento salarial',
                'icon' => 'users',
                'is_core' => false,
                'is_active' => true,
                'order' => 2,
                'dependencies' => null,
            ],
            [
                'name' => 'Contabilidade',
                'slug' => 'contabilidade',
                'description' => 'Plano de contas, lançamentos e demonstrações financeiras',
                'icon' => 'calculator',
                'is_core' => false,
                'is_active' => true,
                'order' => 3,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Gestão de Oficina',
                'slug' => 'oficina',
                'description' => 'Gestão de veículos, ordens de reparação e agendamentos',
                'icon' => 'wrench',
                'is_core' => false,
                'is_active' => true,
                'order' => 4,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'CRM',
                'slug' => 'crm',
                'description' => 'Customer Relationship Management - Gestão de leads e vendas',
                'icon' => 'user-check',
                'is_core' => false,
                'is_active' => true,
                'order' => 5,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Inventário',
                'slug' => 'inventario',
                'description' => 'Gestão de stock, armazéns e movimentos de inventário',
                'icon' => 'package',
                'is_core' => false,
                'is_active' => true,
                'order' => 6,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Compras',
                'slug' => 'compras',
                'description' => 'Gestão de fornecedores e requisições de compra',
                'icon' => 'shopping-cart',
                'is_core' => false,
                'is_active' => true,
                'order' => 7,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Projetos',
                'slug' => 'projetos',
                'description' => 'Gestão de projetos, tarefas e timesheet',
                'icon' => 'briefcase',
                'is_core' => false,
                'is_active' => true,
                'order' => 8,
                'dependencies' => null,
            ],
            [
                'name' => 'Gestão de Eventos',
                'slug' => 'eventos',
                'description' => 'Gestão de eventos, montagem de salas, equipamentos (som, telas, LEDs, streaming)',
                'icon' => 'calendar-alt',
                'is_core' => false,
                'is_active' => true,
                'order' => 9,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Gestão de Hotel',
                'slug' => 'hotel',
                'description' => 'Sistema completo de gestão hoteleira com booking online, reservas, check-in/out, housekeeping e analytics.',
                'icon' => 'hotel',
                'is_core' => false,
                'is_active' => true,
                'order' => 10,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Salão de Beleza',
                'slug' => 'salon',
                'description' => 'Sistema de gestão para salões de beleza, barbearias e spas com agendamento online, gestão de profissionais e clientes.',
                'icon' => 'spa',
                'is_core' => false,
                'is_active' => true,
                'order' => 11,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Tesouraria',
                'slug' => 'treasury',
                'description' => 'Gestão de caixa, bancos, contas a pagar e receber',
                'icon' => 'vault',
                'is_core' => false,
                'is_active' => true,
                'order' => 11,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Notificações',
                'slug' => 'notifications',
                'description' => 'Sistema de notificações e alertas automáticos',
                'icon' => 'bell',
                'is_core' => false,
                'is_active' => true,
                'order' => 12,
                'dependencies' => null,
            ],
        ];
    }
}
