<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoicing\ClientResource;
use App\Models\Client;
use App\Models\Invoicing\PaymentTerm;
use App\Rules\PaisIso;
use App\Rules\ValidateNIF;
use App\Services\Clientes\AcessoAoPortal;
use App\Services\Invoicing\ExtratoDaParte;
use App\Support\Geografia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Os clientes, para o ecrã em React — a ler E a escrever.
 *
 * ESTE É O PRIMEIRO CONTROLADOR QUE ESCREVE, e por isso é onde a regra tem de
 * ficar dita: **as validações são as mesmas do ecrã Livewire, não umas
 * parecidas**. O NIF com o verificador angolano (`ValidateNIF`), único por
 * empresa; o país em código ISO de dois caracteres porque é assim que viaja
 * para a AGT (`customerCountry`). Uma API mais permissiva do que o ecrã não é
 * uma API — é uma forma de meter na base o que o ecrã recusava.
 *
 * E cada verbo tem a sua permissão: ver, criar, editar e apagar são quatro
 * autorizações diferentes, como já eram nas rotas.
 */
class ClientApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->exigir($request, 'invoicing.clients.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', 'in:pessoa_juridica,pessoa_fisica'],
            'provincia' => ['nullable', 'string', 'max:80'],
            // A CIDADE escrita à mão, como no ecrã de sempre: procura por
            // dentro, para «Luanda» apanhar «Luanda Sul».
            'cidade' => ['nullable', 'string', 'max:100'],
            // «Quem entrou este mês» — pela data de criação da ficha.
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = $this->filtrada($filtros)->with('paymentTerm')->withCount('facturas');

        /*
         * OS NÚMEROS DOS CARTÕES CONTAM O CONJUNTO INTEIRO, não a página.
         *
         * O ecrã em Blade contava a empresa toda («Pessoa Jurídica: 214»); ao
         * migrar, os cartões passaram a contar só as quinze linhas à vista e
         * diziam «nesta página» para se justificarem. Um cartão que muda de
         * número ao virar a página não é um resumo — é ruído.
         *
         * A CONSULTA DO RESUMO NASCE DE NOVO dos mesmos filtros, e não de um
         * clone da paginada: aquela leva o `withCount` das facturas na lista de
         * colunas, e uma coluna não agregada ao lado de um `SUM()` faz o MySQL
         * recusar a consulta inteira (`only_full_group_by`).
         */
        $resumo = $this->filtrada($filtros)
            ->toBase()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN type = 'pessoa_juridica' THEN 1 ELSE 0 END) as juridicas,
                SUM(CASE WHEN type = 'pessoa_fisica' THEN 1 ELSE 0 END) as fisicas,
                SUM(CASE WHEN portal_access = 1 THEN 1 ELSE 0 END) as com_portal
            ")
            ->first();

        return ClientResource::collection(
            $query->orderBy('name')->paginate($filtros['por_pagina'] ?? 15)->withQueryString()
        )->additional([
            'resumo' => [
                'total' => (int) ($resumo->total ?? 0),
                'juridicas' => (int) ($resumo->juridicas ?? 0),
                'fisicas' => (int) ($resumo->fisicas ?? 0),
                'com_portal' => (int) ($resumo->com_portal ?? 0),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Criar um cliente: quem cria clientes (`invoicing.clients.create`, ou
        // `customers.create`, o nome que o mapa de papéis dá ao Vendedor) e quem
        // vende ao balcão — o cliente rápido do POS de sempre não pedia nada.
        abort_unless(self::podeCriarCliente($request->user()), 403, __('Sem permissão para esta operação.'));

        $cliente = Client::create(array_merge(
            $this->validar($request),
            ['tenant_id' => activeTenantId()]
        ));

        $this->tratarDoPortal($request, $cliente);

        return (new ClientResource($cliente->fresh()->load('paymentTerm')->loadCount('facturas')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): ClientResource
    {
        $this->exigir($request, 'invoicing.clients.edit');

        $cliente = $this->doTenant($id);
        $cliente->update($this->validar($request, $cliente->id));

        $this->tratarDoPortal($request, $cliente);

        return new ClientResource($cliente->fresh()->load('paymentTerm')->loadCount('facturas'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.clients.delete');

        $cliente = $this->doTenant($id);

        /*
         * UM CLIENTE COM DOCUMENTOS NÃO SE APAGA.
         *
         * A factura aponta para ele, e uma factura sem cliente é um documento
         * fiscal órfão — o SAFT deixa de fechar e a AGT não tem a quem
         * imputar a venda. Diz-se porquê, com o número, em vez de um 500.
         */
        $quantos = $cliente->facturas()->count();

        if ($quantos > 0) {
            return response()->json([
                'message' => __('Este cliente tem :n documento(s) e não pode ser apagado. Um documento fiscal não pode ficar sem cliente.', ['n' => $quantos]),
            ], 422);
        }

        // A PASTA DO CLIENTE VAI COM ELE. O ecrã de sempre apagava-a; sem
        // isto, os logótipos de clientes apagados ficavam no disco para
        // sempre, e um deles é uma imagem de uma empresa que já não é cliente.
        $pasta = 'clients/' . $cliente->id;

        if (Storage::disk('public')->exists($pasta)) {
            Storage::disk('public')->deleteDirectory($pasta);
        }

        $cliente->delete();

        return response()->json(['message' => __('Cliente apagado.')]);
    }

    /** As opções do ecrã: geografia, países e o que este utilizador pode fazer. */
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.clients.view');

        return response()->json([
            'provincias' => Geografia::provincias(),
            // As que a reforma de 2024 criou — o ecrã assinala-as, como o
            // `<x-morada>` já fazia, para ninguém achar que são engano.
            'provincias_novas' => Geografia::provinciasNovas(),

            /*
             * A CASCATA VEM RESOLVIDA DE CÁ.
             *
             * O ecrã precisa dos municípios da província escolhida e das
             * sugestões de bairro do município — e não pode ir buscá-los a
             * uma segunda lista escrita em TypeScript, que é como as
             * províncias acabaram escritas cinco vezes. São 21 províncias e
             * 164 municípios: cabe todo numa resposta, que o ecrã guarda
             * cinco minutos.
             */
            'municipios' => $this->municipiosPorProvincia(),
            'bairros' => $this->bairrosPorMunicipio(),

            'paises' => Geografia::paises(),
            'pais_padrao' => Geografia::PAIS_PADRAO,
            // Onde o cliente entra — é o que o formulário mostra a quem dá
            // acesso ao portal, para não ter de o adivinhar.
            'portal_url' => route('client.login'),
            'tipos' => [
                ['valor' => 'pessoa_juridica', 'rotulo' => __('Empresa')],
                ['valor' => 'pessoa_fisica', 'rotulo' => __('Particular')],
            ],

            /*
             * AS CONDIÇÕES DE PAGAMENTO DESTA EMPRESA.
             *
             * É delas que sai o vencimento das facturas do cliente. Só as
             * activas: uma condição desligada não se atribui de novo, mas
             * quem já a tem continua a vê-la escrita na ficha (o nome vem no
             * próprio cliente, não desta lista).
             *
             * `dias` viaja para o ecrã poder escrever «(30 dias)» ao lado do
             * nome, como o ecrã de sempre fazia.
             */
            'condicoes_pagamento' => PaymentTerm::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'days', 'is_default'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'nome' => $c->name,
                    'dias' => (int) $c->days,
                    'padrao' => (bool) $c->is_default,
                ])->all(),

            // O atalho para as gerir só se mostra a quem pode abrir as
            // definições — era assim no ecrã de sempre (`@can`).
            'pode_gerir_condicoes' => (bool) $request->user()?->can('invoicing.settings.view'),
            'url_condicoes' => route('invoicing.payment-terms'),

            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('invoicing.clients.create'),
                'pode_editar' => (bool) $request->user()?->can('invoicing.clients.edit'),
                'pode_apagar' => (bool) $request->user()?->can('invoicing.clients.delete'),
            ],
        ]);
    }

    /**
     * O EXTRATO DO CLIENTE — o que ele já nos comprou.
     *
     * É a ficha que o ecrã de sempre abria no modal de ver: as contas, as
     * últimas facturas, os artigos que mais leva e a frequência mês a mês. São
     * as duas perguntas que se fazem antes de dar crédito ou negociar um
     * preço: quanto já comprou, e de quanto em quanto tempo volta.
     *
     * As contas vivem no `ExtratoDaParte` — as mesmas do lado do fornecedor,
     * vistas do outro lado — e respeitam o escopo do autor: sem
     * `invoicing.documents.all`, isto conta o que ESTE utilizador facturou.
     */
    public function extrato(Request $request, int $id, ExtratoDaParte $extrato): JsonResponse
    {
        $this->exigir($request, 'invoicing.clients.view');

        $cliente = $this->doTenant($id);

        // O extracto de conta em papel, a partir da ficha — só a quem o abre.
        return response()->json($extrato->doCliente($cliente) + [
            'pdf' => \App\Services\Invoicing\Relatorios\ExtractoDeConta::moradaDoPdfPara($request->user(), \App\Services\Invoicing\ContaCorrenteQuery::CLIENTE, $cliente->id),
        ]);
    }

    /**
     * O LOGÓTIPO DO CLIENTE — um ficheiro na pasta dele, como sempre.
     *
     * Vai à parte da ficha, em multipart, porque um ficheiro não viaja em
     * JSON: é o mesmo caminho do logótipo dos catálogos e das imagens do
     * artigo. Ao criar, o ecrã grava primeiro a ficha e envia a imagem a
     * seguir — antes disso não há `id` nem pasta onde a pôr.
     */
    public function logotipo(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.clients.edit');

        $request->validate(['logotipo' => ['required', 'image', 'max:2048']]);

        $cliente = $this->doTenant($id);

        // A que lá estava sai: são um logótipo, não um histórico deles.
        $this->apagarFicheiro($cliente->logo);

        $extensao = $request->file('logotipo')->extension();
        $nome = 'logo_' . Str::slug($cliente->name) . '.' . $extensao;

        $cliente->update([
            'logo' => $request->file('logotipo')->storeAs('clients/' . $cliente->id, $nome, 'public'),
        ]);

        return response()->json([
            'data' => new ClientResource($cliente->fresh()->load('paymentTerm')->loadCount('facturas')),
            'message' => __('Logótipo guardado.'),
        ]);
    }

    /** Tirar o logótipo: apaga o ficheiro e limpa a coluna. */
    public function apagarLogotipo(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.clients.edit');

        $cliente = $this->doTenant($id);

        $this->apagarFicheiro($cliente->logo);
        $cliente->update(['logo' => null]);

        return response()->json([
            'data' => new ClientResource($cliente->fresh()->load('paymentTerm')->loadCount('facturas')),
            'message' => __('Logótipo removido.'),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * A consulta com os filtros postos — e SEM as colunas da listagem.
     *
     * Serve duas vezes: a lista (que lhe acrescenta a condição de pagamento e
     * a contagem de facturas) e o resumo dos cartões (que lhe acrescenta só
     * agregados). Escrita uma vez, os dois falam sempre do mesmo conjunto —
     * com o filtro «Luanda» posto, os cartões falam de Luanda.
     */
    private function filtrada(array $filtros): \Illuminate\Database\Eloquent\Builder
    {
        return Client::where('tenant_id', activeTenantId())
            ->when($filtros['procura'] ?? null, fn ($q, $procura) => $q->where(function ($w) use ($procura) {
                $w->where('name', 'like', "%{$procura}%")
                    ->orWhere('nif', 'like', "%{$procura}%")
                    ->orWhere('email', 'like', "%{$procura}%")
                    ->orWhere('phone', 'like', "%{$procura}%");
            }))
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filtros['provincia'] ?? null, fn ($q, $v) => $q->where('province', $v))
            ->when($filtros['cidade'] ?? null, fn ($q, $v) => $q->where('city', 'like', "%{$v}%"))
            ->when($filtros['de'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['ate'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    /** @return array<string,list<string>> província => os seus municípios */
    private function municipiosPorProvincia(): array
    {
        $mapa = [];

        foreach (Geografia::provincias() as $provincia) {
            $mapa[$provincia] = Geografia::municipios($provincia);
        }

        return $mapa;
    }

    /**
     * @return array<string,list<string>> município => bairros SUGERIDOS
     *
     * Só os municípios que têm sugestões. Angola não tem registo nacional de
     * bairros, por isso o campo sugere e aceita o que se escrever — nunca é
     * uma lista fechada.
     */
    private function bairrosPorMunicipio(): array
    {
        $mapa = [];

        foreach (Geografia::todosOsMunicipios() as $municipio => $provincia) {
            $bairros = Geografia::bairros($municipio);

            if ($bairros !== []) {
                $mapa[$municipio] = $bairros;
            }
        }

        return $mapa;
    }

    public static function podeCriarCliente(?\Illuminate\Contracts\Auth\Authenticatable $utilizador): bool
    {
        return $utilizador !== null && (
            $utilizador->can('invoicing.clients.create')
            || $utilizador->can('customers.create')
            || PosApiController::podeVenderAoBalcao($utilizador)
        );
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    /**
     * Apagar um ficheiro guardado, se existir e se for nosso.
     *
     * Uma ficha importada pode trazer um endereço completo em vez de um
     * caminho no disco — chamar `delete()` com um `https://…` não apaga nada
     * mas também não pode rebentar a operação.
     */
    private function apagarFicheiro(?string $caminho): void
    {
        if (! filled($caminho) || str_starts_with($caminho, 'http')) {
            return;
        }

        if (Storage::disk('public')->exists($caminho)) {
            Storage::disk('public')->delete($caminho);
        }
    }

    /** Só o que é desta empresa. Um id de outra dá 404, não 403. */
    private function doTenant(int $id): Client
    {
        return Client::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    /**
     * As MESMAS regras do ecrã Livewire.
     *
     * O NIF é único POR EMPRESA e não globalmente — duas empresas podem ter o
     * mesmo cliente, e um índice global fazia a segunda falhar sem razão.
     */
    private function validar(Request $request, ?int $exceptoId = null): array
    {
        $tipo = $request->input('type', 'pessoa_juridica');

        $unico = Rule::unique('invoicing_clients', 'nif')
            ->where(fn ($q) => $q->where('tenant_id', activeTenantId()));

        if ($exceptoId) {
            $unico->ignore($exceptoId);
        }

        /*
         * O VERIFICADOR ANGOLANO SÓ VALE PARA ANGOLA.
         *
         * O `ValidateNIF` conta os dígitos e exige o prefixo 2, 3 ou 5 — as
         * regras da AGT. Aplicá-lo a toda a gente recusava o cliente português
         * com `PT-509999999` e o fornecedor sul-africano com o seu número: um
         * ERP angolano importa, e quem importa tem contrapartes lá fora.
         *
         * Era assim que o formulário do restaurante fazia («if country ===
         * 'AO'»), e é essa a regra certa. O NIF continua obrigatório e único
         * por empresa, seja de que país for.
         */
        $emAngola = $request->input('country', 'AO') === 'AO';

        $dados = $request->validate([
            'type' => ['required', 'in:pessoa_juridica,pessoa_fisica'],
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'nif' => array_values(array_filter([
                'required', 'string', 'max:30',
                $emAngola ? new ValidateNIF($tipo) : null,
                $unico,
            ])),
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:80'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'neighbourhood' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            // Duas letras, porque é o que a AGT recebe em `customerCountry`.
            'country' => ['required', 'string', 'size:2', new PaisIso()],

            /*
             * A CONDIÇÃO DE PAGAMENTO — o catálogo é POR EMPRESA, e por isso
             * o id confirma-se contra esta empresa e não com um `exists` seco:
             * um número escrito à mão no pedido punha o prazo de outra empresa
             * neste cliente, e era esse prazo que passava a dar o vencimento
             * às facturas dele.
             */
            'payment_term_id' => ['nullable', 'integer', Rule::exists('invoicing_payment_terms', 'id')
                ->where(fn ($q) => $q->where('tenant_id', activeTenantId()))],

            /*
             * O ACESSO AO PORTAL vem no mesmo formulário, como vinha no ecrã
             * de sempre. Sem email não se liga: é por lá que o cliente se
             * autentica, e um acesso que ninguém pode usar só engana quem o
             * ligou. A senha nunca é obrigatória — em branco, é gerada.
             */
            'portal_access' => ['nullable', 'boolean'],
            'portal_password' => ['nullable', 'string', 'min:6', 'max:60'],
            'portal_repor_senha' => ['nullable', 'boolean'],
            'portal_avisar' => ['nullable', 'boolean'],
            'email' => [
                Rule::requiredIf(fn () => $request->boolean('portal_access') || $request->boolean('portal_repor_senha')),
                'nullable', 'email', 'max:150',
            ],
        ]);

        // A CIDADE SEGUE O MUNICÍPIO quando não foi escrita. É `city` que as
        // listas mostram; um cliente gravado só com o município aparecia sem
        // localidade nenhuma.
        if (empty($dados['city']) && !empty($dados['municipality'])) {
            $dados['city'] = $dados['municipality'];
        }

        /*
         * `payment_term_days` FICA EM SINCRONIA COM A CONDIÇÃO ESCOLHIDA.
         *
         * São duas colunas para a mesma coisa — a condição é o catálogo novo,
         * os dias são o valor legado que o cálculo do vencimento ainda lê. O
         * ecrã de sempre sincronizava-as ao gravar; sem isto, mudar a condição
         * de «pronto pagamento» para «30 dias» não mexia no vencimento de
         * nenhuma factura, e ninguém percebia porquê.
         */
        if (array_key_exists('payment_term_id', $dados)) {
            $dados['payment_term_id'] = $dados['payment_term_id'] ?: null;

            $dados['payment_term_days'] = $dados['payment_term_id']
                ? (int) (PaymentTerm::where('tenant_id', activeTenantId())
                    ->find($dados['payment_term_id'])?->days ?? 0)
                : 0;
        }

        // Estes não são colunas do cliente — são uma ordem para o serviço do
        // portal, tratada depois de a ficha estar gravada.
        unset($dados['portal_access'], $dados['portal_password'], $dados['portal_repor_senha'], $dados['portal_avisar']);

        return $dados;
    }

    /**
     * Ligar, desligar ou repor o acesso ao portal — depois de a ficha gravar.
     *
     * GUARDAR A FICHA NÃO TROCA A SENHA DE QUEM JÁ TEM ACESSO: só mudar o
     * telefone deixaria o cliente de fora do portal sem ninguém perceber
     * porquê. Só se gera senha nova quando o acesso é dado pela primeira vez,
     * quando a empresa escreve uma, ou quando é pedida a reposição.
     */
    private function tratarDoPortal(Request $request, Client $cliente): void
    {
        $pedeAcesso = $request->boolean('portal_access');
        $repoe = $request->boolean('portal_repor_senha');
        $escolhida = $request->input('portal_password') ?: null;

        /*
         * AVISAR O CLIENTE, OU NÃO.
         *
         * Nem todo o acesso se anuncia: prepara-se a conta hoje e entrega-se a
         * senha em mão na visita da semana que vem. O ecrã em Livewire deixava
         * escolher; ao migrar, a API passou a avisar sempre — e um email de
         * boas-vindas com a senha lá dentro não se desmanda.
         *
         * A omissão é avisar, que é o que acontece na esmagadora maioria das
         * vezes e o que a API já fazia: quem não manda nada não vê diferença.
         */
        $avisa = ! $request->has('portal_avisar') || $request->boolean('portal_avisar');

        if (!$request->has('portal_access') && !$repoe) {
            return;
        }

        if (!$pedeAcesso && !$repoe) {
            if ($cliente->portal_access) {
                app(AcessoAoPortal::class)->revogar($cliente);
            }

            return;
        }

        if ($repoe || $escolhida || !$cliente->password) {
            app(AcessoAoPortal::class)->conceder($cliente, $escolhida, $avisa);
        }
    }
}
