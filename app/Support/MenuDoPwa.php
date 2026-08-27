<?php

namespace App\Support;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Tenant;
use App\Models\User;

/**
 * O menu do PWA: uma definição, e só uma.
 *
 * O menu de baixo, os atalhos do ecrã inicial e a porta de cada rota saem
 * todos daqui. Estavam em três sítios diferentes — o menu no layout, os
 * atalhos no index, e as rotas com um `auth` e mais nada — e três sítios
 * discordam sempre: escondia-se a entrada e o endereço continuava a abrir.
 * Esconder um botão não é fechar uma porta.
 *
 * Uma entrada só aparece (e só abre) quando as TRÊS coisas se verificam:
 *
 *   1. a EMPRESA tem o módulo — sem restaurante não há mesas;
 *   2. o UTILIZADOR tem a permissão — quem não vê clientes no sistema também
 *      não os vê no telemóvel;
 *   3. a empresa não a desligou nas definições — há quem queira o tablet só
 *      com o POS, sem mais nada por onde o empregado se perca.
 *
 * A ordem importa: o módulo e a permissão são regras do sistema e não se
 * negoceiam; a definição da empresa é uma preferência, e só pode TIRAR do que
 * as duas primeiras já deixaram passar.
 *
 * Fora das três fica o Início, que não se desliga nem depende de nada — nem
 * sequer de haver empresa resolvida. Um menu completamente vazio deixa quem lá
 * está sem forma de voltar atrás.
 */
class MenuDoPwa
{
    /**
     * As entradas do PWA, por ordem de apresentação.
     *
     * `fixa` é a que não se desliga: sem o Início não há forma de voltar a
     * lado nenhum, e um menu vazio parece uma aplicação avariada.
     * `destaque` é o botão redondo do meio — o POS, que é a razão de a
     * aplicação existir.
     */
    public const ENTRADAS = [
        'inicio' => [
            'rota'       => 'invoicing.offline.index',
            'icone'      => 'fa-home',
            'etiqueta'   => 'Início',
            'permissao'  => null,
            'modulo'     => null,
            'fixa'       => true,
            'destaque'   => false,
        ],
        'catalogo' => [
            'rota'       => 'invoicing.offline.catalog',
            'icone'      => 'fa-box',
            'etiqueta'   => 'Catálogo',
            'permissao'  => 'invoicing.products.view',
            'modulo'     => 'invoicing',
            'fixa'       => false,
            'destaque'   => false,
        ],
        'restaurante' => [
            'rota'       => 'invoicing.offline.restaurant',
            'icone'      => 'fa-utensils',
            'etiqueta'   => 'Mesas',
            'permissao'  => 'restaurant.orders.view',
            'modulo'     => 'restaurant',
            'fixa'       => false,
            'destaque'   => false,
        ],
        'pos' => [
            'rota'       => 'invoicing.offline.pos',
            'icone'      => 'fa-cash-register',
            'etiqueta'   => 'POS',
            'permissao'  => 'invoicing.pos.access',
            'modulo'     => 'invoicing',
            'fixa'       => false,
            'destaque'   => true,
        ],
        'clientes' => [
            'rota'       => 'invoicing.offline.clients',
            'icone'      => 'fa-users',
            'etiqueta'   => 'Clientes',
            'permissao'  => 'invoicing.clients.view',
            'modulo'     => 'invoicing',
            'fixa'       => false,
            'destaque'   => false,
        ],
        'documentos' => [
            'rota'       => 'invoicing.offline.drafts',
            'icone'      => 'fa-file-invoice',
            'etiqueta'   => 'Documentos',
            'permissao'  => 'invoicing.sales.invoices.view',
            'modulo'     => 'invoicing',
            'fixa'       => false,
            'destaque'   => false,
        ],
    ];

    /**
     * O que já se decidiu neste pedido.
     *
     * O layout desenha o menu e o ecrã inicial volta a perguntar pelos
     * atalhos: sem isto era o dobro das perguntas ao `hasModule()`, que vai à
     * base de dados de cada vez, em todas as páginas do PWA. Vive só durante o
     * pedido — uma permissão retirada faz efeito no pedido seguinte.
     *
     * @var array<string, bool>
     */
    private static array $memoria = [];

    /** Esquece o que foi decidido — para os testes, que mudam permissões a meio. */
    public static function esquecerMemoria(): void
    {
        self::$memoria = [];
    }

    /**
     * As entradas que este utilizador, nesta empresa, pode mesmo usar.
     *
     * @return array<string, array<string, mixed>> chave => definição
     */
    public static function visiveis(?User $utilizador = null, ?Tenant $empresa = null): array
    {
        $utilizador = $utilizador ?: auth()->user();

        if (!$utilizador) {
            return [];
        }

        $empresa = $empresa ?: $utilizador->activeTenant();
        $escolhidas = self::escolhidasPelaEmpresa($empresa);

        return array_filter(
            self::ENTRADAS,
            fn ($def, $chave) => self::podeVer($chave, $utilizador, $empresa, $escolhidas),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Pode este utilizador abrir esta entrada?
     *
     * É esta a função que a rota chama, pelo middleware. Se ela disser que
     * não, não há endereço escrito à mão que valha.
     */
    public static function podeVer(
        string $chave,
        ?User $utilizador = null,
        ?Tenant $empresa = null,
        ?array $escolhidas = null
    ): bool {
        $definicao = self::ENTRADAS[$chave] ?? null;

        if (!$definicao) {
            return false;
        }

        $utilizador = $utilizador ?: auth()->user();

        if (!$utilizador) {
            return false;
        }

        $empresa = $empresa ?: $utilizador->activeTenant();
        $memo = $chave . ':' . $utilizador->id . ':' . ($empresa->id ?? 0);

        if (array_key_exists($memo, self::$memoria)) {
            return self::$memoria[$memo];
        }

        return self::$memoria[$memo] = self::decidir($definicao, $chave, $utilizador, $empresa, $escolhidas);
    }

    /** A decisão em si, sem a memória pelo meio. */
    private static function decidir(
        array $definicao,
        string $chave,
        User $utilizador,
        ?Tenant $empresa,
        ?array $escolhidas
    ): bool {

        // O super admin da plataforma passa: é ele que entra para diagnosticar
        // o que o cliente está a ver, e ficar de fora não ajudaria ninguém.
        if (method_exists($utilizador, 'isSuperAdmin') && $utilizador->isSuperAdmin()) {
            return true;
        }

        // O Início não depende de nada — nem sequer de haver empresa activa.
        // Um menu completamente vazio deixa quem lá está sem forma de voltar
        // atrás, e quem entra sem empresa resolvida precisa mais de uma saída
        // do que de um ecrã em branco.
        if ($definicao['fixa']) {
            return true;
        }

        if (!$empresa) {
            return false;
        }

        if ($definicao['modulo'] && !$empresa->hasModule($definicao['modulo'])) {
            return false;
        }

        if ($definicao['permissao'] && !$utilizador->can($definicao['permissao'])) {
            return false;
        }

        // A escolha da empresa vem por último e só TIRA. (As entradas fixas já
        // saíram por cima: não há escolha a fazer sobre elas.)
        $escolhidas = $escolhidas ?? self::escolhidasPelaEmpresa($empresa);

        return in_array($chave, $escolhidas, true);
    }

    /**
     * As entradas que a empresa deixou ligadas.
     *
     * Sem nada guardado vão TODAS. Uma definição nova não pode apagar o menu
     * de quem já usava a aplicação — quem quiser cortar, corta.
     *
     * @return array<int, string>
     */
    public static function escolhidasPelaEmpresa(?Tenant $empresa): array
    {
        if (!$empresa) {
            return array_keys(self::ENTRADAS);
        }

        try {
            $guardadas = InvoicingSettings::forTenant($empresa->id)->pwa_menu;
        } catch (\Throwable $e) {
            return array_keys(self::ENTRADAS);
        }

        if (!is_array($guardadas)) {
            return array_keys(self::ENTRADAS);
        }

        // Só chaves que ainda existem: uma entrada removida do produto não
        // pode ficar a assombrar as definições guardadas de cada empresa.
        $validas = array_values(array_intersect($guardadas, array_keys(self::ENTRADAS)));

        // Uma lista guardada VAZIA seria um PWA sem menu nenhum — quase de
        // certeza um engano, e um que deixa o tablet inutilizável. Fica o que
        // não se desliga.
        return $validas ?: self::fixas();
    }

    /** @return array<int, string> */
    public static function fixas(): array
    {
        return array_keys(array_filter(self::ENTRADAS, fn ($d) => $d['fixa']));
    }

    /**
     * O que a empresa pode ligar e desligar no ecrã de definições.
     *
     * Só o que faz sentido oferecer-lhe: as entradas fixas não aparecem (não
     * há escolha a fazer) e as de módulos que a empresa não tem também não —
     * oferecer o restaurante a quem não o contratou é prometer o que não há.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function configuraveis(?Tenant $empresa): array
    {
        return array_filter(
            self::ENTRADAS,
            fn ($d) => !$d['fixa'] && (!$d['modulo'] || ($empresa && $empresa->hasModule($d['modulo'])))
        );
    }
}
