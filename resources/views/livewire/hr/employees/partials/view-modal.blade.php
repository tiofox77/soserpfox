{{-- Modal de Visualização de Funcionário --}}
@if($showViewModal && $viewingEmployee)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: true }" x-show="show" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100">
    
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black bg-opacity-75 backdrop-blur-sm" wire:click="closeViewModal"></div>
    
    {{-- Modal --}}
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden border-4 border-blue-500"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100">
            
            {{-- Header --}}
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-2xl text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Detalhes do Funcionário</h3>
                            <p class="text-blue-100 text-sm">{{ $viewingEmployee->full_name }}</p>
                        </div>
                    </div>
                    <button wire:click="closeViewModal" class="text-white hover:bg-white/20 p-2 rounded-lg transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-160px)]">
                
                {{-- Dados Pessoais --}}
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-blue-700 mb-4 flex items-center border-b-2 border-blue-200 pb-2">
                        <i class="fas fa-user-circle mr-2"></i>Dados Pessoais
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Nome Completo</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->full_name }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Email</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->email ?: '—' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Telefone</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->phone ?: '—' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Telemóvel</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->mobile ?: '—' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Data Nascimento</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->birth_date ? $viewingEmployee->birth_date->format('d/m/Y') : '—' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Gênero</label>
                            <p class="font-bold text-gray-800">
                                @if($viewingEmployee->gender === 'male') Masculino
                                @elseif($viewingEmployee->gender === 'female') Feminino
                                @else —
                                @endif
                            </p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">NIF</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->nif ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Dados Profissionais --}}
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-green-700 mb-4 flex items-center border-b-2 border-green-200 pb-2">
                        <i class="fas fa-briefcase mr-2"></i>Dados Profissionais
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Número Funcionário</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->employee_number }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Departamento</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->department->name ?? '—' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Cargo</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->position->title ?? '—' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Data Admissão</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->hire_date ? $viewingEmployee->hire_date->format('d/m/Y') : '—' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Tipo Contrato</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->employment_type }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Status</label>
                            <p class="font-bold">
                                @if($viewingEmployee->status === 'active')
                                    <span class="text-green-600">✓ Ativo</span>
                                @elseif($viewingEmployee->status === 'suspended')
                                    <span class="text-yellow-600">⏸ Suspenso</span>
                                @else
                                    <span class="text-red-600">✗ Inativo</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Documentos --}}
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-purple-700 mb-4 flex items-center border-b-2 border-purple-200 pb-2">
                        <i class="fas fa-id-card mr-2"></i>Documentos
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($viewingEmployee->bi_number)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-id-card text-purple-500 mr-1"></i>Bilhete Identidade</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->bi_number }}</p>
                            @if($viewingEmployee->bi_expiry_date)
                            <p class="text-xs text-gray-600 mt-1">Validade: {{ $viewingEmployee->bi_expiry_date->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        @endif
                        
                        @if($viewingEmployee->passport_number)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-passport text-blue-500 mr-1"></i>Passaporte</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->passport_number }}</p>
                            @if($viewingEmployee->passport_expiry_date)
                            <p class="text-xs text-gray-600 mt-1">Validade: {{ $viewingEmployee->passport_expiry_date->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        @endif

                        @if($viewingEmployee->work_permit_number)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-file-contract text-indigo-500 mr-1"></i>Autorização de Trabalho</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->work_permit_number }}</p>
                            @if($viewingEmployee->work_permit_expiry_date)
                            <p class="text-xs text-gray-600 mt-1">Validade: {{ $viewingEmployee->work_permit_expiry_date->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        @endif

                        @if($viewingEmployee->residence_permit_number)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-home text-green-500 mr-1"></i>Visto de Residência</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->residence_permit_number }}</p>
                            @if($viewingEmployee->residence_permit_expiry_date)
                            <p class="text-xs text-gray-600 mt-1">Validade: {{ $viewingEmployee->residence_permit_expiry_date->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        @endif

                        @if($viewingEmployee->driver_license_number)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-car text-orange-500 mr-1"></i>Carta de Condução</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->driver_license_number }}</p>
                            @if($viewingEmployee->driver_license_category)
                            <p class="text-xs text-gray-600 mt-1">Categoria: {{ $viewingEmployee->driver_license_category }}</p>
                            @endif
                            @if($viewingEmployee->driver_license_expiry_date)
                            <p class="text-xs text-gray-600">Validade: {{ $viewingEmployee->driver_license_expiry_date->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        @endif

                        @if($viewingEmployee->health_insurance_number)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-heartbeat text-red-500 mr-1"></i>Seguro de Saúde</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->health_insurance_number }}</p>
                            @if($viewingEmployee->health_insurance_provider)
                            <p class="text-xs text-gray-600 mt-1">Seguradora: {{ $viewingEmployee->health_insurance_provider }}</p>
                            @endif
                            @if($viewingEmployee->health_insurance_expiry_date)
                            <p class="text-xs text-gray-600">Validade: {{ $viewingEmployee->health_insurance_expiry_date->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        @endif

                        @if($viewingEmployee->social_security_number)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-shield-alt text-teal-500 mr-1"></i>Segurança Social</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->social_security_number }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Contratos --}}
                @if($viewingEmployee->contract_expiry_date || $viewingEmployee->probation_end_date)
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-yellow-700 mb-4 flex items-center border-b-2 border-yellow-200 pb-2">
                        <i class="fas fa-file-signature mr-2"></i>Informações Contratuais
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($viewingEmployee->contract_expiry_date)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Fim do Contrato</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->contract_expiry_date->format('d/m/Y') }}</p>
                        </div>
                        @endif

                        @if($viewingEmployee->probation_end_date)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Fim do Período Experimental</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->probation_end_date->format('d/m/Y') }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Remuneração --}}
                @if($viewingEmployee->salary || $viewingEmployee->bonus || $viewingEmployee->transport_allowance || $viewingEmployee->meal_allowance)
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-green-700 mb-4 flex items-center border-b-2 border-green-200 pb-2">
                        <i class="fas fa-money-bill-wave mr-2"></i>Remuneração
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($viewingEmployee->salary)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-coins text-green-500 mr-1"></i>Salário Base</label>
                            <p class="font-bold text-green-700 text-lg">{{ number_format($viewingEmployee->salary, 2, ',', '.') }} Kz</p>
                        </div>
                        @endif

                        @if($viewingEmployee->bonus)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-gift text-purple-500 mr-1"></i>Bônus/Prêmios</label>
                            <p class="font-bold text-purple-700 text-lg">{{ number_format($viewingEmployee->bonus, 2, ',', '.') }} Kz</p>
                        </div>
                        @endif

                        @if($viewingEmployee->transport_allowance)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-car text-blue-500 mr-1"></i>Subsídio Transporte</label>
                            <p class="font-bold text-blue-700 text-lg">{{ number_format($viewingEmployee->transport_allowance, 2, ',', '.') }} Kz</p>
                        </div>
                        @endif

                        @if($viewingEmployee->meal_allowance)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase"><i class="fas fa-utensils text-orange-500 mr-1"></i>Subsídio Alimentação</label>
                            <p class="font-bold text-orange-700 text-lg">{{ number_format($viewingEmployee->meal_allowance, 2, ',', '.') }} Kz</p>
                        </div>
                        @endif

                        {{-- Total --}}
                        <div class="md:col-span-2 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-4">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-gray-700"><i class="fas fa-calculator text-green-600 mr-2"></i>Remuneração Total Mensal</label>
                                <p class="text-2xl font-bold text-green-700">
                                    {{ number_format(($viewingEmployee->salary ?? 0) + ($viewingEmployee->bonus ?? 0) + ($viewingEmployee->transport_allowance ?? 0) + ($viewingEmployee->meal_allowance ?? 0), 2, ',', '.') }} Kz
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Endereço --}}
                @if($viewingEmployee->address)
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-indigo-700 mb-4 flex items-center border-b-2 border-indigo-200 pb-2">
                        <i class="fas fa-map-marker-alt mr-2"></i>Endereço
                    </h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="font-bold text-gray-800">{{ $viewingEmployee->address }}</p>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ $viewingEmployee->city ? $viewingEmployee->city . ', ' : '' }}
                            {{ $viewingEmployee->province }}
                        </p>
                    </div>
                </div>
                @endif

                {{-- Dados Bancários --}}
                @if($viewingEmployee->bank_name)
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-cyan-700 mb-4 flex items-center border-b-2 border-cyan-200 pb-2">
                        <i class="fas fa-university mr-2"></i>Dados Bancários
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Banco</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->bank_name }}</p>
                        </div>
                        @if($viewingEmployee->bank_account)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-xs text-gray-500 uppercase">Conta</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->bank_account }}</p>
                        </div>
                        @endif
                        @if($viewingEmployee->iban)
                        <div class="bg-gray-50 p-4 rounded-lg col-span-2">
                            <label class="text-xs text-gray-500 uppercase">IBAN</label>
                            <p class="font-bold text-gray-800">{{ $viewingEmployee->iban }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Notas --}}
                @if($viewingEmployee->notes)
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-gray-700 mb-4 flex items-center border-b-2 border-gray-200 pb-2">
                        <i class="fas fa-sticky-note mr-2"></i>Observações
                    </h4>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <p class="text-gray-800 whitespace-pre-wrap">{{ $viewingEmployee->notes }}</p>
                    </div>
                </div>
                @endif

            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 border-t flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-calendar mr-2"></i>
                    Criado em {{ $viewingEmployee->created_at->format('d/m/Y H:i') }}
                </div>
                <div class="flex gap-3">
                    <button wire:click="closeViewModal" 
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-100 transition">
                        <i class="fas fa-times mr-2"></i>Fechar
                    </button>
                    <button wire:click="edit({{ $viewingEmployee->id }}); closeViewModal()" 
                            class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition">
                        <i class="fas fa-edit mr-2"></i>Editar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
