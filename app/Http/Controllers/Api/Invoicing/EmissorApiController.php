<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoicing\QuoteTemplate;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Invoicing\CalculadoraDeDocumento;
use App\Services\Invoicing\DuplicaDocumento;
use App\Services\Invoicing\TaxResolver;
use App\Services\Invoicing\TiposDeDocumento;
use App\Support\Geografia;
use App\Traits\DocumentosPorAutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EMITIR PROPOSTAS — proformas de venda e de compra, e orçamentos.
 *
 * TRÊS REGRAS, E SÃO ELAS QUE JUSTIFICAM ESTE FICHEIRO EXISTIR:
 *
 * 1. A CONTA É FEITA AQUI, SEMPRE. O ecrã pergunta os totais a cada alteração
 *    (`/calcular`) e o servidor volta a fazê-los ao gravar, ignorando o que o
 *    browser tenha mandado. Uma cópia da matemática do imposto em TypeScript
 *    divergiria ao primeiro ajuste, e a divergência aparece como um cêntimo
 *    numa factura que a AGT recusa.
 *
 * 2. A TAXA VEM DO `TaxResolver`, nunca do pedido. Mandar `tax_rate: 0` numa
 *    linha de um artigo a 14% não muda nada.
 *
 * 3. O NÚMERO É DO MODELO. Cada documento numera-se a si próprio no `creating`,
 *    com a série da empresa. Escrever o número aqui era uma segunda sequência
 *    a competir com a primeira — o defeito que já mordeu na tesouraria.
 *
 * SÓ PROPOSTAS. Facturas, recibos e notas ficam de fora até terem cada um o
 * seu travão — ver `TiposDeDocumento::editaveis()`.
 */
class EmissorApiController extends Controller
{
    use DocumentosPorAutor;

    private string $modeloActual = '';

    protected function modeloDoDocumento(): string
    {
        return $this->modeloActual;
    }

    /** Os totais, para o ecrã mostrar enquanto se escreve. Não grava nada. */
    public function calcular(Request $request, string $tipo, CalculadoraDeDocumento $calculadora): JsonResponse
    {
        $this->definicao($request, $tipo, 'view');

        $dados = $request->validate([
            'linhas' => ['array'],
            'linhas.*.product_id' => ['nullable', 'integer'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.price' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'desconto_comercial' => ['nullable', 'numeric', 'min:0'],
            // O DESCONTO DE SEMPRE (`discount_amount`): existe na base desde
            // antes dos outros dois e SOMA ao comercial. Estava a entrar aqui
            // como zero fixo — uma proposta antiga aberta para editar mostrava
            // um total sem o desconto que ela tinha.
            'desconto_legado' => ['nullable', 'numeric', 'min:0'],
            'desconto_financeiro' => ['nullable', 'numeric', 'min:0'],
            'is_service' => ['nullable', 'boolean'],
        ]);

        return response()->json($calculadora->calcular(
            $dados['linhas'] ?? [],
            (float) ($dados['desconto_comercial'] ?? 0),
            (float) ($dados['desconto_legado'] ?? 0),
            (float) ($dados['desconto_financeiro'] ?? 0),
            // Prestação de serviço: retém-se IRT a 6,5%. Sem isto o ecrã
            // mostrava um total e o documento gravava outro.
            (bool) ($dados['is_service'] ?? false)
        ));
    }

    /** O que o editor precisa de saber ao abrir. */
    public function opcoes(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($request, $tipo, 'view');
        $editor = TiposDeDocumento::editaveis()[$tipo];

        $partes = $editor['parte_id'] === 'supplier_id'
            ? Supplier::where('tenant_id', activeTenantId())->where('is_active', true)
                ->orderBy('name')->limit(500)->get(['id', 'name', 'nif'])
            : Client::where('tenant_id', activeTenantId())
                ->orderBy('name')->limit(500)->get(['id', 'name', 'nif']);

        return response()->json([
            'titulo' => $def['titulo'],
            'parte' => $def['parte'],
            'rota' => $def['rota'],
            'partes' => $partes,
            // O `type` vai junto porque é ele que decide se o armazém é
            // obrigatório: um documento só de serviços dispensa-o.
            'artigos' => Product::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'code', 'price', 'unit', 'type']),

            'armazens' => Warehouse::where('tenant_id', activeTenantId())
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),

            // O armazém por omissão da empresa, para uma proposta nova nascer
            // com ele já escolhido — era o que o `mount` do Livewire fazia.
            'armazem_padrao' => Warehouse::getDefault(activeTenantId())?->id,

            // OS MODELOS DE PROPOSTA — só no orçamento, que é o único
            // documento que o `RenderizadorDeProposta` desenha.
            'modelos' => $this->modelosDeProposta($tipo),
            'modelo_padrao' => $this->temModelo($tipo)
                ? QuoteTemplate::where('tenant_id', activeTenantId())
                    ->where('is_active', true)->where('is_default', true)->value('id')
                : null,
            // Cabinda tem regime de IVA próprio e o que o determina é o LOCAL
            // DA OPERAÇÃO. Vazio é "automática": deriva da província da outra
            // parte. Fixar 'AO' por omissão era o que fazia Cabinda passar
            // despercebida numa proposta feita lá.
            'regioes' => [
                ['valor' => '', 'rotulo' => __('Pela província do :parte', ['parte' => $def['parte']])],
                ['valor' => 'AO', 'rotulo' => 'Angola (continente)'],
                ['valor' => 'AO-CAB', 'rotulo' => __('Cabinda (regime próprio)')],
            ],

            /*
             * A OUTRA PARTE CRIA-SE AQUI, sem largar a proposta a meio — é o
             * «cliente rápido» de sempre, e o «fornecedor rápido» quando o
             * documento é de compra.
             *
             * A permissão é a de criar a FICHA (cliente ou fornecedor), que é
             * outra coisa que não a de emitir o documento: quem não a tem não
             * vê o botão, e a porta de criação recusa na mesma.
             */
            'criar_parte' => [
                'tipo' => $def['parte'],
                'pode' => (bool) $request->user()?->can(
                    $editor['parte_id'] === 'supplier_id' ? 'invoicing.suppliers.create' : 'invoicing.clients.create'
                ),
                'pais_padrao' => Geografia::PAIS_PADRAO,
            ],

            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can(str_replace('.view', '.create', $def['permissao'])),
            ],
        ]);
    }

    /** A proposta como o editor a precisa — e se ainda se pode mexer (só rascunhos). */
    public function abrir(Request $request, string $tipo, int $id): JsonResponse
    {
        $this->definicao($request, $tipo, 'view');

        return response()->json($this->paraEditor($tipo, $this->baseDoAutor()->findOrFail($id)));
    }

    /**
     * DUPLICAR: o conteúdo desta proposta, para o editor abrir em branco.
     *
     * Não grava nada — devolve o conteúdo comercial, e o editor abre com ele
     * como uma proposta nova. Uma proforma não tem número fiscal nem hash,
     * mas tem número na série da casa, e esse não se copia: dois documentos
     * com o mesmo número são dois documentos por explicar.
     *
     * Só nos tipos que a lista oferece (`TiposDeDocumento::duplicaveis`). Um
     * tipo editável que não esteja lá dá 404: não é falta de permissão, é que
     * ali não se duplica.
     */
    public function duplicar(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($request, $tipo, 'create');

        abort_unless(TiposDeDocumento::eDuplicavel($tipo), 404, __('Este documento não se duplica.'));

        $d = $this->baseDoAutor()->findOrFail($id);
        $aberta = $this->paraEditor($tipo, $d);

        return response()->json(DuplicaDocumento::resposta(
            $aberta['documento'],
            $aberta['linhas'],
            // De hoje, e a validade recomeça: uma proposta duplicada com a
            // validade da antiga nascia expirada.
            ['data' => now()->toDateString(), 'valido_ate' => null],
            $d,
            $d->{$def['numero']},
        ));
    }

    /**
     * A proposta na forma que o editor conhece.
     *
     * Serve o `abrir` e o `duplicar`: uma forma só, para o duplicado herdar
     * exactamente o que a edição herdaria — e mais nada.
     *
     * @return array{documento: array<string,mixed>, linhas: mixed}
     */
    private function paraEditor(string $tipo, $d): array
    {
        $def = TiposDeDocumento::um($tipo);
        $editor = TiposDeDocumento::editaveis()[$tipo];

        $linhas = $editor['itens']::where($editor['chave'], $d->id)->orderBy('order')->get();
        $data = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v ? substr((string) $v, 0, 10) : null);

        return [
            'documento' => [
                'id' => $d->id,
                'numero' => $d->{$def['numero']},
                'estado' => $d->status,
                'pode_editar' => self::seMexe($d),
                'parte_id' => $d->{$editor['parte_id']},
                'warehouse_id' => $d->warehouse_id,
                'data' => $data($d->{$def['data']}),
                'valido_ate' => $data($d->valid_until ?? null),
                /*
                 * OS TRÊS DESCONTOS DO DOCUMENTO.
                 *
                 * O ecrã de sempre pedia-os e a base guarda-os há muito
                 * (`discount_commercial`, `discount_amount`, `discount_financial`).
                 * Sem eles aqui, reabrir uma proposta com desconto mostrava o
                 * total certo — o gravado — mas as caixas vazias, e a primeira
                 * gravação apagava o desconto sem ninguém dar por isso.
                 */
                'desconto_comercial' => (float) ($d->discount_commercial ?? 0),
                'desconto_legado' => (float) ($d->discount_amount ?? 0),
                'desconto_financeiro' => (float) ($d->discount_financial ?? 0),
                // A região gravada é a das LINHAS: é lá que ela conta.
                'tax_country_region' => $linhas->first()?->tax_country_region,
                // Prestação de serviço (IRT 6,5%) e as condições que saem no
                // papel: sem elas, reabrir uma proposta apagava as duas.
                'is_service' => (bool) ($d->is_service ?? false),
                'notas' => $d->notes,
                'condicoes' => $d->terms,
                // Só o orçamento tem modelo — nos outros vai nulo e o ecrã
                // nem desenha o campo.
                'quote_template_id' => $this->temModelo($tipo) ? $d->quote_template_id : null,
                'campos_proposta' => $this->temModelo($tipo) ? (array) ($d->campos_proposta ?? []) : [],
                'pdf' => url(ltrim($def['rota'], '/') . '/' . $d->id . '/pdf'),
            ],
            'linhas' => $linhas->map(fn ($l) => [
                'product_id' => $l->product_id,
                'description' => $l->description ?? '',
                'quantity' => (float) $l->quantity,
                'price' => (float) $l->unit_price,
                'discount_percent' => (float) ($l->discount_percent ?? 0),
            ])->values(),
        ];
    }

    /** Grava a proposta. Recalcula tudo, ignorando os totais do pedido. */
    public function guardar(Request $request, string $tipo, CalculadoraDeDocumento $calculadora): JsonResponse
    {
        $this->definicao($request, $tipo, 'create');

        return $this->gravarPedido($request, $tipo, $calculadora, null);
    }

    /** Guarda de novo: as linhas nascem de novo, o número fica. */
    public function actualizar(Request $request, string $tipo, CalculadoraDeDocumento $calculadora, int $id): JsonResponse
    {
        $this->definicao($request, $tipo, 'edit');

        $existente = $this->baseDoAutor()->findOrFail($id);

        if (! self::seMexe($existente)) {
            return response()->json(['message' => __('Este documento já foi convertido ou anulado e não se altera.')], 422);
        }

        return $this->gravarPedido($request, $tipo, $calculadora, $existente);
    }

    /**
     * UMA PROPOSTA ENVIADA AINDA SE CORRIGE.
     *
     * Uma proposta NÃO é um documento fiscal: não tem hash, não tem cadeia e
     * a AGT nunca a vê. Enviá-la ao cliente é dizer que já saiu, não é assiná-la
     * — e quando ele responde «troque a quantidade», corrige-se a mesma e
     * reenvia-se, que é o que o ecrã de sempre fazia.
     *
     * O que NÃO se mexe é o que já não é só nosso: uma proposta CONVERTIDA deu
     * origem a uma factura (mudá-la agora punha a factura a divergir da
     * proposta que a justifica), e uma ANULADA está fora de circulação. São os
     * mesmos dois estados que a lista já recusa eliminar.
     */
    private static function seMexe(object $d): bool
    {
        return ! in_array($d->status, ['converted', 'cancelled'], true);
    }

    private function gravarPedido(Request $request, string $tipo, CalculadoraDeDocumento $calculadora, $existente): JsonResponse
    {
        $def = TiposDeDocumento::um($tipo);
        $editor = TiposDeDocumento::editaveis()[$tipo];
        $dados = $request->validate([
            // Da EMPRESA: o cliente ou o fornecedor, conforme o documento.
            'parte_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists($editor['parte_id'] === 'supplier_id' ? 'invoicing_suppliers' : 'invoicing_clients', 'id')->where('tenant_id', activeTenantId())],
            'warehouse_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', activeTenantId())],
            'data' => ['required', 'date'],
            'valido_ate' => ['nullable', 'date', 'after_or_equal:data'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'condicoes' => ['nullable', 'string', 'max:2000'],
            'is_service' => ['nullable', 'boolean'],
            'quote_template_id' => ['nullable', 'integer'],
            'campos_proposta' => ['nullable', 'array'],
            'campos_proposta.*' => ['nullable', 'string', 'max:5000'],
            'tax_country_region' => ['nullable', 'in:AO,AO-CAB'],
            'desconto_comercial' => ['nullable', 'numeric', 'min:0'],
            'desconto_legado' => ['nullable', 'numeric', 'min:0'],
            'desconto_financeiro' => ['nullable', 'numeric', 'min:0'],
            /*
             * GUARDAR OU GUARDAR E ENVIAR — os dois botões do ecrã de sempre.
             *
             * «Enviada» quer dizer que a proposta saiu para a outra parte; não
             * é um estado fiscal e não fecha o documento à edição. Só estes
             * dois se escolhem daqui: convertida põe-na o conversor, e anulada
             * põe-na quem anula.
             */
            'estado' => ['nullable', 'in:draft,sent'],
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.product_id' => ['nullable', 'integer', 'exists:invoicing_products,id'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'linhas.*.price' => ['required', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'linhas.required' => __('Um documento sem linhas não é um documento.'),
            'linhas.min' => __('Um documento sem linhas não é um documento.'),
        ]);

        $eServico = (bool) ($dados['is_service'] ?? false);

        /*
         * O ARMAZÉM SÓ É OBRIGATÓRIO COM MERCADORIA.
         *
         * Era o que o `save()` do Livewire fazia: com produtos físicos no
         * documento exigia-se o armazém, e uma proposta só de serviços
         * dispensava-o. Marcá-la como prestação de serviço diz o mesmo por
         * outras palavras — e é a pessoa a dizê-lo.
         *
         * A pergunta é sobre o pedido TER FALADO no armazém e o ter deixado
         * vazio, que é uma escolha. Um pedido que nem lhe toca — a conversão
         * de um documento, um comando — continua a deixar o modelo pôr o
         * armazém padrão da empresa, como sempre fez.
         */
        if (! $eServico && $request->has('warehouse_id') && empty($dados['warehouse_id'])
            && CalculadoraDeDocumento::temArtigosFisicos($dados['linhas'])) {
            return response()->json([
                'message' => __('Selecione um armazém — existem produtos físicos no documento.'),
                'errors' => ['warehouse_id' => [__('Obrigatório com artigos físicos.')]],
            ], 422);
        }

        // OS TRÊS DESCONTOS DO DOCUMENTO, que entram na conta e ficam gravados.
        $comercial = (float) ($dados['desconto_comercial'] ?? 0);
        $legado = (float) ($dados['desconto_legado'] ?? 0);
        $financeiro = (float) ($dados['desconto_financeiro'] ?? 0);

        // A CONTA É FEITA AQUI. O que o browser mandou de totais é ignorado.
        $conta = $calculadora->calcular($dados['linhas'], $comercial, $legado, $financeiro, $eServico);

        $modelo = $def['modelo'];
        $regiao = $this->regiaoDaOperacao($dados, $editor);
        $temModelo = $this->temModelo($tipo);

        // O armazém confirma-se contra esta empresa; e só se mexe nele se o
        // pedido falou dele, para uma gravação que o omite não apagar o que
        // o documento já tinha.
        $mexeNoArmazem = $request->has('warehouse_id');
        $armazemPedido = ($dados['warehouse_id'] ?? null)
            ? Warehouse::where('tenant_id', activeTenantId())->whereKey($dados['warehouse_id'])->value('id')
            : null;

        $documento = DB::transaction(function () use ($def, $editor, $dados, $conta, $modelo, $existente, $regiao, $eServico, $temModelo, $mexeNoArmazem, $armazemPedido, $comercial, $legado, $financeiro) {
            if ($existente) {
                $editor['itens']::where($editor['chave'], $existente->id)->delete();
            }

            $d = $existente ?? new $modelo();

            if (! $existente) {
                $d->tenant_id = activeTenantId();
                $d->created_by = auth()->id();
                $d->status = 'draft';
            }

            /*
             * O ESTADO SÓ MUDA SE O PEDIDO O DISSER.
             *
             * Guardar uma proposta já enviada não a devolve a rascunho: quem
             * corrige uma proposta que saiu continua a ter uma proposta que
             * saiu. Só o botão «Guardar e enviar» a faz mudar.
             */
            if (! empty($dados['estado'])) {
                $d->status = $dados['estado'];
            }

            $d->{$editor['parte_id']} = $dados['parte_id'];
            $d->{$def['data']} = $dados['data'];            $d->notes = $dados['notas'] ?? null;
            $d->terms = $dados['condicoes'] ?? null;
            $d->is_service = $eServico;
            $d->discount_commercial = $comercial;
            $d->discount_amount = $legado;
            $d->discount_financial = $financeiro;

            if ($mexeNoArmazem) {
                $d->warehouse_id = $armazemPedido;
            }

            if ($temModelo) {
                // O modelo confirma-se contra esta empresa: um id vindo do
                // pedido não é prova de nada.
                $escolhido = ($dados['quote_template_id'] ?? null)
                    ? QuoteTemplate::where('tenant_id', activeTenantId())->find($dados['quote_template_id'])
                    : null;

                $d->quote_template_id = $escolhido?->id;
                $d->campos_proposta = $this->camposDoModeloEscolhido(
                    $escolhido,
                    (array) ($dados['campos_proposta'] ?? []),
                );
            }

            if (in_array('valid_until', $d->getFillable(), true) || ! empty($dados['valido_ate'])) {
                $d->valid_until = $dados['valido_ate'] ?? null;
            }

            $d->subtotal = $conta['totais']['liquido'];
            $d->tax_amount = $conta['totais']['imposto'];
            // A retenção de IRT de uma prestação de serviço, que já saiu do
            // total — o mesmo número que o ecrã mostra.
            $d->irt_amount = $conta['totais']['retencao'];
            $d->total = $conta['totais']['total'];

            // O NÚMERO NÃO SE ESCREVE AQUI: o modelo numera-se a si próprio no
            // `creating`, com a série da empresa.
            $d->save();

            $ordem = 0;

            foreach ($conta['linhas'] as $l) {
                $editor['itens']::create([
                    $editor['chave'] => $d->id,
                    'product_id' => $l['product_id'],
                    'product_name' => $l['nome'],
                    'description' => $l['description'],
                    'quantity' => $l['quantity'],
                    'unit' => $l['unit'],
                    'unit_price' => $l['price'],
                    'discount_percent' => $l['discount_percent'],
                    'discount_amount' => $l['desconto'],
                    'subtotal' => $l['base'],
                    'tax_rate' => $l['tax_rate'],
                    'tax_country_region' => $regiao,
                    'tax_amount' => $l['imposto'],
                    'total' => $l['total'],
                    'order' => ++$ordem,
                ]);
            }

            return $d;
        });

        return response()->json([
            'id' => $documento->id,
            'numero' => $documento->{$def['numero']},
            'total' => round((float) $documento->total, 2),
            'abrir' => $def['rota'] . '/' . $documento->id . '/edit',
            'estado' => $documento->status,
            'message' => match (true) {
                $documento->status === 'sent' => __('Documento :n gravado e dado como enviado.', ['n' => $documento->{$def['numero']}]),
                (bool) $existente => __('Documento :n actualizado.', ['n' => $documento->{$def['numero']}]),
                default => __('Documento :n gravado como rascunho.', ['n' => $documento->{$def['numero']}]),
            },
        ], $existente ? 200 : 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * ESTE DOCUMENTO DESENHA-SE POR UM MODELO DE PROPOSTA?
     *
     * Só o orçamento. É o único que o `RenderizadorDeProposta` sabe desenhar
     * e o único cuja tabela tem `quote_template_id` — as proformas saem
     * sempre pelo desenho da casa.
     */
    private function temModelo(string $tipo): bool
    {
        return $tipo === 'orcamentos';
    }

    /**
     * Os modelos de proposta da empresa, com os campos que cada um pede.
     *
     * Os campos livres viajam com o modelo para o ecrã os poder desenhar mal
     * se escolha um, sem uma segunda ida ao servidor. O padrão vem à frente,
     * como na lista de modelos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function modelosDeProposta(string $tipo): array
    {
        if (! $this->temModelo($tipo)) {
            return [];
        }

        return QuoteTemplate::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('nome')
            ->get()
            ->map(fn (QuoteTemplate $m) => [
                'id' => $m->id,
                'nome' => $m->nome,
                'is_default' => (bool) $m->is_default,
                'campos' => array_values($m->camposLivres()),
            ])
            ->all();
    }

    /**
     * SÓ OS CAMPOS QUE O MODELO ESCOLHIDO PEDE.
     *
     * Trocar de modelo a meio deixava aqui textos órfãos de um modelo antigo,
     * que voltariam a aparecer se alguém voltasse a escolhê-lo. Sem modelo,
     * ou sem nada escrito, fica nulo.
     *
     * @param  array<string, mixed>  $escritos
     * @return array<string, string>|null
     */
    private function camposDoModeloEscolhido(?QuoteTemplate $modelo, array $escritos): ?array
    {
        if (! $modelo) {
            return null;
        }

        $guardar = [];

        foreach (array_keys($modelo->camposLivres()) as $chave) {
            $valor = trim((string) ($escritos[$chave] ?? ''));

            if ($valor !== '') {
                $guardar[$chave] = $valor;
            }
        }

        return $guardar ?: null;
    }

    /**
     * A REGIÃO FISCAL DA OPERAÇÃO, que desce às linhas.
     *
     * Cabinda tem regime de IVA próprio (AO-CAB) e quem o determina é o local
     * da operação, não a sede de ninguém: a mesma entidade compra em Luanda e
     * em Cabinda. Vazio significa AUTOMÁTICA — deriva da província da outra
     * parte, como nas facturas de venda.
     *
     * Enquanto isto não existia, as linhas das propostas ficavam todas com o
     * 'AO' por omissão da coluna: uma proposta feita em Cabinda saía com o
     * imposto do continente, e a factura que dela nascia herdava o erro.
     */
    private function regiaoDaOperacao(array $dados, array $editor): string
    {
        $escolhida = $dados['tax_country_region'] ?? null;

        if (in_array($escolhida, ['AO', 'AO-CAB'], true)) {
            return $escolhida;
        }

        $parte = $editor['parte_id'] === 'supplier_id'
            ? Supplier::where('tenant_id', activeTenantId())->find($dados['parte_id'])
            : Client::where('tenant_id', activeTenantId())->find($dados['parte_id']);

        return $parte ? TaxResolver::regionForClient($parte) : 'AO';
    }

    /**
     * Resolve o tipo, exige a permissão certa para o verbo, e arma o escopo.
     *
     * Um tipo que exista mas ainda NÃO seja editável dá 404 e não 403: não é
     * uma questão de permissão, é que o editor ainda não sabe emiti-lo.
     */
    private function definicao(Request $request, string $tipo, string $verbo): array
    {
        abort_unless(TiposDeDocumento::eEditavel($tipo), 404, __('Este documento ainda não se emite por aqui.'));

        $def = TiposDeDocumento::um($tipo);

        $permissao = match ($verbo) {
            'create' => str_replace('.view', '.create', $def['permissao']),
            'edit' => str_replace('.view', '.edit', $def['permissao']),
            default => $def['permissao'],
        };

        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));

        $this->modeloActual = $def['modelo'];

        return $def;
    }
}
