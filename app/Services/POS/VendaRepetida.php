<?php

namespace App\Services\POS;

use App\Models\Invoicing\SalesInvoice;

/**
 * O MESMO GESTO, DUAS VEZES: a venda já está gravada.
 *
 * Sai de dentro da transacção da venda para a desfazer (o número volta à
 * série) e leva a factura que já existe, que é a que o balcão recebe.
 * Ver PosSaleService::vendaRepetida().
 */
final class VendaRepetida extends \RuntimeException
{
    public function __construct(public readonly SalesInvoice $factura)
    {
        parent::__construct('Venda repetida: ' . $factura->invoice_number);
    }
}
