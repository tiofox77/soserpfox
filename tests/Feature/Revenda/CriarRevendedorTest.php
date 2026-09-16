<?php

namespace Tests\Feature\Revenda;

use App\Models\Reseller;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * O COMANDO QUE CRIA UM REVENDEDOR A PEDIDO DO DONO (16/09/2026): os dados vêm
 * de um ficheiro, a seco não grava nada, e aplicar apaga o ficheiro.
 */
class CriarRevendedorTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private function ficheiro(array $dados): string
    {
        @mkdir(storage_path('app/revenda'), 0775, true);
        $nome = 'teste-' . uniqid() . '.json';
        file_put_contents(storage_path('app/revenda/' . $nome), json_encode($dados));

        return $nome;
    }

    public function test_cria_aprovado_com_a_senha_do_ficheiro_e_apaga_o_ficheiro(): void
    {
        $email = 'novo' . uniqid() . '@exemplo.ao';
        $f = $this->ficheiro(['nome' => 'Celestino Teste', 'email' => strtoupper($email), 'senha_hash' => Hash::make('Senha-do-dono-1')]);

        $this->artisan('revendedor:criar', ['--ficheiro' => $f])->assertSuccessful();
        $this->assertNull(Reseller::where('email', $email)->first(), 'a seco não grava');
        $this->assertFileExists(storage_path('app/revenda/' . $f));

        $this->artisan('revendedor:criar', ['--ficheiro' => $f, '--aplicar' => true])->assertSuccessful();

        $r = Reseller::where('email', $email)->firstOrFail();
        $this->assertSame(['aprovado', 'Celestino Teste'], [$r->status, $r->name]);
        $this->assertNotEmpty($r->code);
        $this->assertTrue(Hash::check('Senha-do-dono-1', $r->password));
        $this->assertFileDoesNotExist(storage_path('app/revenda/' . $f));

        // E entra no portal com essa senha.
        $this->postJson('/revendedor/entrar', ['email' => $email, 'password' => 'Senha-do-dono-1'])->assertOk();
    }

    public function test_um_pedido_pendente_com_o_mesmo_email_fica_aprovado_e_com_a_senha_nova(): void
    {
        $r = Reseller::create(['name' => 'Já Pediu', 'email' => 'pediu' . uniqid() . '@exemplo.ao', 'password' => 'Outra-senha-1']);
        $f = $this->ficheiro(['nome' => 'Já Pediu', 'email' => $r->email, 'senha_hash' => Hash::make('Nova-senha-22')]);

        $this->artisan('revendedor:criar', ['--ficheiro' => $f, '--aplicar' => true])->assertSuccessful();

        $r->refresh();
        $this->assertSame('aprovado', $r->status);
        $this->assertTrue(Hash::check('Nova-senha-22', $r->password));
        $this->assertSame(1, Reseller::where('email', $r->email)->count());
    }

    public function test_um_ficheiro_sem_a_impressao_da_senha_e_recusado(): void
    {
        $f = $this->ficheiro(['nome' => 'Sem Hash', 'email' => 'x' . uniqid() . '@exemplo.ao', 'senha_hash' => 'SenhaEmClaro1']);

        $this->artisan('revendedor:criar', ['--ficheiro' => $f, '--aplicar' => true])->assertFailed();
        @unlink(storage_path('app/revenda/' . $f));
    }
}
