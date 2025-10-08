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
        Schema::table('events_events', function (Blueprint $table) {
            // Fase do evento (workflow)
            $table->enum('phase', [
                'planejamento',      // Planejamento inicial
                'pre_producao',      // Pré-produção
                'montagem',          // Montagem/Setup
                'operacao',          // Evento em operação
                'desmontagem',       // Desmontagem/Teardown
                'concluido'          // Finalizado
            ])->default('planejamento')->after('status');
            
            // Data de confirmação do evento
            $table->dateTime('confirmed_at')->nullable()->after('phase');
            
            // Datas de início/fim de cada fase
            $table->dateTime('pre_production_started_at')->nullable();
            $table->dateTime('setup_started_at')->nullable();
            $table->dateTime('operation_started_at')->nullable();
            $table->dateTime('teardown_started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            
            // Progresso do checklist (%)
            $table->integer('checklist_progress')->default(0)->comment('Progresso do checklist em %');
            
            // Cor personalizada para o calendário
            $table->string('calendar_color', 7)->nullable()->comment('Cor hex para exibição no calendário');
        });

        // Atualizar tabela de checklist para incluir fase
        Schema::table('events_checklists', function (Blueprint $table) {
            $table->enum('phase', [
                'planejamento',
                'pre_producao',
                'montagem',
                'operacao',
                'desmontagem'
            ])->nullable()->after('event_id')->comment('Fase do evento a que pertence esta tarefa');
            
            $table->boolean('is_required')->default(false)->after('status')->comment('Se é obrigatória para avançar de fase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->dropColumn([
                'phase',
                'confirmed_at',
                'pre_production_started_at',
                'setup_started_at',
                'operation_started_at',
                'teardown_started_at',
                'completed_at',
                'checklist_progress',
                'calendar_color'
            ]);
        });

        Schema::table('events_checklists', function (Blueprint $table) {
            $table->dropColumn(['phase', 'is_required']);
        });
    }
};
