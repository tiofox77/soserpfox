<?php

namespace App\Traits;

/**
 * A regra «cada um vê os documentos que emitiu», para fora do Livewire.
 *
 * Os ecrãs usam App\Traits\DocumentosPorAutor, que além disto traz o filtro
 * por autor. Os controladores que servem UM documento — o PDF, a
 * pré-visualização, o download — só precisam da restrição, e precisam mesmo:
 * esconder um documento na lista e depois entregá-lo em PDF a quem souber o
 * número do id não esconde nada.
 */
trait EscopoDeAutor
{
    /** Sem `invoicing.documents.all`, a consulta fica presa ao próprio. */
    protected function escoparAoAutor($query, string $coluna = 'created_by')
    {
        if (! auth()->user()?->can('invoicing.documents.all')) {
            // Sem autor é de ninguém, não é de um colega — ver a nota em
            // App\Traits\DocumentosPorAutor.
            $query->where(function ($q) use ($coluna) {
                $q->where($coluna, auth()->id())->orWhereNull($coluna);
            });
        }

        return $query;
    }
}
