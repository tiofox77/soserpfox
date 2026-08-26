<?php

namespace App\Services\Invoicing\Propostas;

/**
 * O catálogo de peças com que se monta uma proposta.
 *
 * É a fonte única: o editor desenha a paleta a partir daqui e o renderizador
 * sabe o que fazer com cada tipo. Acrescentar um bloco novo é acrescentar uma
 * entrada aqui e um método no RenderizadorDeProposta.
 */
class TiposDeBloco
{
    public static function catalogo(): array
    {
        return [
            'capa' => [
                'nome'     => 'Capa',
                'icone'    => 'fa-image',
                'ajuda'    => 'Primeira página: título, cliente e data.',
                'padroes'  => [
                    'titulo'        => 'Proposta Comercial',
                    'subtitulo'     => '{{cliente.nome}}',
                    'mostrar_logo'  => true,
                    'mostrar_dados' => true,
                    'cor_fundo'     => '',
                    'quebrar_depois' => true,
                ],
            ],

            'titulo' => [
                'nome'    => 'Título de secção',
                'icone'   => 'fa-heading',
                'ajuda'   => 'Separa as partes da proposta.',
                'padroes' => ['texto' => 'Âmbito do trabalho', 'numerar' => true],
            ],

            'texto' => [
                'nome'    => 'Texto',
                'icone'   => 'fa-align-left',
                'ajuda'   => 'Parágrafos, listas, negrito. Igual em todas as propostas.',
                'padroes' => ['html' => '<p>Escreva aqui.</p>'],
            ],

            // A peça que faz este módulo valer a pena: o modelo deixa um campo
            // por preencher e, em cada orçamento, quem o faz escreve o que
            // aquele cliente precisa de ler. O desenho é sempre o mesmo; o
            // conteúdo é sempre o daquele negócio.
            'campo_livre' => [
                'nome'    => 'Campo a preencher',
                'icone'   => 'fa-pen-to-square',
                'ajuda'   => 'Fica em branco no modelo e é escrito em cada orçamento.',
                'padroes' => [
                    'chave'  => 'ambito',
                    'rotulo' => 'Âmbito do trabalho',
                    'ajuda'  => 'O que vai ser feito, em concreto.',
                    'titulo' => 'Âmbito do trabalho',
                    'linhas' => 6,
                ],
            ],

            'dados_cliente' => [
                'nome'    => 'Dados do cliente',
                'icone'   => 'fa-id-card',
                'ajuda'   => 'Caixa com cliente, número, data e validade.',
                'padroes' => ['mostrar_validade' => true, 'mostrar_nif' => true],
            ],

            'itens' => [
                'nome'    => 'Tabela de itens',
                'icone'   => 'fa-table-list',
                'ajuda'   => 'As linhas do orçamento, tal como foram lançadas.',
                'padroes' => [
                    'mostrar_descricao' => true,
                    'mostrar_desconto'  => true,
                    'mostrar_imposto'   => true,
                    'titulo'            => 'Investimento',
                ],
            ],

            'totais' => [
                'nome'    => 'Totais',
                'icone'   => 'fa-calculator',
                'ajuda'   => 'Subtotal, descontos, imposto e total.',
                'padroes' => ['mostrar_por_extenso' => false],
            ],

            'condicoes' => [
                'nome'    => 'Condições',
                'icone'   => 'fa-file-contract',
                'ajuda'   => 'Prazos, pagamento, garantia.',
                'padroes' => [
                    'titulo' => 'Condições',
                    'html'   => '<p>Validade da proposta: até {{orcamento.validade}}.</p>',
                ],
            ],

            'assinaturas' => [
                'nome'    => 'Assinaturas',
                'icone'   => 'fa-signature',
                'ajuda'   => 'Linhas para assinar, de um lado ou dos dois.',
                'padroes' => [
                    'esquerda' => '{{empresa.nome}}',
                    'direita'  => '{{cliente.nome}}',
                    'duas'     => true,
                ],
            ],

            'imagem' => [
                'nome'    => 'Imagem',
                'icone'   => 'fa-photo-film',
                'ajuda'   => 'Portfólio, organigrama, planta.',
                'padroes' => ['url' => '', 'largura' => 100, 'legenda' => ''],
            ],

            'quebra' => [
                'nome'    => 'Quebra de página',
                'icone'   => 'fa-scissors',
                'ajuda'   => 'O que vier a seguir começa numa página nova.',
                'padroes' => [],
            ],
        ];
    }

    public static function padroesDe(string $tipo): array
    {
        return self::catalogo()[$tipo]['padroes'] ?? [];
    }

    public static function existe(string $tipo): bool
    {
        return isset(self::catalogo()[$tipo]);
    }

    /**
     * As variáveis que se podem escrever em qualquer texto do modelo.
     * Agrupadas para o menu de inserção do editor.
     */
    public static function variaveis(): array
    {
        return [
            'Empresa' => [
                '{{empresa.nome}}'     => 'Nome da empresa',
                '{{empresa.nif}}'      => 'NIF',
                '{{empresa.morada}}'   => 'Morada',
                '{{empresa.telefone}}' => 'Telefone',
                '{{empresa.email}}'    => 'Email',
            ],
            'Cliente' => [
                '{{cliente.nome}}'     => 'Nome do cliente',
                '{{cliente.nif}}'      => 'NIF do cliente',
                '{{cliente.morada}}'   => 'Morada do cliente',
                '{{cliente.telefone}}' => 'Telefone do cliente',
                '{{cliente.email}}'    => 'Email do cliente',
            ],
            'Orçamento' => [
                '{{orcamento.numero}}'   => 'Número',
                '{{orcamento.data}}'     => 'Data',
                '{{orcamento.validade}}' => 'Válido até',
                '{{orcamento.subtotal}}' => 'Subtotal',
                '{{orcamento.imposto}}'  => 'Imposto',
                '{{orcamento.total}}'    => 'Total',
                '{{orcamento.moeda}}'    => 'Moeda',
            ],
            'Outros' => [
                '{{utilizador.nome}}' => 'Quem fez a proposta',
                '{{data.hoje}}'       => 'Data de hoje',
                '{{data.ano}}'        => 'Ano',
            ],
        ];
    }
}
