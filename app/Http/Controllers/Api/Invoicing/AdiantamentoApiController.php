<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoicing\Advance;
use App\Services\Invoicing\EmissorDeAdiantamentos;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REGISTAR UM ADIANTAMENTO, para o ecrã em React.
 *
 * As regras vivem no `EmissorDeAdiantamentos`, o mesmo que o Livewire chama.
 * Editar por URL o adiantamento de um colega é vê-lo por inteiro: sem a
 * permissão de ver os documentos de todos, só se abre o que é seu.
 */
class AdiantamentoApiController extends Controller
{
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.advances.create');

        return response()->json([
            'clientes' => Client::where('tenant_id', activeTenantId())->where('is_active', true)
                ->orderBy('name')->limit(500)->get(['id', 'name', 'nif']),
            'formas' => collect(EmissorDeAdiantamentos::FORMAS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
            'permissoes' => ['pode_criar' => true],
        ]);
    }

    public function mostrar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.advances.edit');

        $a = $this->encontrar($request, $id);

        return response()->json(['data' => $this->um($a)]);
    }

    public function guardar(Request $request, EmissorDeAdiantamentos $emissor): JsonResponse
    {
        $this->exigir($request, 'invoicing.advances.create');

        $dados = $request->validate($emissor->regras());
        $this->clienteDaEmpresa($dados['client_id']);

        $a = $emissor->criar($dados, activeTenantId(), $request->user()?->id);

        return response()->json([
            'data' => $this->um($a),
            'message' => __('Adiantamento :n registado.', ['n' => $a->advance_number]),
        ], 201);
    }

    public function actualizar(Request $request, EmissorDeAdiantamentos $emissor, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.advances.edit');

        $a = $this->encontrar($request, $id);
        $dados = $request->validate($emissor->regras());
        $this->clienteDaEmpresa($dados['client_id']);

        try {
            $emissor->actualizar($a, $dados);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['amount' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'data' => $this->um($a->fresh()),
            'message' => __('Adiantamento :n actualizado.', ['n' => $a->advance_number]),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function encontrar(Request $request, int $id): Advance
    {
        $u = $request->user();

        return Advance::where('tenant_id', activeTenantId())
            ->when(! $u?->can('invoicing.documents.all'), fn ($q) => $q->where(fn ($w) => $w->where('created_by', $u?->id)->orWhereNull('created_by')))
            ->findOrFail($id);
    }

    private function clienteDaEmpresa(int $clienteId): void
    {
        abort_unless(
            Client::where('tenant_id', activeTenantId())->whereKey($clienteId)->exists(),
            422,
            __('Cliente desconhecido nesta empresa.')
        );
    }

    private function um(Advance $a): array
    {
        return [
            'id' => $a->id,
            'numero' => $a->advance_number,
            'client_id' => $a->client_id,
            'payment_date' => optional($a->payment_date)->toDateString() ?? (string) $a->payment_date,
            'amount' => round((float) $a->amount, 2),
            'used_amount' => round((float) $a->used_amount, 2),
            'remaining_amount' => round((float) $a->remaining_amount, 2),
            'payment_method' => $a->payment_method,
            'purpose' => $a->purpose,
            'notes' => $a->notes,
            'status' => $a->status,
            'pode_editar' => (float) $a->used_amount <= 0,
            'abrir' => '/invoicing/advances',
            'pdf' => '/invoicing/advances/' . $a->id . '/pdf',
        ];
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
