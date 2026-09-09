<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Services\Workshop\MapasDaOficina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OS MAPAS DA OFICINA — cinco, com as colunas declaradas pelo servidor.
 *
 * O ecrã desenha UMA tabela para os cinco, porque cada mapa diz que colunas
 * tem e em que formato. A alternativa era cinco tabelas escritas à mão em
 * React mais duas listas de colunas nos ficheiros do papel e do Excel: quatro
 * sítios a divergir à primeira coluna nova.
 *
 * A guarda é `workshop.reports.view` — a mesma da rota.
 */
class RelatoriosApiController extends Controller
{
    public function __construct(private readonly MapasDaOficina $mapas) {}

    private function exigir(Request $request): void
    {
        abort_unless($request->user()?->can('workshop.reports.view'), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request);

        return response()->json([
            'mapas' => collect(MapasDaOficina::MAPAS)->map(fn ($m, $chave) => [
                'valor' => $chave,
                'rotulo' => __($m['rotulo']),
                'icone' => $m['icone'],
                'pede_estado' => $m['estado'],
            ])->values(),
            'estados' => collect(MapasDaOficina::ESTADOS)
                ->map(fn ($rotulo, $chave) => ['valor' => $chave, 'rotulo' => __($rotulo)])
                ->values(),
        ]);
    }

    public function mostrar(Request $request): JsonResponse
    {
        $this->exigir($request);

        $filtros = self::validarFiltros($request);

        $mapa = $this->mapas->mapa($filtros['mapa'], activeTenantId(), $filtros);

        // As moradas do papel e do Excel VÊM DAQUI e não são compostas no
        // browser: o ecrã não sabe (nem tem de saber) que filtros é que a
        // exportação aceita, e assim os dois nunca discordam.
        $comFiltros = array_filter($filtros, fn ($v) => $v !== null && $v !== '');

        return response()->json([
            'mapa' => $filtros['mapa'],
            'descargas' => [
                'pdf' => route('workshop.reports.imprimir', $comFiltros),
                'excel' => route('workshop.reports.excel', $comFiltros),
            ],
        ] + $mapa);
    }

    /**
     * OS FILTROS, validados no mesmo sítio para o ecrã e para a exportação.
     *
     * Se o papel aceitasse um filtro que o ecrã recusa, o mapa impresso podia
     * mostrar outra coisa do que se estava a ver — que é pior do que não haver
     * papel nenhum.
     */
    public static function validarFiltros(Request $request): array
    {
        return $request->validate([
            'mapa' => ['required', 'string', 'in:' . implode(',', array_keys(MapasDaOficina::MAPAS))],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'estado' => ['nullable', 'string', 'in:' . implode(',', array_keys(MapasDaOficina::ESTADOS))],
        ]);
    }
}
