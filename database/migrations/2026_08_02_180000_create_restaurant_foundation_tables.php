<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('default_warehouse_id')->nullable()->constrained('invoicing_warehouses')->nullOnDelete();
            $table->foreignId('default_client_id')->nullable()->constrained('invoicing_clients')->nullOnDelete();
            $table->boolean('require_open_shift')->default(true);
            $table->boolean('reserve_stock_on_confirm')->default(true);
            $table->boolean('consume_stock_on_kitchen')->default(true);
            $table->boolean('allow_negative_stock')->default(false);
            $table->unsignedBigInteger('next_order_number')->default(1);
            $table->timestamps();
        });

        Schema::create('restaurant_venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->foreignId('warehouse_id')->nullable()->constrained('invoicing_warehouses')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code'], 'restaurant_venues_tenant_code_unique');
        });

        Schema::create('restaurant_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('restaurant_venues')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'venue_id', 'name'], 'restaurant_areas_tenant_venue_name_unique');
        });

        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('restaurant_venues')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('restaurant_areas')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->enum('status', ['available', 'reserved', 'occupied', 'waiting_kitchen', 'served', 'billing', 'cleaning', 'blocked'])->default('available');
            $table->unsignedSmallInteger('position_x')->default(0);
            $table->unsignedSmallInteger('position_y')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'venue_id', 'code'], 'restaurant_tables_tenant_venue_code_unique');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('restaurant_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('restaurant_venues')->restrictOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('invoicing_clients')->nullOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number', 40);
            $table->uuid('local_uuid')->nullable();
            $table->enum('channel', ['table', 'counter', 'takeaway', 'delivery'])->default('table');
            $table->enum('status', ['draft', 'confirmed', 'in_preparation', 'ready', 'served', 'partially_billed', 'billed', 'cancelled'])->default('draft');
            $table->unsignedSmallInteger('guest_count')->default(1);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'order_number'], 'restaurant_orders_tenant_number_unique');
            $table->unique(['tenant_id', 'local_uuid'], 'restaurant_orders_tenant_uuid_unique');
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('restaurant_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('invoicing_products')->restrictOnDelete();
            $table->string('product_name', 255);
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 10)->default('UN');
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_percent', 8, 4)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->decimal('billed_quantity', 18, 4)->default(0);
            $table->enum('kitchen_status', ['draft', 'queued', 'accepted', 'preparing', 'ready', 'served', 'voided'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'order_id']);
            $table->index(['tenant_id', 'kitchen_status']);
        });

        Schema::create('restaurant_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('restaurant_order_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 50);
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'order_id', 'created_at'], 'restaurant_events_order_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_events');
        Schema::dropIfExists('restaurant_order_items');
        Schema::dropIfExists('restaurant_orders');
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('restaurant_areas');
        Schema::dropIfExists('restaurant_venues');
        Schema::dropIfExists('restaurant_settings');
    }
};
