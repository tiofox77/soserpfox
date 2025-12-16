<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tarefas de Housekeeping
        Schema::create('hotel_housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained('hotel_rooms')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('hotel_reservations')->nullOnDelete();
            
            $table->string('task_type'); // checkout_clean, stay_clean, deep_clean, turndown, inspection
            $table->string('priority')->default('normal'); // urgent, high, normal, low
            $table->string('status')->default('pending'); // pending, in_progress, completed, verified, issue
            
            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();
            $table->integer('estimated_duration')->default(30); // minutos
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->integer('actual_duration')->nullable(); // minutos
            $table->json('checklist')->nullable(); // itens do checklist com status
            $table->text('notes')->nullable();
            $table->text('issues')->nullable(); // problemas encontrados
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'scheduled_date', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        // Inspeções de Quartos
        Schema::create('hotel_room_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained('hotel_rooms')->onDelete('cascade');
            $table->foreignId('housekeeping_task_id')->nullable()->constrained('hotel_housekeeping_tasks')->nullOnDelete();
            $table->foreignId('inspector_id')->constrained('users')->onDelete('cascade');
            
            $table->string('inspection_type'); // routine, post_checkout, pre_checkin, maintenance, complaint
            $table->string('status')->default('pending'); // pending, passed, failed, needs_attention
            $table->integer('score')->nullable(); // 0-100
            
            $table->json('checklist_results')->nullable(); // resultados detalhados
            $table->json('photos')->nullable(); // fotos tiradas
            
            $table->text('notes')->nullable();
            $table->text('issues_found')->nullable();
            $table->boolean('maintenance_required')->default(false);
            
            $table->timestamp('inspected_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'room_id']);
            $table->index(['inspected_at']);
        });

        // Ordens de Manutenção
        Schema::create('hotel_maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->nullable()->constrained('hotel_rooms')->nullOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained('hotel_room_inspections')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('order_number')->unique();
            $table->string('category'); // electrical, plumbing, hvac, furniture, appliance, structural, other
            $table->string('priority')->default('normal'); // urgent, high, normal, low
            $table->string('status')->default('pending'); // pending, scheduled, in_progress, completed, cancelled
            
            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable(); // área específica
            
            $table->date('scheduled_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->text('resolution')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->json('parts_used')->nullable();
            $table->json('photos_before')->nullable();
            $table->json('photos_after')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['room_id', 'status']);
        });

        // Adicionar campo de status de limpeza ao quarto
        Schema::table('hotel_rooms', function (Blueprint $table) {
            $table->string('housekeeping_status')->default('clean')->after('status');
            // clean, dirty, in_progress, inspecting, out_of_order
        });
    }

    public function down(): void
    {
        Schema::table('hotel_rooms', function (Blueprint $table) {
            $table->dropColumn('housekeeping_status');
        });
        
        Schema::dropIfExists('hotel_maintenance_orders');
        Schema::dropIfExists('hotel_room_inspections');
        Schema::dropIfExists('hotel_housekeeping_tasks');
    }
};
