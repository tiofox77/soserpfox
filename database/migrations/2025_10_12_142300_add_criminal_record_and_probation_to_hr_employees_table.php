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
            // Registro Criminal
            $table->string('criminal_record_number')->nullable()->after('probation_end_date');
            $table->date('criminal_record_issue_date')->nullable()->after('criminal_record_number');
            $table->string('criminal_record_document_path')->nullable()->after('criminal_record_issue_date');
            
            // Período Probatório - adicionar coluna de documento
            $table->string('probation_document_path')->nullable()->after('probation_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn([
                'criminal_record_number',
                'criminal_record_issue_date',
                'criminal_record_document_path',
                'probation_document_path',
            ]);
        });
    }
};
