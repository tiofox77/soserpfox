@if($showPlanModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                
                {{-- Header --}}
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-crown mr-3"></i>Alterar Plano do Tenant
                        </h3>
                        <button wire:click="closePlanModal" class="text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                {{-- Body --}}
                <form wire:submit.prevent="updateTenantPlan" class="p-6">
                    {{-- Plano Atual — o acordo como está, não só o nome --}}
                    @if($managingPlanTenant && $managingPlanTenant->activeSubscription)
                    @php $actual = $managingPlanTenant->activeSubscription; @endphp
                    <div class="mb-6 p-4 bg-blue-50 border-2 border-blue-200 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-blue-900">Plano Atual</p>
                                <p class="text-2xl font-bold text-blue-700">{{ $actual->plan?->name ?? '—' }}</p>
                                <p class="text-xs text-blue-600 mt-1">
                                    @if($actual->current_period_end)
                                        termina a {{ $actual->current_period_end->format('d/m/Y') }}
                                        · faltam {{ max(0, (int) now()->diffInDays($actual->current_period_end, false)) }} dias
                                    @else
                                        sem data de fim
                                    @endif
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-blue-600">
                                    {{ \App\Support\CicloDeFacturacao::nome($actual->billing_cycle) }}
                                    @if($actual->dias_personalizados)
                                        · {{ $actual->dias_personalizados }} dias à medida
                                    @elseif($actual->billing_cycle === 'yearly')
                                        · {{ ($actual->com_oferta ?? true) ? 'com 2 meses de oferta' : 'sem oferta' }}
                                    @endif
                                </p>
                                <p class="text-xl font-bold text-blue-700">
                                    {{ number_format($actual->amount, 2) }} Kz
                                </p>
                                @if($actual->preco_por_utilizador !== null)
                                    <p class="text-xs text-blue-600">
                                        {{ $actual->utilizadores_cobrados }} utilizador(es) × {{ number_format($actual->preco_por_utilizador, 2) }} Kz
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    {{-- Seleção de Novo Plano --}}
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-3">
                            <i class="fas fa-box mr-2"></i>Selecione o Novo Plano
                        </label>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($allPlans as $plan)
                            <label class="cursor-pointer">
                                <input type="radio" 
                                       wire:model.live="selectedPlanId"
                                       value="{{ $plan->id }}" 
                                       class="peer sr-only">
                                <div class="p-4 border-2 rounded-xl transition-all peer-checked:border-purple-600 peer-checked:bg-purple-50 peer-checked:shadow-lg hover:border-purple-400 hover:shadow-md">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h4 class="font-bold text-lg text-gray-900">{{ $plan->name }}</h4>
                                            <p class="text-xs text-gray-500 mt-1">{{ $plan->description }}</p>
                                        </div>
                                        @if($plan->is_featured)
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded-lg">
                                            Popular
                                        </span>
                                        @endif
                                    </div>
                                    
                                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                        <div class="flex items-center text-gray-600">
                                            <i class="fas fa-users mr-1 text-purple-600"></i>
                                            {{ $plan->max_users }} utilizadores
                                        </div>
                                        <div class="flex items-center text-gray-600">
                                            <i class="fas fa-database mr-1 text-purple-600"></i>
                                            {{ $plan->max_storage_mb }}MB
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 pt-3 border-t border-gray-200">
                                        <div class="flex items-baseline">
                                            <span class="text-2xl font-bold text-purple-700">
                                                {{ number_format($plan->price_monthly, 2) }}
                                            </span>
                                            <span class="text-sm text-gray-500 ml-1">Kz/mês</span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            ou {{ number_format($plan->price_yearly, 2) }} Kz/ano
                                        </div>
                                    </div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                        @error('selectedPlanId') 
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> 
                        @enderror
                    </div>
                    
                    {{-- Ciclo de Faturação --}}
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-3">
                            <i class="fas fa-calendar-alt mr-2"></i>Ciclo de Faturação
                        </label>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" 
                                       wire:model.live="billingCycle"
                                       value="monthly" 
                                       class="peer sr-only">
                                <div class="p-3 border-2 rounded-xl transition-all peer-checked:border-green-600 peer-checked:bg-green-50 hover:border-green-400">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-sm text-gray-900">Mensal</div>
                                            <div class="text-xs text-gray-500">Por mês</div>
                                        </div>
                                        <i class="fas fa-calendar text-green-600"></i>
                                    </div>
                                </div>
                            </label>
                            
                            <label class="cursor-pointer">
                                <input type="radio" 
                                       wire:model.live="billingCycle"
                                       value="quarterly" 
                                       class="peer sr-only">
                                <div class="p-3 border-2 rounded-xl transition-all peer-checked:border-blue-600 peer-checked:bg-blue-50 hover:border-blue-400">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-sm text-gray-900">Trimestral</div>
                                            <div class="text-xs text-gray-500">A cada 3 meses</div>
                                        </div>
                                        <i class="fas fa-calendar-plus text-blue-600"></i>
                                    </div>
                                </div>
                            </label>
                            
                            <label class="cursor-pointer">
                                <input type="radio" 
                                       wire:model.live="billingCycle"
                                       value="semiannual" 
                                       class="peer sr-only">
                                <div class="p-3 border-2 rounded-xl transition-all peer-checked:border-purple-600 peer-checked:bg-purple-50 hover:border-purple-400">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-sm text-gray-900">Semestral</div>
                                            <div class="text-xs text-gray-500">A cada 6 meses</div>
                                        </div>
                                        <i class="fas fa-calendar-week text-purple-600"></i>
                                    </div>
                                </div>
                            </label>
                            
                            <label class="cursor-pointer">
                                <input type="radio" 
                                       wire:model.live="billingCycle"
                                       value="yearly" 
                                       class="peer sr-only">
                                <div class="p-3 border-2 rounded-xl transition-all peer-checked:border-orange-600 peer-checked:bg-orange-50 hover:border-orange-400">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-sm text-gray-900">Anual</div>
                                            <div class="text-xs text-gray-500">Economize mais</div>
                                        </div>
                                        <i class="fas fa-calendar-check text-orange-600"></i>
                                    </div>
                                </div>
                            </label>
                        </div>
                        @error('billingCycle')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- O acordo: oferta, dias à medida, preço por utilizador.
                         Tudo `.live` — o resumo lá em baixo recalcula a cada tecla,
                         pela MESMA regra do servidor que vai gravar. --}}
                    <div class="mb-6 p-4 bg-gray-50 border-2 border-gray-200 rounded-xl">
                        <label class="block text-sm font-bold text-gray-700 mb-3">
                            <i class="fas fa-handshake mr-2"></i>Condições do acordo
                        </label>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Período em dias (opcional)</label>
                                <input type="number" min="1" max="3660" step="1"
                                       wire:model.live.debounce.400ms="diasPersonalizados"
                                       placeholder="ex.: 364"
                                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm focus:border-purple-500 focus:ring-0">
                                <p class="text-[11px] text-gray-500 mt-1">Se preencher, ganha ao ciclo: o período acaba daqui a N dias.</p>
                                @error('diasPersonalizados') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Preço por utilizador (opcional)</label>
                                <input type="number" min="0" step="0.01"
                                       wire:model.live.debounce.400ms="precoPorUtilizador"
                                       placeholder="Kz por utilizador"
                                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm focus:border-purple-500 focus:ring-0">
                                <p class="text-[11px] text-gray-500 mt-1">Se preencher, o valor passa a ser N utilizadores × este preço.</p>
                                @error('precoPorUtilizador') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Utilizadores a cobrar</label>
                                <input type="number" min="1" step="1"
                                       wire:model.live.debounce.400ms="utilizadoresCobrados"
                                       placeholder="os do plano"
                                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm focus:border-purple-500 focus:ring-0">
                                <p class="text-[11px] text-gray-500 mt-1">Vazio = os utilizadores do plano escolhido.</p>
                                @error('utilizadoresCobrados') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Tecto de documentos: o que a promoção dá. Fica gravado
                             na subscrição, por isso mudar o plano amanhã não mexe
                             em quem assinou hoje. --}}
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Documentos incluídos</label>
                                <input type="number" min="1" step="1" max="1000000"
                                       wire:model.live.debounce.400ms="maxDocumentos"
                                       placeholder="o que o plano der"
                                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg text-sm focus:border-purple-500 focus:ring-0">
                                <p class="text-[11px] text-gray-500 mt-1">Vazio = o tecto do plano. Sem tecto no plano = sem limite.</p>
                                @error('maxDocumentos') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                            </div>
                            @if($managingPlanTenant)
                            <div class="md:col-span-2 flex items-end">
                                @php
                                    $emitidos = $managingPlanTenant->documentosEmitidos();
                                    $tecto = $managingPlanTenant->limiteDeDocumentos();
                                @endphp
                                <p class="text-[11px] text-gray-600 pb-2">
                                    <i class="fas fa-file-invoice mr-1 text-gray-400"></i>
                                    Esta empresa já emitiu <strong>{{ number_format($emitidos, 0, ',', '.') }}</strong> documentos fiscais
                                    @if($tecto !== null)
                                        de <strong>{{ number_format($tecto, 0, ',', '.') }}</strong>
                                        @if($emitidos >= $tecto)
                                            <span class="text-red-600 font-semibold">· esgotado, não consegue facturar</span>
                                        @elseif($emitidos >= $tecto * 0.9)
                                            <span class="text-orange-600 font-semibold">· quase no fim</span>
                                        @endif
                                    @else
                                        <span class="text-gray-500">· sem tecto</span>
                                    @endif
                                </p>
                            </div>
                            @endif
                        </div>

                        @if($billingCycle === 'yearly' && $diasPersonalizados === '')
                        <label class="mt-4 flex items-start cursor-pointer">
                            <input type="checkbox" wire:model.live="comOferta"
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                            <span class="ml-2 text-sm text-gray-700">
                                <strong>Oferecer os 2 meses do anual</strong> — 14 meses pelo preço de 12.
                                <span class="block text-xs text-gray-500">Desligado, o anual dá exactamente 12 meses. Fica gravado no acordo: a renovação repete-o.</span>
                            </span>
                        </label>
                        @endif
                    </div>

                    {{-- Resumo da Mudança — calculado pelo componente, pela mesma
                         regra que grava (getResumoDoPlanoProperty). --}}
                    @php $resumo = $this->resumoDoPlano; @endphp
                    @if($selectedPlanId && $resumo)
                    @php
                        $selectedPlan = $allPlans->firstWhere('id', $selectedPlanId);
                        $currentPlan = $managingPlanTenant && $managingPlanTenant->activeSubscription ? $managingPlanTenant->activeSubscription->plan : null;

                        // Formatar storage
                        $storageMB = $selectedPlan->max_storage_mb;
                        $storageFormatted = $storageMB >= 1000
                            ? number_format($storageMB / 1024, 1) . 'GB'
                            : $storageMB . 'MB';

                        // Calcular diferenças
                        $usersDiff = $currentPlan ? ($selectedPlan->max_users - $currentPlan->max_users) : 0;
                        $storageDiff = $currentPlan ? ($selectedPlan->max_storage_mb - $currentPlan->max_storage_mb) : 0;

                        // Preço — o do acordo, não o da tabela
                        $price = $resumo['valor'];

                        // Nome do ciclo
                        $cycleName = $diasPersonalizados !== ''
                            ? ((int) $diasPersonalizados) . ' dias'
                            : match($billingCycle) {
                                'quarterly' => 'trimestre',
                                'semiannual' => 'semestre',
                                'yearly' => 'ano',
                                default => 'mês',
                            };

                        // Preço anterior
                        $currentPrice = $currentPlan && $managingPlanTenant->activeSubscription
                            ? $managingPlanTenant->activeSubscription->amount
                            : 0;
                        $priceDiff = $price - $currentPrice;
                    @endphp

                    {{-- Total Box --}}
                    <div class="mb-6 p-5 bg-gradient-to-br from-purple-600 to-indigo-700 rounded-xl shadow-lg text-white">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <p class="text-purple-200 text-xs font-semibold uppercase tracking-wide">Total a Pagar</p>
                                <p class="text-sm text-purple-200 mt-0.5">{{ $selectedPlan->name }} • {{ ucfirst($cycleName) }}</p>
                                <p class="text-xs text-purple-200 mt-1">{{ $resumo['base'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-bold">{{ number_format($price, 2) }} <span class="text-lg">Kz</span></p>
                                <p class="text-purple-200 text-xs">/ {{ $cycleName }}</p>
                            </div>
                        </div>
                        <div class="pb-3 mb-3 border-b border-white/20 flex items-center justify-between text-xs">
                            <span class="text-purple-200">
                                <i class="fas fa-calendar-check mr-1"></i>
                                Período: hoje → {{ $resumo['fim']->format('d/m/Y') }} ({{ $resumo['dias'] }} dias)
                            </span>
                            @if($resumo['oferta_aplicavel'])
                                <span class="px-2 py-0.5 rounded-full font-semibold {{ $comOferta ? 'bg-green-500/30 text-green-200' : 'bg-white/20 text-purple-200' }}">
                                    {{ $comOferta ? '+2 meses de oferta' : 'sem oferta' }}
                                </span>
                            @endif
                        </div>
                        @if($currentPlan)
                        <div class="pt-3 border-t border-white/20 flex items-center justify-between">
                            <span class="text-purple-200 text-xs">Valor anterior: {{ number_format($currentPrice, 2) }} Kz</span>
                            @if($priceDiff > 0)
                                <span class="px-2 py-0.5 bg-red-500/30 text-red-200 text-xs font-semibold rounded-full">+{{ number_format($priceDiff, 2) }} Kz</span>
                            @elseif($priceDiff < 0)
                                <span class="px-2 py-0.5 bg-green-500/30 text-green-200 text-xs font-semibold rounded-full">{{ number_format($priceDiff, 2) }} Kz</span>
                            @else
                                <span class="px-2 py-0.5 bg-white/20 text-purple-200 text-xs font-semibold rounded-full">Sem alteração</span>
                            @endif
                        </div>
                        @endif
                    </div>
                    
                    <div class="mb-6 p-4 bg-gradient-to-br from-yellow-50 to-orange-50 border-2 border-yellow-300 rounded-xl shadow-sm">
                        <h4 class="font-bold text-yellow-900 mb-3 flex items-center text-base">
                            <i class="fas fa-info-circle mr-2 text-yellow-600"></i>
                            O que vai acontecer:
                        </h4>
                        <ul class="text-sm text-yellow-900 space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-600 mr-2 mt-0.5"></i>
                                <div>
                                    <strong>Plano:</strong> Será alterado para <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded font-bold">{{ $selectedPlan->name }}</span>
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <i class="fas fa-users text-blue-600 mr-2 mt-0.5"></i>
                                <div>
                                    <strong>Utilizadores:</strong> {{ $selectedPlan->max_users }} utilizadores
                                    @if($currentPlan)
                                        @if($usersDiff > 0)
                                            <span class="text-green-700">(+{{ $usersDiff }} 📈)</span>
                                        @elseif($usersDiff < 0)
                                            <span class="text-red-700">({{ $usersDiff }} 📉)</span>
                                        @else
                                            <span class="text-gray-600">(sem alteração)</span>
                                        @endif
                                    @endif
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <i class="fas fa-database text-cyan-600 mr-2 mt-0.5"></i>
                                <div>
                                    <strong>Armazenamento:</strong> {{ $storageFormatted }}
                                    @if($currentPlan)
                                        @if($storageDiff > 0)
                                            <span class="text-green-700">(+{{ number_format($storageDiff) }}MB 📈)</span>
                                        @elseif($storageDiff < 0)
                                            <span class="text-red-700">({{ number_format($storageDiff) }}MB 📉)</span>
                                        @else
                                            <span class="text-gray-600">(sem alteração)</span>
                                        @endif
                                    @endif
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <i class="fas fa-euro-sign text-purple-600 mr-2 mt-0.5"></i>
                                <div>
                                    <strong>Valor:</strong> <span class="text-lg font-bold text-purple-700">{{ number_format($price, 2) }} Kz</span>
                                    <span class="text-gray-600 text-xs">/ {{ $cycleName }}</span>
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <i class="fas fa-puzzle-piece text-orange-600 mr-2 mt-0.5"></i>
                                <div>
                                    <strong>Módulos:</strong> Serão sincronizados automaticamente com o novo plano
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <i class="fas fa-users-cog text-indigo-600 mr-2 mt-0.5"></i>
                                <div>
                                    <strong>Utilizadores:</strong> Todos os utilizadores do tenant terão acesso imediato aos novos módulos
                                </div>
                            </li>
                        </ul>
                    </div>
                    @endif
                    
                    {{-- Footer --}}
                    <div class="flex justify-end space-x-3">
                        <button type="button" 
                                wire:click="closePlanModal" 
                                class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" 
                                class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 shadow-lg hover:shadow-xl transition">
                            <i class="fas fa-check mr-2"></i>Alterar Plano
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
