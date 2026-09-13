<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\EcraReact;
use App\Support\Entrada;
use Carbon\Carbon;

/**
 * AS DUAS PORTAS FECHADAS — a empresa desactivada e a subscrição expirada.
 *
 * Eram duas rotas com uma closure a devolver um Blade solto (com o Tailwind de
 * um CDN). Os ecrãs são React; daqui sai o que eles mostram, lido da sessão
 * que o `CheckTenantActive` e o `CheckSubscription` deixaram.
 */
class EntradaController extends Controller
{
    public function empresaDesactivada()
    {
        $nome = session('tenant_name');
        $em = session('deactivated_at');

        return EcraReact::solta('entrada/empresa-desactivada', 'Acesso Bloqueado', Entrada::comum() + [
            'login' => route('login'),
            'empresa' => session('tenant_deactivated') ? [
                'nome' => $nome ? (string) $nome : null,
                'em' => $em ? (string) ($em instanceof \DateTimeInterface ? Carbon::instance($em)->format('d/m/Y H:i') : $em) : null,
                'motivo' => session('deactivation_reason') ? (string) session('deactivation_reason') : null,
            ] : null,
        ])();
    }

    public function subscricaoExpirada()
    {
        $u = auth()->user();
        $empresa = $u->activeTenant();
        $subscricao = session('subscription');

        return EcraReact::solta('entrada/subscricao-expirada', 'Subscription Expirada', Entrada::comum() + [
            'empresa' => $empresa ? ['nome' => $empresa->name, 'email' => $u->email] : null,
            'expirou' => $subscricao ? [
                'em' => $subscricao->current_period_end?->format('d/m/Y H:i'),
                'plano' => $subscricao->plan?->name,
            ] : null,
            // Os planos da montra — os mesmos do site e do registo.
            'planos' => Plan::publico()->orderBy('order')->get()->map(fn (Plan $p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'mensal' => (float) $p->price_monthly,
                'tem_anual' => (float) $p->price_yearly > 0,
                'utilizadores' => (int) $p->max_users,
                'armazenamento_gb' => round(((int) $p->max_storage_mb) / 1000, 1),
                'destaque' => (bool) $p->is_featured,
                'funcionalidades' => array_values(array_filter((array) ($p->features ?? []), 'is_string')),
                'escolher' => route('my-account') . '?tab=plan&select=' . $p->id,
            ])->values()->all(),
            'conta' => route('my-account'),
            'sair' => route('logout'),
        ])();
    }
}
