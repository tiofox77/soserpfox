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
use App\Support\TextoEstragado;
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
     * QUEM PODE FECHAR UMA VENDA AO BALCÃO.
     *
     * O balcão em Livewire não perguntava nada: quem entrava no POS vendia. O
     * ecrã novo passou a exigir `invoicing.sales.invoices.create` — a
     * permissão de EMITIR FACTURAS no editor — e o papel «Caixa», que é quem
     * está ao balcão, não a tem: tem `invoicing.pos.access` e
     * `invoicing.pos.sell`. Em todas as empresas os caixas ficaram com o botão
     * «Finalizar Venda» cinzento, e só o administrador vendia (Farmácia Luk
     * Simões, 2026-09-13).
     *
     * Vende quem tem a permissão de vender no POS, quem entra no POS (era a
     * regra de sempre) ou quem pode emitir facturas.
     */
    public static function podeVenderAoBalcao(?\Illuminate\Contracts\Auth\Authenticatable $utilizador, ?string $modulo = null): bool
    {
        if (! $utilizador) {
            return false;
        }

        $permissoes = ['invoicing.pos.sell', 'invoicing.pos.access', 'invoicing.sales.invoices.create'];

        if ($modulo === 'salon') {
            array_push($permissoes, 'salon.pos.sell', 'salon.pos.access');
        }

        foreach ($permissoes as $permissao) {
            if ($utilizador->can($permissao)) {
                return true;
            }
        }

        return false;
    }

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

    /**
     * AS CATEGORIAS COMO O OPERADOR AS LÊ.
     *
     * Duas coisas que o balcão mostrava tal e qual estavam gravadas:
     *
     * - os acentos estragados por uma importação («├üLCOOL») — reparam-se na
     *   leitura (App\Support\TextoEstragado), sem mexer no que está gravado;
     * - a mesma categoria duas vezes: a importada com os acentos estragados e
     *   a criada à mão depois, com os artigos repartidos entre as duas
     *   («ÁLCOOL 7» e «├üLCOOL 60»). Quem procura álcool quer os 67. Juntam-se
     *   num só botão, que filtra pelas duas (`ids`).
     *
     * Ordem alfabética portuguesa: «Álcool» ao pé de «Adesivo», e não depois
     * de «Xarope» como numa ordenação por bytes.
     *
     * @return list<array{id: int, ids: list<int>, nome: string, artigos: int}>
     */
    private function categoriasDoBalcao(int $tenantId): array
    {
        $grupos = [];

        foreach (Category::where('tenant_id', $tenantId)->withCount('products')->get() as $c) {
            $nome = trim(preg_replace('/\s+/u', ' ', (string) TextoEstragado::reparar($c->name)));
            $chave = mb_strtoupper($nome);

            $grupos[$chave][] = ['id' => (int) $c->id, 'nome' => $nome, 'artigos' => (int) $c->products_count];
        }

        $categorias = [];

        foreach ($grupos as $membros) {
            // O nome e o id principais são os da que tem mais artigos.
            usort($membros, fn ($a, $b) => [$b['artigos'], $a['id']] <=> [$a['artigos'], $b['id']]);

            $categorias[] = [
                'id' => $membros[0]['id'],
                'ids' => array_column($membros, 'id'),
                'nome' => $membros[0]['nome'],
                'artigos' => array_sum(array_column($membros, 'artigos')),
            ];
        }

        $ordem = class_exists(\Collator::class) ? new \Collator('pt_PT') : null;

        usort($categorias, fn ($a, $b) => $ordem
            ? $ordem->compare($a['nome'], $b['nome'])
            : strcasecmp(\Illuminate\Support\Str::ascii($a['nome']), \Illuminate\Support\Str::ascii($b['nome'])));

        return $categorias;
    }

    /**
     * O ENDEREÇO DA FOTO DO ARTIGO, a partir da raiz do site.
     *
     * Relativo à raiz e não absoluto: o `image_url` do modelo junta o APP_URL,
     * e numa instalação com o APP_URL a dizer «localhost» (ou http num site em
     * https) a foto não carregava. Um endereço que já é completo fica como está.
     */
    private function enderecoDaImagem(?string $caminho): ?string
    {
        if (! $caminho) {
            return null;
        }

        if (filter_var($caminho, FILTER_VALIDATE_URL) || str_starts_with($caminho, '/')) {
            return $caminho;
        }

        return '/storage/' . ltrim(preg_replace('#^storage/#', '', $caminho), '/');
    }

    /** O turno aberto DESTE operador, que é o que manda no ecrã. */
    /**
     * O BALCÃO DO SALÃO É ESTE MESMO ECRÃ — com os serviços.
     *
     * Quando o salão passou a React, `/salon/pos` ficou a apontar para o POS
     * da facturação, que esconde (e recusa) tudo o que é de um módulo. O
     * salão deixou de ter onde vender um corte de cabelo. O `modulo=salon`
     * devolve o separador dos serviços; só vale para uma empresa com o módulo
     * e para quem entra no POS do salão — escrito à mão no pedido, não abre
     * nada a mais.
     */
    private function moduloDoBalcao(Request $request, int $tenantId): ?string
    {
        if ($request->input('modulo') !== 'salon') {
            return null;
        }

        $empresa = \App\Models\Tenant::find($tenantId);
        $eu = $request->user();

        if (! $empresa?->hasModule('salon') || ! $eu) {
            return null;
        }

        return $eu->can('salon.pos.access') || $eu->can('salon.pos.sell') ? 'salon' : null;
    }

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
            "pos_categorias_react_v2_{$tenantId}",
            60,
            fn () => $this->categoriasDoBalcao($tenantId)
        );

        $modulo = $this->moduloDoBalcao($request, $tenantId);

        return response()->json([
            'modulo' => $modulo,

            // As categorias dos serviços do salão vivem no JSON do serviço, não
            // na coluna — a contagem sai de ServiceCategory::comContagens.
            'categorias_de_servicos' => $modulo === 'salon'
                ? \App\Models\Salon\ServiceCategory::comContagens(
                    \App\Models\Salon\ServiceCategory::where('tenant_id', $tenantId)->where('is_active', true)
                        ->orderBy('order')->orderBy('name')->get(['id', 'name', 'icon', 'color']),
                    $tenantId,
                )->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'ids' => [(int) $c->id],
                    'nome' => $c->name,
                    'artigos' => (int) $c->services_count,
                ])->values()
                : [],

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

            // O cartão de um artigo sem imagem (ou com a imagem em falta)
            // mostrava o logótipo esbatido, como no balcão de sempre.
            'logotipo' => app_logo(),

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
                'pode_vender' => self::podeVenderAoBalcao($request->user(), $modulo),
                'pode_criar_cliente' => ClientApiController::podeCriarCliente($request->user()),
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
     *
     * AOS BOCADOS, e todos. Vinham só os primeiros 50 — numa farmácia com 259
     * ampolas, a grelha parava a meio da letra A e o resto não se via sem
     * procurar. Agora vem uma página de cada vez (`pagina`), e o `meta.mais`
     * diz ao ecrã se há mais para pedir quando se chega ao fundo.
     */
    public const ARTIGOS_POR_PAGINA = 60;

    public function artigos(Request $request): JsonResponse
    {
        $tenantId = (int) activeTenantId();

        abort_unless($tenantId, 403, __('Sem empresa activa.'));

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            // Um id, ou vários separados por vírgula (a mesma categoria
            // gravada duas vezes — ver categoriasDoBalcao).
            'categoria' => ['nullable', 'string', 'regex:/^\d+(,\d+){0,49}$/'],
            'armazem' => ['nullable', 'integer'],
            'modulo' => ['nullable', 'string', 'in:salon'],
            'tipo' => ['nullable', 'string', 'in:servicos,produtos'],
            'pagina' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        if (($filtros['tipo'] ?? null) === 'servicos' && $this->moduloDoBalcao($request, $tenantId) === 'salon') {
            return $this->servicosDoSalao($tenantId, $filtros);
        }

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

        $q = Product::with('taxRate')->where('invoicing_products.tenant_id', $tenantId)
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
                    // O código do artigo também: na farmácia importada é o
                    // código de barras, e há artigos só com ele.
                    ->orWhere('invoicing_products.code', 'like', "%{$procura}%")
                    ->orWhere('invoicing_products.sku', 'like', "%{$procura}%")
                    ->orWhere('invoicing_products.barcode', 'like', "%{$procura}%")
                    ->orWhere('invoicing_products.active_ingredient', 'like', "%{$procura}%")
                    ->orWhere('invoicing_products.size', 'like', "%{$procura}%");
            });
        }

        if ($categoria = ($filtros['categoria'] ?? null)) {
            $q->whereIn('invoicing_products.category_id', array_map('intval', explode(',', (string) $categoria)));
        }

        [$artigos, $meta] = $this->umaPagina(
            // O id desempata: dois artigos com o mesmo nome não podem trocar de
            // página entre um pedido e o seguinte (um repetia, o outro sumia).
            $q->orderBy('invoicing_products.name')->orderBy('invoicing_products.id'),
            (int) ($filtros['pagina'] ?? 1)
        );

        return response()->json([
            'meta' => $meta,
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
                    // O caminho gravado («products/x.jpg») não é um endereço: o browser
                    // pedia-o relativo à página e a imagem saía partida. Ver enderecoDaImagem.
                    'imagem' => $this->enderecoDaImagem($p->featured_image),
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

                    /*
                     * A TAXA DE IMPOSTO DO ARTIGO.
                     *
                     * O balcão precisa dela para mostrar a linha do IVA e o
                     * total COM imposto — como o POS em Livewire sempre fez, e
                     * como a migração para React perdeu em 07/09/2026: o ecrã
                     * passou a mostrar a base com o rótulo «A pagar», e o
                     * operador dizia ao cliente um valor que a factura não ia
                     * ter (o imposto é somado POR CIMA do preço).
                     *
                     * Sai do `TaxResolver`, que é a MESMA fonte que o servidor
                     * usa a emitir: o ecrã não calcula taxa nenhuma por sua
                     * conta, só mostra a que lhe dizem. Quem manda continua a
                     * ser o servidor no momento da emissão.
                     */
                    'taxa' => $this->taxaDoArtigo($p),
                ];
            })->values(),
        ]);
    }

    /**
     * A taxa que esta linha vai levar, em percentagem. Zero quer dizer isento
     * — e é isso que o balcão mostra, por extenso, em vez de se calar.
     */
    private function taxaDoArtigo(?\App\Models\Product $p): float
    {
        return round((float) (\App\Services\Invoicing\TaxResolver::forProduct($p)['rate'] ?? 0), 2);
    }

    /**
     * UMA PÁGINA DA GRELHA — e se há mais depois dela.
     *
     * Pede-se um a mais do que cabe: se vier, há página seguinte. Assim não se
     * conta a lista inteira (com a subconsulta do stock por armazém) a cada
     * bocado que se pede.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array{pagina: int, por_pagina: int, mais: bool}}
     */
    private function umaPagina($consulta, int $pagina): array
    {
        $pagina = max(1, $pagina);
        $linhas = $consulta->offset(($pagina - 1) * self::ARTIGOS_POR_PAGINA)->limit(self::ARTIGOS_POR_PAGINA + 1)->get();
        $mais = $linhas->count() > self::ARTIGOS_POR_PAGINA;

        return [
            $linhas->take(self::ARTIGOS_POR_PAGINA)->values(),
            ['pagina' => $pagina, 'por_pagina' => self::ARTIGOS_POR_PAGINA, 'mais' => $mais],
        ];
    }

    /**
     * OS SERVIÇOS DO SALÃO, no formato da grelha.
     *
     * Um serviço não tem stock nem código de barras; a categoria vem do JSON
     * (`daCategoria`), porque a coluna `category_id` está sempre a nulo nos
     * serviços do salão.
     */
    private function servicosDoSalao(int $tenantId, array $filtros): JsonResponse
    {
        $q = \App\Models\Salon\Service::with('taxRate')->where('tenant_id', $tenantId)->where('is_active', true);

        if ($procura = ($filtros['procura'] ?? null)) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$procura}%")->orWhere('sku', 'like', "%{$procura}%"));
        }

        if ($categoria = ($filtros['categoria'] ?? null)) {
            $q->daCategoria((int) explode(',', (string) $categoria)[0]);
        }

        [$servicos, $meta] = $this->umaPagina($q->orderBy('name')->orderBy('id'), (int) ($filtros['pagina'] ?? 1));

        return response()->json([
            'meta' => $meta,
            'data' => $servicos->map(fn ($s) => [
                'id' => (int) $s->id,
                'nome' => $s->name,
                'codigo' => $s->sku,
                'codigo_de_barras' => null,
                'unidade' => $s->unit ?: 'UN',
                'servico' => true,
                'preco' => round((float) $s->price, 2),
                'pergunta_preco' => (bool) $s->preco_no_pos,
                'imagem' => $this->enderecoDaImagem($s->featured_image),
                'stock' => null,
                'categoria_id' => $s->category_id ? (int) $s->category_id : null,
                'duracao' => (int) $s->duration,
                'receita' => false,
                'controlado' => false,
                // O serviço do salão é um artigo do catálogo (ver o módulo do
                // salão): a taxa resolve-se pela mesma porta que a dos outros.
                'taxa' => $this->taxaDoArtigo($s),
            ])->values(),
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

        $encontrados = Product::with('taxRate')->where('tenant_id', $tenantId)
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
                // O caminho gravado («products/x.jpg») não é um endereço: o browser
                // pedia-o relativo à página e a imagem saía partida. Ver enderecoDaImagem.
                'imagem' => $this->enderecoDaImagem($p->featured_image),
                'stock' => $disponivel === null ? null : round($disponivel, 3),
                'categoria_id' => $p->category_id ? (int) $p->category_id : null,
                'receita' => (bool) $p->requires_prescription,
                'controlado' => (bool) $p->is_controlled,
                // O artigo lido pelo leitor entra no carrinho por aqui: sem a
                // taxa, a linha do IVA saltava-o e o total ficava a menos.
                'taxa' => $this->taxaDoArtigo($p),
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
     * O CLIENTE RÁPIDO DO BALCÃO.
     *
     * O ecrã React chamava a porta dos catálogos com `clientes` — um catálogo
     * que não existe — e a criação dava 404 a toda a gente, Super Admin
     * incluído (auditoria de 2026-09-13). A ficha de clientes também não
     * serve: exige NIF, e ao balcão o cliente ou o dá ou não o dá.
     *
     * São as regras do balcão em Livewire: nome obrigatório, NIF opcional; um
     * NIF que já está na empresa devolve esse cliente em vez de o duplicar; e
     * os NIF genéricos (999999999…) não identificam ninguém — ficam nulos.
     */
    public function criarCliente(Request $request): JsonResponse
    {
        $tenantId = (int) activeTenantId();

        abort_unless($tenantId, 403, __('Sem empresa activa.'));
        abort_unless(
            ClientApiController::podeCriarCliente($request->user()),
            403,
            __('Não tem permissão para criar clientes.'),
        );

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'nif' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
        ], [], ['name' => __('nome'), 'nif' => 'NIF', 'phone' => __('telefone'), 'email' => __('email')]);

        $nif = preg_replace('/\s+/', '', (string) ($dados['nif'] ?? ''));

        if (in_array($nif, ['999999999', '999999998', '000000000'], true)) {
            $nif = '';
        }

        $resposta = fn (Client $c, bool $existente) => response()->json([
            'data' => [
                'id' => (int) $c->id,
                'nome' => $c->name,
                'nif' => $c->nif,
                'telefone' => $c->phone,
                'email' => $c->email,
            ],
            'existente' => $existente,
            'message' => $existente
                ? __('Cliente já existente seleccionado: :cliente', ['cliente' => $c->name])
                : __('Cliente criado e seleccionado: :cliente', ['cliente' => $c->name]),
        ], $existente ? 200 : 201);

        if ($nif !== '') {
            $existente = Client::where('tenant_id', $tenantId)->where('nif', $nif)->first();

            if ($existente) {
                return $resposta($existente, true);
            }
        }

        $cliente = Client::create([
            'tenant_id' => $tenantId,
            'type' => 'pessoa_fisica',
            'name' => trim($dados['name']),
            'nif' => $nif !== '' ? $nif : null,
            'phone' => $dados['phone'] ?? null,
            'email' => $dados['email'] ?? null,
            'country' => \App\Support\Geografia::PAIS_PADRAO,
            'is_active' => true,
            'is_iva_subject' => true,
        ]);

        return $resposta($cliente, false);
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
        $modulo = $this->moduloDoBalcao($request, $tenantId);

        abort_unless(
            self::podeVenderAoBalcao($request->user(), $modulo),
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
            // Percentagem (0–100); o desconto por valor vem em Kz no discount_value.
            'discount_commercial' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
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
            // No balcão do salão, os serviços do salão são o que se vende.
            ->when($modulo, fn ($q) => $q->where('module', '!=', $modulo))
            ->get(['name', 'module']);

        if ($deModulo->isNotEmpty()) {
            $primeiro = $deModulo->first();

            return response()->json([
                'message' => __(':artigo vende-se no POS do módulo :modulo.', [
                    'artigo' => $primeiro->name, 'modulo' => $primeiro->module,
                ]),
            ], 422);
        }

        /*
         * O PREÇO É O DO CATÁLOGO — salvo para quem pode mudar preços.
         *
         * O ecrã só deixa escrever o preço num artigo «preço no POS», e diz
         * `pode_mudar_preco`; mas a porta aceitava qualquer `unit_price`: um
         * caixa sem essa permissão emitia uma Fatura-Recibo assinada a 1 Kz e o
         * stock saía inteiro (auditoria de segurança de 2026-09-13). Uma linha
         * sem artigo do catálogo também é um preço inventado.
         */
        if (! $request->user()?->can('invoicing.products.edit')) {
            $precos = Product::where('tenant_id', $tenantId)
                ->whereIn('id', collect($dados['items'])->pluck('product_id')->filter()->all())
                ->get(['id', 'name', 'price', 'preco_no_pos'])
                ->keyBy('id');

            foreach ($dados['items'] as $linha) {
                $artigo = ! empty($linha['product_id']) ? $precos->get((int) $linha['product_id']) : null;

                if (! $artigo) {
                    return response()->json(['message' => __('Só pode vender artigos do catálogo: «:linha» não é um deles.', ['linha' => $linha['product_name']])], 422);
                }

                if (! $artigo->preco_no_pos && abs((float) $linha['unit_price'] - (float) $artigo->price) > 0.005) {
                    return response()->json([
                        'message' => __('O preço de :artigo é :preco. Mudar o preço ao balcão pede permissão.', [
                            'artigo' => $artigo->name,
                            'preco' => number_format((float) $artigo->price, 2, ',', '.'),
                        ]),
                    ], 422);
                }
            }
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

                // As moradas do papel e do PDF — ver `papeisDaLinha()`.
                ...$this->papeisDaLinha($d),
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
     * AS MORADAS DO PAPEL de uma linha do mapa: onde se imprime e onde está o PDF.
     *
     * A NOTA DE CRÉDITO FICAVA SEM PAPEL NENHUM. Na migração para React só a
     * factura recebeu moradas, e a linha de uma devolução ficou sem botão —
     * quando o ecrã em Livewire a ligava ao PDF da nota. Quem conferia o mapa
     * ao fim do dia via a devolução e não tinha como abrir o documento que a
     * justifica.
     *
     * Uma nota não tem talão: sai sempre em A4, e por isso as duas moradas do
     * papel apontam para a mesma pré-visualização. Assim o ecrã não precisa de
     * saber que tipo de documento tem à frente — imprime «no papel da casa» e
     * a nota responde com o único que tem.
     *
     * O mapa só junta facturas e notas de crédito (o `PosSalesReportQuery` não
     * lê notas de débito). Se uma terceira espécie entrar na união, é uma
     * linha a mais no `match` — e até lá sai sem papel em vez de com o errado.
     *
     * @param  object  $d  A linha do mapa
     * @return array{papeis: array{talao: string, a4: string}|null, pdf: string|null}
     */
    private function papeisDaLinha($d): array
    {
        $id = (int) $d->doc_id;

        return match ($d->doc_tipo) {
            PosSalesReportQuery::TIPO_FACTURA => [
                'papeis' => [
                    'talao' => "/invoicing/sales/invoices/{$id}/talao",
                    'a4' => "/invoicing/sales/invoices/{$id}/preview",
                ],
                'pdf' => "/invoicing/sales/invoices/{$id}/pdf",
            ],
            PosSalesReportQuery::TIPO_NOTA => [
                'papeis' => [
                    'talao' => "/invoicing/credit-notes/{$id}/preview",
                    'a4' => "/invoicing/credit-notes/{$id}/preview",
                ],
                'pdf' => "/invoicing/credit-notes/{$id}/pdf",
            ],
            default => ['papeis' => null, 'pdf' => null],
        };
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
