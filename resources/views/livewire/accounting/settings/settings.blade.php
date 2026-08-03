<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-gray-700 to-gray-900 rounded-xl shadow-lg p-6">
        <h1 class="text-3xl font-bold text-white flex items-center">
            <i class="fas fa-cog mr-3"></i>
            Configurações do Módulo
        </h1>
        <p class="text-gray-300 mt-2">Importar dados iniciais e configurar o módulo de contabilidade</p>
    </div>

    {{-- Messages --}}
    @if(session()->has('success'))
    <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
        <p class="text-green-800 flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            {{ session('success') }}
        </p>
    </div>
    @endif

    @if(session()->has('error'))
    <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
        <p class="text-red-800 flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            {{ session('error') }}
        </p>
    </div>
    @endif

    @if(session()->has('warning'))
    <div class="mb-4 p-4 bg-amber-50 border-l-4 border-amber-500 rounded-lg">
        <p class="text-amber-800 flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            {{ session('warning') }}
        </p>
    </div>
    @endif

    {{-- Sincronizar tudo: acrescenta o que falta em todas as áreas de uma vez --}}
    <div class="mb-6 bg-white rounded-xl shadow-lg border border-gray-200 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900 flex items-center mb-1">
                    <i class="fas fa-wand-magic-sparkles mr-2 text-emerald-600"></i>
                    Sincronizar Contabilidade
                </h3>
                <p class="text-sm text-gray-600">
                    Acrescenta apenas o que ainda não existe — contas, diários, impostos, centros de
                    custo, tipos de documento e períodos. <strong>Não altera nem apaga</strong> o que já tem.
                </p>
            </div>
            <button wire:click="syncAll"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    class="shrink-0 px-6 py-3 bg-gradient-to-r from-emerald-600 to-green-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                <span wire:loading.remove wire:target="syncAll">
                    <i class="fas fa-rotate mr-2"></i>Sincronizar Tudo
                </span>
                <span wire:loading wire:target="syncAll">
                    <i class="fas fa-spinner fa-spin mr-2"></i>A sincronizar...
                </span>
            </button>
        </div>
    </div>

    {{-- Integration Toggle --}}
    <div class="mb-6 bg-white rounded-xl shadow-lg border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-900 flex items-center mb-2">
                    <i class="fas fa-link mr-2 text-blue-600"></i>
                    Integração Automática
                </h3>
                <p class="text-sm text-gray-600 mb-2">
                    Criar lançamentos contabilísticos automaticamente a partir de faturas, recebimentos e pagamentos
                </p>
                <div class="flex items-start space-x-4 text-xs text-gray-500 mt-3">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-1"></i>
                        <span>Faturas → Clientes & Vendas</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-1"></i>
                        <span>Recebimentos → Caixa/Banco</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-1"></i>
                        <span>Pagamentos → Fornecedores</span>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col items-end ml-6">
                <label class="relative inline-flex items-center cursor-pointer mb-2">
                    <input type="checkbox" wire:click="toggleIntegration" 
                           @if($integrationEnabled) checked @endif 
                           class="sr-only peer">
                    <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-gradient-to-r peer-checked:from-blue-600 peer-checked:to-indigo-600"></div>
                </label>
                <span class="text-sm font-semibold {{ $integrationEnabled ? 'text-blue-600' : 'text-gray-500' }}">
                    {{ $integrationEnabled ? 'ATIVADA' : 'DESATIVADA' }}
                </span>
            </div>
        </div>
        
        @if(!$integrationEnabled)
        <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
            <p class="text-xs text-yellow-800">
                <i class="fas fa-info-circle mr-1"></i>
                Com a integração desativada, você precisará criar todos os lançamentos contabilísticos manualmente.
            </p>
        </div>
        @else
        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-3">
            <p class="text-xs text-blue-800">
                <i class="fas fa-bolt mr-1"></i>
                Lançamentos serão criados automaticamente e lançados quando você criar faturas, recebimentos ou pagamentos!
            </p>
        </div>
        @endif
    </div>

    {{-- Contas usadas pela integração --}}
    {{-- A resolução automática acerta na classe e no sinal, mas num plano
         importado de 1500+ contas aterra nos cabeçalhos ("31 CLIENTES" em vez
         de "311 Clientes correntes"). Só o contabilista sabe a conta exacta. --}}
    <div class="mb-6 bg-white rounded-xl shadow-lg border border-gray-200 p-6">
        <h3 class="text-lg font-bold text-gray-900 flex items-center mb-1">
            <i class="fas fa-right-left mr-2 text-indigo-600"></i>
            Contas dos Lançamentos Automáticos
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            As contas foram escolhidas automaticamente pela chave de integração do plano.
            Confirme-as — a sugestão acerta na classe, mas pode não ser a subconta que usa.
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Documento</th>
                        <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Diário</th>
                        <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Débito</th>
                        <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Crédito</th>
                        <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">IVA</th>
                        <th class="px-3 py-2 text-center text-xs font-bold text-gray-700">Estado</th>
                        <th class="px-3 py-2 text-right text-xs font-bold text-gray-700"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($eventos as $__ev => $__rotulo)
                    @php $__m = $mapeamentos[$__ev] ?? null; @endphp
                    <tr class="{{ $__m ? '' : 'bg-amber-50' }}">
                        <td class="px-3 py-3 font-semibold text-gray-900">{{ $__rotulo }}</td>
                        @if($__m)
                            <td class="px-3 py-3 text-gray-600">{{ optional($diarios->firstWhere('id', $__m->journal_id))->name ?? '—' }}</td>
                            @foreach(['debit_account_id', 'credit_account_id', 'vat_account_id'] as $__campo)
                            @php $__c = $contasDisponiveis->firstWhere('id', $__m->{$__campo}); @endphp
                            <td class="px-3 py-3 text-gray-600">
                                @if($__c)
                                    <span class="font-mono text-xs">{{ $__c->code }}</span> {{ $__c->name }}
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            @endforeach
                            <td class="px-3 py-3 text-center">
                                @if($__m->active)
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-bold">Activo</span>
                                @else
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-bold">Inactivo</span>
                                @endif
                                @if($__m->auto_post)
                                    <span class="block text-[10px] text-gray-400 mt-1">lança automaticamente</span>
                                @else
                                    <span class="block text-[10px] text-gray-400 mt-1">fica em rascunho</span>
                                @endif
                            </td>
                        @else
                            <td colspan="5" class="px-3 py-3 text-amber-700">
                                <i class="fas fa-triangle-exclamation mr-1"></i>
                                Sem mapeamento — este documento não gera lançamento
                            </td>
                        @endif
                        <td class="px-3 py-3 text-right">
                            <button wire:click="editarMapeamento('{{ $__ev }}')"
                                    class="px-3 py-1.5 bg-indigo-100 hover:bg-indigo-600 text-indigo-700 hover:text-white rounded-lg text-xs font-semibold transition">
                                <i class="fas fa-pen mr-1"></i>Configurar
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal de edição do mapeamento --}}
    @if(!empty($mapeamentoEmEdicao['event']))
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
            <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-lg font-bold text-white">
                    <i class="fas fa-right-left mr-2"></i>
                    {{ $eventos[$mapeamentoEmEdicao['event']] ?? '' }}
                </h3>
                <button wire:click="cancelarMapeamento" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Diário *</label>
                    <select wire:model="mapeamentoEmEdicao.journal_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">— escolher —</option>
                        @foreach($diarios as $__d)
                        <option value="{{ $__d->id }}">{{ $__d->code }} — {{ $__d->name }}</option>
                        @endforeach
                    </select>
                    @error('mapeamentoEmEdicao.journal_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                @foreach([
                    ['debit_account_id', 'Conta a débito *', 'Clientes na factura; Vendas na nota de crédito'],
                    ['credit_account_id', 'Conta a crédito *', 'Vendas na factura; Clientes na nota de crédito'],
                    ['vat_account_id', 'Conta de IVA', 'IVA liquidado nas vendas; dedutível nas compras'],
                ] as [$__campo, $__rot, $__ajuda])
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ $__rot }}</label>
                    <select wire:model="mapeamentoEmEdicao.{{ $__campo }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">— nenhuma —</option>
                        @foreach($contasDisponiveis as $__c)
                        <option value="{{ $__c->id }}">{{ $__c->code }} — {{ $__c->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">{{ $__ajuda }}</p>
                    @error('mapeamentoEmEdicao.' . $__campo) <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                @endforeach

                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="mapeamentoEmEdicao.active" class="mr-2 rounded">
                        Activo
                    </label>
                    <label class="flex items-center text-sm text-gray-700">
                        <input type="checkbox" wire:model="mapeamentoEmEdicao.auto_post" class="mr-2 rounded">
                        Lançar automaticamente (senão fica em rascunho)
                    </label>
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end gap-3">
                <button wire:click="cancelarMapeamento"
                        class="px-4 py-2 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                    Cancelar
                </button>
                <button wire:click="guardarMapeamento"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold transition">
                    <i class="fas fa-save mr-2"></i>Guardar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Manage Existing Data --}}
    {{-- Sempre visível: escondê-lo quando não havia contas tirava ao utilizador
         as contagens e a via de recuperação numa empresa vazia. --}}
    <div class="mb-6 bg-white rounded-xl shadow-lg border border-gray-200 p-6">
        <h3 class="text-lg font-bold text-gray-900 flex items-center mb-4">
            <i class="fas fa-tools mr-2 text-purple-600"></i>
            Gerir Dados Existentes
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Sincronizar Diários --}}
            <button wire:click="syncJournals"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    class="px-4 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                <span wire:loading.remove wire:target="syncJournals">
                    <i class="fas fa-sync-alt mr-2"></i>Sincronizar Diários (13)
                </span>
                <span wire:loading wire:target="syncJournals">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Sincronizando...
                </span>
            </button>
            
            {{-- Sincronizar Impostos --}}
            <button wire:click="syncTaxes"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    class="px-4 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                <span wire:loading.remove wire:target="syncTaxes">
                    <i class="fas fa-percent mr-2"></i>Sincronizar Impostos
                </span>
                <span wire:loading wire:target="syncTaxes">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Sincronizando...
                </span>
            </button>
            
            {{-- Sincronizar Centros de Custo --}}
            <button wire:click="syncCostCenters"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    class="px-4 py-3 bg-gradient-to-r from-indigo-600 to-blue-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                <span wire:loading.remove wire:target="syncCostCenters">
                    <i class="fas fa-building mr-2"></i>Sincronizar C. Custo
                </span>
                <span wire:loading wire:target="syncCostCenters">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Sincronizando...
                </span>
            </button>
            
            {{-- Apagar Todos: bloqueado quando já há lançamentos --}}
            @if($stats['moves'] > 0)
                <div class="px-4 py-3 bg-gray-100 border-2 border-dashed border-gray-300 text-gray-500 rounded-lg text-center text-sm">
                    <i class="fas fa-lock mr-1"></i>
                    Apagar bloqueado<br>
                    <span class="text-xs">{{ $stats['moves'] }} lançamento(s) registado(s)</span>
                </div>
            @else
                <button wire:click="deleteAllAccountingData"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        onclick="return confirm('⚠️ ATENÇÃO: isto apaga TODOS os dados contabilísticos (contas, diários, impostos e centros de custo não utilizados). É irreversível.\n\nSe só quer acrescentar o que falta, use antes \'Sincronizar Tudo\'.\n\nTem a certeza?')"
                        class="px-4 py-3 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                    <span wire:loading.remove wire:target="deleteAllAccountingData">
                        <i class="fas fa-trash-alt mr-2"></i>Apagar Tudo
                    </span>
                    <span wire:loading wire:target="deleteAllAccountingData">
                        <i class="fas fa-spinner fa-spin mr-2"></i>A apagar...
                    </span>
                </button>
            @endif
        </div>

        {{-- Contagens reais de tudo o que estes botões afectam --}}
        <div class="mt-4 grid grid-cols-2 md:grid-cols-6 gap-3">
            {{-- Classes literais: text-{{ $cor }}-600 não sobrevive ao purge do Tailwind --}}
            @foreach([
                ['Contas', $stats['accounts'], 'text-emerald-600'],
                ['Diários', $stats['journals'], 'text-blue-600'],
                ['Impostos', $stats['taxes'], 'text-purple-600'],
                ['C. Custo', $stats['costCenters'], 'text-indigo-600'],
                ['Períodos', $stats['periods'], 'text-yellow-600'],
                ['Lançamentos', $stats['moves'], 'text-gray-700'],
            ] as [$rotulo, $valor, $classeCor])
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <div class="text-xl font-bold {{ $classeCor }}">{{ $valor }}</div>
                    <div class="text-xs text-gray-500">{{ $rotulo }}</div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 bg-purple-50 border border-purple-200 rounded-lg p-3">
            <p class="text-xs text-purple-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Sincronizar:</strong> acrescenta apenas o que falta — nunca altera nem apaga o que já tem.
                <strong>Apagar Tudo:</strong> só disponível enquanto não houver lançamentos; centros de custo em uso são preservados.
            </p>
        </div>
    </div>

    {{-- Import Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Plano de Contas --}}
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition">
            <div class="bg-gradient-to-r from-emerald-500 to-green-600 p-4">
                <h3 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-sitemap mr-2"></i>
                    Plano de Contas
                </h3>
            </div>
            
            <div class="p-6">
                <div class="mb-4">
                    <p class="text-gray-600 text-sm mb-3">Plano de Contas PGC-AO (Plano Geral de Contabilidade de Angola)</p>

                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Contas Existentes:</span>
                            <span class="text-2xl font-bold text-emerald-600">{{ $stats['accounts'] }}</span>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                        <p class="text-xs text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            @if($stats['accounts'] > 0)
                                Acrescenta só as contas do PGC-AO que ainda não tem. As suas contas
                                actuais ficam intactas.
                            @else
                                Inclui: Activo, Passivo, Capital Próprio, Rendimentos e Gastos
                            @endif
                        </p>
                    </div>
                </div>

                <button wire:click="importAccounts"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="w-full px-4 py-3 bg-gradient-to-r from-emerald-600 to-green-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                    <span wire:loading.remove wire:target="importAccounts">
                        <i class="fas fa-download mr-2"></i>{{ $stats['accounts'] > 0 ? 'Sincronizar Contas' : 'Importar Plano de Contas' }}
                    </span>
                    <span wire:loading wire:target="importAccounts">
                        <i class="fas fa-spinner fa-spin mr-2"></i>A sincronizar...
                    </span>
                </button>
            </div>
        </div>

        {{-- Diários --}}
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-4">
                <h3 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-book mr-2"></i>
                    Diários Contabilísticos
                </h3>
            </div>
            
            <div class="p-6">
                <div class="mb-4">
                    <p class="text-gray-600 text-sm mb-3">Sincronizar 13 diários padrão para organização de lançamentos contabilísticos</p>
                    
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Diários Existentes:</span>
                            <span class="text-2xl font-bold text-blue-600">{{ $stats['journals'] }}</span>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                        <p class="text-xs text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            Geral, Caixa, Banco, Vendas, Compras, Salários, IVA, Depreciações, Op. Diversas, Ajustes, Regularização, Abertura, Encerramento
                        </p>
                    </div>
                </div>

                <button wire:click="syncJournals"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="w-full px-4 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                    <span wire:loading.remove wire:target="syncJournals">
                        <i class="fas fa-sync-alt mr-2"></i>Sincronizar Diários
                    </span>
                    <span wire:loading wire:target="syncJournals">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Sincronizando...
                    </span>
                </button>
            </div>
        </div>

        {{-- Períodos --}}
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition">
            <div class="bg-gradient-to-r from-yellow-500 to-orange-600 p-4">
                <h3 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    Períodos Contabilísticos
                </h3>
            </div>
            
            <div class="p-6">
                <div class="mb-4">
                    <p class="text-gray-600 text-sm mb-3">Períodos mensais do exercício. Escolha o ano — em Dezembro já pode abrir o ano seguinte.</p>

                    {{-- Selector de exercício: sem ele era impossível criar os
                         períodos do ano seguinte antes de ele começar. --}}
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Exercício</label>
                    <select wire:model.live="anoPeriodos"
                            class="w-full mb-3 px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200">
                        <option value="{{ $anoAtual - 1 }}">{{ $anoAtual - 1 }} (anterior)</option>
                        <option value="{{ $anoAtual }}">{{ $anoAtual }} (corrente)</option>
                        <option value="{{ $anoAtual + 1 }}">{{ $anoAtual + 1 }} (seguinte)</option>
                    </select>

                    <div class="bg-gray-50 rounded-lg p-3 mb-4 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Em {{ $anoPeriodos }}:</span>
                            <span class="text-2xl font-bold text-yellow-600">{{ $stats['periodsAno'] }}<span class="text-sm text-gray-400">/12</span></span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>Total (todos os anos)</span>
                            <span class="font-semibold">{{ $stats['periods'] }}</span>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                        <p class="text-xs text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            @if($stats['periodsAno'] >= 12)
                                {{ $anoPeriodos }} está completo. Escolha outro exercício para criar os que faltam.
                            @else
                                Cria os {{ 12 - $stats['periodsAno'] }} mês(es) em falta de {{ $anoPeriodos }}. Períodos já fechados não são tocados.
                            @endif
                        </p>
                    </div>
                </div>

                <button wire:click="importPeriods"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="w-full px-4 py-3 bg-gradient-to-r from-yellow-600 to-orange-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                    <span wire:loading.remove wire:target="importPeriods">
                        <i class="fas fa-download mr-2"></i>Sincronizar {{ $anoPeriodos }}
                    </span>
                    <span wire:loading wire:target="importPeriods">
                        <i class="fas fa-spinner fa-spin mr-2"></i>A sincronizar...
                    </span>
                </button>
            </div>
        </div>

        {{-- Tipos de Documentos --}}
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition">
            <div class="bg-gradient-to-r from-purple-500 to-pink-600 p-4">
                <h3 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-file-alt mr-2"></i>
                    Tipos de Documentos
                </h3>
            </div>
            
            <div class="p-6">
                <div class="mb-4">
                    <p class="text-gray-600 text-sm mb-3">Importar 63 tipos de documentos contabilísticos do Excel (Abertura, Caixa, Facturas, etc.)</p>
                    
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Tipos Existentes:</span>
                            <span class="text-2xl font-bold text-purple-600">{{ $stats['documentTypes'] ?? 0 }}</span>
                        </div>
                    </div>

                    @if(($stats['documentTypes'] ?? 0) > 0)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                            <p class="text-xs text-yellow-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                Já existem tipos cadastrados. Sincronizar adicionará novos tipos sem duplicar.
                            </p>
                        </div>
                    @else
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                            <p class="text-xs text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                Inclui: Abertura, Caixa AKZ/USD, Facturas, Vendas, Compras, Salários, IVA, etc.
                            </p>
                        </div>
                    @endif
                </div>

                <button wire:click="importDocumentTypes"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="w-full px-4 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                    <span wire:loading.remove wire:target="importDocumentTypes">
                        <i class="fas fa-download mr-2"></i>Importar Tipos de Documentos
                    </span>
                    <span wire:loading wire:target="importDocumentTypes">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Importando...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Info Section --}}
    <div class="mt-6 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-lightbulb text-blue-600 text-3xl"></i>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Dica Importante</h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-600 mr-2 mt-1"></i>
                        <span>Importe os dados na ordem: <strong>Plano de Contas → Diários → Tipos de Documentos → Períodos</strong></span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-600 mr-2 mt-1"></i>
                        <span>Cada importação só pode ser executada <strong>uma vez</strong> para evitar duplicações</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-600 mr-2 mt-1"></i>
                        <span>Após importar, você pode criar contas, diários e períodos adicionais manualmente</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-600 mr-2 mt-1"></i>
                        <span>Os dados importados seguem o padrão <strong>SNC Angola</strong> (Sistema de Normalização Contabilística)</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
