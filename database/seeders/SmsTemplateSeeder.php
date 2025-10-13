<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SmsTemplate;

class SmsTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Nova Conta - Credenciais',
                'slug' => 'new_account',
                'description' => 'Mensagem enviada quando uma nova conta de usuário é criada',
                'content' => "🎉 Bem-vindo ao {{app_name}}!\n\nSua conta foi criada na empresa: {{tenant_name}}\n\nEmail: {{user_email}}\nSenha: {{user_password}}\n\nAcesse: {{app_url}}\n\nAltere sua senha após o primeiro acesso.",
                'variables' => [
                    'app_name' => 'Nome da aplicação',
                    'tenant_name' => 'Nome da empresa',
                    'user_name' => 'Nome do usuário',
                    'user_email' => 'Email do usuário',
                    'user_password' => 'Senha do usuário',
                    'app_url' => 'URL da aplicação',
                ],
            ],
            [
                'name' => 'Pagamento Aprovado',
                'slug' => 'payment_approved',
                'description' => 'Mensagem enviada quando um pagamento é aprovado',
                'content' => "✅ PAGAMENTO APROVADO!\n\nEmpresa: {{tenant_name}}\nPlano: {{plan_name}}\n\nSeu plano foi ativado com sucesso!\nAcesse: {{app_url}}",
                'variables' => [
                    'tenant_name' => 'Nome da empresa',
                    'plan_name' => 'Nome do plano',
                    'app_url' => 'URL da aplicação',
                ],
            ],
            [
                'name' => 'Plano Expirando',
                'slug' => 'plan_expiring',
                'description' => 'Mensagem enviada quando o plano está próximo de expirar',
                'content' => "⚠️ ATENÇÃO - Plano Expirando!\n\nEmpresa: {{tenant_name}}\nSeu plano expira em {{days_remaining}} dias.\n\nRenove agora para evitar interrupção do serviço.\nAcesse: {{app_url}}",
                'variables' => [
                    'tenant_name' => 'Nome da empresa',
                    'days_remaining' => 'Dias restantes',
                    'app_url' => 'URL da aplicação',
                ],
            ],
            [
                'name' => 'Teste de SMS',
                'slug' => 'test',
                'description' => 'Template para testes de envio de SMS',
                'content' => "Teste do {{app_name}} - {{test_message}}",
                'variables' => [
                    'app_name' => 'Nome da aplicação',
                    'test_message' => 'Mensagem de teste',
                ],
            ],
        ];

        foreach ($templates as $template) {
            SmsTemplate::updateOrCreate(
                ['slug' => $template['slug'], 'tenant_id' => null],
                $template
            );
        }

        $this->command->info('✅ ' . count($templates) . ' templates SMS criados com sucesso!');
    }
}
