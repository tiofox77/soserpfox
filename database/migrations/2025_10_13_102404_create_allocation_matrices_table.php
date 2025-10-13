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
        Schema::create('allocation_matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('account_code', 20);
            $table->enum('function_type', ['sales_cost', 'distribution', 'administrative', 'rd']);
            $table->decimal('allocation_percent', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'account_code']);
            $table->unique(['tenant_id', 'account_code', 'function_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_matrices');
    }
};
