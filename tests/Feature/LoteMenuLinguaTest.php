<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * O menu lateral e o painel de facturação nas três línguas.
 *
 * Este lote saltou a fila. Estava planeado para último, e foi trazido para a
 * frente porque é a primeira coisa que se vê: alguém escolheu inglês, caiu no
 * painel, e o que tinha à frente — menu inteiro, títulos, cartões — estava
 * todo em português. Do lado de fora aquilo lê-se como "a tradução não
 * funciona", mesmo com setecentas cadeias já traduzidas por trás.
 *
 * Daí os testes atacarem o menu e o painel juntos: é o par que decide se o
 * sistema PARECE traduzido.
 */
class LoteMenuLinguaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $modulo = \App\Models\Module::firstOrCreate(
            ['slug' => 'invoicing'],
            ['name' => 'Faturação', 'is_active' => true]
        );

        $this->tenant->modules()->syncWithoutDetaching([
            $modulo->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        // O painel está atrás de permission:invoicing.dashboard.view. Sem ela
        // todos estes testes davam 403 — e um 403 não tem menu nenhum, portanto
        // o assertDontSee('Fornecedores') passava por a página estar vazia, e
        // não por estar traduzida.
        // E AS ENTRADAS QUE ESTE ENSAIO LÊ ganharam a sua guarda quando as
        // rotas da facturação deixaram de correr só com `auth`: sem elas, o
        // menu vem sem «Turnos de Caixa» e o ensaio media a permissão em vez
        // da língua.
        foreach ([
            'invoicing.dashboard.view',
            'invoicing.pos.access',
            'treasury.reports.view',
        ] as $nome) {
            $this->user->givePermissionTo(
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web'])
            );
        }

        $this->user->forgetCachedPermissions();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function painel(): \Illuminate\Testing\TestResponse
    {
        return $this->get('/invoicing/dashboard');
    }

    /**
     * A página como ela se LÊ, e não como ela viaja.
     *
     * Duas coisas separam uma coisa da outra, e ambas dão testes que mentem:
     *
     *   1. O dicionário do JavaScript vai embutido na página e traz as chaves,
     *      que SÃO o português. Um assertDontSee('Fornecedores') numa página
     *      perfeitamente traduzida falha sempre — a palavra está lá, do lado
     *      esquerdo de um par do dicionário. Fora ele.
     *
     *   2. O @json do Blade escapa os acentos: "Session expirée" viaja como
     *      "Session expirée". O navegador desfaz isso e o utilizador vê o
     *      acento, mas um assertSee ingénuo dava falta de uma tradução que
     *      está lá e funciona.
     *
     *   3. Os comentários HTML viajam com a página mas ninguém os lê. Um
     *      <!-- Tesouraria Module --> fazia um teste acusar português numa
     *      página inteiramente inglesa.
     */
    private function comoSeLe(\Illuminate\Testing\TestResponse $resposta): string
    {
        $html = $resposta->assertOk()->getContent();

        $html = preg_replace('/window\.SOS_TRADUCOES = \{.*?\};/s', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        return preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            fn ($m) => mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UTF-16BE'),
            $html
        );
    }

    // ==================== o menu ====================

    /**
     * Os rótulos escolhidos são os que ESTE utilizador chega a ver.
     *
     * O menu é montado por permissões: cada item só existe no HTML se quem
     * está a olhar tiver direito a ele. Um teste feito com "Fornecedores" ou
     * "Armazéns" passava a dizer que a tradução funcionava quando na verdade a
     * linha nem sequer era desenhada — e um assertDontSee sobre uma linha
     * inexistente passa sempre, pela pior das razões.
     */
    public function test_o_menu_lateral_fala_ingles(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->comoSeLe($this->painel());

        foreach (['Home', 'Invoicing', 'Documents', 'Cash Shifts', 'Treasury'] as $esperado) {
            $this->assertStringContainsString($esperado, $html, "Faltou \"{$esperado}\" no menu inglês.");
        }

        foreach (['>Início<', 'Turnos de Caixa', 'Tesouraria'] as $portugues) {
            $this->assertStringNotContainsString($portugues, $html);
        }
    }

    public function test_o_menu_lateral_fala_frances(): void
    {
        $this->user->update(['locale' => 'fr']);

        $html = $this->comoSeLe($this->painel());

        foreach (['Accueil', 'Facturation', 'Sessions de caisse', 'Trésorerie'] as $esperado) {
            $this->assertStringContainsString($esperado, $html, "Faltou \"{$esperado}\" no menu francês.");
        }

        $this->assertStringNotContainsString('Turnos de Caixa', $html);
    }

    public function test_sem_escolha_o_menu_fala_portugues(): void
    {
        $html = $this->comoSeLe($this->painel());

        foreach (['Turnos de Caixa', 'Tesouraria', 'Documentos'] as $esperado) {
            $this->assertStringContainsString($esperado, $html);
        }

        $this->assertStringNotContainsString('Cash Shifts', $html);
    }

    /**
     * O emoji fica FORA do __().
     *
     * Dentro da chave obrigava o tradutor a copiá-lo à mão para cada língua —
     * três oportunidades de o perder, para zero benefício, porque um 📊 lê-se
     * igual nas três.
     */
    public function test_os_emojis_do_menu_sobrevivem_a_traducao(): void
    {
        $this->user->update(['locale' => 'fr']);

        $html = $this->comoSeLe($this->painel());

        $this->assertStringContainsString('📊', $html, 'O emoji do Dashboard desapareceu.');
        $this->assertStringContainsString('⏰', $html, 'O emoji dos Turnos de Caixa desapareceu.');
        $this->assertStringContainsString('📋', $html, 'O emoji do Histórico de Turnos desapareceu.');
    }

    // ==================== o painel ====================

    /*
     * O PAINEL MUDOU DE CASA, E OS ENSAIOS FORAM ATRÁS DELE.
     *
     * Estes cinco ensaios nasceram contra o painel em Blade, onde tudo — os
     * cartões, o rótulo do gráfico, os valores do CSV — vinha escrito no HTML
     * e se lia com um assertSee. O painel é hoje um ecrã em React
     * (`resources/js/ecras/facturacao/Painel.tsx`) e o HTML da página traz o
     * ponto de montagem e mais nada: os mesmos assertSee passariam a acusar
     * uma tradução partida que não existe, ou — pior — a passar por a palavra
     * portuguesa também já não estar lá.
     *
     * O que eles guardam continua a valer todo, palavra por palavra. O que
     * mudou foi ONDE cada metade se prova:
     *
     *   — o que o servidor desenha (o título da página, os nomes dos meses do
     *     gráfico, o dicionário que a página anuncia) prova-se por HTTP, como
     *     antes;
     *   — o que o ecrã desenha prova-se pelo PAR que o faz acontecer: a frase
     *     portuguesa que o ecrã usa como chave, e a tradução que o dicionário
     *     tem para ela. Uma sem a outra não traduz nada, e é sempre uma das
     *     duas que falta;
     *   — e o resultado com olhos de ver fica em `tests/browser/react.painel.spec.js`,
     *     que abre o painel em inglês num browser a sério.
     */

    /** O ecrã do painel, tal como está escrito hoje. */
    private function fonteDoPainel(): string
    {
        return $this->fonte('resources/js/ecras/facturacao/Painel.tsx');
    }

    private function fonte(string $caminho): string
    {
        $completo = base_path($caminho);

        $this->assertFileExists($completo, "Mudou de sítio: {$caminho}. O ensaio tem de ir atrás.");

        return (string) file_get_contents($completo);
    }

    /**
     * O PAR QUE TRADUZ: a chave que o ecrã usa e a tradução que existe.
     *
     * Não chega provar que o `lang/en.json` tem «Monthly Revenue» — tinha-o já
     * quando o painel em React mostrava «Facturado este mês» a toda a gente,
     * porque a frase que o ecrã escrevia não era a frase que o dicionário
     * conhecia. E também não chega provar que o ecrã embrulha em `t()`: sem
     * entrada no dicionário, o `t()` devolve a própria frase e o ecrã fica em
     * português com ar de traduzido.
     *
     * @param array<string, string> $paresEsperados chave portuguesa => tradução
     */
    private function assertOPainelTraduz(array $paresEsperados, string $lingua): void
    {
        $fonte = $this->fonteDoPainel();
        $frases = \App\Support\DicionarioDoReact::frases($lingua);

        foreach ($paresEsperados as $portugues => $traduzida) {
            $this->assertStringContainsString(
                "t('{$portugues}')",
                $fonte,
                "O painel devia usar «{$portugues}» como chave: é a frase que o dicionário conhece."
            );

            $this->assertSame(
                $traduzida,
                $frases[$portugues] ?? null,
                "Falta a tradução de «{$portugues}» em lang/{$lingua}.json."
            );
        }
    }

    /**
     * O que o SERVIDOR ainda desenha do painel prova-se por HTTP.
     *
     * O título da página não é do React: sai do `EcraReact::pagina()`, passa
     * por `__()` e vem no HTML. É a primeira coisa que se lê ao chegar, e por
     * isso é a que mais depressa denuncia uma página meio traduzida.
     */
    public function test_o_painel_fala_ingles(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->comoSeLe($this->painel());

        $this->assertStringContainsString('Invoicing Dashboard', $html);
        $this->assertStringNotContainsString('Dashboard de Faturação', $html);

        // E a página manda o ecrã buscar o dicionário inglês — sem isto o
        // React monta e traduz para lado nenhum.
        $this->assertStringContainsString('window.__reactLingua = "en"', $html);
        $this->assertStringContainsString('__reactDicionarioUrl', $html);

        $this->assertOPainelTraduz([
            'Dashboard de Faturação' => 'Invoicing Dashboard',
            'Faturação do Mês' => 'Monthly Revenue',
            'Aguardando pagamento' => 'Awaiting payment',
        ], 'en');
    }

    public function test_o_painel_fala_frances(): void
    {
        $this->user->update(['locale' => 'fr']);

        $html = $this->comoSeLe($this->painel());

        $this->assertStringContainsString('Tableau de bord de facturation', $html);
        $this->assertStringNotContainsString('Dashboard de Faturação', $html);
        $this->assertStringContainsString('window.__reactLingua = "fr"', $html);

        $this->assertOPainelTraduz([
            'Dashboard de Faturação' => 'Tableau de bord de facturation',
            'Aguardando pagamento' => 'En attente de paiement',
        ], 'fr');
    }

    /**
     * O gráfico não vem do nosso dicionário.
     *
     * Os nomes dos meses e os separadores decimais saem do `Intl`, e o `Intl`
     * precisa da ETIQUETA da língua ('en-GB'), não da língua ('en'). Estava
     * 'pt-PT' escrito à mão: a página ficava em inglês e o gráfico por baixo
     * continuava a dizer «ago.» com vírgula decimal. É o tipo de
     * meia-tradução que passa despercebida a quem revê o texto e salta à
     * vista a quem usa.
     *
     * A conta está partida em dois, e as duas metades provam-se aqui:
     *
     *   — os NOMES DOS MESES são do servidor (o `PainelDaFacturacao` monta-os
     *     com o Carbon), e viajam na resposta da API: prova-se por HTTP;
     *   — a ETIQUETA para o `Intl` do navegador é do `i18n.ts`, e daí sai para
     *     os formatadores do `tokens.ts` e para o nó da exportação. Aqui
     *     guarda-se a fonte; o valor formatado prova-se no `tokens.test.ts`.
     */
    public function test_o_grafico_recebe_a_etiqueta_da_lingua(): void
    {
        // O português vem PRIMEIRO de propósito. A escolha de língua fica
        // guardada num cookie, e o cookie sobrevive entre pedidos do mesmo
        // teste — depois de visitar em inglês, um utilizador sem língua no
        // perfil continua, e bem, a ver inglês. Testar o "sem escolha" no fim
        // media o cookie, não o valor por omissão.
        foreach (['' => 'jan', 'en' => 'Jan', 'fr' => 'janv'] as $escolhida => $mes) {
            $this->user->update(['locale' => $escolhida ?: null]);

            // A página diz ao ecrã em que língua se está a trabalhar: é dela
            // que sai a etiqueta do Intl.
            $this->assertStringContainsString(
                'window.__reactLingua = "' . ($escolhida ?: 'pt') . '"',
                $this->comoSeLe($this->painel())
            );

            $rotulos = array_column(
                $this->getJson('/api/v1/invoicing/react/painel')->assertOk()->json('por_mes'),
                'rotulo'
            );

            $this->assertCount(12, $rotulos);
            $this->assertStringStartsWith(
                $mes,
                $rotulos[0],
                'Com locale ' . ($escolhida ?: 'nenhum') . " o primeiro mês devia começar por «{$mes}»."
            );
        }

        // A tabela língua → etiqueta vive num sítio só, e é o que sabe a
        // língua que a tem.
        $i18n = $this->fonte('resources/js/i18n.ts');

        foreach (["pt: 'pt-PT'", "en: 'en-GB'", "fr: 'fr-FR'"] as $par) {
            $this->assertStringContainsString($par, $i18n, "Falta o par {$par} na tabela do Intl.");
        }

        $this->assertStringContainsString('export function etiquetaIntl()', $i18n);

        // E os formatadores perguntam-lhe, em vez de escreverem 'pt-PT'.
        $tokens = $this->fonte('resources/js/ui/tokens.ts');

        $this->assertStringContainsString("toLocaleString(etiquetaIntl()", $tokens);
        $this->assertStringContainsString("toLocaleDateString(etiquetaIntl()", $tokens);
        $this->assertStringNotContainsString(
            "toLocaleString('pt-PT'",
            $tokens,
            "Voltou a haver um 'pt-PT' escrito à mão: quem trabalha em inglês vê os números à portuguesa."
        );
        $this->assertStringNotContainsString("toLocaleDateString('pt-PT'", $tokens);

        // O mesmo para o que vai no nó da exportação, que é o que o PDF e o
        // CSV lêem para formatarem datas e números.
        $this->assertStringContainsString('intl: etiquetaIntl(),', $this->fonteDoPainel());
    }

    /**
     * O rótulo da série e o do período acompanham a língua.
     *
     * A frase do período é UMA chave com um `:periodo` lá dentro, e não duas
     * metades coladas: a ordem das palavras noutras línguas não é a
     * portuguesa. Por isso o que se prova aqui é a frase JÁ COMPOSTA.
     */
    public function test_os_rotulos_do_grafico_sao_traduzidos(): void
    {
        $this->assertOPainelTraduz([
            'Vendas (AOA)' => 'Sales (AOA)',
        ], 'en');

        $fonte = $this->fonteDoPainel();
        $frases = \App\Support\DicionarioDoReact::frases('en');

        /*
         * O PERÍODO JÁ NÃO ESTÁ ESCRITO DENTRO DO ECRÃ.
         *
         * Estava — «Este Ano» à letra — porque o painel em React tinha perdido
         * o selector que o de Blade tinha e mostrava sempre o ano. Com o
         * selector de volta, o rótulo do período escolhido vem do servidor, que
         * é quem sabe qual foi e quem tem a língua de quem está a olhar.
         *
         * O que não mudou: o título continua a ser UMA CHAVE SÓ, com o período
         * por dentro. Partido em pedaços obrigava o tradutor a adivinhar a
         * ordem das palavras, e há línguas onde ela não é a portuguesa.
         */
        $this->assertStringContainsString(
            "t('Evolução de Vendas - :periodo', { periodo: d.periodo.rotulo })",
            $fonte,
            'O título do gráfico devia compor-se de uma chave só, com o período que veio do servidor por dentro.'
        );

        $this->assertSame(
            'Sales Trend - This month',
            str_replace(':periodo', $frases['Este mês'], $frases['Evolução de Vendas - :periodo']),
            'A frase composta é o que a pessoa lê — e é ela que tem de fazer sentido em inglês.'
        );
    }

    /**
     * O CSV não pode levar separador de milhares.
     *
     * Um «1.234,56» dentro de um ficheiro separado por vírgulas abria na folha
     * de cálculo com uma coluna a mais e o valor partido em dois. Era um risco
     * teórico enquanto o número saía sempre em português; passou a ser certo
     * no dia em que o formato começou a seguir a língua, porque em inglês
     * `kz()` devolve mesmo «1,234.56».
     *
     * Daí os campos `…Cru` do nó `textosPainel`: os mesmos quatro valores,
     * escritos com `toFixed`, que dá `1234.56` seja qual for a língua. O nó é
     * hoje escrito pelo ecrã, e por isso não chega ao HTML — o que se lê aqui
     * é como ele é montado; que o nó real não leva vírgula nenhuma prova-se em
     * `tests/browser/react.painel.spec.js`.
     */
    public function test_o_csv_leva_numeros_sem_separador_de_milhares(): void
    {
        $fonte = $this->fonteDoPainel();

        $this->assertStringContainsString(
            'const cru = (v: number) => (Number.isFinite(v) ? v : 0).toFixed(2);',
            $fonte,
            'O valor cru do CSV escreve-se sem separador nenhum, e o toFixed é quem o garante.'
        );

        foreach (['facturado', 'recebido', 'pendente', 'vencido'] as $cartao) {
            // Formatado para o PDF, que é para uma pessoa ler...
            $this->assertStringContainsString("{$cartao}: kz(s.total_", $fonte);

            // ...e cru para o CSV, que é para uma folha de cálculo abrir.
            $this->assertStringContainsString("{$cartao}Cru: cru(s.total_", $fonte);
        }

        // E o nó continua a chamar-se o que o exportador procura: o
        // `exportarPainel.ts` lê-o pelo id, e não recebe nada por
        // argumento.
        foreach (['id="textosPainel"', 'id="dadosVendas"'] as $no) {
            $this->assertStringContainsString($no, $fonte, "O exportador procura o nó {$no} e não o encontra.");
        }
    }

    /**
     * O aviso de sessão expirada também se traduz.
     *
     * Vivia numa cadeia JavaScript no layout, e por isso escapou aos
     * varrimentos de Blade. É hoje a peça `casca/sistema`, em React: as frases
     * passam por t() e o dicionário tem de as ter nas três línguas. É o último
     * ecrã que um caixa vê antes de perder a sessão — em português, no meio de
     * um sistema inglês.
     */
    public function test_o_aviso_de_sessao_expirada_e_traduzido(): void
    {
        $this->user->update(['locale' => 'fr']);

        $html = $this->comoSeLe($this->painel());
        $this->assertStringContainsString('data-peca="casca/sistema"', $html);
        $this->assertStringContainsString('window.__reactDicionarioUrl', $html);

        $peca = file_get_contents(resource_path('js/ecras/casca/Sistema.tsx'));
        $frases = ['Sessão expirada', 'Por inatividade, a sua sessão terminou. Inicie sessão novamente para continuar.', 'Iniciar sessão'];

        foreach (['en', 'fr'] as $lingua) {
            $dicionario = json_decode(file_get_contents(lang_path("{$lingua}.json")), true);

            foreach ($frases as $frase) {
                $this->assertStringContainsString("t('{$frase}')", $peca);
                $this->assertNotEmpty($dicionario[$frase] ?? null, "{$lingua}: falta «{$frase}»");
            }
        }

        $this->assertSame('Session expirée', json_decode(file_get_contents(lang_path('fr.json')), true)['Sessão expirada']);
    }
}
