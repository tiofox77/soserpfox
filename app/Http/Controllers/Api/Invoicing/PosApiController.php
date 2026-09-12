<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Category;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\PosShift;
use App\Models\Product;
use App\Models\Invoicing\Warehouse;
use App\Models\Treasury\PaymentMethod as TreasuryPaymentMethod;
use App\Services\POS\PosSaleService;
use App\Services\POS\PosSalesReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * O BALCÃO, para o ecrã em React.
 *
 * ESTE CONTROLADOR NÃO FECHA VENDAS. Quem fecha é o `PosSaleService` — o
 * mesmo serviço que o PWA offline já usava para subir as vendas do aparelho.
 *
 * Havia duas implementações do mesmo facto: o `POSSystem::completeSale` do
 * Livewire (200 linhas dentro de um componente de ecrã) e este serviço. Duas
 * maneiras de gravar a mesma venda acabam sempre por divergir numa delas —
 * e a que diverge é a que ninguém está a olhar. O ecrã novo entra pela porta
 * do serviço, e passam a ser uma só.
 *
 * O que fica aqui é só o que um ecrã precisa de perguntar: que artigos há,
 * que categorias, que formas de pagamento, que turno está aberto e em que
 * armazém se está a vender.
 */
class PosApiController extends Controller
{
    /**
     * Os montantes de troco rápido.
     *
     * São os mesmos sete do ecrã de sempre (`POSSystem::$quickAmounts`), e
     * estão escritos aqui porque também lá estavam escritos: NÃO há definição
     * nenhuma na base que os guarde. Ficam num sítio só para o dia em que
     * houver — e para que os dois ecrãs não digam números diferentes enquanto
     * conviverem.
     *
     * @var list<float>
     */
    private const MONTANTES_RAPIDOS = [1000, 2000, 5000, 10000, 20000, 50000, 100000];

    /**
     * O ARMAZÉM DE ONDE SAI A MERCADORIA.
     *
     * O padrão da empresa; se não houver nenhum marcado, o primeiro activo —
     * e nesse caso o nome diz que não é o padrão, para quem está ao balcão
     * saber que a escolha foi do sistema e não dele. É a regra do ecrã de
     * sempre (`POSSystem::mount`).
     *
     * @return array{id: int|null, nome: string|null}
     */
    private function armazem(int $tenantId): array
    {
        $padrao = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($padrao) {
            return ['id' => (int) $padrao->id, 'nome' => $padrao->name];
        }

        $qualquer = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->first();

        return $qualquer
            ? ['id' => (int) $qualquer->id, 'nome' => __(':armazem (sem padrão)', ['armazem' => $qualquer->name])]
            : ['id' => null, 'nome' => null];
    }

    /** O turno aberto DESTE operador, que é o que manda no ecrã. */
    private function turnoAberto(int $tenantId): ?PosShift
    {
        return PosShift::where('tenant_id', $tenantId)
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    /**
     * O que o ecrã precisa de saber uma vez só.
     *
     * Não traz artigos: esses mudam com a procura e com a categoria, e vêm
     * pela sua própria porta para não viajarem inteiros a cada tecla.
     */
    public function opcoes(Request $request): JsonResponse
    {
        $tenantId = (int) activeTenantId();

        abort_unless($tenantId, 403, __('Sem empresa activa.'));

        $definicoes = InvoicingSettings::forTenant($tenantId);
        $turno = $this->turnoAberto($tenantId);
        $armazem = $this->armazem($tenantId);

        /*
         * AS CATEGORIAS COM A CONTAGEM — cache de 60 s.
         *
         * O `withCount` é uma subconsulta por categoria e, no ecrã de sempre,
         * corria a cada clique no carrinho. Uma categoria nova aparece na
         * mesma quase de imediato, e num minuto de balcão passa de dezenas de
         * execuções a uma.
         */
        $categorias = Cache::remember(
            "pos_categorias_react_{$tenantId}",
            60,
            fn () => Category::where('tenant_id', $tenantId)
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'nome' => $c->name,
                    'artigos' => (int) $c->products_count,
                ])
                ->values()
                ->all()
        );

        return response()->json([
            /*
             * O TURNO MANDA. Sem turno aberto não se vende — o ecrã de sempre
             * mandava a pessoa para os Turnos de Caixa, e é a mesma regra.
             * Quem decide é o servidor: um ecrã que se desenhasse na mesma
             * deixava o operador a carregar em botões que não dão em nada.
             */
            'turno' => $turno ? [
                'id' => (int) $turno->id,
                'numero' => $turno->shift_number,
                'aberto_em' => optional($turno->opened_at)->toDateTimeString(),
                'abertura' => round((float) ($turno->opening_amount ?? 0), 2),
            ] : null,
            'rota_dos_turnos' => '/invoicing/pos/shifts',

            'armazem' => $armazem,
            'categorias' => $categorias,

            /*
             * AS FORMAS DE PAGAMENTO são as da TESOURARIA desta empresa, e não
             * uma lista escrita à mão: é para elas que o movimento vai, e uma
             * forma que o ecrã oferecesse sem existir na tesouraria dava uma
             * venda sem destino para o dinheiro.
             */
            'formas_de_pagamento' => TreasuryPaymentMethod::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn ($m) => [
                    'valor' => $m->code ?: strtolower($m->name),
                    'rotulo' => $m->name,
                ])
                ->values(),

            'definicoes' => [
                // Esconder o que não tem stock — serviços e artigos sem gestão
                // de stock nunca se escondem, que esses vendem-se sempre.
                'esconde_sem_stock' => (bool) ($definicoes->pos_hide_out_of_stock ?? true),
                // Os botões de troco rápido do balcão.
                'montantes_rapidos' => self::MONTANTES_RAPIDOS,
                'taxa_irt' => round((float) ($definicoes->default_irt_rate ?? 6.5), 2),
                // A máscara de dinheiro do resto da facturação vale aqui também.
                'mascara_de_preco' => (bool) ($definicoes->price_mask_enabled ?? false),
            ],

            /*
             * DE QUEM É O CARRINHO.
             *
             * O espelho do carrinho vive no `localStorage`, e a chave dele TEM
             * de dizer a empresa e o operador. Sem a empresa, quem tem mais do
             * que uma casa — e há quem tenha — trocava de empresa e levava o
             * carrinho atrás: artigos escolhidos numa ficavam lá para serem
             * facturados na outra. Sem o operador, um balcão partilhado dava ao
             * turno seguinte o carrinho que o anterior deixou a meio.
             *
             * Vem do SERVIDOR de propósito: o ecrã lia-o de uma `<meta>` que
             * não existe em lado nenhum, e a chave saía sempre igual.
             */
            'dono_do_carrinho' => [
                'empresa' => $tenantId,
                'operador' => (int) ($request->user()?->id ?? 0),
            ],

            'permissoes' => [
                'pode_vender' => (bool) $request->user()?->can('invoicing.sales.invoices.create'),
                'pode_criar_cliente' => (bool) $request->user()?->can('invoicing.clients.create'),
                // Mudar o preço ao balcão é outra permissão: nem toda a gente
                // que vende pode dar desconto pela mão.
                'pode_mudar_preco' => (bool) $request->user()?->can('invoicing.products.edit'),
            ],
        ]);
    }

    /**
     * OS ARTIGOS DA GRELHA.
     *
     * A procura é a do balcão, e é mais larga do que parece: o nome, o código,
     * o código de barras — e ainda a SUBSTÂNCIA ACTIVA e o TAMANHO. Numa
     * farmácia pergunta-se por «paracetamol» sem saber que a caixa diz
     * Ben-u-ron; numa loja de roupa pergunta-se por «38», que não está no nome
     * nem no código. Era assim no ecrã de sempre e continua a ser.
     */
    public function artigos(Request $request): JsonResponse
    {
        $tenantId = (int) activeTenantId();

        abort_unless($tenantId, 403, __('Sem empresa activa.'));

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'categoria' => ['nullable', 'integer'],
            'armazem' => ['nullable', 'integer'],
        ]);

        $armazemId = $filtros['armazem'] ?? $this->armazem($tenantId)['id'];
        $definicoes = InvoicingSettings::forTenant($tenantId);

        /*
         * O STOCK DESTE ARMAZÉM, não o agregado.
         *
         * O agregado (`stock_quantity`) é a soma de todos os armazéns; ao
         * balcão o que interessa é o que está NESTE. A subconsulta é a mesma
         * do ecrã de sempre — ver `stock_in_warehouse`.
         */
        $stock = $armazemId
            ? '(SELECT COALESCE(SUM(s.quantity), 0) FROM invoicing_stocks s
                 WHERE s.product_id = invoicing_products.id
                   AND s.tenant_id = ?
                   AND s.warehouse_id = ' . (int) $armazemId . ')'
            : 'invoicing_products.stock_quantity';

        $q = Product::where('invoicing_products.tenant_id', $tenantId)
            ->where('invoicing_products.is_active', true)
            /*
             * ARTIGOS DE UM MÓDULO DE NEGÓCIO NÃO SE VENDEM AQUI.
             *
             * Um «Corte de Cabelo» existe em `invoicing_products` só para a
             * linha da factura ter artigo de catálogo — quem o marca e cobra é o
             * POS do salão, que sabe do profissional, da duração e da marcação.
             * Ao balcão da facturação era um artigo solto, sem nada disso.
             */
            ->whereNull('invoicing_products.module')
            ->selectRaw("invoicing_products.*, {$stock} AS stock_no_armazem", $armazemId ? [$tenantId] : []);

        if (! empty($definicoes->pos_hide_out_of_stock ?? true)) {
            $q->whereRaw(
                "(invoicing_products.type = 'servico' OR invoicing_products.manage_stock = 0 OR {$stock} > 0)",
                $armazemId ? [$tenantId] : []
            );
        }

        if ($procura = ($filtros['procura'] ?? null)) {
            $q->where(function ($w) use ($procura) {
                $w->where('invoicing_products.name', 'like', "%{$procura}%")
                    ->orWhere('invoicing_products.sku', 'like', "%{$procura}%")
                    ->orWhere('invoicing_products.barcode', 'like', "%{$procura}%")
                    ->orWhere('invoicing_products.active_ingredient', 'like', "%{$procura}%")
                    ->orWhere('invoicing_products.size', 'like', "%{$procura}%");
            });
        }

        if ($categoria = ($filtros['categoria'] ?? null)) {
            $q->where('invoicing_products.category_id', $categoria);
        }

        $artigos = $q->orderBy('invoicing_products.name')->limit(50)->get();

        return response()->json([
            'data' => $artigos->map(function ($p) {
                $servico = $p->type === 'servico';

                return [
                    'id' => (int) $p->id,
                    'nome' => $p->name,
                    'codigo' => $p->sku,
                    'codigo_de_barras' => $p->barcode,
                    'unidade' => $p->unit,
                    'servico' => $servico,
                    'preco' => round((float) $p->price, 2),
                    /*
                     * «PREÇO NO POS» É UM INTERRUPTOR, não um preço.
                     *
                     * A coluna é um `tinyint` e quer dizer: PERGUNTAR O PREÇO
                     * AO BALCÃO. O artigo entra no carrinho pelo modal, com o
                     * preço de catálogo já lá escrito como proposta — é o
                     * artigo que se vende a peso, ou por acordo. Tratá-la como
                     * um valor punha 1,00 Kz no ecrã.
                     */
                    'pergunta_preco' => (bool) $p->preco_no_pos,
                    'imagem' => $p->featured_image,
                    // Serviços e artigos sem gestão de stock não mostram número.
                    'stock' => $servico || ! $p->manage_stock
                        ? null
                        : round((float) $p->stock_no_armazem, 3),
                    'categoria_id' => $p->category_id ? (int) $p->category_id : null,

                    /*
                     * O QUE O BALCÃO TEM DE SABER ANTES DE FECHAR A VENDA.
                     *
                     * Depois de emitida a factura, o artigo já saiu da farmácia:
                     * um aviso que só aparece no fim não serve para nada.
                     *
                     * A RECEITA avisa e não trava — o operador pode ter a
                     * receita na mão. O CONTROLADO (psicotrópico) pergunta, e
                     * só entra no carrinho depois de alguém responder: é uma
                     * substância cuja venda tem registo legal, e ninguém a
                     * despacha com um clique distraído.
                     */
                    'receita' => (bool) $p->requires_prescription,
                    'controlado' => (bool) $p->is_controlled,
                ];
            })->values(),
        ]);
    }

    /**
     * O QUE ESTE CÓDIGO DE BARRAS É.
     *
     * A grelha esconde o que está sem stock — numa das farmácias, 1415 de 5729
     * artigos. Passar o leitor por um artigo esgotado devolvia um ecrã vazio,
     * indistinguível de «este código não existe», e o operador concluía que a
     * leitura não funcionava. Com o produto na mão.
     *
     * Aqui a pergunta é feita ao CATÁLOGO INTEIRO, e a resposta diz qual dos
     * quatro casos é: vende-se, está sem stock, está inactivo, ou não existe.
     *
     * E procura-se por todas as FORMAS EQUIVALENTES do código: o mesmo artigo
     * pode estar guardado com o envelope GS1 à frente ou só com o EAN-13 de
     * dentro, conforme o sistema de onde veio o catálogo — e o leitor tanto
     * manda um como o outro.
     */
    public function porCodigo(Request $request): JsonResponse
    {
        $tenantId = (int) activeTenantId();

        abort_unless($tenantId, 403, __('Sem empresa activa.'));

        $lido = trim((string) $request->query('codigo', ''));

        if (mb_strlen($lido) < 6) {
            return response()->json(['estado' => 'curto']);
        }

        $formas = \App\Support\CodigoDeBarras::formas($lido);

        $encontrados = Product::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->whereIn('barcode', $formas)->orWhereIn('sku', $formas))
            ->get();

        // Correspondência exacta manda; a equivalência é o plano B.
        $p = $encontrados->firstWhere('barcode', $lido)
            ?? $encontrados->firstWhere('sku', $lido)
            ?? $encontrados->first();

        if (! $p) {
            return response()->json(['estado' => 'desconhecido']);
        }

        if (! $p->is_active) {
            return response()->json([
                'estado' => 'inactivo',
                'nome' => $p->name,
                'message' => __(':artigo está inactivo e não pode ser vendido.', ['artigo' => $p->name]),
            ]);
        }

        // ARTIGO DE MÓDULO: a mesma regra da grelha e da venda.
        if (filled($p->module)) {
            return response()->json([
                'estado' => 'de_modulo',
                'nome' => $p->name,
                'message' => __(':artigo vende-se no POS do módulo :modulo.', [
                    'artigo' => $p->name, 'modulo' => $p->module,
                ]),
            ]);
        }

        $armazemId = $this->armazem($tenantId)['id'];
        $controlaStock = $p->type !== 'servico' && $p->manage_stock;

        $disponivel = $controlaStock
            ? (float) DB::table('invoicing_stocks')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $p->id)
                ->when($armazemId, fn ($q) => $q->where('warehouse_id', $armazemId))
                ->sum('quantity')
            : null;

        if ($controlaStock && $disponivel <= 0) {
            return response()->json([
                'estado' => 'sem_stock',
                'nome' => $p->name,
                'message' => __(':artigo está sem stock neste armazém.', ['artigo' => $p->name]),
            ]);
        }

        return response()->json([
            'estado' => 'encontrado',
            'artigo' => [
                'id' => (int) $p->id,
                'nome' => $p->name,
                'codigo' => $p->sku,
                'codigo_de_barras' => $p->barcode,
                'unidade' => $p->unit,
                'servico' => $p->type === 'servico',
                'preco' => round((float) $p->price, 2),
                'pergunta_preco' => (bool) $p->preco_no_pos,
                'imagem' => $p->featured_image,
                'stock' => $disponivel === null ? null : round($disponivel, 3),
                'categoria_id' => $p->category_id ? (int) $p->category_id : null,
                'receita' => (bool) $p->requires_prescription,
                'controlado' => (bool) $p->is_controlled,
            ],
        ]);
    }

    /** Os clientes, para o modal de escolha. Só por procura: são milhares. */
    public function clientes(Request $request): JsonResponse
    {
        $tenantId = (int) activeTenantId();

        abort_unless($tenantId, 403, __('Sem empresa activa.'));

        $procura = (string) $request->query('procura', '');

        $q = Client::where('tenant_id', $tenantId)->where('is_active', true);

        if ($procura !== '') {
            $q->where(function ($w) use ($procura) {
                $w->where('name', 'like', "%{$procura}%")
                    ->orWhere('nif', 'like', "%{$procura}%")
                    ->orWhere('phone', 'like', "%{$procura}%");
            });
        }

        return response()->json([
            'data' => $q->orderBy('name')->limit(30)->get(['id', 'name', 'nif', 'phone', 'email'])
                ->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'nome' => $c->name,
                    'nif' => $c->nif,
                    'telefone' => $c->phone,
                    'email' => $c->email,
                ])
                ->values(),
        ]);
    }

    /**
     * FECHAR A VENDA — pela porta do `PosSaleService`.
     *
     * O `local_uuid` vem do ecrã, um por tentativa de venda. Não é burocracia
     * do offline: é o que torna a venda IDEMPOTENTE. Se a rede tossir e o
     * operador carregar em «Finalizar» outra vez, o serviço reconhece o mesmo
     * identificador e devolve a factura que já gravou, em vez de gravar uma
     * segunda com o mesmo dinheiro, o mesmo stock e a mesma tesouraria.
     *
     * O ecrã em Livewire não tinha essa protecção nenhuma.
     */
    public function vender(Request $request, PosSaleService $servico): JsonResponse
    {
        $tenantId = (int) activeTenantId();

        abort_unless($tenantId, 403, __('Sem empresa activa.'));
        abort_unless(
            $request->user()?->can('invoicing.sales.invoices.create'),
            403,
            __('Sem permissão para vender.')
        );

        // SEM TURNO NÃO SE VENDE. É a guarda do ecrã de sempre, trazida para
        // o servidor: o dinheiro tem de cair no turno de alguém.
        abort_unless(
            $this->turnoAberto($tenantId),
            422,
            __('Não há turno de caixa aberto. Abra um turno antes de vender.')
        );

        $dados = $request->validate([
            'local_uuid' => ['required', 'string', 'max:80'],
            'client_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'string', 'max:30'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'discount_commercial' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.is_service' => ['nullable', 'boolean'],
            'items.*.unit' => ['nullable', 'string', 'max:10'],
        ]);

        /*
         * A GRELHA FILTRA, NÃO PROTEGE.
         *
         * Os ids das linhas vêm do browser. Um artigo de um módulo de negócio
         * (hoje o salão) vende-se no POS desse módulo, que sabe do profissional,
         * da duração e da marcação — ao balcão da facturação era um serviço
         * solto, sem nada disso.
         */
        $deModulo = Product::where('tenant_id', $tenantId)
            ->whereIn('id', collect($dados['items'])->pluck('product_id')->filter()->all())
            ->whereNotNull('module')
            ->get(['name', 'module']);

        if ($deModulo->isNotEmpty()) {
            $primeiro = $deModulo->first();

            return response()->json([
                'message' => __(':artigo vende-se no POS do módulo :modulo.', [
                    'artigo' => $primeiro->name, 'modulo' => $primeiro->module,
                ]),
            ], 422);
        }

        try {
            $factura = $servico->createFromPayload($dados, $tenantId, (int) auth()->id());
        } catch (\App\Services\POS\ClientePorSincronizar $e) {
            // Só acontece no offline (cliente criado no aparelho). Aqui o
            // cliente vem sempre com id, mas a porta é a mesma e a excepção
            // existe — devolvê-la como 409 é honesto.
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => __('Não foi possível fechar a venda: :erro', ['erro' => $e->getMessage()]),
            ], 422);
        }

        $factura->loadMissing(['client', 'items', 'tenant']);

        // O papel configurado lê-se AGORA e não ao abrir o ecrã: a definição
        // pode mudar entre duas vendas e o balcão não recarrega a página.
        $definicoes = InvoicingSettings::forTenant($tenantId);
        $qr = function_exists('getAGTQRData') ? getAGTQRData($factura, 100) : [];

        return response()->json([
            'id' => (int) $factura->id,
            'numero' => $factura->invoice_number,
            'numero_interno' => method_exists($factura, 'numeroInterno') ? $factura->numeroInterno() : $factura->invoice_number,
            'total' => round((float) $factura->total, 2),
            'data' => optional($factura->invoice_date)->toDateString(),
            'cliente' => $factura->client?->name ?? __('Consumidor Final'),
            /*
             * O QR DA AGT vem do servidor, como no PWA.
             *
             * O helper devolve um ARRAY (`data`, `image`, `atcud`) e não uma
             * imagem: mandar o array inteiro para o `src` do ecrã dava uma
             * imagem partida no talão. O que o ecrã desenha é o `image`, que é
             * um data-URI; o `atcud` vai à parte, porque é texto que se lê.
             */
            'qr' => $qr['image'] ?? null,
            'atcud' => $qr['atcud'] ?? $factura->atcud,
            /*
             * O PAPEL EM QUE A EMPRESA IMPRIME, e as duas moradas.
             *
             * O modal abre já no formato configurado — talão ou A4 — com a
             * pré-visualização à vista e a impressão pronta. Ao balcão, dois
             * cliques para ver o que se acabou de vender são dois a mais.
             *
             * `?imprimir=1` faz a página mandar imprimir sozinha, depois de as
             * imagens carregarem.
             */
            'formato' => ($definicoes->pos_formato_impressao ?? 'talao') === 'a4' ? 'a4' : 'talao',
            'papeis' => [
                'talao' => "/invoicing/sales/invoices/{$factura->id}/talao",
                'a4' => "/invoicing/sales/invoices/{$factura->id}/preview",
            ],
            // Compatibilidade com o contrato anterior do POS. Aplicacoes e
            // integracoes que ainda leem `preview` continuam a abrir o A4,
            // enquanto o ecra novo escolhe entre os dois papeis acima.
            'preview' => "/invoicing/sales/invoices/{$factura->id}/preview",
            'message' => __('Venda :numero registada.', ['numero' => $factura->invoice_number]),
        ], 201);
    }

    /**
     * O MAPA DE VENDAS DO BALCÃO.
     *
     * A consulta é a do `PosSalesReportQuery` — o MESMO serviço que o ecrã em
     * Livewire, o PDF e o Excel já usavam. Montar aqui uma consulta própria
     * era ter quatro relatórios a dizer números diferentes sobre o mesmo dia.
     */
    public function relatorio(Request $request): JsonResponse
    {
        $tenantId = (int) activeTenantId();

        abort_unless($tenantId, 403, __('Sem empresa activa.'));

        /*
         * O MÓDULO DE ORIGEM decide também QUEM pode ver.
         *
         * O mesmo mapa serve o balcão da facturação e o do restaurante. Quem
         * gere o restaurante tem `restaurant.reports.view` e pode não ter
         * nenhuma permissão da facturação — era assim no ecrã em Livewire, que
         * aceitava as duas.
         */
        $modulo = (string) $request->string('source_module');

        /*
         * A PERMISSÃO CHAMA-SE `invoicing.pos.reports` — sem `.view`.
         *
         * Estava aqui escrito `invoicing.pos.reports.view`, que NÃO EXISTE na
         * base: é o nome da rota e do `SyncPosReportPermissions` sem o sufixo.
         * Com os curingas desligados, `can()` de uma permissão inexistente é
         * sempre falso — e o caixa que tinha exactamente a permissão que a
         * rota exige abria a página e via um 403 em cada pedido. Só passava
         * quem tivesse, por outro caminho, o direito de ver as facturas.
         */
        abort_unless(
            $request->user()?->can('invoicing.pos.reports')
                || $request->user()?->can('invoicing.sales.invoices.view')
                || ($modulo === 'restaurant' && $request->user()?->can('restaurant.reports.view')),
            403,
            __('Sem permissão para ver os relatórios do POS.')
        );

        $filtros = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'document_type' => ['nullable', 'string', 'max:20'],
            'source_module' => ['nullable', 'string', 'max:30'],
            'user_id' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        /*
         * «CADA UM VÊ AS QUE FEZ» — a regra que faltava aqui.
         *
         * O ecrã em Livewire prendia o mapa às vendas do próprio a quem não
         * tivesse `invoicing.pos.reports.all`; esta porta não o fazia, e
         * qualquer operador com o direito de VER relatórios via as vendas dos
         * colegas — totais incluídos.
         *
         * É AQUI que a decisão tem de ser tomada, e não no ecrã: a lista, os
         * totais e o Excel saem todos do mesmo `PosSalesReportQuery`, e um
         * filtro posto no browser não prende nada.
         */
        $filtros['only_user_id'] = $request->user()?->can('invoicing.pos.reports.all')
            ? ($filtros['user_id'] ?? null)
            : $request->user()?->id;

        $consulta = new PosSalesReportQuery($tenantId, $filtros);

        $pagina = $consulta->listagem()->paginate($filtros['por_pagina'] ?? 20)->withQueryString();

        /*
         * O ESTADO NA AGT, numa consulta só para a página inteira.
         *
         * Não vai para o `PosSalesReportQuery` de propósito: esse é partilhado
         * com o PDF e o Excel, e mexer-lhe mudava ficheiros que ninguém pediu
         * para mudar. Aqui junta-se ao que a página já trouxe.
         *
         * Lê-se a SUBMISSÃO e não só a coluna da factura: é a submissão que
         * sabe se foi recusada e porquê, e uma venda recusada pela AGT que o
         * mapa mostrasse como enviada era pior do que não mostrar nada.
         */
        $ids = collect($pagina->items())
            ->filter(fn ($d) => $d->doc_tipo === PosSalesReportQuery::TIPO_FACTURA)
            ->pluck('doc_id')
            ->all();

        $naAgt = $ids === [] ? collect() : \App\Models\AGT\AGTSubmission::where('tenant_id', $tenantId)
            ->where('document_type', \App\Models\Invoicing\SalesInvoice::class)
            ->whereIn('document_id', $ids)
            ->get(['document_id', 'status', 'error_message'])
            ->keyBy('document_id');

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($d) => [
                /*
                 * O TIPO EM PALAVRA, e não o código.
                 *
                 * A consulta devolve 'FR' e 'NC' em `doc_tipo` — que é o
                 * código do documento, e não o que distingue uma venda de uma
                 * devolução no mapa. O ecrã lê «factura» ou «nota»; o código
                 * verdadeiro vai em `subtipo`, que é onde a etiqueta o mostra.
                 */
                'tipo' => $d->doc_tipo === PosSalesReportQuery::TIPO_FACTURA ? 'factura' : 'nota',
                'subtipo' => $d->doc_subtipo,
                'id' => (int) $d->doc_id,
                'numero' => $d->numero,
                'numero_interno' => $d->numero_interno ?? $d->numero,
                'data' => (string) $d->data,
                'cliente' => $d->cliente_nome ?: __('Consumidor Final'),
                'cliente_nif' => $d->cliente_nif,
                'subtotal' => round((float) $d->subtotal, 2),
                'imposto' => round((float) $d->tax_amount, 2),
                'desconto' => round((float) $d->desconto, 2),
                'total' => round((float) $d->total, 2),
                'forma' => $d->payment_method,
                'estado' => $d->status,
                'motivo' => $d->motivo,
                'factura_origem' => $d->factura_origem,

                /*
                 * O SELO DA AGT, em três estados e uma cor.
                 *
                 * `validated` é o único que quer dizer «está feito». O
                 * `rejected` traz a razão, que é o que permite ir corrigir em
                 * vez de adivinhar. Quando não há submissão nenhuma, ou a
                 * empresa não comunica ou ainda não foi — e dizer «por
                 * comunicar» é honesto nos dois casos.
                 */
                'agt' => $this->seloDaAgt($d, $naAgt->get($d->doc_id)),

                // As duas moradas do papel, como no fecho da venda.
                'papeis' => $d->doc_tipo === PosSalesReportQuery::TIPO_FACTURA ? [
                    'talao' => "/invoicing/sales/invoices/{$d->doc_id}/talao",
                    'a4' => "/invoicing/sales/invoices/{$d->doc_id}/preview",
                ] : null,
            ])->values(),

            'meta' => [
                'total' => $pagina->total(),
                'pagina' => $pagina->currentPage(),
                'paginas' => $pagina->lastPage(),
                // Os totais contam sobre o PERÍODO, não sobre a página: uma
                // soma que mudasse ao carregar em «Seguinte» não é uma soma.
                'totais' => $consulta->totais(),
                // O papel configurado: é nele que o botão de imprimir imprime.
                'formato' => (InvoicingSettings::forTenant($tenantId)->pos_formato_impressao ?? 'talao') === 'a4' ? 'a4' : 'talao',

                /*
                 * O SELECTOR DE OPERADOR só existe para quem pode ver as
                 * vendas de todos. A quem não pode, mostrá-lo seria oferecer
                 * uma escolha que o servidor ignora — e o mapa ficaria a
                 * dizer «filtrado por Maria» a mostrar as do próprio.
                 */
                'pode_ver_todas' => (bool) $request->user()?->can('invoicing.pos.reports.all'),
                'operadores' => $request->user()?->can('invoicing.pos.reports.all')
                    ? \App\Models\User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                        ->orderBy('name')->limit(200)->get(['id', 'name'])
                        ->map(fn ($u) => ['valor' => (string) $u->id, 'rotulo' => $u->name])->values()
                    : [],
            ],
        ]);
    }

    /**
     * O SELO DA AGT de um documento do mapa.
     *
     * Três estados e uma cor, porque é uma coluna estreita e o que se lê de
     * relance é a cor. A razão da recusa vai no título — quem precisa dela
     * pára em cima e lê; quem só quer saber se está feito vê o verde.
     *
     * @param  object  $d  A linha do mapa
     * @param  \App\Models\AGT\AGTSubmission|null  $submissao
     * @return array{estado: string, rotulo: string, cor: string, razao: string|null}
     */
    private function seloDaAgt($d, $submissao): array
    {
        // A nota de crédito também vai à AGT, mas este mapa só junta a
        // submissão das facturas: para as outras não se afirma nada.
        if ($d->doc_tipo !== PosSalesReportQuery::TIPO_FACTURA) {
            return ['estado' => 'nao-aplica', 'rotulo' => '—', 'cor' => 'neutra', 'razao' => null];
        }

        $estado = $submissao->status ?? null;

        return match ($estado) {
            'validated' => ['estado' => 'validado', 'rotulo' => __('Validada'), 'cor' => 'bom', 'razao' => null],
            'submitted' => ['estado' => 'enviado', 'rotulo' => __('Enviada'), 'cor' => 'primaria', 'razao' => null],
            'rejected' => [
                'estado' => 'recusado',
                'rotulo' => __('Recusada'),
                'cor' => 'perigo',
                'razao' => $submissao->error_message,
            ],
            'pending' => ['estado' => 'em-fila', 'rotulo' => __('Em fila'), 'cor' => 'aviso', 'razao' => null],
            // Sem submissão: ou a empresa não comunica, ou ainda não foi.
            default => ['estado' => 'por-comunicar', 'rotulo' => __('Por comunicar'), 'cor' => 'neutra', 'razao' => null],
        };
    }
}
