<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenants\EliminacaoDeEmpresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * A limpeza de uma empresa eliminada.
 *
 * A cascata escrita à mão tratava OITO tabelas. Medido nesta base, 142 têm
 * `tenant_id` — as outras 134 ficavam com linhas a apontar para uma empresa
 * que já não existe. Invisíveis, porque os filtros por empresa nunca mais as
 * devolvem, e lá para sempre.
 */
class EliminacaoDeEmpresaTest extends TenantTestCase
{
    /** Uma empresa com dados espalhados por várias tabelas. */
    private function empresaComDados(): Tenant
    {
        $empresa = Tenant::create([
            'name'      => 'Empresa a Eliminar',
            'slug'      => 'eliminar-' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'del' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        $armazem = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $empresa->id,
            'name'      => 'Armazém',
            'code'      => 'ARM-' . uniqid(),
            'is_active' => true,
        ]);

        $artigo = Product::withoutGlobalScopes()->create([
            'tenant_id'      => $empresa->id,
            'name'           => 'Artigo',
            'sku'            => 'SKU-' . uniqid(),
            'price'          => 100,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        Stock::withoutGlobalScopes()->create([
            'tenant_id'    => $empresa->id,
            'warehouse_id' => $armazem->id,
            'product_id'   => $artigo->id,
            'quantity'     => 10,
        ]);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $empresa->id,
            'name'      => 'Cliente',
            'nif'       => (string) random_int(100000000, 199999999),
            'type'      => 'pessoa_fisica',
            'is_active' => true,
        ]);

        \App\Services\HR\DefinicoesRH::garantirPara($empresa->id);
        \App\Services\Notifications\ModelosPadrao::garantirPara($empresa->id);

        return $empresa;
    }

    public function test_a_lista_de_tabelas_vem_do_esquema(): void
    {
        // Escrita à mão ficava desactualizada na primeira migração que
        // acrescentasse uma tabela — e o sintoma disso é silencioso.
        $tabelas = EliminacaoDeEmpresa::tabelasDaEmpresa();

        $this->assertGreaterThan(100, count($tabelas), 'devem ser mais de cem tabelas');

        foreach (['invoicing_stocks', 'invoicing_products', 'hr_settings', 'notification_templates'] as $esperada) {
            $this->assertContains($esperada, $tabelas, "{$esperada} tem de entrar na limpeza");
        }
    }

    public function test_a_propria_tabela_de_empresas_nao_entra(): void
    {
        // Quem apaga a empresa é o Eloquent; apagá-la aqui deixava o modelo a
        // operar sobre uma linha que já não existe.
        $this->assertNotContains('tenants', EliminacaoDeEmpresa::tabelasDaEmpresa());
    }

    public function test_a_trilha_de_auditoria_nao_e_limpa(): void
    {
        // É append-only e encadeada por hash: é o registo de que a empresa
        // existiu. Não se deita fora o registo de uma coisa por se apagar
        // essa coisa.
        $this->assertNotContains('audit_trail', EliminacaoDeEmpresa::tabelasDaEmpresa());
    }

    public function test_contar_mostra_o_que_seria_apagado_sem_apagar(): void
    {
        $empresa = $this->empresaComDados();

        $antes = EliminacaoDeEmpresa::contar($empresa->id);

        $this->assertArrayHasKey('invoicing_products', $antes);
        $this->assertArrayHasKey('hr_settings', $antes);

        // Contar não pode mexer em nada.
        $this->assertSame($antes, EliminacaoDeEmpresa::contar($empresa->id));
    }

    public function test_a_limpeza_nao_deixa_nada_para_tras(): void
    {
        // O teste que dá sentido a tudo isto.
        $empresa = $this->empresaComDados();

        $antes = EliminacaoDeEmpresa::contar($empresa->id);
        $this->assertGreaterThan(3, count($antes), 'a empresa tem de ter dados em várias tabelas');

        EliminacaoDeEmpresa::limpar($empresa->id);

        $depois = EliminacaoDeEmpresa::contar($empresa->id);

        $this->assertSame(
            [],
            $depois,
            'ficaram linhas por apagar: ' . json_encode($depois, JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_a_limpeza_nao_toca_noutras_empresas(): void
    {
        // O erro que faria estragos irreparáveis.
        $vitima  = $this->empresaComDados();
        $vizinha = $this->empresaComDados();

        $antesVizinha = EliminacaoDeEmpresa::contar($vizinha->id);

        EliminacaoDeEmpresa::limpar($vitima->id);

        $this->assertSame(
            $antesVizinha,
            EliminacaoDeEmpresa::contar($vizinha->id),
            'a empresa do lado não pode perder uma única linha'
        );
    }

    public function test_limpar_duas_vezes_nao_rebenta(): void
    {
        $empresa = $this->empresaComDados();

        EliminacaoDeEmpresa::limpar($empresa->id);
        $segunda = EliminacaoDeEmpresa::limpar($empresa->id);

        $this->assertSame([], $segunda['apagadas'], 'a segunda passagem não tem nada a apagar');
        $this->assertSame([], $segunda['por_apagar']);
    }

    public function test_a_limpeza_converge_em_poucas_passagens(): void
    {
        // As passagens existem para contornar as chaves estrangeiras sem
        // desligar a verificação de integridade — que esconderia exactamente
        // os erros que interessa ver. Se precisasse de muitas, alguma coisa
        // estaria errada.
        $empresa = $this->empresaComDados();

        $r = EliminacaoDeEmpresa::limpar($empresa->id);

        $this->assertLessThanOrEqual(4, $r['passagens'], "precisou de {$r['passagens']} passagens");
        $this->assertSame([], $r['por_apagar'], 'não pode ficar nada por apagar');
    }

    public function test_eliminar_a_empresa_pelo_modelo_limpa_tudo(): void
    {
        // A cadeia completa: forceDelete → cascata do modelo → limpeza.
        $empresa = $this->empresaComDados();
        $id = $empresa->id;

        $pessoa = User::create([
            'name'      => 'Pessoa',
            'email'     => 'p' . uniqid() . '@exemplo.ao',
            'password'  => bcrypt('secret'),
            'tenant_id' => $id,
        ]);
        $pessoa->tenants()->syncWithoutDetaching([$id]);

        $empresa->forceDelete();

        $this->assertSame(
            [],
            EliminacaoDeEmpresa::contar($id),
            'depois de eliminada não pode sobrar linha nenhuma'
        );

        $this->assertNull(Tenant::withTrashed()->find($id));
        $this->assertNull(User::find($pessoa->id), 'a pessoa só pertencia a esta empresa');
    }

    public function test_um_soft_delete_continua_a_nao_limpar_nada(): void
    {
        // A limpeza corre no `forceDeleting`. Um soft delete apenas esconde a
        // empresa — se limpasse, restaurá-la dava uma casa vazia.
        $empresa = $this->empresaComDados();

        $antes = EliminacaoDeEmpresa::contar($empresa->id);

        $empresa->delete();

        $this->assertSame(
            $antes,
            EliminacaoDeEmpresa::contar($empresa->id),
            'um soft delete não pode apagar dados'
        );
    }
}
