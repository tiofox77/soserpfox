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
                ];
            })->values(),
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
            'message' => __('Venda :numero registada.', ['numero' => $factura->invoice_number]),
        ], 201);
    }
}
