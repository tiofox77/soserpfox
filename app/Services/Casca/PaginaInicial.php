<?php

namespace App\Services\Casca;

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Invoicing\SalesInvoice;

/**
 * A PÁGINA INICIAL DE QUEM TRABALHA NUMA EMPRESA (`/home`).
 *
 * Era um Blade com Alpine e um `fetch` de 15 em 15 segundos à própria página,
 * que procurava no HTML se o aviso de «pagamento em análise» ainda lá estava.
 * Passa a ser isto e um ecrã em React.
 *
 * O QUE MUDA NAS CONTAS, e é o que se corrigiu ao passar:
 *
 *  • A EMPRESA É A ACTIVA. Os números, a ficha e os módulos liam
 *    `users.tenant_id` — a empresa por omissão — e quem trocava para a sua
 *    segunda empresa via os clientes, os produtos e os módulos da primeira.
 *  • Os três cartões de acesso rápido apontavam para «#». Levam agora ao
 *    ecrã, e só aparecem a quem o pode abrir.
 *
 * O que fica igual: o pacote (plano, valor, renovação, período de teste, a
 * oferta FOX) só a quem trata dele (`billing.manage`), e as facturas do mês
 * seguem a regra das listas — sem `invoicing.documents.all`, são as SUAS.
 */
class PaginaInicial
{
    public function para(User $user): array
    {
        $empresa = $user->activeTenant();
        $pacote = podeVer('billing.manage');

        $subscricao = $empresa?->subscriptions()
            ->with('plan')
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        $pedidoPendente = Order::where('user_id', $user->id)->where('status', 'pending')->exists();

        return [
            'utilizador' => ['nome' => $user->name],
            'hoje' => now()->toDateString(),
            'sem_empresa' => $user->tenants()->count() === 0,
            'avisos' => $pacote ? $this->avisosDoPacote($empresa, $subscricao, $pedidoPendente) : null,
            'acessos' => $this->acessos($user, $empresa),
            'empresa' => $empresa ? [
                'nome' => $empresa->name,
                'nif' => $empresa->nif,
                'email' => $empresa->email,
                'telefone' => $empresa->phone,
            ] : null,
            'subscricao' => $pacote && $subscricao ? [
                'plano' => $subscricao->plan?->name,
                'valor' => round((float) $subscricao->amount, 2),
                'ciclo' => $subscricao->billing_cycle,
                'inicio' => $subscricao->current_period_start?->toDateString(),
                'renovacao' => $subscricao->current_period_end?->toDateString(),
                'estado' => $subscricao->status,
            ] : null,
            'mostra_subscricao' => $pacote,
            'modulos' => $empresa
                ? $empresa->modules()->orderBy('name')->get()->map(fn ($m) => [
                    'nome' => $m->name,
                    'descricao' => $m->description,
                    'icone' => $m->icon,
                    'activo' => (bool) $m->pivot->is_active,
                ])->values()->all()
                : [],
            'numeros' => $empresa ? $this->numeros($empresa->id) : [],
        ];
    }

    private function avisosDoPacote($empresa, $subscricao, bool $pedidoPendente): array
    {
        $plano = $subscricao?->plan;
        $fox = $plano && str_contains(strtolower((string) $plano->slug), 'fox');
        $tecto = $fox ? $empresa?->limiteDeDocumentos() : null;

        return [
            'pedido_pendente' => $pedidoPendente,
            // Sem plano, mas com um pedido já feito, o aviso é o do pedido.
            'sem_plano' => $empresa !== null && ! $subscricao && ! $pedidoPendente,
            'em_teste' => $subscricao?->status === 'trial' ? [
                'plano' => $plano?->name,
                'dias' => (int) ($plano?->trial_days ?? 0),
                'termina_em' => $subscricao->trial_ends_at?->toDateString(),
            ] : null,
            // O que a oferta dá HOJE a esta empresa — não o que o cartaz dizia.
            'fox' => $fox ? [
                'tecto' => $tecto,
                'emitidos' => $tecto !== null ? $empresa->documentosEmitidos() : null,
            ] : null,
            'empresa' => $empresa?->name,
        ];
    }

    private function acessos(User $user, $empresa): array
    {
        if ($user->isSuperAdmin()) {
            return [
                ['rotulo' => __('Super Admin'), 'nota' => __('Gerir todo o sistema'), 'icone' => 'fa-crown', 'cor' => 'amarelo', 'url' => route('superadmin.dashboard')],
                ['rotulo' => __('Empresas'), 'nota' => __('Gerir organizações'), 'icone' => 'fa-building', 'cor' => 'verde', 'url' => route('superadmin.tenants')],
                ['rotulo' => __('Facturação da plataforma'), 'nota' => __('Faturas e pagamentos'), 'icone' => 'fa-file-invoice-dollar', 'cor' => 'azul', 'url' => route('superadmin.billing')],
            ];
        }

        $temFacturacao = $empresa?->hasModule('invoicing');

        return array_values(array_filter([
            $temFacturacao && podeVer('invoicing.dashboard.view')
                ? ['rotulo' => __('Faturação'), 'nota' => __('Clientes, Faturas e Pagamentos'), 'icone' => 'fa-file-invoice', 'cor' => 'verde', 'url' => route('invoicing.dashboard')] : null,
            $temFacturacao && podeVer('invoicing.products.view')
                ? ['rotulo' => __('Produtos/Serviços'), 'nota' => __('Catálogo e Preços'), 'icone' => 'fa-boxes', 'cor' => 'roxo', 'url' => route('invoicing.products')] : null,
            podeVer('users.view', 'users.manage')
                ? ['rotulo' => __('Utilizadores'), 'nota' => __('Gerir equipa'), 'icone' => 'fa-users', 'cor' => 'azul', 'url' => route('users.index')] : null,
        ]));
    }

    /** Cada número só a quem pode ver aquilo — e as facturas pela regra do autor. */
    private function numeros(int $empresaId): array
    {
        $numeros = [];

        if (podeVer('customers.view')) {
            $numeros['clientes'] = Client::where('tenant_id', $empresaId)->count();
        }

        if (podeVer('products.view')) {
            $numeros['produtos'] = Product::where('tenant_id', $empresaId)->count();
        }

        if (podeVer('invoicing.dashboard.view', 'invoicing.sales.invoices.view')) {
            $base = fn () => escopoDoAutor(
                SalesInvoice::where('tenant_id', $empresaId)
                    ->where('invoice_date', '>=', now()->startOfMonth())
                    ->where('status', '!=', 'cancelled')
            );
            $numeros['facturas_do_mes'] = $base()->count();
            $numeros['facturado_no_mes'] = round((float) $base()->sum('total'), 2);
            $numeros['so_o_seu'] = soVeOSeu();
        }

        return $numeros;
    }
}
