<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Uma permissão não dá outra pela semelhança do nome.
 *
 * 2026-09-02: `enable_wildcard_permission` estava ligado. O Spatie lê os
 * nomes por partes e trata o que falta como «tudo», por isso quem tinha
 * `invoicing.pos.reports` («Ver Relatórios POS») ficava também com
 * `invoicing.pos.reports.all` («…de Todos os Caixas»). Resultado: todo o
 * caixa via as vendas dos colegas, e o ecrã que separava as duas coisas
 * separava-as em vão.
 *
 * Este ensaio prende as duas pontas: a definição e o comportamento.
 */
class PermissoesNaoSeEngolemTest extends TenantTestCase
{
    /** @test */
    public function o_wildcard_esta_desligado(): void
    {
        $this->assertFalse(
            (bool) config('permission.enable_wildcard_permission'),
            'Com o wildcard ligado, `x` passa a valer por `x.y` — corra permissoes:sombras antes de o ligar.'
        );
    }

    /**
     * O caso que deu o alarme: ver os relatórios do POS não é ver os de todos.
     *
     * @test
     */
    public function ver_relatorios_do_pos_nao_da_o_direito_de_ver_os_de_todos(): void
    {
        $this->comPermissoes('invoicing.pos.reports');
        \Spatie\Permission\Models\Permission::findOrCreate('invoicing.pos.reports.all', 'web');

        $utilizador = auth()->user();

        $this->assertTrue($utilizador->can('invoicing.pos.reports'));
        $this->assertFalse(
            $utilizador->can('invoicing.pos.reports.all'),
            'um caixa continua a ver as vendas de todos os colegas'
        );
    }

    /**
     * A regra geral, para as próximas permissões que alguém acrescentar:
     * dar a curta nunca dá a longa.
     *
     * @test
     */
    public function ter_o_prefixo_nunca_da_o_nome_completo(): void
    {
        $this->comPermissoes('relatorios.teste');
        \Spatie\Permission\Models\Permission::findOrCreate('relatorios.teste.tudo', 'web');

        $this->assertFalse(auth()->user()->can('relatorios.teste.tudo'));
    }

    /**
     * Ninguém depende da sintaxe wildcard: se alguém criar uma permissão com
     * `*` no nome, ela deixou de funcionar e este ensaio avisa.
     *
     * @test
     */
    public function nenhuma_permissao_usa_a_sintaxe_wildcard(): void
    {
        $comAsterisco = DB::table('permissions')->where('name', 'like', '%*%')->pluck('name')->all();

        $this->assertSame([], $comAsterisco,
            'permissões com * deixaram de valer quando o wildcard foi desligado: '.implode(', ', $comAsterisco));
    }
}
