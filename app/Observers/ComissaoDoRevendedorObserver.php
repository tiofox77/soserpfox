<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\Revenda\ComissoesDoRevendedor;

/**
 * O PAGAMENTO CONFIRMADO DÁ A COMISSÃO AO REVENDEDOR (RV-11).
 *
 * À parte do OrderObserver e do FacturaDeSubscricaoObserver de propósito: a
 * comissão é outra conta, e falhar aqui nunca pode desfazer a aprovação nem o
 * pagamento — o serviço regista o erro e segue.
 */
class ComissaoDoRevendedorObserver
{
    public function created(Order|Invoice $modelo): void
    {
        // Uma factura pode nascer já paga (a subscrição gravada à mão pelo super admin).
        if ($modelo instanceof Invoice && $modelo->status === 'paid') {
            app(ComissoesDoRevendedor::class)->daFactura($modelo);
        }
    }

    public function updated(Order|Invoice $modelo): void
    {
        if (! $modelo->wasChanged('status')) {
            return;
        }

        if ($modelo instanceof Order && $modelo->status === 'approved') {
            // Só a aprovação de quem gere a plataforma é um pagamento confirmado;
            // a que a empresa faz sozinha é o período de teste.
            $quem = auth()->user();
            app(ComissoesDoRevendedor::class)->doPedido($modelo, $quem instanceof \App\Models\User && $quem->isPlatformSuperAdmin());
        }

        if ($modelo instanceof Invoice && $modelo->status === 'paid') {
            app(ComissoesDoRevendedor::class)->daFactura($modelo);
        }
    }
}
