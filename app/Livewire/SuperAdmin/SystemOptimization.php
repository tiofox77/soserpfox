<?php

namespace App\Livewire\SuperAdmin;

use App\Services\OpcacheService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Otimização do Sistema')]
class SystemOptimization extends Component
{
    public $opcacheStats = [];
    public $opcacheHealth = [];
    public $phpInfo = [];
    public $showConfigModal = false;
    
    // Configurações
    public $environment = 'production'; // production ou development
    public $validate_timestamps = 1;
    public $revalidate_freq = 2;
    public $max_input_vars = 1000;
    public $memory_limit = '512M';
    public $max_execution_time = 300;

    protected $opcacheService;

    public function boot(OpcacheService $opcacheService)
    {
        $this->opcacheService = $opcacheService;
    }

    public function mount()
    {
        $this->loadData();
        $this->loadCurrentSettings();
    }
    
    public function loadCurrentSettings()
    {
        $this->validate_timestamps = (int) ini_get('opcache.validate_timestamps') ?: 1;
        $this->revalidate_freq = (int) ini_get('opcache.revalidate_freq') ?: 2;
        $this->max_input_vars = (int) ini_get('max_input_vars') ?: 1000;
        $this->memory_limit = ini_get('memory_limit') ?: '512M';
        $this->max_execution_time = (int) ini_get('max_execution_time') ?: 300;
    }

    public function loadData()
    {
        // OPcache
        $this->opcacheStats = $this->opcacheService->getFormattedStats();
        $this->opcacheHealth = $this->opcacheService->getHealthStatus();

        // PHP Info
        $this->phpInfo = [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
        ];
    }

    public function clearOpcache()
    {
        if ($this->opcacheService->clear()) {
            $this->dispatch('success', message: '✅ OPcache limpo com sucesso!');
        } else {
            $this->dispatch('error', message: '❌ Erro ao limpar OPcache. Verifique se está ativo.');
        }

        $this->loadData();
    }

    public function clearAllCaches()
    {
        try {
            // Limpar caches do Laravel
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');

            // Limpar OPcache se disponível
            if ($this->opcacheService->isEnabled()) {
                $this->opcacheService->clear();
            }

            $this->dispatch('success', message: '✅ Todos os caches limpos com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: '❌ Erro ao limpar caches: ' . $e->getMessage());
        }

        $this->loadData();
    }

    public function optimizeSystem()
    {
        try {
            // Cache de configuração
            \Artisan::call('config:cache');
            
            // Cache de rotas
            \Artisan::call('route:cache');
            
            // Cache de views
            \Artisan::call('view:cache');

            $this->dispatch('success', message: '✅ Sistema otimizado com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: '❌ Erro ao otimizar: ' . $e->getMessage());
        }

        $this->loadData();
    }

    public function openConfigModal()
    {
        $this->showConfigModal = true;
    }
    
    public function closeConfigModal()
    {
        $this->showConfigModal = false;
    }
    
    public function applyProductionSettings()
    {
        $this->environment = 'production';
        $this->validate_timestamps = 0;
        $this->revalidate_freq = 0;
        $this->max_input_vars = 3000;
        $this->memory_limit = '512M';
        $this->max_execution_time = 360;
    }
    
    public function applyDevelopmentSettings()
    {
        $this->environment = 'development';
        $this->validate_timestamps = 1;
        $this->revalidate_freq = 2;
        $this->max_input_vars = 1000;
        $this->memory_limit = '512M';
        $this->max_execution_time = 300;
    }
    
    public function generateConfigFile()
    {
        try {
            $config = $this->generateUserIniContent();
            
            // Caminho do arquivo .user.ini na raiz do projeto
            $filePath = base_path('.user.ini');
            
            // Salvar arquivo
            file_put_contents($filePath, $config);
            
            $this->dispatch('success', message: '✅ Arquivo .user.ini gerado com sucesso! Faça upload para o cPanel.');
            $this->showConfigModal = false;
            
        } catch (\Exception $e) {
            $this->dispatch('error', message: '❌ Erro ao gerar arquivo: ' . $e->getMessage());
        }
    }
    
    public function downloadConfigFile()
    {
        $config = $this->generateUserIniContent();
        
        return response()->streamDownload(function() use ($config) {
            echo $config;
        }, '.user.ini', [
            'Content-Type' => 'text/plain',
        ]);
    }
    
    private function generateUserIniContent(): string
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        
        return <<<INI
; ============================================
; Configurações PHP - SOSERP
; Gerado em: {$timestamp}
; Ambiente: {$this->environment}
; ============================================

; === PERFORMANCE & MEMORY ===
memory_limit = {$this->memory_limit}
max_execution_time = {$this->max_execution_time}
max_input_vars = {$this->max_input_vars}
max_input_time = 300

; === UPLOADS ===
upload_max_filesize = 64M
post_max_size = 64M

; === OPCACHE SETTINGS ===
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = {$this->validate_timestamps}
opcache.revalidate_freq = {$this->revalidate_freq}
opcache.save_comments = 1
opcache.fast_shutdown = 1

; === SESSION ===
session.gc_maxlifetime = 1440
session.cookie_lifetime = 0

; === ERROR REPORTING ===
display_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

; ============================================
; INSTRUÇÕES DE USO (cPanel):
; 1. Faça upload deste arquivo para a pasta public_html
; 2. Nome do arquivo: .user.ini
; 3. Aguarde 5 minutos para aplicar
; 4. Limpe o OPcache no painel
; ============================================
INI;
    }

    public function render()
    {
        return view('livewire.super-admin.system-optimization');
    }
}
