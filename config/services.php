<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Credenciais do PRODUTOR de software junto da AGT.
     *
     * A AGT entrega conjuntos diferentes para homologação e para produção. As
     * chaves de topo (sem ambiente) são o par legado: continuam a servir de
     * recurso para os dois ambientes enquanto os específicos não existirem —
     * ver AGTProducerStore::credenciais(). É o que hoje autentica todas as
     * submissões, e cortá-lo às cegas parava-as.
     */
    'agt' => [
        'username' => env('AGT_API_USERNAME'),
        'password' => env('AGT_API_PASSWORD'),

        'sandbox' => [
            'username' => env('AGT_SANDBOX_API_USERNAME'),
            'password' => env('AGT_SANDBOX_API_PASSWORD'),
        ],

        'production' => [
            'username' => env('AGT_PRODUCTION_API_USERNAME'),
            'password' => env('AGT_PRODUCTION_API_PASSWORD'),
        ],
    ],

];
