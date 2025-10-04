# SOS ERP - Guia de Instalação

## 1. Configurar Base de Dados

Certifique-se de que o MySQL está rodando e crie a base de dados:

```sql
CREATE DATABASE soserp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 2. Configurar Arquivo .env

Certifique-se de que o arquivo `.env` está configurado corretamente:

```env
APP_NAME="SOS ERP"
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=soserp
DB_USERNAME=root
DB_PASSWORD=
```

## 3. Instalar Dependências

```bash
# Instalar dependências PHP
composer install

# Instalar dependências Node.js
npm install
```

## 4. Gerar Chave da Aplicação

```bash
php artisan key:generate
```

## 5. Limpar Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

## 6. Executar Migrations

```bash
php artisan migrate
```

Isso criará as seguintes tabelas:
- `users` - Utilizadores do sistema
- `tenants` - Organizações/Empresas
- `tenant_user` - Relação utilizador-organização
- `roles` - Roles do sistema
- `permissions` - Permissões do sistema
- `role_permission` - Relação role-permissão
- `modules` - Módulos disponíveis
- `tenant_module` - Módulos ativos por tenant
- `plans` - Planos de subscrição
- `subscriptions` - Subscrições ativas
- `invoices` - Faturas de billing

## 7. Popular Base de Dados (Seeders)

```bash
php artisan db:seed
```

Isso criará:
- ✅ Permissões do sistema (60+ permissões)
- ✅ Roles (Super Admin, Admin, Gestor, Utilizador)
- ✅ Módulos (Faturação, RH, Contabilidade, Oficina, etc.)
- ✅ Planos (Starter, Professional, Business, Enterprise)
- ✅ Super Admin (admin@soserp.com / password)

## 8. Compilar Assets

```bash
npm run dev
# ou para produção
npm run build
```

## 9. Criar Storage Link

```bash
php artisan storage:link
```

## 10. Iniciar Servidor

```bash
php artisan serve
```

Acesse: http://localhost:8000

## Credenciais de Acesso

**Super Admin:**
- Email: `admin@soserp.com`
- Password: `password`

⚠️ **IMPORTANTE:** Altere a senha em produção!

## Estrutura de Pastas

```
soserp/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   │   ├── IdentifyTenant.php
│   │   │   ├── EnsureTenantAccess.php
│   │   │   ├── SuperAdminMiddleware.php
│   │   │   └── CheckTenantModule.php
│   │   └── Livewire/
│   └── Models/
│       ├── User.php
│       ├── Tenant.php
│       ├── Role.php
│       ├── Permission.php
│       ├── Module.php
│       ├── Plan.php
│       ├── Subscription.php
│       └── Invoice.php
├── database/
│   ├── migrations/
│   │   ├── 2025_10_02_001_create_tenants_table.php
│   │   ├── 2025_10_02_002_create_roles_table.php
│   │   ├── 2025_10_02_003_update_users_table.php
│   │   ├── 2025_10_02_004_create_tenant_user_table.php
│   │   ├── 2025_10_02_005_create_modules_table.php
│   │   └── 2025_10_02_006_create_plans_and_subscriptions_table.php
│   └── seeders/
│       ├── PermissionSeeder.php
│       ├── RoleSeeder.php
│       ├── ModuleSeeder.php
│       ├── PlanSeeder.php
│       └── SuperAdminSeeder.php
└── DOC/
    ├── ROADMAP.md
    ├── ENV_CONFIG.md
    └── INSTALLATION.md
```

## Próximos Passos

1. ✅ Infraestrutura base configurada
2. ⏳ Desenvolver área Super Admin (Dashboard, Tenants, Billing)
3. ⏳ Desenvolver área Tenant (Dashboard, Utilizadores)
4. ⏳ Implementar módulo de Faturação
5. ⏳ Implementar módulos adicionais

## Troubleshooting

### Erro: "Failed to parse dotenv file"
- Certifique-se de que valores com espaço estão entre aspas no `.env`
- Exemplo: `APP_NAME="SOS ERP"`

### Erro: "Connection refused"
- Verifique se o MySQL está rodando
- Confirme as credenciais no `.env`

### Erro: "Class not found"
- Execute: `composer dump-autoload`
- Execute: `php artisan config:clear`
