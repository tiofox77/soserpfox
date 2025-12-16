<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categorias de Serviços
        Schema::create('salon_service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Cabelo, Unhas, Estética, Maquilhagem, etc.
            $table->string('slug');
            $table->string('icon')->nullable();
            $table->string('color', 7)->default('#6366f1');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['tenant_id', 'slug']);
        });

        // Serviços do Salão
        Schema::create('salon_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('salon_service_categories')->onDelete('cascade');
            $table->string('name'); // Corte Masculino, Manicure, Limpeza de Pele, etc.
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->integer('duration'); // Duração em minutos
            $table->decimal('price', 12, 2);
            $table->decimal('cost', 12, 2)->default(0); // Custo (produtos usados)
            $table->decimal('commission_percent', 5, 2)->default(0); // Comissão do profissional
            $table->decimal('commission_fixed', 12, 2)->default(0);
            $table->json('required_products')->nullable(); // Produtos consumidos
            $table->boolean('is_active')->default(true);
            $table->boolean('online_booking')->default(true); // Disponível para agendamento online
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['tenant_id', 'code']);
        });

        // Profissionais (Cabeleireiros, Manicures, etc.)
        Schema::create('salon_professionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo')->nullable();
            $table->string('specialization')->nullable(); // Colorista, Barbeiro, etc.
            $table->text('bio')->nullable();
            $table->json('working_days')->nullable(); // [1,2,3,4,5] = Seg a Sex
            $table->time('work_start')->default('09:00');
            $table->time('work_end')->default('18:00');
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->boolean('accepts_online_booking')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Serviços que cada profissional pode realizar
        Schema::create('salon_professional_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained('salon_professionals')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('salon_services')->onDelete('cascade');
            $table->decimal('custom_price', 12, 2)->nullable(); // Preço diferenciado
            $table->integer('custom_duration')->nullable(); // Duração diferenciada
            $table->timestamps();
            
            $table->unique(['professional_id', 'service_id']);
        });

        // Clientes do Salão
        Schema::create('salon_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['female', 'male', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->json('preferences')->nullable(); // Preferências (produtos, profissionais)
            $table->json('allergies')->nullable(); // Alergias a produtos
            $table->string('referred_by')->nullable(); // Indicado por
            $table->integer('total_visits')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->boolean('receives_sms')->default(true);
            $table->boolean('receives_email')->default(true);
            $table->boolean('is_vip')->default(false);
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
        });

        // Agendamentos
        Schema::create('salon_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('appointment_number', 20)->unique();
            $table->foreignId('client_id')->constrained('salon_clients')->onDelete('cascade');
            $table->foreignId('professional_id')->constrained('salon_professionals')->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('total_duration')->default(0); // Duração total em minutos
            $table->enum('status', [
                'scheduled',    // Agendado
                'confirmed',    // Confirmado
                'arrived',      // Cliente chegou
                'in_progress',  // Em atendimento
                'completed',    // Concluído
                'cancelled',    // Cancelado
                'no_show'       // Não compareceu
            ])->default('scheduled');
            $table->enum('source', ['walk_in', 'phone', 'whatsapp', 'website', 'app', 'instagram', 'other'])->default('phone');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['professional_id', 'date']);
        });

        // Serviços do Agendamento
        Schema::create('salon_appointment_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('salon_appointments')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('salon_services')->onDelete('cascade');
            $table->foreignId('professional_id')->nullable()->constrained('salon_professionals')->onDelete('set null');
            $table->integer('duration');
            $table->decimal('price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('commission', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Produtos Usados no Atendimento
        Schema::create('salon_appointment_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('salon_appointments')->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->decimal('quantity', 10, 3);
            $table->string('unit')->default('un');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->timestamps();
        });

        // Pacotes/Combos de Serviços
        Schema::create('salon_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Dia da Noiva, Pacote Mensal, etc.
            $table->text('description')->nullable();
            $table->decimal('regular_price', 12, 2); // Preço se comprar separado
            $table->decimal('package_price', 12, 2); // Preço do pacote
            $table->integer('validity_days')->nullable(); // Validade em dias
            $table->integer('max_uses')->nullable(); // Máximo de utilizações
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Serviços incluídos no Pacote
        Schema::create('salon_package_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('salon_packages')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('salon_services')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        // Configurações do Salão
        Schema::create('salon_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('salon_name')->nullable();
            $table->text('salon_description')->nullable();
            $table->string('salon_address')->nullable();
            $table->string('salon_phone')->nullable();
            $table->string('salon_whatsapp')->nullable();
            $table->string('salon_email')->nullable();
            $table->string('salon_instagram')->nullable();
            $table->time('opening_time')->default('09:00');
            $table->time('closing_time')->default('19:00');
            $table->json('working_days')->nullable(); // [1,2,3,4,5,6] = Seg a Sab
            $table->integer('slot_interval')->default(30); // Intervalo de slots em minutos
            $table->integer('min_advance_booking_hours')->default(2);
            $table->integer('max_advance_booking_days')->default(30);
            $table->integer('cancellation_hours')->default(24); // Horas de antecedência para cancelar
            $table->integer('reminder_hours')->default(24); // Horas antes para lembrete
            $table->boolean('online_booking_enabled')->default(true);
            $table->boolean('require_confirmation')->default(true);
            $table->decimal('no_show_fee_percent', 5, 2)->default(0);
            $table->text('booking_terms')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->timestamps();
            
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_settings');
        Schema::dropIfExists('salon_package_services');
        Schema::dropIfExists('salon_packages');
        Schema::dropIfExists('salon_appointment_products');
        Schema::dropIfExists('salon_appointment_services');
        Schema::dropIfExists('salon_appointments');
        Schema::dropIfExists('salon_clients');
        Schema::dropIfExists('salon_professional_services');
        Schema::dropIfExists('salon_professionals');
        Schema::dropIfExists('salon_services');
        Schema::dropIfExists('salon_service_categories');
    }
};
