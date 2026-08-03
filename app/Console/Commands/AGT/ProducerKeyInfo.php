<?php

namespace App\Console\Commands\AGT;

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
    protected $signature = 'agt:producer-key {--pem : Mostra também a chave pública em PEM}';
    protected $description = 'Impressão digital da chave do produtor usada na FE e no SAFT';

    public function handle(): int
    {
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
}
