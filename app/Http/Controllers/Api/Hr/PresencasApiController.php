<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\Shift;
use App\Services\HR\ImportarPresencas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O PONTO — quem entrou, a que horas, e quem faltou.
 *
 * É a origem do que a folha desconta: uma falta a menos no ponto é dinheiro a
 * mais no salário, e um atraso por marcar é um atraso que ninguém paga.
 *
 * DUAS COISAS QUE VIVEM AQUI E NÃO NO ECRÃ:
 *
 * 1. UMA PESSOA TEM UM PONTO POR DIA. Duas linhas para o mesmo dia contavam a
 *    presença duas vezes na folha. A guarda é uma transacção com bloqueio no
 *    funcionário — dois cliques ao mesmo tempo entravam os dois pela porta.
 *
 * 2. AS HORAS SÃO CONTADAS, não escritas. Entrada e saída dão as horas
 *    trabalhadas; a saída antes da entrada é do dia seguinte, senão um turno
 *    da noite dava horas negativas.
 */
class PresencasApiController extends Controller
{
    /** Os estados que esta tabela aceita, com o rótulo e a cor. */
    private const ESTADOS = [
        ['valor' => 'present', 'rotulo' => 'Presente', 'cor' => 'bom'],
        ['valor' => 'late', 'rotulo' => 'Atrasado', 'cor' => 'aviso'],
        ['valor' => 'half_day', 'rotulo' => 'Meio dia', 'cor' => 'primaria'],
        ['valor' => 'absent', 'rotulo' => 'Ausente', 'cor' => 'perigo'],
        ['valor' => 'sick', 'rotulo' => 'Doente', 'cor' => 'roxo'],
        ['valor' => 'vacation', 'rotulo' => 'De férias', 'cor' => 'neutra'],
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'attendance.manage');

        $tenantId = activeTenantId();

        return response()->json([
            'funcionarios' => Employee::where('tenant_id', $tenantId)->where('status', 'active')
                ->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'employee_number', 'shift_id'])
                ->map(fn ($e) => [
                    'valor' => (string) $e->id,
                    'rotulo' => trim($e->first_name . ' ' . $e->last_name),
                    'nota' => $e->employee_number,
                    'turno' => $e->shift_id,
                ])->values(),

            'turnos' => Shift::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('display_order')->get(['id', 'name', 'start_time', 'end_time'])
                ->map(fn ($t) => [
                    'valor' => (string) $t->id,
                    'rotulo' => $t->name,
                    'entrada' => $t->start_time ? Carbon::parse($t->start_time)->format('H:i') : null,
                ])->values(),

            'estados' => collect(self::ESTADOS)->map(fn ($e) => [
                'valor' => $e['valor'], 'rotulo' => __($e['rotulo']), 'cor' => $e['cor'],
            ])->values(),

            'sistemas' => [
                ['valor' => 'zkteco', 'rotulo' => 'ZKTeco'],
                ['valor' => 'hikvision', 'rotulo' => 'Hikvision'],
            ],

            'permissoes' => ['pode_gerir' => true],
        ]);
    }

    /** A lista de um dia, ou de um intervalo. */
    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'attendance.manage');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'funcionario' => ['nullable', 'integer'],
            'estado' => ['nullable', 'string', 'max:30'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = activeTenantId();

        // Sem intervalo, é HOJE: é o que se quer ver ao abrir o ponto.
        $de = $filtros['de'] ?? now()->toDateString();
        $ate = $filtros['ate'] ?? $de;

        $base = fn () => Attendance::where('tenant_id', $tenantId)
            ->whereBetween('date', [$de, $ate]);

        $q = $base()->with('employee:id,first_name,last_name,employee_number')
            ->when($filtros['funcionario'] ?? null, fn ($q, $f) => $q->where('employee_id', $f))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['procura'] ?? null, fn ($q, $p) => $q->whereHas('employee', fn ($sub) => $sub
                ->where('first_name', 'like', "%{$p}%")
                ->orWhere('last_name', 'like', "%{$p}%")
                ->orWhere('employee_number', 'like', "%{$p}%")))
            ->orderByDesc('date')->orderBy('employee_id');

        $pagina = $q->paginate($filtros['por_pagina'] ?? 25);

        $porEstado = $base()->selectRaw('status, COUNT(*) as quantos')->groupBy('status')->pluck('quantos', 'status');

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Attendance $a) => $this->linha($a))->values(),
            'meta' => [
                'total' => $pagina->total(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
            ],
            'periodo' => ['de' => $de, 'ate' => $ate],
            'resumo' => [
                'total' => $porEstado->sum(),
                'por_estado' => collect(self::ESTADOS)->map(fn ($e) => [
                    'valor' => $e['valor'],
                    'rotulo' => __($e['rotulo']),
                    'cor' => $e['cor'],
                    'quantos' => (int) ($porEstado[$e['valor']] ?? 0),
                ])->values(),
                'horas' => round((float) $base()->sum('hours_worked'), 2),
                'atrasos' => (int) $base()->where('is_late', true)->count(),
            ],
            /*
             * QUEM AINDA NÃO PICOU HOJE — só quando se olha para um dia só.
             *
             * É a razão de existir do botão de entrada rápida: no fim da
             * manhã, esta lista é a das pessoas que faltam marcar.
             */
            'por_marcar' => $de === $ate
                ? Employee::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereNotIn('id', Attendance::where('tenant_id', $tenantId)->whereDate('date', $de)->pluck('employee_id'))
                    ->orderBy('first_name')->limit(200)
                    ->get(['id', 'first_name', 'last_name', 'employee_number'])
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'nome' => trim($e->first_name . ' ' . $e->last_name),
                        'numero' => $e->employee_number,
                    ])->values()
                : [],
        ]);
    }

    /**
     * O MÊS INTEIRO, por funcionário — a vista de calendário do ecrã de
     * sempre. Uma linha por pessoa, uma célula por dia.
     */
    public function calendario(Request $request): JsonResponse
    {
        $this->exigir($request, 'attendance.manage');

        $dados = $request->validate([
            'ano' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $tenantId = activeTenantId();
        $inicio = Carbon::create($dados['ano'], $dados['mes'], 1)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $registos = Attendance::where('tenant_id', $tenantId)
            ->whereBetween('date', [$inicio->toDateString(), $fim->toDateString()])
            ->get(['id', 'employee_id', 'date', 'status', 'hours_worked', 'is_late']);

        $porFuncionario = $registos->groupBy('employee_id');

        return response()->json([
            'de' => $inicio->toDateString(),
            'ate' => $fim->toDateString(),
            'dias' => $fim->day,
            'linhas' => Employee::where('tenant_id', $tenantId)
                ->whereIn('id', $porFuncionario->keys())
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'nome' => trim($e->first_name . ' ' . $e->last_name),
                    'dias' => $porFuncionario[$e->id]->mapWithKeys(fn ($a) => [
                        $a->date->format('Y-m-d') => [
                            'id' => $a->id,
                            'estado' => $a->status,
                            'horas' => (float) ($a->hours_worked ?? 0),
                            'atrasado' => (bool) $a->is_late,
                        ],
                    ]),
                ])->values(),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'attendance.manage');

        return $this->gravar($request, null);
    }

    public function actualizar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'attendance.manage');

        return $this->gravar($request, Attendance::where('tenant_id', activeTenantId())->findOrFail($id));
    }

    /**
     * MARCAR A ENTRADA, agora.
     *
     * O botão do dia: um clique por pessoa, sem formulário. Já marcada, não
     * se marca outra vez — é o mesmo travão do ecrã de sempre.
     */
    public function entrada(Request $request, int $funcionario): JsonResponse
    {
        $this->exigir($request, 'attendance.manage');

        $tenantId = activeTenantId();
        $hoje = now()->toDateString();

        $e = Employee::where('tenant_id', $tenantId)->findOrFail($funcionario);

        $a = DB::transaction(function () use ($e, $tenantId, $hoje) {
            // O bloqueio serializa dois cliques ao mesmo tempo: sem ele, os
            // dois passavam a verificação e criavam duas linhas.
            Employee::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($e->id);

            if (Attendance::where('tenant_id', $tenantId)->where('employee_id', $e->id)->whereDate('date', $hoje)->exists()) {
                throw ValidationException::withMessages([
                    'employee_id' => [__('Esta pessoa já tem ponto marcado hoje.')],
                ]);
            }

            return Attendance::create([
                'tenant_id' => $tenantId,
                'employee_id' => $e->id,
                'shift_id' => $e->shift_id,
                'date' => $hoje,
                'check_in' => now()->format('H:i:s'),
                'status' => 'present',
                'affects_payroll' => true,
            ]);
        });

        // A CONFIRMAÇÃO DIZ A QUEM E A QUE HORAS. O botão está numa lista de
        // nomes: «Entrada marcada.» não chegava para saber qual deles ficou
        // marcado, nem a que horas — que é o que se vai discutir ao fim do mês.
        return response()->json([
            'documento' => $this->linha($a->fresh(['employee'])),
            'message' => __('Entrada de :nome marcada às :hora.', [
                'nome' => $e->full_name,
                'hora' => substr((string) $a->check_in, 0, 5),
            ]),
        ], 201);
    }

    /** MARCAR A SAÍDA — e contar as horas. */
    public function saida(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'attendance.manage');

        $a = Attendance::where('tenant_id', activeTenantId())->findOrFail($id);

        if ($a->check_out) {
            throw ValidationException::withMessages(['check_out' => [__('A saída já estava marcada.')]]);
        }

        $agora = now();

        $a->update([
            'check_out' => $agora->format('H:i:s'),
            'hours_worked' => self::horas($a->date->format('Y-m-d'), $a->check_in, $agora->format('H:i:s')),
        ]);

        $a = $a->fresh(['employee']);

        return response()->json([
            'documento' => $this->linha($a),
            'message' => __('Saída de :nome marcada às :hora. :horas hora(s) contadas.', [
                'nome' => $a->employee?->full_name ?? '',
                'hora' => $agora->format('H:i'),
                'horas' => rtrim(rtrim(number_format((float) $a->hours_worked, 2, ',', ''), '0'), ','),
            ]),
        ]);
    }

    public function eliminar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'attendance.manage');

        Attendance::where('tenant_id', activeTenantId())->findOrFail($id)->delete();

        return response()->json(['message' => __('Ponto eliminado.')]);
    }

    /**
     * IMPORTAR AS PICAGENS do relógio de ponto.
     *
     * A leitura da folha vive no `ImportarPresencas`, onde a API e os ensaios
     * lhe chegam — estava dentro do componente Livewire, sem uma única prova.
     */
    public function importar(Request $request, ImportarPresencas $importador): JsonResponse
    {
        $this->exigir($request, 'attendance.manage');

        $request->validate([
            'ficheiro' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'sistema' => ['required', 'in:zkteco,hikvision'],
        ]);

        try {
            $conta = $importador->importar(
                $request->file('ficheiro')->getRealPath(),
                $request->input('sistema'),
                activeTenantId(),
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'ficheiro' => [__('Não foi possível ler o ficheiro: :porque', ['porque' => $e->getMessage()])],
            ]);
        }

        return response()->json($conta + [
            'message' => $conta['ignorados'] > 0
                ? __(':importados picagem(ns) importada(s), :ignorados ignorada(s).', $conta)
                : __(':importados picagem(ns) importada(s).', $conta),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function gravar(Request $request, ?Attendance $existente): JsonResponse
    {
        $dados = $request->validate([
            'employee_id' => ['required', 'integer'],
            'shift_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:present,absent,late,half_day,sick,vacation'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = activeTenantId();

        $daCasa = Employee::where('tenant_id', $tenantId)->whereKey($dados['employee_id'])->exists();

        if (! $daCasa) {
            throw ValidationException::withMessages(['employee_id' => [__('Esse funcionário não é desta empresa.')]]);
        }

        $a = DB::transaction(function () use ($dados, $tenantId, $existente) {
            /*
             * UMA PESSOA TEM UM PONTO POR DIA.
             *
             * Duas linhas para o mesmo dia contavam a presença duas vezes na
             * folha. O bloqueio no funcionário serializa dois pedidos ao mesmo
             * tempo — sem ele, os dois passavam a verificação.
             */
            Employee::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($dados['employee_id']);

            $repetido = Attendance::where('tenant_id', $tenantId)
                ->where('employee_id', $dados['employee_id'])
                ->whereDate('date', $dados['date'])
                ->when($existente, fn ($q) => $q->where('id', '!=', $existente->id))
                ->exists();

            if ($repetido) {
                throw ValidationException::withMessages([
                    'date' => [__('Já existe um ponto para esta pessoa neste dia. Edite o que lá está.')],
                ]);
            }

            $campos = $dados + [
                'tenant_id' => $tenantId,
                'hours_worked' => self::horas($dados['date'], $dados['check_in'] ?? null, $dados['check_out'] ?? null),
                'affects_payroll' => true,
            ];

            if ($existente) {
                $existente->update($campos);

                return $existente;
            }

            return Attendance::create($campos);
        });

        return response()->json([
            'documento' => $this->linha($a->fresh(['employee'])),
            'message' => $existente ? __('Ponto actualizado.') : __('Ponto registado.'),
        ], $existente ? 200 : 201);
    }

    /**
     * AS HORAS ENTRE DUAS PICAGENS.
     *
     * A saída antes da entrada é do dia seguinte — um turno da noite atravessa
     * a meia-noite, e sem isto dava horas negativas.
     */
    private static function horas(string $dia, ?string $entrada, ?string $saida): ?float
    {
        if (! $entrada || ! $saida) {
            return null;
        }

        $de = Carbon::parse($dia . ' ' . $entrada);
        $ate = Carbon::parse($dia . ' ' . $saida);

        if ($ate->lt($de)) {
            $ate->addDay();
        }

        return round($de->diffInMinutes($ate) / 60, 2);
    }

    private function linha(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'employee_id' => $a->employee_id,
            'funcionario' => $a->employee
                ? trim($a->employee->first_name . ' ' . $a->employee->last_name)
                : __('Funcionário removido'),
            'numero' => $a->employee?->employee_number,
            'date' => $a->date?->format('Y-m-d'),
            'check_in' => $a->check_in ? substr((string) $a->check_in, 0, 5) : null,
            'check_out' => $a->check_out ? substr((string) $a->check_out, 0, 5) : null,
            'hours_worked' => (float) ($a->hours_worked ?? 0),
            'overtime_hours' => (float) ($a->overtime_hours ?? 0),
            'status' => $a->status,
            'is_late' => (bool) $a->is_late,
            'late_minutes' => (int) ($a->late_minutes ?? 0),
            'shift_id' => $a->shift_id,
            'notes' => $a->notes,
        ];
    }
}
