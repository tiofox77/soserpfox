<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fab fa-whatsapp mr-3 text-green-500"></i>
                    Configurações WhatsApp
                </h2>
                <p class="text-gray-600 mt-1">Gerencie as notificações via WhatsApp (Twilio)</p>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="testConnection" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-plug mr-2"></i>Testar Conexão
                </button>
                <button type="button" wire:click="fetchTemplates" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                    <i class="fas fa-sync mr-2"></i>Buscar Templates
                </button>
            </div>
        </div>
    </div>

    @if($connectionStatus)
        <div class="mb-4 p-4 rounded-lg {{ $connectionStatus['success'] ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
            <div class="flex items-center">
                <i class="fas {{ $connectionStatus['success'] ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600' }} mr-2"></i>
                <span class="font-semibold">{{ $connectionStatus['message'] }}</span>
                @if(isset($connectionStatus['account_name']))
                    <span class="ml-2">— Conta: <strong>{{ $connectionStatus['account_name'] }}</strong></span>
                @endif
            </div>
        </div>
    @endif

    <form wire:submit.prevent="save">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Credenciais Twilio --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-key mr-2 text-yellow-500"></i>
                    Credenciais Twilio
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-id-badge text-blue-500 mr-2"></i>Account SID
                        </label>
                        <input wire:model="twilio_account_sid" type="text" placeholder="AC..."
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                        @error('twilio_account_sid') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock text-red-500 mr-2"></i>Auth Token
                        </label>
                        <input wire:model="twilio_auth_token" type="password" placeholder="Auth Token"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                        @error('twilio_auth_token') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fab fa-whatsapp text-green-500 mr-2"></i>Número WhatsApp (From)
                        </label>
                        <input wire:model="whatsapp_from_number" type="text" placeholder="+15558740135"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">Exemplo: +15558740135 ou whatsapp:+15558740135</p>
                        @error('whatsapp_from_number') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-building text-purple-500 mr-2"></i>Business Account ID
                        </label>
                        <input wire:model="whatsapp_business_account_id" type="text" placeholder="2404220280010457"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                        @error('whatsapp_business_account_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center gap-6 pt-2">
                        <label class="flex items-center cursor-pointer">
                            <input wire:model="is_enabled" type="checkbox" class="w-5 h-5 text-green-600 rounded mr-2">
                            <span class="text-sm font-semibold text-gray-700">
                                <i class="fas fa-toggle-on text-green-500 mr-1"></i>WhatsApp Ativo
                            </span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input wire:model="is_sandbox" type="checkbox" class="w-5 h-5 text-yellow-600 rounded mr-2">
                            <span class="text-sm font-semibold text-gray-700">
                                <i class="fas fa-flask text-yellow-500 mr-1"></i>Modo Sandbox
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Templates Configurados --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-file-alt mr-2 text-purple-600"></i>
                    Templates Configurados
                </h3>

                @if(count($templates) > 0)
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nome</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">SID</th>
                                    <th class="px-4 py-3 w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($templates as $index => $template)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $template['name'] }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $template['sid'] }}</td>
                                        <td class="px-4 py-3">
                                            <button type="button" wire:click="removeTemplate({{ $index }})"
                                                    class="text-red-500 hover:text-red-700 transition">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-file-alt text-4xl mb-2"></i>
                        <p>Nenhum template configurado</p>
                        <p class="text-sm">Clique em "Buscar Templates" para importar do Twilio</p>
                    </div>
                @endif

                @if(count($availableTemplates) > 0)
                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-3">
                            <i class="fas fa-cloud-download-alt mr-1 text-blue-500"></i>Templates Disponíveis no Twilio:
                        </h4>
                        <div class="space-y-2">
                            @foreach($availableTemplates as $template)
                                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-100">
                                    <div>
                                        <span class="font-semibold text-gray-800">{{ $template['name'] }}</span>
                                        <br><span class="text-xs text-gray-500 font-mono">{{ $template['sid'] }}</span>
                                    </div>
                                    <button type="button" wire:click="addTemplate({{ json_encode($template) }})"
                                            class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">
                                        <i class="fas fa-plus mr-1"></i>Adicionar
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tipos de Notificação --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-bell mr-2 text-yellow-500"></i>
                Tipos de Notificação
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                    <input type="checkbox" wire:model="notification_settings.salary_advance_approved" class="w-5 h-5 text-green-600 rounded mr-3">
                    <div>
                        <span class="text-sm font-semibold text-gray-700">Adiantamento Aprovado</span>
                        <p class="text-xs text-gray-500">Notificar quando adiantamento salarial for aprovado</p>
                    </div>
                </label>
                <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                    <input type="checkbox" wire:model="notification_settings.salary_advance_rejected" class="w-5 h-5 text-green-600 rounded mr-3">
                    <div>
                        <span class="text-sm font-semibold text-gray-700">Adiantamento Rejeitado</span>
                        <p class="text-xs text-gray-500">Notificar quando adiantamento salarial for rejeitado</p>
                    </div>
                </label>
                <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                    <input type="checkbox" wire:model="notification_settings.vacation_approved" class="w-5 h-5 text-green-600 rounded mr-3">
                    <div>
                        <span class="text-sm font-semibold text-gray-700">Férias Aprovadas</span>
                        <p class="text-xs text-gray-500">Notificar quando férias forem aprovadas</p>
                    </div>
                </label>
                <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                    <input type="checkbox" wire:model="notification_settings.vacation_rejected" class="w-5 h-5 text-green-600 rounded mr-3">
                    <div>
                        <span class="text-sm font-semibold text-gray-700">Férias Rejeitadas</span>
                        <p class="text-xs text-gray-500">Notificar quando férias forem rejeitadas</p>
                    </div>
                </label>
                <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                    <input type="checkbox" wire:model="notification_settings.payslip_ready" class="w-5 h-5 text-green-600 rounded mr-3">
                    <div>
                        <span class="text-sm font-semibold text-gray-700">Recibo Disponível</span>
                        <p class="text-xs text-gray-500">Notificar quando recibo de pagamento estiver pronto</p>
                    </div>
                </label>
                <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                    <input type="checkbox" wire:model="notification_settings.employee_created" class="w-5 h-5 text-green-600 rounded mr-3">
                    <div>
                        <span class="text-sm font-semibold text-gray-700">Funcionário Criado</span>
                        <p class="text-xs text-gray-500">Notificar quando novo funcionário for registrado</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Teste de Envio --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-paper-plane mr-2 text-green-500"></i>
                    Enviar Mensagem de Teste
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-mobile-alt text-green-500 mr-2"></i>Número de Teste (com código do país)
                        </label>
                        <input wire:model="testNumber" type="text" placeholder="+244939729902"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                        @error('testNumber') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment-alt text-blue-500 mr-2"></i>Mensagem
                        </label>
                        <textarea wire:model="testMessage" rows="3" placeholder="Teste de mensagem WhatsApp..."
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
                        @error('testMessage') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <button type="button" wire:click="sendTestMessage"
                            wire:loading.attr="disabled"
                            class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="sendTestMessage">
                            <i class="fab fa-whatsapp mr-2"></i>Enviar Teste
                        </span>
                        <span wire:loading wire:target="sendTestMessage">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                        </span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Salvar --}}
        <div class="flex justify-end">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="save">
                    <i class="fas fa-save mr-2"></i>Salvar Configurações
                </span>
                <span wire:loading wire:target="save">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                </span>
            </button>
        </div>
    </form>
</div>
