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
        ]);

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
