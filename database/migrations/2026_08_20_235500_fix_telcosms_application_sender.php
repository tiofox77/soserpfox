<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sms_settings')
            ->where('provider', 'telcosms')
            ->update([
                'sender_id' => 'SOSERP',
                'config' => json_encode([
                    'telco_application' => 'soserp_prd',
                    'sender' => 'SOSERP',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Não restaurar o remetente incorreto nem alterar credenciais existentes.
    }
};
