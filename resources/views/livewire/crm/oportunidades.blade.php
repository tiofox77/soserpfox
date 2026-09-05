<div>
    <!-- Header — o desenho da página de Produtos, na cor do CRM -->
    <div class="mb-6 bg-gradient-to-r from-teal-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-handshake text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Oportunidades</h2>
                    <p class="text-teal-100 text-sm">Os negócios com nome, valor e etapa</p>
                </div>
            </div>
            <button wire:click="criar" class="bg-white text-teal-600 hover:bg-teal-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova Oportunidade
            </button>
        </div>
    </div>

    <!-- Pesquisa e filtros -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="relative mb-4">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
            </div>
            <input wire:model.live.debounce.300ms="procurar" type="text" placeholder="Pesquisar título ou cliente..."
                   class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all">
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach(['open' => 'Abertas', 'won' => 'Ganhas', 'lost' => 'Perdidas', 'todas' => 'Todas'] as $codigo => $nome)
                <button wire:click="$set('filtroEstado', '{{ $codigo }}')"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition {{ $filtroEstado === $codigo ? 'bg-teal-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    {{ $nome }}
                </button>
            @endforeach
            <a href="{{ route('crm.funil-vendas') }}" class="ml-auto px-4 py-2 rounded-xl text-sm font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                <i class="fas fa-filter mr-1"></i>Ver em funil
            </a>
        </div>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-teal-600"></i>
                Lista de Oportunidades ({{ $oportunidades->total() }})
            </h3>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($oportunidades as $o)
                <div wire:key="op-{{ $o->id }}" class="group p-6 hover:bg-teal-50 transition-all duration-300 {{ $o->status === 'lost' ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-3 mb-2">
                                @if($o->status === 'open')
                                    <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-bold">ABERTA</span>
                                @elseif($o->status === 'won')
                                    <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-bold"><i class="fas fa-trophy mr-1"></i>GANHA</span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-600 rounded-lg text-xs font-bold">PERDIDA</span>
                                @endif
                                <h4 class="text-lg font-bold text-gray-900">{{ $o->title }}</h4>
                            </div>
                            <div class="flex flex-wrap gap-3 text-sm">
                                <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-lg font-semibold">
                                    <i class="fas fa-money-bill-wave mr-1"></i>{{ number_format((float) $o->amount, 0, ',', '.') }} Kz
                                </span>
                                <span class="inline-flex items-center px-3 py-1 bg-violet-100 text-violet-700 rounded-lg font-semibold">
                                    <i class="fas fa-filter mr-1"></i>{{ $o->stage?->name }} · {{ $o->probability }}%
                                </span>
                                <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-lg font-semibold">
                                    <i class="fas fa-user mr-1"></i>{{ $o->client?->name ?? 'Sem cliente' }}
                                </span>
                                @if($o->expected_close_date)
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg font-semibold {{ $o->expected_close_date->isPast() && $o->status === 'open' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600' }}">
                                        <i class="fas fa-calendar mr-1"></i>{{ $o->expected_close_date->format('d/m/Y') }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-lg font-semibold">
                                    <i class="fas fa-comments mr-1"></i>{{ $o->activities_count }}
                                </span>
                            </div>
                            @if($o->status === 'lost' && $o->lost_reason)
                                <p class="mt-2 text-sm font-semibold text-red-500"><i class="fas fa-circle-info mr-1"></i>{{ $o->lost_reason }}</p>
                            @endif

                            {{-- O laço fechado: o negócio ganho mostra o documento
                                 que dele nasceu. Sem isto, o funil dizia quanto se
                                 ganhou e nunca quanto se cobrou. --}}
                            @if($o->factura)
                                <p class="mt-2 text-sm font-semibold text-teal-700">
                                    <i class="fas fa-file-invoice mr-1"></i>
                                    {{ $o->factura->invoice_number }}
                                    <span class="font-normal text-gray-500">
                                        · {{ $o->factura->invoice_date?->format('d/m/Y') }}
                                        · {{ __(ucfirst($o->factura->status)) }}
                                        · {{ valorProtegido((float) $o->factura->total, 'crm.opportunities.view') }} Kz
                                    </span>
                                </p>
                            @elseif($o->status === 'won' && ! $o->client_id)
                                <p class="mt-2 text-sm text-amber-700">
                                    <i class="fas fa-triangle-exclamation mr-1"></i>{{ __('Sem cliente — converta o lead para poder facturar.') }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                            @if($o->status === 'open')
                                <button wire:click="ganhar({{ $o->id }})" wire:confirm="Marcar como GANHA?" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg text-xs font-semibold transition shadow-md">
                                    <i class="fas fa-trophy mr-1"></i>Ganha
                                </button>
                                <button wire:click="$set('paraPerder', {{ $o->id }})" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-semibold transition shadow-md">
                                    <i class="fas fa-xmark mr-1"></i>Perdida
                                </button>
                                <button wire:click="$set('paraActividade', {{ $o->id }})" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-semibold transition shadow-md">
                                    <i class="fas fa-phone mr-1"></i>Interacção
                                </button>
                                <button wire:click="editar({{ $o->id }})" class="px-3 py-1.5 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-xs font-semibold transition shadow-md">
                                    <i class="fas fa-edit mr-1"></i>Editar
                                </button>
                            @else
                                {{-- Ganho e ainda sem documento: é aqui que o negócio
                                     vira factura, sem sair do CRM e sem reescrever o valor. --}}
                                @if($o->status === 'won' && ! $o->sales_invoice_id && $o->client_id && auth()->user()->can('invoicing.sales.invoices.create'))
                                    <button wire:click="facturar({{ $o->id }})"
                                            wire:confirm="{{ __('Gerar a factura deste negócio? Nasce em rascunho, para conferir antes de assumir.') }}"
                                            wire:loading.attr="disabled" wire:target="facturar({{ $o->id }})"
                                            class="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold transition">
                                        <i class="fas fa-file-invoice mr-1"></i>{{ __('Gerar factura') }}
                                    </button>
                                @endif
                                <button wire:click="reabrir({{ $o->id }})" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-semibold transition shadow-md">
                                    <i class="fas fa-rotate-left mr-1"></i>Reabrir
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-handshake text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma oportunidade encontrada</h3>
                    <p class="text-gray-500 mb-4">Uma oportunidade nasce de um lead convertido, ou directamente no botão acima.</p>
                </div>
            @endforelse
        </div>

        @if($oportunidades->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $oportunidades->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: criar/editar -->
    @if($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-gradient-to-r from-teal-600 to-cyan-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-handshake mr-3"></i>{{ $editingId ? 'Editar' : 'Nova' }} Oportunidade
                            </h3>
                            <button wire:click="$set('showForm', false)" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-tag text-teal-500 mr-2"></i>Título *
                                </label>
                                <input wire:model="titulo" type="text" placeholder="Ex.: Fornecimento anual à obra do Camama"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                                @error('titulo') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-blue-500 mr-2"></i>Cliente
                                </label>
                                <select wire:model="clienteId" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 transition">
                                    <option value="">— ainda sem cliente —</option>
                                    @foreach($clientes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-filter text-violet-500 mr-2"></i>Etapa *
                                </label>
                                <select wire:model="etapaId" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 transition">
                                    @foreach($etapas as $e)<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->probability }}%)</option>@endforeach
                                </select>
                                @error('etapaId') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>Valor (Kz)
                                </label>
                                <input wire:model="valor" type="text" inputmode="decimal" placeholder="0,00"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-right font-bold focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar text-orange-500 mr-2"></i>Fecho previsto
                                </label>
                                <input wire:model="fechoPrevisto" type="date"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-align-left text-gray-500 mr-2"></i>Notas
                                </label>
                                <textarea wire:model="notas" rows="2"
                                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition"></textarea>
                            </div>
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button wire:click="$set('showForm', false)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button wire:click="guardar" wire:loading.attr="disabled"
                                    class="px-6 py-2.5 bg-gradient-to-r from-teal-600 to-cyan-600 text-white rounded-xl font-semibold hover:from-teal-700 hover:to-cyan-700 shadow-lg hover:shadow-xl transition disabled:opacity-50">
                                <i class="fas {{ $editingId ? 'fa-save' : 'fa-plus' }} mr-2"></i>{{ $editingId ? 'Atualizar' : 'Criar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: perdida, com motivo -->
    @if($paraPerder)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-gradient-to-r from-red-600 to-rose-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-xmark mr-3"></i>Porque se perdeu?
                            </h3>
                            <button wire:click="$set('paraPerder', null)" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600 mb-4">Os motivos somados são a única resposta honesta a «onde estamos a perder».</p>
                        <input wire:model="motivoPerda" wire:keydown.enter="perder" type="text" placeholder="Ex.: preço, prazo, concorrente..."
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                        @error('motivoPerda') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button wire:click="$set('paraPerder', null)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button wire:click="perder" class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-rose-600 text-white rounded-xl font-semibold hover:from-red-700 hover:to-rose-700 shadow-lg transition">
                                <i class="fas fa-xmark mr-2"></i>Marcar perdida
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: interacção rápida -->
    @if($paraActividade)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-gradient-to-r from-teal-600 to-cyan-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-phone mr-3"></i>Registar interacção
                            </h3>
                            <button wire:click="$set('paraActividade', null)" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex gap-3">
                            <select wire:model="actTipo" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 transition">
                                @foreach(\App\Models\CRM\Activity::TIPOS as $codigo => $nome)
                                    <option value="{{ $codigo }}">{{ $nome }}</option>
                                @endforeach
                            </select>
                            <input wire:model="actAssunto" wire:keydown.enter="registarActividade" type="text" placeholder="O que se falou / combinou"
                                   class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                        </div>
                        @error('actAssunto') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button wire:click="$set('paraActividade', null)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button wire:click="registarActividade" class="px-6 py-2.5 bg-gradient-to-r from-teal-600 to-cyan-600 text-white rounded-xl font-semibold hover:from-teal-700 hover:to-cyan-700 shadow-lg transition">
                                <i class="fas fa-check mr-2"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
