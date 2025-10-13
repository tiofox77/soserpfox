<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;

#[Layout('layouts.client')]
#[Title('Minhas Proformas')]
class ClientProformas extends Component
{
    public function render()
    {
        return view('livewire.client.client-proformas');
    }
}
