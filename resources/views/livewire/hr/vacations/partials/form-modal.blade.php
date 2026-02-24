{{-- Modal Form - Solicitar Férias --}}
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" wire:click="closeModal"></div>

        {{-- Modal Panel --}}
        <div class="relative inline-block w-full max-w-3xl p-6 my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl max-h-[90vh] overflow-y-auto">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-umbrella-beach text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ $editMode ? 'Editar Solicitação de Férias' : 'Nova Solicitação de Férias' }}
                    </h3>
                </div>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Body --}}
            <form wire:submit.prevent="save" class="mt-4">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Funcionário --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Funcionário <span class="text-red-500">*</span>
                            </label>
                            <select wire:model.live="employee_id" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('employee_id') border-red-500 @enderror">
                                <option value="">Selecione o funcionário</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">
                                        {{ $employee->full_name }} ({{ $employee->employee_number }})
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Ano de Referência --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Ano de Referência <span class="text-red-500">*</span>
                            </label>
                            <select wire:model.live="reference_year" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('reference_year') border-red-500 @enderror">
                                @for($year = date('Y') - 1; $year <= date('Y') + 1; $year++)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                @endfor
                            </select>
                            @error('reference_year')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Tipo de Férias --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Tipo de Férias <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="vacation_type" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('vacation_type') border-red-500 @enderror">
                                <option value="normal">Normal (Regulares)</option>
                                <option value="accumulated">Acumuladas (2 anos juntos)</option>
                                <option value="advance">Antecipadas</option>
                                <option value="collective">Coletivas (Empresa)</option>
                            </select>
                            @error('vacation_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Permitir Divisão --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Opções</label>
                            <label class="flex items-center p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition">
                                <input type="checkbox" wire:model="can_split" class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500 mr-3">
                                <span class="text-sm text-gray-700">Permitir dividir férias em períodos (até 3x)</span>
                            </label>
                        </div>
                    </div>

                    {{-- Dias Disponíveis --}}
                    @if($employee_id)
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-600 text-xl mr-3"></i>
                                <div>
                                    <p class="text-sm font-semibold text-blue-900">Dias de férias disponíveis</p>
                                    <p class="text-2xl font-bold text-blue-600">{{ $availableDays }} dias úteis</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Data de Início --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Data de Início <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   wire:model.live="start_date" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('start_date') border-red-500 @enderror"
                                   min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                            @error('start_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Data de Término --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Data de Término <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   wire:model.live="end_date" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('end_date') border-red-500 @enderror"
                                   min="{{ $start_date ? date('Y-m-d', strtotime($start_date . '+1 day')) : date('Y-m-d', strtotime('+2 days')) }}">
                            @error('end_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Resumo de Dias --}}
                    @if($start_date && $end_date)
                        <div class="bg-gray-50 rounded-xl p-4">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Dias Corridos</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ $requestedDays }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Dias Úteis</p>
                                    <p class="text-2xl font-bold text-purple-600">{{ $workingDays }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Valor Total</p>
                                    <p class="text-2xl font-bold text-green-600">{{ number_format($totalAmount, 2, ',', '.') }} Kz</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Funcionário Substituto --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Funcionário Substituto (Opcional)</label>
                        <select wire:model="replacement_employee_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('replacement_employee_id') border-red-500 @enderror">
                            <option value="">Nenhum</option>
                            @foreach($employees as $employee)
                                @if($employee->id != $employee_id)
                                    <option value="{{ $employee->id }}">
                                        {{ $employee->full_name }} ({{ $employee->employee_number }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('replacement_employee_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Observações --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                            <textarea wire:model="notes" 
                                      class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('notes') border-red-500 @enderror" 
                                      rows="3" 
                                      placeholder="Observações sobre as férias..."></textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Upload de Anexo --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Documento Anexo</label>
                            <input type="file" 
                                   wire:model="attachment" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('attachment') border-red-500 @enderror"
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <p class="text-xs text-gray-500 mt-1">PDF, JPG ou PNG (máx. 2MB)</p>
                            @error('attachment')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3 sticky bottom-0 bg-white">
                    <button type="button" wire:click="closeModal"
                            class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-semibold shadow-lg transition-all">
                        <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Criar Solicitação' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
