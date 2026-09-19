<?php

namespace App\Services\Campanha;

use App\Models\AnalyticsEvent;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * OS RESULTADOS DA CAMPANHA, SEPARADOS E SEM OS TESTES.
 *
 * Quatro coisas que se confundem numa só:
 *  - EVENTOS Meta (CompleteRegistration): disparados no browser; um por
 *    inscrição gravada (event_id determinístico por empresa — não duplicam).
 *  - INSCRIÇÕES únicas: as conversões que o servidor gravou (a fonte de
 *    verdade, não o que a Meta atribui).
 *  - EMPRESAS criadas: uma por inscrição.
 *  - PRIMEIRA utilização: quantas dessas empresas já entraram (login).
 *
 * E os TESTES saem das contas comerciais — identificados, nunca adivinhados.
 */
class ResultadosDaCampanha
{
    /** Uma inscrição é teste quando o email/NIF constam da lista, ou o nome/email tem um padrão. */
    public static function eTeste(?string $email, ?string $nif, ?string $nome): bool
    {
        $email = mb_strtolower(trim((string) $email));
        $nif = trim((string) $nif);
        $nome = mb_strtolower(trim((string) $nome));

        $listaEmails = array_map('mb_strtolower', (array) config('campanha.testes.emails', []));
        if ($email !== '' && in_array($email, $listaEmails, true)) {
            return true;
        }

        if ($nif !== '' && in_array($nif, (array) config('campanha.testes.nifs', []), true)) {
            return true;
        }

        foreach ((array) config('campanha.testes.padroes', []) as $padrao) {
            $padrao = mb_strtolower(trim((string) $padrao));
            if ($padrao !== '' && (str_contains($email, $padrao) || str_contains($nome, $padrao))) {
                return true;
            }
        }

        return false;
    }

    /**
     * As linhas das conversões do período, cada uma anotada (empresa, plano,
     * origem, se já entrou, e se é teste).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function linhas(CarbonInterface $desde, CarbonInterface $ate): Collection
    {
        $eventos = AnalyticsEvent::query()
            ->where('event_name', 'complete_registration')
            ->whereBetween('created_at', [$desde, $ate])
            ->orderBy('created_at')
            ->get();

        // Uma empresa conta UMA vez, mesmo que (por um defeito passado) tenha
        // gravado a conversão mais do que uma vez: agrupa-se por tenant_id.
        return $eventos
            ->groupBy(fn (AnalyticsEvent $e) => (int) ($e->meta['tenant_id'] ?? 0) ?: 'ev-' . $e->id)
            ->map(function (Collection $grupo) {
                /** @var AnalyticsEvent $e */
                $e = $grupo->first();
                $tenantId = (int) ($e->meta['tenant_id'] ?? 0);
                $tenant = $tenantId ? Tenant::withTrashed()->find($tenantId) : null;
                $user = $e->user_id ? User::withTrashed()->find($e->user_id) : null;

                $email = $user?->email ?? $tenant?->email;
                $nome = $tenant?->name ?? $user?->name;
                $nif = $tenant?->nif;

                return [
                    'tenant_id' => $tenantId,
                    'empresa' => $nome,
                    'email' => $email,
                    'nif' => $nif,
                    'plano' => $e->meta['plan_slug'] ?? null,
                    'estado' => $e->meta['subscription_status'] ?? null,
                    'utm_source' => $e->utm_source,
                    'utm_campaign' => $e->utm_campaign ?? null,
                    'quando' => $e->created_at,
                    'eventos_gravados' => $grupo->count(),
                    'primeira_utilizacao' => (bool) ($user?->last_login_at),
                    'teste' => self::eTeste($email, $nif, $nome),
                ];
            })
            ->values();
    }

    /**
     * O resumo com as quatro medidas separadas e os testes de fora.
     *
     * @return array{
     *   periodo: array{desde:string, ate:string},
     *   eventos_completeregistration: int,
     *   inscricoes_unicas: int,
     *   empresas_criadas: int,
     *   primeira_utilizacao: int,
     *   testes_excluidos: int,
     *   linhas: Collection<int, array<string, mixed>>
     * }
     */
    public static function resumo(CarbonInterface $desde, CarbonInterface $ate): array
    {
        $linhas = self::linhas($desde, $ate);
        $comerciais = $linhas->where('teste', false);

        return [
            'periodo' => ['desde' => $desde->toDateTimeString(), 'ate' => $ate->toDateTimeString()],
            // Um evento por empresa (disparo do pixel). É o que se compara com a Meta.
            'eventos_completeregistration' => (int) $linhas->sum('eventos_gravados'),
            'inscricoes_unicas' => $comerciais->count(),
            'empresas_criadas' => $comerciais->pluck('tenant_id')->filter()->unique()->count(),
            'primeira_utilizacao' => $comerciais->where('primeira_utilizacao', true)->count(),
            'testes_excluidos' => $linhas->where('teste', true)->count(),
            'linhas' => $linhas,
        ];
    }
}
