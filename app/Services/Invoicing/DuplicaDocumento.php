<?php

namespace App\Services\Invoicing;

/**
 * DUPLICAR UM DOCUMENTO: aproveitar o trabalho, nunca a identidade.
 *
 * Quem factura o mesmo cliente todos os meses, ou faz proformas parecidas umas
 * às outras, estava a reescrever tudo de cada vez — cliente, armazém, doze
 * linhas, descontos, condições. É trabalho que já foi feito uma vez.
 *
 * O QUE SE COPIA: o conteúdo comercial. Cliente ou fornecedor, armazém,
 * linhas com preços, quantidades e descontos, notas, região fiscal, retenção.
 *
 * O QUE NÃO SE COPIA, E PORQUÊ. Um documento fiscal é um documento fiscal
 * PORQUE tem número, série, hash, ATCUD e assinatura. Copiar qualquer um
 * desses campos fazia nascer um segundo documento com a identidade do
 * primeiro: duas realidades fiscais para a mesma venda, que é o defeito mais
 * caro que este sistema pode produzir.
 *
 * Também não viajam:
 *
 *   · AS DATAS. Um duplicado é de hoje. Herdar a data de emissão de um
 *     documento de há três meses punha-o num período fiscal que já fechou.
 *   · O ESTADO E O PAGAMENTO. O duplicado de uma factura paga não está pago.
 *     Herdar isso dava por recebido dinheiro que nunca entrou.
 *
 * NÃO SE GRAVA NADA AQUI, e é essa a decisão que faz esta porta ser segura:
 * duplicar devolve CONTEÚDO para o editor abrir em branco, não um documento
 * novo já criado. Ninguém fica com um rascunho fantasma por ter carregado no
 * botão errado, e o número só é pedido à série quando a pessoa gravar.
 *
 * A REDE DE SEGURANÇA. A lista de campos abaixo é uma negação, e uma negação
 * envelhece: um campo novo no `abrir` de um editor podia trazer o hash do
 * original sem ninguém se lembrar de o acrescentar aqui. Por isso, além da
 * lista, varre-se o conteúdo à procura dos VALORES de identidade do original
 * (`marcas`) e deita-se fora o que os trouxer. É o equivalente ao
 * `isEdit = false` que o trait em Livewire tinha no fim do carregamento.
 */
final class DuplicaDocumento
{
    /**
     * O QUE NUNCA VIAJA, por nome de campo.
     *
     * Cobre os três editores (factura de venda, compra e propostas) num sítio
     * só: cada um tem os seus, e ter três listas era ter duas por corrigir.
     */
    public const IDENTIDADE = [
        // A identidade do documento no ecrã
        'id', 'numero', 'numero_agt', 'estado', 'status', 'pode_editar', 'pdf',
        // A série: o duplicado nasce na série por omissão do seu tipo
        'series_id', 'agt_series_id',
        // A assinatura e a cadeia
        'hash', 'saft_hash', 'hash_previous', 'hash_control', 'atcud',
        // O que a AGT já sabe deste documento
        'agt_status', 'agt_submitted_at', 'agt_invoice_id',
        // O dinheiro que entrou neste, e não no que vai nascer
        'paid_amount', 'pago', 'saldo',
    ];

    /**
     * AS COLUNAS QUE GUARDAM IDENTIDADE FISCAL, em qualquer dos documentos.
     *
     * Serve para ler os VALORES do original e depois os procurar no conteúdo
     * do duplicado. Uma coluna que o modelo não tenha lê-se como nula e não
     * incomoda ninguém.
     */
    private const COLUNAS_DE_IDENTIDADE = [
        'invoice_number', 'proforma_number', 'quote_number', 'receipt_number',
        'credit_note_number', 'debit_note_number', 'advance_number',
        'saft_hash', 'hash', 'hash_previous', 'atcud', 'agt_invoice_id',
    ];

    /**
     * A resposta que o editor recebe para abrir um duplicado.
     *
     * `origem` existe só para o ecrã poder dizer de onde isto veio — quem
     * carrega em duplicar quer ver confirmado que apanhou o documento certo.
     *
     * @param  array<string,mixed>              $documento  o cabeçalho tal como o `abrir` do editor o entrega
     * @param  iterable                         $linhas     as linhas, tal como o `abrir` as entrega
     * @param  array<string,mixed>              $datas      as datas de hoje, por nome de campo
     * @param  object                           $origem     o documento de onde isto vem
     * @param  string|null                      $numero     o número do original, para o ecrã o mostrar
     * @return array{origem: array<string,mixed>, documento: array<string,mixed>, linhas: mixed}
     */
    public static function resposta(array $documento, $linhas, array $datas, object $origem, ?string $numero): array
    {
        return [
            'origem' => [
                'id' => (int) $origem->getKey(),
                'numero' => $numero,
            ],
            'documento' => self::conteudo($documento, $datas, self::marcas($origem)),
            'linhas' => $linhas,
        ];
    }

    /**
     * O cabeçalho sem nada que dê identidade ao original, e com as datas de hoje.
     *
     * @param  array<string,mixed>  $documento
     * @param  array<string,mixed>  $datas
     * @param  array<int,string>    $marcas  os valores de identidade do original
     * @return array<string,mixed>
     */
    public static function conteudo(array $documento, array $datas, array $marcas = []): array
    {
        $conteudo = array_diff_key($documento, array_flip(self::IDENTIDADE));

        // A varredura: um campo que ainda traga o número, o hash ou o ATCUD do
        // original não passa, chame-se ele como se chamar.
        if ($marcas !== []) {
            $conteudo = array_filter(
                $conteudo,
                fn ($valor) => ! (is_string($valor) && in_array(trim($valor), $marcas, true)),
            );
        }

        return array_merge($conteudo, $datas);
    }

    /**
     * Os valores de identidade fiscal gravados num documento.
     *
     * @return array<int,string>
     */
    public static function marcas(object $documento): array
    {
        $marcas = [];

        foreach (self::COLUNAS_DE_IDENTIDADE as $coluna) {
            $valor = method_exists($documento, 'getAttribute')
                ? $documento->getAttribute($coluna)
                : ($documento->{$coluna} ?? null);

            if (is_string($valor) && trim($valor) !== '') {
                $marcas[] = trim($valor);
            }
        }

        return array_values(array_unique($marcas));
    }
}
