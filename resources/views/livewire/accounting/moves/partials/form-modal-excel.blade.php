<div class="fixed inset-0 bg-gray-900 bg-opacity-60 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeModal()">
    
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-7xl max-h-[95vh] overflow-hidden flex flex-col"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header Toolbar - Estilo Primavera --}}
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-4 py-3 flex items-center justify-between border-b-2 border-blue-700">
            <div class="flex items-center space-x-4">
                <div class="flex items-center bg-white/10 rounded-lg px-3 py-2">
                    <i class="fas fa-receipt text-white text-xl mr-2"></i>
                    <span class="text-white font-bold text-lg">Lançamento Contabilístico</span>
                </div>
                <div class="h-8 w-px bg-white/30"></div>
                <span class="text-blue-100 text-sm font-medium">Partidas Dobradas</span>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        {{-- Toolbar de Ações - Estilo Office/Primavera --}}
        <div class="bg-gray-50 border-b border-gray-300 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <button wire:click="save" 
                        wire:loading.attr="disabled"
                        class="flex items-center space-x-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow-md transition font-semibold">
                    <i class="fas fa-save"></i>
                    <span>Guardar</span>
                </button>
                <button type="button" wire:click="closeModal"
                        class="flex items-center space-x-2 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg shadow-md transition font-semibold">
                    <i class="fas fa-times"></i>
                    <span>Cancelar</span>
                </button>
                <div class="w-px h-8 bg-gray-300 mx-2"></div>
                <button type="button" wire:click="addLine"
                        class="flex items-center space-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-md transition font-semibold">
                    <i class="fas fa-plus"></i>
                    <span>Adicionar Linha</span>
                </button>
            </div>
            
            {{-- Status do Balancamento --}}
            <div class="flex items-center space-x-3">
                @php
                    $totalDebit = collect($lines)->sum('debit');
                    $totalCredit = collect($lines)->sum('credit');
                    $diff = $totalDebit - $totalCredit;
                    $balanced = abs($diff) < 0.01;
                @endphp
                
                <div class="flex items-center space-x-2 px-4 py-2 rounded-lg {{ $balanced ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    <i class="fas {{ $balanced ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i>
                    <span class="font-bold">{{ $balanced ? 'Balanceado' : 'Desbalanceado' }}</span>
                </div>
            </div>
        </div>

        <form wire:submit.prevent="save" class="flex-1 overflow-hidden flex flex-col">
            
            {{-- Cabeçalho do Documento - Estilo Compacto --}}
            <div class="bg-white border-b border-gray-300 px-4 py-3">
                <div class="grid grid-cols-5 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            <i class="fas fa-book text-blue-600"></i> Diário *
                        </label>
                        <select wire:model.live="journal_id" 
                                class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required>
                            <option value="">Selecione...</option>
                            @foreach($journals as $journal)
                                <option value="{{ $journal->id }}">{{ $journal->code }} - {{ $journal->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-purple-600"></i> Tipo de Documento
                        </label>
                        <select wire:model="document_type_id" 
                                class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Selecione...</option>
                            @foreach($documentTypes as $docType)
                                <option value="{{ $docType->id }}">{{ $docType->code }} - {{ $docType->description }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            <i class="fas fa-calendar text-green-600"></i> Período *
                        </label>
                        <select wire:model="period_id" 
                                class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required>
                            <option value="">Selecione...</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}">{{ $period->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            <i class="fas fa-calendar-day text-orange-600"></i> Data *
                        </label>
                        <input type="date" wire:model="date" 
                               class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            <i class="fas fa-hashtag text-indigo-600"></i> Referência *
                            @if($ref)
                                <span class="ml-2 text-xs font-normal text-green-600">
                                    <i class="fas fa-arrow-right"></i> Preview: <span class="font-mono font-bold">{{ $ref }}</span>
                                </span>
                            @endif
                        </label>
                        <input type="text" wire:model="ref" 
                               class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 font-mono text-gray-600"
                               placeholder="Selecione o diário para gerar..."
                               readonly
                               required>
                    </div>
                </div>

                <div class="mt-2">
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        <i class="fas fa-align-left text-gray-600"></i> Descrição
                    </label>
                    <input type="text" wire:model="narration" 
                           class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Descrição do lançamento...">
                </div>
            </div>

            {{-- Grid de Linhas - Estilo Excel/Primavera --}}
            <div class="flex-1 overflow-auto bg-gray-50">
                <div class="min-w-full inline-block align-middle">
                    <table class="min-w-full divide-y divide-gray-300 border-collapse">
                        {{-- Cabeçalho da Tabela --}}
                        <thead class="bg-gradient-to-r from-gray-700 to-gray-800 sticky top-0 z-10">
                            <tr>
                                <th class="px-2 py-2 text-left text-xs font-bold text-white uppercase tracking-wider border-r border-gray-600" style="width: 5%">
                                    #
                                </th>
                                <th class="px-2 py-2 text-left text-xs font-bold text-white uppercase tracking-wider border-r border-gray-600" style="width: 35%">
                                    <i class="fas fa-sitemap mr-1"></i> Conta
                                    <i class="fas fa-info-circle ml-1 text-blue-300 cursor-help" 
                                       title="Digite o código ou nome da conta para buscar"></i>
                                </th>
                                <th class="px-2 py-2 text-left text-xs font-bold text-white uppercase tracking-wider border-r border-gray-600" style="width: 25%">
                                    <i class="fas fa-align-left mr-1"></i> Descrição
                                </th>
                                <th class="px-2 py-2 text-right text-xs font-bold text-green-300 uppercase tracking-wider border-r border-gray-600" style="width: 15%">
                                    <i class="fas fa-arrow-up mr-1"></i> Débito (Kz)
                                </th>
                                <th class="px-2 py-2 text-right text-xs font-bold text-red-300 uppercase tracking-wider border-r border-gray-600" style="width: 15%">
                                    <i class="fas fa-arrow-down mr-1"></i> Crédito (Kz)
                                </th>
                                <th class="px-2 py-2 text-center text-xs font-bold text-white uppercase tracking-wider" style="width: 5%">
                                    <i class="fas fa-cog"></i>
                                </th>
                            </tr>
                        </thead>
                        
                        {{-- Corpo da Tabela --}}
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($lines as $index => $line)
                            <tr class="hover:bg-blue-50 transition {{ $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                                {{-- Número da Linha --}}
                                <td class="px-2 py-2 text-sm font-bold text-gray-600 text-center border-r border-gray-200">
                                    {{ $index + 1 }}
                                </td>
                                
                                {{-- Conta com Autocomplete --}}
                                <td class="px-2 py-1 border-r border-gray-200">
                                    <div x-data="{
                                        search: '',
                                        showDropdown: false,
                                        accounts: {{ json_encode($accounts->map(fn($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name])->values()) }},
                                        filteredAccounts: [],
                                        selectedAccount: null,
                                        highlightedIndex: 0,
                                        
                                        init() {
                                            this.filteredAccounts = this.accounts;
                                            @if(isset($line['account_id']) && $line['account_id'])
                                                let account = this.accounts.find(a => a.id == {{ $line['account_id'] }});
                                                if(account) {
                                                    this.search = account.code + ' - ' + account.name;
                                                    this.selectedAccount = account;
                                                }
                                            @endif
                                        },
                                        
                                        filterAccounts() {
                                            if(!this.search) {
                                                this.filteredAccounts = this.accounts;
                                                this.highlightedIndex = 0;
                                                return;
                                            }
                                            const searchLower = this.search.toLowerCase();
                                            this.filteredAccounts = this.accounts.filter(a => 
                                                a.code.toLowerCase().includes(searchLower) || 
                                                a.name.toLowerCase().includes(searchLower)
                                            ).slice(0, 10);
                                            this.highlightedIndex = 0;
                                        },
                                        
                                        selectAccount(account) {
                                            this.selectedAccount = account;
                                            this.search = account.code + ' - ' + account.name;
                                            this.showDropdown = false;
                                            $wire.set('lines.{{ $index }}.account_id', account.id);
                                        },
                                        
                                        selectHighlighted() {
                                            if(this.filteredAccounts.length > 0) {
                                                const account = this.filteredAccounts[this.highlightedIndex];
                                                this.selectAccount(account);
                                            }
                                        },
                                        
                                        navigateDown() {
                                            if(this.highlightedIndex < this.filteredAccounts.length - 1) {
                                                this.highlightedIndex++;
                                            }
                                        },
                                        
                                        navigateUp() {
                                            if(this.highlightedIndex > 0) {
                                                this.highlightedIndex--;
                                            }
                                        }
                                    }" 
                                    class="relative">
                                        <input type="text" 
                                               x-model="search"
                                               @input="filterAccounts()"
                                               @focus="showDropdown = true; filterAccounts()"
                                               @click.away="showDropdown = false"
                                               @keydown.enter.prevent="selectHighlighted()"
                                               @keydown.arrow-down.prevent="navigateDown(); showDropdown = true"
                                               @keydown.arrow-up.prevent="navigateUp()"
                                               @keydown.escape="showDropdown = false"
                                               class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('lines.'.$index.'.account_id') border-red-500 @enderror"
                                               placeholder="Digite código ou nome..."
                                               autocomplete="off">
                                        
                                        {{-- Dropdown de Sugestões --}}
                                        <div x-show="showDropdown && filteredAccounts.length > 0"
                                             x-transition
                                             class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                            <template x-for="(account, idx) in filteredAccounts" :key="account.id">
                                                <div @click="selectAccount(account)"
                                                     :class="idx === highlightedIndex ? 'bg-blue-500 text-white' : 'hover:bg-blue-100'"
                                                     class="px-3 py-2 cursor-pointer border-b border-gray-100 last:border-b-0 transition">
                                                    <div class="flex items-center justify-between">
                                                        <span class="font-mono font-bold" 
                                                              :class="idx === highlightedIndex ? 'text-white' : 'text-blue-700'"
                                                              x-text="account.code"></span>
                                                        <i class="fas fa-check-circle text-green-500" 
                                                           x-show="selectedAccount && selectedAccount.id === account.id && idx !== highlightedIndex"></i>
                                                        <i class="fas fa-arrow-right text-white" 
                                                           x-show="idx === highlightedIndex"></i>
                                                    </div>
                                                    <div class="text-xs mt-0.5"
                                                         :class="idx === highlightedIndex ? 'text-blue-100' : 'text-gray-600'"
                                                         x-text="account.name"></div>
                                                </div>
                                            </template>
                                        </div>
                                        
                                        {{-- Ícone de Busca --}}
                                        <div class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none">
                                            <i class="fas fa-search text-xs"></i>
                                        </div>
                                    </div>
                                </td>
                                
                                {{-- Descrição --}}
                                <td class="px-2 py-1 border-r border-gray-200">
                                    <input type="text" wire:model="lines.{{ $index }}.narration" 
                                           class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Descrição...">
                                </td>
                                
                                {{-- Débito --}}
                                <td class="px-2 py-1 border-r border-gray-200">
                                    <input type="number" wire:model="lines.{{ $index }}.debit" 
                                           step="0.01" min="0"
                                           class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-right font-mono font-bold text-green-700 @error('lines.'.$index.'.debit') border-red-500 @enderror"
                                           placeholder="0,00"
                                           required>
                                </td>
                                
                                {{-- Crédito --}}
                                <td class="px-2 py-1 border-r border-gray-200">
                                    <input type="number" wire:model="lines.{{ $index }}.credit" 
                                           step="0.01" min="0"
                                           class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-right font-mono font-bold text-red-700 @error('lines.'.$index.'.credit') border-red-500 @enderror"
                                           placeholder="0,00"
                                           required>
                                </td>
                                
                                {{-- Ações --}}
                                <td class="px-2 py-1 text-center">
                                    @if(count($lines) > 2)
                                    <button type="button" wire:click="removeLine({{ $index }})" 
                                            class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-100 rounded transition">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            
                            {{-- Linha de Totais --}}
                            <tr class="bg-gradient-to-r from-blue-100 to-indigo-100 border-t-2 border-blue-300 font-bold">
                                <td colspan="3" class="px-4 py-3 text-sm text-gray-800 text-right border-r border-blue-200">
                                    <i class="fas fa-calculator mr-2"></i> TOTAIS
                                </td>
                                <td class="px-2 py-3 text-right text-base font-bold text-green-700 border-r border-blue-200">
                                    {{ number_format(collect($lines)->sum('debit'), 2, ',', '.') }} Kz
                                </td>
                                <td class="px-2 py-3 text-right text-base font-bold text-red-700 border-r border-blue-200">
                                    {{ number_format(collect($lines)->sum('credit'), 2, ',', '.') }} Kz
                                </td>
                                <td></td>
                            </tr>
                            
                            {{-- Diferença --}}
                            <tr class="bg-gradient-to-r from-gray-100 to-gray-200">
                                <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-800 text-right">
                                    <i class="fas fa-balance-scale mr-2"></i> DIFERENÇA
                                </td>
                                <td colspan="2" class="px-2 py-3 text-center text-lg font-bold {{ abs($diff) < 0.01 ? 'text-green-600' : 'text-red-600' }}">
                                    @if(abs($diff) < 0.01)
                                        <i class="fas fa-check-circle mr-2"></i> 0,00 Kz (Balanceado)
                                    @else
                                        <i class="fas fa-exclamation-triangle mr-2"></i> {{ number_format($diff, 2, ',', '.') }} Kz (Desbalanceado)
                                    @endif
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </form>

        {{-- Footer com Info --}}
        <div class="bg-gradient-to-r from-gray-100 to-gray-200 px-4 py-2 border-t border-gray-300 flex items-center justify-between text-xs text-gray-600">
            <div class="flex items-center space-x-4">
                <span><i class="fas fa-list-ol mr-1"></i> {{ count($lines) }} linhas</span>
                <span class="text-gray-400">|</span>
                <span><i class="fas fa-info-circle mr-1"></i> Partidas Dobradas: Débito = Crédito</span>
            </div>
            <div>
                <span class="font-mono">{{ now()->format('d/m/Y H:i') }}</span>
            </div>
        </div>
    </div>
</div>
