<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Events\Event;
use App\Models\Events\Technician;
use App\Models\Events\Venue;
use App\Services\Events\Eventos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O PAINEL DOS EVENTOS — onde está cada um, e o que falta a cada um.
 *
 * O painel antigo tinha quatro números e uma lista dos cinco eventos
 * seguintes. Faltava-lhe o que quem produz eventos precisa de saber ao abrir a
 * manhã:
 *
 *  · O PROGRESSO. Um evento a três dias com o checklist a 20% é um problema; o
 *    mesmo evento a 90% não é. O número existia na coluna e não aparecia em
 *    lado nenhum.
 *  · O EQUIPAMENTO EM ATRASO. Um projector que devia ter voltado há uma semana
 *    é um projector que não está disponível para o evento de sábado — e isso
 *    só se descobria na montagem.
 */
class PainelApiController extends Controller
{
    public function __construct(private readonly Eventos $eventos) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('events.dashboard.view'), 403, __('Sem permissão para esta operação.'));

        $tenantId = activeTenantId();

        $daCasa = fn () => Event::forTenant();

        $seguintes = Event::forTenant()
            ->where('start_date', '>=', now())
            ->whereIn('status', ['orcamento', 'confirmado', 'em_montagem', 'em_andamento'])
            ->with(['client:id,name', 'venue:id,name', 'type:id,name,icon,color'])
            ->orderBy('start_date')
            ->take(8)
            ->get();

        return response()->json([
            'resumo' => [
                'do_mes' => (clone $daCasa())->whereMonth('start_date', now()->month)
                    ->whereYear('start_date', now()->year)->count(),
                'confirmados' => (clone $daCasa())->where('status', 'confirmado')->count(),
                'a_decorrer' => (clone $daCasa())->whereIn('status', ['em_montagem', 'em_andamento'])->count(),
                'valor_do_mes' => (float) (clone $daCasa())
                    ->whereMonth('start_date', now()->month)
                    ->whereYear('start_date', now()->year)
                    ->whereNotIn('status', ['cancelado'])
                    ->sum('total_value'),
                'equipamento_em_uso' => Equipment::forTenant()->whereIn('status', ['em_uso', 'emprestado'])->count(),
                'equipamento_total' => Equipment::forTenant()->count(),
                'tecnicos' => Technician::forTenant()->where('is_active', true)->count(),
                'locais' => Venue::forTenant()->where('is_active', true)->count(),
            ],
            'a_seguir' => $seguintes->map(fn (Event $e) => $this->linha($e))->values(),
            /*
             * O EQUIPAMENTO EM ATRASO tem secção própria e não uma etiqueta
             * perdida no meio da lista: é o que impede a montagem de sábado, e
             * quem abre o painel de manhã tem de o ver sem procurar.
             */
            'em_atraso' => Equipment::forTenant()
                ->where('status', 'emprestado')
                ->whereNotNull('return_due_date')
                ->whereNull('actual_return_date')
                ->whereDate('return_due_date', '<', today())
                ->with(['borrowedToClient:id,name', 'borrowedToTechnician:id,name'])
                ->orderBy('return_due_date')
                ->take(10)
                ->get()
                ->map(fn (Equipment $e) => [
                    'id' => $e->id,
                    'nome' => $e->name,
                    'com_quem' => $e->borrowedToClient?->name ?? $e->borrowedToTechnician?->name,
                    'devolver_em' => $e->return_due_date?->format('Y-m-d'),
                    'dias' => (int) today()->diffInDays($e->return_due_date),
                ])->values(),
            'por_mes' => $this->porMes($tenantId),
            'por_estado' => $this->porEstado(),
            'por_fase' => $this->porFase(),
            'por_tipo' => $this->porTipo($tenantId),
        ]);
    }

    private function linha(Event $e): array
    {
        return [
            'id' => $e->id,
            'numero' => $e->event_number,
            'nome' => $e->name,
            'cliente' => $e->client?->name,
            'local' => $e->venue?->name,
            'tipo' => $e->type?->name,
            'tipo_icone' => $e->type?->icon,
            'tipo_cor' => $e->type?->color,
            'inicio' => $e->start_date?->format('Y-m-d H:i'),
            'fim' => $e->end_date?->format('Y-m-d H:i'),
            'estado' => $e->status,
            'estado_rotulo' => __(Eventos::ESTADOS[$e->status] ?? $e->status),
            'fase' => $e->phase,
            'fase_rotulo' => __(Eventos::FASES[$e->phase] ?? $e->phase),
            'progresso' => (int) $e->checklist_progress,
            'valor' => (float) $e->total_value,
            'pessoas' => (int) $e->expected_attendees,
            'cor' => $e->calendar_color,
        ];
    }

    /**
     * Os eventos dos últimos doze meses.
     *
     * Os meses sem evento vão a ZERO e não desaparecem: uma linha que salta
     * Agosto encosta Julho a Setembro e inventa uma época alta que não houve.
     */
    private function porMes(int $tenantId): array
    {
        $desde = now()->copy()->startOfMonth()->subMonths(11);

        $linhas = Event::forTenant()
            ->where('start_date', '>=', $desde)
            ->selectRaw('DATE_FORMAT(start_date, "%Y-%m") as mes, COUNT(*) as total, SUM(total_value) as valor')
            ->groupBy('mes')
            ->get()
            ->keyBy('mes');

        $etiquetas = [];
        $valores = [];

        for ($i = 0; $i < 12; $i++) {
            $mes = $desde->copy()->addMonths($i);

            $etiquetas[] = $mes->format('m/Y');
            $valores[] = (int) ($linhas[$mes->format('Y-m')]->total ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    private function porEstado(): array
    {
        $linhas = Event::forTenant()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        return [
            'etiquetas' => $linhas->map(fn ($l) => __(Eventos::ESTADOS[$l->status] ?? $l->status))->all(),
            'chaves' => $linhas->pluck('status')->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }

    private function porFase(): array
    {
        $linhas = Event::forTenant()
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->selectRaw('phase, COUNT(*) as total')
            ->groupBy('phase')
            ->get();

        return [
            'etiquetas' => $linhas->map(fn ($l) => __(Eventos::FASES[$l->phase] ?? $l->phase))->all(),
            'chaves' => $linhas->pluck('phase')->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }

    private function porTipo(int $tenantId): array
    {
        $linhas = DB::table('events_events as e')
            ->leftJoin('events_types as t', 't.id', '=', 'e.type_id')
            ->where('e.tenant_id', $tenantId)
            ->whereNull('e.deleted_at')
            ->groupBy('t.id', 't.name')
            ->selectRaw('COALESCE(t.name, "—") as nome, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }
}
