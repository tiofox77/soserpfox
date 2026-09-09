<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HR\Contract;
use App\Models\HR\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OS CONTRATOS — o ecrã que nunca existiu.
 *
 * A tabela `hr_contracts` está no sistema desde o princípio, com vinte e nove
 * colunas, e o `PayrollService` lê-a antes da ficha do funcionário:
 *
 *     $baseSalary = $contract->base_salary ?? $employee->base_salary ?? …
 *
 * Ou seja: o contrato activo DECIDE o salário que é pago — e não havia ecrã
 * nenhum por onde o ver, criar ou corrigir. Uma linha errada aqui pagava mal
 * a alguém todos os meses e não havia sequer onde olhar.
 *
 * DUAS REGRAS QUE O ECRÃ IMPÕE, porque o serviço as pressupõe:
 *
 *  · UM CONTRATO ACTIVO POR PESSOA. `activeContract` é um `hasOne` sobre
 *    `status = active` com `latest()`: com dois activos, o salário pago passa
 *    a depender da ordem de inserção. Activar um novo TERMINA o anterior, e
 *    diz-se isso antes de acontecer.
 *  · O FIM PEDE O PRINCÍPIO. Um contrato a termo sem data de fim não é a
 *    termo; um fim anterior ao início não é um contrato.
 *
 * A guarda é `hr.contracts.*`, criada com este ecrã.
 */
class ContratosApiController extends Controller
{
    private const ESTADOS = [
        ['valor' => 'active', 'rotulo' => 'Em vigor', 'cor' => 'bom'],
        ['valor' => 'expired', 'rotulo' => 'Caducado', 'cor' => 'aviso'],
        ['valor' => 'terminated', 'rotulo' => 'Cessado', 'cor' => 'perigo'],
        ['valor' => 'suspended', 'rotulo' => 'Suspenso', 'cor' => 'neutra'],
    ];

    /** Os tipos vêm do enum da coluna — escritos em português na base. */
    private const TIPOS = ['Determinado', 'Indeterminado', 'Estágio', 'Freelancer'];

    private const PERIODICIDADES = ['Mensal', 'Quinzenal', 'Semanal'];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function base()
    {
        return Contract::where('tenant_id', activeTenantId());
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hr.contracts.view');

        $utilizador = $request->user();

        return response()->json([
            'estados' => collect(self::ESTADOS)->map(fn ($e) => [
                'valor' => $e['valor'], 'rotulo' => __($e['rotulo']), 'cor' => $e['cor'],
            ])->values(),
            'tipos' => collect(self::TIPOS)->map(fn ($t) => ['valor' => $t, 'rotulo' => __($t)])->values(),
            'periodicidades' => collect(self::PERIODICIDADES)->map(fn ($p) => ['valor' => $p, 'rotulo' => __($p)])->values(),
            'funcionarios' => Employee::where('tenant_id', activeTenantId())
                ->where('status', 'active')
                ->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'employee_number', 'base_salary'])
                ->map(fn (Employee $e) => [
                    'valor' => (string) $e->id,
                    'rotulo' => $e->full_name,
                    'nota' => $e->employee_number,
                    // O SALÁRIO DA FICHA, para o formulário o propor: escrever
                    // um valor diferente do da ficha há-de ser uma decisão, não
                    // um descuido de quem não sabia qual era.
                    'salario' => (float) ($e->base_salary ?? $e->salary ?? 0),
                ])->values(),
            'permissoes' => [
                'pode_criar' => (bool) $utilizador?->can('hr.contracts.create'),
                'pode_editar' => (bool) $utilizador?->can('hr.contracts.edit'),
                'pode_eliminar' => (bool) $utilizador?->can('hr.contracts.delete'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'hr.contracts.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:20'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'funcionario' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = $this->base()
            ->with(['employee:id,first_name,last_name,employee_number'])
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['tipo'] ?? null, fn ($q, $t) => $q->where('contract_type', $t))
            ->when($filtros['funcionario'] ?? null, fn ($q, $f) => $q->where('employee_id', $f))
            ->when($filtros['procura'] ?? null, fn ($q, $p) => $q->where(fn ($w) => $w
                ->where('contract_number', 'like', "%{$p}%")
                ->orWhereHas('employee', fn ($e) => $e
                    ->where('first_name', 'like', "%{$p}%")
                    ->orWhere('last_name', 'like', "%{$p}%")
                    ->orWhere('employee_number', 'like', "%{$p}%"))))
            ->orderByDesc('start_date')->orderByDesc('id');

        $pagina = $q->paginate($filtros['por_pagina'] ?? 15);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Contract $c) => $this->linha($c))->values(),
            'meta' => [
                'total' => $pagina->total(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
            ],
            'resumo' => $this->resumo(),
        ]);
    }

    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hr.contracts.view');

        $c = $this->base()->with('employee:id,first_name,last_name,employee_number')->findOrFail($id);

        return response()->json(['documento' => $this->linha($c) + $this->campos($c)]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hr.contracts.create');

        $dados = $this->validar($request);

        $c = DB::transaction(function () use ($dados) {
            $this->terminarOAnterior($dados);

            return Contract::create($dados + [
                'tenant_id' => activeTenantId(),
                'contract_number' => $this->proximoNumero(),
            ]);
        });

        return response()->json([
            'documento' => $this->linha($c->fresh('employee')) + $this->campos($c),
            'message' => __('Contrato :n criado.', ['n' => $c->contract_number]),
        ], 201);
    }

    public function actualizar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hr.contracts.edit');

        $c = $this->base()->findOrFail($id);
        $dados = $this->validar($request, $c);

        DB::transaction(function () use ($c, $dados) {
            $this->terminarOAnterior($dados, $c->id);
            $c->update($dados);
        });

        return response()->json([
            'documento' => $this->linha($c->fresh('employee')) + $this->campos($c->fresh()),
            'message' => __('Contrato :n guardado.', ['n' => $c->contract_number]),
        ]);
    }

    /**
     * CESSAR — e não apagar.
     *
     * Um contrato cessado é o registo de um vínculo que existiu, e a folha dos
     * meses em que ele valeu foi calculada por ele. Apagá-lo apagava a razão
     * de os salários daqueles meses terem sido aqueles.
     */
    public function cessar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hr.contracts.edit');

        $c = $this->base()->findOrFail($id);

        $dados = $request->validate([
            'termination_date' => ['required', 'date'],
            'termination_reason' => ['required', 'string', 'max:2000'],
        ]);

        if (Carbon::parse($dados['termination_date'])->lt($c->start_date)) {
            throw ValidationException::withMessages([
                'termination_date' => [__('A cessação não pode ser anterior ao início do contrato.')],
            ]);
        }

        $c->update($dados + ['status' => 'terminated']);

        return response()->json([
            'documento' => $this->linha($c->fresh('employee')),
            'message' => __('Contrato :n cessado.', ['n' => $c->contract_number]),
        ]);
    }

    public function eliminar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'hr.contracts.delete');

        $c = $this->base()->findOrFail($id);

        // SÓ SE APAGA O QUE NUNCA VALEU. Um contrato que esteve em vigor
        // explica salários já pagos; para o encerrar existe a cessação.
        if ($c->status !== 'active' || $c->start_date?->isPast()) {
            throw ValidationException::withMessages([
                'status' => [__('Só se elimina um contrato que ainda não começou. Um que esteve em vigor cessa-se.')],
            ]);
        }

        $numero = $c->contract_number;
        $c->delete();

        return response()->json(['message' => __('Contrato :n eliminado.', ['n' => $numero])]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Contract $actual = null): array
    {
        $dados = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('hr_employees', 'id')->where('tenant_id', activeTenantId())],
            'contract_type' => ['required', Rule::in(self::TIPOS)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'trial_period_end' => ['nullable', 'date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'food_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'numeric', 'min:0'],
            'payment_frequency' => ['required', Rule::in(self::PERIODICIDADES)],
            'weekly_hours' => ['required', 'integer', 'min:1', 'max:60'],
            'work_start_time' => ['nullable', 'date_format:H:i'],
            'work_end_time' => ['nullable', 'date_format:H:i'],
            'has_health_insurance' => ['nullable', 'boolean'],
            'has_life_insurance' => ['nullable', 'boolean'],
            'vacation_days_per_year' => ['required', 'integer', 'min:0', 'max:60'],
            'subject_to_irt' => ['nullable', 'boolean'],
            'subject_to_inss' => ['nullable', 'boolean'],
            'irt_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(array_column(self::ESTADOS, 'valor'))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $inicio = Carbon::parse($dados['start_date']);

        // UM CONTRATO A TERMO SEM FIM NÃO É A TERMO. O enum aceitava-o e a
        // pessoa ficava com um vínculo que nunca caducava.
        if (in_array($dados['contract_type'], ['Determinado', 'Estágio'], true) && empty($dados['end_date'])) {
            throw ValidationException::withMessages([
                'end_date' => [__('Um contrato a termo ou de estágio tem de dizer quando acaba.')],
            ]);
        }

        foreach (['end_date' => __('O fim'), 'trial_period_end' => __('O fim do período experimental')] as $campo => $nome) {
            if (! empty($dados[$campo]) && Carbon::parse($dados[$campo])->lt($inicio)) {
                throw ValidationException::withMessages([
                    $campo => [__(':campo não pode ser anterior ao início do contrato.', ['campo' => $nome])],
                ]);
            }
        }

        foreach (['food_allowance', 'transport_allowance', 'housing_allowance', 'other_allowances'] as $c) {
            $dados[$c] = $dados[$c] ?? 0;
        }

        foreach (['has_health_insurance', 'has_life_insurance'] as $c) {
            $dados[$c] = (bool) ($dados[$c] ?? false);
        }

        foreach (['subject_to_irt', 'subject_to_inss'] as $c) {
            $dados[$c] = (bool) ($dados[$c] ?? true);
        }

        return $dados;
    }

    /**
     * ACTIVAR UM TERMINA O ANTERIOR.
     *
     * `Employee::activeContract` é um `hasOne` com `latest()`: com dois
     * contratos activos, o salário pago passa a depender da ordem de
     * inserção. O anterior fica CADUCADO, com a véspera do novo como fim.
     */
    private function terminarOAnterior(array $dados, ?int $excepto = null): void
    {
        if (($dados['status'] ?? null) !== 'active') {
            return;
        }

        $vespera = Carbon::parse($dados['start_date'])->subDay();

        Contract::where('tenant_id', activeTenantId())
            ->where('employee_id', $dados['employee_id'])
            ->where('status', 'active')
            ->when($excepto, fn ($q, $id) => $q->where('id', '!=', $id))
            ->get()
            ->each(fn (Contract $c) => $c->update([
                'status' => 'expired',
                'end_date' => $c->end_date && $c->end_date->lt($vespera) ? $c->end_date : $vespera,
            ]));
    }

    /** CT-0001, por empresa. */
    private function proximoNumero(): string
    {
        $ultimo = Contract::where('tenant_id', activeTenantId())
            ->where('contract_number', 'like', 'CT-%')
            ->orderByDesc('id')
            ->value('contract_number');

        $n = $ultimo ? ((int) substr($ultimo, 3)) + 1 : 1;

        return 'CT-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function resumo(): array
    {
        $emVigor = (clone $this->base())->where('status', 'active');

        return [
            'total' => $this->base()->count(),
            'em_vigor' => (clone $emVigor)->count(),
            // OS QUE ACABAM NOS PRÓXIMOS 60 DIAS: um contrato a termo que
            // caduca sem ninguém dar por isso converte-se em permanente por
            // lei, e é uma decisão que a empresa devia tomar de olhos abertos.
            'a_terminar' => (clone $emVigor)
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays(60)->toDateString()])
                ->count(),
            'massa_salarial' => round((float) (clone $emVigor)->sum('base_salary'), 2),
        ];
    }

    private function linha(Contract $c): array
    {
        $fim = $c->end_date;

        return [
            'id' => $c->id,
            'numero' => $c->contract_number,
            'funcionario' => $c->employee?->full_name ?? '—',
            'numero_do_funcionario' => $c->employee?->employee_number,
            'employee_id' => $c->employee_id,
            'tipo' => $c->contract_type,
            'estado' => $c->status,
            'inicio' => $c->start_date?->format('Y-m-d'),
            'fim' => $fim?->format('Y-m-d'),
            'dias_para_o_fim' => $fim ? (int) now()->startOfDay()->diffInDays($fim, false) : null,
            'base' => (float) $c->base_salary,
            'total' => (float) $c->getTotalCompensation(),
        ];
    }

    /** Os campos todos, para o formulário. */
    private function campos(Contract $c): array
    {
        return [
            'trial_period_end' => $c->trial_period_end?->format('Y-m-d'),
            'food_allowance' => (float) $c->food_allowance,
            'transport_allowance' => (float) $c->transport_allowance,
            'housing_allowance' => (float) $c->housing_allowance,
            'other_allowances' => (float) $c->other_allowances,
            'payment_frequency' => $c->payment_frequency,
            'weekly_hours' => (int) $c->weekly_hours,
            'work_start_time' => $c->work_start_time ? substr((string) $c->work_start_time, 0, 5) : null,
            'work_end_time' => $c->work_end_time ? substr((string) $c->work_end_time, 0, 5) : null,
            'has_health_insurance' => (bool) $c->has_health_insurance,
            'has_life_insurance' => (bool) $c->has_life_insurance,
            'vacation_days_per_year' => (int) $c->vacation_days_per_year,
            'subject_to_irt' => (bool) $c->subject_to_irt,
            'subject_to_inss' => (bool) $c->subject_to_inss,
            'irt_percentage' => $c->irt_percentage !== null ? (float) $c->irt_percentage : null,
            'termination_date' => $c->termination_date?->format('Y-m-d'),
            'termination_reason' => $c->termination_reason,
            'notes' => $c->notes,
        ];
    }
}
