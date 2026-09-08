<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Models\HR\SalaryAdvance;
use App\Models\HR\SalaryDiscount;
use App\Services\HR\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A FOLHA DE PAGAMENTO.
 *
 * O ecrã onde um erro custa dinheiro a alguém — por isso este controlador não
 * faz conta nenhuma. Criar, processar, aprovar, marcar paga e recalcular são
 * cinco chamadas ao `PayrollService`, que é onde vivem o IRT por escalões, o
 * INSS, as horas extras, os subsídios e o abate dos adiantamentos.
 *
 * O CICLO, e o que cada passo faz:
 *
 *   CRIAR      um mês, vazio.
 *   PROCESSAR  calcula a linha de cada trabalhador.
 *   APROVAR    grava quem e quando — é a decisão.
 *   PAGAR      e é ESTE passo que dispara as deduções: a prestação do
 *              adiantamento e a do desconto salarial são abatidas aqui, e não
 *              antes. Pagar duas vezes descontava duas prestações.
 *   RECALCULAR reprocessa sem aprovar, para quando muda um salário ou entra
 *              uma hora extra depois de a folha estar feita.
 *
 * A GUARDA: `payroll.process` — a permissão que existia e nenhuma rota
 * aplicava. Ver a folha é ver o salário de toda a gente.
 */
class FolhaApiController extends Controller
{
    private const ESTADOS = [
        ['valor' => 'draft', 'rotulo' => 'Rascunho', 'cor' => 'neutra'],
        ['valor' => 'processing', 'rotulo' => 'Em processamento', 'cor' => 'primaria'],
        ['valor' => 'approved', 'rotulo' => 'Aprovada', 'cor' => 'bom'],
        ['valor' => 'paid', 'rotulo' => 'Paga', 'cor' => 'primaria'],
        ['valor' => 'cancelled', 'rotulo' => 'Anulada', 'cor' => 'perigo'],
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function base()
    {
        return Payroll::where('tenant_id', activeTenantId());
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        return response()->json([
            'estados' => collect(self::ESTADOS)->map(fn ($e) => [
                'valor' => $e['valor'], 'rotulo' => __($e['rotulo']), 'cor' => $e['cor'],
            ])->values(),
            'meses' => collect(range(1, 12))->map(fn ($m) => [
                'valor' => (string) $m,
                'rotulo' => Carbon::create(null, $m, 1)->locale(app()->getLocale())->monthName,
            ])->values(),
            'permissoes' => ['pode_processar' => true],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        $filtros = $request->validate([
            'ano' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'estado' => ['nullable', 'string', 'max:20'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = $this->base()
            ->when($filtros['ano'] ?? null, fn ($q, $a) => $q->where('year', $a))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->orderByDesc('year')->orderByDesc('month');

        $pagina = $q->paginate($filtros['por_pagina'] ?? 15);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Payroll $p) => $this->linha($p))->values(),
            'meta' => [
                'total' => $pagina->total(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
            ],
            'resumo' => [
                'total' => $this->base()->count(),
                'rascunhos' => $this->base()->where('status', 'draft')->count(),
                'por_pagar' => $this->base()->where('status', 'approved')->count(),
                // O que ESTE ano custou em salário líquido: é o número que o
                // dono da empresa procura.
                'liquido_do_ano' => round((float) $this->base()
                    ->where('year', $filtros['ano'] ?? (int) now()->format('Y'))
                    ->sum('total_net_salary'), 2),
            ],
        ]);
    }

    /** A folha aberta: o cabeçalho e a linha de cada trabalhador. */
    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        $p = $this->base()->with(['items.employee:id,first_name,last_name,employee_number'])->findOrFail($id);

        return response()->json([
            'documento' => $this->linha($p) + [
                'notes' => $p->notes,
                'linhas' => $p->items->map(fn (PayrollItem $i) => $this->linhaDoItem($i))->values(),
            ],
        ]);
    }

    /**
     * CRIAR A FOLHA DE UM MÊS.
     *
     * Uma por mês e por empresa: duas folhas do mesmo mês pagavam duas vezes.
     */
    public function guardar(Request $request, PayrollService $folhas): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        $dados = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $tenantId = activeTenantId();

        if ($this->base()->where('year', $dados['year'])->where('month', $dados['month'])->exists()) {
            throw ValidationException::withMessages([
                'month' => [__('Já existe uma folha para este mês.')],
            ]);
        }

        try {
            $p = $folhas->createPayroll($tenantId, $dados['year'], $dados['month']);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['regra' => [$e->getMessage()]]);
        }

        return response()->json([
            'documento' => $this->linha($p),
            'message' => __('Folha :n criada.', ['n' => $p->payroll_number]),
        ], 201);
    }

    /**
     * PROCESSAR — calcula a linha de cada trabalhador.
     *
     * Não aprova: `processPayroll($p, false)` deixa a folha por conferir, que
     * é o que se quer antes de alguém decidir.
     */
    public function processar(Request $request, PayrollService $folhas, int $id): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        $p = $this->base()->findOrFail($id);

        $this->exigirEstado($p, ['draft', 'processing'], __('Esta folha já foi aprovada — recalcule em vez de processar.'));

        try {
            $folhas->processPayroll($p, false);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['regra' => [$e->getMessage()]]);
        }

        return response()->json([
            'documento' => $this->linha($p->fresh()),
            'message' => __('Folha processada. Confira antes de aprovar.'),
        ]);
    }

    /** APROVAR — grava quem e quando. */
    public function aprovar(Request $request, PayrollService $folhas, int $id): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        $p = $this->base()->findOrFail($id);

        $this->exigirEstado($p, ['draft', 'processing'], __('Esta folha já foi aprovada.'));

        if ($p->items()->count() === 0) {
            throw ValidationException::withMessages([
                'status' => [__('Uma folha sem linhas não se aprova — processe-a primeiro.')],
            ]);
        }

        $folhas->approvePayroll($p, auth()->id());

        return response()->json([
            'documento' => $this->linha($p->fresh()),
            'message' => __('Folha aprovada.'),
        ]);
    }

    /**
     * MARCAR PAGA — e é AQUI que as deduções correm.
     *
     * A prestação do adiantamento e a do desconto salarial são abatidas neste
     * passo, e não antes: uma folha aprovada mas por pagar não deve descontar
     * nada a ninguém. Só uma folha APROVADA se paga, e só uma vez — pagar duas
     * vezes descontava duas prestações do mesmo empréstimo.
     */
    public function pagar(Request $request, PayrollService $folhas, int $id): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        $p = $this->base()->with('items')->findOrFail($id);

        $this->exigirEstado($p, ['approved'], __('Só uma folha aprovada se paga.'));

        $folhas->markAsPaid($p, now());
        $folhas->processAdvanceDeductions($p);
        $folhas->processDiscountDeductions($p);

        return response()->json([
            'documento' => $this->linha($p->fresh()),
            'message' => __('Folha paga. Os adiantamentos e os descontos foram abatidos.'),
        ]);
    }

    /**
     * RECALCULAR — reprocessa sem aprovar.
     *
     * Para quando muda um salário, entra uma hora extra ou se corrige um
     * ponto depois de a folha estar feita.
     */
    public function recalcular(Request $request, PayrollService $folhas, int $id): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        $p = $this->base()->findOrFail($id);

        $this->exigirEstado($p, ['draft', 'processing', 'approved'], __('Uma folha paga não se recalcula.'));

        try {
            $folhas->processPayroll($p, false);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['regra' => [$e->getMessage()]]);
        }

        return response()->json([
            'documento' => $this->linha($p->fresh()),
            'message' => __('Folha recalculada.'),
        ]);
    }

    /**
     * ACERTAR OS DESCONTOS DE UMA LINHA.
     *
     * O empréstimo e os outros descontos são PUXADOS da base — as prestações
     * que estão em curso — e não escritos à mão: escrevê-los deixava a folha a
     * dizer uma coisa e o adiantamento a dizer outra. Depois o serviço
     * recalcula o IRT, o INSS e o líquido com os novos descontos.
     */
    public function acertarLinha(Request $request, PayrollService $folhas, int $id, int $linha): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        $p = $this->base()->findOrFail($id);

        $this->exigirEstado($p, ['draft', 'processing', 'approved'], __('Uma folha paga não se altera.'));

        $item = PayrollItem::where('payroll_id', $p->id)->findOrFail($linha);

        $emprestimos = SalaryAdvance::where('tenant_id', $p->tenant_id)
            ->where('employee_id', $item->employee_id)
            ->whereIn('status', ['paid', 'in_deduction'])
            ->where('balance', '>', 0)
            ->sum('installment_amount');

        $outros = SalaryDiscount::where('tenant_id', $p->tenant_id)
            ->where('employee_id', $item->employee_id)
            ->where('status', 'approved')
            ->where('remaining_installments', '>', 0)
            ->sum('installment_amount');

        $item->update([
            'loan_deduction' => $emprestimos,
            'other_deductions' => $outros,
        ]);

        $folhas->recalculateItem($item);

        return response()->json([
            'documento' => $this->linhaDoItem($item->fresh(['employee'])),
            'message' => __('Descontos acertados e linha recalculada.'),
        ]);
    }

    /** ELIMINAR — e só uma folha que ainda não foi aprovada. */
    public function eliminar(Request $request, PayrollService $folhas, int $id): JsonResponse
    {
        $this->exigir($request, 'payroll.process');

        $p = $this->base()->findOrFail($id);

        $this->exigirEstado($p, ['draft', 'processing'], __('Uma folha aprovada ou paga não se elimina.'));

        $numero = $p->payroll_number;
        $folhas->deletePayroll($p);

        return response()->json(['message' => __('Folha :n eliminada.', ['n' => $numero])]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** @param  array<int, string>  $permitidos */
    private function exigirEstado(Payroll $p, array $permitidos, string $frase): void
    {
        if (! in_array($p->status, $permitidos, true)) {
            throw ValidationException::withMessages(['status' => [$frase]]);
        }
    }

    private function linha(Payroll $p): array
    {
        return [
            'id' => $p->id,
            'numero' => $p->payroll_number,
            'year' => (int) $p->year,
            'month' => (int) $p->month,
            'mes' => Carbon::create($p->year, $p->month, 1)->locale(app()->getLocale())->monthName,
            'periodo' => [
                'de' => $p->period_start?->format('Y-m-d'),
                'ate' => $p->period_end?->format('Y-m-d'),
            ],
            'pagamento' => $p->payment_date?->format('Y-m-d'),
            'estado' => $p->status,
            'totais' => [
                'bruto' => (float) $p->total_gross_salary,
                'subsidios' => (float) $p->total_allowances,
                'bonus' => (float) $p->total_bonuses,
                'descontos' => (float) $p->total_deductions,
                'irt' => (float) $p->total_irt,
                'inss_trabalhador' => (float) $p->total_inss_employee,
                'inss_empresa' => (float) $p->total_inss_employer,
                'liquido' => (float) $p->total_net_salary,
            ],
            'funcionarios' => (int) $p->total_employees,
            'processados' => (int) $p->processed_employees,
            'decisao' => [
                'aprovado_por' => optional($p->approved_by ? \App\Models\User::find($p->approved_by) : null)->name,
                'aprovado_em' => $p->approved_at?->format('Y-m-d H:i'),
            ],
        ];
    }

    /** A linha de um trabalhador, agrupada como o recibo a mostra. */
    private function linhaDoItem(PayrollItem $i): array
    {
        return [
            'id' => $i->id,
            'employee_id' => $i->employee_id,
            'funcionario' => $i->employee
                ? trim($i->employee->first_name . ' ' . $i->employee->last_name)
                : __('Funcionário removido'),
            'numero' => $i->employee?->employee_number,

            'ganhos' => [
                'base_salary' => (float) $i->base_salary,
                'food_allowance' => (float) $i->food_allowance,
                'transport_allowance' => (float) $i->transport_allowance,
                'housing_allowance' => (float) $i->housing_allowance,
                'overtime_pay' => (float) $i->overtime_pay,
                'night_shift_pay' => (float) $i->night_shift_pay,
                'family_allowance' => (float) $i->family_allowance,
                'position_subsidy' => (float) $i->position_subsidy,
                'performance_subsidy' => (float) $i->performance_subsidy,
                'holiday_pay' => (float) $i->holiday_pay,
                'commission' => (float) $i->commission,
                'bonus' => (float) $i->bonus,
                'christmas_subsidy_amount' => (float) $i->christmas_subsidy_amount,
                'vacation_subsidy_amount' => (float) $i->vacation_subsidy_amount,
                'other_earnings' => (float) $i->other_earnings,
            ],
            'bruto' => (float) $i->gross_salary,

            'impostos' => [
                'irt_base' => (float) $i->irt_base,
                'irt_rate' => (float) $i->irt_rate,
                'irt_amount' => (float) $i->irt_amount,
                'inss_base' => (float) $i->inss_base,
                'inss_employee' => (float) $i->inss_employee,
                'inss_employer' => (float) $i->inss_employer,
            ],

            'descontos' => [
                'advance_payment' => (float) $i->advance_payment,
                'loan_deduction' => (float) $i->loan_deduction,
                'discount_deduction' => (float) $i->discount_deduction,
                'absence_deduction' => (float) $i->absence_deduction,
                'late_deduction' => (float) $i->late_deduction,
                'food_deduction' => (float) $i->food_deduction,
                'other_deductions' => (float) $i->other_deductions,
            ],
            'total_descontos' => (float) $i->total_deductions,
            'liquido' => (float) $i->net_salary,

            'tempo' => [
                'worked_days' => (float) $i->worked_days,
                'present_days' => (float) $i->present_days,
                'absence_days' => (float) $i->absence_days,
                'late_days' => (int) $i->late_days,
                'total_working_days' => (int) $i->total_working_days,
                'overtime_hours' => (float) $i->overtime_hours,
                'night_hours' => (float) $i->night_hours,
            ],
        ];
    }
}
