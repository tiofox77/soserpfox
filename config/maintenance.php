<?php

/*
 * Token para os endpoints HTTP de manutenção remota.
 * Usado pelo App\Http\Controllers\MaintenanceController.
 *
 * Para rotação: gera novo token com:
 *   php -r "echo bin2hex(random_bytes(24));"
 * e substitui o valor abaixo, ou define MAINTENANCE_TOKEN no .env.
 */
return [
    'token' => env('MAINTENANCE_TOKEN', '372ea01cee5827cbc9f8dbf7d6d4180dc3c0a0cf9aa24d48'),
];
