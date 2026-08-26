<?php

namespace Tests\Feature\Invoicing;

use App\Livewire\Invoicing\Propostas\EditorDeModelo;
use App\Livewire\Invoicing\Propostas\ModelosDeProposta;
use App\Models\Invoicing\QuoteTemplate;
use App\Models\Invoicing\SalesQuote;
use App\Models\Invoicing\SalesQuoteItem;
use App\Models\Tenant;
use App\Services\Invoicing\Propostas\ModelosDeArranque;
use App\Services\Invoicing\Propostas\RenderizadorDeProposta;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TenantTestCase;

/**
 * Modelos de proposta: o desenho do orçamento, separado dos seus números.
 */
class ModelosDePropostaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view', 'create', 'edit', 'delete'] as $acao) {
            $this->user->givePermissionTo(
                Permission::findOrCreate("invoicing.sales.quotes.{$acao}", 'web')
            );
        }
    }

    /**
     * As rotas vivem atrás de `tenant.module:invoicing`. Um teste que só chama
     * Livewire::test() salta o middleware e nunca dá por isso — mas quem abre
     * o ecrã no browser leva 403 se o módulo não estiver ligado.
     */
    private function ligarModuloDeFacturacao(): void
    {
        $modulo = \App\Models\Module::firstOrCreate(
            ['slug' => 'invoicing'],
            ['name' => 'Faturação', 'is_active' => true]
        );

        $this->tenant->modules()->syncWithoutDetaching([
            $modulo->id => ['is_active' => true, 'trial_ends_at' => null],
        ]);
    }

    private function modelo(array $blocos = null, array $estilos = []): QuoteTemplate
    {
        return QuoteTemplate::create([
            'tenant_id' => $this->tenant->id,
            'nome'      => 'Modelo de Teste',
            'blocos'    => $blocos ?? [
                ['id' => 'a1', 'tipo' => 'titulo', 'texto' => 'Âmbito', 'numerar' => true],
                ['id' => 'a2', 'tipo' => 'itens', 'titulo' => 'Investimento',
                 'mostrar_descricao' => true, 'mostrar_desconto' => true, 'mostrar_imposto' => true],
                ['id' => 'a3', 'tipo' => 'totais'],
            ],
            'estilos' => array_merge(QuoteTemplate::ESTILOS_PADRAO, $estilos),
        ]);
    }

    // ── Renderizador ─────────────────────────────────────────────────────

    public function test_render_substitui_as_variaveis(): void
    {
        $modelo = $this->modelo([
            ['id' => 'x', 'tipo' => 'texto', 'html' => '<p>Para {{cliente.nome}} de {{empresa.nome}}.</p>'],
        ]);

        $html = app(RenderizadorDeProposta::class)->renderExemplo($modelo, $this->tenant);

        $this->assertStringContainsString($this->tenant->name, $html);
        $this->assertStringNotContainsString('{{empresa.nome}}', $html);
    }

    /**
     * Uma variável mal escrita não pode chegar ao PDF do cliente: "{{cliete.nome}}"
     * impresso numa proposta é pior do que um traço.
     */
    public function test_variavel_desconhecida_nao_vai_para_o_papel(): void
    {
        $modelo = $this->modelo([
            ['id' => 'x', 'tipo' => 'texto', 'html' => '<p>Olá {{cliete.nome}}.</p>'],
        ]);

        $html = app(RenderizadorDeProposta::class)->renderExemplo($modelo, $this->tenant);

        $this->assertStringNotContainsString('cliete', $html);
        $this->assertStringContainsString('—', $html);
    }

    /** O HTML tem de ser digerível pelo dompdf: nada de flexbox nem grid. */
    public function test_html_nao_usa_css_que_o_dompdf_ignora(): void
    {
        $html = app(RenderizadorDeProposta::class)->renderExemplo(
            ModelosDeArranque::criarParaEmpresa('informatica', $this->tenant->id),
            $this->tenant
        );

        $this->assertStringNotContainsString('display:flex', str_replace(' ', '', $html));
        $this->assertStringNotContainsString('display:grid', str_replace(' ', '', $html));
    }

    public function test_render_com_orcamento_usa_os_numeros_reais(): void
    {
        $modelo = $this->modelo();

        $orcamento = SalesQuote::create([
            'tenant_id' => $this->tenant->id,
            'quote_number' => 'ORC 2026/0042',
            'client_id' => $this->cliente->id,
            'quote_date' => now(),
            'valid_until' => now()->addDays(30),
            'status' => 'draft', 'created_by' => $this->user->id,
            'subtotal' => 100000, 'tax_amount' => 14000, 'total' => 114000,
        ]);

        $artigo = \App\Models\Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SRV-' . uniqid(), 'name' => 'Auditoria de segurança',
            'price' => 100000, 'is_active' => true,
        ]);

        SalesQuoteItem::create([
            'sales_quote_id' => $orcamento->id,
            'product_id' => $artigo->id,
            'product_name' => 'Auditoria de segurança',
            'quantity' => 1, 'unit_price' => 100000, 'tax_rate' => 14, 'total' => 100000,
        ]);

        $html = app(RenderizadorDeProposta::class)->render($modelo, $orcamento->fresh('items'), $this->tenant);

        $this->assertStringContainsString('Auditoria de segurança', $html);
        $this->assertStringContainsString('114.000,00', $html);
        // E não os números de exemplo.
        $this->assertStringNotContainsString('Levantamento de requisitos', $html);
    }

    /** O que o utilizador escreveu no orçamento entra no campo livre. */
    public function test_campo_livre_sai_preenchido_com_o_texto_do_orcamento(): void
    {
        $modelo = $this->modelo([
            ['id' => 'cl', 'tipo' => 'campo_livre', 'chave' => 'ambito',
             'rotulo' => 'Âmbito', 'titulo' => 'Âmbito do trabalho', 'linhas' => 5],
        ]);

        $orcamento = SalesQuote::create([
            'tenant_id' => $this->tenant->id,
            'quote_number' => 'ORC 1', 'client_id' => $this->cliente->id,
            'quote_date' => now(), 'status' => 'draft', 'created_by' => $this->user->id,
            'subtotal' => 0, 'total' => 0,
            'campos_proposta' => ['ambito' => 'Migrar 3 servidores para a nuvem.'],
        ]);

        $html = app(RenderizadorDeProposta::class)->render($modelo, $orcamento, $this->tenant);

        $this->assertStringContainsString('Migrar 3 servidores', $html);
        $this->assertStringNotContainsString('por preencher', $html);
    }

    /** Por preencher tem de se VER — não pode passar despercebido para o PDF. */
    public function test_campo_livre_vazio_avisa_em_vez_de_desaparecer(): void
    {
        $modelo = $this->modelo([
            ['id' => 'cl', 'tipo' => 'campo_livre', 'chave' => 'ambito', 'rotulo' => 'Âmbito', 'titulo' => 'Âmbito'],
        ]);

        $html = app(RenderizadorDeProposta::class)->renderExemplo($modelo, $this->tenant);

        $this->assertStringContainsString('por preencher', $html);
    }

    /** Condições escritas no orçamento mandam sobre o texto do modelo. */
    public function test_condicoes_do_orcamento_mandam_sobre_o_modelo(): void
    {
        $modelo = $this->modelo([
            ['id' => 'c', 'tipo' => 'condicoes', 'titulo' => 'Condições', 'html' => '<p>Texto do modelo.</p>'],
        ]);

        $orcamento = SalesQuote::create([
            'tenant_id' => $this->tenant->id,
            'quote_number' => 'ORC 2', 'client_id' => $this->cliente->id,
            'quote_date' => now(), 'status' => 'draft', 'created_by' => $this->user->id,
            'subtotal' => 0, 'total' => 0,
            'terms' => 'Pagamento a 60 dias, negociado.',
        ]);

        $html = app(RenderizadorDeProposta::class)->render($modelo, $orcamento, $this->tenant);

        $this->assertStringContainsString('60 dias', $html);
        $this->assertStringNotContainsString('Texto do modelo', $html);
    }

    // ── Modelos de arranque ──────────────────────────────────────────────

    public function test_cada_modelo_de_arranque_gera_html_valido(): void
    {
        foreach (array_keys(ModelosDeArranque::catalogo()) as $chave) {
            $modelo = ModelosDeArranque::criarParaEmpresa($chave, $this->tenant->id, $this->user->id);
            $html = app(RenderizadorDeProposta::class)->renderExemplo($modelo, $this->tenant);

            $this->assertStringContainsString('<body>', $html, "Modelo {$chave}");
            $this->assertGreaterThan(1000, strlen($html), "Modelo {$chave} saiu vazio");
            // Blocos sem id não podem ser movidos nem apagados no editor.
            foreach ($modelo->blocos as $b) {
                $this->assertNotEmpty($b['id'] ?? null, "Bloco sem id em {$chave}");
            }
        }
    }

    public function test_o_primeiro_modelo_da_empresa_fica_padrao(): void
    {
        $primeiro = ModelosDeArranque::criarParaEmpresa('simples', $this->tenant->id);
        $this->assertTrue($primeiro->fresh()->is_default);

        $segundo = ModelosDeArranque::criarParaEmpresa('media', $this->tenant->id);
        $this->assertFalse($segundo->fresh()->is_default);
    }

    /** Só pode haver um padrão: dois deixariam o PDF a escolher à sorte. */
    public function test_so_ha_um_modelo_padrao_por_empresa(): void
    {
        $a = $this->modelo();
        $b = $this->modelo();

        $a->tornarPadrao();
        $b->tornarPadrao();

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
        $this->assertSame(1, QuoteTemplate::where('tenant_id', $this->tenant->id)->where('is_default', true)->count());
    }

    // ── Editor ───────────────────────────────────────────────────────────

    public function test_editor_acrescenta_remove_e_reordena(): void
    {
        $modelo = $this->modelo();

        $c = Livewire::test(EditorDeModelo::class, ['id' => $modelo->id])
            ->call('adicionarBloco', 'texto');

        $blocos = $c->get('blocos');
        $this->assertCount(4, $blocos);
        // Entra a seguir ao seleccionado (o primeiro), não no fim.
        $this->assertSame('texto', $blocos[1]['tipo']);

        $ids = array_column($blocos, 'id');
        $c->call('reordenar', array_reverse($ids));
        $this->assertSame(array_reverse($ids), array_column($c->get('blocos'), 'id'));

        $c->call('removerBloco', $ids[0]);
        $this->assertCount(3, $c->get('blocos'));
    }

    /** Duplicar um campo livre tem de lhe dar chave própria. */
    public function test_duplicar_campo_livre_nao_partilha_a_chave(): void
    {
        $modelo = $this->modelo([
            ['id' => 'cl', 'tipo' => 'campo_livre', 'chave' => 'ambito', 'rotulo' => 'Âmbito', 'titulo' => 'Âmbito'],
        ]);

        $c = Livewire::test(EditorDeModelo::class, ['id' => $modelo->id])
            ->call('duplicarBloco', 'cl');

        $chaves = array_column($c->get('blocos'), 'chave');
        $this->assertSame(['ambito', 'ambito_2'], $chaves);
    }

    public function test_editor_guarda_no_modelo(): void
    {
        $modelo = $this->modelo();

        Livewire::test(EditorDeModelo::class, ['id' => $modelo->id])
            ->set('nome', 'Proposta de Software')
            ->call('actualizarEstilo', 'cor_principal', '#db2777')
            ->call('guardar');

        $modelo->refresh();
        $this->assertSame('Proposta de Software', $modelo->nome);
        $this->assertSame('#db2777', $modelo->estilo('cor_principal'));
    }

    /** A chave de um campo livre não pode aceitar o que parta ao ler de volta. */
    public function test_chave_de_campo_livre_e_limpa(): void
    {
        $modelo = $this->modelo([
            ['id' => 'cl', 'tipo' => 'campo_livre', 'chave' => 'ambito', 'rotulo' => 'X', 'titulo' => 'X'],
        ]);

        $c = Livewire::test(EditorDeModelo::class, ['id' => $modelo->id])
            ->call('seleccionar', 'cl')
            ->call('actualizarCampo', 'chave', 'Âmbito do Trabalho!!');

        $this->assertSame('ambito_do_trabalho', $c->get('blocos')[0]['chave']);
    }

    /**
     * O ecrã do editor tem de abrir por HTTP, não só em Livewire::test().
     *
     * O QUE ISTO APANHA: um `<style>` (ou qualquer coisa) fora do elemento de
     * raiz faz o Livewire rebentar com "Multiple root elements detected" — e o
     * service worker do PWA responde com a página de "Sem ligação à internet",
     * que faz parecer falha de rede e esconde o erro por completo. O
     * Livewire::test() não apanhava isto; só o pedido inteiro apanha.
     */
    public function test_o_ecra_do_editor_abre_por_http(): void
    {
        $this->ligarModuloDeFacturacao();
        $modelo = $this->modelo();

        $resposta = $this->get(route('invoicing.sales.quote-templates.edit', $modelo->id));

        $resposta->assertOk();
        $resposta->assertDontSee('Multiple root elements', false);
    }

    public function test_a_lista_de_modelos_abre_por_http(): void
    {
        $this->ligarModuloDeFacturacao();
        $this->modelo();

        $this->get(route('invoicing.sales.quote-templates'))->assertOk();
    }

    public function test_editor_recusa_modelo_de_outra_empresa(): void
    {
        $outra = Tenant::create([
            'name' => 'Vizinha', 'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = QuoteTemplate::withoutEvents(fn () => QuoteTemplate::create([
            'tenant_id' => $outra->id, 'nome' => 'Alheio',
            'blocos' => [], 'estilos' => QuoteTemplate::ESTILOS_PADRAO,
        ]));

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(EditorDeModelo::class, ['id' => $alheio->id]);
    }

    // ── Lista ────────────────────────────────────────────────────────────

    public function test_lista_cria_modelo_de_arranque(): void
    {
        Livewire::test(ModelosDeProposta::class)->call('criarDeArranque', 'informatica');

        $this->assertDatabaseHas('quote_templates', [
            'tenant_id' => $this->tenant->id, 'sector' => 'informatica',
        ]);
    }

    /** Nem "do zero" nasce sem itens: um PDF sem preços parece avariado. */
    public function test_modelo_vazio_ja_traz_itens_e_totais(): void
    {
        Livewire::test(ModelosDeProposta::class)->call('criarVazio');

        $modelo = QuoteTemplate::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $tipos = array_column($modelo->blocos, 'tipo');

        $this->assertContains('itens', $tipos);
        $this->assertContains('totais', $tipos);
    }

    public function test_nao_elimina_modelo_de_outra_empresa(): void
    {
        $outra = Tenant::create([
            'name' => 'Vizinha2', 'slug' => 'viz2-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v2' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = QuoteTemplate::withoutEvents(fn () => QuoteTemplate::create([
            'tenant_id' => $outra->id, 'nome' => 'Alheio',
            'blocos' => [], 'estilos' => QuoteTemplate::ESTILOS_PADRAO,
        ]));

        Livewire::test(ModelosDeProposta::class)->call('eliminar', $alheio->id);

        $this->assertNull($alheio->fresh()->deleted_at);
    }
}
