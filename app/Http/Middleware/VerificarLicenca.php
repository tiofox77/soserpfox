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

        // Integridade dos ficheiros PHP: o serviço `soserp-integridade` levanta
        // esta flag se algum ficheiro da app foi adulterado. Bloqueia tudo.
        if (is_file(storage_path('app/integridade-falha.flag'))) {
            if ($this->ehJson($request)) {
                return response()->json(['message' => 'Integridade dos ficheiros comprometida.'], 423);
            }

            return response(
                '<!doctype html><meta charset="utf-8"><title>soserp</title>'
                . '<body style="font-family:system-ui;max-width:640px;margin:12vh auto;padding:0 20px;color:#0f172a">'
                . '<h1>Integridade comprometida</h1><p>Os ficheiros da aplicação foram alterados desde a instalação. '
                . 'Por segurança, o acesso está bloqueado. Contacte o fornecedor.</p></body>',
                423
            );
        }

        $estado = $this->licencas->estado();

        if ($estado->bloqueiaTudo()) {
            if ($this->ehJson($request)) {
                // 423 Locked: o ecrã percebe que tem de parar.
                return response()->json(['message' => $estado->motivo], 423);
            }

            // URL directo e não route(): as rotas de licença só se registam
            // com o enforce ligado (ver routes/web.php) e um route() faria
            // explodir com RouteNotFoundException justamente quando o sistema
            // já está em apuros.
            return redirect('/licenca');
        }

        // Instalação fresca: licença válida, mas ainda NÃO há empresa. Obriga o
        // assistente de setup. O próprio /setup (a página e o envio) passa —
        // nesta fase não há dados a proteger.
        if (!\App\Models\Tenant::query()->exists()) {
            if ($request->is('setup')) {
                return $next($request);
            }
            if ($this->ehJson($request)) {
                return response()->json(['message' => 'Configuração inicial necessária.'], 409);
            }

            return redirect('/setup');
        }

        // Estados brandos: deixa passar, mas dá o banner ao layout.
        view()->share('licencaEstado', $estado);

        return $next($request);
    }

    private function ehJson(Request $request): bool
    {
        return $request->ajax() || $request->expectsJson();
    }
}
