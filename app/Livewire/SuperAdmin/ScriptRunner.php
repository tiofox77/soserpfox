<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

#[Layout('layouts.superadmin')]
#[Title('Executar Scripts')]
class ScriptRunner extends Component
{
    public $scripts = [];
    public $selectedScript = null;
    public $output = '';
    public $running = false;
    public $logs = [];
    public $showLogs = false;
    
    public function mount()
    {
        $this->loadScripts();
        $this->loadRecentLogs();
    }
    
    public function loadScripts()
    {
        $scriptsPath = base_path('scripts');
        
        if (!File::exists($scriptsPath)) {
            File::makeDirectory($scriptsPath, 0755, true);
        }
        
        // Apenas scripts PHP executáveis (a página corre via `require`).
        // Ficheiros de infra (ftp_*.ps1), dados (.json) e docs (.md) são ignorados.
        $files = collect(File::files($scriptsPath))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'php');

        $this->scripts = $files->map(function ($file) {
            return [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $this->formatBytes($file->getSize()),
                'modified' => $file->getMTime(),
                'modified_human' => date('d/m/Y H:i', $file->getMTime()),
                'description' => $this->getScriptDescription($file->getPathname()),
            ];
        })->sortBy('name')->values()->toArray();
    }
    
    public function loadRecentLogs()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            $this->logs = [];
            return;
        }
        
        $content = File::get($logFile);
        $lines = explode("\n", $content);
        
        // Pegar últimas 100 linhas
        $this->logs = array_slice(array_reverse($lines), 0, 100);
    }
    
    public function runScript($scriptName)
    {
        if ($this->running) {
            $this->dispatch('error', message: 'Já existe um script em execução!');
            return;
        }
        
        $this->running = true;
        $this->output = '';
        $this->selectedScript = $scriptName;
        
        try {
            $scriptPath = base_path('scripts/' . $scriptName);
            
            if (!File::exists($scriptPath)) {
                throw new \Exception('Script não encontrado!');
            }
            
            // Log início
            \Log::info("📜 Executando script: {$scriptName}", [
                'user' => auth()->user()->name,
                'user_id' => auth()->id(),
            ]);
            
            // Executar script e capturar output
            ob_start();
            $startTime = microtime(true);
            
            try {
                require $scriptPath;
                $this->output = ob_get_clean();
                $executionTime = round(microtime(true) - $startTime, 2);
                
                // Log sucesso
                \Log::info("✅ Script executado com sucesso: {$scriptName}", [
                    'execution_time' => $executionTime . 's',
                    'output_length' => strlen($this->output),
                ]);
                
                $this->output .= "\n\n✅ Script executado com sucesso em {$executionTime}s";
                $this->dispatch('success', message: 'Script executado com sucesso!');
                
            } catch (\Exception $e) {
                ob_end_clean();
                throw $e;
            }
            
            $this->loadRecentLogs();
            
        } catch (\Exception $e) {
            \Log::error("❌ Erro ao executar script: {$scriptName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->output = "❌ Erro ao executar script:\n\n" . $e->getMessage();
            $this->dispatch('error', message: 'Erro ao executar script!');
        }
        
        $this->running = false;
    }
    
    public function clearOutput()
    {
        $this->output = '';
        $this->selectedScript = null;
    }
    
    public function refreshScripts()
    {
        $this->loadScripts();
        $this->dispatch('success', message: 'Lista de scripts atualizada!');
    }
    
    public function toggleLogs()
    {
        $this->showLogs = !$this->showLogs;
        if ($this->showLogs) {
            $this->loadRecentLogs();
        }
    }
    
    public function clearLogs()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (File::exists($logFile)) {
            File::put($logFile, '');
            $this->logs = [];
            $this->dispatch('success', message: 'Logs limpos com sucesso!');
        }
    }
    
    private function getScriptDescription($path)
    {
        $content = File::get($path);
        
        // Procurar por comentário de descrição
        if (preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\s*\n/s', $content, $matches)) {
            return trim($matches[1]);
        }
        
        // Ou primeira linha de comentário
        if (preg_match('/^<\?php\s*\n\s*\/\/\s*(.+)/m', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return 'Sem descrição';
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    public function render()
    {
        return view('livewire.super-admin.script-runner');
    }
}
