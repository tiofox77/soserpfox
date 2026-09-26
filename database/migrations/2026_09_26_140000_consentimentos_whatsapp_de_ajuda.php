<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O CONSENTIMENTO PARA SER CONTACTADO POR WHATSAPP (26/09/2026).
 *
 * «Preciso de ajuda para começar» deixa a pessoa aceitar — ou recusar — que a
 * equipa a contacte por WhatsApp. A prova tem de dizer PARA QUÊ (a finalidade),
 * EM QUE EMPRESA estava quando escolheu e PARA QUE NÚMERO: um «sim» sem número
 * não autoriza mensagem nenhuma, e o agente que envia lê daqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consentimentos', function (Blueprint $table) {
            if (! Schema::hasColumn('consentimentos', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('visitor_id')->index();
            }
            if (! Schema::hasColumn('consentimentos', 'finalidade')) {
                $table->string('finalidade', 120)->nullable()->after('versao');
            }
            if (! Schema::hasColumn('consentimentos', 'contacto')) {
                $table->string('contacto', 20)->nullable()->after('finalidade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consentimentos', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['tenant_id', 'finalidade', 'contacto']);
        });
    }
};
