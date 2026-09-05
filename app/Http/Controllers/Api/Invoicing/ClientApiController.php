<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoicing\ClientResource;
use App\Models\Client;
use App\Rules\PaisIso;
use App\Rules\ValidateNIF;
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

        return (new ClientResource($cliente->loadCount('facturas')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): ClientResource
    {
        $this->exigir($request, 'invoicing.clients.edit');

        $cliente = $this->doTenant($id);
        $cliente->update($this->validar($request, $cliente->id));

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

    /** As opções do ecrã: províncias, países e o que este utilizador pode fazer. */
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.clients.view');

        return response()->json([
            'provincias' => Geografia::provincias(),
            'paises' => Geografia::paises(),
            'pais_padrao' => Geografia::PAIS_PADRAO,
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

        return $request->validate([
            'type' => ['required', 'in:pessoa_juridica,pessoa_fisica'],
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'nif' => ['required', new ValidateNIF($tipo), $unico],
            'email' => ['nullable', 'email', 'max:150'],
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
        ]);
    }
}
