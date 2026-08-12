<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Descobrir a região do visitante
    |--------------------------------------------------------------------------
    |
    | O painel de países existia desde sempre e nunca mostrou nada: o país só
    | era lido do cabeçalho `CF-IPCountry`, que existe quando o site está atrás
    | da Cloudflare. Este alojamento não está.
    |
    | Com isto ligado, os endereços por resolver são enviados EM LOTE e DEPOIS
    | do pedido — nunca durante — para o ip-api.com, que devolve país e cidade.
    | Cada endereço é perguntado uma única vez; o resto copia-se do que já se
    | sabe.
    |
    | O QUE SAI DAQUI: o endereço IP, e mais nada. Sem página visitada, sem
    | identificador de visitante, sem quem é. Endereços privados e de rede
    | local nunca saem.
    |
    | Ainda assim é dado de terceiros a sair para fora do sistema. Quem tiver de
    | o impedir põe ANALYTICS_GEO=false no .env — o painel de países fica vazio
    | e nada mais muda.
    |
    */

    'geo' => env('ANALYTICS_GEO', true),

];
