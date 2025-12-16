{{-- Modal Visualizacao --}}
@if($showViewModal && $viewingGuest)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeViewModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl m-4 max-h-[90vh] overflow-hidden">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center text-white text-2xl font-bold">
                        {{ strtoupper(substr($viewingGuest->name, 0, 2)) }}
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white flex items-center gap-2">
                            {{ $viewingGuest->name }}
                            @if($viewingGuest->hotel_vip)
                                <span class="px-2 py-1 bg-yellow-400 text-yellow-900 text-xs rounded-full font-bold">
                                    <i class="fas fa-crown mr-1"></i>VIP
                                </span>
                            @endif
                            @if($viewingGuest->hotel_blacklisted)
                                <span class="px-2 py-1 bg-red-500 text-white text-xs rounded-full font-bold">
                                    <i class="fas fa-ban mr-1"></i>BLOQUEADO
                                </span>
                            @endif
                        </h3>
                        <p class="text-purple-100 text-sm">{{ $viewingGuest->email ?? 'Sem email' }}</p>
                    </div>
                </div>
                <button wire:click="closeViewModal" class="text-white/80 hover:text-white p-2 hover:bg-white/20 rounded-lg transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div class="p-6 space-y-4 overflow-y-auto max-h-[60vh]">
            @php
                $loyaltyData = [];
                if ($viewingGuest->notes && str_starts_with($viewingGuest->notes, '{')) {
                    $loyaltyData = json_decode($viewingGuest->notes, true) ?? [];
                }
                $totalVisits = $loyaltyData['total_visits'] ?? 0;
                $totalSpent = $loyaltyData['total_spent'] ?? 0;
                $loyaltyPoints = $loyaltyData['loyalty_points'] ?? 0;
                $lastVisit = isset($loyaltyData['last_visit_at']) ? \Carbon\Carbon::parse($loyaltyData['last_visit_at']) : null;
            @endphp

            {{-- Fidelidade --}}
            <div class="bg-gradient-to-r from-amber-50 to-yellow-50 rounded-xl p-4 border border-yellow-200">
                <h4 class="font-bold text-yellow-700 mb-3 flex items-center gap-2">
                    <i class="fas fa-award"></i> Programa de Fidelidade
                </h4>
                <div class="grid grid-cols-4 gap-3">
                    <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                        <div class="w-10 h-10 mx-auto mb-2 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-check text-purple-600"></i>
                        </div>
                        <p class="text-2xl font-bold text-gray-900">{{ $totalVisits }}</p>
                        <p class="text-xs text-gray-500">Visitas</p>
                    </div>
                    <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                        <div class="w-10 h-10 mx-auto mb-2 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-green-600"></i>
                        </div>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalSpent / 1000, 0) }}k</p>
                        <p class="text-xs text-gray-500">Gasto (Kz)</p>
                    </div>
                    <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                        <div class="w-10 h-10 mx-auto mb-2 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-star text-yellow-600"></i>
                        </div>
                        <p class="text-2xl font-bold text-gray-900">{{ $loyaltyPoints }}</p>
                        <p class="text-xs text-gray-500">Pontos</p>
                    </div>
                    <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                        <div class="w-10 h-10 mx-auto mb-2 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-blue-600"></i>
                        </div>
                        <p class="text-sm font-bold text-gray-900">{{ $lastVisit ? $lastVisit->format('d/m/Y') : '-' }}</p>
                        <p class="text-xs text-gray-500">Ultima Visita</p>
                    </div>
                </div>
            </div>

            {{-- Info Cards --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-purple-50 rounded-xl p-4">
                    <div class="flex items-center gap-2 text-purple-600 mb-1">
                        <i class="fas fa-phone"></i>
                        <span class="text-xs font-semibold uppercase">Telefone</span>
                    </div>
                    <p class="font-bold text-gray-900">{{ $viewingGuest->phone ?? '-' }}</p>
                </div>
                <div class="bg-indigo-50 rounded-xl p-4">
                    <div class="flex items-center gap-2 text-indigo-600 mb-1">
                        <i class="fas fa-id-card"></i>
                        <span class="text-xs font-semibold uppercase">Documento</span>
                    </div>
                    <p class="font-bold text-gray-900">{{ $viewingGuest->document_number ?? '-' }}</p>
                </div>
                <div class="bg-green-50 rounded-xl p-4">
                    <div class="flex items-center gap-2 text-green-600 mb-1">
                        <i class="fas fa-globe"></i>
                        <span class="text-xs font-semibold uppercase">Nacionalidade</span>
                    </div>
                    <p class="font-bold text-gray-900">{{ $viewingGuest->nationality ?? '-' }}</p>
                </div>
                <div class="bg-blue-50 rounded-xl p-4">
                    <div class="flex items-center gap-2 text-blue-600 mb-1">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="text-xs font-semibold uppercase">Pais</span>
                    </div>
                    <p class="font-bold text-gray-900">{{ $viewingGuest->country ?? 'Angola' }}</p>
                </div>
            </div>

            {{-- Detalhes --}}
            <div class="bg-gray-50 rounded-xl p-4">
                <h4 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <i class="fas fa-info-circle text-purple-500"></i> Detalhes
                </h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-gray-500">NIF:</span>
                        <span class="font-medium text-gray-900 ml-2">{{ $viewingGuest->nif ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Genero:</span>
                        <span class="font-medium text-gray-900 ml-2">
                            @if($viewingGuest->gender === 'male') Masculino
                            @elseif($viewingGuest->gender === 'female') Feminino
                            @else -
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">Nascimento:</span>
                        <span class="font-medium text-gray-900 ml-2">{{ $viewingGuest->birth_date?->format('d/m/Y') ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Cidade:</span>
                        <span class="font-medium text-gray-900 ml-2">{{ $viewingGuest->city ?? '-' }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-gray-500">Endereco:</span>
                        <span class="font-medium text-gray-900 ml-2">{{ $viewingGuest->address ?? '-' }}</span>
                    </div>
                </div>
            </div>

            @if($viewingGuest->notes && !str_starts_with($viewingGuest->notes, '{'))
            <div class="bg-yellow-50 rounded-xl p-4">
                <h4 class="font-bold text-yellow-700 mb-2 flex items-center gap-2">
                    <i class="fas fa-sticky-note"></i> Notas
                </h4>
                <p class="text-gray-700 text-sm">{{ $viewingGuest->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 border-t flex justify-between">
            <div class="flex gap-2">
                <button wire:click="toggleVip({{ $viewingGuest->id }})" class="px-4 py-2 {{ $viewingGuest->hotel_vip ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600' }} rounded-lg hover:opacity-80 transition font-medium text-sm">
                    <i class="fas fa-crown mr-1"></i>{{ $viewingGuest->hotel_vip ? 'Remover VIP' : 'Tornar VIP' }}
                </button>
                <button wire:click="toggleBlacklist({{ $viewingGuest->id }})" class="px-4 py-2 {{ $viewingGuest->hotel_blacklisted ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }} rounded-lg hover:opacity-80 transition font-medium text-sm">
                    <i class="fas fa-ban mr-1"></i>{{ $viewingGuest->hotel_blacklisted ? 'Desbloquear' : 'Bloquear' }}
                </button>
            </div>
            <button wire:click="edit({{ $viewingGuest->id }})" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-semibold">
                <i class="fas fa-edit mr-2"></i>Editar
            </button>
        </div>
    </div>
</div>
@endif
