<?php

namespace Tests\Feature;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\SalesInvoice;
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
 * AGT disse que não servia. Por isso o travão está ANTES de o número ser
 * atribuído — e vive no `EmissorDeNotas`, que é por onde a API em React emite.
 * O carrinho que somava desapareceu com o Livewire; o travão ficou.
 */
class NotaDeCreditoNaoExcedeTest extends TenantTestCase
{
    /** O travão mudou-se dos componentes para o serviço; é lá que se lê. */
    private function servico(): string
    {
        return file_get_contents(app_path('Services/Invoicing/EmissorDeNotas.php'));
    }

    private function factura(float $total, string $estado = 'sent'): SalesInvoice
    {
        return SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT TESTE/' . random_int(1000, 9999),
            'invoice_date'   => now()->toDateString(),
            'status'         => $estado,
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
     * O TRAVÃO ESTÁ ANTES DO NÚMERO FISCAL.
     *
     * De nada serve recusar depois: o documento já ficou numerado, assinado e
     * com a factura marcada como anulada.
     *
     * @test
     */
    public function o_excesso_e_travado_antes_de_o_documento_nascer(): void
    {
        $fonte = $this->servico();

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
     * A FACTURA QUE SE VAI CREDITAR TEM DE ESTAR NA LISTA — E A QUE JÁ NÃO
     * TEM NADA POR ANULAR NÃO.
     *
     * O selector nomeava os estados que entram — `pending`, `partially_paid`,
     * `paid` — e deixava de fora as `sent` (o estado normal de uma factura
     * emitida e por pagar) e as `overdue`. Nesta base eram 117 facturas.
     *
     * E o estrago não era só não ver: vindo pelo botão de creditar, o ecrã já
     * tinha a factura carregada, e a seguir o selector desenhava-se sem a
     * opção — a nota saía SEM referência à factura. Sem referência, o travão
     * do excesso nem corria, e a AGT recusa uma nota que não diga o que
     * corrige.
     *
     * Do outro lado, a queixa simétrica: a lista oferecia para creditar uma
     * factura já inteiramente creditada, e só no fim é que a gravação a
     * recusava. Hoje decide-se pelo SALDO, e decide-se no servidor.
     *
     * @test
     */
    public function a_lista_de_facturas_para_a_nota_conta_pelo_saldo_e_nao_pelo_nome_do_estado(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.credit-notes.view');

        $emitida = $this->factura(1000, 'sent');
        $vencida = $this->factura(1000, 'overdue');
        $rascunho = $this->factura(1000, 'draft');
        $anulada = $this->factura(1000, 'cancelled');

        $semSaldo = $this->factura(1000, 'sent');
        $this->nota($semSaldo, 1000);

        $ids = collect(
            $this->getJson('/api/v1/invoicing/react/notas/credito/facturas')->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($emitida->id), 'uma factura emitida e por pagar credita-se');
        $this->assertTrue($ids->contains($vencida->id), 'e uma vencida também');

        $this->assertFalse($ids->contains($rascunho->id), 'um rascunho ainda não foi emitido');
        $this->assertFalse($ids->contains($anulada->id), 'e uma anulada já não existe');
        $this->assertFalse($ids->contains($semSaldo->id),
            'uma factura já inteiramente creditada não se oferece — a AGT recusaria com E43');

        // E o que falta anular viaja com cada linha: é o que o ecrã propõe.
        $linha = collect($this->getJson('/api/v1/invoicing/react/notas/credito/facturas')->json('data'))
            ->firstWhere('id', $emitida->id);

        $this->assertEqualsWithDelta(1000, $linha['por_creditar'], 0.01);
    }

    /**
     * O BOTÃO DA LISTA FICA APAGADO, NÃO DESAPARECE.
     *
     * Quem procura um botão que sumiu conclui que o sistema o perdeu. Apagado
     * e com a razão à frente responde à pergunta.
     *
     * A lista é hoje o ecrã em React, e a decisão vem decidida do servidor: o
     * `pode_creditar` de cada linha. O ecrã só apaga um botão — e um botão
     * apagado nunca foi segurança: quem forçar o endereço bate no mesmo travão.
     *
     * @test
     */
    public function a_lista_apaga_o_botao_de_creditar_quando_nao_ha_saldo(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/vendas/ListaDeFacturas.tsx'));

        $this->assertStringContainsString('factura.pode_creditar', $ecra,
            'o botão decide-se pelo saldo por anular, resolvido no servidor');
        $this->assertStringContainsString('Já totalmente creditada', $ecra,
            'e diz porque é que está apagado');

        // E a lista traz a soma na mesma consulta: 15 linhas não são 15 idas
        // à base só para saber se o botão acende.
        $api = file_get_contents(app_path('Http/Controllers/Api/Invoicing/SalesInvoiceApiController.php'));
        $this->assertStringContainsString('comCreditado()', $api);
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
        $fonte = $this->servico();

        $this->assertStringContainsString('function excessos(', $fonte,
            'falta o travão por linha');

        $travao  = strpos($fonte, '$excessos = $this->excessos(');
        $criacao = strpos($fonte, 'CreditNote::create(');

        $this->assertNotFalse($travao, 'o travão por linha tem de ser chamado');
        $this->assertLessThan($criacao, $travao,
            'e antes de a nota nascer, senão já ficou numerada e assinada');

        // O que outras notas já tiraram conta na conta de cada linha.
        $this->assertStringContainsString('$jaCreditado', $fonte,
            'duas notas parciais não podem passar juntas o que nenhuma passava sozinha');
    }
}
