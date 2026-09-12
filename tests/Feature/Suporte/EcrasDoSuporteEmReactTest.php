<?php

namespace Tests\Feature\Suporte;

use App\Models\Support\FeatureRequest;
use App\Models\Support\FeatureRequestVote;
use App\Models\Support\Ticket;
use App\Models\Support\TicketMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * O SUPORTE EM REACT: os pedidos de ajuda e o quadro de melhorias.
 *
 * O QUE A MIGRAÇÃO DESTAPOU
 *
 *  · O NÚMERO DO PEDIDO vinha de `Ticket::count() + 1` sobre a tabela INTEIRA.
 *    Duas empresas abriam o mesmo `TKT-000007`, e dois pedidos abertos ao mesmo
 *    tempo na mesma empresa levavam o mesmo número — entre contar e gravar não
 *    havia tranca nenhuma.
 *
 *  · O VOTO estava aberto à rua: a sugestão era procurada por id e mais nada.
 *    Bastava escrever o número de uma sugestão de outra empresa para lhe mexer
 *    na contagem.
 *
 *  · E o TOTAL DE VOTOS era somado e subtraído à mão, ao lado da linha do voto:
 *    dois cliques seguidos gravavam dois votos e a coluna deixava de
 *    corresponder às linhas.
 *
 *  · O estado «à espera de si» existia e não havia onde responder — o botão
 *    «Ver Detalhes» do ecrã antigo não fazia nada.
 */
class EcrasDoSuporteEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/suporte';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function pedido(array $extra = []): Ticket
    {
        return Ticket::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'ticket_number' => 'TKT-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'subject' => 'O POS não imprime',
            'description' => 'Carrego em imprimir e não sai nada.',
            'priority' => 'high',
            'category' => 'technical',
            'status' => 'open',
        ], $extra));
    }

    private function sugestao(array $extra = []): FeatureRequest
    {
        return FeatureRequest::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'title' => 'Exportar o mapa de vendas para Excel',
            'description' => 'Todos os meses copio os números à mão para uma folha de cálculo.',
            'status' => 'pending',
            'votes_count' => 0,
        ], $extra));
    }

    /* ─── As portas ───────────────────────────────────────────────────── */

    /** @test */
    public function os_dois_ecras_abrem_e_montam_o_react(): void
    {
        foreach ([
            'support.tickets' => 'suporte/pedidos',
            'support.features' => 'suporte/melhorias',
        ] as $rota => $ecra) {
            $this->get(route($rota))->assertOk()->assertSee($ecra, false);
        }
    }

    /** Pedir ajuda é auto-serviço: não pede permissão nenhuma. */
    public function test_pedir_ajuda_nao_pede_permissao(): void
    {
        $this->actingAs($this->user)->getJson(self::RAIZ.'/pedidos')->assertOk();
        $this->actingAs($this->user)->getJson(self::RAIZ.'/sugestoes')->assertOk();
    }

    /* ─── Os pedidos ──────────────────────────────────────────────────── */

    /**
     * UM PEDIDO É DE QUEM O ABRIU.
     *
     * A descrição de um problema leva lá dentro números, nomes e capturas de
     * ecrã da casa de quem escreve.
     */
    public function test_a_lista_e_a_de_cada_um(): void
    {
        $meu = $this->pedido(['subject' => 'O meu problema']);

        $colega = User::create([
            'name' => 'Colega', 'email' => 'colega@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $dele = $this->pedido(['user_id' => $colega->id, 'subject' => 'O problema dele']);

        $assuntos = collect($this->actingAs($this->user)->getJson(self::RAIZ.'/pedidos')->assertOk()->json('data'))
            ->pluck('assunto')->all();

        $this->assertContains('O meu problema', $assuntos);
        $this->assertNotContains('O problema dele', $assuntos);

        $this->actingAs($this->user)->getJson(self::RAIZ.'/pedidos/'.$dele->id)->assertNotFound();
        $this->assertNotNull(Ticket::find($meu->id));
    }

    /**
     * O NÚMERO É POR EMPRESA E NÃO SE REPETE.
     *
     * Vinha de uma contagem sobre a tabela inteira: a segunda empresa a abrir
     * um pedido herdava o número da primeira.
     */
    public function test_o_numero_do_pedido_e_por_empresa(): void
    {
        $outra = Tenant::create(['name' => 'Outra Casa', 'email' => 'outra@casa.ao', 'is_active' => true]);

        Ticket::create([
            'tenant_id' => $outra->id, 'user_id' => $this->user->id,
            'ticket_number' => 'TKT-000009', 'subject' => 'De outra casa',
            'description' => 'Nada que nos diga respeito.', 'priority' => 'low',
            'category' => 'other', 'status' => 'open',
        ]);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos', [
            'subject' => 'O primeiro daqui',
            'description' => 'Uma descrição com tamanho suficiente.',
            'priority' => 'medium', 'category' => 'technical',
        ])->assertCreated();

        $meu = Ticket::where('tenant_id', $this->tenant->id)->latest('id')->first();

        $this->assertSame('TKT-000001', $meu->ticket_number, 'a contagem é desta empresa, não da tabela inteira');
    }

    /** E dois pedidos seguidos não levam o mesmo número. */
    public function test_dois_pedidos_seguidos_levam_numeros_diferentes(): void
    {
        foreach (['Primeiro pedido', 'Segundo pedido'] as $assunto) {
            $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos', [
                'subject' => $assunto,
                'description' => 'Uma descrição com tamanho suficiente.',
                'priority' => 'medium', 'category' => 'technical',
            ])->assertCreated();
        }

        $numeros = Ticket::where('tenant_id', $this->tenant->id)->pluck('ticket_number')->all();

        $this->assertCount(2, $numeros);
        $this->assertCount(2, array_unique($numeros), 'dois pedidos não podem levar o mesmo número');
    }

    /** As imagens ficam gravadas — e no máximo cinco. */
    public function test_as_imagens_do_pedido_ficam_gravadas(): void
    {
        $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos', [
            'subject' => 'Com captura de ecrã',
            'description' => 'A captura explica o que a descrição não consegue.',
            'priority' => 'high', 'category' => 'bug',
            'images' => [UploadedFile::fake()->image('ecra.png'), UploadedFile::fake()->image('outro.png')],
        ])->assertCreated();

        $t = Ticket::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();

        $this->assertCount(2, $t->images);

        foreach ($t->images as $caminho) {
            Storage::disk('public')->assertExists($caminho);
        }
    }

    /** Mais do que cinco é recusado por escrito, e não cortado em silêncio. */
    public function test_mais_de_cinco_imagens_e_recusado(): void
    {
        $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos', [
            'subject' => 'Seis capturas',
            'description' => 'Uma descrição com tamanho suficiente.',
            'priority' => 'low', 'category' => 'other',
            'images' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.png"), range(1, 6)),
        ])->assertStatus(422)->assertJsonValidationErrors('images');

        $this->assertSame(0, Ticket::where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * RESPONDER AO PRÓPRIO PEDIDO.
     *
     * O estado «à espera de si» existia e não havia onde responder. Responder
     * devolve o pedido à fila do suporte — senão ficava lá parado com a
     * resposta dentro.
     */
    public function test_responder_devolve_o_pedido_a_fila(): void
    {
        $t = $this->pedido(['status' => 'waiting_response']);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos/'.$t->id.'/responder', [
            'message' => 'A impressora é uma térmica de 80 mm, ligada por USB.',
        ])->assertOk();

        $this->assertSame('in_progress', $t->fresh()->status);
        $this->assertSame(1, TicketMessage::where('ticket_id', $t->id)->count());
        $this->assertFalse((bool) TicketMessage::where('ticket_id', $t->id)->value('is_staff'));
    }

    /** Um pedido fechado não recebe respostas. */
    public function test_nao_se_responde_a_um_pedido_fechado(): void
    {
        $t = $this->pedido(['status' => 'closed']);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos/'.$t->id.'/responder', [
            'message' => 'Ainda acontece.',
        ])->assertStatus(422);

        $this->assertSame(0, TicketMessage::where('ticket_id', $t->id)->count());
    }

    /** E fecha-se o próprio pedido quando já não é preciso. */
    public function test_fechar_o_proprio_pedido(): void
    {
        $t = $this->pedido();

        $this->actingAs($this->user)->postJson(self::RAIZ.'/pedidos/'.$t->id.'/fechar', [])->assertOk();

        $this->assertSame('closed', $t->fresh()->status);
        $this->assertNotNull($t->fresh()->resolved_at);
    }

    /* ─── O quadro de melhorias ───────────────────────────────────────── */

    /** O quadro é da empresa — e só dela. */
    public function test_o_quadro_nao_atravessa_empresas(): void
    {
        $this->sugestao(['title' => 'Sugestão desta casa e com tamanho']);

        $outra = Tenant::create(['name' => 'Outra Casa', 'email' => 'outra2@casa.ao', 'is_active' => true]);
        FeatureRequest::create([
            'tenant_id' => $outra->id, 'user_id' => $this->user->id,
            'title' => 'Sugestão da outra casa e com tamanho',
            'description' => 'Uma descrição suficientemente longa para passar.',
            'status' => 'pending', 'votes_count' => 0,
        ]);

        $titulos = collect($this->actingAs($this->user)->getJson(self::RAIZ.'/sugestoes')->assertOk()->json('data'))
            ->pluck('titulo')->all();

        $this->assertContains('Sugestão desta casa e com tamanho', $titulos);
        $this->assertNotContains('Sugestão da outra casa e com tamanho', $titulos);
    }

    /**
     * O VOTO ESTAVA ABERTO À RUA.
     *
     * A sugestão era procurada por id e mais nada: bastava escrever o número de
     * uma sugestão de outra empresa para lhe mexer na contagem.
     */
    public function test_nao_se_vota_numa_sugestao_de_outra_empresa(): void
    {
        $outra = Tenant::create(['name' => 'Outra Casa', 'email' => 'outra3@casa.ao', 'is_active' => true]);
        $alheia = FeatureRequest::create([
            'tenant_id' => $outra->id, 'user_id' => $this->user->id,
            'title' => 'Sugestão da outra casa e com tamanho',
            'description' => 'Uma descrição suficientemente longa para passar.',
            'status' => 'pending', 'votes_count' => 0,
        ]);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/sugestoes/'.$alheia->id.'/votar', [])
            ->assertNotFound();

        $this->assertSame(0, (int) $alheia->fresh()->votes_count);
        $this->assertSame(0, FeatureRequestVote::where('request_id', $alheia->id)->count());
    }

    /**
     * O TOTAL VEM DAS LINHAS.
     *
     * Era somado e subtraído à mão a par da linha do voto; dois cliques
     * seguidos e a coluna deixava de corresponder ao que lá estava.
     */
    public function test_votar_e_desvotar_deixa_a_contagem_certa(): void
    {
        $s = $this->sugestao();

        $this->actingAs($this->user)->postJson(self::RAIZ.'/sugestoes/'.$s->id.'/votar', [])
            ->assertOk()->assertJson(['votos' => 1, 'votei' => true]);

        $this->assertSame(1, FeatureRequestVote::where('request_id', $s->id)->count());
        $this->assertSame(1, (int) $s->fresh()->votes_count);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/sugestoes/'.$s->id.'/votar', [])
            ->assertOk()->assertJson(['votos' => 0, 'votei' => false]);

        $this->assertSame(0, FeatureRequestVote::where('request_id', $s->id)->count());
        $this->assertSame(0, (int) $s->fresh()->votes_count);
    }

    /** E uma coluna que tenha derivado é reposta ao primeiro voto. */
    public function test_uma_contagem_derivada_endireita_se(): void
    {
        $s = $this->sugestao(['votes_count' => 47]);

        $this->actingAs($this->user)->postJson(self::RAIZ.'/sugestoes/'.$s->id.'/votar', [])
            ->assertOk()->assertJson(['votos' => 1]);

        $this->assertSame(1, (int) $s->fresh()->votes_count);
    }

    /** A lista diz se EU votei, e não se alguém votou. */
    public function test_a_lista_diz_se_eu_votei(): void
    {
        $s = $this->sugestao();

        $colega = User::create([
            'name' => 'Colega', 'email' => 'colega2@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        FeatureRequestVote::create(['request_id' => $s->id, 'user_id' => $colega->id]);
        $s->update(['votes_count' => 1]);

        $linha = $this->actingAs($this->user)->getJson(self::RAIZ.'/sugestoes')->assertOk()->json('data.0');

        $this->assertSame(1, $linha['votos']);
        $this->assertFalse($linha['votei'], 'o voto é do colega, não meu');
    }

    /** Uma sugestão votada já não é só de quem a escreveu. */
    public function test_nao_se_retira_uma_sugestao_votada(): void
    {
        $s = $this->sugestao();

        FeatureRequestVote::create(['request_id' => $s->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/sugestoes/'.$s->id)->assertStatus(422);

        $this->assertNotNull(FeatureRequest::find($s->id));
    }

    /** E nunca se retira a sugestão de outra pessoa. */
    public function test_nao_se_retira_a_sugestao_de_outra_pessoa(): void
    {
        $colega = User::create([
            'name' => 'Colega', 'email' => 'colega3@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $dela = $this->sugestao(['user_id' => $colega->id]);

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/sugestoes/'.$dela->id)->assertNotFound();

        $this->assertNotNull(FeatureRequest::find($dela->id));
    }

    /** A minha sugestão sem votos retira-se. */
    public function test_a_minha_sugestao_sem_votos_retira_se(): void
    {
        $s = $this->sugestao();

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/sugestoes/'.$s->id)->assertOk();

        $this->assertNull(FeatureRequest::find($s->id));
    }

    /** «Mais votadas» ordena pelos votos contados. */
    public function test_as_mais_votadas_vem_a_frente(): void
    {
        $pouca = $this->sugestao(['title' => 'A menos votada de todas elas']);
        $muita = $this->sugestao(['title' => 'A mais votada de todas elas']);

        $colega = User::create([
            'name' => 'Colega', 'email' => 'colega4@empresa.ao', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);

        FeatureRequestVote::create(['request_id' => $muita->id, 'user_id' => $this->user->id]);
        FeatureRequestVote::create(['request_id' => $muita->id, 'user_id' => $colega->id]);
        FeatureRequestVote::create(['request_id' => $pouca->id, 'user_id' => $this->user->id]);

        $titulos = collect(
            $this->actingAs($this->user)->getJson(self::RAIZ.'/sugestoes?ordem=populares')->assertOk()->json('data')
        )->pluck('titulo')->all();

        $this->assertSame('A mais votada de todas elas', $titulos[0]);
    }
}
