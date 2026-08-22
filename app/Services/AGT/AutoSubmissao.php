<?php

namespace App\Services\AGT;

use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Envio automático de um documento à AGT, num sítio só.
 *
 * Estava copiado por seis lados — POS, sincronização do PWA, facturas, notas
 * de crédito, notas de débito e os módulos — cada um com a sua versão da
 * verificação, do tratamento de erro e do registo. E faltava nos recibos, que
 * são documento fiscal como os outros e simplesmente nunca eram enviados.
 *
 * Duas regras que cada cópia tinha de acertar por si, e que agora valem para
 * todas:
 *
 *   · submete-se sempre uma instância FRESCA. O documento é gravado antes das
 *     suas linhas, e quem corre no evento `created` lê a colecção `items`
 *     nesse momento — vazia. O Eloquent guarda-a assim e nunca mais a
 *     consulta, e o documento seguia para a AGT com os totais preenchidos e
 *     zero linhas, que ela recusa.
 *
 *   · uma falha da AGT NUNCA desfaz nem impede o documento. Ele já está
 *     gravado e é válido; a comunicação é assíncrona por desenho e o que
 *     falhar fica por reenviar.
 */
class AutoSubmissao
{
    /**
     * Envia o documento se a empresa tiver o envio automático ligado.
     *
     * @return array{enviado:bool, requestID:?string, erro:?string}
     */
    public static function submeter(Model $documento, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?: (int) ($documento->tenant_id ?: activeTenantId());

        if (!$tenantId) {
            return ['enviado' => false, 'requestID' => null, 'erro' => 'Sem empresa activa.'];
        }

        $definicoes = InvoicingSettings::forTenant($tenantId);

        if (empty($definicoes->agt_auto_submit)) {
            return ['enviado' => false, 'requestID' => null, 'erro' => null];
        }

        $numero = self::numero($documento);

        try {
            // fresh(): ver a nota acima sobre a colecção em cache.
            $resultado = (new AGTService($tenantId))->submitToAGT($documento->fresh() ?: $documento);

            $ok = (bool) ($resultado['success'] ?? false);

            Log::info('AGT: envio automático', [
                'tenant_id' => $tenantId,
                'documento' => $numero,
                'tipo'      => class_basename($documento),
                'sucesso'   => $ok,
                'requestID' => $resultado['requestID'] ?? null,
                'erro'      => $resultado['error'] ?? null,
            ]);

            return [
                'enviado'   => $ok,
                'requestID' => $resultado['requestID'] ?? null,
                'erro'      => $ok ? null : ($resultado['error'] ?: 'A AGT não aceitou o documento.'),
            ];
        } catch (\Throwable $e) {
            // O documento fica gravado e por enviar. Rebentar aqui deixaria o
            // utilizador sem saber se a venda se fez.
            Log::error('AGT: envio automático falhou', [
                'tenant_id' => $tenantId,
                'documento' => $numero,
                'tipo'      => class_basename($documento),
                'erro'      => $e->getMessage(),
            ]);

            return ['enviado' => false, 'requestID' => null, 'erro' => $e->getMessage()];
        }
    }

    /**
     * ENFILEIRA um documento para a AGT — grava-o como pendente e não o envia.
     *
     * É a metade que faltava para separar as duas coisas que nunca deviam ter
     * andado juntas: gravar a factura e comunicá-la ao fisco. Emitir uma FT
     * chamava `submitToAGT()` no mesmo pedido, e o utilizador ficava à espera
     * da AGT — que num dia mau responde a 8 segundos, ou não responde. A venda
     * já estava feita; não há razão para prender quem a fez enquanto o fisco
     * pensa.
     *
     * Aqui cria-se apenas a `AGTSubmission` PENDENTE. Quem a envia é o
     * DespachoPendentes, à boleia do tráfego, depois de a resposta já ter
     * seguido para o browser. O documento sai na mesma — só que sem ninguém
     * a olhar para a ampulheta.
     *
     * Idempotente: se já houver submissão validada ou pendente para este
     * documento, não cria outra. Uma factura não pode ser comunicada duas
     * vezes.
     *
     * @return array{enfileirado:bool, jaEnviado:bool, erro:?string}
     */
    public static function enfileirar(Model $documento, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?: (int) ($documento->tenant_id ?: activeTenantId());

        if (!$tenantId) {
            return ['enfileirado' => false, 'jaEnviado' => false, 'erro' => 'Sem empresa activa.'];
        }

        $definicoes = InvoicingSettings::forTenant($tenantId);

        // Sem envio automático, não se enfileira nada — quem comunica é o
        // utilizador, à mão, quando decidir.
        if (empty($definicoes->agt_auto_submit)) {
            return ['enfileirado' => false, 'jaEnviado' => false, 'erro' => null];
        }

        try {
            $existente = AGTSubmission::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('document_type', get_class($documento))
                ->where('document_id', $documento->id)
                ->whereIn('status', [
                    AGTSubmission::STATUS_PENDING,
                    AGTSubmission::STATUS_SUBMITTED,
                    AGTSubmission::STATUS_VALIDATED,
                ])
                ->first();

            // Já está tratado — pendente, enviado ou validado. Não duplicar.
            if ($existente) {
                return [
                    'enfileirado' => false,
                    'jaEnviado'   => in_array($existente->status, [
                        AGTSubmission::STATUS_SUBMITTED,
                        AGTSubmission::STATUS_VALIDATED,
                    ], true),
                    'erro' => null,
                ];
            }

            // Só o CÓDIGO do tipo (FT, FR, …) — não se mapeia nem se assina o
            // documento agora; isso fica para o envio, à boleia do tráfego.
            $tipo = (new DocumentMapper())->documentTypeCode($documento);

            AGTSubmission::createForDocument($documento->fresh() ?: $documento, $tipo);

            return ['enfileirado' => true, 'jaEnviado' => false, 'erro' => null];
        } catch (\Throwable $e) {
            // O documento fica gravado. Não conseguir enfileirar não pode
            // desfazer a venda — fica por comunicar e vê-se no ecrã da AGT.
            Log::error('AGT: não foi possível enfileirar', [
                'tenant_id' => $tenantId,
                'documento' => self::numero($documento),
                'tipo'      => class_basename($documento),
                'erro'      => $e->getMessage(),
            ]);

            return ['enfileirado' => false, 'jaEnviado' => false, 'erro' => $e->getMessage()];
        }
    }

    private static function numero(Model $documento): string
    {
        foreach ([
            'invoice_number', 'credit_note_number', 'debit_note_number',
            'receipt_number', 'guide_number', 'advance_number',
        ] as $campo) {
            if (!empty($documento->{$campo})) {
                return (string) $documento->{$campo};
            }
        }

        return '(sem número)';
    }
}
