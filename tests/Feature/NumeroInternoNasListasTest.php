<?php

namespace Tests\Feature;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Traits\NumeracaoInternaEAgt;
use Tests\TenantTestCase;

/**
 * A série INTERNA primeiro, a da AGT logo abaixo — e as tabelas a caber.
 *
 * O pedido foi de três partes: as listas deviam ficar como a das facturas e sem
 * barra lateral; a lista de notas de crédito estava mal; e a procura devia ser
 * pela série interna, que é a que aparece primeiro.
 *
 * O documento leva gravado o número da série da AGT — NC NC4226S46906N/000002.
 * Ninguém procura um documento por aquilo. A série da casa (SOSNC) vivia na
 * série ligada e não aparecia em lado nenhum.
 */
class NumeroInternoNasListasTest extends TenantTestCase
{
    private const LISTAS = [
        'credit-notes/credit-notes.blade.php'   => '$creditNote',
        'debit-notes/debit-notes.blade.php'     => '$debitNote',
        'receipts/receipts.blade.php'           => '$receipt',
        'proformas-venda/proformas.blade.php'   => '$proforma',
        'faturas-venda/invoices.blade.php'      => '$invoice',
    ];

    private function lista(string $ficheiro): string
    {
        return file_get_contents(resource_path('views/livewire/invoicing/' . $ficheiro));
    }

    /**
     * UMA SÓ IMPLEMENTAÇÃO DA NUMERAÇÃO.
     *
     * Isto nasceu dentro do modelo das facturas. Copiá-lo para os outros quatro
     * documentos daria cinco cópias da mesma fórmula, e a numeração é a última
     * coisa onde se quer que duas cópias divirjam.
     *
     * @test
     */
    public function a_numeracao_vive_num_sitio_so(): void
    {
        foreach ([SalesInvoice::class, CreditNote::class, DebitNote::class, Receipt::class, SalesProforma::class] as $modelo) {
            $this->assertContains(NumeracaoInternaEAgt::class, class_uses_recursive($modelo),
                class_basename($modelo) . ': não usa a numeração partilhada');
        }

        // E o modelo das facturas já não tem a sua cópia inline.
        $fonte = file_get_contents(app_path('Models/Invoicing/SalesInvoice.php'));
        $this->assertStringNotContainsString('public function numeroInterno(): string', $fonte,
            'a cópia dentro do modelo tem de sair — quem manda é o trait');
    }

    /** @test */
    public function o_numero_interno_sai_da_serie_ligada(): void
    {
        $nota = new CreditNote(['credit_note_number' => 'NC NC4226S46906N/000002']);

        // Sem série ligada, fica o que está gravado.
        $this->assertSame('NC NC4226S46906N/000002', $nota->numeroInterno());
        $this->assertNull($nota->numeroAgt());

        // A composição é pura e é a mesma para todos os documentos.
        $this->assertSame(
            'NC SOSNC/000002',
            CreditNote::comporNumeroInterno('NC', 'SOSNC', 'NC NC4226S46906N/000002')
        );
    }

    /** @test */
    public function todas_as_listas_mostram_a_serie_interna_primeiro(): void
    {
        foreach (self::LISTAS as $ficheiro => $variavel) {
            $fonte = $this->lista($ficheiro);

            $this->assertStringContainsString($variavel . '->numeroInterno()', $fonte,
                "{$ficheiro}: a série interna tem de ser a que aparece");
            $this->assertStringContainsString($variavel . '->numeroAgt()', $fonte,
                "{$ficheiro}: a série da AGT vai a seguir");

            $posInterno = strpos($fonte, $variavel . '->numeroInterno()');
            $posAgt = strpos($fonte, $variavel . '->numeroAgt()');
            $this->assertLessThan($posAgt, $posInterno,
                "{$ficheiro}: a interna vem PRIMEIRO, é por ela que se procura");
        }
    }

    /** @test */
    public function a_procura_encontra_pela_serie_interna(): void
    {
        $componentes = [
            'CreditNotes/CreditNotes.php',
            'DebitNotes/DebitNotes.php',
            'Receipts/Receipts.php',
            'Sales/Proformas.php',
            'Sales/Invoices.php',
        ];

        foreach ($componentes as $ficheiro) {
            $fonte = file_get_contents(app_path('Livewire/Invoicing/' . $ficheiro));

            $this->assertStringContainsString("series_code", $fonte,
                "{$ficheiro}: quem escreve SOSNC tem de encontrar o documento");
            $this->assertStringContainsString("agt_series_id", $fonte,
                "{$ficheiro}: e quem vem do portal com o código da AGT também");
        }
    }

    /**
     * A TABELA TEM DE CABER NO ECRÃ.
     *
     * `whitespace-nowrap` no título obriga «FATURA ORIGEM» e «PORTAL AGT» a uma
     * linha só, e isso fixa uma largura mínima que a coluna nunca larga. Nove
     * colunas assim passavam a largura da página e nascia a barra lateral —
     * medido: a coluna do Portal AGT caiu de 120 para 84 pixéis ao deixar o
     * título partir, e a tabela voltou a caber.
     *
     * @test
     */
    public function nenhum_titulo_de_coluna_fixa_a_largura(): void
    {
        $listas = array_merge(array_keys(self::LISTAS), [
            'faturas-compra/invoices.blade.php',
            'proformas-compra/proformas.blade.php',
            'orcamentos-venda/orcamentos.blade.php',
            'advances/advances.blade.php',
        ]);

        foreach ($listas as $ficheiro) {
            $fonte = $this->lista($ficheiro);

            preg_match_all('#<th\b[^>]*>#', $fonte, $m);

            foreach ($m[0] as $th) {
                $this->assertStringNotContainsString('whitespace-nowrap', $th,
                    "{$ficheiro}: um título que não parte fixa a largura e traz de volta a barra lateral");
            }
        }
    }

    /**
     * As células apertadas como as das facturas.
     *
     * @test
     */
    public function as_celulas_tem_a_medida_da_lista_de_facturas(): void
    {
        foreach (array_keys(self::LISTAS) as $ficheiro) {
            $fonte = $this->lista($ficheiro);

            preg_match_all('#<t[hd]\b[^>]*>#', $fonte, $m);

            foreach ($m[0] as $celula) {
                $this->assertStringNotContainsString('px-6 py-4', $celula,
                    "{$ficheiro}: px-6 é o espaçamento largo que empurrava a tabela para fora");
            }
        }
    }
}
