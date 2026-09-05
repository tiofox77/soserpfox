<?php

namespace App\Traits;

/**
 * Quem vê que documentos.
 *
 * A regra é a mesma que o relatório do POS já usava para as vendas: sem
 * `invoicing.documents.all`, cada um vê os documentos que EMITIU. Com essa
 * permissão, vê os de todos e ganha um filtro por autor.
 *
 * PORQUE EXISTE: as listas de facturas, proformas, orçamentos, notas e
 * recibos mostravam tudo o que havia na empresa a qualquer utilizador que
 * lá pudesse entrar. Um vendedor via as vendas dos colegas — a mesma coisa
 * que o relatório do POS proibia, servida pela porta do lado.
 *
 * O filtro por autor NÃO dá a volta à permissão: quem só pode ver os seus
 * não escapa escolhendo outro nome na lista (é o que `escoparPorAutor`
 * garante ao sair mais cedo).
 */
trait DocumentosPorAutor
{
    /** Filtro por autor. Só tem efeito para quem pode ver os de todos. */
    public $autorId = '';

    /** O modelo que a lista mostra. Cada ecrã diz o seu. */
    abstract protected function modeloDoDocumento(): string;

    /** A coluna que guarda o autor. Quase todas se chamam `created_by`. */
    protected function colunaDoAutor(): string
    {
        return 'created_by';
    }

    /*
     * POR DENTRO CHAMA-SE O MÉTODO, NUNCA $this->veDocumentosDeTodos.
     *
     * A propriedade mágica só existe dentro de um componente Livewire. Este
     * trait passou a ser usado também no controlador da API que serve os
     * ecrãs em React — e lá `$this->veDocumentosDeTodos` é «Undefined
     * property», o que fazia o escopo do autor deixar de correr.
     */
    public function getVeDocumentosDeTodosProperty(): bool
    {
        return (bool) auth()->user()?->can('invoicing.documents.all');
    }

    /** Aplica a regra a QUALQUER consulta de documentos. */
    protected function escoparPorAutor($query, ?string $coluna = null)
    {
        $coluna = $coluna ?? $this->colunaDoAutor();

        if (! $this->getVeDocumentosDeTodosProperty()) {
            // Sai já: deixar o filtro passar aqui era dar a volta à permissão
            // escolhendo o nome de um colega na lista.
            //
            // SEM AUTOR é de ninguém, não é de um colega. Documentos criados
            // fora de uma sessão (importações, sincronização, dados antigos)
            // ficam à vista: escondê-los de toda a gente tornava-os
            // inalcançáveis, que é pior do que mostrá-los. Em produção não
            // existe nenhum — mas nada garante que nunca exista.
            return $query->where(function ($q) use ($coluna) {
                $q->where($coluna, auth()->id())->orWhereNull($coluna);
            });
        }

        if ($this->autorId) {
            $query->where($coluna, $this->autorId);
        }

        return $query;
    }

    /** Consulta-base da empresa activa, já com a regra aplicada. */
    protected function baseDoAutor(?string $modelo = null)
    {
        $modelo = $modelo ?? $this->modeloDoDocumento();

        return $this->escoparPorAutor(
            $modelo::where('tenant_id', activeTenantId())
        );
    }

    /**
     * Quem emitiu documentos nesta empresa — só esses valem como filtro.
     *
     * Para quem só vê os seus, a lista vem vazia: os nomes dos colegas são
     * eles próprios informação que não lhe compete.
     */
    public function getAutoresDosDocumentosProperty()
    {
        if (! $this->getVeDocumentosDeTodosProperty()) {
            return collect();
        }

        $modelo = $this->modeloDoDocumento();
        $coluna = $this->colunaDoAutor();

        return \App\Models\User::whereIn('id',
            $modelo::where('tenant_id', activeTenantId())
                ->whereNotNull($coluna)
                ->distinct()
                ->pluck($coluna)
        )->orderBy('name')->get(['id', 'name']);
    }

    public function updatedAutorId(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /**
     * Buscar UM documento respeitando a empresa e o autor.
     *
     * Os botões da linha (ver, imprimir, apagar, histórico) recebem um id, e
     * pelo id abria-se qualquer documento da empresa.
     */
    protected function documentoDoAutor($id, array $com = [])
    {
        $modelo = $this->modeloDoDocumento();

        $query = $modelo::where('tenant_id', activeTenantId());

        if ($com) {
            $query->with($com);
        }

        return $this->escoparPorAutor($query)->find($id);
    }
}
