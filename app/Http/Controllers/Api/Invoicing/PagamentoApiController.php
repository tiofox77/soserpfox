<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\EmissorDeAdiantamentos;
use App\Services\Invoicing\RegistoDePagamento;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REGISTAR O PAGAMENTO DE UMA FACTURA, para as listas em React.
 *
 * Tudo o que importa — o recibo, a tesouraria, o adiantamento, o excedente,
 * a AGT — vive no `RegistoDePagamento`, o mesmo que o modal Livewire chama.
 * A permissão é a de criar recibos: pagar É emitir um recibo.
 */
class PagamentoApiController extends Controller
{
    public function contexto(Request $request, RegistoDePagamento $registo, string $tipo, int $factura): JsonResponse
    {
        $this->exigir($request);

        $ctx = $registo->contexto($tipo, $factura, activeTenantId());
        $f = $ctx['factura'];

        return response()->json([
            'factura' => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'parte' => $tipo === 'sale' ? ($f->client?->name ?? __('Consumidor Final')) : ($f->supplier?->name ?? ''),
                'total' => round((float) $f->total, 2),
                'pago' => round((float) ($f->paid_amount ?? 0), 2),
            ],
            'por_pagar' => $ctx['por_pagar'],
            'formas' => collect(EmissorDeAdiantamentos::FORMAS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'adiantamentos' => $ctx['adiantamentos']->map(fn ($a) => [
                'id' => $a->id, 'numero' => $a->advance_number, 'disponivel' => round((float) $a->remaining_amount, 2),
            ])->values(),
            'contas' => $ctx['contas']->map(fn ($c) => ['id' => $c->id, 'nome' => trim(($c->bank?->name ? $c->bank->name . ' · ' : '') . $c->account_name)])->values(),
            'caixas' => $ctx['caixas']->map(fn ($c) => ['id' => $c->id, 'nome' => $c->name])->values(),
            'conta_padrao' => $ctx['conta_padrao'],
            'caixa_padrao' => $ctx['caixa_padrao'],
        ]);
    }

    public function registar(Request $request, RegistoDePagamento $registo, string $tipo, int $factura): JsonResponse
    {
        $this->exigir($request);

        $dados = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:' . implode(',', array_keys(RegistoDePagamento::MAPA_METODOS))],
            'account_id' => ['nullable', 'integer'],
            'cash_register_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'advance_id' => ['nullable', 'integer'],
            'advance_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $r = $registo->registar($tipo, $factura, $dados, activeTenantId(), $request->user()?->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['amount' => [$e->getMessage()]]], 422);
        }

        $f = $r['factura'];

        return response()->json([
            'recibo' => $r['recibo']?->receipt_number,
            'estado' => $f->status,
            'excedente' => $r['excedente'],
            'aviso_agt' => $r['aviso_agt'],
            'message' => __('Pagamento registado.')
                . ($r['aviso_agt'] ? ' ' . $r['aviso_agt'] . '.' : '')
                . ($r['adiantamento_criado'] ? ' ' . __('O excedente de :v Kz ficou como adiantamento.', ['v' => number_format($r['excedente'], 2, ',', '.')]) : ''),
        ], 201);
    }

    private function exigir(Request $request): void
    {
        abort_unless($request->user()?->can('invoicing.receipts.create'), 403, __('Sem permissão para esta operação.'));
    }
}
