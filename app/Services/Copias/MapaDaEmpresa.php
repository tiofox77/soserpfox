<?php

namespace App\Services\Copias;

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
     * @return array{directas: list<string>, filhas: array<string, array{coluna: string, pai: string}>}
     */
    public static function tabelas(): array
    {
        if (self::$memoria !== null) {
            return self::$memoria;
        }

        $directas = collect(DB::select(
            'SELECT TABLE_NAME AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ?',
            ['tenant_id']
        ))->pluck('t')->reject(fn ($t) => $t === 'tenants' || in_array($t, self::FORA, true))->values()->all();

        // As chaves estrangeiras, com o que interessa para escolher a do dono:
        // se a coluna aceita nulo e em que posição está.
        $chaves = collect(DB::select(
            'SELECT k.TABLE_NAME AS t, k.COLUMN_NAME AS c, k.REFERENCED_TABLE_NAME AS p, c.IS_NULLABLE AS nulo, c.ORDINAL_POSITION AS pos
               FROM information_schema.KEY_COLUMN_USAGE k
               JOIN information_schema.COLUMNS c
                 ON c.TABLE_SCHEMA = k.TABLE_SCHEMA AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME
              WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL'
        ));

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
        return collect(DB::select(
            'SELECT COLUMN_NAME AS c, DATA_TYPE AS d, EXTRA AS e FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$tabela]
        // Só VIRTUAL/STORED GENERATED: um `DEFAULT CURRENT_TIMESTAMP` aparece
        // como «DEFAULT_GENERATED» e é uma coluna normal, que tem de ir.
        ))->reject(fn ($l) => (bool) preg_match('/\b(VIRTUAL|STORED) GENERATED\b/i', (string) $l->e))
            ->mapWithKeys(fn ($l) => [$l->c => $l->d])->all();
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
