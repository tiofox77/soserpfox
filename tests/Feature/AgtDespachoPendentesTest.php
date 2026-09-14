<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\DespachoPendentes;
use Illuminate\Support\Facades\Cache;
use Tests\TenantTestCase;

/**
 * As submissões andam com o tráfego, sem fila.
 *
 * A fila do Laravel exige um worker. Neste alojamento não há, e o resultado
 * media-se: 165 tarefas paradas e 160 documentos presos em "Enviada" sem que
 * ninguém soubesse o desfecho — nem que a AGT os tinha recusado.
 */
class AgtDespachoPendentesTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * No ambiente ACTIVO da empresa. O despacho passou a ignorar as submissões
     * do outro ambiente (ver AgtAmbienteDasSubmissoesTest) — e sem o campo a
     * coluna nasce em homologação, enquanto uma empresa nova emite em
     * produção: o ensaio via «sem trabalho» por esse motivo, não pelo seu.
     */
    private function submissao(string $estado, ?string $referencia = null, int $tentativas = 0): AGTSubmission
    {
        return AGTSubmission::create([
            'tenant_id'          => $this->tenant->id,
            'agt_environment'    => InvoicingSettings::forTenant($this->tenant->id)->agt_environment ?: 'sandbox',
            'document_type'      => \App\Models\Invoicing\SalesInvoice::class,
            'document_id'        => 1,
            'document_type_code' => 'FT',
            'document_number'    => 'FT S/' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status'             => $estado,
            'agt_reference'      => $referencia,
            'retry_count'        => $tentativas,
        ]);
    }

    public function test_ha_trabalho_quando_falta_enviar(): void
    {
        $this->assertFalse(DespachoPendentes::temTrabalho($this->tenant->id));

        $this->submissao(AGTSubmission::STATUS_PENDING);

        $this->assertTrue(DespachoPendentes::temTrabalho($this->tenant->id));
    }

    public function test_ha_trabalho_quando_falta_saber_o_desfecho(): void
    {
        // O caso que prendia 160 documentos: enviados, com requestID, e o
        // veredicto por perguntar.
        $this->submissao(AGTSubmission::STATUS_SUBMITTED, '202600002634339');

        $this->assertTrue(DespachoPendentes::temTrabalho($this->tenant->id));
    }

    public function test_um_documento_que_a_agt_nunca_aceita_deixa_de_ser_tentado(): void
    {
        // Sem tecto, um defeito de fundo era retentado a cada visita, para
        // sempre, e a lista nunca esvaziava.
        $this->submissao(AGTSubmission::STATUS_PENDING, null, DespachoPendentes::TENTATIVAS_MAX);

        $this->assertFalse(DespachoPendentes::temTrabalho($this->tenant->id));
    }

    public function test_o_que_ja_terminou_nao_da_trabalho(): void
    {
        $this->submissao(AGTSubmission::STATUS_VALIDATED, 'x');
        $this->submissao(AGTSubmission::STATUS_REJECTED);

        $this->assertFalse(DespachoPendentes::temTrabalho($this->tenant->id));
    }

    public function test_o_trabalho_de_uma_empresa_nao_e_visto_por_outra(): void
    {
        // Tratar submissões de outra empresa no pedido de alguém era trabalho
        // de um contribuinte a correr no contexto de outro.
        $this->submissao(AGTSubmission::STATUS_PENDING);

        $outra = \App\Models\Tenant::create([
            'name'      => 'Outra Empresa',
            'slug'      => 'outra-' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'outra' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        $this->assertFalse(DespachoPendentes::temTrabalho($outra->id));
    }

    public function test_sem_envio_automatico_nao_envia_mas_continua_a_consultar(): void
    {
        // Consultar é só leitura, e é o que tira os documentos de "Enviada".
        InvoicingSettings::forTenant($this->tenant->id)->update(['agt_auto_submit' => false]);

        $this->submissao(AGTSubmission::STATUS_PENDING);

        $r = (new DespachoPendentes())->correr($this->tenant->id);

        $this->assertSame(0, $r['enviados'], 'com o envio desligado não se envia por iniciativa própria');
    }

    public function test_sem_empresa_nao_faz_nada(): void
    {
        $r = (new DespachoPendentes())->correr(0);

        $this->assertSame(['enviados' => 0, 'consultados' => 0], $r);
    }

    public function test_a_tranca_impede_dois_despachos_seguidos(): void
    {
        // Muitos utilizadores a navegar não podem multiplicar as chamadas à
        // AGT. Cache::add é atómico: o primeiro escreve, os outros não.
        $chave = "agt:despacho:{$this->tenant->id}";

        $this->assertTrue(Cache::add($chave, 1, 60));
        $this->assertFalse(Cache::add($chave, 1, 60), 'o segundo pedido no mesmo minuto não despacha');
    }
}
