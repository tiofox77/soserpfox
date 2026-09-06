<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\EmissorDeCompras;
use App\Services\Invoicing\TiposDeDocumento;
use App\Traits\DocumentosPorAutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * As listas de documentos que têm todas a mesma forma.
 *
 * Proformas de venda e de compra, orçamentos, facturas de compra e recibos.
 * O que muda entre elas está no `TiposDeDocumento`; aqui há uma consulta só.
 *
 * O ESCOPO POR AUTOR APLICA-SE A TODOS. Quem só vê os documentos que emitiu
 * continua a ver só os seus, seja qual for o tipo — é o mesmo trait que as
 * listas em Blade usam, e não uma segunda regra escrita aqui.
 */
class DocumentosApiController extends Controller
{
    use DocumentosPorAutor;

    /** Preenchido a cada pedido, a partir do tipo pedido no endereço. */
    private string $modeloActual = '';

    /** O slug pedido: é dele que saem as acções que só um tipo tem. */
    private string $tipoActual = '';

    /**
     * As acções que ESTE utilizador pode fazer a uma factura de compra.
     *
     * Decidido uma vez por pedido e não por linha: são centenas de linhas e a
     * permissão é a mesma para todas.
     *
     * @var array<string,bool>
     */
    private array $podeNaCompra = ['anular' => false, 'marcar_paga' => false];

    protected function modeloDoDocumento(): string
    {
        return $this->modeloActual;
    }

    public function index(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($request, $tipo);

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:30'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $numero = $def['numero'];
        $data = $def['data'];
        $relacao = $def['relacao'];

        // A série vem na mesma consulta: é dela que sai o número INTERNO, o
        // que a empresa reconhece. Sem isto era uma ida à base por linha.
        $comSerie = $this->temNumeracaoDupla($def['modelo']);

        $query = $this->baseDoAutor()->with($comSerie ? [$relacao, 'series'] : $relacao);

        if ($procura = ($filtros['procura'] ?? null)) {
            $query->where(function ($q) use ($procura, $numero, $relacao, $comSerie) {
                $q->where($numero, 'like', "%{$procura}%")
                    ->orWhereHas($relacao, fn ($p) => $p->where('name', 'like', "%{$procura}%"));

                /*
                 * PROCURA-SE PELA SÉRIE INTERNA — é a que aparece primeiro.
                 *
                 * O `..._number` gravado leva a série da AGT (NC4226S46906N),
                 * e ninguém procura um documento por aquilo: escreve-se SOSNC.
                 * A lista de facturas já procurava pelas duas; esta, que serve
                 * outros oito documentos, procurava só pelo número gravado e
                 * não encontrava nada pela série da casa.
                 */
                if ($comSerie) {
                    $q->orWhereHas('series', function ($s) use ($procura) {
                        $s->where('series_code', 'like', "%{$procura}%")
                            ->orWhere('agt_series_id', 'like', "%{$procura}%");
                    });
                }
            });
        }

        $query
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('status', $v))
            // Comparação directa e não whereDate: uma função sobre a coluna
            // impede o MySQL de usar o índice.
            ->when($filtros['de'] ?? null, fn ($q, $v) => $q->where($data, '>=', $v))
            ->when($filtros['ate'] ?? null, fn ($q, $v) => $q->where($data, '<=', $v));

        $pagina = $query->orderByDesc($data)->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($d) => $this->linha($d, $def))->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function opcoes(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($request, $tipo);

        return response()->json([
            'titulo' => $def['titulo'],
            'parte' => $def['parte'],
            'rota' => $def['rota'],
            'tem_saldo' => $def['tem_saldo'],
            // O que a coluna do Portal AGT diz neste tipo de documento.
            'agt' => $def['agt'],
            // Pagar é emitir um recibo: a permissão é essa.
            'pode_pagar' => $def['tem_saldo'] && (bool) $request->user()?->can('invoicing.receipts.create'),

            /*
             * DUPLICAR: onde a lista o oferece está no `TiposDeDocumento`, e a
             * permissão é a de CRIAR o mesmo tipo de documento — duplicar é
             * começar um novo, não ler o antigo.
             */
            'pode_duplicar' => TiposDeDocumento::eDuplicavel($tipo)
                && (bool) $request->user()?->can(str_replace('.view', '.create', $def['permissao'])),

            // Os estados que existem MESMO nesta tabela, e não uma lista
            // inventada: cada documento tem os seus, e um filtro com opções
            // que nunca devolvem nada é pior do que não ter filtro.
            'estados' => $this->baseDoAutor()
                ->select('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->filter()
                ->map(fn ($e) => ['valor' => $e, 'rotulo' => $this->rotuloDoEstado($e)])
                ->values(),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** Resolve o tipo, exige a permissão dele, e arma o trait do escopo. */
    private function definicao(Request $request, string $tipo): array
    {
        abort_unless(TiposDeDocumento::existe($tipo), 404, __('Tipo de documento desconhecido.'));

        $def = TiposDeDocumento::um($tipo);

        abort_unless(
            $request->user()?->can($def['permissao']),
            403,
            __('Sem permissão para ver estes documentos.')
        );

        $this->modeloActual = $def['modelo'];
        $this->tipoActual = $tipo;

        // As acções da factura de compra: anular (que é o eliminar dela) e
        // marcar como paga. Só nesta lista existem — as outras não têm o que
        // anular por aqui.
        if ($tipo === 'facturas-compra') {
            $this->podeNaCompra = [
                'anular' => (bool) $request->user()?->can('invoicing.purchases.invoices.delete'),
                'marcar_paga' => (bool) $request->user()?->can('invoicing.purchases.invoices.edit'),
            ];
        }

        return $def;
    }

    /** Os documentos com série ligada têm DOIS números: o interno e o da AGT. */
    private function temNumeracaoDupla(string $modelo): bool
    {
        return method_exists($modelo, 'numeroInterno');
    }

    private function linha($d, array $def): array
    {
        $parte = $d->{$def['relacao']};
        $valor = round((float) ($d->{$def['valor']} ?? 0), 2);
        $duplo = $this->temNumeracaoDupla($def['modelo']);

        $linha = [
            'id' => $d->id,
            // A INTERNA PRIMEIRO, a da AGT logo abaixo. O que está gravado na
            // coluna é o número na série da AGT — críptico, e não é por ele
            // que a empresa chama o documento.
            'numero' => $duplo ? $d->numeroInterno() : $d->{$def['numero']},
            'numero_agt' => $duplo ? $d->numeroAgt() : null,
            'agt' => $this->selo($d, $def),
            'parte' => $parte?->name ?? __('Consumidor Final'),
            'data' => optional($d->{$def['data']})->toDateString() ?? (string) $d->{$def['data']},
            'estado' => $d->status,
            'estado_rotulo' => $this->rotuloDoEstado($d->status),
            'estado_cor' => $this->corDoEstado($d->status),
            'valor' => $valor,
        ];

        if ($def['tem_saldo']) {
            $pago = round((float) ($d->paid_amount ?? 0), 2);
            $linha['pago'] = $pago;
            // Pelo saldo, como em todo o lado: o que falta, não o total.
            $linha['saldo'] = in_array($d->status, ['paid', 'cancelled'], true)
                ? 0.0
                : max(0.0, round($valor - $pago, 2));
        }

        /*
         * AS ACÇÕES QUE ESTA FACTURA DE COMPRA AINDA ACEITA.
         *
         * Quem responde é o `EmissorDeCompras`, o mesmo que depois aceita ou
         * recusa a acção: um botão que aparece e depois recusa é pior do que
         * um botão que não aparece.
         */
        if ($this->tipoActual === 'facturas-compra') {
            $linha['pode_anular'] = $this->podeNaCompra['anular'] && EmissorDeCompras::podeAnular($d);
            $linha['pode_marcar_paga'] = $this->podeNaCompra['marcar_paga'] && EmissorDeCompras::podeMarcarPaga($d);
        }

        return $linha;
    }

    /**
     * O SELO DO PORTAL AGT, decidido do lado de cá.
     *
     * O pedido foi directo: «nas tabelas deve aparecer se foi enviado ou deu
     * erro na AGT». Antes só as facturas o mostravam, e quem emitia uma nota
     * de crédito ficava sem saber se ela tinha sido aceite.
     *
     * O selo distingue quatro coisas, e a quarta é a que evita o alarme falso:
     * aceite, à espera, recusada — e NÃO COMUNICÁVEL, para a proforma, o
     * orçamento e o adiantamento, que não são documentos fiscais e nunca são
     * enviados. Sem essa distinção a coluna dizia «pendente de envio» numa
     * proforma e mandava alguém procurar um envio que nunca vai existir.
     *
     * @return array{natureza: string, estado: ?string, rotulo: string, cor: string}
     */
    private function selo($d, array $def): array
    {
        $natureza = $def['agt'] ?? 'nao-fiscal';
        $estado = strtolower(trim((string) ($d->agt_status ?? '')));

        if ($natureza === 'nao-fiscal') {
            return ['natureza' => $natureza, 'estado' => null,
                'rotulo' => __('Não comunicável à AGT'), 'cor' => 'neutra'];
        }

        // Quem comunica uma factura de compra é o fornecedor que a emitiu.
        if ($natureza === 'fornecedor') {
            return ['natureza' => $natureza, 'estado' => null,
                'rotulo' => __('Responsabilidade do fornecedor'), 'cor' => 'neutra'];
        }

        // Um rascunho ainda não foi emitido: não há envio nenhum por fazer.
        if (($d->status ?? null) === 'draft') {
            return ['natureza' => $natureza, 'estado' => null,
                'rotulo' => __('Ainda não emitida'), 'cor' => 'neutra'];
        }

        [$rotulo, $cor] = match ($estado) {
            'validated', 'accepted', 'approved', 'success' => [__('Emitida no Portal AGT'), 'bom'],
            'submitted', 'processing', 'sent' => [__('Enviada — aguarda AGT'), 'primaria'],
            'rejected', 'failed', 'error' => [__('Falhou — reenviar à AGT'), 'perigo'],
            default => [__('Pendente de envio à AGT'), 'aviso'],
        };

        return ['natureza' => $natureza, 'estado' => $estado ?: null, 'rotulo' => $rotulo, 'cor' => $cor];
    }

    private function rotuloDoEstado(?string $estado): string
    {
        return match ($estado) {
            'draft' => __('Rascunho'),
            'sent', 'issued' => __('Emitido'),
            'pending' => __('Pendente'),
            'partially_paid' => __('Parcialmente pago'),
            'paid' => __('Pago'),
            'overdue' => __('Vencido'),
            'accepted' => __('Aceite'),
            'rejected' => __('Recusado'),
            'converted' => __('Convertido'),
            'expired' => __('Expirado'),
            'cancelled' => __('Anulado'),
            default => ucfirst((string) $estado),
        };
    }

    private function corDoEstado(?string $estado): string
    {
        return match ($estado) {
            'paid', 'accepted', 'converted' => 'bom',
            'draft', 'expired' => 'neutra',
            'cancelled', 'rejected' => 'perigo',
            'overdue' => 'aviso',
            default => 'primaria',
        };
    }
}
