<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">📨 Convites de Usuários</h2>
            <p class="text-sm text-gray-600 mt-1">Convide novos membros para sua equipe</p>
        </div>
        <button wire:click="openModal" 
                class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition flex items-center gap-2">
            <i class="fas fa-user-plus"></i>
            Convidar Usuário
        </button>
    </div>

    {{-- Messages --}}
    @if (session()->has('success'))
        <div class="mb-4 bg-green-50 border-2 border-green-500 rounded-xl p-4 text-green-800 flex items-center">
            <i class="fas fa-check-circle text-2xl mr-3"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 bg-red-50 border-2 border-red-500 rounded-xl p-4 text-red-800 flex items-center">
            <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Lista de Convites --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Convidado</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Convidado por</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Expira em</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($invitations as $invitation)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                        {{ strtoupper(substr($invitation->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $invitation->name }}</div>
                                        @if($invitation->role)
                                            <div class="text-xs text-gray-500">{{ $invitation->role }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ $invitation->email }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ $invitation->invitedBy->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($invitation->status === 'pending' && !$invitation->isExpired())
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-clock"></i> Pendente
                                    </span>
                                @elseif($invitation->status === 'accepted')
                                    <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-check-circle"></i> Aceito
                                    </span>
                                @elseif($invitation->status === 'expired' || $invitation->isExpired())
                                    <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-times-circle"></i> Expirado
                                    </span>
                                @elseif($invitation->status === 'cancelled')
                                    <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-ban"></i> Cancelado
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                @if($invitation->status === 'pending')
                                    {{ $invitation->expires_at->diffForHumans() }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    @if($invitation->status === 'pending' && !$invitation->isExpired())
                                        <button wire:click="resendInvitation({{ $invitation->id }})"
                                                class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs rounded-lg transition"
                                                title="Reenviar convite">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                        <button wire:click="cancelInvitation({{ $invitation->id }})"
                                                onclick="return confirm('Deseja cancelar este convite?')"
                                                class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white text-xs rounded-lg transition"
                                                title="Cancelar convite">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @elseif($invitation->isExpired())
                                        <button wire:click="resendInvitation({{ $invitation->id }})"
                                                class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white text-xs rounded-lg transition"
                                                title="Reenviar convite">
                                            <i class="fas fa-redo"></i> Reenviar
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <i class="fas fa-inbox text-6xl mb-4"></i>
                                    <p class="text-lg font-semibold">Nenhum convite enviado ainda</p>
                                    <p class="text-sm mt-1">Clique em "Convidar Usuário" para começar</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal de Convite --}}
    @if($showModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeModal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4" wire:click.stop>
                {{-- Header --}}
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-t-2xl flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold">📧 Convidar Novo Usuário</h3>
                        <p class="text-sm text-blue-100 mt-1">Envie um convite via email</p>
                    </div>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-6">
                    <form wire:submit.prevent="sendInvitation">
                        {{-- Nome --}}
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Nome Completo *
                            </label>
                            <input type="text" 
                                   wire:model="name"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Ex: João Silva">
                            @error('name') 
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Email *
                            </label>
                            <input type="email" 
                                   wire:model="email"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="joao@empresa.com">
                            @error('email') 
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                            @enderror
                        </div>

                        {{-- Role (opcional) --}}
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Função (opcional)
                            </label>
                            <input type="text" 
                                   wire:model="role"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Ex: Gerente, Vendedor, etc.">
                            @error('role') 
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                            @enderror
                        </div>

                        {{-- Info Box --}}
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <div class="text-sm text-blue-800">
                                    <p class="font-semibold mb-1">O que acontece depois?</p>
                                    <ul class="list-disc list-inside space-y-1 text-xs">
                                        <li>Um email será enviado com link de convite</li>
                                        <li>O convite expira em 7 dias</li>
                                        <li>O usuário poderá criar sua conta pelo link</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- Buttons --}}
                        <div class="flex justify-end gap-3">
                            <button type="button" 
                                    wire:click="closeModal"
                                    class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition flex items-center gap-2"
                                    {{ $sending ? 'disabled' : '' }}>
                                @if($sending)
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Enviando...
                                @else
                                    <i class="fas fa-paper-plane"></i>
                                    Enviar Convite
                                @endif
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
