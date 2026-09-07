<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTKeyStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * A API DA AGT, para o ecrã em React.
 *
 * O que estes ensaios guardam, e o ecrã de sempre já garantia: ver e
 * editar são permissões diferentes; guardar as definições nunca muda o
 * ambiente que emite; activar produção exige o par RSA de produção; as
 * acções que escrevem na AGT recusam-se fora do ambiente activo; repor uma
 * submissão esgotada zera o contador; e a ficha do contribuinte valida os
 * e-mails.
 */
class ApiDaAgtParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/agt';

    /**
     * Um par RSA de 2048 bits gerado só para estes ensaios — nunca assinou
     * nada e nunca assinará. Vai escrito por extenso porque o
     * `openssl_pkey_new()` precisa do `openssl.cnf` e não o encontra em todas
     * as bancadas; ler um PEM, que é o que se está a provar, não precisa dele.
     */
    private const PEM = <<<'CHAVE'
        -----BEGIN PRIVATE KEY-----
        MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCqK6aLY2MJIFko
        Xcka/d1RQzW9Q8A6RDtU5c7kG9DwNb5XbYNOJ+4jF6gXSKnrQ1twEjQmAonL1IBe
        qAhhi+1a2gxfhTT5GEnhbuiqaZOX+DG+N+p6zE/s7Mu54ImA+aKX8WXUe8re5ogi
        K6WvkvWXFFLuYMM69Uvpa6p8WyRhhtaWZTKpJw16K7EYPS+xpS6Iv42vX1pei6Zc
        JlYRF0cNizO6dVqskbFMgCESHiatT0oZn6cZntNZh6YM6xDjT9HrDwKVzwiEf5e9
        qsKVj/5BsRE4plBK9wsSa/dpukbea+rPyNSgYJnkUoYJNDBqOYNov1LEVaGtLOLY
        v3rmC7mpAgMBAAECggEADT959yjRA1tAea0ofIiWR+7rsn0BbH/WnybHdziHqQXE
        GGYbFnTzHAoJ0Os8KEfNiPFv3CQvMl5MMqJSCbcVLtEiLLcP/1MB4HIsHKU8w3Rj
        +f88Ktx4XfV27FUD23XDz+CwGO1cx0LrBv7/JoayRjVjufFwTYkehHD4fDczw0BV
        tQMRNsaJpJkvAgPomfM0wDK7rnXT1Qaz7eN8bxb0hcdY7D95v8mRiZiuxmTXlhVo
        3RWhIU7BpptDiUckW7R+nb3hDsu6uAT1PeWAXrCDhgTA8OGke3jeqffqCaEjfdgc
        PEVPKJuzIcjzAo4Ji1yzUkmvE6o0cda1mmWEtlq9AQKBgQDXvXim6G4tiHlrvZDK
        fUIcGEfUKXvWiU2k8nyvg6BSWPdAX2ouHly2XCTKCWhJ4LjOWdNjUbYK3MaQq7oE
        8zai9JJoNZ8tcfnRUu4oeFHWGES0Qay4Udjz61pFK1SR8FJfhyUS1svxpoybc6G+
        V9DH548W6imZmmxUrmzkARoZAQKBgQDJ7S/x5gb+zr0/Z5w6D6MXfJrHMTrv0tUA
        8BFCOTnjFqOoagt7RGO9Xzhn25DQvfZDoOpaU0OVXzliRWEbjM8gqeHML0Onx+Wd
        iA7lDcafZsenEq4WO7HjGp8rz0m/MRdQRWuCBNg8xFBSgmcbq0Y4tgqaWirSY9Ci
        FF1oxVk4qQKBgQCr6/+z8tGqU3F/XGeAFeWTAf5roktfobdQVTTroVcniGIw2FiD
        PArh//gJUQncpcpgFtEP+tO5QEq0i0UIINFPdtsdVG3vBz7vgsjrU0bT+C73/sYn
        dIIRj2I2cNtKGVtraQUwSB/qCLFQSAuC5fQo+ezbc+uGzrq5mO6JnB8yAQKBgCoB
        NiUK5c+hsAp9gikt0Y50NDpVil4TLI4aYmy1PM55iifhj2vgCSN+qFwqd5CEw7LD
        yZxqj7eF7Ij9x7qUaw3vaPIxrtA7LA++GuMZH4VPOx8NKrujRVjp08yoPT4RdzkS
        h8+vNFBHwjG3wL0nvt7TN5duRFQpwV/F/rxpuSqpAoGAGosNk4yXCUHfIi5ffrV3
        rpzwvsh+Uj3+YWEIsmROpmTfug6aoVXaoZh32nFQVCh5BF0WWEulv6KahYLIj/BC
        26YDNMSOolV9f1ITmzAQbqSDpy0wBqCtwPHa03vWCAiUCNljz7oqiNkG1K8vma15
        B45eum73Hf11QXtRbyAn/NI=
        -----END PRIVATE KEY-----
        CHAVE;

    /** Onde o modo antigo arruma a chave — a pasta legada do `AGTKeyStore`. */
    private function caminhoDaChaveLegado(): string
    {
        return AGTKeyStore::legacyDirectory($this->tenant->id) . '/private_key.pem';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        // Disco falso: estes testes instalam chaves.
        Storage::fake('local');

        // Nada sai para a AGT a partir de um ensaio.
        Http::fake(['*' => Http::response(['requestID' => 'x', 'resultCode' => '0'], 200)]);
    }

    private function definicoes(): InvoicingSettings
    {
        return InvoicingSettings::forTenant($this->tenant->id);
    }

    /** @test */
    public function ver_e_editar_sao_permissoes_diferentes(): void
    {
        $this->getJson(self::RAIZ . '/estado')->assertForbidden();

        $this->comPermissoes('invoicing.agt.view');

        $this->getJson(self::RAIZ . '/opcoes')->assertOk()->assertJsonPath('permissoes.pode_editar', false);
        $this->getJson(self::RAIZ . '/estado')->assertOk()
            ->assertJsonStructure(['empresa' => ['id', 'nome'], 'ambiente', 'definicoes', 'ambientes' => ['sandbox', 'production'], 'chaves', 'em_falta', 'relatorio', 'series', 'submissoes', 'logs']);

        $this->postJson(self::RAIZ . '/definicoes', ['agt_eac_code' => '47730'])->assertForbidden();
        $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production'])->assertForbidden();
    }

    /** @test */
    public function abre_no_ambiente_activo_e_ver_o_outro_nao_muda_nada(): void
    {
        $this->comPermissoes('invoicing.agt.view');
        $this->definicoes()->update(['agt_environment' => 'production']);

        $this->getJson(self::RAIZ . '/estado')->assertOk()
            ->assertJsonPath('ambiente', 'production')
            ->assertJsonPath('ambientes.production.activo', true)
            ->assertJsonPath('ambientes.production.a_ver', true);

        $this->getJson(self::RAIZ . '/estado?ambiente=sandbox')->assertOk()
            ->assertJsonPath('ambiente', 'sandbox')
            ->assertJsonPath('ambientes.sandbox.a_ver', true)
            ->assertJsonPath('ambientes.production.activo', true);

        $this->assertSame('production', $this->definicoes()->fresh()->agt_environment);
    }

    /** O caso perigoso: guardar o CAE enquanto se vê o outro ambiente. @test */
    public function guardar_as_definicoes_nunca_muda_o_ambiente(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->definicoes()->update(['agt_environment' => 'production']);

        $this->postJson(self::RAIZ . '/definicoes', ['agt_eac_code' => '47730', 'agt_auto_submit' => true, 'ambiente' => 'sandbox'])->assertOk();

        $d = $this->definicoes()->fresh();
        $this->assertSame('production', $d->agt_environment, 'guardar o CAE não pode mudar o ambiente');
        $this->assertSame('47730', $d->agt_eac_code);
        $this->assertTrue((bool) $d->agt_auto_submit);
    }

    /** @test */
    public function activar_producao_exige_as_chaves_de_producao(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->definicoes()->update(['agt_environment' => 'sandbox']);

        $r = $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production'])->assertStatus(422);
        $this->assertStringContainsString('par RSA de Produção', $r->json('message'));
        $this->assertSame('sandbox', $this->definicoes()->fresh()->agt_environment);

        AGTKeyStore::store($this->tenant->id, 'pub', 'priv', 'production');

        $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production'])->assertOk()->assertJsonPath('ambiente_activo', 'production');
        $this->assertSame('production', $this->definicoes()->fresh()->agt_environment);

        // Voltar a homologação é sempre possível: é o caminho de recuo.
        $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'sandbox'])->assertOk()->assertJsonPath('ambiente_activo', 'sandbox');
    }

    /** @test */
    public function uma_chave_que_nao_e_pem_e_recusada_no_campo_certo(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');

        $this->postJson(self::RAIZ . '/chaves', ['ambiente' => 'production', 'contributorPublicKey' => 'isto não é PEM', 'contributorPrivateKey' => 'nem isto'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('contributorPublicKey');

        $this->assertFalse(AGTKeyStore::hasKeyPair($this->tenant->id, 'production'));
    }

    /** @test */
    public function remover_chaves_so_afecta_o_ambiente_pedido(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        AGTKeyStore::store($this->tenant->id, 'pubA', 'privA', 'sandbox');
        AGTKeyStore::store($this->tenant->id, 'pubB', 'privB', 'production');

        $this->postJson(self::RAIZ . '/chaves/remover', ['ambiente' => 'production'])->assertOk();

        $this->assertFalse(AGTKeyStore::hasKeyPair($this->tenant->id, 'production'));
        $this->assertTrue(AGTKeyStore::hasKeyPair($this->tenant->id, 'sandbox'), 'apagar as de produção não pode levar as de homologação atrás');
    }

    /** @test */
    public function escrever_na_agt_recusa_se_fora_do_ambiente_activo(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->definicoes()->update(['agt_environment' => 'production']);

        $this->postJson(self::RAIZ . '/series/sincronizar', ['ambiente' => 'sandbox'])
            ->assertStatus(422)
            ->assertJsonPath('ambiente_errado', true);

        // Sem chaves nem produtor, no ambiente certo, não sai pedido nenhum.
        $r = $this->postJson(self::RAIZ . '/series/sincronizar', ['ambiente' => 'production'])->assertStatus(422);
        $this->assertFalse($r->json('ambiente_errado'));
        $this->assertStringContainsString('Falta configurar', $r->json('message'));
    }

    /** @test */
    public function repor_uma_submissao_esgotada_zera_o_contador(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $ambiente = $this->definicoes()->agt_environment ?: 'sandbox';

        $s = AGTSubmission::create([
            'tenant_id' => $this->tenant->id,
            'agt_environment' => $ambiente,
            'document_type' => 'App\\Models\\Invoicing\\SalesInvoice',
            'document_id' => 999999999,
            'document_number' => 'FT A/000002',
            'document_type_code' => 'FT',
            'status' => AGTSubmission::STATUS_PENDING,
            'retry_count' => 5,
            'error_code' => 'COMMS',
            'error_message' => 'A versão 1.2 do schema já não é suportada.',
        ]);

        $estado = $this->getJson(self::RAIZ . '/estado?ambiente=' . $ambiente)->assertOk();
        $linha = collect($estado->json('submissoes'))->firstWhere('id', $s->id);
        $this->assertTrue($linha['esgotada']);
        $this->assertFalse($linha['pode_reenviar']);

        // O documento deste ensaio não existe: o reenvio pára aí, mas o
        // contador já está reposto — é isso que o botão promete.
        $this->postJson(self::RAIZ . '/submissoes/' . $s->id . '/reenviar', ['ambiente' => $ambiente, 'repor' => true])->assertStatus(422);

        $s->refresh();
        $this->assertSame(0, (int) $s->retry_count);
        $this->assertNull($s->error_code);
        $this->assertSame(AGTSubmission::STATUS_PENDING, $s->status);
    }

    /** @test */
    public function nao_repoe_a_submissao_de_outra_empresa(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $ambiente = $this->definicoes()->agt_environment ?: 'sandbox';

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra ' . uniqid(), 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999), 'email' => uniqid() . '@x.ao',
        ]);
        $alheia = AGTSubmission::create([
            'tenant_id' => $outra->id, 'agt_environment' => $ambiente,
            'document_type' => 'App\\Models\\Invoicing\\SalesInvoice', 'document_id' => 1,
            'document_number' => 'FT B/000001', 'document_type_code' => 'FT',
            'status' => AGTSubmission::STATUS_PENDING, 'retry_count' => 5, 'error_code' => 'COMMS',
        ]);

        $this->postJson(self::RAIZ . '/submissoes/' . $alheia->id . '/reenviar', ['ambiente' => $ambiente, 'repor' => true])->assertStatus(422);

        $this->assertSame(5, (int) $alheia->fresh()->retry_count);
    }

    /** @test */
    public function a_ficha_do_contribuinte_grava_e_valida_os_emails(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');

        $this->getJson(self::RAIZ . '/contribuinte')->assertOk()->assertJsonPath('data.tax_registration_number', (string) $this->tenant->nif);

        $this->postJson(self::RAIZ . '/contribuinte', ['agt_establishment_number' => 'LOJA 2', 'agt_notification_emails' => 'bom@empresa.ao, isto-nao-e-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('agt_notification_emails');

        $this->postJson(self::RAIZ . '/contribuinte', ['agt_establishment_number' => 'LOJA 2', 'agt_notification_emails' => 'bom@empresa.ao', 'agt_eac_code' => '47730'])
            ->assertOk()
            ->assertJsonPath('data.agt_establishment_number', 'LOJA 2');

        $d = $this->definicoes()->fresh();
        $this->assertSame('LOJA 2', $d->agt_establishment_number);
        $this->assertSame('bom@empresa.ao', $d->agt_notification_emails);
        $this->assertSame('47730', $d->agt_eac_code);
    }

    /**
     * A CHAVE PRIVADA «DO MODO ANTIGO»: sobe, e NUNCA MAIS DESCE.
     *
     * O ecrã de sempre deixava colá-la e há empresas que ainda assinam com
     * ela; a migração para React tinha-a deixado de fora. Volta com a regra
     * que a torna segura: o que a API diz dela é se está instalada — o PEM não
     * aparece na resposta nem por pedaços. Uma chave privada devolvida numa
     * resposta HTTP fica no registo do navegador, na cache e em qualquer
     * intermediário pelo caminho, e a partir daí qualquer um assina documentos
     * fiscais em nome da empresa.
     *
     * @test
     */
    public function a_chave_do_modo_antigo_guarda_se_e_nunca_volta_na_resposta(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');

        $this->getJson(self::RAIZ . '/contribuinte')->assertOk()->assertJsonPath('data.chave_legado', false);

        $resposta = $this->postJson(self::RAIZ . '/contribuinte/chave', ['contributor_private_key' => self::PEM])
            ->assertOk()
            ->assertJsonPath('data.chave_legado', true);

        // Guardada onde o resto do sistema arruma as chaves.
        Storage::disk('local')->assertExists($this->caminhoDaChaveLegado());
        $this->assertTrue(
            (bool) @openssl_pkey_get_private(Storage::disk('local')->get($this->caminhoDaChaveLegado())),
            'o que ficou no disco tem de continuar a ser uma chave legível'
        );

        // E não voltou: nem o cabeçalho do PEM, nem uma linha do seu corpo.
        $primeiraLinhaDoCorpo = trim(explode("\n", trim(self::PEM))[1]);

        foreach ([$resposta, $this->getJson(self::RAIZ . '/contribuinte')->assertOk()] as $r) {
            $corpo = str_replace('\/', '/', $r->content());

            $this->assertStringNotContainsString('PRIVATE KEY', $corpo);
            $this->assertStringNotContainsString($primeiraLinhaDoCorpo, $corpo);
        }

        // A ficha continua a dizer que está lá — é tudo o que ela diz.
        $this->getJson(self::RAIZ . '/contribuinte')->assertOk()->assertJsonPath('data.chave_legado', true);
    }

    /** @test */
    public function a_chave_do_modo_antigo_remove_se(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');

        $this->postJson(self::RAIZ . '/contribuinte/chave', ['contributor_private_key' => self::PEM])->assertOk();

        $this->postJson(self::RAIZ . '/contribuinte/chave/remover')
            ->assertOk()
            ->assertJsonPath('data.chave_legado', false);

        Storage::disk('local')->assertMissing($this->caminhoDaChaveLegado());
    }

    /**
     * UM TEXTO QUE NÃO É PEM NÃO SE GRAVA.
     *
     * Gravado sem olhar, o defeito só aparecia na primeira factura que a AGT
     * recusasse — longe daqui, e sem ninguém ligar uma coisa à outra.
     *
     * @test
     */
    public function um_texto_que_nao_e_pem_nao_passa_a_chave_do_modo_antigo(): void
    {
        $this->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');

        $this->postJson(self::RAIZ . '/contribuinte/chave', ['contributor_private_key' => 'isto não é uma chave'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('contributor_private_key');

        Storage::disk('local')->assertMissing($this->caminhoDaChaveLegado());
    }

    /** Ver a ficha é uma permissão; instalar a chave que assina é outra. @test */
    public function sem_permissao_de_editar_a_chave_do_modo_antigo_nao_se_toca(): void
    {
        $this->comPermissoes('invoicing.agt.view');

        $this->postJson(self::RAIZ . '/contribuinte/chave', ['contributor_private_key' => self::PEM])->assertForbidden();
        $this->postJson(self::RAIZ . '/contribuinte/chave/remover')->assertForbidden();

        Storage::disk('local')->assertMissing($this->caminhoDaChaveLegado());
    }
}
