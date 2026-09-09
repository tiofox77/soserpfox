<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\RoomType;
use App\Services\Hotel\MapasDoHotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OS MAPAS DO HOTEL — cinco, com as colunas declaradas pelo servidor.
 *
 * O ecrã desenha UMA tabela para os cinco, e por cima dela os números que a
 * hotelaria pergunta: ocupação, ADR e RevPAR. A guarda é
 * `hotel.reports.view` — a mesma da rota.
 */
class RelatoriosApiController extends Controller
{
    public function __construct(private readonly MapasDoHotel $mapas) {}

    private function exigir(Request $request): void
    {
        abort_unless($request->user()?->can('hotel.reports.view'), 403, __('Sem permissão para esta operação.'));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request);

        return response()->json([
            'mapas' => collect(MapasDoHotel::MAPAS)->map(fn ($m, $chave) => [
                'valor' => $chave,
                'rotulo' => __($m['rotulo']),
                'icone' => $m['icone'],
            ])->values(),
            'tipos_de_quarto' => RoomType::where('tenant_id', activeTenantId())
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($t) => ['valor' => (string) $t->id, 'rotulo' => $t->name])->values(),
        ]);
    }

    public function mostrar(Request $request): JsonResponse
    {
        $this->exigir($request);

        $filtros = self::validarFiltros($request);

        $mapa = $this->mapas->mapa($filtros['mapa'], activeTenantId(), $filtros);

        $comFiltros = array_filter($filtros, fn ($v) => $v !== null && $v !== '');

        return response()->json([
            'mapa' => $filtros['mapa'],
            // As moradas do papel e do Excel vêm daqui: o ecrã não sabe (nem
            // tem de saber) que filtros é que a exportação aceita.
            'descargas' => [
                'pdf' => route('hotel.reports.imprimir', $comFiltros),
                'excel' => route('hotel.reports.excel', $comFiltros),
            ],
        ] + $mapa);
    }

    /** Os filtros, validados no mesmo sítio para o ecrã e para a exportação. */
    public static function validarFiltros(Request $request): array
    {
        return $request->validate([
            'mapa' => ['required', 'string', 'in:' . implode(',', array_keys(MapasDoHotel::MAPAS))],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'tipo_de_quarto' => ['nullable', 'integer'],
        ]);
    }
}
