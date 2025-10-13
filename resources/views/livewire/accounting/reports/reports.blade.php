<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
            <i class="fas fa-chart-bar mr-3 text-cyan-600"></i>
            Relatórios Contabilísticos
        </h1>
        <p class="text-gray-600 mt-1">Análise e balanços contabilísticos</p>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
                <input type="date" wire:model.live="dateFrom" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data Fim</label>
                <input type="date" wire:model.live="dateTo" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Relatório</label>
                <select wire:model.live="reportType" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500">
                    <option value="trial_balance">Balancete</option>
                    <option value="ledger">Razão Geral</option>
                    <option value="journal">Diário</option>
                    <option value="vat">Mapa de IVA</option>
                    <option value="income_statement">Demonstração de Resultados (Simples)</option>
                    <option value="balance_sheet">Balanço (Posição Financeira)</option>
                    <option value="income_statement_nature">DR por Natureza (DRN)</option>
                    <option value="income_statement_function">DR por Funções (DRF)</option>
                    <option value="cash_flow">Fluxos de Caixa (DFC)</option>
                </select>
            </div>
            @if($reportType === 'ledger')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Conta</label>
                <select wire:model.live="accountId" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500">
                    <option value="">Selecione uma conta...</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if($reportType === 'journal')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Diário</label>
                <select wire:model.live="journalFilter" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500">
                    <option value="">Todos os diários</option>
                    @foreach($journals as $journal)
                        <option value="{{ $journal->id }}">{{ $journal->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>
    </div>

    {{-- Trial Balance Report --}}
    @if($reportType === 'trial_balance')
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-cyan-600 to-blue-600 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-balance-scale mr-2"></i>
                    Balancete de Verificação
                </h2>
                <p class="text-cyan-100 text-sm">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
            </div>
            <button class="px-4 py-2 bg-white text-cyan-600 rounded-lg hover:bg-cyan-50 transition font-semibold">
                <i class="fas fa-download mr-2"></i>Exportar PDF
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b-2 border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Conta</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Débito</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Crédito</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @php
                        $totalDebit = 0;
                        $totalCredit = 0;
                        $totalBalance = 0;
                    @endphp
                    @forelse($trialBalance as $item)
                    @php
                        $totalDebit += $item['debit'];
                        $totalCredit += $item['credit'];
                        $totalBalance += $item['balance'];
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-3 text-sm font-mono font-semibold text-gray-900">{{ $item['account']->code }}</td>
                        <td class="px-6 py-3 text-sm text-gray-900">{{ $item['account']->name }}</td>
                        <td class="px-6 py-3 text-sm text-right font-medium text-green-600">
                            {{ number_format($item['debit'], 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-3 text-sm text-right font-medium text-red-600">
                            {{ number_format($item['credit'], 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-3 text-sm text-right font-bold {{ $item['balance'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($item['balance'], 2, ',', '.') }} Kz
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-3 block"></i>
                            Nenhum movimento no período selecionado
                        </td>
                    </tr>
                    @endforelse
                    
                    @if($trialBalance->count() > 0)
                    <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                        <td colspan="2" class="px-6 py-4 text-sm text-gray-900 uppercase">Totais</td>
                        <td class="px-6 py-4 text-sm text-right text-green-700">
                            {{ number_format($totalDebit, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-red-700">
                            {{ number_format($totalCredit, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-sm text-right {{ $totalBalance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ number_format($totalBalance, 2, ',', '.') }} Kz
                        </td>
                    </tr>
                    <tr class="bg-blue-50 border-t border-gray-200">
                        <td colspan="5" class="px-6 py-3 text-center">
                            <div class="flex items-center justify-center space-x-4 text-sm">
                                <span class="font-semibold">
                                    <i class="fas fa-check-circle text-green-600 mr-1"></i>
                                    Diferença D/C: <span class="{{ abs($totalDebit - $totalCredit) < 0.01 ? 'text-green-600' : 'text-red-600' }} font-bold">
                                        {{ number_format($totalDebit - $totalCredit, 2, ',', '.') }} Kz
                                    </span>
                                </span>
                                @if(abs($totalDebit - $totalCredit) < 0.01)
                                    <span class="text-green-600 font-semibold">
                                        <i class="fas fa-balance-scale mr-1"></i>Balanceado
                                    </span>
                                @else
                                    <span class="text-red-600 font-semibold">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Desbalanceado
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Ledger Report (Razão Geral) --}}
    @if($reportType === 'ledger')
        @if($ledgerData)
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-book mr-2"></i>
                        Razão Geral - {{ $ledgerData['account']->code }} - {{ $ledgerData['account']->name }}
                    </h2>
                    <p class="text-indigo-100 text-sm">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
                </div>
                <button class="px-4 py-2 bg-white text-indigo-600 rounded-lg hover:bg-indigo-50 transition font-semibold">
                    <i class="fas fa-download mr-2"></i>Exportar PDF
                </button>
            </div>

            {{-- Saldo Inicial --}}
            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <span class="font-semibold text-gray-700">Saldo Inicial</span>
                    <span class="text-lg font-bold {{ $ledgerData['initialBalance'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format(abs($ledgerData['initialBalance']), 2, ',', '.') }} Kz 
                        {{ $ledgerData['initialBalance'] >= 0 ? '(Devedor)' : '(Credor)' }}
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b-2 border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Referência</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Diário</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Descrição</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Débito</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Crédito</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($ledgerData['movements'] as $movement)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-3 text-sm text-gray-900">{{ $movement->move->date->format('d/m/Y') }}</td>
                            <td class="px-6 py-3 text-sm font-semibold text-gray-900">{{ $movement->move->ref }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ $movement->move->journal->name ?? '-' }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ $movement->narration ?? $movement->move->narration ?? '-' }}</td>
                            <td class="px-6 py-3 text-sm text-right font-medium text-green-600">
                                {{ $movement->debit > 0 ? number_format($movement->debit, 2, ',', '.') . ' Kz' : '-' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-right font-medium text-red-600">
                                {{ $movement->credit > 0 ? number_format($movement->credit, 2, ',', '.') . ' Kz' : '-' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-right font-bold {{ $movement->running_balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format(abs($movement->running_balance), 2, ',', '.') }} Kz
                                <span class="text-xs">{{ $movement->running_balance >= 0 ? 'D' : 'C' }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                Nenhum movimento no período selecionado
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Saldo Final --}}
            <div class="px-6 py-4 bg-indigo-50 border-t-2 border-indigo-200">
                <div class="flex justify-between items-center">
                    <span class="font-bold text-gray-900 uppercase">Saldo Final</span>
                    <span class="text-2xl font-bold {{ $ledgerData['finalBalance'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format(abs($ledgerData['finalBalance']), 2, ',', '.') }} Kz 
                        {{ $ledgerData['finalBalance'] >= 0 ? '(Devedor)' : '(Credor)' }}
                    </span>
                </div>
            </div>
        </div>
        @else
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="fas fa-hand-pointer text-6xl text-indigo-300 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Selecione uma Conta</h3>
            <p class="text-gray-600">Escolha uma conta acima para visualizar o Razão Geral.</p>
        </div>
        @endif
    @endif

    {{-- Journal Report (Diário) --}}
    @if($reportType === 'journal')
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-600 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-book-open mr-2"></i>
                    Diário Contabilístico
                </h2>
                <p class="text-green-100 text-sm">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
            </div>
            <button class="px-4 py-2 bg-white text-green-600 rounded-lg hover:bg-green-50 transition font-semibold">
                <i class="fas fa-download mr-2"></i>Exportar PDF
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b-2 border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Referência</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Diário</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Conta</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Descrição</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Débito</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Crédito</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $currentDate = null;
                        $currentMove = null;
                        $dailyDebit = 0;
                        $dailyCredit = 0;
                        $totalDebit = 0;
                        $totalCredit = 0;
                    @endphp
                    
                    @forelse($journalData as $move)
                        @php
                            $moveDate = $move->date->format('d/m/Y');
                            $showDateHeader = ($currentDate !== $moveDate);
                            if ($showDateHeader && $currentDate !== null) {
                                // Mostrar total do dia anterior
                            }
                            $currentDate = $moveDate;
                        @endphp
                        
                        @if($showDateHeader)
                        <tr class="bg-blue-50 border-t-2 border-blue-200">
                            <td colspan="7" class="px-6 py-2 font-bold text-blue-900">
                                <i class="fas fa-calendar-day mr-2"></i>{{ $moveDate }}
                            </td>
                        </tr>
                        @endif
                        
                        @foreach($move->lines as $index => $line)
                        @php
                            $totalDebit += $line->debit;
                            $totalCredit += $line->credit;
                        @endphp
                        <tr class="hover:bg-gray-50 transition {{ $index === 0 ? 'border-t border-gray-300' : '' }}">
                            <td class="px-6 py-3 text-sm text-gray-600">
                                @if($index === 0){{ $moveDate }}@endif
                            </td>
                            <td class="px-6 py-3 text-sm font-semibold text-gray-900">
                                @if($index === 0){{ $move->ref }}@endif
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">
                                @if($index === 0){{ $move->journal->name ?? '-' }}@endif
                            </td>
                            <td class="px-6 py-3 text-sm">
                                <span class="font-mono font-semibold text-gray-900">{{ $line->account->code }}</span>
                                <span class="text-gray-600 ml-2">{{ $line->account->name }}</span>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">
                                {{ $line->narration ?? $move->narration ?? '-' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-right font-medium text-green-600">
                                {{ $line->debit > 0 ? number_format($line->debit, 2, ',', '.') . ' Kz' : '-' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-right font-medium text-red-600">
                                {{ $line->credit > 0 ? number_format($line->credit, 2, ',', '.') . ' Kz' : '-' }}
                            </td>
                        </tr>
                        @endforeach
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-3 block"></i>
                            Nenhum lançamento no período selecionado
                        </td>
                    </tr>
                    @endforelse
                    
                    @if($journalData && $journalData->count() > 0)
                    <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                        <td colspan="5" class="px-6 py-4 text-sm text-gray-900 uppercase">Totais do Período</td>
                        <td class="px-6 py-4 text-sm text-right text-green-700">
                            {{ number_format($totalDebit, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-red-700">
                            {{ number_format($totalCredit, 2, ',', '.') }} Kz
                        </td>
                    </tr>
                    <tr class="bg-green-50">
                        <td colspan="7" class="px-6 py-3 text-center">
                            @php $diff = $totalDebit - $totalCredit; @endphp
                            <div class="flex items-center justify-center space-x-4 text-sm">
                                <span class="font-semibold">
                                    <i class="fas fa-check-circle {{ abs($diff) < 0.01 ? 'text-green-600' : 'text-red-600' }} mr-1"></i>
                                    Diferença D/C: <span class="{{ abs($diff) < 0.01 ? 'text-green-600' : 'text-red-600' }} font-bold">
                                        {{ number_format($diff, 2, ',', '.') }} Kz
                                    </span>
                                </span>
                                @if(abs($diff) < 0.01)
                                    <span class="text-green-600 font-semibold">
                                        <i class="fas fa-balance-scale mr-1"></i>Balanceado
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- VAT Report (Mapa de IVA) --}}
    @if($reportType === 'vat' && $vatData)
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-orange-600 to-red-600 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-file-invoice-dollar mr-2"></i>
                    Mapa de IVA - Angola
                </h2>
                <p class="text-orange-100 text-sm">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
            </div>
            <button class="px-4 py-2 bg-white text-orange-600 rounded-lg hover:bg-orange-50 transition font-semibold">
                <i class="fas fa-file-export mr-2"></i>Exportar AGT
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
            {{-- IVA Liquidado (Vendas) --}}
            <div class="bg-green-50 rounded-xl border-2 border-green-200 p-6">
                <h3 class="text-lg font-bold text-green-900 mb-4 flex items-center">
                    <i class="fas fa-cash-register mr-2"></i>
                    IVA Liquidado (Vendas)
                </h3>
                
                <div class="space-y-2 mb-4">
                    @foreach($vatData['collected']->groupBy('account.code') as $code => $lines)
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-700">{{ $code }} - {{ $lines->first()->account->name }}</span>
                        <span class="font-semibold text-green-700">
                            {{ number_format($lines->sum('credit') - $lines->sum('debit'), 2, ',', '.') }} Kz
                        </span>
                    </div>
                    @endforeach
                </div>
                
                <div class="border-t-2 border-green-300 pt-4">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-green-900 uppercase">Total IVA Liquidado</span>
                        <span class="text-2xl font-bold text-green-700">
                            {{ number_format($vatData['totalCollected'], 2, ',', '.') }} Kz
                        </span>
                    </div>
                </div>
            </div>

            {{-- IVA Dedutível (Compras) --}}
            <div class="bg-blue-50 rounded-xl border-2 border-blue-200 p-6">
                <h3 class="text-lg font-bold text-blue-900 mb-4 flex items-center">
                    <i class="fas fa-shopping-cart mr-2"></i>
                    IVA Dedutível (Compras)
                </h3>
                
                <div class="space-y-2 mb-4">
                    @foreach($vatData['deductible']->groupBy('account.code') as $code => $lines)
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-700">{{ $code }} - {{ $lines->first()->account->name }}</span>
                        <span class="font-semibold text-blue-700">
                            {{ number_format($lines->sum('debit') - $lines->sum('credit'), 2, ',', '.') }} Kz
                        </span>
                    </div>
                    @endforeach
                </div>
                
                <div class="border-t-2 border-blue-300 pt-4">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-blue-900 uppercase">Total IVA Dedutível</span>
                        <span class="text-2xl font-bold text-blue-700">
                            {{ number_format($vatData['totalDeductible'], 2, ',', '.') }} Kz
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Resumo e IVA Líquido --}}
        <div class="px-6 pb-6">
            <div class="bg-gradient-to-r from-orange-100 to-red-100 rounded-xl border-2 {{ $vatData['netVat'] >= 0 ? 'border-orange-300' : 'border-green-300' }} p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-1">IVA Liquidado</p>
                        <p class="text-xl font-bold text-green-700">
                            {{ number_format($vatData['totalCollected'], 2, ',', '.') }} Kz
                        </p>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-1">IVA Dedutível</p>
                        <p class="text-xl font-bold text-blue-700">
                            {{ number_format($vatData['totalDeductible'], 2, ',', '.') }} Kz
                        </p>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-sm text-gray-700 font-semibold mb-1">
                            @if($vatData['netVat'] >= 0)
                                IVA a Pagar
                            @else
                                IVA a Recuperar
                            @endif
                        </p>
                        <p class="text-3xl font-bold {{ $vatData['netVat'] >= 0 ? 'text-orange-700' : 'text-green-700' }}">
                            {{ number_format(abs($vatData['netVat']), 2, ',', '.') }} Kz
                        </p>
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t border-gray-300">
                    <div class="flex items-center justify-center space-x-4 text-sm">
                        @if($vatData['netVat'] >= 0)
                            <i class="fas fa-exclamation-circle text-orange-600 text-xl"></i>
                            <span class="text-gray-700">
                                <strong class="text-orange-700">IVA a entregar ao Estado</strong> até ao dia 20 do mês seguinte
                            </span>
                        @else
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            <span class="text-gray-700">
                                <strong class="text-green-700">IVA a recuperar</strong> - crédito fiscal disponível
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Detalhes por Movimento --}}
        <div class="px-6 pb-6">
            <details class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                <summary class="cursor-pointer font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-gray-600"></i>
                    Ver Detalhes dos Movimentos ({{ $vatData['collected']->count() + $vatData['deductible']->count() }} linhas)
                </summary>
                
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-4 py-2 text-left">Data</th>
                                <th class="px-4 py-2 text-left">Referência</th>
                                <th class="px-4 py-2 text-left">Conta</th>
                                <th class="px-4 py-2 text-left">Tipo</th>
                                <th class="px-4 py-2 text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($vatData['collected'] as $line)
                            <tr class="hover:bg-gray-100">
                                <td class="px-4 py-2">{{ $line->move->date->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 font-semibold">{{ $line->move->ref }}</td>
                                <td class="px-4 py-2">{{ $line->account->code }} - {{ $line->account->name }}</td>
                                <td class="px-4 py-2"><span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Liquidado</span></td>
                                <td class="px-4 py-2 text-right font-semibold text-green-700">
                                    {{ number_format($line->credit - $line->debit, 2, ',', '.') }} Kz
                                </td>
                            </tr>
                            @endforeach
                            
                            @foreach($vatData['deductible'] as $line)
                            <tr class="hover:bg-gray-100">
                                <td class="px-4 py-2">{{ $line->move->date->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 font-semibold">{{ $line->move->ref }}</td>
                                <td class="px-4 py-2">{{ $line->account->code }} - {{ $line->account->name }}</td>
                                <td class="px-4 py-2"><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Dedutível</span></td>
                                <td class="px-4 py-2 text-right font-semibold text-blue-700">
                                    {{ number_format($line->debit - $line->credit, 2, ',', '.') }} Kz
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    </div>
    @endif

    {{-- Income Statement (DRE Simplificada) --}}
    @if($reportType === 'income_statement' && $incomeStatement)
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-pink-600 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-chart-line mr-2"></i>
                    Demonstração de Resultados (DRE)
                </h2>
                <p class="text-purple-100 text-sm">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
            </div>
            <button class="px-4 py-2 bg-white text-purple-600 rounded-lg hover:bg-purple-50 transition font-semibold">
                <i class="fas fa-download mr-2"></i>Exportar PDF
            </button>
        </div>

        <div class="p-6">
            {{-- Rendimentos --}}
            <div class="bg-green-50 rounded-xl border-2 border-green-200 mb-6 overflow-hidden">
                <div class="bg-green-600 px-6 py-3">
                    <h3 class="text-lg font-bold text-white flex items-center">
                        <i class="fas fa-arrow-up mr-2"></i>
                        RENDIMENTOS (Classe 7)
                    </h3>
                </div>
                <div class="p-6 space-y-3">
                    @foreach($incomeStatement['revenues'] as $code => $data)
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">
                            <span class="font-mono font-semibold text-green-700">{{ $code }}</span>
                            <span class="ml-2">{{ $data['name'] }}</span>
                        </span>
                        <span class="font-semibold text-green-700">
                            {{ number_format($data['amount'], 2, ',', '.') }} Kz
                        </span>
                    </div>
                    @endforeach
                    
                    <div class="border-t-2 border-green-300 pt-3 mt-3">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-green-900 uppercase">Total de Rendimentos</span>
                            <span class="text-2xl font-bold text-green-700">
                                {{ number_format($incomeStatement['totalRevenues'], 2, ',', '.') }} Kz
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Gastos --}}
            <div class="bg-red-50 rounded-xl border-2 border-red-200 mb-6 overflow-hidden">
                <div class="bg-red-600 px-6 py-3">
                    <h3 class="text-lg font-bold text-white flex items-center">
                        <i class="fas fa-arrow-down mr-2"></i>
                        GASTOS (Classe 6)
                    </h3>
                </div>
                <div class="p-6 space-y-3">
                    @foreach($incomeStatement['expenses'] as $code => $data)
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">
                            <span class="font-mono font-semibold text-red-700">{{ $code }}</span>
                            <span class="ml-2">{{ $data['name'] }}</span>
                        </span>
                        <span class="font-semibold text-red-700">
                            {{ number_format($data['amount'], 2, ',', '.') }} Kz
                        </span>
                    </div>
                    @endforeach
                    
                    <div class="border-t-2 border-red-300 pt-3 mt-3">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-red-900 uppercase">Total de Gastos</span>
                            <span class="text-2xl font-bold text-red-700">
                                {{ number_format($incomeStatement['totalExpenses'], 2, ',', '.') }} Kz
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Resultado Líquido --}}
            <div class="bg-gradient-to-r {{ $incomeStatement['netIncome'] >= 0 ? 'from-green-100 to-emerald-100 border-green-300' : 'from-red-100 to-pink-100 border-red-300' }} rounded-xl border-2 p-8">
                <div class="text-center">
                    <p class="text-sm text-gray-600 mb-2 uppercase tracking-wide">Resultado Líquido do Período</p>
                    <p class="text-5xl font-bold {{ $incomeStatement['netIncome'] >= 0 ? 'text-green-700' : 'text-red-700' }} mb-4">
                        {{ number_format(abs($incomeStatement['netIncome']), 2, ',', '.') }} Kz
                    </p>
                    <div class="flex items-center justify-center space-x-3">
                        @if($incomeStatement['netIncome'] >= 0)
                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                            <span class="text-lg font-semibold text-green-800">LUCRO</span>
                        @else
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                            <span class="text-lg font-semibold text-red-800">PREJUÍZO</span>
                        @endif
                    </div>
                </div>
                
                {{-- Análise de Margem --}}
                @if($incomeStatement['totalRevenues'] > 0)
                <div class="mt-6 pt-6 border-t border-gray-300">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Rendimentos</p>
                            <p class="text-lg font-bold text-green-700">100%</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Gastos</p>
                            <p class="text-lg font-bold text-red-700">
                                {{ number_format(($incomeStatement['totalExpenses'] / $incomeStatement['totalRevenues']) * 100, 1) }}%
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Margem Líquida</p>
                            <p class="text-lg font-bold {{ $incomeStatement['netIncome'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ number_format(($incomeStatement['netIncome'] / $incomeStatement['totalRevenues']) * 100, 1) }}%
                            </p>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Resumo Gráfico --}}
            @if($incomeStatement['totalRevenues'] > 0 || $incomeStatement['totalExpenses'] > 0)
            <div class="mt-6 bg-gray-50 rounded-xl p-6">
                <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-chart-bar mr-2 text-gray-600"></i>
                    Análise Visual
                </h4>
                <div class="flex items-end space-x-2 h-32">
                    @php
                        $maxValue = max($incomeStatement['totalRevenues'], $incomeStatement['totalExpenses']);
                        $revenueHeight = $maxValue > 0 ? ($incomeStatement['totalRevenues'] / $maxValue) * 100 : 0;
                        $expenseHeight = $maxValue > 0 ? ($incomeStatement['totalExpenses'] / $maxValue) * 100 : 0;
                        $netIncomeHeight = ($maxValue > 0 && $incomeStatement['netIncome'] > 0) ? ($incomeStatement['netIncome'] / $maxValue) * 100 : 0;
                    @endphp
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-green-500 rounded-t" style="height: {{ $revenueHeight }}%"></div>
                        <p class="text-xs mt-2 font-semibold text-green-700">Rendimentos</p>
                    </div>
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-red-500 rounded-t" style="height: {{ $expenseHeight }}%"></div>
                        <p class="text-xs mt-2 font-semibold text-red-700">Gastos</p>
                    </div>
                    @if($incomeStatement['netIncome'] >= 0 && $netIncomeHeight > 0)
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-emerald-500 rounded-t" style="height: {{ $netIncomeHeight }}%"></div>
                        <p class="text-xs mt-2 font-semibold text-emerald-700">Lucro</p>
                    </div>
                    @endif
                </div>
            </div>
            @else
            <div class="mt-6 bg-gray-50 rounded-xl p-6 text-center">
                <i class="fas fa-chart-bar text-gray-400 text-3xl mb-2"></i>
                <p class="text-sm text-gray-600">Sem dados para análise visual no período selecionado</p>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Balance Sheet (Balanço) --}}
    @include('livewire.accounting.reports.partials.balance-sheet')

    {{-- Income Statement by Nature (DR por Natureza) --}}
    @include('livewire.accounting.reports.partials.income-statement-nature')

    {{-- Income Statement by Function (DR por Funções) --}}
    @include('livewire.accounting.reports.partials.income-statement-function')

    {{-- Cash Flow Statement (Fluxos de Caixa) --}}
    @include('livewire.accounting.reports.partials.cash-flow')

    {{-- Other Reports Placeholder --}}
    @if($reportType !== 'trial_balance' && $reportType !== 'ledger' && $reportType !== 'journal' && $reportType !== 'vat' && $reportType !== 'income_statement' && $reportType !== 'balance_sheet' && $reportType !== 'income_statement_nature' && $reportType !== 'income_statement_function' && $reportType !== 'cash_flow')
    <div class="bg-white rounded-xl shadow-lg p-12 text-center">
        <i class="fas fa-chart-pie text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Relatório em Desenvolvimento</h3>
        <p class="text-gray-600">Este relatório será implementado em breve.</p>
    </div>
    @endif
</div>
