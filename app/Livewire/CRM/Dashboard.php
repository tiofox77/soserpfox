<?php

namespace App\Livewire\CRM;

use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Stage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O painel do CRM: as quatro perguntas de segunda-feira de manhã.
 *
 *   1. quanto vale o funil aberto (e o ponderado, que é o honesto)?
 *   2. quanto se ganhou este mês, e a taxa de conversão?
 *   3. o que está ATRASADO — tarefas com prazo estourado?
 *   4. de onde vêm os leads (onde vale a pena gastar)?
 */
#[Layout('layouts.app')]
#[Title('CRM')]
class Dashboard extends Component
{
    public function render()
    {
        $tenantId = activeTenantId();

        $abertas = Opportunity::where('tenant_id', $tenantId)->where('status', 'open');

        $inicioDoMes = now()->startOfMonth();

        $fechadasNoMes = Opportunity::where('tenant_id', $tenantId)
            ->whereIn('status', ['won', 'lost'])
            ->where('closed_at', '>=', $inicioDoMes);

        $ganhasNoMes = (clone $fechadasNoMes)->where('status', 'won');

        $resumo = [
            'funil_valor' => (float) (clone $abertas)->sum('amount'),
            'funil_ponderado' => (float) (clone $abertas)->get()->sum(fn ($o) => $o->weighted_amount),
            'funil_contagem' => (clone $abertas)->count(),
            'ganho_mes' => (float) (clone $ganhasNoMes)->sum('amount'),
            'ganhas_mes' => (clone $ganhasNoMes)->count(),
            // Ganho não é cobrado. O funil sabia dizer quanto se fechou e nunca
            // quanto virou documento — e é essa a pergunta que paga as contas.
            'facturado_mes' => (float) (clone $ganhasNoMes)->whereNotNull('sales_invoice_id')->sum('amount'),
            'por_facturar_mes' => (clone $ganhasNoMes)->whereNull('sales_invoice_id')->count(),
            // A taxa é sobre o que FECHOU no mês: ganhas ÷ (ganhas+perdidas).
            // Contar as abertas no denominador fazia a taxa cair sempre que
            // se registava um negócio novo — punia quem mais usa o CRM.
            'taxa' => ($fechadas = (clone $fechadasNoMes)->count()) > 0
                ? round((clone $ganhasNoMes)->count() * 100 / $fechadas)
                : null,
            'leads_novos_mes' => Lead::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $inicioDoMes)->count(),
            'leads_abertos' => Lead::where('tenant_id', $tenantId)
                ->whereIn('status', Lead::ABERTOS)->count(),
        ];

        // O funil por etapa, para o gráfico de barras.
        $etapas = Stage::doTenant($tenantId);
        $porEtapa = Opportunity::where('tenant_id', $tenantId)->where('status', 'open')
            ->selectRaw('stage_id, COALESCE(SUM(amount), 0) total')
            ->groupBy('stage_id')->pluck('total', 'stage_id');

        $graficoFunil = [
            'etiquetas' => $etapas->pluck('name')->all(),
            'valores' => $etapas->map(fn ($e) => (float) ($porEtapa[$e->id] ?? 0))->all(),
        ];

        // Ganhos por mês, últimos 6 — a linha que diz se está a melhorar.
        $meses = collect(range(5, 0))->map(fn ($atras) => now()->copy()->subMonths($atras)->startOfMonth());
        $ganhosPorMes = Opportunity::where('tenant_id', $tenantId)->where('status', 'won')
            ->where('closed_at', '>=', $meses->first())
            ->selectRaw("DATE_FORMAT(closed_at, '%Y-%m') mes, COALESCE(SUM(amount), 0) total")
            ->groupBy('mes')->pluck('total', 'mes');

        $graficoGanhos = [
            'etiquetas' => $meses->map(fn ($m) => $m->format('M/y'))->all(),
            'valores' => $meses->map(fn ($m) => (float) ($ganhosPorMes[$m->format('Y-m')] ?? 0))->all(),
        ];

        // As origens dos leads (90 dias): onde vale a pena gastar.
        $porOrigem = Lead::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('source, COUNT(*) c')->groupBy('source')->pluck('c', 'source');

        $graficoOrigens = [
            'etiquetas' => $porOrigem->keys()->map(fn ($s) => Lead::ORIGENS[$s] ?? $s)->all(),
            'valores' => $porOrigem->values()->all(),
        ];

        // As tarefas: atrasadas primeiro — são a razão de abrir este ecrã.
        $tarefas = Activity::where('tenant_id', $tenantId)
            ->where('done', false)->whereNotNull('due_at')
            ->with(['lead:id,name', 'opportunity:id,title'])
            ->orderBy('due_at')->limit(8)->get();

        $ultimasOportunidades = Opportunity::where('tenant_id', $tenantId)
            ->with(['stage:id,name', 'client:id,name'])
            ->latest()->limit(6)->get();

        return view('livewire.crm.dashboard', compact(
            'resumo', 'graficoFunil', 'graficoGanhos', 'graficoOrigens', 'tarefas', 'ultimasOportunidades'
        ));
    }
}
