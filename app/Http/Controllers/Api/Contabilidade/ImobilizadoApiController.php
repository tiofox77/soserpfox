<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Accounting\FixedAssetDepreciation;
use App\Services\Accounting\Amortizacoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O IMOBILIZADO.
 *
 * O ECRÃ ANTIGO ERA UMA FACHADA. O formulário estava todo lá — código, nome,
 * categoria, data e valor de aquisição, valor residual, vida útil, método,
 * localização, número de série — e o `save()` era isto:
 *
 *     session()->flash('success', 'Ativo salvo com sucesso!
 *         (Funcionalidade completa será implementada em breve)');
 *
 * Não gravava nada. A lista era um paginador VAZIO construído à mão, os quatro
 * totais eram zeros literais, e o botão «Calcular Depreciações» flashava outra
 * promessa. As três tabelas existiam desde 2025 e nunca receberam uma linha:
 * quem lá entrasse registava bens que desapareciam sem aviso nenhum.
 *
 * Aqui o registo é a sério, e o cálculo das amortizações vive no
 * `Services\Accounting\Amortizacoes`.
 */
class ImobilizadoApiController extends Controller
{
    public function __construct(private Amortizacoes $amortizacoes) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    private function daCasa(int $id): FixedAsset
    {
        return FixedAsset::where('tenant_id', $this->tenantId())->findOrFail($id);
    }

    private const ESTADOS = ['active', 'fully_depreciated', 'sold', 'scrapped'];

    private function rotuloDoEstado(?string $estado): string
    {
        return [
            'active' => __('Em uso'),
            'fully_depreciated' => __('Totalmente amortizado'),
            'sold' => __('Vendido'),
            'scrapped' => __('Abatido'),
        ][$estado] ?? (string) $estado;
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.fixed-assets.view');

        $tenantId = $this->tenantId();

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(array_merge(['todos'], self::ESTADOS))],
            'categoria' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = fn () => FixedAsset::where('tenant_id', $tenantId)
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->where(fn ($w) => $w->where('code', 'like', $t)
                    ->orWhere('name', 'like', $t)
                    ->orWhere('serial_number', 'like', $t)
                    ->orWhere('location', 'like', $t));
            })
            ->when(! empty($filtros['estado']) && $filtros['estado'] !== 'todos',
                fn ($q) => $q->where('status', $filtros['estado']))
            ->when(! empty($filtros['categoria']), fn ($q) => $q->where('category_id', $filtros['categoria']));

        $lista = $base()
            ->with(['category:id,name', 'account:id,code,name'])
            ->withCount(['depreciations', 'depreciations as amortizacoes_lancadas' => fn ($q) => $q->where('status', 'posted')])
            ->orderBy('code')
            ->paginate($filtros['por_pagina'] ?? 25);

        /*
         * OS QUATRO TOTAIS. Eram zeros literais no código do ecrã antigo — e um
         * painel que mostra zeros sem os ter contado é pior do que não mostrar
         * nada, porque parece uma resposta.
         */
        $todos = $base()->get(['acquisition_value', 'accumulated_depreciation', 'book_value']);

        return response()->json([
            'data' => collect($lista->items())->map(fn (FixedAsset $b) => $this->linha($b))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'bens' => $todos->count(),
                'aquisicao' => round((float) $todos->sum('acquisition_value'), 2),
                'amortizado' => round((float) $todos->sum('accumulated_depreciation'), 2),
                'liquido' => round((float) $todos->sum('book_value'), 2),
                // AS AMORTIZAÇÕES POR LANÇAR são trabalho a meio: calculadas e
                // fora da contabilidade.
                'por_lancar' => FixedAssetDepreciation::whereHas('asset', fn ($q) => $q->where('tenant_id', $tenantId))
                    ->where('status', 'draft')->count(),
            ],
            'estados' => collect(self::ESTADOS)->map(fn ($e) => [
                'valor' => $e, 'rotulo' => $this->rotuloDoEstado($e),
            ])->values(),
            'permissoes' => [
                'gerir' => (bool) $request->user()?->can('accounting.fixed-assets.manage'),
                // LANÇAR na contabilidade é outra permissão: mexe nos saldos.
                'lancar' => (bool) $request->user()?->can('accounting.moves.manage'),
            ],
        ]);
    }

    private function linha(FixedAsset $b): array
    {
        return [
            'id' => $b->id,
            'codigo' => $b->code,
            'nome' => $b->name,
            'categoria' => $b->category?->name,
            'categoria_id' => $b->category_id,
            'conta' => $b->account ? $b->account->code.' · '.$b->account->name : null,
            'aquisicao' => $b->acquisition_date?->format('Y-m-d'),
            'valor' => round((float) $b->acquisition_value, 2),
            'residual' => round((float) $b->residual_value, 2),
            'vida_util' => (int) $b->useful_life_years,
            'metodo' => $b->depreciation_method,
            'metodo_rotulo' => $this->rotuloDoMetodo($b->depreciation_method),
            'taxa' => $b->depreciation_rate === null ? null : (float) $b->depreciation_rate,
            'amortizado' => round((float) $b->accumulated_depreciation, 2),
            'liquido' => round((float) $b->book_value, 2),
            'por_amortizar' => $b->porAmortizar(),
            'estado' => $b->status,
            'estado_rotulo' => $this->rotuloDoEstado($b->status),
            'localizacao' => $b->location,
            'serie' => $b->serial_number,
            'amortizacoes' => (int) ($b->depreciations_count ?? 0),
            'amortizacoes_lancadas' => (int) ($b->amortizacoes_lancadas ?? 0),
        ];
    }

    private function rotuloDoMetodo(?string $metodo): string
    {
        return [
            'linear' => __('Quotas constantes'),
            'declining_balance' => __('Quotas degressivas'),
            'units_of_production' => __('Por unidades produzidas'),
        ][$metodo] ?? (string) $metodo;
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.fixed-assets.view');

        $tenantId = $this->tenantId();

        $contas = Account::where('tenant_id', $tenantId)
            ->where('is_view', false)->where('blocked', false)
            ->orderBy('code')->get(['id', 'code', 'name', 'type'])
            ->map(fn ($c) => [
                'valor' => (string) $c->id,
                'rotulo' => $c->code.' · '.$c->name,
                'tipo' => $c->type,
            ])->values();

        return response()->json([
            'contas' => $contas,
            'categorias' => FixedAssetCategory::where('tenant_id', $tenantId)
                ->orderBy('name')->get(['id', 'name', 'default_useful_life', 'default_depreciation_method', 'default_depreciation_rate'])
                ->map(fn ($c) => [
                    'valor' => (string) $c->id,
                    'rotulo' => $c->name,
                    // AS OMISSÕES DA FAMÍLIA, para o formulário as herdar: quem
                    // registou uma viatura não tem de saber a vida útil de cor.
                    'vida_util' => (int) $c->default_useful_life,
                    'metodo' => $c->default_depreciation_method,
                    'taxa' => $c->default_depreciation_rate === null ? null : (float) $c->default_depreciation_rate,
                ])->values(),
            'metodos' => collect(Amortizacoes::METODOS)->map(fn ($m) => [
                'valor' => $m, 'rotulo' => $this->rotuloDoMetodo($m),
            ])->values(),
            'estados' => collect(self::ESTADOS)->map(fn ($e) => [
                'valor' => $e, 'rotulo' => $this->rotuloDoEstado($e),
            ])->values(),
            'permissoes' => [
                'gerir' => (bool) $request->user()?->can('accounting.fixed-assets.manage'),
                'lancar' => (bool) $request->user()?->can('accounting.moves.manage'),
            ],
        ]);
    }

    /** A ficha do bem, com as amortizações linha a linha. */
    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.fixed-assets.view');

        $bem = FixedAsset::where('tenant_id', $this->tenantId())
            ->with([
                'category:id,name', 'account:id,code,name',
                'depreciationAccount:id,code,name', 'accumulatedDepreciationAccount:id,code,name',
                'depreciations.period:id,name,code', 'depreciations.move:id,ref',
            ])
            ->withCount(['depreciations', 'depreciations as amortizacoes_lancadas' => fn ($q) => $q->where('status', 'posted')])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->linha($bem) + [
                'descricao' => $bem->description,
                'conta_id' => $bem->account_id,
                'conta_de_gasto_id' => $bem->depreciation_account_id,
                'conta_de_gasto' => $bem->depreciationAccount
                    ? $bem->depreciationAccount->code.' · '.$bem->depreciationAccount->name : null,
                'conta_acumulada_id' => $bem->accumulated_depreciation_account_id,
                'conta_acumulada' => $bem->accumulatedDepreciationAccount
                    ? $bem->accumulatedDepreciationAccount->code.' · '.$bem->accumulatedDepreciationAccount->name : null,
                'abate' => $bem->disposal_date?->format('Y-m-d'),
                'valor_do_abate' => $bem->disposal_value === null ? null : round((float) $bem->disposal_value, 2),
                'linhas' => $bem->depreciations
                    ->sortBy('depreciation_date')
                    ->map(fn (FixedAssetDepreciation $l) => [
                        'id' => $l->id,
                        'dia' => $l->depreciation_date?->format('Y-m-d'),
                        'periodo' => $l->period?->name ?: $l->period?->code,
                        'valor' => round((float) $l->depreciation_amount, 2),
                        'acumulado' => round((float) $l->accumulated_depreciation, 2),
                        'liquido' => round((float) $l->book_value, 2),
                        'estado' => $l->status,
                        'lancamento' => $l->move?->ref,
                        'pode_lancar' => $l->status === 'draft' && (float) $l->depreciation_amount > 0,
                    ])->values(),
            ],
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, 'accounting.fixed-assets.manage');

        $tenantId = $this->tenantId();

        $bem = $id ? $this->daCasa($id) : new FixedAsset();

        $dados = $request->validate([
            /*
             * O CÓDIGO É ÚNICO POR EMPRESA. A base tem o índice
             * `(tenant_id, code)` desde a migração de 2026 e nada o declarava —
             * mas nada gravava, pelo que também nunca deu erro.
             */
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('fixed_assets', 'code')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer'],
            'account_id' => ['required', 'integer'],
            'depreciation_account_id' => ['required', 'integer'],
            'accumulated_depreciation_account_id' => ['required', 'integer'],
            'acquisition_date' => ['required', 'date_format:Y-m-d'],
            'acquisition_value' => ['required', 'numeric', 'gt:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'depreciation_method' => ['required', Rule::in(Amortizacoes::METODOS)],
            'depreciation_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', Rule::in(self::ESTADOS)],
            'location' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'disposal_date' => ['nullable', 'date_format:Y-m-d'],
            'disposal_value' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'code' => __('código'), 'name' => __('nome'),
            'account_id' => __('conta do bem'),
            'depreciation_account_id' => __('conta de gasto'),
            'accumulated_depreciation_account_id' => __('conta de amortizações acumuladas'),
            'acquisition_date' => __('data de aquisição'),
            'acquisition_value' => __('valor de aquisição'),
            'useful_life_years' => __('vida útil'),
        ]);

        // AS TRÊS CONTAS SÃO DESTA EMPRESA e recebem movimento: é com elas que a
        // amortização se lança, e uma conta de agregação contaria a dobrar.
        foreach ([
            'account_id' => __('Conta do bem não encontrada nesta empresa.'),
            'depreciation_account_id' => __('Conta de gasto não encontrada nesta empresa.'),
            'accumulated_depreciation_account_id' => __('Conta de amortizações acumuladas não encontrada nesta empresa.'),
        ] as $campo => $recado) {
            $conta = Account::where('tenant_id', $tenantId)->find($dados[$campo]);

            if (! $conta) {
                throw ValidationException::withMessages([$campo => [$recado]]);
            }

            if ($conta->is_view) {
                throw ValidationException::withMessages([
                    $campo => [__('A conta :conta é de agregação e não recebe movimento.', [
                        'conta' => $conta->code.' · '.$conta->name,
                    ])],
                ]);
            }
        }

        if (! empty($dados['category_id'])
            && ! FixedAssetCategory::where('tenant_id', $tenantId)->whereKey($dados['category_id'])->exists()) {
            throw ValidationException::withMessages([
                'category_id' => [__('Categoria não encontrada nesta empresa.')],
            ]);
        }

        /*
         * O RESIDUAL NÃO PODE VALER MAIS DO QUE O BEM.
         *
         * Se valesse, não haveria nada para amortizar — e o ecrã ficava a
         * calcular zeros sem dizer porquê.
         */
        $residual = round((float) ($dados['residual_value'] ?? 0), 2);
        $valor = round((float) $dados['acquisition_value'], 2);

        if ($residual >= $valor) {
            throw ValidationException::withMessages([
                'residual_value' => [__('O valor residual tem de ser menor do que o de aquisição — senão não há nada para amortizar.')],
            ]);
        }

        $bem->fill([
            'tenant_id' => $tenantId,
            'code' => trim($dados['code']),
            'name' => trim($dados['name']),
            'description' => $dados['description'] ?? null,
            'category_id' => ($dados['category_id'] ?? null) ?: null,
            'account_id' => $dados['account_id'],
            'depreciation_account_id' => $dados['depreciation_account_id'],
            'accumulated_depreciation_account_id' => $dados['accumulated_depreciation_account_id'],
            'acquisition_date' => $dados['acquisition_date'],
            'acquisition_value' => $valor,
            'residual_value' => $residual,
            'useful_life_years' => (int) $dados['useful_life_years'],
            'depreciation_method' => $dados['depreciation_method'],
            'depreciation_rate' => $dados['depreciation_rate'] ?? null,
            'status' => $dados['status'] ?? ($id ? $bem->status : 'active'),
            'location' => $dados['location'] ?? null,
            'serial_number' => $dados['serial_number'] ?? null,
            'disposal_date' => $dados['disposal_date'] ?? null,
            'disposal_value' => $dados['disposal_value'] ?? null,
            // O acumulado e o líquido saem das linhas; num bem novo são o valor
            // inteiro por amortizar.
            'accumulated_depreciation' => $id ? $bem->accumulated_depreciation : 0,
            'book_value' => $id ? $bem->book_value : $valor,
        ])->save();

        // E depois de gravar, o acumulado e o líquido voltam a sair das linhas:
        // mudar a vida útil ou o valor muda o que já foi amortizado.
        $this->amortizacoes->actualizarOBem($bem->fresh());

        return response()->json([
            'message' => $id
                ? __('Bem :codigo actualizado.', ['codigo' => $bem->code])
                : __('Bem :codigo registado.', ['codigo' => $bem->code]),
            'id' => $bem->id,
        ], $id ? 200 : 201);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.fixed-assets.manage');

        $bem = $this->daCasa($id);

        /*
         * UM BEM COM AMORTIZAÇÕES LANÇADAS NÃO DESAPARECE: os lançamentos
         * ficariam a falar de um bem que não existe. Abate-se ou dá-se por
         * vendido, que é o que o estado serve.
         */
        $lancadas = FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->where('status', 'posted')->count();

        if ($lancadas > 0) {
            throw ValidationException::withMessages([
                'geral' => [__('Este bem tem :n amortização(ões) já lançada(s) na contabilidade. Marque-o como vendido ou abatido em vez de o apagar.', [
                    'n' => $lancadas,
                ])],
            ]);
        }

        $codigo = $bem->code;

        // As linhas em rascunho vão com ele: nunca entraram na contabilidade.
        FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->delete();
        $bem->delete();

        return response()->json(['message' => __('Bem :codigo eliminado.', ['codigo' => $codigo])]);
    }

    /**
     * CALCULAR AS AMORTIZAÇÕES — o botão que só flashava uma promessa.
     *
     * Calcula as que faltam a todos os bens em uso, ou a um só, até à data
     * escolhida. As linhas nascem em RASCUNHO: calcular não é lançar.
     */
    public function calcular(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.fixed-assets.manage');

        $dados = $request->validate([
            'ate' => ['nullable', 'date_format:Y-m-d'],
            'bem' => ['nullable', 'integer'],
        ]);

        $ate = $dados['ate'] ?? now()->endOfMonth()->format('Y-m-d');

        $bens = isset($dados['bem'])
            ? collect([$this->daCasa((int) $dados['bem'])])
            : FixedAsset::where('tenant_id', $this->tenantId())->where('status', 'active')->get();

        $criadas = 0;

        foreach ($bens as $bem) {
            $criadas += $this->amortizacoes->calcular($bem, $ate);
        }

        return response()->json([
            'message' => $criadas > 0
                ? __(':n amortização(ões) calculada(s), em rascunho. Lançar na contabilidade é o passo seguinte.', ['n' => $criadas])
                : __('Não havia amortizações por calcular até :dia.', ['dia' => $ate]),
            'criadas' => $criadas,
        ]);
    }

    /** Lançar uma amortização na contabilidade. */
    public function lancar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'accounting.fixed-assets.manage');
        $this->exigir($request, 'accounting.moves.manage');

        // O escopo faz-se pelo BEM: a tabela das amortizações não tem empresa.
        $linha = FixedAssetDepreciation::whereHas(
            'asset',
            fn ($q) => $q->where('tenant_id', $this->tenantId())
        )->findOrFail($id);

        $linha = $this->amortizacoes->lancar($linha, (int) $request->user()?->id);

        return response()->json([
            'message' => __('Amortização lançada.'),
            'lancamento' => $linha->move?->ref,
        ], 201);
    }
}
