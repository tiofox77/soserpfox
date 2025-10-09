# 📧 SISTEMA DE EMAILS - DOCUMENTAÇÃO COMPLETA

## 🎯 LÓGICA IMPLEMENTADA

### **Dois tipos de emails:**

1. **📨 EMAILS DO SISTEMA** (usam SMTP do Super Admin)
   - Registro de novos usuários
   - Aprovação/Rejeição de pagamentos
   - Suspensão/Cancelamento de planos
   - Avisos de trial expirando
   - Reset de senha
   - Faturas atrasadas
   - Suspensão/Reativação de contas

2. **📧 EMAILS DO TENANT** (usam SMTP específico do tenant)
   - Emails de marketing
   - Comunicados internos
   - Notificações de eventos
   - Lembretes de tarefas
   - Qualquer email personalizado do tenant

---

## 🔐 EMAILS DO SISTEMA (Auto-Detectados)

### **Templates que são SEMPRE do sistema:**
```php
protected static $systemEmailTemplates = [
    'welcome',                  // Boas-vindas no registro
    'payment_approved',         // Aprovação de pagamento
    'payment_rejected',         // Rejeição de pagamento
    'subscription_suspended',   // Suspensão de plano
    'subscription_cancelled',   // Cancelamento de plano
    'trial_expiring',          // Aviso de trial expirando
    'trial_expired',           // Trial expirado
    'password_reset',          // Reset de senha
    'invoice_overdue',         // Fatura atrasada
    'account_suspended',       // Conta suspensa
    'account_reactivated',     // Conta reativada
];
```

### **Como funciona:**
- ✅ Detecção automática pelo slug do template
- ✅ Sempre usa SMTP padrão (Super Admin)
- ✅ Mesmo que passe `tenant_id`, será ignorado
- ✅ Garante que emails importantes sempre saem

---

## 💡 COMO USAR

### **1. Email do Sistema (Detecção Automática)**
```php
// EXEMPLO: Enviar boas-vindas
Mail::to($user->email)
    ->send(new TemplateMail('welcome', [
        'user_name' => $user->name,
        'tenant_name' => $tenant->name,
        'app_name' => config('app.name'),
        'login_url' => route('login'),
    ]));

// ✅ Automaticamente usa SMTP do Super Admin
// ✅ Não precisa passar tenant_id
// ✅ Não precisa passar isSystemEmail
```

### **2. Email do Sistema (Forçado Manualmente)**
```php
// Se você criar um template customizado que deve ser do sistema
Mail::to($user->email)
    ->send(new TemplateMail(
        'meu_template_custom',
        $data,
        null,           // tenant_id = null
        true            // isSystemEmail = true (FORÇA usar Super Admin)
    ));
```

### **3. Email do Tenant (Específico)**
```php
// Email personalizado do tenant (ex: marketing, eventos)
Mail::to($client->email)
    ->send(new TemplateMail(
        'marketing_campaign',
        $data,
        $tenant->id     // USA SMTP do tenant (se tiver)
    ));

// ✅ Usa SMTP do tenant se configurado
// ✅ Se tenant não tiver SMTP, usa padrão
```

---

## 📋 EXEMPLOS PRÁTICOS

### **Exemplo 1: Aprovação de Pagamento**
```php
// Em OrderController ou PaymentController
public function approvePayment($orderId)
{
    $order = Order::findOrFail($orderId);
    $order->update(['status' => 'approved']);
    
    // Enviar email (EMAIL DO SISTEMA)
    try {
        Mail::to($order->user->email)
            ->send(new TemplateMail('payment_approved', [
                'user_name' => $order->user->name,
                'order_id' => $order->id,
                'amount' => number_format($order->total, 2, ',', '.'),
                'payment_method' => $order->payment_method,
                'app_name' => config('app.name'),
            ]));
            
        Log::info('Email de aprovação enviado', [
            'order_id' => $orderId,
            'user' => $order->user->email,
        ]);
        
    } catch (\Exception $e) {
        Log::error('Erro ao enviar email de aprovação', [
            'error' => $e->getMessage(),
            'order_id' => $orderId,
        ]);
    }
    
    return redirect()->back()->with('success', 'Pagamento aprovado!');
}
```

### **Exemplo 2: Suspensão de Plano**
```php
// Em SubscriptionController
public function suspend($subscriptionId)
{
    $subscription = Subscription::findOrFail($subscriptionId);
    $subscription->update(['status' => 'suspended']);
    
    // Enviar email (EMAIL DO SISTEMA)
    Mail::to($subscription->tenant->email)
        ->send(new TemplateMail('subscription_suspended', [
            'tenant_name' => $subscription->tenant->name,
            'plan_name' => $subscription->plan->name,
            'reason' => 'Pagamento não identificado',
            'support_email' => config('mail.from.address'),
            'app_name' => config('app.name'),
        ]));
}
```

### **Exemplo 3: Trial Expirando**
```php
// Em Console/Commands/CheckTrialExpiring.php
public function handle()
{
    $expiringTrials = Subscription::where('status', 'trial')
        ->whereDate('trial_ends_at', '<=', now()->addDays(3))
        ->get();
    
    foreach ($expiringTrials as $subscription) {
        Mail::to($subscription->tenant->email)
            ->send(new TemplateMail('trial_expiring', [
                'tenant_name' => $subscription->tenant->name,
                'days_left' => now()->diffInDays($subscription->trial_ends_at),
                'upgrade_url' => route('subscriptions.upgrade'),
                'app_name' => config('app.name'),
            ]));
    }
}
```

### **Exemplo 4: Email Personalizado do Tenant**
```php
// Em EventController (email do TENANT)
public function sendEventReminder($eventId)
{
    $event = Event::findOrFail($eventId);
    $tenant = auth()->user()->tenant;
    
    // Email PERSONALIZADO do tenant
    Mail::to($event->client->email)
        ->send(new TemplateMail(
            'event_reminder',           // Template customizado
            [
                'client_name' => $event->client->name,
                'event_name' => $event->name,
                'event_date' => $event->date->format('d/m/Y'),
                'event_time' => $event->time,
                'location' => $event->location,
            ],
            $tenant->id                 // USA SMTP do tenant
        ));
}
```

---

## 🔍 LOGS DETALHADOS

O sistema agora loga claramente qual SMTP está sendo usado:

### **Email do Sistema:**
```
🔐 EMAIL DO SISTEMA - Usando SMTP do Super Admin
template: welcome
smtp_host: smtp.gmail.com
reason: Email do sistema (registro, aprovações, avisos)
```

### **Email do Tenant:**
```
📧 EMAIL DO TENANT - Usando SMTP específico
template: event_reminder
tenant_id: 10
smtp_host: smtp.tenant-custom.com
```

---

## ✅ VANTAGENS DA IMPLEMENTAÇÃO

1. **🔐 Segurança**
   - Emails críticos sempre saem (Super Admin)
   - Tenants não podem quebrar emails do sistema

2. **🎯 Automático**
   - Detecção automática por template
   - Não precisa lembrar de passar parâmetros

3. **💪 Flexível**
   - Pode forçar manualmente se necessário
   - Tenants podem ter SMTP personalizado

4. **📊 Rastreável**
   - Logs claros de qual SMTP foi usado
   - Fácil diagnosticar problemas

5. **🚀 Escalável**
   - Adicionar novos templates é simples
   - Lista centralizada de emails do sistema

---

## 📝 CRIAR NOVOS TEMPLATES DE EMAIL

### **1. Criar registro no banco:**
```sql
INSERT INTO email_templates (slug, name, subject, body_html, variables, is_active) 
VALUES (
    'payment_approved',
    'Pagamento Aprovado',
    'Seu pagamento foi aprovado, {user_name}!',
    '<h2>Pagamento Aprovado</h2><p>Olá {user_name},</p>...',
    '["user_name", "order_id", "amount"]',
    1
);
```

### **2. Se for email do sistema, adicionar na lista:**
```php
// Em app/Mail/TemplateMail.php
protected static $systemEmailTemplates = [
    'welcome',
    'payment_approved',  // ← Adicionar aqui
    // ...
];
```

### **3. Usar em qualquer lugar:**
```php
Mail::to($email)->send(new TemplateMail('payment_approved', $data));
```

---

## 🎯 RESUMO

| Tipo | SMTP Usado | Quando Usar | Exemplo |
|------|-----------|-------------|---------|
| **Sistema** | Super Admin | Registro, aprovações, avisos críticos | `welcome`, `payment_approved` |
| **Tenant** | Específico do Tenant | Marketing, eventos, comunicados | `marketing_campaign`, `event_reminder` |

---

## ⚠️ IMPORTANTE

- ✅ **SEMPRE** crie templates de emails críticos na lista `$systemEmailTemplates`
- ✅ **NUNCA** envie emails de registro/aprovação usando SMTP do tenant
- ✅ **SEMPRE** trate exceções ao enviar emails (try/catch)
- ✅ **SEMPRE** logue o envio de emails para diagnóstico

---

**Data:** 09/10/2025  
**Status:** 🟢 IMPLEMENTADO E TESTADO
