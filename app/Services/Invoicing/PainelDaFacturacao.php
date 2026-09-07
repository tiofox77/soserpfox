<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Advance;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Services\Invoicing\Relatorios\Periodo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * OS NÚMEROS DO PAINEL DA FACTURAÇÃO — num sítio só.
 *
 * Nasceu ao migrar o painel para React. A alternativa era o controlador da API
 * repetir as mesmas contas do componente Livewire, e a partir daí os dois
 * ecrãs davam números diferentes ao primeiro ajuste — que é exactamente o
 * defeito que este código já apanhou no PIN de turno, na Geografia, no
 * TaxResolver e no papel de impressão do POS.
 *
 * O PERÍODO É ARGUMENTO, NÃO ESTADO DO ECRÃ. O painel de sempre tinha um
 * selector — semana, mês, ano — e a migração perdeu-o: o ecrã em React passou a
 * mostrar sempre o ano. Voltou, mas por aqui: quem escolhe é o ecrã, quem conta
 * é este serviço, e assim os cartões, o gráfico e o título não podem discordar
 * entre si sobre o período que estão a mostrar. Os atalhos são os MESMOS dos
 * relatórios (`Relatorios\Periodo`); uma segunda lista de períodos acabaria,
 * como sempre acaba, a divergir da primeira.
 *
 * A REGRA QUE ISTO GUARDA. Conta-se pelo SALDO, nunca pelo nome do estado.
 * Nomear os estados que contam foi o que partiu o painel: contava `pending` e
 * `partially_paid`, e uma factura `sent` ou `overdue` desaparecia do cartão.
 * Medido nesta base: o cartão dizia 76 mil por cobrar quando havia 15 milhões,
 * e zero vencidas quando havia 14,5 milhões. Escreve-se ao contrário — o que
 * está liquidado sai, tudo o resto está por cobrar.
 *
 * E tudo passa pelo `escopoDoAutor`: quem só vê os documentos que emitiu vê um
 * painel só com os seus. Um total que inclua as vendas dos colegas é a mesma
 * fuga de informação que a lista teria.
 */
class PainelDaFacturacao
{
    /**
     * O QUE NÃO SE COBRA.
     *
     * O RASCUNHO está aqui: uma factura por acabar ainda não foi emitida a
     * ninguém, e sem certificação da AGT não é uma factura — é um documento
     * em curso. Contá-la como dívida do cliente inchava o painel com dinheiro
     * que ninguém deve, e punha este número a contradizer a lista de
     * facturas, que já não a conta (ver `SomasDasFacturas`).
     */
    private const SEM_NADA_A_COBRAR = ['draft', 'cancelled', 'paid', 'credited'];

    /**
     * Os atalhos que o painel oferece — um subconjunto dos dos relatórios.
     *
     * Fica de fora o `custom` (não há aqui duas caixas de data) e o `ytd`, que
     * para o painel é o mesmo que o ano: ninguém factura no futuro.
     */
    public const PERIODOS = ['today', 'week', 'month', 'quarter', 'year', 'last_year'];

    /** O mês, como o painel de sempre abria. */
    public const PERIODO_OMISSAO = 'month';

    /** Um atalho que este painel conheça — qualquer outra coisa é o de omissão. */
    public static function periodo(?string $pedido): string
    {
        return in_array($pedido, self::PERIODOS, true) ? $pedido : self::PERIODO_OMISSAO;
    }

    /**
     * O rótulo do atalho, JÁ NA LÍNGUA de quem está a olhar.
     *
     * Sai do servidor e não do ecrã, como os nomes dos meses: assim o título do
     * gráfico e a caixa de escolha escrevem «Este mês» da mesma maneira.
     */
    public static function rotulo(string $periodo): string
    {
        return __(Periodo::ATALHOS[self::periodo($periodo)]);
    }

    /** As opções da caixa de escolha, na ordem em que se lêem. */
    public static function opcoesDePeriodo(): array
    {
        return array_map(
            fn (string $p) => ['valor' => $p, 'rotulo' => self::rotulo($p)],
            self::PERIODOS
        );
    }

    /** O intervalo do atalho. É o mesmo cálculo dos relatórios. */
    public static function intervalo(string $periodo): array
    {
        return Periodo::datas(self::periodo($periodo));
    }

    /**
     * O PERÍODO ANTERIOR EQUIVALENTE, para a comparação do cartão.
     *
     * O ano compara-se com o ano passado ATÉ AO MESMO DIA. Comparar um ano a
     * meio com um ano inteiro daria sempre uma queda que não existe — era a
     * nota que a leitura ano a ano já trazia, e vale para os outros períodos.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string} de, até, rótulo
     */
    public static function anterior(string $periodo): array
    {
        $hoje = Carbon::today();

        return match (self::periodo($periodo)) {
            'today' => [$hoje->copy()->subDay()->startOfDay(), $hoje->copy()->subDay()->endOfDay(), __('Ontem')],
            'week' => [$hoje->copy()->subWeek()->startOfWeek(), $hoje->copy()->subWeek()->endOfWeek(), __('Semana passada')],
            'quarter' => [$hoje->copy()->subQuarter()->startOfQuarter(), $hoje->copy()->subQuarter()->endOfQuarter(), __('Trimestre passado')],
            'year' => [$hoje->copy()->subYear()->startOfYear(), $hoje->copy()->subYear()->endOfDay(), __('Ano passado')],
            'last_year' => [$hoje->copy()->subYears(2)->startOfYear(), $hoje->copy()->subYears(2)->endOfYear(), __('Ano anterior')],
            default => [$hoje->copy()->subMonth()->startOfMonth(), $hoje->copy()->subMonth()->endOfMonth(), __('Mês passado')],
        };
    }

    /**
     * Tudo o que o painel mostra, para o período pedido.
     *
     * @return array{periodo: array, stats: array, documents: array, invoiceStatus: array,
     *               pendingInvoices: \Illuminate\Support\Collection,
     *               topClients: \Illuminate\Support\Collection,
     *               recentActivities: \Illuminate\Support\Collection}
     */
    public function numeros(int $tenantId, string $periodo = self::PERIODO_OMISSAO): array
    {
        $periodo = self::periodo($periodo);
        [$de, $ate] = self::intervalo($periodo);
        [$dePassado, $atePassado, $rotuloPassado] = self::anterior($periodo);

        $stats = [
            'total_invoiced' => $this->facturadoEntre($tenantId, $de, $ate),
            'total_invoiced_previous' => $this->facturadoEntre($tenantId, $dePassado, $atePassado),

            'total_received' => (float) escopoDoAutor(Receipt::where('tenant_id', $tenantId))
                ->whereBetween('payment_date', [$de, $ate])
                ->where('status', 'issued')
                ->sum('amount_paid'),

            // O QUE SE DEVE NÃO É UMA GRANDEZA DE PERÍODO. Uma factura de
            // Janeiro por pagar continua por pagar em Dezembro: filtrar a
            // dívida pelo período escolhido esconderia precisamente o que estes
            // dois cartões existem para mostrar — foi assim que um deles dizia
            // 76 mil quando havia 15 milhões. São o retrato de hoje, e a lista
            // lá em baixo segue-os.
            'total_pending' => $this->somaPorCobrar($tenantId),
            'total_overdue' => $this->somaPorCobrar($tenantId, true),
        ];

        $stats['growth'] = $this->crescimento($stats['total_invoiced'], $stats['total_invoiced_previous']);

        return [
            'periodo' => [
                'valor' => $periodo,
                'rotulo' => self::rotulo($periodo),
                'rotulo_anterior' => $rotuloPassado,
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'opcoes' => self::opcoesDePeriodo(),
            ],
            'stats' => $stats,
            'documents' => $this->documentosDoPeriodo($tenantId, $de, $ate),
            'invoiceStatus' => $this->estadoDasFacturas($tenantId, $de, $ate),

            // A mesma regra do cartão: a lista e o número têm de bater certo —
            // e, como ele, mostram a dívida toda e não só a do período.
            'pendingInvoices' => $this->porCobrar($tenantId)
                ->with('client')
                ->orderByRaw('due_date IS NULL, due_date ASC')
                ->limit(10)
                ->get(),

            'topClients' => escopoDoAutor(SalesInvoice::with('client')->where('tenant_id', $tenantId))
                ->whereBetween('invoice_date', [$de, $ate])
                ->where('status', '!=', 'cancelled')
                ->selectRaw('client_id, SUM(total) as total_amount, COUNT(*) as invoice_count')
                ->groupBy('client_id')
                ->orderByDesc('total_amount')
                ->limit(5)
                ->get(),

            'recentActivities' => escopoDoAutor(SalesInvoice::with('client')->where('tenant_id', $tenantId))
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ];
    }

    /* ─── As contas ───────────────────────────────────────────────────── */

    /**
     * O que falta receber, pelo SALDO e não pelo total.
     *
     * Uma factura parcialmente paga não entra inteira na dívida: entra o que
     * falta. É a mesma regra do portal do cliente.
     */
    public function porCobrar(int $tenantId, bool $apenasVencidas = false)
    {
        $q = escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
            ->whereNotIn('status', self::SEM_NADA_A_COBRAR)
            ->whereRaw('COALESCE(total, 0) - COALESCE(paid_amount, 0) > 0.01');

        if ($apenasVencidas) {
            $q->whereNotNull('due_date')->whereDate('due_date', '<', Carbon::today());
        }

        return $q;
    }

    public function somaPorCobrar(int $tenantId, bool $apenasVencidas = false): float
    {
        return (float) $this->porCobrar($tenantId, $apenasVencidas)
            ->sum(DB::raw('COALESCE(total, 0) - COALESCE(paid_amount, 0)'));
    }

    /** O que se facturou num intervalo, sem contar o que foi anulado. */
    public function facturadoEntre(int $tenantId, Carbon $de, Carbon $ate): float
    {
        return (float) escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
            ->whereBetween('invoice_date', [$de, $ate])
            ->where('status', '!=', 'cancelled')
            ->sum('total');
    }

    /**
     * A LINHA DO GRÁFICO, para o período escolhido.
     *
     * PERÍODOS LONGOS AGRUPAM-SE POR MÊS. Agrupar sempre por dia dava, numa
     * empresa com movimento, uma linha com um ponto por cada dia do ano:
     * ilegível, e com os rótulos por cima uns dos outros.
     *
     * E os baldes vazios entram a zero: um gráfico que salte de Março para
     * Junho porque Abril e Maio não têm linhas mente sobre a forma do período.
     *
     * O rótulo sai daqui e não do ecrã — é o servidor que sabe se aquilo é um
     * dia ou um mês, e é ele que tem a língua de quem está a olhar.
     *
     * @return list<array{data: string, rotulo: string, valor: float}>
     */
    public function serie(int $tenantId, string $periodo = self::PERIODO_OMISSAO): array
    {
        [$de, $ate] = self::intervalo($periodo);

        // Dois meses de dias ainda se lêem; um trimestre já não.
        $porMes = $de->diffInDays($ate) > 62;

        $chave = $porMes ? "DATE_FORMAT(invoice_date, '%Y-%m-01')" : 'DATE(invoice_date)';

        $somas = escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
            ->whereBetween('invoice_date', [$de, $ate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw($chave . ' as balde, SUM(total) as soma')
            ->groupBy('balde')
            ->pluck('soma', 'balde');

        $pontos = [];
        $cursor = $porMes ? $de->copy()->startOfMonth() : $de->copy()->startOfDay();

        while ($cursor <= $ate) {
            $balde = $cursor->format('Y-m-d');

            $pontos[] = [
                'data' => $balde,
                'rotulo' => $porMes
                    ? $cursor->locale(app()->getLocale())->isoFormat('MMM')
                    : $cursor->format('d/m'),
                'valor' => (float) ($somas[$balde] ?? 0),
            ];

            $porMes ? $cursor->addMonth() : $cursor->addDay();
        }

        return $pontos;
    }

    /**
     * O facturado mês a mês de um ano, com os doze meses sempre presentes.
     *
     * A VISTA DO ANO INTEIRO, que não depende do período escolhido. Uma
     * consulta, não doze.
     *
     * @return list<array{rotulo: string, valor: float}>
     */
    public function porMes(int $tenantId, ?int $ano = null): array
    {
        $ano ??= (int) Carbon::today()->year;

        $somas = escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
            ->whereYear('invoice_date', $ano)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('MONTH(invoice_date) as mes, SUM(total) as soma')
            ->groupBy('mes')
            ->pluck('soma', 'mes');

        $meses = [];

        for ($m = 1; $m <= 12; $m++) {
            $meses[] = [
                // O NOME DO MÊS SEGUE A LÍNGUA de quem está a olhar. Estava
                // 'pt' escrito à mão: a página inteira em inglês e o gráfico
                // por baixo a dizer «ago.» — a meia-tradução que passa
                // despercebida a quem revê o texto e salta à vista a quem usa.
                'rotulo' => Carbon::create($ano, $m, 1)->locale(app()->getLocale())->isoFormat('MMM'),
                'valor' => (float) ($somas[$m] ?? 0),
            ];
        }

        return $meses;
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** O período, na coluna de data que interessa a cada documento. */
    private function noPeriodo($query, Carbon $de, Carbon $ate, string $coluna = 'invoice_date')
    {
        return $query->whereBetween($coluna, [$de, $ate]);
    }

    private function crescimento(float $agora, float $antes): float
    {
        return $antes > 0 ? (($agora - $antes) / $antes) * 100 : 0.0;
    }

    private function documentosDoPeriodo(int $tenantId, Carbon $de, Carbon $ate): array
    {
        $contar = fn (string $modelo, string $coluna) => $this->noPeriodo(
            escopoDoAutor($modelo::where('tenant_id', $tenantId)),
            $de,
            $ate,
            $coluna
        )->count();

        return [
            'invoices' => $contar(SalesInvoice::class, 'invoice_date'),
            'credit_notes' => $contar(CreditNote::class, 'issue_date'),
            'debit_notes' => $contar(DebitNote::class, 'issue_date'),
            'receipts' => $contar(Receipt::class, 'payment_date'),
            'advances' => $contar(Advance::class, 'payment_date'),
        ];
    }

    /**
     * AS QUATRO CAIXAS DO PERÍODO, contadas pelo saldo como os cartões.
     *
     * Não se sobrepõem: uma factura cai numa caixa e numa só. Se contassem
     * pelo nome do estado, os quatro números diziam uma coisa e o cartão ao
     * lado dizia outra.
     */
    private function estadoDasFacturas(int $tenantId, Carbon $de, Carbon $ate): array
    {
        $hoje = Carbon::today();

        $porVencer = fn ($q) => $q->where(function ($w) use ($hoje) {
            $w->whereNull('due_date')->orWhereDate('due_date', '>=', $hoje);
        });

        return [
            // Liquidadas por pagamento: o avesso do «por cobrar».
            'paid' => $this->noPeriodo(
                escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
                    ->whereNotIn('status', ['cancelled', 'credited'])
                    ->whereRaw('COALESCE(total, 0) - COALESCE(paid_amount, 0) <= 0.01'),
                $de,
                $ate
            )->count(),

            // Por cobrar, dentro do prazo e ainda sem nada recebido.
            'pending' => $porVencer($this->noPeriodo($this->porCobrar($tenantId), $de, $ate))
                ->whereRaw('COALESCE(paid_amount, 0) <= 0.01')
                ->count(),

            // Por cobrar, dentro do prazo, com parte já recebida.
            'partially_paid' => $porVencer($this->noPeriodo($this->porCobrar($tenantId), $de, $ate))
                ->whereRaw('COALESCE(paid_amount, 0) > 0.01')
                ->count(),

            // Passou da data e falta receber — seja qual for o nome do estado.
            'overdue' => $this->noPeriodo($this->porCobrar($tenantId, true), $de, $ate)->count(),
        ];
    }
}
