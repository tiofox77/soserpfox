<?php

namespace App\Services\AGT;

use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Support\Facades\Log;

/**
 * Trata as submissões que ficaram por concluir, sem depender da fila.
 *
 * A fila do Laravel exige um worker a correr. Neste alojamento não há, e o
 * resultado media-se: 165 tarefas paradas e 160 documentos presos em
 * "Enviada" sem que ninguém soubesse o desfecho — nem que a AGT os tinha
 * recusado.
 *
 * Aqui não há fila. O trabalho é feito em pequenas doses, aproveitando as
 * visitas: qualquer utilizador a abrir uma página do sistema faz andar um
 * bocado da lista. Corre DEPOIS de a resposta seguir para o browser, por isso
 * ninguém espera por ele.
 *
 * Duas coisas a fazer, e por esta ordem de importância:
 *
 *   1. ENVIAR o que nunca foi (pending). Um documento por comunicar é o
 *      problema fiscal; saber o desfecho de um já enviado pode esperar.
 *   2. PERGUNTAR o desfecho do que já seguiu (submitted). A AGT é assíncrona:
 *      devolve um requestID na hora e o veredicto só se sabe consultando.
 */
class DespachoPendentes
{
    /** Quantos de cada vez. Poucos: isto corre em pedidos de utilizadores. */
    public const POR_ENVIAR   = 3;
    public const POR_CONSULTAR = 5;

    /**
     * Não insistir para sempre num documento que a AGT nunca aceita.
     *
     * Sem tecto, um documento com um defeito de fundo seria retentado a cada
     * visita, para sempre, e a lista nunca esvaziava.
     */
    public const TENTATIVAS_MAX = 5;

    /**
     * @return array{enviados:int, consultados:int}
     */
    public function correr(int $tenantId): array
    {
        if (!$tenantId) {
            return ['enviados' => 0, 'consultados' => 0];
        }

        $definicoes = InvoicingSettings::forTenant($tenantId);
        $ambiente = GestaoAgt::normalizar($definicoes->agt_environment);

        // Sem envio automático, não se envia nada por iniciativa própria — mas
        // consulta-se na mesma o que já seguiu, que é só leitura e é o que
        // tira os documentos de "Enviada".
        $enviados = empty($definicoes->agt_auto_submit) ? 0 : $this->enviarPendentes($tenantId, $ambiente);

        return [
            'enviados'    => $enviados,
            'consultados' => $this->consultarSubmetidos($tenantId, $definicoes, $ambiente),
        ];
    }

    /**
     * SÓ AS DO AMBIENTE ACTIVO — nas duas metades.
     *
     * O envio e a consulta falam sempre com a AGT do ambiente activo. Com a
     * empresa de volta a homologação, os documentos de produção por enviar
     * seguiam para a AGT de testes e voltavam «validados»; e um `requestID` de
     * produção perguntado à de testes não diz nada de verdadeiro. As do outro
     * ambiente ficam como estão até a empresa lá voltar.
     */
    private function enviarPendentes(int $tenantId, string $ambiente): int
    {
        $pendentes = AGTSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->doAmbiente($ambiente)
            ->where('status', AGTSubmission::STATUS_PENDING)
            ->where('retry_count', '<', self::TENTATIVAS_MAX)
            ->orderBy('id')
            ->limit(self::POR_ENVIAR)
            ->get();

        $feitos = 0;

        foreach ($pendentes as $submissao) {
            $documento = $submissao->document;

            if (!$documento) {
                // O documento desapareceu; a submissão não tem para onde ir.
                $submissao->update([
                    'status'        => AGTSubmission::STATUS_REJECTED,
                    'error_code'    => 'SEM_DOCUMENTO',
                    'error_message' => 'O documento associado já não existe.',
                ]);
                continue;
            }

            try {
                (new AGTService($tenantId))->submitToAGT($documento->fresh() ?: $documento);
                $feitos++;
            } catch (\Throwable $e) {
                // Conta a tentativa para não ficar preso neste documento.
                $submissao->increment('retry_count');
                $submissao->update(['error_message' => $e->getMessage()]);

                Log::warning('AGT: despacho não conseguiu enviar', [
                    'tenant_id'     => $tenantId,
                    'submission_id' => $submissao->id,
                    'erro'          => $e->getMessage(),
                ]);
            }
        }

        return $feitos;
    }

    private function consultarSubmetidos(int $tenantId, InvoicingSettings $definicoes, string $ambiente): int
    {
        $submetidas = AGTSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->doAmbiente($ambiente)
            ->where('status', AGTSubmission::STATUS_SUBMITTED)
            ->whereNotNull('agt_reference')
            // As mais recentes primeiro: é o desfecho delas que interessa a
            // quem está a trabalhar agora.
            ->orderByDesc('id')
            ->limit(self::POR_CONSULTAR)
            ->get();

        if ($submetidas->isEmpty()) {
            return 0;
        }

        $servico = new QueryService($definicoes);
        $feitos = 0;

        foreach ($submetidas as $submissao) {
            try {
                $resultado = $servico->consultByRequestId($submissao->agt_reference);
                $codigo = (string) ($resultado['resultCode'] ?? '');
                $corpo  = $resultado['response'] ?? [];

                if ($codigo === '0') {
                    $submissao->markAsValidated($submissao->agt_reference, $submissao->atcud, $corpo);
                    $feitos++;
                } elseif ($codigo === '2') {
                    // Os erros do documento vêm em documentStatusList[].errorList;
                    // o código real (E43, E70…) fica gravado, e o AGT_ESTADO só
                    // quando a AGT não diz nenhum.
                    $submissao->markAsRejected(
                        AGTErrorCode::primeiroCodigo($corpo) ?? 'AGT_ESTADO',
                        AGTErrorCode::formatarResposta($corpo) ?: 'Documento recusado pela AGT.',
                        $corpo
                    );
                    $feitos++;
                }
                // Outro código: ainda em processamento. Fica como está.
            } catch (\Throwable $e) {
                // Falha de rede a consultar não muda nada: tenta-se na próxima.
                Log::warning('AGT: despacho não conseguiu consultar', [
                    'tenant_id'     => $tenantId,
                    'submission_id' => $submissao->id,
                    'erro'          => $e->getMessage(),
                ]);
            }
        }

        return $feitos;
    }

    /** Há trabalho por fazer? Barato, para não pagar o resto à toa. */
    public static function temTrabalho(int $tenantId): bool
    {
        $ambiente = GestaoAgt::normalizar(InvoicingSettings::forTenant($tenantId)->agt_environment);

        return AGTSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->doAmbiente($ambiente)
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('status', AGTSubmission::STATUS_PENDING)
                      ->where('retry_count', '<', self::TENTATIVAS_MAX);
                })->orWhere(function ($q) {
                    $q->where('status', AGTSubmission::STATUS_SUBMITTED)
                      ->whereNotNull('agt_reference');
                });
            })
            ->exists();
    }
}
