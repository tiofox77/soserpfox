<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeDeleteModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Confirmar Exclusão</h3>
                    <p class="text-red-100 text-sm">Esta ação não pode ser desfeita</p>
                </div>
            </div>
            <button wire:click="closeDeleteModal" 
                    class="text-white hover:text-red-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-trash-alt text-red-600 text-3xl"></i>
                </div>
                <h4 class="text-lg font-bold text-gray-900 mb-2">Excluir Folha de Pagamento?</h4>
                <p class="text-gray-600 mb-4">
                    Tem certeza que deseja excluir a folha de pagamento
                    <strong class="text-gray-900">{{ $months[$deletingPayroll->month] }}/{{ $deletingPayroll->year }}</strong>?
                </p>
            </div>

            {{-- Info da Folha --}}
            <div class="bg-gray-50 rounded-xl p-4 mb-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-gray-600 mb-1">Número:</p>
                        <p class="font-semibold text-gray-900">{{ $deletingPayroll->payroll_number }}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 mb-1">Status:</p>
                        <p class="font-semibold text-gray-900">
                            @if($deletingPayroll->status === 'draft')
                                Rascunho
                            @elseif($deletingPayroll->status === 'processing')
                                Processando
                            @elseif($deletingPayroll->status === 'approved')
                                Aprovada
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-600 mb-1">Funcionários:</p>
                        <p class="font-semibold text-gray-900">{{ $deletingPayroll->total_employees }}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 mb-1">Total Líquido:</p>
                        <p class="font-semibold text-green-600">{{ number_format($deletingPayroll->total_net_salary, 2, ',', '.') }} Kz</p>
                    </div>
                </div>
            </div>

            {{-- Warning --}}
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl mr-3 mt-0.5"></i>
                    <div>
                        <h5 class="text-sm font-bold text-red-900 mb-1">Atenção!</h5>
                        <ul class="text-xs text-red-800 space-y-1">
                            <li>• Todos os <strong>{{ $deletingPayroll->total_employees }} itens de funcionários</strong> serão excluídos</li>
                            <li>• Os cálculos de <strong>IRT e INSS</strong> serão perdidos</li>
                            <li>• Esta ação <strong>não pode ser desfeita</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-gray-200">
            <button wire:click="closeDeleteModal" 
                    type="button"
                    class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button wire:click="confirmDelete" 
                    type="button"
                    class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                <i class="fas fa-trash mr-2"></i>Sim, Excluir
            </button>
        </div>
    </div>
</div>
