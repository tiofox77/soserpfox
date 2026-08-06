<?php

namespace App\Services\AGT;

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
