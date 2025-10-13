# 🔔 MVP Sistema Completo de Notificações

## 📋 Visão Geral

Sistema completo de notificações em tempo real com sino no navbar, notificações persistentes no banco de dados e notificações dinâmicas do sistema.

---

## 🎯 Tipos de Notificações Implementadas

### **1. Notificações de Eventos** 🎫

#### **Evento Criado**
- **When:** Novo evento é criado
- **Who:** Todos usuários com permissão de visualizar eventos
- **Icon:** `fa-calendar-plus`
- **Color:** `blue`

```php
use App\Notifications\EventCreatedNotification;

$users->each->notify(new EventCreatedNotification($event, auth()->user()));
```

#### **Técnico Adicionado**
- **When:** Técnico é designado para um evento
- **Who:** O técnico designado
- **Icon:** `fa-user-plus`
- **Color:** `purple`

```php
use App\Notifications\TechnicianAssignedNotification;

$technician->notify(new TechnicianAssignedNotification($event, auth()->user()));
```

#### **Status do Evento Alterado**
- **When:** Status de um evento muda
- **Who:** Técnicos e criador do evento
- **Icon:** `fa-sync-alt`
- **Color:** `cyan`

```php
use App\Notifications\EventStatusChangedNotification;

$users->each->notify(new EventStatusChangedNotification($event, $oldStatus, $newStatus, auth()->user()));
```

---

### **2. Notificações de Inventário** 📦

#### **Produto Expirando**
- **When:** Produto está próximo da validade (7 dias)
- **Type:** Sistema (dinâmica)
- **Icon:** `fa-exclamation-triangle`
- **Color:** `orange`

#### **Estoque Baixo**
- **When:** Quantidade está abaixo do mínimo
- **Type:** Sistema (dinâmica)
- **Icon:** `fa-box-open`
- **Color:** `yellow`

```php
use App\Notifications\LowStockNotification;

$admins->each->notify(new LowStockNotification($product, $currentStock, $minStock));
```

---

### **3. Notificações de Faturação** 💰

#### **Fatura Criada**
- **When:** Nova fatura é emitida
- **Who:** Admins e gerentes
- **Icon:** `fa-file-invoice`
- **Color:** `green`

```php
use App\Notifications\InvoiceCreatedNotification;

$admins->each->notify(new InvoiceCreatedNotification($invoice));
```

---

### **4. Notificações de Sistema** ⚙️

#### **Plano Expirando**
- **When:** Subscription expira em 15 dias ou menos
- **Type:** Sistema (dinâmica)
- **Icon:** `fa-crown`
- **Color:** `red` (3 dias) / `orange` (7 dias) / `yellow` (15 dias)

```php
use App\Notifications\SubscriptionExpiringNotification;

$user->notify(new SubscriptionExpiringNotification($subscription, $daysRemaining));
```

#### **Limite de Empresas Atingido**
- **Type:** Sistema (dinâmica)
- **Icon:** `fa-building`
- **Color:** `blue`

---

## 🏗️ Arquitetura

### **Componente Livewire**
**Arquivo:** `app/Livewire/Notifications.php`

**Funcionalidades:**
- ✅ Busca notificações do banco de dados
- ✅ Gera notificações dinâmicas do sistema
- ✅ Combina ambas em uma lista unificada
- ✅ Marca como lida
- ✅ Marca todas como lidas
- ✅ Deleta notificações
- ✅ Conta não lidas
- ✅ Auto-refresh a cada 60s

### **Notification Classes**
**Local:** `app/Notifications/`

Cada tipo de notificação tem sua própria classe:
- `EventCreatedNotification.php`
- `TechnicianAssignedNotification.php`
- `EventStatusChangedNotification.php`
- `LowStockNotification.php`
- `InvoiceCreatedNotification.php`
- `SubscriptionExpiringNotification.php`

### **View**
**Arquivo:** `resources/views/livewire/notifications.blade.php`

**Features:**
- ✅ Sino animado com contador
- ✅ Dropdown elegante
- ✅ Cores por tipo de notificação
- ✅ Indicador de não lida
- ✅ Botões de ação (marcar lida, excluir)
- ✅ Auto-refresh via polling

---

## 🎨 Interface do Usuário

### **Sino no Navbar**
```
┌────────────────┐
│   🔔           │  <-- Sem notificações
└────────────────┘

┌────────────────┐
│   🔔  (5)      │  <-- Com 5 notificações não lidas
└────────────────┘
```

### **Dropdown**
```
┌──────────────────────────────────────────┐
│ 🔔 Notificações                    (5)   │
│ ⬇ Marcar todas como lidas               │
├──────────────────────────────────────────┤
│ 📅 Novo Evento Criado          há 2min   │
│    Evento 'Casamento Silva' foi...  ●    │
│    [✓ Marcar lida]  [🗑️ Excluir]        │
├──────────────────────────────────────────┤
│ 👤 Você foi Adicionado...      há 5min   │
│    Você foi designado para...       ●    │
├──────────────────────────────────────────┤
│ 📦 Estoque Baixo              há 1hora   │
│    3 produto(s) com estoque...           │
├──────────────────────────────────────────┤
│          Ver Configurações               │
└──────────────────────────────────────────┘
```

---

## 💾 Banco de Dados

### **Tabela: `notifications`**
```sql
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **Estrutura do JSON (data)**
```json
{
    "type": "event_created",
    "title": "Novo Evento Criado",
    "message": "Evento 'Casamento Silva' foi criado por João Admin",
    "event_id": 123,
    "event_title": "Casamento Silva",
    "created_by": "João Admin",
    "icon": "fa-calendar-plus",
    "color": "blue",
    "url": "/events/123"
}
```

---

## 🚀 Como Usar

### **1. Enviar Notificação Simples**
```php
use App\Notifications\EventCreatedNotification;

// Para um usuário
$user->notify(new EventCreatedNotification($event, auth()->user()));

// Para múltiplos usuários
$users = User::whereHas('roles', function($q) {
    $q->where('name', 'Admin');
})->get();

$users->each->notify(new EventCreatedNotification($event, auth()->user()));
```

### **2. Enviar para Grupo**
```php
// Notificar todos técnicos do evento
$event->technicians->each->notify(
    new EventStatusChangedNotification($event, 'pending', 'confirmed', auth()->user())
);
```

### **3. Notificações Condicionais**
```php
// Notificar apenas se estoque estiver crítico
if ($product->stock < $product->min_stock) {
    $admins = User::role('Admin')->get();
    $admins->each->notify(
        new LowStockNotification($product, $product->stock, $product->min_stock)
    );
}
```

---

## 📊 Integrações

### **Event Observers**
Criar notificações automáticas via Observers:

```php
// app/Observers/EventObserver.php

class EventObserver
{
    public function created(Event $event)
    {
        // Notificar admins e gerentes
        $users = User::role(['Admin', 'Gestor'])
            ->where('tenant_id', $event->tenant_id)
            ->get();
        
        $users->each->notify(new EventCreatedNotification($event, auth()->user()));
    }
    
    public function updated(Event $event)
    {
        if ($event->isDirty('status')) {
            $oldStatus = $event->getOriginal('status');
            $newStatus = $event->status;
            
            // Notificar técnicos
            $event->technicians->each->notify(
                new EventStatusChangedNotification($event, $oldStatus, $newStatus, auth()->user())
            );
        }
    }
}
```

### **Scheduled Commands**
Verificar condições diariamente:

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Verificar produtos expirando
    $schedule->command('notifications:check-expiring-products')->daily();
    
    // Verificar estoque baixo
    $schedule->command('notifications:check-low-stock')->daily();
    
    // Lembrar de renovar subscription
    $schedule->command('notifications:subscription-reminders')->daily();
}
```

---

## 🎨 Cores e Ícones

### **Cores Disponíveis**
| Cor | Uso | Classe CSS |
|-----|-----|------------|
| **green** | Sucesso, confirmação | `bg-green-50` |
| **blue** | Informação, eventos | `bg-blue-50` |
| **yellow** | Aviso, atenção | `bg-yellow-50` |
| **orange** | Alerta, urgente | `bg-orange-50` |
| **red** | Erro, crítico | `bg-red-50` |
| **purple** | Técnicos, usuários | `bg-purple-50` |
| **cyan** | Mudanças, atualizações | `bg-cyan-50` |

### **Ícones Font Awesome**
```
fa-calendar-plus       // Evento criado
fa-user-plus           // Técnico adicionado
fa-sync-alt            // Status mudou
fa-box-open            // Estoque baixo
fa-exclamation-triangle // Alerta
fa-file-invoice        // Fatura
fa-crown               // Plano/Subscription
fa-building            // Empresas
fa-check-circle        // Sucesso
fa-times-circle        // Erro
```

---

## 🔧 Configuração

### **1. Adicionar ao Layout**
Já está integrado em `layouts/app.blade.php`:
```blade
<livewire:notifications />
```

### **2. Polling Interval**
Alterar frequência de atualização:
```blade
<div wire:poll.60s>  {{-- 60 segundos --}}
<div wire:poll.30s>  {{-- 30 segundos --}}
<div wire:poll.5s>   {{-- 5 segundos --}}
```

### **3. Máximo de Notificações**
Alterar limite no componente:
```php
->take(20)  // Mostrar 20 notificações
->take(50)  // Mostrar 50 notificações
```

---

## 📝 Exemplos de Código

### **Criar Notification Class**
```php
php artisan make:notification PaymentReceivedNotification
```

### **Estrutura Básica**
```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    public $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function via($notifiable)
    {
        return ['database']; // ou ['mail', 'database']
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'payment_received',
            'title' => 'Pagamento Recebido',
            'message' => "Pagamento de {$this->payment->amount} Kz foi confirmado",
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
            'icon' => 'fa-money-bill-wave',
            'color' => 'green',
            'url' => route('payments.show', $this->payment->id),
        ];
    }
}
```

---

## 🧪 Testes

### **Script de Teste**
```bash
php scripts/test-notifications-system.php
```

### **Testar Manualmente**
```php
// No Tinker
php artisan tinker

// Criar notificação de teste
$user = User::first();
$user->notify(new \App\Notifications\EventCreatedNotification(
    Event::first(),
    User::first()
));

// Ver notificações
$user->notifications;

// Ver não lidas
$user->unreadNotifications;

// Marcar como lida
$user->unreadNotifications->markAsRead();
```

---

## 📊 Estatísticas

### **Performance**
- ⚡ Carregamento: < 100ms
- 🔄 Auto-refresh: 60s
- 💾 Cache: Sim (computed properties)
- 📱 Mobile-friendly: Sim

### **Capacidade**
- Notificações por usuário: Ilimitadas
- Notificações exibidas: 20 (padrão)
- Histórico: Permanente (até deletar)

---

## 🔒 Segurança

✅ **Isolamento por Tenant:** Notificações filtradas por empresa  
✅ **Permissões:** Apenas notificações do usuário  
✅ **Validação:** IDs verificados antes de ações  
✅ **XSS Protection:** Blade escaping automático  

---

## 📚 Documentação Adicional

- [Laravel Notifications](https://laravel.com/docs/notifications)
- [Livewire Events](https://livewire.laravel.com/docs/events)
- [Font Awesome Icons](https://fontawesome.com/icons)

---

## 🎯 Roadmap Futuro

### **V2.0**
- [ ] Notificações push (PWA)
- [ ] Som ao receber notificação
- [ ] Filtros por tipo
- [ ] Busca de notificações
- [ ] Notificações em grupo

### **V2.1**
- [ ] Notificações por email
- [ ] Notificações por SMS
- [ ] Preferências do usuário
- [ ] Mute temporário

---

**Última atualização:** 11/01/2025  
**Versão:** 1.0 MVP
