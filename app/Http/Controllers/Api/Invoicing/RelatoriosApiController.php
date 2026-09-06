<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\Relatorios\Catalogo;
use App\Services\Invoicing\Relatorios\ExtractoDeConta;
use App\Services\Invoicing\Relatorios\Periodo;
use App\Services\Invoicing\Relatorios\Relatorio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * OS RELATÓRIOS, para o ecrã genérico em React.
 *
 * Cada mapa é uma classe do `Catalogo`; este controlador só lhe pede o
 * esquema e os dados, resolve as listas de opções que vêm dos dados, e
 * serve o CSV da primeira tabela pela rota de página (com a sessão).
 */
class RelatoriosApiController extends Controller
{
    public function seccoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'sales');

        $seccoes = collect(Catalogo::seccoes())->map(fn ($s) => [
            ...$s,
            'relatorios' => collect($s['relatorios'])->map(fn ($r) => [...$r, 'caminho' => Catalogo::caminho($r['slug'])])->values(),
        ])->values();

        return response()->json(['seccoes' => $seccoes]);
    }

    public function mostrar(Request $request, string $slug): JsonResponse
    {
        $relatorio = $this->abrir($request, $slug);
        $f = $this->filtros($request);

        $dados = $relatorio->dados((int) activeTenantId(), $f);
        $esquema = $relatorio->esquema();

        // As listas de opções que dependem da empresa vêm nos dados (clientes,
        // armazéns…): passam para o esquema já como {valor, rotulo}.
        foreach ($esquema['filtros'] as &$filtro) {
            if (is_string($filtro['opcoes'] ?? null)) {
                $filtro['opcoes'] = collect($dados[$filtro['opcoes']] ?? [])->map(fn ($o) => $this->opcao($o))->values()->all();
            }
        }
        unset($filtro);

        if ($esquema['periodo']) {
            [$de, $ate] = Periodo::intervalo($f['period'] ?? null, $f['dateFrom'] ?? null, $f['dateTo'] ?? null, $esquema['periodo']['omissao']);
            $dados['intervalo'] = ['de' => $de, 'ate' => $ate];
        }

        return response()->json([
            'esquema' => $esquema,
            'dados' => $dados,
            'atalhos' => collect(Periodo::ATALHOS)->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => __($rotulo)])->values(),
            'csv' => $esquema['csv'] ? url(Catalogo::caminho($slug) . '/csv') : null,
        ]);
    }

    /** A procura de clientes ou fornecedores do extracto de conta corrente. */
    public function entidades(Request $request, string $slug): JsonResponse
    {
        $relatorio = $this->abrir($request, $slug);
        abort_unless($relatorio instanceof ExtractoDeConta, 404);

        $d = $request->validate(['entidade' => ['nullable', 'string', 'max:20'], 'q' => ['nullable', 'string', 'max:100']]);

        return response()->json(['data' => $relatorio->procurar((int) activeTenantId(), $d['entidade'] ?? 'cliente', $d['q'] ?? '')]);
    }

    /** O CSV da primeira tabela, com o que está no ecrã — e o BOM, para o Excel em português. */
    public function csv(Request $request, string $slug): StreamedResponse
    {
        $relatorio = $this->abrir($request, $slug);
        $esquema = $relatorio->esquema();
        abort_unless($esquema['csv'] && !empty($esquema['tabelas']), 404);

        $dados = $relatorio->dados((int) activeTenantId(), $this->filtros($request));

        /*
         * QUAL DAS TABELAS.
         *
         * Um mapa pode ter mais do que uma, e exportar sempre a primeira dava
         * o papel errado: nos ajustes de stock, a primeira é o resumo «Por
         * operador» e o que se leva para o armazém é a LISTA DE MOVIMENTOS.
         * Sem `tabela`, continua a ser a primeira — que é o que quem já usa a
         * ligação de sempre espera.
         */
        $pedida = (string) $request->query('tabela', '');
        $tabela = collect($esquema['tabelas'])->firstWhere('chave', $pedida) ?? $esquema['tabelas'][0];

        $linhas = $this->linhas($dados, $tabela['chave']);
        $ficheiro = $slug
            . ($pedida !== '' && count($esquema['tabelas']) > 1 ? '_' . $tabela['chave'] : '')
            . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($tabela, $linhas) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, array_map(fn ($c) => $c['rotulo'], $tabela['colunas']), ';');
            foreach ($linhas as $linha) {
                fputcsv($saida, array_map(fn ($c) => $this->celula(data_get($linha, $c['chave']), $c['formato'] ?? null), $tabela['colunas']), ';');
            }
            fclose($saida);
        }, $ficheiro, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function abrir(Request $request, string $slug): Relatorio
    {
        abort_unless(Catalogo::existe($slug), 404);
        $this->exigir($request, $slug);

        return Catalogo::abrir($slug);
    }

    /** Só o que veio escrito, como texto: os relatórios validam o que lhes interessa. */
    private function filtros(Request $request): array
    {
        return collect($request->query())
            ->filter(fn ($v) => is_scalar($v) && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->all();
    }

    private function linhas(array $dados, string $chave): iterable
    {
        $bruto = data_get($dados, $chave);

        if ($bruto instanceof \Illuminate\Contracts\Pagination\Paginator) {
            return $bruto->items();
        }

        return is_iterable($bruto) ? $bruto : [];
    }

    private function celula(mixed $v, ?string $formato): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return match ($formato) {
            'dinheiro', 'numero' => number_format((float) $v, 2, ',', ''),
            'percentagem' => number_format((float) $v, 1, ',', ''),
            'inteiro', 'dias' => (string) (int) round((float) $v),
            'data' => preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $v, $m) ? "{$m[3]}/{$m[2]}/{$m[1]}" : (string) $v,
            default => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE),
        };
    }

    private function opcao(mixed $o): array
    {
        $o = is_object($o) ? (method_exists($o, 'toArray') ? $o->toArray() : (array) $o) : $o;

        if (is_array($o)) {
            return ['valor' => (string) ($o['valor'] ?? $o['id'] ?? ''), 'rotulo' => (string) ($o['rotulo'] ?? $o['name'] ?? $o['id'] ?? '')];
        }

        return ['valor' => (string) $o, 'rotulo' => (string) $o];
    }

    private function exigir(Request $request, string $slug): void
    {
        $permissoes = explode('|', Catalogo::permissao($slug));
        abort_unless($request->user()?->canAny($permissoes), 403, __('Sem permissão para esta operação.'));
    }
}
