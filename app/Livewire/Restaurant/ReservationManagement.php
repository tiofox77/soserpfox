<?php

namespace App\Livewire\Restaurant;

use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\Venue;
use App\Services\Restaurant\RestaurantReservationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Reservas - Restaurante')]
class ReservationManagement extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public ?int $venueId = null;
    public ?int $tableId = null;
    public string $guestName = '';
    public string $phone = '';
    public string $email = '';
    public int $guestCount = 2;
    public string $reservedAt = '';
    public int $durationMinutes = 120;
    public string $notes = '';
    public string $filterDate = '';

    public function mount(): void { $this->filterDate = now()->format('Y-m-d'); $this->reservedAt = now()->addHour()->format('Y-m-d\TH:i'); $this->venueId = Venue::where('tenant_id', activeTenantId())->value('id'); }
    public function create(): void { $this->resetForm(); $this->showForm = true; }
    public function edit(int $id): void { $r = Reservation::where('tenant_id', activeTenantId())->findOrFail($id); $this->editingId=$r->id; $this->venueId=$r->venue_id; $this->tableId=$r->table_id; $this->guestName=$r->guest_name; $this->phone=$r->phone??''; $this->email=$r->email??''; $this->guestCount=$r->guest_count; $this->reservedAt=$r->reserved_at->format('Y-m-d\TH:i'); $this->durationMinutes=$r->duration_minutes; $this->notes=$r->notes??''; $this->showForm=true; }
    public function save(RestaurantReservationService $service): void
    {
        $data=$this->validate(['venueId'=>'required|integer','tableId'=>'nullable|integer','guestName'=>'required|string|max:150','phone'=>'nullable|string|max:30','email'=>'nullable|email','guestCount'=>'required|integer|min:1|max:100','reservedAt'=>'required|date','durationMinutes'=>'required|integer|min:30|max:720','notes'=>'nullable|string|max:1000']);
        try { $service->save(['venue_id'=>$data['venueId'],'table_id'=>$data['tableId'],'guest_name'=>$data['guestName'],'phone'=>$data['phone'],'email'=>$data['email'],'guest_count'=>$data['guestCount'],'reserved_at'=>$data['reservedAt'],'duration_minutes'=>$data['durationMinutes'],'notes'=>$data['notes']],activeTenantId(),auth()->id(),$this->editingId?Reservation::where('tenant_id',activeTenantId())->findOrFail($this->editingId):null); $this->showForm=false; $this->dispatch('notify',type:'success',message:'Reserva guardada.'); } catch(\Throwable $e){$this->dispatch('notify',type:'error',message:$e->getMessage());}
    }
    public function status(int $id,string $status,RestaurantReservationService $service): void { try{$service->changeStatus(Reservation::where('tenant_id',activeTenantId())->findOrFail($id),$status,activeTenantId());}catch(\Throwable $e){$this->dispatch('notify',type:'error',message:$e->getMessage());} }
    private function resetForm(): void { $this->editingId=null;$this->tableId=null;$this->guestName='';$this->phone='';$this->email='';$this->guestCount=2;$this->reservedAt=now()->addHour()->format('Y-m-d\TH:i');$this->durationMinutes=120;$this->notes=''; }
    public function render() { $venues=Venue::with(['tables'=>fn($q)=>$q->where('is_active',true)])->where('tenant_id',activeTenantId())->get(); $reservations=Reservation::with(['table','venue'])->whereDate('reserved_at',$this->filterDate)->orderBy('reserved_at')->get(); return view('livewire.restaurant.reservation-management',compact('venues','reservations')); }
}
