<?php

namespace App\Services\AGT;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * AGT v1.2 — JWS RS256 Signer
 *
 * Implementa as 3 assinaturas canónicas exigidas pela AGT:
 *  1) jwsSoftwareSignature  → assina {productId, productVersion, softwareValidationNumber}
 *  2) jwsDocumentSignature  → assina campos canónicos do documento
 *  3) jwsSignature          → assina os campos canónicos do pedido (request)
 *
 * Formato: header.payload.signature  (Base64URL, RS256)
 * Header sempre: {"typ":"JOSE","alg":"RS256"}
 *
 * Spec: https://quiosqueagt.minfin.gov.ao/doc-agt/faturacao-electronica/1/gestao.html
 */
class JwsSigner
{
    /** Header canónico AGT. */
    public const HEADER = ['typ' => 'JOSE', 'alg' => 'RS256'];

    private ?string $privateKey;
    private ?string $publicKey;

    public function __construct(?string $privateKey = null, ?string $publicKey = null)
    {
        $this->privateKey = $privateKey ?? $this->loadKey('saft/private_key.pem');
        $this->publicKey  = $publicKey  ?? $this->loadKey('saft/public_key.pem');
    }

    /** Assina um payload (array) e devolve string JWS RS256. */
    public function sign(array $payload): string
    {
        if (!$this->privateKey) {
            throw new \RuntimeException('Chave privada RSA não configurada (saft/private_key.pem).');
        }

        $headerEnc  = self::base64UrlEncode($this->jsonCanonical(self::HEADER));
        $payloadEnc = self::base64UrlEncode($this->jsonCanonical($payload));
        $signing    = $headerEnc . '.' . $payloadEnc;

        $signature = '';
        $ok = openssl_sign($signing, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new \RuntimeException('openssl_sign falhou: ' . openssl_error_string());
        }

        return $signing . '.' . self::base64UrlEncode($signature);
    }

    /** Verifica um JWS e devolve [valid, header, payload, error]. */
    public function verify(string $jws, ?string $publicKey = null): array
    {
        $publicKey = $publicKey ?? $this->publicKey;
        if (!$publicKey) {
            return ['valid' => false, 'error' => 'Chave pública não configurada'];
        }

        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'Formato JWS inválido'];
        }
        [$h, $p, $s] = $parts;

        $sigBin  = self::base64UrlDecode($s);
        $signing = $h . '.' . $p;
        $result  = openssl_verify($signing, $sigBin, $publicKey, OPENSSL_ALGO_SHA256);

        return [
            'valid'   => $result === 1,
            'header'  => json_decode(self::base64UrlDecode($h), true),
            'payload' => json_decode(self::base64UrlDecode($p), true),
            'error'   => $result === 1 ? null : 'Assinatura inválida',
        ];
    }

    // ============================================================
    // ASSINATURAS CANÓNICAS AGT v1.2
    // ============================================================

    /**
     * jwsSoftwareSignature — assina os 3 campos do softwareInfoDetail.
     * Ordem canónica obrigatória: productId, productVersion, softwareValidationNumber.
     */
    public function signSoftware(array $softwareInfoDetail): string
    {
        $canonical = [
            'productId'                 => (string) ($softwareInfoDetail['productId'] ?? ''),
            'productVersion'            => (string) ($softwareInfoDetail['productVersion'] ?? ''),
            'softwareValidationNumber'  => (string) ($softwareInfoDetail['softwareValidationNumber'] ?? ''),
        ];
        return $this->sign($canonical);
    }

    /**
     * jwsDocumentSignature — assina campos canónicos do documento.
     * Spec AGT: documentNo, taxRegistrationNumber, documentType, documentDate,
     *           customerTaxID, customerCountry, companyName.
     */
    public function signDocument(array $document, string $issuerNif): string
    {
        $canonical = [
            'documentNo'             => (string) ($document['documentNo'] ?? ''),
            'taxRegistrationNumber'  => (string) $issuerNif,
            'documentType'           => (string) ($document['documentType'] ?? ''),
            'documentDate'           => (string) ($document['documentDate'] ?? ''),
            'customerTaxID'          => (string) ($document['customerTaxID'] ?? ''),
            'customerCountry'        => (string) ($document['customerCountry'] ?? ''),
            'companyName'            => (string) ($document['companyName'] ?? ''),
        ];
        return $this->sign($canonical);
    }

    /**
     * jwsSignature — assina os campos canónicos do request, dependendo do serviço.
     *
     * Para SolicitarSerie:
     *   {taxRegistrationNumber, seriesYear, documentType, establishmentNumber, seriesContingencyIndicator}
     *
     * Para ConsultarFactura:
     *   {taxRegistrationNumber, documentNo}
     *
     * Para RegistarFactura:
     *   {taxRegistrationNumber, numberOfEntries, submissionTimeStamp}
     */
    public function signRequest(array $canonicalFields): string
    {
        // Garantir que os valores são strings/numéricos primitivos
        $cleaned = [];
        foreach ($canonicalFields as $k => $v) {
            $cleaned[$k] = is_scalar($v) || $v === null ? (string) $v : json_encode($v);
        }
        return $this->sign($cleaned);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /** JSON canónico: sem espaços, sem escape Unicode/slashes, ordem preservada. */
    private function jsonCanonical(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function loadKey(string $path): ?string
    {
        try {
            return Storage::disk('local')->exists($path)
                ? Storage::disk('local')->get($path)
                : null;
        } catch (\Throwable $e) {
            Log::warning("JwsSigner: falha ao carregar {$path}: " . $e->getMessage());
            return null;
        }
    }

    public function hasKeys(): bool
    {
        return !empty($this->privateKey) && !empty($this->publicKey);
    }
}
