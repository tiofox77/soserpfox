<div class="p-6">
    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="mb-6 bg-green-50 border border-green-200 rounded-xl px-4 py-3 flex items-center justify-between shadow-sm">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 text-xl mr-3"></i>
                <span class="text-green-800 font-medium">{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="text-green-600 hover:text-green-800">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-xl p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-sitemap text-white text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-white mb-1">Departamentos & Cargos</h1>
                    <p class="text-purple-100">Gestão de estrutura organizacional</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="bg-white rounded-xl shadow-sm mb-6 p-1 flex space-x-1">
        <button wire:click="$set('activeTab', 'departments')"
                class="flex-1 px-6 py-3 rounded-lg font-semibold transition-all duration-200 {{ $activeTab === 'departments' ? 'bg-purple-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100' }}">
            <i class="fas fa-building mr-2"></i>Departamentos
            <span class="ml-2 px-2 py-0.5 rounded-full text-xs {{ $activeTab === 'departments' ? 'bg-purple-500' : 'bg-gray-200' }}">
                {{ $departments->total() }}
            </span>
        </button>
        <button wire:click="$set('activeTab', 'positions')"
                class="flex-1 px-6 py-3 rounded-lg font-semibold transition-all duration-200 {{ $activeTab === 'positions' ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100' }}">
            <i class="fas fa-briefcase mr-2"></i>Cargos
            <span class="ml-2 px-2 py-0.5 rounded-full text-xs {{ $activeTab === 'positions' ? 'bg-indigo-500' : 'bg-gray-200' }}">
                {{ $positions->total() }}
            </span>
        </button>
    </div>

    {{-- Departamentos Tab --}}
    @if($activeTab === 'departments')
        <div class="bg-white rounded-xl shadow-sm p-6">
            {{-- Header Actions --}}
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Lista de Departamentos</h2>
                    <p class="text-sm text-gray-600">{{ $departments->total() }} departamentos cadastrados</p>
                </div>
                <button wire:click="createDept" 
                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i>Novo Departamento
                </button>
            </div>

            {{-- Departments Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($departments as $dept)
                    <div class="bg-gradient-to-br from-white to-gray-50 rounded-xl border-2 {{ $dept->is_active ? 'border-green-200 hover:border-green-300' : 'border-gray-200 hover:border-gray-300' }} p-6 transition-all duration-200 hover:shadow-lg group">
                        {{-- Header --}}
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center mr-3 shadow-md">
                                        <i class="fas fa-building text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900">{{ $dept->name }}</h3>
                                </div>
                                @if($dept->code)
                                    <p class="text-xs text-gray-500 ml-13">
                                        <i class="fas fa-hashtag mr-1"></i>{{ $dept->code }}
                                    </p>
                                @endif
                            </div>
                            @if($dept->is_active)
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                    Ativo
                                </span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                                    Inativo
                                </span>
                            @endif
                        </div>

                        {{-- Description --}}
                        @if($dept->description)
                            <p class="text-sm text-gray-600 mb-4 line-clamp-2">{{ $dept->description }}</p>
                        @endif

                        {{-- Stats & Actions --}}
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-users text-purple-600 mr-2"></i>
                                <span class="font-semibold text-gray-900">{{ $dept->employees_count }}</span>
                                <span class="ml-1">funcionários</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button wire:click="editDept({{ $dept->id }})" 
                                        class="w-8 h-8 flex items-center justify-center bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors">
                                    <i class="fas fa-edit text-sm"></i>
                                </button>
                                <button wire:click="deleteDept({{ $dept->id }})" 
                                        onclick="return confirm('Tem certeza que deseja remover este departamento?')"
                                        class="w-8 h-8 flex items-center justify-center bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    {{-- Empty State --}}
                    <div class="col-span-full bg-gray-50 rounded-xl p-12 text-center">
                        <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-building text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Nenhum departamento cadastrado</h3>
                        <p class="text-gray-600 mb-6">Comece criando o primeiro departamento da sua organização</p>
                        <button wire:click="createDept" 
                                class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-md inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i>Criar Primeiro Departamento
                        </button>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if($departments->hasPages())
                <div class="mt-6">
                    {{ $departments->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Cargos Tab --}}
    @if($activeTab === 'positions')
        <div class="bg-white rounded-xl shadow-sm p-6">
            {{-- Header Actions --}}
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Lista de Cargos</h2>
                    <p class="text-sm text-gray-600">{{ $positions->total() }} cargos cadastrados</p>
                </div>
                <button wire:click="createPos" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i>Novo Cargo
                </button>
            </div>

            {{-- Positions Table --}}
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Cargo</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Departamento</th>
                            <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Funcionários</th>
                            <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($positions as $pos)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center mr-3 shadow-sm">
                                            <i class="fas fa-briefcase text-white text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $pos->title }}</p>
                                            @if($pos->description)
                                                <p class="text-xs text-gray-500">{{ $pos->description }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    @if($pos->code)
                                        <span class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">{{ $pos->code }}</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    @if($pos->department)
                                        <span class="inline-flex items-center">
                                            <i class="fas fa-building text-purple-600 mr-2"></i>
                                            {{ $pos->department->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">Sem departamento</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                                        {{ $pos->employees_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($pos->is_active)
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                            Ativo
                                        </span>
                                    @else
                                        <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                                            Inativo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button wire:click="editPos({{ $pos->id }})" 
                                                class="w-8 h-8 flex items-center justify-center bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                        <button wire:click="deletePos({{ $pos->id }})" 
                                                onclick="return confirm('Tem certeza que deseja remover este cargo?')"
                                                class="w-8 h-8 flex items-center justify-center bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-briefcase text-gray-400 text-3xl"></i>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900 mb-2">Nenhum cargo cadastrado</h3>
                                    <p class="text-gray-600 mb-6">Comece criando o primeiro cargo</p>
                                    <button wire:click="createPos" 
                                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-md inline-flex items-center">
                                        <i class="fas fa-plus mr-2"></i>Criar Primeiro Cargo
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($positions->hasPages())
                <div class="mt-6">
                    {{ $positions->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Modal Departamento --}}
    @if($showDeptModal)
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
             style="backdrop-filter: blur(4px);"
             x-data x-show="true"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             @click.self="$wire.set('showDeptModal', false)">
            
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
                 x-show="true"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 @click.stop>
                
                {{-- Header --}}
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                            <i class="fas fa-building text-white text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">
                                {{ $editDeptMode ? 'Editar Departamento' : 'Novo Departamento' }}
                            </h3>
                            <p class="text-purple-100 text-sm">Preencha os dados do departamento</p>
                        </div>
                    </div>
                    <button wire:click="$set('showDeptModal', false)" 
                            class="text-white hover:text-purple-100 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                {{-- Body --}}
                <form wire:submit.prevent="saveDept" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-building mr-1 text-purple-600"></i>Nome do Departamento *
                        </label>
                        <input type="text" wire:model="dept_name" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all @error('dept_name') border-red-500 @enderror"
                               placeholder="Ex: Recursos Humanos">
                        @error('dept_name') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1 text-indigo-600"></i>Código
                        </label>
                        <input type="text" wire:model="dept_code" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                               placeholder="Ex: RH">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-1 text-blue-600"></i>Descrição
                        </label>
                        <textarea wire:model="dept_description" rows="3"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                                  placeholder="Descreva as responsabilidades do departamento"></textarea>
                    </div>

                    <div class="flex items-center p-4 bg-gray-50 rounded-xl">
                        <input type="checkbox" wire:model="dept_is_active" id="deptActive"
                               class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <label for="deptActive" class="ml-3 text-sm font-semibold text-gray-700">
                            Departamento Ativo
                        </label>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button type="button" wire:click="$set('showDeptModal', false)"
                                class="px-6 py-2.5 bg-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-300 transition-all">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveDept"
                                class="px-6 py-2.5 bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700 transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="saveDept">
                                <i class="fas fa-save mr-2"></i>Salvar
                            </span>
                            <span wire:loading wire:target="saveDept">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal Cargo --}}
    @if($showPosModal)
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
             style="backdrop-filter: blur(4px);"
             x-data x-show="true"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             @click.self="$wire.set('showPosModal', false)">
            
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
                 x-show="true"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 @click.stop>
                
                {{-- Header --}}
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                            <i class="fas fa-briefcase text-white text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">
                                {{ $editPosMode ? 'Editar Cargo' : 'Novo Cargo' }}
                            </h3>
                            <p class="text-indigo-100 text-sm">Preencha os dados do cargo</p>
                        </div>
                    </div>
                    <button wire:click="$set('showPosModal', false)" 
                            class="text-white hover:text-indigo-100 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                {{-- Body --}}
                <form wire:submit.prevent="savePos" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-briefcase mr-1 text-indigo-600"></i>Título do Cargo *
                        </label>
                        <input type="text" wire:model="pos_title" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all @error('pos_title') border-red-500 @enderror"
                               placeholder="Ex: Gerente de Projetos">
                        @error('pos_title') 
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1 text-purple-600"></i>Código
                        </label>
                        <input type="text" wire:model="pos_code" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                               placeholder="Ex: GP">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-building mr-1 text-blue-600"></i>Departamento
                        </label>
                        <select wire:model="pos_department_id"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                            <option value="">Selecione um departamento</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-1 text-green-600"></i>Descrição
                        </label>
                        <textarea wire:model="pos_description" rows="3"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                                  placeholder="Descreva as responsabilidades do cargo"></textarea>
                    </div>

                    <div class="flex items-center p-4 bg-gray-50 rounded-xl">
                        <input type="checkbox" wire:model="pos_is_active" id="posActive"
                               class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <label for="posActive" class="ml-3 text-sm font-semibold text-gray-700">
                            Cargo Ativo
                        </label>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button type="button" wire:click="$set('showPosModal', false)"
                                class="px-6 py-2.5 bg-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-300 transition-all">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="savePos"
                                class="px-6 py-2.5 bg-indigo-600 text-white rounded-xl font-semibold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="savePos">
                                <i class="fas fa-save mr-2"></i>Salvar
                            </span>
                            <span wire:loading wire:target="savePos">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
