<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->foreignId('room_id')->nullable()->constrained('hotel_rooms')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('hotel_staff')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('hotel_staff')->nullOnDelete();
            
            $table->enum('type', ['preventive', 'corrective', 'emergency'])->default('corrective');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('category', ['electrical', 'plumbing', 'hvac', 'furniture', 'appliance', 'structural', 'other'])->default('other');
            
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('location')->nullable(); // Ex: Banheiro, Varanda, etc
            
            $table->enum('status', ['pending', 'in_progress', 'waiting_parts', 'completed', 'cancelled'])->default('pending');
            
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->integer('estimated_time')->nullable(); // Em minutos
            $table->integer('actual_time')->nullable(); // Em minutos
            
            $table->timestamp('scheduled_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->text('resolution_notes')->nullable();
            $table->json('images')->nullable(); // Fotos do problema
            $table->json('parts_used')->nullable(); // Peças utilizadas
            
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'room_id']);
            $table->index(['tenant_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_maintenance_orders');
    }
};
