<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\Analytics\GraficosDeFacturacao;
use App\Services\Invoicing\PainelDaFacturacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        /*
         * OS TRÊS GRÁFICOS DO PERÍODO, do MESMO serviço que desenha o ecrã de
         * gráficos.
         *
         * O painel de sempre tinha-os por baixo dos cartões — como recebemos,
         * que artigos vendem e a folga entre vendas e compras — e a migração
         * deixou-os para trás. Vêm daqui e não de contas próprias: dois sítios
         * a somar a mesma coisa acabam sempre a dar números diferentes, e
         * ninguém sabe qual acreditar.
         */
        $g = GraficosDeFacturacao::para(
            $empresa,
            Carbon::parse($dados['periodo']['de']),
            Carbon::parse($dados['periodo']['ate'])
        );

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

            /*
             * OS GRÁFICOS DO PERÍODO. Saem em pares rótulo/valor, já prontos a
             * desenhar — o ecrã não volta a cruzar duas listas paralelas, que
             * é onde uma barra acaba com o nome de outra.
             */
            'graficos' => [
                'meios_de_pagamento' => $this->emPares($g->recebimentosPorMeio()),
                'top_produtos' => $this->emPares($g->topProdutos(5)),
                'vendas_contra_compras' => $this->vendasContraCompras($g->vendasContraCompras()),
            ],

            /*
             * AS ACTIVIDADES RECENTES — as últimas facturas criadas.
             *
             * O serviço já as contava e a API não as entregava: o painel de
             * sempre fechava com esta lista, e é ela que responde a «o que é
             * que se andou a fazer aqui hoje». Sai enxuta, como as outras.
             */
            'actividades' => $dados['recentActivities']->map(fn ($f) => [
                'id' => $f->id,
                'numero' => $f->numeroInterno(),
                'cliente' => $f->client?->name ?? __('Sem cliente'),
                'quando' => $f->created_at?->diffForHumans(),
                'estado' => $f->status_label,
                'cor' => $f->status_color,
            ])->values(),
        ]);
    }

    /**
     * Uma série do serviço de gráficos em pares rótulo/valor.
     *
     * O serviço devolve duas listas paralelas (`rotulos` e `valores`) porque é
     * o que o Chart.js come. O ecrã em React desenha de pares — e duas listas
     * paralelas que se dessincronizam põem uma barra com o nome de outra.
     *
     * @param  array{rotulos?: list<string>, valores?: list<float>}  $serie
     * @return list<array{rotulo: string, valor: float}>
     */
    private function emPares(array $serie): array
    {
        $valores = $serie['valores'] ?? [];

        return collect($serie['rotulos'] ?? [])
            ->map(fn ($r, $i) => ['rotulo' => (string) $r, 'valor' => round((float) ($valores[$i] ?? 0), 2)])
            ->all();
    }

    /**
     * Vendas contra compras: três listas paralelas viram uma lista de pontos.
     *
     * @param  array{rotulos?: list<string>, vendas?: list<float>, compras?: list<float>}  $serie
     * @return list<array{rotulo: string, vendas: float, compras: float, folga: float}>
     */
    private function vendasContraCompras(array $serie): array
    {
        $vendas = $serie['vendas'] ?? [];
        $compras = $serie['compras'] ?? [];

        return collect($serie['rotulos'] ?? [])->map(function ($r, $i) use ($vendas, $compras) {
            $v = round((float) ($vendas[$i] ?? 0), 2);
            $c = round((float) ($compras[$i] ?? 0), 2);

            // A FOLGA vem contada de cá: é o que o painel de sempre dizia por
            // extenso («a folga entre o que entra e o que sai»), e uma
            // subtracção feita no ecrã é uma conta a mais fora do servidor.
            return ['rotulo' => (string) $r, 'vendas' => $v, 'compras' => $c, 'folga' => round($v - $c, 2)];
        })->all();
    }
}
