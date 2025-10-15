<div>
    {{-- Toastr Integration --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('show-toast', (event) => {
                const data = event[0] || event;
                const type = data.type || 'info';
                const message = data.message || 'Operação realizada';
                
                if (typeof toastr !== 'undefined') {
                    toastr.options = {
                        "closeButton": true,
                        "progressBar": true,
                        "positionClass": "toast-top-right",
                        "timeOut": "5000"
                    };
                    toastr[type](message);
                }
            });
        });
    </script>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" 
             class="mb-6 bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl shadow-lg p-4 text-white flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-2xl mr-3"></i>
                <span class="font-semibold">{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="text-white hover:text-green-100 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-6 bg-gradient-to-r from-red-500 to-pink-600 rounded-2xl shadow-lg p-4 text-white flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                <span class="font-semibold">{{ session('error') }}</span>
            </div>
            <button @click="show = false" class="text-white hover:text-red-100 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-bell text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Notificações</h2>
                    <p class="text-yellow-100 text-sm">Configure Email, SMS e WhatsApp para seu tenant</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('activeTab', 'dashboard')" 
                        class="px-4 py-2 rounded-xl font-semibold transition-all {{ $activeTab === 'dashboard' ? 'bg-white text-yellow-600 shadow-lg' : 'bg-white/20 text-white hover:bg-white/30' }}">
                    <i class="fas fa-chart-line mr-2"></i>Dashboard
                </button>
                <button wire:click="$set('activeTab', 'email')" 
                        class="px-4 py-2 rounded-xl font-semibold transition-all {{ in_array($activeTab, ['email', 'sms', 'whatsapp']) ? 'bg-white text-yellow-600 shadow-lg' : 'bg-white/20 text-white hover:bg-white/30' }}">
                    <i class="fas fa-cog mr-2"></i>Configurações
                </button>
                <a href="{{ route('notifications.templates') }}" 
                   class="px-4 py-2 rounded-xl font-semibold transition-all bg-white/20 text-white hover:bg-white/30">
                    <i class="fas fa-file-alt mr-2"></i>Templates
                </a>
            </div>
        </div>
    </div>

    {{-- Dashboard Tab --}}
    @if($activeTab === 'dashboard')
        @include('livewire.settings.partials._dashboard-stats')
        @include('livewire.settings.partials._dashboard-table')
    @endif

    {{-- Settings Tab --}}
    @if(in_array($activeTab, ['email', 'sms', 'whatsapp']))
    <form wire:submit="save">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            {{-- Tabs Header --}}
            <div class="border-b border-gray-200 bg-gradient-to-r from-gray-50 to-blue-50">
                <nav class="flex -mb-px">
                    <button type="button" wire:click="$set('activeTab', 'email')" 
                            class="flex items-center px-6 py-4 border-b-2 {{ $activeTab === 'email' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} font-medium text-sm transition-colors">
                        <i class="fas fa-envelope mr-2"></i>
                        Email
                    </button>
                    <button type="button" wire:click="$set('activeTab', 'sms')" 
                            class="flex items-center px-6 py-4 border-b-2 {{ $activeTab === 'sms' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} font-medium text-sm transition-colors">
                        <i class="fas fa-sms mr-2"></i>
                        SMS
                    </button>
                    <button type="button" wire:click="$set('activeTab', 'whatsapp')" 
                            class="flex items-center px-6 py-4 border-b-2 {{ $activeTab === 'whatsapp' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} font-medium text-sm transition-colors">
                        <i class="fab fa-whatsapp mr-2"></i>
                        WhatsApp
                    </button>
                </nav>
            </div>

            {{-- Tab Content --}}
            <div class="p-6">
                @if($activeTab === 'email')
                    @include('livewire.settings.partials._settings-email')
                @endif

                @if($activeTab === 'sms')
                    @include('livewire.settings.partials._settings-sms')
                @endif

                @if($activeTab === 'whatsapp')
                    @include('livewire.settings.partials._settings-whatsapp')
                @endif
            </div>

            {{-- Footer with Save Button --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-blue-600 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-save mr-2"></i>
                        Salvar Configurações
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Salvando...
                    </span>
                </button>
            </div>
        </div>
    </form>
    @endif
</div>
