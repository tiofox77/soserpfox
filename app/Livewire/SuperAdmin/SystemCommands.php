<?php

namespace App\Livewire\SuperAdmin;

use App\Models\{Plan, Module, Tenant};
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.superadmin')]
#[Title('Comandos do Sistema')]
class SystemCommands extends Component
{
    public $output = '';
    public $isRunning = false;
    public $selectedCommand = null;
    public $commandParams = [];
    public $executionHistory = [];
    
    // Parâmetros dinâmicos
    public $planId = null;
    public $moduleSlug = null;
    public $tenantId = null;
    
    // Seeders
    public $selectedSeeder = null;
    public $availableSeeders = [];
    public $seederCategories = [];
    public $selectedSeederCategory = 'all';
    public $seederLogs = [];
    public $seederStats = ['total' => 0, 'executed' => 0, 'pending' => 0];

    public function mount()
    {
        $this->loadExecutionHistory();
        $this->loadSeederLogs();
        $this->loadAvailableSeeders();
    }
    
    /**
     * Carregar logs de execucao de seeders da tabela seeder_logs
     */
    private function loadSeederLogs()
    {
        $this->seederLogs = [];
        if (Schema::hasTable('seeder_logs')) {
            $this->seederLogs = DB::table('seeder_logs')
                ->pluck('executed_at', 'seeder')
                ->toArray();
        }
    }
    
    /**
     * Carregar seeders disponíveis (incluindo subpastas)
     */
    /**
     * Seeders que NÃO devem aparecer na página: dados de teste/demo e ficheiros
     * que não são realmente seeders. Mantém a lista limpa e só com o necessário.
     */
    private const EXCLUDED_SEEDERS = [
        'EventTestSeeder',
        'InvoicingTestSeeder',
        'MultiTenantTestSeeder',
        'WorkshopTestDataSeeder',
        'imported_accounts', // ficheiro de dados, não é um Seeder
    ];

    private function isExcludedSeeder(string $className): bool
    {
        return in_array($className, self::EXCLUDED_SEEDERS, true)
            || str_contains($className, 'Test');
    }

    private function loadAvailableSeeders()
    {
        $seederPath = database_path('seeders');
        $seeders = [];
        $categories = ['all' => 'Todos'];

        if (File::isDirectory($seederPath)) {
            // Root seeders
            foreach (File::files($seederPath) as $file) {
                $filename = $file->getFilename();
                if (str_ends_with($filename, '.php') && $filename !== 'DatabaseSeeder.php') {
                    $className = str_replace('.php', '', $filename);
                    if ($this->isExcludedSeeder($className)) {
                        continue;
                    }
                    $seeders[] = [
                        'class' => $className,
                        'namespace' => "Database\\Seeders\\{$className}",
                        'name' => $this->formatSeederName($className),
                        'category' => 'Geral',
                        'path' => $file->getPathname(),
                    ];
                }
            }
            $categories['Geral'] = 'Geral';

            // Subdirectory seeders
            foreach (File::directories($seederPath) as $dir) {
                $dirName = basename($dir);
                $hasSeeder = false;

                foreach (File::files($dir) as $file) {
                    $filename = $file->getFilename();
                    if (str_ends_with($filename, '.php')) {
                        $className = str_replace('.php', '', $filename);
                        if ($this->isExcludedSeeder($className)) {
                            continue;
                        }
                        $hasSeeder = true;
                        $seeders[] = [
                            'class' => $className,
                            'namespace' => "Database\\Seeders\\{$dirName}\\{$className}",
                            'name' => $this->formatSeederName($className),
                            'category' => $dirName,
                            'path' => $file->getPathname(),
                        ];
                    }
                }
                if ($hasSeeder) {
                    $categories[$dirName] = $dirName;
                }
            }
        }
        
        // Ordenar por categoria + nome
        usort($seeders, function($a, $b) {
            $catCmp = strcmp($a['category'], $b['category']);
            return $catCmp !== 0 ? $catCmp : strcmp($a['name'], $b['name']);
        });
        
        // Cross-reference com seeder_logs para status
        foreach ($seeders as &$seeder) {
            $key = $seeder['class'];
            // Verificar com prefixo de subpasta (ex: Accounting\AccountSeeder)
            if ($seeder['category'] !== 'Geral') {
                $keyWithFolder = $seeder['category'] . '\\' . $seeder['class'];
                if (isset($this->seederLogs[$keyWithFolder])) {
                    $seeder['executed'] = true;
                    $seeder['executed_at'] = $this->seederLogs[$keyWithFolder];
                    continue;
                }
            }
            if (isset($this->seederLogs[$key])) {
                $seeder['executed'] = true;
                $seeder['executed_at'] = $this->seederLogs[$key];
            } else {
                $seeder['executed'] = false;
                $seeder['executed_at'] = null;
            }
        }
        unset($seeder);
        
        $this->availableSeeders = $seeders;
        $this->seederCategories = $categories;
        
        // Calcular stats
        $executed = count(array_filter($seeders, fn($s) => $s['executed']));
        $this->seederStats = [
            'total' => count($seeders),
            'executed' => $executed,
            'pending' => count($seeders) - $executed,
        ];
    }
    
    /**
     * Filtrar seeders por categoria
     */
    public function getFilteredSeedersProperty()
    {
        if ($this->selectedSeederCategory === 'all') {
            return $this->availableSeeders;
        }
        return array_filter($this->availableSeeders, fn($s) => $s['category'] === $this->selectedSeederCategory);
    }
    
    /**
     * Formatar nome do seeder para exibição
     */
    private function formatSeederName($className)
    {
        $name = str_replace('Seeder', '', $className);
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
        return $name;
    }

    /**
     * Comandos disponíveis
     */
    public function getAvailableCommands()
    {
        return [
            // ── CACHE & PERFORMANCE ──────────────────────
            'cache_clear' => [
                'name' => 'Limpar Todo Cache',
                'description' => 'Limpar cache, config, routes e views de uma só vez',
                'command' => 'optimize:clear',
                'icon' => 'broom',
                'color' => 'orange',
                'group' => 'Cache & Performance',
                'params' => [],
            ],
            'optimize' => [
                'name' => 'Otimizar Aplicação',
                'description' => 'Cachear config, routes e views para máxima performance',
                'command' => 'optimize',
                'icon' => 'bolt',
                'color' => 'yellow',
                'group' => 'Cache & Performance',
                'params' => [],
            ],
            'config_clear' => [
                'name' => 'Limpar Cache de Config',
                'description' => 'Remover cache de configurações (usar após alterar .env)',
                'command' => 'config:clear',
                'icon' => 'eraser',
                'color' => 'gray',
                'group' => 'Cache & Performance',
                'params' => [],
            ],
            'view_clear' => [
                'name' => 'Limpar Cache de Views',
                'description' => 'Remover views compiladas (usar após alterar templates)',
                'command' => 'view:clear',
                'icon' => 'eye-slash',
                'color' => 'gray',
                'group' => 'Cache & Performance',
                'params' => [],
            ],

            // ── BASE DE DADOS ────────────────────────────
            'migrate' => [
                'name' => 'Executar Migrations',
                'description' => 'Executar migrations pendentes do banco de dados',
                'command' => 'migrate',
                'icon' => 'database',
                'color' => 'red',
                'group' => 'Base de Dados',
                'params' => [
                    'force' => [
                        'label' => 'Forçar execução (produção)',
                        'type' => 'checkbox',
                        'required' => false,
                    ],
                ],
            ],
            'migrate_status' => [
                'name' => 'Status das Migrations',
                'description' => 'Ver quais migrations foram executadas e quais estão pendentes',
                'command' => 'migrate:status',
                'icon' => 'list-check',
                'color' => 'gray',
                'group' => 'Base de Dados',
                'params' => [],
            ],
            'seeders_status' => [
                'name' => 'Status dos Seeders',
                'description' => 'Ver quais seeders foram executados e quais estão pendentes',
                'command' => 'seeders:status',
                'icon' => 'seedling',
                'color' => 'green',
                'group' => 'Base de Dados',
                'params' => [],
            ],

            // ── MÓDULOS & PLANOS ─────────────────────────
            'sync_modules' => [
                'name' => 'Sincronizar Módulos dos Planos',
                'description' => 'Sincronizar módulos de um plano com os tenants que têm subscription ativa',
                'command' => 'plan:sync-modules',
                'icon' => 'sync-alt',
                'color' => 'blue',
                'group' => 'Módulos & Planos',
                'params' => [
                    'plan_id' => [
                        'label' => 'ID do Plano (opcional)',
                        'type' => 'select',
                        'options' => 'plans',
                        'required' => false,
                    ],
                    'module' => [
                        'label' => 'Slug do Módulo (opcional)',
                        'type' => 'select',
                        'options' => 'modules',
                        'required' => false,
                    ],
                    'all' => [
                        'label' => 'Sincronizar todos os planos',
                        'type' => 'checkbox',
                        'required' => false,
                    ],
                ],
            ],
            'attach_module_tenant' => [
                'name' => 'Vincular Módulo ao Tenant',
                'description' => 'Vincular um módulo específico a um tenant ou a todos',
                'command' => 'module:attach',
                'icon' => 'link',
                'color' => 'green',
                'group' => 'Módulos & Planos',
                'params' => [
                    'module_slug' => [
                        'label' => 'Slug do Módulo',
                        'type' => 'select',
                        'options' => 'modules',
                        'required' => true,
                    ],
                    'tenant_id' => [
                        'label' => 'ID do Tenant (deixe vazio para todos)',
                        'type' => 'select',
                        'options' => 'tenants',
                        'required' => false,
                    ],
                ],
            ],
            'attach_module_plan' => [
                'name' => 'Vincular Módulo ao Plano',
                'description' => 'Vincular um módulo específico a um plano ou a todos',
                'command' => 'module:attach-plan',
                'icon' => 'layer-group',
                'color' => 'purple',
                'group' => 'Módulos & Planos',
                'params' => [
                    'module_slug' => [
                        'label' => 'Slug do Módulo',
                        'type' => 'select',
                        'options' => 'modules',
                        'required' => true,
                    ],
                    'plan_id' => [
                        'label' => 'ID do Plano (deixe vazio para todos)',
                        'type' => 'select',
                        'options' => 'plans',
                        'required' => false,
                    ],
                ],
            ],

            // ── SEGURANÇA & UTILIZADORES ─────────────────
            'fix_roles_permissions' => [
                'name' => 'Corrigir Roles e Permissões',
                'description' => 'Corrige roles, permissões e utilizadores sem role em todos os tenants',
                'command' => 'users:fix-roles-permissions',
                'icon' => 'user-shield',
                'color' => 'teal',
                'group' => 'Segurança',
                'params' => [
                    'dry-run' => [
                        'label' => 'Modo Teste (Dry-Run) - Simula sem fazer alterações',
                        'type' => 'checkbox',
                        'required' => false,
                    ],
                ],
            ],

            // ── SISTEMA ──────────────────────────────────
            'storage_link' => [
                'name' => 'Criar Link do Storage',
                'description' => 'Criar link simbólico public/storage → storage/app/public',
                'command' => 'storage:link',
                'icon' => 'folder-open',
                'color' => 'indigo',
                'group' => 'Sistema',
                'params' => [],
            ],

            // ── DEPLOY ─────────────────────────────────────
            'patch_deploy' => [
                'name' => 'Aplicar Patch de Deploy',
                'description' => 'Executar pipeline completo: migrations, seeders, permissões, cache (v1.3.0)',
                'command' => 'patch:deploy',
                'icon' => 'rocket',
                'color' => 'rose',
                'group' => 'Deploy',
                'params' => [
                    'force' => [
                        'label' => 'Forçar (sem confirmação)',
                        'type' => 'checkbox',
                        'required' => false,
                    ],
                    'dry-run' => [
                        'label' => 'Modo Teste (Dry-Run) — Simula sem alterar nada',
                        'type' => 'checkbox',
                        'required' => false,
                    ],
                ],
            ],
            'seeders_run_pending' => [
                'name' => 'Executar Seeders Pendentes',
                'description' => 'Executar apenas os seeders que ainda não foram registados em seeder_logs',
                'command' => 'seeders:status',
                'icon' => 'seedling',
                'color' => 'lime',
                'group' => 'Deploy',
                'params' => [
                    'run' => [
                        'label' => 'Executar pendentes',
                        'type' => 'hidden',
                        'required' => false,
                        'default' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Executar comando
     */
    public function runCommand($commandKey)
    {
        $this->output = '';
        $this->isRunning = true;
        $this->selectedCommand = $commandKey;
        
        $commands = $this->getAvailableCommands();
        
        if (!isset($commands[$commandKey])) {
            $this->addOutput("❌ Comando não encontrado!", 'error');
            $this->isRunning = false;
            return;
        }
        
        $commandConfig = $commands[$commandKey];
        $commandName = $commandConfig['command'];
        
        try {
            $this->addOutput("🚀 Iniciando comando: {$commandConfig['name']}", 'info');
            $this->addOutput("⏳ Executando: php artisan {$commandName}", 'info');
            $this->addOutput(str_repeat('─', 80), 'separator');
            
            // Construir parâmetros
            $params = $this->buildCommandParams($commandKey, $commandConfig);
            
            // Forçar --force em comandos executados via web (sem terminal interactivo)
            if (isset($commandConfig['params']['force']) && !isset($params['--force'])) {
                $params['--force'] = true;
            }
            
            // Capturar output (usar buffer directo para não perder output de sub-comandos)
            $buffer = $this->getOutputBuffer();
            Artisan::call($commandName, $params, $buffer);
            
            $output = $buffer->fetch();
            
            if ($output) {
                $this->addOutput($output, 'success');
            }
            
            $this->addOutput(str_repeat('─', 80), 'separator');
            $this->addOutput("✅ Comando executado com sucesso!", 'success');
            
            // Salvar no histórico
            $this->saveToHistory($commandKey, $commandConfig['name'], true, $output);
            
        } catch (\Exception $e) {
            $this->addOutput("❌ ERRO: " . $e->getMessage(), 'error');
            $this->addOutput($e->getTraceAsString(), 'error');
            
            // Salvar erro no histórico
            $this->saveToHistory($commandKey, $commandConfig['name'], false, $e->getMessage());
        }
        
        $this->isRunning = false;
        $this->selectedCommand = null;
    }

    /**
     * Construir parâmetros do comando
     */
    private function buildCommandParams($commandKey, $config)
    {
        $params = [];
        
        if (empty($config['params'])) {
            return $params;
        }
        
        foreach ($config['params'] as $paramKey => $paramConfig) {
            $value = $this->commandParams[$commandKey][$paramKey] ?? null;
            
            if ($paramConfig['type'] === 'hidden') {
                // Hidden params com default são sempre aplicados
                if (isset($paramConfig['default']) && $paramConfig['default']) {
                    $params["--{$paramKey}"] = true;
                }
            } elseif ($paramConfig['type'] === 'checkbox') {
                if ($value) {
                    $params["--{$paramKey}"] = true;
                }
            } elseif ($value) {
                if (str_starts_with($paramKey, '--')) {
                    $params[$paramKey] = $value;
                } else {
                    $params[$paramKey] = $value;
                }
            }
        }
        
        return $params;
    }

    /**
     * Adicionar linha ao output
     */
    private function addOutput($text, $type = 'info')
    {
        $color = match($type) {
            'error' => 'text-red-600',
            'success' => 'text-green-600',
            'warning' => 'text-yellow-600',
            'info' => 'text-blue-600',
            'separator' => 'text-gray-400',
            default => 'text-gray-700',
        };
        
        $this->output .= "<div class='{$color}'>" . htmlspecialchars($text ?? '') . "</div>";
    }

    /**
     * Buffer de output
     */
    private function getOutputBuffer()
    {
        return new \Symfony\Component\Console\Output\BufferedOutput();
    }

    /**
     * Salvar no histórico
     */
    private function saveToHistory($commandKey, $commandName, $success, $output)
    {
        try {
            $historyFile = storage_path('logs/command_history.json');
            
            $history = [];
            if (File::exists($historyFile)) {
                $history = json_decode(File::get($historyFile), true) ?? [];
            }
            
            $history[] = [
                'command_key' => $commandKey,
                'command_name' => $commandName,
                'success' => $success,
                'output' => substr($output ?? '', 0, 1000),
                'executed_by' => auth()->user()?->name ?? 'Sistema',
                'executed_at' => now()->toDateTimeString(),
            ];
            
            // Manter apenas últimas 50 execuções
            $history = array_slice($history, -50);
            
            File::put($historyFile, json_encode($history, JSON_PRETTY_PRINT));
            
            $this->loadExecutionHistory();
        } catch (\Exception $e) {
            \Log::warning('Falha ao salvar histórico de comandos: ' . $e->getMessage());
        }
    }

    /**
     * Carregar histórico
     */
    private function loadExecutionHistory()
    {
        $historyFile = storage_path('logs/command_history.json');
        
        if (File::exists($historyFile)) {
            $this->executionHistory = json_decode(File::get($historyFile), true) ?? [];
            $this->executionHistory = array_reverse($this->executionHistory);
        }
    }

    /**
     * Limpar histórico
     */
    public function clearHistory()
    {
        $historyFile = storage_path('logs/command_history.json');
        
        if (File::exists($historyFile)) {
            File::delete($historyFile);
        }
        
        $this->executionHistory = [];
        $this->dispatch('success', message: 'Histórico limpo com sucesso!');
    }

    /**
     * Limpar output
     */
    public function clearOutput()
    {
        $this->output = '';
    }
    
    /**
     * Executar seeder selecionado
     */
    public function runSeeder()
    {
        if (!$this->selectedSeeder) {
            $this->dispatch('error', message: 'Selecione um seeder para executar!');
            return;
        }
        
        $this->output = '';
        $this->isRunning = true;
        
        try {
            // Usar namespace completo do seeder seleccionado
            $seederClass = $this->selectedSeeder;
            $seederName = class_basename(str_replace('\\', '/', $seederClass));
            
            $this->addOutput("🌱 Iniciando Seeder: {$seederName}", 'info');
            $this->addOutput("📦 Namespace: {$seederClass}", 'info');
            $this->addOutput("⏳ Executando: php artisan db:seed --class={$seederClass}", 'info');
            $this->addOutput(str_repeat('─', 80), 'separator');
            
            // Executar seeder
            Artisan::call('db:seed', [
                '--class' => $seederClass,
                '--force' => true,
            ], $this->getOutputBuffer());
            
            $output = Artisan::output();
            
            if ($output) {
                $this->addOutput($output, 'success');
            } else {
                $this->addOutput("Seeder executado sem output (sucesso silencioso)", 'success');
            }
            
            $this->addOutput(str_repeat('─', 80), 'separator');
            $this->addOutput("✅ Seeder executado com sucesso!", 'success');
            
            // Salvar no histórico
            $this->saveToHistory(
                "seeder_{$seederName}", 
                "Seeder: {$this->formatSeederName($seederName)}", 
                true, 
                $output ?: 'Executado com sucesso'
            );
            
            $this->dispatch('success', message: "✅ Seeder '{$seederName}' executado com sucesso!");
            
            // Recarregar status
            $this->loadSeederLogs();
            $this->loadAvailableSeeders();
            
        } catch (\Exception $e) {
            $this->addOutput("❌ ERRO: " . $e->getMessage(), 'error');
            $this->addOutput($e->getTraceAsString(), 'error');
            
            $seederName = class_basename(str_replace('\\', '/', $this->selectedSeeder));
            
            // Salvar erro no histórico
            $this->saveToHistory(
                "seeder_{$seederName}", 
                "Seeder: {$this->formatSeederName($seederName)}", 
                false, 
                $e->getMessage()
            );
            
            $this->dispatch('error', message: "❌ Erro ao executar seeder: " . $e->getMessage());
        }
        
        $this->isRunning = false;
    }

    public function render()
    {
        $commands = $this->getAvailableCommands();
        
        // Dados para selects
        $plans = Plan::orderBy('name')->get();
        $modules = Module::orderBy('name')->get();
        $tenants = Tenant::orderBy('name')->get();
        
        return view('livewire.super-admin.system-commands', compact('commands', 'plans', 'modules', 'tenants'));
    }
}
