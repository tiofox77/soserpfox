<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Services\Treasury\DreIntegrado;
use App\Support\CategoriasDeTesouraria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * O DRE INTEGRADO — o que o relatório não mostra de uma vez (27/09/2026):
 *
 *  · o DETALHE de cada valor: os documentos e os movimentos que o compõem,
 *    cada um com o endereço para o abrir («cada valor do relatório deve
 *    permitir consultar o documento e o movimento que lhe deram origem»);
 *  · a CLASSIFICAÇÃO das categorias da tesouraria: despesa, compra de stock,
 *    activo, dívida, transferência… Ver é de quem vê os relatórios; mudar é
 *    de quem edita os movimentos da tesouraria.
 */
class DreIntegradoApiController extends Controller
{
    /** As rubricas que abrem detalhe. */
    private const RUBRICAS = [
        'vendas', 'descontos', 'notas_credito', 'notas_debito', 'cmv',
        'despesas_documentos', 'despesas_movimentos', 'compras_stock_documentos', 'facturas_de_compra',
    ];

    public function detalhe(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('treasury.reports.view'), 403, __('Sem permissão para esta operação.'));

        $d = $request->validate([
            'rubrica' => ['required', 'string', Rule::in([...self::RUBRICAS, ...array_keys(DreIntegrado::NATUREZAS)])],
            'periodo' => ['nullable', 'in:today,week,month,year,custom'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        [$de, $ate] = RelatoriosApiController::intervaloDoPeriodo($d['periodo'] ?? 'month', $d['de'] ?? null, $d['ate'] ?? null);

        return response()->json((new DreIntegrado((int) activeTenantId(), $de, $ate))->detalhe($d['rubrica']));
    }

    /** As categorias da empresa, com a natureza de cada uma. */
    public function naturezas(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('treasury.reports.view'), 403, __('Sem permissão para esta operação.'));

        $tenantId = (int) activeTenantId();
        $escolhidas = DB::table('treasury_naturezas')->where('tenant_id', $tenantId)->pluck('natureza', 'categoria')->all();

        $categorias = collect(CategoriasDeTesouraria::paraEmpresa($tenantId))
            ->map(function ($nome, $codigo) use ($escolhidas) {
                $omissao = DreIntegrado::POR_OMISSAO[$codigo] ?? 'despesa';

                return [
                    'categoria' => (string) $codigo,
                    'rotulo' => __($nome),
                    'natureza' => $escolhidas[$codigo] ?? $omissao,
                    'por_omissao' => $omissao,
                    'de_sistema' => isset(DreIntegrado::POR_OMISSAO[$codigo]),
                ];
            })
            ->sortBy('rotulo')
            ->values();

        return response()->json([
            'categorias' => $categorias,
            'naturezas' => collect(DreIntegrado::NATUREZAS)
                ->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r), 'no_resultado' => in_array($v, DreIntegrado::NO_RESULTADO, true)])
                ->values(),
            'pode_classificar' => (bool) $request->user()?->can('treasury.transactions.edit'),
        ]);
    }

    public function guardarNaturezas(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('treasury.transactions.edit'), 403, __('Sem permissão para esta operação.'));

        $d = $request->validate([
            'naturezas' => ['required', 'array'],
            'naturezas.*' => ['required', 'string', Rule::in(array_keys(DreIntegrado::NATUREZAS))],
        ]);

        $tenantId = (int) activeTenantId();
        $mudadas = 0;

        foreach ($d['naturezas'] as $categoria => $natureza) {
            $categoria = mb_substr(trim((string) $categoria), 0, 100);

            if ($categoria === '') {
                continue;
            }

            // Igual ao da casa numa categoria de sistema: não se guarda — se o
            // de omissão um dia mudar, a empresa acompanha.
            if ((DreIntegrado::POR_OMISSAO[$categoria] ?? null) === $natureza) {
                $mudadas += DB::table('treasury_naturezas')->where('tenant_id', $tenantId)->where('categoria', $categoria)->delete();

                continue;
            }

            DB::table('treasury_naturezas')->updateOrInsert(
                ['tenant_id' => $tenantId, 'categoria' => $categoria],
                ['natureza' => $natureza, 'updated_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()],
            );
            $mudadas++;
        }

        return response()->json(['message' => __('Classificação guardada — o DRE Integrado já a usa.'), 'mudadas' => $mudadas]);
    }
}
