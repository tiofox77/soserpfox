<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O PAINEL DO CRM — as quatro perguntas de segunda-feira de manhã.
 *
 *   1. quanto vale o funil aberto (e o ponderado, que é o honesto)?
 *   2. quanto se ganhou este mês, e a taxa de conversão?
 *   3. o que está ATRASADO — tarefas com prazo estourado?
 *   4. de onde vêm os leads (onde vale a pena gastar)?
 *
 * GANHO NÃO É COBRADO, e é a distinção que o funil não fazia: sabia dizer
 * quanto se fechou e nunca quanto virou documento — que é a pergunta que paga
 * as contas.
 */
class PainelApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('crm.view'), 403, __('Sem permissão para esta operação.'));

        $tenantId = activeTenantId();
        $inicioDoMes = now()->startOfMonth();

        $abertas = fn () => Opportunity::forTenant()->where('status', 'open');

        $fechadasNoMes = fn () => Opportunity::forTenant()
            ->whereIn('status', ['won', 'lost'])
            ->where('closed_at', '>=', $inicioDoMes);

        $ganhasNoMes = fn () => $fechadasNoMes()->where('status', 'won');

        $fechadas = $fechadasNoMes()->count();

        return response()->json([
            'resumo' => [
                'funil_valor' => (float) $abertas()->sum('amount'),
                // O ponderado lê-se do acessor, linha a linha: cada negócio tem
                // a probabilidade da SUA etapa, e uma média à mão mentia.
                'funil_ponderado' => (float) $abertas()->get()->sum(fn ($o) => $o->weighted_amount),
                'funil_contagem' => $abertas()->count(),
                'ganho_mes' => (float) $ganhasNoMes()->sum('amount'),
                'ganhas_mes' => $ganhasNoMes()->count(),
                'facturado_mes' => (float) $ganhasNoMes()->whereNotNull('sales_invoice_id')->sum('amount'),
                'por_facturar_mes' => $ganhasNoMes()->whereNull('sales_invoice_id')->count(),
                /*
                 * A TAXA É SOBRE O QUE FECHOU NO MÊS: ganhas ÷ (ganhas+perdidas).
                 * Contar as abertas no denominador fazia a taxa cair sempre que
                 * se registava um negócio novo — punia quem mais usa o CRM.
                 */
                'taxa' => $fechadas > 0 ? (int) round($ganhasNoMes()->count() * 100 / $fechadas) : null,
                'leads_novos_mes' => Lead::forTenant()->where('created_at', '>=', $inicioDoMes)->count(),
                'leads_abertos' => Lead::forTenant()->whereIn('status', Lead::ABERTOS)->count(),
            ],
            'por_etapa' => $this->porEtapa($tenantId),
            'ganhos_por_mes' => $this->ganhosPorMes(),
            'por_origem' => $this->porOrigem(),
            'tarefas' => Activity::forTenant()
                ->where('done', false)->whereNotNull('due_at')
                ->with(['lead:id,name', 'opportunity:id,title'])
                ->orderBy('due_at')->limit(8)->get()
                ->map(fn (Activity $a) => [
                    'id' => $a->id,
                    'tipo' => $a->type,
                    'tipo_rotulo' => __(Activity::TIPOS[$a->type] ?? $a->type),
                    'assunto' => $a->subject,
                    'prazo' => $a->due_at?->format('Y-m-d H:i'),
                    'atrasada' => $a->due_at?->isPast() ?? false,
                    'lead' => $a->lead?->name,
                    'oportunidade' => $a->opportunity?->title,
                ])->values(),
            'ultimas' => Opportunity::forTenant()
                ->with(['stage:id,name', 'client:id,name'])
                ->latest()->limit(6)->get()
                ->map(fn (Opportunity $o) => [
                    'id' => $o->id,
                    'titulo' => $o->title,
                    'cliente' => $o->client?->name,
                    'etapa' => $o->stage?->name,
                    'valor' => (float) $o->amount,
                    'estado' => $o->status,
                ])->values(),
        ]);
    }

    private function porEtapa(int $tenantId): array
    {
        $etapas = Stage::doTenant($tenantId);

        $totais = Opportunity::forTenant()->where('status', 'open')
            ->selectRaw('stage_id, COALESCE(SUM(amount), 0) total')
            ->groupBy('stage_id')->pluck('total', 'stage_id');

        return [
            'etiquetas' => $etapas->pluck('name')->all(),
            'valores' => $etapas->map(fn ($e) => (float) ($totais[$e->id] ?? 0))->all(),
        ];
    }

    /** Os ganhos dos últimos seis meses — a linha que diz se está a melhorar. */
    private function ganhosPorMes(): array
    {
        $meses = collect(range(5, 0))->map(fn ($atras) => now()->copy()->subMonths($atras)->startOfMonth());

        $porMes = Opportunity::forTenant()->where('status', 'won')
            ->where('closed_at', '>=', $meses->first())
            ->selectRaw("DATE_FORMAT(closed_at, '%Y-%m') mes, COALESCE(SUM(amount), 0) total")
            ->groupBy('mes')->pluck('total', 'mes');

        return [
            'etiquetas' => $meses->map(fn ($m) => $m->format('m/Y'))->all(),
            'valores' => $meses->map(fn ($m) => (float) ($porMes[$m->format('Y-m')] ?? 0))->all(),
        ];
    }

    /** De onde vêm os leads dos últimos 90 dias: onde vale a pena gastar. */
    private function porOrigem(): array
    {
        $linhas = Lead::forTenant()
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('source, COUNT(*) c')->groupBy('source')->pluck('c', 'source');

        return [
            'etiquetas' => $linhas->keys()->map(fn ($s) => __(Lead::ORIGENS[$s] ?? $s))->all(),
            'valores' => $linhas->values()->map(fn ($v) => (int) $v)->all(),
        ];
    }
}
