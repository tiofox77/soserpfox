<?php

/*
 * CORS — permite que a app móvel/web (Flutter, outros domínios) consuma a API.
 * Token Bearer (sem cookies) => supports_credentials = false e origins '*' OK.
 */
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
