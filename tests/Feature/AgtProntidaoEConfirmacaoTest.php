<?php

namespace Tests\Feature;

use App\Models\AGT\AGTSubmission;
use App\Models\AuditTrail;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * TROCAR DE AMBIENTE: pronto para produção, e confirmado.
 *
 *  · Activar produção só com o par RSA do contribuinte deixava a empresa «em
 *    produção» com as credenciais e o número de certificação partilhados de
 *    homologação — a AGT real recusava cada documento. A prontidão exige os
 *    PRÓPRIOS de produção, e a recusa traz a lista item a item.
 *  · Qualquer troca pede `confirmar: true`, e diz quantas submissões de cada
 *    ambiente ficam à espera.
 *  · O super admin passa pela mesma porta — era, das duas, a que escrevia a
 *    coluna directamente.
 */
class AgtProntidaoEConfirmacaoTest extends TenantTestCase
{
    use AgtDeEnsaio;

    private const RAIZ = '/api/v1/invoicing/react/agt';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');
        Http::fake(['*' => Http::response(['requestID' => 'x', 'resultCode' => '0'], 200)]);

        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
        $this->emitirEm('sandbox');
    }

    private function item(array $lista, string $chave): array
    {
        return collect($lista)->firstWhere('chave', $chave);
    }

    public function test_activar_producao_sem_prontidao_recusa_com_a_lista(): void
    {
        // O par do contribuinte está lá — antes bastava isto.
        $this->instalarChavesDoContribuinte('production');
        $this->semProdutorDeProducao();

        $r = $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production', 'confirmar' => true])->assertStatus(422);

        $lista = $r->json('prontidao');
        $this->assertSame(['rsa_contribuinte', 'produtor', 'rsa_produtor', 'certificado', 'nif'], array_column($lista, 'chave'));
        $this->assertTrue($this->item($lista, 'rsa_contribuinte')['ok']);
        $this->assertFalse($this->item($lista, 'produtor')['ok'], 'credenciais partilhadas ou vazias não servem para produção');
        $this->assertFalse($this->item($lista, 'certificado')['ok']);
        $this->assertTrue($this->item($lista, 'nif')['ok']);
        $this->assertStringContainsString('Falta', $r->json('message'));
        $this->assertSame('sandbox', $this->definicoesAgt()->fresh()->agt_environment);
    }

    public function test_as_credenciais_globais_de_homologacao_nao_contam_como_de_producao(): void
    {
        $this->instalarChavesDoContribuinte('production');
        $this->instalarProdutor('production');
        // Só as partilhadas (sem ambiente): o AGTProducerStore recorre a elas,
        // mas não são PRÓPRIAS de produção.
        config([
            'services.agt.production.username' => '',
            'services.agt.production.password' => '',
            'services.agt.username' => 'partilhado',
            'services.agt.password' => 'partilhado',
        ]);

        $r = $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production', 'confirmar' => true])->assertStatus(422);

        $this->assertFalse($this->item($r->json('prontidao'), 'produtor')['ok']);
    }

    public function test_o_estado_traz_a_mesma_prontidao(): void
    {
        $this->semProdutorDeProducao();

        $estado = $this->getJson(self::RAIZ . '/estado')->assertOk();

        $this->assertSame(['rsa_contribuinte', 'produtor', 'rsa_produtor', 'certificado', 'nif'], array_column($estado->json('prontidao_producao'), 'chave'));
        $this->assertFalse($this->item($estado->json('prontidao_producao'), 'rsa_contribuinte')['ok']);

        $this->instalarChavesDoContribuinte('production');
        $this->instalarProdutor('production');

        $pronta = $this->getJson(self::RAIZ . '/estado')->assertOk()->json('prontidao_producao');
        $this->assertSame([true], array_values(array_unique(array_column($pronta, 'ok'))), 'tudo instalado, tudo verde');
    }

    public function test_trocar_sem_confirmar_recusa_e_diz_quantas_ficam_a_espera(): void
    {
        $this->instalarChavesDoContribuinte('production');
        $this->instalarProdutor('production');
        $this->submissaoAgt(['agt_environment' => 'sandbox']);
        $this->submissaoAgt(['agt_environment' => 'sandbox', 'status' => AGTSubmission::STATUS_SUBMITTED, 'agt_reference' => 'R1']);
        $this->submissaoAgt(['agt_environment' => 'sandbox', 'status' => AGTSubmission::STATUS_VALIDATED]);
        $this->submissaoAgt(['agt_environment' => 'production']);

        $r = $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production'])->assertStatus(422);

        $r->assertJsonPath('confirmar_necessario', true)
            ->assertJsonPath('pendentes_por_ambiente.sandbox', 2)
            ->assertJsonPath('pendentes_por_ambiente.production', 1);
        $this->assertSame('sandbox', $this->definicoesAgt()->fresh()->agt_environment);

        // «false», «0», vazio: tudo é um não.
        foreach ([false, 0, '0', ''] as $nao) {
            $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production', 'confirmar' => $nao])->assertStatus(422);
        }

        $ok = $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'production', 'confirmar' => true])->assertOk();
        $ok->assertJsonPath('ambiente_activo', 'production')->assertJsonPath('pendentes_por_ambiente.sandbox', 2);
        $this->assertStringContainsString('ficam à espera', $ok->json('message'));
    }

    public function test_a_troca_fica_na_trilha(): void
    {
        $this->emitirEm('production');

        $this->postJson(self::RAIZ . '/ambiente', ['ambiente' => 'sandbox', 'confirmar' => true])->assertOk();

        app(AuditRecorder::class)->despejar();
        $linha = AuditTrail::where('tenant_id', $this->tenant->id)->where('event', 'agt.ambiente.activado')->latest('id')->first();

        $this->assertNotNull($linha, 'mudar o ambiente fiscal é um acto que tem de deixar rasto');
        $this->assertSame('production', $linha->metadata['de']);
        $this->assertSame('sandbox', $linha->metadata['para']);
    }

    public function test_o_super_admin_passa_pelas_mesmas_guardas(): void
    {
        $dono = User::create(['name' => 'Dono', 'email' => 'dono_' . uniqid() . '@exemplo.ao', 'password' => bcrypt('x')]);
        $dono->forceFill(['is_super_admin' => true])->save();
        $api = '/api/v1/plataforma/react/software/ambiente';

        $this->instalarChavesDoContribuinte('production');
        $this->semProdutorDeProducao();

        // Sem prontidão: a mesma lista.
        $this->actingAs($dono)->putJson($api, ['empresa' => $this->tenant->id, 'ambiente' => 'production', 'confirmar' => true])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'prontidao' => [['chave', 'rotulo', 'ok']]]);

        // Pronta, mas sem confirmar: recusa.
        $this->instalarProdutor('production');
        $this->actingAs($dono)->putJson($api, ['empresa' => $this->tenant->id, 'ambiente' => 'production'])
            ->assertStatus(422)
            ->assertJsonPath('confirmar_necessario', true);
        $this->assertSame('sandbox', $this->definicoesAgt()->fresh()->agt_environment);

        $this->actingAs($dono)->putJson($api, ['empresa' => $this->tenant->id, 'ambiente' => 'production', 'confirmar' => true])
            ->assertOk()
            ->assertJsonPath('ambiente_activo', 'production');
        $this->assertSame('production', $this->definicoesAgt()->fresh()->agt_environment);
    }
}
