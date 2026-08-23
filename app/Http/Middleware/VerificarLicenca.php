<?php

namespace App\Http\Middleware;

use App\Services\Licensing\LicenseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica a licença offline ao tráfego web — mas SÓ na build offline.
 *
 * A primeira linha é a garantia de segurança: com `licensing.enforce` desligado
 * (a cloud), isto é um no-op total — nem sequer lê a licença. Trancar a
 * plataforma partilhada por engano não pode depender de "lembrar de não ligar";
 * depende de o interruptor nascer desligado.
 *
 * Quando ligado:
 *   - BLOQUEADA / INVÁLIDA  → manda para o ecrã de activação (423 no Livewire).
 *   - AVISO / BANNER / SÓ-LEITURA → deixa passar e partilha o estado para o
 *     banner. (O corte de escrita fino do modo só-leitura é incremental — ver
 *     PRD; aqui a arma de dentes é o bloqueio total.)
 */
class VerificarLicenca
{
    public function __construct(private LicenseManager $licencas)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // CLOUD: interruptor desligado → não faz absolutamente nada.
        if (!config('licensing.enforce', false)) {
            return $next($request);
        }

        // Rotas que têm de responder mesmo bloqueado (activação, login, etc.).
        foreach ((array) config('licensing.rotas_livres', []) as $padrao) {
            if ($request->is($padrao)) {
                return $next($request);
            }
        }

        $estado = $this->licencas->estado();

        if ($estado->bloqueiaTudo()) {
            if ($this->ehLivewireOuJson($request)) {
                // 423 Locked: o cliente Livewire trata como recarregar/parar.
                return response()->json(['message' => $estado->motivo], 423);
            }

            return redirect()->route('licenca.index');
        }

        // Estados brandos: deixa passar, mas dá o banner ao layout.
        view()->share('licencaEstado', $estado);

        return $next($request);
    }

    private function ehLivewireOuJson(Request $request): bool
    {
        return $request->hasHeader('X-Livewire') || $request->ajax() || $request->expectsJson();
    }
}
