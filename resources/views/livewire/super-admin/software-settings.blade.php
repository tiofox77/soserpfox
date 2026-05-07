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
                                            A faturação eletrónica é obrigatória em Angola. Cada tenant (empresa) configura as suas próprias credenciais da AGT em
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
                                                <a href="{{ route('invoicing.agt-settings') }}?tenant={{ $tenant['id'] }}"
                                                   class="text-xs text-orange-600 hover:text-orange-800 font-semibold">
                                                    <i class="fas fa-external-link-alt mr-1"></i> Abrir
                                                </a>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
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
