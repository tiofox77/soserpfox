<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_kitchen_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('restaurant_venues')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'venue_id', 'code'], 'restaurant_kitchen_station_code_unique');
        });

        Schema::create('restaurant_kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('restaurant_venues')->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('restaurant_kitchen_stations')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->string('ticket_number', 50);
            $table->enum('status', ['queued', 'accepted', 'preparing', 'ready', 'served', 'voided'])->default('queued');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamp('queued_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'ticket_number'], 'restaurant_kitchen_ticket_number_unique');
            $table->index(['tenant_id', 'station_id', 'status']);
        });

        Schema::create('restaurant_kitchen_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('restaurant_kitchen_tickets')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('restaurant_order_items')->cascadeOnDelete();
            $table->enum('status', ['queued', 'accepted', 'preparing', 'ready', 'served', 'voided'])->default('queued');
            $table->timestamps();
            $table->unique(['tenant_id', 'order_item_id'], 'restaurant_kitchen_order_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_kitchen_ticket_items');
        Schema::dropIfExists('restaurant_kitchen_tickets');
        Schema::dropIfExists('restaurant_kitchen_stations');
    }
};
