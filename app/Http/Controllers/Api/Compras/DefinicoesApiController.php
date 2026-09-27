<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\DefinicoesDasCompras;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS REGRAS DO CIRCUITO DAS COMPRAS (27/09/2026): quantas pessoas aprovam a
 * encomenda, quantas o pedido de pagamento, e o tesoureiro por omissão.
 *
 * Zero é «sem aprovação» — o comportamento de sempre. E não se pede mais
 * aprovações do que as pessoas que podem aprovar: uma encomenda que precisa
 * de três «sim» numa empresa com dois aprovadores ficava parada para sempre.
 */
class DefinicoesApiController extends Controller
{
    public function mostrar(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('compras.view'), 403, __('Sem permissão para esta operação.'));

        $d = DefinicoesDasCompras::da((int) activeTenantId());

        return response()->json([
            'data' => [
                'aprovacoes_encomenda' => (int) $d->aprovacoes_encomenda,
                'aprovacoes_pagamento' => (int) $d->aprovacoes_pagamento,
                'tesoureiro_id' => $d->tesoureiro_id ? (string) $d->tesoureiro_id : '',
            ],
            'tesoureiros' => EncomendasApiController::tesoureiros(),
            'aprovadores' => [
                'encomenda' => $this->quantosPodem('compras.encomendas.aprovar'),
                'pagamento' => $this->quantosPodem('compras.pagamentos.aprovar'),
            ],
            'maximo' => DefinicoesDasCompras::MAXIMO_DE_APROVACOES,
            'pode_definir' => (bool) $request->user()?->can('compras.definicoes.manage'),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('compras.definicoes.manage'), 403, __('Sem permissão para esta operação.'));

        $max = DefinicoesDasCompras::MAXIMO_DE_APROVACOES;

        $dados = $request->validate([
            'aprovacoes_encomenda' => ['required', 'integer', 'min:0', "max:{$max}"],
            'aprovacoes_pagamento' => ['required', 'integer', 'min:0', "max:{$max}"],
            'tesoureiro_id' => ['nullable', 'integer', Rule::in(array_map('intval', array_column(EncomendasApiController::tesoureiros(), 'valor')))],
        ], [
            'tesoureiro_id.in' => __('Essa pessoa não pode pagar fornecedores nesta empresa.'),
        ]);

        foreach (['encomenda' => 'compras.encomendas.aprovar', 'pagamento' => 'compras.pagamentos.aprovar'] as $passo => $permissao) {
            $pedidas = (int) $dados["aprovacoes_{$passo}"];
            $podem = $this->quantosPodem($permissao);

            if ($pedidas > $podem) {
                throw ValidationException::withMessages(["aprovacoes_{$passo}" => [
                    __('Só :n pessoa(s) podem aprovar este passo — com :p aprovações exigidas, nada passaria. Dê a permissão a mais pessoas nos Papéis, ou peça menos.', ['n' => $podem, 'p' => $pedidas]),
                ]]);
            }
        }

        $d = DefinicoesDasCompras::da((int) activeTenantId());
        $d->fill([
            'aprovacoes_encomenda' => (int) $dados['aprovacoes_encomenda'],
            'aprovacoes_pagamento' => (int) $dados['aprovacoes_pagamento'],
            'tesoureiro_id' => $dados['tesoureiro_id'] ?? null,
        ])->save();

        return response()->json(['message' => __('Regras das compras guardadas.')]);
    }

    private function quantosPodem(string $permissao): int
    {
        $empresa = auth()->user()?->activeTenant();

        return $empresa
            ? $empresa->users()->wherePivot('is_active', true)->get(['users.id'])->filter(fn ($u) => $u->can($permissao))->count()
            : 0;
    }
}
