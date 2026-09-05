<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * Em todas as listas de documentos vê-se o que a AGT disse.
 *
 * PORQUE EXISTE. O pedido foi directo: «nas tabelas deve aparecer se foi
 * enviado ou deu erro na AGT». Antes disto só as facturas de venda e de compra
 * mostravam esse estado; quem emitia uma nota de crédito ficava sem saber se
 * ela tinha sido aceite, e a resposta da AGT só aparecia dias depois, ou nunca.
 *
 * O selo distingue quatro coisas, e a quarta é a que evita o alarme falso:
 * aceite, à espera, recusada — e NÃO COMUNICÁVEL, para a proforma, o orçamento
 * e o adiantamento, que não são documentos fiscais e nunca são enviados. Sem
 * essa distinção, a coluna dizia «pendente de envio» numa proforma e mandava
 * alguém procurar um envio que nunca vai existir.
 */
class ColunaAgtNasListasTest extends TenantTestCase
{
    /** As listas que comunicam à AGT: factura, notas e recibo. */
    private const FISCAIS = [
        'faturas-venda/invoices.blade.php',
        'faturas-compra/invoices.blade.php',
        'credit-notes/credit-notes.blade.php',
        'debit-notes/debit-notes.blade.php',
        'receipts/receipts.blade.php',
    ];

    /** As que nunca são comunicadas. */
    private const NAO_FISCAIS = [
        'proformas-venda/proformas.blade.php',
        'proformas-compra/proformas.blade.php',
        'orcamentos-venda/orcamentos.blade.php',
        'advances/advances.blade.php',
    ];

    private function lista(string $ficheiro): string
    {
        return file_get_contents(resource_path('views/livewire/invoicing/' . $ficheiro));
    }

    /** @test */
    public function todas_as_listas_de_documentos_mostram_o_estado_da_agt(): void
    {
        foreach (array_merge(self::FISCAIS, self::NAO_FISCAIS) as $ficheiro) {
            $this->assertStringContainsString('agt-document-status', $this->lista($ficheiro),
                "{$ficheiro}: falta a coluna do estado da AGT");

            $this->assertStringContainsString("__('Portal AGT')", $this->lista($ficheiro),
                "{$ficheiro}: falta o cabeçalho da coluna");
        }
    }

    /**
     * O QUE NÃO É FISCAL TEM DE SE DECLARAR.
     *
     * Sem `natureza => 'nao-fiscal'` o selo cai no ramo por omissão e diz
     * «pendente de envio» — um alarme para uma coisa que nunca acontece.
     *
     * @test
     */
    public function as_listas_nao_fiscais_declaram_se_como_tal(): void
    {
        foreach (self::NAO_FISCAIS as $ficheiro) {
            $this->assertStringContainsString("'natureza' => 'nao-fiscal'", $this->lista($ficheiro),
                "{$ficheiro}: uma proforma ou um orçamento não se comunicam à AGT");
        }

        foreach (self::FISCAIS as $ficheiro) {
            $this->assertStringNotContainsString("'natureza' => 'nao-fiscal'", $this->lista($ficheiro),
                "{$ficheiro}: este documento É comunicado à AGT");
        }
    }

    /**
     * A TABELA TEM DE FECHAR.
     *
     * Uma coluna a mais no cabeçalho sem a célula correspondente no corpo
     * desalinha a tabela toda, e o colspan da linha «sem resultados» deixa de
     * atravessar a largura.
     *
     * @test
     */
    public function o_cabecalho_o_corpo_e_a_linha_de_vazio_tem_a_mesma_largura(): void
    {
        foreach (array_merge(self::FISCAIS, self::NAO_FISCAIS) as $ficheiro) {
            $fonte = $this->lista($ficheiro);

            $cabecalho = substr($fonte, strpos($fonte, '<thead'), strpos($fonte, '</thead>') - strpos($fonte, '<thead'));
            $colunas = preg_match_all('#<th[\s>]#', $cabecalho);

            $corpo = substr($fonte, strpos($fonte, '@forelse'));
            $celulas = preg_match_all('#<td[\s>]#', substr($corpo, 0, strpos($corpo, '</tr>')));

            $this->assertSame($colunas, $celulas,
                "{$ficheiro}: {$colunas} colunas no cabeçalho e {$celulas} células na linha");

            if (preg_match('#colspan="(\d+)"#', $fonte, $m)) {
                $this->assertSame($colunas, (int) $m[1],
                    "{$ficheiro}: o colspan da linha de vazio não atravessa a tabela");
            }
        }
    }

    /** @test */
    public function o_selo_diz_o_que_a_agt_respondeu(): void
    {
        $casos = [
            'validated' => 'Emitida no Portal AGT',
            'submitted' => 'Enviada — aguarda AGT',
            'rejected'  => 'Falhou — reenviar à AGT',
            ''          => 'Pendente de envio à AGT',
        ];

        foreach ($casos as $estado => $esperado) {
            $documento = (object) ['agt_status' => $estado, 'status' => 'issued'];

            $html = view('livewire.invoicing.partials.agt-document-status', ['document' => $documento])->render();

            $this->assertStringContainsString($esperado, $html, "o estado '{$estado}' devia dizer «{$esperado}»");
        }
    }

    /** @test */
    public function o_selo_do_nao_fiscal_nao_inventa_um_envio(): void
    {
        $html = view('livewire.invoicing.partials.agt-document-status', [
            'document' => (object) ['status' => 'issued'],
            'natureza' => 'nao-fiscal',
        ])->render();

        $this->assertStringContainsString('Não comunicável à AGT', $html);
        $this->assertStringNotContainsString('Pendente de envio', $html);
    }

    /** @test */
    public function a_factura_de_compra_continua_a_dizer_de_quem_e_a_responsabilidade(): void
    {
        $html = view('livewire.invoicing.partials.agt-document-status', [
            'document' => (object) ['status' => 'issued'],
            'direction' => 'purchase',
        ])->render();

        // Quem comunica uma factura de compra é o fornecedor que a emitiu.
        $this->assertStringContainsString('Responsabilidade do fornecedor', $html);
    }
}
