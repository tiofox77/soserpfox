<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoicing\SalesInvoiceResource;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Warehouse;
use App\Traits\DocumentosPorAutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * As facturas de venda, para o ecrã em React.
 *
 * ESTE CONTROLADOR NÃO É UMA PORTA NOVA PARA AS REGRAS. Repete, à letra, o que
 * o `App\Livewire\Invoicing\Sales\Invoices` já fazia: a mesma permissão, o
 * mesmo escopo por autor, os mesmos filtros e a mesma ordem. Uma API que
 * mostre um documento que o ecrã escondia não é uma API — é um buraco.
 *
 * Por isso usa o MESMO trait do escopo (`DocumentosPorAutor`) em vez de
 * reescrever a regra. Se a regra mudar, muda nos dois ao mesmo tempo.
 */
class SalesInvoiceApiController extends Controller
{
    use DocumentosPorAutor;

    protected function modeloDoDocumento(): string
    {
        return SalesInvoice::class;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can('invoicing.sales.invoices.view'),
            403,
            __('Sem permissão para ver facturas de venda.')
        );

        $filtros = $request->validate([
            'procura'   => ['nullable', 'string', 'max:120'],
            'estado'    => ['nullable', 'string', 'max:30'],
            'tipo'      => ['nullable', 'in:FT,FR'],
            'armazem'   => ['nullable', 'integer'],
            'autor'     => ['nullable', 'integer'],
            'de'        => ['nullable', 'date'],
            'ate'       => ['nullable', 'date'],
            'por_pagina'=> ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = $this->baseDoAutor()
            ->with(['client', 'warehouse', 'creator', 'series'])
            // O que já foi anulado por notas de crédito vem na mesma consulta:
            // é o que decide o `pode_creditar` de cada linha.
            ->comCreditado();

        if ($procura = ($filtros['procura'] ?? null)) {
            $query->where(function ($q) use ($procura) {
                $q->where('invoice_number', 'like', "%{$procura}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$procura}%"))
                    ->orWhereHas('series', function ($s) use ($procura) {
                        $s->where('series_code', 'like', "%{$procura}%")
                            ->orWhere('agt_series_id', 'like', "%{$procura}%");
                    });
            });
        }

        $query
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('invoice_type', $v))
            ->when($filtros['armazem'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            // O filtro por autor só vale a quem pode ver os documentos de
            // todos: para os outros, o escopo já fechou a porta e deixá-lo
            // passar aqui era dar a volta à permissão escolhendo um nome.
            ->when(
                ($filtros['autor'] ?? null) && $this->getVeDocumentosDeTodosProperty(),
                fn ($q) => $q->where('created_by', $filtros['autor'])
            )
            // Comparação directa e não whereDate: uma função sobre a coluna
            // impede o MySQL de usar o índice, e são milhares de linhas.
            ->when($filtros['de'] ?? null, fn ($q, $v) => $q->where('invoice_date', '>=', $v))
            ->when($filtros['ate'] ?? null, fn ($q, $v) => $q->where('invoice_date', '<=', $v));

        $pagina = $query
            ->orderByDesc('created_at')
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString();

        return SalesInvoiceResource::collection($pagina);
    }

    /**
     * O que o ecrã precisa de saber uma vez só: armazéns, estados, autores.
     *
     * Vem à parte da lista de propósito — muda raramente, e assim não viaja
     * outra vez a cada mudança de filtro.
     */
    public function opcoes(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->can('invoicing.sales.invoices.view'),
            403,
            __('Sem permissão para ver facturas de venda.')
        );

        return response()->json([
            'armazens' => Warehouse::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),

            'estados' => [
                ['valor' => 'draft', 'rotulo' => __('Rascunho')],
                ['valor' => 'sent', 'rotulo' => __('Emitida')],
                ['valor' => 'partially_paid', 'rotulo' => __('Parcialmente paga')],
                ['valor' => 'paid', 'rotulo' => __('Paga')],
                ['valor' => 'overdue', 'rotulo' => __('Vencida')],
                ['valor' => 'credited', 'rotulo' => __('Creditada')],
                ['valor' => 'cancelled', 'rotulo' => __('Anulada')],
            ],

            // Vazio para quem só vê as suas — os nomes dos colegas não são
            // dele. É o próprio trait que decide.
            'autores' => $this->getAutoresDosDocumentosProperty(),

            'permissoes' => [
                've_de_todos' => $this->getVeDocumentosDeTodosProperty(),
                'pode_criar' => (bool) $request->user()?->can('invoicing.sales.invoices.create'),
                'pode_creditar' => (bool) $request->user()?->can('invoicing.credit-notes.create'),
                'pode_receber' => (bool) $request->user()?->can('invoicing.receipts.create'),
            ],
        ]);
    }
}
