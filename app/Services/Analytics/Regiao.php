<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * De onde veio o visitante — país e cidade a partir do endereço IP.
 *
 * O ecrã tinha um painel de países desde sempre e ele nunca mostrou nada: as
 * colunas `country` e `city` só eram preenchidas a partir do cabeçalho
 * `CF-IPCountry`, que existe quando o site está atrás da Cloudflare. Este não
 * está. Resultado: 322 visitas, zero países.
 *
 * COMO SE RESOLVE, E PORQUÊ ASSIM
 * -------------------------------
 * Não se resolve no momento do pedido. O `track` corre em cada página vista e
 * uma chamada de rede lá dentro punha o tempo de resposta do site nas mãos de
 * um serviço de terceiros — e uma visita perdia-se por completo se ele
 * estivesse em baixo.
 *
 * Resolve-se DEPOIS, em lote, sobre os IPs ainda por resolver, e cada IP é
 * perguntado UMA vez: o que já foi resolvido copia-se das linhas anteriores do
 * mesmo IP. Numa base com 322 visitas de meia dúzia de pessoas, isso são meia
 * dúzia de perguntas ao todo.
 *
 * PRIVACIDADE
 * -----------
 * Isto envia endereços IP de visitantes para um serviço externo (ip-api.com).
 * Só o IP, mais nada — sem página, sem identificador, sem quem é. Ainda assim
 * é dado de terceiros a sair para fora, e por isso desliga-se com
 * ANALYTICS_GEO=false. Endereços privados e locais nunca saem.
 */
class Regiao
{
    /** Quantos IPs por passagem. O serviço gratuito limita a 45 pedidos/minuto. */
    private const POR_LOTE = 30;

    public static function activo(): bool
    {
        return (bool) config('analytics.geo', env('ANALYTICS_GEO', true));
    }

    /**
     * Resolve os IPs ainda sem país.
     *
     * @return array{resolvidos:int, copiados:int, perguntados:int, falhados:int}
     */
    public static function resolverPendentes(int $limite = self::POR_LOTE): array
    {
        $conta = ['resolvidos' => 0, 'copiados' => 0, 'perguntados' => 0, 'falhados' => 0];

        if (!self::activo()) {
            return $conta;
        }

        $ips = AnalyticsEvent::query()
            ->whereNull('country')
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->distinct()
            ->limit($limite)
            ->pluck('ip');

        foreach ($ips as $ip) {
            // 1) Já sabemos deste IP? Copia-se, sem sair para a rede.
            $conhecido = AnalyticsEvent::where('ip', $ip)
                ->whereNotNull('country')
                ->select('country', 'city')
                ->first();

            if ($conhecido) {
                self::carimbar($ip, $conhecido->country, $conhecido->city);
                $conta['copiados']++;
                $conta['resolvidos']++;

                continue;
            }

            // 2) Endereços que não saem daqui: casa, rede local, loopback.
            if (self::ehPrivado($ip)) {
                self::carimbar($ip, 'LO', 'Rede local');
                $conta['resolvidos']++;

                continue;
            }

            // 3) Perguntar, uma vez só por IP.
            $lugar = self::perguntar($ip);
            $conta['perguntados']++;

            if (!$lugar) {
                $conta['falhados']++;

                continue;
            }

            self::carimbar($ip, $lugar['country'], $lugar['city']);
            $conta['resolvidos']++;
        }

        return $conta;
    }

    /** Escreve o país/cidade em TODAS as linhas daquele IP que ainda não o tenham. */
    private static function carimbar(string $ip, ?string $pais, ?string $cidade): void
    {
        AnalyticsEvent::where('ip', $ip)
            ->whereNull('country')
            ->update([
                'country' => $pais,
                'city'    => $cidade ? mb_substr($cidade, 0, 80) : null,
            ]);
    }

    /**
     * Um IP que nunca sai da rede onde está.
     *
     * Sem isto, a máquina de desenvolvimento e a rede interna do cliente eram
     * mandadas para o serviço externo a cada arranque — e voltavam sempre sem
     * resposta, portanto ficavam para sempre por resolver e a ser perguntadas
     * outra vez na passagem seguinte.
     */
    private static function ehPrivado(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * O serviço externo. Falha em silêncio — isto nunca pode derrubar nada.
     *
     * @return array{country:string, city:?string}|null
     */
    private static function perguntar(string $ip): ?array
    {
        try {
            $resposta = Http::timeout(4)
                ->retry(1, 200)
                ->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,countryCode,city']);

            if (!$resposta->successful() || ($resposta->json('status') !== 'success')) {
                return null;
            }

            $pais = $resposta->json('countryCode');

            if (!$pais) {
                return null;
            }

            return ['country' => mb_substr($pais, 0, 2), 'city' => $resposta->json('city')];
        } catch (\Throwable $e) {
            Log::debug('Região do visitante não resolvida', ['ip' => $ip, 'erro' => $e->getMessage()]);

            return null;
        }
    }

    /** Quantos IPs continuam por resolver. */
    public static function porResolver(): int
    {
        return (int) DB::table((new AnalyticsEvent)->getTable())
            ->whereNull('country')
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->distinct()
            ->count('ip');
    }

    /** Nome do país a partir do código ISO. Só os que aparecem por aqui. */
    public static function nomeDoPais(?string $codigo): string
    {
        return match (strtoupper((string) $codigo)) {
            'AO' => 'Angola',
            'PT' => 'Portugal',
            'BR' => 'Brasil',
            'MZ' => 'Moçambique',
            'CV' => 'Cabo Verde',
            'ST' => 'São Tomé e Príncipe',
            'GW' => 'Guiné-Bissau',
            'ZA' => 'África do Sul',
            'NA' => 'Namíbia',
            'CD' => 'Rep. Dem. Congo',
            'CG' => 'Congo',
            'US' => 'Estados Unidos',
            'GB' => 'Reino Unido',
            'FR' => 'França',
            'ES' => 'Espanha',
            'DE' => 'Alemanha',
            'CN' => 'China',
            'IN' => 'Índia',
            'LO' => 'Rede local',
            ''   => 'Desconhecido',
            default => strtoupper((string) $codigo),
        };
    }

    /** Bandeira em emoji a partir do código ISO — dois caracteres regionais. */
    public static function bandeira(?string $codigo): string
    {
        $codigo = strtoupper((string) $codigo);

        if (strlen($codigo) !== 2 || $codigo === 'LO' || !ctype_alpha($codigo)) {
            return '🌐';
        }

        return mb_chr(0x1F1E6 + ord($codigo[0]) - 65, 'UTF-8')
             . mb_chr(0x1F1E6 + ord($codigo[1]) - 65, 'UTF-8');
    }
}
