<?php

namespace App\Services\Licensing;

/**
 * Verifica manifestos de atualização (lado CLIENTE). Falha fechado: assinatura
 * má, formato mau ou versão desconhecida → null (não se aplica nada).
 */
class UpdateVerifier
{
    public function __construct(private string $chavePublicaB64)
    {
    }

    /** Devolve os claims do manifesto SÓ se a assinatura for válida. */
    public function ler(string $manifesto): ?array
    {
        $partes = explode('.', trim($manifesto));
        if (count($partes) !== 4) {
            return null;
        }

        [$prefixo, $versao, $payloadB64, $sigB64] = $partes;
        if ($prefixo !== UpdateSigner::PREFIXO || $versao !== UpdateSigner::VERSAO) {
            return null;
        }

        $pub = base64_decode($this->chavePublicaB64, true);
        if ($pub === false || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        $sig = Base64Url::decode($sigB64);
        if (strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        try {
            if (!sodium_crypto_sign_verify_detached($sig, $payloadB64, $pub)) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        $claims = json_decode(Base64Url::decode($payloadB64), true);

        return (is_array($claims) && (int) ($claims['v'] ?? 0) === 1) ? $claims : null;
    }

    /**
     * Confere o SHA-256 de um ficheiro descarregado contra o do manifesto.
     * É a segunda tranca: manifesto assinado diz o hash, o pacote tem de bater.
     */
    public static function ficheiroConfere(string $caminho, string $sha256Esperado): bool
    {
        if (!is_file($caminho) || $sha256Esperado === '') {
            return false;
        }

        return hash_equals(strtolower($sha256Esperado), strtolower(hash_file('sha256', $caminho)));
    }
}
