<?php

use App\Models\Invoicing\InvoicingSeries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')->orderBy('id')->pluck('id')->each(function ($tenantId): void {
            $exists = InvoicingSeries::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query): void {
                    $query->where('document_type', 'pos')
                        ->orWhere('prefix', 'FR');
                })
                ->exists();

            if (!$exists) {
                InvoicingSeries::createDefaultSeries((int) $tenantId, 'pos');
            }
        });
    }

    public function down(): void
    {
        // Não apagar séries fiscais depois de poderem ter emitido documentos.
    }
};
