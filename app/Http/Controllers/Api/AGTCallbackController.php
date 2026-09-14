<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AGT\AGTSubmission;
use App\Models\AGT\AGTCommunicationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint de Callback da AGT — Facturação Electrónica
 *
 * A AGT faz POST para esta URL quando o processamento assíncrono
 * de um documento (registarFactura) é concluído.
 *
 * URL: POST /api/facturacaoelectronica/callback
 *
 * Nota: Funcionalidade "Disponível nas próximas versões" segundo
 *       documentação AGT v1.2. Endpoint preparado para quando activarem.
 *
 * Formato esperado (baseado no obterEstado response):
 * {
 *     "requestID": "202500000000118",
 *     "resultCode": "2",
 *     "taxRegistrationNumber": "5001636863",
 *     "documentStatusList": [...],
 *     "requestErrorList": [...]
 * }
 */
class AGTCallbackController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $ip = $request->ip();

        Log::channel('daily')->info('AGT Callback recebido', [
            'ip' => $ip,
            'payload' => $payload,
        ]);

        // Registar no log de comunicação
        $this->logCommunication($payload, $ip);

        $requestID = $payload['requestID'] ?? null;
        $resultCode = $payload['resultCode'] ?? null;

        if (empty($requestID)) {
            Log::warning('AGT Callback sem requestID', ['payload' => $payload]);
            return response()->json([
                'status' => 'error',
                'message' => 'requestID é obrigatório',
            ], 400);
        }

        // Localizar submissão pelo requestID (guardado em agt_reference)
        $submission = AGTSubmission::where('agt_reference', $requestID)->first();

        /*
         * A MESMA RESPOSTA, EXISTA OU NÃO O requestID.
         *
         * Respondia 200 «requestID não encontrado» a um e 202 «registado» ao
         * outro: a porta é pública, e bastava ir tentando números para saber
         * quais são pedidos verdadeiros deste sistema. A diferença fica no log.
         */
        if (!$submission) {
            Log::warning('AGT Callback: submissão não encontrada', [
                'requestID' => $requestID,
            ]);

            return $this->recebido();
        }

        /*
         * O CORPO DO CALLBACK NÃO DECIDE NADA.
         *
         * Esta porta não tem autenticação, assinatura nem lista de IPs da AGT (e
         * a AGT ainda não a activou). Quem soubesse um `requestID` — e os
         * funcionários da empresa vêem-nos no ecrã da AGT — marcava como
         * VALIDADO um documento que a AGT rejeitou (auditoria de segurança de
         * 2026-09-13). Fica registado; o estado verdadeiro continua a vir da
         * consulta que o próprio sistema faz à AGT (obterEstado), que é
         * autenticada. Quando a AGT publicar como assina o callback, verifica-se
         * a assinatura e volta a usar-se o `processResult`.
         */
        Log::info('AGT Callback registado sem alterar o estado (sem assinatura verificável).', [
            'requestID' => $requestID, 'submission' => $submission->id, 'resultCode' => $resultCode,
        ]);

        return $this->recebido();
    }

    private function recebido(): JsonResponse
    {
        return response()->json([
            'status' => 'received',
            'message' => 'Callback registado',
        ], 202);
    }

    private function processResult(AGTSubmission $submission, array $payload, ?string $resultCode): void
    {
        $requestID = $payload['requestID'] ?? null;
        $documentStatusList = $payload['documentStatusList'] ?? [];
        $requestErrorList = $payload['requestErrorList'] ?? [];

        // resultCode conforme documentação AGT:
        // 1 = Processado com sucesso (todos os documentos aceites)
        // 2 = Processado com sucesso (com documentos rejeitados)
        // 3 = Processado com sucesso (todos os documentos rejeitados)
        // 7 = requestID não encontrado
        // 8 = Pedido ainda em processamento
        // 9 = Erro interno

        $status = match ($resultCode) {
            '1' => AGTSubmission::STATUS_VALIDATED,
            '2' => AGTSubmission::STATUS_VALIDATED,
            '3' => AGTSubmission::STATUS_REJECTED,
            '7' => AGTSubmission::STATUS_REJECTED,
            '8' => AGTSubmission::STATUS_PENDING,
            '9' => AGTSubmission::STATUS_REJECTED,
            default => AGTSubmission::STATUS_PENDING,
        };

        $submission->update([
            'status' => $status,
            'response_payload' => $payload,
            'error_code' => $resultCode,
        ]);

        // Se validado, actualizar o documento associado
        if ($status === AGTSubmission::STATUS_VALIDATED) {
            foreach ($documentStatusList as $docStatus) {
                $atcud = $docStatus['atcud'] ?? null;
                if ($atcud && $submission->document) {
                    $submission->markAsValidated($requestID, $atcud, $payload);
                    break;
                }
            }
            if (empty($documentStatusList) && $submission->document) {
                $submission->update(['validated_at' => now()]);
            }
        } elseif ($status === AGTSubmission::STATUS_REJECTED) {
            $errorMsg = !empty($requestErrorList) ? implode('; ', $requestErrorList) : "resultCode: {$resultCode}";
            $submission->update([
                'error_message' => $errorMsg,
                'rejected_at' => now(),
            ]);
        }

        // Log individual de cada documento no callback
        foreach ($documentStatusList as $docStatus) {
            $documentNo = $docStatus['documentNo'] ?? null;
            if ($documentNo) {
                Log::info('AGT Callback documento processado', [
                    'requestID' => $requestID,
                    'documentNo' => $documentNo,
                    'resultCode' => $docStatus['resultCode'] ?? null,
                    'errors' => $docStatus['errorList'] ?? [],
                ]);
            }
        }

        Log::info('AGT Callback processado', [
            'requestID' => $requestID,
            'resultCode' => $resultCode,
            'status' => $status,
            'submission_id' => $submission->id,
        ]);
    }

    private function logCommunication(array $payload, string $ip): void
    {
        try {
            AGTCommunicationLog::create([
                'tenant_id' => null,
                'service' => 'callback',
                'method' => 'POST',
                'endpoint' => '/api/facturacaoelectronica/callback',
                'request_body' => $payload,
                'response_body' => ['status' => 'received'],
                'response_status' => 200,
                'success' => true,
                'ip_address' => $ip,
            ]);
        } catch (\Exception $e) {
            Log::warning('AGT Callback: falha ao gravar log', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
