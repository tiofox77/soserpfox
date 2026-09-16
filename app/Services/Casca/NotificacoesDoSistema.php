<?php

namespace App\Services\Casca;

use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Invoicing\SomasDasFacturas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AS NOTIFICAÇÕES QUE O SISTEMA CALCULA NA HORA — as do sino que não estão na
 * tabela de notificações: plano activado, prazo a acabar, lotes expirados,
 * stock abaixo do mínimo, facturas vencidas, pedidos por aprovar, limite de
 * empresas.
 *
 * Duas correcções em relação ao componente:
 *
 *  · AS FACTURAS VENCIDAS CONTAVAM-SE PELO NOME DO ESTADO (`pending`, `sent`,
 *    `partial`), que na maior parte das facturas não existe — o aviso ficava
 *    calado com dívida em atraso. Contam-se pelo SALDO, com a mesma expressão
 *    dos cartões da lista (`SomasDasFacturas::sqlPorReceber`), e somam o que
 *    FALTA e não o total.
 *  · O PRAZO DA SUBSCRIÇÃO sai do mesmo sítio que o contador do topo
 *    (`PrazoDaSubscricao`). Lia `ends_at`, vazio numa subscrição activa.
 */
class NotificacoesDoSistema
{
    public function __construct(private PrazoDaSubscricao $prazo) {}

    /** @return array<int, array<string, string>> */
    public function para(User $user): array
    {
        $avisos = [];
        $tenant = $user->activeTenant();
        $facturacao = $tenant && $user->hasActiveModule('invoicing');
        $conta = route('my-account').'?tab=plan';

        if ($tenant) {
            $recente = $tenant->subscriptions()
                ->where('status', 'active')
                ->where('current_period_start', '>=', now()->subHours(48))
                ->orderByDesc('current_period_start')
                ->with('plan:id,name')
                ->first();

            if ($recente) {
                $avisos[] = $this->aviso('success', 'fa-circle-check', 'green', __('Plano Ativado!'),
                    __('Seu plano :plano foi ativado com sucesso!', ['plano' => $recente->plan?->name]),
                    $recente->current_period_start->diffForHumans(), $conta);
            }

            $prazo = $this->prazo->para($user);

            if ($prazo && ! $prazo['expirado'] && $prazo['dias'] <= 15) {
                $urgente = $prazo['dias'] <= 3;

                $avisos[] = $this->aviso('warning', $urgente ? 'fa-triangle-exclamation' : 'fa-clock', $prazo['cor'] === 'green' ? 'yellow' : $prazo['cor'],
                    $urgente ? __('Urgente: Subscription Expirando!') : __('Lembre-se de Renovar'),
                    __('Seu plano expira em :dias dia(s). Renove para continuar usando o sistema.', ['dias' => $prazo['dias']]),
                    Carbon::parse($prazo['termina_em'])->diffForHumans(), $conta);
            }
        }

        if ($facturacao) {
            // Sem janela de dias: um lote que expirou há um mês e continua na
            // prateleira é MAIS urgente do que um de ontem.
            $expirados = ProductBatch::where('tenant_id', $tenant->id)
                ->where('quantity_available', '>', 0)
                ->whereDate('expiry_date', '<', today())
                ->count();

            if ($expirados > 0) {
                $avisos[] = $this->aviso('danger', 'fa-circle-xmark', 'red', __('Produtos Expirados!'),
                    __(':n lote(s) de produtos já expiraram. Ação urgente necessária!', ['n' => $expirados]),
                    __('Agora'), route('invoicing.expiry-report', ['reportType' => 'expired']));
            }

            $aExpirar = ProductBatch::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->whereDate('expiry_date', '>=', today())
                ->whereDate('expiry_date', '<=', today()->addDays(7))
                ->count();

            if ($aExpirar > 0) {
                $avisos[] = $this->aviso('warning', 'fa-triangle-exclamation', 'orange', __('Produtos Expirando em Breve'),
                    __(':n lote(s) de produtos expiram nos próximos 7 dias.', ['n' => $aExpirar]),
                    __('Requer atenção'), route('invoicing.expiry-report', ['reportType' => 'expiring_soon']));
            }

            // O mínimo vive no PRODUTO (`stock_min`), não na linha de stock —
            // a coluna da linha está a zero em toda a base. Query builder e
            // colunas qualificadas: o escopo de empresa sem tabela tornava o
            // `tenant_id` ambíguo com o join.
            $baixo = DB::table('invoicing_stocks')
                ->join('invoicing_products', 'invoicing_products.id', '=', 'invoicing_stocks.product_id')
                ->where('invoicing_stocks.tenant_id', $tenant->id)
                ->where('invoicing_products.stock_min', '>', 0)
                ->where('invoicing_products.is_active', true)
                ->whereColumn('invoicing_stocks.quantity', '<=', 'invoicing_products.stock_min')
                ->count();

            if ($baixo > 0) {
                $avisos[] = $this->aviso('warning', 'fa-box-open', 'yellow', __('Baixo Stock!'),
                    __(':n produto(s) com estoque abaixo do mínimo.', ['n' => $baixo]),
                    __('Requer reposição'), route('invoicing.stock'));
            }

            foreach ($this->facturas($tenant->id) as $aviso) {
                $avisos[] = $aviso;
            }
        }

        if ($user->is_super_admin) {
            $pedidos = Order::where('status', 'pending')->count();

            if ($pedidos > 0) {
                $avisos[] = $this->aviso('info', 'fa-cart-shopping', 'blue', __('Pedidos Pendentes'),
                    __(':n pedido(s) aguardando aprovação.', ['n' => $pedidos]),
                    __('Requer atenção'), route('superadmin.billing'));
            }

            // Os pagamentos de facturas enviados pelos revendedores, por confirmar.
            $pagamentos = \App\Models\Invoice::whereNotNull('payment_submitted_at')->whereIn('status', ['pending', 'overdue'])->count();

            if ($pagamentos > 0) {
                $avisos[] = $this->aviso('info', 'fa-file-invoice-dollar', 'green', __('Pagamentos por confirmar'),
                    __(':n pagamento(s) de facturas enviado(s) por revendedores.', ['n' => $pagamentos]),
                    __('Requer atenção'), route('superadmin.billing'));
            }

            // Os pedidos para ser revendedor (programa de revendedores, RV-02).
            $revendedores = \App\Models\Reseller::where('status', 'pendente')->count();

            if ($revendedores > 0) {
                $avisos[] = $this->aviso('info', 'fa-handshake', 'purple', __('Pedidos de revendedor'),
                    __(':n pedido(s) para ser revendedor por aprovar.', ['n' => $revendedores]),
                    __('Requer atenção'), route('superadmin.revendedores'));
            }
        } else {
            $empresas = $user->tenants()->count();
            $maximo = $user->getMaxCompaniesLimit();

            if ($empresas >= $maximo && $maximo < 999) {
                $avisos[] = $this->aviso('info', 'fa-building', 'blue', __('Limite de Empresas Atingido'),
                    __('Você atingiu o limite de :max empresa(s). Faça upgrade para criar mais.', ['max' => $maximo]),
                    __('Ação disponível'), $conta);
            }
        }

        return $avisos;
    }

    /** A «assinatura» do que está à vista: se nada mudar, continua lido. */
    public static function assinatura(array $avisos): string
    {
        return md5(collect($avisos)->map(fn ($n) => ($n['titulo'] ?? '').'|'.($n['mensagem'] ?? ''))->implode('||'));
    }

    /** As vencidas e as que vencem nos próximos sete dias — pelo saldo. */
    private function facturas(int $tenantId): array
    {
        $t = (new SalesInvoice())->getTable();
        $falta = SomasDasFacturas::sqlPorReceber($t);
        $hoje = today()->toDateString();
        $daquiA3 = today()->addDays(3)->toDateString();
        $daquiA7 = today()->addDays(7)->toDateString();
        $haMaisDe30 = today()->subDays(30)->toDateString();

        $linha = DB::table($t)
            ->where("{$t}.tenant_id", $tenantId)
            ->whereNull("{$t}.deleted_at")
            ->whereNotNull("{$t}.due_date")
            ->whereRaw("({$falta}) > 0")
            ->selectRaw(
                "SUM(CASE WHEN {$t}.due_date < ? THEN 1 ELSE 0 END) AS vencidas, "
                ."COALESCE(SUM(CASE WHEN {$t}.due_date < ? THEN {$falta} ELSE 0 END), 0) AS valor_vencido, "
                ."SUM(CASE WHEN {$t}.due_date < ? THEN 1 ELSE 0 END) AS criticas, "
                ."SUM(CASE WHEN {$t}.due_date > ? AND {$t}.due_date <= ? THEN 1 ELSE 0 END) AS a_vencer, "
                ."COALESCE(SUM(CASE WHEN {$t}.due_date > ? AND {$t}.due_date <= ? THEN {$falta} ELSE 0 END), 0) AS valor_a_vencer, "
                ."SUM(CASE WHEN {$t}.due_date > ? AND {$t}.due_date <= ? THEN 1 ELSE 0 END) AS urgentes",
                [$hoje, $hoje, $haMaisDe30, $hoje, $daquiA7, $hoje, $daquiA7, $hoje, $daquiA3]
            )
            ->first();

        $lista = route('invoicing.sales.invoices');
        $avisos = [];

        if ((int) ($linha->vencidas ?? 0) > 0) {
            $criticas = (int) $linha->criticas;

            $avisos[] = $this->aviso('danger', $criticas > 0 ? 'fa-triangle-exclamation' : 'fa-circle-exclamation', $criticas > 0 ? 'red' : 'orange', __('Faturas Vencidas!'),
                __(':n fatura(s) vencida(s) com :valor Kz por receber', ['n' => (int) $linha->vencidas, 'valor' => number_format((float) $linha->valor_vencido, 2, ',', '.')])
                    .($criticas > 0 ? ' '.__('(:n críticas > 30 dias)', ['n' => $criticas]) : ''),
                __('Ação urgente'), $lista);
        }

        if ((int) ($linha->a_vencer ?? 0) > 0) {
            $urgentes = (int) $linha->urgentes;

            $avisos[] = $this->aviso('warning', 'fa-clock', $urgentes > 0 ? 'orange' : 'yellow', __('Faturas Vencendo em Breve'),
                __(':n fatura(s) vencem nos próximos 7 dias - por receber: :valor Kz', ['n' => (int) $linha->a_vencer, 'valor' => number_format((float) $linha->valor_a_vencer, 2, ',', '.')])
                    .($urgentes > 0 ? ' '.__('(:n em 3 dias)', ['n' => $urgentes]) : ''),
                __('Lembrar clientes'), $lista);
        }

        return $avisos;
    }

    private function aviso(string $tipo, string $icone, string $cor, string $titulo, string $mensagem, string $quando, string $ligacao): array
    {
        return ['tipo' => $tipo, 'icone' => $icone, 'cor' => $cor, 'titulo' => $titulo, 'mensagem' => $mensagem, 'quando' => $quando, 'ligacao' => $ligacao];
    }
}
