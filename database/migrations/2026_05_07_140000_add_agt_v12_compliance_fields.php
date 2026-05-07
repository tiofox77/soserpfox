<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AGT v1.2 Compliance Fields
 * 
 * Adds fields required by Angola AGT Electronic Invoicing v1.2 spec:
 * - softwareInfo (productId, productVersion, softwareValidationNumber)
 * - submissionUUID, requestID for async tracking
 * - eacCode (CAE) per line
 * - jwsDocumentSignature alongside existing jws_signature
 * - series_code (AGT-issued, e.g. "LD6325S2042N"), establishment_number, contingency
 * - line-level: unit_price_base, debit_amount, credit_amount, settlement_amount
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // 1. invoicing_settings — softwareInfo + AGT v1.2
        // =====================================================
        Schema::table('invoicing_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_settings', 'agt_product_id')) {
                $table->string('agt_product_id')->nullable()->after('agt_software_certificate')
                    ->comment('AGT productId (ex: SOS ERP CERTO)');
            }
            if (!Schema::hasColumn('invoicing_settings', 'agt_product_version')) {
                $table->string('agt_product_version')->nullable()->after('agt_product_id')
                    ->comment('AGT productVersion (ex: 1.0.1)');
            }
            if (!Schema::hasColumn('invoicing_settings', 'agt_software_validation_number')) {
                $table->string('agt_software_validation_number')->nullable()->after('agt_product_version')
                    ->comment('AGT softwareValidationNumber (ex: C_134)');
            }
            if (!Schema::hasColumn('invoicing_settings', 'agt_schema_version')) {
                $table->string('agt_schema_version')->default('1.2')->after('agt_software_validation_number')
                    ->comment('AGT schema version (1.2)');
            }
            if (!Schema::hasColumn('invoicing_settings', 'agt_establishment_number')) {
                $table->string('agt_establishment_number')->default('SEDE')->after('agt_schema_version')
                    ->comment('Estabelecimento default (SEDE ou código de agência)');
            }
        });

        // =====================================================
        // 2. invoicing_series — AGT v1.2 série fields
        // =====================================================
        Schema::table('invoicing_series', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_series', 'series_code')) {
                $table->string('series_code', 30)->nullable()->after('agt_series_id')
                    ->comment('Código da série atribuído pela AGT (ex: LD6325S2042N)');
            }
            if (!Schema::hasColumn('invoicing_series', 'establishment_number')) {
                $table->string('establishment_number')->default('SEDE')->after('series_code')
                    ->comment('Estabelecimento da série');
            }
            if (!Schema::hasColumn('invoicing_series', 'series_contingency_indicator')) {
                $table->enum('series_contingency_indicator', ['N', 'S'])->default('N')
                    ->after('establishment_number')
                    ->comment('N = normal, S = contingência');
            }
            if (!Schema::hasColumn('invoicing_series', 'authorized_quantity')) {
                $table->bigInteger('authorized_quantity')->nullable()->after('series_contingency_indicator')
                    ->comment('Quantidade autorizada pela AGT');
            }
            if (!Schema::hasColumn('invoicing_series', 'first_document_no')) {
                $table->bigInteger('first_document_no')->nullable()->after('authorized_quantity')
                    ->comment('Primeiro número de documento autorizado');
            }
            if (!Schema::hasColumn('invoicing_series', 'last_document_no')) {
                $table->bigInteger('last_document_no')->nullable()->after('first_document_no')
                    ->comment('Último número de documento autorizado');
            }
            if (!Schema::hasColumn('invoicing_series', 'series_year')) {
                $table->year('series_year')->nullable()->after('last_document_no')
                    ->comment('Ano da série');
            }
            if (!Schema::hasColumn('invoicing_series', 'submission_uuid')) {
                $table->uuid('submission_uuid')->nullable()->after('series_year')
                    ->comment('UUID enviado à AGT no SolicitarSerie');
            }
        });

        // =====================================================
        // 3. invoicing_sales_invoices — AGT v1.2 doc fields
        // =====================================================
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicing_sales_invoices', 'jws_document_signature')) {
                $table->text('jws_document_signature')->nullable()->after('jws_signature')
                    ->comment('JWS RS256 do documento (campos canónicos AGT)');
            }
            if (!Schema::hasColumn('invoicing_sales_invoices', 'agt_request_id')) {
                $table->string('agt_request_id', 30)->nullable()->after('agt_reference')
                    ->comment('requestID devolvido pela AGT no Registar (assíncrono)');
            }
            if (!Schema::hasColumn('invoicing_sales_invoices', 'agt_submission_uuid')) {
                $table->uuid('agt_submission_uuid')->nullable()->after('agt_request_id')
                    ->comment('submissionUUID enviado à AGT');
            }
            if (!Schema::hasColumn('invoicing_sales_invoices', 'eac_code')) {
                $table->string('eac_code', 10)->nullable()->after('agt_submission_uuid')
                    ->comment('Código de Actividade Económica (CAE)');
            }
            if (!Schema::hasColumn('invoicing_sales_invoices', 'document_status_code')) {
                $table->enum('document_status_code', ['N', 'A', 'F', 'S'])->default('N')
                    ->after('eac_code')
                    ->comment('N=Normal, A=Anulado, F=Facturada, S=Auto-facturação');
            }
        });

        // =====================================================
        // 4. invoicing_credit_notes / debit_notes / receipts / proformas
        // =====================================================
        foreach (['invoicing_credit_notes', 'invoicing_debit_notes', 'invoicing_receipts', 'invoicing_sales_proformas'] as $tbl) {
            if (!Schema::hasTable($tbl)) continue;

            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (!Schema::hasColumn($tbl, 'jws_document_signature')) {
                    $col = $table->text('jws_document_signature')->nullable();
                    if (Schema::hasColumn($tbl, 'jws_signature')) {
                        $col->after('jws_signature');
                    }
                }
                if (!Schema::hasColumn($tbl, 'agt_request_id')) {
                    $table->string('agt_request_id', 30)->nullable();
                }
                if (!Schema::hasColumn($tbl, 'agt_submission_uuid')) {
                    $table->uuid('agt_submission_uuid')->nullable();
                }
                if (!Schema::hasColumn($tbl, 'eac_code')) {
                    $table->string('eac_code', 10)->nullable();
                }
                if (!Schema::hasColumn($tbl, 'document_status_code')) {
                    $table->enum('document_status_code', ['N', 'A', 'F', 'S'])->default('N');
                }
            });
        }

        // =====================================================
        // 5. Linhas — campos v1.2 (unit_price_base, debit/credit, settlement)
        // =====================================================
        $itemTables = [
            'invoicing_sales_invoice_items',
            'invoicing_credit_note_items',
            'invoicing_debit_note_items',
            'invoicing_sales_proforma_items',
        ];

        foreach ($itemTables as $tbl) {
            if (!Schema::hasTable($tbl)) continue;

            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (!Schema::hasColumn($tbl, 'unit_price_base')) {
                    $table->decimal('unit_price_base', 18, 4)->nullable()
                        ->comment('Preço unitário base (sem desconto) — AGT v1.2');
                }
                if (!Schema::hasColumn($tbl, 'debit_amount')) {
                    $table->decimal('debit_amount', 18, 4)->default(0)
                        ->comment('Valor a débito (NC) — AGT v1.2');
                }
                if (!Schema::hasColumn($tbl, 'credit_amount')) {
                    $table->decimal('credit_amount', 18, 4)->default(0)
                        ->comment('Valor a crédito (FT/FR) — AGT v1.2');
                }
                if (!Schema::hasColumn($tbl, 'settlement_amount')) {
                    $table->decimal('settlement_amount', 18, 4)->default(0)
                        ->comment('Valor de desconto/regularização — AGT v1.2');
                }
                if (!Schema::hasColumn($tbl, 'eac_code')) {
                    $table->string('eac_code', 10)->nullable()
                        ->comment('CAE da linha');
                }
                if (!Schema::hasColumn($tbl, 'tax_country_region')) {
                    $table->string('tax_country_region', 10)->default('AO')
                        ->comment('AO ou AO-CAB (Cabinda)');
                }
                if (!Schema::hasColumn($tbl, 'tax_code')) {
                    $table->string('tax_code', 10)->nullable()
                        ->comment('NOR, RED, ISE, NS, OUT, etc.');
                }
                if (!Schema::hasColumn($tbl, 'tax_exemption_code')) {
                    $table->string('tax_exemption_code', 10)->nullable()
                        ->comment('M30, M40, etc.');
                }
                if (!Schema::hasColumn($tbl, 'tax_exemption_reason')) {
                    $table->string('tax_exemption_reason', 255)->nullable();
                }
            });
        }

        // =====================================================
        // 6. withholding_taxes (nova tabela polimórfica)
        // =====================================================
        if (!Schema::hasTable('invoicing_withholding_taxes')) {
            Schema::create('invoicing_withholding_taxes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->morphs('document');
                $table->string('withholding_tax_type', 10)
                    ->comment('IRT, IPU, IPC, etc.');
                $table->string('withholding_tax_description', 255);
                $table->decimal('withholding_tax_percentage', 5, 2)->default(0);
                $table->decimal('withholding_tax_amount', 18, 4)->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'withholding_tax_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_withholding_taxes');

        $itemTables = [
            'invoicing_sales_invoice_items',
            'invoicing_credit_note_items',
            'invoicing_debit_note_items',
            'invoicing_sales_proforma_items',
        ];
        foreach ($itemTables as $tbl) {
            if (!Schema::hasTable($tbl)) continue;
            Schema::table($tbl, function (Blueprint $table) {
                $cols = ['unit_price_base', 'debit_amount', 'credit_amount', 'settlement_amount',
                         'eac_code', 'tax_country_region', 'tax_code', 'tax_exemption_code', 'tax_exemption_reason'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn($table->getTable(), $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        foreach (['invoicing_sales_invoices', 'invoicing_credit_notes', 'invoicing_debit_notes', 'invoicing_receipts', 'invoicing_sales_proformas'] as $tbl) {
            if (!Schema::hasTable($tbl)) continue;
            Schema::table($tbl, function (Blueprint $table) {
                foreach (['jws_document_signature', 'agt_request_id', 'agt_submission_uuid', 'eac_code', 'document_status_code'] as $c) {
                    if (Schema::hasColumn($table->getTable(), $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        Schema::table('invoicing_series', function (Blueprint $table) {
            foreach (['series_code', 'establishment_number', 'series_contingency_indicator',
                      'authorized_quantity', 'first_document_no', 'last_document_no',
                      'series_year', 'submission_uuid'] as $c) {
                if (Schema::hasColumn('invoicing_series', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        Schema::table('invoicing_settings', function (Blueprint $table) {
            foreach (['agt_product_id', 'agt_product_version', 'agt_software_validation_number',
                      'agt_schema_version', 'agt_establishment_number'] as $c) {
                if (Schema::hasColumn('invoicing_settings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
