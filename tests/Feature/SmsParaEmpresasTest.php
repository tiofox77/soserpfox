<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\SmsParaEmpresas;
use App\Models\SmsSetting;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O painel que manda um SMS as empresas, a todas ou so as escolhidas.
 *
 * O SMS custa por mensagem e um engano aqui multiplica-se por todas as
 * empresas, por isso o que se guarda aqui e sobretudo o que TRAVA: nao enviar
 * sem confirmar, nao contar como destinatario quem nao tem telefone, e nao
 * deixar uma falha levar as restantes atras.
 */
class SmsParaEmpresasTest extends TenantTestCase
{
    private function comoDonoDaPlataforma(): User
    {
        $this->user->update(['is_super_admin' => true]);
        $this->actingAs($this->user->fresh());

        return $this->user;
    }

    private function comConfiguracaoDeSms(): void
    {
        SmsSetting::updateOrCreate(['tenant_id' => null], [
            'provider'  => 'd7networks',
            'api_url'   => 'https://api.d7networks.com/messages/v1/send',
            'api_token' => 'token-de-teste',
            'sender_id' => 'SOS ERP',
            'is_active' => true,
        ]);
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

    // ---- o custo, que e o que se paga ------------------------------------

    /** Uma mensagem curta sem acentos e uma parte. */
    public function test_uma_mensagem_curta_e_uma_parte(): void
    {
        $this->comoDonoDaPlataforma();

        Livewire::test(SmsParaEmpresas::class)
            ->set('mensagem', 'Manutencao no sabado as 22h.')
            ->assertReturned(fn () => true);

        $componente = Livewire::test(SmsParaEmpresas::class)->set('mensagem', 'Curta');

        $this->assertSame(1, $componente->instance()->partes());
    }

    /**
     * Um unico acento leva a mensagem para UCS-2, onde cada parte encolhe de
     * 160 para 70. Contar caracteres escondia isto: 160 letras sao uma parte,
     * e as mesmas com um "a" com til sao tres.
     */
    public function test_um_acento_encolhe_a_parte_e_multiplica_o_custo(): void
    {
        $this->comoDonoDaPlataforma();

        $semAcento = str_repeat('a', 160);
        $comAcento = str_repeat('a', 159) . 'ã';

        $um = Livewire::test(SmsParaEmpresas::class)->set('mensagem', $semAcento)->instance()->partes();
        $tres = Livewire::test(SmsParaEmpresas::class)->set('mensagem', $comAcento)->instance()->partes();

        $this->assertSame(1, $um);
        $this->assertGreaterThan($um, $tres, 'o acento tinha de aumentar as partes');
    }

    public function test_sem_mensagem_nao_ha_partes(): void
    {
        $this->comoDonoDaPlataforma();

        $this->assertSame(0, Livewire::test(SmsParaEmpresas::class)->set('mensagem', '')->instance()->partes());
    }

    // ---- quem recebe -----------------------------------------------------

    /** Quem nao tem telefone nao conta como destinatario. */
    public function test_quem_nao_tem_telefone_nao_conta(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();

        Tenant::query()->update(['is_active' => false]);
        $com = $this->empresa('Com Telefone', '923000001');
        $this->empresa('Sem Telefone', null);

        $componente = Livewire::test(SmsParaEmpresas::class)->set('publico', 'todas');

        // Pelos métodos e não por ->get(): quantasAlvo e quantasPodem são
        // variáveis da vista, e o ->get() só chega às propriedades públicas —
        // devolvia null e o teste passava a comparar null com null.
        $this->assertSame(2, $componente->instance()->alvo()->count());
        $this->assertSame(1, $componente->instance()->comTelefone()->count());
        $this->assertSame([$com->id], $componente->instance()->comTelefone()->pluck('id')->all());
    }

    /**
     * E quem escolhe ve QUAIS e que nao tem telefone, na propria lista. Saber
     * so o total ("2 de 3 recebem") obriga a ir procurar a mao qual falta.
     */
    public function test_a_lista_marca_quem_nao_tem_telefone(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();

        Tenant::query()->update(['is_active' => false]);
        $this->empresa('Empresa Sem Numero', null);

        Livewire::test(SmsParaEmpresas::class)
            ->set('publico', 'empresas')
            ->assertSee('Empresa Sem Numero')
            ->assertSee('sem telefone');
    }

    /** Escolher algumas manda so a essas. */
    public function test_escolher_algumas_manda_so_a_essas(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();

        Tenant::query()->update(['is_active' => false]);
        $uma   = $this->empresa('Uma', '923000001');
        $outra = $this->empresa('Outra', '923000002');

        $componente = Livewire::test(SmsParaEmpresas::class)
            ->set('publico', 'empresas')
            ->set('empresa_ids', [$uma->id]);

        $this->assertSame([$uma->id], $componente->instance()->comTelefone()->pluck('id')->all());
        $this->assertNotContains($outra->id, $componente->instance()->comTelefone()->pluck('id')->all());
    }

    /** Escolher "empresas" sem escolher nenhuma nao manda a todas. */
    public function test_escolher_empresas_sem_nenhuma_nao_manda_a_todas(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();
        $this->empresa('Alguma', '923000001');

        $componente = Livewire::test(SmsParaEmpresas::class)
            ->set('publico', 'empresas')
            ->set('empresa_ids', []);

        $this->assertCount(0, $componente->instance()->comTelefone());
    }

    // ---- os travoes ------------------------------------------------------

    /** Nao se envia sem passar pela confirmacao. */
    public function test_nao_envia_sem_confirmar(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();
        $this->empresa('Alguma', '923000001');

        Livewire::test(SmsParaEmpresas::class)
            ->set('mensagem', 'Aviso importante')
            ->call('enviar')
            ->assertSet('resultado', []);
    }

    /** Mudar o publico depois de rever desfaz a confirmacao. */
    public function test_mudar_o_publico_desfaz_a_confirmacao(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();
        $this->empresa('Alguma', '923000001');

        Livewire::test(SmsParaEmpresas::class)
            ->set('mensagem', 'Aviso importante')
            ->call('rever')
            ->assertSet('porConfirmar', true)
            ->set('publico', 'empresas')
            ->assertSet('porConfirmar', false);
    }

    /** Sem ninguem com telefone nao se chega sequer a confirmar. */
    public function test_sem_ninguem_com_telefone_nao_deixa_rever(): void
    {
        $this->comoDonoDaPlataforma();
        $this->comConfiguracaoDeSms();

        Tenant::query()->update(['is_active' => false, 'phone' => null]);
        $this->empresa('Sem Telefone', null);

        Livewire::test(SmsParaEmpresas::class)
            ->set('mensagem', 'Aviso importante')
            ->call('rever')
            ->assertHasErrors('mensagem')
            ->assertSet('porConfirmar', false);
    }

    /** Uma mensagem vazia nao passa. */
    public function test_mensagem_vazia_nao_passa(): void
    {
        $this->comoDonoDaPlataforma();

        Livewire::test(SmsParaEmpresas::class)
            ->set('mensagem', '')
            ->call('rever')
            ->assertHasErrors('mensagem');
    }

    /** Quem nao e dono da plataforma nao entra. */
    public function test_quem_nao_e_dono_da_plataforma_nao_entra(): void
    {
        $this->user->update(['is_super_admin' => false]);
        $this->actingAs($this->user->fresh());

        Livewire::test(SmsParaEmpresas::class)->assertForbidden();
    }
}
