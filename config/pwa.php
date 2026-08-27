<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Série da versão do PWA
    |--------------------------------------------------------------------------
    |
    | A parte que se escreve à mão do número que aparece no cabeçalho da
    | aplicação: `2.0` em `v2.0.37`.
    |
    | O terceiro número — o BUILD — sobe SOZINHO sempre que algum ficheiro do
    | PWA muda (o motor, o service worker, o layout ou as páginas do modo
    | offline). Não se mexe nele à mão, e é isso que o torna fiável: um número
    | que alguém se pode esquecer de subir não responde à única pergunta que se
    | faz ao cabeçalho — «isto mudou desde ontem?».
    |
    | Sobe-se a SÉRIE quando há uma mudança grande de que se queira falar:
    | `2.0` → `2.1` num conjunto de funcionalidades novas, `2.x` → `3.0` numa
    | reescrita. O build continua de onde estava; não recomeça.
    |
    */
    'serie' => env('PWA_SERIE', '2.0'),

];
