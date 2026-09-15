<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Services\Privacidade\Consentimentos;
use App\Support\Privacidade\Ip;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function track(Request $request)
    {
        $data = $request->validate([
            'visitor_id' => 'required|uuid',
            'session_id' => 'required|uuid',
            'type' => 'required|string|max:30',
            'event_name' => 'nullable|string|max:80',
            'url' => 'nullable|string|max:500',
            'path' => 'nullable|string|max:255',
            'referrer' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'utm_term' => 'nullable|string|max:100',
            'utm_content' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:10',
            // Com tecto: um `meta` sem limite deixava quem quisesse gravar
            // megabytes por pedido numa rota pública.
            'meta' => 'nullable|array|max:20',
            'meta.*' => 'nullable|string|max:200',
            // Sem consentimento de estatísticas o browser manda `anonimo`.
            'anonimo' => 'nullable|boolean',
            // Termo pesquisado e tempo em página. Em colunas próprias e não
            // dentro do `meta`: um JSON não se agrupa, e "os mais pesquisados"
            // é precisamente um GROUP BY.
            'search_term' => 'nullable|string|max:150',
            'duration_seconds' => 'nullable|integer|min:0|max:86400',
        ]);

        // Um termo de pesquisa guarda-se sempre em minúsculas: senão "Amidol",
        // "amidol" e "AMIDOL" contam como três termos diferentes e o painel dos
        // mais pesquisados divide-se a si próprio.
        if (!empty($data['search_term'])) {
            $data['search_term'] = mb_strtolower(trim($data['search_term']));
        }

        // A saída da página não é um acto novo: é o desfecho do pageview que já
        // está gravado. Escrever uma linha própria deixaria a duração numa
        // linha e a página noutra — e a média de tempo por página, que se lê da
        // linha do pageview, ficaria sempre vazia.
        if ($data['type'] === 'page_exit') {
            return $this->carimbarDuracao($data);
        }

        $ua = $request->userAgent();
        $device = $this->detectDevice($ua);

        /*
         * QUEM NÃO ACEITOU ESTATÍSTICAS CONTA COMO VISITA, E MAIS NADA.
         *
         * RGPD e Directiva ePrivacy: um identificador guardado no aparelho, o IP
         * e a cidade pedem consentimento. Sem ele a visita grava-se sem IP (logo
         * sem cidade — a localização resolve-se a partir do IP), sem user agent,
         * sem ligação à conta e sem o que vem depois do «?» do endereço. O país
         * vem do cabeçalho do CDN e o aparelho é só a categoria. O browser e o
         * servidor decidem cada um: basta um dos dois dizer que não há
         * consentimento para a visita ser anónima.
         *
         * Com consentimento, o IP guarda-se TRUNCADO (x.x.x.0): a região resolve
         * na mesma, e deixa de apontar para uma ligação concreta.
         */
        $anonimo = ! empty($data['anonimo']) || ! Consentimentos::permite('estatisticas', $request);
        unset($data['anonimo']);

        if ($anonimo) {
            $data['url'] = isset($data['url']) ? strtok($data['url'], '?') : null;
            $data['referrer'] = isset($data['referrer']) && $data['referrer'] !== ''
                ? (parse_url($data['referrer'], PHP_URL_SCHEME) ?: 'https') . '://' . parse_url($data['referrer'], PHP_URL_HOST) . '/'
                : null;
            $data['meta'] = null;
        }

        AnalyticsEvent::create(array_merge($data, [
            'ip' => $anonimo ? null : Ip::anonimizar($request->ip()),
            'country' => $this->detectCountry($request),
            'device_type' => $device['type'],
            'browser' => $device['browser'],
            'os' => $device['os'],
            'user_id' => $anonimo ? null : auth()->id(),
            'user_agent' => $anonimo ? null : substr($ua ?? '', 0, 500),
            'anonimo' => $anonimo,
            'created_at' => now(),
        ]));

        return response()->json(['ok' => true]);
    }

    /**
     * Grava quanto tempo a página esteve aberta, na linha do próprio pageview.
     *
     * Só o pageview MAIS RECENTE daquela sessão e daquele caminho, e só se
     * ainda não tiver duração: uma pessoa que volte à mesma página tem dois
     * pageviews, e a segunda saída não pode reescrever o tempo da primeira.
     */
    protected function carimbarDuracao(array $data)
    {
        $segundos = (int) ($data['duration_seconds'] ?? 0);

        if ($segundos < 1) {
            return response()->json(['ok' => true]);
        }

        AnalyticsEvent::where('session_id', $data['session_id'])
            ->where('type', 'pageview')
            ->where('path', $data['path'] ?? null)
            ->whereNull('duration_seconds')
            ->latest('id')
            ->limit(1)
            ->update(['duration_seconds' => $segundos]);

        return response()->json(['ok' => true]);
    }

    protected function detectCountry(Request $r): ?string
    {
        // Tenta headers comuns de CDN/proxy
        return $r->header('CF-IPCountry') ?: $r->header('X-Country-Code') ?: null;
    }

    protected function detectDevice(?string $ua): array
    {
        if (!$ua) return ['type' => 'unknown', 'browser' => null, 'os' => null];

        $type = 'desktop';
        if (preg_match('/bot|crawler|spider|googlebot|bingbot/i', $ua)) $type = 'bot';
        elseif (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) $type = 'mobile';
        elseif (preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $ua)) $type = 'tablet';

        $browser = 'Other';
        foreach (['Edg' => 'Edge', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari', 'OPR' => 'Opera'] as $needle => $name) {
            if (str_contains($ua, $needle)) { $browser = $name; break; }
        }

        $os = 'Other';
        foreach (['Windows' => 'Windows', 'Mac' => 'macOS', 'Linux' => 'Linux', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iOS'] as $needle => $name) {
            if (str_contains($ua, $needle)) { $os = $name; break; }
        }

        return ['type' => $type, 'browser' => $browser, 'os' => $os];
    }
}
