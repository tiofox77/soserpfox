<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\WorkOrder;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    /**
     * Página de Preview para Impressão
     */
    public function printPreview($id)
    {
        $workOrder = WorkOrder::with([
            'vehicle',
            'mechanic',
            'items.service',
            'items.product',
            'items.mechanic'
        ])
        ->where('tenant_id', activeTenantId())
        ->findOrFail($id);

        return view('workshop.work-orders.print-preview', compact('workOrder'));
    }
}
