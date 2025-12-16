<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adicionar campos para alinhar com técnicos de eventos e mecânicos de oficina
     */
    public function up(): void
    {
        Schema::table('salon_professionals', function (Blueprint $table) {
            $table->string('document')->nullable()->after('phone');
            $table->text('address')->nullable()->after('document');
            $table->string('level')->nullable()->after('specialization')->comment('junior, pleno, senior, master');
            $table->decimal('hourly_rate', 10, 2)->default(0)->after('commission_percent');
            $table->decimal('daily_rate', 10, 2)->default(0)->after('hourly_rate');
            $table->boolean('is_available')->default(true)->after('is_active');
            $table->date('birth_date')->nullable()->after('bio');
            $table->date('hire_date')->nullable()->after('birth_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salon_professionals', function (Blueprint $table) {
            $table->dropColumn([
                'document',
                'address',
                'level',
                'hourly_rate',
                'daily_rate',
                'is_available',
                'birth_date',
                'hire_date',
            ]);
        });
    }
};
