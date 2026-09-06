<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\POS\DefinicaoDePin;
use App\Services\POS\LeituraDeCopiaOffline;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O QUE É DO MODO OFFLINE, para os ecrãs em React: recuperar uma cópia de
 * segurança do PWA, e definir o PIN de turno. Pelos mesmos serviços dos
 * ecrãs Livewire.
 */
class OfflineApiController extends Controller
{
    /* ─── A cópia de segurança ────────────────────────────────────────── */

    /** Lê o ficheiro e diz o que lá está, sem gravar nada. */
    public function analisar(Request $request, LeituraDeCopiaOffline $leitura): JsonResponse
    {
        $this->exigirVendas($request);

        try {
            $inventario = $leitura->analisar($request->file('ficheiro'), (int) activeTenantId());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['inventario' => $inventario]);
    }

    public function importar(Request $request, LeituraDeCopiaOffline $leitura): JsonResponse
    {
        $this->exigirVendas($request);

        try {
            $resultado = $leitura->importar($request->file('ficheiro'), (int) activeTenantId(), (int) $request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('ImportarCopiaOffline', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'A importação falhou: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'resultado' => $resultado,
            'message' => sprintf('%d venda(s) importada(s), %d já existiam.', $resultado['importadas'] ?? 0, $resultado['ja_existiam'] ?? 0),
        ]);
    }

    /* ─── O PIN de turno ──────────────────────────────────────────────── */

    public function pin(Request $request): JsonResponse
    {
        return response()->json(['ja_tem_pin' => (bool) $request->user()->temPinPos(), 'nome' => $request->user()->name]);
    }

    public function definirPin(Request $request, DefinicaoDePin $definicao): JsonResponse
    {
        $d = $request->validate(DefinicaoDePin::regras(), DefinicaoDePin::mensagens());

        // A password errada e o PIN óbvio saem como erros de validação, no campo certo.
        $definicao->definir($request->user(), $d['pin'], $d['password']);

        return response()->json(['ja_tem_pin' => true, 'message' => __('PIN definido. Vai para os tablets na próxima sincronização com internet.')]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** Quem pode emitir é quem pode recuperar o que já foi emitido offline. */
    private function exigirVendas(Request $request): void
    {
        abort_unless(
            $request->user()?->can('invoicing.pos.sell') || $request->user()?->isSuperAdmin(),
            403,
            __('Sem permissão para importar vendas.')
        );
    }
}
