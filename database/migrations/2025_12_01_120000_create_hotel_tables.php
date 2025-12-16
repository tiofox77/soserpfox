<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de Quarto
        Schema::create('hotel_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Suite, Standard, Deluxe, etc.
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2); // Preço base por noite
            $table->decimal('weekend_price', 12, 2)->nullable(); // Preço fim de semana
            $table->integer('capacity')->default(2); // Capacidade máxima
            $table->integer('extra_bed_capacity')->default(0);
            $table->decimal('extra_bed_price', 12, 2)->default(0);
            $table->json('amenities')->nullable(); // WiFi, AC, TV, Minibar, etc.
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['tenant_id', 'code']);
        });

        // Quartos
        Schema::create('hotel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_type_id')->constrained('hotel_room_types')->onDelete('cascade');
            $table->string('number', 20); // Número do quarto
            $table->string('floor', 10)->nullable(); // Andar
            $table->enum('status', ['available', 'occupied', 'maintenance', 'cleaning', 'reserved'])->default('available');
            $table->text('notes')->nullable();
            $table->json('features')->nullable(); // Características específicas do quarto
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['tenant_id', 'number']);
        });

        // Hóspedes
        Schema::create('hotel_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('document_type', 50)->nullable(); // BI, Passaporte, etc.
            $table->string('document_number', 50)->nullable();
            $table->string('nationality')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('company')->nullable();
            $table->string('nif')->nullable();
            $table->text('notes')->nullable();
            $table->json('preferences')->nullable(); // Preferências do hóspede
            $table->integer('total_stays')->default(0);
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'document_number']);
        });

        // Reservas
        Schema::create('hotel_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('reservation_number', 20)->unique();
            $table->foreignId('guest_id')->constrained('hotel_guests')->onDelete('cascade');
            $table->foreignId('room_id')->nullable()->constrained('hotel_rooms')->onDelete('set null');
            $table->foreignId('room_type_id')->constrained('hotel_room_types')->onDelete('cascade');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->timestamp('actual_check_in')->nullable();
            $table->timestamp('actual_check_out')->nullable();
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);
            $table->integer('extra_beds')->default(0);
            $table->enum('status', ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'])->default('pending');
            $table->enum('source', ['direct', 'website', 'booking', 'airbnb', 'phone', 'email', 'walk_in', 'other'])->default('direct');
            $table->decimal('room_rate', 12, 2); // Taxa do quarto por noite
            $table->integer('nights')->default(1);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('extras_total', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'refunded'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('confirmation_code', 10)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'check_in_date', 'check_out_date']);
        });

        // Itens/Serviços da Reserva
        Schema::create('hotel_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('hotel_reservations')->onDelete('cascade');
            $table->enum('type', ['room', 'extra_bed', 'service', 'minibar', 'restaurant', 'laundry', 'other']);
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->date('date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Configurações do Hotel
        Schema::create('hotel_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('hotel_name')->nullable();
            $table->text('hotel_description')->nullable();
            $table->string('hotel_address')->nullable();
            $table->string('hotel_phone')->nullable();
            $table->string('hotel_email')->nullable();
            $table->string('hotel_website')->nullable();
            $table->time('default_check_in_time')->default('14:00');
            $table->time('default_check_out_time')->default('12:00');
            $table->decimal('tax_rate', 5, 2)->default(14.00);
            $table->json('booking_policies')->nullable();
            $table->json('cancellation_policies')->nullable();
            $table->boolean('online_booking_enabled')->default(true);
            $table->integer('min_advance_booking_days')->default(0);
            $table->integer('max_advance_booking_days')->default(365);
            $table->json('payment_methods')->nullable();
            $table->string('currency', 3)->default('AOA');
            $table->json('amenities_list')->nullable();
            $table->timestamps();
            
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_settings');
        Schema::dropIfExists('hotel_reservation_items');
        Schema::dropIfExists('hotel_reservations');
        Schema::dropIfExists('hotel_guests');
        Schema::dropIfExists('hotel_rooms');
        Schema::dropIfExists('hotel_room_types');
    }
};
