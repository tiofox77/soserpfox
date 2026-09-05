<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O contacto do Meta ↔ o lead do CRM.
 *
 * Guarda quem escreveu (o wa_id do WhatsApp, ou o PSID do Messenger/Instagram)
 * e a que lead corresponde. É isto que faz uma pessoa que manda cinco mensagens
 * ser UM lead com cinco actividades — e não cinco leads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('channel');        // whatsapp | facebook | instagram
            $table->string('external_id');     // wa_id ou PSID
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();

            // Um contacto por canal e por empresa.
            $table->unique(['tenant_id', 'channel', 'external_id']);
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_contacts');
    }
};
