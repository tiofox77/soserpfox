<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\SoftwareSetting;
use App\Models\Tenant;
use App\Services\AGT\AGTClient;
use App\Services\AGT\AGTProducerStore;
use Illuminate\Support\Facades\Cache;
use Tests\TenantTestCase;

/**
 * Uma empresa nova nasce em PRODUÇÃO.
 *
 * O padrão era homologação, herdado de quando o sistema estava a ser
 * certificado. Mas quem se regista é uma empresa a sério: nascer em
 * homologação mandava os primeiros documentos para o ambiente de testes da
 * AGT — não contam para nada, e ninguém dá por isso até alguém pedir a
 * factura.
 *
 * O que estes ensaios prendem, e por esta ordem de importância:
 *   1. quem JÁ EXISTE não é arrastado para produção;
 *   2. uma empresa nova nasce em produção e assina os valores de produção;
 *   3. nascer em produção não faz sair nada por engano.
 */
class AgtEmpresaNovaEmProducaoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function empresaNova(): Tenant
    {
        return Tenant::create([
            'name' => 'Empresa Acabada de Nascer',
            'slug' => 'nova-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'nova'.uniqid().'@exemplo.ao',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function uma_empresa_nova_nasce_em_producao(): void
    {
        $nova = $this->empresaNova();

        $d = InvoicingSettings::forTenant($nova->id);

        $this->assertSame('production', $d->agt_environment,
            'quem se regista vai facturar a sério — não para o ambiente de testes');
    }

    /**
     * QUEM JÁ EXISTE NÃO É ARRASTADO.
     *
     * Mudar o ambiente de uma empresa a meio é trocar-lhe as chaves e o
     * certificado por baixo dos pés. Quem está em homologação de propósito
     * continua lá.
     *
     * @test
     */
    public function quem_ja_estava_em_homologacao_la_fica(): void
    {
        $d = InvoicingSettings::forTenant($this->tenant->id);
        $d->update(['agt_environment' => 'sandbox']);
        InvoicingSettings::esquecerMemoria($this->tenant->id);

        // Criar outra empresa (que nasce em produção) não pode mexer nesta.
        $this->empresaNova();
        InvoicingSettings::esquecerMemoria($this->tenant->id);

        $this->assertSame('sandbox', InvoicingSettings::forTenant($this->tenant->id)->agt_environment);
    }

    /** O cliente da AGT de uma empresa nova aponta ao endereço de produção. */
    public function test_a_empresa_nova_fala_com_a_agt_de_producao(): void
    {
        $nova = $this->empresaNova();
        $cliente = new AGTClient($nova->id);

        $this->assertSame('production', $cliente->getEnvironment());
        $this->assertSame(AGTClient::PRODUCTION_URL, $cliente->getBaseUrl());
    }

    /**
     * E assina os valores que a produção certificou — não os de homologação.
     *
     * @test
     */
    public function a_empresa_nova_assina_os_valores_de_producao(): void
    {
        SoftwareSetting::set('invoicing', 'saft_version_production', '1.0.0', 'string');
        SoftwareSetting::set('invoicing', 'saft_software_cert_production', 'FE/324/AGT/2026', 'string');
        SoftwareSetting::set('invoicing', 'saft_version_sandbox', '1.0', 'string');
        SoftwareSetting::set('invoicing', 'saft_software_cert_sandbox', 'FE/351/AGT/2026', 'string');
        Cache::flush();

        $nova = $this->empresaNova();
        $ambiente = AGTProducerStore::ambienteDaEmpresa($nova->id);

        $this->assertSame('production', $ambiente);
        $this->assertSame('1.0.0', AGTProducerStore::productVersion($ambiente));
        $this->assertSame('FE/324/AGT/2026', AGTProducerStore::numeroCertificacao($ambiente));
    }

    /**
     * NASCER EM PRODUÇÃO NÃO ENVIA NADA POR ENGANO.
     *
     * Sem as chaves do contribuinte daquele ambiente não há assinatura, e sem
     * a submissão automática ligada não sai documento nenhum por iniciativa
     * própria. O risco de apontar à AGT verdadeira é ter as portas abertas —
     * e elas nascem fechadas.
     *
     * @test
     */
    public function nascer_em_producao_nao_abre_nenhuma_porta(): void
    {
        $nova = $this->empresaNova();
        $d = InvoicingSettings::forTenant($nova->id);

        $this->assertEmpty($d->agt_auto_submit, 'a submissão automática nasce desligada');

        $cliente = new AGTClient($nova->id);

        $this->assertFalse($cliente->hasContributorKey(),
            'sem chaves do contribuinte não há como assinar — e o ecrã recusa-se a sincronizar');
    }
}
