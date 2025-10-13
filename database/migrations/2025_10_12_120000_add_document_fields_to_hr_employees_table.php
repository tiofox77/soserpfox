<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            // Documentos e Validades
            $table->string('passport_number')->nullable()->after('bi_expiry_date');
            $table->date('passport_expiry_date')->nullable()->after('passport_number');
            
            $table->string('work_permit_number')->nullable()->after('passport_expiry_date');
            $table->date('work_permit_expiry_date')->nullable()->after('work_permit_number');
            
            $table->string('residence_permit_number')->nullable()->after('work_permit_expiry_date');
            $table->date('residence_permit_expiry_date')->nullable()->after('residence_permit_number');
            
            $table->string('driver_license_number')->nullable()->after('residence_permit_expiry_date');
            $table->date('driver_license_expiry_date')->nullable()->after('driver_license_number');
            $table->string('driver_license_category')->nullable()->after('driver_license_expiry_date');
            
            $table->string('health_insurance_number')->nullable()->after('driver_license_category');
            $table->date('health_insurance_expiry_date')->nullable()->after('health_insurance_number');
            $table->string('health_insurance_provider')->nullable()->after('health_insurance_expiry_date');
            
            $table->date('contract_expiry_date')->nullable()->after('health_insurance_provider');
            $table->date('probation_end_date')->nullable()->after('contract_expiry_date');
        });
    }

    public function down()
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn([
                'passport_number', 'passport_expiry_date',
                'work_permit_number', 'work_permit_expiry_date',
                'residence_permit_number', 'residence_permit_expiry_date',
                'driver_license_number', 'driver_license_expiry_date', 'driver_license_category',
                'health_insurance_number', 'health_insurance_expiry_date', 'health_insurance_provider',
                'contract_expiry_date', 'probation_end_date'
            ]);
        });
    }
};
