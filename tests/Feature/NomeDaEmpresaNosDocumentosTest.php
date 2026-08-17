<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Que nome da empresa sai impresso nos documentos.
 *
 * Uma empresa tem dois — o comercial, da tabuleta, e a designação social, do
 * registo. Antes o sistema decidia sozinho e decidia mal: a factura em PDF
 * imprimia um e o talão do POS imprimia o outro.
 */
class NomeDaEmpresaNosDocumentosTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(string $comercial, ?string $social): Tenant
    {
        return Tenant::create([
            'name'         => $comercial,
            'company_name' => $social,
            'email'        => uniqid() . '@teste.local',
            'nif'          => '5' . random_int(10000000, 99999999),
        ]);
    }

    private function escolha(Tenant $t, string $qual): void
    {
        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $t->id],
            ['nome_nos_documentos' => $qual]
        );

        InvoicingSettings::esquecerMemoria($t->id);
    }

    public function test_por_omissao_sai_a_designacao_social(): void
    {
        $t = $this->empresa('Farmácia Vital Saúde', 'Kienga, Limitada');

        $this->assertSame('Kienga, Limitada', $t->nomeParaDocumentos());
    }

    public function test_escolhendo_comercial_sai_o_nome_da_tabuleta(): void
    {
        $t = $this->empresa('Farmácia Vital Saúde', 'Kienga, Limitada');
        $this->escolha($t, InvoicingSettings::NOME_COMERCIAL);

        $this->assertSame('Farmácia Vital Saúde', $t->nomeParaDocumentos());
    }

    public function test_escolhendo_social_sai_o_nome_do_registo(): void
    {
        $t = $this->empresa('Farmácia Vital Saúde', 'Kienga, Limitada');
        $this->escolha($t, InvoicingSettings::NOME_SOCIAL);

        $this->assertSame('Kienga, Limitada', $t->nomeParaDocumentos());
    }

    public function test_sem_designacao_social_sai_o_comercial(): void
    {
        // Mais vale o outro nome do que um documento com o cabeçalho em branco.
        $t = $this->empresa('Farmácia Vital Saúde', null);
        $this->escolha($t, InvoicingSettings::NOME_SOCIAL);

        $this->assertSame('Farmácia Vital Saúde', $t->nomeParaDocumentos());
    }

    public function test_sem_nome_comercial_sai_a_social(): void
    {
        $t = $this->empresa('', 'Kienga, Limitada');
        $this->escolha($t, InvoicingSettings::NOME_COMERCIAL);

        $this->assertSame('Kienga, Limitada', $t->nomeParaDocumentos());
    }

    public function test_a_escolha_de_uma_empresa_nao_afecta_a_outra(): void
    {
        $uma = $this->empresa('Comercial A', 'Social A');
        $outra = $this->empresa('Comercial B', 'Social B');

        $this->escolha($uma, InvoicingSettings::NOME_COMERCIAL);
        $this->escolha($outra, InvoicingSettings::NOME_SOCIAL);

        $this->assertSame('Comercial A', $uma->nomeParaDocumentos());
        $this->assertSame('Social B', $outra->nomeParaDocumentos());
    }
}
