<?php

namespace App\Services\Restaurant;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\MenuDestaque;
use App\Models\Restaurant\MenuOrder;
use App\Models\Restaurant\RestaurantSettings;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A CARTA DO RESTAURANTE, vista pelo cliente no telemóvel.
 *
 * É PÚBLICA: quem abre isto não tem sessão, não tem empresa activa e não é
 * utilizador de nada. Toda a leitura diz explicitamente de que empresa é — os
 * escopos automáticos por tenant não funcionam sem sessão, e confiar neles
 * aqui seria servir a carta de outra casa.
 *
 * DUAS VIAS PARA PEDIR, e o restaurante escolhe qual (ou as duas):
 *
 *   · WHATSAPP — a mensagem sai pronta, com a mesa e os artigos escolhidos.
 *     Não toca na base de dados: alguém do restaurante é que lança a comanda.
 *   · PEDIDO NA PÁGINA — cria um PEDIDO à espera que um empregado aceita na
 *     sala. Vem desligado, e liga-se a sério nas definições.
 *
 * O QUE ESTA PÁGINA NÃO FAZ: preços não se negoceiam, não há pagamento, e não
 * se mostra stock. Um número desactualizado é pior do que nenhum.
 */
class CartaPublica
{
    /** Uma carta maior do que isto já não se lê num telemóvel. */
    private const MAXIMO_DE_PRATOS = 500;

    public function __construct(private readonly RestaurantSettings $d)
    {
    }

    public function mesa(?string $codigo): ?DiningTable
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        return DiningTable::withoutGlobalScopes()
            ->where('tenant_id', $this->d->tenant_id)
            ->where('code', $codigo)
            ->first();
    }

    /**
     * Como se chama esta mesa. Um nome já é um rótulo escrito por gente — as
     * mesas chamam-se «Mesa 3», e pôr «Mesa» à frente dava «Mesa Mesa 3». O
     * prefixo só faz falta quando se cai para o código cru.
     */
    public function rotuloDaMesa(?string $codigo, ?DiningTable $mesa): ?string
    {
        if ($mesa) {
            return $mesa->name ?: __('Mesa :codigo', ['codigo' => $mesa->code]);
        }

        return $codigo ? __('Mesa :codigo', ['codigo' => $codigo]) : null;
    }

    /** Tudo o que a página precisa, de uma vez: a carta viaja no HTML. */
    public function paraAPagina(?string $codigoDaMesa): array
    {
        $d = $this->d;
        $mesa = $this->mesa($codigoDaMesa);
        $precos = (bool) $d->menu_show_prices;
        $logoSoserp = app_logo() ?: asset('images/logo.png');

        $pratos = Product::withoutGlobalScopes()
            ->where('tenant_id', $d->tenant_id)
            ->where('is_active', true)
            // A carta é o que se VENDE. Um artigo a zero é matéria-prima ou um
            // registo por acabar — numa carta pública lê-se como oferta.
            ->where('price', '>', 0)
            ->with('category:id,name')
            ->orderBy('name')
            ->limit(self::MAXIMO_DE_PRATOS)
            ->get();

        $categorias = Category::withoutGlobalScopes()
            ->where('tenant_id', $d->tenant_id)
            ->where('is_active', true)
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderBy('order')
            ->get(['id', 'name']);

        $prato = fn (Product $p) => [
            'id' => $p->id,
            'nome' => $p->name,
            'descricao' => $p->description,
            // Com os preços escondidos, o preço nem chega ao browser.
            'preco' => $precos ? (float) $p->price : null,
            'imagem' => $p->image_url,
            'categoria_id' => $p->category_id,
            'categoria' => $p->category?->name,
        ];

        return [
            'casa' => [
                'titulo' => $d->menu_title,
                'descricao' => $d->menu_description,
                'logo' => self::comoUrl($d->menu_logo),
                'logo_soserp' => $logoSoserp,
                'capa' => self::comoUrl($d->menu_cover),
                'cor' => $d->menu_primary_color ?: '#ea580c',
                'acento' => $d->menu_accent_color ?: ($d->menu_primary_color ?: '#ea580c'),
                'escuro' => ($d->menu_theme ?? 'claro') === 'escuro',
                'mostra_precos' => $precos,
                'aceita_pedidos' => (bool) $d->menu_orders_enabled,
                'whatsapp' => $this->numeroDoWhatsapp(),
                'destaques_titulo' => $d->menu_destaques_titulo,
            ],
            'mesa' => $codigoDaMesa ? [
                'codigo' => $codigoDaMesa,
                'rotulo' => $this->rotuloDaMesa($codigoDaMesa, $mesa),
                'reconhecida' => $mesa !== null,
            ] : null,
            'categorias' => $categorias->map(fn ($c) => ['id' => $c->id, 'nome' => $c->name])->values()->all(),
            'pratos' => $pratos->map($prato)->values()->all(),
            // O que a casa quer vender hoje.
            'destaques' => MenuDestaque::paraCarta($d->tenant_id)
                ->filter(fn ($x) => $x->produto)
                ->map(fn ($x) => $prato($x->produto))
                ->values()->all(),
        ];
    }

    /** Só com o WhatsApp ligado E um número: um botão que não leva a lado nenhum não aparece. */
    public function numeroDoWhatsapp(): ?string
    {
        $numero = preg_replace('/\D+/', '', (string) $this->d->menu_whatsapp_number);

        return ($numero && $this->d->menu_whatsapp_enabled) ? $numero : null;
    }

    /**
     * O CLIENTE PEDE PELA PRÓPRIA PÁGINA.
     *
     * NÃO CRIA UMA COMANDA. Cria um PEDIDO à espera, que um empregado aceita no
     * ecrã da sala — e é aí, com o turno dele aberto, que nasce a comanda. Nada
     * se vende com a caixa fechada, e um cliente sentado à mesa não tem turno.
     *
     * As guardas, todas necessárias porque isto é uma porta para a rua: os
     * pedidos ligados, uma MESA reconhecida (a única coisa que amarra o pedido a
     * alguém que está lá dentro), e um pedido por mesa a cada meio minuto.
     *
     * @param  array<int,int>  $escolhas  [product_id => quantidade]
     */
    public function enviarPedido(?string $codigoDaMesa, array $escolhas, ?string $nome, ?string $telefone, ?string $observacoes): MenuOrder
    {
        $recusa = fn (string $m) => ValidationException::withMessages(['pedido' => $m]);

        if (! $this->d->menu_orders_enabled) {
            throw $recusa(__('Este restaurante não recebe pedidos por aqui.'));
        }

        $mesa = $this->mesa($codigoDaMesa);
        if (! $mesa) {
            throw $recusa(__('Faça o pedido a partir do QR da sua mesa, ou envie por WhatsApp.'));
        }

        $escolhas = array_filter($escolhas, fn ($q) => $q > 0);
        if (! $escolhas) {
            throw $recusa(__('Escolha alguma coisa primeiro.'));
        }

        $chave = 'menu-pedido:'.$this->d->tenant_id.':'.$mesa->id;
        if (RateLimiter::tooManyAttempts($chave, 1)) {
            throw $recusa(__('Aguarde um momento antes de enviar outro pedido.'));
        }
        RateLimiter::hit($chave, 30);

        // Só artigos DESTA casa, à venda — um id de outra empresa escrito à mão
        // não entra no pedido.
        $artigos = Product::withoutGlobalScopes()
            ->where('tenant_id', $this->d->tenant_id)
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->whereIn('id', array_keys($escolhas))
            ->get(['id', 'name', 'price']);

        if ($artigos->isEmpty()) {
            throw $recusa(__('Escolha alguma coisa primeiro.'));
        }

        $linhas = $artigos->map(fn ($a) => [
            'product_id' => $a->id,
            'name' => $a->name,
            'quantity' => (int) $escolhas[$a->id],
            // O preço fica REGISTADO mas não manda: quando o empregado aceitar,
            // quem decide é o catálogo desse momento.
            'price_seen' => (float) $a->price,
        ])->values()->all();

        return MenuOrder::withoutGlobalScopes()->create([
            'tenant_id' => $this->d->tenant_id,
            'table_id' => $mesa->id,
            'table_code' => $mesa->code,
            'items' => $linhas,
            'estimated_total' => collect($linhas)->sum(fn ($l) => $l['price_seen'] * $l['quantity']),
            'customer_name' => trim((string) $nome) ?: null,
            'customer_phone' => trim((string) $telefone) ?: null,
            'notes' => trim((string) $observacoes) ?: null,
            'status' => 'pending',
        ]);
    }

    /**
     * O caminho guardado é do disco público, mas um valor antigo pode ser um URL
     * inteiro. Se já é um endereço, não se lhe toca.
     */
    private static function comoUrl(?string $valor): ?string
    {
        if (! $valor) {
            return null;
        }

        return filter_var($valor, FILTER_VALIDATE_URL) ? $valor : Storage::url($valor);
    }
}
