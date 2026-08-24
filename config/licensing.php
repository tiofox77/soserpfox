<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chave PÚBLICA do emissor (vendor)
    |--------------------------------------------------------------------------
    |
    | Ed25519, em Base64 (32 bytes). É a ÚNICA metade do par que vive na
    | máquina do cliente. A chave PRIVADA (que assina as licenças) nunca sai
    | de quem gere a plataforma — sem ela, ninguém forja uma licença válida.
    |
    | Gera-se o par uma vez com `php artisan licenca:chaves`. A pública vai
    | aqui (ou no .env do cliente); a privada guarda-se em cofre, fora do repo.
    |
    */
    'public_key' => env('LICENSE_PUBLIC_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Onde a licença e o estado vivem na máquina do cliente
    |--------------------------------------------------------------------------
    |
    | `license_path`  — o ficheiro com o token da licença (texto).
    | `state_path`    — estado local: último check-in e o relógio-máximo já
    |                    visto (para apanhar quem recua a data do PC).
    | `inline_token`  — alternativa ao ficheiro: a licença no próprio .env.
    |
    */
    'license_path' => env('LICENSE_PATH', storage_path('app/license/license.key')),
    'state_path'   => env('LICENSE_STATE_PATH', storage_path('app/license/state.json')),
    'inline_token' => env('LICENSE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Barreira offline (dias sem "ligar a casa")
    |--------------------------------------------------------------------------
    |
    | Valor por omissão. A própria licença pode sobrepor por-tenant (campo
    | `graca`), porque um cliente numa zona sem rede fiável precisa de mais
    | folga do que um na cidade.
    |
    */
    'offline_grace_days' => (int) env('LICENSE_OFFLINE_GRACE_DAYS', 15),

    /*
    |--------------------------------------------------------------------------
    | Escada de degradação (fração da graça → estado)
    |--------------------------------------------------------------------------
    |
    | Bloquear em seco no dia 15 apanha o cliente de surpresa. Isto avisa
    | primeiro, depois trava a escrita, e só no fim tranca. Ex.: graça=15 →
    | avisa ao 8º dia, banner ao 12º, só-leitura ao 15º… antes do bloqueio.
    |
    */
    'degradacao' => [
        'aviso'      => (float) env('LICENSE_STEP_AVISO', 0.5),
        'banner'     => (float) env('LICENSE_STEP_BANNER', 0.8),
        'so_leitura' => (float) env('LICENSE_STEP_SO_LEITURA', 0.95),
        // 1.0 (100%) = bloqueio total, tratado no verificador.
    ],

    /*
    |--------------------------------------------------------------------------
    | Prender a licença a UMA máquina (fingerprint de hardware)
    |--------------------------------------------------------------------------
    |
    | Quando ligado, uma licença emitida com fingerprint só corre nessa
    | máquina: copiá-la para outra máquina invalida-a. Emitir sem fingerprint
    | (licença "flutuante") continua a funcionar em qualquer lado.
    |
    */
    'bind_machine' => (bool) env('LICENSE_BIND_MACHINE', true),

    /*
    |--------------------------------------------------------------------------
    | Tolerância de recuo do relógio (segundos)
    |--------------------------------------------------------------------------
    |
    | Se o relógio do sistema andar para trás mais do que isto face ao maior
    | instante já observado, a verificação suspende-se (batota de data). Uns
    | minutos de folga para acertos de fuso/NTP legítimos.
    |
    */
    'clock_skew_tolerance' => (int) env('LICENSE_CLOCK_SKEW', 120),

    /*
    |--------------------------------------------------------------------------
    | Enforcement — ligar/desligar o bloqueio
    |--------------------------------------------------------------------------
    |
    | INTERRUPTOR MESTRE. Falso por omissão: na CLOUD o middleware de licença é
    | um no-op total (nem lê a licença). SÓ a build offline/on-premise põe
    | LICENSE_ENFORCE=true. Nunca ligar isto na cloud — trancaria a plataforma
    | inteira (não há licença instalada).
    |
    */
    'enforce' => (bool) env('LICENSE_ENFORCE', false),

    /*
    |--------------------------------------------------------------------------
    | Rotas sempre livres (mesmo com o sistema bloqueado)
    |--------------------------------------------------------------------------
    |
    | O ecrã de activação, o login/logout, o health-check e a manutenção têm
    | de responder mesmo quando a licença bloqueia — senão o cliente fica sem
    | forma de instalar uma licença nova. Padrões do Laravel `Request::is()`.
    |
    */
    'rotas_livres' => [
        'licenca', 'licenca/*',
        'login', 'logout',
        'up', 'maintenance/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Check-in / phone-home — CLIENTE (build offline)
    |--------------------------------------------------------------------------
    |
    | `checkin_url` — para onde a instalação liga para "dar sinal de vida" e
    |                 receber uma licença renovada. Vazio = nunca liga (fica só
    |                 na graça offline; usa-se em demos ou air-gapped).
    | `checkin_interval_hours` — de quanto em quanto tempo tenta (à boleia do
    |                 tráfego, com tranca).
    |
    | REGRA DE OURO: o contador offline SÓ reinicia quando o cliente recebe uma
    | licença renovada com ASSINATURA VÁLIDA. Um servidor falso ou um
    | man-in-the-middle não consegue manter uma cópia pirata viva.
    |
    */
    // Com um valor por omissão: instalações feitas por versões antigas do
    // instalador não têm esta chave no .env e ficavam sem forma de pedir
    // licença online, sem dizerem porquê. Inofensivo na cloud — nada disto é
    // usado com o `enforce` desligado.
    'checkin_url'            => env('LICENSE_CHECKIN_URL', 'https://soserp.vip/api/license/checkin'),
    'checkin_interval_hours' => (int) env('LICENSE_CHECKIN_INTERVAL', 12),

    /*
    |--------------------------------------------------------------------------
    | Renovação — SERVIDOR de licenças (vendor)
    |--------------------------------------------------------------------------
    |
    | `signing_key` — a chave PRIVADA que assina as licenças renovadas no
    |                 check-in. Vive SÓ na infra do vendor (env), NUNCA numa
    |                 build de cliente. Sem ela, o servidor não renova.
    | `renew_days`  — validade de cada licença renovada. Curta de propósito:
    |                 obriga a instalação a continuar a ligar-se.
    |
    | Idealmente isto corre num serviço próprio e endurecido, separado da app
    | web — ter a chave privada na app é um risco a mitigar (ver PRD §6/§7).
    |
    */
    'signing_key' => env('LICENSE_SIGNING_KEY'),
    'renew_days'  => (int) env('LICENSE_RENEW_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Atualizações (F4)
    |--------------------------------------------------------------------------
    |
    | Manifesto de atualização = descritor ASSINADO (Ed25519) de uma versão:
    | versão, versão mínima, URL do pacote e o seu SHA-256. O cliente NUNCA
    | aplica nada sem verificar a assinatura do manifesto E o hash do pacote —
    | e qualquer falha ao aplicar faz rollback (restaura backup).
    |
    | Chaves: por omissão reutilizam as da licença; podem ser separadas.
    |   - `public_key`  (cliente verifica)  ← vendor
    |   - `signing_key` (vendor assina)     ← NUNCA no cliente
    |
    | Rollout é decidido POR-TENANT pelo super admin (tabela app_update_targets
    | ou rollout='all' na versão). `current` é a versão instalada nesta máquina.
    |
    */
    'update' => [
        'check_url'   => env('LICENSE_UPDATE_URL', ''),
        'public_key'  => env('LICENSE_UPDATE_PUBLIC_KEY', env('LICENSE_PUBLIC_KEY', '')),
        'signing_key' => env('LICENSE_UPDATE_SIGNING_KEY', env('LICENSE_SIGNING_KEY')),
        'current'     => env('APP_VERSION', '1.0.0'),
        'backup_dir'  => env('LICENSE_UPDATE_BACKUP_DIR', storage_path('app/updates/backups')),
        'work_dir'    => env('LICENSE_UPDATE_WORK_DIR', storage_path('app/updates/work')),
        'auto_apply'  => (bool) env('LICENSE_UPDATE_AUTO_APPLY', false),
    ],

];
