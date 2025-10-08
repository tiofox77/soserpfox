<?php

namespace App\Livewire\SuperAdmin;

use App\Models\{Plan, Module, Tenant};
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
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

    public function mount()
    {
        $this->loadExecutionHistory();
    }

    /**
     * Comandos disponíveis
     */
    public function getAvailableCommands()
    {
        return [
            'sync_modules' => [
                'name' => 'Sincronizar Módulos dos Planos',
                'description' => 'Sincronizar todos os módulos de um plano específico ou todos os planos com os tenants',
                'command' => 'plan:sync-modules',
                'icon' => 'sync-alt',
                'color' => 'blue',
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
            'cache_clear' => [
                'name' => 'Limpar Cache',
                'description' => 'Limpar todo o cache da aplicação',
                'command' => 'cache:clear',
                'icon' => 'trash-alt',
                'color' => 'orange',
                'params' => [],
            ],
            'config_cache' => [
                'name' => 'Cachear Configurações',
                'description' => 'Gerar cache de configurações para performance',
                'command' => 'config:cache',
                'icon' => 'cog',
                'color' => 'indigo',
                'params' => [],
            ],
            'migrate' => [
                'name' => 'Executar Migrations',
                'description' => 'Executar migrations pendentes do banco de dados',
                'command' => 'migrate',
                'icon' => 'database',
                'color' => 'red',
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
                'description' => 'Ver status de todas as migrations',
                'command' => 'migrate:status',
                'icon' => 'list-check',
                'color' => 'gray',
                'params' => [],
            ],
            'fix_roles_permissions' => [
                'name' => 'Corrigir Roles e Permissões',
                'description' => 'Corrige roles e permissões de todos os usuários e tenants. Cria roles padrão, sincroniza permissões e corrige usuários sem roles.',
                'command' => 'users:fix-roles-permissions',
                'icon' => 'user-shield',
                'color' => 'teal',
                'params' => [
                    'dry-run' => [
                        'label' => 'Modo Teste (Dry-Run) - Simula sem fazer alterações',
                        'type' => 'checkbox',
                        'required' => false,
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
            
            // Capturar output
            Artisan::call($commandName, $params, $this->getOutputBuffer());
            
            $output = Artisan::output();
            
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
            
            if ($paramConfig['type'] === 'checkbox') {
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
        
        $this->output .= "<div class='{$color}'>" . htmlspecialchars($text) . "</div>";
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
        $historyFile = storage_path('logs/command_history.json');
        
        $history = [];
        if (File::exists($historyFile)) {
            $history = json_decode(File::get($historyFile), true) ?? [];
        }
        
        $history[] = [
            'command_key' => $commandKey,
            'command_name' => $commandName,
            'success' => $success,
            'output' => substr($output, 0, 1000), // Limitar tamanho
            'executed_by' => auth()->user()->name,
            'executed_at' => now()->toDateTimeString(),
        ];
        
        // Manter apenas últimas 50 execuções
        $history = array_slice($history, -50);
        
        File::put($historyFile, json_encode($history, JSON_PRETTY_PRINT));
        
        $this->loadExecutionHistory();
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
