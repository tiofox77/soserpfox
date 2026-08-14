<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Images URL (Produção)
    |--------------------------------------------------------------------------
    |
    | URL base para imagens de produtos. Use o domínio de produção para garantir
    | que as imagens sempre sejam carregadas do servidor correto, mesmo em
    | ambiente local. Exemplo: https://gur.ao
    |
    */

    'images_url' => env('IMAGES_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    /*
    | Angola, e não UTC. Quem usa isto está em Angola e tudo o que via — a hora
    | de uma venda, de um turno, de um movimento — aparecia uma hora atrasada.
    |
    | Angola é UTC+1 o ano inteiro e não tem horário de verão, pelo que a
    | diferença é sempre a mesma hora.
    |
    | O QUE ISTO NÃO FAZ: não altera um único byte já gravado. O MySQL guarda
    | DATETIME sem fuso nenhum, portanto o que muda é o SIGNIFICADO do que já lá
    | está — os registos anteriores a esta mudança continuam a mostrar a hora
    | UTC a que foram feitos, agora lida como hora de Angola, ou seja uma hora
    | mais cedo do que aconteceram. Foi uma decisão: só os documentos novos
    | passam a ser gravados em hora de Angola.
    |
    | E é a decisão certa pelo lado fiscal. Somar uma hora ao que está gravado
    | partia as assinaturas: o system_entry_date entra na cadeia assinada com
    | RSA-SHA256, e como cada documento assina o hash do anterior, mexer num
    | único partia a cadeia inteira a partir dele. Está medido: 1288 facturas
    | com hash gravado, 176 já comunicadas à AGT.
    |
    | O QUE FOI PRECISO ARRANJAR PARA ISTO NÃO PARTIR NADA:
    |   · DocumentMapper convertia o system_entry_date GRAVADO para UTC. Era
    |     inofensivo só porque o fuso também era UTC; aqui passaria a declarar
    |     à AGT um instante diferente do assinado;
    |   · o POS offline carimba em ISO com Z e o servidor gravava esses dígitos
    |     em bruto — ver DateHelper::doDispositivo();
    |   · a data por omissão dos documentos offline vinha do relógio UTC, o que
    |     datava do dia anterior tudo o que fosse vendido entre a meia-noite e
    |     a uma da manhã.
    |
    | NÃO acrescentar 'timezone' à ligação MySQL em config/database.php: as
    | colunas de negócio são TIMESTAMP, que o MySQL converte à ida e à volta
    | pelo fuso da SESSÃO. Fixá-lo desloca tudo o que já está gravado — que é
    | exactamente o que esta decisão evita.
    */

    'timezone' => env('APP_TIMEZONE', 'Africa/Luanda'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'pt'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'pt'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
