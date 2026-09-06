<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\Advance;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
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
    /** O que já não se cobra. Ver a nota acima: a lista é do avesso. */
    private const LIQUIDADAS = ['cancelled', 'paid', 'credited'];

    /**
     * Tudo o que o painel mostra, para a data de hoje.
     *
     * @return array{stats: array, documents: array, invoiceStatus: array,
     *               pendingInvoices: \Illuminate\Support\Collection,
     *               topClients: \Illuminate\Support\Collection,
     *               recentActivities: \Illuminate\Support\Collection}
     */
    public function numeros(int $tenantId): array
    {
        $hoje = Carbon::today();
        $mesPassado = Carbon::now()->subMonth()->startOfMonth();
        $fimDoMesPassado = Carbon::now()->subMonth()->endOfMonth();

        $stats = [
            'total_invoiced' => (float) $this->doMes($this->vendasVivas($tenantId), $hoje)->sum('total'),

            'total_invoiced_last_month' => (float) $this->vendasVivas($tenantId)
                ->whereBetween('invoice_date', [$mesPassado, $fimDoMesPassado])
                ->sum('total'),

            'total_received' => (float) escopoDoAutor(Receipt::where('tenant_id', $tenantId))
                ->whereMonth('payment_date', $hoje->month)
                ->whereYear('payment_date', $hoje->year)
                ->where('status', 'issued')
                ->sum('amount_paid'),

            'total_pending' => $this->somaPorCobrar($tenantId),
            'total_overdue' => $this->somaPorCobrar($tenantId, true),

            // ANO A ANO, ATÉ AO MESMO DIA. Comparar um ano a meio com um ano
            // inteiro daria sempre uma queda que não existe.
            'year_invoiced' => $this->facturadoEntre(
                $tenantId,
                $hoje->copy()->startOfYear(),
                $hoje->copy()->endOfDay()
            ),
            'year_invoiced_previous' => $this->facturadoEntre(
                $tenantId,
                $hoje->copy()->subYear()->startOfYear(),
                $hoje->copy()->subYear()->endOfDay()
            ),
        ];

        $stats['growth'] = $this->crescimento($stats['total_invoiced'], $stats['total_invoiced_last_month']);
        $stats['year_growth'] = $this->crescimento($stats['year_invoiced'], $stats['year_invoiced_previous']);

        return [
            'stats' => $stats,
            'documents' => $this->documentosDoMes($tenantId, $hoje),
            'invoiceStatus' => $this->estadoDasFacturas($tenantId, $hoje),

            // A mesma regra do cartão: a lista e o número têm de bater certo.
            'pendingInvoices' => $this->porCobrar($tenantId)
                ->with('client')
                ->orderByRaw('due_date IS NULL, due_date ASC')
                ->limit(10)
                ->get(),

            'topClients' => escopoDoAutor(SalesInvoice::with('client')->where('tenant_id', $tenantId))
                ->whereMonth('invoice_date', $hoje->month)
                ->whereYear('invoice_date', $hoje->year)
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
            ->whereNotIn('status', self::LIQUIDADAS)
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
     * O facturado mês a mês de um ano, com os doze meses sempre presentes.
     *
     * UMA CONSULTA, não doze. E os meses sem nada entram a zero: um gráfico
     * que salte de Março para Junho porque Abril e Maio não têm linhas mente
     * sobre a forma do ano.
     *
     * O rótulo sai daqui e não do ecrã — assim o painel em Blade e o painel em
     * React escrevem «Set» da mesma maneira.
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

    private function vendasVivas(int $tenantId)
    {
        return escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
            ->where('status', '!=', 'cancelled');
    }

    /** O MÊS E O ANO. Sem o ano, Setembro de 2026 contava Setembro de 2025. */
    private function doMes($query, Carbon $dia)
    {
        return $query->whereMonth('invoice_date', $dia->month)->whereYear('invoice_date', $dia->year);
    }

    private function crescimento(float $agora, float $antes): float
    {
        return $antes > 0 ? (($agora - $antes) / $antes) * 100 : 0.0;
    }

    private function documentosDoMes(int $tenantId, Carbon $hoje): array
    {
        $contar = fn (string $modelo, string $coluna) => escopoDoAutor($modelo::where('tenant_id', $tenantId))
            ->whereMonth($coluna, $hoje->month)
            ->whereYear($coluna, $hoje->year)
            ->count();

        return [
            'invoices' => $contar(SalesInvoice::class, 'invoice_date'),
            'credit_notes' => $contar(CreditNote::class, 'issue_date'),
            'debit_notes' => $contar(DebitNote::class, 'issue_date'),
            'receipts' => $contar(Receipt::class, 'payment_date'),
            'advances' => $contar(Advance::class, 'payment_date'),
        ];
    }

    /**
     * AS QUATRO CAIXAS DO MÊS, contadas pelo saldo como os cartões.
     *
     * Não se sobrepõem: uma factura cai numa caixa e numa só. Se contassem
     * pelo nome do estado, os quatro números diziam uma coisa e o cartão ao
     * lado dizia outra.
     */
    private function estadoDasFacturas(int $tenantId, Carbon $hoje): array
    {
        $porVencer = fn ($q) => $q->where(function ($w) use ($hoje) {
            $w->whereNull('due_date')->orWhereDate('due_date', '>=', $hoje);
        });

        return [
            // Liquidadas por pagamento: o avesso do «por cobrar».
            'paid' => $this->doMes(
                escopoDoAutor(SalesInvoice::where('tenant_id', $tenantId))
                    ->whereNotIn('status', ['cancelled', 'credited'])
                    ->whereRaw('COALESCE(total, 0) - COALESCE(paid_amount, 0) <= 0.01'),
                $hoje
            )->count(),

            // Por cobrar, dentro do prazo e ainda sem nada recebido.
            'pending' => $porVencer($this->doMes($this->porCobrar($tenantId), $hoje))
                ->whereRaw('COALESCE(paid_amount, 0) <= 0.01')
                ->count(),

            // Por cobrar, dentro do prazo, com parte já recebida.
            'partially_paid' => $porVencer($this->doMes($this->porCobrar($tenantId), $hoje))
                ->whereRaw('COALESCE(paid_amount, 0) > 0.01')
                ->count(),

            // Passou da data e falta receber — seja qual for o nome do estado.
            'overdue' => $this->doMes($this->porCobrar($tenantId, true), $hoje)->count(),
        ];
    }
}
