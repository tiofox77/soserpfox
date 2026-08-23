<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\MachineFingerprint;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Emite (assina) uma licença. Lado do EMISSOR — precisa da chave privada, que
 * vem de --secret ou da env LICENSE_SIGNING_KEY. Nunca correr no cliente.
 */
class LicencaEmitir extends Command
{
    protected $signature = 'licenca:emitir
        {--tenant= : id do tenant}
        {--empresa= : nome da empresa}
        {--nif= : NIF da empresa}
        {--plano= : slug/nome do plano}
        {--modulos= : módulos permitidos, separados por vírgula (ou * para todos)}
        {--dias=365 : validade em dias a partir de hoje}
        {--expira= : data de expiração exacta (Y-m-d), sobrepõe --dias}
        {--graca= : dias de graça offline (sobrepõe o default da config)}
        {--maquina : prende à impressão digital DESTA máquina}
        {--fingerprint= : prende a um fingerprint específico}
        {--secret= : chave privada Base64 (senão usa LICENSE_SIGNING_KEY)}
        {--out= : grava o token neste ficheiro}';

    protected $description = 'Emite (assina) uma licença offline para um tenant';

    public function handle(): int
    {
        $secret = $this->option('secret') ?: env('LICENSE_SIGNING_KEY');
        if (!$secret) {
            $this->error('Sem chave privada. Passe --secret= ou defina LICENSE_SIGNING_KEY.');

            return self::FAILURE;
        }

        $expira = $this->option('expira')
            ? CarbonImmutable::parse($this->option('expira'))->endOfDay()
            : CarbonImmutable::now()->addDays((int) $this->option('dias'));

        $fingerprint = null;
        if ($this->option('fingerprint')) {
            $fingerprint = $this->option('fingerprint');
        } elseif ($this->option('maquina')) {
            $fingerprint = MachineFingerprint::atual();
            $this->line("Preso à máquina actual: {$fingerprint}");
        }

        $modulos = $this->option('modulos')
            ? array_values(array_filter(array_map('trim', explode(',', $this->option('modulos')))))
            : [];

        $claims = array_filter([
            'tenant_id' => $this->option('tenant') ? (int) $this->option('tenant') : null,
            'empresa'   => $this->option('empresa'),
            'nif'       => $this->option('nif'),
            'plano'     => $this->option('plano'),
            'modulos'   => $modulos,
            'exp'       => $expira->getTimestamp(),
            'graca'     => $this->option('graca') !== null && $this->option('graca') !== ''
                ? (int) $this->option('graca') : null,
            'fp'        => $fingerprint,
            'env'       => app()->environment('production') ? 'prod' : 'homolog',
        ], fn ($v) => $v !== null && $v !== []);

        try {
            $token = (new LicenseIssuer())->emitir($claims, $secret);
        } catch (\Throwable $e) {
            $this->error('Falha ao emitir: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Licença emitida:');
        $this->line($token);
        $this->newLine();
        $this->line('Expira em: ' . $expira->toDateTimeString()
            . ($fingerprint ? ' · presa a máquina' : ' · flutuante (qualquer máquina)'));

        if ($destino = $this->option('out')) {
            File::ensureDirectoryExists(dirname($destino));
            file_put_contents($destino, $token);
            $this->info("Gravado em: {$destino}");
        }

        return self::SUCCESS;
    }
}
