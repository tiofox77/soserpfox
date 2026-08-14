<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * A contagem de documentos por serie, que e o que autoriza — ou nao — desmarcar
 * uma serie padrao.
 *
 * O formatNumber ja teve tres formas, e a contagem tem de as reconhecer todas:
 *
 *   ate 2025-12-16   "FR SOSFR 2025/000123"   segundo bloco = series_code inteiro
 *   3 a 6 Ago 2026   "SOS FR/000123"          primeiro bloco 'SOS' fixo
 *   desde 2026-08-06 "FR FR/000123"           forma actual
 *
 * A versao anterior ancorava o padrao no PRIMEIRO bloco com o prefixo de hoje,
 * pelo que nenhuma das duas primeiras formas batia certo: uma serie veterana
 * dava zero documentos. Numa serie por estrear esse zero e verdade; numa que
 * emitiu milhares e cegueira — e era esse zero que autorizava desmarca-la.
 */
class SeriePadraoPorDocumentosTest extends TenantTestCase
{
    private const INDICE = 'invoicing_series_padrao_unico_unique';

    /** Credenciais guardadas com a aplicação viva, para repor o índice depois de ela morrer. */
    private array $ligacao = [];

    private bool $largou = false;

    /**
     * O índice único torna o estado avariado impossível — que é o objectivo
     * dele — e por isso o teste não consegue montá-lo. Larga-se enquanto se
     * reproduz o que há em produção, onde o índice ainda NÃO entrou: enquanto
     * houver casos por decidir a migração recusa-se a criá-lo, e é nesse
     * intervalo que este comando tem de saber trabalhar.
     *
     * Aqui e não no setUp: no MySQL um DROP INDEX faz commit implícito, e o
     * setUp corre já dentro da transacção do DatabaseTransactions. O commit
     * levava-a com ele e tudo o que o teste gravasse — empresas, utilizadores,
     * séries, facturas — ficava para trás na base de testes, teste após teste.
     * O setUpTraits corre com a aplicação de pé mas antes de a transacção
     * começar, que é a única janela onde este DDL não estraga nada.
     */
    protected function setUpTraits()
    {
        $this->ligacao = config('database.connections.' . config('database.default'));

        if ($this->indiceExiste()) {
            DB::statement('DROP INDEX ' . self::INDICE . ' ON invoicing_series');
            $this->largou = true;
        }

        return parent::setUpTraits();
    }

    /**
     * Repor DEPOIS do parent: é ele que desfaz a transacção. Criar o índice
     * antes disso era outro commit implícito, com o mesmo estrago do outro lado.
     * Como a aplicação já morreu, a ligação é feita à mão.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if (!$this->largou) {
            return;
        }

        $this->largou = false;

        try {
            $pdo = new \PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s', $this->ligacao['host'], $this->ligacao['port'], $this->ligacao['database']),
                $this->ligacao['username'],
                $this->ligacao['password']
            );

            $pdo->exec('CREATE UNIQUE INDEX ' . self::INDICE . ' ON invoicing_series (padrao_unico)');
        } catch (\Throwable $e) {
            // Não estoirar o teste por causa da arrumação, mas também não deixar
            // a base de testes sem o índice sem ninguém dar por isso.
            fwrite(STDERR, PHP_EOL . '  AVISO: não foi possível repor ' . self::INDICE . ': ' . $e->getMessage() . PHP_EOL);
        }
    }

    private function indiceExiste(): bool
    {
        return (int) (DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            ['invoicing_series', self::INDICE]
        )->total ?? 0) > 0;
    }

    private function serie(string $codigo, int $proximo, bool $padrao = true, ?string $agt = null): InvoicingSeries
    {
        return InvoicingSeries::create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'pos',
            'series_code' => $codigo,
            'name' => $codigo,
            'prefix' => 'FR',
            'next_number' => $proximo,
            'number_padding' => 6,
            'is_default' => $padrao,
            'is_active' => true,
            'agt_series_id' => $agt,
        ]);
    }

    /** @param int|null $serieId null = linha antiga, de antes de a coluna existir */
    private function factura(?int $serieId, string $numero): void
    {
        DB::table('invoicing_sales_invoices')->insert([
            'tenant_id'      => $this->tenant->id,
            'series_id'      => $serieId,
            'invoice_number' => $numero,
            'client_id'      => $this->cliente->id,
            'invoice_date'   => now()->toDateString(),
            'created_by'     => $this->user->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function contar(InvoicingSeries $s): array
    {
        $metodo = new \ReflectionMethod(\App\Console\Commands\CorrigirSeriesPadrao::class, 'documentosEmitidos');
        $metodo->setAccessible(true);

        return $metodo->invoke(app(\App\Console\Commands\CorrigirSeriesPadrao::class), $s);
    }

    private function limpar(): void
    {
        InvoicingSeries::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('document_type', 'pos')->delete();
    }

    // ---- as tres formas historicas do numero ----------------------------

    /** Ate 2025-12-16: o segundo bloco era o series_code INTEIRO, e o ano vinha depois. */
    public function test_conta_a_forma_antiga_com_o_codigo_inteiro_e_o_ano(): void
    {
        $this->limpar();
        $s = $this->serie('SOSFR', 4);

        $this->factura(null, 'FR SOSFR 2025/000001');
        $this->factura(null, 'FR SOSFR 2025/000002');
        $this->factura(null, 'FR SOSFR 2025/000003');

        $this->assertSame(3, $this->contar($s)['gravados']);
    }

    /** 3 a 6 de Agosto de 2026: o primeiro bloco era 'SOS' fixo. */
    public function test_conta_a_forma_com_sos_no_primeiro_bloco(): void
    {
        $this->limpar();
        $s = $this->serie('SOSFR', 3);

        $this->factura(null, 'SOS FR/000001');
        $this->factura(null, 'SOS FR/000002');

        $this->assertSame(2, $this->contar($s)['gravados']);
    }

    /** A forma de hoje. */
    public function test_conta_a_forma_actual(): void
    {
        $this->limpar();
        $s = $this->serie('SOSFR', 2);

        $this->factura(null, 'FR FR/000001');

        $this->assertSame(1, $this->contar($s)['gravados']);
    }

    /**
     * Registada na AGT DEPOIS de ja ter emitido: os antigos trazem o codigo, os
     * novos o codigo AGT. A serie e a mesma e os dois contam.
     */
    public function test_conta_dos_dois_lados_do_registo_na_agt(): void
    {
        $this->limpar();
        $s = $this->serie('SOSFR', 4, true, 'FR7626S6286N');

        $this->factura(null, 'FR FR/000001');                  // antes do registo
        $this->factura(null, 'FR FR7626S6286N/000002');        // depois
        $this->factura(null, 'FR FR7626S6286N/000003');

        $this->assertSame(3, $this->contar($s)['gravados']);
    }

    /** O series_id manda, e nao depende da forma do numero. */
    public function test_a_ligacao_pelo_series_id_conta_seja_qual_for_o_numero(): void
    {
        $this->limpar();
        $s = $this->serie('SOSFR', 2);

        $this->factura($s->id, 'FORMA QUE NUNCA EXISTIU 9999');

        $this->assertSame(1, $this->contar($s)['gravados']);
    }

    /** O mesmo documento nao pode ser contado pelas duas vias. */
    public function test_o_mesmo_documento_nao_conta_a_dobrar(): void
    {
        $this->limpar();
        $s = $this->serie('SOSFR', 2);

        $this->factura($s->id, 'FR FR/000001');   // bate pelo series_id E pelo numero

        $this->assertSame(1, $this->contar($s)['gravados']);
    }

    /** Uma serie nao pode ficar com os documentos da irma. */
    public function test_nao_apanha_documentos_de_outra_serie(): void
    {
        $this->limpar();
        $a  = $this->serie('A', 3);
        $fr = $this->serie('SOSFR', 1, false);

        $this->factura(null, 'FR A/000001');
        $this->factura(null, 'FR A/000002');

        $this->assertSame(2, $this->contar($a)['gravados']);
        $this->assertSame(0, $this->contar($fr)['gravados'], 'apanhou documentos da irma');
    }

    // ---- a decisao ------------------------------------------------------

    /**
     * O caso que quase correu mal: a veterana com os documentos todos em forma
     * antiga, ao lado da irma nova com um documento recente. Antes da correccao
     * a veterana dava zero e era ela a desmarcada.
     */
    public function test_a_serie_veterana_em_forma_antiga_nao_e_desmarcada(): void
    {
        $this->limpar();
        $veterana = $this->serie('SOSFR', 2313);
        $nova     = $this->serie('A', 2);

        for ($i = 1; $i <= 3; $i++) {
            $this->factura(null, sprintf('FR SOSFR 2025/%06d', $i));
        }
        $this->factura($nova->id, 'FR A/000001');

        $this->assertSame(3, $this->contar($veterana)['gravados'], 'a veterana devia ver os seus documentos');

        $this->artisan('series:corrigir-padrao', [
            '--tenant' => $this->tenant->id,
            '--aplicar' => true,
        ])->assertSuccessful();

        $this->assertTrue((bool) $veterana->fresh()->is_default, 'desmarcou a serie que emitiu');
        $this->assertTrue((bool) $nova->fresh()->is_default, 'ambas emitiram: era decisao humana');
    }

    /** Uma com documentos e as outras a zero continua a ser decidida. */
    public function test_uma_so_com_documentos_ainda_decide(): void
    {
        $this->limpar();
        $emUso = $this->serie('A', 2);
        $vazia = $this->serie('SOSFR', 1);

        $this->factura($emUso->id, 'FR A/000001');

        $this->artisan('series:corrigir-padrao', [
            '--tenant' => $this->tenant->id,
            '--aplicar' => true,
        ])->assertSuccessful();

        $this->assertTrue((bool) $emUso->fresh()->is_default);
        $this->assertFalse((bool) $vazia->fresh()->is_default);
    }

    /** Contador andado sem documentos: o comando NAO decide, e e de proposito. */
    public function test_contador_andado_sem_documentos_nao_autoriza_desmarcar(): void
    {
        $this->limpar();
        $emUso = $this->serie('A', 2);
        $vazia = $this->serie('SOSFR', 385);   // contador em 384, nada gravado

        $this->factura($emUso->id, 'FR A/000001');

        $this->artisan('series:corrigir-padrao', [
            '--tenant' => $this->tenant->id,
            '--aplicar' => true,
        ])->assertSuccessful();

        $this->assertTrue((bool) $emUso->fresh()->is_default);
        $this->assertTrue((bool) $vazia->fresh()->is_default, 'um zero nao e prova de que nao emitiu');
    }

    /** Ambas por estrear: nao ha sinal nenhum, fica para humano. */
    public function test_ambas_a_zero_ficam_para_humano(): void
    {
        $this->limpar();
        $uma   = $this->serie('SOSRC', 1);
        $outra = $this->serie('SOSRCA', 1);

        $this->artisan('series:corrigir-padrao', [
            '--tenant' => $this->tenant->id,
            '--aplicar' => true,
        ])->assertSuccessful();

        $this->assertTrue((bool) $uma->fresh()->is_default);
        $this->assertTrue((bool) $outra->fresh()->is_default);
    }

    /** A saida mostra os numeros em bruto, que e o que permite decidir. */
    public function test_a_saida_mostra_as_formas_de_numero_gravadas(): void
    {
        $this->limpar();
        $a  = $this->serie('A', 3);
        $fr = $this->serie('SOSFR', 3);

        $this->factura($a->id, 'FR A/000001');
        $this->factura(null, 'FR SOSFR 2025/000001');

        $this->artisan('series:corrigir-padrao', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain('números realmente gravados')
            ->expectsOutputToContain('FR A/#')
            ->expectsOutputToContain('FR SOSFR #/#')
            ->assertSuccessful();
    }
}
