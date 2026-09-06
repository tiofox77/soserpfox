<?php

namespace Tests\Feature;

use App\Services\Audit\AuditRecorder;
use Tests\TenantTestCase;

/**
 * A API DA AUDITORIA, para o ecrã em React.
 *
 * Só leitura, por construção. O que estes ensaios guardam: exige a
 * permissão das definições; lista o que ficou registado com a frase
 * legível; abre uma linha com os campos; e a cadeia verifica-se a pedido.
 */
class ApiDaAuditoriaParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/auditoria';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    /** @test */
    public function so_se_le_com_a_permissao_das_definicoes(): void
    {
        $this->getJson(self::RAIZ)->assertForbidden();
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();
        $this->getJson(self::RAIZ . '/integridade')->assertForbidden();

        $this->comPermissoes('invoicing.settings.view');

        $this->getJson(self::RAIZ)->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);
    }

    /** @test */
    public function lista_o_que_ficou_registado_e_abre_uma_linha(): void
    {
        $this->comPermissoes('invoicing.settings.view');

        app(AuditRecorder::class)->exportou('Ensaio', 'csv', null, ['de' => '2026-01-01', 'ate' => '2026-01-31']);

        $lista = $this->getJson(self::RAIZ . '?evento=exportacao')->assertOk();
        $this->assertGreaterThanOrEqual(1, $lista->json('meta.total'));

        $linha = $lista->json('data.0');
        $this->assertSame('exportacao', $linha['evento']);
        $this->assertNotEmpty($linha['frase']);
        $this->assertNotEmpty($linha['quando']);

        $this->getJson(self::RAIZ . '/' . $linha['id'])->assertOk()->assertJsonStructure(['data' => ['frase', 'campos']]);

        $this->assertContains('exportacao', $this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('eventos'));
    }

    /** @test */
    public function os_filtros_apertam(): void
    {
        $this->comPermissoes('invoicing.settings.view');

        app(AuditRecorder::class)->exportou('Ensaio', 'csv', null, []);

        $this->assertSame(0, $this->getJson(self::RAIZ . '?evento=deleted')->assertOk()->json('meta.total'));
        $this->assertSame(0, $this->getJson(self::RAIZ . '?de=2099-01-01')->assertOk()->json('meta.total'));
        $this->assertGreaterThanOrEqual(1, $this->getJson(self::RAIZ . '?ate=' . now()->toDateString())->assertOk()->json('meta.total'));
    }

    /** @test */
    public function a_cadeia_verifica_se_a_pedido(): void
    {
        $this->comPermissoes('invoicing.settings.view');

        app(AuditRecorder::class)->exportou('Ensaio', 'csv', null, []);
        app(AuditRecorder::class)->exportou('Outro ensaio', 'xml', null, []);

        $this->getJson(self::RAIZ . '/integridade')->assertOk()->assertJsonPath('ok', true)->assertJsonPath('total', 0);
    }
}
