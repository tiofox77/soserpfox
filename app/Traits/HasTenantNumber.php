<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;

/**
 * Geração de números sequenciais POR TENANT à prova de concorrência.
 *
 * Substitui o padrão frágil `PREFIXO . (Model::count() + 1)`, que:
 *   - conta globalmente (ou reinicia por tenant) → colide com índice único;
 *   - quebra após eliminações/soft-delete (count() diminui → regenera um nº já usado);
 *   - tem corrida entre dois utilizadores a criar ao mesmo tempo.
 *
 * Requer índice único composto (tenant_id, <coluna>) na tabela.
 */
trait HasTenantNumber
{
    /**
     * Próximo número sequencial do tenant ativo (ex.: OS-00042).
     * Considera registos soft-deleted, que ainda ocupam o número no índice único.
     */
    public static function generateTenantNumber(string $column, string $prefix, int $pad = 5): string
    {
        $tenantId = activeTenantId();

        $query = static::query();
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        $last = $query->where('tenant_id', $tenantId)
            ->where($column, 'like', $prefix . '%')
            ->orderByRaw("CAST(SUBSTRING_INDEX(`{$column}`, '-', -1) AS UNSIGNED) DESC")
            ->value($column);

        $next = $last ? ((int) substr((string) strrchr($last, '-'), 1)) + 1 : 1;

        return $prefix . str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Cria o registo com número gerado de forma ATÓMICA.
     * Se o índice único (tenant_id, coluna) rejeitar por corrida (1062),
     * regenera o número e tenta de novo — nunca duplica nem falha visivelmente.
     */
    public static function createWithTenantNumber(array $attributes, string $column, string $prefix, int $pad = 5): static
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $attributes[$column] = static::generateTenantNumber($column, $prefix, $pad);
            try {
                return static::create($attributes);
            } catch (QueryException $e) {
                $isDuplicate = ($e->errorInfo[1] ?? null) === 1062
                    && str_contains($e->getMessage(), $column);
                if ($isDuplicate && $attempt < 6) {
                    usleep(random_int(15000, 70000)); // 15-70ms de backoff
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException("Não foi possível gerar um número único para {$column}.");
    }
}
