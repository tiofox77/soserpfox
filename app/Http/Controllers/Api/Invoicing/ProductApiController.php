<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoicing\ProductResource;
use App\Models\AGT\AGTTaxExemptionCode;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Invoicing\RastreioDoArtigo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Os artigos, para o ecrã em React.
 *
 * DUAS REGRAS DESTA CASA QUE AQUI NÃO SE QUEBRAM:
 *
 * 1. O `stock_quantity` NUNCA se escreve numa edição. É um agregado derivado
 *    das linhas de `invoicing_stocks`, que o `StockObserver` mantém. Escrevê-lo
 *    aqui devolvia o valor que estava no ecrã quando ele abriu — revertendo as
 *    vendas que aconteceram entretanto — e criava ajustes fantasma sem
 *    movimento nem rasto. O stock ajusta-se na Gestão de Stock, com movimento
 *    registado. Só na CRIAÇÃO é que a quantidade inicial entra.
 *
 * 2. O imposto sai do catálogo (`tax_rate_id`) ou é isenção com motivo
 *    (`exemption_reason`) — nunca uma taxa escrita à mão. Quem resolve a taxa
 *    de cada linha de documento é o `TaxResolver`, e este ecrã não o
 *    contorna.
 *
 * 3. OS CAMPOS DE SECTOR (farmácia, vestuário, cosmética, mercearia) gravam-se
 *    e filtram-se por aqui. Nenhum é obrigatório: a maioria do catálogo não
 *    preenche nenhum, e um campo tornado obrigatório para servir dois sectores
 *    partia o sistema de toda a gente. O género e a conservação são listas
 *    fechadas porque alimentam filtros e relatórios — texto livre daria "M",
 *    "masc" e "Homem" a significarem o mesmo.
 *
 * 4. AS IMAGENS (destaque e galeria) gravam-se NO MESMO SÍTIO E FORMATO de
 *    sempre: caminho relativo no disco `public`, coluna `featured_image` para
 *    a de destaque e a coluna JSON `gallery` para as restantes. É o que o POS
 *    do restaurante, a carta e a transferência entre empresas já lêem — um
 *    segundo esquema deixaria metade do sistema a ver imagens e a outra
 *    metade a ver caixas vazias.
 */
class ProductApiController extends Controller
{
    /**
     * Tecto da galeria.
     *
     * Não é um número mágico: sem tecto, um formulário com o dedo preso na
     * tecla enche o disco da empresa com a mesma fotografia, e o JSON da
     * coluna cresce sem ninguém dar por isso. Dez chegam para mostrar um
     * artigo por todos os lados.
     */
    private const MAXIMO_DA_GALERIA = 10;

    /** Lista fechada: o género alimenta filtros e relatórios. */
    private const GENEROS = [
        'masculino' => 'Masculino', 'feminino' => 'Feminino',
        'unissexo' => 'Unissexo', 'crianca' => 'Criança',
    ];

    /** Lista fechada: isto manda no stock — prateleira, frigorífico ou arca. */
    private const CONSERVACAO = ['ambiente' => 'Ambiente', 'refrigerado' => 'Refrigerado', 'congelado' => 'Congelado'];

    private const LISTA_GENEROS = 'masculino,feminino,unissexo,crianca';

    private const LISTA_CONSERVACAO = 'ambiente,refrigerado,congelado';

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->exigir($request, 'invoicing.products.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', 'in:produto,servico'],
            'categoria' => ['nullable', 'integer'],
            'activo' => ['nullable', 'in:1,0'],
            'so_em_falta' => ['nullable', 'in:1'],
            // Os filtros de sector. `prescricao` é 'sim'/'nao' e não um
            // booleano: 'nao' é um filtro a sério, e um `false` seria
            // indistinguível de "sem filtro".
            'prescricao' => ['nullable', 'in:sim,nao'],
            'tamanho' => ['nullable', 'string', 'max:20'],
            'cor' => ['nullable', 'string', 'max:40'],
            'conservacao' => ['nullable', 'in:ambiente,refrigerado,congelado'],

            /*
             * O FILTRO DE QUALIDADE DA FICHA — o que o ecrã de sempre chamava
             * `qualidadeFilter`. Serve para arrumar o catálogo: encontrar o
             * que ficou sem preço, sem código de barras ou sem categoria. Um
             * artigo sem preço vende-se a zero no POS, e um sem categoria não
             * aparece em nenhum filtro — são erros que só se acham a procurar
             * por eles.
             */
            'qualidade' => ['nullable', 'in:sem_preco,sem_codigo_barras,sem_categoria'],

            // «Que artigos entraram este mês» — pela data de criação da ficha.
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],

            /*
             * VER OS ELIMINADOS.
             *
             * O artigo apagado sempre foi recuperável (o modelo tem
             * `SoftDeletes`), mas durante anos não houve forma de o fazer pela
             * aplicação — só com SQL directo. O ecrã de sempre ganhou este
             * modo, e a migração para React voltou a deixar os apagados
             * invisíveis.
             */
            'eliminados' => ['nullable', 'in:1'],

            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        /*
         * A MESMA CONSULTA DO ECRÃ LIVEWIRE, incluindo o que ele NÃO faz.
         *
         * Não se filtra por `module` aqui — o ecrã de sempre também não — para
         * as duas listas mostrarem exactamente os mesmos artigos. Uma API que
         * esconda o que o ecrã mostra é tão errada como uma que mostre a mais.
         *
         * O stock vem SOMADO DAS LINHAS (`withSum`) e não da coluna agregada:
         * é a mesma fonte que a lista de sempre usa, e a única que não mente
         * quando o agregado ficou para trás.
         */
        $query = $this->filtrada($filtros)
            ->withSum('stocks as stock_das_linhas', 'quantity')
            ->with(['category', 'taxRate']);

        /*
         * OS NÚMEROS DOS CARTÕES CONTAM O CATÁLOGO FILTRADO, não a página.
         *
         * O ecrã em Blade contava o catálogo inteiro (`$estatisticas`); ao
         * migrar, os cartões passaram a contar as quinze linhas à vista e a
         * dizê-lo em letra pequena. Um «preço médio» que muda ao virar a
         * página não é um preço médio.
         *
         * A CONSULTA DO RESUMO NASCE DE NOVO dos mesmos filtros, e não de um
         * clone da listagem: aquela leva o `withSum` do stock na lista de
         * colunas, e uma coluna não agregada ao lado de um `SUM()` faz o MySQL
         * recusar a consulta inteira (`only_full_group_by`).
         */
        $resumo = $this->filtrada($filtros)
            ->toBase()
            ->selectRaw("
                COUNT(*) as total,
                AVG(NULLIF(price, 0)) as preco_medio,
                SUM(CASE WHEN type = 'servico' THEN 1 ELSE 0 END) as servicos,
                SUM(CASE WHEN manage_stock = 1
                          AND stock_min IS NOT NULL AND stock_min > 0
                          AND stock_quantity <= stock_min THEN 1 ELSE 0 END) as em_falta
            ")
            ->first();

        return ProductResource::collection(
            $query->orderBy('name')->paginate($filtros['por_pagina'] ?? 15)->withQueryString()
        )->additional([
            'resumo' => [
                'total' => (int) ($resumo->total ?? 0),
                'preco_medio' => round((float) ($resumo->preco_medio ?? 0), 2),
                'servicos' => (int) ($resumo->servicos ?? 0),
                'em_falta' => (int) ($resumo->em_falta ?? 0),
            ],
        ]);
    }

    /**
     * A consulta com os filtros postos — e SEM as colunas da listagem.
     *
     * Serve duas vezes: a lista (que lhe acrescenta o stock somado e as
     * relações) e o resumo dos cartões (que lhe acrescenta só agregados).
     * Escrita uma vez, os dois falam sempre do mesmo conjunto.
     *
     * NÃO SE FILTRA POR `module` — o ecrã de sempre também não filtrava — para
     * as duas listas mostrarem exactamente os mesmos artigos. Uma API que
     * esconda o que o ecrã mostra é tão errada como uma que mostre a mais.
     */
    private function filtrada(array $filtros): \Illuminate\Database\Eloquent\Builder
    {
        return Product::where('tenant_id', activeTenantId())
            // SÓ os apagados, quando se pede a lixeira: é uma lista à parte,
            // não uma lista com os apagados misturados — misturados, ninguém
            // percebe porque é que um artigo não aparece no POS.
            ->when(($filtros['eliminados'] ?? null) === '1', fn ($q) => $q->onlyTrashed())
            ->when($filtros['procura'] ?? null, fn ($q, $procura) => $q->where(function ($w) use ($procura) {
                $w->where('name', 'like', "%{$procura}%")
                    ->orWhere('code', 'like', "%{$procura}%")
                    ->orWhere('sku', 'like', "%{$procura}%")
                    ->orWhere('barcode', 'like', "%{$procura}%");
            }))
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filtros['categoria'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(
                isset($filtros['activo']),
                fn ($q) => $q->where('is_active', (bool) $filtros['activo'])
            )
            // Em falta = gere stock, TEM mínimo definido, e está nele ou
            // abaixo. O mínimo tem de ser > 0: sem isso, todo o artigo com
            // zero de mínimo aparecia sempre em falta.
            ->when(
                ($filtros['so_em_falta'] ?? null) === '1',
                fn ($q) => $q->where('manage_stock', true)
                    ->whereNotNull('stock_min')->where('stock_min', '>', 0)
                    ->whereColumn('stock_quantity', '<=', 'stock_min')
            )
            // Receita: quem NÃO exige inclui os que chegaram sem valor. A
            // coluna tem omissão `false`, mas artigos migrados de sistemas
            // antigos chegam a NULL e desapareciam da venda livre.
            ->when(
                ($filtros['prescricao'] ?? null) === 'sim',
                fn ($q) => $q->comReceita()
            )
            ->when(
                ($filtros['prescricao'] ?? null) === 'nao',
                fn ($q) => $q->where(function ($x) {
                    $x->where('requires_prescription', false)->orWhereNull('requires_prescription');
                })
            )
            ->when($filtros['tamanho'] ?? null, fn ($q, $v) => $q->porTamanho($v))
            ->when($filtros['cor'] ?? null, fn ($q, $v) => $q->porCor($v))
            ->when($filtros['conservacao'] ?? null, fn ($q, $v) => $q->porConservacao($v))

            // O QUE FALTA NA FICHA. As mesmas três perguntas do ecrã de
            // sempre: preço a zero conta como sem preço, e o código de barras
            // vazio conta como ausente — em muitas fichas ele é '' e não NULL.
            ->when(
                ($filtros['qualidade'] ?? null) === 'sem_preco',
                fn ($q) => $q->where(fn ($x) => $x->whereNull('price')->orWhere('price', '<=', 0))
            )
            ->when(
                ($filtros['qualidade'] ?? null) === 'sem_codigo_barras',
                fn ($q) => $q->where(fn ($x) => $x->whereNull('barcode')->orWhere('barcode', ''))
            )
            ->when(
                ($filtros['qualidade'] ?? null) === 'sem_categoria',
                fn ($q) => $q->whereNull('category_id')
            )

            ->when($filtros['de'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['ate'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    /**
     * RESTAURAR UM ARTIGO APAGADO.
     *
     * A eliminação sempre foi recuperável — o modelo usa `SoftDeletes` — mas
     * durante anos não houve como o fazer pela aplicação, só com SQL directo.
     * Volta com a ficha, o histórico e as imagens intactos.
     *
     * Pede a MESMA permissão de apagar: quem pode tirar da lista é quem pode
     * repô-la.
     */
    public function restaurar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.products.delete');

        $artigo = Product::onlyTrashed()
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);

        $artigo->restore();

        return response()->json([
            'data' => new ProductResource($artigo->fresh()->load(['category', 'taxRate'])),
            'message' => __('Artigo restaurado: :nome', ['nome' => $artigo->name]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.products.create');

        $dados = $this->validar($request);

        // Só na criação. Ver a nota no topo da classe.
        $dados['stock_quantity'] = $request->input('type') === 'servico'
            ? 0
            : (float) $request->input('stock_quantity', 0);

        $artigo = Product::create($dados + ['tenant_id' => activeTenantId()]);

        return (new ProductResource($artigo->load(['category', 'taxRate'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): ProductResource
    {
        $this->exigir($request, 'invoicing.products.edit');

        $artigo = Product::where('tenant_id', activeTenantId())->findOrFail($id);

        /*
         * O AGREGADO NÃO SE TOCA. Nem sequer se aceita do pedido: se o corpo
         * trouxer `stock_quantity`, ignora-se em silêncio — a alternativa era
         * recusar, e recusar um campo que o ecrã não mostra confunde mais do
         * que ajuda. Quem ajusta stock fá-lo na Gestão de Stock, com movimento.
         */
        $artigo->update($this->validar($request, $artigo->id, $artigo));

        return new ProductResource($artigo->fresh()->load(['category', 'taxRate']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.products.delete');

        $artigo = Product::where('tenant_id', activeTenantId())->findOrFail($id);

        /*
         * UM ARTIGO QUE JÁ FOI VENDIDO NÃO SE APAGA — DESACTIVA-SE.
         *
         * A linha da factura aponta para ele. Apagá-lo deixava documentos
         * fiscais a referir um artigo que já não existe, e o SAFT com linhas
         * órfãs. Desactivar tira-o do POS e das listas sem mexer no passado.
         */
        $vendido = $artigo->linhasVendidas()->exists();

        if ($vendido) {
            $artigo->update(['is_active' => false]);

            return response()->json([
                'message' => __('Este artigo já foi vendido, por isso foi DESACTIVADO em vez de apagado. Deixa de aparecer no POS e nas listas, e os documentos antigos continuam certos.'),
                'desactivado' => true,
            ]);
        }

        $artigo->delete();

        return response()->json(['message' => __('Artigo apagado.'), 'desactivado' => false]);
    }

    /* ─── As imagens ──────────────────────────────────────────────────── */

    /**
     * A imagem de destaque. Uma só, e substitui a anterior.
     *
     * Vive sob `invoicing.products.edit` e não sob uma permissão própria:
     * trocar a fotografia de um artigo é editá-lo.
     */
    public function imagem(Request $request, int $id): ProductResource
    {
        $this->exigir($request, 'invoicing.products.edit');

        $artigo = $this->doCatalogo($id);

        $request->validate(['imagem' => ['required', 'image', 'max:2048']]);

        $anterior = $artigo->featured_image;
        $ficheiro = $request->file('imagem');

        $caminho = $ficheiro->storeAs(
            'products/' . $artigo->id,
            'featured_' . Str::slug($artigo->name) . '.' . $ficheiro->getClientOriginalExtension(),
            'public'
        );

        /*
         * A ANTERIOR SÓ SE APAGA DEPOIS, E SÓ SE FOR OUTRA.
         *
         * O nome sai do nome do artigo: substituir um JPEG por outro JPEG dá o
         * MESMO caminho, e apagar «a anterior» apagava o ficheiro acabado de
         * escrever — o artigo ficava com um caminho gravado a apontar para o
         * vazio. Era o que o ecrã de sempre fazia.
         */
        if (filled($anterior) && $anterior !== $caminho) {
            $this->apagarDoDisco($anterior);
        }

        $artigo->update(['featured_image' => $caminho]);

        return new ProductResource($artigo->fresh()->load(['category', 'taxRate']));
    }

    /** Tira a imagem de destaque — do artigo e do disco. */
    public function apagarImagem(Request $request, int $id): ProductResource
    {
        $this->exigir($request, 'invoicing.products.edit');

        $artigo = $this->doCatalogo($id);

        $this->apagarDoDisco($artigo->featured_image);
        $artigo->update(['featured_image' => null]);

        return new ProductResource($artigo->fresh()->load(['category', 'taxRate']));
    }

    /** Junta imagens à galeria. ACRESCENTA — nunca substitui o que já lá está. */
    public function galeria(Request $request, int $id): ProductResource
    {
        $this->exigir($request, 'invoicing.products.edit');

        $artigo = $this->doCatalogo($id);

        $request->validate([
            'imagens' => ['required', 'array', 'min:1', 'max:' . self::MAXIMO_DA_GALERIA],
            'imagens.*' => ['image', 'max:2048'],
        ]);

        $galeria = array_values(array_filter($artigo->gallery ?? []));
        $aChegar = count($request->file('imagens'));

        if (count($galeria) + $aChegar > self::MAXIMO_DA_GALERIA) {
            throw ValidationException::withMessages([
                'imagens' => [__('A galeria só leva :max imagens. Apague alguma antes de juntar mais.', [
                    'max' => self::MAXIMO_DA_GALERIA,
                ])],
            ]);
        }

        foreach ($request->file('imagens') as $ficheiro) {
            /*
             * NOME AO ACASO, e não `gallery_<índice>_<time>`.
             *
             * O nome de sempre juntava a posição na lista ao segundo do
             * relógio: duas imagens acrescentadas no mesmo segundo a um
             * artigo com a galeria vazia davam ambas `gallery_1_…` e a
             * segunda apagava a primeira em silêncio.
             */
            $galeria[] = $ficheiro->storeAs(
                'products/' . $artigo->id . '/gallery',
                Str::lower(Str::random(16)) . '.' . $ficheiro->getClientOriginalExtension(),
                'public'
            );
        }

        $artigo->update(['gallery' => $galeria]);

        return new ProductResource($artigo->fresh()->load(['category', 'taxRate']));
    }

    /** Tira UMA imagem da galeria. */
    public function apagarDaGaleria(Request $request, int $id): ProductResource
    {
        $this->exigir($request, 'invoicing.products.edit');

        $artigo = $this->doCatalogo($id);

        $pedido = (string) $request->query('caminho', '');

        /*
         * O CAMINHO CONFIRMA-SE CONTRA A GALERIA DESTE ARTIGO.
         *
         * Vem do pedido, e um caminho do pedido é um caminho que alguém pode
         * escrever à mão. Sem esta confirmação, um `../../.env` bem posto
         * apagava o que não era dele — e, mesmo sem má intenção, apagava a
         * imagem de outra empresa.
         */
        $galeria = array_values(array_filter($artigo->gallery ?? []));

        abort_unless(in_array($pedido, $galeria, true), 404, __('Essa imagem não é deste artigo.'));

        $this->apagarDoDisco($pedido);

        $artigo->update(['gallery' => array_values(array_filter($galeria, fn ($c) => $c !== $pedido))]);

        return new ProductResource($artigo->fresh()->load(['category', 'taxRate']));
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.products.view');

        return response()->json([
            'categorias' => Category::where('tenant_id', activeTenantId())
                ->orderBy('name')
                ->get(['id', 'name']),

            /*
             * MARCAS E FORNECEDORES — as duas listas que o formulário de
             * sempre montava DENTRO da vista, com um `Model::where(...)` no
             * meio do Blade. Vêm com o resto das opções, numa viagem só.
             *
             * As marcas desligadas não se oferecem (era o que a lista de
             * sempre fazia); os fornecedores vão todos, porque um artigo
             * comprado a um fornecedor que já ninguém usa continua a ser dele.
             */
            'marcas' => Brand::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),

            'fornecedores' => Supplier::where('tenant_id', activeTenantId())
                ->orderBy('name')
                ->get(['id', 'name']),

            // As taxas do catálogo da empresa. O regime fiscal já as afinou —
            // nunca se escreve uma percentagem à mão.
            'taxas' => Tax::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->orderBy('rate')
                ->get(['id', 'name', 'rate']),

            'unidades' => ['un', 'kg', 'g', 'l', 'ml', 'm', 'cm', 'm2', 'm3', 'cx', 'pct', 'par', 'hora'],

            'generos' => collect(self::GENEROS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'conservacao' => collect(self::CONSERVACAO)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),

            /*
             * OS MOTIVOS DE ISENÇÃO OFICIAIS DA AGT (DS.120 §9.5).
             *
             * NÃO É TEXTO LIVRE, e é essa a razão de virem daqui. O ecrã em
             * Blade tinha-os numa lista agrupada por tipo de imposto; ao migrar
             * ficou um campo de texto com «Ex.: M99» ao lado — e um motivo
             * escrito à mão («isento», «art 12») é rejeitado pela AGT no envio
             * da factura, muito depois de a venda estar feita.
             *
             * Vêm agrupados como no ecrã de sempre: IEC (I01–I16), Imposto de
             * Selo (S01–S03) e IVA (M01–M93).
             */
            'motivos_de_isencao' => AGTTaxExemptionCode::where('is_active', true)
                ->orderByRaw("FIELD(tax_type, 'IVA', 'IS', 'IEC', 'NS')")
                ->orderBy('code')
                ->get(['code', 'description', 'tax_type'])
                ->map(fn ($m) => [
                    'valor' => $m->code,
                    'rotulo' => $m->code . ' — ' . $m->description,
                    'grupo' => match ($m->tax_type) {
                        'IVA' => __('IVA'),
                        'IS' => __('Imposto de Selo'),
                        'IEC' => __('IEC'),
                        default => __('Não sujeição'),
                    },
                ])->all(),

            // O PERFIL DECIDE O QUE APARECE POR OMISSÃO — e mais nada.
            //
            // Nunca decide o que existe nem o que protege: os avisos do POS
            // seguem os dados do artigo, sempre. E não esconde o que já está
            // gravado: por isso vai junto o que o catálogo TEM, para o ecrã
            // mostrar os campos e os filtros a quem desligou o perfil (ou
            // nunca o ligou) mas já tem artigos marcados. Sem isto, desligar
            // o perfil deixava dados gravados sem forma de os ver nem filtrar.
            'perfis' => InvoicingSettings::forTenant(activeTenantId())->perfisActivos(),
            'variantes' => $this->variantesDoCatalogo(),

            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('invoicing.products.create'),
                'pode_editar' => (bool) $request->user()?->can('invoicing.products.edit'),
                'pode_apagar' => (bool) $request->user()?->can('invoicing.products.delete'),
            ],
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    /** O artigo desta empresa, ou 404. Um `id` do pedido confirma-se sempre. */
    private function doCatalogo(int $id): Product
    {
        return Product::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    /**
     * PARA ONDE FOI ESTE ARTIGO: vendas e movimentos de stock, lado a lado.
     *
     * O ecrã de sempre tinha-o num botão por linha, e a migração deixou-o para
     * trás inteiro. As contas vivem no `RastreioDoArtigo`, não aqui: é a
     * discrepância entre o vendido e o que saiu do stock que este ecrã existe
     * para mostrar, e essa conta não pode ter duas versões.
     *
     * UM ARTIGO APAGADO CONTINUA A RASTREAR-SE. Ele anda em facturas emitidas,
     * e é precisamente quando alguém o apagou que se quer saber por onde
     * andou — daí o `withTrashed`.
     */
    public function rastreio(Request $request, int $id, RastreioDoArtigo $rastreio): JsonResponse
    {
        $this->exigir($request, 'invoicing.products.view');

        $artigo = Product::withTrashed()
            ->where('tenant_id', activeTenantId())
            ->findOrFail($id);

        return response()->json(
            $rastreio->para($artigo, (int) $request->query('dias', RastreioDoArtigo::PERIODO_OMISSAO))
        );
    }

    /**
     * Tira um ficheiro do disco, se lá estiver.
     *
     * Um endereço completo (artigo importado) não é ficheiro nosso e não se
     * apaga: só se esquece a referência.
     */
    private function apagarDoDisco(?string $caminho): void
    {
        if (! filled($caminho) || filter_var($caminho, FILTER_VALIDATE_URL)) {
            return;
        }

        if (Storage::disk('public')->exists($caminho)) {
            Storage::disk('public')->delete($caminho);
        }
    }

    /**
     * O que o catálogo TEM de sector — tamanhos e cores reais, e se há
     * artigos de receituário ou de conservação.
     *
     * Serve para mostrar os filtros a quem já tem artigos marcados mesmo com
     * o perfil desligado: numa oficina (sem perfil e sem dados) continuam
     * escondidos, que é o que se pretende.
     */
    private function variantesDoCatalogo(): array
    {
        $catalogo = fn () => Product::where('tenant_id', activeTenantId());

        return [
            'tamanhos' => $catalogo()->whereNotNull('size')->where('size', '<>', '')
                ->distinct()->orderBy('size')->pluck('size')->all(),
            'cores' => $catalogo()->whereNotNull('color')->where('color', '<>', '')
                ->distinct()->orderBy('color')->pluck('color')->all(),
            'ha_receituario' => $catalogo()->where(
                fn ($q) => $q->where('requires_prescription', true)->orWhere('is_controlled', true)
            )->exists(),
            'ha_conservacao' => $catalogo()->whereNotNull('storage_conditions')
                ->where('storage_conditions', '<>', '')->exists(),
        ];
    }

    /**
     * As mesmas regras de sempre, nos campos que este ecrã trata.
     *
     * `$actual` é o artigo que se está a editar — serve para os campos cujo
     * valor por omissão, numa edição, é o que JÁ ESTÁ GRAVADO e não o que é
     * bom para um artigo novo.
     */
    private function validar(Request $request, ?int $exceptoId = null, ?Product $actual = null): array
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'type' => ['required', 'in:produto,servico'],
            'description' => ['nullable', 'string', 'max:2000'],

            /*
             * O CÓDIGO DO ARTIGO — gerado automaticamente, mas editável.
             *
             * É assim desde sempre: o `Product::creating` põe um `PROD000001`
             * a quem vier sem código, e o ecrã mostra-o para quem quiser usar
             * o código que já tem no armazém. Aqui é `nullable` de propósito —
             * quem não o mandar continua a receber o gerado — mas é ÚNICO POR
             * EMPRESA, que é o que a coluna promete e o que o gerador presume
             * ao procurar o maior número usado.
             */
            'code' => ['nullable', 'string', 'max:50', Rule::unique('invoicing_products', 'code')
                ->where(fn ($q) => $q->where('tenant_id', activeTenantId())->whereNull('deleted_at'))
                ->ignore($exceptoId)],

            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'category_id' => ['required', 'integer', 'exists:invoicing_categories,id'],

            /*
             * A MARCA E O FORNECEDOR CONFIRMAM-SE CONTRA ESTA EMPRESA.
             *
             * A regra de sempre era um `exists:invoicing_brands,id` seco: um
             * número escrito à mão no pedido punha a marca de outra empresa
             * num artigo nosso, e o nome dela aparecia depois na ficha. Um
             * `id` que venha do pedido confirma-se — e sem os apagados, que
             * as duas tabelas têm SoftDeletes.
             */
            'brand_id' => ['nullable', 'integer', Rule::exists('invoicing_brands', 'id')
                ->where('tenant_id', activeTenantId())->whereNull('deleted_at')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('invoicing_suppliers', 'id')
                ->where('tenant_id', activeTenantId())->whereNull('deleted_at')],
            'tax_type' => ['required', 'in:iva,isento'],
            'tax_rate_id' => ['required_if:tax_type,iva', 'nullable', 'integer', 'exists:invoicing_taxes,id'],
            'exemption_reason' => ['required_if:tax_type,isento', 'nullable', 'string', 'max:255'],
            'manage_stock' => ['nullable', 'boolean'],
            // Trabalhos à medida: o preço escreve-se na hora, no POS.
            'preco_no_pos' => ['nullable', 'boolean'],
            'stock_min' => ['nullable', 'integer', 'min:0'],
            'stock_max' => ['nullable', 'integer', 'min:0', 'gte:stock_min'],
            'is_active' => ['nullable', 'boolean'],

            /*
             * LOTES E VALIDADES — é isto que liga o artigo ao módulo dos
             * lotes. Sem estes quatro interruptores o artigo nunca lá entra:
             * é o `track_batches` que o `Product::controlaStock()` lê para
             * saber que este artigo desconta lote a lote.
             */
            'track_batches' => ['nullable', 'boolean'],
            'track_expiry' => ['nullable', 'boolean'],
            'require_batch_on_purchase' => ['nullable', 'boolean'],
            'require_batch_on_sale' => ['nullable', 'boolean'],

            // FARMÁCIA — nenhum obrigatório. Um artigo comum não preenche
            // nada disto e tem de continuar a poder ser gravado.
            'requires_prescription' => ['nullable', 'boolean'],
            'is_controlled' => ['nullable', 'boolean'],
            'active_ingredient' => ['nullable', 'string', 'max:255'],
            'dosage' => ['nullable', 'string', 'max:60'],
            'pharmaceutical_form' => ['nullable', 'string', 'max:40'],
            'armed_registration' => ['nullable', 'string', 'max:60'],

            // VESTUÁRIO.
            'size' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:40'],
            'gender' => ['nullable', 'in:' . self::LISTA_GENEROS],
            'material' => ['nullable', 'string', 'max:120'],

            // COSMÉTICA E MERCEARIA. Os meses depois de aberto são um prazo
            // real: 0 não quer dizer nada e 120 (dez anos) já é engano.
            'net_content' => ['nullable', 'string', 'max:40'],
            'pao_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'inci_ingredients' => ['nullable', 'string'],
            'storage_conditions' => ['nullable', 'in:' . self::LISTA_CONSERVACAO],
            'allergens' => ['nullable', 'string', 'max:255'],
            'origin_country' => ['nullable', 'string', 'max:60'],
        ]);

        /*
         * AS MARCAS DE FARMÁCIA SÃO BOOLEANOS QUE TAMBÉM SE DESLIGAM.
         *
         * As colunas são NOT NULL com omissão `false`. Um `null` vindo do
         * formulário atropelava a omissão e o MySQL recusava; e um booleano
         * que só sabe ligar-se é pior do que não existir — o artigo ficava
         * marcado como sujeito a receita para sempre.
         */
        $booleanos = [
            'requires_prescription', 'is_controlled',
            'track_batches', 'track_expiry', 'require_batch_on_purchase', 'require_batch_on_sale',
        ];

        foreach ($booleanos as $marca) {
            if (array_key_exists($marca, $dados)) {
                $dados[$marca] = (bool) $dados[$marca];
            }
        }

        /*
         * Um SERVIÇO não gere stock. Deixá-lo ligado punha-o a desaparecer do
         * POS assim que a «quantidade» chegasse a zero — e um serviço não tem
         * quantidade nenhuma.
         *
         * Um PRODUTO nasce a gerir stock: a caixa desmarcada por omissão deixou
         * farmácias inteiras com artigos que se vendiam e nunca desciam, e o
         * sintoma só aparecia semanas depois, com as contagens já fora.
         *
         * MAS A EDITAR QUEM MANDA É O QUE ESTÁ GRAVADO. Um artigo a que alguém
         * desligou o stock de propósito não pode voltar a ligá-lo só porque um
         * pedido de edição não trouxe o campo — isso apagava uma decisão do
         * utilizador sem ninguém dar por nada.
         */
        $dados['manage_stock'] = $dados['type'] === 'servico'
            ? false
            : (bool) ($dados['manage_stock'] ?? $actual?->manage_stock ?? true);

        /*
         * UM CÓDIGO EM BRANCO NÃO SE GRAVA.
         *
         * A criar, tira-se do pedido e o `Product::creating` gera-o — era o
         * que o ecrã de sempre fazia quando o campo era limpo. A editar, um
         * campo vazio apagaria o código de um artigo que já anda em facturas
         * emitidas, e um artigo sem código deixa de se encontrar por ele.
         */
        if (array_key_exists('code', $dados) && ! filled(trim((string) $dados['code']))) {
            unset($dados['code']);
        }

        /*
         * PERGUNTAR O PREÇO NO POS.
         *
         * Como o `manage_stock`: a editar, a omissão é o que está gravado —
         * um pedido que não traga o campo não pode desmarcar a decisão de
         * quem a tomou. Na factura de venda o preço escreve-se na linha, como
         * sempre; isto é só do balcão (`App\Livewire\POS\POSSystem`).
         */
        $dados['preco_no_pos'] = (bool) ($dados['preco_no_pos'] ?? $actual?->preco_no_pos ?? false);

        /*
         * E UM SERVIÇO TAMBÉM NÃO TEM LOTES.
         *
         * É a mesma regra do stock, continuada: um lote é uma remessa de
         * mercadoria com número e validade, e uma hora de trabalho não tem
         * remessa nenhuma. Deixar as marcas ligadas num serviço punha o POS
         * a pedir um lote que nunca ninguém iria dar, e a venda encravava.
         */
        if ($dados['type'] === 'servico') {
            foreach (['track_batches', 'track_expiry', 'require_batch_on_purchase', 'require_batch_on_sale'] as $marca) {
                $dados[$marca] = false;
            }
        }

        // Um dos dois, nunca os dois.
        $dados['tax_rate_id'] = $dados['tax_type'] === 'iva' ? ($dados['tax_rate_id'] ?? null) : null;
        $dados['exemption_reason'] = $dados['tax_type'] === 'isento' ? ($dados['exemption_reason'] ?? null) : null;

        /*
         * AS COLUNAS QUE NÃO ACEITAM NULL.
         *
         * `cost`, `stock_min` e `is_active` são NOT NULL *com omissão na base*
         * — e uma omissão só se aplica quando a coluna NÃO vem no INSERT.
         * Mandar `null` explicitamente atropela-a e o MySQL recusa: «Column
         * 'cost' cannot be null», 500 no ecrã. Um campo deixado em branco no
         * formulário chega aqui como null, portanto é aqui que se converte.
         */
        foreach (['cost' => 0, 'stock_min' => 0] as $campo => $omissao) {
            if (($dados[$campo] ?? null) === null) {
                $dados[$campo] = $omissao;
            }
        }

        $dados['is_active'] = (bool) ($dados['is_active'] ?? true);

        return $dados;
    }
}
