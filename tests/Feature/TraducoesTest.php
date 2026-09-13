<?php

namespace Tests\Feature;

use App\Http\Middleware\DefinirLingua;
use Tests\TenantTestCase;

/**
 * O circuito multi-língua — fase 0 do plano (docs/PLANO-MULTILINGUA.md).
 *
 * Duas metades: o DETECTOR, que varre o código à procura de __('...') e
 * rebenta com a lista do que está por traduzir em en/fr — é ele que
 * transforma "acho que está traduzido" em "está traduzido" —, e o CIRCUITO,
 * que prova que a língua escolhida chega mesmo ao ecrã.
 */
class TraducoesTest extends TenantTestCase
{
    /*
     * O CRACHÁ «Novo» DO MENU LATERAL, tal como a página o entrega à casca.
     *
     * Serve de sonda da língua porque diz três coisas diferentes nas três, e
     * porque se mede na MARCAÇÃO: procurar a palavra solta apanhava-a dentro
     * do dicionário que a página leva embutido (as chaves são portuguesas) e
     * dizia que a página fala português quando fala inglês. A barra lateral é
     * hoje o ecrã `casca` em React: o crachá vai nas props, já traduzido pelo
     * servidor, como `"extra":"…"` escapado no atributo.
     */
    private const EM_PORTUGUES = '&quot;extra&quot;:&quot;Novo&quot;';
    private const EM_INGLES = '&quot;extra&quot;:&quot;New&quot;';
    private const EM_FRANCES = '&quot;extra&quot;:&quot;Nouveau&quot;';

    protected function setUp(): void
    {
        parent::setUp();

        // O ecrã piloto vive atrás do tenant.module:invoicing — sem o módulo
        // activo, tudo dava 403 e o teste media a porta, não a língua.
        $modulo = \App\Models\Module::firstOrCreate(
            ['slug' => 'invoicing'],
            ['name' => 'Faturação', 'is_active' => true]
        );

        $this->tenant->modules()->syncWithoutDetaching([
            $modulo->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        // E ATRÁS DA SUA PERMISSÃO, desde que as rotas da facturação deixaram
        // de correr só com `auth` — o piloto é a transferência entre armazéns.
        $this->comPermissoes('invoicing.warehouse-transfer.view');
    }

    /**
     * Onde o detector procura. Alarga-se lote a lote, com a fase 1.
     *
     * Acrescentar um caminho aqui é o que fecha um lote: a partir desse
     * momento, qualquer cadeia usada sem tradução põe a suite vermelha.
     */
    private const ONDE = [
        // Fase 0 — piloto (e, desde o lote 3, o menu lateral).
        //
        // O menu foi trazido para a frente do plano por uma razão que só se vê
        // com o sistema à frente: é a primeira coisa que aparece. Quem escolhe
        // inglês e cai num menu inteiro em português conclui que a tradução
        // não funciona, mesmo com 700 cadeias já traduzidas por trás.
        'resources/views/layouts/app.blade.php',

        /*
         * Fase 1, lote 2 — POS (inclui o JavaScript do PWA offline).
         *
         * SAÍRAM DAQUI `resources/views/livewire/pos` e `app/Livewire/POS`, e
         * não por estarem traduzidos: o balcão passou a React e o `POSSystem`
         * foi apagado. O que resta em Blade é o balcão SEM REDE, que continua
         * onde estava — e é onde a tradução continua a ser exigida.
         */
        'resources/views/invoicing/offline/pos.blade.php',
        'public/js/pwa-invoicing.js',
        'public/js/pos-offline-ticket.js',

        // Os MODELOS também falam com o utilizador.
        //
        // Os acessores get*LabelAttribute devolvem o texto do crachá de
        // estado. Não estão no Blade, portanto escaparam a todos os lotes —
        // e quem revê um ecrã traduzido não os encontra lá para reparar que
        // a coluna "Estado" continua a dizer "Expirado" em inglês.
        'app/Models/Invoicing',

        /*
         * OS ECRÃS DA FACTURAÇÃO SAÍRAM DAQUI, e não por estarem traduzidos.
         *
         * Eram dezassete caminhos em `resources/views/livewire/invoicing/**`,
         * apagados com a migração para React. As cadeias mudaram-se para os
         * `.tsx`, que não passam por `__()` — hoje falam português e mais
         * nada. Ver `test_os_ecras_em_react_ainda_nao_falam_as_tres_linguas`,
         * que marca a dívida em vez de a deixar desaparecer com os ficheiros.
         */
    ];

    // ==================== o detector ====================

    public function test_todas_as_cadeias_usadas_tem_traducao_em_en_e_fr(): void
    {
        $usadas = $this->cadeiasUsadas();

        $this->assertNotEmpty($usadas, 'O varrimento tem de encontrar alguma coisa — o piloto usa __().');

        foreach (['en', 'fr'] as $lingua) {
            $dicionario = json_decode(file_get_contents(base_path("lang/{$lingua}.json")), true);

            $this->assertIsArray($dicionario, "lang/{$lingua}.json tem de ser JSON válido.");

            $emFalta = array_diff($usadas, array_keys($dicionario));

            $this->assertEmpty(
                $emFalta,
                "Cadeias sem tradução em {$lingua}:\n  " . implode("\n  ", $emFalta)
            );
        }
    }

    /** Uma tradução vazia é uma falta disfarçada. */
    public function test_nenhuma_traducao_esta_vazia(): void
    {
        foreach (['en', 'fr'] as $lingua) {
            $dicionario = json_decode(file_get_contents(base_path("lang/{$lingua}.json")), true);

            foreach ($dicionario as $chave => $valor) {
                $this->assertNotSame('', trim((string) $valor), "Tradução vazia em {$lingua}: {$chave}");
            }
        }
    }

    /**
     * Os placeholders têm de sobreviver à tradução.
     *
     * Uma tradução que perca o :ref ou o :n mostra a frase com um buraco —
     * ou pior, com o placeholder por preencher.
     */
    public function test_os_placeholders_sobrevivem_a_traducao(): void
    {
        foreach (['en', 'fr'] as $lingua) {
            $dicionario = json_decode(file_get_contents(base_path("lang/{$lingua}.json")), true);

            foreach ($dicionario as $chave => $valor) {
                /*
                 * UM PLACEHOLDER COMEÇA POR LETRA — `:n`, `:nome`, `:quantos`.
                 *
                 * Com `\w+` também apanhava o `:00` de uma HORA escrita na
                 * frase («entre as 22:00 e as 06:00»), e exigia que a tradução
                 * francesa — que escreve «22h00» — o conservasse. Era um alarme
                 * a dizer que a tradução tinha perdido um placeholder que nunca
                 * existiu.
                 */
                preg_match_all('/:([a-zA-Z]\w*)/', $chave, $originais);

                foreach ($originais[1] as $p) {
                    $this->assertStringContainsString(
                        ":{$p}",
                        $valor,
                        "A tradução {$lingua} de \"{$chave}\" perdeu o placeholder :{$p}."
                    );
                }
            }
        }
    }

    // ==================== o circuito ====================

    /**
     * A língua escolhida chega mesmo ao ecrã.
     *
     * O ecrã piloto era o das transferências, em Blade; hoje a página serve a
     * casca do React e as cadeias do miolo já não passam por `__()`. O que se
     * mede aqui continua a ser o que interessa e é do Laravel: o middleware
     * `DefinirLingua` escolhe a língua, e o LAYOUT — menu, cabeçalho, avisos —
     * sai traduzido à volta do ecrã, seja ele qual for.
     */
    public function test_o_utilizador_com_locale_en_ve_o_piloto_em_ingles(): void
    {
        $this->user->update(['locale' => 'en']);

        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee(self::EM_INGLES, false)
            ->assertDontSee(self::EM_PORTUGUES, false);
    }

    public function test_o_utilizador_com_locale_fr_ve_o_piloto_em_frances(): void
    {
        $this->user->update(['locale' => 'fr']);

        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee(self::EM_FRANCES, false)
            ->assertDontSee(self::EM_INGLES, false);
    }

    public function test_sem_escolha_o_ecra_fala_portugues(): void
    {
        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee(self::EM_PORTUGUES, false)
            ->assertDontSee(self::EM_INGLES, false);
    }

    /** ?lang= muda a língua e guarda-a no perfil. */
    public function test_o_parametro_lang_muda_e_fica_guardado(): void
    {
        $this->get('/invoicing/warehouse-transfer?lang=en')
            ->assertOk()
            ->assertSee(self::EM_INGLES, false);

        $this->assertSame('en', $this->user->refresh()->locale);

        // E a visita seguinte, sem parâmetro, continua em inglês.
        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee(self::EM_INGLES, false);
    }

    /** Uma língua inventada não passa. */
    public function test_uma_lingua_invalida_e_ignorada(): void
    {
        $this->get('/invoicing/warehouse-transfer?lang=xx')->assertOk();

        $this->assertNull($this->user->refresh()->locale);
    }

    /** A língua da empresa vale para quem não escolheu a sua. */
    public function test_a_lingua_da_empresa_e_o_ponto_de_partida(): void
    {
        $this->tenant->update(['locale' => 'fr']);

        $this->get('/invoicing/warehouse-transfer')
            ->assertOk()
            ->assertSee(self::EM_FRANCES, false);
    }

    /** As mensagens que o servidor devolve saem na língua do utilizador. */
    public function test_as_mensagens_do_componente_saem_traduzidas(): void
    {
        $this->user->update(['locale' => 'en']);
        app()->setLocale('en');

        // O ecrã é React e a recusa vem da API, mas o texto continua a ser do
        // servidor — é ele que sabe a língua de quem está do outro lado. A
        // mesma frase que o componente de transferências dizia, agora dita
        // pelo serviço `TransferenciaDeStock`.
        $this->assertSame('Select the source warehouse.', __('Selecione o armazém de origem.'));

        app()->setLocale('fr');

        $this->assertSame("Sélectionnez l'entrepôt d'origine.", __('Selecione o armazém de origem.'));
    }

    /**
     * A DÍVIDA QUE A MIGRAÇÃO PARA REACT DEIXOU.
     *
     * Dezassete caminhos de vistas da facturação saíram da lista `ONDE` porque
     * os ficheiros deixaram de existir. As cadeias que lá estavam vivem hoje
     * nos `.tsx`, escritas em português e sem passar por `__()`: quem escolhe
     * inglês continua a ver o menu em inglês e o miolo do ecrã em português.
     *
     * Fica escrito aqui, e não numa nota de rodapé, para que o dia em que o
     * React ganhar dicionário se saiba exactamente o que estava por fazer.
     */
    public function test_os_ecras_em_react_falam_as_tres_linguas(): void
    {
        $ecras = glob(resource_path('js/ecras/facturacao/*.tsx'))
            + glob(resource_path('js/ecras/facturacao/*/*.tsx'));

        $semTradutor = [];

        foreach ($ecras as $ficheiro) {
            if (str_ends_with($ficheiro, '.test.tsx')) {
                continue;
            }

            $fonte = file_get_contents($ficheiro);

            if (str_contains($fonte, "from '@/i18n'")) {
                continue;
            }

            /*
             * UM ECRÃ SEM FRASE NENHUMA NÃO PRECISA DE TRADUTOR.
             *
             * Há peças que só desenham — a faixa do topo, o cartão de número —
             * e recebem o texto todo de fora. Exigir-lhes o `t()` era exigir
             * um import por usar, e o ensaio passava a mentir sobre o que
             * guarda. O que se persegue é o ficheiro que ESCREVE texto e o
             * deixa fora do dicionário.
             */
            $escreveTexto = preg_match('/>[A-ZÀ-Ú][a-zà-úçãõéíóâêô ]{3,}</u', $fonte)
                || preg_match('/(etiqueta|titulo|placeholder|aria-label|rotulo)="[A-ZÀ-Ú][^"]{3,}"/u', $fonte);

            if ($escreveTexto) {
                $semTradutor[] = basename($ficheiro);
            }
        }

        $this->assertSame([], $semTradutor,
            'estes ecrãs da facturação não importam o tradutor: falam sempre português — ' . implode(', ', $semTradutor));

        // E o mecanismo está ligado: o arranque carrega o dicionário antes de
        // montar, senão os `t()` à cabeça de um módulo apanhavam-no vazio.
        $arranque = file_get_contents(resource_path('js/react.tsx'));

        $this->assertStringContainsString('carregarDicionario', $arranque,
            'o arranque tem de carregar o dicionário antes de montar os ecrãs');
    }

    /**
     * O QUE O `t()` DOS `.tsx` PEDE TEM DE ESTAR NO DICIONÁRIO.
     *
     * O teste acima só prova que o ecrã IMPORTA o tradutor. Isso não chega:
     * um `t('Por marcar hoje')` sem entrada em `lang/en.json` devolve a chave
     * — o português — e o ecrã fica meio traduzido sem ninguém dar por isso.
     *
     * Foi assim que 920 cadeias dos ecrãs em React chegaram ao fim da
     * migração fora do dicionário: o detector do `ONDE` procura `__()` em
     * PHP e em `.js`, e nunca olhou para os `.tsx`. Este é o guarda que
     * faltava — e a partir daqui, um ecrã novo em português rebenta a suite.
     */
    public function test_as_cadeias_do_react_tem_traducao_em_en_e_fr(): void
    {
        $usadas = $this->cadeiasDoReact();

        $this->assertGreaterThan(
            500,
            count($usadas),
            'o varrimento dos .tsx tem de encontrar as cadeias do React — se caiu para quase nada, o padrão deixou de casar'
        );

        foreach (['en', 'fr'] as $lingua) {
            $dicionario = json_decode(file_get_contents(base_path("lang/{$lingua}.json")), true);

            $emFalta = array_diff($usadas, array_keys($dicionario));

            $this->assertEmpty(
                $emFalta,
                "Cadeias do React sem tradução em {$lingua} (" . count($emFalta) . "):\n  "
                . implode("\n  ", array_slice($emFalta, 0, 40))
            );
        }
    }

    // ==================== o varrimento ====================

    /**
     * As cadeias dentro de `t('...')` em todo o `resources/js`.
     *
     * Só literais: um `t(variavel)` não se pode extrair, e é por isso que os
     * rótulos que vêm do servidor são traduzidos do lado do servidor.
     */
    private function cadeiasDoReact(): array
    {
        $cadeias = [];

        $ficheiros = array_filter(
            iterator_to_array(new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path('js'))
            )),
            fn ($f) => $f->isFile()
                && preg_match('/\.tsx?$/', $f->getFilename())
                && ! str_ends_with($f->getFilename(), '.test.tsx')
        );

        foreach ($ficheiros as $f) {
            $conteudo = file_get_contents($f->getPathname());

            // `t('...')` e `t("...")`. O `\b` antes do `t` evita apanhar o
            // fecho de `useEffect(`, `parseInt(` e companhia.
            if (preg_match_all('/\bt\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'/', $conteudo, $m)) {
                foreach ($m[1] as $cadeia) {
                    $cadeias[self::interpretarEscapes($cadeia)] = true;
                }
            }

            if (preg_match_all('/\bt\(\s*"((?:[^"\\\\]|\\\\.)+)"/', $conteudo, $m)) {
                foreach ($m[1] as $cadeia) {
                    $cadeias[self::interpretarEscapes($cadeia)] = true;
                }
            }
        }

        return array_keys($cadeias);
    }

    /**
     * Todas as cadeias dentro de __(), __n() e trans_choice() nos sítios
     * vigiados — em PHP, Blade E JavaScript.
     *
     * O .js entrou com o lote 2: o POS tem cadeias em JavaScript, e um
     * detector que só olhasse para .php deixava passar exactamente a parte
     * do sistema onde uma falta é mais cara — o caixa, offline, sem forma de
     * ser avisado.
     */
    private function cadeiasUsadas(): array
    {
        $cadeias = [];

        foreach (self::ONDE as $caminho) {
            $absoluto = base_path($caminho);
            $ficheiros = is_dir($absoluto)
                ? array_filter(
                    iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absoluto))),
                    fn ($f) => $f->isFile()
                        && (str_ends_with($f->getFilename(), '.php') || str_ends_with($f->getFilename(), '.js'))
                )
                : (is_file($absoluto) ? [new \SplFileInfo($absoluto)] : []);

            foreach ($ficheiros as $f) {
                $conteudo = file_get_contents($f->getPathname());

                // __('...'), __n('...') e trans_choice('...'), com aspas
                // simples. As plicas escapadas dentro da cadeia ficam de fora
                // por agora — o detector prefere acusar de menos a rebentar
                // com falsos positivos.
                if (preg_match_all("/(?:__n|__|trans_choice)\(\s*'((?:[^'\\\\]|\\\\.)+)'/", $conteudo, $m)) {
                    foreach ($m[1] as $cadeia) {
                        $cadeias[self::interpretarEscapes($cadeia)] = true;
                    }
                }

                // Em JavaScript também se escreve com aspas duplas.
                if (str_ends_with($f->getFilename(), '.js')
                    && preg_match_all('/(?:__n|__)\(\s*"((?:[^"\\\\]|\\\\.)+)"/', $conteudo, $m)) {
                    foreach ($m[1] as $cadeia) {
                        $cadeias[self::interpretarEscapes($cadeia)] = true;
                    }
                }
            }
        }

        return array_keys($cadeias);
    }

    /**
     * O texto que a linguagem vê em execução, e não o que está escrito no
     * ficheiro.
     *
     * Isto era um `stripslashes()`, e estava errado de uma forma calada:
     * stripslashes tira a barra e deixa a letra, portanto `\n` no código
     * virava a letra "n". A chave que o detector procurava era uma terceira
     * coisa — nem o que está no ficheiro, nem o que o JavaScript procura em
     * execução, que é uma mudança de linha a sério.
     *
     * Resultado prático: a mensagem de "Adicionar ao ecrã principal" do iOS
     * nunca teria sido traduzida, e ninguém saberia porquê.
     */
    private static function interpretarEscapes(string $cadeia): string
    {
        return strtr($cadeia, [
            '\\n'  => "\n",
            '\\r'  => "\r",
            '\\t'  => "\t",
            "\\'"  => "'",
            '\\"'  => '"',
            '\\\\' => '\\',
        ]);
    }
}
