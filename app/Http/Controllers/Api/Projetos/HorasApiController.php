<?php

namespace App\Http\Controllers\Api\Projetos;

use App\Http\Controllers\Controller;
use App\Models\Projetos\HoraLancada;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;
use App\Services\Projetos\RegistoDeHoras;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A FOLHA DE HORAS — a semana de quem a está a ver.
 *
 * MOSTRA-SE UMA SEMANA DE CADA VEZ, de propósito: quem lança horas lança-as do
 * que se lembra, e a memória não vai além de dias. Uma lista infinita convida
 * a lançar tudo ao molho no fim do mês, e horas lançadas ao molho são horas
 * inventadas.
 *
 * SÓ AS PRÓPRIAS HORAS — a menos que se tenha `projetos.horas.gerir`. Sem isso,
 * um id vindo do browser abria a folha de horas dos colegas.
 */
class HorasApiController extends Controller
{
    public function __construct(private readonly RegistoDeHoras $registo) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    /**
     * A minha linha — ou a de qualquer um, com a permissão de gerir.
     *
     * É a guarda que faz a diferença entre uma folha de horas e um registo
     * partilhado onde qualquer um corrige as horas de qualquer um.
     */
    private function minha(Request $request, int $id): HoraLancada
    {
        $q = HoraLancada::forTenant();

        if (! $request->user()?->can('projetos.horas.gerir')) {
            $q->where('user_id', $request->user()?->id);
        }

        return $q->findOrFail($id);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'projetos.horas.registar');

        return response()->json([
            'projetos' => Projeto::forTenant()->whereIn('estado', Projeto::ABERTOS)
                ->orderBy('nome')->get(['id', 'codigo', 'nome'])
                ->map(fn ($p) => ['valor' => (string) $p->id, 'rotulo' => $p->codigo.' · '.$p->nome])->values(),
            'permissoes' => [
                'pode_gerir_de_todos' => (bool) $request->user()?->can('projetos.horas.gerir'),
            ],
        ]);
    }

    /** As tarefas abertas do projeto escolhido — para o formulário. */
    public function tarefas(Request $request, int $projeto): JsonResponse
    {
        $this->exigir($request, 'projetos.horas.registar');

        Projeto::forTenant()->findOrFail($projeto);

        return response()->json([
            'data' => Tarefa::forTenant()
                ->where('projeto_id', $projeto)
                ->whereIn('estado', Tarefa::ABERTAS)
                ->orderBy('titulo')->get(['id', 'titulo'])
                ->map(fn ($t) => ['valor' => (string) $t->id, 'rotulo' => $t->titulo])->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'projetos.horas.registar');

        $dados = $request->validate([
            'semana' => ['nullable', 'date'],
        ]);

        $inicio = Carbon::parse($dados['semana'] ?? now())->startOfWeek();
        $fim = $inicio->copy()->endOfWeek();

        $linhas = HoraLancada::forTenant()
            ->where('user_id', $request->user()?->id)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->with(['projeto:id,codigo,nome', 'tarefa:id,titulo'])
            ->orderBy('data')
            ->get();

        $dias = [];

        foreach (range(0, 6) as $i) {
            $dia = $inicio->copy()->addDays($i);
            $doDia = $linhas->filter(fn (HoraLancada $l) => $l->data->isSameDay($dia));

            $dias[] = [
                'dia' => $dia->toDateString(),
                'rotulo' => $dia->translatedFormat('D d/m'),
                'hoje' => $dia->isToday(),
                'total' => round((float) $doDia->sum('horas'), 2),
                'linhas' => $doDia->map(fn (HoraLancada $l) => $this->linha($l))->values(),
            ];
        }

        return response()->json([
            'semana' => $inicio->toDateString(),
            'de' => $inicio->toDateString(),
            'ate' => $fim->toDateString(),
            'dias' => $dias,
            'resumo' => [
                'total' => round((float) $linhas->sum('horas'), 2),
                'facturavel' => round((float) $linhas->where('facturavel', true)->sum('horas'), 2),
                // Quantas já saíram em documento — as que já não se corrigem.
                'facturadas' => round((float) $linhas->filter(fn ($l) => $l->jaFacturada())->sum('horas'), 2),
            ],
        ]);
    }

    private function linha(HoraLancada $l): array
    {
        return [
            'id' => $l->id,
            'projeto_id' => $l->projeto_id,
            'projeto' => $l->projeto?->codigo,
            'projeto_nome' => $l->projeto?->nome,
            'tarefa_id' => $l->tarefa_id,
            'tarefa' => $l->tarefa?->titulo,
            'dia' => $l->data?->format('Y-m-d'),
            'horas' => (float) $l->horas,
            'descricao' => $l->descricao,
            'facturavel' => (bool) $l->facturavel,
            'valor_hora' => $l->valor_hora !== null ? (float) $l->valor_hora : null,
            // JÁ FACTURADA é o que torna a linha intocável: está a sustentar um
            // documento emitido, e mexer-lhe mudaria o que o cliente já viu.
            'facturada' => $l->jaFacturada(),
        ];
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'projetos.horas.registar');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'projeto_id' => ['required', Rule::exists('projetos', 'id')->where('tenant_id', $tenantId)],
            'tarefa_id' => ['nullable', 'integer'],
            'data' => ['required', 'date'],
            'horas' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'facturavel' => ['boolean'],
        ], [], ['projeto_id' => __('projeto'), 'horas' => __('horas')]);

        try {
            if ($id) {
                $l = $this->registo->actualizar($this->minha($request, $id), $tenantId, $dados);
                $mensagem = __('Lançamento corrigido.');
            } else {
                $l = $this->registo->lancar($tenantId, $request->user()?->id, $dados);
                $mensagem = __('Horas lançadas.');
            }
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json([
            'message' => $mensagem,
            'data' => $this->linha($l->fresh(['projeto', 'tarefa'])),
        ], $id ? 200 : 201);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'projetos.horas.registar');

        try {
            $this->registo->apagar($this->minha($request, $id), activeTenantId());
        } catch (\InvalidArgumentException $e) {
            $this->recusa($e->getMessage());
        }

        return response()->json(['message' => __('Lançamento apagado.')]);
    }
}
