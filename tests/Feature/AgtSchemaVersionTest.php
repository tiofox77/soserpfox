<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTPayloadBuilder;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase as TestCase;

/**
 * A versão do schema que vai para a AGT tem UMA fonte.
 *
 * 2026-09-02: a AGT recusou em produção «A versão 1.2 do schema já não é
 * suportada […] atualize o campo schemaVersion para uma versão 2.x». A
 * constante do AGTPayloadBuilder dizia 1.2 — e o AGTClient repetia '1.2'
 * por conta própria em seis sítios (séries, listagem, estado…). Mudar a
 * constante e deixar os seis para trás era o E39 outra vez: os documentos
 * numa versão, as séries noutra.
 */
class AgtSchemaVersionTest extends TestCase
{
    /** @test */
    public function a_versao_por_omissao_e_a_que_a_agt_pede(): void
    {
        $this->assertSame('2.0', AGTPayloadBuilder::SCHEMA_VERSION);
    }

    /**
     * A ARMADILHA DO DEFAULT. A coluna nasceu com DEFAULT '1.2' e todas as
     * empresas tinham '1.2' escrito — a constante nunca ganhava. Uma empresa
     * nova tem de nascer SEM versão própria, e a coluna sem DEFAULT.
     *
     * @test
     */
    public function uma_empresa_nova_nao_fica_presa_a_uma_versao(): void
    {
        $definicoes = InvoicingSettings::forTenant($this->tenant->id);

        $this->assertNull($definicoes->agt_schema_version, 'a empresa nasceu com uma versão de schema escrita');

        $coluna = DB::selectOne(
            'SELECT COLUMN_DEFAULT, IS_NULLABLE FROM information_schema.COLUMNS'
            .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['invoicing_settings', 'agt_schema_version']
        );

        $this->assertNull($coluna->COLUMN_DEFAULT, 'a coluna voltou a ter DEFAULT');
        $this->assertSame('YES', $coluna->IS_NULLABLE);
    }

    /**
     * Guarda ao nível da fonte: o cliente não pode voltar a escrever a versão
     * à mão. Todas as ocorrências de schemaVersion no AGTClient têm de
     * apontar para a constante (com a definição da empresa a ganhar).
     *
     * @test
     */
    public function o_cliente_nao_escreve_a_versao_a_mao(): void
    {
        $fonte = file_get_contents(app_path('Services/AGT/AGTClient.php'));

        preg_match_all("/'schemaVersion'\s*=>\s*([^,\n]+)/", $fonte, $ocorrencias);

        $this->assertNotEmpty($ocorrencias[1], 'o AGTClient deixou de enviar schemaVersion?');

        foreach ($ocorrencias[1] as $expressao) {
            $this->assertStringContainsString(
                'AGTPayloadBuilder::SCHEMA_VERSION',
                $expressao,
                "schemaVersion escrito à mão no AGTClient: {$expressao}"
            );
        }

        $this->assertStringNotContainsString("'1.2'", $fonte, 'ficou um 1.2 literal no AGTClient');
    }

    /** O construtor de payloads também não pode ter outra versão escondida. */
    public function test_o_construtor_de_payloads_usa_a_constante(): void
    {
        $fonte = file_get_contents(app_path('Services/AGT/AGTPayloadBuilder.php'));

        preg_match_all("/=>\s*'(\d\.\d)'/", $fonte, $literais);

        $this->assertSame([], $literais[1], 'versão literal no AGTPayloadBuilder: '.implode(', ', $literais[1]));
    }
}
