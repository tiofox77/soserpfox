<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseIssuer;
use Illuminate\Console\Command;

/**
 * Gera o par de chaves Ed25519 do emissor. Corre-se UMA vez, do lado de quem
 * gere a plataforma — nunca na máquina do cliente.
 */
class LicencaChaves extends Command
{
    protected $signature = 'licenca:chaves';

    protected $description = 'Gera um par de chaves Ed25519 para assinar licenças (emissor)';

    public function handle(): int
    {
        $par = LicenseIssuer::gerarParDeChaves();

        $this->newLine();
        $this->info('Par de chaves gerado. Guarde a PRIVADA em cofre — quem a tiver, forja licenças.');
        $this->newLine();

        $this->line('<comment>CHAVE PÚBLICA</comment> (vai para config/licensing.php ou LICENSE_PUBLIC_KEY do cliente):');
        $this->line($par['publica']);
        $this->newLine();

        $this->line('<comment>CHAVE PRIVADA</comment> (só no emissor; use em LICENSE_SIGNING_KEY ou --secret):');
        $this->line($par['privada']);
        $this->newLine();

        $this->warn('NUNCA comitar a chave privada. Se vazar, gere um par novo e reemita as licenças.');

        return self::SUCCESS;
    }
}
