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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // welcome, plan_approved, plan_rejected, plan_updated, account_suspended
            $table->string('name');
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->json('variables')->nullable(); // {user_name}, {tenant_name}, {plan_name}, etc.
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
