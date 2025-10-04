<?php

namespace App\Livewire\SuperAdmin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Storage;

#[Layout('layouts.app')]
#[Title('Configuração SAFT-AO')]
class SaftConfiguration extends Component
{
    public $publicKeyExists = false;
    public $privateKeyExists = false;
    public $publicKeyContent = '';
    public $publicKeyDate = null;
    public $privateKeyDate = null;
    
    public function mount()
    {
        $this->checkKeys();
    }
    
    public function checkKeys()
    {
        // Verificar se as chaves existem
        $this->publicKeyExists = Storage::disk('local')->exists('saft/public_key.pem');
        $this->privateKeyExists = Storage::disk('local')->exists('saft/private_key.pem');
        
        if ($this->publicKeyExists) {
            $this->publicKeyContent = Storage::disk('local')->get('saft/public_key.pem');
            $this->publicKeyDate = Storage::disk('local')->lastModified('saft/public_key.pem');
        }
        
        if ($this->privateKeyExists) {
            $this->privateKeyDate = Storage::disk('local')->lastModified('saft/private_key.pem');
        }
    }
    
    public function generateKeys()
    {
        try {
            // Criar diretório se não existir
            if (!Storage::disk('local')->exists('saft')) {
                Storage::disk('local')->makeDirectory('saft');
            }
            
            // Verificar se OpenSSL está disponível
            if (!extension_loaded('openssl')) {
                throw new \Exception('Extensão OpenSSL não está habilitada no PHP');
            }
            
            // Limpar erros anteriores do OpenSSL
            while (openssl_error_string() !== false);
            
            // Definir arquivo de configuração OpenSSL temporário
            $configFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'openssl.cnf';
            
            // Criar arquivo de configuração OpenSSL se não existir
            if (!file_exists($configFile)) {
                $configContent = <<<EOD
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name

[ req_distinguished_name ]
EOD;
                file_put_contents($configFile, $configContent);
            }
            
            // Configuração para chave RSA 2048 bits (conforme SAFT-AO Angola)
            $config = [
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
                "config" => $configFile,
            ];
            
            // Gerar par de chaves
            $res = openssl_pkey_new($config);
            
            if (!$res) {
                $errors = [];
                while ($error = openssl_error_string()) {
                    $errors[] = $error;
                }
                throw new \Exception('Erro ao gerar par de chaves: ' . implode(', ', $errors));
            }
            
            // Extrair chave privada
            $exportSuccess = openssl_pkey_export($res, $privateKey, null, $config);
            
            if (!$exportSuccess) {
                throw new \Exception('Erro ao exportar chave privada: ' . openssl_error_string());
            }
            
            // Extrair chave pública
            $publicKeyDetails = openssl_pkey_get_details($res);
            
            if (!$publicKeyDetails) {
                throw new \Exception('Erro ao obter detalhes da chave pública: ' . openssl_error_string());
            }
            
            $publicKey = $publicKeyDetails["key"];
            
            // Salvar chave privada
            Storage::disk('local')->put('saft/private_key.pem', $privateKey);
            
            // Salvar chave pública
            Storage::disk('local')->put('saft/public_key.pem', $publicKey);
            
            // Salvar metadados
            $metadata = [
                'generated_at' => now()->toDateTimeString(),
                'algorithm' => 'RSA-2048',
                'digest' => 'SHA-256',
                'compliance' => 'SAFT-AO Angola',
                'php_version' => PHP_VERSION,
                'openssl_version' => OPENSSL_VERSION_TEXT,
            ];
            Storage::disk('local')->put('saft/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
            
            // Nota: openssl_free_key() está deprecated no PHP 8+
            // Os recursos são liberados automaticamente
            
            $this->checkKeys();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Chaves SAFT-AO geradas com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Erro ao gerar chaves SAFT: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao gerar chaves: ' . $e->getMessage()
            ]);
        }
    }
    
    public function downloadPublicKey()
    {
        if (!$this->publicKeyExists) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Chave pública não encontrada!'
            ]);
            return;
        }
        
        return Storage::disk('local')->download('saft/public_key.pem', 'saft_public_key.pem');
    }
    
    public function downloadPrivateKey()
    {
        if (!$this->privateKeyExists) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Chave privada não encontrada!'
            ]);
            return;
        }
        
        return Storage::disk('local')->download('saft/private_key.pem', 'saft_private_key.pem');
    }
    
    public function regenerateKeys()
    {
        if (!confirm('Atenção! Regenerar as chaves invalidará todos os documentos assinados. Deseja continuar?')) {
            return;
        }
        
        // Fazer backup das chaves antigas
        if ($this->publicKeyExists) {
            $backupPath = 'saft/backups/' . date('Y-m-d_His');
            Storage::disk('local')->makeDirectory($backupPath);
            Storage::disk('local')->copy('saft/public_key.pem', $backupPath . '/public_key.pem');
            Storage::disk('local')->copy('saft/private_key.pem', $backupPath . '/private_key.pem');
        }
        
        $this->generateKeys();
    }

    public function render()
    {
        return view('livewire.super-admin.saft-configuration');
    }
}
