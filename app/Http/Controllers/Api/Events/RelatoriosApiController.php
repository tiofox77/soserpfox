<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Events\Event;
use App\Models\Events\EventType;
use App\Services\Events\Eventos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * OS RELATÓRIOS DOS EVENTOS.
 *
 * O ECRÃ ANTIGO TINHA DOIS BOTÕES QUE NÃO FAZIAM NADA: «PDF» e «Excel»
 * chamavam métodos que só diziam «ainda não existe». Ficou o CSV, que funciona
 * e abre em qualquer folha de cálculo — e é o único que se oferece, porque um
 * botão que avisa que não faz nada continua a ser um botão a mais.
 *
 * O PDF DO ECRÃ, esse, é o do browser: a pré-visualização é a folha, como em
 * todos os outros relatórios da casa.
 */
class RelatoriosApiController extends Controller
{
    private function filtros(Request $request): array
    {
        return $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'cliente' => ['nullable', 'integer'],
            'tipo' => ['nullable', 'integer'],
            'estado' => ['nullable', 'string'],
        ]);
    }

    private function consulta(array $f)
    {
        $de = $f['de'] ?? now()->subMonths(12)->format('Y-m-d');
        $ate = $f['ate'] ?? now()->format('Y-m-d');

        return Event::forTenant()
            ->whereDate('start_date', '>=', $de)
            ->whereDate('start_date', '<=', $ate)
            ->when($f['cliente'] ?? null, fn ($q, $c) => $q->where('client_id', $c))
            ->when($f['tipo'] ?? null, fn ($q, $t) => $q->where('type_id', $t))
            ->when($f['estado'] ?? null, fn ($q, $e) => $q->where('status', $e));
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('events.reports.view'), 403, __('Sem permissão para esta operação.'));

        $f = $this->filtros($request);

        $de = $f['de'] ?? now()->subMonths(12)->format('Y-m-d');
        $ate = $f['ate'] ?? now()->format('Y-m-d');

        $total = (clone $this->consulta($f))->count();

        return response()->json([
            'de' => $de,
            'ate' => $ate,
            'resumo' => [
                'eventos' => $total,
                'valor' => (float) (clone $this->consulta($f))->sum('total_value'),
                'pessoas' => (int) (clone $this->consulta($f))->sum('expected_attendees'),
                'valor_medio' => $total > 0
                    ? round((float) (clone $this->consulta($f))->sum('total_value') / $total, 2)
                    : 0.0,
                'concluidos' => (clone $this->consulta($f))->where('status', 'concluido')->count(),
                'cancelados' => (clone $this->consulta($f))->where('status', 'cancelado')->count(),
            ],
            'por_mes' => $this->porMes($f),
            'por_estado' => $this->agrupado($f, 'status', fn ($v) => __(Eventos::ESTADOS[$v] ?? $v)),
            'por_cliente' => $this->porCliente($f),
            'por_tipo' => $this->porTipo($f),
            'data' => (clone $this->consulta($f))
                ->with(['client:id,name', 'venue:id,name', 'type:id,name,icon,color'])
                ->orderBy('start_date', 'desc')
                ->limit(200)
                ->get()
                ->map(fn (Event $e) => [
                    'id' => $e->id,
                    'numero' => $e->event_number,
                    'nome' => $e->name,
                    'cliente' => $e->client?->name,
                    'local' => $e->venue?->name,
                    'tipo' => $e->type?->name,
                    'tipo_icone' => $e->type?->icon,
                    'inicio' => $e->start_date?->format('Y-m-d'),
                    'fim' => $e->end_date?->format('Y-m-d'),
                    'estado' => $e->status,
                    'estado_rotulo' => __(Eventos::ESTADOS[$e->status] ?? $e->status),
                    'pessoas' => (int) $e->expected_attendees,
                    'valor' => (float) $e->total_value,
                ])->values(),
            'opcoes' => [
                'clientes' => Client::where('tenant_id', activeTenantId())->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->name])->values(),
                'tipos' => EventType::forTenant()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($t) => ['valor' => (string) $t->id, 'rotulo' => $t->name])->values(),
                'estados' => collect(Eventos::ESTADOS)
                    ->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            ],
        ]);
    }

    /** Os meses do período — e os meses sem evento vão a zero. */
    private function porMes(array $f): array
    {
        $linhas = (clone $this->consulta($f))
            ->selectRaw('DATE_FORMAT(start_date, "%Y-%m") as mes, COUNT(*) as total, SUM(total_value) as valor')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return [
            'etiquetas' => $linhas->map(fn ($l) => substr($l->mes, 5, 2).'/'.substr($l->mes, 0, 4))->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
            'receita' => $linhas->map(fn ($l) => (float) $l->valor)->all(),
        ];
    }

    private function agrupado(array $f, string $coluna, callable $rotulo): array
    {
        $linhas = (clone $this->consulta($f))
            ->selectRaw("{$coluna} as chave, COUNT(*) as total")
            ->groupBy('chave')
            ->orderByDesc('total')
            ->get();

        return [
            'etiquetas' => $linhas->map(fn ($l) => $rotulo($l->chave))->all(),
            'chaves' => $linhas->pluck('chave')->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }

    private function porCliente(array $f): array
    {
        $linhas = (clone $this->consulta($f))
            ->leftJoin('invoicing_clients as c', 'c.id', '=', 'events_events.client_id')
            ->groupBy('c.id', 'c.name')
            ->selectRaw('COALESCE(c.name, "—") as nome, COUNT(*) as total, SUM(events_events.total_value) as valor')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
            'receita' => $linhas->map(fn ($l) => (float) $l->valor)->all(),
        ];
    }

    private function porTipo(array $f): array
    {
        $linhas = (clone $this->consulta($f))
            ->leftJoin('events_types as t', 't.id', '=', 'events_events.type_id')
            ->groupBy('t.id', 't.name')
            ->selectRaw('COALESCE(t.name, "—") as nome, COUNT(*) as total')
            ->orderByDesc('total')
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }

    /** O CSV — o único que existe, e abre no Excel. */
    public function csv(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('events.reports.view'), 403, __('Sem permissão para esta operação.'));

        $eventos = (clone $this->consulta($this->filtros($request)))
            ->with(['client:id,name', 'venue:id,name', 'type:id,name'])
            ->orderBy('start_date')
            ->get();

        $nome = 'eventos_'.now()->format('Y-m-d_His').'.csv';

        return response()->stream(function () use ($eventos) {
            $saida = fopen('php://output', 'w');

            // O BOM: sem ele, o Excel abre «Coloração» como «ColoraÃ§Ã£o».
            fwrite($saida, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($saida, [
                __('Número'), __('Nome'), __('Cliente'), __('Local'), __('Tipo'),
                __('Estado'), __('Início'), __('Fim'), __('Pessoas'), __('Valor'),
            ], ';');

            foreach ($eventos as $e) {
                fputcsv($saida, array_map([\App\Support\CelulaSegura::class, 'texto'], [
                    $e->event_number,
                    $e->name,
                    $e->client?->name ?? '',
                    $e->venue?->name ?? '',
                    $e->type?->name ?? '',
                    __(Eventos::ESTADOS[$e->status] ?? $e->status),
                    $e->start_date?->format('d/m/Y H:i'),
                    $e->end_date?->format('d/m/Y H:i'),
                    (int) $e->expected_attendees,
                    number_format((float) $e->total_value, 2, ',', ''),
                ]), ';');
            }

            fclose($saida);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nome}\"",
        ]);
    }
}
