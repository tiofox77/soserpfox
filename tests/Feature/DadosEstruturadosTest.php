<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Support\DadosEstruturados;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * OS DADOS ESTRUTURADOS DAS PÁGINAS PÚBLICAS.
 *
 * O que se guarda é o que estava errado: duas SoftwareApplication na página
 * inicial com ofertas diferentes, a Softec Angola como Pessoa fundadora, um
 * FAQPage com perguntas que a página não mostrava, e as páginas dos módulos
 * sem JSON-LD nenhum.
 */
class DadosEstruturadosTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * ESTE ENSAIO SEMEIA OS SEUS PRÓPRIOS PLANOS.
     *
     * Vivia à custa do que estivesse na base: com planos públicos passava, sem
     * eles a página não tinha `offers` e o ensaio rebentava em «Undefined array
     * key». E as bases dos processos paralelos nascem SÓ COM O ESQUEMA (ver o
     * `scripts/prepare_test_db.php`) — portanto sem plano nenhum. Resultado:
     * verde em sequencial, vermelho em paralelo, sempre, e uma falha que toda
     * a gente aprendia a ignorar.
     *
     * Um ensaio traz o que precisa.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (Plan::publico()->doesntExist()) {
            Plan::create([
                'name' => 'Plano de Ensaio', 'slug' => 'ensaio-' . uniqid(),
                'description' => 'Semeado pelo ensaio dos dados estruturados.',
                'price_monthly' => 25000, 'price_yearly' => 250000,
                'trial_days' => 0, 'max_users' => 5, 'max_companies' => 1,
                'is_active' => true, 'is_public' => true, 'order' => 1,
            ]);
        }
    }

    /** Todos os nós de todos os blocos JSON-LD da página — e cada bloco tem de ser JSON válido. */
    private function nos(TestResponse $r): array
    {
        $r->assertOk();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $r->getContent(), $m);
        $this->assertNotEmpty($m[1], 'a página não tem JSON-LD');

        $nos = [];
        foreach ($m[1] as $bloco) {
            $json = json_decode($bloco, true);
            $this->assertIsArray($json, 'um bloco JSON-LD não é JSON válido: ' . json_last_error_msg());
            $this->assertSame('https://schema.org', $json['@context']);
            array_push($nos, ...($json['@graph'] ?? [$json]));
        }

        return $nos;
    }

    private function doTipo(array $nos, string $tipo): array
    {
        return array_values(array_filter($nos, fn ($n) => ($n['@type'] ?? null) === $tipo));
    }

    /** O texto à vista, sem o que vai dentro de <script>. */
    private function textoVisivel(TestResponse $r): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', '', $r->getContent());

        return html_entity_decode(strip_tags($html), ENT_QUOTES);
    }

    public function test_a_pagina_inicial_tem_um_software_uma_empresa_e_nenhuma_pessoa(): void
    {
        $nos = $this->nos($this->get('/'));

        $this->assertCount(1, $this->doTipo($nos, 'SoftwareApplication'), 'o software tem uma identidade só');
        $this->assertCount(1, $this->doTipo($nos, 'Organization'));
        $this->assertSame([], $this->doTipo($nos, 'Person'));
        $this->assertStringNotContainsString('"Person"', json_encode($nos));

        $empresa = $this->doTipo($nos, 'Organization')[0];
        $this->assertSame('Softec Angola', $empresa['name']);
        $this->assertSame('SOSERP', $empresa['brand']['name']);

        $software = $this->doTipo($nos, 'SoftwareApplication')[0];
        $this->assertSame('SOSERP', $software['name']);
        $this->assertSame(['@id' => $empresa['@id']], $software['publisher']);
    }

    /** As ofertas são os planos públicos, com os preços da tabela. */
    public function test_as_ofertas_da_pagina_inicial_sao_os_planos_publicos(): void
    {
        Plan::create([
            'name' => '🧪 Plano Escondido', 'slug' => 'escondido-' . uniqid(), 'description' => 'x',
            'price_monthly' => 777, 'price_yearly' => 0, 'trial_days' => 0, 'max_users' => 1, 'max_companies' => 1,
            'is_active' => true, 'is_public' => false, 'order' => 999,
        ]);

        $software = $this->doTipo($this->nos($this->get('/')), 'SoftwareApplication')[0];
        $publicos = Plan::publico()->orderBy('order')->get();

        $ofertas = $software['offers']['@type'] === 'AggregateOffer' ? $software['offers']['offers'] : [$software['offers']];

        $this->assertCount($publicos->count(), $ofertas);
        foreach ($publicos->values() as $i => $plano) {
            $this->assertSame(DadosEstruturados::semEmoji($plano->name), $ofertas[$i]['name']);
            $this->assertEqualsWithDelta((float) $plano->price_monthly, (float) $ofertas[$i]['price'], 0.001);
            $this->assertSame('AOA', $ofertas[$i]['priceCurrency']);
            $this->assertDoesNotMatchRegularExpression('/[\x{1F000}-\x{1FAFF}]/u', $ofertas[$i]['name']);
        }
        $this->assertStringNotContainsString('Plano Escondido', json_encode($ofertas, JSON_UNESCAPED_UNICODE));
    }

    /** Um FAQPage cujas perguntas não estão à vista vai contra as regras do Google. */
    public function test_as_perguntas_do_json_ld_estao_a_vista_na_pagina_inicial(): void
    {
        $r = $this->get('/');
        $faq = $this->doTipo($this->nos($r), 'FAQPage');

        $this->assertCount(1, $faq);
        $visivel = $this->textoVisivel($r);
        foreach ($faq[0]['mainEntity'] as $pergunta) {
            $this->assertStringContainsString($pergunta['name'], $visivel);
            $this->assertStringContainsString($pergunta['acceptedAnswer']['text'], $visivel);
        }
    }

    public static function paginasDosModulos(): array
    {
        return [
            'faturação' => ['vendas', 'pacote-vendas', 'Faturação e POS'],
            'rh' => ['rh', 'pacote-rh', 'Recursos Humanos'],
            'restaurante' => ['restaurant', 'pacote-restaurante', 'Restaurante'],
        ];
    }

    /** @dataProvider paginasDosModulos */
    public function test_cada_pagina_de_modulo_tem_o_seu_json_ld(string $slug, string $planoSlug, string $migalha): void
    {
        $r = $this->get("/modulos/{$slug}");
        $nos = $this->nos($r);
        $url = DadosEstruturados::raiz() . "/modulos/{$slug}";

        // A mesma empresa e o mesmo software da página inicial — pelo @id.
        $this->assertSame(DadosEstruturados::id('software'), $this->doTipo($nos, 'SoftwareApplication')[0]['@id']);
        $this->assertSame('Softec Angola', $this->doTipo($nos, 'Organization')[0]['name']);
        $this->assertCount(1, $this->doTipo($nos, 'SoftwareApplication'));

        $pagina = $this->doTipo($nos, 'WebPage')[0];
        $this->assertSame($url, $pagina['url']);
        $r->assertSee('<link rel="canonical" href="' . $url . '">', false);

        $migalhas = $this->doTipo($nos, 'BreadcrumbList')[0]['itemListElement'];
        $this->assertSame(['SOSERP', 'Módulos', $migalha], array_column($migalhas, 'name'));

        // A oferta é a do plano, com o preço dele — só se o plano se vender.
        $plano = Plan::publico()->where('slug', $planoSlug)->first();
        $software = $this->doTipo($nos, 'SoftwareApplication')[0];
        if ($plano) {
            $this->assertEqualsWithDelta((float) $plano->price_monthly, (float) $software['offers']['price'], 0.001);
            $this->assertSame(DadosEstruturados::semEmoji($plano->name), $software['offers']['name']);
        }

        // As perguntas estão à vista.
        $faq = $this->doTipo($nos, 'FAQPage')[0];
        $this->assertGreaterThanOrEqual(4, count($faq['mainEntity']));
        $visivel = $this->textoVisivel($r);
        foreach ($faq['mainEntity'] as $pergunta) {
            $this->assertStringContainsString($pergunta['name'], $visivel);
            $this->assertStringContainsString($pergunta['acceptedAnswer']['text'], $visivel);
        }

        // As funcionalidades da lista são os cartões da página.
        foreach ($this->doTipo($nos, 'ItemList')[0]['itemListElement'] as $f) {
            $this->assertStringContainsString($f['name'], $visivel);
        }
    }

    /** Um pacote que deixou de se vender não fica com preço na página nem no JSON-LD. */
    public function test_um_plano_escondido_nao_da_oferta_na_pagina_do_modulo(): void
    {
        Plan::where('slug', 'pacote-rh')->update(['is_public' => false]);

        $r = $this->get('/modulos/rh');
        $software = $this->doTipo($this->nos($r), 'SoftwareApplication')[0];

        $this->assertArrayNotHasKey('offers', $software);
        $r->assertDontSee('id="pricing"', false);
    }

    /** A página de RH já não promete o recibo «enviado por email com QR code», que não existe. */
    public function test_a_pagina_de_rh_nao_promete_o_que_o_sistema_nao_faz(): void
    {
        $this->get('/modulos/rh')->assertOk()->assertDontSee('QR code')->assertDontSee('turnover');
    }
}
