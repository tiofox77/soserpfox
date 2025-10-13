<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-sms mr-3 text-green-600"></i>
                    Configurações SMS
                </h2>
                <p class="text-gray-600 mt-1">Gerencie as configurações de envio de SMS via D7 Networks</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="openTestModal" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-paper-plane mr-2"></i>Enviar SMS Teste
                </button>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button wire:click="$set('activeTab', 'settings')" 
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'settings' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-cog mr-2"></i>Configurações
                </button>
                <button wire:click="$set('activeTab', 'templates')" 
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'templates' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-comment-alt mr-2"></i>Templates ({{ count($templates) }})
                </button>
                <button wire:click="$set('activeTab', 'logs')" 
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'logs' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-history mr-2"></i>Histórico
                </button>
            </nav>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Enviados</p>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total']) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-sms text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Sucesso</p>
                    <p class="text-2xl font-bold text-green-600">{{ number_format($stats['sent']) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Falhas</p>
                    <p class="text-2xl font-bold text-red-600">{{ number_format($stats['failed']) }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Hoje</p>
                    <p class="text-2xl font-bold text-purple-600">{{ number_format($stats['today']) }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-calendar-day text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    @if($activeTab === 'settings')
        {{-- Configuration Form --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-cog mr-2 text-green-600"></i>
            Configuração API D7 Networks
        </h3>

        <form wire:submit.prevent="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-server text-blue-500 mr-2"></i>Provider
                    </label>
                    <input wire:model="provider" type="text" readonly class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-100">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-signature text-purple-500 mr-2"></i>Sender ID *
                        <span class="text-xs text-gray-500 ml-1">(Máx. 11 caracteres)</span>
                    </label>
                    <input wire:model="sender_id" type="text" maxlength="11" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    @error('sender_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-link text-indigo-500 mr-2"></i>API URL *
                </label>
                <input wire:model="api_url" type="url" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                @error('api_url') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-key text-yellow-500 mr-2"></i>API Token *
                </label>
                <textarea wire:model="api_token" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 font-mono text-sm"></textarea>
                @error('api_token') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-bell text-orange-500 mr-2"></i>Report URL (Opcional)
                </label>
                <input wire:model="report_url" type="url" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                @error('report_url') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">URL para receber delivery reports da API</p>
            </div>

            <div class="mb-4 flex items-center">
                <input wire:model="is_active" type="checkbox" id="is_active" class="w-5 h-5 text-green-600 rounded mr-2">
                <label for="is_active" class="text-sm font-semibold text-gray-700">
                    <i class="fas fa-toggle-on text-green-500 mr-1"></i>SMS Ativo (Habilitar envio de SMS)
                </label>
            </div>

            <div class="flex gap-2">
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50">
                    <span wire:loading.remove>
                        <i class="fas fa-save mr-2"></i>Salvar Configurações
                    </span>
                    <span wire:loading>
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </form>
    </div>
    @endif

    @if($activeTab === 'templates')
        {{-- SMS Templates --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-comment-alt mr-2 text-purple-600"></i>
                Templates SMS
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($templates as $template)
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h4 class="font-semibold text-gray-800 flex items-center">
                                    {{ $template->name }}
                                    @if($template->is_active)
                                        <span class="ml-2 px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded-full">Ativo</span>
                                    @else
                                        <span class="ml-2 px-2 py-0.5 bg-gray-100 text-gray-800 text-xs rounded-full">Inativo</span>
                                    @endif
                                </h4>
                                <code class="text-xs text-gray-500">{{ $template->slug }}</code>
                            </div>
                        </div>

                        @if($template->description)
                            <p class="text-sm text-gray-600 mb-3">{{ $template->description }}</p>
                        @endif

                        <div class="bg-gray-50 p-3 rounded-lg mb-3">
                            <p class="text-xs text-gray-500 mb-1 font-semibold">Preview da Mensagem:</p>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap font-mono">{{ Str::limit($template->content, 150) }}</p>
                        </div>

                        @if($template->variables)
                            <div class="mb-3">
                                <p class="text-xs text-gray-500 font-semibold mb-1">Variáveis Disponíveis:</p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($template->variables as $var => $desc)
                                        @php
                                            $displayVar = '{{' . $var . '}}';
                                        @endphp
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded font-mono">{{ $displayVar }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <button wire:click="editTemplate({{ $template->id }})" 
                                class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                            <i class="fas fa-edit mr-2"></i>Editar Template
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($activeTab === 'logs')
        {{-- SMS History --}}
        <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-blue-600"></i>
            Histórico de SMS
        </h3>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data/Hora</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Destinatário</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Mensagem</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Request ID</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $log->sent_at?->format('d/m/Y H:i:s') ?? $log->created_at->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 font-mono">
                                {{ $log->recipient }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $typeColors = [
                                        'test' => 'bg-gray-100 text-gray-800',
                                        'new_account' => 'bg-green-100 text-green-800',
                                        'payment_approved' => 'bg-blue-100 text-blue-800',
                                        'plan_expiring' => 'bg-yellow-100 text-yellow-800',
                                    ];
                                    $color = $typeColors[$log->type] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $color }}">
                                    {{ $log->type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">
                                {{ Str::limit($log->message, 50) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($log->status === 'sent')
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                        <i class="fas fa-check-circle mr-1"></i>Enviado
                                    </span>
                                @else
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">
                                        <i class="fas fa-times-circle mr-1"></i>Falhou
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 font-mono text-xs">
                                {{ $log->request_id ? Str::limit($log->request_id, 20) : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2 text-gray-300"></i>
                                <p>Nenhum SMS enviado ainda</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
    @endif

    {{-- Template Edit Modal --}}
    @if($showTemplateModal)
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl m-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-edit text-purple-600 mr-2"></i>Editar Template SMS
                    </h3>
                    <button wire:click="$set('showTemplateModal', false)" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="saveTemplate">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tag text-purple-500 mr-2"></i>Nome do Template
                        </label>
                        <input wire:model="template_name" type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        @error('template_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-code text-blue-500 mr-2"></i>Slug (não editável)
                        </label>
                        <input wire:model="template_slug" type="text" readonly
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-100">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-info-circle text-indigo-500 mr-2"></i>Descrição
                        </label>
                        <input wire:model="template_description" type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        @error('template_description') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment-dots text-green-500 mr-2"></i>Conteúdo da Mensagem
                            <span class="text-xs text-gray-500 ml-1">Use {{variavel}} para variáveis dinâmicas</span>
                        </label>
                        <textarea wire:model="template_content" rows="8" 
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 font-mono text-sm"></textarea>
                        @error('template_content') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Use \n para quebra de linha
                        </p>
                    </div>

                    <div class="mb-4 flex items-center">
                        <input wire:model="template_is_active" type="checkbox" id="template_is_active" class="w-5 h-5 text-purple-600 rounded mr-2">
                        <label for="template_is_active" class="text-sm font-semibold text-gray-700">
                            <i class="fas fa-toggle-on text-purple-500 mr-1"></i>Template Ativo
                        </label>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" 
                                wire:loading.attr="disabled"
                                class="flex-1 px-4 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveTemplate">
                                <i class="fas fa-save mr-2"></i>Salvar Template
                            </span>
                            <span wire:loading wire:target="saveTemplate">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                            </span>
                        </button>
                        <button type="button" wire:click="$set('showTemplateModal', false)" 
                                class="px-4 py-2.5 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Test SMS Modal --}}
    @if($showTestModal)
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-paper-plane text-blue-600 mr-2"></i>Enviar SMS de Teste
                    </h3>
                    <button wire:click="$set('showTestModal', false)" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="sendTestSms">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-mobile-alt text-green-500 mr-2"></i>Telefone (com código do país)
                        </label>
                        <input wire:model="test_phone" type="text" placeholder="+244939729902" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('test_phone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment-alt text-blue-500 mr-2"></i>Mensagem
                        </label>
                        <textarea wire:model="test_message" rows="4" 
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        @error('test_message') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" 
                                wire:loading.attr="disabled"
                                class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
                            <span wire:loading.remove wire:target="sendTestSms">
                                <i class="fas fa-paper-plane mr-2"></i>Enviar SMS
                            </span>
                            <span wire:loading wire:target="sendTestSms">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                            </span>
                        </button>
                        <button type="button" wire:click="$set('showTestModal', false)" 
                                class="px-4 py-2.5 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
