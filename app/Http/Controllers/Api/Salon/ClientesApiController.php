<?php

namespace App\Http\Controllers\Api\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon\Client;
use App\Support\Geografia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS CLIENTES DO SALÃO.
 *
 * SÃO AS CLIENTES DA FACTURAÇÃO (`invoicing_clients`) — a mesma ficha que
 * recebe a factura. O que é do salão (visitas, gasto acumulado, pontos,
 * alergias, VIP) vive no JSON do `notes`, e é por isso que o VIP se procura
 * com um `like` e não com um `where` numa coluna.
 *
 * A EMPRESA VAI NO INSERT. A tabela é partilhada e `tenant_id` não aceita nulo:
 * sem essa linha, criar uma cliente pelo ecrã do salão rebentava com «Field
 * tenant_id doesn't have a default value» — nunca funcionou.
 *
 * AS ALERGIAS NÃO SÃO UM CAMPO QUALQUER. Num salão, uma tinta no couro
 * cabeludo de quem é alérgica é uma ida ao hospital: aparecem na ficha e na
 * marcação, não escondidas atrás de um separador.
 */
class ClientesApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.clients.view');

        return response()->json([
            'paises' => collect(Geografia::paises())
                ->map(fn ($nome, $iso) => ['valor' => (string) $iso, 'rotulo' => $nome])->values(),
            'provincias' => collect(Geografia::provincias())
                ->map(fn ($nome) => ['valor' => (string) $nome, 'rotulo' => $nome])->values(),
            'generos' => [
                ['valor' => 'feminino', 'rotulo' => __('Feminino')],
                ['valor' => 'masculino', 'rotulo' => __('Masculino')],
                ['valor' => 'outro', 'rotulo' => __('Outro')],
            ],
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can('salon.clients.create'),
                'pode_editar' => (bool) $request->user()?->can('salon.clients.edit'),
                'pode_apagar' => (bool) $request->user()?->can('salon.clients.delete'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.clients.view');

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'vip' => ['nullable', Rule::in(['vip', 'normal'])],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $lista = Client::forTenant()
            ->withCount('appointments')
            ->when($filtros['procura'] ?? null, fn ($q, $t) => $q->search($t))
            ->when(($filtros['vip'] ?? null) === 'vip', fn ($q) => $q->vip())
            ->when(($filtros['vip'] ?? null) === 'normal',
                fn ($q) => $q->whereRaw("(notes NOT LIKE '%\"is_vip\":true%' OR notes IS NULL)"))
            ->orderBy('name')
            ->paginate($filtros['por_pagina'] ?? 15);

        $vip = Client::forTenant()->vip()->count();

        return response()->json([
            'data' => collect($lista->items())->map(fn (Client $c) => $this->linha($c))->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'total' => Client::forTenant()->count(),
                'vip' => $vip,
                'normais' => Client::forTenant()->count() - $vip,
            ],
        ]);
    }

    private function linha(Client $c): array
    {
        return [
            'id' => $c->id,
            'nome' => $c->name,
            'email' => $c->email,
            'telefone' => $c->phone,
            'whatsapp' => $c->mobile,
            'nascimento' => $c->birth_date?->format('Y-m-d'),
            'genero' => $c->gender,
            'morada' => $c->address,
            'pais' => $c->country,
            'provincia' => $c->province,
            'cidade' => $c->city,
            'codigo_postal' => $c->postal_code,
            'vip' => (bool) $c->is_vip,
            'alergias' => $c->allergies,
            'visitas' => (int) $c->total_visits,
            'gasto' => (float) $c->total_spent,
            'pontos' => (int) $c->loyalty_points,
            'ultima_visita' => $c->salon_data['last_visit_at'] ?? null,
            'marcacoes' => (int) ($c->appointments_count ?? 0),
        ];
    }

    /** A ficha: a cliente e as últimas marcações dela. */
    public function ficha(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.clients.view');

        $c = Client::forTenant()
            ->with(['appointments' => fn ($q) => $q
                ->with(['professional:id,name', 'services.service'])->latest('date')->take(10)])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->linha($c),
            'marcacoes' => $c->appointments->map(fn ($m) => [
                'id' => $m->id,
                'numero' => $m->appointment_number,
                'dia' => $m->date?->format('Y-m-d'),
                'inicio' => $m->start_time?->format('H:i'),
                'profissional' => $m->professional?->name,
                'estado' => $m->status,
                'estado_rotulo' => __(\App\Models\Salon\Appointment::STATUSES[$m->status] ?? $m->status),
                'total' => (float) $m->total,
                'servicos' => $m->services->map(fn ($s) => $s->service?->name)->filter()->values(),
            ])->values(),
        ]);
    }

    public function guardar(Request $request, ?int $id = null): JsonResponse
    {
        $this->exigir($request, $id ? 'salon.clients.edit' : 'salon.clients.create');

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['feminino', 'masculino', 'outro'])],
            'address' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_vip' => ['boolean'],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:120'],
        ], [], ['name' => __('nome')]);

        $valores = [
            'name' => $dados['name'],
            'email' => $dados['email'] ?? null,
            'phone' => $dados['phone'] ?? null,
            'mobile' => ($dados['whatsapp'] ?? null) ?: ($dados['phone'] ?? null),
            'birth_date' => $dados['birth_date'] ?? null,
            'gender' => $dados['gender'] ?? null,
            'address' => $dados['address'] ?? null,
            'country' => $dados['country'] ?? 'AO',
            'province' => $dados['province'] ?? null,
            'city' => $dados['city'] ?? null,
            'postal_code' => $dados['postal_code'] ?? null,
            /*
             * A coluna é um `enum('pessoa_fisica','pessoa_juridica')`. Escrever
             * 'particular' dava «Data truncated for column type» e a cliente
             * simplesmente NÃO era criada.
             */
            'type' => 'pessoa_fisica',
            'is_active' => true,
        ];

        /*
         * SÓ SE ESCREVE O QUE VEIO NO PEDIDO.
         *
         * O `updateSalonData` faz merge, mas um `allergies => []` posto por
         * omissão é um merge que APAGA. Quem gravasse a ficha sem esse campo
         * — outro ecrã, outra chamada — deixava a cliente sem alergias e sem
         * VIP, e ninguém dava por isso: num salão, uma alergia que desaparece
         * da ficha é uma tinta no couro cabeludo de quem não a podia levar.
         */
        $doSalao = array_filter([
            'is_vip' => $request->has('is_vip') ? (bool) ($dados['is_vip'] ?? false) : null,
            'allergies' => $request->has('allergies') ? array_values($dados['allergies'] ?? []) : null,
        ], fn ($v) => $v !== null);

        if ($id) {
            $cliente = Client::forTenant()->findOrFail($id);

            $cliente->update($valores);

            // O MERGE É OBRIGATÓRIO: visitas, gasto e pontos vivem no mesmo
            // JSON, e reescrevê-lo inteiro apagava o histórico de fidelidade
            // de quem só mudou de telefone.
            $cliente->updateSalonData($doSalao);
        } else {
            $cliente = Client::create($valores + [
                'tenant_id' => activeTenantId(),
                'notes' => json_encode($doSalao + [
                    'is_vip' => false,
                    'allergies' => [],
                    'preferences' => [],
                    'total_visits' => 0,
                    'total_spent' => 0,
                    'loyalty_points' => 0,
                ]),
            ]);
        }

        return response()->json([
            'message' => $id ? __('Cliente actualizada.') : __('Cliente criada.'),
            'data' => $this->linha($cliente->fresh()),
        ], $id ? 200 : 201);
    }

    public function alternarVip(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.clients.edit');

        $c = Client::forTenant()->findOrFail($id);
        $c->setVip(! $c->is_vip);

        return response()->json([
            'message' => $c->fresh()->is_vip ? __('Cliente VIP.') : __('VIP retirado.'),
            'vip' => (bool) $c->fresh()->is_vip,
        ]);
    }

    public function apagar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'salon.clients.delete');

        $c = Client::forTenant()->findOrFail($id);

        if ($c->appointments()->whereIn('status', ['scheduled', 'confirmed', 'arrived', 'in_progress'])->exists()) {
            $this->recusa(__('Esta cliente tem marcações por atender.'));
        }

        $c->delete();

        return response()->json(['message' => __('Cliente removida.')]);
    }
}
