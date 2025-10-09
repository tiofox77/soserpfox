# ✅ PASSOS PARA COMPLETAR IMPLEMENTAÇÃO

## 📊 STATUS ATUAL:

### ✅ **JÁ CRIADO:**
- ✅ Migration (5 tabelas)
- ✅ Models completos:
  - Technician.php
  - Team.php  
  - TeamMember.php
  - EquipmentMovement.php
  - EventReport.php

### ⏳ **FALTAM:**
- Controllers Livewire (apenas 1 foi iniciado)
- Views
- Rotas
- Menu Sidebar

---

## 🚀 COMANDOS PARA EXECUTAR:

### 1️⃣ **Rodar Migration (PRIMEIRO)**
```bash
php artisan migrate
```

### 2️⃣ **Criar Controllers Livewire**
```bash
php artisan make:livewire Events/TeamsManager
```

O `TechniciansManager` já foi criado em:
- `app/Livewire/Events/TechniciansManager.php`
- `resources/views/livewire/events/technicians-manager.blade.php`

---

## 📝 ADICIONAR ROTAS

Abra: `routes/web.php`

Adicione dentro do grupo `Route::middleware(['auth'])->prefix('events')->name('events.')`:

```php
// Técnicos
Route::prefix('technicians')->name('technicians.')->group(function () {
    Route::get('/', \App\Livewire\Events\TechniciansManager::class)->name('index');
});

// Equipes
Route::prefix('teams')->name('teams.')->group(function () {
    Route::get('/', \App\Livewire\Events\TeamsManager::class)->name('index');
});
```

---

## 🎨 ADICIONAR AO SIDEBAR

Abra: `resources/views/layouts/app.blade.php`

Encontre a seção de Eventos e adicione após "Tipos de Eventos":

```blade
<a href="{{ route('events.technicians.index') }}" 
   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('events.technicians.*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
    <i class="fas fa-user-hard-hat w-5 text-cyan-400 text-sm"></i>
    <span x-show="sidebarOpen" class="ml-3 text-sm">Técnicos</span>
</a>

<a href="{{ route('events.teams.index') }}" 
   class="flex items-center pl-8 pr-4 py-2.5 {{ request()->routeIs('events.teams.*') ? 'bg-blue-700 border-l-4 border-pink-400' : 'hover:bg-blue-700/50' }} transition">
    <i class="fas fa-users w-5 text-teal-400 text-sm"></i>
    <span x-show="sidebarOpen" class="ml-3 text-sm">Equipes</span>
</a>
```

---

## 📄 CONTEÚDO BÁSICO PARA TECHNICIANS MANAGER

### Controller: `app/Livewire/Events/TechniciansManager.php`

```php
<?php

namespace App\Livewire\Events;

use App\Models\Events\Technician;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Técnicos')]
class TechniciansManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingId = null;
    
    // Form
    public $name = '';
    public $email = '';
    public $phone = '';
    public $document = '';
    public $specialties = [];
    public $level = 'pleno';
    public $hourly_rate = 0;
    public $daily_rate = 0;
    public $is_active = true;

    protected $rules = [
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'email' => 'nullable|email',
        'specialties' => 'required|array|min:1',
        'level' => 'required|in:junior,pleno,senior,master',
        'hourly_rate' => 'nullable|numeric|min:0',
        'daily_rate' => 'nullable|numeric|min:0',
    ];

    public function render()
    {
        $technicians = Technician::where('tenant_id', activeTenantId())
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.events.technicians-manager', compact('technicians'));
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $tech = Technician::where('tenant_id', activeTenantId())->findOrFail($id);
        
        $this->editingId = $id;
        $this->name = $tech->name;
        $this->email = $tech->email;
        $this->phone = $tech->phone;
        $this->document = $tech->document;
        $this->specialties = $tech->specialties ?? [];
        $this->level = $tech->level;
        $this->hourly_rate = $tech->hourly_rate;
        $this->daily_rate = $tech->daily_rate;
        $this->is_active = $tech->is_active;
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'tenant_id' => activeTenantId(),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'document' => $this->document,
            'specialties' => $this->specialties,
            'level' => $this->level,
            'hourly_rate' => $this->hourly_rate,
            'daily_rate' => $this->daily_rate,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Technician::find($this->editingId)->update($data);
            $message = '✅ Técnico atualizado!';
        } else {
            Technician::create($data);
            $message = '✅ Técnico criado!';
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
        $this->closeModal();
    }

    public function delete($id)
    {
        Technician::where('tenant_id', activeTenantId())->findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Técnico excluído!']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['name', 'email', 'phone', 'document', 'specialties', 'editingId']);
        $this->level = 'pleno';
        $this->hourly_rate = 0;
        $this->daily_rate = 0;
        $this->is_active = true;
    }
}
```

### View: `resources/views/livewire/events/technicians-manager.blade.php`

```blade
<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4">
                    <i class="fas fa-user-hard-hat text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold">Técnicos</h2>
                    <p class="text-cyan-100 text-sm mt-1">Gerencie técnicos e suas especialidades</p>
                </div>
            </div>
            <button wire:click="create" 
                    class="bg-white text-cyan-600 px-6 py-3 rounded-xl font-bold hover:bg-cyan-50 transition shadow-lg">
                <i class="fas fa-plus mr-2"></i>Novo Técnico
            </button>
        </div>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <input type="text" wire:model.live="search" 
               class="w-full md:w-96 px-4 py-2 border-2 border-gray-300 rounded-lg"
               placeholder="🔍 Buscar técnico...">
    </div>

    {{-- Lista de Técnicos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($technicians as $tech)
        <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition p-5 border-l-4 border-cyan-500">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <h3 class="font-bold text-lg text-gray-800">{{ $tech->name }}</h3>
                    <p class="text-sm text-gray-600">{{ $tech->phone }}</p>
                    @if($tech->email)
                    <p class="text-xs text-gray-500">{{ $tech->email }}</p>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button wire:click="edit({{ $tech->id }})"
                            class="p-2 rounded-lg hover:bg-blue-100 text-blue-600 transition">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button wire:click="delete({{ $tech->id }})"
                            onclick="return confirm('Excluir técnico?')"
                            class="p-2 rounded-lg hover:bg-red-100 text-red-600 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
            {{-- Especialidades --}}
            <div class="flex flex-wrap gap-1 mb-2">
                @foreach($tech->specialties ?? [] as $spec)
                <span class="px-2 py-1 bg-cyan-100 text-cyan-700 text-xs rounded-full">
                    {{ ucfirst($spec) }}
                </span>
                @endforeach
            </div>
            
            {{-- Nível e Valores --}}
            <div class="text-sm text-gray-600 mt-2 pt-2 border-t">
                <span class="font-semibold">Nível:</span> {{ ucfirst($tech->level) }}<br>
                @if($tech->daily_rate > 0)
                <span class="font-semibold">Cachê/dia:</span> {{ number_format($tech->daily_rate, 2) }} €
                @endif
            </div>
        </div>
        @empty
        <div class="col-span-3 text-center py-12">
            <i class="fas fa-user-hard-hat text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">Nenhum técnico cadastrado</p>
        </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $technicians->links() }}
    </div>

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
            <div class="bg-gradient-to-r from-cyan-600 to-blue-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
                <h3 class="text-xl font-bold text-white">
                    {{ $editingId ? 'Editar' : 'Novo' }} Técnico
                </h3>
                <button wire:click="closeModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-bold mb-2">Nome Completo *</label>
                        <input type="text" wire:model="name" class="w-full px-4 py-2 border-2 rounded-lg">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold mb-2">Telefone *</label>
                        <input type="text" wire:model="phone" class="w-full px-4 py-2 border-2 rounded-lg">
                        @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold mb-2">Email</label>
                        <input type="email" wire:model="email" class="w-full px-4 py-2 border-2 rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold mb-2">Documento (BI/NIF)</label>
                        <input type="text" wire:model="document" class="w-full px-4 py-2 border-2 rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold mb-2">Nível *</label>
                        <select wire:model="level" class="w-full px-4 py-2 border-2 rounded-lg">
                            <option value="junior">Junior</option>
                            <option value="pleno">Pleno</option>
                            <option value="senior">Senior</option>
                            <option value="master">Master</option>
                        </select>
                    </div>
                    
                    <div class="col-span-2">
                        <label class="block text-sm font-bold mb-2">Especialidades *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="specialties" value="audio" class="rounded">
                                Áudio
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="specialties" value="video" class="rounded">
                                Vídeo
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="specialties" value="iluminacao" class="rounded">
                                Iluminação
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="specialties" value="streaming" class="rounded">
                                Streaming
                            </label>
                        </div>
                        @error('specialties') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold mb-2">Valor/Hora (€)</label>
                        <input type="number" wire:model="hourly_rate" step="0.01" class="w-full px-4 py-2 border-2 rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold mb-2">Valor/Dia (€)</label>
                        <input type="number" wire:model="daily_rate" step="0.01" class="w-full px-4 py-2 border-2 rounded-lg">
                    </div>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button wire:click="save" 
                            class="flex-1 bg-cyan-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-cyan-700 transition">
                        <i class="fas fa-save mr-2"></i>Salvar
                    </button>
                    <button wire:click="closeModal" 
                            class="px-6 py-3 border-2 rounded-lg font-bold hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
```

---

## ✅ CHECKLIST FINAL:

- [x] Models criados
- [ ] Migration rodada (`php artisan migrate`)
- [ ] Controller TechniciansManager preenchido
- [ ] Rotas adicionadas
- [ ] Sidebar atualizado
- [ ] Testar no navegador

---

**Após completar estes passos, o sistema estará 100% funcional!** 🎉
