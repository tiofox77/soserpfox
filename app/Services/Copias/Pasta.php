<?php

namespace App\Services\Copias;

use App\Models\Copias\CopiaDeSeguranca;
use RuntimeException;

/**
 * A PASTA DAS CÓPIAS NO SERVIDOR — e o índice que se refaz a partir dela.
 *
 *   storage/app/copias-de-seguranca/plataforma/soserp-bd-20260915-120000.sql.gz[.soscopia]
 *   storage/app/copias-de-seguranca/empresas/17/soserp-empresa-17-20260915-120000.jsonl.gz[.soscopia]
 *
 * Ao lado de cada cópia, um .json com o que ela é (âmbito, sha256, origem,
 * resumo). Restaurar a base inteira volta a tabela `copias_de_seguranca` atrás
 * no tempo — as cópias feitas depois continuam na pasta, e `reindexar()`
 * devolve-as ao índice.
 */
class Pasta
{
    public static function raiz(): string
    {
        return rtrim((string) config('copias.pasta'), '/\\');
    }

    public static function doAmbito(?int $tenantId): string
    {
        $pasta = self::raiz() . ($tenantId ? '/empresas/' . $tenantId : '/plataforma');
        if (! is_dir($pasta)) {
            @mkdir($pasta, 0750, true);
        }

        // O docroot deste alojamento é a raiz do projecto: a pasta não pode ser
        // servida nem por engano.
        $bloqueio = self::raiz() . '/.htaccess';
        if (! is_file($bloqueio)) {
            @file_put_contents($bloqueio, "Require all denied\nDeny from all\n");
        }

        return $pasta;
    }

    public static function nomeNovo(?int $tenantId, bool $cifrada): string
    {
        $base = $tenantId
            ? sprintf('soserp-empresa-%d-%s.jsonl.gz', $tenantId, now()->format('Ymd-His'))
            : sprintf('soserp-bd-%s.sql.gz', now()->format('Ymd-His'));

        return $base . ($cifrada ? '.soscopia' : '');
    }

    public static function caminho(CopiaDeSeguranca $c): string
    {
        return self::doAmbito($c->tenant_id) . '/' . self::nomeSeguro($c->ficheiro);
    }

    /** Um nome que não sai da pasta (sem barras nem «..»). */
    public static function nomeSeguro(string $nome): string
    {
        $nome = basename(str_replace('\\', '/', $nome));
        if (! preg_match('/^soserp-[A-Za-z0-9._-]+$/', $nome)) {
            throw new RuntimeException('Nome de cópia inválido.');
        }

        return $nome;
    }

    public static function gravarLado(CopiaDeSeguranca $c): void
    {
        @file_put_contents(self::caminho($c) . '.json', json_encode([
            'ficheiro' => $c->ficheiro,
            'tenant_id' => $c->tenant_id,
            'tamanho' => $c->tamanho,
            'sha256' => $c->sha256,
            'cifrada' => $c->cifrada,
            'origem' => $c->origem,
            'resumo' => $c->resumo,
            'iniciada_em' => $c->iniciada_em?->toIso8601String(),
            'concluida_em' => $c->concluida_em?->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** Devolve ao índice as cópias que estão na pasta e não na tabela. */
    public static function reindexar(): int
    {
        $n = 0;
        foreach (glob(self::raiz() . '/{plataforma,empresas/*}/soserp-*.json', GLOB_BRACE) ?: [] as $lado) {
            $d = json_decode((string) @file_get_contents($lado), true);
            $ficheiro = $d['ficheiro'] ?? null;
            if (! $ficheiro || ! is_file(dirname($lado) . '/' . $ficheiro) || CopiaDeSeguranca::where('ficheiro', $ficheiro)->exists()) {
                continue;
            }
            CopiaDeSeguranca::create([
                'tenant_id' => $d['tenant_id'] ?? null,
                'ficheiro' => $ficheiro,
                'tamanho' => (int) ($d['tamanho'] ?? filesize(dirname($lado) . '/' . $ficheiro)),
                'sha256' => $d['sha256'] ?? null,
                'cifrada' => (bool) ($d['cifrada'] ?? false),
                'origem' => $d['origem'] ?? 'automatica',
                'estado' => 'concluida',
                'resumo' => $d['resumo'] ?? null,
                'iniciada_em' => $d['iniciada_em'] ?? null,
                'concluida_em' => $d['concluida_em'] ?? null,
                'ficheiro_local' => true,
            ]);
            $n++;
        }

        return $n;
    }

    public static function espacoUsado(?int $tenantId = null): int
    {
        $total = 0;
        foreach (glob(self::doAmbito($tenantId) . '/soserp-*') ?: [] as $f) {
            if (! str_ends_with($f, '.json')) {
                $total += (int) @filesize($f);
            }
        }

        return $total;
    }
}
