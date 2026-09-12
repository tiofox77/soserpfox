<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoicing\Warehouse;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Restaurant\VenueLimitRequest;
use App\Models\Tenant;
use App\Services\Restaurant\RestaurantVenueLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS DEFINIÇÕES DO RESTAURANTE — as regras da casa e a sua estrutura.
 *
 * TRÊS GRAVAÇÕES SEPARADAS, e não uma só:
 *
 *   · as REGRAS (cozinha, stock, taxa de serviço, gorjetas);
 *   · a ESTRUTURA (estabelecimentos, zonas, mesas, postos de cozinha);
 *   · a CARTA PÚBLICA.
 *
 * Publicar preços ao mundo é uma decisão diferente de escolher um armazém por
 * omissão, e misturá-las fazia um clique numa caixa qualquer publicar a carta
 * sem querer. Também por isso a carta nasce DESLIGADA: um restaurante que não
 * pediu isto não pode acordar com os seus preços numa página aberta.
 *
 * VER E EDITAR SÃO PERMISSÕES DIFERENTES. Quem tem `settings.view` lê; mexer
 * exige `settings.edit`.
 */
class DefinicoesApiController extends Controller
{
    public function __construct(private readonly RestaurantVenueLimitService $limites) {}

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    /** O modelo de cada peça da estrutura, num sítio só. */
    private function modelo(string $tipo): string
    {
        return match ($tipo) {
            'estabelecimento' => Venue::class,
            'zona' => Area::class,
            'mesa' => DiningTable::class,
            'posto' => KitchenStation::class,
            default => abort(404),
        };
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.view');

        $tenantId = activeTenantId();
        $d = RestaurantSettings::forTenant($tenantId);
        $empresa = Tenant::findOrFail($tenantId);

        // Um endereço sugerido a partir do nome da empresa, para quem só quer
        // ligar o interruptor e acabar. Continua editável.
        $slug = (string) ($d->menu_slug ?? '');

        return response()->json([
            'regras' => [
                'default_warehouse_id' => $d->default_warehouse_id,
                'default_client_id' => $d->default_client_id,
                'use_kitchen_workflow' => (bool) ($d->use_kitchen_workflow ?? true),
                'require_recipe_for_products' => (bool) $d->require_recipe_for_products,
                'reserve_stock_on_confirm' => (bool) ($d->reserve_stock_on_confirm ?? true),
                'consume_stock_on_kitchen' => (bool) ($d->consume_stock_on_kitchen ?? true),
                'allow_negative_stock' => (bool) $d->allow_negative_stock,
                'service_charge_percent' => (float) ($d->service_charge_percent ?? 0),
                'tips_enabled' => (bool) ($d->tips_enabled ?? true),
                'kitchen_auto_print' => (bool) $d->kitchen_auto_print,
            ],
            'carta' => [
                'menu_slug' => $slug !== '' ? $slug : Str::slug((string) $empresa->name),
                'online_menu_enabled' => (bool) $d->online_menu_enabled,
                'menu_whatsapp_enabled' => (bool) ($d->menu_whatsapp_enabled ?? true),
                'menu_orders_enabled' => (bool) $d->menu_orders_enabled,
                'menu_show_prices' => (bool) ($d->menu_show_prices ?? true),
                'menu_whatsapp_number' => (string) ($d->menu_whatsapp_number ?? ''),
                'menu_title' => (string) ($d->menu_title ?? ''),
                'menu_description' => (string) ($d->menu_description ?? ''),
                'menu_primary_color' => (string) ($d->menu_primary_color ?: '#ea580c'),
            ],
            'url_da_carta' => $slug !== '' ? url('/menu/'.$slug) : null,
            'estabelecimentos' => Venue::where('tenant_id', $tenantId)->with(['areas.tables'])->orderBy('name')->get()
                ->map(fn (Venue $v) => [
                    'id' => $v->id, 'codigo' => $v->code, 'nome' => $v->name,
                    'warehouse_id' => $v->warehouse_id, 'activo' => (bool) $v->is_active,
                    'zonas' => $v->areas->map(fn (Area $z) => [
                        'id' => $z->id, 'nome' => $z->name, 'activa' => (bool) $z->is_active,
                        'mesas' => $z->tables->map(fn (DiningTable $m) => [
                            'id' => $m->id, 'codigo' => $m->code, 'nome' => $m->name,
                            'lugares' => (int) $m->capacity, 'activa' => (bool) $m->is_active,
                            'estado' => $m->status,
                        ])->values(),
                    ])->values(),
                ])->values(),
            'postos' => KitchenStation::where('tenant_id', $tenantId)->with('venue:id,name')->orderBy('sort_order')->get()
                ->map(fn (KitchenStation $p) => [
                    'id' => $p->id, 'codigo' => $p->code, 'nome' => $p->name,
                    'estabelecimento' => $p->venue?->name, 'venue_id' => $p->venue_id,
                    'activo' => (bool) $p->is_active,
                ])->values(),
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])->map(fn ($a) => ['valor' => (string) $a->id, 'rotulo' => $a->name])->values(),
            'clientes' => Client::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')
                ->limit(200)->get(['id', 'name'])
                ->map(fn ($c) => ['valor' => (string) $c->id, 'rotulo' => $c->name])->values(),
            'limite_de_estabelecimentos' => max(1, (int) $empresa->restaurant_venue_limit),
            'pedido_de_limite' => VenueLimitRequest::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('status', 'pending')->latest()->first(['id', 'requested_limit', 'created_at']),
            'permissoes' => ['pode_editar' => (bool) $request->user()?->can('restaurant.settings.edit')],
        ]);
    }

    /** As regras da casa. */
    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'default_warehouse_id' => ['nullable', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', $tenantId)],
            'default_client_id' => ['nullable', Rule::exists('invoicing_clients', 'id')->where('tenant_id', $tenantId)],
            'use_kitchen_workflow' => ['boolean'],
            'require_recipe_for_products' => ['boolean'],
            'reserve_stock_on_confirm' => ['boolean'],
            'consume_stock_on_kitchen' => ['boolean'],
            'allow_negative_stock' => ['boolean'],
            'service_charge_percent' => ['numeric', 'min:0', 'max:100'],
            'tips_enabled' => ['boolean'],
            'kitchen_auto_print' => ['boolean'],
        ]);

        RestaurantSettings::forTenant($tenantId);

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->update([
            'default_warehouse_id' => $dados['default_warehouse_id'] ?? null,
            'default_client_id' => $dados['default_client_id'] ?? null,
            // O turno é obrigatório e não se desliga: vender com a caixa
            // fechada é vender sem ninguém responder pelo dinheiro.
            'require_open_shift' => true,
            'use_kitchen_workflow' => (bool) ($dados['use_kitchen_workflow'] ?? true),
            'require_recipe_for_products' => (bool) ($dados['require_recipe_for_products'] ?? false),
            'reserve_stock_on_confirm' => (bool) ($dados['reserve_stock_on_confirm'] ?? true),
            'consume_stock_on_kitchen' => (bool) ($dados['consume_stock_on_kitchen'] ?? true),
            'allow_negative_stock' => (bool) ($dados['allow_negative_stock'] ?? false),
            'service_charge_percent' => max(0, min(100, (float) ($dados['service_charge_percent'] ?? 0))),
            'tips_enabled' => (bool) ($dados['tips_enabled'] ?? true),
            'kitchen_auto_print' => (bool) ($dados['kitchen_auto_print'] ?? false),
        ]);

        return response()->json(['message' => __('Configurações guardadas.')]);
    }

    /**
     * A carta pública.
     *
     * O SLUG É O ENDEREÇO e é único na tabela inteira, não por empresa: dois
     * restaurantes com o mesmo endereço é um a servir a carta do outro.
     */
    public function guardarCarta(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $tenantId = activeTenantId();
        $activa = $request->boolean('online_menu_enabled');

        $dados = $request->validate([
            'menu_slug' => [$activa ? 'required' : 'nullable', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('restaurant_settings', 'menu_slug')->ignore($tenantId, 'tenant_id')],
            'online_menu_enabled' => ['boolean'],
            'menu_whatsapp_enabled' => ['boolean'],
            'menu_orders_enabled' => ['boolean'],
            'menu_show_prices' => ['boolean'],
            'menu_whatsapp_number' => ['nullable', 'string', 'max:30'],
            'menu_title' => ['nullable', 'string', 'max:120'],
            'menu_description' => ['nullable', 'string', 'max:2000'],
            'menu_primary_color' => ['nullable', 'string', 'max:20'],
        ], [
            'menu_slug.required' => __('Dê um endereço à carta antes de a publicar.'),
            'menu_slug.regex' => __('O endereço só pode ter letras minúsculas, números e hífens.'),
            'menu_slug.unique' => __('Esse endereço já está a ser usado por outro restaurante.'),
        ]);

        // O WhatsApp sem número não serve para nada, e um botão que não leva a
        // lado nenhum é pior do que botão nenhum.
        if ($request->boolean('menu_whatsapp_enabled') && trim((string) ($dados['menu_whatsapp_number'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'menu_whatsapp_number' => [__('Indique o número que vai receber os pedidos.')],
            ]);
        }

        RestaurantSettings::forTenant($tenantId);

        RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->update([
            'menu_slug' => $dados['menu_slug'] ?: null,
            'online_menu_enabled' => $activa,
            'menu_whatsapp_enabled' => $request->boolean('menu_whatsapp_enabled'),
            'menu_orders_enabled' => $request->boolean('menu_orders_enabled'),
            'menu_show_prices' => $request->boolean('menu_show_prices'),
            'menu_whatsapp_number' => ($dados['menu_whatsapp_number'] ?? '') ?: null,
            'menu_title' => ($dados['menu_title'] ?? '') ?: null,
            'menu_description' => ($dados['menu_description'] ?? '') ?: null,
            'menu_primary_color' => ($dados['menu_primary_color'] ?? '') ?: null,
        ]);

        return response()->json([
            'message' => __('Carta guardada.'),
            'url_da_carta' => $dados['menu_slug'] ? url('/menu/'.$dados['menu_slug']) : null,
        ]);
    }

    /* ─── A estrutura ──────────────────────────────────────────────────── */

    public function criarEstabelecimento(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'code' => ['required', 'string', 'max:30',
                Rule::unique('restaurant_venues', 'code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:120'],
            'warehouse_id' => ['nullable', Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', $tenantId)],
        ], [], ['code' => __('código'), 'name' => __('nome')]);

        // O tecto por empresa vive no serviço: ele tranca a empresa, conta o
        // que já existe e recusa — sem isso, dois separadores abertos ao mesmo
        // tempo criavam dois estabelecimentos com o limite a um.
        $v = $this->limites->createVenue($tenantId, [
            'code' => mb_strtoupper($dados['code']),
            'name' => $dados['name'],
            'warehouse_id' => $dados['warehouse_id'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['message' => __('Estabelecimento criado.'), 'id' => $v->id], 201);
    }

    public function pedirMaisEstabelecimentos(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $dados = $request->validate([
            'requested_limit' => ['required', 'integer', 'min:2', 'max:20'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [], ['requested_limit' => __('novo limite')]);

        $this->limites->requestIncrease(
            activeTenantId(), (int) $request->user()?->id,
            $dados['requested_limit'], $dados['reason'] ?? null,
        );

        return response()->json([
            'message' => __('Pedido enviado ao administrador. Será avisado após a análise.'),
        ], 201);
    }

    public function criarZona(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'venue_id' => ['required', Rule::exists('restaurant_venues', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:100'],
        ], [], ['name' => __('nome')]);

        $v = Venue::where('tenant_id', $tenantId)->findOrFail($dados['venue_id']);

        Area::create([
            'tenant_id' => $tenantId, 'venue_id' => $v->id, 'name' => $dados['name'],
            'sort_order' => $v->areas()->count(), 'is_active' => true,
        ]);

        return response()->json(['message' => __('Zona criada.')], 201);
    }

    public function criarPosto(Request $request): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'venue_id' => ['required', Rule::exists('restaurant_venues', 'id')->where('tenant_id', $tenantId)],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:100'],
        ], [], ['code' => __('código'), 'name' => __('nome')]);

        KitchenStation::create([
            'tenant_id' => $tenantId, 'venue_id' => $dados['venue_id'],
            'code' => mb_strtoupper($dados['code']), 'name' => $dados['name'],
            'sort_order' => KitchenStation::where('tenant_id', $tenantId)->where('venue_id', $dados['venue_id'])->count(),
            'is_active' => true,
        ]);

        return response()->json(['message' => __('Estação criada.')], 201);
    }

    public function alternar(Request $request, string $tipo, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $registo = $this->modelo($tipo)::where('tenant_id', activeTenantId())->findOrFail($id);
        $registo->update(['is_active' => ! $registo->is_active]);

        return response()->json(['message' => __('Registo actualizado.'), 'activo' => (bool) $registo->is_active]);
    }

    public function renomear(Request $request, string $tipo, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:30'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], ['name' => __('nome')]);

        $registo = $this->modelo($tipo)::where('tenant_id', activeTenantId())->findOrFail($id);

        $valores = ['name' => $dados['name']];

        if (in_array($tipo, ['estabelecimento', 'mesa', 'posto'], true)) {
            $valores['code'] = mb_strtoupper((string) ($dados['code'] ?? $registo->code));
        }

        if ($tipo === 'mesa') {
            $valores['capacity'] = (int) ($dados['capacity'] ?? $registo->capacity);
        }

        $registo->update($valores);

        return response()->json(['message' => __('Registo actualizado.')]);
    }

    /**
     * Eliminar uma peça da estrutura.
     *
     * O QUE TEM HISTÓRICO NÃO SE APAGA — desactiva-se. Apagar uma mesa com
     * comandas deixava documentos a apontar para o vazio, e o relatório «quanto
     * rende cada mesa» a contar uma mesa sem nome.
     */
    public function apagar(Request $request, string $tipo, int $id): JsonResponse
    {
        $this->exigir($request, 'restaurant.settings.edit');

        $registo = $this->modelo($tipo)::where('tenant_id', activeTenantId())->findOrFail($id);

        match ($tipo) {
            'estabelecimento' => ($registo->orders()->exists() || $registo->tables()->exists())
                ? $this->recusa(__('O estabelecimento possui mesas ou comandas e não pode ser eliminado. Desactive-o.')) : null,
            'zona' => $registo->tables()->exists()
                ? $this->recusa(__('A zona possui mesas e não pode ser eliminada.')) : null,
            'mesa' => $registo->orders()->exists()
                ? $this->recusa(__('A mesa possui histórico e não pode ser eliminada. Desactive-a.')) : null,
            'posto' => $registo->tickets()->exists()
                ? $this->recusa(__('A estação possui tickets e não pode ser eliminada. Desactive-a.')) : null,
        };

        $registo->delete();

        return response()->json(['message' => __('Registo eliminado.')]);
    }
}
