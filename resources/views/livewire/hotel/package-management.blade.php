<div class="p-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center">
                <i class="fas fa-gift text-white text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Pacotes & Promoções</h1>
                <p class="text-gray-500 dark:text-gray-400">Gerencie ofertas especiais e códigos promocionais</p>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="mb-4 p-4 bg-green-100 border border-green-200 text-green-700 rounded-xl flex items-center gap-2">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-l-4 border-purple-500">
            <p class="text-sm text-gray-500">Total Pacotes</p>
            <p class="text-2xl font-bold text-purple-600">{{ $stats['total_packages'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-l-4 border-green-500">
            <p class="text-sm text-gray-500">Pacotes Ativos</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['active_packages'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-l-4 border-pink-500">
            <p class="text-sm text-gray-500">Total Códigos</p>
            <p class="text-2xl font-bold text-pink-600">{{ $stats['total_promos'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-l-4 border-amber-500">
            <p class="text-sm text-gray-500">Códigos Ativos</p>
            <p class="text-2xl font-bold text-amber-600">{{ $stats['active_promos'] }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm mb-6">
        <div class="flex border-b dark:border-gray-700">
            <button wire:click="$set('activeTab', 'packages')" 
                    class="flex-1 px-6 py-4 font-semibold transition {{ $activeTab === 'packages' ? 'text-purple-600 border-b-2 border-purple-600 bg-purple-50 dark:bg-purple-900/20' : 'text-gray-500 hover:text-gray-700' }}">
                <i class="fas fa-box-open mr-2"></i> Pacotes
            </button>
            <button wire:click="$set('activeTab', 'promo_codes')" 
                    class="flex-1 px-6 py-4 font-semibold transition {{ $activeTab === 'promo_codes' ? 'text-pink-600 border-b-2 border-pink-600 bg-pink-50 dark:bg-pink-900/20' : 'text-gray-500 hover:text-gray-700' }}">
                <i class="fas fa-ticket-alt mr-2"></i> Códigos Promocionais
            </button>
        </div>

        <div class="p-4">
            {{-- Search & Actions --}}
            <div class="flex items-center justify-between mb-4">
                <div class="relative flex-1 max-w-md">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Pesquisar..."
                           class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-purple-500 focus:outline-none dark:bg-gray-700">
                </div>
                @if($activeTab === 'packages')
                <button wire:click="openPackageModal" class="ml-4 px-6 py-3 bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700 transition">
                    <i class="fas fa-plus mr-2"></i> Novo Pacote
                </button>
                @else
                <button wire:click="openPromoModal" class="ml-4 px-6 py-3 bg-pink-600 text-white rounded-xl font-semibold hover:bg-pink-700 transition">
                    <i class="fas fa-plus mr-2"></i> Novo Código
                </button>
                @endif
            </div>

            {{-- Packages Tab --}}
            @if($activeTab === 'packages')
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($packages as $package)
                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 border-2 {{ $package->is_active ? 'border-purple-200 dark:border-purple-800' : 'border-gray-200 dark:border-gray-700 opacity-60' }}">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-10 h-10 bg-{{ $package->getTypeColor() }}-100 dark:bg-{{ $package->getTypeColor() }}-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas {{ $package->getTypeIcon() }} text-{{ $package->getTypeColor() }}-500"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-gray-800 dark:text-white">{{ $package->name }}</h3>
                                <p class="text-xs text-gray-500">{{ $package->getTypeLabel() }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button wire:click="openPackageModal({{ $package->id }})" class="p-2 text-gray-500 hover:text-purple-600">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button wire:click="togglePackageStatus({{ $package->id }})" class="p-2 {{ $package->is_active ? 'text-green-500' : 'text-gray-400' }}">
                                <i class="fas {{ $package->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                            </button>
                        </div>
                    </div>
                    
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">{{ $package->description ?: 'Sem descrição' }}</p>
                    
                    <div class="flex items-center justify-between text-sm">
                        <div class="text-gray-500">
                            @if($package->price)
                            <span class="font-bold text-purple-600">{{ number_format($package->price, 0, ',', '.') }} Kz</span>
                            @elseif($package->discount_percentage)
                            <span class="font-bold text-green-600">{{ $package->discount_percentage }}% OFF</span>
                            @elseif($package->discount_amount)
                            <span class="font-bold text-green-600">-{{ number_format($package->discount_amount, 0, ',', '.') }} Kz</span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400">Min. {{ $package->min_nights }} noite(s)</span>
                    </div>
                    
                    @if($package->valid_until)
                    <div class="mt-2 text-xs {{ $package->valid_until->isPast() ? 'text-red-500' : 'text-gray-400' }}">
                        Válido até: {{ $package->valid_until->format('d/m/Y') }}
                    </div>
                    @endif
                </div>
                @empty
                <div class="col-span-3 text-center py-12 text-gray-500">
                    <i class="fas fa-box-open text-4xl mb-3 opacity-50"></i>
                    <p>Nenhum pacote encontrado</p>
                </div>
                @endforelse
            </div>
            <div class="mt-4">{{ $packages->links() }}</div>
            @endif

            {{-- Promo Codes Tab --}}
            @if($activeTab === 'promo_codes')
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">Desconto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">Uso</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">Validade</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-400">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse($promoCodes as $promo)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50 {{ !$promo->is_active ? 'opacity-50' : '' }}">
                            <td class="px-4 py-3">
                                <span class="px-3 py-1 bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300 rounded-lg font-mono font-bold">
                                    {{ $promo->code }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ $promo->name }}</td>
                            <td class="px-4 py-3">
                                @if($promo->discount_type === 'percentage')
                                <span class="text-green-600 font-bold">{{ $promo->discount_value }}%</span>
                                @else
                                <span class="text-green-600 font-bold">{{ number_format($promo->discount_value, 0, ',', '.') }} Kz</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ $promo->times_used }}{{ $promo->usage_limit ? '/' . $promo->usage_limit : '' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                @if($promo->valid_until)
                                {{ $promo->valid_until->format('d/m/Y') }}
                                @else
                                <span class="text-gray-400">Sem limite</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full bg-{{ $promo->getStatusColor() }}-100 text-{{ $promo->getStatusColor() }}-700">
                                    {{ $promo->getStatusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="openPromoModal({{ $promo->id }})" class="p-2 text-gray-500 hover:text-pink-600">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="togglePromoStatus({{ $promo->id }})" class="p-2 {{ $promo->is_active ? 'text-green-500' : 'text-gray-400' }}">
                                    <i class="fas {{ $promo->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                <i class="fas fa-ticket-alt text-4xl mb-3 opacity-50"></i>
                                <p>Nenhum código promocional encontrado</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $promoCodes->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Package Modal --}}
    @if($showPackageModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50" wire:click="$set('showPackageModal', false)"></div>
            
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
                <div class="bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4">
                    <div class="flex items-center justify-between text-white">
                        <h2 class="text-xl font-bold">{{ $editingPackageId ? 'Editar Pacote' : 'Novo Pacote' }}</h2>
                        <button wire:click="$set('showPackageModal', false)"><i class="fas fa-times"></i></button>
                    </div>
                </div>

                <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)] space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold mb-1">Nome do Pacote *</label>
                            <input type="text" wire:model="packageName" class="w-full px-4 py-2 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">Tipo</label>
                            <select wire:model="packageType" class="w-full px-4 py-2 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
                                <option value="romantic">Romântico</option>
                                <option value="family">Família</option>
                                <option value="business">Negócios</option>
                                <option value="wellness">Bem-estar</option>
                                <option value="adventure">Aventura</option>
                                <option value="other">Outro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">Noites Mín./Máx.</label>
                            <div class="flex gap-2">
                                <input type="number" wire:model="packageMinNights" placeholder="Mín" class="w-1/2 px-3 py-2 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
                                <input type="number" wire:model="packageMaxNights" placeholder="Máx" class="w-1/2 px-3 py-2 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Descrição</label>
                        <textarea wire:model="packageDescription" rows="2" class="w-full px-4 py-2 border rounded-xl dark:bg-gray-700 dark:border-gray-600 resize-none"></textarea>
                    </div>

                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4">
                        <label class="block text-sm font-semibold mb-2 text-purple-700 dark:text-purple-300">Preço / Desconto</label>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Preço Fixo (Kz)</label>
                                <input type="number" wire:model="packagePrice" placeholder="0" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Desconto %</label>
                                <input type="number" wire:model="packageDiscountPercentage" placeholder="0" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Desconto Fixo (Kz)</label>
                                <input type="number" wire:model="packageDiscountAmount" placeholder="0" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">Válido De</label>
                            <input type="date" wire:model="packageValidFrom" class="w-full px-4 py-2 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">Válido Até</label>
                            <input type="date" wire:model="packageValidUntil" class="w-full px-4 py-2 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Serviços Incluídos</label>
                        <div class="flex gap-2 mb-2">
                            <input type="text" wire:model="newService" placeholder="Ex: Pequeno-almoço" class="flex-1 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600" wire:keydown.enter.prevent="addService">
                            <button wire:click="addService" class="px-4 py-2 bg-purple-500 text-white rounded-lg"><i class="fas fa-plus"></i></button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($packageIncludedServices as $index => $service)
                            <span class="px-3 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full text-sm flex items-center gap-2">
                                {{ $service }}
                                <button wire:click="removeService({{ $index }})" class="hover:text-red-500"><i class="fas fa-times text-xs"></i></button>
                            </span>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="packageIsActive" class="w-5 h-5 rounded text-purple-500">
                            <span class="font-medium">Ativo</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="packageShowOnline" class="w-5 h-5 rounded text-purple-500">
                            <span class="font-medium">Exibir Online</span>
                        </label>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t flex justify-end gap-3">
                    <button wire:click="$set('showPackageModal', false)" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 rounded-xl font-medium">Cancelar</button>
                    <button wire:click="savePackage" class="px-6 py-2 bg-purple-600 text-white rounded-xl font-medium hover:bg-purple-700">Salvar</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Promo Code Modal --}}
    @if($showPromoModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50" wire:click="$set('showPromoModal', false)"></div>
            
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg">
                <div class="bg-gradient-to-r from-pink-500 to-rose-600 px-6 py-4">
                    <div class="flex items-center justify-between text-white">
                        <h2 class="text-xl font-bold">{{ $editingPromoId ? 'Editar Código' : 'Novo Código Promocional' }}</h2>
                        <button wire:click="$set('showPromoModal', false)"><i class="fas fa-times"></i></button>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">Código *</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="promoCode" class="flex-1 px-3 py-2 border rounded-lg font-mono uppercase dark:bg-gray-700 dark:border-gray-600">
                                <button wire:click="generateCode" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg" title="Gerar código">
                                    <i class="fas fa-sync"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">Nome *</label>
                            <input type="text" wire:model="promoName" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">Tipo de Desconto</label>
                            <select wire:model="promoDiscountType" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                                <option value="percentage">Percentual (%)</option>
                                <option value="fixed">Valor Fixo (Kz)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">Valor *</label>
                            <input type="number" wire:model="promoDiscountValue" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">Valor Mínimo (Kz)</label>
                            <input type="number" wire:model="promoMinAmount" placeholder="Opcional" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">Desconto Máximo (Kz)</label>
                            <input type="number" wire:model="promoMaxDiscount" placeholder="Opcional" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">Limite de Usos</label>
                            <input type="number" wire:model="promoUsageLimit" placeholder="Ilimitado" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">Por Cliente</label>
                            <input type="number" wire:model="promoUsagePerCustomer" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">Válido De</label>
                            <input type="date" wire:model="promoValidFrom" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">Válido Até</label>
                            <input type="date" wire:model="promoValidUntil" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="promoIsActive" class="w-5 h-5 rounded text-pink-500">
                        <span class="font-medium">Código Ativo</span>
                    </label>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t flex justify-end gap-3">
                    <button wire:click="$set('showPromoModal', false)" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 rounded-xl font-medium">Cancelar</button>
                    <button wire:click="savePromo" class="px-6 py-2 bg-pink-600 text-white rounded-xl font-medium hover:bg-pink-700">Salvar</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
