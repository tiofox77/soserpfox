<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;

/**
 * O lado do EMISSOR (vendor): gera o par de chaves e assina licenças.
 *
 * Isto corre em quem gere a plataforma, NUNCA na máquina do cliente — precisa
 * da chave privada. O cliente só tem o LicenseVerifier e a chave pública.
 *
 * Formato do token (4 partes separadas por ponto, tudo em base64url):
 *
 *     SOSERP-LIC . v1 . <payload> . <assinatura>
 *
 * A assinatura Ed25519 é feita sobre a string `<payload>` exactamente como
 * viaja — não sobre o JSON recodificado. Assim não há ambiguidade de
 * canonicalização entre assinar e verificar.
 */
class LicenseIssuer
{
    public const PREFIXO = 'SOSERP-LIC';
    public const VERSAO  = 'v1';

    /**
     * Gera um par Ed25519. Guarda a `privada` em cofre (fora do repo!) e
     * distribui a `publica` (config/licensing.php ou .env do cliente).
     *
     * @return array{publica:string, privada:string} ambos em Base64
     */
    public static function gerarParDeChaves(): array
    {
        $par = sodium_crypto_sign_keypair();

        return [
            'publica' => base64_encode(sodium_crypto_sign_publickey($par)),
            'privada' => base64_encode(sodium_crypto_sign_secretkey($par)),
        ];
    }

    /**
     * Assina um conjunto de claims e devolve o token da licença.
     *
     * @param array  $claims          tenant_id, empresa, nif, plano, modulos[],
     *                                 exp (unix), fp (fingerprint), graca…
     * @param string $chavePrivadaB64 a chave privada Ed25519 em Base64
     */
    /** A cripto Ed25519 está disponível? (extensão sodium ou o polyfill) */
    public static function criptoDisponivel(): bool
    {
        return function_exists('sodium_crypto_sign_detached')
            && defined('SODIUM_CRYPTO_SIGN_SECRETKEYBYTES');
    }

    public function emitir(array $claims, string $chavePrivadaB64): string
    {
        // Sem isto, a falta da extensão sodium rebentava com um
        // "Undefined constant" no meio de um pedido — erro que não diz a
        // ninguém o que fazer. O alojamento pode não ter a extensão; nesse
        // caso vale o polyfill paragonie/sodium_compat.
        if (!self::criptoDisponivel()) {
            throw new \RuntimeException(
                'Cripto Ed25519 indisponível: falta a extensão PHP "sodium" '
                . '(ou o pacote paragonie/sodium_compat) neste servidor.'
            );
        }

        $secret = base64_decode(trim($chavePrivadaB64), true);

        // Diagnóstico em vez de "chave inválida": o erro mais comum é colar a
        // chave PÚBLICA no lugar da privada (são as duas base64 e parecem-se).
        // Dizer o tamanho encontrado poupa meia hora a quem está a configurar.
        if ($secret === false) {
            throw new \InvalidArgumentException(
                'LICENSE_SIGNING_KEY não é Base64 válido. Copie a chave PRIVADA inteira '
                . '(88 caracteres), sem espaços nem quebras de linha.'
            );
        }

        if (strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            $pista = strlen($secret) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                ? ' Isto é a chave PÚBLICA (32 bytes / 44 caracteres) — o LICENSE_SIGNING_KEY precisa da chave PRIVADA (64 bytes / 88 caracteres).'
                : ' Verifique se a chave foi copiada por inteiro.';

            throw new \InvalidArgumentException(
                'LICENSE_SIGNING_KEY inválida: tem ' . strlen($secret) . ' bytes, esperados '
                . SODIUM_CRYPTO_SIGN_SECRETKEYBYTES . ' (Ed25519).' . $pista
            );
        }

        $claims['v']   = 1;
        $claims['iat'] = $claims['iat'] ?? CarbonImmutable::now()->getTimestamp();
        if (empty($claims['lic'])) {
            $claims['lic'] = self::gerarId();
        }

        $json = json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadB64 = Base64Url::encode($json);

        $assinatura = sodium_crypto_sign_detached($payloadB64, $secret);

        return implode('.', [
            self::PREFIXO,
            self::VERSAO,
            $payloadB64,
            Base64Url::encode($assinatura),
        ]);
    }

    /** UUID v4 simples para identificar a licença emitida. */
    private static function gerarId(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
