<?php

namespace App\Services\Copias;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * QUE TABELAS SÃO «OS DADOS DE UMA EMPRESA».
 *
 * Lido do esquema e não de uma lista escrita à mão — uma lista fixa envelhece
 * em silêncio, e o que falta é o que a empresa julga ter na cópia:
 *
 *  · DIRECTAS: as tabelas com `tenant_id` (176 em 2026-09-15);
 *  · FILHAS: as que não têm `tenant_id` mas apontam, por chave estrangeira, para
 *    uma directa — as linhas das facturas, dos salários, das ordens de serviço.
 *    Uma cópia sem elas tinha as facturas sem linhas.
 *
 * FICAM DE FORA, de propósito (`FORA`): o que é da plataforma e não da empresa
 * (subscrição, pedidos de plano, módulos, facturas da plataforma), as contas das
 * pessoas (uma pessoa pertence a várias empresas — repor a senha de ontem numa
 * empresa mudava-a em todas), a trilha de auditoria e as comunicações à AGT
 * (a prova do que aconteceu não volta atrás), e os registos de envio (voltar
 * atrás faria sair outra vez os avisos já enviados).
 */
class MapaDaEmpresa
{
    public const FORA = [
        // Contas e pertença
        'users', 'tenant_user', 'user_invitations', 'reposicoes_de_pin', 'pwa_devices',
        // Plataforma: subscrição, planos, facturação da plataforma
        'subscriptions', 'orders', 'invoices', 'tenant_module', 'avisos_de_subscricao',
        'licencas_emitidas', 'license_requests', 'app_update_targets', 'feature_requests',
        'platform_message_reads', 'restaurant_venue_limit_requests', 'support_tickets',
        // Prova e histórico que não voltam atrás
        'audit_trail', 'agt_submissions', 'agt_communication_logs', 'erros_do_sistema',
        'analytics_events', 'email_logs', 'sms_logs', 'notification_sends', 'agent_messages',
        // As próprias cópias
        'agendas_de_copia', 'destinos_de_copia', 'copias_de_seguranca', 'restauros_de_copia', 'envios_de_copia',
    ];

    /** Tabelas que referenciam contas de pessoas: só se repõem linhas de pessoas que existem. */
    public const COM_PESSOAS = ['model_has_roles' => 'model_id', 'model_has_permissions' => 'model_id'];

    private static ?array $memoria = null;

    /**
     * O esquema lido, guardado enquanto as migrações não mudarem: um dia de
     * cada vez, no máximo. Ver `esquema()`.
     */
    private const VALIDADE_SEGUNDOS = 86400;

    /**
     * @return array{directas: list<string>, filhas: array<string, array{coluna: string, pai: string}>}
     */
    public static function tabelas(): array
    {
        if (self::$memoria !== null) {
            return self::$memoria;
        }

        $esquema = self::esquema();

        $directas = collect($esquema)
            ->filter(fn ($t) => isset($t['colunas']['tenant_id']))
            ->keys()
            ->reject(fn ($t) => $t === 'tenants' || in_array($t, self::FORA, true))->values()->all();

        // As chaves estrangeiras, com o que interessa para escolher a do dono:
        // se a coluna aceita nulo e em que posição está.
        $chaves = collect($esquema)->flatMap(fn ($t, $tabela) => collect($t['chaves'])->map(fn ($k) => (object) [
            't' => $tabela,
            'c' => $k[0],
            'p' => $k[1],
            'nulo' => $t['colunas'][$k[0]]['nulo'] ?? 'YES',
            'pos' => $t['colunas'][$k[0]]['pos'] ?? 999,
        ]));

        // Filhas e netas: duas passagens. Das chaves possíveis escolhe-se a que
        // NÃO aceita nulo (a das linhas da factura é obrigatória; a da factura
        // numa linha de ordem de serviço não é), e depois a primeira da tabela.
        $filhas = [];
        for ($passagem = 0; $passagem < 2; $passagem++) {
            foreach ($chaves->groupBy('t') as $tabela => $ks) {
                if (in_array($tabela, $directas, true) || isset($filhas[$tabela]) || in_array($tabela, self::FORA, true) || $tabela === 'tenants') {
                    continue;
                }
                $dono = $ks->filter(fn ($k) => in_array($k->p, $directas, true) || isset($filhas[$k->p]))
                    ->sortBy(fn ($k) => [$k->nulo === 'YES' ? 1 : 0, (int) $k->pos])
                    ->first();
                if ($dono) {
                    $filhas[$tabela] = ['coluna' => $dono->c, 'pai' => $dono->p];
                }
            }
        }

        return self::$memoria = ['directas' => $directas, 'filhas' => $filhas];
    }

    public static function esquecer(): void
    {
        self::$memoria = null;

        try {
            Cache::forget(self::chave());
        } catch (\Throwable) {
            // Sem cache não há nada a esquecer.
        }
    }

    /**
     * O ESQUEMA DA BASE, TABELA A TABELA — e guardado (22/09/2026).
     *
     * Lia-se do `information_schema` com duas consultas à base INTEIRA em cada
     * cópia (uma a cada cinco minutos, o dia todo). Num MySQL partilhado essas
     * consultas abrem todas as tabelas de uma vez: a das chaves estrangeiras
     * esbarrou duas vezes no `max_statement_time` no dia 22 — uma delas a meio
     * da rajada em que os pedidos esgotaram as 30 ligações do alojamento.
     *
     * Agora: `SHOW FULL TABLES` e, por tabela, `SHOW COLUMNS` e `SHOW CREATE
     * TABLE` — cada um abre só aquela tabela. E o resultado fica na cache,
     * chaveado pela última migração: só se volta a ler quando o esquema muda,
     * ou ao fim de um dia.
     *
     * @return array<string, array{colunas: array<string, array{tipo: string, extra: string, nulo: string, pos: int}>, chaves: list<array{0: string, 1: string}>}>
     */
    private static function esquema(): array
    {
        try {
            return Cache::remember(self::chave(), self::VALIDADE_SEGUNDOS, fn () => self::lerEsquema());
        } catch (\Throwable) {
            // Uma cache que não responde não pode impedir a cópia.
            return self::lerEsquema();
        }
    }

    private static function chave(): string
    {
        try {
            $versao = (int) DB::table('migrations')->max('id');
        } catch (\Throwable) {
            $versao = 0;
        }

        return 'copias:esquema:' . md5(DB::getDatabaseName() . '|' . $versao);
    }

    private static function lerEsquema(): array
    {
        $esquema = [];

        foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $linha) {
            $tabela = (string) array_values((array) $linha)[0];
            $esquema[$tabela] = [
                'colunas' => self::lerEsquemaDe($tabela),
                'chaves' => self::lerChavesDe($tabela),
            ];
        }

        return $esquema;
    }

    /** As colunas de UMA tabela — `SHOW COLUMNS` abre só essa. */
    private static function lerEsquemaDe(string $tabela): array
    {
        $colunas = [];

        foreach (DB::select('SHOW COLUMNS FROM `' . str_replace('`', '``', $tabela) . '`') as $pos => $c) {
            $colunas[$c->Field] = [
                // O DATA_TYPE do information_schema: «bigint(20) unsigned» →
                // «bigint», «enum('a','b')» → «enum».
                'tipo' => strtolower((string) strtok((string) $c->Type, '( ')),
                'extra' => (string) $c->Extra,
                'nulo' => $c->Null === 'YES' ? 'YES' : 'NO',
                'pos' => $pos + 1,
            ];
        }

        return $colunas;
    }

    /**
     * As chaves estrangeiras de UMA tabela, do seu CREATE TABLE: uma linha
     * «CONSTRAINT `x` FOREIGN KEY (`a`, `b`) REFERENCES `pai` (…)» por chave.
     *
     * @return list<array{0: string, 1: string}> [coluna, tabela-pai]
     */
    private static function lerChavesDe(string $tabela): array
    {
        $criar = (array) (DB::select('SHOW CREATE TABLE `' . str_replace('`', '``', $tabela) . '`')[0] ?? []);
        $chaves = [];

        preg_match_all('/FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)`/', (string) ($criar['Create Table'] ?? ''), $fks, PREG_SET_ORDER);
        foreach ($fks as $fk) {
            foreach (explode(',', $fk[1]) as $coluna) {
                $chaves[] = [trim($coluna, ' `'), $fk[2]];
            }
        }

        return $chaves;
    }

    /** Todas as tabelas do âmbito, directas primeiro (a ordem da exportação). */
    public static function todas(): array
    {
        $m = self::tabelas();

        return array_merge($m['directas'], array_keys($m['filhas']));
    }

    /**
     * As colunas de uma tabela que se copiam, com o tipo (para saber o que é
     * binário).
     *
     * SEM AS COLUNAS GERADAS: o MySQL calcula-as e recusa quem lhes escreva
     * («The value specified for generated column 'padrao_unico' is not
     * allowed», nas séries). Não vão na cópia nem se repõem.
     */
    public static function colunas(string $tabela): array
    {
        // Do esquema guardado (ver `esquema()`); uma tabela que ainda lá não
        // esteja lê-se à parte — só ela.
        $colunas = self::esquema()[$tabela]['colunas'] ?? self::lerEsquemaDe($tabela);

        // Só VIRTUAL/STORED GENERATED (o MariaDB diz PERSISTENT): um
        // `DEFAULT CURRENT_TIMESTAMP` aparece como «DEFAULT_GENERATED» e é uma
        // coluna normal, que tem de ir.
        return collect($colunas)
            ->reject(fn ($c) => (bool) preg_match('/\b(VIRTUAL|STORED|PERSISTENT) GENERATED\b/i', $c['extra']))
            ->map(fn ($c) => $c['tipo'])->all();
    }

    /** A consulta das linhas de uma tabela que são desta empresa. */
    public static function consulta(string $tabela, int $tenantId)
    {
        $mapa = self::tabelas();

        if (in_array($tabela, $mapa['directas'], true)) {
            return DB::table($tabela)->where('tenant_id', $tenantId);
        }

        $f = $mapa['filhas'][$tabela];

        return DB::table($tabela)->whereIn($f['coluna'], self::consulta($f['pai'], $tenantId)->select('id'));
    }
}
