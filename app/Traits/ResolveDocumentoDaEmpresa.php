<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Buscar um documento GARANTINDO que é da empresa activa, sem rebentar.
 *
 * PORQUE EXISTE: as listas usavam `findOrFail($id)`. Quando o utilizador troca
 * de empresa, o ecrã continua a mostrar (por um instante, ou até recarregar) a
 * lista da empresa ANTERIOR — com botões vivos a carregar ids que já não lhe
 * pertencem. O filtro por empresa faz o seu trabalho e não encontra nada, mas o
 * `findOrFail` transforma isso num 500 ou num "No query results for model
 * [App\Models\...] 35" à cara do utilizador. Nenhuma das duas coisas lhe diz o
 * que se passou nem o que fazer.
 *
 * Aqui devolve-se `null`, avisa-se em português e manda-se recarregar. O
 * bloqueio de acesso entre empresas mantém-se exactamente igual — o que muda é
 * só a forma de o comunicar.
 */
trait ResolveDocumentoDaEmpresa
{
    /**
     * @param  class-string<Model>  $modelo
     * @param  string[]  $com  relações a carregar
     */
    protected function documentoDaEmpresa(string $modelo, $id, array $com = []): ?Model
    {
        $query = $modelo::where('tenant_id', activeTenantId());

        if ($com) {
            $query->with($com);
        }

        $documento = $query->find($id);

        if (!$documento) {
            \Log::warning('Documento fora da empresa activa', [
                'modelo'    => $modelo,
                'id'        => $id,
                'tenant_id' => activeTenantId(),
            ]);

            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => __('Este documento não pertence à empresa activa. A actualizar a lista…'),
            ]);
            $this->dispatch('recarregar-pagina');
        }

        return $documento;
    }
}
