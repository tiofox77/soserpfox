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
        if (!Schema::hasTable('tenant_module')) {
            Schema::create('tenant_module', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('module_id')->constrained()->onDelete('cascade');
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable(); // Configurações específicas do módulo para este tenant
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();
                
                $table->unique(['tenant_id', 'module_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_module');
    }
};
