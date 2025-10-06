<div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">📅 Eventos</h1>
            <p class="text-gray-600 mt-1">Gestão de eventos e montagens</p>
        </div>
        <button wire:click="create" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>Novo Evento
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input wire:model.live="search" type="text" placeholder="Buscar por nome ou número..." 
                   class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
            
            <select wire:model.live="statusFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                <option value="all">Todos os Status</option>
                <option value="orcamento">Orçamento</option>
                <option value="confirmado">Confirmado</option>
                <option value="em_montagem">Em Montagem</option>
                <option value="em_andamento">Em Andamento</option>
                <option value="concluido">Concluído</option>
                <option value="cancelado">Cancelado</option>
            </select>

            <select wire:model.live="dateFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                <option value="all">Todas as Datas</option>
                <option value="today">Hoje</option>
                <option value="this_week">Esta Semana</option>
                <option value="this_month">Este Mês</option>
                <option value="upcoming">Próximos</option>
            </select>
        </div>
    </div>

    <!-- Events Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Evento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($events as $event)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4">
                        <div>
                            <p class="text-sm font-bold text-gray-900">{{ $event->name }}</p>
                            <p class="text-xs text-gray-500">{{ $event->event_number }}</p>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $event->client->name ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $event->start_date->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-{{ $event->status_color }}-100 text-{{ $event->status_color }}-800">
                            {{ $event->status_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right text-sm">
                        <button wire:click="edit({{ $event->id }})" class="text-blue-600 hover:text-blue-800 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button wire:click="delete({{ $event->id }})" onclick="return confirm('Excluir evento?')" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="px-6 py-4 border-t">
            {{ $events->links() }}
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <h3 class="text-2xl font-bold mb-4">{{ $editMode ? 'Editar Evento' : 'Novo Evento' }}</h3>
                
                <form wire:submit.prevent="save">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nome do Evento *</label>
                            <input wire:model="name" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-2" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cliente</label>
                            <select wire:model="client_id" class="w-full border border-gray-300 rounded-lg px-4 py-2">
                                <option value="">Selecione...</option>
                                @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Local</label>
                            <select wire:model="venue_id" class="w-full border border-gray-300 rounded-lg px-4 py-2">
                                <option value="">Selecione...</option>
                                @foreach($venues as $venue)
                                <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo *</label>
                            <select wire:model="type" class="w-full border border-gray-300 rounded-lg px-4 py-2" required>
                                <option value="corporativo">Corporativo</option>
                                <option value="casamento">Casamento</option>
                                <option value="conferencia">Conferência</option>
                                <option value="show">Show</option>
                                <option value="streaming">Streaming</option>
                                <option value="outros">Outros</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Participantes Esperados</label>
                            <input wire:model="expected_attendees" type="number" class="w-full border border-gray-300 rounded-lg px-4 py-2">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Data/Hora Início *</label>
                            <input wire:model="start_date" type="datetime-local" class="w-full border border-gray-300 rounded-lg px-4 py-2" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Data/Hora Fim *</label>
                            <input wire:model="end_date" type="datetime-local" class="w-full border border-gray-300 rounded-lg px-4 py-2" required>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                            <textarea wire:model="description" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2"></textarea>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notas</label>
                            <textarea wire:model="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-4 py-2"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
