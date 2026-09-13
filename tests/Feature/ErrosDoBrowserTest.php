<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Um ecrã que rebenta no browser de alguém chega ao registo agrupado de erros,
 * como os do servidor.
 */
class ErrosDoBrowserTest extends TestCase
{
    public function test_o_erro_do_ecra_fica_no_registo_agrupado(): void
    {
        // O registo grava por uma ligação própria e sobrevive às transacções
        // dos ensaios: limpa-se o que uma corrida anterior deixou.
        $ecra = 'ensaio/balcao';
        DB::table('erros_do_sistema')->where('mensagem', 'like', 'Browser [ensaio/%')->delete();

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/erros-do-browser', [
                'mensagem' => "Cannot read properties of undefined (reading 'nome')",
                'pilha' => "TypeError: Cannot read properties of undefined
    at Cartao (app-x.js:1:2)",
                'ecra' => $ecra,
                'origem' => 'ecra',
                'endereco' => '/invoicing/pos',
            ])->assertStatus(202);
        }

        $linhas = DB::table('erros_do_sistema')->where('mensagem', 'like', "Browser [{$ecra}]%")->get();

        $this->assertCount(1, $linhas, 'duas vezes o mesmo erro é um problema só');
        $this->assertSame(2, (int) $linhas[0]->ocorrencias);
    }

    public function test_um_relato_sem_mensagem_ou_com_origem_inventada_e_recusado(): void
    {
        $this->postJson('/erros-do-browser', ['origem' => 'ecra'])->assertStatus(422);
        $this->postJson('/erros-do-browser', ['mensagem' => 'x', 'origem' => 'qualquer'])->assertStatus(422);
    }
}
