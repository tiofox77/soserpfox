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
        Schema::create('accounting_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained('accounting_journals');
            $table->foreignId('period_id')->constrained('accounting_periods');
            $table->date('date');
            $table->string('ref', 50)->index();
            $table->text('narration')->nullable();
            $table->enum('state', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'ref']);
            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_moves');
    }
};
