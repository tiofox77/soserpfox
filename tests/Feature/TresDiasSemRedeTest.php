<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * Três dias de loja sem internet, com troca de funcionários.
 *
 * É o pior caso real desta farmácia: a rede vai-se, vende-se à mesma durante
 * dias, as caixas rendem-se umas às outras, e a certa altura a rede volta.
 * O que não pode acontecer, em circunstância nenhuma, é desaparecer uma venda
 * — é dinheiro cobrado ao balcão que deixaria de existir em lado nenhum.
 *
 * O teste ataca o lado do SERVIDOR, que é onde a perda seria definitiva: o
 * aparelho ainda se pode inspeccionar, uma venda que nunca entrou não.
 */
class TresDiasSemRedeTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A API do PWA pede permissão desde 2026-09-13 (AutorizaApiDoPwa): o
        // utilizador do ensaio é um caixa a sério, não um membro sem papel.
        $this->comPermissoesDoPwa();

        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.view')
             ->comModulo('invoicing');
    }

    private function caixa(string $nome): User
    {
        $u = User::create([
            'name'      => $nome,
            'email'     => strtolower(str_replace(' ', '.', $nome)) . uniqid() . '@kienga.local',
            'password'  => bcrypt(uniqid()),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $u->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);

        setPermissionsTeamId($this->tenant->id);
        $u->syncRoles($this->user->roles);

        return $this->operadorDoPwa($u);
    }

    /** Uma venda como o aparelho a enfileira: com o seu local_uuid. */
    private function venda(string $uuid, float $preco, string $quando): array
    {
        return [
            'local_uuid'       => $uuid,
            'client_id'        => null,
            'payment_method'   => 'cash',
            'amount_received'  => $preco,
            'created_at_local' => $quando,
            'items'            => [[
                'product_id'   => $this->produtoComStock(500)->id,
                'product_name' => 'Artigo de teste',
                'quantity'     => 1,
                'unit_price'   => $preco,
                'tax_rate'     => 0,
                'is_service'   => false,
                'unit'         => 'UN',
            ]],
        ];
    }

    private function enviar(User $caixa, array $venda)
    {
        return $this->actingAs($caixa)->postJson('/api/v1/invoicing/pos/sale', $venda);
    }

    public function test_tres_dias_de_vendas_de_tres_caixas_nao_perdem_nada(): void
    {
        $judite = $this->caixa('Judite Victoriano');
        $rosa = $this->caixa('Rosa Recruta');
        $josefa = $this->caixa('Josefa Chipata');

        // A loja vendeu durante três dias. Cada caixa fez o seu turno, e o
        // aparelho guardou tudo com a data em que aconteceu — não a de agora.
        $porEnviar = [];
        $turnos = [
            [$judite, '2026-08-15'],
            [$rosa, '2026-08-15'],
            [$judite, '2026-08-16'],
            [$josefa, '2026-08-16'],
            [$rosa, '2026-08-17'],
            [$josefa, '2026-08-17'],
        ];

        foreach ($turnos as $i => [$caixa, $dia]) {
            foreach (range(1, 4) as $n) {
                $uuid = "pos_{$i}_{$n}_" . uniqid();
                $porEnviar[] = [$caixa, $this->venda($uuid, 1000 + $n * 100, "{$dia}T10:0{$n}:00")];
            }
        }

        $this->assertCount(24, $porEnviar);

        // A rede volta. A fila sobe toda.
        $numeros = [];

        foreach ($porEnviar as [$caixa, $venda]) {
            $r = $this->enviar($caixa, $venda);
            $r->assertSuccessful();
            $numeros[] = $r->json('data.invoice_number') ?? $r->json('invoice_number');
        }

        $this->assertSame(
            24,
            SalesInvoice::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count(),
            'as 24 vendas dos três dias tinham de estar todas no servidor'
        );

        // Nenhum número repetido: duas facturas com o mesmo número seriam
        // recusadas pela AGT e impossíveis de explicar ao fisco.
        $comNumero = array_filter($numeros);
        $this->assertSame(count($comNumero), count(array_unique($comNumero)));
    }

    public function test_reenviar_a_mesma_venda_nao_a_duplica(): void
    {
        $judite = $this->caixa('Judite Victoriano');
        $venda = $this->venda('pos_repetida_' . uniqid(), 2500, '2026-08-16T11:00:00');

        // Acontece: a resposta perde-se, o aparelho não risca o trabalho da
        // fila e volta a mandar. Facturar duas vezes o mesmo é pior do que
        // não facturar.
        $this->enviar($judite, $venda)->assertSuccessful();
        $this->enviar($judite, $venda)->assertSuccessful();
        $this->enviar($judite, $venda)->assertSuccessful();

        $this->assertSame(
            1,
            SalesInvoice::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count()
        );
    }

    public function test_a_venda_de_uma_caixa_nao_fica_em_nome_de_outra(): void
    {
        $judite = $this->caixa('Judite Victoriano');
        $rosa = $this->caixa('Rosa Recruta');

        $this->enviar($judite, $this->venda('pos_j_' . uniqid(), 1000, '2026-08-16T09:00:00'))->assertSuccessful();
        $this->enviar($rosa, $this->venda('pos_r_' . uniqid(), 2000, '2026-08-16T15:00:00'))->assertSuccessful();

        $docs = SalesInvoice::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $docs);

        // É para isto que cada caixa tem a sua conta: sem autoria certa, o
        // fecho de caixa não se pode conferir com ninguém.
        $this->assertSame($judite->id, (int) $docs[0]->created_by);
        $this->assertSame($rosa->id, (int) $docs[1]->created_by);
    }

    public function test_a_data_da_venda_e_a_do_balcao_e_nao_a_da_sincronizacao(): void
    {
        $judite = $this->caixa('Judite Victoriano');

        $this->enviar($judite, $this->venda('pos_data_' . uniqid(), 1500, '2026-08-15T08:30:00'))
            ->assertSuccessful();

        $doc = SalesInvoice::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        // Uma venda de sexta que chega na segunda não pode ficar datada de
        // segunda: o fecho de caixa da sexta deixava de bater certo.
        $this->assertSame('2026-08-15', $doc->invoice_date->format('Y-m-d'));
    }
}
