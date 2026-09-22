<?php

namespace Tests\Feature;

use App\Services\Copias\MapaDaEmpresa;
use App\Support\ConectorMysqlComFolego;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

/**
 * A BASE SEM LIGAÇÕES LIVRES (22/09/2026).
 *
 * O alojamento dá 30 ligações ao utilizador da base. Numa tarde de 22/09 os
 * pedidos esgotaram-nas, e o cliente viu um 500 com o SQL e o código do
 * servidor na página de entrada. Prova-se:
 *  · a ligação volta a tentar quando o tecto está cheio, e só nesse caso;
 *  · quem continua sem ligação vê «ocupado, tente daqui a pouco» (503), sem
 *    SQL, e o registo leva uma linha curta em vez de um rasto de 20 KB;
 *  · as cópias de segurança já não leem o esquema inteiro a cada cópia.
 */
class BaseSemLigacoesLivresTest extends TestCase
{
    private const TECTO = "SQLSTATE[HY000] [1226] User 'soserpp' has exceeded the 'max_user_connections' resource (current value: 30)";

    private function conector(array $falhas): ConectorMysqlComFolego
    {
        return new class($falhas) extends ConectorMysqlComFolego {
            public int $tentativas = 0;

            public array $esperas = [];

            public function __construct(private array $falhas)
            {
            }

            protected function ligar($dsn, array $config, array $options)
            {
                $this->tentativas++;
                $falha = array_shift($this->falhas);
                if ($falha) {
                    throw new PDOException($falha);
                }

                return new \PDO('sqlite::memory:');
            }

            protected function esperar(int $milissegundos): void
            {
                $this->esperas[] = $milissegundos;
            }
        };
    }

    public function test_o_mysql_usa_o_conector_com_folego(): void
    {
        $this->assertInstanceOf(ConectorMysqlComFolego::class, app('db.connector.mysql'));
    }

    public function test_com_o_tecto_cheio_espera_e_volta_a_tentar(): void
    {
        $c = $this->conector([self::TECTO, 'SQLSTATE[HY000] [1040] Too many connections']);

        $this->assertInstanceOf(\PDO::class, $c->createConnection('mysql:host=x', [], []));
        $this->assertSame(3, $c->tentativas);
        $this->assertSame([250, 600], $c->esperas);
    }

    public function test_desiste_depois_das_tres_esperas(): void
    {
        $c = $this->conector(array_fill(0, 4, self::TECTO));

        try {
            $c->createConnection('mysql:host=x', [], []);
            $this->fail('devia ter desistido');
        } catch (PDOException $e) {
            $this->assertStringContainsString('1226', $e->getMessage());
        }

        $this->assertSame(4, $c->tentativas);
        $this->assertSame(ConectorMysqlComFolego::ESPERAS_MS, $c->esperas);
    }

    public function test_uma_senha_errada_nao_espera(): void
    {
        $c = $this->conector(["SQLSTATE[HY000] [1045] Access denied for user 'x'@'localhost'"]);

        $this->expectException(PDOException::class);

        try {
            $c->createConnection('mysql:host=x', [], []);
        } finally {
            $this->assertSame(1, $c->tentativas);
            $this->assertSame([], $c->esperas);
        }
    }

    /* ─── O que o cliente vê ──────────────────────────────────────────── */

    private function rotaQueEsbarraNoTecto(): void
    {
        Route::middleware('web')->match(['GET', 'POST'], '/__ensaio/tecto', fn () => throw new QueryException(
            'mysql', 'select * from `sessions` where `id` = ? limit 1', ['x'], new PDOException(self::TECTO),
        ));
        Route::middleware('web')->get('/__ensaio/outro-erro', fn () => throw new QueryException(
            'mysql', 'select 1', [], new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'x' doesn't exist"),
        ));
    }

    public function test_quem_esbarra_no_tecto_ve_ocupado_sem_sql(): void
    {
        $this->rotaQueEsbarraNoTecto();

        $r = $this->get('/__ensaio/tecto');

        $r->assertStatus(503)->assertHeader('Retry-After', '15');
        $r->assertSee('Tente de novo dentro de alguns segundos', false);
        $r->assertSee('http-equiv="refresh"', false);
        $r->assertDontSee('sessions', false);
        $r->assertDontSee('max_user_connections', false);
        $r->assertDontSee('soserpp', false);
    }

    public function test_um_formulario_nao_se_reenvia_sozinho(): void
    {
        $this->rotaQueEsbarraNoTecto();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/__ensaio/tecto')
            ->assertStatus(503)
            ->assertDontSee('http-equiv="refresh"', false);
    }

    public function test_o_react_recebe_503_em_json(): void
    {
        $this->rotaQueEsbarraNoTecto();

        $this->getJson('/__ensaio/tecto')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '10')
            ->assertJsonPath('message', 'O sistema está com muitos pedidos neste momento. Tente de novo dentro de alguns segundos.');
    }

    public function test_os_outros_erros_da_base_seguem_como_sempre(): void
    {
        $this->rotaQueEsbarraNoTecto();

        $this->get('/__ensaio/outro-erro')->assertStatus(500);
    }

    public function test_o_registo_leva_uma_linha_curta_e_nao_o_rasto(): void
    {
        $this->rotaQueEsbarraNoTecto();
        $registos = [];
        Event::listen(MessageLogged::class, function (MessageLogged $m) use (&$registos) {
            $registos[] = [$m->level, $m->message, $m->context];
        });

        $this->get('/__ensaio/tecto')->assertStatus(503);

        $this->assertSame([['warning', 'Base de dados sem ligações livres [1226]: GET /__ensaio/tecto', []]], $registos);
    }

    /* ─── As cópias de segurança ──────────────────────────────────────── */

    public function test_o_mapa_das_copias_le_o_esquema_uma_vez_e_guarda(): void
    {
        MapaDaEmpresa::esquecer();
        $primeiro = MapaDaEmpresa::tabelas();

        // Um processo novo (a memória estática vazia), com a cache cheia.
        (new \ReflectionProperty(MapaDaEmpresa::class, 'memoria'))->setValue(null, null);

        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });

        $segundo = MapaDaEmpresa::tabelas();
        MapaDaEmpresa::colunas('invoicing_sales_invoices');

        $this->assertSame($primeiro, $segundo);
        $this->assertSame([], array_values(array_filter($consultas, fn ($sql) => stripos($sql, 'information_schema') !== false || stripos($sql, 'SHOW ') === 0)),
            'nem information_schema nem SHOW: vem tudo da cache');

        MapaDaEmpresa::esquecer();
    }

    public function test_o_mapa_nunca_le_o_esquema_inteiro(): void
    {
        MapaDaEmpresa::esquecer();

        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });

        MapaDaEmpresa::tabelas();

        $this->assertSame([], array_values(array_filter($consultas, fn ($sql) => stripos($sql, 'information_schema') !== false)));
        $this->assertContains('invoicing_sales_invoices', MapaDaEmpresa::tabelas()['directas']);
        $this->assertSame('invoicing_sales_invoices', MapaDaEmpresa::tabelas()['filhas']['invoicing_sales_invoice_items']['pai'] ?? null);

        MapaDaEmpresa::esquecer();
    }
}
