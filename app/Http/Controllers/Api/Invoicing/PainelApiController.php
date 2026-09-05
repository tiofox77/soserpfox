<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\PainelDaFacturacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Os números do painel da facturação.
 *
 * ESTE CONTROLADOR NÃO FAZ CONTAS NENHUMAS. Chama o `PainelDaFacturacao`, que
 * é o mesmo serviço que o componente Livewire usa — é essa a razão de o
 * serviço existir. Se as contas vivessem aqui, os dois painéis passavam a dar
 * números diferentes ao primeiro ajuste, e ninguém saberia qual acreditar.
 *
 * Só o formato muda: aqui sai JSON, lá sai HTML.
 */
class PainelApiController extends Controller
{
    public function __invoke(Request $request, PainelDaFacturacao $painel): JsonResponse
    {
        abort_unless(
            $request->user()?->can('invoicing.dashboard.view'),
            403,
            __('Sem permissão para ver o painel da facturação.')
        );

        $dados = $painel->numeros(activeTenantId());

        return response()->json([
            'stats' => $dados['stats'],
            'documentos' => $dados['documents'],
            'estado_das_facturas' => $dados['invoiceStatus'],

            // O ano mês a mês, com os doze meses sempre presentes.
            'por_mes' => $painel->porMes(activeTenantId()),
            'por_mes_ano_passado' => $painel->porMes(activeTenantId(), (int) now()->subYear()->year),

            // As listas saem enxutas: o painel mostra número, cliente, data e
            // saldo, e mais nada. Devolver o modelo inteiro publicava a
            // assinatura fiscal numa resposta que ninguém precisa dela.
            'por_cobrar' => $dados['pendingInvoices']->map(fn ($f) => [
                'id' => $f->id,
                'numero' => $f->numeroInterno(),
                'cliente' => $f->client?->name ?? __('Consumidor Final'),
                'vencimento' => optional($f->due_date)->toDateString(),
                'vencida' => $f->due_date && $f->due_date->isPast(),
                'saldo' => round((float) $f->total - (float) $f->paid_amount, 2),
            ])->values(),

            'melhores_clientes' => $dados['topClients']->map(fn ($l) => [
                'cliente' => $l->client?->name ?? __('Consumidor Final'),
                'total' => round((float) $l->total_amount, 2),
                'documentos' => (int) $l->invoice_count,
            ])->values(),
        ]);
    }
}
