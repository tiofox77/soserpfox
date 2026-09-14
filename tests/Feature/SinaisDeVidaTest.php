<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
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
 *
 * A segunda metade prova o que a produção mostrou errado em 2026-09-14: a
 * inscrição contava como «a montar» durante um mês, um rascunho ou uma factura
 * de há 26 dias contavam como «a facturar», e o dono de várias empresas acendia
 * todas ao entrar numa.
 */
class SinaisDeVidaTest extends TenantTestCase
{
    private function outraEmpresa(?\DateTimeInterface $criada = null, bool $activa = true): Tenant
    {
        $empresa = Tenant::create([
            'name'  => 'Outra ' . uniqid(),
            'slug'  => 'outra-' . uniqid(),
            'nif'   => (string) random_int(500000000, 599999999),
            'email' => 'o' . uniqid() . '@exemplo.ao',
            'is_active' => $activa,
        ]);

        if ($criada) {
            DB::table('tenants')->where('id', $empresa->id)->update(['created_at' => $criada]);
        }

        return $empresa;
    }

    /** Uma factura mínima que a base aceite. */
    private function factura(\DateTimeInterface $quando, string $estado = 'sent'): void
    {
        DB::table('invoicing_sales_invoices')->insert([
            'tenant_id'      => $this->tenant->id,
            'invoice_number' => 'FT-' . uniqid(),
            'client_id'      => $this->cliente->id,
            'invoice_date'   => $quando,
            'status'         => $estado,
            'created_by'     => $this->user->id,
            'created_at'     => $quando,
            'updated_at'     => $quando,
        ]);
    }

    private function artigo(Tenant $empresa, \DateTimeInterface $quando): int
    {
        return DB::table('invoicing_products')->insertGetId([
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

    /** A empresa da classe base nasceu agora; estes casos precisam de uma antiga. */
    private function envelhecer(Tenant $empresa): void
    {
        DB::table('tenants')->where('id', $empresa->id)->update(['created_at' => now()->subMonths(3)]);
        $this->user->update(['last_login_at' => null]);
        DB::table('tenant_user')->where('tenant_id', $empresa->id)->update(['ultimo_acesso_em' => null]);
        DB::table('invoicing_products')->where('tenant_id', $empresa->id)->update(['created_at' => now()->subMonths(3)]);
        DB::table('invoicing_clients')->where('tenant_id', $empresa->id)->update(['created_at' => now()->subMonths(3)]);
    }

    private function estado(Tenant|int $empresa): string
    {
        $id = is_int($empresa) ? $empresa : $empresa->id;

        return SinaisDeVida::para([$id])[$id]->estado['chave'];
    }

    public function test_uma_empresa_antiga_sem_nada_e_dada_como_nunca_usou(): void
    {
        $vazia = $this->outraEmpresa(now()->subMonths(2));

        $sinais = SinaisDeVida::para([$vazia->id]);

        $this->assertSame('vazia', $sinais[$vazia->id]->estado['chave']);
        $this->assertSame(0, $sinais[$vazia->id]->artigos);
    }

    /** Acabada de se inscrever: ainda está a começar, não «nunca usou». */
    public function test_uma_inscricao_recente_esta_a_montar(): void
    {
        $this->assertSame('a_montar', $this->estado($this->outraEmpresa()));
    }

    /**
     * ENTRAR NÃO É USAR. A inscrição faz login: com a regra antiga, toda a
     * inscrição do último mês ficava «a montar» — 25 empresas que só entraram
     * no dia em que se inscreveram.
     */
    public function test_so_ter_entrado_ha_semanas_sem_registar_nada_e_nunca_usou(): void
    {
        $empresa = $this->outraEmpresa(now()->subDays(25));
        $dono = User::create(['name' => 'Dono', 'email' => 'd' . uniqid() . '@exemplo.ao', 'password' => bcrypt('x'), 'tenant_id' => $empresa->id]);
        $dono->tenants()->syncWithoutDetaching([$empresa->id]);
        $dono->update(['last_login_at' => now()->subDays(25)]);

        $s = SinaisDeVida::para([$empresa->id])[$empresa->id];

        $this->assertSame('vazia', $s->estado['chave']);
        $this->assertSame(1, $s->entraram_30d, 'a pessoa entrou nos 30 dias, e o cartão continua a mostrá-lo');
    }

    /** Quem factura está vivo, ponto. */
    public function test_facturar_e_o_sinal_mais_forte(): void
    {
        $this->factura(now());

        $sinais = SinaisDeVida::para([$this->tenant->id]);

        $this->assertSame('activa', $sinais[$this->tenant->id]->estado['chave']);
        $this->assertSame(1, $sinais[$this->tenant->id]->facturas_30d);
    }

    /** Um rascunho não foi emitido: não prova que a empresa factura. */
    public function test_um_rascunho_nao_conta_como_factura(): void
    {
        $this->envelhecer($this->tenant);
        $this->factura(now(), 'draft');

        $s = SinaisDeVida::para([$this->tenant->id])[$this->tenant->id];

        $this->assertSame(0, $s->facturas_30d);
        $this->assertNull($s->ultima_factura);
        $this->assertNotSame('activa', $s->estado['chave']);
    }

    /** Uma factura de há um ano não prova que a empresa esteja viva hoje. */
    public function test_uma_factura_antiga_nao_conta_para_os_30_dias(): void
    {
        $this->factura(now()->subYear());

        $sinais = SinaisDeVida::para([$this->tenant->id]);

        $this->assertSame(0, $sinais[$this->tenant->id]->facturas_30d);
    }

    /**
     * UMA FACTURA DE HÁ 26 DIAS E NINGUÉM A APARECER: parou. Era «a facturar»
     * só por caber na janela de 30 dias.
     */
    public function test_facturou_ha_semanas_e_nao_voltou_esta_adormecida(): void
    {
        $this->envelhecer($this->tenant);
        $this->factura(now()->subDays(26));

        $s = SinaisDeVida::para([$this->tenant->id])[$this->tenant->id];

        $this->assertSame(1, $s->facturas_30d, 'o número dos 30 dias continua certo');
        $this->assertSame('adormecida', $s->estado['chave']);
        $this->assertStringContainsString('26', SinaisDeVida::motivo($s));
    }

    /** Já facturou e continua a entrar: está a usar, não adormecida. */
    public function test_facturou_ha_semanas_mas_continua_a_entrar_esta_a_usar(): void
    {
        $this->envelhecer($this->tenant);
        $this->factura(now()->subDays(20));
        $this->user->update(['last_login_at' => now()->subDays(2)]);

        $this->assertSame('a_usar', $this->estado($this->tenant));
    }

    /** Stock, tesouraria, restaurante…: trabalhar sem facturar também é usar. */
    public function test_operar_sem_facturar_esta_a_usar(): void
    {
        $this->envelhecer($this->tenant);
        $artigo = $this->artigo($this->tenant, now()->subMonths(3));
        DB::table('invoicing_stock_movements')->insert([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $artigo,
            'type' => 'in',
            'quantity' => 5,
            'user_id' => $this->user->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $s = SinaisDeVida::para([$this->tenant->id])[$this->tenant->id];

        $this->assertSame('a_usar', $s->estado['chave']);
        $this->assertSame(1, $s->movimentos_30d);
        $this->assertSame(1, $s->operacoes_30d);
    }

    /** Ter catálogo e ninguém a entrar há um mês é uma empresa a adormecer. */
    public function test_catalogo_sem_actividade_recente_e_adormecida(): void
    {
        $empresa = $this->outraEmpresa(now()->subYear());

        $this->artigo($empresa, now()->subMonths(6));

        $sinais = SinaisDeVida::para([$empresa->id]);

        $this->assertSame('adormecida', $sinais[$empresa->id]->estado['chave']);
        $this->assertSame(1, $sinais[$empresa->id]->artigos);
    }

    /** As desactivadas saem dos outros cartões — contavam em «a montar» e «nunca usaram». */
    public function test_uma_empresa_desactivada_tem_estado_proprio(): void
    {
        $empresa = $this->outraEmpresa(null, false);

        $this->assertSame('desactivada', $this->estado($empresa));
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
        DB::table('tenant_user')->where('user_id', $this->user->id)->update(['ultimo_acesso_em' => null]);
        $this->user->update(['last_login_at' => now()->subDays(2)]);

        $sinais = SinaisDeVida::para([$this->tenant->id]);

        $this->assertNotNull($sinais[$this->tenant->id]->ultima_entrada);
        $this->assertSame(1, $sinais[$this->tenant->id]->entraram_30d);
    }

    /**
     * O DONO DE VÁRIAS EMPRESAS. A trabalhar na farmácia, fazia a outra empresa
     * dele parecer viva («entrou há 15 min» numa empresa parada há dez dias).
     * O acesso conta só na empresa onde esteve.
     */
    public function test_quem_tem_varias_empresas_so_acende_aquela_em_que_esteve(): void
    {
        $outra = $this->outraEmpresa(now()->subMonths(2));
        $this->user->tenants()->syncWithoutDetaching([$outra->id]);
        $this->user->update(['last_login_at' => now()]);
        DB::table('tenant_user')->where('user_id', $this->user->id)->update(['ultimo_acesso_em' => null]);
        DB::table('tenant_user')->where('user_id', $this->user->id)->where('tenant_id', $this->tenant->id)
            ->update(['ultimo_acesso_em' => now()]);

        $sinais = SinaisDeVida::para([$this->tenant->id, $outra->id]);

        $this->assertNotNull($sinais[$this->tenant->id]->ultima_entrada);
        $this->assertNull($sinais[$outra->id]->ultima_entrada, 'a outra empresa não foi visitada');
        $this->assertSame(0, $sinais[$outra->id]->entraram_30d);
    }

    /**
     * O custo não pode crescer com o número de empresas.
     *
     * Consultas fixas para a página inteira. O caminho fácil — perguntar por
     * empresa ou por tabela — torna a lista inutilizável ao fim de trinta
     * clientes.
     */
    public function test_o_numero_de_consultas_nao_cresce_com_as_empresas(): void
    {
        $empresas = collect([$this->tenant->id]);
        for ($i = 0; $i < 5; $i++) {
            $empresas->push($this->outraEmpresa()->id);
        }

        SinaisDeVida::esquecerEsquema();
        DB::enableQueryLog();
        DB::flushQueryLog();

        SinaisDeVida::para($empresas);

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            6,
            $consultas,
            "Seis empresas deram {$consultas} consultas — o custo está a crescer com a lista."
        );
    }

    public function test_uma_lista_vazia_nao_faz_consultas(): void
    {
        $this->assertTrue(SinaisDeVida::para([])->isEmpty());
    }
}
