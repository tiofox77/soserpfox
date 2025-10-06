<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de Locais de Eventos
        try {
            if (!Schema::hasTable('events_venues')) {
                Schema::create('events_venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_person')->nullable();
            $table->integer('capacity')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Migration events_venues: ' . $e->getMessage());
        }

        // Tabela de Equipamentos
        try {
            if (!Schema::hasTable('events_equipment')) {
                Schema::create('events_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('code')->nullable();
            $table->enum('category', ['audio', 'video', 'iluminacao', 'streaming', 'led', 'estrutura', 'outros'])->default('outros');
            $table->text('specifications')->nullable();
            $table->decimal('daily_price', 15, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->integer('quantity_available')->default(1);
            $table->enum('status', ['disponivel', 'em_uso', 'manutencao', 'danificado'])->default('disponivel');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'category', 'status']);
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Migration events_equipment: ' . $e->getMessage());
        }

        // Tabela de Eventos
        try {
            if (!Schema::hasTable('events_events')) {
                Schema::create('events_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('client_id')->nullable()->constrained('invoicing_clients')->onDelete('set null');
            $table->foreignId('venue_id')->nullable()->constrained('events_venues')->onDelete('set null');
            $table->string('event_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['corporativo', 'casamento', 'conferencia', 'show', 'streaming', 'outros'])->default('outros');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->dateTime('setup_start')->nullable();
            $table->dateTime('teardown_end')->nullable();
            $table->integer('expected_attendees')->nullable();
            $table->decimal('total_value', 15, 2)->default(0);
            $table->enum('status', ['orcamento', 'confirmado', 'em_montagem', 'em_andamento', 'concluido', 'cancelado'])->default('orcamento');
            $table->text('notes')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status', 'start_date']);
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Migration events_events: ' . $e->getMessage());
        }

        // Tabela de Equipamentos do Evento
        try {
            if (!Schema::hasTable('events_event_equipment')) {
                Schema::create('events_event_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events_events')->onDelete('cascade');
            $table->foreignId('equipment_id')->constrained('events_equipment')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->integer('days')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Migration events_event_equipment: ' . $e->getMessage());
        }

        // Tabela de Equipe do Evento
        try {
            if (!Schema::hasTable('events_event_staff')) {
                Schema::create('events_event_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events_events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', ['coordenador', 'tecnico_audio', 'tecnico_video', 'tecnico_luz', 'operador_streaming', 'assistente'])->default('assistente');
            $table->dateTime('assigned_start')->nullable();
            $table->dateTime('assigned_end')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->timestamps();
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Migration events_event_staff: ' . $e->getMessage());
        }

        // Tabela de Checklist do Evento
        try {
            if (!Schema::hasTable('events_checklists')) {
                Schema::create('events_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events_events')->onDelete('cascade');
            $table->string('task');
            $table->text('description')->nullable();
            $table->enum('status', ['pendente', 'em_progresso', 'concluido'])->default('pendente');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Migration events_checklists: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_checklists');
        Schema::dropIfExists('events_event_staff');
        Schema::dropIfExists('events_event_equipment');
        Schema::dropIfExists('events_events');
        Schema::dropIfExists('events_equipment');
        Schema::dropIfExists('events_venues');
    }
};
