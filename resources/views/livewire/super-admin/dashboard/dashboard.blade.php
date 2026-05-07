<div>
    <!-- Row 1: 6 Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
        <!-- Tenants -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-5 border border-purple-100 overflow-hidden hover:shadow-xl transition-all">
            <div class="absolute top-0 right-0 w-20 h-20 bg-purple-50 rounded-full -mr-10 -mt-10 opacity-50"></div>
            <div class="relative z-10">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30 mb-3">
                    <i class="fas fa-building text-white text-lg"></i>
                </div>
                <p class="text-xs text-purple-600 font-semibold uppercase tracking-wide">Tenants</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_tenants'] }}</p>
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-xs text-green-600 font-medium"><i class="fas fa-check-circle mr-0.5"></i> {{ $stats['active_tenants'] }}</span>
                    <span class="text-xs text-gray-400">|</span>
                    <span class="text-xs text-red-500 font-medium"><i class="fas fa-times-circle mr-0.5"></i> {{ $stats['inactive_tenants'] }}</span>
                </div>
            </div>
        </div>

        <!-- Users -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-5 border border-green-100 overflow-hidden hover:shadow-xl transition-all">
            <div class="absolute top-0 right-0 w-20 h-20 bg-green-50 rounded-full -mr-10 -mt-10 opacity-50"></div>
            <div class="relative z-10">
                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/30 mb-3">
                    <i class="fas fa-users text-white text-lg"></i>
                </div>
                <p class="text-xs text-green-600 font-semibold uppercase tracking-wide">Utilizadores</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_users'] }}</p>
                <p class="text-xs text-gray-500 mt-2">
                    @if($stats['tenant_growth'] > 0)
                        <span class="text-green-600"><i class="fas fa-arrow-up mr-0.5"></i>+{{ $stats['tenant_growth'] }}%</span> este mês
                    @else
                        Registados no sistema
                    @endif
                </p>
            </div>
        </div>

        <!-- Revenue -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-5 border border-yellow-100 overflow-hidden hover:shadow-xl transition-all">
            <div class="absolute top-0 right-0 w-20 h-20 bg-yellow-50 rounded-full -mr-10 -mt-10 opacity-50"></div>
            <div class="relative z-10">
                <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-yellow-500/30 mb-3">
                    <i class="fas fa-money-bill-wave text-white text-lg"></i>
                </div>
                <p class="text-xs text-orange-600 font-semibold uppercase tracking-wide">Receita Total</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['total_revenue'], 0, ',', '.') }} <span class="text-sm font-normal text-gray-500">Kz</span></p>
                <p class="text-xs text-gray-500 mt-2">
                    Mês: <span class="font-semibold text-orange-600">{{ number_format($stats['monthly_revenue'], 0, ',', '.') }} Kz</span>
                </p>
            </div>
        </div>

        <!-- Modules -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-5 border border-indigo-100 overflow-hidden hover:shadow-xl transition-all">
            <div class="absolute top-0 right-0 w-20 h-20 bg-indigo-50 rounded-full -mr-10 -mt-10 opacity-50"></div>
            <div class="relative z-10">
                <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/30 mb-3">
                    <i class="fas fa-puzzle-piece text-white text-lg"></i>
                </div>
                <p class="text-xs text-indigo-600 font-semibold uppercase tracking-wide">Módulos</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_modules'] }}</p>
                <p class="text-xs text-gray-500 mt-2">Activos no sistema</p>
            </div>
        </div>

        <!-- Subscriptions -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-5 border border-cyan-100 overflow-hidden hover:shadow-xl transition-all">
            <div class="absolute top-0 right-0 w-20 h-20 bg-cyan-50 rounded-full -mr-10 -mt-10 opacity-50"></div>
            <div class="relative z-10">
                <div class="w-12 h-12 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-cyan-500/30 mb-3">
                    <i class="fas fa-crown text-white text-lg"></i>
                </div>
                <p class="text-xs text-cyan-600 font-semibold uppercase tracking-wide">Subscrições</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['active_subscriptions'] }}</p>
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-xs text-blue-600 font-medium">{{ $stats['trial_subscriptions'] }} trial</span>
                </div>
            </div>
        </div>

        <!-- Pending Orders -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-5 border border-rose-100 overflow-hidden hover:shadow-xl transition-all">
            <div class="absolute top-0 right-0 w-20 h-20 bg-rose-50 rounded-full -mr-10 -mt-10 opacity-50"></div>
            <div class="relative z-10">
                <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-pink-600 rounded-xl flex items-center justify-center shadow-lg shadow-rose-500/30 mb-3">
                    <i class="fas fa-clock text-white text-lg"></i>
                </div>
                <p class="text-xs text-rose-600 font-semibold uppercase tracking-wide">Pendentes</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['pending_orders'] }}</p>
                <p class="text-xs text-gray-500 mt-2">
                    @if($stats['pending_orders'] > 0)
                        <a href="{{ route('superadmin.billing') }}" class="text-rose-600 hover:underline">Ver pedidos</a>
                    @else
                        Pedidos/Faturas
                    @endif
                </p>
            </div>
        </div>
    </div>

    <!-- Row 2: Charts (Tenant Growth + Revenue) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Tenant Growth Chart -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-chart-bar text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Crescimento de Tenants</h3>
                        <p class="text-xs text-gray-500">Últimos 6 meses</p>
                    </div>
                </div>
                <span class="text-xs font-medium px-2 py-1 rounded-full {{ $stats['tenant_growth'] >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $stats['tenant_growth'] >= 0 ? '+' : '' }}{{ $stats['tenant_growth'] }}%
                </span>
            </div>
            <div class="p-6" x-data="{
                chartData: @js($tenantGrowthChart),
                get maxCount() { return Math.max(...this.chartData.map(d => d.count), 1) }
            }">
                <div class="flex items-end justify-between gap-3 h-40">
                    <template x-for="(item, i) in chartData" :key="i">
                        <div class="flex-1 flex flex-col items-center gap-2">
                            <span class="text-xs font-bold text-gray-700" x-text="item.count"></span>
                            <div class="w-full rounded-t-lg bg-gradient-to-t from-purple-500 to-purple-400 transition-all duration-700 hover:from-purple-600 hover:to-purple-500"
                                 :style="'height: ' + Math.max((item.count / maxCount) * 120, 4) + 'px'">
                            </div>
                            <span class="text-[10px] text-gray-500 font-medium" x-text="item.label"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-chart-area text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Receita Mensal</h3>
                        <p class="text-xs text-gray-500">Últimos 6 meses</p>
                    </div>
                </div>
                @if($stats['pending_revenue'] > 0)
                <span class="text-xs font-medium px-2 py-1 rounded-full bg-yellow-100 text-yellow-700">
                    {{ number_format($stats['pending_revenue'], 0, ',', '.') }} Kz pendente
                </span>
                @endif
            </div>
            <div class="p-6" x-data="{
                chartData: @js($revenueChart),
                get maxAmount() { return Math.max(...this.chartData.map(d => d.amount), 1) }
            }">
                <div class="flex items-end justify-between gap-3 h-40">
                    <template x-for="(item, i) in chartData" :key="i">
                        <div class="flex-1 flex flex-col items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-600" x-text="(item.amount/1000).toFixed(0) + 'k'"></span>
                            <div class="w-full rounded-t-lg bg-gradient-to-t from-orange-500 to-yellow-400 transition-all duration-700 hover:from-orange-600 hover:to-yellow-500"
                                 :style="'height: ' + Math.max((item.amount / maxAmount) * 120, 4) + 'px'">
                            </div>
                            <span class="text-[10px] text-gray-500 font-medium" x-text="item.label"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Plans Distribution + Top Modules -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Plans Distribution -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-tags text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Distribuição por Plano</h3>
                        <p class="text-xs text-gray-500">Subscrições activas por plano</p>
                    </div>
                </div>
                <a href="{{ route('superadmin.plans') }}" class="text-xs text-blue-600 hover:text-blue-700 font-medium">Gerir Planos →</a>
            </div>
            <div class="p-6">
                @php $totalPlanSubs = collect($plansDistribution)->sum('count'); @endphp
                @forelse($plansDistribution as $plan)
                    <div class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="w-3 h-3 rounded-full mr-3 flex-shrink-0" style="background-color: {{ $plan['color'] }}"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $plan['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ number_format($plan['price'], 0, ',', '.') }} Kz/mês</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <div class="w-24 bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-500" 
                                     style="width: {{ $totalPlanSubs > 0 ? round(($plan['count'] / $totalPlanSubs) * 100) : 0 }}%; background-color: {{ $plan['color'] }}"></div>
                            </div>
                            <span class="text-sm font-bold text-gray-700 w-8 text-right">{{ $plan['count'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-tags text-gray-300 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-500">Nenhum plano configurado</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Top Modules -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-th-large text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Módulos Mais Usados</h3>
                        <p class="text-xs text-gray-500">Tenants activos por módulo</p>
                    </div>
                </div>
                <a href="{{ route('superadmin.modules') }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Ver Todos →</a>
            </div>
            <div class="p-6">
                @php $maxModCount = collect($topModules)->max('count') ?: 1; @endphp
                @forelse($topModules as $mod)
                    <div class="flex items-center justify-between py-2.5 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="w-8 h-8 rounded-lg {{ $mod['is_core'] ? 'bg-indigo-100' : 'bg-gray-100' }} flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="{{ $mod['icon'] }} {{ $mod['is_core'] ? 'text-indigo-600' : 'text-gray-600' }} text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $mod['name'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <div class="w-20 bg-gray-100 rounded-full h-1.5">
                                <div class="h-1.5 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all duration-500" 
                                     style="width: {{ round(($mod['count'] / $maxModCount) * 100) }}%"></div>
                            </div>
                            @if($mod['is_core'])
                                <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded">CORE</span>
                            @endif
                            <span class="text-sm font-bold text-gray-700 w-6 text-right">{{ $mod['count'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-puzzle-piece text-gray-300 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-500">Nenhum módulo registado</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Row 4: Top Subscriptions + Expiring Subscriptions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top Subscriptions by Value -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-trophy text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Top Subscrições</h3>
                        <p class="text-xs text-gray-500">Maiores valores activos</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                @forelse($topSubscriptions as $sub)
                    <div class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center mr-3 flex-shrink-0">
                                <span class="text-white text-xs font-bold">{{ $loop->iteration }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $sub->tenant->name ?? 'N/A' }}</p>
                                <p class="text-xs text-gray-500">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 font-medium">{{ $sub->plan->name ?? 'N/A' }}</span>
                                    <span class="ml-1">{{ ucfirst($sub->billing_cycle ?? 'monthly') }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <p class="text-sm font-bold text-gray-900">{{ number_format($sub->amount ?? 0, 0, ',', '.') }} Kz</p>
                            @if($sub->current_period_end)
                                <p class="text-[10px] text-gray-500">até {{ $sub->current_period_end->format('d/m/Y') }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-trophy text-gray-300 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-500">Nenhuma subscrição activa</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Expiring Soon -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-red-500 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-exclamation-triangle text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">A Expirar em 30 Dias</h3>
                        <p class="text-xs text-gray-500">Subscrições que precisam de renovação</p>
                    </div>
                </div>
                @if($expiringSubscriptions->count() > 0)
                    <span class="text-xs font-bold px-2 py-1 rounded-full bg-red-100 text-red-700 animate-pulse">{{ $expiringSubscriptions->count() }}</span>
                @endif
            </div>
            <div class="p-6">
                @forelse($expiringSubscriptions as $sub)
                    @php
                        $daysLeft = now()->diffInDays($sub->current_period_end, false);
                        $urgency = $daysLeft <= 7 ? 'red' : ($daysLeft <= 15 ? 'amber' : 'yellow');
                    @endphp
                    <div class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-{{ $urgency }}-100 flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas fa-hourglass-half text-{{ $urgency }}-600 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $sub->tenant->name ?? 'N/A' }}</p>
                                <p class="text-xs text-gray-500">{{ $sub->plan->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-{{ $urgency }}-100 text-{{ $urgency }}-700">
                                {{ $daysLeft }} {{ $daysLeft == 1 ? 'dia' : 'dias' }}
                            </span>
                            <p class="text-[10px] text-gray-500 mt-1">{{ $sub->current_period_end->format('d/m/Y') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-700">Tudo em dia!</p>
                        <p class="text-xs text-gray-500">Nenhuma subscrição a expirar nos próximos 30 dias</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Row 5: Recent Tenants + Recent Orders -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Recent Tenants -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-building text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Tenants Recentes</h3>
                        <p class="text-xs text-gray-500">+{{ $stats['new_tenants_month'] }} este mês</p>
                    </div>
                </div>
                <a href="{{ route('superadmin.tenants') }}" class="text-xs text-blue-600 hover:text-blue-700 font-medium">Ver todos →</a>
            </div>
            <div class="p-4">
                @forelse($recentTenants as $tenant)
                    <div class="group flex items-center justify-between py-3 px-3 hover:bg-gray-50 rounded-xl transition-colors {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="relative flex-shrink-0 mr-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center">
                                    <span class="text-white font-bold text-xs">{{ strtoupper(substr($tenant->name, 0, 2)) }}</span>
                                </div>
                                <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 {{ $tenant->is_active ? 'bg-green-500' : 'bg-gray-400' }} rounded-full border-2 border-white"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $tenant->name }}</p>
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <span>{{ $tenant->created_at->format('d/m/Y') }}</span>
                                    <span class="text-gray-300">|</span>
                                    <span>{{ $tenant->users_count ?? 0 }} users</span>
                                    <span class="text-gray-300">|</span>
                                    <span>{{ $tenant->modules->count() }} mod.</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                            @if($tenant->activeSubscription && $tenant->activeSubscription->plan)
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">{{ $tenant->activeSubscription->plan->name }}</span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full {{ $tenant->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $tenant->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                            <div class="hidden group-hover:flex space-x-1">
                                <button wire:click="manageTenant({{ $tenant->id }})" class="w-6 h-6 rounded flex items-center justify-center hover:bg-blue-100 transition-colors" title="Gerir">
                                    <i class="fas fa-external-link-alt text-blue-600 text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-building text-gray-300 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-500">Nenhum tenant registado</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-rose-500 to-pink-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-shopping-cart text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Pedidos Recentes</h3>
                        <p class="text-xs text-gray-500">Últimas solicitações de planos</p>
                    </div>
                </div>
                <a href="{{ route('superadmin.billing') }}" class="text-xs text-rose-600 hover:text-rose-700 font-medium">Ver todos →</a>
            </div>
            <div class="p-4">
                @forelse($recentOrders as $order)
                    @php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-700',
                            'approved' => 'bg-green-100 text-green-700',
                            'rejected' => 'bg-red-100 text-red-700',
                            'cancelled' => 'bg-gray-100 text-gray-700',
                        ];
                        $statusLabels = [
                            'pending' => 'Pendente',
                            'approved' => 'Aprovado',
                            'rejected' => 'Rejeitado',
                            'cancelled' => 'Cancelado',
                        ];
                    @endphp
                    <div class="flex items-center justify-between py-3 px-3 hover:bg-gray-50 rounded-xl transition-colors {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="w-10 h-10 rounded-xl {{ $order->status === 'pending' ? 'bg-yellow-100' : ($order->status === 'approved' ? 'bg-green-100' : 'bg-gray-100') }} flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas {{ $order->status === 'pending' ? 'fa-hourglass-half text-yellow-600' : ($order->status === 'approved' ? 'fa-check text-green-600' : 'fa-times text-gray-600') }}"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $order->tenant->name ?? 'N/A' }}</p>
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <span>{{ $order->plan->name ?? 'N/A' }}</span>
                                    <span class="text-gray-300">|</span>
                                    <span>{{ $order->created_at->format('d/m/Y') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                            <span class="text-sm font-bold text-gray-700">{{ number_format($order->amount ?? 0, 0, ',', '.') }} Kz</span>
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <i class="fas fa-shopping-cart text-gray-300 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-500">Nenhum pedido registado</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Row 6: Quick Actions -->
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-gradient-to-br from-gray-700 to-gray-900 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-bolt text-yellow-400"></i>
            </div>
            <h3 class="text-sm font-bold text-gray-900">Acções Rápidas</h3>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
            <a href="{{ route('superadmin.tenants') }}" class="flex flex-col items-center p-3 rounded-xl hover:bg-purple-50 transition-colors group">
                <div class="w-10 h-10 bg-purple-100 group-hover:bg-purple-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                    <i class="fas fa-plus text-purple-600"></i>
                </div>
                <span class="text-xs font-medium text-gray-700 text-center">Novo Tenant</span>
            </a>
            <a href="{{ route('superadmin.plans') }}" class="flex flex-col items-center p-3 rounded-xl hover:bg-blue-50 transition-colors group">
                <div class="w-10 h-10 bg-blue-100 group-hover:bg-blue-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                    <i class="fas fa-tags text-blue-600"></i>
                </div>
                <span class="text-xs font-medium text-gray-700 text-center">Planos</span>
            </a>
            <a href="{{ route('superadmin.modules') }}" class="flex flex-col items-center p-3 rounded-xl hover:bg-indigo-50 transition-colors group">
                <div class="w-10 h-10 bg-indigo-100 group-hover:bg-indigo-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                    <i class="fas fa-puzzle-piece text-indigo-600"></i>
                </div>
                <span class="text-xs font-medium text-gray-700 text-center">Módulos</span>
            </a>
            <a href="{{ route('superadmin.billing') }}" class="flex flex-col items-center p-3 rounded-xl hover:bg-green-50 transition-colors group">
                <div class="w-10 h-10 bg-green-100 group-hover:bg-green-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                    <i class="fas fa-file-invoice-dollar text-green-600"></i>
                </div>
                <span class="text-xs font-medium text-gray-700 text-center">Billing</span>
            </a>
            <a href="{{ route('superadmin.system-commands') }}" class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                <div class="w-10 h-10 bg-gray-200 group-hover:bg-gray-300 rounded-lg flex items-center justify-center mb-2 transition-colors">
                    <i class="fas fa-terminal text-gray-700"></i>
                </div>
                <span class="text-xs font-medium text-gray-700 text-center">Comandos</span>
            </a>
            <a href="{{ route('superadmin.system-settings') }}" class="flex flex-col items-center p-3 rounded-xl hover:bg-orange-50 transition-colors group">
                <div class="w-10 h-10 bg-orange-100 group-hover:bg-orange-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                    <i class="fas fa-cog text-orange-600"></i>
                </div>
                <span class="text-xs font-medium text-gray-700 text-center">Definições</span>
            </a>
            <a href="{{ route('superadmin.email-templates') }}" class="flex flex-col items-center p-3 rounded-xl hover:bg-cyan-50 transition-colors group">
                <div class="w-10 h-10 bg-cyan-100 group-hover:bg-cyan-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                    <i class="fas fa-envelope text-cyan-600"></i>
                </div>
                <span class="text-xs font-medium text-gray-700 text-center">Emails</span>
            </a>
            <a href="{{ route('superadmin.system-optimization') }}" class="flex flex-col items-center p-3 rounded-xl hover:bg-rose-50 transition-colors group">
                <div class="w-10 h-10 bg-rose-100 group-hover:bg-rose-200 rounded-lg flex items-center justify-center mb-2 transition-colors">
                    <i class="fas fa-tachometer-alt text-rose-600"></i>
                </div>
                <span class="text-xs font-medium text-gray-700 text-center">Optimização</span>
            </a>
        </div>
    </div>
</div>
