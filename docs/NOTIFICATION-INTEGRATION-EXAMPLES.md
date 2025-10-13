# 🔗 Exemplos de Integração do Sistema de Notificações

## 📋 Como Integrar Notificações no Sistema

---

## 🎯 1. Observers (Recomendado)

### **Criar Observer para Eventos**

```php
// app/Observers/EventObserver.php

namespace App\Observers;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventCreatedNotification;
use App\Notifications\EventStatusChangedNotification;

class EventObserver
{
    /**
     * Quando um evento é criado
     */
    public function created(Event $event)
    {
        // Notificar todos admins e gerentes do tenant
        $users = User::role(['Admin', 'Gestor'])
            ->whereHas('tenants', function($q) use ($event) {
                $q->where('tenants.id', $event->tenant_id);
            })
            ->get();
        
        foreach ($users as $user) {
            $user->notify(new EventCreatedNotification($event, auth()->user()));
        }
        
        \Log::info('📧 Notificações enviadas: Evento criado', [
            'event_id' => $event->id,
            'users_notified' => $users->count(),
        ]);
    }
    
    /**
     * Quando um evento é atualizado
     */
    public function updated(Event $event)
    {
        // Verificar se o status mudou
        if ($event->isDirty('status')) {
            $oldStatus = $event->getOriginal('status');
            $newStatus = $event->status;
            
            // Notificar técnicos + criador
            $usersToNotify = collect();
            
            // Adicionar técnicos
            if ($event->technicians) {
                $usersToNotify = $usersToNotify->merge($event->technicians);
            }
            
            // Adicionar criador
            if ($event->created_by) {
                $creator = User::find($event->created_by);
                if ($creator) {
                    $usersToNotify->push($creator);
                }
            }
            
            // Enviar notificações
            foreach ($usersToNotify->unique('id') as $user) {
                $user->notify(new EventStatusChangedNotification(
                    $event,
                    $oldStatus,
                    $newStatus,
                    auth()->user()
                ));
            }
            
            \Log::info('📧 Notificações enviadas: Status do evento alterado', [
                'event_id' => $event->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'users_notified' => $usersToNotify->count(),
            ]);
        }
    }
}
```

### **Registrar Observer**

```php
// app/Providers/AppServiceProvider.php

use App\Models\Event;
use App\Observers\EventObserver;

public function boot()
{
    Event::observe(EventObserver::class);
}
```

---

## 🎯 2. Controllers

### **Notificar ao Adicionar Técnico**

```php
// app/Livewire/Events/EventManagement.php

public function addTechnician($eventId, $technicianId)
{
    $event = Event::findOrFail($eventId);
    $technician = User::findOrFail($technicianId);
    
    // Adicionar técnico ao evento
    $event->technicians()->attach($technicianId);
    
    // ENVIAR NOTIFICAÇÃO
    $technician->notify(new TechnicianAssignedNotification($event, auth()->user()));
    
    $this->dispatch('success', message: 'Técnico adicionado e notificado!');
}
```

---

## 🎯 3. Scheduled Commands

### **Verificar Estoque Baixo Diariamente**

```php
// app/Console/Commands/CheckLowStockCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Product;
use App\Models\User;
use App\Notifications\LowStockNotification;

class CheckLowStockCommand extends Command
{
    protected $signature = 'notifications:check-low-stock';
    protected $description = 'Verificar produtos com estoque baixo e notificar admins';

    public function handle()
    {
        $this->info('Verificando estoque baixo...');
        
        $tenants = \App\Models\Tenant::where('is_active', true)->get();
        $notificationsSent = 0;
        
        foreach ($tenants as $tenant) {
            // Produtos com estoque baixo
            $lowStockProducts = Stock::where('tenant_id', $tenant->id)
                ->whereColumn('quantity', '<', 'minimum_quantity')
                ->where('minimum_quantity', '>', 0)
                ->get();
            
            if ($lowStockProducts->isNotEmpty()) {
                // Buscar admins do tenant
                $admins = User::role('Admin')
                    ->whereHas('tenants', function($q) use ($tenant) {
                        $q->where('tenants.id', $tenant->id);
                    })
                    ->get();
                
                // Notificar cada admin sobre cada produto
                foreach ($lowStockProducts as $stock) {
                    $product = Product::find($stock->product_id);
                    
                    if ($product) {
                        foreach ($admins as $admin) {
                            $admin->notify(new LowStockNotification(
                                $product,
                                $stock->quantity,
                                $stock->minimum_quantity
                            ));
                            $notificationsSent++;
                        }
                    }
                }
                
                $this->info("Tenant {$tenant->name}: {$lowStockProducts->count()} produtos com estoque baixo");
            }
        }
        
        $this->info("Total de notificações enviadas: {$notificationsSent}");
        return 0;
    }
}
```

### **Agendar no Kernel**

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Verificar estoque baixo todos os dias às 9h
    $schedule->command('notifications:check-low-stock')
             ->dailyAt('09:00');
    
    // Verificar produtos expirando todos os dias às 8h
    $schedule->command('notifications:check-expiring-products')
             ->dailyAt('08:00');
    
    // Lembrar de renovar subscription 15, 7 e 3 dias antes
    $schedule->command('notifications:subscription-reminders')
             ->daily();
}
```

---

## 🎯 4. Livewire Actions

### **Notificar ao Emitir Fatura**

```php
// app/Livewire/Invoicing/InvoiceManagement.php

public function issueInvoice($invoiceId)
{
    $invoice = SalesInvoice::findOrFail($invoiceId);
    
    // Emitir fatura
    $invoice->update(['status' => 'issued']);
    
    // NOTIFICAR ADMINS E GERENTES
    $users = User::role(['Admin', 'Gestor'])
        ->where('tenant_id', $invoice->tenant_id)
        ->get();
    
    foreach ($users as $user) {
        $user->notify(new InvoiceCreatedNotification($invoice));
    }
    
    \Log::info('📧 Notificações enviadas: Fatura emitida', [
        'invoice_id' => $invoice->id,
        'invoice_number' => $invoice->invoice_number,
        'users_notified' => $users->count(),
    ]);
    
    $this->dispatch('success', message: 'Fatura emitida e notificações enviadas!');
}
```

---

## 🎯 5. Model Events

### **Notificar Automaticamente**

```php
// app/Models/Event.php

class Event extends Model
{
    protected static function booted()
    {
        // Quando técnico é adicionado
        static::pivotAttached(function ($model, $relationName, $pivotIds, $pivotIdsAttributes) {
            if ($relationName === 'technicians') {
                foreach ($pivotIds as $technicianId) {
                    $technician = User::find($technicianId);
                    if ($technician) {
                        $technician->notify(new TechnicianAssignedNotification(
                            $model,
                            auth()->user()
                        ));
                    }
                }
            }
        });
    }
}
```

---

## 🎯 6. Jobs (Assíncrono)

### **Enviar Notificações em Massa**

```php
// app/Jobs/SendBulkNotificationsJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBulkNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $users;
    public $notification;

    public function __construct($users, $notification)
    {
        $this->users = $users;
        $this->notification = $notification;
    }

    public function handle()
    {
        foreach ($this->users as $user) {
            $user->notify($this->notification);
        }
    }
}
```

### **Usar o Job**

```php
// Despachar job para enviar notificações
SendBulkNotificationsJob::dispatch(
    $users,
    new EventCreatedNotification($event, auth()->user())
);
```

---

## 🎯 7. Webhooks

### **Notificar via Webhook**

```php
// app/Http/Controllers/WebhookController.php

public function handlePaymentWebhook(Request $request)
{
    $data = $request->all();
    
    // Verificar pagamento aprovado
    if ($data['status'] === 'approved') {
        $subscription = Subscription::find($data['subscription_id']);
        
        if ($subscription) {
            // Ativar subscription
            $subscription->update(['status' => 'active']);
            
            // NOTIFICAR USUÁRIO
            $user = $subscription->tenant->users()->first();
            if ($user) {
                $user->notify(new SubscriptionActivatedNotification($subscription));
            }
        }
    }
    
    return response()->json(['success' => true]);
}
```

---

## 🎯 8. Testes

### **Testar Notificações**

```php
// tests/Feature/NotificationTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Notifications\EventCreatedNotification;
use Illuminate\Support\Facades\Notification;

class NotificationTest extends TestCase
{
    public function test_event_created_sends_notification()
    {
        Notification::fake();
        
        $user = User::factory()->create();
        $event = Event::factory()->create();
        
        // Enviar notificação
        $user->notify(new EventCreatedNotification($event, $user));
        
        // Verificar
        Notification::assertSentTo(
            $user,
            EventCreatedNotification::class
        );
    }
    
    public function test_notification_appears_in_dropdown()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        
        // Enviar notificação
        $user->notify(new EventCreatedNotification($event, $user));
        
        // Verificar banco de dados
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => EventCreatedNotification::class,
        ]);
        
        // Verificar contador
        $this->assertEquals(1, $user->unreadNotifications->count());
    }
}
```

---

## 📊 Exemplos Práticos Completos

### **Fluxo Completo: Criar Evento**

```php
// 1. Criar evento
$event = Event::create([
    'title' => 'Casamento Silva',
    'date' => now()->addDays(30),
    'tenant_id' => auth()->user()->tenant_id,
    'created_by' => auth()->id(),
]);

// 2. Observer dispara automaticamente
// EventObserver::created() é chamado

// 3. Notificações são enviadas para admins e gerentes

// 4. Usuários veem sino com contador
// 5. Clicam e veem a notificação
// 6. Clicam na notificação e vão para a página do evento
```

### **Fluxo Completo: Adicionar Técnico**

```php
// 1. Admin adiciona técnico ao evento
$event->technicians()->attach($technicianId);

// 2. Disparar notificação manualmente
$technician = User::find($technicianId);
$technician->notify(new TechnicianAssignedNotification($event, auth()->user()));

// 3. Técnico recebe notificação
// 4. Sino mostra contador
// 5. Técnico clica e vê que foi adicionado
// 6. Clica e vai para o evento
```

---

## 🔧 Utilitários

### **Helper para Notificar Grupo**

```php
// app/Helpers/NotificationHelper.php

function notifyGroup($role, $tenantId, $notification)
{
    $users = User::role($role)
        ->whereHas('tenants', function($q) use ($tenantId) {
            $q->where('tenants.id', $tenantId);
        })
        ->get();
    
    foreach ($users as $user) {
        $user->notify($notification);
    }
    
    return $users->count();
}

// Uso:
$count = notifyGroup('Admin', $tenantId, new EventCreatedNotification($event, auth()->user()));
```

---

## ✅ Checklist de Implementação

### **Para Cada Tipo de Notificação:**

- [ ] Criar Notification class
- [ ] Definir `via()` (database, mail, etc)
- [ ] Definir `toArray()` com estrutura correta
- [ ] Escolher ícone e cor apropriados
- [ ] Definir URL de destino
- [ ] Integrar no Observer ou Controller
- [ ] Testar envio
- [ ] Testar visualização no sino
- [ ] Testar marcar como lida
- [ ] Verificar logs

---

**Documentação completa:** `docs/NOTIFICATION-SYSTEM-MVP.md`  
**Script de teste:** `scripts/test-notifications-system.php`
