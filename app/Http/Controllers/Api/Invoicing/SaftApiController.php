<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\GeradorDeSaft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * O SAFT-AO, para o ecrã em React.
 *
 * As contagens vêm pela API; a descarga é uma rota de página com a sessão,
 * porque um ficheiro não viaja em JSON. As duas passam pelo `GeradorDeSaft`,
 * o mesmo que o ecrã Livewire usa — o XML entregue à AGT não tem duas
 * versões.
 */
class SaftApiController extends Controller
{
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.saft.view');

        return response()->json([
            'tipos' => collect(GeradorDeSaft::TIPOS)->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => __($rotulo)])->values(),
            'seccoes' => collect(GeradorDeSaft::SECCOES)->map(fn ($rotulo, $chave) => ['chave' => $chave, 'rotulo' => __($rotulo)])->values(),
            'periodo' => ['de' => now()->startOfMonth()->toDateString(), 'ate' => now()->endOfMonth()->toDateString()],
            'permissoes' => ['pode_gerar' => (bool) $request->user()?->can('invoicing.saft.generate')],
            'descarga' => url('invoicing/saft-generator/descarregar'),
        ]);
    }

    public function estatisticas(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.saft.view');

        return response()->json(['data' => $this->gerador($request)->estatisticas()]);
    }

    /** A descarga: gera, guarda a cópia, deixa rasto e entrega o ficheiro. */
    public function descarregar(Request $request): StreamedResponse
    {
        $this->exigir($request, 'invoicing.saft.generate');

        $saft = $this->gerador($request)->gerar();

        return response()->streamDownload(function () use ($saft) {
            echo $saft['xml'];
        }, $saft['ficheiro'], ['Content-Type' => 'application/xml']);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function gerador(Request $request): GeradorDeSaft
    {
        $d = $request->validate(GeradorDeSaft::regras());

        // As secções que faltarem ficam ligadas: é assim que o ecrã de sempre abre.
        $seccoes = collect($request->only(array_keys(GeradorDeSaft::SECCOES)))
            ->map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))
            ->all();

        return new GeradorDeSaft((int) activeTenantId(), $d['startDate'], $d['endDate'], $d['documentType'], $seccoes);
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
