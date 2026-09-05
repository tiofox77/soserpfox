<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\InvoicingSeries;
use DomainException;
use Illuminate\Support\Collection;

/**
 * AS SÉRIES DE NUMERAÇÃO — criar, renomear e escolher a padrão, num sítio só.
 *
 * Vivia dentro do `Livewire\Invoicing\Settings`. Ao migrar o ecrã para React,
 * saiu para aqui; o Livewire e a API chamam o mesmo.
 *
 * O QUE ESTE CAMINHO GARANTE:
 *
 *  · O PREFIXO É SEMPRE O DO CATÁLOGO, nunca o que o browser mandar. É o
 *    primeiro bloco do número, e é esse bloco que a AGT lê para classificar
 *    o documento: assim nasceram as séries 'PRF' e 'PP' onde a AGT espera
 *    'PR' — documentos recusados com E32. Um tipo que o catálogo não
 *    conhece não dá série nenhuma.
 *
 *  · ESCOLHER A PADRÃO É UM ACTO DELIBERADO. Passar o padrão para uma série
 *    por estrear enquanto outra do mesmo tipo já vai adiantada abre uma
 *    segunda numeração em paralelo — o que não passa num SAFT. Pode ser
 *    deliberado (mudar de série no início do ano), por isso não se bloqueia,
 *    mas exige confirmação com os números concretos à frente dos olhos.
 */
class GestorDeSeries
{
    /** Os tipos que o ecrã oferece, na ordem em que aparecem. */
    public const TIPOS = [
        'proforma' => ['nome' => 'Proforma de Venda', 'icone' => 'file-invoice', 'cor' => 'blue'],
        'invoice' => ['nome' => 'Fatura de Venda', 'icone' => 'file-invoice-dollar', 'cor' => 'green'],
        'pos' => ['nome' => 'Fatura-Recibo (POS)', 'icone' => 'cash-register', 'cor' => 'emerald'],
        'receipt' => ['nome' => 'Recibo', 'icone' => 'receipt', 'cor' => 'purple'],
        'credit_note' => ['nome' => 'Nota de Crédito', 'icone' => 'file-excel', 'cor' => 'orange'],
        'debit_note' => ['nome' => 'Nota de Débito', 'icone' => 'file-alt', 'cor' => 'red'],
        'purchase' => ['nome' => 'Fatura de Compra', 'icone' => 'shopping-cart', 'cor' => 'indigo'],
        'advance' => ['nome' => 'Adiantamento', 'icone' => 'hand-holding-usd', 'cor' => 'cyan'],
    ];

    /** Os tipos com o prefixo do catálogo, que é a fonte única. */
    public function tipos(): array
    {
        $tipos = [];

        foreach (self::TIPOS as $tipo => $d) {
            $tipos[] = [
                'tipo' => $tipo,
                'nome' => __($d['nome']),
                'icone' => $d['icone'],
                'cor' => $d['cor'],
                'prefixo' => $this->prefixoDoCatalogo($tipo),
            ];
        }

        return $tipos;
    }

    /**
     * O prefixo que este tipo de documento TEM de ter — ou null se o tipo não
     * pertencer a catálogo nenhum.
     *
     * Documento fiscal → catálogo AGT. Documento interno, como a proforma de
     * compra → catálogo canónico da casa: nunca vai à AGT, logo não tem nem
     * pode ter prefixo fiscal.
     */
    public function prefixoDoCatalogo(string $tipo): ?string
    {
        $agt = InvoicingSeries::prefixoDe($tipo);

        if ($agt !== null) {
            return $agt;
        }

        if (InvoicingSeries::tipoInterno($tipo)) {
            return SeriesCatalog::paraTipo($tipo)['prefix'] ?? null;
        }

        return null;
    }

    /** As séries activas da empresa, agrupadas por tipo. */
    public function activas(int $tenantId): Collection
    {
        return InvoicingSeries::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('document_type')
            ->orderBy('series_code')
            ->get()
            ->groupBy('document_type');
    }

    /** @throws DomainException quando o tipo não é do catálogo */
    public function criar(int $tenantId, string $tipo, string $codigo, ?string $nome, ?string $descricao): InvoicingSeries
    {
        $prefixo = $this->prefixoDoCatalogo($tipo);

        if ($prefixo === null) {
            throw new DomainException(__('Tipo de documento desconhecido (:tipo). A série não foi criada.', [
                'tipo' => $tipo !== '' ? $tipo : __('vazio'),
            ]));
        }

        return InvoicingSeries::create([
            'tenant_id' => $tenantId,
            'document_type' => $tipo,
            'series_code' => $codigo,
            'name' => $nome ?: "Série {$prefixo} {$codigo}",
            'prefix' => $prefixo,
            'include_year' => true,
            'next_number' => 1,
            'number_padding' => 6,
            'is_default' => false,
            'is_active' => true,
            'current_year' => now()->year,
            'reset_yearly' => true,
            'description' => $descricao,
        ]);
    }

    public function actualizar(InvoicingSeries $serie, string $codigo, ?string $nome, ?string $descricao): void
    {
        $serie->update([
            'series_code' => $codigo,
            // O prefixo do nome sai da própria série, não do pedido.
            'name' => $nome ?: "Série {$serie->prefix} {$codigo}",
            'description' => $descricao,
        ]);
    }

    /**
     * Torna esta série a padrão do seu tipo.
     *
     * @return array|null  Sem `$confirmado`, e havendo outra série do mesmo
     *                     tipo já adiantada, devolve o aviso com os números
     *                     das duas — e não faz nada. Null quando ficou feito.
     */
    public function tornarPadrao(InvoicingSeries $serie, bool $confirmado = false): ?array
    {
        if (! $confirmado) {
            $adiantada = InvoicingSeries::where('tenant_id', $serie->tenant_id)
                ->where('document_type', $serie->document_type)
                ->where('id', '!=', $serie->id)
                ->where('next_number', '>', 1)
                ->orderByDesc('next_number')
                ->first();

            if ((int) $serie->next_number <= 1 && $adiantada) {
                return [
                    'nova' => $serie->series_code,
                    'nova_proximo' => (int) $serie->next_number,
                    'em_uso' => $adiantada->series_code,
                    'em_uso_proximo' => (int) $adiantada->next_number,
                ];
            }
        }

        // Desmarcar as outras e marcar esta numa só transacção — em passos
        // soltos, uma falha entre os dois UPDATEs deixava o tipo sem padrão.
        $serie->tornarPadrao();

        return null;
    }
}
