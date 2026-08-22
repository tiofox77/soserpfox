<?php

namespace App\Console\Commands\AGT;

use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTProducerStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Identifica a chave do PRODUTOR (saft/) — a que assina o bloco softwareInfo
 * da Facturação Electrónica e a cadeia de hash do SAFT-AO.
 *
 * Serve para confirmar que a chave pública registada no portal da AGT é a
 * mesma com que este servidor assina. Se local e produção tiverem pares
 * diferentes, a AGT recusa as assinaturas de um deles.
 *
 * Só devolve metadados (impressão digital, tamanho, validade do par) e, com
 * --pem, a chave PÚBLICA — que é pública por definição. A privada nunca é
 * mostrada.
 *
 *   php artisan agt:producer-key
 *   php artisan agt:producer-key --pem
 */
class ProducerKeyInfo extends Command
{
    protected $signature = 'agt:producer-key
        {--pem : Mostra também a chave pública em PEM}
        {--tenant= : Mostra o produtor efectivo para uma empresa}
        {--ambiente= : sandbox ou production}';
    protected $description = 'Impressão digital da chave do produtor usada na FE e no SAFT';

    public function handle(): int
    {
        if ($this->option('tenant') || $this->option('ambiente')) {
            return $this->mostrarAmbiente();
        }

        $disk = Storage::disk('local');

        $temPub  = $disk->exists('saft/public_key.pem');
        $temPriv = $disk->exists('saft/private_key.pem');

        $this->info('=== Chave do PRODUTOR (saft/) ===');
        $this->line('  pública : ' . ($temPub ? 'presente' : '<fg=red>EM FALTA</>'));
        $this->line('  privada : ' . ($temPriv ? 'presente' : '<fg=red>EM FALTA</>'));

        if (!$temPub || !$temPriv) {
            $this->newLine();
            $this->warn('  Sem par completo não é possível assinar softwareInfo nem o SAFT.');
            return self::SUCCESS;
        }

        $pubPem = $disk->get('saft/public_key.pem');
        $pub = openssl_pkey_get_public($pubPem);
        $priv = openssl_pkey_get_private($disk->get('saft/private_key.pem'));

        if (!$pub || !$priv) {
            $this->error('  Chave ilegível (PEM inválido).');
            return self::FAILURE;
        }

        $dPub  = openssl_pkey_get_details($pub);
        $dPriv = openssl_pkey_get_details($priv);
        $par = $dPub['key'] === $dPriv['key'];

        // Verificação real: assinar e verificar com RS256, que é o que a FE usa.
        openssl_sign('sos-erp-producer-check', $sig, $priv, OPENSSL_ALGO_SHA256);
        $verifica = openssl_verify('sos-erp-producer-check', $sig, $pub, OPENSSL_ALGO_SHA256) === 1;

        $this->newLine();
        $this->line('  algoritmo        : ' . ($dPub['type'] === OPENSSL_KEYTYPE_RSA ? 'RSA' : 'não-RSA'));
        $this->line('  tamanho          : ' . $dPub['bits'] . ' bits');
        $this->line('  par corresponde  : ' . ($par ? '<fg=green>SIM</>' : '<fg=red>NÃO</>'));
        $this->line('  RS256 verifica   : ' . ($verifica ? '<fg=green>SIM</>' : '<fg=red>NÃO</>'));

        // Impressão digital da PÚBLICA: é por aqui que se compara servidores.
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pubPem));
        $this->line('  sha256 (pública) : ' . strtoupper(substr(hash('sha256', $der), 0, 32)));
        $this->line('  modificada em    : ' . date('Y-m-d H:i:s', $disk->lastModified('saft/public_key.pem')));

        if ($this->option('pem')) {
            $this->newLine();
            $this->line('<fg=cyan>--- chave PÚBLICA (para registar no portal da AGT) ---</>');
            $this->line(trim($pubPem));
        }

        return self::SUCCESS;
    }

    private function mostrarAmbiente(): int
    {
        $tenantId = (int) ($this->option('tenant') ?: 0);
        $settings = $tenantId ? InvoicingSettings::forTenant($tenantId) : null;
        $ambiente = AGTProducerStore::normalizar(
            $this->option('ambiente') ?: ($settings?->agt_environment ?? null)
        );
        $disk = Storage::disk('local');
        $pubPath = AGTProducerStore::publicKeyPath($ambiente);
        $privPath = AGTProducerStore::privateKeyPath($ambiente);

        $this->info('=== Produtor efectivo ===');
        $this->line('  tenant                 : ' . ($tenantId ?: '—'));
        $this->line('  ambiente               : ' . $ambiente);
        $this->line('  productId              : ' . AGTProducerStore::productId($ambiente));
        $this->line('  productVersion         : ' . AGTProducerStore::productVersion($ambiente));
        $this->line('  certificação           : ' . (AGTProducerStore::numeroCertificacao($ambiente) ?: '(vazia)'));
        $this->line('  certificação própria   : ' . (AGTProducerStore::temCertificacaoPropria($ambiente) ? 'SIM' : 'NÃO — usa valor legado'));
        $this->line('  credenciais próprias   : ' . (AGTProducerStore::credenciais($ambiente)['proprias'] ? 'SIM' : 'NÃO — usa valor legado'));
        $this->line('  utilizador Basic Auth  : ' . (AGTProducerStore::credenciais($ambiente)['username'] ?: '(vazio)'));
        $this->line('  chave própria ambiente : ' . (AGTProducerStore::temChavesProprias($ambiente) ? 'SIM' : 'NÃO — usa saft/ legado'));
        $this->line('  chave pública          : ' . $pubPath);
        $this->line('  chave privada          : ' . $privPath);

        // O QUE VAI MESMO ASSINADO, byte a byte.
        //
        // O E39 ("os dados constantes na assinatura não estão de acordo com
        // o Processo de Certificação") é quase sempre um caractere: uma
        // versão "1.0" contra "1.0.0", um hífen onde o certificado tem
        // travessão. A tabela normal esconde isso; aqui mostra-se entre
        // barras verticais e com o comprimento, para se ver o invisível.
        $pid = AGTProducerStore::productId($ambiente);
        $pver = AGTProducerStore::productVersion($ambiente);
        $pcert = AGTProducerStore::numeroCertificacao($ambiente);
        $this->newLine();
        $this->info('=== softwareInfoDetail assinado (compare com o certificado da AGT) ===');
        foreach (['productId' => $pid, 'productVersion' => $pver, 'softwareValidationNumber' => $pcert] as $k => $v) {
            $this->line(sprintf('  %-26s |%s|  (%d bytes)', $k, $v, strlen((string) $v)));
        }
        // Aviso específico para a versão: "1.0" vs "1.0.0" é o caso número um.
        if (!preg_match('/^\d+\.\d+\.\d+$/', (string) $pver)) {
            $this->warn('  productVersion não é X.Y.Z — se o certificado disser "1.0.0", isto dá E39.');
        }
        if (str_contains((string) $pid, ' - ')) {
            $this->warn('  productId usa hífen " - ". Se o certificado tiver travessão " — ", dá E39.');
        }

        if (!$disk->exists($pubPath) || !$disk->exists($privPath)) {
            $this->error('  Par RSA do produtor incompleto.');
            return self::FAILURE;
        }

        $pubPem = $disk->get($pubPath);
        $pub = openssl_pkey_get_public($pubPem);
        $priv = openssl_pkey_get_private($disk->get($privPath));
        if (!$pub || !$priv) {
            $this->error('  Par RSA ilegível.');
            return self::FAILURE;
        }

        $dPub = openssl_pkey_get_details($pub);
        $dPriv = openssl_pkey_get_details($priv);
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pubPem));
        $this->line('  RSA                    : ' . $dPub['bits'] . ' bits; par ' . ($dPub['key'] === $dPriv['key'] ? 'OK' : 'NÃO CORRESPONDE'));
        $this->line('  sha256 pública         : ' . strtoupper(substr(hash('sha256', $der), 0, 32)));

        if ($this->option('pem')) {
            $this->newLine();
            $this->line(trim($pubPem));
        }

        return self::SUCCESS;
    }
}
