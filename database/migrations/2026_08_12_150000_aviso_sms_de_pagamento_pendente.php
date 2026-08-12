<?php

use App\Models\SoftwareSetting;
use App\Services\Billing\AvisoDePagamentoPendente;
use Illuminate\Database\Migrations\Migration;

/**
 * O número que recebe os avisos de pagamento por aprovar.
 *
 * Fica na base e não escrito no código: quem trata das aprovações muda, vai
 * de férias, ou passa a ser dois. Trocar o número não pode obrigar a um
 * deploy.
 *
 * O valor de partida é o que foi pedido; o interruptor nasce ligado.
 */
return new class extends Migration
{
    public function up(): void
    {
        SoftwareSetting::set(
            'billing',
            'aviso_sms_numero',
            AvisoDePagamentoPendente::NUMERO_POR_OMISSAO,
            'string',
            'Número que recebe o SMS quando há um pagamento à espera de aprovação'
        );

        SoftwareSetting::set(
            'billing',
            'aviso_sms_ativo',
            true,
            'boolean',
            'Avisar por SMS os pagamentos pendentes de aprovação'
        );
    }

    public function down(): void
    {
        \DB::table('software_settings')
            ->where('module', 'billing')
            ->whereIn('setting_key', ['aviso_sms_numero', 'aviso_sms_ativo'])
            ->delete();
    }
};
