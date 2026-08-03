<?php

namespace App\Services\AGT;

use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Support\Facades\Storage;

/**
 * Localização das chaves RSA do contribuinte, POR EMPRESA e POR AMBIENTE.
 *
 * O Portal do Contribuinte entrega pares de chaves diferentes para homologação
 * e para produção. Antes guardava-se um único par em
 * `agt/tenants/{id}/`, pelo que passar de sandbox para produção continuava a
 * assinar com a chave de testes — a AGT de produção rejeitaria a assinatura.
 *
 * Estrutura:
 *   agt/tenants/{tenant}/sandbox/{public,private}_key.pem
 *   agt/tenants/{tenant}/production/{public,private}_key.pem
 *
 * Caminho legado (sem ambiente) continua a ser lido como recurso, para as
 * instalações que ainda não correram `agt:migrate-keys`.
 *
 * Nota: o disco 'local' tem raiz em storage/app/private no Laravel 11+.
 */
class AGTKeyStore
{
    public const AMBIENTES = ['sandbox', 'production'];

    /** Ambiente configurado para a empresa. */
    public static function ambiente(int $tenantId): string
    {
        $env = InvoicingSettings::forTenant($tenantId)->agt_environment ?: 'sandbox';

        return in_array($env, self::AMBIENTES, true) ? $env : 'sandbox';
    }

    /** Pasta das chaves do ambiente indicado (ou do configurado). */
    public static function directory(int $tenantId, ?string $environment = null): string
    {
        $env = $environment ?: self::ambiente($tenantId);

        return "agt/tenants/{$tenantId}/{$env}";
    }

    /** Pasta legada, sem ambiente. */
    public static function legacyDirectory(int $tenantId): string
    {
        return "agt/tenants/{$tenantId}";
    }

    /**
     * Caminho efectivo de uma chave: o do ambiente, ou o legado se aquele ainda
     * não existir. Devolve sempre o do ambiente quando nenhum existe, para que
     * as escritas caiam no sítio novo.
     */
    public static function keyPath(int $tenantId, string $tipo, ?string $environment = null): string
    {
        $env = $environment ?: self::ambiente($tenantId);
        $ficheiro = $tipo === 'private' ? 'private_key.pem' : 'public_key.pem';
        $doAmbiente = self::directory($tenantId, $env) . '/' . $ficheiro;

        if (Storage::disk('local')->exists($doAmbiente)) {
            return $doAmbiente;
        }

        // Recurso ao caminho legado APENAS em homologação. As chaves antigas
        // (sem ambiente) são de homologação — foi essa a premissa da migração.
        // Deixar produção cair nelas fazia o ecrã dizer "chave configurada"
        // quando o que lá estava era a chave de testes: assinaríamos documentos
        // reais com a chave errada e a AGT recusaria a assinatura.
        if ($env === 'sandbox') {
            $legado = self::legacyDirectory($tenantId) . '/' . $ficheiro;
            if (Storage::disk('local')->exists($legado)) {
                return $legado;
            }
        }

        return $doAmbiente;
    }

    public static function privateKeyPath(int $tenantId, ?string $environment = null): string
    {
        return self::keyPath($tenantId, 'private', $environment);
    }

    public static function publicKeyPath(int $tenantId, ?string $environment = null): string
    {
        return self::keyPath($tenantId, 'public', $environment);
    }

    /** Há par completo (pública + privada) para este ambiente? */
    public static function hasKeyPair(int $tenantId, ?string $environment = null): bool
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::privateKeyPath($tenantId, $environment))
            && $disk->exists(self::publicKeyPath($tenantId, $environment));
    }

    /** Guarda o par no ambiente indicado. */
    public static function store(int $tenantId, string $publicKey, string $privateKey, ?string $environment = null): void
    {
        $dir = self::directory($tenantId, $environment);
        Storage::disk('local')->put($dir . '/public_key.pem', $publicKey);
        Storage::disk('local')->put($dir . '/private_key.pem', $privateKey);
    }

    /** Remove o par do ambiente indicado (e o legado, se for o caso). */
    public static function forget(int $tenantId, ?string $environment = null): void
    {
        $disk = Storage::disk('local');

        foreach ([self::directory($tenantId, $environment), self::legacyDirectory($tenantId)] as $dir) {
            foreach (['public_key.pem', 'private_key.pem'] as $f) {
                if ($disk->exists("{$dir}/{$f}")) {
                    $disk->delete("{$dir}/{$f}");
                }
            }
        }
    }
}
