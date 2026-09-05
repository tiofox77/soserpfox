<?php

namespace App\Services\CRM;

use App\Models\CRM\Opportunity;
use App\Models\Invoicing\SalesInvoice;
use App\Services\Invoicing\ModuleInvoiceService;
use Illuminate\Support\Facades\DB;

/**
 * O negócio ganho vira documento — e o CRM fica a saber qual.
 *
 * PELA PORTA ÚNICA. A factura sai do `ModuleInvoiceService`, o mesmo que o
 * hotel, a oficina, o salão e os projetos usam. Montar aqui um documento à
 * mão significaria uma segunda implementação dos impostos, da retenção de IRT
 * e da comunicação à AGT — que é exactamente o que já custou caro nesta casa.
 *
 * NASCE EM RASCUNHO, de propósito: o valor do funil é uma expectativa
 * negociada, não uma factura conferida. Quem ganha revê e assume.
 */
class FacturarOportunidade
{
    public function __construct(private ModuleInvoiceService $facturacao)
    {
    }

    /**
     * @throws \InvalidArgumentException  com a razão, para o ecrã a mostrar
     */
    public function facturar(Opportunity $oportunidade, int $tenantId): SalesInvoice
    {
        if ((int) $oportunidade->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Esta oportunidade não é desta empresa.');
        }

        if ($oportunidade->status !== 'won') {
            throw new \InvalidArgumentException('Só se factura um negócio ganho. Marque-o como ganho primeiro.');
        }

        if (! $oportunidade->client_id) {
            throw new \InvalidArgumentException('Esta oportunidade não tem cliente — converta o lead ou escolha um cliente.');
        }

        // A marca, e não o estado, é o que impede facturar duas vezes — a mesma
        // regra das horas dos projetos.
        if ($oportunidade->sales_invoice_id) {
            throw new \InvalidArgumentException('Este negócio já foi facturado.');
        }

        $valor = (float) $oportunidade->amount;

        if ($valor <= 0) {
            throw new \InvalidArgumentException('Um negócio sem valor não dá documento. Escreva o valor acordado.');
        }

        return DB::transaction(function () use ($oportunidade, $tenantId, $valor) {
            $factura = $this->facturacao->emitir([
                'tenant_id' => $tenantId,
                'client_id' => $oportunidade->client_id,
                'status'    => 'draft',
                'lines'     => [[
                    // O artigo resolve-se (ou nasce) no catálogo pelo título do
                    // negócio, com o regime fiscal da empresa — ver
                    // ModuleInvoiceService::produtoDoCatalogo.
                    'name'       => $oportunidade->title,
                    'quantity'   => 1,
                    'unit_price' => $valor,
                    'is_service' => true,
                    'unit'       => 'UN',
                ]],
                'notes'          => 'Negócio ganho no CRM: '.$oportunidade->title.'.',
                'origem_modulo'  => 'crm',
                'origem'         => 'OPO-'.$oportunidade->id,
            ]);

            $oportunidade->forceFill([
                'sales_invoice_id' => $factura->id,
                'facturada_em'     => now(),
            ])->save();

            return $factura;
        });
    }
}
