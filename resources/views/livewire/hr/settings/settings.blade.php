<div>
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
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-cog text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Configurações de Recursos Humanos</h2>
                    <p class="text-purple-100 text-sm">Regras de trabalho Angola 2025 e parâmetros do sistema</p>
                </div>
            </div>
            <div class="flex gap-3">
                <button wire:click="resetToDefaults" 
                        onclick="return confirm('Tem certeza que deseja restaurar todas as configurações para os valores padrão?')"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-5 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-undo mr-2"></i>Restaurar Padrões
                </button>
                <button wire:click="save" 
                        class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Salvar Alterações
                </button>
            </div>
        </div>
    </div>

    {{-- Filtro de Categoria --}}
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6">
        <div class="flex items-center gap-4">
            <label class="font-semibold text-gray-700">Filtrar por Categoria:</label>
            <select wire:model.live="categoryFilter" 
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="all">📋 Todas as Categorias</option>
                <option value="general">⚙️ Geral</option>
                <option value="worktime">⏰ Horário de Trabalho</option>
                <option value="overtime">🕐 Horas Extras</option>
                <option value="vacation">🏖️ Férias</option>
                <option value="leave">📅 Licenças</option>
                <option value="payroll">💰 Folha de Pagamento</option>
                <option value="benefits">🎁 Subsídios</option>
            </select>
        </div>
    </div>

    {{-- Aviso da primeira visita: as definições acabaram de ser criadas.
         Sem isto, quem abrisse a página depois de ela estar vazia não percebia
         o que tinha mudado nem de onde vinham os valores. --}}
    @if($criadasAgora > 0)
        <div class="mb-6 rounded-2xl border-2 border-green-300 bg-green-50 p-4">
            <p class="font-bold text-green-800">
                <i class="fas fa-circle-check mr-2"></i>
                {{ $criadasAgora }} configuração(ões) criada(s) para esta empresa
            </p>
            <p class="text-sm text-green-700 mt-1">
                Esta empresa não tinha configurações de RH — foram criadas agora com os valores
                da legislação laboral angolana. Confira-as antes do próximo processamento de salários:
                são elas que decidem o INSS, as isenções e o cálculo das horas extra.
            </p>
        </div>
    @endif

    {{-- Configurações por Categoria --}}
    @forelse($settings as $category => $categorySettings)
        <div class="bg-white rounded-2xl shadow-lg mb-6 overflow-hidden border-l-4 
            @if($category === 'general') border-blue-500
            @elseif($category === 'worktime') border-indigo-500
            @elseif($category === 'overtime') border-yellow-500
            @elseif($category === 'vacation') border-green-500
            @elseif($category === 'leave') border-purple-500
            @elseif($category === 'payroll') border-emerald-500
            @elseif($category === 'benefits') border-pink-500
            @endif">
            
            {{-- Header da Categoria --}}
            <div class="bg-gradient-to-r 
                @if($category === 'general') from-blue-50 to-indigo-50
                @elseif($category === 'worktime') from-indigo-50 to-purple-50
                @elseif($category === 'overtime') from-yellow-50 to-orange-50
                @elseif($category === 'vacation') from-green-50 to-emerald-50
                @elseif($category === 'leave') from-purple-50 to-pink-50
                @elseif($category === 'payroll') from-emerald-50 to-teal-50
                @elseif($category === 'benefits') from-pink-50 to-rose-50
                @endif 
                p-5 border-b border-gray-200">
                <h3 class="text-xl font-bold flex items-center
                    @if($category === 'general') text-blue-700
                    @elseif($category === 'worktime') text-indigo-700
                    @elseif($category === 'overtime') text-yellow-700
                    @elseif($category === 'vacation') text-green-700
                    @elseif($category === 'leave') text-purple-700
                    @elseif($category === 'payroll') text-emerald-700
                    @elseif($category === 'benefits') text-pink-700
                    @endif">
                    @if($category === 'general')
                        <i class="fas fa-info-circle mr-3"></i>Configurações Gerais
                    @elseif($category === 'worktime')
                        <i class="fas fa-business-time mr-3"></i>Horário de Trabalho
                    @elseif($category === 'overtime')
                        <i class="fas fa-clock mr-3"></i>Horas Extras
                    @elseif($category === 'vacation')
                        <i class="fas fa-umbrella-beach mr-3"></i>Férias
                    @elseif($category === 'leave')
                        <i class="fas fa-calendar-times mr-3"></i>Licenças
                    @elseif($category === 'payroll')
                        <i class="fas fa-money-bill-wave mr-3"></i>Folha de Pagamento
                    @elseif($category === 'benefits')
                        <i class="fas fa-gift mr-3"></i>Subsídios
                    @endif
                </h3>
            </div>
            
            {{-- Corpo com Settings --}}
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($categorySettings as $setting)
                        @php
                            // Definições que ainda nenhum cálculo lê. Mostrá-las
                            // como campos vulgares é mentir: edita-se, grava, e
                            // não acontece nada — que é exactamente a impressão
                            // de "isto não funciona".
                            $informativa = in_array($setting->key, \App\Services\HR\DefinicoesRH::INFORMATIVAS, true);
                        @endphp
                        <div class="p-5 rounded-xl border transition-all {{ $informativa ? 'bg-amber-50/50 border-amber-200' : 'bg-gray-50 border-gray-200 hover:border-purple-300' }}">
                            <div class="mb-3">
                                @if($informativa)
                                    <span class="inline-flex items-center gap-1 mb-2 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-bold"
                                          title="O valor fica guardado, mas nenhum cálculo o usa ainda">
                                        <i class="fas fa-circle-info"></i> Registo apenas — ainda não entra no cálculo
                                    </span>
                                @endif
                                <label class="font-bold text-gray-800 flex items-center justify-between">
                                    <span>{{ $setting->label }}</span>
                                    <span class="text-xs px-2 py-1 rounded-full 
                                        @if($setting->value_type === 'boolean') bg-blue-100 text-blue-700
                                        @elseif($setting->value_type === 'percentage') bg-green-100 text-green-700
                                        @elseif($setting->value_type === 'integer') bg-purple-100 text-purple-700
                                        @else bg-gray-100 text-gray-700
                                        @endif">
                                        {{ $setting->value_type_name }}
                                    </span>
                                </label>
                                @if($setting->description)
                                    <p class="text-sm text-gray-600 mt-1">{{ $setting->description }}</p>
                                @endif
                            </div>

                            @if($setting->value_type === 'boolean')
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           class="sr-only peer" 
                                           wire:model.live="editingSettings.{{ $setting->key }}"
                                           wire:change="saveSetting('{{ $setting->key }}')"
                                           id="setting_{{ $setting->key }}">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                    <span class="ms-3 text-sm font-medium text-gray-700">
                                        {{ $setting->casted_value ? '✓ Sim' : '✗ Não' }}
                                    </span>
                                </label>
                            @elseif($setting->value_type === 'integer')
                                <div class="relative">
                                    <input type="number" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" 
                                           wire:model.blur="editingSettings.{{ $setting->key }}"
                                           wire:blur="saveSetting('{{ $setting->key }}')"
                                           wire:keydown.enter="saveSetting('{{ $setting->key }}')"
                                           value="{{ $setting->value }}"
                                           step="1"
                                           placeholder="{{ $setting->value }}">
                                    <div wire:loading wire:target="saveSetting('{{ $setting->key }}')" class="absolute right-3 top-3">
                                        <i class="fas fa-spinner fa-spin text-purple-600"></i>
                                    </div>
                                </div>
                            @elseif($setting->value_type === 'decimal')
                                <div class="relative">
                                    <input type="number" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" 
                                           wire:model.blur="editingSettings.{{ $setting->key }}"
                                           wire:blur="saveSetting('{{ $setting->key }}')"
                                           wire:keydown.enter="saveSetting('{{ $setting->key }}')"
                                           value="{{ $setting->value }}"
                                           step="0.01"
                                           min="0"
                                           placeholder="{{ $setting->value }}">
                                    <div wire:loading wire:target="saveSetting('{{ $setting->key }}')" class="absolute right-3 top-3">
                                        <i class="fas fa-spinner fa-spin text-purple-600"></i>
                                    </div>
                                </div>
                            @elseif($setting->value_type === 'percentage')
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <input type="number" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" 
                                               wire:model.blur="editingSettings.{{ $setting->key }}"
                                               wire:blur="saveSetting('{{ $setting->key }}')"
                                               wire:keydown.enter="saveSetting('{{ $setting->key }}')"
                                               value="{{ $setting->value }}"
                                               step="0.1"
                                               min="0"
                                               placeholder="{{ $setting->value }}">
                                        <div wire:loading wire:target="saveSetting('{{ $setting->key }}')" class="absolute right-3 top-3">
                                            <i class="fas fa-spinner fa-spin text-purple-600"></i>
                                        </div>
                                    </div>
                                    <span class="px-3 py-2 bg-purple-100 text-purple-700 rounded-lg font-semibold">%</span>
                                </div>
                            @else
                                <div class="relative">
                                    <input type="text" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" 
                                           wire:model.blur="editingSettings.{{ $setting->key }}"
                                           wire:blur="saveSetting('{{ $setting->key }}')"
                                           wire:keydown.enter="saveSetting('{{ $setting->key }}')"
                                           value="{{ $setting->value }}"
                                           placeholder="{{ $setting->value }}">
                                    <div wire:loading wire:target="saveSetting('{{ $setting->key }}')" class="absolute right-3 top-3">
                                        <i class="fas fa-spinner fa-spin text-purple-600"></i>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>
                                        <strong>Atual:</strong> 
                                        @if($setting->value_type === 'boolean')
                                            {{ $setting->casted_value ? 'Sim' : 'Não' }}
                                        @elseif($setting->value_type === 'percentage')
                                            {{ $setting->value }}%
                                        @else
                                            {{ $setting->value }}
                                        @endif
                                    </span>
                                    <span>
                                        <strong>Padrão:</strong> 
                                        @if($setting->value_type === 'boolean')
                                            {{ $setting->default_value ? 'Sim' : 'Não' }}
                                        @elseif($setting->value_type === 'percentage')
                                            {{ $setting->default_value }}%
                                        @else
                                            {{ $setting->default_value }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        {{-- O ecrã era um @foreach sem saída vazia: quando não havia definições
             — que era o caso de todas as empresas menos a primeira — a página
             mostrava o cabeçalho, o filtro, e mais NADA. Sem lista, sem aviso,
             sem explicação. Lia-se como "não funciona". --}}
        <div class="bg-white rounded-2xl shadow-lg p-10 text-center mb-6">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-sliders-h text-gray-400 text-3xl"></i>
            </div>
            @if($categoryFilter !== 'all')
                <h3 class="font-bold text-gray-900 text-lg mb-1">Nenhuma configuração nesta categoria</h3>
                <p class="text-gray-500 text-sm mb-4">Escolha outra categoria ou veja todas.</p>
                <button wire:click="$set('categoryFilter', 'all')"
                        class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-semibold transition">
                    Ver todas as categorias
                </button>
            @else
                <h3 class="font-bold text-gray-900 text-lg mb-1">Sem configurações de RH</h3>
                <p class="text-gray-500 text-sm">
                    Não foi possível criar as configurações desta empresa. Comunique ao suporte —
                    entretanto o processamento de salários usa os valores por omissão do sistema.
                </p>
            @endif
        </div>
    @endforelse

    {{-- Informação sobre Legislação Angolana --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
        <h3 class="text-xl font-bold text-blue-800 mb-4 flex items-center">
            <i class="fas fa-balance-scale mr-3"></i>Legislação Trabalhista Angolana 2025
        </h3>
        <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-white p-5 rounded-xl shadow">
                <h4 class="font-bold text-green-700 mb-3 flex items-center">
                    <i class="fas fa-umbrella-beach mr-2"></i>Férias
                </h4>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start">
                        <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                        <span>22 dias úteis por ano completo de trabalho</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                        <span>Subsídio de férias (14º mês): mínimo 50% do salário base</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                        <span>Cálculo proporcional aos meses trabalhados</span>
                    </li>
                </ul>
            </div>

            <div class="bg-white p-5 rounded-xl shadow">
                <h4 class="font-bold text-yellow-700 mb-3 flex items-center">
                    <i class="fas fa-clock mr-2"></i>Horas Extras
                </h4>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        <span><strong>Dias úteis:</strong> 50% adicional sobre valor/hora normal</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        <span><strong>Fins de semana:</strong> 100% adicional</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        <span><strong>Feriados:</strong> 100% adicional</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        <span><strong>Trabalho noturno:</strong> 25% adicional (22h-6h)</span>
                    </li>
                </ul>
            </div>

            <div class="bg-white p-5 rounded-xl shadow">
                <h4 class="font-bold text-pink-700 mb-3 flex items-center">
                    <i class="fas fa-gift mr-2"></i>Subsídios Obrigatórios
                </h4>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start">
                        <i class="fas fa-check text-pink-600 mr-2 mt-1"></i>
                        <span><strong>Subsídio de Férias (14º):</strong> mínimo 50% do salário</span>
                    </li>
                </ul>
            </div>

            <div class="bg-white p-5 rounded-xl shadow">
                <h4 class="font-bold text-purple-700 mb-3 flex items-center">
                    <i class="fas fa-calendar-alt mr-2"></i>Licenças Previstas em Lei
                </h4>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start">
                        <i class="fas fa-check text-purple-600 mr-2 mt-1"></i>
                        <span><strong>Maternidade:</strong> 90 dias (3 meses)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-purple-600 mr-2 mt-1"></i>
                        <span><strong>Paternidade:</strong> 3 dias</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-purple-600 mr-2 mt-1"></i>
                        <span><strong>Casamento:</strong> 10 dias</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-purple-600 mr-2 mt-1"></i>
                        <span><strong>Luto (familiar direto):</strong> 5 dias</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Botão final de salvar --}}
    <div class="mt-6 flex justify-end gap-3">
        <div class="text-sm text-gray-600 flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            <span>As configurações são salvas automaticamente ao sair do campo ou pressionar Enter</span>
        </div>
        <button wire:click="save" 
                wire:loading.attr="disabled"
                class="px-8 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-bold transition-all shadow-lg hover:shadow-xl transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="save">
                <i class="fas fa-save mr-2"></i>Salvar Todas as Alterações Pendentes
            </span>
            <span wire:loading wire:target="save">
                <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
            </span>
        </button>
    </div>

    {{-- Toast de Notificação --}}
    <div x-data="{ 
        show: false, 
        message: '',
        isError: false,
        init() {
            Livewire.on('notify', (event) => {
                this.showToast(event.message || event[0]?.message);
            });
        },
        showToast(msg) {
            this.message = msg;
            this.isError = msg.includes('✗') || msg.toLowerCase().includes('erro');
            this.show = true;
            setTimeout(() => this.show = false, 2500);
        }
    }"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform translate-y-2"
         class="fixed bottom-4 right-4 bg-white text-gray-800 px-6 py-3 rounded-lg shadow-2xl flex items-center gap-3 z-50 border-t-4"
         :class="isError ? 'border-red-500' : 'border-blue-500'"
         style="display: none;">
        <i :class="isError ? 'fas fa-exclamation-circle text-red-500' : 'fas fa-check-circle text-blue-500'" class="text-xl"></i>
        <span class="font-semibold" x-text="message"></span>
    </div>

    {{-- Estilos para animação de salvamento --}}
    <style>
        @keyframes pulse-green {
            0%, 100% {
                border-color: rgb(34, 197, 94);
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            }
            50% {
                border-color: rgb(34, 197, 94);
                box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
            }
        }

        .save-pulse {
            animation: pulse-green 0.6s ease-out;
        }

        input:focus {
            outline: none;
        }
    </style>

    {{-- Script para adicionar efeito visual ao salvar --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('setting-saved', (event) => {
                const key = event.key;
                const inputs = document.querySelectorAll(`[wire\\:blur*="${key}"]`);
                inputs.forEach(input => {
                    input.classList.add('save-pulse');
                    setTimeout(() => input.classList.remove('save-pulse'), 600);
                });
            });
        });
    </script>
</div>
