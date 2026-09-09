<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\Leave;
use App\Models\HR\Payroll;
use App\Models\HR\SalaryAdvance;
use App\Models\HR\Vacation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O PAINEL DO RH — o que está à espera de decisão, e o que a equipa custa.
 *
 * Um painel de RH não é uma lista de números bonitos: é a resposta a «o que é
 * que eu tenho de fazer hoje». Por isso os AVISOS vêm primeiro e cada um traz
 * a morada do ecrã onde se resolve — férias por aprovar, documentos a vencer,
 * adiantamentos à espera, folhas em rascunho.
 *
 * OS AVISOS SÃO FILTRADOS PELA PERMISSÃO DE QUEM VÊ. Mandar alguém para um
 * ecrã que lhe vai dar 403 é pior do que não avisar: parece uma avaria do
 * sistema quando é a guarda a funcionar.
 *
 * A guarda é `hr.dashboard.view` — a permissão que existia e que a rota nunca
 * aplicava.
 */
class PainelApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('hr.dashboard.view'), 403, __('Sem permissão para esta operação.'));

        $tenantId = activeTenantId();
        $hoje = Carbon::today();

        $pessoas = Employee::where('tenant_id', $tenantId);

        $deFerias = Vacation::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $hoje)
            ->whereDate('end_date', '>=', $hoje)
            ->count();

        $porEstado = Attendance::where('tenant_id', $tenantId)
            ->whereDate('date', $hoje)
            ->selectRaw('status, COUNT(*) as quantos')
            ->groupBy('status')
            ->pluck('quantos', 'status');

        $ultimaFolha = Payroll::where('tenant_id', $tenantId)
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        return response()->json([
            'cartoes' => [
                'funcionarios' => (clone $pessoas)->count(),
                'activos' => (clone $pessoas)->where('status', 'active')->count(),
                'de_ferias' => $deFerias,
                'presentes_hoje' => (int) ($porEstado['present'] ?? 0) + (int) ($porEstado['late'] ?? 0),
            ],
            'hoje' => [
                'presentes' => (int) ($porEstado['present'] ?? 0),
                'atrasados' => (int) ($porEstado['late'] ?? 0),
                'ausentes' => (int) ($porEstado['absent'] ?? 0),
                'marcados' => (int) $porEstado->sum(),
            ],
            'folha' => $this->ultimaFolha($ultimaFolha),
            'avisos' => $this->avisos($request, $tenantId, $hoje),
            'graficos' => [
                'custo_mensal' => $this->custoDaFolhaPorMes($tenantId),
                'por_departamento' => $this->porDepartamento($tenantId),
                'presenca_da_semana' => $this->presencaDaSemana($tenantId),
                'por_vinculo' => $this->porVinculo($tenantId),
            ],
            'listas' => [
                'aniversarios' => $this->aniversariantes($tenantId),
                'admissoes' => $this->ultimasAdmissoes($tenantId),
                'proximas_ferias' => $this->proximasFerias($tenantId, $hoje),
            ],
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function ultimaFolha(?Payroll $p): array
    {
        return [
            'existe' => $p !== null,
            'mes' => $p ? Carbon::create($p->year, $p->month, 1)->locale(app()->getLocale())->monthName . ' ' . $p->year : null,
            'estado' => $p?->status,
            'bruto' => (float) ($p->total_gross_salary ?? 0),
            'liquido' => (float) ($p->total_net_salary ?? 0),
            'descontos' => (float) ($p->total_deductions ?? 0),
            'trabalhadores' => (int) ($p->processed_employees ?? 0),
        ];
    }

    /**
     * O QUE ESTÁ À ESPERA DE ALGUÉM — e só o que este alguém pode resolver.
     *
     * @return array<int, array<string, mixed>>
     */
    private function avisos(Request $request, int $tenantId, Carbon $hoje): array
    {
        $utilizador = $request->user();
        $avisos = [];

        $juntar = function (string $permissao, int $quantos, array $aviso) use (&$avisos, $utilizador) {
            if ($quantos > 0 && $utilizador?->can($permissao)) {
                $avisos[] = $aviso;
            }
        };

        $ferias = Vacation::where('tenant_id', $tenantId)->where('status', 'pending')->count();
        $juntar('hr.vacations.view', $ferias, [
            'tom' => 'aviso', 'icone' => 'fa-umbrella-beach',
            'titulo' => __('Férias por aprovar'),
            'texto' => __(':n pedido(s) de férias à espera de decisão', ['n' => $ferias]),
            'morada' => route('hr.vacations.index'), 'accao' => __('Ver pedidos'),
        ]);

        $licencas = Leave::where('tenant_id', $tenantId)->where('status', 'pending')->count();
        $juntar('hr.leaves.view', $licencas, [
            'tom' => 'aviso', 'icone' => 'fa-calendar-xmark',
            'titulo' => __('Licenças por aprovar'),
            'texto' => __(':n licença(s) ou falta(s) à espera de decisão', ['n' => $licencas]),
            'morada' => route('hr.leaves'), 'accao' => __('Ver licenças'),
        ]);

        $adiantamentos = SalaryAdvance::where('tenant_id', $tenantId)->where('status', 'pending')->count();
        $juntar('hr.advances.view', $adiantamentos, [
            'tom' => 'primaria', 'icone' => 'fa-hand-holding-dollar',
            'titulo' => __('Adiantamentos por aprovar'),
            'texto' => __(':n adiantamento(s) à espera de decisão', ['n' => $adiantamentos]),
            'morada' => route('hr.advances'), 'accao' => __('Ver adiantamentos'),
        ]);

        // OS DOCUMENTOS A VENCER: um BI caducado é uma pessoa que não pode ser
        // paga por transferência nem inscrita em lado nenhum.
        $documentos = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('bi_expiry_date')
            ->whereBetween('bi_expiry_date', [$hoje, $hoje->copy()->addMonth()])
            ->count();
        $juntar('employees.view', $documentos, [
            'tom' => 'perigo', 'icone' => 'fa-id-card',
            'titulo' => __('Documentos a vencer'),
            'texto' => __(':n funcionário(s) com o BI a caducar no próximo mês', ['n' => $documentos]),
            'morada' => route('hr.employees.index'), 'accao' => __('Ver funcionários'),
        ]);

        $rascunhos = Payroll::where('tenant_id', $tenantId)->where('status', 'draft')->count();
        $juntar('payroll.process', $rascunhos, [
            'tom' => 'primaria', 'icone' => 'fa-file-invoice-dollar',
            'titulo' => __('Folhas em rascunho'),
            'texto' => __(':n folha(s) de pagamento por aprovar', ['n' => $rascunhos]),
            'morada' => route('hr.payroll'), 'accao' => __('Ver folhas'),
        ]);

        return $avisos;
    }

    /**
     * O CUSTO DA FOLHA, MÊS A MÊS.
     *
     * É a pergunta que a direcção faz: um número sozinho não diz se a massa
     * salarial está a crescer.
     */
    private function custoDaFolhaPorMes(int $tenantId): array
    {
        $desde = Carbon::now()->subMonths(11)->startOfMonth();

        $porMes = DB::table('hr_payrolls')
            ->where('tenant_id', $tenantId)
            ->whereRaw('STR_TO_DATE(CONCAT(year, "-", LPAD(month, 2, "0"), "-01"), "%Y-%m-%d") >= ?', [$desde->format('Y-m-d')])
            ->groupBy('year', 'month')
            ->selectRaw('year, month, SUM(total_net_salary) as total')
            ->get()
            ->keyBy(fn ($l) => sprintf('%04d-%02d', $l->year, $l->month));

        $etiquetas = [];
        $valores = [];

        for ($m = 0; $m < 12; $m++) {
            $data = $desde->copy()->addMonths($m);

            $etiquetas[] = $data->translatedFormat('M/y');
            $valores[] = (float) ($porMes[$data->format('Y-m')]->total ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    private function porDepartamento(int $tenantId): array
    {
        $linhas = DB::table('hr_employees as e')
            ->leftJoin('hr_departments as d', 'd.id', '=', 'e.department_id')
            ->where('e.tenant_id', $tenantId)
            ->where('e.status', 'active')
            ->groupBy('e.department_id', 'd.name')
            ->selectRaw('COALESCE(d.name, ?) as nome, COUNT(*) as total', [__('Sem departamento')])
            ->orderByDesc('total')
            ->get();

        return [
            'etiquetas' => $linhas->pluck('nome')->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }

    private function presencaDaSemana(int $tenantId): array
    {
        $desde = Carbon::today()->subDays(6);

        $porDia = Attendance::where('tenant_id', $tenantId)
            ->whereDate('date', '>=', $desde)
            ->whereIn('status', ['present', 'late'])
            ->selectRaw('DATE(date) as dia, COUNT(*) as quantos')
            ->groupBy('dia')
            ->pluck('quantos', 'dia');

        $etiquetas = [];
        $valores = [];

        for ($i = 0; $i < 7; $i++) {
            $d = $desde->copy()->addDays($i);

            $etiquetas[] = $d->format('d/m');
            $valores[] = (int) ($porDia[$d->toDateString()] ?? 0);
        }

        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }

    /** Como está composta a equipa — muda a leitura de todos os outros números. */
    private function porVinculo(int $tenantId): array
    {
        $rotulos = [
            'permanent' => __('Efectivo'), 'fixed_term' => __('A termo'),
            'temporary' => __('Temporário'), 'internship' => __('Estágio'),
            'contract' => __('Prestação de serviços'), 'probation' => __('Período experimental'),
            'active' => __('Activo'), 'on_leave' => __('De licença'),
            'suspended' => __('Suspenso'), 'retired' => __('Reformado'),
        ];

        $linhas = DB::table('hr_employees')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->groupBy('employment_status')
            ->selectRaw('COALESCE(employment_status, "—") as tipo, COUNT(*) as total')
            ->orderByDesc('total')
            ->get();

        return [
            'etiquetas' => $linhas->map(fn ($l) => $rotulos[$l->tipo] ?? $l->tipo)->all(),
            'valores' => $linhas->map(fn ($l) => (int) $l->total)->all(),
        ];
    }

    private function aniversariantes(int $tenantId): array
    {
        return Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', Carbon::now()->month)
            ->orderByRaw('DAY(birth_date)')
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'birth_date', 'employee_number'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'nome' => $e->full_name,
                'numero' => $e->employee_number,
                'dia' => $e->birth_date?->format('d/m'),
            ])->values()->all();
    }

    private function ultimasAdmissoes(int $tenantId): array
    {
        return Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('hire_date')
            ->orderByDesc('hire_date')
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'hire_date', 'employee_number'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'nome' => $e->full_name,
                'numero' => $e->employee_number,
                'quando' => $e->hire_date?->format('Y-m-d'),
            ])->values()->all();
    }

    private function proximasFerias(int $tenantId, Carbon $hoje): array
    {
        return Vacation::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereDate('start_date', '>', $hoje)
            ->orderBy('start_date')
            ->with('employee:id,first_name,last_name')
            ->limit(5)
            ->get()
            ->map(fn (Vacation $v) => [
                'id' => $v->id,
                'nome' => $v->employee?->full_name ?? '—',
                'de' => $v->start_date?->format('Y-m-d'),
                'ate' => $v->end_date?->format('Y-m-d'),
                'dias' => (int) ($v->requested_days ?? 0),
            ])->values()->all();
    }
}
