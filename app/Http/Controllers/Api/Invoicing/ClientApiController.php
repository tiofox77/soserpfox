<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoicing\ClientResource;
use App\Models\Client;
use App\Rules\PaisIso;
use App\Rules\ValidateNIF;
use App\Services\Clientes\AcessoAoPortal;
use App\Support\Geografia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = Client::where('tenant_id', activeTenantId())
            ->withCount('facturas');

        if ($procura = ($filtros['procura'] ?? null)) {
            $query->where(function ($q) use ($procura) {
                $q->where('name', 'like', "%{$procura}%")
                    ->orWhere('nif', 'like', "%{$procura}%")
                    ->orWhere('email', 'like', "%{$procura}%")
                    ->orWhere('phone', 'like', "%{$procura}%");
            });
        }

        $query
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filtros['provincia'] ?? null, fn ($q, $v) => $q->where('province', $v));

        return ClientResource::collection(
            $query->orderBy('name')->paginate($filtros['por_pagina'] ?? 15)->withQueryString()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.clients.create');

        $cliente = Client::create(array_merge(
            $this->validar($request),
            ['tenant_id' => activeTenantId()]
        ));

        $this->tratarDoPortal($request, $cliente);

        return (new ClientResource($cliente->fresh()->loadCount('facturas')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): ClientResource
    {
        $this->exigir($request, 'invoicing.clients.edit');

        $cliente = $this->doTenant($id);
        $cliente->update($this->validar($request, $cliente->id));

        $this->tratarDoPortal($request, $cliente);

        return new ClientResource($cliente->fresh()->loadCount('facturas'));
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
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('invoicing.clients.create'),
                'pode_editar' => (bool) $request->user()?->can('invoicing.clients.edit'),
                'pode_apagar' => (bool) $request->user()?->can('invoicing.clients.delete'),
            ],
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

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

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
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

        $dados = $request->validate([
            'type' => ['required', 'in:pessoa_juridica,pessoa_fisica'],
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'nif' => ['required', new ValidateNIF($tipo), $unico],
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
             * O ACESSO AO PORTAL vem no mesmo formulário, como vinha no ecrã
             * de sempre. Sem email não se liga: é por lá que o cliente se
             * autentica, e um acesso que ninguém pode usar só engana quem o
             * ligou. A senha nunca é obrigatória — em branco, é gerada.
             */
            'portal_access' => ['nullable', 'boolean'],
            'portal_password' => ['nullable', 'string', 'min:6', 'max:60'],
            'portal_repor_senha' => ['nullable', 'boolean'],
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

        // Estes não são colunas do cliente — são uma ordem para o serviço do
        // portal, tratada depois de a ficha estar gravada.
        unset($dados['portal_access'], $dados['portal_password'], $dados['portal_repor_senha']);

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
            app(AcessoAoPortal::class)->conceder($cliente, $escolhida);
        }
    }
}
