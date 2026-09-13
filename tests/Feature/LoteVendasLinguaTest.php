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
            // O POS do PWA passou a exigir a sua (ver App\Support\MenuDoPwa).
            'invoicing.pos.access',
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
            // O título da página é o do documento, como nas outras quatro
            // linhas — «Lista de …» era o cabeçalho que o ecrã em Blade
            // desenhava por dentro, e o miolo hoje é do browser.
            'proformas'        => ['/invoicing/sales/proformas', 'Proformas de Venda', 'Sales Proforma Invoices', 'Factures proforma de vente'],
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
     * O ecrã é desenhado no aparelho (resources/js/pwa/ecras/Pos.tsx) e
     * traduz com o dicionário que a PÁGINA traz — sem rede não há outro. O
     * que se prova: a página vem na língua de quem entra, o dicionário vem
     * com ela e diz «Cart»/«Panier», e o ecrã pede mesmo «Carrinho».
     */
    public function test_o_pos_offline_fala_as_tres_linguas(): void
    {
        $dicionario = function (string $html): array {
            preg_match('#<script type="application/json" id="pwa-dicionario">(.*?)</script>#s', $html, $m);

            return json_decode($m[1] ?? '[]', true) ?: [];
        };

        $this->user->update(['locale' => null]);
        $html = $this->get('/invoicing/offline/pos')->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="pt', $html);
        $this->assertSame([], $dicionario($html), 'em português a chave já é a frase');

        $this->user->update(['locale' => 'en']);
        $html = $this->get('/invoicing/offline/pos')->assertOk()->getContent();
        $this->assertStringContainsString('window.__reactLingua = "en"', $html);
        $this->assertSame('Cart', $dicionario($html)['Carrinho'] ?? null);

        $this->user->update(['locale' => 'fr']);
        $html = $this->get('/invoicing/offline/pos')->assertOk()->getContent();
        $this->assertStringContainsString('window.__reactLingua = "fr"', $html);
        $this->assertSame('Panier', $dicionario($html)['Carrinho'] ?? null);

        $pos = implode("
", array_map('file_get_contents', array_merge(
            [resource_path('js/pwa/ecras/Pos.tsx')],
            glob(resource_path('js/pwa/ecras/pos/*.tsx')) ?: [],
        )));
        $this->assertStringContainsString("t('Carrinho')", $pos, 'o ecrã do POS tem de pedir a frase ao dicionário');
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
