<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
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
            'meta' => 'nullable|array',
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

        AnalyticsEvent::create(array_merge($data, [
            'ip' => $request->ip(),
            'country' => $this->detectCountry($request),
            'device_type' => $device['type'],
            'browser' => $device['browser'],
            'os' => $device['os'],
            'user_id' => auth()->id(),
            'user_agent' => substr($ua ?? '', 0, 500),
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
