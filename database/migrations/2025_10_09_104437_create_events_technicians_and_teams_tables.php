<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabela de Técnicos
        Schema::create('events_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Link opcional com usuário
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('document')->nullable(); // BI/NIF
            $table->text('address')->nullable();
            
            // Especialidades
            $table->json('specialties')->nullable(); // ['audio', 'video', 'iluminacao', 'streaming']
            $table->enum('level', ['junior', 'pleno', 'senior', 'master'])->default('pleno');
            
            // Financeiro
            $table->decimal('hourly_rate', 10, 2)->default(0); // Valor/hora
            $table->decimal('daily_rate', 10, 2)->default(0);  // Valor/dia
            
            // Status e disponibilidade
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->text('notes')->nullable();
            
            // Documentos
            $table->string('photo')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('hire_date')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'is_active']);
        });
        
        // Tabela de Equipes
        Schema::create('events_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique(); // Ex: EQ-001
            $table->text('description')->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('events_technicians')->onDelete('set null');
            $table->enum('type', ['audio', 'video', 'iluminacao', 'streaming', 'completa', 'mista'])->default('mista');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });
        
        // Tabela de Membros da Equipe
        Schema::create('events_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('events_teams')->onDelete('cascade');
            $table->foreignId('technician_id')->constrained('events_technicians')->onDelete('cascade');
            $table->enum('role', ['lider', 'tecnico', 'assistente', 'operador'])->default('tecnico');
            $table->timestamps();
            
            $table->unique(['team_id', 'technician_id']);
        });
        
        // Tabela de Movimentação de Equipamentos
        Schema::create('events_equipment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events_events')->onDelete('cascade');
            $table->foreignId('equipment_id')->nullable()->constrained('events_equipments_manager')->onDelete('set null');
            $table->enum('type', ['saida', 'retorno', 'transferencia'])->default('saida');
            $table->integer('quantity');
            
            // Quem movimentou
            $table->foreignId('technician_id')->nullable()->constrained('events_technicians')->onDelete('set null');
            $table->foreignId('team_id')->nullable()->constrained('events_teams')->onDelete('set null');
            
            // Quando
            $table->dateTime('movement_datetime');
            
            // Estado
            $table->enum('condition', ['perfeito', 'bom', 'regular', 'danificado', 'quebrado'])->default('perfeito');
            $table->text('observations')->nullable();
            
            // Localização
            $table->string('location_from')->nullable();
            $table->string('location_to')->nullable();
            
            // Responsável pelo registro
            $table->foreignId('registered_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            $table->index(['event_id', 'movement_datetime']);
        });
        
        // Tabela de Relatórios de Eventos
        Schema::create('events_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events_events')->onDelete('cascade');
            $table->string('report_number')->unique(); // REL-001
            $table->enum('type', ['saida_material', 'retorno_material', 'execucao', 'incidentes', 'geral'])->default('geral');
            
            // Datas
            $table->dateTime('report_date');
            $table->dateTime('event_start')->nullable();
            $table->dateTime('event_end')->nullable();
            
            // Equipe envolvida
            $table->foreignId('team_id')->nullable()->constrained('events_teams')->onDelete('set null');
            $table->json('technicians')->nullable(); // Array de IDs de técnicos
            
            // Conteúdo do relatório
            $table->text('summary')->nullable();
            $table->json('equipments_used')->nullable(); // Lista de equipamentos e quantidades
            $table->json('incidents')->nullable(); // Lista de incidentes
            $table->text('observations')->nullable();
            
            // Tempos
            $table->time('setup_duration')->nullable(); // Tempo de montagem
            $table->time('teardown_duration')->nullable(); // Tempo de desmontagem
            
            // Avaliação
            $table->enum('client_satisfaction', ['1', '2', '3', '4', '5'])->nullable();
            $table->text('client_feedback')->nullable();
            
            // Status
            $table->enum('status', ['rascunho', 'finalizado', 'aprovado'])->default('rascunho');
            
            // Quem criou
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['event_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events_reports');
        Schema::dropIfExists('events_equipment_movements');
        Schema::dropIfExists('events_team_members');
        Schema::dropIfExists('events_teams');
        Schema::dropIfExists('events_technicians');
    }
};
