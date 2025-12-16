<?php

namespace App\Livewire\Support;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\Support\FeatureRequest;
use App\Models\Support\FeatureRequestVote;
use Illuminate\Support\Facades\Storage;

#[Layout('layouts.app')]
class FeatureRequestsBoard extends Component
{
    use WithFileUploads;
    
    public $showModal = false;
    public $title = '';
    public $description = '';
    public $filter = 'popular';
    public $images = [];
    
    public function openModal()
    {
        $this->showModal = true;
        $this->reset(['title', 'description']);
    }
    
    public function closeModal()
    {
        $this->showModal = false;
    }
    
    public function createRequest()
    {
        $this->validate([
            'title' => 'required|min:10',
            'description' => 'required|min:20',
            'images.*' => 'nullable|image|max:2048',
        ]);
        
        // Processar imagens
        $imagePaths = [];
        if ($this->images && count($this->images) > 0) {
            $tenantId = auth()->user()->tenant_id;
            $userId = auth()->id();
            $requestId = 'REQ-' . time();
            
            foreach (array_slice($this->images, 0, 5) as $image) {
                $path = $image->store("features/{$tenantId}/{$userId}/{$requestId}", 'public');
                $imagePaths[] = $path;
            }
        }
        
        FeatureRequest::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'title' => $this->title,
            'description' => $this->description,
            'images' => $imagePaths,
            'status' => 'pending',
            'votes_count' => 0,
        ]);
        
        session()->flash('message', 'Sugestão enviada com sucesso!');
        $this->closeModal();
        $this->reset(['images']);
    }
    
    public function toggleVote($requestId)
    {
        $request = FeatureRequest::find($requestId);
        $vote = FeatureRequestVote::where('request_id', $requestId)
            ->where('user_id', auth()->id())
            ->first();
            
        if ($vote) {
            $vote->delete();
            $request->decrement('votes_count');
        } else {
            FeatureRequestVote::create([
                'request_id' => $requestId,
                'user_id' => auth()->id(),
            ]);
            $request->increment('votes_count');
        }
    }
    
    public function render()
    {
        $query = FeatureRequest::with('user', 'votes')
            ->where('tenant_id', auth()->user()->tenant_id);
            
        if ($this->filter === 'popular') {
            $query->orderBy('votes_count', 'desc');
        } elseif ($this->filter === 'recent') {
            $query->latest();
        } elseif ($this->filter === 'my') {
            $query->where('user_id', auth()->id());
        }
        
        $requests = $query->get();
        
        return view('livewire.support.feature-requests-board', [
            'requests' => $requests
        ]);
    }
}
