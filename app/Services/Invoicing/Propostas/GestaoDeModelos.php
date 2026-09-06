<?php

namespace App\Services\Invoicing\Propostas;

use App\Models\Invoicing\QuoteTemplate;
use DomainException;

/**
 * A LISTA DE MODELOS DE PROPOSTA de uma empresa: criar (de arranque ou
 * vazio), duplicar, tornar padrão, eliminar. O ecrã de sempre e o ecrã em
 * React fazem tudo por aqui.
 */
class GestaoDeModelos
{
    public function __construct(private readonly int $tenantId)
    {
    }

    public function lista(string $procura = '')
    {
        return QuoteTemplate::where('tenant_id', $this->tenantId)
            ->when($procura !== '', fn ($q) => $q->where('nome', 'like', '%' . $procura . '%'))
            ->withCount('orcamentos')
            ->orderByDesc('is_default')
            ->orderBy('nome');
    }

    public function abrir(int $id): QuoteTemplate
    {
        return QuoteTemplate::where('tenant_id', $this->tenantId)->find($id)
            ?? throw new DomainException(__('Este modelo não pertence à empresa activa.'));
    }

    public function criarDeArranque(string $chave, ?int $userId): QuoteTemplate
    {
        if (!isset(ModelosDeArranque::catalogo()[$chave])) {
            throw new DomainException(__('Modelo de arranque desconhecido.'));
        }

        return ModelosDeArranque::criarParaEmpresa($chave, $this->tenantId, $userId);
    }

    /**
     * Nem mesmo "vazio" nasce vazio: sem itens e sem totais, o primeiro PDF
     * sairia sem preços e parecia avariado.
     */
    public function criarVazio(?int $userId): QuoteTemplate
    {
        $modelo = QuoteTemplate::create([
            'tenant_id' => $this->tenantId,
            'nome' => 'Modelo novo',
            'blocos' => [
                ['id' => 'dc_' . substr(md5(uniqid('', true)), 0, 8), 'tipo' => 'dados_cliente',
                    'mostrar_validade' => true, 'mostrar_nif' => true],
                ['id' => 'it_' . substr(md5(uniqid('', true)), 0, 8), 'tipo' => 'itens',
                    'titulo' => 'Investimento', 'mostrar_descricao' => true,
                    'mostrar_desconto' => true, 'mostrar_imposto' => true],
                ['id' => 'to_' . substr(md5(uniqid('', true)), 0, 8), 'tipo' => 'totais',
                    'mostrar_por_extenso' => false],
            ],
            'estilos' => QuoteTemplate::ESTILOS_PADRAO,
            'created_by' => $userId,
        ]);

        if (QuoteTemplate::where('tenant_id', $this->tenantId)->count() === 1) {
            $modelo->tornarPadrao();
        }

        return $modelo;
    }

    public function duplicar(int $id, ?int $userId): QuoteTemplate
    {
        $original = $this->abrir($id);

        $copia = $original->replicate(['is_default']);
        $copia->nome = $original->nome . ' (cópia)';
        $copia->is_default = false;
        $copia->created_by = $userId;
        $copia->save();

        return $copia;
    }

    public function tornarPadrao(int $id): QuoteTemplate
    {
        $modelo = $this->abrir($id);
        $modelo->tornarPadrao();

        return $modelo;
    }

    /**
     * Soft delete: os orçamentos já feitos apontam para aqui e teriam de
     * continuar a saber com que desenho foram impressos.
     */
    public function eliminar(int $id): void
    {
        $this->abrir($id)->delete();
    }
}
