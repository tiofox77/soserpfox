<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;

/**
 * Assina manifestos de atualização (lado VENDOR). Mesmo desenho da licença:
 * Ed25519, assinatura sobre os bytes que viajam, sem ambiguidade.
 *
 * Token: SOSERP-UPD.v1.<payload>.<assinatura>
 * Payload: { v, versao, min_versao, notas, pacote_url, pacote_sha256,
 *            obrigatorio, iat }
 */
class UpdateSigner
{
    public const PREFIXO = 'SOSERP-UPD';
    public const VERSAO  = 'v1';

    public function assinar(array $claims, string $chavePrivadaB64): string
    {
        $secret = base64_decode($chavePrivadaB64, true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \InvalidArgumentException('Chave privada inválida (Ed25519 em Base64).');
        }

        $claims['v']   = 1;
        $claims['iat'] = $claims['iat'] ?? CarbonImmutable::now()->getTimestamp();

        $json = json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadB64 = Base64Url::encode($json);
        $sig = sodium_crypto_sign_detached($payloadB64, $secret);

        return implode('.', [self::PREFIXO, self::VERSAO, $payloadB64, Base64Url::encode($sig)]);
    }
}
