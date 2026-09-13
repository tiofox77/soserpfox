<?php

namespace App\Support;

use App\Http\Controllers\PwaController;
use Closure;
use Illuminate\Http\Response;

/**
 * A PÁGINA DO PWA — uma casca só, para os onze ecrãs.
 *
 * Eram onze Blade com Alpine, cada um a carregar o motor (`pwa-invoicing.js`),
 * o papel e o turno por `<script>`, e o layout a juntar mais quatrocentas
 * linhas de JavaScript à mão (a entrada sem rede, o convite a instalar, a
 * barra do estado). Agora o servidor manda sempre a MESMA casca e o pacote do
 * PWA (`resources/js/pwa.tsx`) desenha o ecrã.
 *
 * O ECRÃ SAI DO ENDEREÇO, E NÃO SÓ DO SERVIDOR. Sem rede, o service worker
 * serve a página guardada — e quando a pedida não está guardada serve OUTRA
 * (o POS) debaixo desse endereço. Com um Blade por ecrã isso punha o POS no
 * lugar dos clientes. Com uma casca só, qualquer página guardada desenha
 * qualquer ecrã: o React olha para o endereço. O `ecra` que vem daqui é o
 * recurso para quando o endereço não diz nada (o 403 de «sem acesso»).
 *
 * As regras de quem vê o quê continuam a ser do servidor (`MenuDoPwa`), e o
 * menu vai nas props: é também por ele que o ecrã servido de recurso sabe se
 * a entrada pedida é deste utilizador.
 */
final class PaginaDoPwa
{
    /** Os ecrãs e o título de cada um. As chaves são as do registo em `resources/js/pwa/ecras.ts`. */
    public const ECRAS = [
        'entrada' => 'Entrar',
        'pin-esquecido' => 'Esqueci o PIN',
        'inicio' => 'Início',
        'catalogo' => 'Catálogo',
        'clientes' => 'Clientes',
        'novo-cliente' => 'Novo cliente',
        'documentos' => 'Documentos',
        'novo-documento' => 'Novo documento',
        'pos' => 'POS',
        'restaurante' => 'Mesas',
        'sem-acesso' => 'Sem acesso',
    ];

    /** As que abrem sem sessão — a entrada e o PIN esquecido. Não levam menu nem utilizador. */
    public const PUBLICAS = ['entrada', 'pin-esquecido'];

    /** Para as rotas: `Route::get('/pos', PaginaDoPwa::rota('pos'))`. */
    public static function rota(string $ecra): Closure
    {
        return fn () => self::resposta($ecra);
    }

    public static function resposta(string $ecra, array $extras = [], int $estado = 200): Response
    {
        abort_unless(isset(self::ECRAS[$ecra]), 404);

        return response()->view('pwa.ecra', [
            'ecra' => $ecra,
            'titulo' => __(self::ECRAS[$ecra]),
            'props' => self::props($ecra, $extras),
            'dicionario' => DicionarioDoReact::frases(),
        ], $estado);
    }

    /** O que o ecrã sabe do servidor. Tudo o resto vem da base local. */
    public static function props(string $ecra, array $extras = []): array
    {
        $versao = app(PwaController::class);

        $props = [
            'ecra' => $ecra,
            'csrf' => csrf_token(),
            'lingua' => app()->getLocale(),
            'versao' => [
                'numero' => $versao->numeroDeVersao(),
                'assinatura' => $versao->buildVersion(),
                'etiqueta' => $versao->buildLabel(),
            ],
            'rotas' => self::rotas(),
        ];

        if (in_array($ecra, self::PUBLICAS, true)) {
            $erros = session('errors');

            return $props + [
                'erro' => $erros ? $erros->first() : null,
                'email' => (string) old('email', ''),
            ] + $extras;
        }

        $utilizador = auth()->user();

        return $props + [
            'utilizador' => $utilizador ? [
                'id' => $utilizador->id,
                'nome' => $utilizador->name,
                'email' => $utilizador->email,
            ] : null,
            'empresa' => activeTenantId(),
            'menu' => self::menu(),
            // As 21 províncias da reforma de 2024. O formulário do cliente tinha
            // as 18 de antes, escritas à mão.
            'provincias' => Geografia::provincias(),
        ] + $extras;
    }

    /**
     * O menu de baixo, já decidido: módulo, permissão e escolha da empresa.
     *
     * @return list<array{chave: string, url: string, icone: string, etiqueta: string, destaque: bool}>
     */
    public static function menu(): array
    {
        $entradas = [];

        foreach (MenuDoPwa::visiveis() as $chave => $def) {
            $entradas[] = [
                'chave' => $chave,
                'url' => route($def['rota'], [], false),
                'icone' => $def['icone'],
                'etiqueta' => __($def['etiqueta']),
                'destaque' => (bool) $def['destaque'],
            ];
        }

        return $entradas;
    }

    /** Os endereços, relativos: um host de APP_URL diferente da origem servida era outra chave no cache. */
    public static function rotas(): array
    {
        $r = fn (string $nome) => route($nome, [], false);

        return [
            'entrada' => $r('invoicing.offline.login'),
            'pinEsquecido' => $r('invoicing.offline.pin-esquecido'),
            'inicio' => $r('invoicing.offline.index'),
            'catalogo' => $r('invoicing.offline.catalog'),
            'clientes' => $r('invoicing.offline.clients'),
            'novoCliente' => $r('invoicing.offline.client-new'),
            'documentos' => $r('invoicing.offline.drafts'),
            'novoDocumento' => $r('invoicing.offline.draft-new'),
            'pos' => $r('invoicing.offline.pos'),
            'restaurante' => $r('invoicing.offline.restaurant'),
            'sair' => $r('invoicing.offline.sair'),
            'aplicacao' => $r('invoicing.offline.exit'),
            'definirPin' => $r('invoicing.offline.pin'),
            'login' => $r('login'),
            'subscricaoExpirada' => $r('subscription.expired'),
        ];
    }
}
