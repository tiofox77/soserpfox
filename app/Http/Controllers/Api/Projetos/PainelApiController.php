<?php

namespace App\Http\Controllers\Api\Projetos;

use App\Http\Controllers\Controller;
use App\Models\Projetos\HoraLancada;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O PAINEL DOS PROJETOS — o que está a fugir.
 *
 * Três coisas fogem num projeto, e são as três que este ecrã mostra: o
 * ORÇAMENTO que está a ser ultrapassado, o PRAZO das tarefas que já passou, e
 * as HORAS TRABALHADAS QUE NINGUÉM FACTUROU — dinheiro que a casa já gastou a
 * fazer e nunca cobrou.
 */
class PainelApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('projetos.view'), 403, __('Sem permissão para esta operação.'));

        $inicioMes = now()->startOfMonth()->toDateString();

        $porFacturar = HoraLancada::forTenant()->porFacturar()
            ->selectRaw('COALESCE(SUM(horas), 0) h, COALESCE(SUM(horas * valor_hora), 0) v')
            ->first();

        /*
         * O CONSUMO CALCULA-SE DAS HORAS, sempre.
         *
         * Nunca se guarda em coluna: um agregado guardado diverge da soma das
         * linhas à primeira correcção de um lançamento, e depois ninguém sabe
         * qual dos dois números está certo.
         */
        $consumo = HoraLancada::forTenant()
            ->selectRaw('projeto_id, SUM(horas) horas, SUM(horas * COALESCE(valor_hora, 0)) valor')
            ->groupBy('projeto_id')->get()->keyBy('projeto_id');

        $activos = Projeto::forTenant()
            ->whereIn('estado', Projeto::ABERTOS)
            ->with('cliente:id,name')
            ->orderBy('nome')->limit(10)->get()
            ->map(function (Projeto $p) use ($consumo) {
                $gasto = (float) ($consumo[$p->id]->valor ?? 0);
                $orcamento = (float) $p->orcamento;

                return [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nome' => $p->nome,
                    'cliente' => $p->cliente?->name,
                    'estado' => $p->estado,
                    'estado_rotulo' => __(Projeto::ESTADOS[$p->estado] ?? $p->estado),
                    'horas' => (float) ($consumo[$p->id]->horas ?? 0),
                    'orcamento' => $orcamento,
                    'gasto' => $gasto,
                    // SEM ORÇAMENTO NÃO HÁ PERCENTAGEM: «0%» seria mentira, e
                    // uma barra vazia num projeto sem tecto engana mais do que
                    // não mostrar nada.
                    'percentagem' => $orcamento > 0 ? round($gasto / $orcamento * 100, 1) : null,
                ];
            });

        return response()->json([
            'resumo' => [
                'activos' => Projeto::forTenant()->where('estado', 'activo')->count(),
                'horas_mes' => round((float) HoraLancada::forTenant()
                    ->where('data', '>=', $inicioMes)->sum('horas'), 2),
                'tarefas_abertas' => Tarefa::forTenant()->whereIn('estado', Tarefa::ABERTAS)->count(),
                'tarefas_atrasadas' => Tarefa::forTenant()
                    ->whereIn('estado', Tarefa::ABERTAS)
                    ->whereNotNull('prazo')->whereDate('prazo', '<', today())->count(),
                'horas_por_facturar' => round((float) ($porFacturar->h ?? 0), 2),
                'valor_por_facturar' => round((float) ($porFacturar->v ?? 0), 2),
            ],
            'activos' => $activos->values(),
            'estouros' => $activos->filter(fn ($a) => ($a['percentagem'] ?? 0) > 100)->values(),
            'atrasadas' => Tarefa::forTenant()
                ->whereIn('estado', Tarefa::ABERTAS)
                ->whereNotNull('prazo')->whereDate('prazo', '<', today())
                ->with(['projeto:id,codigo,nome', 'responsavel:id,name'])
                ->orderBy('prazo')->limit(8)->get()
                ->map(fn (Tarefa $t) => [
                    'id' => $t->id,
                    'titulo' => $t->titulo,
                    'projeto' => $t->projeto?->codigo,
                    'responsavel' => $t->responsavel?->name,
                    'prazo' => $t->prazo?->format('Y-m-d'),
                    'dias' => (int) today()->diffInDays($t->prazo),
                    'prioridade' => $t->prioridade,
                    'prioridade_rotulo' => __(Tarefa::PRIORIDADES[$t->prioridade] ?? $t->prioridade),
                ])->values(),
            'horas_por_mes' => $this->horasPorMes(),
        ]);
    }

    /** As horas dos últimos seis meses — e os meses sem horas vão a zero. */
    private function horasPorMes(): array
    {
        $meses = collect(range(5, 0))->map(fn ($atras) => now()->copy()->subMonths($atras)->startOfMonth());

        $porMes = HoraLancada::forTenant()
            ->where('data', '>=', $meses->first()->toDateString())
            ->selectRaw("DATE_FORMAT(data, '%Y-%m') mes, COALESCE(SUM(horas), 0) total")
            ->groupBy('mes')->pluck('total', 'mes');

        return [
            'etiquetas' => $meses->map(fn ($m) => $m->format('m/Y'))->all(),
            'valores' => $meses->map(fn ($m) => (float) ($porMes[$m->format('Y-m')] ?? 0))->all(),
        ];
    }
}
