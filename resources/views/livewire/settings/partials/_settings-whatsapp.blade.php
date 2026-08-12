{{-- WhatsApp Settings --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Configurações WhatsApp --}}
    <div class="bg-white rounded-2xl shadow-lg border border-green-100 p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fab fa-whatsapp text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900">Configurações WhatsApp</h3>
        </div>

        <div class="space-y-4">
            <div class="flex items-center p-3 bg-green-50 rounded-lg">
                <input type="checkbox" wire:model="whatsapp_enabled" id="whatsappEnabled" class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                <label for="whatsappEnabled" class="ml-3 text-sm font-medium text-gray-900">
                    Ativar Notificações por WhatsApp
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Account SID (Twilio)</label>
                <input type="text" wire:model="whatsapp_account_sid" placeholder="AC..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Auth Token</label>
                <input type="password" autocomplete="new-password" placeholder="{{ ($segredosGuardados['whatsapp_auth_token'] ?? false) ? '•••••••• (guardado)' : 'Escreva o token' }}" wire:model="whatsapp_auth_token" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Número WhatsApp</label>
                <input type="text" wire:model="whatsapp_from_number" placeholder="+15558740135" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Business Account ID</label>
                <input type="text" wire:model="whatsapp_business_account_id" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>

            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                <input type="checkbox" wire:model="whatsapp_sandbox" id="whatsappSandbox" class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                <label for="whatsappSandbox" class="ml-3 text-sm font-medium text-gray-900">
                    Modo Sandbox
                </label>
            </div>

            {{-- Link para Templates Automatizados --}}
            <div class="border-t border-gray-200 pt-4 pb-4">
                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-4 border-2 border-dashed border-indigo-200">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-gray-900 mb-1 flex items-center">
                                <i class="fas fa-magic text-indigo-600 mr-2"></i>
                                Templates Automatizados
                            </h4>
                            <p class="text-xs text-gray-600 mb-3">
                                Configure notificações automáticas para eventos, lembretes e mais
                            </p>
                            <a href="{{ route('notifications.templates') }}" 
                               class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 text-white text-sm font-semibold rounded-lg hover:from-indigo-600 hover:to-purple-700 transition shadow-lg">
                                <i class="fas fa-file-alt mr-2"></i>
                                Gerenciar Templates
                            </a>
                        </div>
                        <div class="ml-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-robot text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <button type="button" wire:click="fetchWhatsAppTemplates" 
                        wire:loading.attr="disabled"
                        class="w-full bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-4 py-3 rounded-lg font-semibold hover:from-blue-600 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl mb-3 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="fetchWhatsAppTemplates">
                        <i class="fas fa-sync mr-2"></i>Buscar Templates
                    </span>
                    <span wire:loading wire:target="fetchWhatsAppTemplates">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Buscando...
                    </span>
                </button>

                @if(count($whatsapp_templates) > 0)
                    <div class="mb-3">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Templates Configurados ({{ count($whatsapp_templates) }})</h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($whatsapp_templates as $index => $template)
                                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">{{ $template['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $template['sid'] }}</p>
                                    </div>
                                    <button type="button" wire:click="removeWhatsAppTemplate({{ $index }})" 
                                            class="ml-3 text-red-600 hover:text-red-800">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(count($availableWhatsAppTemplates) > 0)
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Templates Disponíveis ({{ count($availableWhatsAppTemplates) }})</h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($availableWhatsAppTemplates as $template)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">{{ $template['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $template['sid'] }}</p>
                                    </div>
                                    <button type="button" wire:click="addWhatsAppTemplate({{ json_encode($template) }})" 
                                            class="ml-3 bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-lg text-xs font-semibold transition-colors">
                                        <i class="fas fa-plus mr-1"></i>Adicionar
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Enviar Teste</h4>
                
                <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200 mb-3">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-yellow-600 mt-1 mr-2"></i>
                        <div class="text-xs text-yellow-800">
                            <p class="font-semibold mb-1">WhatsApp Business API</p>
                            <p>Apenas templates aprovados podem ser enviados. Busque templates primeiro.</p>
                        </div>
                    </div>
                </div>

                @if(count($whatsapp_templates) > 0)
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template para Teste</label>
                        <select wire:model="testTemplateSid" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">Selecione um template</option>
                            @foreach($whatsapp_templates as $template)
                                <option value="{{ $template['sid'] }}">{{ $template['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif(count($availableWhatsAppTemplates) > 0)
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template para Teste</label>
                        <select wire:model="testTemplateSid" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">Selecione um template</option>
                            @foreach($availableWhatsAppTemplates as $template)
                                <option value="{{ $template['sid'] }}">{{ $template['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="mb-3 p-3 bg-orange-50 rounded-lg border border-orange-200">
                        <p class="text-sm text-orange-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Clique em "Buscar Templates" primeiro para carregar os templates disponíveis.
                        </p>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Número de Teste</label>
                    <input type="text" wire:model="testPhone" placeholder="+244923456789" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Formato internacional com código do país</p>
                </div>

                <button type="button" wire:click="prepareTestWhatsApp" 
                        wire:loading.attr="disabled"
                        class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-3 rounded-lg font-semibold hover:from-green-600 hover:to-emerald-700 transition-all shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="prepareTestWhatsApp">
                        <i class="fas fa-paper-plane mr-2"></i>Enviar Teste com Template
                    </span>
                    <span wire:loading wire:target="prepareTestWhatsApp">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Preparando...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Envio automático.

         Aqui estavam 314 linhas a ensinar a configurar um cron job — caminho
         do projecto, comando, frequências, instruções para cPanel, Linux e
         Windows. Deixou de ser preciso: as notificações saem à boleia do
         tráfego, tratadas depois de a resposta seguir para o browser, com uma
         tranca de dez minutos por empresa.

         Manter as instruções seria pior do que inútil: quem as seguisse ficava
         com um cron a correr o mesmo comando que o sistema já corre sozinho. --}}
    <div class="bg-white rounded-2xl shadow-lg border border-emerald-100 p-6 mt-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-green-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-bolt text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900">Envio automático</h3>
                <p class="text-xs text-gray-500">Sem cron job, sem configuração no servidor</p>
            </div>
        </div>

        <div class="bg-emerald-50 rounded-lg p-4 border border-emerald-200 mb-4">
            <p class="text-sm text-gray-700">
                <i class="fas fa-circle-check text-emerald-600 mr-2"></i>
                As notificações dos modelos activos são enviadas <strong>sozinhas</strong>, enquanto
                houver alguém a usar o sistema. Não é preciso configurar nada no servidor.
            </p>
        </div>

        <div class="grid sm:grid-cols-3 gap-3 mb-4">
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                <p class="text-xs font-bold text-gray-500 uppercase mb-1">Com que frequência</p>
                <p class="text-sm text-gray-800">A cada 10 minutos, no máximo</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                <p class="text-xs font-bold text-gray-500 uppercase mb-1">Quando</p>
                <p class="text-sm text-gray-800">Com alguém a navegar no sistema</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                <p class="text-xs font-bold text-gray-500 uppercase mb-1">Repetições</p>
                <p class="text-sm text-gray-800">Um aviso por destinatário e por dia</p>
            </div>
        </div>

        <div class="bg-amber-50 rounded-lg p-3 border border-amber-200 mb-4">
            <p class="text-xs text-amber-800">
                <i class="fas fa-circle-info mr-1"></i>
                Como o envio depende de haver alguém a usar o sistema, num dia sem ninguém a
                trabalhar as notificações ficam à espera — e saem no primeiro acesso seguinte.
            </p>
        </div>

        {{-- O comando manual saiu daqui.

             Era o último resto das instruções de cron: uma linha de terminal
             num ecrã de definições. Quem usa esta página não tem acesso à
             consola do servidor, e mostrar-lhe um comando que não pode correr
             só levanta a dúvida de que talvez fosse preciso corrê-lo — que é
             exactamente o contrário do que este painel diz.

             Continua a existir para quem tem consola (`php artisan
             notifications:send-scheduled`) e pela rota de manutenção. --}}
    </div>

    {{-- O `copyToClipboard` saiu com os campos que o usavam: existia só para
         copiar o caminho do projecto e o comando do cron, e ficou sem ninguém
         a chamá-lo. Confirmado que mais nenhum ecrã de definições depende
         dele. --}}

    {{-- Modal de Variáveis --}}
    @if($showVariablesModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" 
             x-data="{ show: true }" 
             x-show="show" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Overlay --}}
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" 
                     @click="show = false; $wire.closeVariablesModal()"></div>

                {{-- Modal --}}
                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full z-50"
                     x-show="show"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    {{-- Header --}}
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-edit text-white"></i>
                                </div>
                                <h3 class="text-lg font-bold text-white">
                                    Configurar Variáveis do Template
                                </h3>
                            </div>
                            <button wire:click="closeVariablesModal" 
                                    class="text-white hover:text-green-100 transition">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4">
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                Template: <strong class="text-gray-900">{{ $selectedTemplate['name'] ?? '' }}</strong>
                            </p>
                            <p class="text-xs text-gray-500">
                                Preencha as variáveis abaixo para personalizar a mensagem:
                            </p>
                        </div>

                        <div class="space-y-4">
                            @foreach($testTemplateVariables as $key => $value)
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-tag text-green-500 mr-2"></i>
                                        <span class="font-bold text-green-600">@{{ $key }}</span>
                                        @if($key === 'date')
                                            <span class="text-gray-500 text-xs ml-2">(ex: 15/10/2025)</span>
                                        @elseif($key === 'event')
                                            <span class="text-gray-500 text-xs ml-2">(ex: Consulta Médica)</span>
                                        @elseif($key === 'number')
                                            <span class="text-gray-500 text-xs ml-2">(ex: 123)</span>
                                        @elseif($key === 'var')
                                            <span class="text-gray-500 text-xs ml-2">(valor personalizado)</span>
                                        @endif
                                    </label>
                                    <input type="text" 
                                           wire:model="testTemplateVariables.{{ $key }}" 
                                           placeholder="Digite o valor para {{ $key }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                    <p class="text-xs text-gray-500 mt-2">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Substituirá <code class="bg-white px-2 py-1 rounded border border-gray-300 font-mono text-xs">@{{ $key }}</code> no template
                                    </p>
                                </div>
                            @endforeach
                            
                            @if(empty($testTemplateVariables))
                                <div class="text-center py-4 text-gray-500">
                                    <i class="fas fa-info-circle text-2xl mb-2"></i>
                                    <p>Este template não possui variáveis.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t border-gray-200">
                        <button type="button" 
                                wire:click="closeVariablesModal"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="button" 
                                wire:click="sendTestWhatsApp"
                                wire:loading.attr="disabled"
                                class="px-6 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg font-semibold hover:from-green-600 hover:to-emerald-700 transition shadow-lg disabled:opacity-50">
                            <span wire:loading.remove wire:target="sendTestWhatsApp">
                                <i class="fas fa-paper-plane mr-2"></i>Enviar Agora
                            </span>
                            <span wire:loading wire:target="sendTestWhatsApp">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            console.log('Variables Modal is open:', @json($showVariablesModal));
            console.log('Template variables:', @json($testTemplateVariables));
        </script>
    @endif

    {{-- Tipos de Notificação --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-bell text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900">Tipos de Notificação</h3>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center">
                    <i class="fas fa-user-plus text-blue-500 mr-3"></i>
                    <span class="text-sm font-medium text-gray-900">Funcionário Criado</span>
                </div>
                <input type="checkbox" wire:model="whatsapp_notifications.employee_created" 
                       class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
            </div>

            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center">
                    <i class="fas fa-dollar-sign text-green-500 mr-3"></i>
                    <span class="text-sm font-medium text-gray-900">Adiantamento Aprovado</span>
                </div>
                <input type="checkbox" wire:model="whatsapp_notifications.salary_advance_approved" 
                       class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
            </div>

            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center">
                    <i class="fas fa-times-circle text-red-500 mr-3"></i>
                    <span class="text-sm font-medium text-gray-900">Adiantamento Rejeitado</span>
                </div>
                <input type="checkbox" wire:model="whatsapp_notifications.salary_advance_rejected" 
                       class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
            </div>

            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center">
                    <i class="fas fa-umbrella-beach text-yellow-500 mr-3"></i>
                    <span class="text-sm font-medium text-gray-900">Férias Aprovadas</span>
                </div>
                <input type="checkbox" wire:model="whatsapp_notifications.vacation_approved" 
                       class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
            </div>

            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center">
                    <i class="fas fa-ban text-gray-500 mr-3"></i>
                    <span class="text-sm font-medium text-gray-900">Férias Rejeitadas</span>
                </div>
                <input type="checkbox" wire:model="whatsapp_notifications.vacation_rejected" 
                       class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
            </div>

            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center">
                    <i class="fas fa-file-invoice text-purple-500 mr-3"></i>
                    <span class="text-sm font-medium text-gray-900">Recibo de Pagamento</span>
                </div>
                <input type="checkbox" wire:model="whatsapp_notifications.payslip_ready" 
                       class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
            </div>

            {{-- Evento Criado --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-plus text-teal-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Evento Criado</span>
                    </div>
                    <input type="checkbox" wire:model.live="whatsapp_notifications.event_created" 
                           class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                </div>
                
                @if($whatsapp_notifications['event_created'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="whatsapp_notification_templates.event_created"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">-- Selecione um template --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['whatsapp_enabled'])
                                    <option value="{{ $tpl['id'] }}">
                                        {{ $tpl['name'] }} ({{ ucfirst($tpl['module']) }} - {{ $tpl['event'] }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @if(empty($availableNotificationTemplates))
                            <p class="text-xs text-orange-600 mt-1">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Nenhum template configurado. <a href="/notifications/templates" class="underline font-semibold">Criar template</a>
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Lembrete de Evento --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-bell text-purple-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Lembrete de Evento</span>
                    </div>
                    <input type="checkbox" wire:model.live="whatsapp_notifications.event_reminder" 
                           class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                </div>
                
                @if($whatsapp_notifications['event_reminder'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="whatsapp_notification_templates.event_reminder"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">-- Selecione um template --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['whatsapp_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }} ({{ ucfirst($tpl['module']) }} - {{ $tpl['event'] }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Técnico Designado --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-user-tag text-cyan-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Técnico Designado</span>
                    </div>
                    <input type="checkbox" wire:model.live="whatsapp_notifications.technician_assigned" 
                           class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                </div>
                
                @if($whatsapp_notifications['technician_assigned'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="whatsapp_notification_templates.technician_assigned"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">-- Selecione um template --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['whatsapp_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }} ({{ ucfirst($tpl['module']) }} - {{ $tpl['event'] }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Evento Cancelado --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-times text-red-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Evento Cancelado</span>
                    </div>
                    <input type="checkbox" wire:model.live="whatsapp_notifications.event_cancelled" 
                           class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                </div>
                
                @if($whatsapp_notifications['event_cancelled'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="whatsapp_notification_templates.event_cancelled"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">-- Selecione um template --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['whatsapp_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }} ({{ ucfirst($tpl['module']) }} - {{ $tpl['event'] }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Tarefa Atribuída --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-tasks text-orange-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Tarefa Atribuída</span>
                    </div>
                    <input type="checkbox" wire:model.live="whatsapp_notifications.task_assigned" 
                           class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                </div>
                
                @if($whatsapp_notifications['task_assigned'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="whatsapp_notification_templates.task_assigned"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">-- Selecione um template --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['whatsapp_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }} ({{ ucfirst($tpl['module']) }} - {{ $tpl['event'] }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Reunião Agendada --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-handshake text-lime-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Reunião Agendada</span>
                    </div>
                    <input type="checkbox" wire:model.live="whatsapp_notifications.meeting_scheduled" 
                           class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                </div>
                
                @if($whatsapp_notifications['meeting_scheduled'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="whatsapp_notification_templates.meeting_scheduled"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">-- Selecione um template --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['whatsapp_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }} ({{ ucfirst($tpl['module']) }} - {{ $tpl['event'] }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
