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
        // Centros de Custo
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->enum('type', ['revenue', 'cost', 'support'])->default('cost');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });
        
        // Dimensões Analíticas (Projetos, Segmentos, etc)
        Schema::create('analytic_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Projeto, Departamento, Segmento
            $table->string('code', 50);
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();
        });
        
        // Tags Analíticas
        Schema::create('analytic_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_id')->constrained('analytic_dimensions')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['dimension_id', 'code']);
        });
        
        // Distribuição Analítica em Lançamentos
        Schema::create('move_line_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('move_line_id')->constrained('accounting_move_lines')->cascadeOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('analytic_tag_id')->nullable()->constrained('analytic_tags')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('percentage', 5, 2)->default(100);
            $table->timestamps();
        });
        
        // Orçamentos
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('year');
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts');
            $table->decimal('january', 15, 2)->default(0);
            $table->decimal('february', 15, 2)->default(0);
            $table->decimal('march', 15, 2)->default(0);
            $table->decimal('april', 15, 2)->default(0);
            $table->decimal('may', 15, 2)->default(0);
            $table->decimal('june', 15, 2)->default(0);
            $table->decimal('july', 15, 2)->default(0);
            $table->decimal('august', 15, 2)->default(0);
            $table->decimal('september', 15, 2)->default(0);
            $table->decimal('october', 15, 2)->default(0);
            $table->decimal('november', 15, 2)->default(0);
            $table->decimal('december', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'closed'])->default('draft');
            $table->timestamps();
            
            $table->index(['tenant_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('move_line_analytics');
        Schema::dropIfExists('analytic_tags');
        Schema::dropIfExists('analytic_dimensions');
        Schema::dropIfExists('cost_centers');
    }
};
