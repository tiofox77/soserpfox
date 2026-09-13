<?php

namespace Tests\Feature;

use App\Models\Module;
use Tests\TenantTestCase;

/**
 * O MENU LATERAL NÃO MUDA SEM SE DAR POR ISSO.
 *
 * O menu saiu de 1.500 linhas de Blade para o `MenuDaCasca`. Antes da mudança
 * gravou-se o que o menu de sempre mostrava a dois utilizadores — um vulgar
 * com todas as permissões e todos os módulos, e o super admin da plataforma —
 * e este ensaio compara o menu de hoje com essa gravação: as mesmas ligações,
 * com os mesmos textos, pela mesma ordem, com o mesmo acesso.
 *
 * A barra lateral é hoje só o ecrã `casca`, em React (a de Blade saiu em
 * 2026-09-13). O que se compara é o que a PÁGINA lhe entrega nas props, lido
 * na mesma sequência em que a barra de Blade o desenhava: um cabeçalho, uma
 * ligação, um botão de grupo, e por aí fora.
 *
 * Para voltar a gravar (só quando a mudança no menu é deliberada):
 *   GRAVAR_MENU=1 php artisan test --filter=MenuDaCascaFielTest
 */
class MenuDaCascaFielTest extends TenantTestCase
{
    private const PASTA = __DIR__ . '/../fixtures/menu-da-casca/';

    /** Os módulos que existem — a base de ensaio nasce sem nenhum. */
    private const MODULOS = [
        'compras', 'contabilidade', 'crm', 'eventos', 'hotel', 'inventario', 'invoicing',
        'notifications', 'oficina', 'projetos', 'restaurant', 'rh', 'salon', 'treasury',
    ];

    /** Todas as permissões que o menu pergunta. */
    private const PERMISSOES = [
        'invoicing.dashboard.view', 'invoicing.pos.access', 'invoicing.pos.sell', 'invoicing.pos.reports',
        'invoicing.clients.view', 'invoicing.suppliers.view', 'invoicing.products.view', 'invoicing.categories.view',
        'invoicing.brands.view', 'invoicing.sales.proformas.view', 'invoicing.sales.quotes.view',
        'invoicing.sales.invoices.view', 'invoicing.purchases.proformas.view', 'invoicing.purchases.invoices.view',
        'invoicing.imports.view', 'invoicing.receipts.view', 'invoicing.credit-notes.view', 'invoicing.debit-notes.view',
        'invoicing.transport-guides.view', 'invoicing.advances.view', 'invoicing.warehouses.view', 'invoicing.stock.view',
        'invoicing.warehouse-transfer.view', 'invoicing.inter-company-transfer.view', 'invoicing.reports.view',
        'invoicing.taxes.view', 'invoicing.series.view', 'invoicing.settings.view', 'invoicing.saft.view', 'invoicing.agt.view',
        'treasury.reports.view', 'treasury.accounts.view', 'treasury.transactions.view', 'treasury.transfers.view',
        'treasury.payment-methods.view', 'treasury.banks.view', 'treasury.cash-registers.view',
        'events.dashboard.view', 'accounting.dashboard.view', 'workshop.dashboard.view',
        // O RH: cada entrada do menu passou a estar atrás da permissão que a
        // rota exige, para o menu não oferecer o que a guarda recusa.
        'hr.dashboard.view', 'employees.view', 'hr.departments.view', 'hr.positions.view',
        'hr.contracts.view', 'attendance.manage', 'hr.vacations.view', 'hr.leaves.view',
        'hr.overtime.view', 'hr.discounts.view', 'hr.shifts.view', 'payroll.process',
        'hr.advances.view', 'hr.irt.view', 'hr.reports.view', 'hr.settings.view',
        'hotel.dashboard.view', 'salon.dashboard.view', 'notifications.view', 'inventario.dashboard.view',
        'restaurant.dashboard.view', 'restaurant.floor.view', 'restaurant.orders.view', 'restaurant.settings.view',
        'restaurant.kitchen.view', 'restaurant.reservations.view', 'restaurant.recipes.view', 'restaurant.stock.view',
        // A CARTA passou a ser `restaurant.menu.*` e não `orders.*`: os ecrãs
        // em React exigem a permissão que corresponde ao que se faz, e o menu
        // acompanhou. Ver `permissions:sync-restaurante`.
        'restaurant.reports.view', 'restaurant.menu.view',
        // A OFICINA E O SALÃO: as entradas do menu passaram a estar atrás da
        // permissão que a rota exige, como as do RH.
        'workshop.vehicles.view', 'workshop.mechanics.view', 'workshop.services.view',
        'workshop.parts.view', 'workshop.work-orders.view', 'workshop.reports.view',
        'salon.appointments.view', 'salon.clients.view', 'salon.services.view',
        'salon.categories.view', 'salon.professionals.view', 'salon.products.view',
        'salon.pos.access', 'salon.reports.view', 'salon.settings.view',
        // O HOTEL, pela mesma razão.
        'hotel.reservations.view', 'hotel.reservations.edit', 'hotel.checkout.manage', 'hotel.walk-in.create',
        'hotel.housekeeping.view', 'hotel.maintenance.view', 'hotel.staff.view',
        'hotel.rooms.view', 'hotel.room-types.view', 'hotel.guests.view',
        'hotel.reports.view', 'hotel.rates.view', 'hotel.packages.view', 'hotel.settings.view',
        // A CONTABILIDADE E OS EVENTOS fecham a lista dos cinco módulos.
        'accounting.accounts.view', 'accounting.journals.view', 'accounting.document-types.view',
        'accounting.moves.view', 'accounting.periods.view', 'accounting.reports.view',
        'accounting.reconciliation.view', 'accounting.fixed-assets.view', 'accounting.currencies.view',
        'accounting.cost-centers.view', 'accounting.analytics.view', 'accounting.budgets.view',
        'accounting.settings.view',
        'events.calendar.view', 'events.reports.view', 'events.equipment.view',
        'events.venues.view', 'events.types.view', 'events.technicians.view',
        // E as entradas que ficaram atrás da permissão quando a facturação
        // deixou de ter rotas a correr só com `auth`.
        'invoicing.product-batches.view', 'invoicing.reports.view', 'notifications.view',
        'users.manage', 'crm.view', 'crm.leads.view', 'crm.opportunities.view',
        'inventario.view', 'inventario.contagem.manage', 'compras.view', 'projetos.view',
        'crm.dashboard.view', 'crm.integrations.manage', 'compras.dashboard.view', 'compras.requisicoes.view',
        'compras.encomendas.view', 'projetos.dashboard.view', 'projetos.tarefas.view', 'projetos.horas.registar',
        'settings.view',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Uma empresa inactiva não tem módulo nenhum — e a de bancada nasce sem a marca.
        $this->tenant->forceFill(['is_active' => true])->save();
    }

    /** @test */
    public function o_menu_de_um_utilizador_com_tudo_e_o_de_sempre(): void
    {
        foreach (self::MODULOS as $slug) {
            $this->comModulo($slug);
        }
        $this->comPermissoes(...self::PERMISSOES);

        $this->comparar('utilizador-com-tudo', $this->menuDe('/home'));
    }

    /** @test */
    public function o_menu_do_super_admin_e_o_de_sempre(): void
    {
        $this->user->forceFill(['is_super_admin' => true])->save();

        // O /home do super admin tem outro layout; a conta é a página comum a todos.
        $this->comparar('super-admin', $this->menuDe('/my-account'));
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** As ligações, cabeçalhos e títulos do menu, pela ordem em que aparecem. */
    private function menuDe(string $caminho): array
    {
        $r = $this->get($caminho);

        if ($r->isRedirect()) {
            $r = $this->get($r->headers->get('Location'));
        }

        $r->assertOk();

        preg_match('/data-peca="casca"\s+data-props="([^"]*)"/', $r->getContent(), $m);
        $this->assertNotEmpty($m, 'a página não montou a casca');
        $menu = json_decode(html_entity_decode($m[1], ENT_QUOTES), true)['menu'];

        $origem = rtrim(config('app.url'), '/');
        $texto = fn (string $t) => trim(preg_replace('/\s+/u', ' ', $t));
        $a = fn (array $e) => array_filter([
            'tag' => 'a',
            'href' => str_replace($origem, '', $e['url']),
            'texto' => $texto((isset($e['prefixo']) ? $e['prefixo'].' ' : '').$e['rotulo']),
            'activo' => ! empty($e['activo']) ? true : null,
        ], fn ($v) => $v !== null);

        $nav = [['tag' => 'p', 'texto' => __('Menu Principal')]];

        foreach ($menu['principal'] as $e) {
            $nav[] = $a($e);
        }

        foreach ($menu['grupos'] as $g) {
            if ($g['simples']) {
                $nav[] = $a($g);
                foreach ($g['entradas'] as $e) {
                    $nav[] = $a($e);
                }
                continue;
            }

            $nav[] = ['tag' => 'button', 'texto' => $texto($g['rotulo'])];

            foreach ($g['entradas'] as $e) {
                if (isset($e['separador'])) {
                    continue;
                }
                if (isset($e['sub'])) {
                    $sub = $e['sub'];
                    $nav[] = ['tag' => 'button', 'texto' => $texto((isset($sub['prefixo']) ? $sub['prefixo'].' ' : '').$sub['rotulo'])];
                    foreach ($sub['entradas'] as $se) {
                        if (isset($se['titulo']) || isset($se['separador'])) {
                            continue;
                        }
                        $nav[] = $a($se);
                    }
                    continue;
                }
                $nav[] = $a($e);
            }
        }

        foreach ($menu['superadmin'] as $seccao) {
            $nav[] = ['tag' => 'p', 'texto' => $seccao['titulo']];
            foreach ($seccao['entradas'] as $e) {
                $nav[] = $a($e);
            }
        }

        $u = $menu['utilizador'];
        $rodape = [
            // O botão de fechar do telemóvel.
            ['tag' => 'button', 'texto' => ''],
            array_filter([
                'tag' => 'a',
                'href' => str_replace($origem, '', $menu['suporte']['url']),
                'texto' => $texto($menu['suporte']['rotulo'].' '.$menu['suporte']['extra']),
                'activo' => ! empty($menu['suporte']['activo']) ? true : null,
            ], fn ($v) => $v !== null),
            ['tag' => 'button', 'texto' => $texto($u['nome'].' '.$u['papel'])],
        ];
        foreach ($u['ligacoes'] as $l) {
            $rodape[] = ['tag' => 'a', 'href' => str_replace($origem, '', $l['url']), 'texto' => $texto($l['rotulo'])];
        }
        $rodape[] = ['tag' => 'a', 'href' => str_replace($origem, '', $u['atualizacoes']['url']), 'texto' => $texto($u['atualizacoes']['rotulo'].' '.$u['atualizacoes']['versao'])];
        $rodape[] = ['tag' => 'button', 'texto' => __('Sair')];

        return ['nav' => $nav, 'rodape' => $rodape];
    }

    private function comparar(string $nome, array $menu): void
    {
        $ficheiro = self::PASTA . $nome . '.json';

        if (getenv('GRAVAR_MENU')) {
            @mkdir(self::PASTA, 0777, true);
            file_put_contents($ficheiro, json_encode($menu, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $this->assertFileExists($ficheiro);

            return;
        }

        $this->assertFileExists($ficheiro, 'Falta a gravação — corre com GRAVAR_MENU=1 uma vez, com o menu de sempre.');

        $gravado = json_decode(file_get_contents($ficheiro), true);

        $this->assertSame($gravado['nav'], $menu['nav'], 'O menu lateral deixou de ser o que era.');
        $this->assertSame($gravado['rodape'], $menu['rodape'], 'O rodapé da barra lateral deixou de ser o que era.');
    }
}
