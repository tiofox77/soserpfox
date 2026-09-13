<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * O QUE AS PÁGINAS DE ENTRADA SABEM DO PEDIDO — entrar, recuperar a senha,
 * aceitar um convite, a conta bloqueada.
 *
 * Eram Blade soltos, cada um com o Tailwind e o Font Awesome de um CDN (que a
 * instalação offline não alcança) e cada um a esquecer uma parte: o login não
 * mostrava o `->with('error')` de quem o mandava para lá (o limite de
 * utilizadores ao aceitar um convite morria ali), o check-in expresso não
 * mostrava o erro de uma reserva que não o permitia.
 *
 * Os formulários continuam a ser formulários de verdade — POST para os
 * controladores de sempre, com o token, os erros e o que a pessoa escreveu a
 * voltarem na sessão. Esta classe só junta isso para o ecrã em React.
 */
final class Entrada
{
    /** O comum a todas: o token, os erros da validação, o que se tinha escrito, os recados e o contacto. */
    public static function comum(array $recadosSem = []): array
    {
        $erros = session('errors');

        return [
            'csrf' => csrf_token(),
            'site' => route('landing.home'),
            'erros' => $erros ? array_map(fn ($m) => $m[0], $erros->getBag('default')->messages()) : [],
            // Nunca as senhas nem o token de recuperação: não voltam ao browser.
            'antigos' => array_diff_key(
                (array) session()->getOldInput(),
                array_flip(['password', 'password_confirmation', '_token', 'token']),
            ),
            'recados' => RecadosDaSessao::lista($recadosSem),
            'contacto' => self::contacto(),
        ];
    }

    /** O contacto da plataforma — o das definições, o mesmo da página inicial do site. */
    public static function contacto(): array
    {
        return [
            'email' => (string) SystemSetting::get('contact_email', 'contato@soserp.vip'),
            'telefone' => (string) SystemSetting::get('contact_phone', '+244 939 729 902'),
        ];
    }
}
