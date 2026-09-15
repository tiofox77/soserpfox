<?php

namespace Tests\Feature\Copias;

use App\Models\Copias\AgendaDeCopia;
use App\Models\Copias\CopiaDeSeguranca;
use App\Models\Copias\DestinoDeCopia;
use App\Models\Copias\RestauroDeCopia;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Copias\Agendador;
use App\Services\Copias\BaseInteira;
use App\Services\Copias\Cifra;
use App\Services\Copias\DadosDaEmpresa;
use App\Services\Copias\Destinos\Fornecedores;
use App\Services\Copias\Destinos\OAuth;
use App\Services\Copias\FazerCopia;
use App\Services\Copias\RestaurarCopia;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * CÓPIAS DE SEGURANÇA — da plataforma (a base inteira) e de cada empresa (só os dados dela).
 *
 * Prova-se o que importa quando se precisa de uma cópia: que ela tem o que diz
 * ter, que repor devolve exactamente o estado copiado e não toca noutra empresa,
 * que um ficheiro adulterado ou de outra empresa é recusado, que não se apagam
 * documentos fiscais emitidos depois da cópia, que os destinos recebem o
 * ficheiro às partes, e que as senhas dos destinos nunca saem na API.
 */
class CopiasDeSegurancaTest extends TenantTestCase
{
    private string $pasta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pasta = storage_path('framework/testing/copias-' . Str::random(8));
        config(['copias.pasta' => $this->pasta, 'copias.pedaco_bytes' => 1024]);

        // A base inteira não se despeja nos testes: um mysqldump falso que
        // escreve um ficheiro com a marca de fim.
        $this->app->instance(BaseInteira::class, new class extends BaseInteira {
            public array $reposta = [];

            public function despejar(string $destino): void
            {
                $gz = gzopen($destino, 'wb');
                gzwrite($gz, "-- SOSERP teste\nCREATE TABLE x (id int);\n" . str_repeat("-- enchimento\n", 50) . "-- Dump completed on 2026-09-15\n");
                gzclose($gz);
            }

            public function repor(string $origem): array
            {
                $this->reposta[] = $origem;

                return ['reposta' => true];
            }
        });
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory($this->pasta);
        parent::tearDown();
    }

    private function artigo(string $nome, ?int $tenantId = null): int
    {
        return DB::table('invoicing_products')->insertGetId([
            'tenant_id' => $tenantId ?? $this->tenant->id, 'name' => $nome, 'code' => 'C-' . uniqid(), 'sku' => 'S-' . uniqid(),
            'price' => 100, 'type' => 'produto', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function dono(): User
    {
        setPermissionsTeamId($this->tenant->id);
        $papel = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $this->user->assignRole($papel);
        $this->user->forceFill(['password' => bcrypt('Senha-do-dono-1')])->save();

        return $this->user->fresh();
    }

    /* ─── A cifra ──────────────────────────────────────────────────────── */

    public function test_a_cifra_vai_e_volta_e_recusa_a_frase_errada(): void
    {
        @mkdir($this->pasta, 0777, true);
        $claro = $this->pasta . '/claro.bin';
        file_put_contents($claro, random_bytes(3 * 1048576 + 17));

        Cifra::cifrar($claro, $this->pasta . '/c.soscopia', 'uma frase bem comprida');
        $this->assertTrue(Cifra::estaCifrado($this->pasta . '/c.soscopia'));

        Cifra::decifrar($this->pasta . '/c.soscopia', $this->pasta . '/volta.bin', 'uma frase bem comprida');
        $this->assertSame(hash_file('sha256', $claro), hash_file('sha256', $this->pasta . '/volta.bin'));

        $this->expectExceptionMessage('Frase-passe errada');
        Cifra::decifrar($this->pasta . '/c.soscopia', $this->pasta . '/errada.bin', 'outra frase qualquer');
    }

    /* ─── Os dados de uma empresa ──────────────────────────────────────── */

    public function test_repor_a_copia_da_empresa_devolve_o_estado_copiado_e_nao_toca_noutra(): void
    {
        $servico = app(DadosDaEmpresa::class);
        $guardado = $this->artigo('Paracetamol');
        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $daOutra = $this->artigo('Da outra empresa', $outra->id);

        @mkdir($this->pasta, 0777, true);
        $ficheiro = $this->pasta . '/empresa.jsonl.gz';
        $r = $servico->exportar($this->tenant->id, $ficheiro);
        $this->assertGreaterThan(0, $r['linhas']);
        $this->assertArrayHasKey('invoicing_products', $r['por_tabela']);

        // Depois da cópia: um artigo apagado e outro criado.
        DB::table('invoicing_products')->where('id', $guardado)->delete();
        $novo = $this->artigo('Criado depois da cópia');
        DB::table('invoicing_products')->where('id', $daOutra)->update(['name' => 'Da outra, alterado']);

        $this->travel(1)->minutes();
        $resumo = $servico->repor($ficheiro, $this->tenant->id);

        $this->assertSame('Paracetamol', DB::table('invoicing_products')->where('id', $guardado)->value('name'));
        $this->assertFalse(DB::table('invoicing_products')->where('id', $novo)->exists(), 'o que foi criado depois da cópia sai');
        $this->assertSame('Da outra, alterado', DB::table('invoicing_products')->where('id', $daOutra)->value('name'), 'a outra empresa não é tocada');
        $this->assertGreaterThan(0, $resumo['repostas']);
    }

    public function test_as_linhas_das_facturas_vao_na_copia(): void
    {
        $factura = DB::table('invoicing_sales_invoices')->insertGetId([
            'tenant_id' => $this->tenant->id, 'invoice_number' => 'FT-' . uniqid(), 'client_id' => $this->cliente->id,
            'invoice_date' => now(), 'status' => 'draft', 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $produto = $this->artigo('Linha');
        DB::table('invoicing_sales_invoice_items')->insert([
            'sales_invoice_id' => $factura, 'product_id' => $produto, 'product_name' => 'Linha', 'description' => 'Linha da factura', 'quantity' => 2,
            'unit_price' => 50, 'subtotal' => 100, 'total' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        @mkdir($this->pasta, 0777, true);
        $r = app(DadosDaEmpresa::class)->exportar($this->tenant->id, $this->pasta . '/e.jsonl.gz');

        $this->assertSame(1, $r['por_tabela']['invoicing_sales_invoice_items'] ?? 0);
    }

    public function test_uma_copia_alterada_ou_de_outra_empresa_e_recusada(): void
    {
        $servico = app(DadosDaEmpresa::class);
        $this->artigo('Original');
        @mkdir($this->pasta, 0777, true);
        $ficheiro = $this->pasta . '/e.jsonl.gz';
        $servico->exportar($this->tenant->id, $ficheiro);

        // De outra empresa:
        try {
            $servico->conferir($ficheiro, $this->tenant->id + 999999);
            $this->fail('aceitou a cópia de outra empresa');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('outra empresa', $e->getMessage());
        }

        // Alterada: troca-se um nome dentro do ficheiro.
        $conteudo = gzdecode(file_get_contents($ficheiro));
        file_put_contents($ficheiro, gzencode(str_replace('Original', 'Injectado', $conteudo)));

        $this->expectExceptionMessage('assinatura');
        $servico->conferir($ficheiro, $this->tenant->id);
    }

    /** Documentos fiscais emitidos depois da cópia não se apagam. */
    public function test_nao_se_repoe_uma_copia_anterior_a_documentos_fiscais(): void
    {
        $servico = app(DadosDaEmpresa::class);
        @mkdir($this->pasta, 0777, true);
        $ficheiro = $this->pasta . '/e.jsonl.gz';
        $servico->exportar($this->tenant->id, $ficheiro);

        $this->travel(5)->minutes();
        DB::table('invoicing_sales_invoices')->insert([
            'tenant_id' => $this->tenant->id, 'invoice_number' => 'FT-' . uniqid(), 'client_id' => $this->cliente->id,
            'invoice_date' => now(), 'status' => 'sent', 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectExceptionMessage('documentos fiscais');
        $servico->repor($ficheiro, $this->tenant->id);
    }

    /* ─── Fazer, enviar, reter ─────────────────────────────────────────── */

    public function test_a_copia_da_plataforma_faz_se_cifra_se_e_vai_para_o_google_drive_as_partes(): void
    {
        config(['copias.pedaco_bytes' => 128]);
        OAuth::guardarCredenciais('google', 'id-google', 'segredo-google');
        $agenda = AgendaDeCopia::para(null);
        $agenda->forceFill(['cifrar' => true])->guardarFrase('frase-muito-segura-123');
        $agenda->save();

        $destino = DestinoDeCopia::create(['tenant_id' => null, 'nome' => 'Drive', 'tipo' => 'google_drive', 'manter' => 5, 'activo' => true,
            'configuracao' => ['refresh_token' => 'rt', 'access_token' => 'at', 'expira_em' => now()->addHour()->timestamp, 'pasta_id' => 'pasta1']]);

        $partes = 0;
        Http::fake(function (PedidoHttp $p) use (&$partes) {
            if (str_contains($p->url(), 'uploadType=resumable')) {
                return Http::response('', 200, ['Location' => 'https://www.googleapis.com/upload/sessao-1']);
            }
            if ($p->url() === 'https://www.googleapis.com/upload/sessao-1') {
                $partes++;
                [$inicio, $fim, $total] = array_map('intval', preg_split('/[-\/]/', substr($p->header('Content-Range')[0], 6)));

                return $fim + 1 < $total ? Http::response('', 308) : Http::response(['id' => 'ficheiro-google-1'], 200);
            }

            return Http::response(['error' => 'inesperado ' . $p->url()], 500);
        });

        $copia = app(FazerCopia::class)->fazer(null, 'manual', $this->user->id);

        $this->assertSame('concluida', $copia->estado);
        $this->assertTrue($copia->cifrada);
        $this->assertStringEndsWith('.sql.gz.soscopia', $copia->ficheiro);
        $this->assertFileExists($this->pasta . '/plataforma/' . $copia->ficheiro);
        $this->assertFileExists($this->pasta . '/plataforma/' . $copia->ficheiro . '.json');
        $this->assertSame('enviado', $copia->envios->first()->estado);
        $this->assertSame('ficheiro-google-1', $copia->envios->first()->remoto);
        $this->assertGreaterThan(1, $partes, 'o envio tinha de ir às partes');
        $this->assertNotNull($agenda->fresh()->proxima_em);
        $this->assertTrue($agenda->fresh()->proxima_em->gt(now()->addHours(5)));
    }

    public function test_um_destino_que_falha_nao_impede_os_outros_e_fica_o_erro(): void
    {
        DestinoDeCopia::create(['nome' => 'WebDAV', 'tipo' => 'webdav', 'manter' => 5, 'activo' => true,
            'configuracao' => ['url' => 'https://nuvem.exemplo.ao/dav', 'utilizador' => 'u', 'senha' => 's']]);
        DestinoDeCopia::create(['nome' => 'S3 avariado', 'tipo' => 's3', 'manter' => 5, 'activo' => true,
            'configuracao' => ['endpoint' => 'https://s3.exemplo.ao', 'regiao' => 'us-east-1', 'bucket' => 'b', 'chave_acesso' => 'k', 'chave_secreta' => 's']]);

        Http::fake([
            'nuvem.exemplo.ao/*' => Http::response('', 201),
            's3.exemplo.ao/*' => Http::response('<Error>AccessDenied</Error>', 403),
        ]);

        $copia = app(FazerCopia::class)->fazer(null, 'manual');

        $estados = $copia->envios->mapWithKeys(fn ($e) => [$e->destino->nome => $e->estado]);
        $this->assertSame('enviado', $estados['WebDAV']);
        $this->assertSame('falhou', $estados['S3 avariado']);
        $this->assertStringContainsString('AccessDenied', (string) DestinoDeCopia::where('nome', 'S3 avariado')->value('ultimo_erro'));
    }

    public function test_o_s3_assina_os_pedidos_com_sigv4(): void
    {
        $destino = DestinoDeCopia::create(['nome' => 'R2', 'tipo' => 's3', 'manter' => 5, 'activo' => true, 'pasta' => 'copias',
            'configuracao' => ['endpoint' => 'https://conta.r2.cloudflarestorage.com', 'regiao' => 'auto', 'bucket' => 'soserp', 'chave_acesso' => 'AKIA', 'chave_secreta' => 'segredo', 'estilo_caminho' => true]]);
        Http::fake(['*' => Http::response('', 200)]);
        @mkdir($this->pasta, 0777, true);
        file_put_contents($this->pasta . '/f.gz', 'conteudo');

        $remoto = Fornecedores::criar($destino)->enviar($this->pasta . '/f.gz', 'soserp-bd-20260915-120000.sql.gz');

        $this->assertSame('copias/soserp-bd-20260915-120000.sql.gz', $remoto);
        Http::assertSent(fn (PedidoHttp $p) => $p->method() === 'PUT'
            && $p->url() === 'https://conta.r2.cloudflarestorage.com/soserp/copias/soserp-bd-20260915-120000.sql.gz'
            && str_starts_with($p->header('Authorization')[0], 'AWS4-HMAC-SHA256 Credential=AKIA/')
            && str_contains($p->header('Authorization')[0], '/auto/s3/aws4_request'));
    }

    public function test_a_retencao_apaga_as_mais_antigas_da_pasta(): void
    {
        $agenda = AgendaDeCopia::para(null);
        $agenda->forceFill(['manter_locais' => 2])->save();

        $feitas = [];
        for ($i = 0; $i < 4; $i++) {
            $this->travel(1)->minutes();
            $feitas[] = app(FazerCopia::class)->fazer(null, 'manual');
        }

        $this->assertFileDoesNotExist($this->pasta . '/plataforma/' . $feitas[0]->ficheiro);
        $this->assertFileExists($this->pasta . '/plataforma/' . $feitas[3]->ficheiro);
        $this->assertSame(2, CopiaDeSeguranca::whereNull('tenant_id')->where('ficheiro_local', true)->count());
    }

    /* ─── O agendador ──────────────────────────────────────────────────── */

    public function test_o_agendador_faz_a_da_plataforma_primeiro_e_depois_a_empresa_mais_atrasada(): void
    {
        AgendaDeCopia::query()->delete();
        DB::table('tenants')->where('id', '<>', $this->tenant->id)->update(['is_active' => false]);

        $primeira = app(Agendador::class)->correrUma();
        $this->assertSame('plataforma', $primeira['ambito']);

        $segunda = app(Agendador::class)->correrUma();
        $this->assertSame("empresa #{$this->tenant->id}", $segunda['ambito']);

        $this->assertNull(app(Agendador::class)->correrUma(), 'nada mais estava na hora');
    }

    /* ─── A API ────────────────────────────────────────────────────────── */

    public function test_as_senhas_dos_destinos_nunca_saem_na_api(): void
    {
        $this->dono();
        DestinoDeCopia::create(['tenant_id' => $this->tenant->id, 'nome' => 'FTP', 'tipo' => 'ftp', 'manter' => 5, 'activo' => true,
            'configuracao' => ['anfitriao' => 'ftp.exemplo.com', 'utilizador' => 'u', 'senha' => 'a-senha-secreta', 'seguranca' => 'explicito']]);

        $r = $this->getJson('/api/v1/invoicing/react/copias')->assertOk();

        $this->assertStringNotContainsString('a-senha-secreta', $r->getContent());
        $this->assertSame('••••••••', $r->json('destinos.0.configuracao.senha'));
    }

    public function test_quem_nao_gere_a_conta_nao_ve_as_copias_da_empresa(): void
    {
        $caixa = User::create(['name' => 'Caixa', 'email' => 'caixa' . uniqid() . '@x.ao', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $caixa->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->actingAs($caixa)->getJson('/api/v1/invoicing/react/copias')->assertForbidden();
        $this->actingAs($caixa)->getJson('/api/v1/plataforma/react/copias')->assertForbidden();
    }

    public function test_uma_empresa_nao_aponta_um_destino_para_a_rede_interna(): void
    {
        $this->dono();

        $this->postJson('/api/v1/invoicing/react/copias/destinos', [
            'tipo' => 'ftp', 'nome' => 'Interno', 'manter' => 5, 'activo' => true,
            'configuracao' => ['anfitriao' => '127.0.0.1', 'utilizador' => 'u', 'senha' => 's', 'seguranca' => 'nenhuma'],
        ])->assertStatus(422)->assertJsonValidationErrors('configuracao');
    }

    public function test_editar_um_destino_sem_escrever_a_senha_mantem_a_gravada(): void
    {
        $this->dono();
        $d = DestinoDeCopia::create(['tenant_id' => $this->tenant->id, 'nome' => 'WebDAV', 'tipo' => 'webdav', 'manter' => 5, 'activo' => true,
            'configuracao' => ['url' => 'https://nuvem.exemplo.com/dav', 'utilizador' => 'u', 'senha' => 'senha-antiga']]);

        $this->putJson("/api/v1/invoicing/react/copias/destinos/{$d->id}", [
            'nome' => 'WebDAV renomeado', 'manter' => 3, 'activo' => true, 'pasta' => 'Copias',
            'configuracao' => ['url' => 'https://nuvem.exemplo.com/dav', 'utilizador' => 'u', 'senha' => '••••••••'],
        ])->assertOk();

        $this->assertSame('senha-antiga', $d->fresh()->cfg('senha'));
        $this->assertSame('WebDAV renomeado', $d->fresh()->nome);
    }

    public function test_fazer_uma_copia_pela_api_responde_logo_e_conclui_depois(): void
    {
        $this->dono();

        $r = $this->postJson('/api/v1/invoicing/react/copias/fazer')->assertStatus(202);

        $copia = CopiaDeSeguranca::find($r->json('copia_id'));
        $this->assertSame('concluida', $copia->estado, 'o trabalho corre no terminate do pedido');
        $this->assertSame($this->tenant->id, $copia->tenant_id);
        $this->assertStringStartsWith("soserp-empresa-{$this->tenant->id}-", $copia->ficheiro);
    }

    public function test_repor_pede_a_senha_e_a_palavra_restaurar(): void
    {
        $this->dono();
        $copia = app(FazerCopia::class)->fazer($this->tenant->id, 'manual');

        $this->postJson('/api/v1/invoicing/react/copias/restaurar', ['copia_id' => $copia->id, 'confirmacao' => 'sim', 'senha' => 'Senha-do-dono-1'])
            ->assertStatus(422)->assertJsonValidationErrors('confirmacao');
        $this->postJson('/api/v1/invoicing/react/copias/restaurar', ['copia_id' => $copia->id, 'confirmacao' => 'RESTAURAR', 'senha' => 'errada'])
            ->assertStatus(422)->assertJsonValidationErrors('senha');

        $r = $this->postJson('/api/v1/invoicing/react/copias/restaurar', ['copia_id' => $copia->id, 'confirmacao' => 'RESTAURAR', 'senha' => 'Senha-do-dono-1'])
            ->assertStatus(202);

        $restauro = RestauroDeCopia::find($r->json('restauro_id'));
        $this->assertSame('concluido', $restauro->estado, (string) $restauro->erro);
        $this->assertNotNull($restauro->copia_previa_id, 'antes de repor tira-se uma cópia do estado actual');
        $this->assertSame('antes_de_restaurar', CopiaDeSeguranca::find($restauro->copia_previa_id)->origem);
    }

    public function test_uma_empresa_nao_repoe_nem_descarrega_a_copia_de_outra(): void
    {
        $this->dono();
        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheia = app(FazerCopia::class)->fazer($outra->id, 'manual');

        $this->get("/api/v1/invoicing/react/copias/{$alheia->id}/descarregar")->assertNotFound();
        $this->postJson('/api/v1/invoicing/react/copias/restaurar', ['copia_id' => $alheia->id, 'confirmacao' => 'RESTAURAR', 'senha' => 'Senha-do-dono-1'])
            ->assertNotFound();
    }

    public function test_o_retorno_do_oauth_recusa_um_state_forjado_e_liga_com_um_valido(): void
    {
        $this->user->forceFill(['is_super_admin' => true])->save();
        OAuth::guardarCredenciais('dropbox', 'id-db', 'segredo-db');
        $destino = DestinoDeCopia::create(['tenant_id' => null, 'nome' => 'Dropbox', 'tipo' => 'dropbox', 'manter' => 5, 'activo' => true, 'configuracao' => []]);
        Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 14400])]);

        $this->actingAs($this->user->fresh())->get('/copias/oauth/retorno?state=forjado&code=x')
            ->assertRedirect()->assertRedirectContains('oauth=erro');
        $this->assertFalse($destino->fresh()->ligado);

        $url = OAuth::urlDeAutorizacao($destino, $this->user->id);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        $this->actingAs($this->user->fresh())->get('/copias/oauth/retorno?state=' . urlencode($q['state']) . '&code=codigo-real')
            ->assertRedirectContains('oauth=ligado');
        $this->assertTrue($destino->fresh()->ligado);
        $this->assertSame('rt', $destino->fresh()->cfg('refresh_token'));
    }

    public function test_uma_copia_abandonada_a_meio_deixa_de_estar_a_correr(): void
    {
        $this->dono();
        $presa = CopiaDeSeguranca::create(['tenant_id' => $this->tenant->id, 'ficheiro' => 'soserp-a-preparar-1.tmp', 'origem' => 'automatica', 'estado' => 'a_correr', 'iniciada_em' => now()->subMinutes(30)]);
        $recente = CopiaDeSeguranca::create(['tenant_id' => $this->tenant->id, 'ficheiro' => 'soserp-a-preparar-2.tmp', 'origem' => 'manual', 'estado' => 'a_correr', 'iniciada_em' => now()->subMinute()]);

        $this->getJson('/api/v1/invoicing/react/copias')->assertOk();

        $this->assertSame('falhou', $presa->fresh()->estado);
        $this->assertStringContainsString('interrompida', $presa->fresh()->erro);
        $this->assertSame('a_correr', $recente->fresh()->estado, 'a que começou agora ainda pode estar a correr');
    }

    /**
     * As colunas GERADAS ficam fora (o MySQL recusa quem lhes escreva), mas um
     * `DEFAULT CURRENT_TIMESTAMP` aparece como «DEFAULT_GENERATED» e é uma
     * coluna normal — o primeiro filtro deitava-as fora também.
     */
    public function test_as_colunas_geradas_ficam_fora_da_copia_e_as_de_data_por_omissao_vao(): void
    {
        $series = \App\Services\Copias\MapaDaEmpresa::colunas('invoicing_series');
        $this->assertArrayNotHasKey('padrao_unico', $series);
        $this->assertArrayHasKey('tenant_id', $series);

        $this->assertArrayHasKey('failed_at', \App\Services\Copias\MapaDaEmpresa::colunas('failed_jobs'));
    }
}
