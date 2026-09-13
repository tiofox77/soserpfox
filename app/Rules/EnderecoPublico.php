<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * UM ENDEREÇO QUE O SERVIDOR VAI VISITAR TEM DE SER DA INTERNET PÚBLICA.
 *
 * O `base_url` da ligação ao KiandaStay era qualquer `url`: com
 * `http://127.0.0.1:8080/…` o servidor pedia a si próprio (ou à rede interna do
 * alojamento) e devolvia a resposta, ou dizia pelas mensagens de erro que portas
 * estavam abertas (auditoria de segurança de 2026-09-13). Exige-se https e que
 * nenhum dos endereços para onde o nome aponta seja interno.
 */
class EnderecoPublico implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $partes = parse_url((string) $value);

        if (($partes['scheme'] ?? '') !== 'https' || empty($partes['host']) || isset($partes['user']) || isset($partes['pass'])) {
            $fail(__('Use um endereço https:// do site.'));

            return;
        }

        if (! self::permite((string) $value)) {
            $fail(__('Esse endereço não é da internet pública.'));
        }
    }

    /**
     * Pode o servidor visitar este endereço? https e nenhum IP interno.
     *
     * Um nome que (ainda) não resolve não leva a lado nenhum e passa; quem faz o
     * pedido volta a chamar isto no momento de sair — um DNS mudado depois de
     * gravado não leva o servidor à rede interna.
     */
    public static function permite(string $url): bool
    {
        $partes = parse_url($url);
        $anfitriao = $partes['host'] ?? '';

        if (($partes['scheme'] ?? '') !== 'https' || $anfitriao === '') {
            return false;
        }

        $anfitriao = trim($anfitriao, '[]');
        $ips = filter_var($anfitriao, FILTER_VALIDATE_IP) ? [$anfitriao] : (gethostbynamel($anfitriao) ?: []);

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
