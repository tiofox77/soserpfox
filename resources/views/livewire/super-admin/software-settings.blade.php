<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-8 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-cogs mr-3"></i>
                        Configurações do Software
                    </h1>
                    <p class="mt-1 text-indigo-200 text-sm">Configure bloqueios, restrições e regras globais do sistema por módulo</p>
                </div>
                <span class="px-4 py-2 bg-white/20 rounded-xl text-sm font-bold backdrop-blur-sm">
                    <i class="fas fa-shield-alt mr-1"></i> Super Admin
                </span>
            </div>
        </div>

        {{-- Toast --}}
        @if (session()->has('message'))
        <div class="mb-6 p-4 rounded-xl shadow-sm border
            {{ session('message-type') === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 
               (session('message-type') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800') }}">
            <div class="flex items-center">
                <i class="fas fa-{{ session('message-type') === 'success' ? 'check-circle text-green-500' : (session('message-type') === 'error' ? 'exclamation-triangle text-red-500' : 'info-circle text-blue-500') }} mr-3"></i>
                <span class="text-sm font-medium">{{ session('message') }}</span>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {{-- Sidebar de Módulos --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-700 to-gray-900 px-5 py-4">
                        <h3 class="text-white font-bold text-sm flex items-center">
                            <i class="fas fa-th-large mr-2"></i> Módulos
                        </h3>
                    </div>
                    <div class="p-2">
                        <button wire:click="switchModule('invoicing')"
                                class="w-full text-left px-4 py-3 rounded-xl transition flex items-center group
                                {{ $activeModule === 'invoicing' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'hover:bg-gray-50 text-gray-600' }}">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center mr-3 shrink-0
                                {{ $activeModule === 'invoicing' ? 'bg-indigo-100' : 'bg-gray-100' }}">
                                <i class="fas fa-file-invoice {{ $activeModule === 'invoicing' ? 'text-indigo-600' : 'text-gray-400' }}"></i>
                            </div>
                            <div>
                                <span class="font-semibold text-sm block">Faturação</span>
                                <span class="text-[10px] {{ $activeModule === 'invoicing' ? 'text-indigo-400' : 'text-gray-400' }}">Documentos e bloqueios</span>
                            </div>
                        </button>

                        <button wire:click="switchModule('agt')"
                                class="w-full text-left px-4 py-3 rounded-xl transition flex items-center group mt-1
                                {{ $activeModule === 'agt' ? 'bg-orange-50 text-orange-700 border border-orange-200' : 'hover:bg-gray-50 text-gray-600' }}">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center mr-3 shrink-0
                                {{ $activeModule === 'agt' ? 'bg-orange-100' : 'bg-gray-100' }}">
                                <i class="fas fa-file-signature {{ $activeModule === 'agt' ? 'text-orange-600' : 'text-gray-400' }}"></i>
                            </div>
                            <div>
                                <span class="font-semibold text-sm block">Faturação Eletrónica</span>
                                <span class="text-[10px] {{ $activeModule === 'agt' ? 'text-orange-400' : 'text-gray-400' }}">AGT / Decreto 71/25</span>
                            </div>
                        </button>

                        <div class="px-4 py-3 mt-1 text-gray-300 flex items-center cursor-not-allowed">
                            <div class="w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center mr-3 shrink-0">
                                <i class="fas fa-boxes text-gray-300"></i>
                            </div>
                            <div>
                                <span class="font-semibold text-sm block">Inventário</span>
                                <span class="text-[10px]">Em breve</span>
                            </div>
                        </div>

                        <div class="px-4 py-3 mt-1 text-gray-300 flex items-center cursor-not-allowed">
                            <div class="w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center mr-3 shrink-0">
                                <i class="fas fa-users text-gray-300"></i>
                            </div>
                            <div>
                                <span class="font-semibold text-sm block">Utilizadores</span>
                                <span class="text-[10px]">Em breve</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Info --}}
                <div class="mt-4 bg-amber-50 border border-amber-200 rounded-2xl p-4">
                    <h4 class="text-amber-800 font-semibold text-xs flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Atenção
                    </h4>
                    <p class="text-amber-700 text-xs mt-1 leading-relaxed">
                        As configurações de <strong>Faturação</strong> aplicam-se a todos os tenants.
                        As configurações de <strong>Faturação Eletrónica</strong> são por tenant (cada empresa configura a sua).
                    </p>
                </div>
            </div>

            {{-- Conteúdo Principal --}}
            <div class="lg:col-span-3">

                {{-- ═══════════════════════════════════════════ --}}
                {{-- MÓDULO: FATURAÇÃO --}}
                {{-- ═══════════════════════════════════════════ --}}
                @if($activeModule === 'invoicing')
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-500 px-6 py-4">
                        <h2 class="text-white font-bold text-lg flex items-center">
                            <i class="fas fa-file-invoice mr-2"></i>
                            Módulo de Faturação
                        </h2>
                        <p class="text-indigo-200 text-xs mt-1">Controle de eliminação de documentos fiscais</p>
                    </div>

                    <div class="p-6">
                        <form wire:submit.prevent="saveSettings">
                            {{-- Bloqueio de Eliminação --}}
                            <div class="mb-6">
                                <h3 class="text-gray-800 font-bold text-sm flex items-center mb-1">
                                    <i class="fas fa-lock mr-2 text-red-500"></i>
                                    Bloqueio de Eliminação de Documentos
                                </h3>
                                <p class="text-gray-500 text-xs mb-5">
                                    Ao ativar, os utilizadores não poderão eliminar os documentos. Apenas anulações serão permitidas.
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @php
                                        $docTypes = [
                                            ['prop' => 'block_delete_sales_invoice', 'label' => 'Faturas de Venda', 'icon' => 'file-invoice', 'desc' => 'Sales Invoices'],
                                            ['prop' => 'block_delete_proforma', 'label' => 'Proformas', 'icon' => 'file-alt', 'desc' => 'Proformas de Venda'],
                                            ['prop' => 'block_delete_receipt', 'label' => 'Recibos', 'icon' => 'receipt', 'desc' => 'Recibos de Pagamento'],
                                            ['prop' => 'block_delete_credit_note', 'label' => 'Notas de Crédito', 'icon' => 'file-invoice-dollar', 'desc' => 'Credit Notes'],
                                            ['prop' => 'block_delete_debit_note', 'label' => 'Notas de Débito', 'icon' => 'file-circle-plus', 'desc' => 'Debit Notes'],
                                            ['prop' => 'block_delete_invoice_receipt', 'label' => 'Faturas Recibo', 'icon' => 'file-contract', 'desc' => 'Invoice Receipts'],
                                            ['prop' => 'block_delete_pos_invoice', 'label' => 'Faturas POS', 'icon' => 'cash-register', 'desc' => 'Ponto de Venda'],
                                        ];
                                    @endphp

                                    @foreach($docTypes as $doc)
                                    <div class="border rounded-xl p-4 transition hover:shadow-md
                                        {{ $this->{$doc['prop']} ? 'border-red-200 bg-red-50/50' : 'border-gray-200 bg-white' }}">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0
                                                    {{ $this->{$doc['prop']} ? 'bg-red-100' : 'bg-gray-100' }}">
                                                    <i class="fas fa-{{ $doc['icon'] }} {{ $this->{$doc['prop']} ? 'text-red-500' : 'text-gray-400' }}"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <span class="font-semibold text-sm text-gray-800 block">{{ $doc['label'] }}</span>
                                                    <span class="text-[10px] text-gray-400">{{ $doc['desc'] }}</span>
                                                </div>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" wire:model="{{$doc['prop']}}" class="sr-only peer">
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                                            </label>
                                        </div>
                                        <div class="mt-2">
                                            @if($this->{$doc['prop']})
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">
                                                    <i class="fas fa-lock mr-1"></i> Bloqueado
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">
                                                    <i class="fas fa-unlock mr-1"></i> Permitido
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Aviso --}}
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5 mr-3"></i>
                                    <div>
                                        <p class="text-amber-800 text-sm font-medium">Estas configurações afetam todos os tenants</p>
                                        <p class="text-amber-700 text-xs mt-1">Documentos bloqueados só podem ser anulados, não eliminados.</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Botões --}}
                            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                                <button type="button" wire:click="resetSettings"
                                        class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                                    <i class="fas fa-undo mr-2"></i> Resetar
                                </button>
                                <button type="submit"
                                        class="px-6 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-indigo-500 to-purple-500 rounded-xl hover:from-indigo-600 hover:to-purple-600 transition shadow-lg shadow-indigo-200">
                                    <i class="fas fa-save mr-2"></i> Salvar Configurações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif

                {{-- ═══════════════════════════════════════════ --}}
                {{-- MÓDULO: FATURAÇÃO ELETRÓNICA (AGT) --}}
                {{-- ═══════════════════════════════════════════ --}}
                @if($activeModule === 'agt')
                <div class="space-y-6">
                    {{-- Cabeçalho --}}
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-orange-500 to-red-500 px-6 py-4">
                            <h2 class="text-white font-bold text-lg flex items-center">
                                <i class="fas fa-file-signature mr-2"></i>
                                Faturação Eletrónica — AGT Angola
                            </h2>
                            <p class="text-orange-200 text-xs mt-1">Decreto Presidencial n.º 71/25 — Regime Jurídico das Facturas</p>
                        </div>

                        <div class="p-6">
                            {{-- Info sobre Decreto 71/25 --}}
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                                <div class="flex items-start">
                                    <i class="fas fa-gavel text-blue-500 mt-0.5 mr-3"></i>
                                    <div>
                                        <p class="text-blue-800 text-sm font-bold">Decreto Presidencial n.º 71/25 (20 Mar 2025)</p>
                                        <p class="text-blue-700 text-xs mt-1 leading-relaxed">
                                            A faturação eletrónica é obrigatória em Angola. As credenciais Basic Auth do
                                            <strong>produtor SOS ERP</strong> são globais e geridas apenas nesta área.
                                            Cada empresa configura somente os seus dados de contribuinte em
                                            <strong>Faturação → AGT Angola</strong>.
                                        </p>
                                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <div class="bg-blue-100 rounded-lg p-2">
                                                <span class="text-blue-800 text-xs font-bold">Fase 1 — 1 Jan 2026</span>
                                                <p class="text-blue-600 text-[10px]">Grandes Contribuintes + Fornecedores do Estado</p>
                                            </div>
                                            <div class="bg-blue-100 rounded-lg p-2">
                                                <span class="text-blue-800 text-xs font-bold">Fase 2 — 21 Set 2026</span>
                                                <p class="text-blue-600 text-[10px]">Todos os contribuintes (Regime Geral e Simplificado)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Status por Tenant --}}
                            <div class="border-t border-gray-100 pt-6 mb-6">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
                                    <div>
                                        <h3 class="text-gray-800 font-bold text-sm flex items-center">
                                            <i class="fas fa-key mr-2 text-orange-500"></i>
                                            Credenciais API do Produtor
                                        </h3>
                                        <p class="text-gray-500 text-xs mt-1">
                                            Basic Auth emitido pela AGT ao produtor SOS ERP. Cada empresa usa o conjunto
                                            do ambiente em que está — quem está em homologação usa o de homologação,
                                            quem está em produção usa o de produção.
                                        </p>
                                    </div>
                                    {{-- Três estados, não dois. Com dois, o recurso às
                                         partilhadas contava como "Configuradas" e o badge
                                         contradizia o aviso mesmo ao lado, que dizia que
                                         este ambiente não tinha credenciais próprias. --}}
                                    <div class="flex items-center gap-2 self-start">
                                        @php
                                            if ($credenciaisProprias) {
                                                $estilo = 'bg-green-100 text-green-700';
                                                $icone  = 'check-circle';
                                                $texto  = 'Próprias deste ambiente';
                                            } elseif ($hasGlobalCredentials) {
                                                $estilo = 'bg-amber-100 text-amber-700';
                                                $icone  = 'circle-exclamation';
                                                $texto  = 'A usar as partilhadas';
                                            } else {
                                                $estilo = 'bg-red-100 text-red-700';
                                                $icone  = 'times-circle';
                                                $texto  = 'Não configuradas';
                                            }
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap {{ $estilo }}">
                                            <i class="fas fa-{{ $icone }} mr-1.5"></i>
                                            {{ $texto }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Seletor de ambiente.
                                     Escolhe qual dos dois conjuntos se está a PREENCHER. Não muda
                                     o ambiente de nenhuma empresa — cada uma tem o seu, em
                                     Faturação › Definições AGT, e é ele que decide qual se usa. --}}
                                <div class="mb-5 rounded-xl border-2 {{ $produtorAmbiente === 'production' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                        <div class="flex-1">
                                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">A configurar</label>
                                            {{-- @selected é obrigatório aqui. Sem ele nenhuma opção
                                                 traz o estado no HTML, e cada vez que o Livewire
                                                 volta a desenhar o browser cai na primeira: o
                                                 seletor dizia "Homologação" com o servidor em
                                                 produção, e o que se via a seguir — credenciais,
                                                 chave — era do ambiente errado. --}}
                                            <select wire:model.live="produtorAmbiente"
                                                    class="w-full sm:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                                <option value="sandbox" @selected($produtorAmbiente === 'sandbox')>Homologação (sandbox)</option>
                                                <option value="production" @selected($produtorAmbiente === 'production')>Produção</option>
                                            </select>
                                        </div>
                                        <div class="text-xs {{ $produtorAmbiente === 'production' ? 'text-red-700' : 'text-amber-800' }} sm:max-w-md">
                                            @if($produtorAmbiente === 'production')
                                                <i class="fas fa-triangle-exclamation mr-1"></i>
                                                <strong>Produção.</strong> O que gravar aqui passa a assinar e a submeter
                                                documentos reais das empresas que estejam em produção.
                                            @else
                                                <i class="fas fa-flask mr-1"></i>
                                                <strong>Homologação.</strong> Só afecta as empresas em ambiente de testes.
                                            @endif

                                            @unless($credenciaisProprias)
                                                <p class="mt-2 pt-2 border-t border-current/20">
                                                    <i class="fas fa-circle-info mr-1"></i>
                                                    Este ambiente ainda não tem credenciais próprias e está a usar as
                                                    antigas, partilhadas com o outro ambiente. Preencha os dois campos
                                                    para as separar.
                                                </p>
                                            @endunless
                                        </div>
                                    </div>
                                </div>

                                <form wire:submit.prevent="saveAgtProducerCredentials">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-2">Username</label>
                                            {{-- O username herdado vai no placeholder, não no campo.
                                                 No campo parecia que este ambiente já estava
                                                 configurado; escondido de todo parecia que se tinha
                                                 perdido. É o que autentica hoje, e vê-se. --}}
                                            <input type="text" wire:model="agt_basic_username" autocomplete="off"
                                                   class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 text-sm"
                                                   placeholder="{{ $usernameHerdado !== '' ? $usernameHerdado . '  (partilhado — em uso)' : 'Username fornecido pela AGT' }}">
                                            @error('agt_basic_username') <span class="block text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
                                            @if($usernameHerdado !== '')
                                                <p class="mt-1 text-[11px] text-gray-500">
                                                    Em uso: <strong class="font-mono">{{ $usernameHerdado }}</strong>, das credenciais
                                                    partilhadas. Preencha os dois campos só para dar a este ambiente as suas.
                                                </p>
                                            @endif
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-2">Password</label>
                                            <div class="relative">
                                                <input type="{{ $showGlobalPassword ? 'text' : 'password' }}"
                                                       wire:model="agt_basic_password" autocomplete="new-password"
                                                       class="w-full px-4 py-3 pr-11 rounded-xl border border-gray-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 text-sm"
                                                       placeholder="{{ $hasGlobalCredentials ? 'Deixe vazio para manter a actual' : 'Password fornecida pela AGT' }}">
                                                <button type="button" wire:click="$toggle('showGlobalPassword')"
                                                        class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                                    <i class="fas fa-{{ $showGlobalPassword ? 'eye-slash' : 'eye' }}"></i>
                                                </button>
                                            </div>
                                            @error('agt_basic_password') <span class="block text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-3 mt-5 pt-4 border-t border-gray-100">
                                        @if($hasGlobalCredentials)
                                            <button type="button" wire:click="clearAgtProducerCredentials"
                                                    wire:confirm="Remover as credenciais globais? Nenhuma empresa poderá comunicar com a AGT."
                                                    class="px-4 py-2.5 text-xs font-bold text-red-600 bg-red-50 rounded-xl hover:bg-red-100 transition">
                                                <i class="fas fa-trash-alt mr-1"></i> Remover
                                            </button>
                                        @else
                                            <span></span>
                                        @endif
                                        <button type="submit"
                                                class="px-5 py-2.5 text-xs font-bold text-white bg-gradient-to-r from-orange-500 to-red-500 rounded-xl hover:from-orange-600 hover:to-red-600 transition shadow">
                                            <i class="fas fa-save mr-1.5"></i> Guardar Credenciais
                                        </button>
                                    </div>
                                </form>
                            </div>

                            {{-- Chave RSA do produtor: só estado.

                                 A chave já existe e é gerada fora daqui. Havia
                                 campos para colar um par novo, que não serviam
                                 para nada a não ser convidar a substituir, por
                                 engano, a chave que assina os documentos de
                                 todas as empresas ao mesmo tempo. --}}
                            <div class="mb-7 rounded-2xl border border-gray-200 bg-white p-5">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                                    <div>
                                        <h3 class="font-bold text-sm text-gray-900 flex items-center">
                                            <i class="fas fa-key mr-2 text-orange-500"></i>
                                            Chave RSA do Produtor de Software
                                        </h3>
                                        <p class="text-gray-500 text-xs mt-1">
                                            Assina a <code class="text-[11px] bg-gray-100 px-1 rounded">jwsSoftwareSignature</code>
                                            de todos os documentos, de todas as empresas. Já está instalada —
                                            aqui só se confirma qual é. As chaves do contribuinte configuram-se
                                            em cada empresa, em <strong>Facturação › Definições AGT</strong>.
                                        </p>
                                    </div>
                                    <span class="inline-flex self-start items-center px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap
                                        {{ $hasProducerKeys ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        <i class="fas fa-{{ $hasProducerKeys ? 'check-circle' : 'times-circle' }} mr-1.5"></i>
                                        {{ $hasProducerKeys ? 'Instalada' : 'Não encontrada' }}
                                    </span>
                                </div>

                                @if($hasProducerKeys && !empty($producerKeyInfo) && empty($producerKeyInfo['erro']))
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                        <div class="rounded-xl bg-gray-50 p-3">
                                            <p class="text-[10px] text-gray-500 uppercase tracking-wide">Tipo</p>
                                            <p class="font-bold text-gray-800 mt-0.5">{{ $producerKeyInfo['tipo'] }} {{ $producerKeyInfo['bits'] }} bits</p>
                                        </div>
                                        <div class="rounded-xl bg-gray-50 p-3">
                                            <p class="text-[10px] text-gray-500 uppercase tracking-wide">Actualizada</p>
                                            <p class="font-bold text-gray-800 mt-0.5">{{ $producerKeyInfo['actualizada'] }}</p>
                                        </div>
                                        <div class="rounded-xl bg-gray-50 p-3 col-span-2">
                                            <p class="text-[10px] text-gray-500 uppercase tracking-wide">Impressão digital (SHA-256)</p>
                                            <p class="font-mono text-[10px] text-gray-700 mt-0.5 break-all">{{ $producerKeyInfo['impressao'] }}</p>
                                        </div>
                                    </div>

                                    <p class="mt-3 text-[11px] text-gray-500">
                                        <i class="fas fa-folder-open mr-1"></i>
                                        <code class="bg-gray-100 px-1 rounded">storage/app/private/{{ $producerKeyInfo['caminho'] }}</code>
                                        @unless($producerKeyInfo['propria'])
                                            — partilhada com o outro ambiente.
                                        @endunless
                                    </p>
                                @elseif(!empty($producerKeyInfo['erro']))
                                    <div class="rounded-xl bg-red-50 border border-red-200 p-3 text-xs text-red-700">
                                        <i class="fas fa-triangle-exclamation mr-1"></i>
                                        A chave existe mas não foi possível lê-la: {{ $producerKeyInfo['erro'] }}
                                    </div>
                                @else
                                    {{-- "Não encontrada" com a chave a existir no servidor é
                                         quase sempre o caminho: o disco `local` aponta para
                                         storage/app/private desde o Laravel 11. --}}
                                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                                        <i class="fas fa-triangle-exclamation mr-1"></i>
                                        Nenhuma chave encontrada para
                                        <strong>{{ $produtorAmbiente === 'production' ? 'Produção' : 'Homologação' }}</strong>.
                                        Procurou-se em <code class="bg-white/70 px-1 rounded">storage/app/private/saft/{{ $produtorAmbiente }}/</code>
                                        e em <code class="bg-white/70 px-1 rounded">storage/app/private/saft/</code>.
                                        Se a chave existe no servidor noutro sítio — por exemplo em
                                        <code class="bg-white/70 px-1 rounded">storage/app/saft/</code>, o caminho antigo —
                                        é preciso movê-la, senão nenhum documento é assinado.
                                    </div>
                                @endif
                            </div>

                            {{-- Consola de testes AGT --}}
                            <div id="agt-test-console"
                                 x-data
                                 @agt-test-console-focus.window="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                                 class="mb-7 rounded-2xl border border-slate-200 bg-slate-50 overflow-hidden">
                                <div class="px-5 py-4 bg-slate-900 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <h3 class="font-bold text-sm flex items-center">
                                            <i class="fas fa-flask mr-2 text-orange-400"></i>
                                            Consola de Testes AGT
                                        </h3>
                                        <p class="text-slate-400 text-xs mt-1">Teste seguro e de leitura, sem emitir documentos nem alterar séries.</p>
                                    </div>
                                    <span class="inline-flex self-start items-center px-3 py-1.5 rounded-full text-[10px] font-bold bg-slate-800 border border-slate-700">
                                        <i class="fas fa-shield-alt mr-1.5 text-green-400"></i> Apenas Super Admin
                                    </span>
                                </div>

                                <div class="p-5 space-y-5">
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-2">Empresa usada no teste</label>
                                            <select wire:model.live="selectedAgtTenantId"
                                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-200">
                                                <option value="">Seleccione uma empresa</option>
                                                @foreach($tenantAgtStatus as $tenant)
                                                    {{-- @selected: sem ele o seletor volta à primeira
                                                         opção a cada redesenho e o teste corria contra
                                                         uma empresa diferente da que estava à vista. --}}
                                                    <option value="{{ $tenant['id'] }}" @selected((int) $selectedAgtTenantId === (int) $tenant['id'])>{{ $tenant['name'] }} — {{ $tenant['nif'] ?? 'sem NIF' }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-2">Ambiente a testar</label>
                                            <div class="grid grid-cols-2 gap-3">
                                                <label class="relative cursor-pointer">
                                                    <input type="radio" wire:model.live="agtTestEnvironment" value="sandbox" class="peer sr-only">
                                                    <span class="flex items-center p-3 rounded-xl border-2 border-slate-200 bg-white peer-checked:border-amber-400 peer-checked:bg-amber-50 transition">
                                                        <span class="w-9 h-9 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center mr-3">
                                                            <i class="fas fa-vial"></i>
                                                        </span>
                                                        <span>
                                                            <strong class="block text-xs text-slate-800">Sandbox</strong>
                                                            <small class="text-[10px] text-slate-500">Homologação e testes</small>
                                                        </span>
                                                    </span>
                                                </label>
                                                <label class="relative cursor-pointer">
                                                    <input type="radio" wire:model.live="agtTestEnvironment" value="production" class="peer sr-only">
                                                    <span class="flex items-center p-3 rounded-xl border-2 border-slate-200 bg-white peer-checked:border-red-500 peer-checked:bg-red-50 transition">
                                                        <span class="w-9 h-9 rounded-lg bg-red-100 text-red-600 flex items-center justify-center mr-3">
                                                            <i class="fas fa-broadcast-tower"></i>
                                                        </span>
                                                        <span>
                                                            <strong class="block text-xs text-slate-800">Produção</strong>
                                                            <small class="text-[10px] text-slate-500">Ambiente real AGT</small>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    @if($agtTestEnvironment === 'production')
                                        <div class="flex items-start p-3 rounded-xl bg-red-50 border border-red-200 text-red-800">
                                            <i class="fas fa-exclamation-triangle mt-0.5 mr-2"></i>
                                            <p class="text-xs"><strong>Produção seleccionada.</strong> O teste consulta a API real, mas não regista facturas nem solicita séries.</p>
                                        </div>
                                    @endif

                                    {{-- Checklist operacional --}}
                                    @php
                                        $checks = [
                                            ['key' => 'producer_credentials', 'label' => 'Basic Auth produtor', 'icon' => 'key'],
                                            ['key' => 'producer_rsa', 'label' => 'Chave RSA produtor', 'icon' => 'shield-alt'],
                                            ['key' => 'software_certificate', 'label' => 'Certificado software', 'icon' => 'certificate'],
                                            ['key' => 'tenant_nif', 'label' => 'NIF da empresa', 'icon' => 'id-card'],
                                            ['key' => 'contributor_rsa', 'label' => 'Chave do contribuinte', 'icon' => 'lock'],
                                            ['key' => 'active_series', 'label' => 'Série activa', 'icon' => 'list-ol'],
                                        ];
                                    @endphp
                                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                                        @foreach($checks as $check)
                                            @php $ready = (bool) ($agtReadiness[$check['key']] ?? false); @endphp
                                            <div class="p-3 rounded-xl border {{ $ready ? 'bg-green-50 border-green-200' : 'bg-white border-slate-200' }}">
                                                <i class="fas fa-{{ $check['icon'] }} {{ $ready ? 'text-green-600' : 'text-slate-300' }}"></i>
                                                <p class="text-[10px] font-bold mt-2 {{ $ready ? 'text-green-800' : 'text-slate-500' }}">{{ $check['label'] }}</p>
                                                <span class="text-[9px] {{ $ready ? 'text-green-600' : 'text-red-500' }}">{{ $ready ? 'Pronto' : 'Em falta' }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between pt-4 border-t border-slate-200">
                                        <p class="text-[11px] text-slate-500">
                                            URL efectiva:
                                            <code class="text-slate-700">{{ $agtTestEnvironment === 'production' ? \App\Services\AGT\AGTClient::PRODUCTION_URL : \App\Services\AGT\AGTClient::SANDBOX_URL }}</code>
                                        </p>
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <button type="button" wire:click="applyAgtEnvironmentToTenant"
                                                    wire:loading.attr="disabled"
                                                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-300 hover:bg-slate-100 disabled:opacity-50">
                                                <i class="fas fa-save mr-1.5"></i> Guardar ambiente na empresa
                                            </button>
                                            <button type="button" wire:click="testAgtConnection"
                                                    wire:loading.attr="disabled"
                                                    class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 shadow disabled:opacity-50">
                                                <span wire:loading.remove wire:target="testAgtConnection"><i class="fas fa-play mr-1.5"></i> Testar ligação</span>
                                                <span wire:loading wire:target="testAgtConnection"><i class="fas fa-circle-notch fa-spin mr-1.5"></i> A testar...</span>
                                            </button>
                                        </div>
                                    </div>

                                    @if(!empty($agtTestResult))
                                        <div class="rounded-xl border p-4 {{ ($agtTestResult['success'] ?? false) ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                                            <div class="flex items-start">
                                                <i class="fas fa-{{ ($agtTestResult['success'] ?? false) ? 'check-circle text-green-600' : 'times-circle text-red-600' }} text-xl mr-3"></i>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-bold {{ ($agtTestResult['success'] ?? false) ? 'text-green-800' : 'text-red-800' }}">
                                                        {{ ($agtTestResult['success'] ?? false) ? 'Ligação estabelecida' : 'Teste sem sucesso' }}
                                                    </p>
                                                    <p class="text-xs mt-1 {{ ($agtTestResult['success'] ?? false) ? 'text-green-700' : 'text-red-700' }}">
                                                        {{ $agtTestResult['message'] ?? $agtTestResult['error'] ?? 'Sem detalhe adicional.' }}
                                                    </p>
                                                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 text-[10px] text-slate-600">
                                                        <span><strong>Ambiente:</strong> {{ ucfirst($agtTestResult['environment'] ?? $agtTestEnvironment) }}</span>
                                                        <span><strong>HTTP:</strong> {{ $agtTestResult['http_status'] ?? 'N/A' }}</span>
                                                        <span><strong>Tempo:</strong> {{ $agtTestResult['elapsed_ms'] ?? 0 }} ms</span>
                                                        <span><strong>Teste:</strong> {{ $agtTestResult['tested_at'] ?? 'agora' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-800"><i class="fas fa-terminal mr-2 text-orange-500"></i>Operações oficiais AGT</h4>
                                            <p class="text-[11px] text-slate-500 mt-1">Consultas seguras no ambiente e empresa seleccionados. RegistarFactura só é executado pelo fluxo fiscal de emissão.</p>
                                        </div>

                                        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-2">
                                            @foreach([
                                                ['RegistarFactura', '/registarFactura', 'Emissão'],
                                                ['ObterEstado', '/obterEstado', 'Consulta'],
                                                ['ConsultarFactura', '/consultarFactura', 'Consulta'],
                                                ['ListarFacturas', '/listarFacturas', 'Consulta'],
                                            ] as $endpoint)
                                                <div class="rounded-xl border border-slate-200 p-3">
                                                    <div class="flex justify-between gap-2"><strong class="text-xs text-slate-800">{{ $endpoint[0] }}</strong><span class="text-[9px] font-bold {{ $endpoint[2] === 'Emissão' ? 'text-red-600' : 'text-green-600' }}">{{ $endpoint[2] }}</span></div>
                                                    <code class="block text-[9px] text-slate-500 mt-2 break-all">{{ $agtTestEnvironment === 'production' ? \App\Services\AGT\AGTClient::PRODUCTION_URL : \App\Services\AGT\AGTClient::SANDBOX_URL }}{{ $endpoint[1] }}</code>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="grid md:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 mb-1">Operação</label>
                                                <select wire:model.live="agtApiOperation" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm">
                                                    <option value="listarFacturas" @selected($agtApiOperation === 'listarFacturas')>Listar facturas por período</option>
                                                    <option value="consultarFactura" @selected($agtApiOperation === 'consultarFactura')>Consultar factura</option>
                                                    <option value="obterEstado" @selected($agtApiOperation === 'obterEstado')>Obter estado do pedido</option>
                                                </select>
                                            </div>
                                            @if($agtApiOperation === 'consultarFactura')
                                                <div><label class="block text-xs font-bold text-slate-700 mb-1">Número do documento</label><input wire:model="agtApiDocumentNo" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm" placeholder="FT 2026/1">@error('agtApiDocumentNo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                                            @elseif($agtApiOperation === 'obterEstado')
                                                <div><label class="block text-xs font-bold text-slate-700 mb-1">Request ID</label><input wire:model="agtApiRequestId" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm">@error('agtApiRequestId')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                                            @else
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div><label class="block text-xs font-bold text-slate-700 mb-1">De</label><input type="date" wire:model="agtApiDateFrom" class="w-full px-3 py-3 rounded-xl border border-slate-200 text-sm"></div>
                                                    <div><label class="block text-xs font-bold text-slate-700 mb-1">Até</label><input type="date" wire:model="agtApiDateTo" class="w-full px-3 py-3 rounded-xl border border-slate-200 text-sm"></div>
                                                </div>
                                            @endif
                                        </div>

                                        <button type="button" wire:click="runAgtApiOperation" wire:loading.attr="disabled"
                                                class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-slate-800 hover:bg-slate-900 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="runAgtApiOperation"><i class="fas fa-play mr-1.5"></i>Executar consulta</span>
                                            <span wire:loading wire:target="runAgtApiOperation"><i class="fas fa-circle-notch fa-spin mr-1.5"></i>A comunicar...</span>
                                        </button>

                                        @if(!empty($agtApiOperationResult))
                                            <div class="rounded-xl border p-4 {{ ($agtApiOperationResult['success'] ?? false) ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                                                <p class="text-xs font-bold {{ ($agtApiOperationResult['success'] ?? false) ? 'text-green-800' : 'text-red-800' }}">{{ ($agtApiOperationResult['success'] ?? false) ? 'Resposta válida da AGT' : 'Operação recusada ou com erro' }}</p>
                                                <p class="text-xs mt-1 text-slate-700">{{ $agtApiOperationResult['error'] ?? $agtApiOperationResult['message'] ?? 'Pedido processado.' }}</p>
                                                <div class="text-[10px] text-slate-500 mt-2">{{ $agtApiOperationResult['tested_at'] ?? '' }} · {{ $agtApiOperationResult['elapsed_ms'] ?? 0 }} ms · HTTP {{ $agtApiOperationResult['status'] ?? $agtApiOperationResult['http_status'] ?? 'N/A' }}</div>
                                                @if(isset($agtApiOperationResult['data']) || isset($agtApiOperationResult['response']))
                                                    <pre class="mt-3 p-3 rounded-lg bg-slate-900 text-green-300 text-[10px] overflow-auto max-h-72">{{ json_encode($agtApiOperationResult['data'] ?? $agtApiOperationResult['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <h3 class="text-gray-800 font-bold text-sm flex items-center mb-4">
                                <i class="fas fa-building mr-2 text-orange-500"></i>
                                Estado de Configuração por Empresa
                            </h3>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase">Empresa</th>
                                            <th class="text-center px-4 py-3 text-xs font-bold text-gray-500 uppercase">Ambiente</th>
                                            <th class="text-center px-4 py-3 text-xs font-bold text-gray-500 uppercase">Chave</th>
                                            <th class="text-center px-4 py-3 text-xs font-bold text-gray-500 uppercase">API Config.</th>
                                            <th class="text-center px-4 py-3 text-xs font-bold text-gray-500 uppercase">Séries</th>
                                            <th class="text-center px-4 py-3 text-xs font-bold text-gray-500 uppercase">Auto-Submit</th>
                                            <th class="text-center px-4 py-3 text-xs font-bold text-gray-500 uppercase">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($tenantAgtStatus as $tenant)
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center mr-3 shrink-0">
                                                        <i class="fas fa-building text-orange-500 text-xs"></i>
                                                    </div>
                                                    <div>
                                                        <span class="font-semibold text-gray-800 text-sm">{{ $tenant['name'] }}</span>
                                                        <span class="block text-[10px] text-gray-400">NIF: {{ $tenant['nif'] ?? 'N/A' }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($tenant['environment'] === 'production')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-700">
                                                        Produção
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-700">
                                                        Sandbox
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <i class="fas fa-{{ $tenant['contributor_key'] ? 'lock text-green-500' : 'unlock text-red-400' }}"
                                                   title="{{ $tenant['contributor_key'] ? 'Chave do contribuinte configurada' : 'Chave do contribuinte em falta' }}"></i>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($tenant['api_configured'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">
                                                        <i class="fas fa-check mr-1"></i> Sim
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">
                                                        <i class="fas fa-times mr-1"></i> Não
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="text-xs font-bold text-gray-700">{{ $tenant['series_count'] }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($tenant['auto_submit'])
                                                    <i class="fas fa-check-circle text-green-500"></i>
                                                @else
                                                    <i class="fas fa-minus-circle text-gray-300"></i>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="inline-flex items-center gap-2">
                                                    <button type="button" wire:click="prepareAgtTenantTest({{ $tenant['id'] }})"
                                                            class="text-xs text-blue-600 hover:text-blue-800 font-semibold">
                                                        <i class="fas fa-vial mr-1"></i> Testar
                                                    </button>
                                                    <a href="{{ route('invoicing.agt-settings') }}?tenant={{ $tenant['id'] }}"
                                                       class="text-xs text-orange-600 hover:text-orange-800 font-semibold">
                                                        <i class="fas fa-external-link-alt mr-1"></i> Abrir
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Nenhum tenant com configurações AGT encontrado.
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Chaves RSA Globais --}}
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-gray-700 to-gray-900 px-6 py-4">
                            <h3 class="text-white font-bold text-sm flex items-center">
                                <i class="fas fa-key mr-2 text-yellow-400"></i>
                                Chaves RSA (Globais)
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    @if($hasRsaKeys)
                                        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mr-4">
                                            <i class="fas fa-lock text-green-600 text-lg"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-green-700">Chaves RSA configuradas</p>
                                            <p class="text-xs text-gray-500">Assinatura SAFT-AO activa para todos os tenants</p>
                                        </div>
                                    @else
                                        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center mr-4">
                                            <i class="fas fa-unlock text-red-600 text-lg"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-red-700">Chaves RSA não configuradas</p>
                                            <p class="text-xs text-gray-500">Configure em SuperAdmin → SAFT para activar assinatura</p>
                                        </div>
                                    @endif
                                </div>
                                <a href="{{ route('superadmin.saft') }}"
                                   class="px-4 py-2 text-xs font-bold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
                                    <i class="fas fa-cog mr-1"></i> Configurar SAFT
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Conformidade --}}
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h3 class="text-gray-800 font-bold text-sm flex items-center">
                                <i class="fas fa-clipboard-check mr-2 text-green-500"></i>
                                Requisitos de Conformidade (Decreto 71/25)
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @php
                                    $checks = [
                                        ['label' => 'Hash SAFT com gross_total', 'done' => true, 'desc' => 'Art. 8º — Assinatura digital'],
                                        ['label' => 'Numeração AGT (FT/NC/ND)', 'done' => true, 'desc' => 'Art. 5º — Séries de documentos'],
                                        ['label' => 'Expressão obrigatória NC', 'done' => true, 'desc' => 'Art. 12º — Anulação/Rectificação'],
                                        ['label' => 'Data/Local de entrega', 'done' => true, 'desc' => 'Art. 6º — Facto tributário'],
                                        ['label' => 'Total por extenso', 'done' => true, 'desc' => 'Art. 9º — Valor em palavras'],
                                        ['label' => 'Protecção pós-finalização', 'done' => true, 'desc' => 'Art. 11º — Imutabilidade'],
                                        ['label' => 'Aviso prazo 5 dias', 'done' => true, 'desc' => 'Art. 7º — Emissão atempada'],
                                        ['label' => 'Campos SAFT obrigatórios', 'done' => true, 'desc' => 'Anexo I — net_total, tax_payable, etc.'],
                                    ];
                                @endphp

                                @foreach($checks as $check)
                                <div class="flex items-center p-3 rounded-xl {{ $check['done'] ? 'bg-green-50 border border-green-100' : 'bg-red-50 border border-red-100' }}">
                                    @if($check['done'])
                                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                    @else
                                        <i class="fas fa-times-circle text-red-500 mr-3"></i>
                                    @endif
                                    <div>
                                        <span class="text-xs font-bold {{ $check['done'] ? 'text-green-800' : 'text-red-800' }}">{{ $check['label'] }}</span>
                                        <span class="block text-[10px] {{ $check['done'] ? 'text-green-600' : 'text-red-600' }}">{{ $check['desc'] }}</span>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
