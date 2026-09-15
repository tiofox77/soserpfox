<?php

namespace App\Http\Controllers\Privacidade;

use App\Http\Controllers\Controller;
use App\Services\Privacidade\Consentimentos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A ESCOLHA DO AVISO DE COOKIES — a prova dela.
 *
 * O aviso (public/js/consentimento.js) escreve o cookie do seu lado, para a
 * página reagir logo; isto grava a escolha na tabela `consentimentos`, que é
 * o que permite demonstrar mais tarde que houve consentimento (RGPD art. 7.º).
 * Pública e sem CSRF (fica em api/analytics/*): quem ainda não tem conta
 * também escolhe.
 */
class ConsentimentoController extends Controller
{
    public function guardar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'estatisticas' => ['required', 'boolean'],
            'marketing' => ['required', 'boolean'],
            'visitor_id' => ['nullable', 'uuid'],
        ]);

        $user = $request->user();

        foreach (['estatisticas', 'marketing'] as $tipo) {
            Consentimentos::registar($tipo, (bool) $d[$tipo], 'aviso', $user, $d['visitor_id'] ?? null, $request);
        }

        return response()
            ->json(['ok' => true, 'versao' => Consentimentos::versao()])
            ->withCookie(Consentimentos::cookie((bool) $d['estatisticas'], (bool) $d['marketing']));
    }
}
