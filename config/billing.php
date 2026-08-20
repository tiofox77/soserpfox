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

    /*
    |--------------------------------------------------------------------------
    | Avisos ao cliente
    |--------------------------------------------------------------------------
    |
    | Interruptor MESTRE dos avisos de facturação ao cliente (email e SMS):
    | factura emitida, a vencer, vencida, renovada, e plano a expirar.
    |
    | Nasce DESLIGADO em cada instalação nova. Assim que se liga, a primeira
    | varredura percorre todas as subscrições e facturas de uma vez — são
    | mensagens a pessoas reais. Ver SEMPRE primeiro o que ela faria:
    |
    |   php artisan subscricoes:avisos --so-ver
    |
    */

    // Ligado a 20/08/2026. A leitura seca em produção deu zero — não havia
    // nada a enviar nesse momento, pelo que ligar não mandou mensagem nenhuma.
    'avisos_ao_cliente' => env('BILLING_AVISOS_CLIENTE', true),

    /*
    | O canal PAGO, com interruptor SEPARADO do mestre e de propósito.
    |
    | Cada SMS é dinheiro na conta da plataforma. Ligar os avisos não pode, por
    | si só, começar a gastar na operadora: liga-se o email, vê-se uma semana
    | de registos limpos, e só então se liga isto.
    */

    // Idem. Na prática o volume é pequeno: quase nenhum utilizador tem
    // telefone gravado, e um número que o SmsService não consiga marcar é
    // saltado sem se chegar a pagar nada à operadora.
    'avisos_sms' => env('BILLING_AVISOS_SMS', true),

    /*
    | Em que dias exactos se avisa — não em que intervalo.
    |
    | Um intervalo mandava o mesmo aviso todos os dias até o cliente pagar. O 8
    | não está na lista porque é o dia em que a factura é emitida, e já leva o
    | seu próprio aviso.
    */

    'avisos_dias_antes'  => [3, 1],       // antes do vencimento
    'avisos_dias_atraso' => [1, 3, 7],    // depois do vencimento

    /*
    | Tecto de mensagens por passagem. Uma rajada acidental fica contida, e o
    | que sobra sai na passagem seguinte. Quando é atingido fica registado —
    | um tecto que corta em silêncio faz parecer que está tudo tratado.
    */

    'avisos_max_por_passagem' => (int) env('BILLING_AVISOS_MAX', 25),

];
