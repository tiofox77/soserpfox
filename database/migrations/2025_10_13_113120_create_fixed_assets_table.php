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
        // Tabela de categorias PRIMEIRO
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('default_useful_life')->default(5);
            $table->enum('default_depreciation_method', ['linear', 'declining_balance'])->default('linear');
            $table->decimal('default_depreciation_rate', 5, 2)->nullable();
            $table->timestamps();
        });
        
        // Depois fixed_assets
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('fixed_asset_categories')->nullOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts');
            $table->foreignId('depreciation_account_id')->constrained('accounting_accounts');
            $table->foreignId('accumulated_depreciation_account_id')->constrained('accounting_accounts');
            $table->date('acquisition_date');
            $table->decimal('acquisition_value', 15, 2);
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->integer('useful_life_years');
            $table->enum('depreciation_method', ['linear', 'declining_balance', 'units_of_production'])->default('linear');
            $table->decimal('depreciation_rate', 5, 2)->nullable(); // Para declining balance
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->decimal('book_value', 15, 2);
            $table->enum('status', ['active', 'fully_depreciated', 'sold', 'scrapped'])->default('active');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_value', 15, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('serial_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'status']);
        });
        
        // Por último depreciações
        Schema::create('fixed_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('accounting_periods');
            $table->date('depreciation_date');
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('accumulated_depreciation', 15, 2);
            $table->decimal('book_value', 15, 2);
            $table->foreignId('move_id')->nullable()->constrained('accounting_moves')->nullOnDelete();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
    }
};
