{{-- Modal Visualizacao --}}
@if($showViewModal && $viewingStaff)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showViewModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl m-4 overflow-hidden">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-teal-600 to-cyan-700 p-6 text-white">
            <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-xl overflow-hidden bg-white/20 flex items-center justify-center">
                    @if($viewingStaff->photo)
                        <img src="{{ asset('storage/' . $viewingStaff->photo) }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-3xl font-bold">{{ $viewingStaff->initials }}</span>
                    @endif
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ $viewingStaff->name }}</h2>
                    <p class="text-teal-100">{{ $viewingStaff->position_label }}</p>
                    <span class="inline-block mt-1 px-3 py-1 bg-white/20 rounded-lg text-sm">{{ $viewingStaff->department_label }}</span>
                </div>
                <button wire:click="$set('showViewModal', false)" class="ml-auto text-white/80 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        {{-- Conteudo --}}
        <div class="p-6 max-h-[60vh] overflow-y-auto">
            {{-- Status --}}
            <div class="mb-6 flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-sm font-bold {{ $viewingStaff->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    <i class="fas {{ $viewingStaff->is_active ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                    {{ $viewingStaff->is_active ? 'Ativo' : 'Inativo' }}
                </span>
                @if($viewingStaff->hire_date)
                <span class="text-sm text-gray-500">Desde {{ $viewingStaff->hire_date->format('d/m/Y') }}</span>
                @endif
            </div>

            {{-- Contacto --}}
            <div class="grid grid-cols-2 gap-4 mb-6">
                @if($viewingStaff->email)
                <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl">
                    <i class="fas fa-envelope text-blue-500"></i>
                    <div><p class="text-xs text-gray-500">Email</p><p class="font-semibold text-gray-900">{{ $viewingStaff->email }}</p></div>
                </div>
                @endif
                @if($viewingStaff->phone)
                <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl">
                    <i class="fas fa-phone text-green-500"></i>
                    <div><p class="text-xs text-gray-500">Telefone</p><p class="font-semibold text-gray-900">{{ $viewingStaff->phone }}</p></div>
                </div>
                @endif
                @if($viewingStaff->document)
                <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl">
                    <i class="fas fa-id-card text-purple-500"></i>
                    <div><p class="text-xs text-gray-500">Documento</p><p class="font-semibold text-gray-900">{{ $viewingStaff->document }}</p></div>
                </div>
                @endif
                @if($viewingStaff->address)
                <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl">
                    <i class="fas fa-map-marker-alt text-red-500"></i>
                    <div><p class="text-xs text-gray-500">Endereco</p><p class="font-semibold text-gray-900">{{ $viewingStaff->address }}</p></div>
                </div>
                @endif
            </div>

            {{-- Horario --}}
            <div class="mb-6 bg-green-50 rounded-xl p-4">
                <h4 class="font-bold text-green-700 mb-3"><i class="fas fa-clock mr-2"></i>Horario</h4>
                <div class="flex items-center gap-4 mb-3">
                    <span class="text-lg font-bold text-gray-900">{{ $viewingStaff->work_start?->format('H:i') ?? '08:00' }} - {{ $viewingStaff->work_end?->format('H:i') ?? '17:00' }}</span>
                </div>
                <div class="flex gap-2">
                    @foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'] as $i => $day)
                    <span class="flex-1 py-2 text-center rounded-lg text-xs font-bold {{ in_array($i, $viewingStaff->working_days ?? []) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400' }}">{{ $day }}</span>
                    @endforeach
                </div>
            </div>

            {{-- Salario --}}
            @if($viewingStaff->monthly_salary > 0 || $viewingStaff->hourly_rate > 0)
            <div class="mb-6 bg-amber-50 rounded-xl p-4">
                <h4 class="font-bold text-amber-700 mb-3"><i class="fas fa-coins mr-2"></i>Remuneracao</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center"><p class="text-xs text-gray-500">Salario Mensal</p><p class="text-xl font-bold text-gray-900">{{ number_format($viewingStaff->monthly_salary, 0, ',', '.') }} Kz</p></div>
                    <div class="text-center"><p class="text-xs text-gray-500">Taxa/Hora</p><p class="text-xl font-bold text-gray-900">{{ number_format($viewingStaff->hourly_rate, 0, ',', '.') }} Kz</p></div>
                </div>
            </div>
            @endif

            {{-- Notas --}}
            @if($viewingStaff->notes)
            <div class="p-3 bg-yellow-50 rounded-xl">
                <p class="text-sm text-yellow-700"><i class="fas fa-sticky-note mr-2"></i>{{ $viewingStaff->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="border-t bg-gray-50 px-6 py-4 flex justify-between">
            <span class="text-sm text-gray-500">Criado em {{ $viewingStaff->created_at->format('d/m/Y') }}</span>
            <div class="flex gap-2">
                <button wire:click="$set('showViewModal', false)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition font-medium">Fechar</button>
                <button wire:click="openModal({{ $viewingStaff->id }})" class="px-4 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition font-semibold"><i class="fas fa-edit mr-2"></i>Editar</button>
            </div>
        </div>
    </div>
</div>
@endif
