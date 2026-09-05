<?php

namespace Tests\Feature\Invoicing;

use App\Models\Client;
use Tests\TenantTestCase;

/**
 * O cliente criado no PWA, quando chega ao servidor.
 *
 * O DEFEITO QUE ISTO TRAVA foi apanhado a testar em produção, e era invisível:
 * nenhum erro, nenhuma fila parada, nenhum registo de falha. O operador criava
 * um cliente, o aparelho dizia que estava feito, e o cliente não existia.
 *
 * A desduplicação por NIF tratava o `999999999` como identidade. Mas esse é o
 * NIF de CONSUMIDOR FINAL: é o que fica em toda a venda de balcão a quem não
 * dá contribuinte, e é o que o POS envia por omissão. Então:
 *
 *   1. o operador cria "Maria da esquina", sem NIF;
 *   2. o PWA envia 999999999, porque é o valor por omissão;
 *   3. o servidor encontra o Consumidor Final com esse NIF e devolve-o;
 *   4. a Maria nunca é criada — e no aparelho fica o Consumidor Final no
 *      lugar dela.
 *
 * Todo o cliente de balcão sem contribuinte era engolido em silêncio.
 */
class ClienteDoPwaTest extends TenantTestCase
{
    private function criar(array $dados)
    {
        return $this->postJson('/api/v1/invoicing/clients', $dados);
    }

    /**
     * @test
     */
    public function um_cliente_sem_contribuinte_e_criado_e_nao_confundido_com_o_consumidor_final(): void
    {
        $this->comModulo('invoicing');

        $consumidorFinal = Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Consumidor Final',
            'nif'       => '999999999',
            'is_active' => true,
        ]);

        $resposta = $this->criar([
            'name'       => 'Maria da Esquina',
            'nif'        => '999999999',
            'local_uuid' => 'c_maria_' . uniqid(),
        ]);

        $resposta->assertSuccessful();

        // NÃO pode voltar o Consumidor Final.
        $this->assertNotSame(
            $consumidorFinal->id,
            $resposta->json('id'),
            'O servidor devolveu o Consumidor Final em vez de criar o cliente novo.'
        );

        $this->assertSame('Maria da Esquina', $resposta->json('name'));

        $this->assertDatabaseHas('invoicing_clients', [
            'tenant_id' => $this->tenant->id,
            'name'      => 'Maria da Esquina',
        ]);
    }

    /** @test */
    public function dois_clientes_de_balcao_seguidos_sao_dois_clientes(): void
    {
        $this->comModulo('invoicing');

        $primeiro = $this->criar(['name' => 'João', 'nif' => '999999999', 'local_uuid' => 'c_joao_' . uniqid()]);
        $segundo = $this->criar(['name' => 'Ana', 'nif' => '999999999', 'local_uuid' => 'c_ana_' . uniqid()]);

        $primeiro->assertSuccessful();
        $segundo->assertSuccessful();

        // Com o NIF genérico a desduplicar, o segundo devolvia o primeiro — e
        // uma loja com vinte clientes de balcão acabava com um só.
        $this->assertNotSame($primeiro->json('id'), $segundo->json('id'));
    }

    /**
     * A idempotência A SÉRIO continua a funcionar: um NIF real desduplica.
     *
     * É o que impede o mesmo cliente de nascer duas vezes quando a resposta se
     * perde e o aparelho reenvia.
     *
     * @test
     */
    public function um_nif_real_continua_a_desduplicar(): void
    {
        $this->comModulo('invoicing');

        $existente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Empresa Real, Lda',
            'nif'       => '5417289442',
            'is_active' => true,
        ]);

        $resposta = $this->criar([
            'name'       => 'Empresa Real (outra vez)',
            'nif'        => '5417289442',
            'local_uuid' => 'c_repetido_' . uniqid(),
        ]);

        $resposta->assertSuccessful();
        $this->assertSame($existente->id, $resposta->json('id'));
        $this->assertTrue($resposta->json('duplicated'));
    }

    /**
     * E o `local_uuid` continua a ser a primeira defesa: o mesmo pedido
     * repetido devolve o mesmo cliente, sem criar um segundo.
     *
     * @test
     */
    public function o_mesmo_pedido_repetido_nao_cria_dois(): void
    {
        $this->comModulo('invoicing');

        $uuid = 'c_retry_' . uniqid();

        $primeira = $this->criar(['name' => 'Cliente de Balcão', 'nif' => '999999999', 'local_uuid' => $uuid]);
        $segunda = $this->criar(['name' => 'Cliente de Balcão', 'nif' => '999999999', 'local_uuid' => $uuid]);

        $primeira->assertSuccessful();
        $segunda->assertSuccessful();

        $this->assertSame($primeira->json('id'), $segunda->json('id'));
        $this->assertSame(1, Client::where('tenant_id', $this->tenant->id)->where('name', 'Cliente de Balcão')->count());
    }
}
