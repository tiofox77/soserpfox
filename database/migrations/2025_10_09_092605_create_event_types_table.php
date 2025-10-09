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
        Schema::create('events_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('icon')->default('📅'); // Emoji ou classe de ícone
            $table->string('color')->default('#8b5cf6'); // Cor hex
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });
        
        // Inserir tipos padrão para cada tenant existente
        DB::table('tenants')->get()->each(function ($tenant) {
            $defaultTypes = [
                ['name' => 'Corporativo', 'icon' => '🏢', 'color' => '#3b82f6', 'order' => 1],
                ['name' => 'Casamento', 'icon' => '💍', 'color' => '#ec4899', 'order' => 2],
                ['name' => 'Conferência', 'icon' => '🎤', 'color' => '#8b5cf6', 'order' => 3],
                ['name' => 'Show', 'icon' => '🎸', 'color' => '#ef4444', 'order' => 4],
                ['name' => 'Streaming', 'icon' => '📹', 'color' => '#06b6d4', 'order' => 5],
                ['name' => 'Outros', 'icon' => '📌', 'color' => '#6b7280', 'order' => 99],
            ];
            
            foreach ($defaultTypes as $type) {
                DB::table('events_types')->insert([
                    'tenant_id' => $tenant->id,
                    'name' => $type['name'],
                    'icon' => $type['icon'],
                    'color' => $type['color'],
                    'order' => $type['order'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events_types');
    }
};
