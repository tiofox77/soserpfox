<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenantId = (int) ($argv[1] ?? 59);

$s = App\Models\Invoicing\InvoicingSettings::forTenant($tenantId);
$s->agt_software_validation_number = 'FE/351/AGT/2026';
$s->agt_product_id      = 'SOS ERP - SOLUÇÕES EMPRESARIAIS';
$s->agt_product_version = '1.0';
$s->save();

echo "✅ Tenant {$tenantId} actualizado:\n";
echo "  productId:                {$s->agt_product_id}\n";
echo "  productVersion:           {$s->agt_product_version}\n";
echo "  softwareValidationNumber: {$s->agt_software_validation_number}\n";
