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
        Schema::create('tenant_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            // Email Settings
            $table->boolean('email_enabled')->default(true);
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->nullable();
            $table->string('smtp_username')->nullable();
            $table->string('smtp_password')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            
            // SMS Settings
            $table->boolean('sms_enabled')->default(false);
            $table->string('sms_provider')->nullable(); // twilio, nexmo, etc
            $table->string('sms_account_sid')->nullable();
            $table->string('sms_auth_token')->nullable();
            $table->string('sms_from_number')->nullable();
            
            // WhatsApp Settings
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_provider')->default('twilio');
            $table->string('whatsapp_account_sid')->nullable();
            $table->string('whatsapp_auth_token')->nullable();
            $table->string('whatsapp_from_number')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->boolean('whatsapp_sandbox')->default(true);
            
            // Notification Preferences (JSON)
            $table->json('email_notifications')->nullable();
            $table->json('sms_notifications')->nullable();
            $table->json('whatsapp_notifications')->nullable();
            $table->json('whatsapp_templates')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_notification_settings');
    }
};
