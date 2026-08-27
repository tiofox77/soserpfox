<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\HR\MapaDeIRT;
use Illuminate\Http\Request;

/**
 * O mapa de IRT em papel e em ficheiro.
 *
 * O ecrã serve para conferir; isto serve para entregar. Ambos leem o MESMO
 * serviço — se o papel fosse montado à parte, um dia dizia coisa diferente do
 * que se viu no ecrã, e só se descobria depois de entregue.
 */
class MapaDeIRTController extends Controller
{
    public function __construct(private MapaDeIRT $mapas)
    {
    }

    public function imprimir(Request $request)
    {
        [$ano, $mes, $departamento] = $this->periodo($request);

        $empresa = Tenant::find(activeTenantId());
        $mapa = $this->mapas->paraMes(activeTenantId(), $ano, $mes, ['departamento' => $departamento]);

        // HTML com botão de imprimir, como os recibos do RH: o mapa é largo e
        // sai melhor em paisagem no diálogo do browser do que num A4 vertical
        // gerado à força.
        return view('pdf.hr.mapa-irt', [
            'mapa'    => $mapa,
            'empresa' => $empresa,
            'ano'     => $ano,
            'mes'     => $mes,
        ]);
    }

    public function csv(Request $request)
    {
        [$ano, $mes, $departamento] = $this->periodo($request);

        $empresa = Tenant::find(activeTenantId());
        $mapa = $this->mapas->paraMes(activeTenantId(), $ano, $mes, ['departamento' => $departamento]);

        $conteudo = $this->mapas->csv($mapa, $empresa->name ?? '', $empresa->nif ?? '');
        $ficheiro = 'mapa_irt_' . $ano . '_' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '.csv';

        return response($conteudo, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $ficheiro . '"',
        ]);
    }

    /**
     * Ano e mês validados. Vêm do URL, e um mês 13 ou um ano 0 fariam o
     * Carbon saltar para outro período sem se queixar.
     */
    private function periodo(Request $request): array
    {
        $dados = $request->validate([
            'ano'          => 'required|integer|min:2000|max:2100',
            'mes'          => 'required|integer|min:1|max:12',
            'departamento' => 'nullable|integer',
        ]);

        return [
            (int) $dados['ano'],
            (int) $dados['mes'],
            $dados['departamento'] ?? null,
        ];
    }
}
