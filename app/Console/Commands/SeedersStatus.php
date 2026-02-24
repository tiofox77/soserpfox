<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedersStatus extends Command
{
    protected $signature = 'seeders:status {--run : Executar seeders pendentes} {--force : Forçar execução mesmo se já registado}';

    protected $description = 'Verificar e executar seeders pendentes para produção';

    /**
     * Lista ORDENADA de seeders obrigatórios.
     * Adicionar novos seeders aqui quando criados.
     * Formato: 'SeederClass' => 'Descrição curta'
     */
    protected array $requiredSeeders = [
        // ── Core (ordem importa) ─────────────────────────────
        'PermissionSeeder' => 'Permissions base + hotel/salon/workshop',
        'RoleSeeder' => 'Roles globais com filtros dinâmicos',
        'ModuleSeeder' => 'Módulos do sistema',
        'PlanSeeder' => 'Planos de subscrição',
        'SuperAdminSeeder' => 'Conta Super Admin',

        // ── Faturação ────────────────────────────────────────
        'InvoicingTaxesSeeder' => 'Impostos de faturação (IVA, etc)',
        'InvoicingSeriesSeeder' => 'Séries de documentos',
        'DefaultClientSeeder' => 'Cliente final por defeito',
        'TreasuryBanksSeeder' => 'Bancos de Angola',
        'CategorySeeder' => 'Categorias de produtos',
        'BrandSeeder' => 'Marcas de produtos',

        // ── Templates ────────────────────────────────────────
        'EmailTemplateSeeder' => 'Templates de email',
        'NewUserEmailTemplateSeeder' => 'Template email novo user',
        'DefaultNotificationTemplatesSeeder' => 'Templates notificações',
        'SmsTemplateSeeder' => 'Templates SMS',
        'SmsSettingSeeder' => 'Config SMS',

        // ── Contabilidade ────────────────────────────────────
        'AccountingSeeder' => 'Config contabilidade base',

        // ── RH ───────────────────────────────────────────────
        'HRSettingsSeeder' => 'Config RH',

        // ── Hotel ────────────────────────────────────────────
        'HotelModuleSeeder' => 'Dados iniciais hotel',

        // ── Módulos/Notificações ─────────────────────────────
        'NotificationsModuleSeeder' => 'Módulo notificações',

        // ── Contabilidade (sub-seeders) ─────────────────────
        'CostCenterSeeder' => 'Centros de custo padrão',
        'Accounting\AccountSeeder' => 'Plano de contas contabilístico',
        'Accounting\DocumentTypeSeeder' => 'Tipos de documento contabilístico',
        'Accounting\IntegrationMappingSeeder' => 'Mapeamentos integração contabilidade',
        'Accounting\JournalSeeder' => 'Diários contabilísticos',
        'Accounting\PeriodSeeder' => 'Períodos contabilísticos',
        'Accounting\TaxSeeder' => 'Impostos contabilísticos (IVA/IRT)',

        // ── Manutenção/Actualização ──────────────────────────
        'UpdatePermissionsSeeder' => 'Actualização permissões (incremental)',
        'CleanOldPermissionsSeeder' => 'Limpeza permissões obsoletas',
        'UpdateOldRolesSeeder' => 'Actualização roles antigos',
        'AddWarehousePermissionsSeeder' => 'Permissões armazéns',
        'PermissionsSeeder' => 'Permissões faturação detalhadas',
        'FinancialStatementMappingSeeder' => 'Mapeamento demonstrações financeiras',
    ];

    public function handle(): int
    {
        if (!Schema::hasTable('seeder_logs')) {
            $this->error('Tabela seeder_logs não existe. Execute: php artisan migrate');
            return 1;
        }

        $executed = DB::table('seeder_logs')->pluck('executed_at', 'seeder')->toArray();
        $pending = [];
        $done = [];

        foreach ($this->requiredSeeders as $seeder => $description) {
            if (isset($executed[$seeder])) {
                $done[$seeder] = ['desc' => $description, 'at' => $executed[$seeder]];
            } else {
                $pending[$seeder] = $description;
            }
        }

        // ── Mostrar status ───────────────────────────────────
        $this->newLine();
        $this->info("╔══════════════════════════════════════════════════╗");
        $this->info("║          SEEDERS STATUS - PRODUÇÃO               ║");
        $this->info("╚══════════════════════════════════════════════════╝");
        $this->newLine();

        // Executados
        $this->info("✅ EXECUTADOS (" . count($done) . "):");
        if (empty($done)) {
            $this->line("   Nenhum registado.");
        } else {
            foreach ($done as $seeder => $info) {
                $this->line("   <fg=green>✓</> {$seeder} — {$info['desc']} <fg=gray>[{$info['at']}]</>");
            }
        }

        $this->newLine();

        // Pendentes
        $this->warn("⏳ PENDENTES (" . count($pending) . "):");
        if (empty($pending)) {
            $this->line("   <fg=green>Tudo em dia!</>");
        } else {
            $i = 1;
            foreach ($pending as $seeder => $description) {
                $this->line("   <fg=yellow>{$i}.</> {$seeder} — {$description}");
                $i++;
            }
        }

        $this->newLine();
        $this->line("Total: " . count($this->requiredSeeders) . " seeders | " . count($done) . " executados | " . count($pending) . " pendentes");
        $this->newLine();

        // ── Executar se --run ────────────────────────────────
        if ($this->option('run') && !empty($pending)) {
            $force = $this->option('force');

            if (!$force && !$this->confirm('Executar ' . count($pending) . ' seeders pendentes?')) {
                $this->info('Cancelado.');
                return 0;
            }

            $batch = (int) DB::table('seeder_logs')->max('batch') + 1;
            $errors = [];

            foreach ($pending as $seeder => $description) {
                $class = "Database\\Seeders\\{$seeder}";

                // Tentar subpastas comuns
                if (!class_exists($class)) {
                    $subFolders = ['Accounting', 'HR'];
                    foreach ($subFolders as $folder) {
                        $altClass = "Database\\Seeders\\{$folder}\\{$seeder}";
                        if (class_exists($altClass)) {
                            $class = $altClass;
                            break;
                        }
                    }
                }

                if (!class_exists($class)) {
                    $this->warn("   ⚠ {$seeder} — classe não encontrada, ignorado");
                    continue;
                }

                try {
                    $this->line("   ▶ {$seeder}...");
                    app()->make($class)->run();

                    DB::table('seeder_logs')->insert([
                        'seeder' => $seeder,
                        'batch' => $batch,
                        'executed_at' => now(),
                    ]);

                    $this->info("   ✅ {$seeder} — OK");
                } catch (\Throwable $e) {
                    $errors[] = $seeder;
                    $this->error("   ❌ {$seeder} — " . $e->getMessage());
                }
            }

            $this->newLine();
            if (empty($errors)) {
                $this->info("🎉 Todos os seeders pendentes executados com sucesso! (batch {$batch})");
            } else {
                $this->warn("⚠ Concluído com " . count($errors) . " erro(s): " . implode(', ', $errors));
            }
        }

        // ── Detectar seeders no filesystem não registados ─────
        $unregistered = $this->detectUnregisteredSeeders();
        if (!empty($unregistered)) {
            $this->newLine();
            $this->warn("⚠ SEEDERS NÃO REGISTADOS (existem no filesystem mas não na lista):");
            foreach ($unregistered as $u) {
                $this->line("   <fg=red>?</> {$u}");
            }
            $this->line("   <fg=gray>Adicione-os a \$requiredSeeders em SeedersStatus.php</>");
        }

        // ── Gerar comando de deploy ──────────────────────────
        if (!empty($pending) && !$this->option('run')) {
            $this->newLine();
            $this->info("Para executar em produção:");
            $this->line("   php artisan seeders:status --run");
            $this->newLine();
            $this->info("Ou executar individualmente:");
            foreach ($pending as $seeder => $desc) {
                $this->line("   php artisan db:seed --class={$seeder}");
            }
        }

        return 0;
    }

    /**
     * Detectar seeders no filesystem que não estão na lista $requiredSeeders.
     * Ignora: DatabaseSeeder, seeders de teste, e subpastas do Accounting/HR já incluídas.
     */
    protected function detectUnregisteredSeeders(): array
    {
        $ignore = [
            'DatabaseSeeder',
            'MultiTenantTestSeeder', 'InvoicingTestSeeder', 'EventTestSeeder',
            'WorkshopTestDataSeeder', 'ProductSeeder', 'ClientSeeder', 'SupplierSeeder',
            'WarehouseSeeder', 'TaxRateSeeder', 'EquipmentCategorySeeder',
        ];

        $registered = array_keys($this->requiredSeeders);
        $allIgnored = array_merge($ignore, $registered);

        $unregistered = [];
        $seedersPath = database_path('seeders');

        foreach (glob("{$seedersPath}/*.php") as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!in_array($name, $allIgnored)) {
                $unregistered[] = $name;
            }
        }

        // Subpastas
        foreach (glob("{$seedersPath}/*/*.php") as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $folder = basename(dirname($file));
            $fullName = "{$folder}\\{$name}";
            if ($name === 'imported_accounts') continue;
            if (!in_array($name, $allIgnored) && !in_array($fullName, $allIgnored)) {
                $unregistered[] = $fullName;
            }
        }

        return $unregistered;
    }

    /**
     * Marcar seeders já executados (bootstrap inicial).
     * Útil para marcar tudo como executado num sistema existente.
     */
    public static function markAllAsExecuted(): void
    {
        $instance = new self();
        $batch = (int) DB::table('seeder_logs')->max('batch') + 1;

        foreach ($instance->requiredSeeders as $seeder => $description) {
            DB::table('seeder_logs')->insertOrIgnore([
                'seeder' => $seeder,
                'batch' => $batch,
                'executed_at' => now(),
            ]);
        }
    }
}
