<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
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
                'is_active' => false,
                'order' => 5,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Inventário',
                'slug' => 'inventario',
                'description' => 'Gestão de stock, armazéns e movimentos de inventário',
                // `package` não existe no Font Awesome 6 — é `box`. Dava
                // `fas fa-package`, uma classe sem desenho nenhum, e o módulo
                // aparecia sem ícone pelo mesmo motivo que as Notificações.
                'icon' => 'box',
                'is_core' => false,
                'is_active' => false,
                'order' => 6,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Compras',
                'slug' => 'compras',
                'description' => 'Gestão de fornecedores e requisições de compra',
                'icon' => 'shopping-cart',
                'is_core' => false,
                'is_active' => false,
                'order' => 7,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Projetos',
                'slug' => 'projetos',
                'description' => 'Gestão de projetos, tarefas e timesheet',
                'icon' => 'briefcase',
                'is_core' => false,
                'is_active' => false,
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
                'description' => 'Gestão de caixas, bancos, métodos de pagamento e transações. Acompanha sempre a Faturação (os pagamentos dependem da tesouraria).',
                'icon' => 'wallet',
                'is_core' => false,
                'is_active' => true,
                'order' => 12,
                'dependencies' => ['invoicing'],
            ],
            [
                'name' => 'Gestão de Restaurante',
                'slug' => 'restaurant',
                'description' => 'Sala, mesas, comandas, cozinha, receitas e checkout integrado com Facturação e Tesouraria.',
                'icon' => 'utensils',
                'is_core' => false,
                'is_active' => true,
                'order' => 13,
                'dependencies' => ['invoicing'],
            ],
            [
                // Faltava aqui. O módulo existia na base de produção, posto à
                // mão, e não nesta lista — numa instalação de raiz não era
                // criado, e como as rotas estão atrás de `tenant.module:
                // notifications`, ninguém lá chegava.
                'name' => 'Notificações',
                'slug' => 'notifications',
                'description' => 'Notificações por Email, SMS e WhatsApp, com modelos por evento.',
                // Nome de ícone SEM prefixo, como todos os outros: quem desenha
                // compõe `fas fa-{icon}`. Na base estava `ri-notification-3-line`,
                // do Remix Icons, que dava `fas fa-ri-notification-3-line` — uma
                // classe que não existe, e por isso um espaço em branco.
                'icon' => 'bell',
                'is_core' => false,
                'is_active' => true,
                'order' => 14,
                'dependencies' => null,
            ],
        ];

        foreach ($modules as $module) {
            // `updateOrCreate` nestes dois: são os que tiveram o registo
            // corrigido depois de já existir na base, e um `firstOrCreate`
            // deixava o valor errado lá para sempre.
            if (in_array($module['slug'], ['restaurant', 'notifications', 'inventario'], true)) {
                Module::updateOrCreate(['slug' => $module['slug']], $module);
            } else {
                Module::firstOrCreate(['slug' => $module['slug']], $module);
            }
        }
    }
}
