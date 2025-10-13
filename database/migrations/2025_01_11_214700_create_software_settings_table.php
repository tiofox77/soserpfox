<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('software_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module')->index(); // invoicing, events, inventory, etc
            $table->string('setting_key')->index(); // block_delete_invoice, block_delete_proforma, etc
            $table->string('setting_value')->nullable(); // true/false, on/off, json values
            $table->string('setting_type')->default('boolean'); // boolean, string, json, integer
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Índice único para evitar duplicatas
            $table->unique(['module', 'setting_key']);
        });
        
        // Inserir configurações padrão do módulo de faturação
        DB::table('software_settings')->insert([
            [
                'module' => 'invoicing',
                'setting_key' => 'block_delete_sales_invoice',
                'setting_value' => 'false',
                'setting_type' => 'boolean',
                'description' => 'Bloquear eliminação de faturas de venda',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'module' => 'invoicing',
                'setting_key' => 'block_delete_proforma',
                'setting_value' => 'false',
                'setting_type' => 'boolean',
                'description' => 'Bloquear eliminação de proformas',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'module' => 'invoicing',
                'setting_key' => 'block_delete_receipt',
                'setting_value' => 'false',
                'setting_type' => 'boolean',
                'description' => 'Bloquear eliminação de recibos',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'module' => 'invoicing',
                'setting_key' => 'block_delete_credit_note',
                'setting_value' => 'false',
                'setting_type' => 'boolean',
                'description' => 'Bloquear eliminação de notas de crédito',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'module' => 'invoicing',
                'setting_key' => 'block_delete_invoice_receipt',
                'setting_value' => 'false',
                'setting_type' => 'boolean',
                'description' => 'Bloquear eliminação de faturas recibo',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'module' => 'invoicing',
                'setting_key' => 'block_delete_pos_invoice',
                'setting_value' => 'false',
                'setting_type' => 'boolean',
                'description' => 'Bloquear eliminação de faturas POS',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('software_settings');
    }
};
