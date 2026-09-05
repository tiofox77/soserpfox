<?php

namespace App\Livewire\Concerns;

/**
 * Duplicar um documento: aproveitar o trabalho, não a identidade.
 *
 * Quem factura o mesmo cliente todos os meses, ou faz orçamentos parecidos uns
 * aos outros, estava a reescrever tudo de cada vez — cliente, armazém, doze
 * linhas, descontos, condições. É trabalho que já foi feito uma vez.
 *
 * O QUE SE COPIA: o conteúdo comercial. Cliente ou fornecedor, armazém,
 * linhas com preços e quantidades, descontos, notas e condições, tipo de
 * documento.
 *
 * O QUE NÃO SE COPIA, E PORQUÊ. Um documento fiscal é um documento fiscal
 * PORQUE tem número, série, hash, ATCUD e assinatura. Copiar qualquer um
 * desses campos fazia nascer um segundo documento com a identidade do
 * primeiro: duas realidades fiscais para a mesma venda, que é o defeito mais
 * caro que este sistema pode produzir. Aqui nascem em branco, e a numeração
 * segue o seu caminho normal na gravação.
 *
 * Também não se copia:
 *
 *   · AS DATAS. Um duplicado é de hoje. Herdar a data de emissão de um
 *     documento de há três meses punha-o no período fiscal errado.
 *   · O ESTADO E O PAGAMENTO. O duplicado de uma factura paga não está pago.
 *     Herdar isso dava por recebido dinheiro que nunca entrou.
 *
 * A garantia não é só de intenção: os `loadX()` destes ecrãs nunca leram esses
 * campos — o duplicado herda o que o ecrã de edição herdaria, e mais nada. O
 * ensaio DuplicarDocumentoTest confirma-o campo a campo.
 */
trait DuplicaDocumento
{
    /** O número do documento de onde isto veio. Só para o ecrã o dizer. */
    public ?string $duplicadoDe = null;

    /**
     * O id pedido na barra de endereço para duplicar, se houver.
     *
     * Vai por query string (`?duplicar=123`) e não por rota própria: a rota de
     * criação já existe e é a mesma: o que muda é de onde vêm os valores
     * iniciais, não o ecrã.
     */
    protected function idParaDuplicar(): ?int
    {
        $id = request()->query('duplicar');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Marca este formulário como duplicado e garante que nasce novo.
     *
     * Chama-se DEPOIS do `loadX()`: o carregamento é o mesmo do ecrã de
     * edição, e é aqui que se corta o que faria dele uma edição.
     */
    protected function marcarComoDuplicado(?string $numeroDeOrigem): void
    {
        $this->duplicadoDe = $numeroDeOrigem;

        // A rede de segurança. Se algum `loadX()` vier um dia a ligar o modo
        // de edição, isto desliga-o — senão gravar por cima do original era
        // silencioso, e o original desaparecia.
        $this->isEdit = false;
    }
}
