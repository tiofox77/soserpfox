<?php

namespace Tests\Feature;

use App\Models\Invoicing\Advance;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * A API DOS ADIANTAMENTOS, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: o adiantamento nasce numerado
 * e disponível por inteiro; um já usado não se edita; numa edição o
 * restante volta a ser o valor; e sem a permissão de ver os documentos de
 * todos só se abre o que é seu.
 */
class ApiDosAdiantamentosParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/adiantamentos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'client_id' => $this->clienteEmpresa()->id,
            'payment_date' => now()->toDateString(),
            'amount' => 25000,
            'payment_method' => 'transfer',
            'purpose' => 'Sinal',
        ], $por);
    }

    /** @test */
    public function sem_permissao_nao_ha_nada(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /** @test */
    public function nasce_numerado_e_disponivel_por_inteiro(): void
    {
        $this->comPermissoes('invoicing.advances.create');

        $r = $this->postJson(self::RAIZ, $this->corpo())->assertCreated();

        $a = Advance::find($r->json('data.id'));

        $this->assertStringStartsWith('AD/', $a->advance_number);
        $this->assertSame('available', $a->status);
        $this->assertSame('sale', $a->type);
        $this->assertEqualsWithDelta(25000, $a->remaining_amount, 0.01);
        $this->assertSame($this->user->id, $a->created_by);

        $this->postJson(self::RAIZ, $this->corpo(['amount' => 0, 'payment_method' => 'xpto']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'payment_method']);
    }

    /** @test */
    public function um_adiantamento_ja_usado_nao_se_edita(): void
    {
        $this->comPermissoes('invoicing.advances.create', 'invoicing.advances.edit');

        $id = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('data.id');

        $this->putJson(self::RAIZ . '/' . $id, $this->corpo(['amount' => 30000]))->assertOk();

        $a = Advance::find($id);
        $this->assertEqualsWithDelta(30000, $a->amount, 0.01);
        $this->assertEqualsWithDelta(30000, $a->remaining_amount, 0.01, 'o restante volta a ser o valor');

        $a->use(1000);

        $this->putJson(self::RAIZ . '/' . $id, $this->corpo(['amount' => 40000]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertFalse($this->getJson(self::RAIZ . '/' . $id)->assertOk()->json('data.pode_editar'));
    }

    /** Sem «ver os de todos», só se abre o que é seu. @test */
    public function so_se_abre_o_que_e_seu(): void
    {
        $this->comPermissoes('invoicing.advances.create', 'invoicing.advances.edit');

        $colega = User::factory()->create();
        $doColega = Advance::create([
            'tenant_id' => $this->tenant->id, 'type' => 'sale', 'client_id' => $this->clienteEmpresa()->id,
            'payment_date' => now(), 'amount' => 500, 'payment_method' => 'cash', 'status' => 'available', 'created_by' => $colega->id,
        ]);

        $this->getJson(self::RAIZ . '/' . $doColega->id)->assertNotFound();

        $this->comPermissoes('invoicing.documents.all');

        $this->getJson(self::RAIZ . '/' . $doColega->id)->assertOk();
    }
}
