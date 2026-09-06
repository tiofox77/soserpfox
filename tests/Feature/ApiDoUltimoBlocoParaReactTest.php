<?php

namespace Tests\Feature;

use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\QuoteTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * O ÚLTIMO BLOCO DA FACTURAÇÃO EM REACT: os turnos do POS, a cópia
 * offline, o PIN de turno e os modelos de proposta.
 *
 * O que estes ensaios guardam: o turno abre uma vez e fecha com o dinheiro
 * contado; o histórico prende quem não pode ver todos aos seus; uma cópia
 * que não é JSON é recusada com a razão; o PIN exige a password e recusa o
 * óbvio; e o editor de modelos grava dentro da folha.
 */
class ApiDoUltimoBlocoParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    /* ─── Turnos ──────────────────────────────────────────────────────── */

    /** @test */
    public function o_turno_abre_uma_vez_e_fecha_com_o_dinheiro_contado(): void
    {
        $this->assertNull($this->getJson(self::RAIZ . '/turnos/estado')->assertOk()->json('turno'));

        $this->postJson(self::RAIZ . '/turnos/abrir', ['opening_balance' => -1])->assertStatus(422)->assertJsonValidationErrors('opening_balance');

        $aberto = $this->postJson(self::RAIZ . '/turnos/abrir', ['opening_balance' => 5000, 'opening_notes' => 'Manhã'])->assertCreated();
        $this->assertSame('open', $aberto->json('turno.status'));
        $this->assertEqualsWithDelta(5000, $aberto->json('turno.expected_cash'), 0.001);

        $this->postJson(self::RAIZ . '/turnos/abrir', ['opening_balance' => 10])->assertStatus(422);
        $this->assertNotNull($this->getJson(self::RAIZ . '/turnos/estado')->json('turno.shift_number'));

        // Fechar sem contar o dinheiro não passa; contado a menos dá diferença negativa.
        $this->postJson(self::RAIZ . '/turnos/fechar', [])->assertStatus(422)->assertJsonValidationErrors('actual_cash');
        $fechado = $this->postJson(self::RAIZ . '/turnos/fechar', ['actual_cash' => 4800, 'difference_reason' => 'troco'])->assertOk();

        $this->assertSame('closed', $fechado->json('turno.status'));
        $this->assertEqualsWithDelta(-200, $fechado->json('turno.cash_difference'), 0.001);
        $this->assertNull($this->getJson(self::RAIZ . '/turnos/estado')->json('turno'));
        $this->assertStringContainsString('/pos/export/shift/', $fechado->json('turno.exportar.pdf'));

        $this->postJson(self::RAIZ . '/turnos/fechar', ['actual_cash' => 1])->assertStatus(422);
    }

    /** @test */
    public function o_historico_prende_quem_nao_pode_ver_todos_aos_seus(): void
    {
        $colega = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $alheio = PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $colega->id, 'status' => 'open', 'opened_at' => now(), 'opening_balance' => 1,
        ], $this->tenant->id);
        $meu = $this->postJson(self::RAIZ . '/turnos/abrir', ['opening_balance' => 1])->assertCreated()->json('turno.id');

        $lista = $this->getJson(self::RAIZ . '/turnos/historico')->assertOk();
        $this->assertFalse($lista->json('pode_ver_todos'));
        $this->assertSame([$meu], array_column($lista->json('data'), 'id'));
        $this->assertSame([], $lista->json('utilizadores'));
        $this->getJson(self::RAIZ . '/turnos/' . $alheio->id)->assertNotFound();

        // Nem pelo filtro: escolher outro nome era dar a volta à permissão.
        $this->assertSame([$meu], array_column($this->getJson(self::RAIZ . '/turnos/historico?userId=' . $colega->id)->json('data'), 'id'));

        $this->comPermissoes('invoicing.pos.reports.all');
        $ids = array_column($this->getJson(self::RAIZ . '/turnos/historico')->assertOk()->json('data'), 'id');
        $this->assertContains($alheio->id, $ids);
        $this->getJson(self::RAIZ . '/turnos/' . $alheio->id)->assertOk()->assertJsonPath('turno.id', $alheio->id);
    }

    /* ─── Cópia offline ───────────────────────────────────────────────── */

    /** @test */
    public function uma_copia_que_nao_e_json_e_recusada_com_a_razao(): void
    {
        $this->postJson(self::RAIZ . '/copia-offline/analisar')->assertForbidden();

        $this->comPermissoes('invoicing.pos.sell');

        $this->post(self::RAIZ . '/copia-offline/analisar', [], ['Accept' => 'application/json'])->assertStatus(422);

        $r = $this->post(self::RAIZ . '/copia-offline/analisar', ['ficheiro' => UploadedFile::fake()->createWithContent('copia.json', 'isto não é json')], ['Accept' => 'application/json']);
        $r->assertStatus(422);
        $this->assertStringContainsString('JSON válido', $r->json('message'));

        $outra = UploadedFile::fake()->createWithContent('copia.json', json_encode(['formato' => 'x', 'versao' => 1, 'tenant_id' => 999999, 'dados' => ['sync_queue' => []]]));
        $this->post(self::RAIZ . '/copia-offline/importar', ['ficheiro' => $outra], ['Accept' => 'application/json'])->assertStatus(422);
    }

    /* ─── PIN ─────────────────────────────────────────────────────────── */

    /** @test */
    public function o_pin_exige_a_password_e_recusa_o_obvio(): void
    {
        $this->user->forceFill(['password' => Hash::make('segredo-123')])->save();

        $this->getJson(self::RAIZ . '/pin')->assertOk()->assertJsonPath('ja_tem_pin', false);

        $this->postJson(self::RAIZ . '/pin', ['pin' => '4827', 'pin_confirmation' => '4827', 'password' => 'errada'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson(self::RAIZ . '/pin', ['pin' => '1234', 'pin_confirmation' => '1234', 'password' => 'segredo-123'])
            ->assertStatus(422)->assertJsonValidationErrors('pin');

        $this->postJson(self::RAIZ . '/pin', ['pin' => '4827', 'pin_confirmation' => '4828', 'password' => 'segredo-123'])
            ->assertStatus(422)->assertJsonValidationErrors('pin');

        $this->postJson(self::RAIZ . '/pin', ['pin' => '4827', 'pin_confirmation' => '4827', 'password' => 'segredo-123'])
            ->assertOk()->assertJsonPath('ja_tem_pin', true);

        $this->assertTrue($this->user->fresh()->temPinPos());
    }

    /* ─── Modelos de proposta ─────────────────────────────────────────── */

    /** @test */
    public function os_modelos_criam_se_duplicam_se_e_o_primeiro_e_o_padrao(): void
    {
        $this->getJson(self::RAIZ . '/modelos-de-proposta')->assertForbidden();

        $this->comPermissoes('invoicing.sales.quotes.view', 'invoicing.sales.quotes.create', 'invoicing.sales.quotes.edit', 'invoicing.sales.quotes.delete');

        $this->assertCount(4, $this->getJson(self::RAIZ . '/modelos-de-proposta/opcoes')->assertOk()->json('arranque'));

        $vazio = $this->postJson(self::RAIZ . '/modelos-de-proposta', [])->assertCreated();
        $this->assertTrue($vazio->json('data.is_default'), 'o primeiro modelo da empresa é o padrão');
        $this->assertSame(3, $vazio->json('data.blocos_n'), 'nem o vazio nasce vazio');

        $simples = $this->postJson(self::RAIZ . '/modelos-de-proposta', ['arranque' => 'simples'])->assertCreated();
        $this->assertFalse($simples->json('data.is_default'));
        $this->postJson(self::RAIZ . '/modelos-de-proposta', ['arranque' => 'nao-existe'])->assertStatus(422);

        $copia = $this->postJson(self::RAIZ . '/modelos-de-proposta/' . $simples->json('data.id') . '/duplicar')->assertCreated();
        $this->assertStringEndsWith('(cópia)', $copia->json('data.nome'));

        $this->postJson(self::RAIZ . '/modelos-de-proposta/' . $copia->json('data.id') . '/padrao')->assertOk();
        $this->assertTrue(QuoteTemplate::find($copia->json('data.id'))->is_default);
        $this->assertFalse(QuoteTemplate::find($vazio->json('data.id'))->is_default, 'só há um padrão');

        $this->deleteJson(self::RAIZ . '/modelos-de-proposta/' . $vazio->json('data.id'))->assertOk();
        $this->assertNull(QuoteTemplate::find($vazio->json('data.id')));
        $this->assertNotNull(QuoteTemplate::withTrashed()->find($vazio->json('data.id')), 'soft delete: os orçamentos apontam para aqui');

        $this->assertSame(2, $this->getJson(self::RAIZ . '/modelos-de-proposta')->assertOk()->json('meta.total'));
    }

    /** @test */
    public function o_editor_grava_dentro_da_folha(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view', 'invoicing.sales.quotes.create', 'invoicing.sales.quotes.edit');
        $id = $this->postJson(self::RAIZ . '/modelos-de-proposta', ['arranque' => 'simples'])->assertCreated()->json('data.id');

        $editor = $this->getJson(self::RAIZ . '/modelos-de-proposta/' . $id . '/editor')->assertOk();
        $antes = count($editor->json('estado.blocos'));
        $this->assertGreaterThan(0, $antes);
        $this->assertStringContainsString('<', $editor->json('previa'), 'a pré-visualização é a folha renderizada');
        $this->assertNotEmpty($editor->json('catalogo'));

        $r = $this->postJson(self::RAIZ . '/modelos-de-proposta/' . $id . '/editor', ['accao' => 'adicionar', 'tipo' => 'titulo'])->assertOk();
        $this->assertCount($antes + 1, $r->json('estado.blocos'));
        $novo = $r->json('estado.seleccionado');
        $this->assertNotNull($novo);

        $r = $this->postJson(self::RAIZ . '/modelos-de-proposta/' . $id . '/editor', ['accao' => 'campo', 'id' => $novo, 'campo' => 'texto', 'valor' => 'Âmbito'])->assertOk();
        $this->assertSame('Âmbito', collect($r->json('estado.blocos'))->firstWhere('id', $novo)['texto']);

        // Fora da folha A4 não se grava: os limites são do servidor.
        $r = $this->postJson(self::RAIZ . '/modelos-de-proposta/' . $id . '/editor', ['accao' => 'layout', 'id' => $novo, 'layout' => ['pagina' => 1, 'x' => 9999, 'y' => -5, 'largura' => 300, 'altura' => 100]])->assertOk();
        $layout = collect($r->json('estado.blocos'))->firstWhere('id', $novo)['layout'];
        $this->assertSame(794 - 300, $layout['x']);
        $this->assertSame(0, $layout['y']);

        $this->assertCount($antes + 1, QuoteTemplate::find($id)->blocos, 'gravou');

        $r = $this->postJson(self::RAIZ . '/modelos-de-proposta/' . $id . '/editor', ['accao' => 'remover', 'id' => $novo])->assertOk();
        $this->assertCount($antes, $r->json('estado.blocos'));
    }

    /** Sem a permissão de editar, nem ler o editor. @test */
    public function o_editor_exige_a_permissao_de_editar(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view', 'invoicing.sales.quotes.create');
        $id = $this->postJson(self::RAIZ . '/modelos-de-proposta', ['arranque' => 'simples'])->assertCreated()->json('data.id');

        $this->getJson(self::RAIZ . '/modelos-de-proposta/' . $id . '/editor')->assertForbidden();
        $this->postJson(self::RAIZ . '/modelos-de-proposta/' . $id . '/editor', ['accao' => 'pagina'])->assertForbidden();
    }
}
