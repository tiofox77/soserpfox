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
 * é a fonte única destas somas — é essa a razão de o serviço existir. Se as
 * contas vivessem aqui, o painel e os relatórios que bebem do mesmo sítio
 * passavam a dar números diferentes ao primeiro ajuste, e ninguém saberia qual
 * acreditar. Só o formato muda: aqui sai JSON.
 *
 * O PERÍODO VEM NO PEDIDO e vai inteiro para o serviço. É ele que o valida
 * contra os atalhos conhecidos — assim os cartões, o gráfico e o título saem
 * todos do mesmo intervalo, que era o que o selector do painel de sempre
 * garantia e a migração perdeu.
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

        $empresa = activeTenantId();
        $periodo = PainelDaFacturacao::periodo($request->query('periodo'));

        $dados = $painel->numeros($empresa, $periodo);

        return response()->json([
            // O período escolhido, com o rótulo já traduzido e a lista para a
            // caixa de escolha: o ecrã não guarda uma segunda lista de atalhos.
            'periodo' => $dados['periodo'],

            'stats' => $dados['stats'],
            'documentos' => $dados['documents'],
            'estado_das_facturas' => $dados['invoiceStatus'],

            // A linha do gráfico: dias ou meses, conforme o período pedido.
            'serie' => $painel->serie($empresa, $periodo),

            // O ano inteiro, mês a mês, que não depende do período escolhido.
            'por_mes' => $painel->porMes($empresa),
            'por_mes_ano_passado' => $painel->porMes($empresa, (int) now()->subYear()->year),

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
