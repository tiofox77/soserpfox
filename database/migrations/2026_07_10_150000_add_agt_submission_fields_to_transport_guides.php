<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_transport_guides', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_transport_guides', 'jws_document_signature')) {
                $table->text('jws_document_signature')->nullable();
            }
            if (!Schema::hasColumn('invoicing_transport_guides', 'agt_submission_uuid')) {
                $table->string('agt_submission_uuid', 100)->nullable();
            }
            if (!Schema::hasColumn('invoicing_transport_guides', 'agt_request_id')) {
                $table->string('agt_request_id', 100)->nullable();
            }
            if (!Schema::hasColumn('invoicing_transport_guides', 'agt_submitted_at')) {
                $table->dateTime('agt_submitted_at')->nullable();
            }
            if (!Schema::hasColumn('invoicing_transport_guides', 'document_status_code')) {
                $table->string('document_status_code', 5)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_transport_guides', function (Blueprint $table) {
            foreach (['jws_document_signature', 'agt_submission_uuid', 'agt_request_id', 'agt_submitted_at', 'document_status_code'] as $col) {
                if (Schema::hasColumn('invoicing_transport_guides', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
