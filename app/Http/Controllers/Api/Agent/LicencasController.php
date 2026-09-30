<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Api\Plataforma\LicenciamentoApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Reutiliza as regras do painel sem colocar tokens de licenca nos logs de repeticao. */
class LicencasController extends LicenciamentoApiController
{
    public function instalacao(int $id): JsonResponse
    {
        return $this->semToken(parent::instalacao($id));
    }

    public function emitir(Request $request): JsonResponse
    {
        $request->validate(['fingerprint' => ['required', 'string', 'max:64'],
            'todos_os_modulos' => ['required', 'boolean'], 'graca' => ['nullable', 'integer', 'min:1', 'max:7']]);
        $request->merge(['graca' => $request->input('graca', 7)]);
        return $this->semToken(parent::emitir($request));
    }

    public function renovar(Request $request, int $id): JsonResponse
    {
        return $this->semToken(parent::renovar($request, $id));
    }

    private function semToken(JsonResponse $response): JsonResponse
    {
        $data = $response->getData(true);
        unset($data['token'], $data['instalacao']['token']);
        if (isset($data['message'])) $data['message'] = 'Licenca gravada; entrega pelo canal de licenciamento. Nenhum token exportado.';
        return $response->setData($data);
    }
}
