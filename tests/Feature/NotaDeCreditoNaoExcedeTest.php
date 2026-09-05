<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\CreditNotes\CreditNoteCreate;
use App\Livewire\Invoicing\DebitNotes\DebitNoteCreate;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\SalesInvoice;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Uma nota de crédito não anula mais do que a factura tinha.
 *
 * PORQUE EXISTE. A AGT recusou uma nota real com E43 — «a soma dos valores a
 * anular excede o montante ainda não anulado do documento base». A factura
 * levava 34 almoços; a nota levava 69. E 69 é 34 mais os 35 que o operador
 * escreveu: o carrinho tratava a quantidade escrita como RELATIVA e somava-a à
 * que lá estava, em vez de a substituir.
 *
 * O documento nasceu com número fiscal, hash e assinatura, e só dias depois a
 * AGT disse que não servia. Por isso são duas correcções e não uma: a
 * quantidade passa a substituir, e o excesso é travado ANTES de o número ser
 * atribuído.
 */
class NotaDeCreditoNaoExcedeTest extends TenantTestCase
{
    private function fonte(string $ecra): string
    {
        return file_get_contents(app_path("Livewire/Invoicing/{$ecra}"));
    }

    private function factura(float $total): SalesInvoice
    {
        return SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT TESTE/' . random_int(1000, 9999),
            'invoice_date'   => now()->toDateString(),
            'status'         => 'sent',
            'total'          => $total,
            'created_by'     => $this->user->id,
        ]);
    }

    private function nota(SalesInvoice $factura, float $total): CreditNote
    {
        return CreditNote::create([
            'tenant_id'          => $this->tenant->id,
            'client_id'          => $factura->client_id,
            'invoice_id'         => $factura->id,
            'credit_note_number' => 'NC ' . strtoupper(substr(uniqid(), -8)),
            'issue_date'         => now()->toDateString(),
            'status'             => 'issued',
            'reason'             => 'return',
            'subtotal'           => $total,
            'tax_amount'         => 0,
            'total'              => $total,
            'type'               => 'total',
            'created_by'         => $this->user->id,
        ]);
    }

    /**
     * A QUANTIDADE ESCRITA SUBSTITUI.
     *
     * `update($id, ['quantity' => 35])` com um escalar ACRESCENTA 35 no
     * darryldecode/cart. Só a forma com `relative => false` troca o valor.
     *
     * @test
     */
    public function a_quantidade_das_notas_substitui_em_vez_de_somar(): void
    {
        $ecras = [
            'notas de crédito' => 'CreditNotes/CreditNoteCreate.php',
            'notas de débito'  => 'DebitNotes/DebitNoteCreate.php',
        ];

        foreach ($ecras as $nome => $ficheiro) {
            $fonte = $this->fonte($ficheiro);

            $ini = strpos($fonte, 'function updateQuantity(');
            $this->assertNotFalse($ini, "{$nome}: falta o método que muda a quantidade");

            $corpo = substr($fonte, $ini, 600);

            $this->assertStringContainsString("'relative' => false", $corpo,
                "{$nome}: a quantidade escrita tem de SUBSTITUIR — em escalar, o carrinho soma");
            $this->assertStringNotContainsString("['quantity' => \$quantity]", $corpo,
                "{$nome}: a forma escalar é a que soma");
        }
    }

    /**
     * OS OUTROS ECRÃS JÁ ESTAVAM CERTOS — E TÊM DE CONTINUAR.
     *
     * Facturas, proformas, orçamentos e POS sempre passaram `relative => false`.
     * Se alguém voltar à forma escalar num deles, o mesmo estrago repete-se.
     *
     * @test
     */
    public function nenhum_ecra_de_documentos_volta_a_forma_que_soma(): void
    {
        $ecras = [
            'Sales/InvoiceCreate.php',
            'Sales/ProformaCreate.php',
            'Sales/QuoteCreate.php',
            'Purchases/InvoiceCreate.php',
            'Purchases/ProformaCreate.php',
        ];

        foreach ($ecras as $ficheiro) {
            $this->assertStringNotContainsString("['quantity' => \$quantity]", $this->fonte($ficheiro),
                basename($ficheiro) . ': a forma escalar soma em vez de substituir');
        }
    }

    /**
     * O TRAVÃO ESTÁ ANTES DO NÚMERO FISCAL.
     *
     * De nada serve recusar depois: o documento já ficou numerado, assinado e
     * com a factura marcada como anulada.
     *
     * @test
     */
    public function o_excesso_e_travado_antes_de_o_documento_nascer(): void
    {
        $fonte = $this->fonte('CreditNotes/CreditNoteCreate.php');

        $travao = strpos($fonte, 'porAnular');
        $criacao = strpos($fonte, 'CreditNote::create(');

        $this->assertNotFalse($travao, 'falta o travão do excesso');
        $this->assertNotFalse($criacao);
        $this->assertLessThan($criacao, $travao, 'o travão tem de vir ANTES de a nota ser criada');

        // Conta o que outras notas já tiraram: duas parciais somam. E pela
        // fonte única do modelo, a mesma que decide o botão e o selector.
        $this->assertStringContainsString('porCreditar(', $fonte,
            'o saldo por anular sai do modelo, não de uma soma escrita aqui à parte');
        $this->assertStringContainsString('E43', $fonte, 'a mensagem diz porquê, com o código da AGT');
    }

    /**
     * QUANTO FALTA ANULAR, CONTADO PELO SALDO.
     *
     * @test
     */
    public function o_saldo_por_anular_desconta_as_notas_ja_emitidas(): void
    {
        $factura = $this->factura(1000);

        $this->assertSame(1000.0, $factura->porCreditar());
        $this->assertFalse($factura->jaTotalmenteCreditada());

        // Uma nota parcial deixa a factura ainda creditável.
        $this->nota($factura, 400);

        $this->assertSame(600.0, $factura->fresh()->porCreditar());
        $this->assertFalse($factura->fresh()->jaTotalmenteCreditada());

        // A segunda fecha-a.
        $this->nota($factura, 600);

        $this->assertSame(0.0, $factura->fresh()->porCreditar());
        $this->assertTrue($factura->fresh()->jaTotalmenteCreditada());

        // Uma nota cancelada não tira nada: conta-se pelo avesso.
        $cancelada = $this->nota($factura, 100);
        $cancelada->update(['status' => 'cancelled']);

        $this->assertSame(0.0, $factura->fresh()->porCreditar(),
            'uma nota cancelada não pode contar como anulação');
    }

    /**
     * A NOTA QUE SE EDITA NÃO SE CONTA A SI PRÓPRIA.
     *
     * Sem isto, corrigir a nota que anulou a factura inteira era impossível: a
     * factura parecia sem saldo por causa da própria nota que se estava a
     * corrigir.
     *
     * @test
     */
    public function a_nota_em_edicao_nao_conta_contra_si(): void
    {
        $factura = $this->factura(1000);
        $nota = $this->nota($factura, 1000);

        $this->assertTrue($factura->fresh()->jaTotalmenteCreditada());
        $this->assertFalse($factura->fresh()->jaTotalmenteCreditada($nota->id),
            'a corrigir esta nota, a factura volta a ter os 1000 por anular');
    }

    /**
     * O ECRÃ NÃO PRÉ-ENCHE UMA FACTURA SEM NADA POR ANULAR — E DIZ PORQUÊ.
     *
     * Era a queixa: a lista mostrava o botão de creditar numa factura já
     * inteiramente creditada, e só depois de a nota estar toda preenchida é
     * que o travão da gravação a recusava.
     *
     * @test
     */
    public function o_ecra_recusa_uma_factura_ja_totalmente_creditada(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.credit_notes.create');

        $factura = $this->factura(1000);
        $this->nota($factura, 1000);

        Livewire::test(CreditNoteCreate::class, ['invoice' => $factura->id])
            ->assertSet('semSaldoPorAnular', $factura->invoice_number)
            ->assertSet('invoice_id', '')
            ->assertSet('client_id', '')
            ->assertSee('já está totalmente creditada');
    }

    /** E a que ainda tem saldo continua a entrar como sempre. @test */
    public function o_ecra_pre_enche_a_factura_que_ainda_da_para_creditar(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.credit_notes.create');

        $factura = $this->factura(1000);
        $this->nota($factura, 400);

        Livewire::test(CreditNoteCreate::class, ['invoice' => $factura->id])
            ->assertSet('semSaldoPorAnular', '')
            ->assertSet('invoice_id', $factura->id);
    }

    /**
     * O BOTÃO DA LISTA FICA APAGADO, NÃO DESAPARECE.
     *
     * Quem procura um botão que sumiu conclui que o sistema o perdeu. Apagado
     * e com a razão à frente responde à pergunta.
     *
     * @test
     */
    public function a_lista_apaga_o_botao_de_creditar_quando_nao_ha_saldo(): void
    {
        $lista = file_get_contents(resource_path('views/livewire/invoicing/faturas-venda/invoices.blade.php'));

        $this->assertStringContainsString('$invoice->jaTotalmenteCreditada()', $lista,
            'o botão decide-se pelo saldo por anular');
        $this->assertStringContainsString('Já totalmente creditada', $lista,
            'e diz porque é que está apagado');

        // E a lista traz a soma na mesma consulta: 15 linhas não são 15 idas
        // à base só para saber se o botão acende.
        $componente = file_get_contents(app_path('Livewire/Invoicing/Sales/Invoices.php'));
        $this->assertStringContainsString('comCreditado()', $componente);
    }

    /**
     * A FACTURA QUE SE VAI CREDITAR TEM DE ESTAR NA LISTA.
     *
     * O selector nomeava os estados que entram — `pending`, `partially_paid`,
     * `paid` — e deixava de fora as `sent` (o estado normal de uma factura
     * emitida e por pagar) e as `overdue`. Nesta base eram 117 facturas.
     *
     * E o estrago não era só não ver: vindo pelo botão de creditar, o ecrã já
     * tinha o `invoice_id` e os produtos carregados, e a seguir o `<select>`
     * desenhava-se sem a opção — o `wire:model.live` devolvia vazio e a nota
     * saía SEM referência à factura. Sem referência, o travão do excesso nem
     * corria, e a AGT recusa uma nota que não diga o que corrige.
     *
     * @test
     */
    public function a_lista_de_facturas_nao_esconde_as_emitidas(): void
    {
        foreach (['CreditNotes/CreditNoteCreate.php', 'DebitNotes/DebitNoteCreate.php'] as $ecra) {
            $fonte = $this->fonte($ecra);

            $this->assertStringNotContainsString(
                "whereIn('status', ['pending', 'partially_paid', 'paid'])",
                $fonte,
                $ecra . ': nomear os estados que entram esconde as facturas `sent`'
            );

            $this->assertStringContainsString(
                "whereNotIn('status', ['draft', 'cancelled'])",
                $fonte,
                $ecra . ': fica de fora o que ainda não existe e o que já não existe'
            );

            $this->assertStringContainsString(
                "orWhere('id', \$this->invoice_id)",
                $fonte,
                $ecra . ': a factura que veio no endereço entra sempre na lista'
            );
        }
    }

    /**
     * O TRAVÃO É TAMBÉM POR LINHA, E NÃO SÓ PELO TOTAL.
     *
     * O da NC4226S46906N/000002 batia certo no total e creditava 69 almoços de
     * uma linha que só teve 34 — a outra linha vinha a menos e compensava. É
     * esse o E43 que a AGT devolveu.
     *
     * @test
     */
    public function o_travao_conta_as_quantidades_de_cada_linha(): void
    {
        $fonte = $this->fonte('CreditNotes/CreditNoteCreate.php');

        $this->assertStringContainsString('linhasQueExcedemAFactura', $fonte,
            'falta o travão por linha');

        $travao  = strpos($fonte, '$excessos = $this->linhasQueExcedemAFactura');
        $criacao = strpos($fonte, 'CreditNote::create(');

        $this->assertNotFalse($travao, 'o travão por linha tem de ser chamado');
        $this->assertLessThan($criacao, $travao,
            'e antes de a nota nascer, senão já ficou numerada e assinada');

        // O que outras notas já tiraram conta na conta de cada linha.
        $this->assertStringContainsString('$jaCreditado', $fonte,
            'duas notas parciais não podem passar juntas o que nenhuma passava sozinha');
    }

    /** @test */
    public function os_dois_ecras_continuam_a_carregar(): void
    {
        // Um erro de sintaxe num destes deixa a facturação sem notas.
        $this->assertTrue(class_exists(CreditNoteCreate::class));
        $this->assertTrue(class_exists(DebitNoteCreate::class));
    }
}
