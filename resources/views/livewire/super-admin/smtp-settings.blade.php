<div class="p-6">
    <!-- Header -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">🔧 Configurações SMTP</h1>
            <p class="text-gray-600 mt-1">Gerencie as configurações de envio de email</p>
        </div>
        <button wire:click="create" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center">
            <i class="fas fa-plus mr-2"></i> Nova Configuração
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

    <!-- Settings Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @forelse($settings as $setting)
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border-2 {{ $setting->is_default ? 'border-green-500' : 'border-gray-200' }}">
                <!-- Card Header -->
                <div class="p-6 {{ $setting->is_default ? 'bg-gradient-to-r from-green-500 to-emerald-600' : 'bg-gradient-to-r from-gray-500 to-gray-600' }} text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-xl font-bold flex items-center">
                                <i class="fas fa-server mr-2"></i>
                                {{ $setting->host }}
                            </h3>
                            <p class="text-sm opacity-90 mt-1">
                                {{ $setting->tenant ? $setting->tenant->name : 'Configuração Global' }}
                            </p>
                        </div>
                        @if($setting->is_default)
                            <span class="px-3 py-1 bg-white text-green-600 rounded-full text-xs font-bold">
                                ★ PADRÃO
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Card Body -->
                <div class="p-6 space-y-3">
                    <!-- SMTP Details -->
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 font-semibold">Porta:</p>
                            <p class="text-gray-900">{{ $setting->port }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 font-semibold">Encriptação:</p>
                            <p class="text-gray-900 uppercase">{{ $setting->encryption }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-gray-500 font-semibold">Username:</p>
                            <p class="text-gray-900 font-mono text-xs">{{ $setting->username }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-gray-500 font-semibold">From Email:</p>
                            <p class="text-gray-900 text-xs">{{ $setting->from_email }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-gray-500 font-semibold">From Name:</p>
                            <p class="text-gray-900">{{ $setting->from_name }}</p>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="pt-3 border-t">
                        <button wire:click="toggleActive({{ $setting->id }})" 
                                class="px-3 py-1 rounded-full text-xs font-semibold transition {{ $setting->is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200' }}">
                            {{ $setting->is_active ? '✓ Ativo' : '✗ Inativo' }}
                        </button>

                        @if($setting->last_tested_at)
                            <span class="ml-2 text-xs text-gray-500">
                                <i class="fas fa-clock"></i> Testado: {{ $setting->last_tested_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>

                    <!-- Actions -->
                    <div class="pt-3 border-t flex flex-wrap gap-2">
                        <button wire:click="testConnection({{ $setting->id }})" 
                                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
                                {{ $testing ? 'disabled' : '' }}>
                            <i class="fas {{ $testing ? 'fa-spinner fa-spin' : 'fa-plug' }}"></i>
                            {{ $testing ? 'Testando...' : 'Testar Conexão' }}
                        </button>

                        <button wire:click="openSendTestModal({{ $setting->id }})" 
                                class="flex-1 bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Teste
                        </button>

                        @if(!$setting->is_default)
                            <button wire:click="setAsDefault({{ $setting->id }})" 
                                    class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                                <i class="fas fa-star"></i> Definir Padrão
                            </button>
                        @endif

                        <button wire:click="edit({{ $setting->id }})" 
                                class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                            <i class="fas fa-edit"></i>
                        </button>

                        <button wire:click="delete({{ $setting->id }})" 
                                onclick="return confirm('Tem certeza que deseja excluir esta configuração?')"
                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-2 bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-server text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-500 mb-2">Nenhuma configuração SMTP encontrada</p>
                <p class="text-gray-400 mb-6">Crie sua primeira configuração para enviar emails</p>
                <button wire:click="create" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-semibold transition">
                    <i class="fas fa-plus mr-2"></i> Criar Configuração
                </button>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $settings->links() }}
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto" wire:click.stop>
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 text-white p-6 flex justify-between items-center sticky top-0 z-10">
                    <h2 class="text-2xl font-bold">
                        {{ $editMode ? '✏️ Editar Configuração SMTP' : '➕ Nova Configuração SMTP' }}
                    </h2>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form wire:submit.prevent="save" class="p-6 space-y-6">
                    <!-- Tenant -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tenant (opcional)</label>
                        <select wire:model="tenant_id" 
                                class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                            <option value="">Configuração Global (Todos Tenants)</option>
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Deixe vazio para configuração global</p>
                    </div>

                    <!-- Row 1: Host & Port -->
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Host SMTP *</label>
                            <input type="text" wire:model="host" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 @error('host') border-red-500 @enderror"
                                   placeholder="smtp.gmail.com">
                            @error('host') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Porta *</label>
                            <input type="number" wire:model="port" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 @error('port') border-red-500 @enderror">
                            @error('port') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Row 2: Username & Encryption -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Username *</label>
                            <input type="text" wire:model="username" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 @error('username') border-red-500 @enderror"
                                   placeholder="seu-email@gmail.com">
                            @error('username') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Encriptação *</label>
                            <select wire:model="encryption" 
                                    class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 @error('encryption') border-red-500 @enderror">
                                <option value="tls">TLS (Recomendado)</option>
                                <option value="ssl">SSL</option>
                                <option value="none">Nenhuma</option>
                            </select>
                            @error('encryption') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Senha * {{ $editMode ? '(deixe vazio para não alterar)' : '' }}
                        </label>
                        <input type="password" wire:model="password" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 @error('password') border-red-500 @enderror"
                               placeholder="••••••••">
                        @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500 mt-1">⚠️ A senha será criptografada no banco de dados</p>
                    </div>

                    <!-- Row 3: From Email & Name -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">From Email *</label>
                            <input type="email" wire:model="from_email" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 @error('from_email') border-red-500 @enderror"
                                   placeholder="noreply@empresa.com">
                            @error('from_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">From Name *</label>
                            <input type="text" wire:model="from_name" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 @error('from_name') border-red-500 @enderror"
                                   placeholder="SOS ERP">
                            @error('from_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Checkboxes -->
                    <div class="space-y-3 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <input type="checkbox" wire:model="is_default" id="is_default" class="w-5 h-5 text-green-600 rounded">
                            <label for="is_default" class="ml-2 text-gray-700 font-semibold">
                                ⭐ Definir como configuração padrão
                            </label>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" wire:model="is_active" id="is_active" class="w-5 h-5 text-green-600 rounded">
                            <label for="is_active" class="ml-2 text-gray-700 font-semibold">
                                ✓ Configuração ativa
                            </label>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 text-sm">
                        <p class="font-semibold text-blue-900">💡 Exemplos de configuração:</p>
                        <ul class="mt-2 space-y-1 text-blue-800">
                            <li><strong>Gmail:</strong> smtp.gmail.com:587 (TLS)</li>
                            <li><strong>Outlook:</strong> smtp-mail.outlook.com:587 (TLS)</li>
                            <li><strong>SendGrid:</strong> smtp.sendgrid.net:587 (TLS)</li>
                            <li><strong>Mailgun:</strong> smtp.mailgun.org:587 (TLS)</li>
                        </ul>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" wire:click="closeModal" 
                                class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition">
                            {{ $editMode ? 'Atualizar' : 'Criar' }} Configuração
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Send Test Email Modal -->
    @if($showSendTestModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeSendTestModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" wire:click.stop>
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-6 flex justify-between items-center">
                    <h2 class="text-2xl font-bold">📧 Enviar Email de Teste</h2>
                    <button wire:click="closeSendTestModal" class="text-white hover:text-gray-200 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6">
                    <div class="mb-6">
                        <p class="text-gray-600 mb-4">Digite o email para onde deseja enviar o teste:</p>
                        
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                        <input type="email" wire:model="sendTestEmail" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                               placeholder="seu-email@exemplo.com">
                        @error('sendTestEmail') 
                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                        @enderror
                    </div>

                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 text-sm">
                        <p class="font-semibold text-blue-900">ℹ️ Informação:</p>
                        <p class="text-blue-800 mt-1">Será enviado um email de teste simples contendo as informações da configuração SMTP.</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" wire:click="closeSendTestModal" 
                                class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-semibold transition">
                            Cancelar
                        </button>
                        <button wire:click="sendTestEmailWithSmtp" 
                                class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold transition flex items-center"
                                {{ $sendingTest ? 'disabled' : '' }}>
                            @if($sendingTest)
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                Enviando...
                            @else
                                <i class="fas fa-paper-plane mr-2"></i>
                                Enviar Email
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
