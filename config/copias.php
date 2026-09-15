<?php

/**
 * CÓPIAS DE SEGURANÇA — da plataforma inteira e de cada empresa.
 *
 * Pedido de 2026-09-15: «autobackup no superadmin da BD, de 6 em 6 horas, com
 * Google Drive, OneDrive, FTP e outros, e restauro» — e o mesmo em cada
 * empresa, só com os dados dela.
 *
 * Os valores daqui são os de omissão; o que o dono muda no ecrã fica em
 * `agendas_de_copia` e manda.
 */
return [

    /* Onde ficam as cópias no servidor. Dentro de storage/: fora do docroot. */
    'pasta' => storage_path('app/copias-de-seguranca'),

    'plataforma' => [
        'activa' => true,
        'intervalo_horas' => 6,
        'manter_locais' => 12,       // 3 dias a 6/6 horas
    ],

    'empresa' => [
        'activa' => true,
        'intervalo_horas' => 24,
        'manter_locais' => 7,
        // O mínimo que uma empresa pode escolher: 76 empresas a despejar de 6 em
        // 6 horas num alojamento partilhado é carga que a plataforma não aguenta.
        'intervalo_minimo_horas' => 6,
    ],

    /* Os intervalos que o ecrã oferece. */
    'intervalos' => [1, 2, 3, 4, 6, 8, 12, 24, 48, 168],

    /* Tamanho de cada pedaço enviado aos destinos que aceitam envio às partes. */
    'pedaco_bytes' => 8 * 1024 * 1024,

    /*
     | As aplicações OAuth da plataforma (Google, Microsoft, Dropbox). Criadas
     | UMA vez pelo dono da plataforma na consola de cada fornecedor; as empresas
     | só carregam em «Ligar». O ecrã da plataforma permite gravá-las cifradas
     | (system_settings) — estes são só os valores do .env, se existirem.
     */
    'oauth' => [
        'google' => ['client_id' => env('COPIAS_GOOGLE_CLIENT_ID'), 'client_secret' => env('COPIAS_GOOGLE_CLIENT_SECRET')],
        'microsoft' => ['client_id' => env('COPIAS_MICROSOFT_CLIENT_ID'), 'client_secret' => env('COPIAS_MICROSOFT_CLIENT_SECRET')],
        'dropbox' => ['client_id' => env('COPIAS_DROPBOX_CLIENT_ID'), 'client_secret' => env('COPIAS_DROPBOX_CLIENT_SECRET')],
    ],
];
