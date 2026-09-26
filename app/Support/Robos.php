<?php

namespace App\Support;

/**
 * OS ROBÔS NÃO SÃO VISITANTES.
 *
 * O robô que a Meta usa para rever os anúncios (`meta-externalads`) abre o
 * site e o registo como uma pessoa, e cada clique dele contava como visita e
 * como «registo começado». Os que geram a pré-visualização de um link
 * (WhatsApp, Facebook, Telegram) e os indexadores fazem o mesmo.
 *
 * A regra é pelo user agent do pedido — o do cabeçalho, que existe sempre,
 * mesmo quando a visita é anónima e o user agent não se grava.
 */
final class Robos
{
    /** Pedaços do user agent que denunciam um robô (comparação sem maiúsculas). */
    private const SINAIS = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
        'facebookexternalhit', 'meta-externalads', 'meta-externalagent', 'facebookcatalog',
        'whatsapp', 'telegram', 'skypeuripreview', 'linkedinbot', 'embedly',
        'headlesschrome', 'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptime',
        'python-requests', 'python-urllib', 'curl/', 'wget/', 'go-http-client', 'java/', 'okhttp', 'axios/', 'node-fetch', 'postman',
    ];

    public static function eRobo(?string $userAgent): bool
    {
        $ua = mb_strtolower(trim((string) $userAgent));

        // Sem user agent nenhum, não é um browser.
        if ($ua === '') {
            return true;
        }

        foreach (self::SINAIS as $sinal) {
            if (str_contains($ua, $sinal)) {
                return true;
            }
        }

        return false;
    }
}
