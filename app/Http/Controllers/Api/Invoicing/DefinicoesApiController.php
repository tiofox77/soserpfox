<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\PaymentTerm;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Supplier;
use App\Models\Treasury\PaymentMethod;
use App\Services\Invoicing\DefinicoesDaFacturacao;
use App\Services\Invoicing\GestorDeSeries;
use App\Services\Invoicing\SeriesCatalog;
use App\Support\MenuDoPwa;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AS DEFINIÇÕES DA FACTURAÇÃO, para o ecrã em React.
 *
 * NÃO HÁ REGRAS AQUI. Ler, validar e guardar vivem no
 * `DefinicoesDaFacturacao`; as séries no `GestorDeSeries` — os mesmos que o
 * componente Livewire chama. Ver é uma permissão, editar é outra: cada
 * escrita exige a segunda.
 */
class DefinicoesApiController extends Controller
{
    public function mostrar(Request $request, DefinicoesDaFacturacao $definicoes, GestorDeSeries $gestor): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.view');

        $tenantId = activeTenantId();
        abort_unless($tenantId, 409, __('Nenhuma empresa activa.'));

        $s = InvoicingSettings::forTenant($tenantId);
        $definicoes->essenciais($s, $tenantId);

        $tipos = $gestor->tipos();
        $listados = array_column($tipos, 'tipo');

        $serie = fn (InvoicingSeries $x) => [
            'id' => $x->id,
            'document_type' => $x->document_type,
            'series_code' => $x->series_code,
            'name' => $x->name,
            'prefix' => $x->prefix,
            'next_number' => (int) $x->next_number,
            'is_default' => (bool) $x->is_default,
            'agt_series_id' => $x->agt_series_id ?: null,
            'pode_renomear' => SeriesCatalog::podeRenomear($x),
            'description' => $x->description,
        ];

        $porTipo = [];
        $outras = [];

        foreach ($gestor->activas($tenantId) as $tipo => $doTipo) {
            if (in_array($tipo, $listados, true)) {
                $porTipo[$tipo] = $doTipo->map($serie)->values();
            } else {
                // Séries de tipos sem cartão próprio (a proforma de compra,
                // ou um tipo escrito com erro): existem e mostram-se.
                foreach ($doTipo as $x) {
                    $outras[] = $serie($x);
                }
            }
        }

        $escolhas = fn (array $lista) => collect($lista)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => $r])->values();

        return response()->json([
            'definicoes' => $definicoes->ler($s, $request->user()?->activeTenant()),

            'opcoes' => [
                'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'clientes' => Client::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name']),
                'fornecedores' => Supplier::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name']),
                'impostos' => Tax::where('tenant_id', $tenantId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'rate']),
                'formas_de_pagamento' => PaymentMethod::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name']),
                'condicoes_de_pagamento' => PaymentTerm::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'days']),

                'moedas' => $escolhas(['AOA' => 'AOA — Kwanza', 'USD' => 'USD — Dólar', 'EUR' => 'EUR — Euro']),
                'metodos_de_pagamento' => $escolhas([
                    'dinheiro' => __('Dinheiro'), 'transferencia' => __('Transferência Bancária'),
                    'multicaixa' => __('Multicaixa'), 'cartao' => __('Cartão de Crédito/Débito'), 'cheque' => __('Cheque'),
                ]),
                'formatos_de_numero' => $escolhas([
                    'angola' => 'Angola — 20.000,00', 'international' => 'Internacional — 20,000.00',
                    'portugal' => 'Portugal — 20.000,00', 'brazil' => 'Brasil — 20.000,00',
                    'france' => 'França — 20 000,00', 'switzerland' => "Suíça — 20'000.00", 'india' => 'Índia — 20,000.00',
                ]),
                'modos_de_arredondamento' => $escolhas([
                    'normal' => __('Normal (matemático)'), 'up' => __('Sempre para cima'),
                    'down' => __('Sempre para baixo'), 'half_up' => __('Meio para cima (0,5+)'),
                ]),
                'nomes_nos_documentos' => $escolhas([
                    InvoicingSettings::NOME_SOCIAL => __('Designação social'),
                    InvoicingSettings::NOME_COMERCIAL => __('Nome comercial'),
                ]),

                // Só o que a empresa pode ligar e desligar: as fixas não
                // aparecem, nem as de módulos que não tem.
                'entradas_do_pwa' => collect(MenuDoPwa::configuraveis($request->user()?->activeTenant()))
                    ->map(fn ($d, $chave) => ['chave' => $chave, 'etiqueta' => __($d['etiqueta']), 'icone' => $d['icone']])
                    ->values(),
            ],

            'series' => [
                'tipos' => $tipos,
                'por_tipo' => (object) $porTipo,
                'outras' => $outras,
            ],

            'permissoes' => [
                'pode_editar' => (bool) $request->user()?->can('invoicing.settings.edit'),
            ],
        ]);
    }

    public function guardar(Request $request, DefinicoesDaFacturacao $definicoes): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.edit');

        $tenantId = activeTenantId();
        abort_unless($tenantId, 409, __('Nenhuma empresa activa.'));

        $request->validate($definicoes->regras($tenantId) + [
            'default_warehouse_id' => ['nullable', 'integer'],
            'default_client_id' => ['nullable', 'integer'],
            'default_supplier_id' => ['nullable', 'integer'],
            'default_tax_id' => ['nullable', 'integer'],
            'pos_default_payment_method_id' => ['nullable', 'integer'],
            'default_payment_method' => ['nullable', 'string', 'max:50'],
            'invoice_footer_text' => ['nullable', 'string', 'max:65535'],
            'default_notes' => ['nullable', 'string', 'max:65535'],
            'default_terms' => ['nullable', 'string', 'max:65535'],
        ]);

        $aviso = $definicoes->guardar(
            InvoicingSettings::forTenant($tenantId),
            $request->only(array_merge(DefinicoesDaFacturacao::CAMPOS, ['pwa_menu', 'default_payment_term_id'])),
            $tenantId
        );

        return response()->json([
            'message' => __('Configurações salvas com sucesso!'),
            'aviso' => $aviso,
        ]);
    }

    public function criarSerie(Request $request, GestorDeSeries $gestor): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.edit');

        $dados = $request->validate([
            'tipo' => ['required', 'string', 'max:50'],
            'codigo' => ['required', 'string', 'max:10'],
            'nome' => ['nullable', 'string', 'max:100'],
            'descricao' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $serie = $gestor->criar(activeTenantId(), $dados['tipo'], $dados['codigo'], $dados['nome'] ?? null, $dados['descricao'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['tipo' => [$e->getMessage()]]], 422);
        }

        return response()->json(['serie' => $this->umaSerie($serie), 'message' => __('Nova série criada com sucesso!')], 201);
    }

    public function renomearSerie(Request $request, GestorDeSeries $gestor, int $serie): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.edit');

        // O scope ao tenant é OBRIGATÓRIO: sem ele, a empresa A renomeava a
        // série fiscal da empresa B.
        $s = InvoicingSeries::where('tenant_id', activeTenantId())->findOrFail($serie);

        $dados = $request->validate([
            'codigo' => ['required', 'string', 'max:10'],
            'nome' => ['nullable', 'string', 'max:100'],
            'descricao' => ['nullable', 'string', 'max:500'],
        ]);

        $gestor->actualizar($s, $dados['codigo'], $dados['nome'] ?? null, $dados['descricao'] ?? null);

        return response()->json(['serie' => $this->umaSerie($s->fresh()), 'message' => __('Série atualizada com sucesso!')]);
    }

    /**
     * Sem `confirmado`, e havendo outra série do mesmo tipo já adiantada,
     * devolve o aviso — e não muda nada. O ecrã mostra-o e volta com
     * `confirmado` se for essa a decisão.
     */
    public function tornarPadrao(Request $request, GestorDeSeries $gestor, int $serie): JsonResponse
    {
        $this->exigir($request, 'invoicing.settings.edit');

        $s = InvoicingSeries::where('tenant_id', activeTenantId())->findOrFail($serie);

        $aviso = $gestor->tornarPadrao($s, (bool) $request->boolean('confirmado'));

        return response()->json([
            'aviso' => $aviso,
            'message' => $aviso ? null : __('Série padrão definida com sucesso!'),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function umaSerie(InvoicingSeries $x): array
    {
        return [
            'id' => $x->id,
            'document_type' => $x->document_type,
            'series_code' => $x->series_code,
            'name' => $x->name,
            'prefix' => $x->prefix,
            'next_number' => (int) $x->next_number,
            'is_default' => (bool) $x->is_default,
            'agt_series_id' => $x->agt_series_id ?: null,
            'pode_renomear' => SeriesCatalog::podeRenomear($x),
            'description' => $x->description,
        ];
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
