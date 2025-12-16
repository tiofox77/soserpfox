<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remover FK e atualizar salon_appointments.client_id para usar invoicing_clients
        Schema::table('salon_appointments', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('salon_appointments', function (Blueprint $table) {
            $table->foreign('client_id')
                  ->references('id')
                  ->on('invoicing_clients')
                  ->onDelete('cascade');
        });

        // 2. Remover FK e atualizar salon_professional_services.service_id para usar invoicing_products
        Schema::table('salon_professional_services', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::table('salon_professional_services', function (Blueprint $table) {
            $table->foreign('service_id')
                  ->references('id')
                  ->on('invoicing_products')
                  ->onDelete('cascade');
        });

        // 3. Remover FK e atualizar salon_appointment_services.service_id para usar invoicing_products
        Schema::table('salon_appointment_services', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::table('salon_appointment_services', function (Blueprint $table) {
            $table->foreign('service_id')
                  ->references('id')
                  ->on('invoicing_products')
                  ->onDelete('cascade');
        });

        // 4. Remover FK e atualizar salon_package_services.service_id para usar invoicing_products
        Schema::table('salon_package_services', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::table('salon_package_services', function (Blueprint $table) {
            $table->foreign('service_id')
                  ->references('id')
                  ->on('invoicing_products')
                  ->onDelete('cascade');
        });

        // 5. Remover tabelas não mais necessárias
        Schema::dropIfExists('salon_services');
        Schema::dropIfExists('salon_clients');
    }

    public function down(): void
    {
        // Recriar tabelas (se necessário reverter)
        Schema::create('salon_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('salon_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('salon_service_categories')->onDelete('cascade');
            $table->string('name');
            $table->integer('duration')->default(30);
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        // Reverter FKs
        Schema::table('salon_appointments', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreign('client_id')->references('id')->on('salon_clients')->onDelete('cascade');
        });

        Schema::table('salon_professional_services', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreign('service_id')->references('id')->on('salon_services')->onDelete('cascade');
        });

        Schema::table('salon_appointment_services', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreign('service_id')->references('id')->on('salon_services')->onDelete('cascade');
        });
    }
};
