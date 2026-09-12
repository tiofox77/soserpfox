<?php

namespace App\Services\Analytics;

/**
 * De onde veio a visita.
 *
 * O ecrã classificava a origem com um CASE dentro do SQL, com uma mão-cheia de
 * domínios escritos à mão (google, facebook, instagram, linkedin, whatsapp) e
 * tudo o resto em "other". Isso escondia justamente o que interessa saber: QUE
 * sites nos mandam gente. Nesta base, mais de metade dos referrers são páginas
 * da própria aplicação — que o CASE contava como "other", como se fossem
 * tráfego externo.
 *
 * Aqui separa-se em duas coisas diferentes:
 *
 *   - o CANAL: directo, orgânico, social, referência, campanha, interno
 *   - a FONTE: o domínio propriamente dito, ou o utm_source quando existe
 *
 * Feito em PHP e não em SQL porque um domínio não se extrai com LIKE, e porque
 * a lista de motores de busca e redes sociais cresce — é para se ler e mexer,
 * não para se descobrir dentro de uma consulta.
 */
class Origem
{
    /**
     * Os canais que `classificar()` sabe devolver.
     *
     * A lista estava escrita à mão no Blade do ecrã, ao lado do `match` das
     * cores e do `match` dos ícones — três sítios a repetir os mesmos seis
     * nomes. Acrescentar um canal aqui obrigava a lembrar dos outros dois.
     */
    public const CANAIS = ['directo', 'orgânico', 'social', 'campanha', 'referência', 'interno'];

    /** Motores de busca: tráfego orgânico. */
    private const BUSCA = [
        'google.', 'bing.', 'yahoo.', 'duckduckgo.', 'yandex.', 'baidu.',
        'ecosia.', 'brave.', 'search.',
    ];

    /** Redes sociais e mensagens. */
    private const SOCIAL = [
        'facebook.'  => 'Facebook',
        'fb.'        => 'Facebook',
        'instagram.' => 'Instagram',
        'linkedin.'  => 'LinkedIn',
        'lnkd.in'    => 'LinkedIn',
        't.co'       => 'X (Twitter)',
        'twitter.'   => 'X (Twitter)',
        'x.com'      => 'X (Twitter)',
        'whatsapp.'  => 'WhatsApp',
        'wa.me'      => 'WhatsApp',
        'youtube.'   => 'YouTube',
        'youtu.be'   => 'YouTube',
        'tiktok.'    => 'TikTok',
        't.me'       => 'Telegram',
        'telegram.'  => 'Telegram',
    ];

    /**
     * Classifica um referrer (e o utm_source, quando existe).
     *
     * @return array{canal:string, fonte:string, dominio:?string}
     */
    public static function classificar(?string $referrer, ?string $utmSource = null, ?string $dominioProprio = null): array
    {
        // Uma campanha marcada com utm_source manda em tudo o resto: foi quem
        // fez a campanha que disse de onde vem.
        if (!empty($utmSource)) {
            return ['canal' => 'campanha', 'fonte' => $utmSource, 'dominio' => null];
        }

        $dominio = self::dominio($referrer);

        if (!$dominio) {
            return ['canal' => 'directo', 'fonte' => 'Directo', 'dominio' => null];
        }

        // Tráfego da própria casa: alguém que estava na aplicação e foi ao site.
        // Contar isto como "referência" inflaciona o que vem de fora.
        if ($dominioProprio && str_contains($dominio, $dominioProprio)) {
            return ['canal' => 'interno', 'fonte' => 'Dentro do sistema', 'dominio' => $dominio];
        }

        foreach (self::BUSCA as $pedaco) {
            if (str_contains($dominio, $pedaco)) {
                return ['canal' => 'orgânico', 'fonte' => self::bonito($dominio), 'dominio' => $dominio];
            }
        }

        foreach (self::SOCIAL as $pedaco => $nome) {
            if (str_contains($dominio, $pedaco)) {
                return ['canal' => 'social', 'fonte' => $nome, 'dominio' => $dominio];
            }
        }

        return ['canal' => 'referência', 'fonte' => self::bonito($dominio), 'dominio' => $dominio];
    }

    /** O domínio de um URL, sem "www." e sem porta. Null se não houver. */
    public static function dominio(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $anfitriao = parse_url($url, PHP_URL_HOST);

        if (!$anfitriao) {
            // Um referrer pode vir só como domínio, sem esquema.
            $anfitriao = parse_url('http://' . ltrim($url, '/'), PHP_URL_HOST);
        }

        if (!$anfitriao) {
            return null;
        }

        // Minúsculas PRIMEIRO. Ao contrário, um "WWW." maiúsculo não casava com
        // o padrão e sobrevivia — e o mesmo site aparecia como duas fontes
        // diferentes na lista, "exemplo.ao" e "www.exemplo.ao".
        return preg_replace('/^www\./', '', strtolower($anfitriao));
    }

    /** "m.facebook.com" → "Facebook.com". Só para ler melhor. */
    private static function bonito(string $dominio): string
    {
        $limpo = preg_replace('/^(m|mobile|l|lm)\./', '', $dominio);

        return ucfirst($limpo);
    }

    /** Cor do canal, para o ecrã não ter um if-else gigante. */
    public static function cor(string $canal): string
    {
        return match ($canal) {
            'directo'    => 'bg-slate-100 text-slate-700',
            'orgânico'   => 'bg-green-100 text-green-700',
            'social'     => 'bg-blue-100 text-blue-700',
            'campanha'   => 'bg-purple-100 text-purple-700',
            'referência' => 'bg-amber-100 text-amber-700',
            'interno'    => 'bg-gray-100 text-gray-500',
            default      => 'bg-gray-100 text-gray-700',
        };
    }

    public static function icone(string $canal): string
    {
        return match ($canal) {
            'directo'    => 'fa-arrow-right-to-bracket',
            'orgânico'   => 'fa-magnifying-glass',
            'social'     => 'fa-share-nodes',
            'campanha'   => 'fa-bullhorn',
            'referência' => 'fa-link',
            'interno'    => 'fa-house',
            default      => 'fa-circle-question',
        };
    }
}
