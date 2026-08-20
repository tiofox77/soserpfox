<?php

namespace App\Services\Agent;

use App\Models\Tenant;
use App\Support\NifAngolano;
use Illuminate\Support\Facades\DB;

/**
 * Sinais agregados de uma empresa, para o agente ler sem tocar em dados
 * operacionais em bruto.
 *
 * Regra absoluta: SÓ CONTAGENS. Nunca sai daqui o nome de um artigo, um
 * preço, um endereço de email, um número de telefone nem o NIF completo.
 * O agente precisa de saber "esta conta está vazia" ou "300 artigos sem
 * preço", não do catálogo.
 */
class SinaisDoTenant
{
    /** Saúde do catálogo, num único GROUP BY. */
    public function produtos(int $tenantId): array
    {
        // invoicing_products NÃO tem global scope de tenant: o where é
        // obrigatório, senão soma-se o catálogo de todas as empresas. E
        // whereNull(deleted_at) porque a tabela usa SoftDeletes.
        $r = DB::table('invoicing_products')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) total,
                SUM(is_active = 1) activos,
                SUM(price <= 0) sem_preco,
                SUM(barcode IS NULL OR barcode = "") sem_codigo_barras,
                SUM(manage_stock = 1 AND type = "produto" AND stock_quantity <= 0) sem_stock,
                SUM(tax_type = "isento") isentos,
                SUM(tax_type = "iva") com_iva,
                SUM(category_id IS NULL) sem_categoria')
            ->first();

        return [
            'total'             => (int) $r->total,
            'activos'           => (int) $r->activos,
            'sem_preco'         => (int) $r->sem_preco,
            'sem_codigo_barras' => (int) $r->sem_codigo_barras,
            'sem_stock'         => (int) $r->sem_stock,
            'isentos'           => (int) $r->isentos,
            'com_iva'           => (int) $r->com_iva,
            'sem_categoria'     => (int) $r->sem_categoria,
            'conta_vazia'       => ((int) $r->total) === 0,
        ];
    }

    /**
     * Relatório de envios dos últimos 30 dias, só contagens.
     *
     * Nota honesta: no email, "enviado" significa aceite pelo servidor de
     * correio, NÃO entregue — não há webhook de retorno neste sistema. E as
     * falhas de email são subcontadas (só entram quando registadas à mão),
     * por isso "por_confirmar" pode incluir envios que na verdade falharam.
     */
    public function envios(int $tenantId): array
    {
        $desde = now()->subDays(30);

        $email = DB::table('email_logs')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $desde)
            ->selectRaw('COUNT(*) total,
                SUM(status = "sent") enviados,
                SUM(status = "failed") falhados,
                SUM(status = "pending") por_confirmar')
            ->first();

        $sms = DB::table('sms_logs')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $desde)
            ->selectRaw('COUNT(*) total,
                SUM(status = "sent") enviados,
                SUM(status = "failed") falhados')
            ->first();

        return [
            'janela_dias' => 30,
            'email' => [
                'total'         => (int) $email->total,
                'enviados'      => (int) $email->enviados,
                'falhados'      => (int) $email->falhados,
                'por_confirmar' => (int) $email->por_confirmar,
            ],
            'sms' => [
                'total'    => (int) $sms->total,
                'enviados' => (int) $sms->enviados,
                'falhados' => (int) $sms->falhados,
            ],
            'nota' => 'email "enviado" = aceite pelo servidor, não confirma entrega.',
        ];
    }

    /**
     * NIF da empresa, classificado e POR INTEIRO.
     *
     * Ia mascarado ('54******23'). Foi retirado a pedido de quem gere a
     * plataforma: um NIF cortado ao meio não se verifica contra a AGT nem se
     * compara com um documento — que é exactamente para o que o agente
     * precisa dele quando encontra um registo por corrigir.
     */
    public function nif(Tenant $tenant): array
    {
        $c = NifAngolano::classificar($tenant->nif);

        return [
            'estado' => $c['estado'],
            'nif'    => $c['nif'],
            'motivo' => $c['motivo'],
        ];
    }
}
