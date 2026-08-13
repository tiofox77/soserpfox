<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Que língua fala este pedido.
 *
 * A resolução, por ordem de quem manda mais:
 *
 *   1. `?lang=` no pedido — é como se muda, e fica guardado (no perfil se há
 *      sessão, num cookie se não há);
 *   2. a escolha do utilizador (users.locale);
 *   3. o ponto de partida da empresa (tenants.locale);
 *   4. o cookie, para visitantes (registo, página inicial);
 *   5. português.
 *
 * O Carbon segue a mesma língua, para os "há 3 dias" saírem certos de borla.
 * Tudo num middleware só: a regra de que língua se fala não pode estar
 * espalhada por ecrãs.
 */
class DefinirLingua
{
    /** As línguas que o sistema fala. Acrescentar aqui quando houver mais. */
    public const LINGUAS = ['pt', 'en', 'fr'];

    private const COOKIE = 'soserp_lingua';

    /** Um ano: a língua de uma pessoa não muda com a sessão. */
    private const MINUTOS_DO_COOKIE = 60 * 24 * 365;

    public function handle(Request $request, Closure $next): Response
    {
        $pedida = $this->pedidaNoUrl($request);
        $lingua = $pedida
            ?? $this->doUtilizador()
            ?? $this->daEmpresa()
            ?? $this->doCookie($request)
            ?? config('app.locale', 'pt');

        app()->setLocale($lingua);
        Carbon::setLocale($lingua);

        $response = $next($request);

        // A escolha explícita fica guardada — no perfil e no cookie, para
        // valer também antes de haver sessão (registo, landing).
        if ($pedida) {
            $this->guardar($pedida);
            $response->headers->setCookie(
                cookie(self::COOKIE, $pedida, self::MINUTOS_DO_COOKIE)
            );
        }

        return $response;
    }

    private function pedidaNoUrl(Request $request): ?string
    {
        $lang = $request->query('lang');

        return is_string($lang) && in_array($lang, self::LINGUAS, true) ? $lang : null;
    }

    private function doUtilizador(): ?string
    {
        $locale = auth()->user()?->locale;

        return in_array($locale, self::LINGUAS, true) ? $locale : null;
    }

    private function daEmpresa(): ?string
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        // Uma consulta directa à coluna, e não activeTenant(): esse método tem
        // memória por instância do utilizador, e num processo que viva mais do
        // que um pedido (testes, Octane) a memória é de ANTES de a língua ter
        // mudado. Para uma coluna, o caminho curto é também o correcto.
        try {
            $tenantId = session('active_tenant_id') ?? $user->tenant_id;

            $locale = $tenantId
                ? \App\Models\Tenant::whereKey($tenantId)->value('locale')
                : null;
        } catch (\Throwable) {
            // A língua nunca pode ser a razão de um pedido falhar.
            return null;
        }

        return in_array($locale, self::LINGUAS, true) ? $locale : null;
    }

    private function doCookie(Request $request): ?string
    {
        $locale = $request->cookie(self::COOKIE);

        return in_array($locale, self::LINGUAS, true) ? $locale : null;
    }

    private function guardar(string $lingua): void
    {
        $user = auth()->user();

        if ($user && $user->locale !== $lingua) {
            try {
                $user->forceFill(['locale' => $lingua])->save();
            } catch (\Throwable $e) {
                \Log::warning('Não foi possível guardar a língua no perfil', ['erro' => $e->getMessage()]);
            }
        }
    }
}
