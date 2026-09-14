<?php

namespace App\Services\Tenants;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A empresa está viva ou é só uma linha na base?
 *
 * A lista de empresas mostrava nome, plano e número de utilizadores — e com
 * isso não se distingue um cliente que factura todos os dias de um que se
 * registou, abriu duas páginas e nunca mais voltou. As duas linhas são iguais,
 * e as decisões que dependem disso (a quem telefonar, quem está a fugir, que
 * plano vale a pena manter) tomavam-se às cegas.
 *
 * O QUE ESTAVA ERRADO NA PRIMEIRA VERSÃO (visto na produção em 2026-09-14):
 *
 *  · «A MONTAR» ERA QUALQUER INSCRIÇÃO DO ÚLTIMO MÊS. A inscrição faz login, e
 *    um login nos últimos 30 dias bastava. 34 empresas «a montar», das quais
 *    25 entraram só no dia em que se inscreveram e 12 estavam desactivadas.
 *  · «A FACTURAR» ERA UMA FACTURA EM 30 DIAS, rascunho incluído. Uma empresa
 *    com uma factura de há 26 dias e sem aparecer há três semanas estava
 *    «a facturar».
 *  · A ÚLTIMA ENTRADA ERA DA PESSOA, NÃO DA EMPRESA. O dono de três empresas a
 *    trabalhar numa acendia as outras duas («entrou há 15 min» numa empresa
 *    parada há dez dias). Agora lê-se `tenant_user.ultimo_acesso_em`, gravado
 *    na empresa activa.
 *  · SÓ A FACTURAÇÃO CONTAVA COMO USO. Restaurante, hotel, tesouraria, RH ou
 *    propostas não existiam para a lista.
 *  · AS DESACTIVADAS ENTRAVAM NAS CONTAS de «a montar» e «nunca usaram».
 *
 * COMO ISTO NÃO REBENTA A PÁGINA: uma consulta para TODAS as tabelas de
 * actividade (um UNION ALL de agregados por empresa), uma para as pessoas, uma
 * para as empresas e uma, só na primeira vez, ao esquema. Quatro consultas,
 * esteja a listar dez empresas ou mil.
 */
class SinaisDeVida
{
    /** A janela das contagens que o cartão mostra («/30d»). */
    public const JANELA = 30;

    /** O que conta como «agora» para decidir o estado. */
    public const RECENTE = 14;

    /**
     * USO: o que só existe se alguém estiver a trabalhar com o sistema. As
     * facturas contam à parte (são o sinal mais forte) e os movimentos de
     * stock também, porque o cartão os mostra.
     */
    private const USO = [
        'invoicing_stock_movements', 'invoicing_sales_proformas', 'invoicing_sales_quotes',
        'invoicing_receipts', 'invoicing_credit_notes', 'invoicing_debit_notes',
        'invoicing_purchase_invoices', 'invoicing_purchase_orders', 'invoicing_transport_guides',
        'invoicing_stock_counts', 'treasury_transactions', 'restaurant_orders', 'hotel_reservations',
        'salon_appointments', 'workshop_work_orders', 'hr_payrolls', 'crm_leads',
    ];

    /** MONTAGEM: preparar a casa — catálogo, clientes, fornecedores. */
    private const MONTAGEM = ['invoicing_products', 'invoicing_clients', 'invoicing_suppliers'];

    private const FACTURAS = 'invoicing_sales_invoices';

    /**
     * As colunas das tabelas que isto lê, perguntadas UMA vez por processo.
     *
     * Cada Schema::hasColumn é uma ida à base: com vinte tabelas eram sessenta
     * consultas só para saber se as colunas existem.
     *
     * @var array<string, true>|null
     */
    private static ?array $colunas = null;

    /**
     * Os sinais de cada empresa, indexados pelo id.
     *
     * @param  iterable<int|object>  $empresas
     */
    public static function para(iterable $empresas): Collection
    {
        $ids = collect($empresas)->map(fn ($e) => is_object($e) ? (int) $e->id : (int) $e)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $agora = now();
        $desde = $agora->copy()->subDays(self::JANELA);

        $fichas = DB::table('tenants')->whereIn('id', $ids)->get(['id', 'created_at', 'is_active'])->keyBy('id');
        $actividade = self::actividade($ids, $desde);
        $pessoas = self::pessoas($ids, $desde);

        return $ids->mapWithKeys(function ($id) use ($fichas, $actividade, $pessoas) {
            $a = $actividade[$id] ?? [];
            $p = $pessoas[$id] ?? ['total' => 0, 'recentes' => 0, 'ultima' => null];
            $ficha = $fichas[$id] ?? null;

            $sinais = [
                'facturas_30d'     => (int) ($a['facturas']['n30'] ?? 0),
                'artigos'          => (int) ($a['invoicing_products']['total'] ?? 0),
                'movimentos_30d'   => (int) ($a['invoicing_stock_movements']['n30'] ?? 0),
                'operacoes_30d'    => (int) collect(self::USO)->sum(fn ($t) => $a[$t]['n30'] ?? 0),
                'ultima_factura'   => self::data($a['facturas']['ultima'] ?? null),
                'ultima_operacao'  => self::maisRecente(array_map(fn ($t) => $a[$t]['ultima'] ?? null, self::USO)),
                'ultima_montagem'  => self::maisRecente(array_map(fn ($t) => $a[$t]['ultima'] ?? null, self::MONTAGEM)),
                'ultima_entrada'   => self::data($p['ultima']),
                'utilizadores'     => (int) $p['total'],
                'entraram_30d'     => (int) $p['recentes'],
                'criada_em'        => self::data($ficha?->created_at),
                'activa'           => $ficha ? (bool) $ficha->is_active : true,
            ];

            $sinais['ultima_actividade'] = self::maisRecente([
                $sinais['ultima_factura'], $sinais['ultima_operacao'], $sinais['ultima_montagem'], $sinais['ultima_entrada'],
            ]);
            $sinais['estado'] = self::classificar($sinais);

            return [$id => (object) $sinais];
        });
    }

    /**
     * O estado, pela ordem de perguntas que interessa a quem telefona.
     *
     *   desactivada — fora das contas de actividade: foi a plataforma que a desligou;
     *   activa      — facturou nos últimos 14 dias;
     *   a_usar      — trabalha no sistema (stock, tesouraria, restaurante, RH…) sem
     *                 facturar, ou já facturou e continua a entrar;
     *   a_montar    — nunca facturou e está a começar: inscreveu-se, preparou o
     *                 catálogo ou entrou nos últimos 14 dias;
     *   adormecida  — tem dados, mas não há sinal nenhum há mais de 14 dias;
     *   vazia       — não registou nada. Entrar e sair não é usar: a própria
     *                 inscrição faz login.
     */
    public static function classificar(array $s): array
    {
        $recente = now()->subDays(self::RECENTE);
        $depois = fn (?Carbon $d) => $d !== null && $d->gte($recente);

        if (! ($s['activa'] ?? true)) {
            return ['chave' => 'desactivada', 'texto' => 'Desactivada', 'cor' => 'slate'];
        }

        if ($depois($s['ultima_factura'] ?? null)) {
            return ['chave' => 'activa', 'texto' => 'A facturar', 'cor' => 'green'];
        }

        if ($depois($s['ultima_operacao'] ?? null)) {
            return ['chave' => 'a_usar', 'texto' => 'A usar', 'cor' => 'blue'];
        }

        if ($depois($s['criada_em'] ?? null) || $depois($s['ultima_montagem'] ?? null) || $depois($s['ultima_entrada'] ?? null)) {
            return ($s['ultima_factura'] ?? null)
                ? ['chave' => 'a_usar', 'texto' => 'A usar', 'cor' => 'blue']
                : ['chave' => 'a_montar', 'texto' => 'A montar', 'cor' => 'amber'];
        }

        if (($s['ultima_factura'] ?? null) || ($s['ultima_operacao'] ?? null) || ($s['ultima_montagem'] ?? null) || ($s['artigos'] ?? 0) > 0) {
            return ['chave' => 'adormecida', 'texto' => 'Adormecida', 'cor' => 'orange'];
        }

        return ['chave' => 'vazia', 'texto' => 'Nunca usou', 'cor' => 'red'];
    }

    /**
     * A frase que diz PORQUÊ aquele estado — o cartão sozinho («A usar») não
     * deixa ver se é uma empresa que factura menos ou uma que só entra.
     */
    public static function motivo(object $s): string
    {
        $quando = fn (?Carbon $d) => self::distancia($d) ?? '—';
        $recente = now()->subDays(self::RECENTE);

        return match ($s->estado['chave']) {
            'desactivada' => $s->ultima_actividade
                ? __('Desactivada · último sinal :quando', ['quando' => $quando($s->ultima_actividade)])
                : __('Desactivada · nunca registou nada'),
            'activa' => __('Última factura :quando', ['quando' => $quando($s->ultima_factura)]),
            'a_usar' => $s->ultima_operacao && $s->ultima_operacao->gte($recente)
                ? ($s->ultima_factura
                    ? __('Trabalha no sistema (última operação :quando), sem facturas há :factura', [
                        'quando' => $quando($s->ultima_operacao),
                        'factura' => self::distancia($s->ultima_factura, absoluta: true),
                    ])
                    : __('Trabalha no sistema (última operação :quando), sem nunca ter facturado', ['quando' => $quando($s->ultima_operacao)]))
                : __('Continua a entrar (:quando), mas a última factura foi :factura', [
                    'quando' => $quando(self::maisRecente([$s->ultima_entrada, $s->ultima_montagem])),
                    'factura' => $quando($s->ultima_factura),
                ]),
            'a_montar' => $s->criada_em && $s->criada_em->gte($recente)
                ? __('Inscreveu-se :quando, ainda sem facturas', ['quando' => $quando($s->criada_em)])
                : __('A preparar (último sinal :quando), ainda sem facturas', ['quando' => $quando(self::maisRecente([$s->ultima_montagem, $s->ultima_entrada]))]),
            'adormecida' => $s->ultima_actividade
                ? __('Parada · último sinal :quando', ['quando' => $quando($s->ultima_actividade)])
                : __('Tem catálogo, mas ninguém lhe mexe'),
            default => __('Inscreveu-se :quando e nunca registou nada', ['quando' => $quando($s->criada_em)]),
        };
    }

    /**
     * «há 26 dias», e não «há 3 semanas».
     *
     * A regra dos estados corta aos 14 dias: «há 2 semanas» tanto podia ser 13
     * como 20, e quem lê não percebia porque é que duas empresas com a mesma
     * frase estavam em cartões diferentes.
     */
    public static function distancia(?Carbon $d, bool $absoluta = false): ?string
    {
        if ($d === null) {
            return null;
        }

        return $d->diffForHumans(array_filter([
            'skip' => ['week'],
            'syntax' => $absoluta ? \Carbon\CarbonInterface::DIFF_ABSOLUTE : null,
        ], fn ($v) => $v !== null));
    }

    /**
     * Toda a actividade numa consulta: um agregado por (tabela, empresa).
     *
     * @return array<int, array<string, array{ultima: ?string, n30: int, total: int}>>
     */
    private static function actividade(Collection $ids, Carbon $desde): array
    {
        $partes = [];

        foreach (array_merge([self::FACTURAS], self::USO, self::MONTAGEM) as $tabela) {
            if (! self::tem($tabela, 'tenant_id') || ! self::tem($tabela, 'created_at')) {
                continue;
            }

            $q = DB::table($tabela)
                ->selectRaw('tenant_id, ? AS grupo, MAX(created_at) AS ultima, SUM(created_at >= ?) AS n30, COUNT(*) AS total', [
                    $tabela === self::FACTURAS ? 'facturas' : $tabela,
                    $desde->toDateTimeString(),
                ])
                ->whereIn('tenant_id', $ids)
                ->groupBy('tenant_id');

            if (self::tem($tabela, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }

            // Um rascunho não foi emitido: não prova que a empresa factura.
            if ($tabela === self::FACTURAS && self::tem($tabela, 'status')) {
                $q->where('status', '<>', 'draft');
            }

            $partes[] = $q;
        }

        if ($partes === []) {
            return [];
        }

        $consulta = array_shift($partes);
        foreach ($partes as $p) {
            $consulta->unionAll($p);
        }

        $por = [];
        foreach ($consulta->get() as $l) {
            $por[(int) $l->tenant_id][$l->grupo] = [
                'ultima' => $l->ultima,
                'n30' => (int) $l->n30,
                'total' => (int) $l->total,
            ];
        }

        return $por;
    }

    /**
     * Quantas pessoas tem, quantas entraram no último mês, e quando foi a
     * última vez que alguém entrou NESTA empresa.
     *
     * O acesso por empresa (`tenant_user.ultimo_acesso_em`) só existe desde
     * 2026-09-14. Até lá, quem pertence a UMA empresa só é lido pelo
     * `last_login_at` — é o mesmo número. Quem pertence a várias não tem como
     * ser atribuído a nenhuma, e fica de fora até à primeira visita.
     *
     * @return array<int, array{total: int, recentes: int, ultima: ?string}>
     */
    private static function pessoas(Collection $ids, Carbon $desde): array
    {
        if (! self::tem('users', 'last_login_at')) {
            $acesso = 'NULL';
        } elseif (self::tem('tenant_user', 'ultimo_acesso_em')) {
            $acesso = 'COALESCE(tenant_user.ultimo_acesso_em, CASE WHEN quantas.empresas = 1 THEN users.last_login_at END)';
        } else {
            $acesso = 'users.last_login_at';
        }

        $quantas = DB::table('tenant_user')->select('user_id', DB::raw('COUNT(*) AS empresas'))->groupBy('user_id');

        // O agregado precisa de um alias: `pluck(DB::raw('MAX(...)'))` procura
        // uma propriedade com o nome da expressão inteira e devolvia null para
        // toda a gente — o ecrã dizia "nunca entrou" de todas as empresas.
        $linhas = DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->joinSub($quantas, 'quantas', 'quantas.user_id', '=', 'tenant_user.user_id')
            ->whereIn('tenant_user.tenant_id', $ids)
            ->when(self::tem('users', 'deleted_at'), fn ($q) => $q->whereNull('users.deleted_at'))
            ->groupBy('tenant_user.tenant_id')
            ->selectRaw("tenant_user.tenant_id, COUNT(DISTINCT users.id) AS total, MAX({$acesso}) AS ultima, SUM(({$acesso}) >= ?) AS recentes", [$desde->toDateTimeString()])
            ->get();

        return $linhas->mapWithKeys(fn ($l) => [
            (int) $l->tenant_id => ['total' => (int) $l->total, 'recentes' => (int) $l->recentes, 'ultima' => $l->ultima],
        ])->all();
    }

    /** A tabela tem a coluna? Uma consulta ao esquema, a primeira vez. */
    private static function tem(string $tabela, string $coluna): bool
    {
        if (self::$colunas === null) {
            $tabelas = array_merge([self::FACTURAS, 'users', 'tenant_user'], self::USO, self::MONTAGEM);

            self::$colunas = DB::table('information_schema.columns')
                ->where('table_schema', DB::getDatabaseName())
                ->whereIn('table_name', $tabelas)
                ->get([DB::raw('table_name AS t'), DB::raw('column_name AS c')])
                ->mapWithKeys(fn ($l) => ["{$l->t}.{$l->c}" => true])
                ->all();
        }

        return isset(self::$colunas["{$tabela}.{$coluna}"]);
    }

    /** Esquecer o esquema — para os testes que o mudam a meio. */
    public static function esquecerEsquema(): void
    {
        self::$colunas = null;
    }

    private static function data(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return $valor instanceof \DateTimeInterface ? Carbon::instance($valor) : Carbon::parse($valor);
    }

    /** @param  array<int, mixed>  $datas */
    private static function maisRecente(array $datas): ?Carbon
    {
        return collect($datas)->map(fn ($d) => self::data($d))->filter()->sortByDesc(fn (Carbon $d) => $d->getTimestamp())->first();
    }
}
