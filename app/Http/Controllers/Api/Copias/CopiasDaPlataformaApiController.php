<?php

namespace App\Http\Controllers\Api\Copias;

use App\Services\Copias\Destinos\OAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AS CÓPIAS DA PLATAFORMA — a base inteira. Só o super admin (grupo `superadmin`).
 *
 * E as aplicações OAuth: o «client id» e o «client secret» de Google, Microsoft
 * e Dropbox, que depois servem a plataforma e todas as empresas.
 */
class CopiasDaPlataformaApiController extends CopiasApiController
{
    protected function ambito(Request $request): ?int
    {
        abort_unless($request->user()?->isPlatformSuperAdmin(), 403);

        return null;
    }

    public function aplicacoes(Request $request): JsonResponse
    {
        $this->ambito($request);

        return response()->json([
            'retorno' => OAuth::retorno(),
            'aplicacoes' => collect(OAuth::FORNECEDORES)->mapWithKeys(fn ($f) => [$f => [
                'configurado' => OAuth::configurado($f),
                'client_id' => OAuth::credenciais($f)['client_id'],
            ]]),
        ]);
    }

    public function guardarAplicacao(Request $request, string $fornecedor): JsonResponse
    {
        $this->ambito($request);
        abort_unless(in_array($fornecedor, OAuth::FORNECEDORES, true), 404);

        $d = $request->validate([
            'client_id' => ['required', 'string', 'max:300'],
            'client_secret' => [OAuth::configurado($fornecedor) ? 'nullable' : 'required', 'string', 'max:300'],
        ]);

        OAuth::guardarCredenciais(
            $fornecedor,
            $d['client_id'],
            $d['client_secret'] ?: (string) OAuth::credenciais($fornecedor)['client_secret']
        );

        return response()->json(['message' => __('Aplicação :f guardada.', ['f' => ucfirst($fornecedor)])]);
    }
}
