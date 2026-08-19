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
    | Uma factura é um documento que o cliente vê e sobre o qual lhe é pedido
    | dinheiro, pelo que a primeira passagem em produção NÃO se dá às cegas:
    | vê-se primeiro o que ela faria, e só depois se liga.
    |
    |   1. php artisan subscriptions:renovar --so-ver   (não grava nada)
    |   2. conferir a lista
    |   3. ligar aqui, ou pôr RENOVACAO_AUTOMATICA=false no .env para desligar
    |
    | Feito a 19/08/2026: a leitura seca em produção deu zero facturas a
    | emitir (a renovação mais próxima é a 18/09/2026), pelo que ligar não
    | emitiu nada nesse momento. Fica ligado a partir daqui.
    |
    | Desligado, o comando continua a existir e pode ser corrido à mão; o que
    | não acontece é a emissão sozinha.
    |
    */

    'renovacao_automatica' => env('RENOVACAO_AUTOMATICA', true),

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
