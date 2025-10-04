<div>
    <!-- Header with Gradient -->
    <div class="mb-6 bg-gradient-to-r from-green-600 to-teal-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-tags text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Planos de Subscrição</h2>
                    <p class="text-green-100 text-sm">Gerir planos e preços disponíveis</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-green-600 hover:bg-green-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Plano
            </button>
        </div>
    </div>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 stagger-animation">
        @foreach($plans as $plan)
            <div class="group bg-white rounded-2xl shadow-lg overflow-hidden {{ $plan->is_featured ? 'ring-2 ring-blue-500 scale-105 card-glow' : '' }} card-hover card-zoom">
                @if($plan->is_featured)
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white text-center py-3 text-sm font-bold tracking-wide flex items-center justify-center gradient-shift animate-pulse">
                        <i class="fas fa-star mr-2 animate-spin"></i>RECOMENDADO
                    </div>
                @endif
                
                <div class="p-6">
                    <!-- Plan Icon & Name -->
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 mx-auto bg-gradient-to-br from-green-500 to-green-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 mb-4 icon-float card-bounce gradient-shift">
                            <i class="fas fa-crown text-white text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan->name }}</h3>
                        <p class="text-sm text-gray-600">{{ $plan->description }}</p>
                    </div>
                    
                    <!-- Pricing -->
                    <div class="mb-6 text-center p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl">
                        <div class="flex items-baseline justify-center">
                            <span class="text-4xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 2) }}</span>
                            <span class="text-gray-600 ml-2">Kz/mês</span>
                        </div>
                        <div class="text-sm text-gray-500 mt-2">
                            ou {{ number_format($plan->price_yearly, 2) }} Kz/ano
                        </div>
                        @if($plan->getYearlySavingsPercentage() > 0)
                            <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full mt-2">
                                <i class="fas fa-piggy-bank mr-1"></i>
                                Poupe {{ $plan->getYearlySavingsPercentage() }}%
                            </span>
                        @endif
                    </div>
                    
                    <!-- Features -->
                    @if($plan->features)
                        <div class="space-y-2 mb-6">
                            @foreach($plan->features as $feature)
                                <div class="flex items-start">
                                    <div class="w-5 h-5 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0 mt-0.5 mr-2">
                                        <i class="fas fa-check text-green-600 text-xs"></i>
                                    </div>
                                    <span class="text-sm text-gray-700">{{ $feature }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    
                    <!-- Specs -->
                    <div class="space-y-2 mb-6 p-4 bg-gray-50 rounded-xl">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center text-xs text-gray-600">
                                <span class="w-5 h-5 rounded bg-blue-100 flex items-center justify-center mr-2">
                                    <i class="fas fa-users text-blue-600 text-[10px]"></i>
                                </span>
                                Utilizadores
                            </span>
                            <span class="text-sm font-bold text-gray-900">{{ $plan->max_users }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center text-xs text-gray-600">
                                <span class="w-5 h-5 rounded bg-green-100 flex items-center justify-center mr-2">
                                    <i class="fas fa-building text-green-600 text-[10px]"></i>
                                </span>
                                Empresas
                            </span>
                            <span class="text-sm font-bold text-gray-900">
                                @if($plan->max_companies >= 999)
                                    <i class="fas fa-infinity text-green-600"></i> Ilimitado
                                @else
                                    {{ $plan->max_companies }}
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center text-xs text-gray-600">
                                <span class="w-5 h-5 rounded bg-purple-100 flex items-center justify-center mr-2">
                                    <i class="fas fa-database text-purple-600 text-[10px]"></i>
                                </span>
                                Storage
                            </span>
                            <span class="text-sm font-bold text-gray-900">{{ number_format($plan->max_storage_mb / 1000, 1) }}GB</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center text-xs text-gray-600">
                                <span class="w-5 h-5 rounded bg-orange-100 flex items-center justify-center mr-2">
                                    <i class="fas fa-gift text-orange-600 text-[10px]"></i>
                                </span>
                                Trial
                            </span>
                            <span class="text-sm font-bold text-gray-900">{{ $plan->trial_days }} dias</span>
                        </div>
                    </div>
                    
                    <!-- Status & Stats -->
                    <div class="pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-gray-500">
                                <strong class="text-gray-900">{{ $plan->subscriptions()->where('status', 'active')->count() }}</strong> subscrições ativas
                            </span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <span wire:click="toggleStatus({{ $plan->id }})" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full cursor-pointer {{ $plan->is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }} transition">
                                <span class="w-1.5 h-1.5 rounded-full {{ $plan->is_active ? 'bg-green-500' : 'bg-gray-500' }} mr-1.5"></span>
                                {{ $plan->is_active ? 'Disponível' : 'Indisponível' }}
                            </span>
                            
                            <button wire:click="edit({{ $plan->id }})" class="opacity-0 group-hover:opacity-100 transition-opacity inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100">
                                <i class="fas fa-edit mr-1"></i>Editar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Modals -->
    {{-- @include('livewire.super-admin.plans.partials.form-modal') --}}
    {{-- Modal temporariamente inline até criar arquivo parcial --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeModal"></div>
                
                <div class="relative bg-white rounded-2xl max-w-3xl w-full shadow-2xl transform transition-all" @click.stop>
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-green-600 to-teal-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center text-white">
                            <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-tags text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold">
                                {{ $editingPlanId ? 'Editar Plano' : 'Novo Plano' }}
                            </h3>
                        </div>
                        <button wire:click="closeModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <!-- Modal Body -->
                    <form wire:submit.prevent="save" class="p-6 max-h-[70vh] overflow-y-auto">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-tag text-green-500 mr-2"></i>Nome *
                                </label>
                                <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                @error('name') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-link text-teal-500 mr-2"></i>Slug *
                                </label>
                                <input wire:model="slug" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                                @error('slug') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-sort text-gray-500 mr-2"></i>Ordem *
                                </label>
                                <input wire:model="order" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-gray-500 focus:border-transparent transition">
                                @error('order') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição *
                                </label>
                                <textarea wire:model="description" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition"></textarea>
                                @error('description') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>Preço Mensal (Kz) *
                                </label>
                                <input wire:model="price_monthly" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                @error('price_monthly') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-money-bill-wave text-blue-500 mr-2"></i>Preço Anual (Kz) *
                                </label>
                                <input wire:model="price_yearly" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                @error('price_yearly') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-users text-orange-500 mr-2"></i>Máx. Utilizadores *
                                </label>
                                <input wire:model="max_users" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                @error('max_users') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-building text-green-500 mr-2"></i>Máx. Empresas *
                                </label>
                                <input wire:model="max_companies" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition" min="1">
                                @error('max_companies') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                                <p class="text-xs text-gray-500 mt-1"><i class="fas fa-info-circle mr-1"></i>999 = Ilimitado</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-database text-purple-500 mr-2"></i>Storage (MB) *
                                </label>
                                <input wire:model="max_storage_mb" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                @error('max_storage_mb') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-gift text-pink-500 mr-2"></i>Trial (dias) *
                                </label>
                                <input wire:model="trial_days" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                                @error('trial_days') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                            </div>
                            
                            <!-- Features -->
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-list text-blue-500 mr-2"></i>Funcionalidades
                                </label>
                                <div class="flex space-x-2 mb-3">
                                    <input wire:model="newFeature" wire:keydown.enter.prevent="addFeature" type="text" placeholder="Digite uma funcionalidade e pressione Enter" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                    <button type="button" wire:click="addFeature" class="px-4 py-2.5 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div class="space-y-2 max-h-40 overflow-y-auto">
                                    @forelse($features as $index => $feature)
                                        <div class="flex items-center justify-between px-4 py-2 bg-gray-50 rounded-lg">
                                            <span class="text-sm text-gray-700"><i class="fas fa-check text-green-500 mr-2"></i>{{ $feature }}</span>
                                            <button type="button" wire:click="removeFeature({{ $index }})" class="text-red-500 hover:text-red-700">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    @empty
                                        <p class="text-xs text-gray-500 text-center py-2">Nenhuma funcionalidade adicionada</p>
                                    @endforelse
                                </div>
                            </div>
                            
                            <!-- Modules Section -->
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-puzzle-piece text-purple-500 mr-2"></i>Módulos Incluídos
                                </label>
                                <div class="grid grid-cols-2 gap-3 p-4 bg-gray-50 rounded-xl max-h-60 overflow-y-auto">
                                    @forelse($modules as $module)
                                        <label class="flex items-center p-3 bg-white rounded-lg cursor-pointer hover:bg-blue-50 transition border border-gray-200 {{ in_array($module->id, $selectedModules) ? 'border-blue-500 bg-blue-50' : '' }}">
                                            <input wire:model="selectedModules" type="checkbox" value="{{ $module->id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-5 h-5">
                                            <span class="ml-3 flex-1">
                                                <span class="block text-sm font-semibold text-gray-900">
                                                    <i class="fas fa-{{ $module->icon }} text-blue-600 mr-2"></i>{{ $module->name }}
                                                </span>
                                                <span class="block text-xs text-gray-500 mt-0.5">{{ $module->description }}</span>
                                            </span>
                                        </label>
                                    @empty
                                        <p class="col-span-2 text-sm text-gray-500 text-center py-4">Nenhum módulo disponível</p>
                                    @endforelse
                                </div>
                            </div>
                            
                            <div class="col-span-2 flex space-x-4">
                                <label class="flex-1 flex items-center px-4 py-3 bg-green-50 rounded-xl cursor-pointer hover:bg-green-100 transition">
                                    <input wire:model="is_active" type="checkbox" class="rounded border-gray-300 text-green-600 focus:ring-green-500 w-5 h-5">
                                    <span class="ml-3 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-power-off text-green-500 mr-2"></i>Ativo
                                    </span>
                                </label>
                                
                                <label class="flex-1 flex items-center px-4 py-3 bg-blue-50 rounded-xl cursor-pointer hover:bg-blue-100 transition">
                                    <input wire:model="is_featured" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-5 h-5">
                                    <span class="ml-3 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-star text-blue-500 mr-2"></i>Destaque
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-teal-600 text-white rounded-xl font-semibold hover:from-green-700 hover:to-teal-700 shadow-lg hover:shadow-xl transition">
                                <i class="fas {{ $editingPlanId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                                {{ $editingPlanId ? 'Atualizar' : 'Criar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    {{-- TODO: Criar arquivos parciais:
        @include('livewire.super-admin.plans.partials.form-modal')
        @include('livewire.super-admin.plans.partials.delete-modal')
        @include('livewire.super-admin.plans.partials.view-modal')
    --}}
</div>
