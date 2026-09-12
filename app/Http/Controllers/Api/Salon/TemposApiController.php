<?php

namespace App\Http\Controllers\Api\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon\Appointment;
use App\Models\Salon\Professional;
use App\Models\Salon\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O RELATÓRIO DE TEMPOS — quanto tempo leva, de facto.
 *
 * É O RELATÓRIO QUE MONTA A AGENDA. Um corte marcado para 30 minutos que leva
 * sempre 50 faz a agenda inteira atrasar-se a partir do meio da manhã, e a
 * culpa parece ser de quem atende. Aqui vê-se o previsto ao lado do real.
 *
 * SÓ CONTA O QUE TEM AS DUAS HORAS. Sem `started_at` e `completed_at` não há
 * tempo real nenhum — e uma média calculada sobre metade dos atendimentos é
 * pior do que média nenhuma.
 *
 * A TOLERÂNCIA É DE CINCO MINUTOS para cada lado: num salão, cinco minutos não
 * são um atraso, são a conversa à porta.
 */
class TemposApiController extends Controller
{
    private const TOLERANCIA = 5;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('salon.reports.view'), 403, __('Sem permissão para esta operação.'));

        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'profissional' => ['nullable', 'integer'],
            'servico' => ['nullable', 'integer'],
        ], [], ['de' => __('data inicial'), 'ate' => __('data final')]);

        $de = $filtros['de'] ?? now()->startOfMonth()->toDateString();
        $ate = $filtros['ate'] ?? now()->toDateString();
        $tenantId = activeTenantId();

        $marcacoes = Appointment::forTenant()
            ->where('status', 'completed')
            ->whereNotNull('started_at')->whereNotNull('completed_at')
            ->whereBetween('date', [$de, $ate])
            ->when($filtros['profissional'] ?? null, fn ($q, $p) => $q->where('professional_id', $p))
            ->when($filtros['servico'] ?? null, fn ($q, $s) => $q
                ->whereHas('services', fn ($w) => $w->where('service_id', $s)))
            ->with(['client:id,name', 'professional:id,name', 'services.service'])
            ->orderByDesc('date')->orderByDesc('start_time')
            ->get();

        return response()->json([
            'de' => $de,
            'ate' => $ate,
            'resumo' => $this->resumo($marcacoes),
            'data' => $marcacoes->map(fn (Appointment $m) => [
                'id' => $m->id,
                'numero' => $m->appointment_number,
                'dia' => $m->date?->format('Y-m-d'),
                'inicio' => $m->start_time?->format('H:i'),
                'cliente' => $m->client?->name ?? __('Sem cliente'),
                'profissional' => $m->professional?->name,
                'previsto' => (int) $m->total_duration,
                'real' => $m->actual_duration,
                'diferenca' => $m->time_difference,
                'espera' => $m->wait_time,
                'total' => (float) $m->total,
                'servicos' => $m->services->map(fn ($s) => $s->service?->name)->filter()->values(),
            ])->values(),
            'por_profissional' => $this->porProfissional($de, $ate),
            'por_servico' => $this->porServico($tenantId, $de, $ate),
            'opcoes' => [
                'profissionais' => Professional::forTenant()->active()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($p) => ['valor' => (string) $p->id, 'rotulo' => $p->name])->values(),
                'servicos' => Service::forTenant()->active()->orderBy('name')->get()
                    ->map(fn ($s) => ['valor' => (string) $s->id, 'rotulo' => $s->name])->values(),
            ],
        ]);
    }

    private function resumo($marcacoes): array
    {
        $comTempo = $marcacoes->filter(fn (Appointment $m) => $m->actual_duration !== null);

        if ($comTempo->isEmpty()) {
            return [
                'atendimentos' => 0, 'tempo_total' => 0, 'tempo_medio' => 0, 'espera_media' => 0,
                'a_horas' => 0, 'atrasados' => 0, 'mais_rapidos' => 0, 'eficiencia' => 0,
            ];
        }

        $total = (int) $comTempo->sum('actual_duration');
        $previsto = (int) $comTempo->sum('total_duration');

        return [
            'atendimentos' => $comTempo->count(),
            'tempo_total' => $total,
            'tempo_medio' => (int) round($comTempo->avg('actual_duration')),
            'espera_media' => (int) round(
                $comTempo->filter(fn (Appointment $m) => $m->wait_time !== null)->avg('wait_time') ?? 0,
            ),
            'a_horas' => $comTempo->filter(fn ($m) => abs($m->time_difference ?? 0) <= self::TOLERANCIA)->count(),
            'atrasados' => $comTempo->filter(fn ($m) => ($m->time_difference ?? 0) > self::TOLERANCIA)->count(),
            'mais_rapidos' => $comTempo->filter(fn ($m) => ($m->time_difference ?? 0) < -self::TOLERANCIA)->count(),
            // Previsto a dividir pelo real: acima de 100% anda-se mais depressa
            // do que a agenda diz. O tecto de 200% evita que um atendimento de
            // um minuto mal fechado faça a média explodir.
            'eficiencia' => $previsto > 0 && $total > 0 ? min(200, round(($previsto / $total) * 100, 1)) : 100,
        ];
    }

    private function porProfissional(string $de, string $ate): array
    {
        return Appointment::forTenant()
            ->selectRaw('professional_id, COUNT(*) as total')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as real_media')
            ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as real_total')
            ->selectRaw('AVG(total_duration) as previsto_medio')
            ->selectRaw('SUM(total) as receita')
            ->where('status', 'completed')
            ->whereNotNull('started_at')->whereNotNull('completed_at')
            ->whereBetween('date', [$de, $ate])
            ->groupBy('professional_id')
            ->with('professional:id,name')
            ->get()
            ->map(fn ($l) => [
                'nome' => $l->professional?->name ?? __('Sem profissional'),
                'atendimentos' => (int) $l->total,
                'tempo_medio' => (int) round((float) $l->real_media),
                'tempo_total' => (int) $l->real_total,
                'previsto_medio' => (int) round((float) $l->previsto_medio),
                'receita' => (float) $l->receita,
                'eficiencia' => (float) $l->previsto_medio > 0 && (float) $l->real_media > 0
                    ? min(200, round(((float) $l->previsto_medio / (float) $l->real_media) * 100, 1))
                    : 100,
            ])->values()->all();
    }

    private function porServico(int $tenantId, string $de, string $ate): array
    {
        return DB::table('salon_appointment_services as sas')
            ->join('salon_appointments as sa', 'sas.appointment_id', '=', 'sa.id')
            ->join('invoicing_products as ss', 'sas.service_id', '=', 'ss.id')
            ->selectRaw('ss.name as nome, COUNT(*) as total, SUM(sas.total) as receita')
            ->selectRaw('AVG(sas.duration) as previsto_medio')
            ->where('sa.tenant_id', $tenantId)
            ->whereNull('sa.deleted_at')
            ->where('sa.status', 'completed')
            ->whereBetween('sa.date', [$de, $ate])
            ->groupBy('sas.service_id', 'ss.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($l) => [
                'nome' => $l->nome,
                'total' => (int) $l->total,
                'receita' => (float) $l->receita,
                'previsto_medio' => (int) round((float) $l->previsto_medio),
            ])->values()->all();
    }
}
