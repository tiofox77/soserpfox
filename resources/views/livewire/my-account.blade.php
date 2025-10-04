<div class="min-h-screen bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 py-8">
    <div class="max-w-6xl mx-auto px-4">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-4 mb-2">
                <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-3xl font-bold text-gray-900">Minha Conta</h1>
            </div>
            <p class="text-gray-600">Gerencie suas empresas, plano e configurações</p>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-2xl shadow-lg mb-6 overflow-hidden">
            <div class="flex border-b border-gray-200">
                <button wire:click="setActiveTab('companies')" 
                        class="flex-1 px-6 py-4 text-sm font-semibold transition-all {{ $activeTab === 'companies' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:bg-gray-50' }}">
                    <i class="fas fa-building mr-2"></i>Minhas Empresas
                </button>
                <button wire:click="setActiveTab('plan')" 
                        class="flex-1 px-6 py-4 text-sm font-semibold transition-all {{ $activeTab === 'plan' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:bg-gray-50' }}">
                    <i class="fas fa-crown mr-2"></i>Meu Plano
                </button>
                <button wire:click="setActiveTab('profile')" 
                        class="flex-1 px-6 py-4 text-sm font-semibold transition-all {{ $activeTab === 'profile' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:bg-gray-50' }}">
                    <i class="fas fa-user-circle mr-2"></i>Perfil
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="space-y-6">
            
            @if($activeTab === 'companies')
                @include('livewire.my-account.companies-tab')
            @endif

            @if($activeTab === 'plan')
                @include('livewire.my-account.plan-tab')
            @endif

            @if($activeTab === 'profile')
                @include('livewire.my-account.profile-tab')
            @endif

        </div>
    </div>
    
    <!-- Modals -->
    @include('livewire.my-account.create-company-modal')
    @include('livewire.my-account.edit-company-modal')
    @include('livewire.my-account.delete-company-modal')
</div>

@push('scripts')
<script>
    // Listener para trocar de empresa
    Livewire.on('switch-tenant', (data) => {
        if (confirm('Deseja alternar para esta empresa?')) {
            Livewire.dispatch('switchTenant', { tenantId: data.tenantId });
        }
    });
</script>
@endpush
