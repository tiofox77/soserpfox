<?php

namespace App\Support;

/**
 * A GALERIA DE ÍCONES — o que se pode escolher, por assunto.
 *
 * Os formulários pediam o código à mão: uma caixa de texto onde se escrevia
 * `fa-money-bill` e se esperava pelo melhor. Quem não conhece o Font Awesome
 * de cor não tinha por onde começar, e um código mal escrito não dá erro
 * nenhum — dá um quadrado vazio na lista, que só se descobre depois.
 *
 * A LISTA VIVE AQUI, EM PHP, e não no JavaScript. Há formulários em React (os
 * catálogos) e formulários em Blade (o salão, os módulos, os equipamentos):
 * duas galerias em dois sítios eram duas listas a divergir à primeira adição,
 * e é exactamente essa a classe de defeito que esta migração passou o tempo a
 * corrigir. O React recebe-a pela API; o Blade lê-a directamente.
 *
 * A LISTA É CURADA, e não o catálogo inteiro. O Font Awesome tem milhares de
 * ícones e uma grelha com todos não é uma escolha, é um labirinto. Estão aqui
 * os que servem um ERP — dinheiro, comércio, comida, oficina, escritório — e
 * todos os que já estão gravados nesta base.
 *
 * Só ícones do **Font Awesome 6 Free (solid)**, que é o que está em
 * `public/vendor/css/fontawesome.min.css`. Um ícone do plano pago ou de outra
 * família desenha-se como um quadrado vazio, e ninguém percebe porquê.
 */
class GaleriaDeIcones
{
    /**
     * Por grupo: `código sem o «fa-» => nome por que se procura`.
     *
     * O nome é em português porque é por ele que se procura: quem quer um
     * carrinho escreve «carrinho», não `cart-shopping`.
     */
    private const GRUPOS = [
        'Dinheiro' => [
            'money-bill' => 'nota',
            'money-bill-wave' => 'dinheiro',
            'money-check' => 'cheque',
            'wallet' => 'carteira',
            'credit-card' => 'cartão',
            'coins' => 'moedas',
            'cash-register' => 'caixa registadora',
            'sack-dollar' => 'saco de dinheiro',
            'hand-holding-dollar' => 'pagamento',
            'piggy-bank' => 'poupança',
            'building-columns' => 'banco',
            'university' => 'banco antigo',
            'receipt' => 'recibo',
            'right-left' => 'transferência',
            'exchange-alt' => 'troca',
            'arrow-down' => 'entrada',
            'arrow-up' => 'saída',
            'percent' => 'percentagem',
        ],
        'Comércio' => [
            'shopping-basket' => 'cesto',
            'cart-shopping' => 'carrinho',
            'bag-shopping' => 'saco',
            'store' => 'loja',
            'tag' => 'etiqueta',
            'tags' => 'etiquetas',
            'barcode' => 'código de barras',
            'box' => 'caixa',
            'boxes-stacked' => 'armazém',
            'warehouse' => 'depósito',
            'truck' => 'camião',
            'gift' => 'presente',
            'layer-group' => 'grupo',
        ],
        'Comida e bebida' => [
            'utensils' => 'restaurante',
            'bowl-food' => 'prato',
            'burger' => 'hambúrguer',
            'pizza-slice' => 'pizza',
            'drumstick-bite' => 'frango',
            'fish' => 'peixe',
            'bread-slice' => 'pão',
            'cake-candles' => 'bolo',
            'ice-cream' => 'gelado',
            'apple-whole' => 'fruta',
            'carrot' => 'legumes',
            'mug-hot' => 'café',
            'glass-water' => 'água',
            'bottle-droplet' => 'garrafa',
            'wine-bottle' => 'vinho',
            'beer-mug-empty' => 'cerveja',
            'martini-glass-citrus' => 'cocktail',
        ],
        'Tecnologia' => [
            'desktop' => 'computador',
            'laptop' => 'portátil',
            'mobile' => 'telemóvel',
            'tv' => 'televisão',
            'headphones' => 'auscultadores',
            'print' => 'impressora',
            'keyboard' => 'teclado',
            'camera' => 'câmara',
            'hard-drive' => 'disco',
            'wifi' => 'internet',
            'plug' => 'ficha',
            'battery-full' => 'bateria',
            'gamepad' => 'jogos',
            'lightbulb' => 'lâmpada',
        ],
        'Casa e vestuário' => [
            'house' => 'casa',
            'couch' => 'sofá',
            'bed' => 'cama',
            'shirt' => 'camisa',
            'shoe-prints' => 'calçado',
            'socks' => 'meias',
            'hat-cowboy' => 'chapéu',
            'glasses' => 'óculos',
            'gem' => 'joias',
            'broom' => 'limpeza',
            'soap' => 'sabão',
            'spray-can-sparkles' => 'detergente',
        ],
        'Saúde e beleza' => [
            'heart' => 'coração',
            'heart-pulse' => 'saúde',
            'pills' => 'medicamentos',
            'stethoscope' => 'consulta',
            'kit-medical' => 'primeiros socorros',
            'tooth' => 'dentista',
            'scissors' => 'cabeleireiro',
            'hand-sparkles' => 'manicure',
            'pump-soap' => 'higiene',
            'spa' => 'estética',
        ],
        'Oficina e obra' => [
            'wrench' => 'chave',
            'screwdriver-wrench' => 'ferramentas',
            'hammer' => 'martelo',
            'toolbox' => 'caixa de ferramentas',
            'gear' => 'peça',
            'gears' => 'mecânica',
            'car' => 'carro',
            'motorcycle' => 'mota',
            'oil-can' => 'óleo',
            'paint-roller' => 'pintura',
            'trowel' => 'construção',
            'helmet-safety' => 'obra',
        ],
        'Escritório' => [
            'pen' => 'caneta',
            'file-lines' => 'documento',
            'folder' => 'pasta',
            'folder-open' => 'pasta aberta',
            'envelope' => 'email',
            'paperclip' => 'anexo',
            'calendar' => 'calendário',
            'clipboard' => 'lista',
            'book' => 'livro',
            'briefcase' => 'serviços',
            'building' => 'empresa',
            'users' => 'pessoas',
            'user-tie' => 'cliente',
            'phone' => 'telefone',
            'puzzle-piece' => 'módulo',
        ],
        'Lazer e outros' => [
            'basketball' => 'desporto',
            'futbol' => 'futebol',
            'dumbbell' => 'ginásio',
            'bicycle' => 'bicicleta',
            'music' => 'música',
            'film' => 'cinema',
            'plane' => 'viagens',
            'umbrella-beach' => 'praia',
            'dog' => 'animais',
            'seedling' => 'plantas',
            'paw' => 'veterinário',
            'star' => 'destaque',
            'bullhorn' => 'som',
            'microphone' => 'microfone',
            'volume-high' => 'coluna',
        ],
    ];

    /**
     * Os grupos na forma que os ecrãs desenham.
     *
     * @return array<int, array{nome: string, icones: array<int, array{codigo: string, nome: string}>}>
     */
    public static function grupos(): array
    {
        $grupos = [];

        foreach (self::GRUPOS as $nome => $icones) {
            $lista = [];

            foreach ($icones as $codigo => $comoSeChama) {
                $lista[] = ['codigo' => 'fa-' . $codigo, 'nome' => __($comoSeChama)];
            }

            $grupos[] = ['nome' => __($nome), 'icones' => $lista];
        }

        return $grupos;
    }

    /**
     * Todos os códigos, com o `fa-` à frente.
     *
     * @return array<int, string>
     */
    public static function todos(): array
    {
        $todos = [];

        foreach (self::GRUPOS as $icones) {
            foreach (array_keys($icones) as $codigo) {
                $todos[] = 'fa-' . $codigo;
            }
        }

        return $todos;
    }
}
