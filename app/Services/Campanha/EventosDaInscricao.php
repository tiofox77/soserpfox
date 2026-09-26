<?php

namespace App\Services\Campanha;

use App\Models\AnalyticsEvent;
use App\Services\Privacidade\Consentimentos;
use App\Support\Privacidade\Ip;
use App\Support\Robos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * OS PASSOS DA INSCRIÇÃO QUE SÓ O SERVIDOR VÊ (26/09/2026).
 *
 * O painel contava cliques em links para `/register` como «registos». Um
 * clique não é uma pessoa a preencher nada. O início EFECTIVO do formulário
 * é a primeira etapa que passa na validação — e isso só o servidor sabe.
 *
 * Grava-se uma vez por sessão, com a mesma identidade de visitante da
 * conversão (`registration_visitor_id`) e a mesma origem
 * (`registration_acquisition`), para o relatório seguir a pessoa do clique
 * até à empresa. O IP, o browser e os identificadores das campanhas só com o
 * consentimento de cada categoria — a mesma regra de `RegistarEmpresa`.
 */
final class EventosDaInscricao
{
    public const INICIADO = 'registo_iniciado';

    /** A primeira etapa do formulário passou: a pessoa começou mesmo. */
    public static function iniciado(?string $plano = null): void
    {
        $sessao = session();

        if ($sessao->get('registo_iniciado_gravado')) {
            return;
        }

        if (Robos::eRobo(request()->userAgent())) {
            return;
        }

        try {
            $origem = $sessao->get('registration_acquisition', []);
            $estatisticas = Consentimentos::permite('estatisticas');
            $marketing = Consentimentos::permite('marketing');

            $visitante = $sessao->get('registration_visitor_id');
            if (! $visitante) {
                $visitante = (string) Str::uuid();
                $sessao->put('registration_visitor_id', $visitante);
            }

            $evento = [
                'visitor_id' => $visitante,
                'session_id' => (string) Str::uuid(),
                'type' => 'conversion',
                'event_name' => self::INICIADO,
                'url' => null,
                'path' => '/register',
                'referrer' => null,
                'utm_source' => $origem['utm_source'] ?? null,
                'utm_medium' => $origem['utm_medium'] ?? null,
                'utm_campaign' => $origem['utm_campaign'] ?? null,
                'utm_term' => $origem['utm_term'] ?? null,
                'utm_content' => $origem['utm_content'] ?? null,
                'ip' => $estatisticas ? Ip::anonimizar(request()->ip()) : null,
                'user_id' => null,
                'meta' => [
                    'modulo' => $sessao->get('registration_module'),
                    'plan_slug' => $plano,
                    'fbclid' => $marketing ? ($origem['fbclid'] ?? null) : null,
                    'gclid' => $marketing ? ($origem['gclid'] ?? null) : null,
                ],
                'user_agent' => $estatisticas ? substr((string) request()->userAgent(), 0, 500) : null,
                'created_at' => now(),
            ];

            if (Schema::hasColumn('analytics_events', 'anonimo')) {
                $evento['anonimo'] = ! $estatisticas;
            }

            AnalyticsEvent::create($evento);
            $sessao->put('registo_iniciado_gravado', true);
        } catch (\Throwable $e) {
            Log::warning('Início do registo não gravado', ['erro' => $e->getMessage()]);
        }
    }
}
