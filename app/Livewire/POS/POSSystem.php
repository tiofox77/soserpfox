<?php

namespace App\Livewire\POS;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Product;
use App\Support\CodigoDeBarras;
use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\PosShift;
use App\Models\Category;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\PaymentMethod as TreasuryPaymentMethod;
use App\Models\Treasury\CashRegister;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use Illuminate\Support\Facades\DB;
use Darryldecode\Cart\Facades\CartFacade as Cart;

#[Layout('layouts.app')]
// O título do separador fica em português: um atributo PHP só aceita expressões
// constantes, e __() é uma chamada de função. É o mesmo que o lote 1 fez nas
// facturas — muda-se em todo o lado ao mesmo tempo ou em lado nenhum.
#[Title('POS - Ponto de Venda')]
class POSSystem extends Component
{
    public $currentShift = null;
    public $warehouseId = null;   // Armazém default do tenant (única fonte de stock no POS)
    public $warehouseName = '';   // Nome a mostrar na UI
    public $search = '';
    public $selectedCategory = null;
    public $selectedClient = null;
    public $searchClient = '';
    public $showClientModal = false;

    // Criação rápida de cliente no POS
    public $showQuickClientModal = false;
    public $quickClientName = '';
    public $quickClientNif = '';
    public $quickClientPhone = '';
    public $quickClientEmail = '';
    public $showPaymentModal = false;
    public $showPrintModal = false;
    // Factura do talão. Preenchida ao concluir a venda e LIBERTADA ao fechar o
    // modal de impressão (closePrintModal), que é o que faltava antes.
    //
    // Continua a ser propriedade pública de propósito: removê-la fazia rebentar
    // com PublicPropertyNotFoundException as caixas que tivessem a página aberta
    // durante uma actualização — o navegador continua a enviar o snapshot antigo,
    // que a inclui, e o Livewire recusa-se a hidratar uma propriedade que já não
    // existe. Numa farmácia isso é o POS a parar a meio do atendimento.
    //
    // O peso a sério era o `lastInvoiceQR` (imagem QR em base64, ~44 KB) que
    // NENHUMA vista usava — o modal gera o seu próprio QR. Esse foi removido:
    // depois de uma venda cada clique passava de 32 ms/15 queries/1 KB para
    // 140 ms/27 queries/45 KB, e assim ficava até recarregar a página.
    public $lastInvoice = null;

    /**
     * COMPATIBILIDADE — não usar. Nada no sistema escreve nesta propriedade.
     *
     * Existe só para as sessões abertas ANTES desta actualização poderem
     * continuar: o snapshot que o navegador guardou inclui `lastInvoiceQR`, e o
     * Livewire recusa-se a hidratar um snapshot com uma propriedade que já não
     * existe (PublicPropertyNotFoundException, HTTP 500). Sem isto, uma caixa
     * com a página aberta durante o deploy ficava parada a meio de um
     * atendimento até alguém a recarregar.
     *
     * Guardava a imagem do QR em base64 (~44 KB) e viajava em todos os pedidos
     * seguintes à primeira venda, apesar de nenhuma vista a usar — o modal de
     * impressão gera o seu próprio QR. Pode ser removida quando não restarem
     * sessões antigas (com segurança, numa janela sem atendimento).
     */
    public $lastInvoiceQR = null;
    
    // Pagamento
    public $paymentMethod = 'cash';
    public $amountReceived = 0;
    public $notes = '';
    public $discount = 0;
    public $discountType = 'percentage'; // percentage ou fixed
    public $paymentMethods = []; // Métodos de pagamento do Treasury

    // Formas de pagamento repartidas (multi-tender). Vazio = uma forma só
    // (o $paymentMethod). Cada entrada: {method, amount, reference}.
    public $payments = [];
    public $multiPagamento = false;
    
    // Calculados
    public $cartItems = [];
    public $cartTotal = 0;
    public $cartSubtotal = 0;
    public $cartTax = 0;
    public $cartIrt = 0;
    public $cartDiscount = 0;
    public $cartQuantity = 0;
    public $taxRate = 14;
    public string $taxLabel = '14%';
    public $irtRate = 6.5;
    public $change = 0;
    
    // Quick amounts (trocos rápidos)
    public $quickAmounts = [1000, 2000, 5000, 10000, 20000, 50000, 100000];

    public function mount()
    {
        // Verificar se há turno aberto
        $this->currentShift = PosShift::where('tenant_id', activeTenantId())
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->first();
        
        // Se não houver turno aberto, redirecionar para abrir turno
        if (!$this->currentShift) {
            session()->flash('warning', '⚠️ ' . __('Você precisa abrir um turno antes de usar o POS!'));
            return redirect()->route('invoicing.pos.shifts');
        }

        // Armazém DEFAULT do tenant — single source of truth no POS
        $defaultWh = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        if ($defaultWh) {
            $this->warehouseId = $defaultWh->id;
            $this->warehouseName = $defaultWh->name;
        } else {
            // Fallback — tenant sem armazém default; criar/encontrar primeiro activo
            $any = Warehouse::where('tenant_id', activeTenantId())->where('is_active', true)->first();
            if ($any) {
                $this->warehouseId = $any->id;
                // O nome do armazém é dado da empresa; só a nota entre parêntesis
                // é nossa — daí o placeholder em vez de uma concatenação.
                $this->warehouseName = __(':armazem (sem default)', ['armazem' => $any->name]);
            } else {
                $this->warehouseName = __('— sem armazém configurado —');
            }
        }
        
        // Definir cliente padrão "Consumidor Final".
        // O OR TEM de estar agrupado: sem a closure, o `orWhere` quebrava o filtro
        // de tenant e o POS acabava a faturar para o Consumidor Final de OUTRA
        // empresa (fuga entre empresas — 372 faturas afetadas em produção).
        $this->selectedClient = Client::where('tenant_id', activeTenantId())
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%Consumidor Final%')
                  ->orWhere('nif', '999999999');
            })
            ->first();
        
        // Se não existir, criar
        if (!$this->selectedClient) {
            $this->selectedClient = Client::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Consumidor Final',
                'nif' => '999999999',
                'email' => 'consumidorfinal@pos.local',
                'phone' => '999999999',
                'address' => 'N/A',
                'is_active' => true,
            ]);
        }
        
        // Carregar métodos de pagamento do Treasury.
        // Por sort_order, não por nome: ordenados alfabeticamente o "Cheque"
        // ficava em primeiro e, como o valor inicial ('cash') não corresponde a
        // nenhum código (os códigos são MAIÚSCULAS: CASH, TPA, ...), o <select>
        // abria em Cheque. O sort_order já traz a ordem de uso real —
        // Dinheiro, Multicaixa, TPA, Transferência, Cheque.
        $this->paymentMethods = TreasuryPaymentMethod::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderByRaw('COALESCE(sort_order, 9999)')
            ->orderBy('name')
            ->get();

        // Selecção inicial: o código REAL do primeiro método, para o que está
        // seleccionado corresponder ao que se vê.
        $this->paymentMethod = $this->metodoPagamentoPorOmissao();
        
        // Carregar carrinho
        $this->loadCart();
    }

    public function loadCart()
    {
        $cart = Cart::session($this->cartKey());
        $content = $cart->getContent();

        // O carrinho vive na sessão e pode atravessar alterações de preço,
        // imposto ou regime fiscal. Uma linha antiga conservava, por exemplo,
        // preço 1.799,98 e IVA 0%, mesmo quando a ficha já dizia 1.800,00 e 14%.
        // Antes de qualquer total, sincronizar sempre os dados CANÓNICOS.
        $productIds = $content->keys()->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->values();
        $products = Product::where('tenant_id', activeTenantId())->whereIn('id', $productIds)->get()->keyBy('id');
        foreach ($content as $id => $item) {
            if (!is_numeric($id) || !($product = $products->get((int) $id))) continue;
            $attrs = \App\Helpers\InvoiceCalculationHelper::atributos($item);
            $tax = \App\Services\Invoicing\TaxResolver::forProduct($product, activeTenantId());
            $canonicalPrice = round((float) $product->price, 2);
            $canonicalAttrs = array_merge($attrs, [
                'image' => $product->image,
                'sku' => $product->sku,
                'unit' => $product->unit ?: 'UN',
                'tax_rate' => $tax['rate'],
                'tax_type' => $tax['type'],
                'exemption_reason' => $tax['exemption_code'],
            ]);
            if (round((float) $item->price, 2) !== $canonicalPrice
                || (float) ($attrs['tax_rate'] ?? -1) !== (float) $tax['rate']
                || ($attrs['tax_type'] ?? null) !== $tax['type']) {
                $cart->update($id, ['price' => $canonicalPrice, 'attributes' => $canonicalAttrs]);
            }
        }
        $content = $cart->getContent();

        // Ordem estável de inserção — a lib darryldecode/cart reordena (remove e
        // re-insere no fim) ao fazer update(), fazendo o item "saltar" para o final.
        // Mantemos um mapa de sequência por id na sessão para preservar a ordem.
        $orderKey = 'pos_cart_order_' . auth()->id();
        $order = session($orderKey, []);
        $changed = false;
        $next = empty($order) ? 0 : (max($order) + 1);
        foreach ($content as $id => $item) {
            if (!isset($order[$id])) { $order[$id] = $next++; $changed = true; }
        }
        foreach (array_keys($order) as $id) {
            if (!$content->has($id)) { unset($order[$id]); $changed = true; }
        }
        if ($changed) { session([$orderKey => $order]); }

        $this->cartItems = $content->sortBy(fn($item) => $order[$item->id] ?? PHP_INT_MAX)->values();
        // Soma monetária linha a linha. Evita que frações ocultas se acumulem
        // enquanto a interface mostra cada linha arredondada.
        $this->cartSubtotal = round($content->sum(fn ($item) => round((float) $item->price * (float) $item->quantity, 2)), 2);
        $this->cartQuantity = $cart->getTotalQuantity();
        
        // Obter configurações de impostos
        $settings = InvoicingSettings::forTenant(activeTenantId());
        // Sem hardcode de 14: em regime de isenção a taxa por omissão é 0.
        $defaultTaxRate = $settings->default_tax_rate
            ?? (\App\Services\Invoicing\TaxResolver::defaultTax(activeTenantId())->rate ?? 0);
        $defaultIrtRate = $settings->default_irt_rate ?? 6.5;
        $applyIrtServices = $settings->apply_irt_services ?? true;
        
        // Guardar taxas para exibição
        $this->taxRate = $defaultTaxRate;
        $rates = $content->map(fn ($item) => (float) (\App\Helpers\InvoiceCalculationHelper::atributos($item)['tax_rate'] ?? 0))
            ->unique()->sort()->values();
        $this->taxLabel = $rates->count() === 1
            ? number_format((float) $rates->first(), 0).'%'
            : ($rates->isEmpty() ? '0%' : 'misto');
        $this->irtRate = $defaultIrtRate;
        
        // Garantir que desconto seja numérico
        $discount = is_numeric($this->discount) ? floatval($this->discount) : 0;
        
        // Calcular desconto
        if ($this->discountType === 'percentage') {
            $this->cartDiscount = ($this->cartSubtotal * $discount) / 100;
        } else {
            $this->cartDiscount = $discount;
        }
        
        // Cálculo CANÓNICO — exatamente o mesmo helper que completeSale() usa para
        // gravar a fatura. Antes o ecrã calculava o IVA sobre o subtotal SEM
        // desconto, pelo que o total mostrado (e o troco) não batiam com o
        // documento emitido.
        $calcItems = collect($this->cartItems)->map(function ($item) use ($defaultTaxRate) {
            $attrs = \App\Helpers\InvoiceCalculationHelper::atributos($item);
            return (object) [
                'price'      => $item->price,
                'quantity'   => $item->quantity,
                'attributes' => [
                    'discount_percent' => $attrs['discount_percent'] ?? 0,
                    'tax_rate'         => $attrs['tax_rate'] ?? $defaultTaxRate,
                ],
            ];
        });

        $calc = \App\Helpers\InvoiceCalculationHelper::calculateTotals(
            $calcItems,
            $this->cartDiscount,   // desconto comercial
            0,
            0,
            false
        );

        $this->cartTax   = $calc['tax_amount'];
        $this->cartTotal = $calc['total'];

        // IRT (retenção em serviços) — calculado sobre a base já com desconto
        $this->cartIrt = 0;
        if ($applyIrtServices) {
            foreach ($this->cartItems as $item) {
                $attrs = \App\Helpers\InvoiceCalculationHelper::atributos($item);
                $isService = ($attrs['type'] ?? null) === 'service';
                if ($isService) {
                    $base = $item->price * $item->quantity;
                    if ($this->cartSubtotal > 0) {
                        $base -= $this->cartDiscount * ($base / $this->cartSubtotal); // desconto pró-rata
                    }
                    $this->cartIrt += $base * ($defaultIrtRate / 100);
                }
            }
            $this->cartIrt = round($this->cartIrt, 2);
            $this->cartTotal = round($this->cartTotal - $this->cartIrt, 2);
        }

        $this->calculateChange();

        // Rede de segurança: espelhar o carrinho no cliente (localStorage). Se a sessão
        // expirar por inatividade, o carrinho (que vive na sessão do servidor) desaparece;
        // o cliente guarda esta cópia e restaura-a após novo login (ver scripts do POS).
        $this->dispatch('pos-cart-sync',
            key: 'pos_cart_' . auth()->id() . '_' . ($this->currentShift->id ?? 0),
            count: $this->cartItems->count(),
            items: $this->cartItems->map(fn($i) => [
                'id' => $i->id,
                'quantity' => (float) $i->quantity,
            ])->values()->toArray(),
        );
    }

    /**
     * Restaura o carrinho a partir da cópia guardada no cliente (localStorage) após a
     * sessão ter expirado e o utilizador voltar a iniciar sessão. Reutiliza addToCart +
     * updateQuantity para revalidar stock/lotes e recalcular preços no servidor — nunca
     * confia em preços vindos do cliente.
     */
    public function restoreCartFromClient($items)
    {
        if (!is_array($items) || empty($items)) {
            return;
        }
        $restored = 0;
        foreach ($items as $it) {
            $pid = $it['id'] ?? null;
            $qty = isset($it['quantity']) ? (float) $it['quantity'] : 0;
            if (!$pid || $qty < 1) {
                continue;
            }
            $product = Product::where('id', $pid)
                ->where('tenant_id', activeTenantId())
                ->first();
            if (!$product) {
                continue;
            }
            // Adicionar 1 unidade (valida stock/lote); depois fixar a quantidade guardada.
            //
            // O controlado entra já confirmado: quem o pôs no carrinho antes da
            // sessão expirar já respondeu à pergunta. Voltar a perguntá-la num
            // restauro automático seria perguntar sem o artigo à frente — e uma
            // resposta negativa por distração deixava-o de fora da venda
            // recuperada, silenciosamente.
            if (!Cart::session($this->cartKey())->get($pid)) {
                $this->addToCart($pid, true);
            }
            if (Cart::session($this->cartKey())->get($pid)) {
                $this->updateQuantity($pid, $qty);   // fixa qty absoluta, auto-limitada ao stock
                $restored++;
            }
        }
        if ($restored > 0) {
            // "item(s) restaurado(s)" não existe em inglês nem em francês: cada
            // forma é uma frase inteira, escolhida pelo trans_choice.
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '🛒 ' . trans_choice(
                    'Venda recuperada — :n item restaurado no carrinho.|Venda recuperada — :n itens restaurados no carrinho.',
                    $restored,
                    ['n' => $restored]
                ),
            ]);
        }
    }
    
    public function updatedDiscount()
    {
        // Normalizar desconto (se vazio ou inválido, definir como 0)
        if (!is_numeric($this->discount) || $this->discount === '' || $this->discount === null) {
            $this->discount = 0;
        }
        
        $this->loadCart();
    }
    
    public function updatedDiscountType()
    {
        $this->loadCart();
    }
    
    public function setQuickAmount($amount)
    {
        $this->amountReceived = $amount;
        $this->calculateChange();
    }

    /**
     * Chave do carrinho: utilizador E empresa.
     *
     * Era só o utilizador. Quem tem mais de uma empresa — e há quem tenha —
     * trocava de empresa e levava o carrinho atrás: artigos escolhidos numa
     * ficavam lá para serem facturados na outra. O addToCart valida a empresa
     * ao ENTRAR, mas o fecho da venda percorre o carrinho e cria as linhas sem
     * voltar a verificar, por isso o artigo alheio passava.
     *
     * Com a empresa na chave, cada uma tem o seu carrinho e o outro fica
     * intacto onde estava.
     */
    protected function cartKey(): string
    {
        return auth()->id() . '_t' . (activeTenantId() ?: 0);
    }

    /**
     * Stock disponível do produto NO ARMAZÉM DEFAULT do tenant.
     * Usa invoicing_stocks quando há linha; caso contrário, fallback para
     * product.stock_quantity (tenants que não usam multi-armazém).
     */
    protected function stockInWarehouse(Product $product): float
    {
        if (!$this->warehouseId) {
            return (float) ($product->stock_quantity ?? 0);
        }
        $row = Stock::where('tenant_id', activeTenantId())
            ->where('warehouse_id', $this->warehouseId)
            ->where('product_id', $product->id)
            ->first();
        if ($row) {
            return (float) $row->quantity;
        }
        // Multi-arm: se o produto já tem linhas noutros armazéns, NUNCA usar o
        // fallback agregado — o stock real neste armazém é 0. O agregado
        // (stock_quantity) só é válido para tenants legados sem multi-armazém.
        $hasAnyStockRow = Stock::where('tenant_id', activeTenantId())
            ->where('product_id', $product->id)
            ->exists();
        if ($hasAnyStockRow) {
            return 0.0;
        }
        return (float) ($product->stock_quantity ?? 0);
    }

    /**
     * Leitura de código de barras: o artigo vai directo para o carrinho.
     *
     * O leitor escreve o código no campo de procura e carrega em Enter. Até
     * aqui isso só filtrava a grelha — e como a grelha esconde o que está sem
     * stock (1415 de 5729 artigos visíveis numa das farmácias), passar o leitor
     * por um artigo esgotado devolvia um ecrã vazio, indistinguível de "este
     * código não existe". O operador concluía que a leitura não funcionava.
     *
     * Só dispara com correspondência EXACTA do código de barras e a partir de
     * seis caracteres: escrever o nome de um artigo nunca dá um código exacto,
     * por isso quem procura à mão não é interrompido.
     */
    public function updatedSearch(): void
    {
        $codigo = trim((string) $this->search);

        if (mb_strlen($codigo) < 6) {
            return;
        }

        // O mesmo artigo pode estar guardado com o envelope GS1 à frente ou
        // só com o EAN-13 de dentro, conforme o sistema de onde veio o
        // catálogo; e o leitor tanto manda um como o outro. Procura-se por
        // todas as formas equivalentes, senão o POS diz "não existe" com o
        // produto na mão do operador.
        $formas = CodigoDeBarras::formas($codigo);

        $encontrados = Product::where('tenant_id', activeTenantId())
            ->whereIn('barcode', $formas)
            ->get();

        // Correspondência exacta manda; a equivalência é o plano B.
        $produto = $encontrados->firstWhere('barcode', $codigo) ?? $encontrados->first();

        if (!$produto) {
            return;   // não é um código conhecido: fica a servir de filtro
        }

        $this->search = '';

        if (!$produto->is_active) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ ' . __(':artigo está inactivo e não pode ser vendido.', ['artigo' => $produto->name]),
            ]);

            return;
        }

        // O addToCart trata do resto e é ele que avisa quando não há stock —
        // que é a informação que faltava.
        $this->addToCart($produto->id);
    }

    /**
     * A pesquisa que levou a escolher um artigo.
     *
     * Registada AQUI e não em cada tecla premida, por duas razões que se
     * anulariam uma à outra se fosse ao contrário: gravar por tecla escreveria
     * "ami", "amid" e "amido" como três pesquisas — e o painel dos mais
     * pesquisados mostraria prefixos em vez de nomes —, e obrigaria a uma
     * consulta por tecla no ecrã mais usado do sistema.
     *
     * Assim é um registo por pesquisa a sério, e com a informação que mais vale:
     * o termo que levou alguém a escolher alguma coisa.
     */
    private function registarPesquisaQueLevouAEscolha(): void
    {
        $termo = trim((string) $this->search);

        if ($termo === '') {
            return;
        }

        \App\Services\Analytics\RegistoDeVisita::pesquisa($termo, 'pos');
    }

    /**
     * @param bool $controladoConfirmado Resposta do operador à confirmação de
     *                                   psicotrópico/estupefaciente. Só o ecrã
     *                                   (ou um restauro de carrinho já
     *                                   confirmado) a liga.
     */
    public function addToCart($productId, $controladoConfirmado = false)
    {
        // Na segunda passagem — o operador confirmou o psicotrópico e o ecrã
        // repetiu a chamada — não se volta a registar: é a mesma escolha, não
        // uma pesquisa nova, e sairia a contar em dobro no painel dos artigos
        // mais procurados.
        if (!$controladoConfirmado) {
            $this->registarPesquisaQueLevouAEscolha();
        }

        // Scope ao tenant: $productId vem do browser. Sem o filtro, um produto
        // de OUTRA empresa passava — o stockInWarehouse() não encontrava linhas
        // (filtra por tenant), caía no agregado stock_quantity da empresa alheia
        // e, sendo > 0, o produto entrava no carrinho e saía na factura desta.
        $product = Product::where('tenant_id', activeTenantId())->find($productId);

        // Inactivo não se vende. A grelha já o esconde, mas o id vem do browser
        // e este método é o que grava — a grelha filtra, não protege.
        if ($product && !$product->is_active) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ ' . __(':artigo está inactivo e não pode ser vendido.', ['artigo' => $product->name]),
            ]);

            return;
        }

        if (!$product) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ ' . __('Produto não encontrado!')
            ]);
            return;
        }

        // Artigo de um módulo de negócio: vende-se no POS desse módulo, não
        // aqui. A grelha já não o mostra, mas o id vem do browser — a grelha
        // filtra, não protege.
        if (filled($product->module)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ ' . __(':artigo vende-se no POS do módulo :modulo.', [
                    'artigo' => $product->name,
                    'modulo' => $product->module,
                ]),
            ]);
            return;
        }

        // Artigos que não controlam stock (serviços e produtos com "Gerenciar
        // Stock" desligado) vendem-se sempre — sem validação de disponibilidade
        // e sem lotes. Entram directos no carrinho.
        $controlaStock = $product->controlaStock();

        $available = $controlaStock ? $this->stockInWarehouse($product) : 0;

        if ($controlaStock && $available <= 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ ' . __('Produto sem stock no armazém :armazem!', ['armazem' => $this->warehouseName])
            ]);
            return;
        }

        // Verificar se já existe no carrinho
        $cartItem = Cart::session($this->cartKey())->get($productId);
        $currentQuantity = $cartItem ? $cartItem->quantity : 0;
        $newQuantity = $currentQuantity + 1;

        // Se produto rastreia lotes, validar disponibilidade nos lotes
        if ($controlaStock && $product->track_batches) {
            $availableBatches = \App\Models\Invoicing\ProductBatch::where('tenant_id', activeTenantId())
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->get();
            
            $totalAvailable = $availableBatches->sum('quantity_available');
            
            if ($availableBatches->isEmpty()) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '❌ ' . __('Produto exige lote mas não há lotes disponíveis!')
                ]);
                return;
            }
            
            // Verificar lotes expirados
            $expiredBatches = $availableBatches->filter(fn($b) => $b->is_expired);
            if ($expiredBatches->isNotEmpty()) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '❌ ' . __('Lotes expirados encontrados!')
                ]);
                return;
            }
            
            if ($newQuantity > $totalAvailable) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => '⚠️ ' . __('Quantidade excede lotes disponíveis! Disponível: :quantidade un', ['quantidade' => $totalAvailable])
                ]);
                return;
            }
        }
        // Validar stock disponível NO ARMAZÉM (só se o artigo controla stock).
        elseif ($controlaStock && $newQuantity > $available) {
            $this->dispatch('stock-error');
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => '⚠️ ' . __('Stock insuficiente em :armazem! Disponível: :quantidade un', [
                    'armazem'    => $this->warehouseName,
                    'quantidade' => $available,
                ])
            ]);
            return;
        }

        // Psicotrópico / estupefaciente: confirmar ANTES de entrar no carrinho.
        //
        // Não é rigor contabilístico. Estes artigos têm registo obrigatório e
        // vendê-los por engano tem consequência legal para a farmácia — e
        // quando a venda fecha o artigo já saiu do balcão, não há como desfazer.
        //
        // A pergunta é feita aqui, depois de validado o stock, para não se
        // confirmar uma venda que a seguir falharia por falta de existências.
        // A resposta volta pelo mesmo método com o sinal ligado.
        if ($product->is_controlled && !$controladoConfirmado) {
            $this->dispatch(
                'pos-confirmar-controlado',
                productId: $product->id,
                // O texto viaja já traduzido: a caixa de confirmação é do
                // navegador e não tem por onde traduzir o que lhe entregam.
                message: __(':artigo é um medicamento controlado (psicotrópico ou estupefaciente), de registo obrigatório. Confirma a venda?', [
                    'artigo' => $product->name,
                ]),
            );

            return;
        }

        // Imposto resolvido pela fonte ÚNICA (regime do tenant + produto).
        // O antigo `?? 14` hardcoded fazia empresas em regime de isenção
        // emitirem faturas com IVA 14%.
        $tx = \App\Services\Invoicing\TaxResolver::forProduct($product, activeTenantId());

        Cart::session($this->cartKey())->add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'attributes' => [
                'image' => $product->image,
                'sku' => $product->sku,
                'unit' => $product->unit ?? 'UN',
                'tax_rate' => $tx['rate'],
                'tax_type' => $tx['type'],
                'exemption_reason' => $tx['exemption_code'],
                'discount_percent' => 0,
            ]
        ]);

        $this->loadCart();
        
        // Disparar evento para tocar som
        $this->dispatch('item-added');

        // Receita médica: o aviso SUBSTITUI a confirmação normal, não se soma
        // a ela. O toast do sistema faz toastr.remove() a cada aviso novo, por
        // isso de dois seguidos só se vê o último — e o último tem de ser este.
        //
        // Avisa, não trava: o operador pode ter a receita na mão, e travar a
        // venda deixaria a farmácia sem forma de a fazer.
        if ($product->requires_prescription) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => '⚠️ ' . __('Adicionado — :artigo exige RECEITA MÉDICA. Confirme a receita antes de entregar.', [
                    'artigo' => $product->name,
                ])
            ]);

            return;
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ ' . __('Produto adicionado! (:quantidade/:disponivel un em :armazem)', [
                'quantidade' => $newQuantity,
                'disponivel' => $available,
                'armazem'    => $this->warehouseName,
            ])
        ]);
    }

    public function updateQuantity($itemId, $quantity)
    {
        // Entrada vazia/inválida → reverter para o valor atual sem alterar
        if ($quantity === '' || $quantity === null || !is_numeric($quantity)) {
            $this->loadCart();
            return;
        }

        $quantity = (float) $quantity;

        if ($quantity <= 0) {
            $this->removeFromCart($itemId);
            return;
        }

        $product = Product::where("tenant_id", activeTenantId())->find($itemId);
        
        if (!$product) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ ' . __('Produto não encontrado!')
            ]);
            return;
        }
        
        // Se produto rastreia lotes, validar nos lotes
        if ($product->track_batches) {
            $availableBatches = \App\Models\Invoicing\ProductBatch::where('tenant_id', activeTenantId())
                ->where('product_id', $itemId)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->get();
            
            $totalAvailable = $availableBatches->sum('quantity_available');
            
            if ($quantity > $totalAvailable) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => '⚠️ ' . __('Quantidade excede lotes! Disponível: :quantidade un. Ajustando...', ['quantidade' => $totalAvailable])
                ]);
                $quantity = $totalAvailable;
            }
        }
        // Validar stock NO ARMAZÉM
        else {
            $available = $this->stockInWarehouse($product);
            if ($quantity > $available) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => '⚠️ ' . __('Excede stock em :armazem! Disponível: :quantidade un. Ajustando...', [
                        'armazem'    => $this->warehouseName,
                        'quantidade' => $available,
                    ])
                ]);
                $quantity = $available;
            }
        }

        Cart::session($this->cartKey())->update($itemId, [
            'quantity' => [
                'relative' => false,
                'value' => $quantity
            ]
        ]);

        $this->loadCart();
    }

    public function increaseQuantity($itemId)
    {
        $cartItem = Cart::session($this->cartKey())->get($itemId);
        
        if (!$cartItem) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ ' . __('Item não encontrado no carrinho!')
            ]);
            return;
        }
        
        $newQuantity = $cartItem->quantity + 1;
        $isService = isset($cartItem->attributes['type']) && $cartItem->attributes['type'] === 'service';
        
        // Só validar stock quando o artigo o controla. Serviços e produtos com
        // "Gerenciar Stock" desligado sobem de quantidade sem limite.
        if (!$isService) {
            $product = Product::where("tenant_id", activeTenantId())->find($itemId);

            if (!$product) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '❌ ' . __('Produto não encontrado!')
                ]);
                return;
            }

            $available = $this->stockInWarehouse($product);
            if ($product->controlaStock() && $newQuantity > $available) {
                $this->dispatch('stock-error');
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => '⚠️ ' . __('Stock máximo atingido em :armazem! Disponível: :quantidade un', [
                        'armazem'    => $this->warehouseName,
                        'quantidade' => $available,
                    ])
                ]);
                return;
            }
        }
        
        Cart::session($this->cartKey())->update($itemId, [
            'quantity' => 1
        ]);
        $this->loadCart();
        
        $this->dispatch('cart-updated', ['action' => 'add']);
        
        // O ramo do serviço não tem texto nenhum para traduzir — é o nome do
        // artigo (dado do cliente) e a quantidade. Fica como está.
        $message = $isService
            ? '📈 ' . $cartItem->name . ' (' . $newQuantity . 'x)'
            : '📈 ' . __('Quantidade: :quantidade un (:armazem)', [
                'quantidade' => $newQuantity,
                'armazem'    => $this->warehouseName,
            ]);
            
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => $message
        ]);
    }

    public function decreaseQuantity($itemId)
    {
        $item = Cart::session($this->cartKey())->get($itemId);
        if ($item->quantity > 1) {
            Cart::session($this->cartKey())->update($itemId, [
                'quantity' => -1
            ]);
            $this->loadCart();
            
            // Disparar evento para tocar som
            $this->dispatch('cart-updated', ['action' => 'remove']);
        } else {
            $this->removeFromCart($itemId);
        }
    }

    public function removeFromCart($itemId)
    {
        Cart::session($this->cartKey())->remove($itemId);
        $this->loadCart();
        
        // Disparar evento para tocar som
        $this->dispatch('item-removed');
        
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => __('Produto removido do carrinho')
        ]);
    }

    public function clearCart()
    {
        Cart::session($this->cartKey())->clear();
        session()->forget('pos_cart_order_' . auth()->id());
        $this->loadCart();
        
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => __('Carrinho limpo')
        ]);
    }

    public function selectClient($clientId)
    {
        // Scoped ao tenant activo: qualquer método público Livewire é invocável
        // pelo cliente, logo um id arbitrário não pode selecionar cliente de
        // outra empresa (IDOR).
        $client = Client::where('tenant_id', activeTenantId())->find($clientId);
        if (!$client) {
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Cliente inválido.')]);
            return;
        }
        $this->selectedClient = $client;
        $this->showClientModal = false;
        $this->searchClient = '';
    }

    /**
     * Abre o modal de criação rápida de cliente. Pré-preenche o nome
     * com o texto de pesquisa actual, se houver.
     */
    public function openQuickClientModal()
    {
        $term = trim((string) $this->searchClient);
        $this->quickClientName  = $term && !ctype_digit($term) ? $term : '';
        $this->quickClientNif   = $term && ctype_digit($term) ? $term : '';
        $this->quickClientPhone = '';
        $this->quickClientEmail = '';
        $this->resetErrorBag();
        $this->showClientModal = false;
        $this->showQuickClientModal = true;
    }

    public function closeQuickClientModal()
    {
        $this->showQuickClientModal = false;
    }

    /**
     * Cria um cliente rápido (apenas campos essenciais) e seleciona-o automaticamente.
     */
    public function quickCreateClient()
    {
        $this->validate([
            'quickClientName'  => 'required|string|max:255',
            'quickClientNif'   => 'nullable|string|max:20',
            'quickClientPhone' => 'nullable|string|max:30',
            'quickClientEmail' => 'nullable|email|max:255',
        ], [], [
            // Os nomes dos campos entram nas mensagens de validação ("o campo
            // nome é obrigatório"), por isso seguem a língua do utilizador.
            // NIF é sigla angolana e fica como está.
            'quickClientName'  => __('nome'),
            'quickClientNif'   => 'NIF',
            'quickClientPhone' => __('telefone'),
            'quickClientEmail' => __('email'),
        ]);

        $tenantId = activeTenantId();
        if (!$tenantId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Tenant inválido.')]);
            return;
        }

        // Evitar duplicar se NIF já existir neste tenant
        if (!empty($this->quickClientNif)) {
            $existing = Client::where('tenant_id', $tenantId)
                ->where('nif', $this->quickClientNif)
                ->first();
            if ($existing) {
                $this->selectedClient = $existing;
                $this->showQuickClientModal = false;
                $this->dispatch('notify', [
                    'type' => 'info',
                    'message' => '✓ ' . __('Cliente já existente seleccionado: :cliente', ['cliente' => $existing->name]),
                ]);
                return;
            }
        }

        $client = Client::create([
            'tenant_id'      => $tenantId,
            'type'           => 'pessoa_fisica',
            'name'           => $this->quickClientName,
            'nif'            => $this->quickClientNif ?: null,
            'phone'          => $this->quickClientPhone ?: null,
            'email'          => $this->quickClientEmail ?: null,
            'country'        => 'Angola',
            'is_active'      => true,
            'is_iva_subject' => true,
        ]);

        $this->selectedClient = $client;
        $this->showQuickClientModal = false;
        $this->searchClient = '';
        $this->reset(['quickClientName', 'quickClientNif', 'quickClientPhone', 'quickClientEmail']);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✓ ' . __('Cliente criado e seleccionado: :cliente', ['cliente' => $client->name]),
        ]);
    }

    public function updatedAmountReceived()
    {
        // Normalizar valor recebido (se vazio ou inválido, definir como 0)
        if (!is_numeric($this->amountReceived) || $this->amountReceived === '' || $this->amountReceived === null) {
            $this->amountReceived = 0;
        }
        
        $this->calculateChange();
    }

    public function calculateChange()
    {
        // Garantir que amountReceived seja numérico
        $amountReceived = is_numeric($this->amountReceived) ? floatval($this->amountReceived) : 0;
        $this->change = $amountReceived - $this->cartTotal;
    }

    // ── Multi-pagamento ──────────────────────────────────────────────
    public function toggleMultiPagamento(): void
    {
        $this->multiPagamento = !$this->multiPagamento;
        if ($this->multiPagamento && empty($this->payments)) {
            // Arranca com uma linha no método actual e o total em falta.
            $this->payments = [[
                'method' => $this->paymentMethod,
                'amount' => round((float) $this->cartTotal, 2),
                'reference' => '',
            ]];
        }
        if (!$this->multiPagamento) {
            $this->payments = [];
        }
    }

    public function addPayment(): void
    {
        $this->payments[] = [
            'method' => $this->paymentMethod,
            'amount' => round(max(0, (float) $this->cartTotal - $this->pagamentosTotal()), 2),
            'reference' => '',
        ];
    }

    public function removePayment($i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments);
    }

    public function pagamentosTotal(): float
    {
        return round(array_sum(array_map(fn ($p) => (float) ($p['amount'] ?? 0), $this->payments)), 2);
    }

    public function faltaPagar(): float
    {
        return round((float) $this->cartTotal - $this->pagamentosTotal(), 2);
    }

    /**
     * As formas de pagamento a gravar. Sem multi-pagamento é uma só, pelo
     * total, no método escolhido — o comportamento de sempre.
     */
    private function tendersActuais(float $total): array
    {
        if (!$this->multiPagamento || empty($this->payments)) {
            return [['method' => $this->paymentMethod, 'amount' => round($total, 2), 'reference' => null]];
        }

        $tenders = [];
        foreach ($this->payments as $p) {
            $valor = round((float) ($p['amount'] ?? 0), 2);
            if ($valor <= 0) {
                continue;
            }
            $tenders[] = [
                'method'    => (string) ($p['method'] ?? $this->paymentMethod),
                'amount'    => $valor,
                'reference' => ($p['reference'] ?? '') !== '' ? (string) $p['reference'] : null,
            ];
        }

        return $tenders ?: [['method' => $this->paymentMethod, 'amount' => round($total, 2), 'reference' => null]];
    }

    public function completeSale()
    {
        if ($this->cartItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Carrinho vazio!')
            ]);
            return;
        }

        if (!$this->selectedClient) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Selecione um cliente!')
            ]);
            return;
        }

        if ($this->multiPagamento) {
            // Multi-tender: a soma das formas tem de bater com o total.
            if (abs($this->pagamentosTotal() - (float) $this->cartTotal) > 0.02) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('As formas de pagamento (:pago) não somam o total (:total).', [
                        'pago'  => number_format($this->pagamentosTotal(), 2),
                        'total' => number_format((float) $this->cartTotal, 2),
                    ]),
                ]);
                return;
            }
        } elseif ($this->amountReceived < $this->cartTotal) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Valor recebido insuficiente!')
            ]);
            return;
        }

        DB::beginTransaction();
        try {
            // Converter items para coleção com atributos do carrinho
            $cartItemsForCalc = collect($this->cartItems)->map(function($item) {
                return (object)[
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'attributes' => [
                        'discount_percent' => 0,
                        'tax_rate' => $item->attributes->tax_rate ?? 0,
                    ]
                ];
            });
            
            // Usar helper de cálculos AGT
            $calculations = \App\Helpers\InvoiceCalculationHelper::calculateTotals(
                $cartItemsForCalc,
                $this->cartDiscount, // commercial_discount
                0,  // discountAmount
                0,  // financial_discount
                false // isService
            );

            // Buscar ou criar série padrão POS (FR A)
            // O método getDefaultSeries cria automaticamente se não existir
            $series = InvoicingSeries::getDefaultSeries(activeTenantId(), 'pos');
            
            // Gerar número de fatura no formato AGT: FR A 2025/000001
            $invoiceNumber = $series->getNextNumber();

            // Criar fatura usando tabela existente
            $invoice = SalesInvoice::create([
                'tenant_id'           => activeTenantId(),
                'series_id'           => $series->id,
                'client_id'           => $this->selectedClient->id,
                'warehouse_id'        => $this->warehouseId ?: defaultWarehouseId(),
                'invoice_number'      => $invoiceNumber,
                'invoice_type'        => 'FR',   // Fatura-Recibo (SAFT-AO) — a série é FR
                'invoice_date'        => now(),
                'due_date'            => now(),
                'status'              => 'paid',
                // Totais AGT
                'subtotal'            => $calculations['subtotal'],
                'net_total'           => $calculations['incidencia_iva'],
                'tax_amount'          => $calculations['tax_amount'],
                'tax_payable'         => $calculations['tax_amount'],
                'irt_amount'          => $calculations['irt_amount'],
                // SAFT-AO: GrossTotal = NetTotal + TaxPayable. A retenção (IRT)
                // declara-se no seu próprio campo, NÃO se abate ao grossTotal —
                // gravar aqui o `total` (já líquido de IRT) fazia
                // netTotal + taxPayable ≠ grossTotal e a AGT recusava o
                // documento. O que o cliente paga continua em `total`.
                'gross_total'         => $calculations['incidencia_iva'] + $calculations['tax_amount'],
                'discount_amount'     => $calculations['desconto_comercial_total'],
                'discount_commercial' => $this->cartDiscount,
                'total'               => $calculations['total'],
                'paid_amount'         => $this->amountReceived,
                // Campos SAFT-AO obrigatórios (Decreto 71/25)
                'invoice_status'      => 'F',
                'invoice_status_date' => now(),
                'source_id'           => auth()->id(),
                'source_billing'      => 'P',
                'system_entry_date'   => now(),
                'notes'               => $this->notes,
                'payment_method'      => $this->paymentMethod,
                'created_by'          => auth()->id(),
            ]);

            // Adicionar itens
            $itemOrder = 0;
            foreach ($this->cartItems as $item) {
                $attrs     = $item->attributes;
                $taxRate   = (float) ($attrs->tax_rate ?? 0);
                $taxType   = $attrs->tax_type ?? 'iva';
                $exemption = $attrs->exemption_reason ?? null;
                $unit      = $attrs->unit ?? 'UN';
                $subtotal  = round($item->price * $item->quantity, 2);
                $taxAmount = round($subtotal * ($taxRate / 100), 2);

                // Verificar se é serviço (ID começa com 'service_')
                $isService = str_starts_with((string) $item->id, 'service_');
                // Extrair ID numérico: 'service_21' -> 21, ou ID do produto
                $productId = $isService ? (int) str_replace('service_', '', $item->id) : $item->id;

                // O artigo é MESMO desta empresa?
                //
                // O addToCart valida a empresa ao entrar, mas quem valida não é
                // quem grava: este ciclo cria as linhas a partir do carrinho, e
                // o carrinho sobrevive à sessão. Um carrinho começado noutra
                // empresa — há quem tenha duas — saía facturado nesta, com os
                // artigos de lá. A chave do carrinho passou a incluir a
                // empresa; isto é a segunda tranca, no sítio que grava.
                if (!$isService && !Product::where('tenant_id', activeTenantId())->whereKey($productId)->exists()) {
                    \Log::warning('POS: artigo de outra empresa recusado no fecho da venda', [
                        'tenant_id'  => activeTenantId(),
                        'product_id' => $productId,
                        'artigo'     => $item->name,
                    ]);

                    // Esta mensagem chega ao ecrã (o catch mostra-a), por isso é
                    // texto de interface e não só de registo.
                    throw new \DomainException(
                        __('O artigo ":artigo" não pertence a esta empresa. Limpe o carrinho e volte a adicioná-lo.', [
                            'artigo' => $item->name,
                        ])
                    );
                }

                // Códigos SAFT-AO por linha
                $taxCode    = ($taxType === 'isento' || $taxRate == 0) ? 'ISE' : 'NOR';
                $saftType   = ($taxType === 'isento' || $taxRate == 0) ? 'ISE' : 'NOR';

                SalesInvoiceItem::create([
                    'sales_invoice_id'     => $invoice->id,
                    'product_id'           => $productId,
                    'product_name'         => $item->name,
                    'description'          => $isService ? '[SERVIÇO] ' . $item->name : $item->name,
                    'quantity'             => $item->quantity,
                    'unit'                 => $unit,
                    'unit_price'           => $item->price,
                    'discount_percent'     => (float) ($attrs->discount_percent ?? 0),
                    'discount_amount'      => 0,
                    'subtotal'             => $subtotal,
                    'tax_rate'             => $taxRate,
                    'tax_amount'           => $taxAmount,
                    'total'                => $subtotal + $taxAmount,
                    'order'                => ++$itemOrder,
                    // Campos AGT DS.120
                    'tax_code'             => $taxCode,
                    'tax_country_region'   => 'AO',
                    // Código (varchar 10) vs descrição (varchar 255) — normalizar
                    // sempre: o produto pode ter a descrição gravada onde devia
                    // estar o código, e isso rebentava o INSERT ("Data too long").
                    'tax_exemption_code'   => $taxType === 'isento' ? Product::normalizeExemptionCode($exemption) : null,
                    'tax_exemption_reason' => $taxType === 'isento' ? Product::exemptionReasonText($exemption) : null,
                ]);

                // Atualizar stock apenas para produtos
                if (!$isService) {
                    $product = Product::where("tenant_id", activeTenantId())->find($item->id);
                    if ($product) {
                        // Deduzir do armazém (linha Stock) se existir; senão, fallback agregado.
                        // NUNCA escrever o agregado quando há linha: o StockObserver mantém
                        // products.stock_quantity = SUM(invoicing_stocks) no save() da linha.
                        // (A escrita manual antiga sobrepunha o valor do observer com um
                        // cálculo em memória, perpetuando divergências agregado/armazéns.)
                        // Regra única (BaixaDeStock): desconta a quantidade toda,
                        // mesmo que o armazém fique negativo. O travão em zero
                        // que aqui estava fazia o stock descer menos do que o
                        // movimento registava.
                        \App\Services\Invoicing\BaixaDeStock::aplicar(
                            (int) activeTenantId(),
                            $this->warehouseId ? (int) $this->warehouseId : null,
                            $product,
                            (float) $item->quantity
                        );

                        // Ledger: registar o movimento da venda. semAplicarStock para o hook
                        // created do StockMovement não voltar a debitar (o stock já foi
                        // atualizado acima) — mesma técnica do WarehouseTransfer.
                        if ($this->warehouseId) {
                            StockMovement::semAplicarStock(function () use ($invoice, $product, $item) {
                                StockMovement::create([
                                    'tenant_id'      => activeTenantId(),
                                    'warehouse_id'   => $this->warehouseId,
                                    'product_id'     => $product->id,
                                    'type'           => 'out',
                                    'quantity'       => (float) $item->quantity,
                                    'unit_cost'      => $product->cost,
                                    'reference_type' => SalesInvoice::class,
                                    'reference_id'   => $invoice->id,
                                    'user_id'        => auth()->id(),
                                    'notes'          => 'Venda POS - ' . $invoice->invoice_number,
                                ]);
                            });
                        }
                    }
                }
            }

            // ---- Formas de pagamento (multi-tender) ----
            // Cada parte (numerário, multicaixa, …) fica registada por si, e
            // gera a sua linha de tesouraria e de turno. O bucketing do turno
            // por método passa a ficar certo num pagamento misto.
            $tenders = $this->tendersActuais((float) $invoice->total);

            if (count($tenders) > 1) {
                $invoice->payment_method = 'multiple';
                $invoice->save();
            }

            if ($this->currentShift) {
                $this->currentShift->refresh();
            }

            foreach ($tenders as $tender) {
                \App\Models\Invoicing\SalePayment::create([
                    'sales_invoice_id'  => $invoice->id,
                    'tenant_id'         => activeTenantId(),
                    'payment_method'    => $tender['method'],
                    'payment_method_id' => TreasuryPaymentMethod::where('tenant_id', activeTenantId())
                        ->whereRaw('LOWER(code) = ?', [strtolower($tender['method'])])->value('id'),
                    'amount'            => $tender['amount'],
                    'reference'         => $tender['reference'],
                    'user_id'           => auth()->id(),
                ]);

                // Tesouraria: uma linha por tender.
                $this->createTreasuryTransaction($invoice, $tender['method'], (float) $tender['amount']);

                // Turno: uma transacção por tender.
                if ($this->currentShift) {
                    try {
                        $this->currentShift->addTransaction([
                            'type' => 'invoice',
                            'reference_type' => 'App\\Models\\Invoicing\\SalesInvoice',
                            'reference_id' => $invoice->id,
                            'reference_number' => $invoice->invoice_number,
                            'payment_method' => $tender['method'],
                            'amount' => $tender['amount'],
                            'description' => 'Venda POS - ' . $invoice->invoice_number,
                            'metadata' => [
                                'client_id' => $this->selectedClient->id,
                                'client_name' => $this->selectedClient->name,
                                'items_count' => $this->cartItems->count(),
                                'amount_received' => $this->amountReceived,
                                'change' => $this->change,
                            ],
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('ERRO ao registrar venda no turno', [
                            'shift_id' => $this->currentShift->id,
                            'invoice_id' => $invoice->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if (!$this->currentShift) {
                \Log::warning('Venda realizada sem turno aberto', [
                    'invoice_number' => $invoice->invoice_number,
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();

            // Gerar hash SAFT-AO (encadear com fatura anterior — Decreto 71/25)
            try {
                $invoice->generateHash();
            } catch (\Throwable $e) {
                \Log::error('POS: Erro ao gerar hash SAFT', [
                    'invoice' => $invoice->invoice_number,
                    'error'   => $e->getMessage(),
                ]);
            }

            // Auto-submeter à AGT se habilitado nas configurações
            try {
                $settings = \App\Models\Invoicing\InvoicingSettings::forTenant(activeTenantId());
                if (!empty($settings->agt_auto_submit)) {
                    // fresh(): este $invoice já teve a colecção `items` lida
                    // pelo observer que corre na criação — quando ainda não
                    // havia linhas nenhumas. O Eloquent guarda essa colecção
                    // vazia e nunca mais a consulta, e o documento seguia para
                    // a AGT com os totais preenchidos e ZERO linhas.
                    $agtResult = $invoice->fresh()->submitToAGT();
                    \Log::info('POS: Fatura submetida à AGT', [
                        'invoice'   => $invoice->invoice_number,
                        'requestID' => $agtResult['requestID'] ?? null,
                        'success'   => $agtResult['success'] ?? false,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::error('POS: Erro ao submeter para AGT', [
                    'invoice' => $invoice->invoice_number,
                    'error'   => $e->getMessage(),
                ]);
                // Não bloqueia a venda — AGT pode ser re-submetida manualmente
            }

            // Guardar factura para o talão. É libertada em closePrintModal().
            $this->lastInvoice = $invoice->fresh()->load(['client', 'items.product', 'tenant']);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => '✅ ' . __('Venda concluída! Fatura: :numero | Troco: :troco Kz', [
                    'numero' => $invoice->invoice_number,
                    'troco'  => number_format($this->change, 2),
                ])
            ]);

            // Limpar carrinho e resetar
            $this->showPaymentModal = false;
            $this->clearCart();
            
            // Manter cliente como Consumidor Final
            $this->selectedClient = Client::where('tenant_id', activeTenantId())
                ->where('nif', '999999999')
                ->first();
            
            $this->amountReceived = 0;
            $this->notes = '';
            $this->change = 0;
            $this->discount = 0;
            $this->payments = [];
            $this->multiPagamento = false;

            // Abrir modal de impressão
            $this->showPrintModal = true;

        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('Erro ao concluir venda POS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao concluir venda: :erro', ['erro' => $e->getMessage()])
            ]);
        }
    }

    public function openPaymentModal()
    {
        if ($this->cartItems->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => '❌ ' . __('Carrinho vazio! Adicione produtos primeiro.')
            ]);
            return;
        }
        
        if (!$this->selectedClient) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => '⚠️ ' . __('Selecione um cliente primeiro!')
            ]);
            return;
        }
        
        $this->showPaymentModal = true;
        $this->amountReceived = $this->cartTotal;
        $this->calculateChange();
    }
    
    /**
     * Código do método de pagamento a mostrar por omissão.
     *
     * Manda a definição da empresa (Faturação › Definições › "Método de
     * Pagamento Padrão no POS"). Ignorá-la e usar sempre o primeiro por
     * sort_order fazia com que configurar Transferência não tivesse efeito
     * nenhum — o POS continuava a abrir em Dinheiro e parecia que a definição
     * não gravava.
     *
     * Só se não houver definição (ou apontar para um método já desactivado) é
     * que cai no primeiro da lista.
     */
    private function metodoPagamentoPorOmissao(): string
    {
        $metodos = collect($this->paymentMethods);

        $configurado = InvoicingSettings::forTenant(activeTenantId())->pos_default_payment_method_id;

        if ($configurado) {
            $metodo = $metodos->firstWhere('id', (int) $configurado);
            if ($metodo) {
                return $metodo->code;
            }
        }

        return $metodos->first()->code ?? 'cash';
    }

    private function createTreasuryTransaction($invoice, ?string $paymentMethod = null, ?float $amount = null)
    {
        // Cada forma de pagamento (tender) é uma linha própria. Sem argumentos
        // é o comportamento antigo: uma linha pelo total, no método escolhido.
        $paymentMethod = $paymentMethod ?? $this->paymentMethod;
        $amount = $amount ?? (float) $invoice->total;

        // Buscar payment method do Treasury (case-insensitive pelo código)
        $code = strtolower((string) $paymentMethod);
        $treasuryPaymentMethod = TreasuryPaymentMethod::where('tenant_id', activeTenantId())
            ->whereRaw('LOWER(code) = ?', [$code])
            ->first();

        // Categoria: derivar do TIPO do método quando existir; senão, mapa por código
        $typeToCategory = [
            'cash' => 'cash', 'card' => 'card', 'bank_transfer' => 'bank_transfer',
            'digital_wallet' => 'digital_payment', 'check' => 'bank_transfer',
        ];
        $codeToCategory = [
            'cash' => 'cash', 'transfer' => 'bank_transfer', 'multicaixa' => 'card',
            'mcx' => 'card', 'tpa' => 'card', 'card' => 'card',
            'mbway' => 'digital_payment', 'mobile' => 'digital_payment',
        ];
        $category = $treasuryPaymentMethod
            ? ($typeToCategory[$treasuryPaymentMethod->type] ?? 'cash')
            : ($codeToCategory[$code] ?? 'cash');

        $destination = $treasuryPaymentMethod
            ? app(\App\Services\Treasury\TreasuryMovementService::class)->destination($treasuryPaymentMethod, activeTenantId(), userId: auth()->id())
            : ['account_id' => null, 'cash_register_id' => null];

        // Criar transação de entrada (receita)
        app(\App\Services\Treasury\TreasuryMovementService::class)->post([
            'tenant_id' => activeTenantId(),
            'user_id' => auth()->id(),
            'account_id' => $destination['account_id'],
            'cash_register_id' => $destination['cash_register_id'],
            'payment_method_id' => $treasuryPaymentMethod?->id,
            'invoice_id' => $invoice->id,
            'transaction_number' => 'TRX-' . strtoupper(uniqid()),
            'type' => 'income',
            'category' => $category,
            'amount' => $amount,
            'currency' => 'AOA',
            'transaction_date' => now(),
            'reference' => $invoice->invoice_number,
            'description' => 'Venda POS - Fatura: ' . $invoice->invoice_number . ' - Cliente: ' . $invoice->client->name,
            'notes' => $this->notes,
            'status' => 'completed',
            'is_reconciled' => false,
        ]);

    }

    /**
     * Fecha o talão e LIBERTA a factura do estado do componente.
     *
     * É o que faltava: sem isto a factura (e antes também o QR de 44 KB) ficava
     * no estado até recarregar a página, e cada clique seguinte arrastava-a.
     */
    public function closePrintModal(): void
    {
        $this->showPrintModal = false;
        $this->lastInvoice = null;
    }

    public function getNextInvoiceNumberProperty(): string
    {
        $series = InvoicingSeries::getDefaultSeries(activeTenantId(), 'pos');
        return $series ? $series->previewNextNumber() : '—';
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $whId = $this->warehouseId;

        // Produtos do armazém DEFAULT: stock = invoicing_stocks.quantity (do armazém)
        // ou, se não houver linha, fallback para products.stock_quantity (legado).
        // O alias `stock_in_warehouse` é o stock visível no POS — usado no card.
        // Sub-select: produto tem QUALQUER linha em invoicing_stocks (qualquer armazém)?
        // Se sim, esse produto já está no regime multi-armazém e o agregado legado
        // (invoicing_products.stock_quantity) deixa de ser de confiança.
        $hasStockRowSql = '(SELECT 1 FROM invoicing_stocks ss WHERE ss.tenant_id = ? AND ss.product_id = invoicing_products.id LIMIT 1)';
        $stockExpr = "COALESCE(invoicing_stocks.quantity, CASE WHEN EXISTS{$hasStockRowSql} THEN 0 ELSE COALESCE(invoicing_products.stock_quantity, 0) END)";

        $productsQuery = Product::where('invoicing_products.tenant_id', $tenantId)
            ->where('invoicing_products.is_active', true)
            // Artigos de um MÓDULO de negócio (hoje: salão) não se vendem aqui.
            // Um "Corte de Cabelo" existe em invoicing_products só para a linha
            // da factura ter artigo de catálogo — quem o marca e cobra é o POS
            // do salão, que sabe do profissional, da duração e da marcação.
            // Ao balcão da facturação era um artigo solto, sem nada disso.
            ->whereNull('invoicing_products.module')
            ->leftJoin('invoicing_stocks', function ($join) use ($whId, $tenantId) {
                $join->on('invoicing_stocks.product_id', '=', 'invoicing_products.id')
                     ->where('invoicing_stocks.tenant_id', $tenantId)
                     ->where('invoicing_stocks.warehouse_id', $whId);
            })
            ->selectRaw("invoicing_products.*, {$stockExpr} AS stock_in_warehouse", [$tenantId]);

        // Ocultar produtos sem stock (não afecta serviços) — controlado em Settings → Faturacão
        $hideOutOfStock = \App\Models\Invoicing\InvoicingSettings::forTenant($tenantId)->pos_hide_out_of_stock ?? true;
        if ($hideOutOfStock) {
            // Nunca esconder serviços NEM produtos que não controlam stock:
            // esses vendem-se sempre, com ou sem stock no armazém.
            $productsQuery->whereRaw(
                "(invoicing_products.type = 'servico' OR invoicing_products.manage_stock = 0 OR {$stockExpr} > 0)",
                [$tenantId]
            );
        }

        if ($this->search) {
            $productsQuery->where(function ($q) {
                $q->where('invoicing_products.name', 'like', '%' . $this->search . '%')
                  ->orWhere('invoicing_products.sku', 'like', '%' . $this->search . '%')
                  ->orWhere('invoicing_products.barcode', 'like', '%' . $this->search . '%')
                  // Numa farmácia pergunta-se pela substância, não pela marca:
                  // quem pede "paracetamol" não sabe se a caixa diz Ben-u-ron.
                  // Numa loja de roupa pergunta-se pelo tamanho — e "38" não
                  // está no nome do artigo nem no código.
                  ->orWhere('invoicing_products.active_ingredient', 'like', '%' . $this->search . '%')
                  ->orWhere('invoicing_products.size', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->selectedCategory) {
            $productsQuery->where('invoicing_products.category_id', $this->selectedCategory);
        }

        $products = $productsQuery->limit(50)->get();

        // Categorias: o withCount() é uma subconsulta por categoria (60 aqui,
        // ~30 ms) e corria a CADA clique no carrinho. Cache de 60 s: num minuto
        // de trabalho passa de dezenas de execuções a uma, e uma categoria nova
        // aparece na mesma quase de imediato.
        $categories = \Illuminate\Support\Facades\Cache::remember(
            "pos_categories_{$tenantId}",
            60,
            fn () => Category::where('tenant_id', $tenantId)->withCount('products')->get()
        );

        // Clientes: só quando o modal de cliente está aberto ou há pesquisa.
        // Antes carregava 10 clientes em cada clique no carrinho, sem serem vistos.
        $clients = collect();
        if ($this->showClientModal || $this->showQuickClientModal || filled($this->searchClient)) {
            $clientsQuery = Client::where('tenant_id', $tenantId)
                ->where('is_active', true);

            if ($this->searchClient) {
                $clientsQuery->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->searchClient . '%')
                      ->orWhere('nif', 'like', '%' . $this->searchClient . '%');
                });
            }

            $clients = $clientsQuery->limit(10)->get();
        }

        return view('livewire.pos.possystem', [
            'products' => $products,
            'categories' => $categories,
            'clients' => $clients,
        ]);
    }
}
