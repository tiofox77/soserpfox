<?php

namespace App\Livewire\Hotel;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Hotel\RoomType;
use Illuminate\Support\Facades\Storage;

class RoomTypeManagement extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $showModal = false;
    public $showViewModal = false;
    public $viewingRoomType = null;
    public $editingId = null;
    public $confirmingDelete = false;
    public $deleteId = null;
    public $viewMode = 'grid'; // grid ou list

    // Form fields
    public $name = '';
    public $code = '';
    public $description = '';
    public $base_price = 0;
    public $weekend_price = null;
    public $capacity = 2;
    public $extra_bed_capacity = 0;
    public $extra_bed_price = 0;
    public $amenities = [];
    public $is_active = true;

    // Images
    public $featured_image;
    public $gallery = [];
    public $existing_featured_image = null;
    public $existing_gallery = [];

    public $availableAmenities = [
        'wifi' => 'WiFi Gratuito',
        'ac' => 'Ar Condicionado',
        'tv' => 'TV Cabo',
        'minibar' => 'Minibar',
        'safe' => 'Cofre',
        'balcony' => 'Varanda',
        'sea_view' => 'Vista Mar',
        'bathtub' => 'Banheira',
        'shower' => 'Chuveiro',
        'hairdryer' => 'Secador',
        'iron' => 'Ferro de Engomar',
        'desk' => 'Secretária',
        'phone' => 'Telefone',
        'room_service' => 'Serviço de Quarto',
        'breakfast' => 'Pequeno-almoço Incluído',
    ];

    protected $rules = [
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:20',
        'description' => 'nullable|string',
        'base_price' => 'required|numeric|min:0',
        'weekend_price' => 'nullable|numeric|min:0',
        'capacity' => 'required|integer|min:1|max:20',
        'extra_bed_capacity' => 'required|integer|min:0|max:5',
        'extra_bed_price' => 'required|numeric|min:0',
        'amenities' => 'array',
        'is_active' => 'boolean',
        'featured_image' => 'nullable|image|max:2048',
        'gallery.*' => 'nullable|image|max:2048',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedName($value)
    {
        // Gerar código automaticamente apenas se não estiver editando ou se o código estiver vazio
        if (!$this->editingId || empty($this->code)) {
            $this->code = $this->generateCode($value);
        }
    }

    private function generateCode($name)
    {
        if (empty($name)) {
            return '';
        }

        // Gera código: primeiras 3 letras maiúsculas + número sequencial
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
        
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }

        // Encontrar próximo número disponível
        $lastCode = RoomType::forTenant()
            ->where('code', 'like', $prefix . '-%')
            ->orderByRaw("CAST(SUBSTRING(code, 5) AS UNSIGNED) DESC")
            ->first();

        if ($lastCode && preg_match('/-(\d+)$/', $lastCode->code, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public function openModal()
    {
        $this->reset(['editingId', 'name', 'code', 'description', 'base_price', 'weekend_price', 
                      'capacity', 'extra_bed_capacity', 'extra_bed_price', 'amenities', 'is_active',
                      'featured_image', 'gallery', 'existing_featured_image', 'existing_gallery']);
        $this->is_active = true;
        $this->capacity = 2;
        $this->showModal = true;
    }

    public function view($id)
    {
        $this->viewingRoomType = RoomType::forTenant()->withCount('rooms')->findOrFail($id);
        $this->showViewModal = true;
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    public function edit($id)
    {
        $roomType = RoomType::forTenant()->findOrFail($id);
        
        $this->editingId = $id;
        $this->name = $roomType->name;
        $this->code = $roomType->code;
        $this->description = $roomType->description;
        $this->base_price = $roomType->base_price;
        $this->weekend_price = $roomType->weekend_price;
        $this->capacity = $roomType->capacity;
        $this->extra_bed_capacity = $roomType->extra_bed_capacity;
        $this->extra_bed_price = $roomType->extra_bed_price;
        $this->amenities = $roomType->amenities ?? [];
        $this->is_active = $roomType->is_active;
        
        // Imagens existentes
        $this->existing_featured_image = $roomType->featured_image;
        $this->existing_gallery = $roomType->gallery ?? [];
        $this->featured_image = null;
        $this->gallery = [];
        
        $this->showModal = true;
    }

    public function removeFeaturedImage()
    {
        $this->featured_image = null;
        $this->existing_featured_image = null;
    }

    public function removeGalleryImage($index)
    {
        if (isset($this->existing_gallery[$index])) {
            unset($this->existing_gallery[$index]);
            $this->existing_gallery = array_values($this->existing_gallery);
        }
    }

    public function removeNewGalleryImage($index)
    {
        if (isset($this->gallery[$index])) {
            unset($this->gallery[$index]);
            $this->gallery = array_values($this->gallery);
        }
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'weekend_price' => $this->weekend_price,
            'capacity' => $this->capacity,
            'extra_bed_capacity' => $this->extra_bed_capacity,
            'extra_bed_price' => $this->extra_bed_price,
            'amenities' => $this->amenities,
            'is_active' => $this->is_active,
        ];

        // Caminho base com tenant
        $tenantId = activeTenantId();
        $basePath = "tenants/{$tenantId}/hotel/room-types";

        // Processar imagem de destaque
        if ($this->featured_image) {
            $data['featured_image'] = $this->featured_image->store($basePath, 'public');
        } elseif ($this->existing_featured_image) {
            $data['featured_image'] = $this->existing_featured_image;
        } else {
            $data['featured_image'] = null;
        }

        // Processar galeria
        $galleryImages = $this->existing_gallery ?? [];
        if ($this->gallery && count($this->gallery) > 0) {
            foreach ($this->gallery as $image) {
                $galleryImages[] = $image->store("{$basePath}/gallery", 'public');
            }
        }
        $data['gallery'] = $galleryImages;

        if ($this->editingId) {
            $roomType = RoomType::forTenant()->findOrFail($this->editingId);
            
            // Remover imagem antiga se foi substituída
            if ($this->featured_image && $roomType->featured_image) {
                Storage::disk('public')->delete($roomType->featured_image);
            }
            
            $roomType->update($data);
            $this->dispatch('notify', message: 'Tipo de quarto atualizado com sucesso!', type: 'success');
        } else {
            RoomType::create($data);
            $this->dispatch('notify', message: 'Tipo de quarto criado com sucesso!', type: 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'code', 'description', 'base_price', 'weekend_price', 
                      'capacity', 'extra_bed_capacity', 'extra_bed_price', 'amenities', 'is_active',
                      'featured_image', 'gallery', 'existing_featured_image', 'existing_gallery']);
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        $roomType = RoomType::forTenant()->findOrFail($this->deleteId);
        
        if ($roomType->rooms()->count() > 0) {
            $this->dispatch('error', message: 'Não é possível excluir. Existem quartos associados a este tipo.');
            $this->confirmingDelete = false;
            return;
        }

        $roomType->delete();
        $this->dispatch('success', message: 'Tipo de quarto excluído com sucesso!');
        $this->confirmingDelete = false;
        $this->deleteId = null;
    }

    public function toggleStatus($id)
    {
        $roomType = RoomType::forTenant()->findOrFail($id);
        $roomType->update(['is_active' => !$roomType->is_active]);
        
        $this->dispatch('success', message: 'Status atualizado com sucesso!');
    }

    public function render()
    {
        $roomTypes = RoomType::forTenant()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%');
                });
            })
            ->withCount('rooms')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.hotel.room-types.room-types', compact('roomTypes'))
            ->layout('layouts.app');
    }
}
