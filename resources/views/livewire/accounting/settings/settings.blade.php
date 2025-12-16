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

    {{-- Manage Existing Data --}}
    @if($stats['accounts'] > 0 || $stats['journals'] > 0)
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
            
            {{-- Apagar Todos --}}
            <button wire:click="deleteAllAccountingData"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    onclick="return confirm('⚠️ ATENÇÃO: Isto irá apagar TODOS os dados contabilísticos (contas, diários, impostos, centros de custo). Esta ação é irreversível!\n\nTem certeza que deseja continuar?')"
                    class="px-4 py-3 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-lg hover:shadow-lg transition font-semibold">
                <span wire:loading.remove wire:target="deleteAllAccountingData">
                    <i class="fas fa-trash-alt mr-2"></i>Apagar Tudo
                </span>
                <span wire:loading wire:target="deleteAllAccountingData">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Apagando...
                </span>
            </button>
        </div>
        
        <div class="mt-4 bg-purple-50 border border-purple-200 rounded-lg p-3">
            <p class="text-xs text-purple-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Sincronizar:</strong> Atualiza/cria registos padrão sem apagar dados existentes. 
                <strong>Apagar Tudo:</strong> Remove todos os dados (apenas se não houver lançamentos).
            </p>
        </div>
    </div>
    @endif

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
                    <p class="text-gray-600 text-sm mb-3">Importar 71 contas do Plano de Contas SNC (Sistema de Normalização Contabilística de Angola)</p>
                    
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Contas Existentes:</span>
                            <span class="text-2xl font-bold text-emerald-600">{{ $stats['accounts'] }}</span>
                        </div>
                    </div>

                    @if($stats['accounts'] > 0)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                            <p class="text-xs text-yellow-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                Já existem contas cadastradas. Não é possível importar novamente.
                            </p>
                        </div>
                    @else
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                            <p class="text-xs text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                Inclui: Ativo, Passivo, Capital Próprio, Rendimentos e Gastos
                            </p>
                        </div>
                    @endif
                </div>

                <button wire:click="importAccounts"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        @if($stats['accounts'] > 0) disabled class="w-full px-4 py-3 bg-gray-300 text-gray-500 rounded-lg font-semibold cursor-not-allowed" @else class="w-full px-4 py-3 bg-gradient-to-r from-emerald-600 to-green-600 text-white rounded-lg hover:shadow-lg transition font-semibold" @endif>
                    <span wire:loading.remove wire:target="importAccounts">
                        <i class="fas fa-download mr-2"></i>Importar Plano de Contas
                    </span>
                    <span wire:loading wire:target="importAccounts">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Importando...
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
                    <p class="text-gray-600 text-sm mb-3">Importar 12 períodos mensais para o ano atual ({{ now()->year }})</p>
                    
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Períodos Existentes:</span>
                            <span class="text-2xl font-bold text-yellow-600">{{ $stats['periods'] }}</span>
                        </div>
                    </div>

                    @if($stats['periods'] >= 12)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                            <p class="text-xs text-yellow-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                Já existem 12 períodos cadastrados. Não é possível importar novamente.
                            </p>
                        </div>
                    @else
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                            <p class="text-xs text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                Janeiro a Dezembro de {{ now()->year }}
                            </p>
                        </div>
                    @endif
                </div>

                <button wire:click="importPeriods"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        @if($stats['periods'] >= 12) disabled class="w-full px-4 py-3 bg-gray-300 text-gray-500 rounded-lg font-semibold cursor-not-allowed" @else class="w-full px-4 py-3 bg-gradient-to-r from-yellow-600 to-orange-600 text-white rounded-lg hover:shadow-lg transition font-semibold" @endif>
                    <span wire:loading.remove wire:target="importPeriods">
                        <i class="fas fa-download mr-2"></i>Importar Períodos
                    </span>
                    <span wire:loading wire:target="importPeriods">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Importando...
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
