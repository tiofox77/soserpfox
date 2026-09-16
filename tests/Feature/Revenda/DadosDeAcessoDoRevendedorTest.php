<?php

namespace Tests\Feature\Revenda;

use App\Mail\Revenda\AvisoDaRevenda;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Revenda\RegraDeComissao;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * O EMAIL DOS DADOS DE ACESSO (16/09/2026): pelo botão do super admin ou pelo
 * comando, só a aprovados, e a senha nunca vai no email.
 */
class DadosDeAcessoDoRevendedorTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private function revendedor(string $estado = 'aprovado'): Reseller
    {
        $r = Reseller::create(['name' => 'Celestino Acesso', 'email' => 'acesso' . uniqid() . '@exemplo.ao', 'password' => 'Senha-secreta-9']);
        $r->forceFill(['status' => $estado, 'code' => $estado === 'aprovado' ? Reseller::novoCodigo('Celestino') : null,
            'commission' => RegraDeComissao::de(['valor' => 20])->paraGuardar()])->save();

        return $r;
    }

    private function corpo(AvisoDaRevenda $m): string
    {
        return $m->render();
    }

    public function test_o_comando_a_seco_nao_envia_e_com_enviar_envia_o_guia_sem_a_senha(): void
    {
        Mail::fake();
        $r = $this->revendedor();

        $this->artisan('revendedor:dados-de-acesso', ['codigo' => strtolower($r->code)])->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('revendedor:dados-de-acesso', ['codigo' => $r->code, '--enviar' => true])->assertSuccessful();

        Mail::assertSent(AvisoDaRevenda::class, function (AvisoDaRevenda $m) use ($r) {
            $html = $this->corpo($m);

            return $m->hasTo($r->email)
                && $m->assunto === 'Os seus dados de acesso ao portal do revendedor'
                && str_contains($html, $r->code)
                && str_contains($html, e($r->link()))
                && str_contains($html, route('revendedor.login'))
                && str_contains($html, route('revendedor.esqueci'))
                && str_contains($html, 'Em Pagamentos paga pelo cliente')
                && ! str_contains($html, 'Senha-secreta-9');
        });
    }

    public function test_o_comando_recusa_codigo_desconhecido_e_revendedor_nao_aprovado(): void
    {
        Mail::fake();
        $suspenso = $this->revendedor();
        $suspenso->forceFill(['status' => 'suspenso'])->save();

        $this->artisan('revendedor:dados-de-acesso', ['codigo' => 'NAOEXISTE999', '--enviar' => true])->assertFailed();
        $this->artisan('revendedor:dados-de-acesso', ['codigo' => $suspenso->code, '--enviar' => true])->assertFailed();
        Mail::assertNothingSent();
    }

    public function test_o_super_admin_envia_pelo_botao_e_so_a_aprovados(): void
    {
        Mail::fake();
        $dono = User::create(['name' => 'Dono', 'email' => 'dono' . uniqid() . '@exemplo.ao', 'password' => 'x', 'is_super_admin' => true, 'is_active' => true]);
        $aprovado = $this->revendedor();
        $pendente = $this->revendedor('pendente');

        $this->actingAs($dono);
        $this->postJson("/api/v1/plataforma/react/revendedores/{$aprovado->id}/dados-de-acesso")
            ->assertOk()->assertJsonPath('message', "Dados de acesso enviados para {$aprovado->email}.");
        Mail::assertSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($aprovado->email));

        $this->postJson("/api/v1/plataforma/react/revendedores/{$pendente->id}/dados-de-acesso")
            ->assertStatus(422)->assertJsonValidationErrors('id');
        Mail::assertNotSent(AvisoDaRevenda::class, fn ($m) => $m->hasTo($pendente->email));
    }

    public function test_quem_nao_e_super_admin_nao_envia(): void
    {
        Mail::fake();
        $r = $this->revendedor();
        $qualquer = User::create(['name' => 'Qualquer', 'email' => 'q' . uniqid() . '@exemplo.ao', 'password' => 'x', 'is_active' => true]);

        $this->actingAs($qualquer);
        $this->postJson("/api/v1/plataforma/react/revendedores/{$r->id}/dados-de-acesso")->assertForbidden();
        Mail::assertNothingSent();
    }
}
