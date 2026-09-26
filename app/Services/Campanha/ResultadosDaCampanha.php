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
 * O PERCURSO, passo a passo, cada um pela sua fonte (26/09/2026):
 *  - CLIQUES para o registo: o browser, `click_register` — um clique não é
 *    uma pessoa; conta-se por visitante, sem os robôs.
 *  - FORMULÁRIOS INICIADOS: o servidor, `registo_iniciado` — a primeira etapa
 *    que passou na validação (EventosDaInscricao).
 *  - EMPRESAS criadas: o servidor, `complete_registration`, uma por empresa
 *    (agrupado por tenant_id, mesmo que uma tenha gravado duas vezes).
 *  - TESTES activados: a subscrição da empresa entrou em teste.
 *  - PRIMEIRA utilização: a empresa criou o primeiro trabalho a sério
 *    (PrimeiraUtilizacao) — e não o login, que o registo faz sozinho.
 *
 * Os TESTES saem das contas comerciais — identificados, nunca adivinhados. As
 * LINHAS não levam dados pessoais: nem email, nem NIF, nem nome. O email, o
 * NIF e o nome só servem, cá dentro, para reconhecer as contas de teste.
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
     * As empresas criadas no período, uma linha cada, SEM dados pessoais:
     * origem, módulo de entrada, plano, estado, teste e primeira utilização.
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

                $estado = $e->meta['subscription_status'] ?? null;
                $primeira = $tenant ? PrimeiraUtilizacao::de($tenant) : null;

                return [
                    'tenant_id' => $tenantId,
                    'plano' => $e->meta['plan_slug'] ?? null,
                    'modulo' => $e->meta['modulo'] ?? null,
                    'estado' => $estado,
                    'origem' => $e->utm_source ?: (! empty($e->meta['fbclid']) ? 'facebook' : (! empty($e->meta['gclid']) ? 'google' : null)),
                    'utm_campaign' => $e->utm_campaign ?? null,
                    'quando' => $e->created_at,
                    'eventos_gravados' => $grupo->count(),
                    // O teste começou: no registo, ou mais tarde (um pendente aprovado para teste).
                    'teste_activado' => $estado === 'trial'
                        || ($tenant && $tenant->subscriptions()->whereNotNull('trial_ends_at')->exists()),
                    'primeira_utilizacao' => $primeira !== null,
                    'primeira_utilizacao_em' => $primeira,
                    'teste' => self::eTeste($user?->email ?? $tenant?->email, $tenant?->nif, $tenant?->name ?? $user?->name),
                ];
            })
            ->values();
    }

    /**
     * Os visitantes distintos que clicaram para o registo, e os formulários
     * iniciados — os dois passos antes de haver empresa.
     *
     * @return array{cliques_para_registo: int, formularios_iniciados: int}
     */
    public static function antesDaEmpresa(CarbonInterface $desde, CarbonInterface $ate): array
    {
        return [
            // Por visitante: dez cliques da mesma pessoa são uma pessoa. Sem
            // quem tinha sessão iniciada (já é cliente).
            'cliques_para_registo' => AnalyticsEvent::query()
                ->where('event_name', 'click_register')
                ->whereNull('user_id')
                ->whereBetween('created_at', [$desde, $ate])
                ->distinct()->count('visitor_id'),
            'formularios_iniciados' => AnalyticsEvent::query()
                ->where('event_name', EventosDaInscricao::INICIADO)
                ->whereBetween('created_at', [$desde, $ate])
                ->distinct()->count('visitor_id'),
        ];
    }

    /**
     * O resumo com o percurso separado e os testes de fora.
     *
     * @return array{
     *   periodo: array{desde:string, ate:string},
     *   cliques_para_registo: int,
     *   formularios_iniciados: int,
     *   eventos_completeregistration: int,
     *   inscricoes_unicas: int,
     *   empresas_criadas: int,
     *   testes_activados: int,
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
            ...self::antesDaEmpresa($desde, $ate),
            // Conversões gravadas pelo servidor (uma por empresa). NÃO são os
            // disparos do pixel: esses dependem do consentimento de marketing
            // e de o browser os deixar sair — é a Meta que os conta.
            'eventos_completeregistration' => (int) $linhas->sum('eventos_gravados'),
            'inscricoes_unicas' => $comerciais->count(),
            'empresas_criadas' => $comerciais->pluck('tenant_id')->filter()->unique()->count(),
            'testes_activados' => $comerciais->where('teste_activado', true)->count(),
            'primeira_utilizacao' => $comerciais->where('primeira_utilizacao', true)->count(),
            'testes_excluidos' => $linhas->where('teste', true)->count(),
            'linhas' => $linhas,
        ];
    }
}
