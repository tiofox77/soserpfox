<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * O comando que troca a senha de quem trabalha na bancada de ensaio.
 *
 * PORQUE É PRECISO PRENDÊ-LO. Um comando que escreve senhas em produção é uma
 * porta, e as portas fecham-se com ensaios, não com boas intenções. Aqui
 * exige-se que ele: recuse tudo o que não seja a bancada, recuse texto onde
 * espera um hash, e não diga nada sobre contas de clientes.
 */
class SenhaDaBancadaTest extends TestCase
{
    // Cada ensaio monta a sua bancada: sem isto, o slug repetia-se.
    use DatabaseTransactions;

    private function empresa(string $slug, string $nome): Tenant
    {
        // firstOrCreate: o slug da bancada é fixo por definição, e pode ter
        // ficado de execuções anteriores na base de ensaios.
        return Tenant::firstOrCreate(['slug' => $slug], [
            'name'      => $nome,
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);
    }

    private function bancada(): Tenant
    {
        return $this->empresa('bancada-de-ensaio', 'Bancada de Ensaio');
    }

    private function pessoa(Tenant $empresa, string $email): User
    {
        // Também firstOrCreate: os emails da bancada são fixos.
        $u = User::firstOrCreate(['email' => $email], [
            'name'      => 'Alguém',
            'password'  => bcrypt('a-senha-de-antes'),
            'tenant_id' => $empresa->id,
        ]);

        $u->forceFill(['password' => bcrypt('a-senha-de-antes')])->save();

        $u->tenants()->syncWithoutDetaching([$empresa->id => ['is_active' => true]]);

        return $u;
    }

    /** @test */
    public function troca_a_senha_de_quem_e_da_bancada(): void
    {
        $empresa = $this->bancada();
        $gerente = $this->pessoa($empresa, 'gerente@ensaio.soserp.vip');

        $hash = Hash::make('uma-senha-de-ensaio');

        $this->artisan('bancada:senha', [
            '--email' => 'gerente@ensaio.soserp.vip',
            '--hash' => $hash,
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('uma-senha-de-ensaio', $gerente->fresh()->password));
    }

    /**
     * A PORTA SÓ ABRE PARA A BANCADA.
     *
     * Com o token de manutenção na mão, este comando não pode ser a forma de
     * entrar na conta de um cliente.
     *
     * @test
     */
    public function recusa_quem_nao_e_da_bancada(): void
    {
        $this->bancada();

        $outra = $this->empresa('farmacia-a-serio', 'Farmácia a Sério');
        $cliente = $this->pessoa($outra, 'dono@farmacia.ao');
        $antes = $cliente->password;

        $this->artisan('bancada:senha', [
            '--email' => 'dono@farmacia.ao',
            '--hash' => Hash::make('tentativa'),
        ])->assertFailed();

        $this->assertSame($antes, $cliente->fresh()->password, 'a senha de um cliente não pode ser tocada');
    }

    /**
     * SÓ HASH, NUNCA TEXTO.
     *
     * Em produção os argumentos viajam na query e ficam nos registos de acesso
     * do servidor. Uma senha em texto num registo é uma senha perdida.
     *
     * @test
     */
    public function recusa_uma_senha_em_texto(): void
    {
        $empresa = $this->bancada();
        $gerente = $this->pessoa($empresa, 'gerente@ensaio.soserp.vip');
        $antes = $gerente->password;

        $this->artisan('bancada:senha', [
            '--email' => 'gerente@ensaio.soserp.vip',
            '--hash' => 'uma-senha-qualquer-em-texto',
        ])->assertFailed();

        $this->assertSame($antes, $gerente->fresh()->password);
    }

    /** @test */
    public function sem_hash_apenas_relata_e_nao_toca_em_nada(): void
    {
        $empresa = $this->bancada();
        $gerente = $this->pessoa($empresa, 'gerente@ensaio.soserp.vip');
        $antes = $gerente->password;

        $this->artisan('bancada:senha')
            ->expectsOutputToContain('gerente@ensaio.soserp.vip')
            ->assertSuccessful();

        $this->assertSame($antes, $gerente->fresh()->password);
    }

    /** @test */
    public function sem_bancada_montada_nao_faz_nada(): void
    {
        // A transacção desfaz isto no fim do ensaio.
        Tenant::where('slug', 'bancada-de-ensaio')->delete();

        $this->artisan('bancada:senha')->assertFailed();
    }
}
