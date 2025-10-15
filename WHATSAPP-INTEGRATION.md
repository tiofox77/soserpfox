# 📱 Integração WhatsApp - SOSERP

## ✅ Sistema Implementado

Sistema completo de notificações WhatsApp integrado ao painel Super Admin.

---

## 🎯 **Funcionalidades**

### **1. Painel de Configuração**
- ✅ Configuração de credenciais Twilio (SID + Auth Token)
- ✅ Configuração do número WhatsApp Business
- ✅ Importação automática de templates do Twilio
- ✅ Gerenciamento de templates ativos
- ✅ Controle de tipos de notificação (ativar/desativar)
- ✅ Teste de conexão com Twilio
- ✅ Envio de mensagens de teste

### **2. Tipos de Notificação Disponíveis**
- Adiantamento Salarial Aprovado
- Adiantamento Salarial Rejeitado
- Férias Aprovadas
- Férias Rejeitadas
- Recibo de Pagamento Disponível
- Funcionário Criado

---

## 📂 **Arquivos Criados**

### **Database**
- `database/migrations/2025_10_14_103942_create_whatsapp_settings_table.php`
- `app/Models/WhatsAppSetting.php`

### **Service**
- `app/Services/WhatsAppService.php`

### **Livewire Component**
- `app/Livewire/SuperAdmin/WhatsAppNotifications.php`
- `resources/views/livewire/super-admin/whats-app-notifications.blade.php`

### **Routes**
- `/superadmin/whatsapp-notifications` (Super Admin apenas)

---

## 🔧 **Configuração**

### **1. Acesse o Painel**
```
URL: /superadmin/whatsapp-notifications
```

### **2. Configure Credenciais**
- **Account SID**: `ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
- **Auth Token**: Seu token do Twilio Console
- **Número WhatsApp**: `+15551234567`
- **Business Account ID**: `seu-business-id`

### **3. Buscar Templates**
1. Clique em **"Buscar Templates"**
2. Sistema listará todos os templates disponíveis do Twilio
3. Clique em **"Adicionar"** nos templates desejados

### **4. Ativar Notificações**
1. Marque o checkbox **"Ativar WhatsApp Notificações"**
2. Selecione quais tipos de notificação enviar
3. Clique em **"Salvar Configurações"**

---

## 💻 **Como Usar no Código**

### **Enviar Mensagem Simples**
```php
use App\Services\WhatsAppService;

$whatsapp = new WhatsAppService();
$messageSid = $whatsapp->sendMessage(
    '+244939729902',
    'Olá! Seu adiantamento foi aprovado.'
);
```

### **Enviar com Template**
```php
use App\Services\WhatsAppService;

$whatsapp = new WhatsAppService();
$messageSid = $whatsapp->sendTemplate(
    '+244939729902',
    'evento_dia_x',
    ['date' => '20/10/2025']
);
```

### **Verificar se Está Ativo**
```php
use App\Models\WhatsAppSetting;

$settings = WhatsAppSetting::getSettings();

if ($settings->isActive()) {
    // WhatsApp está configurado e ativo
}

if ($settings->isNotificationEnabled('salary_advance_approved')) {
    // Tipo de notificação está ativado
}
```

---

## 🔌 **Integração com Sistema**

### **Exemplo: Enviar notificação ao aprovar adiantamento**
```php
// app/Livewire/HR/SalaryAdvances.php

use App\Services\WhatsAppService;
use App\Models\WhatsAppSetting;

public function approveAdvance($advanceId)
{
    $advance = SalaryAdvance::find($advanceId);
    $advance->status = 'approved';
    $advance->save();
    
    // Enviar notificação por email (existente)
    $this->sendEmailNotification($advance);
    
    // Enviar notificação WhatsApp (NOVO)
    $this->sendWhatsAppNotification($advance);
}

protected function sendWhatsAppNotification($advance)
{
    $settings = WhatsAppSetting::getSettings();
    
    // Verificar se WhatsApp está ativo E tipo de notificação está habilitado
    if (!$settings->isNotificationEnabled('salary_advance_approved')) {
        return;
    }
    
    $employee = $advance->employee;
    
    // Verificar se funcionário tem telefone
    if (!$employee->phone) {
        return;
    }
    
    $whatsapp = new WhatsAppService();
    
    // Opção 1: Mensagem simples
    $message = "Olá {$employee->name}!\n\n"
             . "Seu adiantamento salarial foi aprovado.\n"
             . "Valor: " . number_format($advance->amount, 2) . " AOA\n\n"
             . "SOSERP - SoftecAngola";
    
    $whatsapp->sendMessage($employee->phone, $message);
    
    // Opção 2: Com template (se configurado)
    // $whatsapp->sendTemplate(
    //     $employee->phone,
    //     'salary_advance_approved_template',
    //     [
    //         'name' => $employee->name,
    //         'amount' => number_format($advance->amount, 2),
    //         'date' => now()->format('d/m/Y')
    //     ]
    // );
}
```

---

## 📋 **Templates Disponíveis**

### **Templates atuais no Twilio:**
1. **evento_dia_x** - Lembrete de evento
   - Variável: `{{date}}`

2. **notification_order_tracking** - Rastreamento de pedido

3. **notifications_order_update_template** - Atualização de pedido

4. **notifications_appointment_confirmation_template** - Confirmação de agendamento

5. **message_opt_in** - Opt-in de mensagens

6. **notifications_welcome_template** - Boas-vindas

---

## 🧪 **Testes**

### **Scripts de Teste Criados:**
- ✅ `test-whatsapp-business.php` - Teste mensagem simples
- ✅ `test-template-evento.php` - Teste com template específico
- ✅ `enviar-whatsapp-template.php` - Script genérico

### **Via Painel Super Admin:**
1. Acesse `/superadmin/whatsapp-notifications`
2. Seção **"Enviar Mensagem de Teste"**
3. Preencha número e mensagem
4. Clique em **"Enviar Teste"**

---

## 🔐 **Segurança**

- ✅ Auth Token armazenado criptografado no banco
- ✅ Apenas Super Admin pode acessar configurações
- ✅ Logs de envio no Laravel Log
- ✅ Validação de números antes de enviar

---

## 📊 **Monitoramento**

### **Logs do Sistema:**
```bash
tail -f storage/logs/laravel.log | grep WhatsApp
```

### **Logs do Twilio:**
```
https://console.twilio.com/us1/monitor/logs/sms
```

---

## 🚀 **Próximos Passos**

1. ✅ Criar templates específicos para cada tipo de notificação
2. ✅ Integrar com eventos do sistema (aprovações, rejeições)
3. ✅ Adicionar histórico de mensagens enviadas
4. ✅ Criar fila para envio assíncrono
5. ✅ Implementar webhook para receber respostas

---

## 📞 **Suporte**

**Twilio Console:** https://www.twilio.com/console  
**Meta Business Manager:** https://business.facebook.com/  
**Documentação Twilio:** https://www.twilio.com/docs/whatsapp

---

## ✅ **Status Atual**

- ✅ **Database:** Migração executada
- ✅ **Models:** WhatsAppSetting criado
- ✅ **Service:** WhatsAppService funcionando
- ✅ **UI:** Painel Super Admin completo
- ✅ **Routes:** Rota configurada
- ✅ **Testes:** Scripts funcionando
- ✅ **Integração:** Pronto para uso

**🎉 Sistema 100% funcional e pronto para produção!**
