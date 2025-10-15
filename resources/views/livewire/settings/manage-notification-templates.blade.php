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

    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-bell text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Templates de Notificação</h2>
                    <p class="text-indigo-100 text-sm">Gerencie notificações automatizadas por módulo</p>
                </div>
            </div>
            <button wire:click="create" 
                    class="bg-white text-indigo-600 px-6 py-3 rounded-xl font-semibold hover:bg-indigo-50 transition shadow-lg">
                <i class="fas fa-plus mr-2"></i>Novo Template
            </button>
        </div>
    </div>

    {{-- Lista de Templates --}}
    <div class="grid grid-cols-1 gap-4">
        @forelse($templates as $template)
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="text-lg font-bold text-gray-900">{{ $template->name }}</h3>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
                                {{ $modules[$template->module] ?? $template->module }}
                            </span>
                            @if($template->is_active)
                                <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">
                                    ✓ Ativo
                                </span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-600">
                                    Inativo
                                </span>
                            @endif
                        </div>
                        
                        @if($template->description)
                            <p class="text-sm text-gray-600 mb-3">{{ $template->description }}</p>
                        @endif
                        
                        {{-- Canais --}}
                        <div class="flex items-center gap-3 text-sm mb-2">
                            @if($template->email_enabled)
                                <span class="flex items-center text-blue-600">
                                    <i class="fas fa-envelope mr-1"></i> Email
                                </span>
                            @endif
                            @if($template->sms_enabled)
                                <span class="flex items-center text-purple-600">
                                    <i class="fas fa-sms mr-1"></i> SMS
                                </span>
                            @endif
                            @if($template->whatsapp_enabled)
                                <span class="flex items-center text-green-600">
                                    <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                                </span>
                            @endif
                        </div>
                        
                        {{-- Trigger Info --}}
                        <div class="text-xs text-gray-500">
                            <i class="fas fa-clock mr-1"></i>
                            {{ $triggers[$template->trigger_event] ?? $template->trigger_event }}
                            @if($template->notify_before_minutes)
                                - {{ $template->notify_before_minutes }} minutos antes
                            @endif
                        </div>

                        {{-- Variáveis Mapeadas --}}
                        @if($template->variable_mappings && count($template->variable_mappings) > 0)
                            <div class="mt-3 flex flex-wrap gap-1">
                                @foreach($template->variable_mappings as $var => $field)
                                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs">
                                        @{{ $var }} → {{ $field }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    
                    {{-- Actions --}}
                    <div class="flex gap-2">
                        <button wire:click="openTestModal({{ $template->id }})" 
                                class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition"
                                title="Testar Template">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                        <button wire:click="edit({{ $template->id }})" 
                                class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition"
                                title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button wire:click="toggleActive({{ $template->id }})" 
                                class="px-4 py-2 {{ $template->is_active ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700' }} rounded-lg hover:opacity-80 transition"
                                title="{{ $template->is_active ? 'Desativar' : 'Ativar' }}">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button wire:click="delete({{ $template->id }})" 
                                onclick="return confirm('Tem certeza que deseja excluir este template?')"
                                class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition"
                                title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12 bg-white rounded-xl shadow">
                <i class="fas fa-bell-slash text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg mb-2">Nenhum template criado ainda</p>
                <p class="text-gray-400 text-sm mb-4">Crie templates para automatizar notificações do sistema</p>
                <button wire:click="create" class="text-indigo-600 hover:text-indigo-700 font-semibold">
                    <i class="fas fa-plus mr-2"></i>Criar primeiro template
                </button>
            </div>
        @endforelse
    </div>

    {{-- Modal de Criação/Edição --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" 
             x-data="{ show: true }" 
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
            
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" 
                     @click="show = false; $wire.closeModal()"></div>

                {{-- Modal --}}
                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full max-h-[90vh] overflow-y-auto">
                    
                    {{-- Header --}}
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold text-white">
                                <i class="fas fa-bell mr-2"></i>
                                {{ $editing ? 'Editar Template' : 'Novo Template' }}
                            </h3>
                            <button wire:click="closeModal" class="text-white hover:text-indigo-100">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>

                    <form wire:submit="save">
                        {{-- Body --}}
                        <div class="px-6 py-6 space-y-6">
                            
                            {{-- Informações Básicas --}}
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-info-circle text-indigo-500 mr-2"></i>
                                    Informações Básicas
                                </h4>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome *</label>
                                        <input type="text" wire:model="name" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Módulo *</label>
                                        <select wire:model.live="module" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            @foreach($modules as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                                    <textarea wire:model="description" rows="2"
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"></textarea>
                                </div>
                            </div>

                            {{-- Canais --}}
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-share-alt text-indigo-500 mr-2"></i>
                                    Canais de Notificação
                                </h4>
                                
                                <div class="flex gap-6">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="email_enabled" class="w-5 h-5 text-blue-600 rounded">
                                        <span class="ml-2 text-sm font-medium text-gray-900">
                                            <i class="fas fa-envelope text-blue-600 mr-1"></i> Email
                                        </span>
                                    </label>
                                    
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="sms_enabled" class="w-5 h-5 text-purple-600 rounded">
                                        <span class="ml-2 text-sm font-medium text-gray-900">
                                            <i class="fas fa-sms text-purple-600 mr-1"></i> SMS
                                        </span>
                                    </label>
                                    
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="whatsapp_enabled" class="w-5 h-5 text-green-600 rounded">
                                        <span class="ml-2 text-sm font-medium text-gray-900">
                                            <i class="fab fa-whatsapp text-green-600 mr-1"></i> WhatsApp
                                        </span>
                                    </label>
                                </div>
                            </div>

                            {{-- Templates --}}
                            @if($whatsapp_enabled || $sms_enabled)
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center justify-between">
                                        <span>
                                            <i class="fas fa-file-alt text-indigo-500 mr-2"></i>
                                            Templates
                                        </span>
                                        <button type="button" 
                                                wire:click="loadAvailableTemplates"
                                                wire:loading.attr="disabled"
                                                class="text-sm px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition">
                                            <span wire:loading.remove wire:target="loadAvailableTemplates">
                                                <i class="fas fa-sync mr-1"></i>Carregar
                                            </span>
                                            <span wire:loading wire:target="loadAvailableTemplates">
                                                <i class="fas fa-spinner fa-spin mr-1"></i>Carregando...
                                            </span>
                                        </button>
                                    </h4>
                                    
                                    @if($whatsapp_enabled)
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Template WhatsApp</label>
                                            <select wire:model.live="whatsapp_template_sid" 
                                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                                                <option value="">Selecione um template</option>
                                                @foreach($availableWhatsAppTemplates as $tpl)
                                                    <option value="{{ $tpl['sid'] }}">{{ $tpl['name'] }}</option>
                                                @endforeach
                                            </select>
                                            @if(empty($availableWhatsAppTemplates))
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Clique em "Carregar" para buscar templates do WhatsApp
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    @if($sms_enabled)
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="fas fa-sms text-purple-600 mr-1"></i>
                                                Corpo da Mensagem SMS
                                            </label>
                                            <textarea wire:model="sms_body" 
                                                      rows="4"
                                                      placeholder="Digite a mensagem SMS. Use @{{ variavel }} para inserir dados dinâmicos."
                                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 font-mono text-sm"></textarea>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Exemplo: Evento @{{ event }} no dia @{{ date }} em @{{ local }}
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            
                            {{-- Corpo Email --}}
                            @if($email_enabled)
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-envelope text-blue-600 mr-2"></i>
                                        Corpo do Email
                                    </h4>
                                    
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Assunto do Email</label>
                                        <input type="text" wire:model="email_subject" 
                                               placeholder="Ex: Lembrete de Evento - @{{ event }}"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Corpo do Email</label>
                                        <textarea wire:model="email_body" 
                                                  rows="6"
                                                  placeholder="Digite o corpo do email. Use @{{ variavel }} para inserir dados dinâmicos."
                                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Exemplo: Olá! Lembrete para o evento @{{ event }} no dia @{{ date }} em @{{ local }}
                                        </p>
                                    </div>
                                </div>
                            @endif
                            
                            {{-- Variáveis Disponíveis --}}
                            @if(!empty($moduleVariables))
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-tags text-indigo-500 mr-2"></i>
                                        Variáveis Disponíveis para {{ ucfirst($module) }}
                                    </h4>
                                    
                                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-4 rounded-lg border border-indigo-200">
                                        <p class="text-sm text-gray-700 mb-3">
                                            <i class="fas fa-info-circle text-indigo-600 mr-1"></i>
                                            Use estas variáveis em seus templates SMS e Email:
                                        </p>
                                        
                                        <div class="grid grid-cols-2 gap-3">
                                            @foreach($moduleVariables as $varName => $varInfo)
                                                @php
                                                    $varDisplay = '{{ ' . $varName . ' }}';
                                                @endphp
                                                <div class="bg-white p-3 rounded-lg border border-indigo-100 hover:border-indigo-300 transition">
                                                    <div class="flex items-center justify-between mb-1">
                                                        <code class="text-sm font-bold text-green-600 bg-green-50 px-2 py-1 rounded">
                                                            {{ $varDisplay }}
                                                        </code>
                                                        <button type="button" 
                                                                onclick="navigator.clipboard.writeText('{{ $varDisplay }}')"
                                                                class="text-xs text-gray-500 hover:text-indigo-600">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                    <p class="text-xs text-gray-600">{{ $varInfo['label'] }}</p>
                                                    <p class="text-xs text-gray-400 mt-1">Campo: {{ $varInfo['field'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                        
                                        <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <p class="text-xs text-yellow-800">
                                                <i class="fas fa-lightbulb mr-1"></i>
                                                <strong>Dica:</strong> Clique no ícone <i class="fas fa-copy text-xs"></i> para copiar a variável e colar no corpo da mensagem.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Timing --}}
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-clock text-indigo-500 mr-2"></i>
                                    Quando Enviar
                                </h4>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Evento *</label>
                                        <select wire:model="trigger_event" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            @foreach($triggers as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    @if($trigger_event === 'date_approaching')
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Minutos Antes</label>
                                            <input type="number" wire:model="notify_before_minutes" 
                                                   placeholder="1440 (24 horas)"
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <p class="text-xs text-gray-500 mt-1">1440 = 24h, 10080 = 7 dias</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Mapeamento de Variáveis --}}
                            @if(!empty($detectedVariables))
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-code text-indigo-500 mr-2"></i>
                                        Mapeamento de Variáveis
                                    </h4>
                                    
                                    <div class="space-y-3">
                                        @foreach($detectedVariables as $variable)
                                            <div class="flex items-center gap-4 bg-gray-50 p-4 rounded-lg">
                                                <div class="w-32">
                                                    <span class="text-sm font-mono font-bold text-green-600">
                                                        @{{ $variable }}
                                                    </span>
                                                </div>
                                                <i class="fas fa-arrow-right text-gray-400"></i>
                                                <div class="flex-1">
                                                    <select wire:model="variable_mappings.{{ $variable }}" 
                                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                                        <option value="">Selecione o campo</option>
                                                        @foreach($availableFields as $field => $label)
                                                            <option value="{{ $field }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Status --}}
                            <div>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="is_active" class="w-5 h-5 text-green-600 rounded">
                                    <span class="ml-2 text-sm font-medium text-gray-900">
                                        <i class="fas fa-check-circle text-green-600 mr-1"></i> Template Ativo
                                    </span>
                                </label>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                            <button type="button" wire:click="closeModal"
                                    class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit" 
                                    wire:loading.attr="disabled"
                                    class="px-6 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-lg hover:from-indigo-600 hover:to-purple-700 transition shadow-lg disabled:opacity-50">
                                <span wire:loading.remove wire:target="save">
                                    <i class="fas fa-save mr-2"></i>{{ $editing ? 'Atualizar' : 'Criar' }} Template
                                </span>
                                <span wire:loading wire:target="save">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de Teste --}}
    @if($showTestModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" 
             x-data="{ show: true }" 
             x-show="show">
            
            <div class="flex items-center justify-center min-h-screen px-4">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75" 
                     @click="show = false; $wire.closeTestModal()"></div>

                {{-- Modal --}}
                <div class="inline-block bg-white rounded-2xl shadow-xl sm:max-w-lg sm:w-full z-50">
                    
                    {{-- Header --}}
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4 rounded-t-2xl">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold text-white flex items-center">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Testar Template
                            </h3>
                            <button wire:click="closeTestModal" class="text-white hover:text-green-100">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-phone text-green-600 mr-2"></i>
                                Número de Teste * 
                                <span class="text-xs text-gray-500 font-normal">(Angola)</span>
                            </label>
                            <input type="text" wire:model="testPhone" 
                                   placeholder="939729902 ou +244939729902"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Aceita: 939729902, +244939729902 ou 244939729902
                            </p>
                            @error('testPhone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        @if(!empty($testVariables))
                            <div class="border-t border-gray-200 pt-4">
                                <h4 class="text-sm font-bold text-gray-900 mb-3">
                                    <i class="fas fa-code text-indigo-600 mr-2"></i>
                                    Variáveis do Template
                                </h4>
                                @foreach($testVariables as $var => $value)
                                    <div class="mb-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            @{{ $var }}
                                        </label>
                                        <input type="text" wire:model="testVariables.{{ $var }}" 
                                               placeholder="Valor para {{ $var }}"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3 rounded-b-2xl">
                        <button type="button" wire:click="closeTestModal"
                                class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="button" wire:click="sendTest"
                                wire:loading.attr="disabled"
                                class="px-6 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition shadow-lg disabled:opacity-50">
                            <span wire:loading.remove wire:target="sendTest">
                                <i class="fas fa-paper-plane mr-2"></i>Enviar Teste
                            </span>
                            <span wire:loading wire:target="sendTest">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
