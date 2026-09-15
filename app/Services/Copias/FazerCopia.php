<?php

namespace App\Services\Copias;

use App\Models\Copias\AgendaDeCopia;
use App\Models\Copias\CopiaDeSeguranca;
use App\Models\Copias\DestinoDeCopia;
use App\Models\Copias\EnvioDeCopia;
use App\Services\Copias\Destinos\Fornecedores;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * FAZER UMA CÓPIA — do princípio ao fim.
 *
 *   1. despejar (a base inteira, ou os dados de uma empresa) para a pasta;
 *   2. cifrar, se a agenda o pede, e apagar o ficheiro em claro;
 *   3. sha256 e o .json ao lado (Pasta);
 *   4. enviar para cada destino activo do âmbito — um que falhe não impede os
 *      outros, e fica o erro no envio e no destino;
 *   5. retenção: as mais antigas saem da pasta e de cada destino;
 *   6. a agenda marca a próxima.
 *
 * UMA DE CADA VEZ POR ÂMBITO: tranca atómica na cache. Dois pedidos a disparar
 * a mesma cópia ao mesmo tempo davam dois mysqldump a disputar o disco.
 */
class FazerCopia
{
    public function __construct(
        private BaseInteira $base,
        private DadosDaEmpresa $dados,
    ) {
    }

    public static function tranca(?int $tenantId): string
    {
        return 'copias:a-correr:' . ($tenantId ?? 'plataforma');
    }

    public function fazer(?int $tenantId, string $origem = 'automatica', ?int $pedidaPor = null, ?CopiaDeSeguranca $registo = null): CopiaDeSeguranca
    {
        $tranca = Cache::lock(self::tranca($tenantId), 3600);
        if (! $tranca->get()) {
            throw new RuntimeException(__('Já está uma cópia a ser feita. Aguarde que termine.'));
        }

        @set_time_limit(0);
        $agenda = AgendaDeCopia::para($tenantId);
        $cifrar = $agenda->cifrar && $agenda->fraseEmClaro();
        $nome = Pasta::nomeNovo($tenantId, (bool) $cifrar);

        $copia = $registo ?? CopiaDeSeguranca::create([
            'tenant_id' => $tenantId,
            'ficheiro' => $nome,
            'origem' => $origem,
            'estado' => 'a_correr',
            'pedida_por' => $pedidaPor,
            'iniciada_em' => now(),
        ]);
        if ($registo) {
            $copia->forceFill(['ficheiro' => $nome, 'estado' => 'a_correr', 'iniciada_em' => now()])->save();
        }

        $pasta = Pasta::doAmbito($tenantId);
        $final = $pasta . '/' . $nome;
        $claro = $cifrar ? $pasta . '/.a-cifrar-' . $nome . '.tmp' : $final;

        try {
            $resumo = $tenantId === null
                ? $this->despejarBase($claro)
                : $this->dados->exportar($tenantId, $claro);

            if ($cifrar) {
                Cifra::cifrar($claro, $final, $agenda->fraseEmClaro());
                @unlink($claro);
            }

            $copia->forceFill([
                'tamanho' => filesize($final),
                'sha256' => hash_file('sha256', $final),
                'cifrada' => (bool) $cifrar,
                'resumo' => $resumo,
                'estado' => 'concluida',
                'concluida_em' => now(),
                'erro' => null,
            ])->save();
            Pasta::gravarLado($copia);
        } catch (\Throwable $e) {
            @unlink($claro);
            @unlink($final);
            $copia->forceFill(['estado' => 'falhou', 'erro' => mb_strimwidth($e->getMessage(), 0, 1000, '…'), 'concluida_em' => now()])->save();
            $agenda->marcarFeita();
            $tranca->release();
            Log::error('Cópia de segurança falhou', ['tenant_id' => $tenantId, 'erro' => $e->getMessage()]);

            throw $e;
        }

        try {
            $this->enviar($copia);
            $this->reter($tenantId, $agenda);
        } finally {
            $agenda->marcarFeita();
            $tranca->release();
        }

        return $copia->fresh(['envios.destino']);
    }

    /** Envia uma cópia para os destinos activos do âmbito (ou só para um). */
    public function enviar(CopiaDeSeguranca $copia, ?DestinoDeCopia $so = null): void
    {
        $destinos = $so ? collect([$so]) : DestinoDeCopia::doAmbito($copia->tenant_id)->where('activo', true)->get();
        $local = Pasta::caminho($copia);

        foreach ($destinos as $destino) {
            $envio = EnvioDeCopia::firstOrCreate(['copia_id' => $copia->id, 'destino_id' => $destino->id], ['estado' => 'pendente']);

            try {
                $remoto = Fornecedores::criar($destino)->enviar($local, $copia->ficheiro);
                $envio->forceFill(['estado' => 'enviado', 'remoto' => $remoto, 'erro' => null, 'enviado_em' => now()])->save();
                $destino->forceFill(['ultimo_erro' => null, 'ligado' => true])->save();
            } catch (\Throwable $e) {
                $mensagem = mb_strimwidth($e->getMessage(), 0, 1000, '…');
                $envio->forceFill(['estado' => 'falhou', 'erro' => $mensagem])->save();
                $destino->forceFill(['ultimo_erro' => $mensagem])->save();
                Log::warning('Envio de cópia falhou', ['destino' => $destino->id, 'tipo' => $destino->tipo, 'erro' => $e->getMessage()]);
            }
        }
    }

    /**
     * As mais antigas saem. Nunca a mais recente concluída, e nunca a cópia
     * tirada antes de um restauro nas últimas 48 horas — é o caminho de volta.
     */
    public function reter(?int $tenantId, AgendaDeCopia $agenda): void
    {
        $locais = CopiaDeSeguranca::doAmbito($tenantId)->where('estado', 'concluida')->where('ficheiro_local', true)
            ->orderByDesc('concluida_em')->get();

        foreach ($locais->slice(max(1, (int) $agenda->manter_locais)) as $antiga) {
            if ($antiga->origem === 'antes_de_restaurar' && $antiga->concluida_em?->gt(now()->subHours(48))) {
                continue;
            }
            @unlink(Pasta::caminho($antiga));
            @unlink(Pasta::caminho($antiga) . '.json');
            $antiga->forceFill(['ficheiro_local' => false])->save();
        }

        foreach (DestinoDeCopia::doAmbito($tenantId)->get() as $destino) {
            $enviados = EnvioDeCopia::where('destino_id', $destino->id)->where('estado', 'enviado')->orderByDesc('enviado_em')->get();

            foreach ($enviados->slice(max(1, (int) $destino->manter)) as $envio) {
                try {
                    Fornecedores::criar($destino)->apagar((string) $envio->remoto);
                    $envio->forceFill(['estado' => 'apagado'])->save();
                } catch (\Throwable $e) {
                    Log::warning('Retenção no destino falhou', ['destino' => $destino->id, 'erro' => $e->getMessage()]);
                }
            }
        }

        // O registo de uma cópia que já não está em lado nenhum não serve de nada.
        CopiaDeSeguranca::doAmbito($tenantId)->where('ficheiro_local', false)
            ->whereDoesntHave('envios', fn ($q) => $q->where('estado', 'enviado'))
            ->where('concluida_em', '<', now()->subDays(1))
            ->delete();
    }

    private function despejarBase(string $destino): array
    {
        $this->base->despejar($destino);

        return ['tipo' => 'mysqldump', 'tamanho_comprimido' => filesize($destino)];
    }
}
