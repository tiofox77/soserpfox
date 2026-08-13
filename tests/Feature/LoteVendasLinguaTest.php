<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * Os ecrãs de vendas nas três línguas — lote 1 da fase 1.
 *
 * O detector garante que as cadeias TÊM tradução; isto garante que a tradução
 * CHEGA ao ecrã. São perguntas diferentes: uma cadeia pode estar traduzida no
 * JSON e o ecrã continuar a mostrar português porque o __() ficou de fora.
 */
class LoteVendasLinguaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Os ecrãs vivem atrás de permission:invoicing.* — sem elas, tudo dá
        // 403 e o teste media a porta, não a língua.
        setPermissionsTeamId($this->tenant->id);

        foreach ([
            'invoicing.sales.invoices.view', 'invoicing.sales.proformas.view',
            'invoicing.receipts.view', 'invoicing.credit-notes.view',
            'invoicing.debit-notes.view',
        ] as $permissao) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
            $this->user->givePermissionTo($permissao);
        }

        $modulo = \App\Models\Module::firstOrCreate(
            ['slug' => 'invoicing'],
            ['name' => 'Faturação', 'is_active' => true]
        );

        $this->tenant->modules()->syncWithoutDetaching([
            $modulo->id => ['is_active' => true, 'activated_at' => now()],
        ]);
    }

    /** Cada ecrã do lote, na sua palavra mais característica. */
    public static function ecras(): array
    {
        return [
            'facturas'         => ['/invoicing/sales/invoices', 'Faturas de Venda', 'Sales Invoices', 'Factures de vente'],
            'proformas'        => ['/invoicing/sales/proformas', 'Lista de Proformas', 'Proforma Invoice List', 'Liste des factures proforma'],
            'recibos'          => ['/invoicing/receipts', 'Recibos', 'Receipts', 'Reçus'],
            'notas de crédito' => ['/invoicing/credit-notes', 'Notas de Crédito', 'Credit Notes', 'Avoirs'],
            'notas de débito'  => ['/invoicing/debit-notes', 'Notas de Débito', 'Debit Notes', 'Notes de débit'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ecras')]
    public function test_o_ecra_fala_as_tres_linguas(string $rota, string $pt, string $en, string $fr): void
    {
        $this->user->update(['locale' => null]);
        $this->get($rota)->assertOk()->assertSee($pt);

        // Sem assertDontSee do português: o MENU LATERAL ainda não está
        // traduzido (é trabalho do lote 5), e aparece em todas as páginas.
        // Exigir a ausência do PT aqui media o menu, não o ecrã.
        $this->user->update(['locale' => 'en']);
        $this->get($rota)->assertOk()->assertSee($en);

        $this->user->update(['locale' => 'fr']);
        $this->get($rota)->assertOk()->assertSee($fr);
    }

    // ==================== lote 2: o POS ====================

    /**
     * O POS offline nas três línguas — ecrã E dicionário JavaScript.
     *
     * É o único ecrã do sistema onde a tradução tem duas metades que podem
     * falhar em separado: o HTML, que o Blade traduz no servidor, e o
     * JavaScript, que traduz no browser com o dicionário que a página traz.
     * Um pode estar certo e o outro errado.
     */
    public function test_o_pos_offline_fala_as_tres_linguas(): void
    {
        $this->user->update(['locale' => null]);
        $this->get('/invoicing/offline/pos')->assertOk()->assertSee('Carrinho');

        $this->user->update(['locale' => 'en']);
        $this->get('/invoicing/offline/pos')
            ->assertOk()
            ->assertSee('Cart')
            // E o dicionário do JS veio com a página, na mesma língua.
            ->assertSee('window.SOS_LINGUA = "en"', false);

        $this->user->update(['locale' => 'fr']);
        $this->get('/invoicing/offline/pos')
            ->assertOk()
            ->assertSee('Panier')
            ->assertSee('window.SOS_LINGUA = "fr"', false);
    }

    /**
     * A gestão de turnos de caixa.
     *
     * Sem 'if (status === 200)': a rota não tem middleware de permissão,
     * portanto ou abre nas três línguas ou há aqui um problema a sério. Um
     * teste que se cala quando o ecrã dá 403 é um teste que passa pela razão
     * errada — e já me aconteceu duas vezes nesta sessão.
     */
    public function test_os_turnos_de_caixa_falam_as_tres_linguas(): void
    {
        $this->user->update(['locale' => null]);
        $this->get('/invoicing/pos/shift-history')->assertOk()->assertSee('Histórico de Turnos');

        $this->user->update(['locale' => 'en']);
        $this->get('/invoicing/pos/shift-history')->assertOk()->assertSee('Shift History');

        $this->user->update(['locale' => 'fr']);
        $this->get('/invoicing/pos/shift-history')->assertOk()->assertSee('Historique des sessions');
    }
}
