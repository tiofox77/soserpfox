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
        $permissao = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'invoicing.dashboard.view',
            'guard_name' => 'web',
        ]);

        $this->user->givePermissionTo($permissao);
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

    public function test_o_painel_fala_ingles(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->comoSeLe($this->painel());

        foreach (['Invoicing Dashboard', 'Monthly Revenue', 'Awaiting payment'] as $esperado) {
            $this->assertStringContainsString($esperado, $html);
        }

        $this->assertStringNotContainsString('Dashboard de Faturação', $html);
        $this->assertStringNotContainsString('Aguardando pagamento', $html);
    }

    public function test_o_painel_fala_frances(): void
    {
        $this->user->update(['locale' => 'fr']);

        $html = $this->comoSeLe($this->painel());

        $this->assertStringContainsString('Tableau de bord de facturation', $html);
        $this->assertStringContainsString('En attente de paiement', $html);
        $this->assertStringNotContainsString('Dashboard de Faturação', $html);
    }

    /**
     * O gráfico não vem do nosso dicionário.
     *
     * Os nomes dos meses e os separadores decimais saem do Intl do navegador,
     * e o Intl precisa da etiqueta da língua. Estava 'pt-PT' escrito à mão em
     * quatro sítios: a página ficava em inglês e o gráfico por baixo continuava
     * a dizer "ago." com vírgula decimal. É o tipo de meia-tradução que passa
     * despercebida a quem revê o texto e salta à vista a quem usa.
     */
    public function test_o_grafico_recebe_a_etiqueta_da_lingua(): void
    {
        // O português vem PRIMEIRO de propósito. A escolha de língua fica
        // guardada num cookie, e o cookie sobrevive entre pedidos do mesmo
        // teste — depois de visitar em inglês, um utilizador sem língua no
        // perfil continua, e bem, a ver inglês. Testar o "sem escolha" no fim
        // media o cookie, não o valor por omissão.
        foreach (['' => 'pt-PT', 'en' => 'en-GB', 'fr' => 'fr-FR'] as $escolhida => $etiqueta) {
            $this->user->update(['locale' => $escolhida ?: null]);

            $this->assertStringContainsString(
                'const SOS_INTL = "' . $etiqueta . '"',
                $this->comoSeLe($this->painel()),
                'Com locale ' . ($escolhida ?: 'nenhum') . " o gráfico devia receber {$etiqueta}."
            );
        }
    }

    /** O rótulo da série e o do período acompanham a língua. */
    public function test_os_rotulos_do_grafico_sao_traduzidos(): void
    {
        $this->user->update(['locale' => 'en']);

        $html = $this->comoSeLe($this->painel());

        $this->assertStringContainsString('Sales (AOA)', $html);
        $this->assertStringContainsString('Sales Trend - This Month', $html);
    }

    /**
     * O CSV não pode levar separador de milhares.
     *
     * O number_format() por omissão escreve "1,234.56" — uma vírgula dentro
     * de um ficheiro separado por vírgulas. A folha de calculo abria a linha
     * com uma coluna a mais e o valor partido em dois.
     */
    public function test_o_csv_leva_numeros_sem_separador_de_milhares(): void
    {
        $html = $this->comoSeLe($this->painel());

        preg_match_all("/csv \+= .*?\+ ',([0-9.]*)/", $html, $m);

        $this->assertNotEmpty($m[1], 'Não encontrei os valores do CSV na página.');

        foreach ($m[1] as $valor) {
            $this->assertStringNotContainsString(
                ',',
                $valor,
                "O valor {$valor} leva vírgula — parte a coluna do CSV."
            );
        }
    }

    /**
     * O overlay de sessão expirada também se traduz.
     *
     * Vive dentro de uma cadeia JavaScript no layout, e por isso escapou aos
     * varrimentos de Blade. É o último ecrã que um caixa vê antes de perder a
     * sessão — em português, no meio de um sistema inglês.
     */
    public function test_o_aviso_de_sessao_expirada_e_traduzido(): void
    {
        $this->user->update(['locale' => 'fr']);

        $html = $this->comoSeLe($this->painel());

        $this->assertStringContainsString('Session expirée', $html);
        $this->assertStringNotContainsString('Sess&atilde;o expirada', $html);
    }
}
