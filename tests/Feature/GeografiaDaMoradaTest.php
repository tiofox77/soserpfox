<?php

namespace Tests\Feature;

use App\Livewire\Company\CompanyProfile;
use App\Livewire\Invoicing\Clients;
use App\Models\Client;
use App\Support\Geografia;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A morada deixa de se escrever à mão.
 *
 * O PAÍS É O QUE IMPORTA. Viaja para a AGT como `customerCountry`, que a
 * DS.120 exige em ISO 3166-1 alfa-2 — duas letras. Era um campo de texto: uma
 * empresa angolana estava gravada com «Portugal», e um cliente com o país por
 * extenso seguia para a AGT com oito caracteres num campo de dois.
 *
 * E as listas estavam escritas quatro e cinco vezes — províncias no Client, no
 * Supplier e em três vistas; países dentro de sete ficheiros Blade. É a
 * armadilha do E39 outra vez: cópias da mesma regra acabam diferentes.
 */
class GeografiaDaMoradaTest extends TenantTestCase
{
    // ── A fonte ───────────────────────────────────────────────────────

    public function test_os_paises_sao_iso_com_angola_la_dentro(): void
    {
        $paises = Geografia::paises();

        $this->assertGreaterThan(200, count($paises), 'a lista tem de ser o mundo, não sete vizinhos');
        $this->assertSame('Angola', $paises['AO']);
        $this->assertSame('Portugal', $paises['PT']);
        $this->assertArrayNotHasKey('OTHER', $paises, '«Outro» não é um país');

        foreach (array_keys($paises) as $codigo) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $codigo, "«{$codigo}» não é ISO alfa-2");
        }
    }

    public function test_as_provincias_sao_as_21_da_reforma_de_2024(): void
    {
        $provincias = Geografia::provincias();

        $this->assertCount(21, $provincias);

        foreach (['Luanda', 'Benguela', 'Huíla', 'Cabinda', 'Zaire'] as $antiga) {
            $this->assertContains($antiga, $provincias);
        }

        // As três que a reforma criou, mais o desdobramento do Cuando Cubango.
        foreach (['Icolo e Bengo', 'Cuando', 'Cubango', 'Moxico Leste'] as $nova) {
            $this->assertContains($nova, $provincias, "falta a província nova «{$nova}»");
            $this->assertContains($nova, Geografia::provinciasNovas());
        }
    }

    public function test_cada_provincia_tem_municipios_e_nenhum_se_repete(): void
    {
        $vistos = [];

        foreach (Geografia::provincias() as $provincia) {
            $municipios = Geografia::municipios($provincia);

            $this->assertNotEmpty($municipios, "a província «{$provincia}» ficou sem municípios");

            foreach ($municipios as $m) {
                $this->assertArrayNotHasKey($m, $vistos,
                    "o município «{$m}» aparece em «{$provincia}» e em «" . ($vistos[$m] ?? '?') . '»');
                $vistos[$m] = $provincia;
            }
        }

        $this->assertGreaterThan(150, count($vistos), 'Angola tem mais de 150 municípios');
        $this->assertSame('Benguela', Geografia::provinciaDoMunicipio('Lobito'));
    }

    /** O que estava gravado à mão tem de ser reconhecido. */
    public function test_o_pais_escrito_a_mao_converte_se(): void
    {
        foreach (['Angola' => 'AO', 'ANGOLA' => 'AO', 'angola' => 'AO', 'AO' => 'AO', 'ao' => 'AO',
                  'Portugal' => 'PT', 'pt' => 'PT', 'Brasil' => 'BR', 'RDC' => 'CD'] as $escrito => $esperado) {
            $this->assertSame($esperado, Geografia::normalizarPais($escrito), "«{$escrito}»");
        }

        // O que não se reconhece devolve null: quem chama decide, e adivinhar
        // o país de um documento fiscal é pior do que admitir que não se sabe.
        $this->assertNull(Geografia::normalizarPais('Terra Média'));
        $this->assertNull(Geografia::normalizarPais(''));
        $this->assertNull(Geografia::normalizarPais(null));
    }

    public function test_a_provincia_da_divisao_anterior_ainda_se_entende(): void
    {
        $this->assertSame('Cubango', Geografia::normalizarProvincia('Cuando Cubango'));
        $this->assertSame('Cuanza Norte', Geografia::normalizarProvincia('Kwanza Norte'));
        $this->assertSame('Huíla', Geografia::normalizarProvincia('huila'));
        // Um nome que não se reconhece mostra-se, não se apaga.
        $this->assertSame('Terra Nova', Geografia::normalizarProvincia('Terra Nova'));
    }

    // ── Não voltar a haver cópias ─────────────────────────────────────

    public function test_nao_ha_segunda_lista_de_paises_nem_de_provincias(): void
    {
        $ficheiros = array_merge(
            glob(app_path('Models/*.php')) ?: [],
            glob(resource_path('views/livewire/**/**/*.blade.php')) ?: [],
            glob(resource_path('views/livewire/**/*.blade.php')) ?: [],
        );

        foreach ($ficheiros as $ficheiro) {
            $conteudo = file_get_contents($ficheiro);

            $this->assertStringNotContainsString('PROVINCIAS_ANGOLA', $conteudo,
                basename($ficheiro) . ' voltou a ter a sua própria lista de províncias');
            $this->assertStringNotContainsString('<option value="AO">Angola', $conteudo,
                basename($ficheiro) . ' voltou a escrever os países à mão');
        }
    }

    // ── O ecrã da empresa ─────────────────────────────────────────────

    public function test_a_empresa_grava_o_pais_em_codigo(): void
    {
        $this->comPermissoes('settings.view', 'settings.edit');
        $this->actingAs($this->user);

        Livewire::test(CompanyProfile::class)
            ->set('country', 'AO')
            ->set('province', 'Luanda')
            ->set('municipality', 'Talatona')
            ->set('neighbourhood', 'Lar do Patriota')
            ->call('save')
            ->assertHasNoErrors();

        $t = $this->tenant->fresh();

        $this->assertSame('AO', $t->country);
        $this->assertSame('Luanda', $t->province);
        $this->assertSame('Talatona', $t->municipality);
        $this->assertSame('Lar do Patriota', $t->neighbourhood);
        // A CIDADE ACOMPANHA O MUNICÍPIO: relatórios, filtros e o SAFT lêem
        // `city`, e sem isto passavam a ver as moradas antigas para sempre.
        $this->assertSame('Talatona', $t->city);
    }

    /**
     * O ecrã não deixa lá ficar um país escrito por extenso — converte-o.
     *
     * O formulário não chega a estar inválido: quem escreva «Portugal» (ou
     * cole de um registo antigo) vê o campo passar a PT. O que não se
     * reconhece cai no país da casa, para o campo nunca ficar num estado que
     * a AGT recusa.
     */
    public function test_a_empresa_converte_o_pais_escrito_por_extenso(): void
    {
        $this->comPermissoes('settings.view', 'settings.edit');
        $this->actingAs($this->user);

        Livewire::test(CompanyProfile::class)
            ->set('country', 'Portugal')
            ->assertSet('country', 'PT')
            ->set('country', 'Terra Média')
            ->assertSet('country', Geografia::PAIS_PADRAO);
    }

    /** E a regra recusa, para quem grave sem passar pelo ecrã. */
    public function test_a_regra_do_pais_recusa_o_que_nao_e_iso(): void
    {
        $validador = fn ($valor) => validator(['country' => $valor], ['country' => [new \App\Rules\PaisIso()]]);

        $this->assertTrue($validador('AO')->passes());
        $this->assertTrue($validador('PT')->passes());
        $this->assertFalse($validador('Portugal')->passes());
        $this->assertFalse($validador('ANGOLA')->passes());

        // O vazio é assunto do `required`, que anda sempre com esta regra: o
        // Laravel não corre regras de objecto sobre valores vazios.
        $this->assertFalse(
            validator(['country' => ''], ['country' => ['required', new \App\Rules\PaisIso()]])->passes()
        );

        // A mensagem diz o código certo em vez de só recusar.
        $this->assertStringContainsString('PT',
            $validador('Portugal')->errors()->first('country'));
    }

    /** Mudar de país não pode deixar lá a província do país anterior. */
    public function test_sair_de_angola_limpa_a_provincia_e_o_municipio(): void
    {
        $this->comPermissoes('settings.view', 'settings.edit');
        $this->actingAs($this->user);

        Livewire::test(CompanyProfile::class)
            ->set('country', 'AO')
            ->set('province', 'Luanda')
            ->set('municipality', 'Talatona')
            ->set('country', 'PT')
            ->assertSet('province', null)
            ->assertSet('municipality', null);
    }

    public function test_mudar_de_provincia_limpa_o_municipio(): void
    {
        $this->comPermissoes('settings.view', 'settings.edit');
        $this->actingAs($this->user);

        Livewire::test(CompanyProfile::class)
            ->set('country', 'AO')
            ->set('province', 'Luanda')
            ->set('municipality', 'Talatona')
            ->set('province', 'Benguela')
            ->assertSet('municipality', null);
    }

    // ── Os clientes ───────────────────────────────────────────────────

    public function test_o_cliente_novo_nasce_com_o_codigo_e_nao_com_o_nome(): void
    {
        $this->comPermissoes('invoicing.clients.create', 'invoicing.clients.view');
        $this->actingAs($this->user);

        // Era aqui o furo: a propriedade nascia 'AO' e o resetForm() punha
        // 'Angola' por cima a cada formulário novo.
        Livewire::test(Clients::class)
            ->call('create')
            ->assertSet('country', 'AO');
    }

    public function test_o_cliente_grava_municipio_e_bairro(): void
    {
        $this->comPermissoes('invoicing.clients.create', 'invoicing.clients.view');
        $this->actingAs($this->user);

        Livewire::test(Clients::class)
            ->call('create')
            ->set('name', 'Cliente da Geografia')
            ->set('nif', '5417654999')
            ->set('country', 'AO')
            ->set('province', 'Benguela')
            ->set('municipality', 'Lobito')
            ->set('neighbourhood', 'Restinga')
            ->call('save')
            ->assertHasNoErrors();

        $c = Client::where('nif', '5417654999')->first();

        $this->assertNotNull($c);
        $this->assertSame('AO', $c->country);
        $this->assertSame('Lobito', $c->municipality);
        $this->assertSame('Restinga', $c->neighbourhood);
        $this->assertSame('Lobito', $c->city);
    }

    // ── O que sai para a AGT ──────────────────────────────────────────

    /**
     * O MAPEADOR DA AGT converte, em vez de deixar passar.
     *
     * Só conhecia «ANGOLA»; tudo o resto seguia tal como estava escrito.
     */
    public function test_o_pais_do_cliente_chega_a_agt_em_duas_letras(): void
    {
        $mapa = new \ReflectionClass(\App\Services\AGT\DocumentMapper::class);
        $fonte = file_get_contents($mapa->getFileName());

        $this->assertStringContainsString('Geografia::normalizarPais', $fonte,
            'o mapeador voltou a ler o país em bruto');
        $this->assertStringNotContainsString("in_array(\$country, ['ANGOLA', 'AO']", $fonte);
    }

    /**
     * O SAFT LIA UMA COLUNA QUE NÃO EXISTE.
     *
     * `$client->country_code` era sempre null, portanto TODOS os clientes
     * saíam no ficheiro entregue à AGT como angolanos — incluindo os
     * estrangeiros. Não dava erro nenhum: dava um ficheiro errado.
     */
    public function test_o_saft_deixa_de_ler_uma_coluna_inexistente(): void
    {
        foreach (['invoicing_clients', 'invoicing_suppliers'] as $tabela) {
            $this->assertNotContains('country_code',
                \Illuminate\Support\Facades\Schema::getColumnListing($tabela),
                "a coluna country_code passou a existir em {$tabela} — rever o SAFT");
        }

        $fonte = file_get_contents(app_path('Services/Invoicing/GeradorDeSaft.php'));

        $this->assertStringNotContainsString('$client->country_code', $fonte);
        $this->assertStringNotContainsString('$supplier->country_code', $fonte);
    }

    public function test_o_codigo_qr_leva_o_pais_normalizado(): void
    {
        $fonte = file_get_contents(app_path('Services/AGT/QRCodeService.php'));

        $this->assertStringContainsString('Geografia::normalizarPais', $fonte,
            'o país do QR impresso na factura voltou a ser lido em bruto');
    }

    /**
     * O DEFAULT DA COLUNA DIZIA «Portugal».
     *
     * O registo nunca perguntou o país, e 67 empresas em produção ficaram
     * portuguesas sem ninguém o escolher. É a mesma avaria do
     * `agt_schema_version`: um DEFAULT errado espalha-se em silêncio e passa
     * a parecer um facto.
     */
    public function test_uma_empresa_nova_nasce_angolana(): void
    {
        // Sem parâmetro: o MySQL não aceita um `?` no LIKE de um SHOW COLUMNS.
        $coluna = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM tenants LIKE 'country'");

        $this->assertSame('AO', $coluna[0]->Default ?? null,
            'o default da coluna voltou a dizer outra coisa');
    }

    /**
     * O SAFT da EMPRESA não segue essa coluna — de propósito.
     *
     * Enquanto o país da empresa vier de um default que ninguém escolheu, 67
     * empresas declarar-se-iam portuguesas num ficheiro entregue à AGT.
     */
    public function test_o_saft_da_empresa_nao_segue_o_pais_herdado(): void
    {
        $fonte = file_get_contents(app_path('Services/Invoicing/GeradorDeSaft.php'));

        $this->assertStringContainsString(
            "\$companyAddress->addChild('Country', \\App\\Support\\Geografia::PAIS_PADRAO)",
            $fonte
        );
    }

    /** As colunas novas existem nas três tabelas que saem em documentos. */
    public function test_as_tres_tabelas_fiscais_tem_municipio_e_bairro(): void
    {
        foreach (['tenants', 'invoicing_clients', 'invoicing_suppliers'] as $tabela) {
            foreach (['province', 'municipality', 'neighbourhood'] as $coluna) {
                $this->assertTrue(
                    \Illuminate\Support\Facades\Schema::hasColumn($tabela, $coluna),
                    "falta {$tabela}.{$coluna}"
                );
            }
        }
    }
}
