<div class="p-6">
    <!-- Header -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">📧 Email Templates</h1>
            <p class="text-gray-600 mt-1">Gerencie os templates de email do sistema</p>
        </div>
        <button wire:click="create" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center">
            <i class="fas fa-plus mr-2"></i> Novo Template
        </button>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
            <p class="font-bold">Sucesso!</p>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
            <p class="font-bold">Erro!</p>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <!-- Search -->
    <div class="mb-6">
        <input type="text" wire:model.live="search" placeholder="🔍 Pesquisar templates..." 
               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
    </div>

    <!-- Templates Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider">Template</th>
                    <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider">Slug</th>
                    <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider">Assunto</th>
                    <th class="px-6 py-4 text-center text-xs font-medium uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-center text-xs font-medium uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($templates as $template)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4">
                            <div>
                                <p class="font-bold text-gray-900">{{ $template->name }}</p>
                                @if($template->description)
                                    <p class="text-xs text-gray-500 mt-1">{{ $template->description }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-mono">
                                {{ $template->slug }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm text-gray-700">{{ Str::limit($template->subject, 50) }}</p>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button wire:click="toggleActive({{ $template->id }})" 
                                    class="px-3 py-1 rounded-full text-xs font-semibold transition {{ $template->is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200' }}">
                                {{ $template->is_active ? '✓ Ativo' : '✗ Inativo' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex justify-center space-x-2">
                                <button wire:click="openTestModal({{ $template->id }})" 
                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs transition"
                                        title="Enviar Teste">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <button wire:click="preview({{ $template->id }})" 
                                        class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-xs transition"
                                        title="Preview">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button wire:click="edit({{ $template->id }})" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs transition"
                                        title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="delete({{ $template->id }})" 
                                        onclick="return confirm('Tem certeza que deseja excluir este template?')"
                                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs transition"
                                        title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                            <p class="text-lg">Nenhum template encontrado</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $templates->links() }}
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto" wire:click.stop>
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 flex justify-between items-center sticky top-0 z-10">
                    <h2 class="text-2xl font-bold">
                        {{ $editMode ? '✏️ Editar Template' : '➕ Novo Template' }}
                    </h2>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form wire:submit.prevent="save" class="p-6 space-y-6">
                    <!-- Row 1 -->
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Nome do Template *</label>
                            <input type="text" wire:model="name" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Slug *</label>
                            <input type="text" wire:model="slug" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono @error('slug') border-red-500 @enderror"
                                   placeholder="welcome">
                            @error('slug') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Assunto do Email *</label>
                        <input type="text" wire:model="subject" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('subject') border-red-500 @enderror"
                               placeholder="Bem-vindo ao {app_name}!">
                        @error('subject') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
                        <textarea wire:model="description" rows="2"
                                  class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                  placeholder="Descrição do quando este template é usado..."></textarea>
                    </div>

                    <!-- Body HTML -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Conteúdo HTML *</label>
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-3 mb-2 text-xs">
                            <p><strong>Variáveis disponíveis:</strong> {user_name}, {tenant_name}, {app_name}, {plan_name}, {old_plan_name}, {new_plan_name}, {reason}, {support_email}, {login_url}</p>
                        </div>
                        <textarea wire:model="body_html" rows="15"
                                  class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm @error('body_html') border-red-500 @enderror"></textarea>
                        @error('body_html') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <!-- Body Text -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Conteúdo Texto (opcional)</label>
                        <textarea wire:model="body_text" rows="4"
                                  class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Versão em texto puro para clientes de email que não suportam HTML</p>
                    </div>

                    <!-- Active Status -->
                    <div class="flex items-center">
                        <input type="checkbox" wire:model="is_active" id="is_active" class="w-5 h-5 text-blue-600 rounded">
                        <label for="is_active" class="ml-2 text-gray-700 font-semibold">Template Ativo</label>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" wire:click="closeModal" 
                                class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                            {{ $editMode ? 'Atualizar' : 'Criar' }} Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Preview Modal -->
    @if($showPreviewModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closePreviewModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto" wire:click.stop>
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-6 flex justify-between items-center sticky top-0 z-10">
                    <h2 class="text-2xl font-bold">👁️ Preview do Email</h2>
                    <button wire:click="closePreviewModal" class="text-white hover:text-gray-200 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6">
                    <!-- Subject Preview -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg border-2 border-gray-200">
                        <p class="text-xs text-gray-600 mb-1">Assunto:</p>
                        <p class="text-lg font-bold text-gray-900">{{ $previewData['subject'] ?? '' }}</p>
                    </div>

                    <!-- HTML Preview -->
                    <div class="border-2 border-gray-200 rounded-lg overflow-hidden">
                        <iframe srcdoc="{{ htmlspecialchars($previewData['body_html'] ?? '') }}" 
                                class="w-full" 
                                style="min-height: 500px; border: none;">
                        </iframe>
                    </div>

                    <!-- Text Preview (if exists) -->
                    @if(!empty($previewData['body_text']))
                        <div class="mt-6">
                            <p class="text-sm font-semibold text-gray-700 mb-2">Versão Texto:</p>
                            <div class="p-4 bg-gray-50 rounded-lg border-2 border-gray-200 font-mono text-sm">
                                {{ $previewData['body_text'] }}
                            </div>
                        </div>
                    @endif
                </div>

                <div class="p-6 border-t">
                    <button wire:click="closePreviewModal" 
                            class="w-full px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold transition">
                        Fechar Preview
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Test Email Modal -->
    @if($showTestModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeTestModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" wire:click.stop>
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 text-white p-6 flex justify-between items-center">
                    <h2 class="text-2xl font-bold">📧 Enviar Email de Teste</h2>
                    <button wire:click="closeTestModal" class="text-white hover:text-gray-200 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6">
                    <div class="mb-6">
                        <p class="text-gray-600 mb-4">Digite o email para onde deseja enviar o teste:</p>
                        
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                        <input type="email" wire:model="testEmail" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="seu-email@exemplo.com">
                        @error('testEmail') 
                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                        @enderror
                    </div>

                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 text-sm">
                        <p class="font-semibold text-blue-900">ℹ️ Informação:</p>
                        <p class="text-blue-800 mt-1">O email será enviado com dados de exemplo para você visualizar como ficará.</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" wire:click="closeTestModal" 
                                class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition">
                            Cancelar
                        </button>
                        <button wire:click="sendTestEmail" 
                                class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition flex items-center"
                                {{ $sending ? 'disabled' : '' }}>
                            @if($sending)
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                Enviando...
                            @else
                                <i class="fas fa-paper-plane mr-2"></i>
                                Enviar Teste
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
