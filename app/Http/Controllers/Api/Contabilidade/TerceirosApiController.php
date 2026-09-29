<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ContaCorrente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A CONTA-CORRENTE DE TERCEIROS (clientes e fornecedores) — o ecrã de React
 * `contabilidade/terceiros`. As contas saem do `Services\Accounting\ContaCorrente`;
 * aqui só se valida, se autoriza e se responde.
 */
class TerceirosApiController extends Controller
{
    public function __construct(private ContaCorrente $contaCorrente)
    {
    }

    private function exigir(Request $request): void
    {
        abort_unless($request->user()?->can('accounting.partners.view'), 403, __('Sem permissão para esta operação.'));
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    /** Os saldos: um terceiro por linha. */
    public function index(Request $request): JsonResponse
    {
        $this->exigir($request);

        $f = $request->validate([
            'tipo'      => ['required', Rule::in(ContaCorrente::TIPOS)],
            'ate'       => ['nullable', 'date'],
            'procura'   => ['nullable', 'string', 'max:100'],
            'com_saldo' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->contaCorrente->saldos(
            $this->tenantId(),
            $f['tipo'],
            $f['ate'] ?? null,
            $f['procura'] ?? null,
            (bool) ($f['com_saldo'] ?? false),
        ));
    }

    /** O extrato de um terceiro, com saldo acumulado. */
    public function extrato(Request $request, string $tipo, int $id): JsonResponse
    {
        $this->exigir($request);
        abort_unless(in_array($tipo, ContaCorrente::TIPOS, true), 404);

        $f = $request->validate([
            'de'  => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ]);

        return response()->json($this->contaCorrente->extrato(
            $this->tenantId(),
            $tipo,
            $id,
            $f['de'] ?? null,
            $f['ate'] ?? null,
        ));
    }

    /** A antiguidade de saldos (0–30, 31–60, 61–90, 91–180, +180 dias). */
    public function antiguidade(Request $request): JsonResponse
    {
        $this->exigir($request);

        $f = $request->validate([
            'tipo'     => ['required', Rule::in(ContaCorrente::TIPOS)],
            'data'     => ['nullable', 'date'],
            'terceiro' => ['nullable', 'integer'],
        ]);

        return response()->json($this->contaCorrente->antiguidade(
            $this->tenantId(),
            $f['tipo'],
            $f['data'] ?? null,
            isset($f['terceiro']) ? (int) $f['terceiro'] : null,
        ));
    }
}
