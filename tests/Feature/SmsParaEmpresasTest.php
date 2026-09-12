<?php

namespace Tests\Feature;

use App\Models\SmsSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PartesDeSms;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * O painel que manda um SMS as empresas, a todas ou so as escolhidas.
 *
 * O SMS custa por mensagem e um engano aqui multiplica-se por todas as
 * empresas, por isso o que se guarda aqui e sobretudo o que TRAVA: nao enviar
 * sem rever, nao contar como destinatario quem nao tem telefone, e nao deixar
 * uma falha levar as restantes atras.
 *
 * O ecrã passou a React e fala com `/api/v1/plataforma/react/sms-empresas`.
 */
class SmsParaEmpresasTest extends TenantTestCase
{
    private const API = '/api/v1/plataforma/react/sms-empresas';

    private function comoDonoDaPlataforma(): User
    {
        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->user->fresh());

        return $this->user;
    }

    private function comConfiguracaoDeSms(): void
    {
        // A TelcoSMS e não a D7: a D7 envia por cURL cru, que o Http::fake não
        // apanha, e um ensaio que mande um SMS a sério é um ensaio que custa.
        SmsSetting::where('tenant_id', $this->tenant->id)->delete();
        SmsSetting::updateOrCreate(['tenant_id' => null], [
            'provider'  => 'telcosms',
            'api_url'   => 'https://www.telcosms.co.ao/api/v2/send_message',
            'api_token' => 'token-de-teste',
            'sender_id' => 'SOSERP',
            'config'    => ['telco_application' => 'soserp_prd', 'sender' => 'SOSERP'],
            'is_active' => true,
        ]);
        Http::fake(['www.telcosms.co.ao/*' => Http::response('', 200)]);
        Http::preventStrayRequests();
    }

    private function empresa(string $nome, ?string $telefone): Tenant
    {
        return Tenant::create([
            'name'         => $nome,
            'company_name' => $nome,
            'phone'        => $telefone,
            'is_active'    => true,
        ]);
    }

    private function pedido(array $troca = []): array
    {
        return array_merge(['mensagem' => 'Aviso importante', 'publico' => 'todas', 'empresa_ids' => [], 'plano_ids' => []], $troca);
    }

    // ---- o custo, que e o que se paga ------------------------------------

    /** Uma mensagem curta sem acentos e uma parte. */
    public function test_uma_mensagem_curta_e_uma_parte(): void
    {
        $this->assertSame(1, PartesDeSms::contar('Manutencao no sabado as 22h.'));
    }

    /**
     * Um acento leva a mensagem a UCS-2: a mesma mensagem com 'ã' custa tres
     * partes em vez de uma. E a diferenca que o ecra tem de mostrar.
     */
    public function test_um_acento_encolhe_a_parte_e_multiplica_o_custo(): void
    {
        $semAcento = str_repeat('a', 150);
        $comAcento = str_repeat('a', 149) . 'ã';

        $this->assertSame(1, PartesDeSms::contar($semAcento));
        $this->assertSame(3, PartesDeSms::contar($comAcento));
    }

    public function test_sem_mensagem_nao_ha_partes(): void
    {
        $this->assertSame(0, PartesDeSms::contar(''));
    }

    /** O ecrã faz a mesma conta enquanto se escreve — com os mesmos limites. */
    public function test_o_ecra_conta_as_partes_com_os_mesmos_limites(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/plataforma/SmsParaEmpresas.tsx'));

        $this->assertStringContainsString('[70, 67]', $ecra);
        $this->assertStringContainsString('[160, 153]', $ecra);
    }

    // ---- quem recebe -----------------------------------------------------

    /** Quem nao tem telefone nao conta como destinatario — e diz-se quem e. */
    public function test_quem_nao_tem_telefone_nao_conta(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();

        Tenant::query()->update(['is_active' => false]);
        $this->empresa('Com Telefone', '923000001');
        $this->empresa('Sem Telefone', null);

        $this->postJson(self::API . '/rever', $this->pedido())
            ->assertOk()
            ->assertJson(['alvo' => 2, 'com_telefone' => 1, 'sem_telefone' => ['Sem Telefone'], 'partes' => 1, 'total_de_partes' => 1]);
    }

    /**
     * E quem escolhe ve QUAIS e que nao tem telefone, na propria lista. Saber
     * so o total ("2 de 3 recebem") obriga a ir procurar a mao qual falta.
     */
    public function test_a_lista_marca_quem_nao_tem_telefone(): void
    {
        $this->comoDonoDaPlataforma();

        Tenant::query()->update(['is_active' => false]);
        $sem = $this->empresa('Empresa Sem Numero', null);

        $linha = collect($this->getJson(self::API)->assertOk()->json('empresas'))->firstWhere('id', $sem->id);

        $this->assertNull($linha['telefone']);
        $this->assertStringContainsString("t('sem telefone')", file_get_contents(resource_path('js/ecras/plataforma/SmsParaEmpresas.tsx')));
    }

    /** Escolher algumas manda so a essas. */
    public function test_escolher_algumas_manda_so_a_essas(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();

        Tenant::query()->update(['is_active' => false]);
        $uma = $this->empresa('Uma', '923000001');
        $this->empresa('Outra', '923000002');

        $assinatura = $this->postJson(self::API . '/rever', $this->pedido(['publico' => 'empresas', 'empresa_ids' => [$uma->id]]))
            ->assertOk()->assertJson(['com_telefone' => 1])->json('assinatura');

        $this->postJson(self::API . '/enviar', $this->pedido(['publico' => 'empresas', 'empresa_ids' => [$uma->id], 'assinatura' => $assinatura]))
            ->assertOk()->assertJsonPath('resultado.enviados', 1);

        $this->assertDatabaseHas('sms_logs', ['tenant_id' => $uma->id, 'type' => 'aviso_plataforma']);
        $this->assertDatabaseMissing('sms_logs', ['recipient' => '+244923000002']);
    }

    /** Escolher "empresas" sem escolher nenhuma nao manda a todas. */
    public function test_escolher_empresas_sem_nenhuma_nao_manda_a_todas(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();
        $this->empresa('Alguma', '923000001');

        $this->postJson(self::API . '/rever', $this->pedido(['publico' => 'empresas', 'empresa_ids' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('empresa_ids');
    }

    // ---- os travoes ------------------------------------------------------

    /** Nao se envia sem passar pela revisao. */
    public function test_nao_envia_sem_confirmar(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();
        $this->empresa('Alguma', '923000001');

        $this->postJson(self::API . '/enviar', $this->pedido())
            ->assertStatus(422)->assertJsonValidationErrors('assinatura');

        $this->assertDatabaseMissing('sms_logs', ['type' => 'aviso_plataforma']);
    }

    /**
     * Mudar o publico depois de rever desfaz a confirmacao: a confirmacao e
     * sobre AQUELES destinatarios e AQUELE texto. Trocar uma empresa por outra
     * — o mesmo numero de destinatarios — tambem tem de ser recusado.
     */
    public function test_mudar_o_publico_desfaz_a_confirmacao(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();

        Tenant::query()->update(['is_active' => false]);
        $uma = $this->empresa('Uma', '923000001');
        $this->empresa('Outra', '923000002');

        $outra = Tenant::where('name', 'Outra')->firstOrFail();
        $assinatura = $this->postJson(self::API . '/rever', $this->pedido(['publico' => 'empresas', 'empresa_ids' => [$uma->id]]))->json('assinatura');

        $this->postJson(self::API . '/enviar', $this->pedido(['publico' => 'todas', 'assinatura' => $assinatura]))
            ->assertStatus(422)->assertJsonValidationErrors('assinatura');

        $this->postJson(self::API . '/enviar', $this->pedido(['publico' => 'empresas', 'empresa_ids' => [$outra->id], 'assinatura' => $assinatura]))
            ->assertStatus(422)->assertJsonValidationErrors('assinatura');

        $this->postJson(self::API . '/enviar', $this->pedido(['publico' => 'empresas', 'empresa_ids' => [$uma->id], 'mensagem' => 'Outro texto', 'assinatura' => $assinatura]))
            ->assertStatus(422)->assertJsonValidationErrors('assinatura');

        $this->assertDatabaseMissing('sms_logs', ['type' => 'aviso_plataforma']);
    }

    /** Sem ninguem com telefone nao se chega sequer a confirmar. */
    public function test_sem_ninguem_com_telefone_nao_deixa_rever(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();

        Tenant::query()->update(['is_active' => false, 'phone' => null]);
        $this->empresa('Sem Telefone', null);

        $this->postJson(self::API . '/rever', $this->pedido())
            ->assertStatus(422)->assertJsonValidationErrors('mensagem');
    }

    /** Uma mensagem vazia nao passa. */
    public function test_mensagem_vazia_nao_passa(): void
    {
        $this->comoDonoDaPlataforma();

        $this->postJson(self::API . '/rever', $this->pedido(['mensagem' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('mensagem');
    }

    /**
     * O que sai e o que esta escrito, com as variaveis trocadas por empresa.
     * Escolher um modelo e corrigir o texto na caixa mandava o modelo intacto.
     */
    public function test_o_texto_escrito_e_o_que_sai_com_as_variaveis_trocadas(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();

        Tenant::query()->update(['is_active' => false]);
        $uma = $this->empresa('Padaria Kianda', '923000001');

        $pedido = $this->pedido(['mensagem' => 'Ola {{tenant_name}}, paragem as 22h.', 'publico' => 'empresas', 'empresa_ids' => [$uma->id]]);
        $assinatura = $this->postJson(self::API . '/rever', $pedido)->assertOk()->json('assinatura');

        $this->postJson(self::API . '/enviar', $pedido + ['assinatura' => $assinatura])
            ->assertOk()->assertJsonPath('resultado.enviados', 1);

        $this->assertDatabaseHas('sms_logs', ['tenant_id' => $uma->id, 'message' => 'Ola Padaria Kianda, paragem as 22h.']);
    }

    /** Quem nao e dono da plataforma nao entra. */
    public function test_quem_nao_e_dono_da_plataforma_nao_entra(): void
    {
        $this->user->forceFill(['is_super_admin' => false])->save();
        $this->actingAs($this->user->fresh());

        $this->getJson(self::API)->assertForbidden();
        $this->postJson(self::API . '/enviar', $this->pedido(['assinatura' => str_repeat('a', 64)]))->assertForbidden();
    }

    /**
     * A pagina abre mesmo.
     *
     * Faltava este quando era Livewire: os testes exercitavam o componente sem
     * passar pelo layout, e a rota rebentava com 500 sem nenhum teste dar por
     * isso.
     */
    public function test_a_pagina_abre(): void
    {
        $this->comoDonoDaPlataforma();

        $this->get('/superadmin/sms-empresas')
            ->assertOk()
            ->assertSee('data-ecra="plataforma/sms-empresas"', false);
    }

    /** E quem nao e dono da plataforma nao entra por la. */
    public function test_a_rota_recusa_quem_nao_e_dono_da_plataforma(): void
    {
        $this->user->forceFill(['is_super_admin' => false])->save();
        $this->actingAs($this->user->fresh());

        $this->assertNotEquals(200, $this->get('/superadmin/sms-empresas')->getStatusCode());
    }
}
