# 🤖 Sistema de Rejeição Automática de Pedidos

## ✅ Status: IMPLEMENTADO E AGENDADO

---

## 🎯 O que foi implementado

### 1. ✅ **Command** - Rejeitar Pedidos Expirados
**Arquivo:** `app/Console/Commands/RejectExpiredOrders.php`

**Descrição:**
Command que busca e rejeita automaticamente pedidos com status `'pending'` que foram criados há mais de X dias (padrão: 7 dias).

**Assinatura:**
```bash
php artisan orders:reject-expired [opções]
```

**Opções:**
- `--days=7` - Número de dias para considerar pedido expirado (padrão: 7)
- `--dry-run` - Modo simulação (não executa, apenas mostra)

---

### 2. ✅ **Agendamento** - Execução Diária Automática
**Arquivo:** `routes/console.php` (linha 24-29)

```php
// Rejeitar pedidos pendentes há mais de 7 dias - Executar diariamente às 9h
Schedule::command('orders:reject-expired --days=7')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));
```

**Configuração:**
- ⏰ **Horário:** Todos os dias às 09:00
- 🔒 **Proteção:** Não sobrepõe execuções (withoutOverlapping)
- 🖥️ **Servidor único:** Executa em apenas um servidor (onOneServer)
- 📧 **Notificação:** Email em caso de falha

---

## 🔄 Como Funciona

### **Fluxo Automático:**

```
┌─────────────────────────────────────────┐
│  Scheduler do Laravel (Cron)           │
│  Executa todos os dias às 09:00        │
└──────────────┬──────────────────────────┘
               ↓
┌─────────────────────────────────────────┐
│  Command: orders:reject-expired         │
│  Busca pedidos pending > 7 dias         │
└──────────────┬──────────────────────────┘
               ↓
┌─────────────────────────────────────────┐
│  Para cada pedido encontrado:           │
│  1. $order->reject($reason, 1)          │
│  2. Status → 'rejected'                 │
│  3. rejection_reason → "Pedido..."      │
│  4. rejected_by → 1 (Sistema)           │
└──────────────┬──────────────────────────┘
               ↓
┌─────────────────────────────────────────┐
│  OrderObserver::updated()               │
│  Detecta mudança para 'rejected'        │
└──────────────┬──────────────────────────┘
               ↓
┌─────────────────────────────────────────┐
│  processRejection($order)               │
│  1. Busca SMTP do BD                    │
│  2. configure()                         │
│  3. Busca template 'plan_rejected'      │
│  4. Renderiza com dados                 │
└──────────────┬──────────────────────────┘
               ↓
┌─────────────────────────────────────────┐
│  Mail::send(TemplateMail)               │
│  ✉️ Email enviado ao cliente            │
└─────────────────────────────────────────┘
```

---

## 🧪 Como Testar

### **1. Modo Dry-Run (Simulação)**
```bash
# Simular com 7 dias
php artisan orders:reject-expired --dry-run

# Simular com 3 dias
php artisan orders:reject-expired --dry-run --days=3

# Simular com 30 dias
php artisan orders:reject-expired --dry-run --days=30
```

**Output esperado:**
```
🔍 Buscando pedidos pendentes há mais de 7 dias...
📋 Encontrados 2 pedidos para rejeitar:

+----+---------------+---------------+--------------+-----------+------------------+
| ID | Tenant        | Plano         | Valor        | Criado há | Data             |
+----+---------------+---------------+--------------+-----------+------------------+
| 45 | Empresa ABC   | Plano Premium | R$ 299,00    | 10 dias   | 30/09/2025 14:30 |
| 67 | Loja XYZ      | Plano Básico  | R$ 99,00     | 8 dias    | 01/10/2025 09:15 |
+----+---------------+---------------+--------------+-----------+------------------+

⚠️  Modo DRY-RUN ativo. Nenhum pedido será rejeitado.
```

---

### **2. Execução Manual Real**
```bash
# Rejeitar pedidos pendentes há mais de 7 dias
php artisan orders:reject-expired

# Você será perguntado se confirma
# Digite 'yes' para confirmar
```

**Output esperado:**
```
🔍 Buscando pedidos pendentes há mais de 7 dias...
📋 Encontrados 2 pedidos para rejeitar:

[tabela com pedidos]

 Deseja rejeitar estes pedidos? (yes/no) [yes]:
 > yes

🔄 Rejeitando pedidos...

Rejeitando pedido #45 (Empresa ABC)...
  ✅ Rejeitado com sucesso
Rejeitando pedido #67 (Loja XYZ)...
  ✅ Rejeitado com sucesso

═══════════════════════════════════════════════════════
✅ Processamento concluído!
   • Rejeitados com sucesso: 2
═══════════════════════════════════════════════════════
```

---

### **3. Verificar Agendamento**
```bash
# Listar tarefas agendadas
php artisan schedule:list

# Output:
# 0 9 * * * php artisan orders:reject-expired --days=7 .... Next Due: 1 day from now
```

---

### **4. Executar Schedule Manualmente**
```bash
# Rodar todas as tarefas agendadas agora
php artisan schedule:run

# Verificar saída no log
tail -f storage/logs/laravel.log
```

---

## 📧 Email Enviado

Quando um pedido é rejeitado automaticamente, o cliente recebe:

**Subject:** `❌ Atualização sobre seu plano - {app_name}`  
**Template:** `plan_rejected`  
**SMTP:** Do banco de dados

**Variáveis preenchidas:**
```php
[
    'user_name' => 'João Silva',
    'tenant_name' => 'Empresa ABC',
    'plan_name' => 'Plano Premium',
    'amount' => 'R$ 299,00',
    'reason' => 'Pedido pendente há mais de 7 dias sem confirmação de pagamento.',
    'order_id' => 45,
    'support_email' => 'suporte@soserp.vip',
    'support_url' => 'https://soserp.vip/support',
    'billing_url' => 'https://soserp.vip/billing',
]
```

---

## 📊 Logs Gerados

### **No Command:**
```
🔍 Buscando pedidos pendentes há mais de 7 dias...
📋 Encontrados 2 pedidos para rejeitar
🔄 Rejeitando pedidos...
Rejeitando pedido #45 (Empresa ABC)...
  ✅ Rejeitado com sucesso
```

### **No Laravel Log:**
```log
[2025-10-09 09:00:01] Rejeitando pedido
   - order_id: 45
   - reason: Pedido pendente há mais de 7 dias...
   - rejected_by: 1

[2025-10-09 09:00:02] ❌ OrderObserver: Pedido rejeitado, enviando notificação
   - order_id: 45
   - old_status: pending
   - new_status: rejected

[2025-10-09 09:00:03] 📧 Iniciando envio de notificação de rejeição
   - tenant_id: 12
   - user_email: joao@empresa.com

[2025-10-09 09:00:04] ✅ Email de rejeição enviado com sucesso!
   - template: plan_rejected
```

---

## ⚙️ Configuração do Cron

Para o agendamento funcionar em produção, adicione ao crontab:

```bash
# Editar crontab
crontab -e

# Adicionar esta linha:
* * * * * cd /path/to/soserp && php artisan schedule:run >> /dev/null 2>&1
```

**Verificar se está rodando:**
```bash
# Ver logs do cron
tail -f /var/log/cron

# Ou ver saída do Laravel
tail -f storage/logs/laravel.log
```

---

## 🎛️ Personalização

### **Alterar dias para rejeição:**

**Opção 1: No agendamento**
```php
// routes/console.php
Schedule::command('orders:reject-expired --days=14') // 14 dias
    ->dailyAt('09:00');
```

**Opção 2: Manual**
```bash
php artisan orders:reject-expired --days=14
```

---

### **Alterar horário de execução:**
```php
// routes/console.php
Schedule::command('orders:reject-expired --days=7')
    ->dailyAt('02:00') // 02:00 da manhã
    // ou
    ->daily() // Todo dia à meia-noite
    // ou
    ->twiceDaily(9, 18) // 09:00 e 18:00
    // ou
    ->weekly() // Semanalmente
```

---

### **Múltiplas verificações:**
```php
// Verificar diariamente com diferentes prazos
Schedule::command('orders:reject-expired --days=7')
    ->dailyAt('09:00');

Schedule::command('orders:reject-expired --days=14')
    ->dailyAt('10:00');

Schedule::command('orders:reject-expired --days=30')
    ->dailyAt('11:00');
```

---

## 🔐 Segurança

- ✅ Campo `rejected_by` = 1 (Sistema/Auto)
- ✅ Motivo padronizado e claro para o cliente
- ✅ Sem confirmação em modo agendado (automático)
- ✅ Com confirmação em modo manual
- ✅ Modo dry-run para testes seguros
- ✅ Logs detalhados de todas as operações
- ✅ Email de notificação em caso de falha

---

## 📈 Monitoramento

### **Verificar pedidos que seriam rejeitados:**
```bash
# Ver quantos seriam rejeitados sem executar
php artisan orders:reject-expired --dry-run
```

### **Criar relatório mensal:**
```sql
-- Pedidos rejeitados automaticamente este mês
SELECT 
    COUNT(*) as total,
    SUM(amount) as valor_total
FROM orders
WHERE status = 'rejected'
  AND rejected_by = 1
  AND rejected_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH);
```

---

## 🚨 Troubleshooting

### **Command não está rodando automaticamente:**

1. **Verificar cron:**
   ```bash
   crontab -l
   ```

2. **Verificar agendamento:**
   ```bash
   php artisan schedule:list
   ```

3. **Executar manualmente:**
   ```bash
   php artisan schedule:run
   ```

4. **Verificar logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

### **Email não está sendo enviado:**

1. **Verificar SMTP no BD:**
   ```bash
   php artisan tinker
   >>> SmtpSetting::default()->active()->first()
   ```

2. **Verificar template:**
   ```bash
   php artisan tinker
   >>> EmailTemplate::where('slug', 'plan_rejected')->first()
   ```

3. **Verificar logs de email:**
   ```bash
   tail -f storage/logs/laravel.log | grep "Email"
   ```

---

## 🎉 Resumo Final

**✅ Sistema 100% Automático!**

| Recurso | Status |
|---------|--------|
| ✅ Command criado | OK |
| ✅ Agendamento configurado | OK |
| ✅ Email automático | OK |
| ✅ SMTP do BD | OK |
| ✅ Template do BD | OK |
| ✅ Logs detalhados | OK |
| ✅ Modo dry-run | OK |
| ✅ Confirmação manual | OK |
| ✅ Proteção anti-overlap | OK |
| ✅ Notificação de falha | OK |

**Execução:**
- ⏰ **Diariamente às 09:00**
- 📧 **Email automático ao cliente**
- 🤖 **100% automático**
- 📊 **Logs completos**

---

**Data:** 2025-10-09  
**Versão:** 1.0  
**Desenvolvido por:** Cascade AI
