<?php

namespace App\Services\Copias;

use App\Models\Copias\AgendaDeCopia;
use App\Models\Copias\CopiaDeSeguranca;
use App\Models\Copias\DestinoDeCopia;
use App\Models\Copias\RestauroDeCopia;
use App\Services\Copias\Destinos\Fornecedores;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * REPOR UMA CÓPIA — da pasta, de um destino, ou de um ficheiro carregado.
 *
 *   1. arranjar o ficheiro (da pasta; senão descarrega-se do destino);
 *   2. conferir o sha256 que ficou gravado quando a cópia foi feita;
 *   3. decifrar, se vier cifrada;
 *   4. TIRAR UMA CÓPIA DO ESTADO ACTUAL — sempre, antes de mexer em nada. É o
 *      caminho de volta se a cópia escolhida era a errada;
 *   5. repor (a base inteira pelo mysql; os dados de uma empresa pela
 *      transacção do DadosDaEmpresa, que recusa se houver documentos fiscais
 *      emitidos depois da cópia);
 *   6. o registo do restauro fica com o resultado, o erro ou a recusa.
 */
class RestaurarCopia
{
    public function __construct(
        private FazerCopia $fazer,
        private BaseInteira $base,
        private DadosDaEmpresa $dados,
    ) {
    }

    public function restaurar(RestauroDeCopia $restauro, ?string $frase = null, ?string $ficheiroCarregado = null): RestauroDeCopia
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $tenantId = $restauro->tenant_id;
        $trabalho = Pasta::doAmbito($tenantId) . '/.restauro-' . $restauro->id;
        @mkdir($trabalho, 0750, true);

        $tranca = Cache::lock(FazerCopia::tranca($tenantId), 3600);
        if (! $tranca->get()) {
            $restauro->forceFill(['estado' => 'falhou', 'erro' => __('Está uma cópia a ser feita neste momento. Tente de novo quando terminar.'), 'concluido_em' => now()])->save();

            return $restauro;
        }

        try {
            $restauro->forceFill(['estado' => 'a_correr', 'iniciado_em' => now()])->save();

            $ficheiro = $this->obterFicheiro($restauro, $trabalho, $ficheiroCarregado);

            // Decifrar: com a frase dada agora, ou com a da agenda.
            if (Cifra::estaCifrado($ficheiro)) {
                $frase ??= AgendaDeCopia::para($tenantId)->fraseEmClaro();
                if (! $frase) {
                    throw new RuntimeException(__('Esta cópia está cifrada: escreva a frase-passe para a repor.'));
                }
                $claro = $trabalho . '/copia-em-claro' . ($tenantId ? '.jsonl.gz' : '.sql.gz');
                Cifra::decifrar($ficheiro, $claro, $frase);
                $ficheiro = $claro;
            }

            // Para uma empresa, confere-se tudo ANTES de tirar a cópia prévia:
            // uma cópia recusada não deixa uma cópia prévia inútil para trás.
            if ($tenantId) {
                $conferido = $this->dados->conferir($ficheiro, $tenantId);
                if ($fiscais = $this->dados->fiscaisDepoisDe($tenantId, \Carbon\Carbon::parse($conferido['cabecalho']['criada_em']))) {
                    $restauro->forceFill([
                        'estado' => 'recusado',
                        'erro' => __('Depois desta cópia foram emitidos documentos fiscais: :lista. Não se podem apagar documentos entregues e comunicados — escolha uma cópia mais recente.', [
                            'lista' => collect($fiscais)->map(fn ($n, $r) => "{$r} ({$n})")->implode(', '),
                        ]),
                        'resumo' => ['documentos_depois' => $fiscais],
                        'concluido_em' => now(),
                    ])->save();

                    return $restauro;
                }
            }

            $tranca->release();
            $previa = $this->fazer->fazer($tenantId, 'antes_de_restaurar', $restauro->pedido_por);
            $restauro->forceFill(['copia_previa_id' => $previa->id])->save();

            if (! $tranca->get()) {
                throw new RuntimeException(__('Outra cópia começou entretanto. Tente de novo.'));
            }

            $resumo = $tenantId ? $this->dados->repor($ficheiro, $tenantId) : $this->base->repor($ficheiro);

            // Depois de repor a base inteira, este registo pode ter voltado atrás
            // no tempo: grava-se de novo, e o índice das cópias refaz-se da pasta.
            $restauro = RestauroDeCopia::find($restauro->id) ?? $restauro;
            if (! $tenantId) {
                Pasta::reindexar();
            }

            $restauro->forceFill(['estado' => 'concluido', 'resumo' => $resumo, 'erro' => null, 'concluido_em' => now()])->save();

            Log::warning('Cópia de segurança reposta', ['tenant_id' => $tenantId, 'restauro' => $restauro->id, 'por' => $restauro->pedido_por]);
        } catch (\Throwable $e) {
            $restauro->forceFill(['estado' => 'falhou', 'erro' => mb_strimwidth($e->getMessage(), 0, 1000, '…'), 'concluido_em' => now()])->save();
            Log::error('Restauro de cópia falhou', ['tenant_id' => $tenantId, 'restauro' => $restauro->id, 'erro' => $e->getMessage()]);
        } finally {
            $tranca->release();
            foreach (glob($trabalho . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($trabalho);
            if ($ficheiroCarregado) {
                @unlink($ficheiroCarregado);
            }
        }

        return $restauro;
    }

    private function obterFicheiro(RestauroDeCopia $restauro, string $trabalho, ?string $carregado): string
    {
        if ($carregado) {
            return $carregado;
        }

        $copia = $restauro->copia_id ? CopiaDeSeguranca::find($restauro->copia_id) : null;

        // Da pasta, se lá estiver.
        if ($copia && $copia->ficheiro_local && is_file(Pasta::caminho($copia))) {
            $caminho = Pasta::caminho($copia);
            $this->conferirSha($caminho, $copia);

            return $caminho;
        }

        // De um destino: o escolhido, ou o primeiro onde a cópia foi enviada.
        $destino = $restauro->destino_id ? DestinoDeCopia::find($restauro->destino_id) : null;
        $remoto = $restauro->ficheiro;

        if ($copia && ! $remoto) {
            $envio = $copia->envios()->where('estado', 'enviado')
                ->when($destino, fn ($q) => $q->where('destino_id', $destino->id))
                ->with('destino')->first();
            if (! $envio || ! $envio->destino) {
                throw new RuntimeException(__('Esta cópia já não está no servidor nem em nenhum destino.'));
            }
            $destino = $envio->destino;
            $remoto = $envio->remoto;
        }

        if (! $destino || ! $remoto) {
            throw new RuntimeException(__('Não foi possível encontrar o ficheiro da cópia.'));
        }
        if ((int) $destino->tenant_id !== (int) $restauro->tenant_id) {
            throw new RuntimeException(__('Esse destino não é deste âmbito.'));
        }

        $local = $trabalho . '/descarregada';
        Fornecedores::criar($destino)->descarregar((string) $remoto, $local);
        if ($copia) {
            $this->conferirSha($local, $copia);
        }

        return $local;
    }

    private function conferirSha(string $caminho, CopiaDeSeguranca $copia): void
    {
        if ($copia->sha256 && ! hash_equals($copia->sha256, hash_file('sha256', $caminho))) {
            throw new RuntimeException(__('O ficheiro da cópia não confere com o que foi gravado (sha256). Está corrompido ou foi alterado.'));
        }
    }
}
