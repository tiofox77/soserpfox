<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoicing\SalesInvoiceResource;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\Warehouse;
use App\Services\Invoicing\SomasDasFacturas;
use App\Traits\DocumentosPorAutor;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Os seis caracteres finais de um número provisório do POS offline.
     *
     * O talão sai como PEND-20260817-A3F9C1, e esses seis são a cauda do
     * `local_uuid` da venda — que já fica gravado, portanto não foi preciso
     * guardar nada de novo. Aceita-se o número inteiro ou só a cauda, porque
     * quem lê um talão amarrotado ao telefone dita o que consegue.
     *
     * Devolve null quando a pesquisa não tem ar de número provisório: sem
     * isso, procurar por um nome ia bater contra os `local_uuid` todos e
     * trazer facturas que não têm nada que ver com o que se procurou.
     */
    public static function caudaDoNumeroProvisorio(?string $termo): ?string
    {
        $termo = trim((string) $termo);

        if (preg_match('/^PEND[-\s]?\d{6,8}[-\s]?([0-9A-Za-z]{6})$/i', $termo, $m)) {
            return $m[1];
        }

        return preg_match('/^[0-9A-Fa-f]{6}$/', $termo) ? $termo : null;
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

        $query = $this->comFiltros(
            $this->baseDoAutor()
                ->with(['client', 'warehouse', 'creator', 'series'])
                // O que já foi anulado por notas de crédito vem na mesma
                // consulta: é o que decide o `pode_creditar` de cada linha.
                ->comCreditado(),
            $filtros
        );

        $pagina = $query
            ->orderByDesc('created_at')
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString();

        /*
         * OS CARTÕES DO TOPO SOMAM O QUE ESTÁ FILTRADO, NÃO SÓ A PÁGINA.
         *
         * Uma soma da página seria um número que muda ao carregar em
         * «Seguinte». A conta corre no servidor, sobre a MESMA consulta —
         * mesmos filtros, mesmo escopo por autor — e a regra vive no serviço,
         * não aqui: ver o `SomasDasFacturas`.
         *
         * Consulta limpa, sem os `with()` nem o subselect do creditado: quem
         * soma não desenha linhas.
         */
        $somas = (new SomasDasFacturas)->de(
            $this->comFiltros($this->baseDoAutor(), $filtros)
        );

        return SalesInvoiceResource::collection($pagina)->additional([
            'meta' => ['somas' => $somas],
        ]);
    }

    /**
     * Os filtros da lista, aplicados a uma consulta qualquer.
     *
     * Vive à parte porque corre DUAS vezes: uma para as linhas da página,
     * outra para as somas dos cartões. Escrever os filtros nos dois sítios era
     * garantir que um dia o cartão somava um universo diferente do da tabela.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function comFiltros(Builder $query, array $filtros): Builder
    {
        if ($procura = ($filtros['procura'] ?? null)) {
            $query->where(function ($q) use ($procura) {
                $q->where('invoice_number', 'like', "%{$procura}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$procura}%"))
                    ->orWhereHas('series', function ($s) use ($procura) {
                        $s->where('series_code', 'like', "%{$procura}%")
                            ->orWhere('agt_series_id', 'like', "%{$procura}%");
                    });

                // Pelo número provisório do talão offline.
                //
                // Uma venda feita sem rede sai com um talão a dizer
                // PEND-20260817-A3F9C1, e é esse papel que o cliente leva. Ao
                // sincronizar, a venda recebe o número fiscal a sério e o
                // provisório deixa de aparecer em lado nenhum — quem voltasse
                // com o talão a pedir a factura não era encontrado.
                if ($cauda = self::caudaDoNumeroProvisorio($procura)) {
                    $q->orWhere('local_uuid', 'like', '%' . $cauda);
                }
            });
        }

        return $query
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

                // Quem pode EDITAR pode fechar a conta à mão («marcar como
                // paga»). É a permissão do ecrã de sempre, e não a dos
                // recibos: marcar como paga não lança dinheiro nenhum.
                'pode_editar' => (bool) $request->user()?->can('invoicing.sales.invoices.edit'),
            ],
        ]);
    }

    /**
     * MARCAR A FACTURA COMO PAGA — sem recibo, à mão.
     *
     * É o segundo botão verde da lista de sempre, e não se confunde com o
     * primeiro: «Registar Pagamento» abre o recibo e lança o dinheiro; este
     * apenas FECHA A CONTA de uma factura que já foi paga por fora e cujo
     * recibo ninguém vai lançar. Quem quer o dinheiro registado usa o outro.
     *
     * As três recusas são as do ecrã Livewire, à letra:
     *
     * 1. A FACTURA-RECIBO já é paga no acto da venda. Marcá-la outra vez seria
     *    um convite a contar o mesmo recebimento duas vezes. O botão não
     *    aparece — mas a defesa tem de estar aqui, porque a porta é HTTP.
     * 2. O que já está `paid` ou `cancelled` não se marca de novo.
     * 3. E o escopo por autor manda: quem só vê as suas não fecha a dos outros
     *    (é o `baseDoAutor` que o garante, não um `if`).
     */
    public function marcarComoPaga(Request $request, int $id): JsonResponse
    {
        abort_unless(
            $request->user()?->can('invoicing.sales.invoices.edit'),
            403,
            __('Sem permissão para alterar facturas de venda.')
        );

        $factura = $this->baseDoAutor()->findOrFail($id);

        if (($factura->invoice_type ?? 'FT') === 'FR') {
            return response()->json(['message' => __('A Fatura-Recibo já é paga no acto da venda.')], 422);
        }

        if (in_array($factura->status, ['paid', 'cancelled'], true)) {
            // Duas frases inteiras, e não uma frase colada a um adjectivo:
            // noutras línguas a concordância e a ordem não são as portuguesas.
            return response()->json([
                'message' => $factura->status === 'paid'
                    ? __('Esta fatura já está paga.')
                    : __('Esta fatura já está cancelada.'),
            ], 422);
        }

        $factura->status = 'paid';
        $factura->save();

        return response()->json([
            'estado' => $factura->status,
            'message' => __('Fatura marcada como paga!'),
        ]);
    }
}
