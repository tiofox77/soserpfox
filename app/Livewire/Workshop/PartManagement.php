<?php

namespace App\Livewire\Workshop;

use App\Livewire\Workshop\ArtigosDaOficina;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Peças da oficina.
 *
 * Uma peça É um produto do catálogo de faturação — não um catálogo paralelo.
 * Por isso este ecrã herda a gestão de produtos da faturação em vez de manter
 * uma segunda implementação: é a mesma tabela, as mesmas regras e os mesmos
 * campos fiscais, filtrados aos produtos físicos.
 *
 * A implementação anterior era uma cópia que tinha divergido até deixar de
 * funcionar:
 *  - importava App\Models\Invoicing\ProductCategory, classe que NÃO EXISTE
 *    (a categoria real é App\Models\Category, tabela invoicing_categories) —
 *    o ecrã rebentava com erro 500 ao renderizar;
 *  - filtrava e gravava type='product', mas a coluna é ENUM('produto','servico'):
 *    a listagem devolvia 0 de 11.724 produtos e gravar truncava a coluna;
 *  - escrevia stock, min_stock, track_inventory e supplier — nenhuma dessas
 *    colunas existe em invoicing_products;
 *  - gravava o stock à mão, quando a fonte de verdade são as linhas de
 *    invoicing_stocks (stock_quantity é um agregado do StockObserver);
 *  - não recolhia tax_type / tax_rate_id / exemption_reason, pelo que uma peça
 *    criada aqui ia para a fatura sem regime fiscal — e uma linha isenta sem
 *    código de isenção é recusada pela AGT;
 *  - não tinha lixeira nem restauro, apesar de o produto usar SoftDeletes.
 *
 * Herdando, qualquer correção futura na faturação chega aqui sozinha.
 */
#[Layout('layouts.app')]
#[Title('Peças - Oficina')]
class PartManagement extends ArtigosDaOficina
{
    public function mount(): void
    {
        // Valor INICIAL do filtro, não um valor imposto: o ecrã abre nas peças,
        // mas o utilizador pode alargar a "Todos" ou ver os serviços.
        //
        // Fixá-lo em cada render() deixava o filtro impossível de limpar — o
        // "×" no chip não fazia nada — e numa empresa cujo catálogo é todo de
        // serviços (uma prestadora de serviços, por exemplo) o ecrã ficava
        // permanentemente vazio, sem forma de lá chegar aos artigos.
        $this->typeFilter = 'produto';

        // Um artigo criado a partir daqui é, por omissão, uma peça física.
        $this->type = 'produto';
    }
}
