<?php

namespace App\Console\Commands;

use App\Models\AppUpdate;
use App\Services\Licensing\UpdateSigner;
use Illuminate\Console\Command;

/**
 * Publica (assina + guarda) uma versão do soserp — lado VENDOR. Precisa da
 * chave privada de atualização (--secret ou LICENSE_UPDATE_SIGNING_KEY).
 */
class AtualizacaoPublicar extends Command
{
    protected $signature = 'atualizacao:publicar
        {--versao= : versão a publicar (ex.: 1.2.0)}
        {--url= : URL do pacote (zip) da versão}
        {--sha256= : SHA-256 do pacote}
        {--min= : versão mínima instalada para poder saltar}
        {--notas= : notas de versão}
        {--obrigatorio : marca a atualização como obrigatória}
        {--rollout=none : none (só alvos) ou all (toda a gente)}
        {--secret= : chave privada Base64 (senão LICENSE_UPDATE_SIGNING_KEY)}';

    protected $description = 'Publica e assina uma versão para atualização';

    public function handle(): int
    {
        $secret = $this->option('secret') ?: config('licensing.update.signing_key');
        if (!$secret) {
            $this->error('Sem chave privada. Use --secret ou LICENSE_UPDATE_SIGNING_KEY.');

            return self::FAILURE;
        }

        foreach (['versao', 'url', 'sha256'] as $obrigatorio) {
            if (!$this->option($obrigatorio)) {
                $this->error("Falta --{$obrigatorio}.");

                return self::FAILURE;
            }
        }

        if (!in_array($this->option('rollout'), ['none', 'all'], true)) {
            $this->error('--rollout tem de ser none ou all.');

            return self::FAILURE;
        }

        $claims = array_filter([
            'versao'        => $this->option('versao'),
            'min_versao'    => $this->option('min'),
            'notas'         => $this->option('notas'),
            'pacote_url'    => $this->option('url'),
            'pacote_sha256' => strtolower($this->option('sha256')),
            'obrigatorio'   => (bool) $this->option('obrigatorio'),
        ], fn ($v) => $v !== null && $v !== '');

        $manifesto = (new UpdateSigner())->assinar($claims, $secret);

        $update = AppUpdate::updateOrCreate(
            ['versao' => $this->option('versao')],
            [
                'min_versao'    => $this->option('min'),
                'notas'         => $this->option('notas'),
                'pacote_url'    => $this->option('url'),
                'pacote_sha256' => strtolower($this->option('sha256')),
                'obrigatorio'   => (bool) $this->option('obrigatorio'),
                'rollout'       => $this->option('rollout'),
                'manifesto'     => $manifesto,
            ]
        );

        $this->info("Versão {$update->versao} publicada (rollout: {$update->rollout}).");
        $this->line('Manifesto assinado gravado. Use `atualizacao:alvo` para o canary por-tenant.');

        return self::SUCCESS;
    }
}
