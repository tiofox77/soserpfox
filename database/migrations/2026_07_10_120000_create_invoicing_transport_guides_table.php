<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoicing_transport_guides')) {
            Schema::create('invoicing_transport_guides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('guide_number', 40);
                $table->string('type', 4)->default('GT'); // GT = Guia de Transporte, GR = Guia de Remessa
                $table->unsignedBigInteger('invoice_id')->nullable();   // fatura de origem (opcional)
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->date('issue_date');
                $table->dateTime('loading_datetime')->nullable();
                $table->string('vehicle_plate', 30)->nullable();
                $table->string('driver_name', 120)->nullable();
                $table->string('driver_document', 60)->nullable();
                $table->string('load_address', 255)->nullable();
                $table->string('unload_address', 255)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('issued'); // issued | cancelled
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'guide_number']);
            });
        }

        if (!Schema::hasTable('invoicing_transport_guide_items')) {
            Schema::create('invoicing_transport_guide_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transport_guide_id')->index();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_name', 255)->nullable();
                $table->string('description', 500)->nullable();
                $table->decimal('quantity', 15, 3)->default(0);
                $table->string('unit', 20)->default('un');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_transport_guide_items');
        Schema::dropIfExists('invoicing_transport_guides');
    }
};
