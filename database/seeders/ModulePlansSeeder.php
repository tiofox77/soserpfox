<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Seeder para planos específicos por módulo.
 * Cada plano dá acesso ao módulo de Faturação (core) + 1 módulo vertical.
 *
 * Preços competitivos para o mercado angolano (vs Vendus, PHC, etc).
 * Anual ≈ 2 meses grátis (-17%).
 */
class ModulePlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // ============================================================
            // 📊 PACOTE VENDAS — Faturação pura
            // ============================================================
            [
                'name' => '📊 Pacote Vendas',
                'slug' => 'pacote-vendas',
                'description' => 'Solução completa de faturação e ponto de venda (POS). Ideal para lojas, restaurantes e comércio em geral.',
                'price_monthly' => 5900,
                'price_quarterly' => 16800,
                'price_semiannual' => 31900,
                'price_yearly' => 59000,
                'max_users' => 5,
                'max_companies' => 1,
                'max_storage_mb' => 2000,
                'features' => [
                    'Faturação completa (FT, FR, NC, Proforma)',
                    'POS Offline — vendas mesmo sem internet',
                    'Gestão de clientes e produtos',
                    'Integração AGT (SAFT, hash, séries fiscais)',
                    'Relatórios de vendas',
                    'App PWA para tablet/telemóvel',
                    'Até 5 utilizadores',
                    '2GB de armazenamento',
                ],
                'included_modules' => ['invoicing'],
                'modules_slugs' => ['invoicing'],
                'is_active' => true,
                'is_featured' => true,
                'trial_days' => 14,
                'order' => 10,
            ],

            // ============================================================
            // 👥 PACOTE RH — Recursos Humanos
            // ============================================================
            [
                'name' => '👥 Pacote RH',
                'slug' => 'pacote-rh',
                'description' => 'Gestão completa de Recursos Humanos: colaboradores, assiduidade, processamento salarial e ficha do trabalhador.',
                'price_monthly' => 9900,
                'price_quarterly' => 28200,
                'price_semiannual' => 53400,
                'price_yearly' => 99000,
                'max_users' => 8,
                'max_companies' => 1,
                'max_storage_mb' => 3000,
                'features' => [
                    'Gestão de colaboradores',
                    'Controlo de assiduidade e férias',
                    'Processamento salarial',
                    'Ficha do trabalhador (PDF)',
                    'Recibo de vencimento mensal',
                    'INSS e IRT automáticos',
                    'Faturação básica incluída',
                    'Até 8 utilizadores',
                    '3GB de armazenamento',
                ],
                'included_modules' => ['invoicing', 'rh'],
                'modules_slugs' => ['invoicing', 'rh'],
                'is_active' => true,
                'is_featured' => false,
                'trial_days' => 14,
                'order' => 11,
            ],

            // ============================================================
            // 🏨 PACOTE HOTEL — Gestão hoteleira
            // ============================================================
            [
                'name' => '🏨 Pacote Hotel',
                'slug' => 'pacote-hotel',
                'description' => 'Sistema completo para hotéis, pousadas e residenciais: booking online, reservas, check-in/out, housekeeping e analytics.',
                'price_monthly' => 19900,
                'price_quarterly' => 56700,
                'price_semiannual' => 107400,
                'price_yearly' => 199000,
                'max_users' => 10,
                'max_companies' => 1,
                'max_storage_mb' => 10000,
                'features' => [
                    'Booking engine online (motor de reservas)',
                    'Gestão de quartos e tarifas',
                    'Check-in / Check-out',
                    'Housekeeping e governança',
                    'Channel Manager (Booking.com, Airbnb)',
                    'Faturação integrada (FT, FR)',
                    'Analytics e ocupação',
                    'Até 10 utilizadores',
                    '10GB de armazenamento',
                ],
                'included_modules' => ['invoicing', 'hotel'],
                'modules_slugs' => ['invoicing', 'hotel'],
                'is_active' => true,
                'is_featured' => true,
                'trial_days' => 30,
                'order' => 12,
            ],

            // ============================================================
            // 💇 PACOTE SALÃO — Beleza, Barbearia, Spa
            // ============================================================
            [
                'name' => '💇 Pacote Salão de Beleza',
                'slug' => 'pacote-salao',
                'description' => 'Gestão completa para salões de beleza, barbearias e spas: agendamento online, profissionais, comissões e fidelização.',
                'price_monthly' => 7900,
                'price_quarterly' => 22500,
                'price_semiannual' => 42600,
                'price_yearly' => 79000,
                'max_users' => 6,
                'max_companies' => 1,
                'max_storage_mb' => 2000,
                'features' => [
                    'Agendamento online (clientes marcam pelo telemóvel)',
                    'Gestão de profissionais e agenda',
                    'Cálculo automático de comissões',
                    'Catálogo de serviços e preços',
                    'Histórico de clientes e fidelização',
                    'Faturação integrada',
                    'POS para receção',
                    'Até 6 utilizadores',
                    '2GB de armazenamento',
                ],
                'included_modules' => ['invoicing', 'salon'],
                'modules_slugs' => ['invoicing', 'salon'],
                'is_active' => true,
                'is_featured' => false,
                'trial_days' => 14,
                'order' => 13,
            ],

            // ============================================================
            // 🔧 PACOTE OFICINA — Auto e Mecânica
            // ============================================================
            [
                'name' => '🔧 Pacote Oficina',
                'slug' => 'pacote-oficina',
                'description' => 'Gestão completa para oficinas auto e mecânicas: veículos, ordens de reparação, peças, mão-de-obra e orçamentos.',
                'price_monthly' => 12900,
                'price_quarterly' => 36800,
                'price_semiannual' => 69600,
                'price_yearly' => 129000,
                'max_users' => 8,
                'max_companies' => 1,
                'max_storage_mb' => 5000,
                'features' => [
                    'Gestão de veículos e clientes',
                    'Ordens de reparação (OR)',
                    'Orçamentos digitais',
                    'Gestão de mecânicos e mão-de-obra',
                    'Catálogo de peças e stock',
                    'Histórico completo por veículo',
                    'Faturação integrada (FT, FR)',
                    'Até 8 utilizadores',
                    '5GB de armazenamento',
                ],
                'included_modules' => ['invoicing', 'oficina'],
                'modules_slugs' => ['invoicing', 'oficina'],
                'is_active' => true,
                'is_featured' => false,
                'trial_days' => 14,
                'order' => 14,
            ],

            // ============================================================
            // 🍽️ PACOTE RESTAURANTE — Sala, cozinha e faturação
            // ============================================================
            [
                'name' => '🍽️ Pacote Restaurante',
                'slug' => 'pacote-restaurante',
                'description' => 'Gestão completa para restaurantes, bares e pastelarias: sala, mesas, comandas, cozinha/KDS, reservas, receitas, stock e faturação AGT.',
                'price_monthly' => 14900,
                'price_quarterly' => 42500,
                'price_semiannual' => 80500,
                'price_yearly' => 149000,
                'max_users' => 10,
                'max_companies' => 1,
                'max_storage_mb' => 5000,
                'features' => [
                    'Mapa de sala, zonas e mesas',
                    'Comandas digitais e divisão de conta',
                    'Cozinha/KDS com tickets e tempos',
                    'Reservas e controlo de ocupação',
                    'Fichas técnicas, receitas e ingredientes',
                    'Stock e desperdícios auditados',
                    'Pagamentos simples e mistos pela Tesouraria',
                    'Faturação AGT integrada (FR e FT)',
                    'Relatórios de vendas, ticket médio e produtos',
                    'Até 10 utilizadores e 5GB de armazenamento',
                ],
                'included_modules' => ['restaurant', 'invoicing'],
                'modules_slugs' => ['restaurant', 'invoicing'],
                'is_active' => true,
                'is_featured' => true,
                'trial_days' => 14,
                'order' => 15,
            ],
        ];

        foreach ($plans as $data) {
            $moduleSlugs = $data['modules_slugs'];
            unset($data['modules_slugs']);

            // A Tesouraria acompanha sempre a Faturação (métodos de pagamento dependem dela)
            if (in_array('invoicing', $moduleSlugs) && !in_array('treasury', $moduleSlugs)) {
                $moduleSlugs[] = 'treasury';
            }
            if (isset($data['included_modules']) && is_array($data['included_modules'])
                && in_array('invoicing', $data['included_modules'])
                && !in_array('treasury', $data['included_modules'])) {
                $data['included_modules'][] = 'treasury';
            }

            $plan = Plan::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            // Sincronizar módulos via tabela pivot plan_module
            $moduleIds = Module::whereIn('slug', $moduleSlugs)->pluck('id')->toArray();
            $plan->modules()->sync($moduleIds);

            $this->command->info("✓ {$plan->name} — " . number_format($plan->price_monthly, 0, ',', '.') . " Kz/mês — módulos: " . implode(', ', $moduleSlugs));
        }

        $this->command->newLine();
        $this->command->info('✅ 6 pacotes modulares criados/atualizados.');
    }
}
