<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Emissão automática das facturas de renovação
    |--------------------------------------------------------------------------
    |
    | Com isto ligado, o sistema emite sozinho a factura do período seguinte
    | (ver App\Services\Plataforma\RenovacaoDeSubscricoes) à boleia do tráfego,
    | uma vez por hora — ver App\Http\Middleware\FacturarRenovacoes.
    |
    | NASCE DESLIGADO DE PROPÓSITO. Uma factura é um documento que o cliente vê
    | e sobre o qual lhe é pedido dinheiro; a primeira passagem em produção
    | emite de uma vez as contas de TODAS as subscrições a acabar nos próximos
    | dias, e isso deve ser visto antes de acontecer. O caminho é:
    |
    |   1. php artisan subscriptions:renovar --so-ver   (não grava nada)
    |   2. conferir a lista
    |   3. ligar aqui, ou pôr RENOVACAO_AUTOMATICA=true no .env
    |
    | Desligado, o comando continua a existir e pode ser corrido à mão; o que
    | não acontece é a emissão sozinha.
    |
    */

    'renovacao_automatica' => env('RENOVACAO_AUTOMATICA', false),

    /*
    |--------------------------------------------------------------------------
    | Antecedência da factura de renovação, em dias
    |--------------------------------------------------------------------------
    |
    | Com quantos dias de antecedência sai a conta do período seguinte. A
    | factura vence sempre no último dia do período em curso, seja qual for
    | este valor — isto só decide quando o cliente a recebe.
    |
    */

    'dias_de_antecedencia' => (int) env('RENOVACAO_DIAS_ANTECEDENCIA', 8),

];
