<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pacotes promocionais
        Schema::create('hotel_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->enum('type', ['romantic', 'family', 'business', 'wellness', 'adventure', 'other'])->default('other');
            $table->decimal('price', 12, 2)->nullable(); // Preço fixo do pacote
            $table->decimal('discount_percentage', 5, 2)->nullable(); // Ou desconto %
            $table->decimal('discount_amount', 12, 2)->nullable(); // Ou desconto fixo
            $table->integer('min_nights')->default(1);
            $table->integer('max_nights')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('included_services')->nullable(); // Serviços incluídos
            $table->json('room_type_ids')->nullable(); // Tipos de quarto aplicáveis
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_online')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Códigos promocionais
        Schema::create('hotel_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 12, 2);
            $table->decimal('min_amount', 12, 2)->nullable(); // Valor mínimo para aplicar
            $table->decimal('max_discount', 12, 2)->nullable(); // Desconto máximo
            $table->integer('usage_limit')->nullable(); // Limite de usos total
            $table->integer('usage_per_customer')->default(1); // Limite por cliente
            $table->integer('times_used')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('room_type_ids')->nullable(); // Tipos de quarto aplicáveis
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Histórico de uso de códigos
        Schema::create('hotel_promo_code_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('promo_code_id')->constrained('hotel_promo_codes')->onDelete('cascade');
            $table->foreignId('reservation_id')->nullable()->constrained('hotel_reservations')->onDelete('set null');
            $table->foreignId('guest_id')->nullable()->constrained('hotel_guests')->onDelete('set null');
            $table->decimal('discount_applied', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_promo_code_usage');
        Schema::dropIfExists('hotel_promo_codes');
        Schema::dropIfExists('hotel_packages');
    }
};
