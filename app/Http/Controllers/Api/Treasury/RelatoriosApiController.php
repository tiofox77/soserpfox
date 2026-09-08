<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Services\Treasury\RelatoriosDeTesouraria;
use App\Support\CategoriasDeTesouraria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OS RELATÓRIOS FINANCEIROS, para o ecrã em React.
 *
 * AS CONTAS NÃO ESTÃO AQUI. Vivem na `RelatoriosDeTesouraria`, a mesma classe
 * que o controlador de descarga usa para o PDF e o Excel — porque um
 * relatório que dá números diferentes no ecrã e no ficheiro é pior do que não
 * existir: alguém imprime, leva a uma reunião, e descobre em público que não
 * bate certo.
 *
 * Este controlador escolhe o período, chama a classe e traduz os nomes das
 * categorias para português. Mais nada.
 */
class RelatoriosApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('treasury.reports.view'), 403, __('Sem permissão para esta operação.'));

        $d = $request->validate([
            'tipo' => ['nullable', 'in:' . implode(',', array_keys(RelatoriosDeTesouraria::TIPOS))],
            'periodo' => ['nullable', 'in:today,week,month,year,custom'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        $tipo = $d['tipo'] ?? 'cash_flow';
        $periodo = $d['periodo'] ?? 'month';

        [$de, $ate] = $this->intervalo($periodo, $d['de'] ?? null, $d['ate'] ?? null);

        $relatorios = new RelatoriosDeTesouraria((int) activeTenantId(), $de, $ate);

        return response()->json([
            'tipo' => $tipo,
            'titulo' => __(RelatoriosDeTesouraria::nomeDoTipo($tipo)),
            'periodo' => $periodo,
            'de' => $de,
            'ate' => $ate,
            // Os quatro que existem, para o ecrã desenhar os separadores sem
            // os ter escritos à mão — a lista vive na classe das contas.
            'tipos' => collect(RelatoriosDeTesouraria::TIPOS)
                ->map(fn ($nome, $chave) => ['valor' => $chave, 'rotulo' => __($nome)])
                ->values(),
            'dados' => $this->legivel($relatorios->dados($tipo)),
            // AS MORADAS DE DESCARGA, montadas pelo servidor: o ecrã não tem
            // de saber como se compõe o URL de um relatório.
            'descargas' => [
                'pdf' => route('treasury.reports.pdf', ['tipo' => $tipo, 'de' => $de, 'ate' => $ate]),
                'excel' => route('treasury.reports.excel', ['tipo' => $tipo, 'de' => $de, 'ate' => $ate]),
            ],
        ]);
    }

    /**
     * O intervalo do período escolhido.
     *
     * `custom` usa as duas datas que vierem — e se faltar alguma, cai no mês,
     * que é o que o ecrã abre.
     *
     * @return array{0: string, 1: string}
     */
    private function intervalo(string $periodo, ?string $de, ?string $ate): array
    {
        if ($periodo === 'custom' && $de && $ate) {
            // Ao contrário faz o relatório sair vazio sem ninguém perceber
            // porquê: troca-se em vez de se devolver nada.
            return $de <= $ate ? [$de, $ate] : [$ate, $de];
        }

        return match ($periodo) {
            'today' => [now()->startOfDay()->toDateString(), now()->endOfDay()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    /**
     * OS CÓDIGOS DAS CATEGORIAS VIRAM NOMES.
     *
     * A base guarda `customer_payment` e `digital_payment`. Um relatório para
     * levar ao contabilista não pode dizer isso — e a tradução vive no
     * `CategoriasDeTesouraria`, que já conhece as que a empresa tem.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function legivel(array $dados): array
    {
        foreach (['incomeByCategory', 'expenseByCategory', 'expensesByCategory'] as $chave) {
            if (! isset($dados[$chave])) {
                continue;
            }

            $dados[$chave] = collect($dados[$chave])->map(fn ($l) => [
                'codigo' => $l->category,
                'rotulo' => CategoriasDeTesouraria::nome($l->category),
                'valor' => (float) $l->total,
            ])->values();
        }

        return $dados;
    }
}
