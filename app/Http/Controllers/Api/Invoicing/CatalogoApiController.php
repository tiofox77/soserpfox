<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\Catalogos;
use App\Services\Invoicing\ExtratoDaParte;
use App\Support\GaleriaDeIcones;
use App\Support\Geografia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * OS CATÁLOGOS, para o ecrã em React — uma API só para seis catálogos.
 *
 * Tudo o que distingue um catálogo do outro vem do esquema (`Catalogos`):
 * regras, guardas, o que se normaliza, quem pode o quê. Este controlador
 * valida com as regras do esquema, chama as suas funções, e devolve linhas
 * na forma que a tabela mostra. Cada verbo exige a sua permissão.
 */
class CatalogoApiController extends Controller
{
    public function opcoes(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        $tenantId = activeTenantId();
        $this->antes($def, $tenantId);

        return response()->json([
            'titulo' => __($def['titulo']),
            'singular' => __($def['singular']),
            'icone' => $def['icone'],
            // A COR E A FRASE DA FAIXA. Cada catálogo tinha a sua cor no ecrã
            // em Blade — os fornecedores laranja, as categorias ciano, as
            // marcas rosa — e não era enfeite: reconhece-se a página pela
            // faixa antes de ler o título.
            'cor' => $def['cor'] ?? 'primaria',
            'descricao' => __($def['descricao'] ?? ''),
            // «Novo Fornecedor», «Nova Categoria» — o rótulo do botão de criar,
            // por catálogo. Um «Novo(a) fornecedor» montado à mão a partir do
            // singular não é português, e noutras línguas é pior.
            'novo' => __($def['novo'] ?? 'Novo registo'),
            /*
             * A COLUNA POR QUE SE CHAMA UMA LINHA.
             *
             * O ecrã escrevia `l.name` em sete sítios — o título da ficha, a
             * pergunta de apagar, todos os rótulos de leitura. Uma viatura não
             * tem `name`: chama-se pela matrícula, e a pergunta saía «Vai
             * apagar . Não há volta.»
             */
            'nome' => $def['nome'] ?? 'name',
            'pesquisa' => __($def['pesquisa_ajuda']),
            /*
             * UMA COLUNA `multi` LEVA AS OPÇÕES DO CAMPO CORRESPONDENTE.
             *
             * A célula mostra crachás, e o que está gravado é a CHAVE
             * (`sea_view`) e não o rótulo. Sem a lista, a lista de quartos
             * dizia «balcony sea_view bathtub» — que é o nome da coluna na
             * base, não uma resposta a ninguém. Nas especialidades do mecânico
             * não se notava porque lá o valor gravado já é o rótulo.
             */
            'colunas' => array_map(function ($c) use ($def, $tenantId) {
                $c['rotulo'] = __($c['rotulo']);

                if (($c['formato'] ?? '') === 'multi' && ! isset($c['opcoes'])) {
                    $campo = collect($def['campos'])->firstWhere('chave', $c['chave']);

                    if (isset($campo['opcoes'])) {
                        $c['opcoes'] = self::traduzidas($campo['opcoes']);
                    } elseif (isset($campo['referencia'])) {
                        $c['opcoes'] = $this->referencias($def, $tenantId)[$campo['referencia']] ?? [];
                    }
                }

                return $c;
            }, $def['colunas']),
            /*
             * OS RÓTULOS DAS ESCOLHAS TAMBÉM SE TRADUZEM.
             *
             * O `campo()` traduzia o rótulo do campo e deixava as opções em
             * português: um formulário em inglês com «Nível: Júnior/Pleno» é
             * meia tradução, que é pior do que nenhuma — parece avaria.
             */
            'campos' => array_map(function ($c) use ($def, $tenantId) {
                /*
                 * UM `multi` PODE PEDIR AS OPÇÕES A UMA REFERÊNCIA.
                 *
                 * «A que tipos de quarto se aplica este pacote» é uma lista
                 * que muda com os dados, e não uma escrita no esquema. Com as
                 * opções resolvidas aqui, o ecrã e a coluna da tabela tratam-na
                 * como qualquer outra lista fechada.
                 */
                if (($c['tipo'] ?? '') === 'multi' && isset($c['referencia']) && ! isset($c['opcoes'])) {
                    $c['opcoes'] = $this->referencias($def, $tenantId)[$c['referencia']] ?? [];
                }

                return isset($c['opcoes']) ? array_merge($c, ['opcoes' => self::traduzidas($c['opcoes'])]) : $c;
            }, $def['campos']),
            /*
             * UM FILTRO PODE PEDIR AS OPÇÕES A UMA REFERÊNCIA.
             *
             * «Filtrar por tipo de quarto» é uma lista que muda com os dados,
             * e não uma escrita no esquema: declara `referencia` e as opções
             * vêm das mesmas que o formulário usa. Sem isto, um filtro assim
             * chegava ao ecrã com uma lista vazia.
             */
            'filtros' => array_map(fn ($f) => array_merge($f, [
                'rotulo' => __($f['rotulo']),
                'tipo' => $f['tipo'] ?? 'escolha',
                'ajuda' => isset($f['ajuda']) ? __($f['ajuda']) : null,
            ],
                isset($f['opcoes']) ? ['opcoes' => self::traduzidas($f['opcoes'])] : [],
                isset($f['referencia']) ? ['opcoes' => $this->referencias($def, $tenantId)[$f['referencia']] ?? []] : [],
            ), $def['filtros']),
            // Se este catálogo aceita o intervalo de datas de criação: é o
            // ecrã que desenha os dois campos, e só onde eles servem.
            'datas' => ! empty($def['datas']),
            // Se este catálogo tem extrato — a janela de VER com as contas.
            'extrato' => ! empty($def['extrato']),
            // Se este catálogo tem uma ficha própria de VER (hoje: a da viatura).
            'ficha' => $def['ficha'] ?? null,
            'accoes' => $def['accoes'],
            /*
             * COMO SE CHAMA A IMAGEM DESTE CATÁLOGO.
             *
             * «Logótipo» num fornecedor, «Imagem de destaque» num tipo de
             * quarto, «Fotografia» numa pessoa. O botão dizia sempre
             * «Logótipo», que numa lista de quartos não quer dizer nada.
             */
            'imagem' => ! empty($def['accoes']['logotipo'])
                ? ['rotulo' => __($def['imagem']['rotulo'] ?? 'Logótipo')]
                : null,
            'galeria' => ! empty($def['accoes']['galeria'])
                ? ['rotulo' => __($def['galeria']['rotulo'] ?? 'Galeria')]
                : null,
            /*
             * A ATRIBUIÇÃO EM LOTE, e como se chama neste catálogo.
             *
             * Só o ecrã precisa do título e da dica da procura; quem se
             * atribui a quê é decisão do esquema e nunca chega ao browser.
             */
            'atribuir' => ! empty($def['accoes']['atribuir']) ? [
                'titulo' => __($def['atribuir']['titulo']),
                'nada' => __($def['atribuir']['nada']),
                'pesquisa_ajuda' => __($def['atribuir']['pesquisa_ajuda']),
            ] : null,
            /*
             * A IMPORTAÇÃO EM LOTE — «Importar de RH», nos mecânicos.
             *
             * Como a atribuição: só o rótulo do botão, o título e a dica da
             * procura chegam ao browser. De onde se importa e o que se copia
             * é decisão do esquema.
             */
            'importar' => ! empty($def['accoes']['importar']) ? [
                'botao' => __($def['importar']['botao']),
                'titulo' => __($def['importar']['titulo']),
                'nada' => __($def['importar']['nada']),
                'pesquisa_ajuda' => __($def['importar']['pesquisa_ajuda']),
            ] : null,
            'referencias' => $this->referencias($def, $tenantId),
            'geografia' => ! empty($def['geografia']) ? [
                'paises' => collect(Geografia::paises())->map(fn ($nome, $codigo) => ['valor' => $codigo, 'rotulo' => $nome])->values(),
                'provincias' => Geografia::provincias(),
                'municipios' => collect(Geografia::provincias())->mapWithKeys(fn ($p) => [$p => Geografia::municipios($p)]),
                'pais_padrao' => Geografia::PAIS_PADRAO,
            ] : null,
            /*
             * A GALERIA DE ÍCONES, e só onde há um campo que a use.
             *
             * A lista vive em PHP (`GaleriaDeIcones`) porque há formulários
             * em React e formulários em Blade: duas galerias em dois sítios
             * eram duas listas a divergir à primeira adição.
             */
            'galeria_de_icones' => collect($def['campos'])->contains(fn ($c) => ($c['tipo'] ?? '') === 'icone')
                ? GaleriaDeIcones::grupos()
                : null,
            'permissoes' => [
                'pode_escrever' => (bool) $request->user()?->can($def['permissoes']['criar'])
                    && (empty($def['partilhado']) || (bool) $request->user()?->isPlatformSuperAdmin()),
            ],
            'voltar' => $def['rota'],
        ]);
    }

    public function index(Request $request, string $tipo): AnonymousResourceCollection
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        $tenantId = activeTenantId();
        $this->antes($def, $tenantId);

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            // O intervalo de datas, só nos catálogos que o declaram — a
            // consulta ignora-o nos outros, mas aceitar o que não se usa
            // convida a acreditar que filtra.
            'de' => [empty($def['datas']) ? 'prohibited' : 'nullable', 'date'],
            'ate' => [empty($def['datas']) ? 'prohibited' : 'nullable', 'date'],
        ] + array_fill_keys(array_column($def['filtros'], 'chave'), ['nullable', 'string', 'max:80']));

        $referencias = $this->referencias($def, $tenantId);

        $pagina = Catalogos::consulta($def, $tenantId, $filtros)
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString()
            ->through(fn (Model $m) => Catalogos::linha($def, $m, $referencias));

        return JsonResource::collection($pagina);
    }

    public function store(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['criar']);
        $this->soAPlataformaEscreveNoPartilhado($request, $def);

        $tenantId = activeTenantId();
        $dados = $this->validar($request, $def, null, $tenantId);

        // O catálogo PARTILHADO não leva empresa: a tabela não tem a coluna.
        $atributos = empty($def['partilhado'])
            ? array_merge($dados, ['tenant_id' => $tenantId])
            : $dados;

        /*
         * QUEM TEM NÚMERO PRÓPRIO CRIA-SE PELA SUA PORTA.
         *
         * A viatura ganha um `vehicle_number` e o serviço um `service_code`,
         * gerados por empresa e de forma atómica (`createWithTenantNumber`,
         * que repete em caso de choque). Um `create()` cru deixava a coluna a
         * nulo e o índice único `(tenant_id, …)` recusava o segundo registo —
         * que era exactamente o que o Livewire fazia bem e não se pode perder.
         */
        $m = isset($def['criar'])
            ? ($def['criar'])($atributos)
            : $def['modelo']::create($atributos);
        $this->depois($def, $m);

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => __(':nome criado(a).', ['nome' => __($def['singular'])]),
        ], 201);
    }

    public function update(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['editar']);
        $this->soAPlataformaEscreveNoPartilhado($request, $def);

        $tenantId = activeTenantId();
        $m = $this->encontrar($def, $tenantId, $id);
        $dados = $this->validar($request, $def, $m, $tenantId);

        $m->update($dados);
        $this->depois($def, $m);

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => __(':nome guardado(a).', ['nome' => __($def['singular'])]),
        ]);
    }

    public function destroy(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['apagar']);
        $this->soAPlataformaEscreveNoPartilhado($request, $def);

        $m = $this->encontrar($def, activeTenantId(), $id);

        // A guarda é a do esquema: com compras, artigos ou stock não se apaga.
        if (! $def['accoes']['apagar'] || ! ($def['pode_apagar'])($m)) {
            return response()->json(['message' => __('Não é possível apagar: está em uso.')], 422);
        }

        if (isset($def['ao_apagar'])) {
            ($def['ao_apagar'])($m);
        }

        $m->delete();

        return response()->json(['message' => __(':nome apagado(a).', ['nome' => __($def['singular'])])]);
    }

    /** Activar/desactivar, ou tornar padrão — o que o esquema permitir. */
    public function accao(Request $request, string $tipo, int $id, string $accao): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['editar']);
        $this->soAPlataformaEscreveNoPartilhado($request, $def);

        abort_unless(in_array($accao, ['activar', 'padrao'], true) && ! empty($def['accoes'][$accao]), 404);

        $tenantId = activeTenantId();
        $m = $this->encontrar($def, $tenantId, $id);

        if ($accao === 'activar') {
            $m->update(['is_active' => ! $m->is_active]);
            $mensagem = $m->is_active ? __('Activado(a).') : __('Desactivado(a).');
        } else {
            ($def['padrao'])($m);
            $mensagem = __('Definido(a) como padrão.');
        }

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => $mensagem,
        ]);
    }

    /**
     * QUEM SE PODE ATRIBUIR A ESTE REGISTO — e quem já lá está.
     *
     * Serve o modal de atribuição em lote: a lista de candidatos desta
     * empresa, com procura, e os que já pertencem marcados. Quem é candidato
     * decide-o o esquema (`atribuir.modelo` e `atribuir.onde`) — o ecrã não
     * sabe o que é um funcionário.
     */
    public function atribuiveis(Request $request, string $tipo, int $id): JsonResponse
    {
        [$def, $atribuir, $m, $tenantId] = $this->paraAtribuir($request, $tipo, $id, null);

        $procura = trim((string) $request->query('procura', ''));

        $candidatos = $atribuir['modelo']::query()
            ->where('tenant_id', $tenantId)
            ->when(isset($atribuir['onde']), fn ($q) => $atribuir['onde']($q))
            ->when($procura !== '', fn ($q) => $q->where(function ($sub) use ($atribuir, $procura) {
                foreach ($atribuir['pesquisa'] as $coluna) {
                    $sub->orWhere($coluna, 'like', "%{$procura}%");
                }
            }))
            // Uma lista de escolha não é uma lista de tudo: com trezentos
            // funcionários, quem procura afina — e o servidor não despeja a
            // tabela inteira para o browser.
            ->limit(300)
            ->get();

        return response()->json([
            'data' => $candidatos->map(fn (Model $c) => [
                'id' => $c->id,
                'nome' => ($atribuir['nome'])($c),
                'nota' => isset($atribuir['nota']) ? (string) ($atribuir['nota'])($c) : null,
                'atribuido' => (int) $c->{$atribuir['coluna']} === (int) $m->id,
            ])->sortBy('nome')->values(),
            'total' => $candidatos->count(),
        ]);
    }

    /**
     * ATRIBUIR EM LOTE — e DESATRIBUIR o que ficou de fora.
     *
     * O modal manda a lista COMPLETA de quem fica: quem lá está e não vem na
     * lista sai. Mandar só os novos deixava sem maneira de tirar alguém do
     * turno sem ir à ficha dele — que é precisamente o trabalho que este modal
     * existe para poupar.
     *
     * Os ids são confirmados contra esta empresa antes de se escrever: um id
     * de fora vinha do pedido e não é prova de nada.
     */
    public function atribuir(Request $request, string $tipo, int $id): JsonResponse
    {
        // Atribuir é ESCREVER: pede a permissão de editar, não a de ver.
        [$def, $atribuir, $m, $tenantId] = $this->paraAtribuir(
            $request, $tipo, $id, $this->definicao($tipo)['permissoes']['editar'],
        );

        $dados = $request->validate([
            'ids' => ['present', 'array'],
            'ids.*' => ['integer'],
        ]);

        $daCasa = $atribuir['modelo']::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $dados['ids'])
            ->pluck('id');

        $coluna = $atribuir['coluna'];

        DB::transaction(function () use ($atribuir, $m, $tenantId, $daCasa, $coluna) {
            // Sai quem estava e já não vem.
            $atribuir['modelo']::query()
                ->where('tenant_id', $tenantId)
                ->where($coluna, $m->id)
                ->whereNotIn('id', $daCasa)
                ->update([$coluna => null]);

            // Entra quem vem.
            if ($daCasa->isNotEmpty()) {
                $atribuir['modelo']::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('id', $daCasa)
                    ->update([$coluna => $m->id]);
            }
        });

        return response()->json([
            'quantos' => $daCasa->count(),
            'message' => $daCasa->isEmpty()
                ? __('Ninguém ficou atribuído.')
                : __(':quantos atribuição(ões) gravada(s).', ['quantos' => $daCasa->count()]),
        ]);
    }

    /**
     * QUEM SE PODE IMPORTAR PARA ESTE CATÁLOGO.
     *
     * O ecrã dos mecânicos tinha o «Importar de RH»: a oficina não contrata
     * duas vezes a mesma pessoa — ela já está na ficha de pessoal, com o nome,
     * o telefone e o BI, e escrevê-la outra vez à mão é trabalho e é um erro à
     * espera de acontecer.
     *
     * QUEM JÁ CÁ ESTÁ VEM MARCADO E BLOQUEADO, não escondido: escondê-lo fazia
     * a lista mudar de tamanho sem explicação e quem procurasse um nome que já
     * tinha importado julgava que o funcionário tinha desaparecido do RH.
     */
    public function importaveis(Request $request, string $tipo): JsonResponse
    {
        [$def, $importar, $tenantId] = $this->paraImportar($request, $tipo, null);

        $procura = trim((string) $request->query('procura', ''));

        $candidatos = $importar['modelo']::query()
            ->where('tenant_id', $tenantId)
            ->when(isset($importar['onde']), fn ($q) => $importar['onde']($q))
            ->when($procura !== '', fn ($q) => $q->where(function ($sub) use ($importar, $procura) {
                foreach ($importar['pesquisa'] as $coluna) {
                    $sub->orWhere($coluna, 'like', "%{$procura}%");
                }
            }))
            ->limit(300)
            ->get();

        $jaCa = ($importar['ja_ca'])($candidatos, $tenantId);

        return response()->json([
            'data' => $candidatos->map(fn (Model $c) => [
                'id' => $c->id,
                'nome' => ($importar['nome'])($c),
                'nota' => isset($importar['nota']) ? (string) ($importar['nota'])($c) : null,
                // O ecrã lê `atribuido` para marcar — é o mesmo modal.
                'atribuido' => in_array($c->id, $jaCa, true),
                'bloqueado' => in_array($c->id, $jaCa, true),
            ])->sortBy('nome')->values(),
            'total' => $candidatos->count(),
        ]);
    }

    /**
     * IMPORTAR EM LOTE — cria no catálogo quem vier na lista.
     *
     * Ao contrário do atribuir, isto NÃO é «a lista completa de quem fica»:
     * quem já cá está não se apaga por não vir marcado. Importar é uma
     * entrada, e desfazê-la é apagar o mecânico — decisão que se toma na
     * lista, com a guarda do `pode_apagar` a valer.
     */
    public function importar(Request $request, string $tipo): JsonResponse
    {
        // Importar CRIA registos: pede a permissão de criar.
        [$def, $importar, $tenantId] = $this->paraImportar($request, $tipo, $this->definicao($tipo)['permissoes']['criar']);

        $dados = $request->validate([
            'ids' => ['present', 'array'],
            'ids.*' => ['integer'],
        ]);

        // Os ids vêm do pedido e não provam nada: confirmam-se contra esta
        // empresa antes de se escrever seja o que for.
        $candidatos = $importar['modelo']::query()
            ->where('tenant_id', $tenantId)
            ->when(isset($importar['onde']), fn ($q) => $importar['onde']($q))
            ->whereIn('id', $dados['ids'])
            ->get();

        $jaCa = ($importar['ja_ca'])($candidatos, $tenantId);

        $criados = 0;
        $repetidos = 0;

        DB::transaction(function () use ($def, $importar, $candidatos, $jaCa, $tenantId, &$criados, &$repetidos) {
            foreach ($candidatos as $c) {
                if (in_array($c->id, $jaCa, true)) {
                    $repetidos++;

                    continue;
                }

                $atributos = array_merge(($importar['mapear'])($c), ['tenant_id' => $tenantId]);

                isset($def['criar'])
                    ? ($def['criar'])($atributos)
                    : $def['modelo']::create($atributos);

                $criados++;
            }
        });

        return response()->json([
            'quantos' => $criados,
            'message' => $criados === 0
                ? __('Não havia nada de novo para importar.')
                : ($repetidos > 0
                    ? __(':quantos importado(s), :repetidos já existia(m).', ['quantos' => $criados, 'repetidos' => $repetidos])
                    : __(':quantos importado(s).', ['quantos' => $criados])),
        ]);
    }

    /**
     * O que os dois pontos de importação precisam: o esquema, a descrição da
     * importação e a empresa.
     *
     * @return array{0: array, 1: array, 2: int}
     */
    private function paraImportar(Request $request, string $tipo, ?string $permissao): array
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $permissao ?? $def['permissoes']['criar']);

        abort_unless(! empty($def['accoes']['importar']), 404, __('Aqui não se importa nada.'));

        return [$def, $def['importar'], activeTenantId()];
    }

    /**
     * O que os dois pontos de atribuição precisam, resolvido de uma vez: o
     * esquema, a descrição da atribuição, o registo desta empresa e o id.
     *
     * @return array{0: array, 1: array, 2: Model, 3: int}
     */
    private function paraAtribuir(Request $request, string $tipo, int $id, ?string $permissao): array
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $permissao ?? $def['permissoes']['ver']);

        abort_unless(! empty($def['accoes']['atribuir']), 404, __('Aqui não se atribui nada.'));

        $tenantId = activeTenantId();

        return [$def, $def['atribuir'], $this->encontrar($def, $tenantId, $id), $tenantId];
    }

    /**
     * O EXTRATO DE UM FORNECEDOR — o que já lhe comprámos.
     *
     * É a ficha que o ecrã de sempre abria no modal de ver: as contas, as
     * últimas facturas de compra, os artigos que mais lhe compramos e a
     * frequência mês a mês. É o que se olha antes de negociar um preço ou de
     * decidir se vale a pena mudar de fornecedor.
     *
     * SÓ NOS FORNECEDORES: uma marca ou uma unidade de medida não têm extrato
     * nenhum, e um endereço que responde a todos os catálogos com listas vazias
     * faz acreditar que o fornecedor não comprou nada.
     */
    public function extrato(Request $request, string $tipo, int $id, ExtratoDaParte $extrato): JsonResponse
    {
        abort_unless($tipo === 'fornecedores', 404, __('Este catálogo não tem extrato.'));

        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['ver']);

        $fornecedor = $this->encontrar($def, activeTenantId(), $id);

        // O extracto de conta em papel, a partir da ficha — só a quem o abre.
        return response()->json($extrato->doFornecedor($fornecedor) + [
            'pdf' => \App\Services\Invoicing\Relatorios\ExtractoDeConta::moradaDoPdfPara($request->user(), \App\Services\Invoicing\ContaCorrenteQuery::FORNECEDOR, (int) $fornecedor->id),
        ]);
    }

    /** O logótipo do fornecedor: um ficheiro na pasta dele, como sempre. */
    /**
     * A IMAGEM DE UM REGISTO — o logótipo do fornecedor, a foto do quarto.
     *
     * Era só o logótipo, com a coluna (`logo`) e a pasta (`suppliers/`)
     * escritas aqui dentro. O esquema passa a poder dizer onde a imagem vive,
     * porque um tipo de quarto guarda a sua em `featured_image` e um pacote em
     * `image` — e três acções iguais com nomes diferentes eram três sítios
     * para o mesmo defeito.
     */
    public function logotipo(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['editar']);
        $this->soAPlataformaEscreveNoPartilhado($request, $def);
        abort_unless(! empty($def['accoes']['logotipo']), 404);

        $request->validate(['logotipo' => ['required', 'image', 'max:2048']]);

        $tenantId = activeTenantId();
        $m = $this->encontrar($def, $tenantId, $id);

        $coluna = $def['imagem']['coluna'] ?? 'logo';
        $raiz = $def['imagem']['pasta'] ?? 'suppliers';
        $prefixo = $def['imagem']['prefixo'] ?? 'logo';

        $pasta = $raiz . '/' . $m->id;
        $nome = $prefixo . '_' . Str::slug((string) $m->{$def['nome'] ?? 'name'}) . '.'
            . $request->file('logotipo')->extension();

        if ($m->{$coluna} && Storage::disk('public')->exists($m->{$coluna})) {
            Storage::disk('public')->delete($m->{$coluna});
        }

        $m->update([$coluna => $request->file('logotipo')->storeAs($pasta, $nome, 'public')]);

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => __('Imagem guardada.'),
        ]);
    }

    /**
     * A GALERIA — juntar imagens à lista de um registo.
     *
     * O tipo de quarto tem uma imagem de destaque (a acção de cima) e uma
     * GALERIA: é ela que o site de reservas mostra, e é o que faz alguém
     * escolher um quarto em vez de outro.
     */
    public function juntarAGaleria(Request $request, string $tipo, int $id): JsonResponse
    {
        [$def, $m, $galeria, $tenantId] = $this->paraAGaleria($request, $tipo, $id);

        $request->validate([
            'imagens' => ['required', 'array', 'max:10'],
            'imagens.*' => ['image', 'max:2048'],
        ]);

        $caminhos = collect($m->{$galeria['coluna']} ?? [])->filter()->values()->all();

        foreach ($request->file('imagens') as $ficheiro) {
            $caminhos[] = $ficheiro->storeAs(
                $galeria['pasta'] . '/' . $m->id,
                uniqid('img_') . '.' . $ficheiro->extension(),
                'public'
            );
        }

        $m->update([$galeria['coluna'] => $caminhos]);

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => __(':quantas imagem(ns) juntada(s).', ['quantas' => count($request->file('imagens'))]),
        ], 201);
    }

    /**
     * TIRAR UMA IMAGEM DA GALERIA — pelo CAMINHO e não pela posição.
     *
     * Pela posição, apagar a segunda e depois a terceira apagava a quarta: os
     * índices mudam assim que a lista encolhe.
     */
    public function tirarDaGaleria(Request $request, string $tipo, int $id): JsonResponse
    {
        [$def, $m, $galeria, $tenantId] = $this->paraAGaleria($request, $tipo, $id);

        $dados = $request->validate(['caminho' => ['required', 'string', 'max:500']]);

        $caminhos = collect($m->{$galeria['coluna']} ?? [])->filter()->values();

        abort_unless($caminhos->contains($dados['caminho']), 404, __('Essa imagem não é deste registo.'));

        if (Storage::disk('public')->exists($dados['caminho'])) {
            Storage::disk('public')->delete($dados['caminho']);
        }

        $m->update([$galeria['coluna'] => $caminhos->reject(fn ($c) => $c === $dados['caminho'])->values()->all()]);

        return response()->json([
            'data' => Catalogos::linha($def, $m->fresh(), $this->referencias($def, $tenantId)),
            'message' => __('Imagem removida.'),
        ]);
    }

    /** @return array{0: array, 1: Model, 2: array, 3: int} */
    private function paraAGaleria(Request $request, string $tipo, int $id): array
    {
        $def = $this->definicao($tipo);
        $this->exigir($request, $def['permissoes']['editar']);
        $this->soAPlataformaEscreveNoPartilhado($request, $def);
        abort_unless(! empty($def['accoes']['galeria']), 404, __('Aqui não há galeria.'));

        $tenantId = activeTenantId();

        return [$def, $this->encontrar($def, $tenantId, $id), $def['galeria'], $tenantId];
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function definicao(string $tipo): array
    {
        abort_unless(Catalogos::existe($tipo), 404, __('Catálogo desconhecido.'));

        return Catalogos::um($tipo);
    }

    private function antes(array $def, int $tenantId): void
    {
        if (isset($def['antes'])) {
            ($def['antes'])($tenantId);
        }
    }

    private function referencias(array $def, int $tenantId): array
    {
        return isset($def['referencias']) ? ($def['referencias'])($tenantId) : [];
    }

    private function encontrar(array $def, int $tenantId, int $id): Model
    {
        return empty($def['partilhado'])
            ? $def['modelo']::query()->where('tenant_id', $tenantId)->findOrFail($id)
            : $def['modelo']::query()->findOrFail($id);
    }

    /**
     * As regras do esquema, mais a validação própria do catálogo (o nome
     * único, a categoria-mãe desta empresa), e depois o `preparar`.
     */
    private function validar(Request $request, array $def, ?Model $existente, int $tenantId): array
    {
        $chaves = array_column($def['campos'], 'chave');
        $dados = $request->validate($def['regras']);
        $dados = array_intersect_key($request->only($chaves), array_flip($chaves)) + $dados;

        if (isset($def['validar'])) {
            $erros = ($def['validar'])($dados, $existente, $tenantId);
            if ($erros) {
                throw ValidationException::withMessages(array_map(fn ($e) => [$e], $erros));
            }
        }

        if (isset($def['preparar'])) {
            $dados = ($def['preparar'])($dados, $existente, $tenantId);
        }

        return array_intersect_key($dados, array_flip(array_merge($chaves, ['sort_order', 'saft_code', 'compound_tax', 'address', 'country', 'province', 'municipality', 'neighbourhood', 'city', 'postal_code'])));
    }

    private function depois(array $def, Model $m): void
    {
        if (isset($def['depois'])) {
            ($def['depois'])($m);
        }
    }

    /** Uma lista de escolhas com os rótulos na língua de quem está a ver. */
    private static function traduzidas(array $opcoes): array
    {
        return array_map(fn ($o) => array_merge($o, ['rotulo' => __($o['rotulo'])]), $opcoes);
    }

    /**
     * UMA LISTA PARTILHADA É DA PLATAFORMA.
     *
     * Os bancos não têm `tenant_id`: são os mesmos para todas as empresas. A
     * permissão `treasury.banks.delete` é de cada empresa, e com ela qualquer
     * administrador renomeava ou apagava um banco para toda a gente
     * (auditoria de segurança de 2026-09-13). Escrever numa lista partilhada
     * é do dono da plataforma.
     */
    private function soAPlataformaEscreveNoPartilhado(Request $request, array $def): void
    {
        abort_if(
            ! empty($def['partilhado']) && ! $request->user()?->isPlatformSuperAdmin(),
            403,
            __('Esta lista é partilhada por todas as empresas: só a plataforma a altera.'),
        );
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
