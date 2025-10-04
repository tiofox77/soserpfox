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
        Schema::create('treasury_banks', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // BFA, BAI, BIC, etc
            $table->string('code')->unique(); // BFA, BAI, BIC
            $table->string('swift_code')->nullable();
            $table->string('country')->default('AO'); // Angola
            $table->string('logo_url')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('code');
            $table->index(['country', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_banks');
    }
};
