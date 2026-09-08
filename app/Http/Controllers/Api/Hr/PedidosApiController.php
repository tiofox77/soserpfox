<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Services\Hr\PedidosDeRh;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * OS SEIS PEDIDOS DO RH, numa API só.
 *
 * Férias, licenças, horas extras, turno nocturno, adiantamentos e descontos:
 * alguém pede, alguém aprova ou recusa com um motivo, e depois paga-se. O que
 * muda entre eles vem do `PedidosDeRh` como esquema — este controlador não
 * sabe o que é um pedido de férias.
 *
 * A CONTA NÃO É AQUI. Criar um pedido chama o serviço próprio
 * (`VacationService`, `OvertimeService`, `SalaryAdvanceService`), que é onde
 * vivem o direito a férias, o multiplicador da hora extra e o tecto do
 * adiantamento. Este controlador valida, chama, e traduz a recusa em 422.
 *
 * A GUARDA É POR VERBO E POR TIPO. Ver não é criar, e criar não é APROVAR:
 * quem pede as suas férias não é quem as autoriza. Nenhuma destas rotas
 * tinha permissão nenhuma antes desta migração.
 */
class PedidosApiController extends Controller
{
    private function definicao(string $tipo): array
    {
        abort_unless(PedidosDeRh::existe($tipo), 404, __('Esse pedido não existe.'));

        return PedidosDeRh::um($tipo);
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    /** A consulta base: desta empresa, e com o filtro que o tipo declare. */
    private function base(array $def)
    {
        $q = $def['modelo']::where('tenant_id', activeTenantId());

        // As horas extras e o turno nocturno partilham a tabela: o esquema é
        // que diz qual metade é a sua.
        if (isset($def['onde'])) {
            ($def['onde'])($q);
        }

        return $q;
    }

    /** O que o ecrã precisa ao abrir: o esquema, as listas e as permissões. */
    public function opcoes(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        return response()->json([
            'titulo' => __($def['titulo']),
            'singular' => __($def['singular']),
            'novo' => __($def['novo']),
            'icone' => $def['icone'],
            'cor' => $def['cor'],
            'descricao' => __($def['descricao']),
            'rota' => $def['rota'],
            'pdf' => $def['pdf'] ?? null,
            'campos' => $def['campos'],
            'colunas' => $def['colunas'],
            'estados' => collect($def['estados'])->map(fn ($e) => [
                'valor' => $e['valor'], 'rotulo' => __($e['rotulo']), 'cor' => $e['cor'],
            ])->values(),
            'accoes' => $def['accoes'],
            'valor' => ['coluna' => $def['valor']['coluna'], 'rotulo' => __($def['valor']['rotulo'])],
            'anexo' => isset($def['anexo']) ? ['rotulo' => __($def['anexo']['rotulo'])] : null,
            // O formulário próprio da aprovação — só o adiantamento o tem.
            'ao_aprovar' => isset($def['ao_aprovar']) ? ['campos' => $def['ao_aprovar']['campos']] : null,
            // Se este pedido ocupa DIAS, e por isso vale a pena ver no mês.
            'calendario' => ! empty($def['calendario']),

            'funcionarios' => Employee::where('tenant_id', activeTenantId())
                ->where('status', 'active')->orderBy('first_name')->limit(500)
                ->get(['id', 'first_name', 'last_name', 'employee_number'])
                ->map(fn ($e) => [
                    'valor' => (string) $e->id,
                    'rotulo' => PedidosDeRh::nomeDoFuncionario($e),
                    'nota' => $e->employee_number,
                ])->values(),

            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can($def['permissoes']['criar']),
                'pode_aprovar' => (bool) $request->user()?->can($def['permissoes']['aprovar']),
                'pode_apagar' => (bool) $request->user()?->can($def['permissoes']['apagar']),
            ],
        ]);
    }

    /** A lista, com procura, filtros e as contagens por estado. */
    public function index(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:30'],
            'funcionario' => ['nullable', 'integer'],
            'ano' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = $this->base($def)->with('employee')
            ->when($filtros['procura'] ?? null, fn ($q, $p) => $q->where(function ($sub) use ($def, $p) {
                foreach ($def['pesquisa'] as $coluna) {
                    $sub->orWhere($coluna, 'like', "%{$p}%");
                }
            }))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when($filtros['funcionario'] ?? null, fn ($q, $f) => $q->where('employee_id', $f))
            ->when($filtros['ano'] ?? null, fn ($q, $a) => $q->whereYear($def['data'], $a))
            ->latest('id');

        $pagina = $q->paginate($filtros['por_pagina'] ?? 15);

        /*
         * AS CONTAGENS SÃO DE TODA A EMPRESA, não da página.
         *
         * «3 pendentes» tem de querer dizer três pendentes — e é o número que
         * diz a quem aprova que tem trabalho à espera.
         */
        $porEstado = $this->base($def)
            ->selectRaw('status, COUNT(*) as quantos')
            ->groupBy('status')->pluck('quantos', 'status');

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Model $m) => $this->linha($def, $m))->values(),
            'meta' => [
                'total' => $pagina->total(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
            ],
            'resumo' => [
                'total' => $porEstado->sum(),
                'por_estado' => collect($def['estados'])->map(fn ($e) => [
                    'valor' => $e['valor'],
                    'rotulo' => __($e['rotulo']),
                    'cor' => $e['cor'],
                    'quantos' => (int) ($porEstado[$e['valor']] ?? 0),
                ])->values(),
                'valor' => (float) $this->base($def)->sum($def['valor']['coluna']),
            ],
        ]);
    }

    /**
     * O CALENDÁRIO DO MÊS — quem está fora, e quando.
     *
     * A lista responde a «que pedidos há»; o calendário responde à pergunta
     * que se faz antes de aprovar mais um: «quem já está fora nessa semana?».
     * O ecrã de férias em Blade tinha-o e é o que impede aprovar meia equipa
     * para a mesma semana.
     *
     * APANHA TUDO O QUE TOCA NO MÊS, e não só o que começa nele: umas férias
     * de 28 de Julho a 10 de Agosto contam nos dois meses.
     */
    public function calendario(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        abort_unless(! empty($def['calendario']), 404, __('Este pedido não tem calendário.'));

        $dados = $request->validate([
            'ano' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $inicio = \Carbon\Carbon::create($dados['ano'], $dados['mes'], 1)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $de = $def['calendario']['de'];
        $ate = $def['calendario']['ate'];

        $registos = $this->base($def)->with('employee')
            ->where($de, '<=', $fim->toDateString())
            ->where($ate, '>=', $inicio->toDateString())
            // Um pedido recusado ou anulado não ocupa ninguém.
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderBy($de)
            ->get();

        return response()->json([
            'de' => $inicio->toDateString(),
            'ate' => $fim->toDateString(),
            'eventos' => $registos->map(fn (Model $m) => [
                'id' => $m->id,
                'numero' => (string) $m->{$def['numero']},
                'funcionario' => PedidosDeRh::nomeDoFuncionario($m->employee),
                'de' => $m->{$de}?->format('Y-m-d'),
                'ate' => $m->{$ate}?->format('Y-m-d'),
                'estado' => $m->status,
            ])->values(),
        ]);
    }

    /** A ficha de um pedido — o modal de ver, sem sair da lista. */
    public function ficha(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        $m = $this->base($def)->with('employee')->findOrFail($id);

        return response()->json(['documento' => $this->ficha_($def, $m)]);
    }

    /**
     * CRIAR — e é o SERVIÇO PRÓPRIO que cria.
     *
     * A recusa do serviço («não tem dias de férias suficientes», «passa o
     * tecto do adiantamento») é uma regra de negócio, não um erro: traduz-se
     * em 422 com a frase que ele escreveu, para o ecrã a mostrar tal e qual.
     */
    public function guardar(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['criar']);

        $dados = $request->validate($def['regras']);

        $this->confirmarFuncionarios($dados);

        $dados['tenant_id'] = activeTenantId();

        try {
            $m = ($def['criar'])($dados);
        } catch (DomainException | \Exception $e) {
            // Uma regra de negócio não é um 500. O serviço escreveu a frase;
            // devolve-se tal como está.
            throw ValidationException::withMessages(['regra' => [$e->getMessage()]]);
        }

        return response()->json([
            'documento' => $this->ficha_($def, $m->fresh(['employee'])),
            'message' => __(':pedido registado.', ['pedido' => __($def['singular'])]),
        ], 201);
    }

    /**
     * APROVAR.
     *
     * Escreve quem e quando — um pedido aprovado sem se saber por quem não
     * serve de prova a ninguém. Só um pedido PENDENTE se aprova: aprovar duas
     * vezes reescrevia a data e apagava quem tinha decidido antes.
     */
    public function aprovar(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['aprovar']);

        abort_unless(! empty($def['accoes']['aprovar']), 404, __('Este pedido não se aprova.'));

        $m = $this->base($def)->findOrFail($id);

        $this->exigirEstado($m, ['pending'], __('Só um pedido pendente se aprova.'));

        $mudar = ['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()];

        // O adiantamento aprova-se por um VALOR, que pode ser menor do que o
        // pedido — e é dele que sai a prestação e o saldo.
        if (isset($def['ao_aprovar'])) {
            $extra = $request->validate($def['ao_aprovar']['regras']);
            $mudar = array_merge($mudar, ($def['ao_aprovar']['aplicar'])($m, $extra));
        }

        $m->update($mudar);

        return response()->json([
            'documento' => $this->ficha_($def, $m->fresh(['employee'])),
            'message' => __('Aprovado.'),
        ]);
    }

    /**
     * RECUSAR — com motivo, que é obrigatório.
     *
     * Uma recusa sem motivo é uma pessoa a perguntar porquê a quem já não se
     * lembra. Dez caracteres, como no ecrã de sempre.
     */
    public function rejeitar(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['aprovar']);

        abort_unless(! empty($def['accoes']['rejeitar']), 404, __('Este pedido não se recusa.'));

        $dados = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'rejection_reason.required' => __('Escreva o motivo da recusa.'),
            'rejection_reason.min' => __('O motivo tem de explicar alguma coisa — pelo menos dez caracteres.'),
        ]);

        $m = $this->base($def)->findOrFail($id);

        $this->exigirEstado($m, ['pending'], __('Só um pedido pendente se recusa.'));

        $m->update([
            'status' => 'rejected',
            'rejection_reason' => $dados['rejection_reason'],
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
        ]);

        return response()->json([
            'documento' => $this->ficha_($def, $m->fresh(['employee'])),
            'message' => __('Recusado.'),
        ]);
    }

    /**
     * DAR POR PAGO.
     *
     * Só depois de aprovado: pagar um pedido que ninguém autorizou é dinheiro
     * a sair sem decisão por trás.
     */
    public function pagar(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['aprovar']);

        abort_unless(! empty($def['accoes']['pagar']), 404, __('Este pedido não se paga por aqui.'));

        $m = $this->base($def)->findOrFail($id);

        // Umas férias já a decorrer também se pagam — era o que o ecrã de
        // sempre deixava, e o pagamento faz-se muitas vezes já com a pessoa
        // fora.
        $this->exigirEstado($m, $def['pagar_a_partir_de'] ?? ['approved'], __('Só um pedido aprovado se paga.'));

        /*
         * QUEM SABE PAGAR É O MODELO, e não este controlador.
         *
         * As colunas não são as mesmas nem o estado muda em todos: nas horas
         * extras e no adiantamento o estado passa a `paid`; nas FÉRIAS não —
         * o `enum` da coluna nem sequer tem esse valor, e o que marca o
         * pagamento é a bandeira `paid`. Escrever `status = 'paid'` à mão
         * dava um 1265 do MySQL em cima do utilizador.
         */
        $m->markAsPaid(auth()->id());

        return response()->json([
            'documento' => $this->ficha_($def, $m->fresh(['employee'])),
            'message' => __('Dado por pago.'),
        ]);
    }

    /** ANULAR — o pedido fica no histórico, marcado como anulado. */
    public function cancelar(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['aprovar']);

        abort_unless(! empty($def['accoes']['cancelar']), 404, __('Este pedido não se anula.'));

        $dados = $request->validate(['cancellation_reason' => ['nullable', 'string', 'max:500']]);

        $m = $this->base($def)->findOrFail($id);

        $this->exigirEstado($m, ['pending', 'approved'], __('Este pedido já não se anula.'));

        $mudar = ['status' => 'cancelled'];

        foreach (['cancellation_reason' => $dados['cancellation_reason'] ?? null, 'cancelled_by' => auth()->id(), 'cancelled_at' => now()] as $coluna => $valor) {
            if (in_array($coluna, $m->getFillable(), true)) {
                $mudar[$coluna] = $valor;
            }
        }

        $m->update($mudar);

        return response()->json([
            'documento' => $this->ficha_($def, $m->fresh(['employee'])),
            'message' => __('Anulado.'),
        ]);
    }

    /**
     * ELIMINAR — e só o que ainda não foi decidido.
     *
     * Um pedido aprovado, pago ou recusado é o registo de uma DECISÃO: apagá-lo
     * deixava o histórico do funcionário com um buraco. Esses anulam-se.
     */
    public function eliminar(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['apagar']);

        $m = $this->base($def)->findOrFail($id);

        $this->exigirEstado($m, ['pending'], __('Um pedido já decidido não se elimina — anule-o.'));

        $m->delete();

        return response()->json(['message' => __('Pedido eliminado.')]);
    }

    /** O ANEXO: o atestado da licença, o documento assinado do desconto. */
    public function anexo(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['criar']);

        abort_unless(isset($def['anexo']), 404, __('Este pedido não leva anexo.'));

        $request->validate(['ficheiro' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);

        $tenantId = activeTenantId();
        $m = $this->base($def)->findOrFail($id);
        $coluna = $def['anexo']['coluna'];

        if ($m->{$coluna}) {
            Storage::disk('public')->delete($m->{$coluna});
        }

        $caminho = $request->file('ficheiro')->storeAs(
            "tenants/{$tenantId}/rh/{$tipo}/{$m->id}",
            'anexo.' . $request->file('ficheiro')->extension(),
            'public',
        );

        $m->update([$coluna => $caminho]);

        return response()->json([
            'documento' => $this->ficha_($def, $m->fresh(['employee'])),
            'message' => __('Anexo carregado.'),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * OS FUNCIONÁRIOS DO PEDIDO SÃO DESTA EMPRESA.
     *
     * Um `exists:hr_employees,id` aceitava o de outra, e o pedido ficava a
     * apontar para fora de casa — com o salário de outra empresa a entrar na
     * conta do adiantamento.
     */
    private function confirmarFuncionarios(array $dados): void
    {
        $tenantId = activeTenantId();

        foreach (['employee_id', 'replacement_employee_id'] as $campo) {
            if (empty($dados[$campo])) {
                continue;
            }

            $daCasa = Employee::where('tenant_id', $tenantId)->whereKey($dados[$campo])->exists();

            if (! $daCasa) {
                throw ValidationException::withMessages([
                    $campo => [__('Esse funcionário não é desta empresa.')],
                ]);
            }
        }
    }

    /** @param  array<int, string>  $permitidos */
    private function exigirEstado(Model $m, array $permitidos, string $frase): void
    {
        if (! in_array($m->status, $permitidos, true)) {
            throw ValidationException::withMessages(['status' => [$frase]]);
        }
    }

    /** A linha da lista: o que se lê de relance. */
    private function linha(array $def, Model $m): array
    {
        $linha = [
            'id' => $m->id,
            'numero' => (string) $m->{$def['numero']},
            'funcionario' => PedidosDeRh::nomeDoFuncionario($m->employee),
            'estado' => $m->status,
            'valor' => (float) ($m->{$def['valor']['coluna']} ?? 0),
            'criado_em' => $m->created_at?->format('Y-m-d'),
        ];

        foreach ($def['colunas'] as $c) {
            $valor = $m->{$c['chave']};

            $linha[$c['chave']] = $valor instanceof \DateTimeInterface ? $valor->format('Y-m-d') : $valor;

            // O rótulo de uma escolha vem já traduzido: o ecrã não sabe o que
            // é `bereavement`.
            if ($c['formato'] === 'escolha') {
                $opcoes = collect($def['campos'])->firstWhere('chave', $c['chave'])['opcoes'] ?? [];
                $linha['rotulos'][$c['chave']] = collect($opcoes)->firstWhere('valor', (string) $valor)['rotulo'] ?? (string) $valor;
            }
        }

        return $linha;
    }

    /** A ficha inteira, para o modal de ver e para o formulário. */
    private function ficha_(array $def, Model $m): array
    {
        $ficha = $this->linha($def, $m);

        foreach ($def['campos'] as $c) {
            $valor = $m->{$c['chave']};

            $ficha[$c['chave']] = match (true) {
                $valor instanceof \DateTimeInterface => $c['tipo'] === 'hora' ? $valor->format('H:i') : $valor->format('Y-m-d'),
                default => $valor,
            };
        }

        // Quem decidiu, e quando — é o que faz o pedido servir de prova.
        $ficha['decisao'] = [
            'aprovado_por' => optional($m->approved_by ? \App\Models\User::find($m->approved_by) : null)->name,
            'aprovado_em' => $m->approved_at?->format('Y-m-d H:i'),
            'recusado_por' => optional($m->rejected_by ? \App\Models\User::find($m->rejected_by) : null)->name,
            'recusado_em' => $m->rejected_at?->format('Y-m-d H:i'),
            'motivo_da_recusa' => $m->rejection_reason,
        ];

        $ficha['anexo'] = isset($def['anexo']) && $m->{$def['anexo']['coluna']}
            ? Storage::disk('public')->url($m->{$def['anexo']['coluna']})
            : null;

        return $ficha;
    }
}
