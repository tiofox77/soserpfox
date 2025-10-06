<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

#[Layout('layouts.superadmin')]
#[Title('Atualizações do Sistema')]
class SystemUpdates extends Component
{
    public $currentVersion = '5.0.0';
    public $releases = [];
    public $loading = false;
    public $updateInProgress = false;
    public $updateLog = [];
    public $progressPercentage = 0;
    public $currentStep = '';
    
    // GitHub Config
    private $githubRepo = 'tiofox77/soserpfox';
    
    public function mount()
    {
        // Carregar versão atual do arquivo de configuração
        $versionFile = base_path('version.txt');
        if (File::exists($versionFile)) {
            $this->currentVersion = trim(File::get($versionFile));
        }
        
        // Não buscar automaticamente para evitar timeout no carregamento inicial
        // Usuário deve clicar em "Atualizar Lista" manualmente
    }

    public function fetchReleases()
    {
        $this->loading = true;
        
        try {
            // Aumentar timeout e adicionar retry
            $response = Http::timeout(30)
                ->retry(3, 100)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'SOS-ERP-System'
                ])
                ->get("https://api.github.com/repos/{$this->githubRepo}/releases");
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (empty($data)) {
                    $this->releases = [];
                    $this->dispatch('notify', [
                        'type' => 'info',
                        'message' => 'ℹ️ Nenhuma release encontrada no repositório'
                    ]);
                } else {
                    $this->releases = collect($data)
                        ->map(function ($release) {
                            return [
                                'tag_name' => $release['tag_name'] ?? 'unknown',
                                'name' => $release['name'] ?? $release['tag_name'] ?? 'Release',
                                'body' => $release['body'] ?? 'Sem descrição',
                                'published_at' => $release['published_at'] ?? now(),
                                'prerelease' => $release['prerelease'] ?? false,
                                'zipball_url' => $release['zipball_url'] ?? '',
                                'tarball_url' => $release['tarball_url'] ?? '',
                                'is_newer' => version_compare($release['tag_name'] ?? '0.0.0', $this->currentVersion, '>'),
                            ];
                        })
                        ->toArray();
                    
                    $this->dispatch('notify', [
                        'type' => 'success',
                        'message' => '✅ ' . count($this->releases) . ' releases encontradas!'
                    ]);
                }
            } else {
                $this->releases = [];
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '❌ Erro ao buscar releases: HTTP ' . $response->status()
                ]);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->releases = [];
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => '⚠️ Sem conexão com GitHub. Verifique sua internet ou tente mais tarde.'
            ]);
            \Log::warning('GitHub API timeout', ['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->releases = [];
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ Erro: ' . $e->getMessage()
            ]);
            \Log::error('GitHub API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        $this->loading = false;
    }

    public function installUpdate($version)
    {
        if (!auth()->user()->isSuperAdmin()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sem permissão!'
            ]);
            return;
        }

        $this->updateInProgress = true;
        $this->updateLog = [];
        $this->progressPercentage = 0;
        
        try {
            // 1. Criar backup (0-15%)
            $this->currentStep = 'Criando backup do sistema...';
            $this->addLog('🚀 Iniciando atualização do sistema para v' . $version, 'info');
            $this->addLog('📦 Criando backup de segurança...', 'info');
            $this->progressPercentage = 5;
            
            $this->createBackup();
            $this->progressPercentage = 15;
            $this->addLog('✅ Backup criado com sucesso', 'success');

            // 2. Baixar release (15-35%)
            $this->currentStep = 'Baixando atualização...';
            $this->progressPercentage = 20;
            $this->addLog('⬇️ Baixando versão ' . $version . ' do GitHub...', 'info');
            
            $zipPath = $this->downloadRelease($version);
            $this->progressPercentage = 35;
            $this->addLog('✅ Download concluído', 'success');

            // 3. Extrair arquivos (35-55%)
            $this->currentStep = 'Extraindo arquivos...';
            $this->progressPercentage = 40;
            $this->addLog('📂 Extraindo arquivos da atualização...', 'info');
            
            $this->extractUpdate($zipPath);
            $this->progressPercentage = 55;
            $this->addLog('✅ Arquivos extraídos e copiados', 'success');

            // 4. Executar migrations (55-70%)
            $this->currentStep = 'Executando migrations...';
            $this->progressPercentage = 60;
            $this->addLog('🔧 Executando migrations do banco de dados...', 'info');
            
            Artisan::call('migrate', ['--force' => true]);
            $this->progressPercentage = 70;
            $this->addLog('✅ Migrations executadas com sucesso', 'success');

            // 5. Limpar cache (70-90%)
            $this->currentStep = 'Limpando cache...';
            $this->progressPercentage = 75;
            $this->addLog('🧹 Limpando cache do sistema...', 'info');
            
            Artisan::call('optimize:clear');
            $this->addLog('  → Cache de aplicação limpo', 'info');
            Artisan::call('view:clear');
            $this->addLog('  → Cache de views limpo', 'info');
            Artisan::call('route:clear');
            $this->addLog('  → Cache de rotas limpo', 'info');
            Artisan::call('config:clear');
            $this->addLog('  → Cache de config limpo', 'info');
            
            $this->progressPercentage = 90;
            $this->addLog('✅ Cache limpo completamente', 'success');

            // 6. Atualizar versão (90-100%)
            $this->currentStep = 'Finalizando...';
            $this->progressPercentage = 95;
            $this->addLog('💾 Salvando nova versão...', 'info');
            
            $this->currentVersion = $version;
            $this->saveVersion($version);
            $this->progressPercentage = 100;
            $this->addLog('✅ Versão atualizada: ' . $version, 'success');
            
            $this->currentStep = 'Concluído!';
            $this->addLog('🎉 Atualização concluída com sucesso!', 'success');
            $this->addLog('🔄 Recarregue a página para ver as mudanças', 'info');

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '✅ Sistema atualizado para versão ' . $version . '! Recarregue a página.'
            ]);

        } catch (\Exception $e) {
            $this->addLog('❌ ERRO: ' . $e->getMessage(), 'error');
            $this->addLog('💡 Dica: Verifique os logs para mais detalhes', 'error');
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro na atualização: ' . $e->getMessage()
            ]);
        }
        
        $this->updateInProgress = false;
    }

    private function createBackup()
    {
        $backupPath = storage_path('backups');
        
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
        $zipPath = $backupPath . '/' . $fileName;

        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            // Backup de arquivos importantes
            $files = File::allFiles(base_path('app'));
            foreach ($files as $file) {
                $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                $zip->addFile($file->getRealPath(), $relativePath);
            }
            
            $zip->close();
        }
    }

    private function downloadRelease($version)
    {
        $url = "https://github.com/{$this->githubRepo}/archive/refs/tags/{$version}.zip";
        
        $response = Http::timeout(120)->get($url);
        
        if (!$response->successful()) {
            throw new \Exception('Falha ao baixar release');
        }

        $zipPath = storage_path('updates/' . $version . '.zip');
        
        if (!File::exists(storage_path('updates'))) {
            File::makeDirectory(storage_path('updates'), 0755, true);
        }

        File::put($zipPath, $response->body());
        
        return $zipPath;
    }

    private function extractUpdate($zipPath)
    {
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath) === TRUE) {
            $extractPath = storage_path('updates/extracted');
            
            if (File::exists($extractPath)) {
                File::deleteDirectory($extractPath);
            }
            
            File::makeDirectory($extractPath, 0755, true);
            $zip->extractTo($extractPath);
            $zip->close();
            
            // Encontrar o diretório raiz extraído
            $dirs = File::directories($extractPath);
            $sourceDir = $dirs[0] ?? $extractPath;
            
            // Arquivos/diretórios que NÃO devem ser sobrescritos
            $protectedPaths = [
                '.env',
                'storage/logs',
                'storage/framework/sessions',
                'storage/framework/views',
                'storage/framework/cache',
                'storage/app/public',
                'vendor', // Será atualizado via composer
                '.git',
                '.gitignore',
                'node_modules',
            ];
            
            // Copiar arquivos atualizados
            $this->copyDirectory($sourceDir, base_path(), $protectedPaths);
            
            // Atualizar dependências do composer
            $this->addLog('📦 Atualizando dependências Composer...');
            $composerPath = base_path('composer.json');
            if (File::exists($composerPath)) {
                // Executar composer install
                exec('cd ' . base_path() . ' && composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1', $output, $return);
                if ($return === 0) {
                    $this->addLog('✅ Composer atualizado');
                } else {
                    $this->addLog('⚠️ Aviso: Erro ao atualizar Composer');
                }
            }
            
            // Limpar arquivos temporários
            File::deleteDirectory($extractPath);
            File::delete($zipPath);
            
        } else {
            throw new \Exception('Falha ao extrair arquivo ZIP');
        }
    }
    
    private function copyDirectory($source, $destination, $protectedPaths = [])
    {
        if (!File::isDirectory($source)) {
            return;
        }
        
        if (!File::isDirectory($destination)) {
            File::makeDirectory($destination, 0755, true);
        }
        
        $items = File::allFiles($source);
        
        foreach ($items as $item) {
            $relativePath = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getRealPath());
            $destPath = $destination . DIRECTORY_SEPARATOR . $relativePath;
            
            // Verificar se o caminho está protegido
            $isProtected = false;
            foreach ($protectedPaths as $protected) {
                if (str_starts_with($relativePath, $protected)) {
                    $isProtected = true;
                    break;
                }
            }
            
            if (!$isProtected) {
                $destDir = dirname($destPath);
                if (!File::isDirectory($destDir)) {
                    File::makeDirectory($destDir, 0755, true);
                }
                File::copy($item->getRealPath(), $destPath);
            }
        }
    }

    private function saveVersion($version)
    {
        $versionFile = base_path('version.txt');
        File::put($versionFile, $version);
    }

    private function addLog($message, $type = 'info')
    {
        $this->updateLog[] = [
            'time' => now()->format('H:i:s'),
            'message' => $message,
            'type' => $type,
        ];
    }

    public function render()
    {
        return view('livewire.super-admin.systemupdates');
    }
}

