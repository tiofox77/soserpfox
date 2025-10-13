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
        Schema::table('hr_employees', function (Blueprint $table) {
            // Adicionar colunas para paths dos documentos
            $table->string('bi_document_path')->nullable()->after('bi_expiry_date');
            $table->string('passport_document_path')->nullable()->after('passport_expiry_date');
            $table->string('work_permit_document_path')->nullable()->after('work_permit_expiry_date');
            $table->string('residence_permit_document_path')->nullable()->after('residence_permit_expiry_date');
            $table->string('driver_license_document_path')->nullable()->after('driver_license_expiry_date');
            $table->string('health_insurance_document_path')->nullable()->after('health_insurance_expiry_date');
            $table->string('contract_document_path')->nullable()->after('contract_expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn([
                'bi_document_path',
                'passport_document_path',
                'work_permit_document_path',
                'residence_permit_document_path',
                'driver_license_document_path',
                'health_insurance_document_path',
                'contract_document_path',
            ]);
        });
    }
};
