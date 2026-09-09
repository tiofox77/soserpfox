<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * NENHUM MÓDULO VOLTA A FICAR SEM GUARDA.
 *
 * O levantamento que abriu este trabalho: **71 rotas** em cinco módulos —
 * hotel, salão, oficina, contabilidade e eventos — corriam com
 * `['auth', 'tenant.module:x']` e mais nada. As permissões existiam, apareciam
 * no ecrã de papéis, e nenhuma rota as exigia.
 *
 * Este ensaio é o travão. Uma rota nova sem `permission:` num destes módulos
 * põe a suite vermelha, e quem a escreve tem de decidir a permissão — ou
 * declarar aqui, por escrito, que é pública de propósito.
 *
 * Não vale para tudo: há módulos que ainda não passaram por esta limpeza, e
 * pô-los aqui era pôr a suite vermelha por trabalho que ainda não foi feito.
 * A lista cresce à medida que cada um é fechado.
 */
class ModulosComGuardaTest extends TenantTestCase
{
    /**
     * Os módulos já fechados — que são TODOS.
     *
     * Começou nos cinco que corriam sem guarda nenhuma; a facturação entrou a
     * seguir, quando se viu que as LISTAS pediam permissão e os irmãos — o
     * editor, o PDF, a pré-visualização — não pediam nada.
     */
    private const FECHADOS = [
        'hotel', 'salon', 'workshop', 'accounting', 'events',
        'invoicing', 'notifications', 'hr', 'treasury', 'restaurant',
        'crm', 'compras', 'projetos', 'inventario',
    ];

    /**
     * AS ROTAS PÚBLICAS DE PROPÓSITO — e porquê.
     *
     * Todas elas identificam a empresa por outra coisa que não a sessão. Uma
     * entrada nova aqui é uma decisão, e tem de trazer a razão.
     */
    private const PUBLICAS = [
        // O check-in por QR: quem autoriza é o `confirmation_code` do URL. O
        // hóspede lê o código no telemóvel e não tem conta nenhuma.
        'hotel/reservations/{id}/checkin/{code}',
        'hotel/reservations/{id}/checkin/{code}/confirm',

        // A página de reservas do hotel: quem escolhe a empresa é o SLUG.
        'hotel/booking/{slug}',

        /*
         * AS PORTAS DO PWA. Não são ecrãs: são a entrada, a saída e a casca.
         *
         * As três primeiras vivem FORA do `auth` de propósito — quem lá chega
         * não tem sessão nem rede, e é isso que a página resolve. O `sair` tem
         * de ser um nada-a-fazer quando a sessão já caiu, senão o trabalho
         * ficava a falhar para sempre na fila do aparelho.
         */
        'invoicing/offline/login',
        'invoicing/offline/pin-esquecido',
        'invoicing/offline/sair',
        'invoicing/offline/exit',

        // O PIN de turno é de cada um: não há permissão a pedir para alguém
        // definir o seu próprio.
        'invoicing/offline/pin',

        // A casca do PWA e o molde do documento que o aparelho preenche sem
        // rede — o que se pode ver lá dentro é que passa pelo `pwa:`.
        'invoicing/offline',
        'invoicing/offline/molde/{tipo}',
    ];

    /**
     * GUARDAS QUE NÃO SE CHAMAM `permission:`.
     *
     * O PWA offline tem a sua — o `pwa:<chave>` pergunta o mesmo que o menu:
     * o módulo da empresa, a permissão do utilizador e a escolha da empresa
     * nas definições. Exigir-lhe `permission:` era pedir duas vezes a mesma
     * coisa, e a segunda não sabe das outras duas.
     */
    private const OUTRAS_GUARDAS = ['pwa:'];

    /** @test */
    public function nenhuma_rota_destes_modulos_corre_sem_permissao(): void
    {
        $semGuarda = [];

        foreach (Route::getRoutes() as $rota) {
            $uri = $rota->uri();

            if (! preg_match('#^(' . implode('|', self::FECHADOS) . ')/#', $uri)) {
                continue;
            }

            if (in_array($uri, self::PUBLICAS, true)) {
                continue;
            }

            $temGuarda = collect($rota->gatherMiddleware())->contains(
                fn ($m) => str_starts_with($m, 'permission:')
                    || collect(self::OUTRAS_GUARDAS)->contains(fn ($g) => str_starts_with($m, $g))
            );

            if (! $temGuarda) {
                $semGuarda[] = $rota->methods()[0] . ' ' . $uri;
            }
        }

        $this->assertSame(
            [],
            $semGuarda,
            "Rotas sem permissão nos módulos já fechados:\n  " . implode("\n  ", $semGuarda)
                . "\n\nOu se lhes dá a permissão, ou se declara em PUBLICAS com a razão."
        );
    }

    /**
     * A PERMISSÃO NÃO É DE UM IRMÃO.
     *
     * Uma guarda posta na rota errada é pior do que nenhuma: parece protegida
     * e protege outra coisa. Aconteceu ao aplicar as da facturação em massa —
     * `invoices.pdf` existe nas VENDAS e nas COMPRAS, e as de compra ficaram a
     * pedir a permissão de venda. Quem tivesse facturas de compra e não de
     * venda deixava de ver as suas, e quem tivesse as de venda via as de
     * compra sem ter direito.
     *
     * O QUE ESTE ENSAIO PERSEGUE É ESSA TROCA, e só ela: quando a rota e a
     * permissão partilham o pai mas divergem no filho. Exigir que o nome da
     * permissão fosse sempre prefixo do nome da rota não dava: as duas coisas
     * foram baptizadas em alturas diferentes (`hr.payroll` pede
     * `payroll.process`, `crm.oportunidades` pede `crm.opportunities.view`), e
     * um ensaio cheio de excepções deixa de guardar seja o que for.
     */
    /**
     * OS PARES QUE SE CONFUNDEM — porque partilham o resto do nome.
     *
     * `invoices.pdf` e `proformas.create` existem dos dois lados, e a única
     * coisa que os distingue é este segmento. A lista é curta de propósito:
     * um ensaio que tentasse adivinhar a família por heurística enchia-se de
     * excepções e deixava de guardar seja o que for.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const IRMAOS = [
        ['sales', 'purchases'],
        ['credit-notes', 'debit-notes'],
    ];

    /** @test */
    public function a_permissao_de_uma_rota_nao_e_a_de_um_irmao(): void
    {
        $trocadas = [];

        foreach (Route::getRoutes() as $rota) {
            $nome = $rota->getName();

            if (! $nome || ! preg_match('#^(' . implode('|', self::FECHADOS) . ')\.#', $nome)) {
                continue;
            }

            foreach ($rota->gatherMiddleware() as $m) {
                if (! str_starts_with($m, 'permission:')) {
                    continue;
                }

                $permissao = substr($m, strlen('permission:'));

                foreach (self::IRMAOS as [$um, $outro]) {
                    foreach ([[$um, $outro], [$outro, $um]] as [$daRota, $daPermissao]) {
                        if (str_contains($nome, ".{$daRota}.") && str_contains($permissao, ".{$daPermissao}.")) {
                            $trocadas[] = "{$nome} pede {$permissao} — «{$daPermissao}» onde a rota diz «{$daRota}»";
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $trocadas,
            "Rotas guardadas pela permissão de um irmão:\n  " . implode("\n  ", $trocadas)
        );
    }

    /**
     * E O MENU NÃO OFERECE O QUE A ROTA RECUSA.
     *
     * Uma entrada de menu SEM GUARDA NENHUMA, para uma rota que exige
     * permissão, manda o utilizador para um 403 — que se lê como avaria do
     * sistema quando é a guarda a funcionar.
     *
     * TRÊS FORMAS CONTAM COMO GUARDA, e o menu usa as três: `permissao` (uma),
     * `qualquer` (várias, basta uma) e `quando` (uma condição própria, para o
     * que as outras duas não dizem). O que este ensaio persegue é a entrada
     * que não tem nenhuma — e, quando tem `permissao`, que seja mesmo a que a
     * rota exige.
     */
    /** @test */
    public function o_menu_nao_oferece_o_que_a_rota_recusa(): void
    {
        // A permissão que cada rota exige, pela rota.
        $exigidas = [];

        foreach (Route::getRoutes() as $rota) {
            $nome = $rota->getName();

            if (! $nome || ! preg_match('#^(' . implode('|', self::FECHADOS) . ')\.#', $nome)) {
                continue;
            }

            foreach ($rota->gatherMiddleware() as $m) {
                if (str_starts_with($m, 'permission:')) {
                    $exigidas[$nome] = substr($m, strlen('permission:'));
                    break;
                }
            }
        }

        $semGuarda = [];
        $trocadas = [];

        foreach ($this->entradasDoMenu() as $entrada) {
            $rota = $entrada['rota'] ?? null;

            if (! $rota || ! isset($exigidas[$rota])) {
                continue;
            }

            $pede = $exigidas[$rota];
            $declarada = $entrada['permissao'] ?? null;

            if ($declarada === null) {
                if (! isset($entrada['qualquer']) && ! isset($entrada['quando'])) {
                    $semGuarda[] = $rota . ' (a rota pede ' . $pede . ')';
                }

                continue;
            }

            // Declarou uma — tem de ser a mesma, ou uma das alternativas.
            if (! in_array($declarada, explode('|', $pede), true)) {
                $trocadas[] = $rota . ': o menu pede ' . $declarada . ', a rota pede ' . $pede;
            }
        }

        $this->assertSame(
            [],
            $semGuarda,
            "Entradas de menu sem guarda nenhuma, para rotas que exigem permissão:\n  "
                . implode("\n  ", $semGuarda)
        );

        $this->assertSame(
            [],
            $trocadas,
            "Entradas de menu com uma permissão diferente da que a rota exige:\n  "
                . implode("\n  ", $trocadas)
        );
    }

    /**
     * As entradas do esquema do menu, sem as resolver para um utilizador.
     *
     * @return array<int, array<string, mixed>>
     */
    private function entradasDoMenu(): array
    {
        $metodo = new \ReflectionMethod(\App\Support\MenuDaCasca::class, 'grupos');
        $metodo->setAccessible(true);

        $entradas = [];

        foreach ($metodo->invoke(null) as $grupo) {
            foreach ($grupo['entradas'] ?? [] as $entrada) {
                if (isset($entrada['sub'])) {
                    foreach ($entrada['sub'] as $sub) {
                        $entradas[] = $sub;
                    }

                    continue;
                }

                $entradas[] = $entrada;
            }
        }

        return $entradas;
    }
}
