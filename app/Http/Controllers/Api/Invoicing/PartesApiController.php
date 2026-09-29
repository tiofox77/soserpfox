<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Supplier;
use App\Services\Invoicing\ClienteDoEmissor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PROCURAR O CLIENTE (OU O FORNECEDOR) NO SERVIDOR.
 *
 * As opções dos emissores trazem os 500 primeiros por ordem alfabética, e a
 * caixa de procura filtrava só essa lista no browser. Na JG Inox (598
 * clientes) a «T.P.A. — Televisão Pública de Angola» era a 545.ª: não se
 * encontrava na factura nem na proforma, escrevesse-se o que se escrevesse
 * (29/09/2026). Com isto, o que não veio na lista vem da base.
 *
 * `?procura=` — nome, NIF ou telefone (o nome também sem pontos nem hífenes:
 * «tpa» encontra «T.P.A.-»). `?id=` — um só, para o documento que se abre já
 * com um cliente que não veio na lista mostrar o nome dele.
 *
 * Quem pode procurar é quem já recebe a lista em algum dos ecrãs que escolhem
 * a parte: a procura não mostra nada que esses ecrãs não mostrassem.
 */
class PartesApiController extends Controller
{
    private const MAXIMO = 30;

    /** As permissões dos ecrãs que escolhem um cliente. */
    private const DE_CLIENTES = [
        'invoicing.clients.view',
        'invoicing.sales.invoices.create',
        'invoicing.sales.proformas.view',
        'invoicing.sales.quotes.view',
        'invoicing.credit-notes.view',
        'invoicing.debit-notes.view',
        'invoicing.receipts.view',
        'invoicing.advances.create',
        'invoicing.advances.edit',
        'invoicing.transport-guides.view',
        'invoicing.settings.view',
    ];

    /** As permissões dos ecrãs que escolhem um fornecedor. */
    private const DE_FORNECEDORES = [
        'invoicing.suppliers.view',
        'invoicing.purchases.invoices.create',
        'invoicing.purchases.proformas.view',
        'invoicing.receipts.view',
        'invoicing.imports.view',
        'invoicing.settings.view',
    ];

    public function procurar(Request $request, string $tipo): JsonResponse
    {
        $deClientes = $tipo === 'clientes';

        abort_unless(
            $request->user()?->canAny($deClientes ? self::DE_CLIENTES : self::DE_FORNECEDORES),
            403,
            __('Sem permissão para esta operação.')
        );

        $id = (int) $request->query('id', 0);
        $termo = trim((string) $request->query('procura', ''));

        if ($id <= 0 && mb_strlen($termo) < 2) {
            return response()->json(['data' => []]);
        }

        $q = $deClientes
            ? Client::query()->with('paymentTerm')
            : Supplier::query();

        $q->where('tenant_id', activeTenantId());

        if ($id > 0) {
            // Pelo id não se filtra o activo: um documento antigo mostra o
            // fornecedor que tem, mesmo que já não se use.
            $q->whereKey($id);
        } else {
            // Os emissores de compras só oferecem fornecedores activos.
            if (! $deClientes) {
                $q->where('is_active', true);
            }

            $this->filtrar($q, $termo);
        }

        $partes = $q->orderBy('name')->limit(self::MAXIMO)->get();

        return response()->json([
            'data' => $partes->map(fn ($p) => $deClientes
                ? ClienteDoEmissor::linha($p)
                : ['id' => $p->id, 'name' => $p->name, 'nif' => $p->nif])->values(),
        ]);
    }

    /**
     * Nome, NIF, telefone ou telemóvel. A colação da base já ignora acentos e
     * maiúsculas («televisão» encontra «TELEVISAO»); os pontos e hífenes dos
     * nomes tiram-se à parte.
     */
    private function filtrar(Builder $q, string $termo): void
    {
        $como = '%' . addcslashes($termo, '%_\\') . '%';
        $compacto = preg_replace('/[^\pL\pN]+/u', '', $termo);

        $q->where(function (Builder $w) use ($como, $compacto) {
            $w->where('name', 'like', $como)
                ->orWhere('nif', 'like', $como)
                ->orWhere('phone', 'like', $como)
                ->orWhere('mobile', 'like', $como);

            if ($compacto !== '') {
                $w->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(name, '.', ''), '-', ''), ' ', '') LIKE ?",
                    ['%' . addcslashes($compacto, '%_\\') . '%']
                );
            }
        });
    }
}
