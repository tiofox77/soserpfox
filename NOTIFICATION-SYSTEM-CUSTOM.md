# 🔔 SISTEMA DE NOTIFICAÇÕES CUSTOMIZÁVEIS POR ÁREA

## 📋 VISÃO GERAL

Sistema completo de notificações que permite **criar templates customizados por área/módulo** com mapeamento de variáveis do banco de dados.

---

## ✅ ESTRUTURA CRIADA

### **1. Database Migration**
`database/migrations/2025_10_14_123639_create_notification_templates_table.php`

**Tabela:** `notification_templates`

**Campos Principais:**
- **Identificação:**
  - `name` - Nome do template (ex: "Lembrete de Evento")
  - `slug` - Identificador único (ex: "event_reminder")
  - `module` - Área/Módulo (hr, events, finance, etc)
  - `description` - Descrição do template

- **Canais:**
  - `email_enabled` - Habilitar Email
  - `sms_enabled` - Habilitar SMS
  - `whatsapp_enabled` - Habilitar WhatsApp

- **Templates:**
  - `email_template_id` - ID do template de email
  - `sms_template_sid` - SID do template SMS
  - `whatsapp_template_sid` - SID do template WhatsApp

- **Timing:**
  - `trigger_event` - Quando enviar (created, updated, date_approaching)
  - `notify_before_minutes` - Minutos antes do evento
  - `notify_at_time` - Hora específica (HH:MM)

- **Mapeamento:**
  - `variable_mappings` - JSON com mapeamento de variáveis

### **2. Model**
`app/Models/NotificationTemplate.php`

**Métodos Principais:**
- `getAvailableModules()` - Lista de módulos disponíveis
- `getAvailableTriggers()` - Eventos de disparo
- `getVariables()` - Extrair variáveis do template
- `mapVariables($model)` - Mapear campos da BD para variáveis
- `meetsConditions($model)` - Verificar condições

### **3. Livewire Component**
`app/Livewire/Settings/ManageNotificationTemplates.php`

Interface para gerenciar templates de notificação

---

## 🎯 EXEMPLO DE USO: EVENTOS

### **Cenário: Lembrete de Evento**

**1. Configurar Template:**
```php
NotificationTemplate::create([
    'tenant_id' => 1,
    'name' => 'Lembrete de Evento',
    'slug' => 'event_reminder',
    'module' => 'events',
    'description' => 'Envia lembrete 24h antes do evento',
    
    // Canais
    'whatsapp_enabled' => true,
    'sms_enabled' => true,
    
    // Templates
    'whatsapp_template_sid' => 'HX123...', // Template do Twilio
    'sms_template_sid' => 'HX123...',
    
    // Timing
    'trigger_event' => 'date_approaching',
    'notify_before_minutes' => 1440, // 24 horas = 1440 minutos
    
    // Mapeamento de Variáveis
    'variable_mappings' => [
        'event' => 'name',              // {{event}} = events.name
        'date' => 'start_date',         // {{date}} = events.start_date
        'var' => 'location',            // {{var}} = events.location
        'number' => 'id'                // {{number}} = events.id
    ],
    
    'is_active' => true
]);
```

**2. Template WhatsApp:**
```
Lembrete: Evento {{event}} amanhã dia {{date}} em {{var}}. ID: {{number}}
```

**3. Quando o Cron Rodar (24h antes):**
```php
$event = Event::find(1);
// {
//     id: 1,
//     name: "Conferência Tech",
//     start_date: "2025-10-15",
//     location: "Auditório Principal"
// }

$template = NotificationTemplate::where('slug', 'event_reminder')->first();

// Mapear variáveis
$variables = $template->mapVariables($event);
// {
//     'event': 'Conferência Tech',
//     'date': '2025-10-15',
//     'var': 'Auditório Principal',
//     'number': '1'
// }

// Enviar WhatsApp
$whatsapp->sendTemplate($phone, 'event_reminder', $variables, $template->whatsapp_template_sid);
```

**4. Mensagem Enviada:**
```
Lembrete: Evento Conferência Tech amanhã dia 2025-10-15 em Auditório Principal. ID: 1
```

---

## 📦 MÓDULOS DISPONÍVEIS

```php
[
    'hr' => 'Recursos Humanos',
    'events' => 'Eventos',
    'calendar' => 'Calendário',
    'finance' => 'Financeiro',
    'crm' => 'CRM',
    'projects' => 'Projetos',
    'tasks' => 'Tarefas',
]
```

---

## ⚡ TRIGGERS DISPONÍVEIS

```php
[
    'created' => 'Quando criado',
    'updated' => 'Quando atualizado',
    'date_approaching' => 'Data se aproximando',
    'status_changed' => 'Status mudou',
    'custom' => 'Personalizado',
]
```

---

## 🗂️ MAPEAMENTO DE VARIÁVEIS

### **Notação de Ponto (Dot Notation)**

```json
{
  "event": "name",                    // Campo direto
  "date": "start_date",               // Campo direto
  "var": "location",                  // Campo direto
  "user": "user.name",                // Relacionamento
  "email": "user.email",              // Relacionamento aninhado
  "department": "user.department.name" // Múltiplos níveis
}
```

### **Exemplos por Módulo:**

**HR - Funcionário Criado:**
```json
{
  "name": "first_name",
  "position": "job_title",
  "date": "start_date",
  "department": "department.name"
}
```

**Eventos - Lembrete:**
```json
{
  "event": "name",
  "date": "start_date",
  "location": "venue",
  "organizer": "organizer.name"
}
```

**Financeiro - Fatura Vencendo:**
```json
{
  "invoice_number": "number",
  "amount": "total",
  "due_date": "due_date",
  "client": "client.name"
}
```

---

## 🔄 CONDIÇÕES

Enviar apenas se certas condições forem verdadeiras:

```json
[
  {
    "field": "status",
    "operator": "=",
    "value": "confirmed"
  },
  {
    "field": "attendees_count",
    "operator": ">",
    "value": 10
  }
]
```

**Operadores:**
- `=` - Igual
- `!=` - Diferente
- `>` - Maior que
- `<` - Menor que

---

## 📅 TIMING EXAMPLES

### **1. Imediatamente ao Criar**
```php
'trigger_event' => 'created',
'notify_before_minutes' => null
```

### **2. 24 Horas Antes**
```php
'trigger_event' => 'date_approaching',
'notify_before_minutes' => 1440  // 24 * 60
```

### **3. 1 Semana Antes**
```php
'trigger_event' => 'date_approaching',
'notify_before_minutes' => 10080  // 7 * 24 * 60
```

### **4. No Horário Específico**
```php
'trigger_event' => 'custom',
'notify_at_time' => '09:00'
```

---

## 🎨 INTERFACE (A IMPLEMENTAR)

### **Dashboard de Templates**

```
┌─────────────────────────────────────────────────────┐
│ 🔔 Templates de Notificação                        │
├─────────────────────────────────────────────────────┤
│ [+ Novo Template]              [Filtrar: Todos ▼]  │
│                                                     │
│ ┌─────────────────────────────────────────────────┐│
│ │ 📅 Lembrete de Evento                  [events] ││
│ │ WhatsApp ✅ | SMS ✅ | Email ❌                  ││
│ │ Trigger: 24h antes do evento                    ││
│ │ Variáveis: event, date, location, number        ││
│ │                          [Editar] [Desativar]   ││
│ └─────────────────────────────────────────────────┘│
│                                                     │
│ ┌─────────────────────────────────────────────────┐│
│ │ 👤 Novo Funcionário                         [hr] ││
│ │ Email ✅ | WhatsApp ✅ | SMS ❌                  ││
│ │ Trigger: Ao criar                               ││
│ │ Variáveis: name, position, start_date           ││
│ │                          [Editar] [Desativar]   ││
│ └─────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────┘
```

### **Formulário de Criação/Edição**

```
┌────────────────────────────────────────────┐
│ Criar Template de Notificação             │
├────────────────────────────────────────────┤
│                                            │
│ Informações Básicas                        │
│ ├─ Nome: [________________]                │
│ ├─ Módulo: [Eventos ▼]                     │
│ └─ Descrição: [__________]                 │
│                                            │
│ Canais de Notificação                      │
│ ├─ ☑ Email                                 │
│ ├─ ☑ SMS                                   │
│ └─ ☑ WhatsApp                              │
│                                            │
│ Templates                                  │
│ ├─ WhatsApp: [Selecione template ▼]       │
│ └─ SMS: [Selecione template ▼]             │
│                                            │
│ Timing                                     │
│ ├─ Quando: [Data se aproximando ▼]        │
│ └─ Avisar: [24] horas antes                │
│                                            │
│ Mapeamento de Variáveis                    │
│ ┌──────────────────────────────────────┐  │
│ │ {{event}}  →  [name ▼]               │  │
│ │ {{date}}   →  [start_date ▼]         │  │
│ │ {{var}}    →  [location ▼]           │  │
│ │ {{number}} →  [id ▼]                 │  │
│ └──────────────────────────────────────┘  │
│                                            │
│ Condições (Opcional)                       │
│ ├─ Campo: [status ▼] = [confirmed]        │
│ └─ [+ Adicionar condição]                  │
│                                            │
│              [Cancelar] [Salvar]           │
└────────────────────────────────────────────┘
```

---

## 🚀 PRÓXIMOS PASSOS

### **1. Executar Migration**
```bash
php artisan migrate
```

### **2. Criar Interface Livewire**
Implementar o componente `ManageNotificationTemplates`

### **3. Criar Cron Job**
```php
// app/Console/Commands/SendScheduledNotifications.php
// Verificar templates com trigger "date_approaching"
// Enviar notificações no momento certo
```

### **4. Integrar com Módulos**
Adicionar listeners nos eventos dos módulos:
```php
// events/created
// events/updated
// employees/created
// etc
```

### **5. Criar Rotas**
```php
Route::get('/notifications/templates', ManageNotificationTemplates::class);
```

---

## 💡 BENEFÍCIOS

✅ **Flexibilidade Total** - Criar notificações para qualquer área  
✅ **Sem Código** - Tudo via interface gráfica  
✅ **Mapeamento Dinâmico** - Vincular campos da BD às variáveis  
✅ **Multi-Canal** - Email, SMS, WhatsApp  
✅ **Timing Preciso** - Enviar no momento exato  
✅ **Condições** - Enviar apenas quando necessário  
✅ **Audit Trail** - Saber o que foi enviado e quando  

---

## 📊 EXEMPLO COMPLETO: CALENDÁRIO DE EVENTOS

```php
// 1. Criar Template
$template = NotificationTemplate::create([
    'tenant_id' => auth()->user()->activeTenant()->id,
    'name' => 'Lembrete de Reunião',
    'slug' => 'meeting_reminder',
    'module' => 'calendar',
    'description' => 'Lembrete enviado 1 hora antes da reunião',
    
    'whatsapp_enabled' => true,
    'whatsapp_template_sid' => 'HX123abc...',
    
    'trigger_event' => 'date_approaching',
    'notify_before_minutes' => 60, // 1 hora antes
    
    'variable_mappings' => [
        'event' => 'title',
        'date' => 'start_datetime',
        'location' => 'room',
        'attendees' => 'attendees_count'
    ],
    
    'conditions' => [
        ['field' => 'type', 'operator' => '=', 'value' => 'meeting'],
        ['field' => 'status', 'operator' => '=', 'value' => 'confirmed']
    ],
    
    'is_active' => true
]);

// 2. Cron Executa (1 hora antes da reunião)
// O sistema busca reuniões que começam em 1 hora

// 3. Para cada reunião encontrada:
$meeting = CalendarEvent::find(1);
$variables = $template->mapVariables($meeting);

// 4. Enviar para cada participante
foreach ($meeting->attendees as $attendee) {
    $whatsapp->sendTemplate(
        $attendee->phone,
        $template->name,
        $variables,
        $template->whatsapp_template_sid
    );
}
```

---

**🎉 Sistema completo de notificações customizáveis criado!**

**Status:** Estrutura criada ✅ | Interface pendente ⏳ | Cron pendente ⏳
