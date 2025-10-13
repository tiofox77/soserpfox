# 📜 Scripts do Sistema

Esta pasta contém scripts utilitários para manutenção e testes do sistema.

## 🚀 Como Usar

### Via Interface (Recomendado)

1. Acesse: **Super Admin** → **Sistema** → **Executar Scripts**
2. Selecione um script da lista
3. Clique em **Executar**
4. Visualize o output em tempo real

### Via Terminal

```bash
php scripts/nome-do-script.php
```

## 📝 Criando Novos Scripts

Para criar um novo script que aparece na interface:

```php
<?php
/**
 * DESCRIÇÃO DO SCRIPT
 * 
 * Esta descrição aparecerá na interface
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TÍTULO DO SCRIPT\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Seu código aqui

echo "\n✅ Script executado com sucesso!\n\n";
```

## 📂 Organização

| Categoria | Padrão | Exemplo |
|-----------|--------|---------|
| **Testes** | `test-*.php` ou `test_*.php` | `test-email.php` |
| **Atualizações** | `update-*.php` ou `atualizar-*.php` | `update-templates.php` |
| **Verificações** | `check-*.php` ou `verificar-*.php` | `check-database.php` |
| **Diagnósticos** | `diagnostico-*.php` | `diagnostico-email.php` |
| **Criação** | `criar-*.php` ou `setup-*.php` | `criar-tenant.php` |
| **Análise** | `analisar-*.php` | `analisar-logs.php` |

## 🔒 Segurança

⚠️ **IMPORTANTE:**

- Scripts só podem ser executados por **Super Admins**
- Todos os outputs são logados
- Scripts devem validar dados antes de modificar
- Use transações para operações críticas

## 📊 Logs

Todos os scripts executados são registrados em:

- **Arquivo:** `storage/logs/laravel.log`
- **Visualização:** Interface → Botão "Ver Logs"
- **Informações logadas:**
  - Usuário que executou
  - Horário de execução
  - Tempo de execução
  - Output gerado
  - Erros (se houver)

## 🛠️ Scripts Disponíveis

### Emails
- `test-invitation-email.php` - Testar email de convite
- `test-register-email.php` - Testar email de registro
- `test-logo-email.php` - Testar logo em emails
- `diagnostico-email-nao-chega.php` - Diagnosticar problemas de entrega

### Templates
- `atualizar-templates-faltantes.php` - Criar templates faltantes
- `criar-templates-sistema.php` - Criar todos templates do sistema
- `listar-templates.php` - Listar templates disponíveis
- `verificar-layout-templates.php` - Verificar layouts

### Sistema
- `check_blade_syntax.php` - Verificar sintaxe Blade
- `check_openssl.php` - Verificar OpenSSL
- `check_roles.php` - Verificar roles e permissões
- `UPDATE_PLANS.php` - Atualizar planos

### POS
- `test_pos_shift.php` - Testar turno POS
- `check_shifts.php` - Verificar turnos

### Comunicação
- `test_sms_d7.php` - Testar SMS via D7
- `test_sms_system.php` - Testar sistema de SMS
- `test_whatsapp_d7.php` - Testar WhatsApp via D7

## 💡 Dicas

1. **Sempre teste scripts novos** em ambiente de desenvolvimento primeiro
2. **Faça backup** antes de executar scripts que modificam dados
3. **Use echo/print** para gerar output visível na interface
4. **Documente bem** o que o script faz no comentário inicial
5. **Trate exceções** para evitar crashes

## 🔄 Atualizar Lista

A lista de scripts é atualizada automaticamente, mas você pode forçar:

- Interface: Botão **"Atualizar"**
- Ou: Adicionar novo arquivo `.php` nesta pasta

## ⚙️ Configurações

Scripts têm acesso a:

- ✅ Toda a aplicação Laravel
- ✅ Banco de dados
- ✅ Helpers e Services
- ✅ Models e Migrations
- ✅ Configurações (.env)

## 📞 Suporte

Para problemas com scripts:

1. Verifique os logs: **storage/logs/laravel.log**
2. Execute via terminal para ver erros completos
3. Verifique permissões da pasta `scripts/`

---

**Última atualização:** 11/10/2025  
**Versão:** 1.0
