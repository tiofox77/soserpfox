<div class="p-6" x-data="{ showModal: false, selectedMessage: null }">

    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-cyan-600 to-blue-700 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-3">
                    <i class="fas fa-comments"></i>
                    Mensagens de Contacto
                </h1>
                <p class="text-cyan-100 mt-1 text-sm">Mensagens recebidas pelo formulário de contacto do site</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold">{{ $messages->total() }}</div>
                    <div class="text-xs text-cyan-200">Total</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-red-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-envelope text-red-500"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800">{{ \App\Models\ContactMessage::where('status', 'new')->count() }}</div>
                    <div class="text-xs text-gray-500">Novas</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-amber-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-eye text-amber-500"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800">{{ \App\Models\ContactMessage::where('status', 'read')->count() }}</div>
                    <div class="text-xs text-gray-500">Lidas</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-green-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-reply text-green-500"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-800">{{ \App\Models\ContactMessage::where('status', 'replied')->count() }}</div>
                    <div class="text-xs text-gray-500">Respondidas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 text-sm"
                        placeholder="Buscar por nome, email ou empresa...">
                </div>
            </div>
            <div class="w-full md:w-48">
                <select wire:model.live="statusFilter"
                    class="w-full py-2.5 px-4 border border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 text-sm">
                    <option value="">Todos os Status</option>
                    <option value="new">Novas</option>
                    <option value="read">Lidas</option>
                    <option value="replied">Respondidas</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">
                <i class="fas fa-inbox mr-2 text-cyan-500"></i>Mensagens ({{ $messages->total() }})
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Telefone</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Empresa</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">Acções</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($messages as $message)
                        <tr class="hover:bg-gray-50 transition {{ $message->status === 'new' ? 'bg-red-50/30' : '' }}">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-800">{{ $message->name }}</span>
                                    @if($message->status === 'new')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Nova</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <a href="mailto:{{ $message->email }}" class="text-cyan-600 hover:text-cyan-800 hover:underline">{{ $message->email }}</a>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                @if($message->phone)
                                    <a href="tel:{{ $message->phone }}" class="hover:text-cyan-600">{{ $message->phone }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $message->company ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if($message->status === 'new')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                        <i class="fas fa-circle text-[6px]"></i> Nova
                                    </span>
                                @elseif($message->status === 'read')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                        <i class="fas fa-circle text-[6px]"></i> Lida
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        <i class="fas fa-circle text-[6px]"></i> Respondida
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs">{{ $message->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="selectedMessage = {{ $message->toJson() }}; showModal = true"
                                        class="p-2 text-cyan-600 hover:bg-cyan-50 rounded-lg transition" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    @if($message->status === 'new')
                                        <button wire:click="markAsRead({{ $message->id }})"
                                            class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition" title="Marcar como lida">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    @endif

                                    @if($message->status !== 'replied')
                                        <button wire:click="markAsReplied({{ $message->id }})"
                                            class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Marcar como respondida">
                                            <i class="fas fa-reply"></i>
                                        </button>
                                    @endif

                                    <button wire:click="delete({{ $message->id }})" wire:confirm="Tem certeza que deseja excluir esta mensagem?"
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                    <i class="fas fa-inbox text-4xl"></i>
                                    <p class="text-sm">Nenhuma mensagem encontrada</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($messages->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $messages->links() }}
            </div>
        @endif
    </div>

    <!-- Modal de Detalhes (Alpine.js) -->
    <template x-if="showModal && selectedMessage">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showModal = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-0 m-4 max-h-[90vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">

                <!-- Header -->
                <div class="bg-gradient-to-r from-cyan-600 to-blue-700 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fas fa-envelope-open-text"></i>
                        Mensagem de <span x-text="selectedMessage.name"></span>
                    </h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase">Nome</label>
                            <p class="text-gray-800 font-medium" x-text="selectedMessage.name"></p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase">Email</label>
                            <p>
                                <a :href="'mailto:' + selectedMessage.email" class="text-cyan-600 hover:underline" x-text="selectedMessage.email"></a>
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase">Telefone</label>
                            <p class="text-gray-800" x-text="selectedMessage.phone || 'Não informado'"></p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase">Empresa</label>
                            <p class="text-gray-800" x-text="selectedMessage.company || 'Não informado'"></p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase">Data</label>
                            <p class="text-gray-800" x-text="new Date(selectedMessage.created_at).toLocaleString('pt-AO')"></p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase">Endereço IP</label>
                            <p class="text-gray-800" x-text="selectedMessage.ip_address || 'Não registado'"></p>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase">Mensagem</label>
                        <div class="mt-1 bg-gray-50 border border-gray-200 rounded-xl p-4 text-gray-700 whitespace-pre-wrap text-sm" x-text="selectedMessage.message"></div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                    <a :href="'mailto:' + selectedMessage.email"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-xl hover:bg-green-700 transition text-sm font-medium">
                        <i class="fas fa-reply"></i> Responder por Email
                    </a>
                    <button @click="showModal = false"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition text-sm font-medium">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
