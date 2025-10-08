<div class="p-6">
    <!-- Header -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">📊 Logs de Email</h1>
            <p class="text-gray-600 mt-1">Histórico e rastreamento de emails enviados</p>
        </div>
        <button wire:click="clearOldLogs" onclick="return confirm('Excluir logs mais antigos que 90 dias?')" 
                class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center">
            <i class="fas fa-trash mr-2"></i> Limpar Antigos
        </button>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
            <p class="font-bold">Sucesso!</p>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold">Total</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-envelope text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold">Enviados</p>
                    <p class="text-3xl font-bold text-green-600">{{ $stats['sent'] }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold">Falhados</p>
                    <p class="text-3xl font-bold text-red-600">{{ $stats['failed'] }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold">Pendentes</p>
                    <p class="text-3xl font-bold text-yellow-600">{{ $stats['pending'] }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Pesquisar</label>
                <input type="text" wire:model.live="search" placeholder="Email, assunto..." 
                       class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    <option value="sent">Enviado</option>
                    <option value="failed">Falhado</option>
                    <option value="pending">Pendente</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Template</label>
                <select wire:model.live="templateFilter" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    @foreach($templates as $template)
                        <option value="{{ $template }}">{{ $template }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Data Início</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Data Fim</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider">Data/Hora</th>
                    <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider">Destinatário</th>
                    <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider">Assunto</th>
                    <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider">Template</th>
                    <th class="px-6 py-4 text-center text-xs font-medium uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-center text-xs font-medium uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($logs as $log)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $log->created_at->format('d/m/Y') }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $log->created_at->format('H:i:s') }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $log->to_email }}</div>
                            @if($log->to_name)
                                <div class="text-xs text-gray-500">{{ $log->to_name }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ Str::limit($log->subject, 40) }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-mono">
                                {{ $log->template_slug ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($log->status === 'sent')
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                    ✓ Enviado
                                </span>
                            @elseif($log->status === 'failed')
                                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">
                                    ✗ Falhou
                                </span>
                            @else
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">
                                    ⏳ Pendente
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex justify-center space-x-2">
                                <button wire:click="viewDetails({{ $log->id }})" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs transition"
                                        title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button wire:click="delete({{ $log->id }})" 
                                        onclick="return confirm('Excluir este log?')"
                                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs transition"
                                        title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                            <p class="text-lg">Nenhum log encontrado</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $logs->links() }}
    </div>

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedLog)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeDetailModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto" wire:click.stop>
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 flex justify-between items-center sticky top-0 z-10">
                    <h2 class="text-2xl font-bold">📄 Detalhes do Log #{{ $selectedLog->id }}</h2>
                    <button wire:click="closeDetailModal" class="text-white hover:text-gray-200 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Status -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm font-semibold text-gray-700 mb-2">Status</p>
                        @if($selectedLog->status === 'sent')
                            <span class="px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                                ✓ Enviado em {{ $selectedLog->sent_at?->format('d/m/Y H:i:s') }}
                            </span>
                        @elseif($selectedLog->status === 'failed')
                            <span class="px-4 py-2 bg-red-100 text-red-800 rounded-full text-sm font-semibold">
                                ✗ Falhou em {{ $selectedLog->failed_at?->format('d/m/Y H:i:s') }}
                            </span>
                            @if($selectedLog->error_message)
                                <div class="mt-3 p-3 bg-red-50 border-l-4 border-red-500 text-sm text-red-800">
                                    <strong>Erro:</strong> {{ $selectedLog->error_message }}
                                </div>
                            @endif
                        @endif
                    </div>

                    <!-- Email Info -->
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2">Destinatário</p>
                            <p class="text-gray-900">{{ $selectedLog->to_email }}</p>
                            @if($selectedLog->to_name)
                                <p class="text-sm text-gray-500">{{ $selectedLog->to_name }}</p>
                            @endif
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2">Remetente</p>
                            <p class="text-gray-900">{{ $selectedLog->from_email }}</p>
                            @if($selectedLog->from_name)
                                <p class="text-sm text-gray-500">{{ $selectedLog->from_name }}</p>
                            @endif
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2">Template</p>
                            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-mono">
                                {{ $selectedLog->template_slug ?? '-' }}
                            </span>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2">Criado em</p>
                            <p class="text-gray-900">{{ $selectedLog->created_at->format('d/m/Y H:i:s') }}</p>
                        </div>
                    </div>

                    <!-- Subject -->
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-2">Assunto</p>
                        <p class="text-gray-900 p-3 bg-gray-50 rounded-lg">{{ $selectedLog->subject }}</p>
                    </div>

                    <!-- Body Preview -->
                    @if($selectedLog->body_preview)
                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2">Preview do Conteúdo</p>
                            <p class="text-gray-700 p-3 bg-gray-50 rounded-lg text-sm">{{ $selectedLog->body_preview }}</p>
                        </div>
                    @endif

                    <!-- Template Data -->
                    @if($selectedLog->template_data)
                        <div>
                            <p class="text-sm font-semibold text-gray-700 mb-2">Dados do Template</p>
                            <pre class="text-xs p-3 bg-gray-900 text-green-400 rounded-lg overflow-x-auto font-mono">{{ json_encode($selectedLog->template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif

                    <!-- Relations -->
                    <div class="grid grid-cols-3 gap-4">
                        @if($selectedLog->tenant)
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <p class="text-xs font-semibold text-blue-700 mb-1">Tenant</p>
                                <p class="text-sm text-blue-900">{{ $selectedLog->tenant->name }}</p>
                            </div>
                        @endif

                        @if($selectedLog->user)
                            <div class="p-3 bg-green-50 rounded-lg">
                                <p class="text-xs font-semibold text-green-700 mb-1">Enviado por</p>
                                <p class="text-sm text-green-900">{{ $selectedLog->user->name }}</p>
                            </div>
                        @endif

                        @if($selectedLog->smtpSetting)
                            <div class="p-3 bg-purple-50 rounded-lg">
                                <p class="text-xs font-semibold text-purple-700 mb-1">SMTP</p>
                                <p class="text-sm text-purple-900">{{ $selectedLog->smtpSetting->host }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="p-6 border-t flex justify-end">
                    <button wire:click="closeDetailModal" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
