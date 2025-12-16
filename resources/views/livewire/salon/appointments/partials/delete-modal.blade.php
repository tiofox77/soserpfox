@if($showDeleteModal && $deletingAppointment)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelDelete"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-6 pt-6 pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-16 w-16 rounded-full bg-red-100 sm:mx-0 sm:h-14 sm:w-14 animate-pulse">
                        <i class="fas fa-calendar-times text-red-600 text-2xl"></i>
                    </div>
                    
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Cancelar Agendamento</h3>
                        <div class="mt-3">
                            <p class="text-sm text-gray-600 mb-3">Tem certeza que deseja cancelar este agendamento?</p>
                            
                            <div class="p-4 bg-red-50 rounded-xl border-2 border-red-200">
                                <div class="flex items-center gap-4 mb-3">
                                    <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-purple-600 text-white rounded-xl flex items-center justify-center font-bold">
                                        {{ $deletingAppointment->date->format('d') }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900">{{ $deletingAppointment->date->format('d/m/Y') }} às {{ \Carbon\Carbon::parse($deletingAppointment->start_time)->format('H:i') }}</p>
                                        <p class="text-sm text-gray-600">{{ $deletingAppointment->client->name ?? 'Cliente' }}</p>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-user-tie text-purple-500 mr-1"></i>
                                    {{ $deletingAppointment->professional->display_name }}
                                </p>
                            </div>
                            
                            <p class="text-sm text-red-600 font-semibold mt-3 flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                O cliente será notificado do cancelamento.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-6 py-4 sm:flex sm:flex-row-reverse gap-3">
                <button wire:click="confirmCancel" class="w-full inline-flex justify-center items-center rounded-xl border border-transparent shadow-lg px-6 py-3 bg-red-600 text-base font-bold text-white hover:bg-red-700 sm:ml-3 sm:w-auto transition-all">
                    <i class="fas fa-times mr-2"></i>Sim, Cancelar
                </button>
                <button wire:click="cancelDelete" class="mt-3 w-full inline-flex justify-center items-center rounded-xl border-2 border-gray-300 shadow-sm px-6 py-3 bg-white text-base font-semibold text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto transition-all">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
