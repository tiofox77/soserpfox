<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class NewUserEmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EmailTemplate::updateOrCreate(
            ['slug' => 'new-user'],
            [
                'name' => 'Novo Usuário - Credenciais de Acesso',
                'subject' => '🎉 Bem-vindo ao {{app_name}} - Suas Credenciais',
                'body_html' => $this->getTemplateHtml(),
                'variables' => json_encode([
                    'user_name' => 'Nome do usuário',
                    'user_email' => 'Email do usuário',
                    'user_password' => 'Senha do usuário',
                    'tenant_name' => 'Nome da empresa',
                    'tenant_email' => 'Email da empresa',
                    'tenant_domain' => 'Domínio da empresa',
                    'app_name' => 'Nome da aplicação',
                    'app_url' => 'URL da aplicação',
                    'login_url' => 'URL de login',
                    'support_email' => 'Email de suporte',
                ]),
                'is_active' => true,
            ]
        );
    }

    private function getTemplateHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 40px 30px;
        }
        .welcome-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .credentials-box {
            background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
            border: 2px solid #667eea;
            padding: 25px;
            margin: 25px 0;
            border-radius: 10px;
        }
        .credentials-box h3 {
            margin-top: 0;
            color: #667eea;
            font-size: 18px;
        }
        .credential-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .credential-label {
            font-weight: bold;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }
        .credential-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Conta Criada com Sucesso!</h1>
            <p style="margin: 10px 0 0 0; font-size: 16px;">Suas credenciais de acesso</p>
        </div>
        
        <div class="content">
            <div class="welcome-box">
                <p style="margin: 0; font-size: 16px;">
                    <strong>Olá, {{user_name}}!</strong>
                </p>
                <p style="margin: 10px 0 0 0;">
                    Uma conta foi criada para você na empresa <strong>{{tenant_name}}</strong>. 
                    Abaixo estão suas credenciais de acesso ao sistema <strong>{{app_name}}</strong>.
                </p>
            </div>

            <div class="credentials-box">
                <h3>🔐 Suas Credenciais de Acesso</h3>
                
                <div class="credential-item">
                    <div style="width: 100%;">
                        <div class="credential-label">📧 Email / Username</div>
                        <div class="credential-value">{{user_email}}</div>
                    </div>
                </div>
                
                <div class="credential-item">
                    <div style="width: 100%;">
                        <div class="credential-label">🔑 Senha</div>
                        <div class="credential-value">{{user_password}}</div>
                    </div>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="{{login_url}}" class="btn">
                    🚀 Acessar Sistema Agora
                </a>
            </div>

            <div class="info-box">
                <strong>⚠️ Importante - Segurança:</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>Guarde suas credenciais em local seguro</li>
                    <li><strong>Recomendamos alterar sua senha</strong> após o primeiro acesso</li>
                    <li>Nunca compartilhe sua senha com outras pessoas</li>
                    <li>Use uma senha forte com letras, números e símbolos</li>
                </ul>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <h3 style="color: #667eea; margin-top: 0;">📋 Informações da Empresa</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold;">Empresa:</td>
                        <td style="padding: 8px 0;">{{tenant_name}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold;">Email:</td>
                        <td style="padding: 8px 0;">{{tenant_email}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold;">Domínio:</td>
                        <td style="padding: 8px 0;">{{tenant_domain}}</td>
                    </tr>
                </table>
            </div>

            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; text-align: center;">
                <p style="margin: 0 0 10px 0; color: #666;">
                    <strong>Precisa de ajuda?</strong>
                </p>
                <p style="margin: 0; color: #666;">
                    Entre em contato com o suporte: <a href="mailto:{{support_email}}" style="color: #667eea;">{{support_email}}</a>
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p style="margin: 0;">© 2025 {{app_name}}. Todos os direitos reservados.</p>
            <p style="margin: 10px 0 0 0;">Este é um email automático, por favor não responda.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}

