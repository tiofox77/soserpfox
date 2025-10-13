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
        Schema::create('accounting_withholdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->index();
            $table->string('name');
            $table->enum('type', ['service', 'rent', 'professional', 'other']);
            $table->decimal('rate', 5, 2); // 6.50 = 6.5%
            $table->foreignId('account_id')->constrained('accounting_accounts');
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_withholdings');
    }
};
