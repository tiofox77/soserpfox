<?php

namespace App\Services\Copias\Destinos;

use RuntimeException;

/**
 * UMA EMPRESA NÃO PODE APONTAR O SERVIDOR PARA DENTRO DE CASA.
 *
 * Um destino FTP, SFTP, WebDAV ou S3 é um endereço que o SERVIDOR vai contactar.
 * Deixado solto, qualquer administrador de uma empresa escrevia «127.0.0.1»,
 * «10.0.0.5» ou o endereço de metadados da nuvem e usava as cópias para bater
 * à porta da base de dados ou da rede interna da plataforma (SSRF). Nos destinos
 * das empresas o anfitrião tem de resolver para um endereço público — antes de
 * gravar e antes de cada ligação (o DNS pode mudar entre as duas).
 *
 * A plataforma não passa por aqui: o dono pode querer um NAS na rede dele.
 */
class Rede
{
    public static function exigirPublico(string $anfitriao): void
    {
        $anfitriao = trim($anfitriao, '[] ');

        if ($anfitriao === '' || in_array(strtolower($anfitriao), ['localhost', 'localhost.localdomain'], true)) {
            throw new RuntimeException(__('O endereço do destino tem de ser público.'));
        }

        $ips = filter_var($anfitriao, FILTER_VALIDATE_IP) ? [$anfitriao] : self::resolver($anfitriao);

        if ($ips === []) {
            throw new RuntimeException(__('Não foi possível encontrar o endereço :a.', ['a' => $anfitriao]));
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException(__('O endereço do destino tem de ser público.'));
            }
        }
    }

    /** O anfitrião de um URL. */
    public static function anfitriaoDe(string $url): string
    {
        return (string) parse_url($url, PHP_URL_HOST);
    }

    private static function resolver(string $anfitriao): array
    {
        $ips = @gethostbynamel($anfitriao) ?: [];

        foreach (@dns_get_record($anfitriao, DNS_AAAA) ?: [] as $r) {
            if (! empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
