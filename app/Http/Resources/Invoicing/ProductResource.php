<?php

namespace App\Http\Resources\Invoicing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A forma de um artigo quando sai para o React.
 *
 * O STOCK QUE SAI DAQUI É A SOMA DAS LINHAS, não a coluna agregada. São a
 * mesma coisa enquanto o `StockObserver` estiver a fazer o seu trabalho — e
 * quando não estiver, é a soma que diz a verdade. É a mesma fonte que a lista
 * de sempre usa.
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $gereStock = (bool) $this->manage_stock;
        $stock = (float) ($this->stock_das_linhas ?? $this->stock_quantity ?? 0);
        $minimo = (int) ($this->stock_min ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'type' => $this->type,
            'tipo_rotulo' => $this->type === 'servico' ? __('Serviço') : __('Produto'),
            'description' => $this->description,
            'unit' => $this->unit,

            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null,
            'category_id' => $this->category_id,

            // A marca e o fornecedor habitual. Só o número: o nome está na
            // lista de opções, e mandá-lo aqui obrigava a carregar duas
            // relações em cada linha da listagem para nada.
            'brand_id' => $this->brand_id,
            'supplier_id' => $this->supplier_id,

            'price' => round((float) $this->price, 2),
            'cost' => $this->cost === null ? null : round((float) $this->cost, 2),

            'tax_type' => $this->tax_type,
            'tax_rate_id' => $this->tax_rate_id,
            // A percentagem vem do catálogo da empresa, nunca escrita à mão.
            'taxa' => $this->taxRate ? (float) $this->taxRate->rate : null,
            'exemption_reason' => $this->exemption_reason,

            'manage_stock' => $gereStock,
            // Trabalhos à medida: o preço escreve-se na hora, no balcão.
            'preco_no_pos' => (bool) $this->preco_no_pos,
            'stock' => $gereStock ? $stock : null,
            'stock_min' => $this->stock_min,
            'stock_max' => $this->stock_max,
            // Em falta é uma DECISÃO, e sai decidida: só conta em quem gere
            // stock e tem mínimo definido.
            'em_falta' => $gereStock && $minimo > 0 && $stock <= $minimo,
            'esgotado' => $gereStock && $stock <= 0,

            /*
             * LOTES E VALIDADES — sempre, pela mesma razão dos campos de
             * sector logo abaixo: o formulário carrega a ficha daqui, e uma
             * chave omitida deixava o valor anterior no ecrã. Um artigo a que
             * se tirou o controlo de lotes continuava a aparecer marcado.
             */
            'track_batches' => (bool) $this->track_batches,
            'track_expiry' => (bool) $this->track_expiry,
            'require_batch_on_purchase' => (bool) $this->require_batch_on_purchase,
            'require_batch_on_sale' => (bool) $this->require_batch_on_sale,

            'is_active' => (bool) $this->is_active,

            /*
             * OS CAMPOS DE SECTOR VÃO SEMPRE, mesmo a null.
             *
             * Duas razões, e as duas doeram: o formulário carrega a ficha
             * daqui, e uma chave omitida deixava o valor anterior no ecrã —
             * um artigo que deixasse de exigir receita continuava a
             * aparecer marcado. E o PWA junta o catálogo com bulkPut, onde
             * uma chave em falta é «não mexer», não «apagar».
             */
            'requires_prescription' => (bool) $this->requires_prescription,
            'is_controlled' => (bool) $this->is_controlled,
            'active_ingredient' => $this->active_ingredient,
            'dosage' => $this->dosage,
            'pharmaceutical_form' => $this->pharmaceutical_form,
            'armed_registration' => $this->armed_registration,
            'size' => $this->size,
            'color' => $this->color,
            'gender' => $this->gender,
            'material' => $this->material,
            'net_content' => $this->net_content,
            'pao_months' => $this->pao_months === null ? null : (int) $this->pao_months,
            'inci_ingredients' => $this->inci_ingredients,
            'storage_conditions' => $this->storage_conditions,
            'allergens' => $this->allergens,
            'origin_country' => $this->origin_country,

            /*
             * AS IMAGENS SAEM EM DOIS FORMATOS, e os dois são precisos.
             *
             * O CAMINHO é o que está gravado na coluna — é a chave com que se
             * pede para apagar uma imagem da galeria, e a única que não muda
             * quando o domínio das imagens muda. A URL é para mostrar.
             *
             * Devolver só a URL obrigava o ecrã a desfazê-la para adivinhar o
             * caminho, e um `str_replace('/storage/', '')` é exactamente o
             * género de adivinha que parte no dia em que o disco mudar.
             */
            'imagem' => self::url($this->featured_image),
            'imagem_caminho' => $this->featured_image,
            'galeria' => collect($this->gallery ?? [])
                ->filter(fn ($c) => filled($c))
                ->map(fn ($c) => ['caminho' => $c, 'url' => self::url($c)])
                ->values()
                ->all(),
        ];
    }

    /**
     * A morada de uma imagem guardada.
     *
     * Um artigo importado pode trazer um endereço completo em vez de um
     * caminho no disco — passá-lo pelo `Storage::url` dava
     * `/storage/https://…`, uma imagem partida sem explicação nenhuma.
     */
    private static function url(?string $caminho): ?string
    {
        if (! filled($caminho)) {
            return null;
        }

        return filter_var($caminho, FILTER_VALIDATE_URL) ? $caminho : Storage::disk('public')->url($caminho);
    }
}
