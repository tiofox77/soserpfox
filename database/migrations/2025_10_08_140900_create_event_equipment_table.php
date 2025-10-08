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
        Schema::create('event_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events_events')->onDelete('cascade');
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->onDelete('cascade');
            $table->foreignId('equipment_set_id')->nullable()->constrained('equipment_sets')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            $table->integer('quantity')->default(1);
            $table->dateTime('start_datetime')->nullable();
            $table->dateTime('end_datetime')->nullable();
            $table->enum('status', ['reservado', 'em_uso', 'devolvido'])->default('reservado');
            $table->text('notes')->nullable();
            
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['event_id', 'status']);
            $table->index(['equipment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_equipment');
    }
};
