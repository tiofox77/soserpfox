<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use App\Services\HR\MapaDeIRT;
use App\Services\HR\VacationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OS MAPAS DO RH — cinco relatórios e o mapa de IRT.
 *
 * Um mapa de salários é o salário de toda a gente numa página só, e o mapa de
 * IRT é o que se entrega à AGT. Nenhum deles tinha permissão nenhuma: bastava
 * ter o módulo de RH activo para os abrir. Ganham `hr.reports.view` e
 * `hr.irt.view`.
 *
 * NENHUM RECALCULA NADA. O mapa de salários e o de custos lêem as linhas da
 * folha; o de IRT lê o `MapaDeIRT`, que lê o que ficou retido no
 * processamento. Se recalculassem, o mapa podia dizer um valor e os recibos
 * que os trabalhadores têm em casa dizerem outro.
 *
 * O SALDO DE FÉRIAS é a excepção que confirma a regra: pergunta ao
 * `VacationService`, que é a fonte única do direito a férias — reimplementar a
 * conta aqui era ter duas verdades sobre quantos dias uma pessoa tem.
 */
class RelatoriosApiController extends Controller
{
    /** Os cinco mapas, com o que cada um pede. */
    private const MAPAS = [
        'mapa_de_salarios' => ['rotulo' => 'Mapa de Salários', 'icone' => 'fa-money-check-dollar', 'mes' => true, 'departamento' => true],
        'custo_por_departamento' => ['rotulo' => 'Custo por Departamento', 'icone' => 'fa-sitemap', 'mes' => true, 'departamento' => false],
        'resumo_de_presencas' => ['rotulo' => 'Resumo de Presenças', 'icone' => 'fa-clipboard-check', 'mes' => true, 'departamento' => true],
        'saldo_de_ferias' => ['rotulo' => 'Saldo de Férias', 'icone' => 'fa-umbrella-beach', 'mes' => false, 'departamento' => true],
        'quadro_de_pessoal' => ['rotulo' => 'Evolução do Quadro de Pessoal', 'icone' => 'fa-chart-line', 'mes' => false, 'departamento' => false],
    ];

    /**
     * OS ESTADOS QUE CONTAM COMO FALTA JUSTIFICADA.
     *
     * Estava escrito dentro do componente Livewire; sai para uma constante
     * porque é a regra que decide a taxa de assiduidade de cada pessoa.
     */
    private const JUSTIFICADAS = [
        'sick', 'vacation', 'sick_leave', 'on_leave', 'maternity_leave', 'paternity_leave',
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'hr.reports.view');

        return response()->json([
            'mapas' => collect(self::MAPAS)->map(fn ($m, $chave) => [
                'valor' => $chave,
                'rotulo' => __($m['rotulo']),
                'icone' => $m['icone'],
                'pede_mes' => $m['mes'],
                'pede_departamento' => $m['departamento'],
            ])->values(),
            'departamentos' => Department::where('tenant_id', activeTenantId())
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['valor' => (string) $d->id, 'rotulo' => $d->name])->values(),
            'meses' => collect(range(1, 12))->map(fn ($m) => [
                'valor' => (string) $m,
                'rotulo' => Carbon::create(null, $m, 1)->locale(app()->getLocale())->monthName,
            ])->values(),
            'anos' => collect(range((int) now()->year, (int) now()->year - 5))
                ->map(fn ($a) => ['valor' => (string) $a, 'rotulo' => (string) $a])->values(),
        ]);
    }

    public function mostrar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hr.reports.view');

        $dados = $request->validate([
            'mapa' => ['required', 'string', 'in:' . implode(',', array_keys(self::MAPAS))],
            'ano' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
            'departamento' => ['nullable', 'integer'],
        ]);

        $tenantId = activeTenantId();
        $ano = (int) $dados['ano'];
        $mes = (int) ($dados['mes'] ?? now()->month);
        $departamento = $dados['departamento'] ?? null;

        return response()->json([
            'mapa' => $dados['mapa'],
            'periodo' => [
                'ano' => $ano,
                'mes' => $mes,
                'nome_do_mes' => Carbon::create($ano, $mes, 1)->locale(app()->getLocale())->monthName,
            ],
        ] + match ($dados['mapa']) {
            'mapa_de_salarios' => $this->mapaDeSalarios($tenantId, $ano, $mes, $departamento),
            'custo_por_departamento' => $this->custoPorDepartamento($tenantId, $ano, $mes),
            'resumo_de_presencas' => $this->resumoDePresencas($tenantId, $ano, $mes, $departamento),
            'saldo_de_ferias' => $this->saldoDeFerias($tenantId, $ano, $departamento),
            'quadro_de_pessoal' => $this->quadroDePessoal($tenantId, $ano),
        });
    }

    /* ─── Mapa de salários ────────────────────────────────────────────── */

    private function mapaDeSalarios(int $tenantId, int $ano, int $mes, ?int $departamento): array
    {
        $folha = Payroll::where('tenant_id', $tenantId)->where('year', $ano)->where('month', $mes)->first();

        if (! $folha) {
            return ['linhas' => [], 'totais' => null, 'nada' => __('Não há folha de pagamento neste mês.')];
        }

        $itens = PayrollItem::where('payroll_id', $folha->id)
            ->with(['employee:id,first_name,last_name,employee_number,department_id', 'employee.department:id,name'])
            ->when($departamento, fn ($q, $d) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $d)))
            ->get()
            ->sortBy(fn (PayrollItem $i) => $i->employee?->full_name ?? '')
            ->values();

        return [
            'linhas' => $itens->map(fn (PayrollItem $i) => [
                'id' => $i->id,
                'nome' => $i->employee?->full_name ?? '—',
                'numero' => $i->employee?->employee_number,
                'departamento' => $i->employee?->department?->name,
                'base' => (float) $i->base_salary,
                'alimentacao' => (float) $i->food_allowance,
                'transporte' => (float) $i->transport_allowance,
                'bruto' => (float) $i->gross_salary,
                'inss' => (float) $i->inss_employee,
                'irt' => (float) $i->irt_amount,
                'descontos' => (float) $i->total_deductions,
                'liquido' => (float) $i->net_salary,
            ])->all(),
            'totais' => [
                'base' => (float) $itens->sum('base_salary'),
                'alimentacao' => (float) $itens->sum('food_allowance'),
                'transporte' => (float) $itens->sum('transport_allowance'),
                'bruto' => (float) $itens->sum('gross_salary'),
                'inss' => (float) $itens->sum('inss_employee'),
                'irt' => (float) $itens->sum('irt_amount'),
                'descontos' => (float) $itens->sum('total_deductions'),
                'liquido' => (float) $itens->sum('net_salary'),
            ],
            'folha' => ['numero' => $folha->payroll_number, 'estado' => $folha->status],
        ];
    }

    /* ─── Custo por departamento ──────────────────────────────────────── */

    private function custoPorDepartamento(int $tenantId, int $ano, int $mes): array
    {
        $folha = Payroll::where('tenant_id', $tenantId)->where('year', $ano)->where('month', $mes)->first();

        if (! $folha) {
            return ['linhas' => [], 'totais' => null, 'nada' => __('Não há folha de pagamento neste mês.')];
        }

        $linhas = PayrollItem::where('payroll_id', $folha->id)
            ->with(['employee:id,department_id', 'employee.department:id,name'])
            ->get()
            ->groupBy(fn (PayrollItem $i) => $i->employee?->department?->name ?? __('Sem departamento'))
            ->map(fn ($itens, $nome) => [
                'departamento' => $nome,
                'pessoas' => $itens->count(),
                'bruto' => (float) $itens->sum('gross_salary'),
                'inss' => (float) $itens->sum('inss_employee') + (float) $itens->sum('inss_employer'),
                'irt' => (float) $itens->sum('irt_amount'),
                'liquido' => (float) $itens->sum('net_salary'),
                'media' => $itens->count() > 0 ? round((float) $itens->sum('net_salary') / $itens->count(), 2) : 0.0,
            ])
            ->sortByDesc('bruto')
            ->values();

        return [
            'linhas' => $linhas->all(),
            'totais' => [
                'pessoas' => (int) $linhas->sum('pessoas'),
                'bruto' => (float) $linhas->sum('bruto'),
                'inss' => (float) $linhas->sum('inss'),
                'irt' => (float) $linhas->sum('irt'),
                'liquido' => (float) $linhas->sum('liquido'),
            ],
            'folha' => ['numero' => $folha->payroll_number, 'estado' => $folha->status],
        ];
    }

    /* ─── Resumo de presenças ─────────────────────────────────────────── */

    private function resumoDePresencas(int $tenantId, int $ano, int $mes, ?int $departamento): array
    {
        $inicio = Carbon::create($ano, $mes, 1)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $pessoas = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->when($departamento, fn ($q, $d) => $q->where('department_id', $d))
            ->with('department:id,name')
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_number', 'department_id']);

        // UMA CONSULTA PARA TODA A GENTE, e não uma por pessoa: o ecrã em
        // Livewire fazia um SELECT por funcionário dentro do `map`, e numa
        // empresa com cem pessoas eram cem idas à base para desenhar a tabela.
        $porPessoa = Attendance::where('tenant_id', $tenantId)
            ->whereIn('employee_id', $pessoas->pluck('id'))
            ->whereBetween('date', [$inicio->toDateString(), $fim->toDateString()])
            ->selectRaw('employee_id, status, COUNT(*) as quantos')
            ->groupBy('employee_id', 'status')
            ->get()
            ->groupBy('employee_id');

        $linhas = $pessoas->map(function (Employee $e) use ($porPessoa) {
            $contas = ($porPessoa[$e->id] ?? collect())->pluck('quantos', 'status');

            $presentes = (int) ($contas['present'] ?? 0);
            $atrasos = (int) ($contas['late'] ?? 0);
            $meioDia = (int) ($contas['half_day'] ?? 0);
            $faltas = (int) ($contas['absent'] ?? 0);
            $justificadas = (int) $contas->filter(fn ($n, $estado) => in_array($estado, self::JUSTIFICADAS, true))->sum();

            $equivalente = $presentes + $atrasos + ($meioDia * 0.5);
            $total = (int) $contas->sum();

            return [
                'id' => $e->id,
                'nome' => $e->full_name,
                'numero' => $e->employee_number,
                'departamento' => $e->department?->name,
                'equivalente' => round($equivalente, 1),
                'atrasos' => $atrasos,
                'meio_dia' => $meioDia,
                'faltas' => $faltas,
                'justificadas' => $justificadas,
                'marcados' => $total,
                'taxa' => $total > 0 ? round(min(100, (($equivalente + $justificadas) / $total) * 100), 1) : 0.0,
            ];
        })->values();

        return [
            'linhas' => $linhas->all(),
            'totais' => [
                'pessoas' => $linhas->count(),
                'equivalente' => round((float) $linhas->sum('equivalente'), 1),
                'faltas' => (int) $linhas->sum('faltas'),
                'atrasos' => (int) $linhas->sum('atrasos'),
            ],
            'nada' => $linhas->isEmpty() ? __('Não há funcionários activos com estes filtros.') : null,
        ];
    }

    /* ─── Saldo de férias ─────────────────────────────────────────────── */

    private function saldoDeFerias(int $tenantId, int $ano, ?int $departamento): array
    {
        $ferias = app(VacationService::class);

        $linhas = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->when($departamento, fn ($q, $d) => $q->where('department_id', $d))
            ->with('department:id,name')
            ->orderBy('first_name')->orderBy('last_name')
            ->get()
            ->map(function (Employee $e) use ($ferias, $ano) {
                $saldo = $ferias->getAvailableVacationDays($e, $ano);

                return [
                    'id' => $e->id,
                    'nome' => $e->full_name,
                    'numero' => $e->employee_number,
                    'departamento' => $e->department?->name,
                    'direito' => (int) $saldo['entitled'],
                    'gozados' => (int) $saldo['used'],
                    'saldo' => (int) $saldo['available'],
                ];
            })
            ->values();

        return [
            'linhas' => $linhas->all(),
            'totais' => [
                'pessoas' => $linhas->count(),
                'direito' => (int) $linhas->sum('direito'),
                'gozados' => (int) $linhas->sum('gozados'),
                'saldo' => (int) $linhas->sum('saldo'),
            ],
            'nada' => $linhas->isEmpty() ? __('Não há funcionários activos com estes filtros.') : null,
        ];
    }

    /* ─── Quadro de pessoal ───────────────────────────────────────────── */

    private function quadroDePessoal(int $tenantId, int $ano): array
    {
        $linhas = [];
        $anterior = null;

        for ($m = 1; $m <= 12; $m++) {
            $mes = Carbon::create($ano, $m, 1)->startOfMonth();

            if ($mes->gt(now()->startOfMonth())) {
                break;
            }

            // O mês corrente é uma fotografia de hoje; os anteriores usam o
            // último dia do mês. Assim o mês em curso não desaparece até ao 30.
            $quando = $mes->isSameMonth(now()) ? now()->endOfDay() : $mes->copy()->endOfMonth();

            $quantos = Employee::where('tenant_id', $tenantId)
                ->whereNotNull('hire_date')
                ->where('hire_date', '<=', $quando)
                ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>', $quando))
                ->count();

            $linhas[] = [
                'mes' => $m,
                'nome_do_mes' => $mes->locale(app()->getLocale())->monthName,
                'pessoas' => $quantos,
                'variacao' => $anterior === null ? null : $quantos - $anterior,
            ];

            $anterior = $quantos;
        }

        return [
            'linhas' => $linhas,
            'totais' => [
                'inicio' => $linhas[0]['pessoas'] ?? 0,
                'fim' => end($linhas)['pessoas'] ?? 0,
            ],
            'grafico' => [
                'etiquetas' => array_column($linhas, 'nome_do_mes'),
                'valores' => array_column($linhas, 'pessoas'),
            ],
            'nada' => $linhas === [] ? __('Este ano ainda não começou.') : null,
        ];
    }

    /* ─── Mapa de IRT ─────────────────────────────────────────────────── */

    /**
     * O IMPOSTO RETIDO NO MÊS, na forma em que se declara.
     *
     * Abre no MÊS PASSADO: o IRT retido entrega-se depois de o mês fechar, por
     * isso é esse que se está a preparar quando se abre este ecrã.
     */
    public function irt(Request $request, MapaDeIRT $mapas): JsonResponse
    {
        $this->exigir($request, 'hr.irt.view');

        $anterior = now()->subMonthNoOverflow();

        $dados = $request->validate([
            'ano' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
            'departamento' => ['nullable', 'integer'],
        ]);

        $ano = (int) ($dados['ano'] ?? $anterior->year);
        $mes = (int) ($dados['mes'] ?? $anterior->month);

        $mapa = $mapas->paraMes(activeTenantId(), $ano, $mes, [
            'departamento' => $dados['departamento'] ?? null,
        ]);

        return response()->json([
            'periodo' => [
                'ano' => $ano,
                'mes' => $mes,
                'nome_do_mes' => Carbon::create($ano, $mes, 1)->locale(app()->getLocale())->monthName,
                'etiqueta' => $mapa['periodo'],
            ],
            'linhas' => $mapa['linhas'],
            'totais' => $mapa['totais'],
            // AS FOLHAS QUE FICARAM DE FORA MOSTRAM-SE, e não se escondem: um
            // rascunho por aprovar é a razão nº1 de o mapa não bater com o que
            // a contabilidade espera, e descobri-lo depois de entregar é tarde.
            'folhas' => collect($mapa['folhas'])->map(fn ($f) => [
                'numero' => $f->payroll_number, 'estado' => $f->status,
            ])->values(),
            'ignoradas' => collect($mapa['ignoradas'])->map(fn ($f) => [
                'numero' => $f->payroll_number, 'estado' => $f->status,
            ])->values(),
            'departamentos' => Department::where('tenant_id', activeTenantId())
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['valor' => (string) $d->id, 'rotulo' => $d->name])->values(),
        ]);
    }
}
