<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class PatchDeploy extends Command
{
    protected $signature = 'patch:deploy {--force : Executar sem confirmação} {--dry-run : Simular sem executar}';

    protected $description = 'Aplicar patch de deploy — migrations, seeders, cache, permissões (v1.3.0)';

    private array $log = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->printBanner();

        if (!$force && !$dryRun) {
            if (!$this->confirm('⚠️  Deseja aplicar o patch completo agora?')) {
                $this->warn('Operação cancelada.');
                return 0;
            }
        }

        $mode = $dryRun ? '🔍 MODO DRY-RUN (simulação)' : '🚀 MODO PRODUÇÃO';
        $this->info($mode);
        $this->newLine();

        $steps = [
            ['Modo Manutenção (activar)', fn() => $this->stepMaintenance(true, $dryRun)],
            ['Migrations pendentes', fn() => $this->stepMigrations($dryRun)],
            ['Seeders pendentes', fn() => $this->stepSeeders($dryRun)],
            ['Permissões e Roles', fn() => $this->stepPermissions($dryRun)],
            ['Limpar cache completo', fn() => $this->stepClearCache($dryRun)],
            ['Optimizar aplicação', fn() => $this->stepOptimize($dryRun)],
            ['Storage link', fn() => $this->stepStorageLink($dryRun)],
            ['Modo Manutenção (desactivar)', fn() => $this->stepMaintenance(false, $dryRun)],
        ];

        $totalSteps = count($steps);
        $success = true;

        foreach ($steps as $i => [$name, $callback]) {
            $step = $i + 1;
            $this->info("━━━ [{$step}/{$totalSteps}] {$name} ━━━");

            try {
                $result = $callback();
                $this->addLog($name, $result['status'], $result['message']);

                if ($result['status'] === 'error') {
                    $this->error("   ❌ {$result['message']}");
                    $success = false;
                    if (!$force) {
                        $this->error('Pipeline interrompido. Use --force para continuar mesmo com erros.');
                        break;
                    }
                } else {
                    $this->info("   ✅ {$result['message']}");
                }
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                $this->addLog($name, 'error', $msg);
                $this->error("   ❌ Exceção: {$msg}");
                $success = false;
                if (!$force) break;
            }

            $this->newLine();
        }

        // Resumo final
        $this->printSummary($success, $dryRun);

        // Guardar log
        $this->saveLog($dryRun);

        return $success ? 0 : 1;
    }

    // ── STEPS ───────────────────────────────────────────

    private function stepMaintenance(bool $up, bool $dryRun): array
    {
        if ($dryRun) {
            $action = $up ? 'activar' : 'desactivar';
            return ['status' => 'skipped', 'message' => "Dry-run: iria {$action} modo manutenção"];
        }

        if ($up) {
            Artisan::call('down', ['--secret' => 'patch-deploy-2024']);
            return ['status' => 'ok', 'message' => 'Modo manutenção activado (secret: patch-deploy-2024)'];
        } else {
            Artisan::call('up');
            return ['status' => 'ok', 'message' => 'Modo manutenção desactivado'];
        }
    }

    private function stepMigrations(bool $dryRun): array
    {
        // Verificar pendentes
        Artisan::call('migrate:status');
        $output = Artisan::output();
        $pending = substr_count($output, 'Pending');

        if ($pending === 0) {
            return ['status' => 'ok', 'message' => 'Nenhuma migration pendente'];
        }

        if ($dryRun) {
            return ['status' => 'skipped', 'message' => "{$pending} migrations pendentes (não executadas em dry-run)"];
        }

        Artisan::call('migrate', ['--force' => true]);
        $result = Artisan::output();

        return ['status' => 'ok', 'message' => "{$pending} migrations executadas\n{$result}"];
    }

    private function stepSeeders(bool $dryRun): array
    {
        if (!Schema::hasTable('seeder_logs')) {
            if ($dryRun) {
                return ['status' => 'skipped', 'message' => 'Tabela seeder_logs não existe (será criada pelas migrations)'];
            }
        }

        // Usar a lógica do SeedersStatus command
        $requiredSeeders = $this->getRequiredSeeders();
        $executedSeeders = Schema::hasTable('seeder_logs')
            ? DB::table('seeder_logs')->pluck('seeder')->toArray()
            : [];

        $pending = [];
        foreach ($requiredSeeders as $class => $description) {
            if (!in_array($class, $executedSeeders)) {
                $pending[$class] = $description;
            }
        }

        if (empty($pending)) {
            return ['status' => 'ok', 'message' => 'Todos os ' . count($requiredSeeders) . ' seeders já executados'];
        }

        $count = count($pending);
        $names = implode(', ', array_keys($pending));

        if ($dryRun) {
            return ['status' => 'skipped', 'message' => "{$count} seeders pendentes: {$names}"];
        }

        // Executar pendentes
        $executed = 0;
        foreach ($pending as $class => $desc) {
            $this->line("      🌱 {$class} — {$desc}");

            // Resolver namespace
            $namespace = str_contains($class, '\\')
                ? "Database\\Seeders\\{$class}"
                : "Database\\Seeders\\{$class}";

            try {
                Artisan::call('db:seed', ['--class' => $namespace, '--force' => true]);

                // Registar no seeder_logs
                if (Schema::hasTable('seeder_logs')) {
                    DB::table('seeder_logs')->insertOrIgnore([
                        'seeder' => $class,
                        'batch' => DB::table('seeder_logs')->max('batch') + 1,
                        'executed_at' => now(),
                    ]);
                }

                $executed++;
                $this->line("         ✓ OK");
            } catch (\Exception $e) {
                $this->warn("         ⚠ Erro: " . $e->getMessage());
            }
        }

        return ['status' => 'ok', 'message' => "{$executed}/{$count} seeders executados"];
    }

    private function stepPermissions(bool $dryRun): array
    {
        if ($dryRun) {
            return ['status' => 'skipped', 'message' => 'Dry-run: iria executar users:fix-roles-permissions'];
        }

        try {
            Artisan::call('users:fix-roles-permissions');
            $output = trim(Artisan::output());
            return ['status' => 'ok', 'message' => "Roles e permissões corrigidos\n{$output}"];
        } catch (\Exception $e) {
            return ['status' => 'warning', 'message' => 'Comando users:fix-roles-permissions não disponível: ' . $e->getMessage()];
        }
    }

    private function stepClearCache(bool $dryRun): array
    {
        if ($dryRun) {
            return ['status' => 'skipped', 'message' => 'Dry-run: iria limpar cache completo'];
        }

        Artisan::call('optimize:clear');
        return ['status' => 'ok', 'message' => 'Cache, config, routes e views limpos'];
    }

    private function stepOptimize(bool $dryRun): array
    {
        if ($dryRun) {
            return ['status' => 'skipped', 'message' => 'Dry-run: iria optimizar aplicação'];
        }

        Artisan::call('optimize');
        return ['status' => 'ok', 'message' => 'Config, routes e views cacheados'];
    }

    private function stepStorageLink(bool $dryRun): array
    {
        $linkPath = public_path('storage');

        if (file_exists($linkPath) || is_link($linkPath)) {
            return ['status' => 'ok', 'message' => 'Storage link já existe'];
        }

        if ($dryRun) {
            return ['status' => 'skipped', 'message' => 'Dry-run: iria criar storage link'];
        }

        Artisan::call('storage:link');
        return ['status' => 'ok', 'message' => 'Storage link criado'];
    }

    // ── HELPERS ─────────────────────────────────────────

    private function getRequiredSeeders(): array
    {
        // Mesma lista do SeedersStatus command — manter sincronizado
        return [
            'PermissionSeeder' => 'Permissions base + hotel/salon/workshop',
            'RoleSeeder' => 'Roles globais com filtros dinâmicos',
            'ModuleSeeder' => 'Módulos do sistema',
            'PlanSeeder' => 'Planos de subscrição',
            'SuperAdminSeeder' => 'Conta Super Admin',
            'InvoicingTaxesSeeder' => 'Impostos de faturação (IVA, etc)',
            'InvoicingSeriesSeeder' => 'Séries de documentos',
            'DefaultClientSeeder' => 'Cliente final por defeito',
            'TreasuryBanksSeeder' => 'Bancos de Angola',
            'CategorySeeder' => 'Categorias de produtos',
            'BrandSeeder' => 'Marcas de produtos',
            'EmailTemplateSeeder' => 'Templates de email',
            'NewUserEmailTemplateSeeder' => 'Template email novo user',
            'DefaultNotificationTemplatesSeeder' => 'Templates notificações',
            'SmsTemplateSeeder' => 'Templates SMS',
            'SmsSettingSeeder' => 'Config SMS',
            'AccountingSeeder' => 'Config contabilidade base',
            'HRSettingsSeeder' => 'Config RH',
            'HotelModuleSeeder' => 'Dados iniciais hotel',
            'NotificationsModuleSeeder' => 'Módulo notificações',
            'CostCenterSeeder' => 'Centros de custo padrão',
            'Accounting\\AccountSeeder' => 'Plano de contas contabilístico',
            'Accounting\\DocumentTypeSeeder' => 'Tipos de documento contabilístico',
            'Accounting\\IntegrationMappingSeeder' => 'Mapeamentos integração contabilidade',
            'Accounting\\JournalSeeder' => 'Diários contabilísticos',
            'Accounting\\PeriodSeeder' => 'Períodos contabilísticos',
            'Accounting\\TaxSeeder' => 'Impostos contabilísticos (IVA/IRT)',
            'UpdatePermissionsSeeder' => 'Actualização permissões (incremental)',
            'CleanOldPermissionsSeeder' => 'Limpeza permissões obsoletas',
            'UpdateOldRolesSeeder' => 'Actualização roles antigos',
            'AddWarehousePermissionsSeeder' => 'Permissões armazéns',
            'PermissionsSeeder' => 'Permissões faturação detalhadas',
            'FinancialStatementMappingSeeder' => 'Mapeamento demonstrações financeiras',
        ];
    }

    private function addLog(string $step, string $status, string $message): void
    {
        $this->log[] = [
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    private function saveLog(bool $dryRun): void
    {
        $suffix = $dryRun ? '_dryrun' : '';
        $filename = 'patch_deploy_' . now()->format('Y-m-d_His') . $suffix . '.json';
        $path = storage_path("logs/{$filename}");

        File::put($path, json_encode([
            'version' => '1.3.0',
            'date' => now()->toDateTimeString(),
            'dry_run' => $dryRun,
            'steps' => $this->log,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("📋 Log guardado em: storage/logs/{$filename}");
    }

    private function printBanner(): void
    {
        $this->newLine();
        $this->line('╔════════════════════════════════════════════════════╗');
        $this->line('║           SOSERP — DEPLOY PATCH v1.3.0            ║');
        $this->line('║                  ' . now()->format('d/m/Y H:i') . '                   ║');
        $this->line('╠════════════════════════════════════════════════════╣');
        $this->line('║  1. Modo manutenção                               ║');
        $this->line('║  2. Migrations pendentes                          ║');
        $this->line('║  3. Seeders pendentes                             ║');
        $this->line('║  4. Fix roles & permissões                        ║');
        $this->line('║  5. Limpar cache                                  ║');
        $this->line('║  6. Optimizar aplicação                           ║');
        $this->line('║  7. Storage link                                  ║');
        $this->line('║  8. Desactivar manutenção                         ║');
        $this->line('╚════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function printSummary(bool $success, bool $dryRun): void
    {
        $this->newLine();
        $this->line('╔════════════════════════════════════════════════════╗');

        if ($dryRun) {
            $this->line('║        🔍 DRY-RUN COMPLETO (nada alterado)        ║');
        } elseif ($success) {
            $this->line('║          ✅ PATCH APLICADO COM SUCESSO!             ║');
        } else {
            $this->line('║          ❌ PATCH TEVE ERROS — VER LOG              ║');
        }

        $this->line('╚════════════════════════════════════════════════════╝');

        $ok = count(array_filter($this->log, fn($l) => $l['status'] === 'ok'));
        $skip = count(array_filter($this->log, fn($l) => $l['status'] === 'skipped'));
        $err = count(array_filter($this->log, fn($l) => $l['status'] === 'error'));

        $this->info("  ✅ {$ok} passos OK | ⏭ {$skip} ignorados | ❌ {$err} erros");
        $this->newLine();
    }
}
