<?php

namespace App\Services\Tenants;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A empresa está viva ou é só uma linha na base?
 *
 * A lista de empresas mostrava nome, plano e número de utilizadores — e com
 * isso não se distingue um cliente que factura todos os dias de um que se
 * registou, abriu duas páginas e nunca mais voltou. As duas linhas são iguais,
 * e as decisões que dependem disso (a quem telefonar, quem está a fugir, que
 * plano vale a pena manter) tomavam-se às cegas.
 *
 * COMO ISTO NÃO REBENTA A PÁGINA: uma consulta por sinal, agrupada por
 * empresa, para o conjunto de empresas da página. Seis consultas fixas, esteja
 * a listar dez empresas ou mil — e não seis por empresa, que era o caminho
 * fácil e o que torna uma lista inutilizável ao fim de trinta clientes.
 */
class SinaisDeVida
{
    /** Quantos dias contam como "recente". */
    private const JANELA = 30;

    /**
     * O que já se perguntou ao esquema, nesta execução.
     *
     * Cada Schema::hasTable/hasColumn é uma ida à base. Sem isto, os seis
     * sinais custavam dezoito consultas em vez de seis — dois terços do
     * trabalho eram a perguntar se as colunas existem, uma e outra vez.
     */
    private static array $esquema = [];

    /**
     * Os sinais de cada empresa, indexados pelo id.
     *
     * @param  iterable<int>  $empresas
     */
    public static function para(iterable $empresas): Collection
    {
        $ids = collect($empresas)->map(fn ($e) => is_object($e) ? (int) $e->id : (int) $e)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $desde = now()->subDays(self::JANELA);

        $facturas   = self::contarPorEmpresa('invoicing_sales_invoices', $ids, $desde);
        $artigos    = self::contarPorEmpresa('invoicing_products', $ids);
        $movimentos = self::contarPorEmpresa('invoicing_stock_movements', $ids, $desde);
        $entradas   = self::ultimaEntrada($ids);
        $utilizador = self::utilizadores($ids);

        return $ids->mapWithKeys(function ($id) use ($facturas, $artigos, $movimentos, $entradas, $utilizador) {
            $sinais = [
                'facturas_30d'   => (int) ($facturas[$id] ?? 0),
                'artigos'        => (int) ($artigos[$id] ?? 0),
                'movimentos_30d' => (int) ($movimentos[$id] ?? 0),
                'ultima_entrada' => $entradas[$id] ?? null,
                'utilizadores'   => (int) ($utilizador[$id]['total'] ?? 0),
                'entraram_30d'   => (int) ($utilizador[$id]['recentes'] ?? 0),
            ];

            $sinais['estado'] = self::classificar($sinais);

            return [$id => (object) $sinais];
        });
    }

    /**
     * Viva, a arrancar, a adormecer, ou morta.
     *
     * A ordem das perguntas é a que interessa: quem factura está vivo, ponto.
     * Quem não factura mas entrou esta semana ainda está a montar a casa —
     * não é a mesma coisa que quem não aparece há um mês.
     */
    private static function classificar(array $s): array
    {
        if ($s['facturas_30d'] > 0) {
            return ['chave' => 'activa', 'texto' => 'A facturar', 'cor' => 'green'];
        }

        if ($s['movimentos_30d'] > 0 || $s['entraram_30d'] > 0) {
            // Mexe no sistema mas não emite documentos: ou está a montar, ou
            // usa só uma parte (stock, RH) e isso também é usar.
            return $s['artigos'] > 0
                ? ['chave' => 'a_usar',    'texto' => 'A usar',      'cor' => 'blue']
                : ['chave' => 'a_montar',  'texto' => 'A montar',    'cor' => 'amber'];
        }

        if ($s['artigos'] > 0 || $s['ultima_entrada']) {
            return ['chave' => 'adormecida', 'texto' => 'Adormecida', 'cor' => 'orange'];
        }

        return ['chave' => 'vazia', 'texto' => 'Nunca usou', 'cor' => 'red'];
    }


    /** Existe, e ja o perguntei? */
    private static function temTabela(string $tabela): bool
    {
        return self::$esquema["t:$tabela"] ??= Schema::hasTable($tabela);
    }

    private static function temColuna(string $tabela, string $coluna): bool
    {
        return self::$esquema["c:$tabela.$coluna"] ??= (self::temTabela($tabela) && Schema::hasColumn($tabela, $coluna));
    }

    /**
     * Contagem por empresa numa tabela, opcionalmente só do que é recente.
     *
     * Tolera tabelas que não existam nesta instalação: um módulo por instalar
     * não pode fazer rebentar a lista de empresas.
     */
    private static function contarPorEmpresa(string $tabela, Collection $ids, $desde = null): array
    {
        if (!self::temColuna($tabela, 'tenant_id')) {
            return [];
        }

        $q = DB::table($tabela)
            ->select('tenant_id', DB::raw('COUNT(*) as total'))
            ->whereIn('tenant_id', $ids);

        if ($desde && self::temColuna($tabela, 'created_at')) {
            $q->where('created_at', '>=', $desde);
        }

        if (self::temColuna($tabela, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        return $q->groupBy('tenant_id')->pluck('total', 'tenant_id')->all();
    }

    /** A última vez que alguém desta empresa entrou no sistema. */
    private static function ultimaEntrada(Collection $ids): array
    {
        if (!self::temColuna('users', 'last_login_at')) {
            return [];
        }

        // Pelo pivot: um utilizador pode pertencer a várias empresas, e o
        // users.tenant_id é só a empresa de origem.
        //
        // O agregado precisa de um alias: `pluck(DB::raw('MAX(...)'))` procura
        // uma propriedade com o nome da expressão inteira e devolve null para
        // toda a gente — o ecrã dizia "nunca entrou" de todas as empresas,
        // incluindo as que estavam a facturar naquele minuto.
        return DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->whereIn('tenant_user.tenant_id', $ids)
            ->whereNotNull('users.last_login_at')
            ->groupBy('tenant_user.tenant_id')
            ->select('tenant_user.tenant_id', DB::raw('MAX(users.last_login_at) as ultima'))
            ->pluck('ultima', 'tenant_id')
            ->map(fn ($d) => $d ? \Carbon\Carbon::parse($d) : null)
            ->all();
    }

    /** Quantos utilizadores tem, e quantos apareceram no último mês. */
    private static function utilizadores(Collection $ids): array
    {
        $temLogin = self::temColuna('users', 'last_login_at');
        $desde    = now()->subDays(self::JANELA);

        $linhas = DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->whereIn('tenant_user.tenant_id', $ids)
            ->whereNull('users.deleted_at')
            ->select(
                'tenant_user.tenant_id',
                DB::raw('COUNT(DISTINCT users.id) as total'),
                $temLogin
                    ? DB::raw("SUM(CASE WHEN users.last_login_at >= '{$desde->toDateTimeString()}' THEN 1 ELSE 0 END) as recentes")
                    : DB::raw('0 as recentes')
            )
            ->groupBy('tenant_user.tenant_id')
            ->get();

        return $linhas->mapWithKeys(fn ($l) => [
            (int) $l->tenant_id => ['total' => (int) $l->total, 'recentes' => (int) $l->recentes],
        ])->all();
    }
}
