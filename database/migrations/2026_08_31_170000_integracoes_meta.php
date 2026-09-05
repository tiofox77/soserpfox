<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integração Meta POR EMPRESA — a ligação a Facebook, Instagram e WhatsApp
 * (Graph / WhatsApp Cloud API).
 *
 * Por empresa, e não global: cada cliente liga os SEUS activos do Meta (a sua
 * Página, a sua conta, o seu número). Os segredos (tokens) guardam-se cifrados
 * pelo cast 'encrypted' do modelo — nunca em claro na base.
 *
 * NÃO reaproveita o whatsapp_settings existente: esse é do Twilio e é global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();

            // A App do Meta (a mesma para os 3 canais desta empresa).
            $table->string('app_id')->nullable();
            $table->text('app_secret')->nullable();          // cifrado
            // O token que a empresa escreve na configuração do webhook no Meta;
            // é o que confirmamos no GET de verificação.
            $table->string('webhook_verify_token')->nullable();

            // Facebook (Página + Messenger).
            $table->boolean('facebook_enabled')->default(false);
            $table->string('facebook_page_id')->nullable();
            $table->string('facebook_page_name')->nullable();
            $table->text('facebook_page_token')->nullable();  // cifrado

            // Instagram (mensagens directas).
            $table->boolean('instagram_enabled')->default(false);
            $table->string('instagram_account_id')->nullable();
            $table->string('instagram_username')->nullable();

            // WhatsApp Cloud API.
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->string('whatsapp_display_number')->nullable();
            $table->text('whatsapp_token')->nullable();       // cifrado

            // Comportamento.
            $table->boolean('lead_ads_enabled')->default(false); // anúncios → leads
            $table->boolean('criar_leads')->default(true);       // mensagem nova → lead no CRM

            $table->timestamp('ultimo_evento_em')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_integrations');
    }
};
