<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeCreateModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-plus text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Nova Folha de Pagamento</h3>
                    <p class="text-green-100 text-sm">Criar folha mensal para funcionários ativos</p>
                </div>
            </div>
            <button wire:click="closeCreateModal" 
                    class="text-white hover:text-green-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                {{-- Ano --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-calendar mr-1 text-green-600"></i>Ano *
                    </label>
                    <select wire:model="createYear" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all @error('createYear') border-red-500 @enderror">
                        @foreach($years as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                    @error('createYear')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Mês --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-calendar-alt mr-1 text-green-600"></i>Mês *
                    </label>
                    <select wire:model="createMonth" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all @error('createMonth') border-red-500 @enderror">
                        @foreach($months as $num => $name)
                            <option value="{{ $num }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('createMonth')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Info Box --}}
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg mb-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 text-xl mr-3 mt-0.5"></i>
                    <div>
                        <h4 class="text-sm font-bold text-blue-900 mb-1">Informações Importantes</h4>
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li>• A folha será criada para <strong>todos os funcionários ativos</strong></li>
                            <li>• Cálculos de <strong>IRT e INSS</strong> conforme legislação angolana</li>
                            <li>• Dados baseados em contratos e configurações de RH</li>
                            <li>• Status inicial: <strong>Rascunho</strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Configurações Aplicadas --}}
            <div class="bg-green-50 border border-green-200 p-4 rounded-xl">
                <h4 class="text-sm font-bold text-green-900 mb-3 flex items-center">
                    <i class="fas fa-cog mr-2"></i>Configurações Aplicadas (Lei Angolana)
                </h4>
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="flex items-center justify-between bg-white p-2 rounded-lg">
                        <span class="text-gray-600">INSS Empregado:</span>
                        <span class="font-bold text-gray-900">3%</span>
                    </div>
                    <div class="flex items-center justify-between bg-white p-2 rounded-lg">
                        <span class="text-gray-600">INSS Empregador:</span>
                        <span class="font-bold text-gray-900">8%</span>
                    </div>
                    <div class="flex items-center justify-between bg-white p-2 rounded-lg">
                        <span class="text-gray-600">IRT:</span>
                        <span class="font-bold text-gray-900">Progressivo</span>
                    </div>
                    <div class="flex items-center justify-between bg-white p-2 rounded-lg">
                        <span class="text-gray-600">Subsídios (até 30k):</span>
                        <span class="font-bold text-green-700">Isentos</span>
                    </div>
                </div>
                <div class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-xs text-yellow-800">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <strong>Subsídios:</strong> Isentos de IRT até 30.000 Kz cada (alimentação/transporte). Valores acima tributam apenas o excedente.
                    </p>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-gray-200">
            <button wire:click="closeCreateModal" 
                    type="button"
                    class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button wire:click="createPayroll" 
                    type="button"
                    class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                <i class="fas fa-check mr-2"></i>Criar Folha
            </button>
        </div>
    </div>
</div>
