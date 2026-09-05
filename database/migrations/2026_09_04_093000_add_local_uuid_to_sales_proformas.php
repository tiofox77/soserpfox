<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('invoicing_sales_proformas', 'local_uuid')) {
            Schema::table('invoicing_sales_proformas', function (Blueprint $table) {
                $table->string('local_uuid', 80)->nullable();
                $table->unique(['tenant_id', 'local_uuid'], 'sales_proformas_tenant_local_uuid_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoicing_sales_proformas', function (Blueprint $table) {
            $table->dropUnique('sales_proformas_tenant_local_uuid_unique');
            $table->dropColumn('local_uuid');
        });
    }
};
