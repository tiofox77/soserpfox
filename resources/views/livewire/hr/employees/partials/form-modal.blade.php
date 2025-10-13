<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-data="{ activeTab: 'personal' }"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-user text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        {{ $editMode ? 'Editar Funcionário' : 'Novo Funcionário' }}
                    </h3>
                    <p class="text-blue-100 text-sm">Preencha os dados completos do colaborador</p>
                </div>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:text-blue-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Tabs Navigation --}}
        <div class="bg-gray-50 border-b border-gray-200 px-6">
            <div class="flex space-x-1 -mb-px">
                <button @click="activeTab = 'personal'" 
                        :class="activeTab === 'personal' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-user mr-2"></i>Pessoais
                </button>
                <button @click="activeTab = 'documents'" 
                        :class="activeTab === 'documents' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-id-card mr-2"></i>Documentos
                </button>
                <button @click="activeTab = 'contact'" 
                        :class="activeTab === 'contact' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-address-book mr-2"></i>Contato
                </button>
                <button @click="activeTab = 'professional'" 
                        :class="activeTab === 'professional' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-briefcase mr-2"></i>Profissional
                </button>
                <button @click="activeTab = 'salary'" 
                        :class="activeTab === 'salary' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-money-bill-wave mr-2"></i>Remuneração
                </button>
                <button @click="activeTab = 'banking'" 
                        :class="activeTab === 'banking' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-university mr-2"></i>Bancário
                </button>
            </div>
        </div>

        <form wire:submit.prevent="save" class="overflow-y-auto max-h-[calc(90vh-200px)]">
            
            {{-- Tab: Dados Pessoais --}}
            <div x-show="activeTab === 'personal'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-user mr-1 text-blue-600"></i>Primeiro Nome *
                        </label>
                        <input type="text" wire:model="first_name" 
                               class="w-full px-4 py-2.5 border @error('first_name') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Nome do funcionário">
                        @error('first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-user mr-1 text-blue-600"></i>Último Nome *
                        </label>
                        <input type="text" wire:model="last_name" 
                               class="w-full px-4 py-2.5 border @error('last_name') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Sobrenome">
                        @error('last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1 text-purple-600"></i>Data de Nascimento
                        </label>
                        <input type="date" wire:model="birth_date" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-venus-mars mr-1 text-pink-600"></i>Gênero
                        </label>
                        <select wire:model="gender" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            <option value="">Selecione</option>
                            <option value="M">👨 Masculino</option>
                            <option value="F">👩 Feminino</option>
                            <option value="Outro">🧑 Outro</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-id-card mr-1 text-green-600"></i>NIF
                        </label>
                        <input type="text" wire:model="nif" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Número de Identificação Fiscal">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-shield-alt mr-1 text-cyan-600"></i>Nº Segurança Social (INSS)
                        </label>
                        <input type="text" wire:model="social_security_number" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="INSS">
                    </div>
                </div>
            </div>

            {{-- Tab: Documentos --}}
            <div x-show="activeTab === 'documents'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    {{-- BI (Bilhete de Identidade) --}}
                    <div class="col-span-2 bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-id-card mr-2 text-blue-600"></i>
                            Bilhete de Identidade (BI)
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Nº do BI</label>
                                <input type="text" wire:model="bi_number" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                       placeholder="000000000AA000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Data de Validade</label>
                                <input type="date" wire:model="bi_expiry_date" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Anexar Documento</label>
                                <input type="file" wire:model="bi_document" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (máx. 2MB)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Passaporte --}}
                    <div class="col-span-2 bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-passport mr-2 text-blue-600"></i>
                            Passaporte
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Nº do Passaporte</label>
                                <input type="text" wire:model="passport_number" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                       placeholder="N000000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Data de Validade</label>
                                <input type="date" wire:model="passport_expiry_date" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2"><i class="fas fa-paperclip mr-1"></i>Anexar</label>
                                <input type="file" wire:model="passport_document" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (máx. 2MB)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Autorização de Trabalho --}}
                    <div class="col-span-2 bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-briefcase mr-2 text-green-600"></i>
                            Autorização de Trabalho
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Nº da Autorização</label>
                                <input type="text" wire:model="work_permit_number" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                       placeholder="AT000000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Data de Validade</label>
                                <input type="date" wire:model="work_permit_expiry_date" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2"><i class="fas fa-paperclip mr-1"></i>Anexar</label>
                                <input type="file" wire:model="work_permit_document" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (máx. 2MB)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Autorização de Residência --}}
                    <div class="col-span-2 bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-home mr-2 text-purple-600"></i>
                            Autorização de Residência
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Nº da Autorização</label>
                                <input type="text" wire:model="residence_permit_number" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                       placeholder="AR000000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Data de Validade</label>
                                <input type="date" wire:model="residence_permit_expiry_date" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2"><i class="fas fa-paperclip mr-1"></i>Anexar</label>
                                <input type="file" wire:model="residence_permit_document" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (máx. 2MB)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Carta de Condução --}}
                    <div class="col-span-2 bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-car mr-2 text-orange-600"></i>
                            Carta de Condução
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Nº da Carta</label>
                                <input type="text" wire:model="driver_license_number" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                       placeholder="CC000000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Categoria</label>
                                <input type="text" wire:model="driver_license_category" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                       placeholder="B, C, etc">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Data de Validade</label>
                                <input type="date" wire:model="driver_license_expiry_date" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2"><i class="fas fa-paperclip mr-1"></i>Anexar</label>
                                <input type="file" wire:model="driver_license_document" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (máx. 2MB)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Seguro de Saúde --}}
                    <div class="col-span-2 bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-heart mr-2 text-red-600"></i>
                            Seguro de Saúde
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Nº da Apólice</label>
                                <input type="text" wire:model="health_insurance_number" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                       placeholder="SSxxxxxxxxx">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Seguradora</label>
                                <input type="text" wire:model="health_insurance_provider" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                       placeholder="Nome da seguradora">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Data de Validade</label>
                                <input type="date" wire:model="health_insurance_expiry_date" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2"><i class="fas fa-paperclip mr-1"></i>Anexar</label>
                                <input type="file" wire:model="health_insurance_document" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (máx. 2MB)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Contrato e Período Probatório --}}
                    <div class="col-span-2 bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-file-contract mr-2 text-cyan-600"></i>
                            Contrato e Período Probatório
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Fim do Contrato</label>
                                <input type="date" wire:model="contract_expiry_date" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                                <p class="text-xs text-gray-500 mt-1">Para contratos a termo certo</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Fim do Período Probatório</label>
                                <input type="date" wire:model="probation_end_date" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                                <p class="text-xs text-gray-500 mt-1">Geralmente 3-6 meses</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2"><i class="fas fa-paperclip mr-1"></i>Anexar Contrato</label>
                                <input type="file" wire:model="contract_document" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (máx. 2MB)</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2"><i class="fas fa-paperclip mr-1"></i>Anexar Doc. Período Probatório</label>
                                <input type="file" wire:model="probation_document" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (máx. 2MB)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Registro Criminal --}}
                    <div class="col-span-2 bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-certificate mr-2 text-slate-600"></i>
                            Registro Criminal
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Nº do Certificado</label>
                                <input type="text" wire:model="criminal_record_number" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                       placeholder="CRC000000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2">Data de Emissão</label>
                                <input type="date" wire:model="criminal_record_issue_date" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-2"><i class="fas fa-paperclip mr-1"></i>Anexar</label>
                                <input type="file" wire:model="criminal_record_document" accept=".pdf,.jpg,.jpeg,.png"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG (máx. 2MB)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Info Card --}}
                    <div class="col-span-2 bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-amber-600 text-xl mr-3 mt-1"></i>
                            <div>
                                <p class="text-sm font-semibold text-amber-900 mb-1">⚠️ Alertas Automáticos</p>
                                <p class="text-xs text-amber-700">
                                    O sistema enviará alertas automáticos 30 dias antes do vencimento de qualquer documento. 
                                    Documentos vencidos aparecerão com destaque no painel de funcionários.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Tab: Contato --}}
            <div x-show="activeTab === 'contact'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-1 text-blue-600"></i>Email
                        </label>
                        <input type="email" wire:model="email" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="email@exemplo.com">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1 text-green-600"></i>Telefone
                        </label>
                        <input type="text" wire:model="phone" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="+244 xxx xxx xxx">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-mobile-alt mr-1 text-purple-600"></i>Celular
                        </label>
                        <input type="text" wire:model="mobile" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="+244 xxx xxx xxx">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1 text-red-600"></i>Endereço
                        </label>
                        <textarea wire:model="address" rows="2"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                  placeholder="Rua, bairro, número"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-city mr-1 text-indigo-600"></i>Cidade
                        </label>
                        <input type="text" wire:model="city" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Cidade">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-map mr-1 text-orange-600"></i>Província
                        </label>
                        <input type="text" wire:model="province" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Província">
                    </div>
                </div>
            </div>

            {{-- Tab: Dados Profissionais --}}
            <div x-show="activeTab === 'professional'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-building mr-1 text-purple-600"></i>Departamento
                        </label>
                        <select wire:model="department_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            <option value="">Selecione um departamento</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-briefcase mr-1 text-cyan-600"></i>Cargo
                        </label>
                        <select wire:model="position_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            <option value="">Selecione um cargo</option>
                            @foreach($positions as $pos)
                                <option value="{{ $pos->id }}">{{ $pos->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-plus mr-1 text-green-600"></i>Data de Admissão
                        </label>
                        <input type="date" wire:model="hire_date" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-file-contract mr-1 text-blue-600"></i>Tipo de Emprego *
                        </label>
                        <select wire:model="employment_type" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            <option value="Contrato">📄 Contrato</option>
                            <option value="Freelancer">💼 Freelancer</option>
                            <option value="Estágio">🎓 Estágio</option>
                            <option value="Temporário">⏱️ Temporário</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-toggle-on mr-1 text-indigo-600"></i>Status *
                        </label>
                        <select wire:model="status" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            <option value="active">✅ Ativo</option>
                            <option value="suspended">⏸️ Suspenso</option>
                            <option value="terminated">❌ Desligado</option>
                            <option value="on_leave">🏖️ Em Licença</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1 text-yellow-600"></i>Observações
                        </label>
                        <textarea wire:model="notes" rows="3"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                  placeholder="Observações adicionais sobre o funcionário"></textarea>
                    </div>
                </div>
            </div>

            {{-- Tab: Remuneração --}}
            <div x-show="activeTab === 'salary'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>Salário Base (Kz)
                        </label>
                        <input type="number" wire:model="salary" step="0.01" min="0"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="0,00">
                        <p class="text-xs text-gray-500 mt-1">Salário mensal base do funcionário</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-gift mr-1 text-purple-600"></i>Bônus/Prêmios (Kz)
                        </label>
                        <input type="number" wire:model="bonus" step="0.01" min="0"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="0,00">
                        <p class="text-xs text-gray-500 mt-1">Bônus mensal ou prêmios adicionais</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-car mr-1 text-blue-600"></i>Subsídio de Transporte (Kz)
                        </label>
                        <input type="number" wire:model="transport_allowance" step="0.01" min="0"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="0,00">
                        <p class="text-xs text-gray-500 mt-1">Valor mensal para transporte</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-utensils mr-1 text-orange-600"></i>Subsídio de Alimentação (Kz)
                        </label>
                        <input type="number" wire:model="meal_allowance" step="0.01" min="0"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="0,00">
                        <p class="text-xs text-gray-500 mt-1">Valor mensal para alimentação</p>
                    </div>

                    {{-- Resumo da Remuneração Total --}}
                    <div class="md:col-span-2 mt-4">
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-700 mb-1">
                                        <i class="fas fa-calculator mr-2 text-green-600"></i>Remuneração Total Mensal
                                    </p>
                                    <p class="text-xs text-gray-600">Soma de todos os valores acima</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-3xl font-bold text-green-700">
                                        {{ number_format((floatval($salary ?? 0)) + (floatval($bonus ?? 0)) + (floatval($transport_allowance ?? 0)) + (floatval($meal_allowance ?? 0)), 2, ',', '.') }} Kz
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Info Card --}}
                    <div class="md:col-span-2 bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 text-xl mr-3 mt-1"></i>
                            <div>
                                <p class="text-sm font-semibold text-blue-900 mb-1">Informações sobre Remuneração</p>
                                <ul class="text-xs text-blue-700 space-y-1">
                                    <li>• <strong>Salário Base:</strong> Valor fixo mensal do contrato de trabalho</li>
                                    <li>• <strong>Bônus:</strong> Valores variáveis por desempenho ou metas</li>
                                    <li>• <strong>Subsídio de Transporte:</strong> Ajuda de custo para deslocamento</li>
                                    <li>• <strong>Subsídio de Alimentação:</strong> Ajuda de custo para refeições</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab: Dados Bancários --}}
            <div x-show="activeTab === 'banking'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-university mr-1 text-blue-600"></i>Banco
                        </label>
                        <input type="text" wire:model="bank_name" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Nome do banco">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-credit-card mr-1 text-green-600"></i>Conta Bancária
                        </label>
                        <input type="text" wire:model="bank_account" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="Número da conta">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-barcode mr-1 text-purple-600"></i>IBAN
                        </label>
                        <input type="text" wire:model="iban" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                               placeholder="AO06 XXXX XXXX XXXX XXXX">
                    </div>

                    <div class="md:col-span-3 bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 text-xl mr-3 mt-1"></i>
                            <div>
                                <p class="text-sm font-semibold text-blue-900 mb-1">Informações Bancárias</p>
                                <p class="text-xs text-blue-700">
                                    Estes dados serão utilizados para processamento de pagamentos e folha de pagamento. 
                                    Certifique-se de que as informações estão corretas.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </form>

        {{-- Footer --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
            <div class="text-sm text-gray-600">
                <i class="fas fa-info-circle mr-1"></i>
                Campos marcados com * são obrigatórios
            </div>
            <div class="flex items-center space-x-3">
                <button type="button" 
                        wire:click="closeModal"
                        class="px-6 py-2.5 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="button"
                        wire:click="save"
                        wire:loading.attr="disabled" 
                        wire:target="save"
                        class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Salvar' }}
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin mr-2"></i>{{ $editMode ? 'Atualizando...' : 'Salvando...' }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
