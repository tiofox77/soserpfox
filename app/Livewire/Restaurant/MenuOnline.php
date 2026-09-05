<?php

namespace App\Livewire\Restaurant;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\MenuOrder;
use App\Models\Restaurant\RestaurantSettings;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * A carta do restaurante, vista pelo cliente no telemóvel.
 *
 * É PÚBLICA: quem abre isto não tem sessão, não tem empresa activa e não é
 * utilizador de nada. Toda a leitura tem de dizer explicitamente de que
 * empresa é — os escopos automáticos por tenant não funcionam sem sessão, e
 * confiar neles aqui seria servir a carta de outra casa.
 *
 * DUAS VIAS PARA PEDIR, e o restaurante escolhe qual (ou as duas):
 *
 *   · WHATSAPP — a mensagem sai pronta, com a mesa e os artigos escolhidos.
 *     Não toca na base de dados: é o cliente a mandar uma mensagem ao
 *     restaurante, e alguém de lá é que lança a comanda. Funciona em qualquer
 *     casa, sem configuração nenhuma, e por isso vem ligado.
 *   · PEDIDO NA PÁGINA — cria a comanda directamente. Mais cómodo e mais
 *     arriscado: quem tiver o endereço lança pedidos sem passar por ninguém.
 *     Vem desligado, e liga-se a sério nas definições.
 *
 * O QUE ESTA PÁGINA NÃO FAZ: preços não se negoceiam aqui, não há pagamento,
 * e não se mostra stock. Uma carta que anuncia "resta 1" convida a uma corrida
 * que a cozinha não pediu, e um número desactualizado é pior do que nenhum.
 */
class MenuOnline extends Component
{
    public string $slug = '';

    /** O código da mesa, quando se chega por um QR colado à mesa. */
    public ?string $mesa = null;

    public string $pesquisa = '';
    public ?int $categoriaId = null;

    /** O que o cliente escolheu: [product_id => quantidade]. */
    public array $escolhas = [];

    // Só usados quando o restaurante aceita pedidos pela própria página.
    public string $nome = '';
    public string $telefone = '';
    public string $observacoes = '';
    public bool $pedidoEnviado = false;

    public RestaurantSettings $definicoes;

    private ?DiningTable $mesaResolvida = null;

    public function mount(string $slug, ?string $mesa = null): void
    {
        $definicoes = RestaurantSettings::porSlugPublico($slug);

        // 404 e não 403, mesmo quando o menu existe mas está desligado: a
        // diferença entre "não há" e "está desligado" contava a estranhos que
        // aquele restaurante é cliente do sistema. Não é informação de ninguém.
        abort_unless($definicoes, 404);

        $this->slug = $slug;
        $this->definicoes = $definicoes;
        $this->mesa = $mesa;
    }

    /** A mesa do QR, se existir mesmo nesta casa. */
    public function getMesaDoQrProperty(): ?DiningTable
    {
        if ($this->mesa === null) {
            return null;
        }

        return $this->mesaResolvida ??= DiningTable::withoutGlobalScopes()
            ->where('tenant_id', $this->definicoes->tenant_id)
            ->where('code', $this->mesa)
            ->first();
    }

    /**
     * Como se chama esta mesa, para pôr no ecrã e na mensagem.
     *
     * Uma definição só, e não duas: dizia "Mesa" à frente do nome e as mesas
     * chamam-se "Mesa 3" — saía "Mesa Mesa 3" no cabeçalho e na mensagem de
     * WhatsApp. O nome já é um rótulo escrito por gente; o prefixo só faz
     * falta quando se cai para o código cru.
     */
    public function rotuloDaMesa(): ?string
    {
        if ($mesa = $this->mesaDoQr) {
            return $mesa->name ?: __('Mesa :codigo', ['codigo' => $mesa->code]);
        }

        return $this->mesa ? __('Mesa :codigo', ['codigo' => $this->mesa]) : null;
    }

    public function escolher(int $produtoId): void
    {
        $this->escolhas[$produtoId] = ($this->escolhas[$produtoId] ?? 0) + 1;
    }

    public function retirar(int $produtoId): void
    {
        if (!isset($this->escolhas[$produtoId])) {
            return;
        }

        $this->escolhas[$produtoId]--;

        if ($this->escolhas[$produtoId] <= 0) {
            unset($this->escolhas[$produtoId]);
        }
    }

    public function limpar(): void
    {
        $this->escolhas = [];
    }

    public function getTotalEscolhidoProperty(): float
    {
        if (!$this->escolhas) {
            return 0.0;
        }

        $precos = Product::withoutGlobalScopes()
            ->where('tenant_id', $this->definicoes->tenant_id)
            ->whereIn('id', array_keys($this->escolhas))
            ->pluck('price', 'id');

        $total = 0.0;

        foreach ($this->escolhas as $id => $quantidade) {
            $total += (float) ($precos[$id] ?? 0) * $quantidade;
        }

        return $total;
    }

    /**
     * A mensagem de WhatsApp, pronta a enviar.
     *
     * Leva a MESA em primeiro lugar. É a informação que mais falta nestes
     * pedidos — quem recebe fica com uma lista de pratos e ninguém sabe para
     * onde vão — e é precisamente a que o QR da mesa já sabe.
     */
    public function getLinkDoWhatsappProperty(): ?string
    {
        $numero = preg_replace('/\D+/', '', (string) $this->definicoes->menu_whatsapp_number);

        if (!$numero || !$this->definicoes->menu_whatsapp_enabled) {
            return null;
        }

        $casa = $this->definicoes->menu_title ?: __('Restaurante');
        $linhas = [__('Pedido por :casa', ['casa' => $casa])];

        if ($rotulo = $this->rotuloDaMesa()) {
            $linhas[] = $rotulo;
        }

        $linhas[] = '';

        $artigos = Product::withoutGlobalScopes()
            ->where('tenant_id', $this->definicoes->tenant_id)
            ->whereIn('id', array_keys($this->escolhas))
            ->get(['id', 'name', 'price']);

        foreach ($artigos as $artigo) {
            $quantidade = $this->escolhas[$artigo->id] ?? 0;
            $linhas[] = $this->definicoes->menu_show_prices
                ? sprintf('%dx %s — %s Kz', $quantidade, $artigo->name, number_format((float) $artigo->price, 2, ',', '.'))
                : sprintf('%dx %s', $quantidade, $artigo->name);
        }

        if ($this->definicoes->menu_show_prices && $this->totalEscolhido > 0) {
            $linhas[] = '';
            $linhas[] = __('Total: :valor Kz', ['valor' => number_format($this->totalEscolhido, 2, ',', '.')]);
        }

        return 'https://wa.me/' . $numero . '?text=' . rawurlencode(implode("\n", $linhas));
    }

    /**
     * O cliente faz o pedido pela própria página.
     *
     * NÃO CRIA UMA COMANDA. Cria um PEDIDO à espera, que um empregado aceita
     * no ecrã da sala — e é aí, com o turno dele aberto, que nasce a comanda.
     *
     * A razão é a regra do turno: nada se vende com a caixa fechada, e essa
     * regra vale no POS e no restaurante. Um cliente sentado à mesa não tem
     * turno nenhum. Deixar esta página abrir comandas obrigava a furar a regra
     * — e um furo aberto ao público é o pior sítio para o ter.
     *
     * As guardas, todas necessárias porque isto é uma porta para a rua:
     *
     *   · o restaurante tem de ter ligado os pedidos (vem desligado);
     *   · tem de haver MESA, e reconhecida: um pedido sem destino não serve a
     *     ninguém, e é a única coisa que amarra este pedido a alguém que está
     *     mesmo lá dentro;
     *   · limite de frequência por mesa, senão um endereço público é um
     *     convite a encher a sala de pedidos falsos.
     */
    public function enviarPedido(): void
    {
        if (!$this->definicoes->menu_orders_enabled) {
            $this->addError('pedido', __('Este restaurante não recebe pedidos por aqui.'));

            return;
        }

        $mesa = $this->mesaDoQr;

        if (!$mesa) {
            $this->addError('pedido', __('Faça o pedido a partir do QR da sua mesa, ou envie por WhatsApp.'));

            return;
        }

        if (!$this->escolhas) {
            $this->addError('pedido', __('Escolha alguma coisa primeiro.'));

            return;
        }

        // Um pedido de cada meio minuto por mesa. Chega para quem está a comer
        // e não chega para quem está a brincar.
        $chave = 'menu-pedido:' . $this->definicoes->tenant_id . ':' . $mesa->id;

        if (RateLimiter::tooManyAttempts($chave, 1)) {
            $this->addError('pedido', __('Aguarde um momento antes de enviar outro pedido.'));

            return;
        }

        RateLimiter::hit($chave, 30);

        $artigos = Product::withoutGlobalScopes()
            ->where('tenant_id', $this->definicoes->tenant_id)
            ->whereIn('id', array_keys($this->escolhas))
            ->get(['id', 'name', 'price']);

        $linhas = $artigos->map(fn ($artigo) => [
            'product_id' => $artigo->id,
            'name'       => $artigo->name,
            'quantity'   => $this->escolhas[$artigo->id] ?? 0,
            // O preço fica REGISTADO mas não manda: quando o empregado aceitar,
            // quem decide é o catálogo desse momento. Isto é o que o cliente
            // viu, e serve para se perceber uma discrepância — não para
            // congelar um preço que uma página pública mostrou.
            'price_seen' => (float) $artigo->price,
        ])->values()->all();

        MenuOrder::withoutGlobalScopes()->create([
            'tenant_id'       => $this->definicoes->tenant_id,
            'table_id'        => $mesa->id,
            'table_code'      => $mesa->code,
            'items'           => $linhas,
            'estimated_total' => $this->totalEscolhido,
            'customer_name'   => trim($this->nome) ?: null,
            'customer_phone'  => trim($this->telefone) ?: null,
            'notes'           => trim($this->observacoes) ?: null,
            'status'          => 'pending',
        ]);

        $this->escolhas = [];
        $this->observacoes = '';
        $this->pedidoEnviado = true;
    }

    public function render()
    {
        $tenantId = $this->definicoes->tenant_id;

        $categorias = Category::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderBy('order')
            ->get(['id', 'name']);

        $pratos = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            // A carta é o que se VENDE. Um artigo a zero é matéria-prima ou
            // um registo por acabar — numa carta pública lê-se como oferta.
            ->where('price', '>', 0)
            ->when($this->categoriaId, fn ($q) => $q->where('category_id', $this->categoriaId))
            ->when(trim($this->pesquisa) !== '', function ($q) {
                $termo = '%' . trim($this->pesquisa) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $termo)->orWhere('description', 'like', $termo));
            })
            ->with('category:id,name')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('livewire.restaurant.menu-online', [
            'categorias' => $categorias,
            'pratos'     => $pratos,
            // O que a casa quer vender hoje. Vazio quando ninguém escolheu
            // nada — e nesse caso a carta abre logo pelas categorias.
            'destaques'  => \App\Models\Restaurant\MenuDestaque::paraCarta($tenantId),
        ])->layout('layouts.guest', [
            'title' => $this->definicoes->menu_title ?: __('Menu'),
        ]);
    }
}
