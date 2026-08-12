<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\Tenants\SinaisDeVida;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * A empresa está viva ou é só uma linha na base?
 *
 * A lista de empresas mostrava nome, plano e número de utilizadores — e com
 * isso não se distingue um cliente que factura todos os dias de um que se
 * registou, abriu duas páginas e nunca mais voltou. As duas linhas eram
 * iguais, e as decisões que dependem disso — a quem telefonar, quem está a
 * fugir, que plano vale a pena manter — tomavam-se às cegas.
 */
class SinaisDeVidaTest extends TenantTestCase
{
    private function outraEmpresa(): Tenant
    {
        return Tenant::create([
            'name'  => 'Outra ' . uniqid(),
            'slug'  => 'outra-' . uniqid(),
            'nif'   => (string) random_int(500000000, 599999999),
            'email' => 'o' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);
    }

    /** Uma factura mínima que a base aceite. */
    private function factura(\DateTimeInterface $quando): void
    {
        DB::table('invoicing_sales_invoices')->insert([
            'tenant_id'      => $this->tenant->id,
            'invoice_number' => 'FT-' . uniqid(),
            'client_id'      => $this->cliente->id,
            'invoice_date'   => $quando,
            'created_by'     => $this->user->id,
            'created_at'     => $quando,
            'updated_at'     => $quando,
        ]);
    }

    private function artigo(Tenant $empresa, \DateTimeInterface $quando): void
    {
        DB::table('invoicing_products')->insert([
            'tenant_id'  => $empresa->id,
            'name'       => 'Artigo',
            'code'       => 'COD-' . uniqid(),
            'sku'        => 'SKU-' . uniqid(),
            'price'      => 100,
            'type'       => 'produto',
            'created_at' => $quando,
            'updated_at' => $quando,
        ]);
    }

    public function test_uma_empresa_sem_nada_e_dada_como_nunca_usou(): void
    {
        $vazia = $this->outraEmpresa();

        $sinais = SinaisDeVida::para([$vazia->id]);

        $this->assertSame('vazia', $sinais[$vazia->id]->estado['chave']);
        $this->assertSame(0, $sinais[$vazia->id]->artigos);
    }

    /** Quem factura está vivo, ponto. */
    public function test_facturar_e_o_sinal_mais_forte(): void
    {
        $this->factura(now());

        $sinais = SinaisDeVida::para([$this->tenant->id]);

        $this->assertSame('activa', $sinais[$this->tenant->id]->estado['chave']);
        $this->assertSame(1, $sinais[$this->tenant->id]->facturas_30d);
    }

    /** Uma factura de há um ano não prova que a empresa esteja viva hoje. */
    public function test_uma_factura_antiga_nao_conta_para_os_30_dias(): void
    {
        $this->factura(now()->subYear());

        $sinais = SinaisDeVida::para([$this->tenant->id]);

        $this->assertSame(0, $sinais[$this->tenant->id]->facturas_30d);
    }

    /** Ter catálogo e ninguém a entrar há um mês é uma empresa a adormecer. */
    public function test_catalogo_sem_actividade_recente_e_adormecida(): void
    {
        $empresa = $this->outraEmpresa();

        $this->artigo($empresa, now()->subMonths(6));

        $sinais = SinaisDeVida::para([$empresa->id]);

        $this->assertSame('adormecida', $sinais[$empresa->id]->estado['chave']);
        $this->assertSame(1, $sinais[$empresa->id]->artigos);
    }

    /** Os utilizadores contam-se pelo pivot, não pela empresa de origem. */
    public function test_os_utilizadores_da_empresa_sao_contados(): void
    {
        $sinais = SinaisDeVida::para([$this->tenant->id]);

        $this->assertGreaterThanOrEqual(1, $sinais[$this->tenant->id]->utilizadores);
    }

    /**
     * A última entrada é lida mesmo.
     *
     * Estava a ser lida com pluck(DB::raw('MAX(...)')), que procura uma
     * propriedade com o nome da expressão inteira e devolve null para toda a
     * gente: o ecrã dizia "nunca entrou" de todas as empresas, incluindo as
     * que estavam a facturar naquele minuto.
     */
    public function test_a_ultima_entrada_e_lida(): void
    {
        $this->user->update(['last_login_at' => now()->subDays(2)]);

        $sinais = SinaisDeVida::para([$this->tenant->id]);

        $this->assertNotNull($sinais[$this->tenant->id]->ultima_entrada);
        $this->assertSame(1, $sinais[$this->tenant->id]->entraram_30d);
    }

    /**
     * O custo não pode crescer com o número de empresas.
     *
     * Seis consultas fixas para a página inteira. O caminho fácil — perguntar
     * por empresa — torna a lista inutilizável ao fim de trinta clientes.
     */
    public function test_o_numero_de_consultas_nao_cresce_com_as_empresas(): void
    {
        $empresas = collect([$this->tenant->id]);
        for ($i = 0; $i < 5; $i++) {
            $empresas->push($this->outraEmpresa()->id);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        SinaisDeVida::para($empresas);

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            12,
            $consultas,
            "Seis empresas deram {$consultas} consultas — o custo está a crescer com a lista."
        );
    }

    public function test_uma_lista_vazia_nao_faz_consultas(): void
    {
        $this->assertTrue(SinaisDeVida::para([])->isEmpty());
    }
}
