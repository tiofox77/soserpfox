<?php

namespace Tests\Feature;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Services\Invoicing\TiposDeDocumento;
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
 *
 * O selo era um partial Blade partilhado pelas nove listas. Hoje as listas são
 * React e a decisão vem DECIDIDA do servidor: o ecrã recebe o rótulo e a cor,
 * não o estado cru para concluir por si.
 */
class ColunaAgtNasListasTest extends TenantTestCase
{
    /** Os ecrãs em React que desenham as listas de documentos. */
    private const ECRAS = [
        'js/ecras/facturacao/vendas/ListaDeFacturas.tsx',
        'js/ecras/facturacao/ListaDeDocumentos.tsx',
    ];

    /** As listas que comunicam à AGT pela mão da empresa. */
    private const FISCAIS = ['notas-credito', 'notas-debito', 'recibos'];

    /** As que nunca são comunicadas. */
    private const NAO_FISCAIS = ['proformas-venda', 'proformas-compra', 'orcamentos', 'adiantamentos'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function nota(?string $agt, string $estado = 'issued'): CreditNote
    {
        $n = CreditNote::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'credit_note_number' => 'NC ' . strtoupper(substr(uniqid(), -8)),
            'issue_date' => now()->toDateString(),
            'status' => $estado,
            'reason' => 'return',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => 'total',
            'created_by' => $this->user->id,
        ]);

        $n->forceFill(['agt_status' => $agt])->saveQuietly();

        return $n;
    }

    private function linhaDe(string $tipo, int $id): ?array
    {
        return collect(
            $this->getJson("/api/v1/invoicing/react/documentos/{$tipo}")->assertOk()->json('data')
        )->firstWhere('id', $id);
    }

    /** @test */
    public function todas_as_listas_de_documentos_mostram_o_estado_da_agt(): void
    {
        // Cada tipo diz, nas suas opções, o que a coluna vai dizer — é isso
        // que o ecrã usa e é isso que impede um documento novo de entrar sem
        // ninguém decidir a sua natureza fiscal.
        foreach (TiposDeDocumento::todos() as $slug => $def) {
            $this->comPermissoes($def['permissao']);

            $this->getJson("/api/v1/invoicing/react/documentos/{$slug}/opcoes")
                ->assertOk()
                ->assertJsonPath('agt', $def['agt']);

            $this->assertContains($def['agt'], ['propria', 'fornecedor', 'nao-fiscal'],
                "{$slug}: a natureza fiscal tem de ser uma das três");
        }

        // E a coluna existe mesmo nos dois ecrãs.
        foreach (self::ECRAS as $ficheiro) {
            $fonte = file_get_contents(resource_path($ficheiro));

            $this->assertMatchesRegularExpression('#(Portal AGT|>AGT<)#', $fonte,
                "{$ficheiro}: falta o cabeçalho da coluna do estado da AGT");
            $this->assertStringContainsString('.agt', $fonte,
                "{$ficheiro}: falta a célula com o estado da AGT");
        }
    }

    /**
     * O QUE NÃO É FISCAL TEM DE SE DECLARAR.
     *
     * Sem a natureza `nao-fiscal` o selo cai no ramo por omissão e diz
     * «pendente de envio» — um alarme para uma coisa que nunca acontece.
     *
     * @test
     */
    public function as_listas_nao_fiscais_declaram_se_como_tal(): void
    {
        $tipos = TiposDeDocumento::todos();

        foreach (self::NAO_FISCAIS as $slug) {
            $this->assertSame('nao-fiscal', $tipos[$slug]['agt'],
                "{$slug}: uma proforma, um orçamento ou um adiantamento não se comunicam à AGT");
        }

        foreach (self::FISCAIS as $slug) {
            $this->assertSame('propria', $tipos[$slug]['agt'],
                "{$slug}: este documento É comunicado à AGT pela empresa");
        }

        // A factura de compra é fiscal, mas de quem a emitiu.
        $this->assertSame('fornecedor', $tipos['facturas-compra']['agt']);
    }

    /** @test */
    public function o_selo_diz_o_que_a_agt_respondeu(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view');

        $casos = [
            'validated' => ['Emitida no Portal AGT', 'bom'],
            'submitted' => ['Enviada — aguarda AGT', 'primaria'],
            'rejected'  => ['Falhou — reenviar à AGT', 'perigo'],
            ''          => ['Pendente de envio à AGT', 'aviso'],
        ];

        foreach ($casos as $estado => [$rotulo, $cor]) {
            $nota = $this->nota($estado);

            $linha = $this->linhaDe('notas-credito', $nota->id);

            $this->assertSame($rotulo, $linha['agt']['rotulo'], "o estado '{$estado}' devia dizer «{$rotulo}»");
            $this->assertSame($cor, $linha['agt']['cor'], 'a cor acompanha o rótulo — nunca só a cor');
        }
    }

    /**
     * A LISTA DAS FACTURAS DE VENDA DIZ O MESMO QUE AS OUTRAS.
     *
     * Tinha uma regra sua, que lia o `jws_signature` — a assinatura LOCAL,
     * posta ao emitir, antes de qualquer envio. Uma factura que a AGT recusou,
     * ou que nunca lá chegou, aparecia a verde a dizer «Emitida no Portal AGT».
     * Por isso todas as facturas daqui levam assinatura: o selo tem de a
     * ignorar e ler só o que a AGT respondeu.
     *
     * @test
     */
    public function a_lista_das_facturas_de_venda_le_o_que_a_agt_respondeu_e_nao_a_assinatura(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $casos = [
            'validada' => ['validated', 'sent', 'Emitida no Portal AGT', 'bom', true],
            'enviada' => ['submitted', 'sent', 'Enviada — aguarda AGT', 'primaria', false],
            'rejeitada' => ['rejected', 'sent', 'Falhou — reenviar à AGT', 'perigo', false],
            'falhou' => ['failed', 'sent', 'Falhou — reenviar à AGT', 'perigo', false],
            'por enviar' => [null, 'sent', 'Pendente de envio à AGT', 'aviso', false],
            'rascunho' => [null, 'draft', 'Ainda não emitida', 'neutra', false],
        ];

        $ids = [];

        foreach ($casos as $nome => [$agt, $estado]) {
            $f = SalesInvoice::create([
                'tenant_id' => $this->tenant->id,
                'client_id' => $this->clienteEmpresa()->id,
                'invoice_number' => 'FT TESTE/' . strtoupper(substr(uniqid(), -8)),
                'invoice_date' => now()->toDateString(),
                'status' => $estado,
                'total' => 1000,
                'created_by' => $this->user->id,
            ]);

            // Sem eventos: o que se põe aqui é o que a AGT teria respondido.
            $f->forceFill(['agt_status' => $agt, 'jws_signature' => 'assinatura.local.do.documento'])->saveQuietly();

            $ids[$nome] = $f->id;
        }

        $linhas = collect(
            $this->getJson('/api/v1/invoicing/react/sales-invoices?por_pagina=100')->assertOk()->json('data')
        );

        foreach ($casos as $nome => [, , $rotulo, $cor, $comunicada]) {
            $selo = $linhas->firstWhere('id', $ids[$nome])['agt'] ?? null;

            $this->assertNotNull($selo, "a factura {$nome} tem de vir na lista");
            $this->assertSame($rotulo, $selo['rotulo'], "a factura {$nome} devia dizer «{$rotulo}»");
            $this->assertSame($cor, $selo['cor'], "{$nome}: a cor acompanha o rótulo");
            $this->assertSame($comunicada, $selo['comunicada'], "{$nome}: só a validada conta como comunicada");
        }

        // E o ecrã desenha a cor que o servidor decidiu, não uma sua.
        $fonte = file_get_contents(resource_path('js/ecras/facturacao/vendas/ListaDeFacturas.tsx'));
        $this->assertStringContainsString('f.agt.cor', $fonte);
    }

    /** Um rascunho ainda não foi emitido: não há envio nenhum por fazer. @test */
    public function um_rascunho_nao_diz_que_esta_pendente_de_envio(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view');

        $linha = $this->linhaDe('notas-credito', $this->nota(null, 'draft')->id);

        $this->assertSame('Ainda não emitida', $linha['agt']['rotulo']);
    }

    /** @test */
    public function o_selo_do_nao_fiscal_nao_inventa_um_envio(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $proforma = SalesProforma::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'proforma_number' => 'PRF/' . random_int(1000, 9999),
            'proforma_date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 100,
            'created_by' => $this->user->id,
        ]);

        $selo = $this->linhaDe('proformas-venda', $proforma->id)['agt'];

        $this->assertSame('nao-fiscal', $selo['natureza']);
        $this->assertSame('Não comunicável à AGT', $selo['rotulo']);
        $this->assertStringNotContainsString('Pendente', $selo['rotulo']);
    }

    /** @test */
    public function a_factura_de_compra_continua_a_dizer_de_quem_e_a_responsabilidade(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view');

        $fornecedor = \App\Models\Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor ' . uniqid(),
            'is_active' => true,
        ]);

        $compra = PurchaseInvoice::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $fornecedor->id,
            'invoice_number' => 'FC/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'pending',
            'total' => 5000,
            'paid_amount' => 0,
            'created_by' => $this->user->id,
        ]);

        // Quem comunica uma factura de compra é o fornecedor que a emitiu.
        $this->assertSame(
            'Responsabilidade do fornecedor',
            $this->linhaDe('facturas-compra', $compra->id)['agt']['rotulo']
        );
    }

    /**
     * A TABELA TEM DE FECHAR.
     *
     * Uma coluna a mais no cabeçalho sem a célula correspondente no corpo
     * desalinha a tabela toda.
     *
     * @test
     */
    public function o_cabecalho_e_o_corpo_tem_a_mesma_largura(): void
    {
        /*
         * CONTAR ETIQUETAS DEIXOU DE PROVAR ALGUMA COISA.
         *
         * Enquanto o cabeçalho era escrito à mão, um `<th>` a mais do que os
         * `<td>` era uma coluna esquecida — e esta contagem apanhava-a. Hoje o
         * cabeçalho das listas é DESENHADO A PARTIR DE UMA LISTA de colunas
         * (`{colunas.map(…) => <th>}`): há um `<th>` no ficheiro e oito
         * `<td>`, e está tudo certo.
         *
         * A propriedade continua a valer — o que mudou foi onde se mede. Ela
         * prova-se agora no BROWSER, sobre a tabela desenhada, em
         * `tests/browser/react.todas-as-paginas.spec.js`: para cada página com
         * tabela, o número de células da primeira linha tem de ser o número de
         * colunas do cabeçalho. É mais forte do que contar texto, porque
         * apanha também a coluna que existe no ficheiro e não chega ao ecrã.
         *
         * Aqui fica o que ainda se pode afirmar do ficheiro: as duas metades
         * existem, e nenhuma lista ficou sem cabeçalho ou sem corpo.
         */
        foreach (self::ECRAS as $ficheiro) {
            $fonte = file_get_contents(resource_path($ficheiro));

            $this->assertMatchesRegularExpression('#<th[\s>]#', $fonte, "{$ficheiro}: tabela sem cabeçalho");
            $this->assertMatchesRegularExpression('#<td[\s>]#', $fonte, "{$ficheiro}: tabela sem corpo");
        }
    }
}
