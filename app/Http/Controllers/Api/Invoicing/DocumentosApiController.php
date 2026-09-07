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

    /** Se ESTE utilizador pode apagar documentos deste tipo. Decidido uma vez. */
    private bool $podeApagar = false;

    /** Se pode converter em factura. Também uma vez, e não por linha. */
    private bool $podeConverter = false;

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
            /*
             * O MOTIVO — só nas notas, que são as únicas que o têm.
             *
             * A lista em Blade tinha-o em caixa própria: uma nota de crédito
             * de devolução e uma de correcção contam histórias diferentes, e
             * é por aqui que se separam. Onde o tipo não o declara, o filtro é
             * RECUSADO em vez de ignorado — ignorado em silêncio, devolvia a
             * lista toda e fazia acreditar que filtrava.
             */
            'motivo' => [empty($def['motivo']) ? 'prohibited' : 'nullable', 'string', 'max:40'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $data = $def['data'];

        $comRelacoes = $this->temNumeracaoDupla($def['modelo'])
            ? [$def['relacao'], 'series']
            : [$def['relacao']];

        // A FACTURA DE ORIGEM das notas vem na mesma consulta: a lista mostra o
        // número dela em coluna própria, e ir buscá-la por linha eram tantas
        // consultas quantas as notas na página.
        if (! empty($def['origem'])) {
            $comRelacoes[] = $def['origem']['relacao'];
        }

        $query = $this->filtrada($def, $filtros)->with($comRelacoes);

        $pagina = $query->orderByDesc($data)->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString();

        /*
         * OS CARTÕES DO TOPO CONTAM A LISTA FILTRADA INTEIRA, não a página.
         *
         * A lista em Blade tinha cinco — total, rascunhos, os emitidos, os
         * pagos e o valor — e contava a tabela toda. Ao migrar, ficaram dois a
         * somar as quinze linhas à vista e a dizê-lo em letra pequena: um
         * «valor» que muda ao virar a página não é um valor.
         *
         * O AGRUPAMENTO POR ESTADO vem de cá porque é o servidor que sabe
         * quais existem mesmo nesta tabela — a lista de estados de uma
         * proforma não é a de uma nota de crédito.
         */
        $resumo = $this->filtrada($def, $filtros)
            ->toBase()
            ->selectRaw("status, COUNT(*) as quantos, SUM({$def['valor']}) as valor")
            ->groupBy('status')
            ->get();

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($d) => $this->linha($d, $def))->values(),
            'resumo' => [
                'total' => (int) $resumo->sum('quantos'),
                'valor' => round((float) $resumo->sum('valor'), 2),
                'por_estado' => $resumo->map(fn ($l) => [
                    'estado' => $l->status,
                    'rotulo' => $this->rotuloDoEstado($l->status),
                    'cor' => $this->corDoEstado($l->status),
                    'quantos' => (int) $l->quantos,
                    'valor' => round((float) $l->valor, 2),
                ])->sortByDesc('quantos')->values(),
            ],
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
            // A frase por baixo do título e o rótulo do botão de criar, como
            // a faixa em Blade os tinha: «Devoluções, descontos e correções»
            // diz o que uma nota de crédito é a quem nunca emitiu nenhuma.
            'descricao' => __($def['descricao'] ?? ''),
            'novo' => __($def['novo'] ?? 'Novo documento'),
            // Criar é outra permissão: quem só vê a lista não a cria.
            'pode_criar' => (bool) $request->user()?->can(str_replace('.view', '.create', $def['permissao'])),
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

            // APAGAR e CONVERTER: o ecrã só desenha o botão onde ele existe e
            // este utilizador o pode usar.
            'pode_apagar' => $this->podeApagar,
            'pode_converter' => $this->podeConverter,
            // Onde há histórico de conversões há também o botão que o abre.
            'tem_historico' => ! empty($def['historico']),

            /*
             * A COLUNA DO PRAZO — vencimento nas facturas de compra, validade
             * nas propostas, nenhuma nos recibos e nas notas. O rótulo vem
             * daqui porque é o esquema que sabe de que documento se trata.
             */
            'prazo' => ! empty($def['prazo']) ? __($def['prazo']['rotulo']) : null,

            // A FACTURA DE ORIGEM das notas, e o cabeçalho da coluna dela.
            'origem' => ! empty($def['origem']) ? __($def['origem']['rotulo']) : null,

            // Os motivos que ESTE tipo de nota tem. Lista fechada, do esquema:
            // uma nota de crédito não se justifica com «juros».
            'motivos' => collect($def['motivo'] ?? [])
                ->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => __($rotulo)])
                ->values(),

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

    /**
     * A FICHA DE UM DOCUMENTO — o modal de VER que a lista em Blade tinha.
     *
     * Quem só quer conferir um documento não tem de abrir o editor (onde se
     * estraga um por engano) nem a pré-visualização (que é uma página inteira,
     * feita para imprimir). Isto é o que se olha de relance: quem, quando, o
     * que leva e quanto dá.
     *
     * O RECIBO E O ADIANTAMENTO NÃO TÊM LINHAS — não são documentos de
     * mercadoria, são dinheiro. A ficha mostra-lhes o cabeçalho e os totais, e
     * a tabela das linhas não aparece em vez de aparecer vazia.
     */
    public function mostrar(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($request, $tipo);

        $d = $this->baseDoAutor()->findOrFail($id);

        $parte = $d->{$def['relacao']};
        $temLinhas = method_exists($d, 'items');

        $linhas = $temLinhas
            ? $d->items()->get()->map(fn ($l) => [
                'descricao' => $l->description ?: $l->product_name,
                'unidade' => $l->unit,
                'quantidade' => round((float) $l->quantity, 3),
                'preco' => round((float) $l->unit_price, 2),
                'desconto' => round((float) ($l->discount_percent ?? 0), 2),
                'taxa' => round((float) ($l->tax_rate ?? 0), 2),
                'total' => round((float) $l->total, 2),
            ])->values()
            : collect();

        return response()->json([
            'numero' => $this->temNumeracaoDupla($def['modelo']) ? $d->numeroInterno() : $d->{$def['numero']},
            'numero_agt' => $this->temNumeracaoDupla($def['modelo']) ? $d->numeroAgt() : null,

            'parte' => [
                'nome' => $parte?->name ?? __('Consumidor Final'),
                'nif' => $parte?->nif,
                'email' => $parte?->email,
                'telefone' => $parte?->phone,
            ],

            'data' => optional($d->{$def['data']})->toDateString(),
            'prazo' => ! empty($def['prazo'])
                ? optional($d->{$def['prazo']['coluna']})->toDateString()
                : null,
            'prazo_rotulo' => ! empty($def['prazo']) ? __($def['prazo']['rotulo']) : null,

            'estado_rotulo' => $this->rotuloDoEstado($d->status),
            'estado_cor' => $this->corDoEstado($d->status),
            'agt' => $this->selo($d, $def),

            // A região fiscal sai no documento e viaja para a AGT: continental,
            // Cabinda e as outras têm taxas próprias.
            'regiao_fiscal' => $d->tax_country_region ?? null,
            'notas' => $d->notes ?? null,

            /*
             * O BLOCO FISCAL, quando o documento tem um.
             *
             * O modal das notas mostrava-o e é o que responde à pergunta que se
             * faz a seguir a emitir: «foi aceite?». Sai o hash do SAFT abreviado
             * (os quatro primeiros caracteres, como sempre — o hash inteiro não
             * cabe nem serve para nada aqui), o estado SAFT por extenso, e a
             * referência que a AGT devolveu.
             *
             * NULL numa proforma: não é documento fiscal e não tem nada disto.
             */
            'fiscal' => $this->blocoFiscal($d, $def),

            /*
             * O DOCUMENTO RECTIFICADO, nas notas — e a expressão do Art. 12.º.
             *
             * Uma nota corrige uma factura, e a factura tem de estar escrita na
             * ficha: é o que liga as duas na conferência.
             */
            'rectifica' => ! empty($def['origem']) ? (function () use ($d, $def) {
                $o = $d->{$def['origem']['relacao']};

                return [
                    'rotulo' => __($def['origem']['rotulo']),
                    'numero' => $o
                        ? (method_exists($o, 'numeroInterno') ? $o->numeroInterno() : $o->invoice_number)
                        : null,
                    'id' => $o?->id,
                    'motivo' => isset($def['motivo'][$d->reason]) ? __($def['motivo'][$d->reason]) : $d->reason,
                    'expressao' => $d->reason_text ?: __('Rectificação'),
                ];
            })() : null,

            'tem_linhas' => $temLinhas,
            'linhas' => $linhas,

            'totais' => [
                'subtotal' => round((float) ($d->subtotal ?? 0), 2),
                // Os dois descontos que o documento tem: o comercial (na linha
                // ou no total) e o financeiro (pronto pagamento).
                'desconto_comercial' => round(
                    (float) ($d->discount_commercial ?? 0) + (float) ($d->discount_amount ?? 0),
                    2
                ),
                'desconto_financeiro' => round((float) ($d->discount_financial ?? 0), 2),
                'imposto' => round((float) ($d->tax_amount ?? 0), 2),
                'total' => round((float) $d->{$def['valor']}, 2),
            ],

            /*
             * IEC E IMPOSTO DE SELO, se os houver.
             *
             * Foram declarados à AGT linha a linha (`LineTax`), e sem eles o
             * total NÃO RECONCILIA: quem soma o subtotal com o IVA fica a
             * dever a diferença sem perceber de onde vem. O modal em Blade
             * mostrava-os por isso mesmo.
             */
            'impostos_extra' => $this->impostosExtra($d, $temLinhas),
        ]);
    }

    /**
     * O BLOCO FISCAL de um documento — hash, estado SAFT e submissão à AGT.
     *
     * SÓ NOS DOCUMENTOS FISCAIS. Uma proforma, um orçamento ou um adiantamento
     * nunca são enviados: um bloco vazio a dizer «não submetido» num deles
     * mandava alguém procurar um envio que nunca vai existir.
     *
     * @return array<string,mixed>|null
     */
    private function blocoFiscal($d, array $def): ?array
    {
        /*
         * SÓ NO QUE A EMPRESA COMUNICA.
         *
         * `nao-fiscal` (proforma, orçamento, adiantamento) nunca é enviado, e
         * `fornecedor` (factura de compra) é comunicado por quem a emitiu — as
         * colunas do envio nem sequer existem nessa tabela. Um bloco a dizer
         * «não submetido» em qualquer dos casos mandava alguém procurar um
         * envio que nunca vai existir.
         */
        if (($def['agt'] ?? 'nao-fiscal') !== 'propria') {
            return null;
        }

        // O ESTADO SAFT por extenso. É uma letra na coluna (`invoice_status`),
        // e sozinha não diz nada a quem a lê pela primeira vez.
        $estadosSaft = ['N' => __('N — Normal'), 'F' => __('F — Facturado'), 'A' => __('A — Anulado')];
        $letra = (string) ($d->invoice_status ?? '');

        return [
            // Os quatro primeiros caracteres, como sempre: o hash inteiro não
            // cabe e não serve para conferir nada à vista.
            'hash' => filled($d->saft_hash) ? substr((string) $d->saft_hash, 0, 4) . '…' : null,
            'estado_saft' => $estadosSaft[$letra] ?? ($letra ?: null),
            'agt_estado' => $d->agt_status ?: null,
            'agt_referencia' => $d->agt_reference ?: null,
            /*
             * A DATA DE SUBMISSÃO passa por `optional()`.
             *
             * Nenhum modelo convertia esta coluna e vinha texto cru: o
             * `->format()` rebentava e o modal dava 500. O cast já lá está, mas
             * o ecrã não volta a depender disso.
             */
            'agt_submetido_em' => optional($d->agt_submitted_at)->toDateTimeString(),
        ];
    }

    /**
     * Os impostos declarados à parte do IVA, somados por tipo.
     *
     * @return list<array{tipo: string, valor: float}>
     */
    private function impostosExtra($d, bool $temLinhas): array
    {
        if (! $temLinhas) {
            return [];
        }

        $linhas = $d->items()->get();
        $primeira = $linhas->first();

        if (! $primeira) {
            return [];
        }

        return \App\Models\Invoicing\LineTax::where('line_type', get_class($primeira))
            ->whereIn('line_id', $linhas->pluck('id'))
            ->get()
            ->groupBy('tax_type')
            ->map(fn ($grupo, $tipo) => [
                'tipo' => (string) $tipo,
                'valor' => round((float) $grupo->sum('tax_amount'), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * APAGAR UM DOCUMENTO — só onde o esquema o permite, e só o que ainda dá.
     *
     * Uma proposta convertida em factura não se elimina: a factura aponta para
     * ela e ficaria a referir um documento que já não existe. É a mesma regra
     * do ecrã em Blade, dita aqui porque é aqui que se cumpre — esconder o
     * botão é conveniência, nunca segurança.
     *
     * As FACTURAS DE COMPRA não passam por aqui: anulam-se (que é o eliminar
     * delas) para o stock que entrou ser revertido.
     */
    public function destroy(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($request, $tipo);

        abort_unless(! empty($def['apaga']), 404, __('Este documento não se elimina por aqui.'));
        abort_unless($this->podeApagar, 403, __('Sem permissão para eliminar este documento.'));

        $d = $this->baseDoAutor()->findOrFail($id);

        if (in_array($d->status, ['converted', 'cancelled'], true)) {
            return response()->json([
                'message' => __('Este documento já foi convertido ou anulado e não pode ser eliminado.'),
            ], 422);
        }

        $d->delete();

        return response()->json(['message' => __('Documento eliminado.')]);
    }

    /**
     * CONVERTER UMA PROPOSTA EM FACTURA.
     *
     * A conta é do MODELO (`convertToInvoice`), que é onde sempre esteve e
     * onde já está resolvido o que interessa: a taxa é a de HOJE e não a que
     * ficou gravada na proposta — uma proforma feita antes de a empresa mudar
     * de regime tem a taxa antiga na linha, e copiá-la fazia nascer hoje uma
     * factura a liquidar IVA que a empresa já não pode cobrar.
     *
     * A factura nasce em RASCUNHO. Converter não é emitir: quem converte
     * confere e emite depois, no editor.
     */
    public function converter(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($request, $tipo);

        abort_unless(! empty($def['converte']), 404, __('Este documento não se converte em factura.'));
        abort_unless($this->podeConverter, 403, __('Sem permissão para emitir facturas.'));

        $d = $this->baseDoAutor()->findOrFail($id);

        try {
            $factura = $d->convertToInvoice();
        } catch (\Throwable $e) {
            // O modelo recusa por razões que a pessoa tem de ler — «proforma
            // já convertida», por exemplo. Um 500 mandava-a adivinhar.
            return response()->json([
                'message' => __('Não foi possível converter: :erro', ['erro' => $e->getMessage()]),
            ], 422);
        }

        return response()->json([
            'message' => __('Convertido em factura :numero. Fica em rascunho até ser emitida.', [
                'numero' => $factura->invoice_number,
            ]),
            'factura' => [
                'id' => $factura->id,
                'numero' => $factura->invoice_number,
                'rota' => $tipo === 'proformas-compra'
                    ? '/invoicing/purchases/invoices'
                    : '/invoicing/sales/invoices',
            ],
        ]);
    }

    /**
     * O HISTÓRICO DE CONVERSÕES: que facturas já saíram desta proposta.
     *
     * A mesma proposta pode ser convertida MAIS DO QUE UMA VEZ — o ecrã de
     * sempre não bloqueava a segunda conversão, de propósito (um fornecimento
     * repetido factura-se a partir da mesma proforma). Este ecrã é o que evita
     * a duplicação por engano: mostra o que já saiu antes de se converter
     * outra vez.
     */
    public function historico(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($request, $tipo);

        abort_unless(! empty($def['historico']), 404, __('Este documento não tem histórico de conversões.'));

        $d = $this->baseDoAutor()->findOrFail($id);

        $relacao = $d->{$def['historico']};

        // `hasOne` na proforma de compra, `hasMany` nas de venda: normaliza-se
        // para uma lista, e o ecrã não tem de saber a diferença.
        $facturas = $relacao instanceof \Illuminate\Support\Collection
            ? $relacao
            : collect($relacao ? [$relacao] : []);

        return response()->json([
            'documento' => [
                'numero' => $this->temNumeracaoDupla($def['modelo'])
                    ? $d->numeroInterno()
                    : $d->{$def['numero']},
                'parte' => $d->{$def['relacao']}?->name ?? __('Consumidor Final'),
                'data' => optional($d->{$def['data']})->toDateString(),
                'valor' => round((float) $d->{$def['valor']}, 2),
                'estado_rotulo' => $this->rotuloDoEstado($d->status),
                'estado_cor' => $this->corDoEstado($d->status),
            ],
            'facturas' => $facturas->map(fn ($f) => [
                'id' => $f->id,
                'numero' => method_exists($f, 'numeroInterno') ? $f->numeroInterno() : $f->invoice_number,
                'data' => optional($f->invoice_date)->toDateString(),
                'vencimento' => optional($f->due_date)->toDateString(),
                'total' => round((float) $f->total, 2),
                'estado_rotulo' => $this->rotuloDoEstado($f->status),
                'estado_cor' => $this->corDoEstado($f->status),
                'rota' => $tipo === 'proformas-compra'
                    ? '/invoicing/purchases/invoices'
                    : '/invoicing/sales/invoices',
            ])->values(),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * A consulta com os filtros postos, e SEM as relações da listagem.
     *
     * Serve duas vezes: a lista (que lhe junta o cliente, a série e a factura
     * de origem) e o resumo dos cartões (que só agrupa por estado). Escrita
     * uma vez, os dois falam sempre do mesmo conjunto — com o filtro «pagas»
     * posto, os cartões falam das pagas.
     */
    private function filtrada(array $def, array $filtros): \Illuminate\Database\Eloquent\Builder
    {
        $numero = $def['numero'];
        $data = $def['data'];
        $relacao = $def['relacao'];
        $comSerie = $this->temNumeracaoDupla($def['modelo']);

        return $this->baseDoAutor()
            ->when($filtros['procura'] ?? null, fn ($q, $procura) => $q->where(function ($w) use ($procura, $numero, $relacao, $comSerie) {
                $w->where($numero, 'like', "%{$procura}%")
                    ->orWhereHas($relacao, fn ($p) => $p->where('name', 'like', "%{$procura}%"));

                /*
                 * PROCURA-SE PELA SÉRIE INTERNA — é a que aparece primeiro.
                 *
                 * O `..._number` gravado leva a série da AGT (NC4226S46906N),
                 * e ninguém procura um documento por aquilo: escreve-se SOSNC.
                 */
                if ($comSerie) {
                    $w->orWhereHas('series', function ($s) use ($procura) {
                        $s->where('series_code', 'like', "%{$procura}%")
                            ->orWhere('agt_series_id', 'like', "%{$procura}%");
                    });
                }
            }))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('status', $v))
            // Comparação directa e não whereDate: uma função sobre a coluna
            // impede o MySQL de usar o índice.
            ->when($filtros['de'] ?? null, fn ($q, $v) => $q->where($data, '>=', $v))
            ->when($filtros['ate'] ?? null, fn ($q, $v) => $q->where($data, '<=', $v))
            // O motivo, nas notas: a coluna chama-se `reason` nas duas.
            ->when($filtros['motivo'] ?? null, fn ($q, $v) => $q->where('reason', $v));
    }

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

        // Apagar e converter: decididos uma vez por pedido, não por linha —
        // são centenas de linhas e a permissão é a mesma para todas.
        $this->podeApagar = ! empty($def['apaga']) && (bool) $request->user()?->can($def['apaga']);

        /*
         * CONVERTER EM FACTURA É CRIAR UMA FACTURA.
         *
         * A permissão não é a de ver a proposta nem a de a editar: nasce um
         * documento novo, e quem não pode facturar não pode fazer nascer uma
         * factura por este atalho.
         */
        $this->podeConverter = ! empty($def['converte']) && (bool) $request->user()?->can(
            str_starts_with($tipo, 'proformas-compra')
                ? 'invoicing.purchases.invoices.create'
                : 'invoicing.sales.invoices.create'
        );

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

        /*
         * O PRAZO: vencimento nas facturas de compra, validade nas propostas.
         *
         * A lista em Blade tinha-o em coluna própria, e não é decoração: uma
         * proforma caducada não se converte, e uma factura de compra vencida é
         * dinheiro em atraso. `expirado` vem decidido de cá — a comparação com
         * «hoje» é a do servidor, e não a do relógio de quem está a ver.
         */
        if (! empty($def['prazo'])) {
            $prazo = $d->{$def['prazo']['coluna']};

            $linha['prazo'] = optional($prazo)->toDateString() ?? ($prazo ? (string) $prazo : null);
            $linha['expirado'] = $prazo
                && \Illuminate\Support\Carbon::parse($prazo)->isPast()
                && ! in_array($d->status, ['paid', 'cancelled', 'converted'], true);
        }

        // A FACTURA DE ORIGEM das notas, com o número que a empresa reconhece.
        if (! empty($def['origem'])) {
            $origem = $d->{$def['origem']['relacao']};

            $linha['origem'] = $origem
                ? [
                    'id' => $origem->id,
                    'numero' => method_exists($origem, 'numeroInterno')
                        ? $origem->numeroInterno()
                        : ($origem->invoice_number ?? (string) $origem->id),
                ]
                : null;
        }

        // O MOTIVO da nota, com o rótulo já traduzido: o ecrã não guarda uma
        // segunda lista de motivos para os escrever por extenso.
        if (! empty($def['motivo'])) {
            $linha['motivo'] = $d->reason;
            $linha['motivo_rotulo'] = isset($def['motivo'][$d->reason])
                ? __($def['motivo'][$d->reason])
                : ($d->reason ?: null);
        }

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

        /*
         * APAGAR — e só o que AINDA se pode apagar.
         *
         * Uma proposta convertida em factura não se elimina: a factura aponta
         * para ela, e ficaria a referir um documento que já não existe. É a
         * mesma regra que o ecrã em Blade aplicava, decidida do lado de cá
         * porque um botão que aparece e depois recusa é pior do que um botão
         * que não aparece.
         */
        if ($this->podeApagar) {
            $linha['pode_apagar'] = ! in_array($d->status, ['converted', 'cancelled'], true);
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
