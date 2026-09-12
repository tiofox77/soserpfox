<?php

namespace App\Http\Controllers\Api\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon\Appointment;
use App\Models\Salon\Professional;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O PAINEL DO SALÃO — o dia, profissional a profissional.
 *
 * A AGENDA POR PESSOA é a razão de ser deste ecrã: um salão não trabalha por
 * lista de marcações, trabalha por cadeira. Quem abre isto de manhã quer saber
 * quem tem o dia cheio e quem tem buracos.
 *
 * AS FALTAS TÊM CARTÃO PRÓPRIO. Um salão com muitos «não compareceu» tem um
 * problema de confirmação, não de procura — e isso não se via em lado nenhum.
 */
class PainelApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('salon.dashboard.view'), 403, __('Sem permissão para esta operação.'));

        $dia = Carbon::parse($request->input('dia', today()->toDateString()));
        $tenantId = activeTenantId();

        $doDia = fn () => Appointment::forTenant()->forDate($dia);

        $concluidas = $doDia()->where('status', 'completed')
            ->whereNotNull('started_at')->whereNotNull('completed_at')->get();

        $marcacoes = Appointment::forTenant()->forDate($dia)
            ->with(['client:id,name,phone', 'professional:id,name', 'services.service'])
            ->orderBy('start_time')
            ->get();

        $profissionais = Professional::forTenant()->active()->orderBy('name')->get(['id', 'name', 'specialization']);

        return response()->json([
            'dia' => $dia->toDateString(),
            'resumo' => [
                'marcacoes' => $doDia()->count(),
                'confirmadas' => $doDia()->where('status', 'confirmed')->count(),
                'em_curso' => $doDia()->where('status', 'in_progress')->count(),
                'concluidas' => $doDia()->where('status', 'completed')->count(),
                'faltas' => $doDia()->where('status', 'no_show')->count(),
                'receita_do_dia' => (float) $doDia()->where('status', 'completed')->sum('total'),
                'receita_do_mes' => (float) Appointment::forTenant()
                    ->whereMonth('date', $dia->month)->whereYear('date', $dia->year)
                    ->where('status', 'completed')->sum('total'),
                // O tempo REAL, e não o previsto: é a diferença entre os dois
                // que diz se a agenda está bem montada.
                'duracao_media' => (int) round($concluidas->avg(fn ($m) => $m->actual_duration) ?? 0),
                'espera_media' => (int) round(
                    $concluidas->filter(fn ($m) => $m->wait_time !== null)->avg('wait_time') ?? 0,
                ),
            ],
            'agenda' => $profissionais->map(fn (Professional $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'especialidade' => $p->specialization,
                'marcacoes' => $marcacoes->where('professional_id', $p->id)
                    ->map(fn (Appointment $m) => $this->linha($m))->values(),
            ])->values(),
            /*
             * NÃO HÁ MARCAÇÃO SEM PROFISSIONAL: `professional_id` é NOT NULL.
             * A agenda acima cobre-as todas — uma lista de «sem profissional»
             * seria uma secção que nunca teria nada e que alguém acabaria por
             * tentar preencher.
             */
            'a_seguir' => $marcacoes
                ->whereIn('status', ['scheduled', 'confirmed', 'arrived'])
                ->take(5)->map(fn (Appointment $m) => $this->linha($m))->values(),
            'por_dia' => $this->receitaPorDia($tenantId),
            'por_estado' => $this->marcacoesPorEstado(),
            'por_profissional' => $this->receitaPorProfissional($tenantId),
            'servicos' => $this->servicosMaisPedidos($tenantId),
        ]);
    }

    private function linha(Appointment $m): array
    {
        return [
            'id' => $m->id,
            'numero' => $m->appointment_number,
            'cliente' => $m->client?->name ?? __('Sem cliente'),
            'telefone' => $m->client?->phone,
            'profissional' => $m->professional?->name,
            'inicio' => $m->start_time?->format('H:i'),
            'fim' => $m->end_time?->format('H:i'),
            'duracao' => (int) $m->total_duration,
            'estado' => $m->status,
            'estado_rotulo' => __(Appointment::STATUSES[$m->status] ?? $m->status),
            'total' => (float) $m->total,
            'servicos' => $m->services->map(fn ($s) => $s->service?->name)->filter()->values(),
        ];
    }

    /**
     * A receita dos últimos 30 dias.
     *
     * Os dias sem marcação vão a ZERO e não desaparecem: uma linha que salta a
     * segunda-feira fechada dá-lhe a receita do domingo.
     */
    private function receitaPorDia(int $tenantId): array
    {
        $dias = 30;
        $desde = today()->subDays($dias - 1);

        $porDia = Appointment::forTenant()
            ->where('status', 'completed')
            ->where('date', '>=', $desde->format('Y-m-d'))
            ->groupBy('dia')
            ->selectRaw('DATE(date) as dia, SUM(total) as total')
            ->pluck('total', 'dia');

        $etiquetas = [];
        $valores = [];

        for ($d = 0; $d < $dias; $d++) {
            $data = $desde->copy()->addDays($d);

            $etiquetas[] = $data->format('d/m');
            $valores[] = (float) ($porDia[$data->format('Y-m-d')] ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** As marcações do mês por estado — as FALTAS são o número que interessa. */
    private function marcacoesPorEstado(): array
    {
        $linhas = Appointment::forTenant()
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->get();

        return [
            'etiquetas' => $linhas->map(fn ($l) => __(Appointment::STATUSES[$l->status] ?? $l->status))->all(),
            'chaves' => $linhas->pluck('status')->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }

    /** Quanto rende cada profissional no mês. */
    private function receitaPorProfissional(int $tenantId): array
    {
        $linhas = DB::table('salon_appointments as a')
            ->leftJoin('salon_professionals as p', 'p.id', '=', 'a.professional_id')
            ->where('a.tenant_id', $tenantId)
            ->whereNull('a.deleted_at')
            ->where('a.status', 'completed')
            ->whereMonth('a.date', now()->month)
            ->whereYear('a.date', now()->year)
            ->groupBy('p.id', 'p.name')
            ->selectRaw('COALESCE(p.name, "—") as nome, SUM(a.total) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->map(fn ($l) => (float) $l->total)->all(),
        ];
    }

    /** Os serviços mais pedidos no mês. */
    private function servicosMaisPedidos(int $tenantId): array
    {
        $linhas = DB::table('salon_appointment_services as s')
            ->join('salon_appointments as a', 'a.id', '=', 's.appointment_id')
            ->join('invoicing_products as v', 'v.id', '=', 's.service_id')
            ->where('a.tenant_id', $tenantId)
            ->whereNull('a.deleted_at')
            ->whereNotIn('a.status', ['cancelled'])
            ->whereMonth('a.date', now()->month)
            ->whereYear('a.date', now()->year)
            ->groupBy('v.id', 'v.name')
            ->selectRaw('v.name as nome, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }
}
