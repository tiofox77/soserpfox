<?php

namespace App\Services\AGT;

use App\Helpers\SAFTHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de Assinatura Digital AGT
 * Decreto Presidencial n.º 71/25 - Angola
 * 
 * Gera assinaturas JWS (JSON Web Signature) conforme especificação AGT
 * Utiliza chaves RSA-2048 existentes do SAFTHelper
 */
class SignatureService
{
    private ?string $privateKey = null;
    private ?string $publicKey = null;

    public function __construct()
    {
        $this->loadKeys();
    }

    // =========================================
    // CARREGAMENTO DE CHAVES
    // =========================================

    private function loadKeys(): void
    {
        if (Storage::disk('local')->exists('saft/private_key.pem')) {
            $this->privateKey = Storage::disk('local')->get('saft/private_key.pem');
        }
        
        if (Storage::disk('local')->exists('saft/public_key.pem')) {
            $this->publicKey = Storage::disk('local')->get('saft/public_key.pem');
        }
    }

    public function hasKeys(): bool
    {
        return SAFTHelper::keysExist();
    }

    // =========================================
    // ASSINATURA JWS
    // =========================================

    /**
     * Assinar documento com JWS (JSON Web Signature)
     * Formato: header.payload.signature (Base64URL)
     */
    public function signDocument($document): ?string
    {
        if (!$this->privateKey) {
            Log::warning('SignatureService: Chave privada não encontrada');
            return null;
        }

        try {
            // Header JWS
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWS',
            ];

            // Payload com dados obrigatórios do documento
            $payload = $this->buildPayload($document);

            // Codificar header e payload em Base64URL
            $headerEncoded = $this->base64UrlEncode(json_encode($header));
            $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

            // Dados a assinar
            $dataToSign = $headerEncoded . '.' . $payloadEncoded;

            // Assinar com RSA-SHA256
            $pkeyId = openssl_pkey_get_private($this->privateKey);
            if (!$pkeyId) {
                throw new \Exception('Erro ao carregar chave privada: ' . openssl_error_string());
            }

            $signature = '';
            $signed = openssl_sign($dataToSign, $signature, $pkeyId, OPENSSL_ALGO_SHA256);

            if (!$signed) {
                throw new \Exception('Erro ao assinar documento: ' . openssl_error_string());
            }

            // JWS completo: header.payload.signature
            $jws = $dataToSign . '.' . $this->base64UrlEncode($signature);

            return $jws;

        } catch (\Exception $e) {
            Log::error('SignatureService: Erro ao assinar documento', [
                'error' => $e->getMessage(),
                'document_id' => $document->id ?? null,
            ]);
            return null;
        }
    }

    /**
     * Construir payload JWS conforme especificação AGT
     */
    private function buildPayload($document): array
    {
        $documentNumber = $document->invoice_number 
            ?? $document->credit_note_number 
            ?? $document->debit_note_number 
            ?? $document->receipt_number;

        $nifEmissor = $document->tenant?->nif 
            ?? $document->tenant?->tax_id 
            ?? '';

        $nifCliente = $document->client?->nif ?? '999999999';

        $invoiceDate = $document->invoice_date 
            ?? $document->issue_date 
            ?? now();

        $systemEntryDate = $document->system_entry_date ?? now();

        return [
            'iss' => $nifEmissor, // NIF emissor
            'iat' => time(), // Timestamp de emissão
            'doc' => [
                'type' => $document->invoice_type ?? $this->getDocumentType($document),
                'number' => $documentNumber,
                'date' => $invoiceDate->format('Y-m-d'),
                'system_date' => $systemEntryDate->format('Y-m-d\TH:i:s'),
                'customer_nif' => $nifCliente,
                'gross_total' => round($document->gross_total ?? $document->total, 2),
                'net_total' => round($document->net_total ?? $document->subtotal, 2),
                'tax_amount' => round($document->tax_amount, 2),
            ],
            'hash' => $document->hash ?? $document->saft_hash,
            'hash_previous' => $document->hash_previous ?? '',
        ];
    }

    private function getDocumentType($document): string
    {
        $class = get_class($document);
        
        return match (true) {
            str_contains($class, 'SalesInvoice') => 'FT',
            str_contains($class, 'CreditNote') => 'NC',
            str_contains($class, 'DebitNote') => 'ND',
            str_contains($class, 'Receipt') => 'RC',
            str_contains($class, 'Proforma') => 'FP',
            default => 'FT',
        };
    }

    // =========================================
    // VERIFICAÇÃO DE ASSINATURA
    // =========================================

    /**
     * Verificar assinatura JWS
     */
    public function verifySignature(string $jws): array
    {
        if (!$this->publicKey) {
            return [
                'valid' => false,
                'error' => 'Chave pública não encontrada',
            ];
        }

        try {
            // Separar componentes do JWS
            $parts = explode('.', $jws);
            if (count($parts) !== 3) {
                return [
                    'valid' => false,
                    'error' => 'Formato JWS inválido',
                ];
            }

            [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

            // Decodificar
            $header = json_decode($this->base64UrlDecode($headerEncoded), true);
            $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
            $signature = $this->base64UrlDecode($signatureEncoded);

            // Verificar algoritmo
            if (($header['alg'] ?? '') !== 'RS256') {
                return [
                    'valid' => false,
                    'error' => 'Algoritmo não suportado',
                ];
            }

            // Verificar assinatura
            $dataToVerify = $headerEncoded . '.' . $payloadEncoded;
            $pubkeyId = openssl_pkey_get_public($this->publicKey);

            if (!$pubkeyId) {
                return [
                    'valid' => false,
                    'error' => 'Erro ao carregar chave pública',
                ];
            }

            $result = openssl_verify($dataToVerify, $signature, $pubkeyId, OPENSSL_ALGO_SHA256);

            return [
                'valid' => $result === 1,
                'header' => $header,
                'payload' => $payload,
                'error' => $result === 1 ? null : 'Assinatura inválida',
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================================
    // ASSINAR E GUARDAR NO DOCUMENTO
    // =========================================

    /**
     * Assinar documento e guardar assinatura
     */
    public function signAndSave($document): bool
    {
        $jws = $this->signDocument($document);
        
        if (!$jws) {
            return false;
        }

        try {
            $document->update([
                'jws_signature' => $jws,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('SignatureService: Erro ao guardar assinatura', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
            ]);
            return false;
        }
    }

    // =========================================
    // HELPERS BASE64URL
    // =========================================

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // =========================================
    // HASH SAFT (USAR EXISTENTE)
    // =========================================

    /**
     * Gerar hash SAFT usando o helper existente
     */
    public function generateHash($document): ?string
    {
        $invoiceDate = $document->invoice_date ?? $document->issue_date ?? now();
        $systemEntryDate = $document->system_entry_date ?? now();
        $documentNumber = $document->invoice_number 
            ?? $document->credit_note_number 
            ?? $document->debit_note_number;
        $grossTotal = $document->gross_total ?? $document->total;
        $previousHash = $document->hash_previous ?? '';

        return SAFTHelper::generateHash(
            $invoiceDate->format('Y-m-d'),
            $systemEntryDate->format('Y-m-d H:i:s'),
            $documentNumber,
            $grossTotal,
            $previousHash
        );
    }

    /**
     * Gerar hash e JWS para documento
     */
    public function signComplete($document): array
    {
        // 1. Gerar hash SAFT
        $hash = $this->generateHash($document);
        
        if ($hash) {
            $document->hash = $hash;
            $document->saft_hash = $hash;
        }

        // 2. Gerar assinatura JWS
        $jws = $this->signDocument($document);

        // 3. Guardar
        $document->update([
            'hash' => $hash,
            'saft_hash' => $hash,
            'jws_signature' => $jws,
        ]);

        return [
            'hash' => $hash,
            'jws_signature' => $jws,
            'success' => !empty($hash) && !empty($jws),
        ];
    }
}
