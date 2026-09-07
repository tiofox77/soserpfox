<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\AGT\AmbienteErradoException;
use App\Services\AGT\PainelDoAdquirente;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AS FACTURAS RECEBIDAS (ADQUIRENTE), para o ecrã em React.
 *
 * Tudo passa pelo `PainelDoAdquirente`. Ver é a permissão da AGT
 * (`invoicing.agt.view`), a mesma que a rota da página exige; confirmar e
 * rejeitar são acções que ESCREVEM na AGT e pedem a de editar
 * (`invoicing.agt.edit`) — não se confirma uma factura de fornecedor com a
 * permissão de espreitar.
 *
 * A empresa é a da SESSÃO (`activeTenantId()`), não a do registo do
 * utilizador: quem tem várias empresas trabalhava na que estivesse escrita
 * na ficha, e não na que tinha aberta.
 */
class AdquirenteApiController extends Controller
{
    public function estado(Request $request): JsonResponse
    {
        $this->exigirVer($request);

        return response()->json([
            'data' => $this->painel()->estado(),
            'permissoes' => ['pode_validar' => $this->podeValidar($request)],
        ]);
    }

    public function listar(Request $request): JsonResponse
    {
        $this->exigirVer($request);
        $d = $request->validate(PainelDoAdquirente::regrasDoPeriodo());

        try {
            $dados = $this->painel()->listar($d['de'], $d['ate'], $d['ambiente'] ?? null);
        } catch (DomainException $e) {
            return $this->recusa($e);
        }

        return response()->json(['data' => $dados]);
    }

    public function detalhe(Request $request): JsonResponse
    {
        $this->exigirVer($request);
        $d = $request->validate(PainelDoAdquirente::regrasDoDocumento());

        try {
            $dados = $this->painel()->detalhe($d['documento'], $d['ambiente'] ?? null);
        } catch (DomainException $e) {
            return $this->recusa($e);
        }

        return response()->json(['data' => $dados]);
    }

    /** Confirmar (C) ou rejeitar (R): escreve na AGT, e é irreversível. */
    public function validar(Request $request): JsonResponse
    {
        $this->exigirValidar($request);
        $d = $request->validate(PainelDoAdquirente::regrasDaValidacao());

        try {
            $dados = $this->painel()->validar(
                $d['documento'],
                $d['accao'],
                isset($d['percentagem_iva_dedutivel']) ? (float) $d['percentagem_iva_dedutivel'] : null,
                isset($d['valor_nao_dedutivel']) ? (float) $d['valor_nao_dedutivel'] : null,
                $d['ambiente'] ?? null
            );
        } catch (DomainException $e) {
            return $this->recusa($e);
        }

        return response()->json(['data' => $dados, 'message' => $dados['mensagem']]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function painel(): PainelDoAdquirente
    {
        $empresa = activeTenantId();

        abort_unless($empresa, 403, __('Empresa não seleccionada ou sem acesso.'));

        return new PainelDoAdquirente((int) $empresa);
    }

    private function recusa(DomainException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'ambiente_errado' => $e instanceof AmbienteErradoException,
        ], 422);
    }

    private function podeValidar(Request $request): bool
    {
        return (bool) ($request->user()?->isPlatformSuperAdmin() || $request->user()?->can('invoicing.agt.edit'));
    }

    private function exigirVer(Request $request): void
    {
        abort_unless(
            $request->user()?->isPlatformSuperAdmin() || $request->user()?->can('invoicing.agt.view'),
            403,
            __('Sem permissão para esta operação.')
        );
    }

    private function exigirValidar(Request $request): void
    {
        abort_unless($this->podeValidar($request), 403, __('Sem permissão para esta operação.'));
    }
}
