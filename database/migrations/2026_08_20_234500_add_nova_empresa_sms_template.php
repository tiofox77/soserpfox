<?php

use App\Models\SmsTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sms_templates')) return;
        SmsTemplate::updateOrCreate(['slug' => 'nova_empresa'], [
            'name' => 'Nova Empresa Registada',
            'description' => 'Aviso ao Super Admin quando uma nova empresa entra na plataforma',
            'content' => 'SOS ERP: nova empresa registada - {{tenant_name}}{{tenant_nif}}. Ver em {{app_url}}/superadmin/tenants',
            'variables' => [
                'tenant_name' => 'Nome da empresa',
                'tenant_nif' => 'NIF da empresa entre parênteses',
                'app_url' => 'URL da aplicação',
            ],
            'is_active' => true,
            'tenant_id' => null,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('sms_templates')) SmsTemplate::where('slug', 'nova_empresa')->whereNull('tenant_id')->delete();
    }
};
