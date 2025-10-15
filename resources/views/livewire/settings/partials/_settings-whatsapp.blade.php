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
                <input type="password" wire:model="whatsapp_auth_token" 
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

    {{-- Configuração do Cron Job --}}
    <div class="bg-white rounded-2xl shadow-lg border border-purple-100 p-6 mt-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-clock text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900">Automatização com Cron Job</h3>
        </div>

        <div class="space-y-4">
            {{-- Descrição --}}
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <p class="text-sm text-gray-700 mb-2">
                    <i class="fas fa-info-circle text-purple-600 mr-2"></i>
                    Configure o cron job no servidor para enviar notificações automaticamente baseadas nos templates criados.
                </p>
            </div>

            {{-- Caminho do Projeto --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-folder text-purple-600 mr-2"></i>
                    Caminho do Projeto
                </label>
                <div class="flex items-center">
                    <input type="text" 
                           value="{{ base_path() }}" 
                           readonly
                           id="projectPath"
                           class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-l-lg text-sm font-mono text-gray-700">
                    <button type="button" 
                            onclick="copyToClipboard('projectPath')"
                            class="px-4 py-2 bg-purple-600 text-white rounded-r-lg hover:bg-purple-700 transition">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>

            {{-- Comando Artisan --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-terminal text-purple-600 mr-2"></i>
                    Comando de Notificações
                </label>
                <div class="flex items-center">
                    <input type="text" 
                           value="php artisan notifications:send-scheduled" 
                           readonly
                           id="artisanCommand"
                           class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-l-lg text-sm font-mono text-gray-700">
                    <button type="button" 
                            onclick="copyToClipboard('artisanCommand')"
                            class="px-4 py-2 bg-purple-600 text-white rounded-r-lg hover:bg-purple-700 transition">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>

            {{-- Comandos por Tipo de Servidor --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    <i class="fas fa-cog text-purple-600 mr-2"></i>
                    Configuração do Cron Job
                </label>
                
                {{-- Tabs --}}
                <div class="flex gap-2 mb-3" x-data="{ tab: 'cpanel' }">
                    <button type="button" 
                            @click="tab = 'cpanel'"
                            :class="tab === 'cpanel' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                        cPanel
                    </button>
                    <button type="button" 
                            @click="tab = 'linux'"
                            :class="tab === 'linux' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                        Linux/SSH
                    </button>
                    <button type="button" 
                            @click="tab = 'windows'"
                            :class="tab === 'windows' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                        Windows
                    </button>
                </div>

                {{-- cPanel Tab --}}
                <div x-show="tab === 'cpanel'" class="space-y-3">
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <h5 class="text-sm font-bold text-gray-900 mb-2">
                            <i class="fas fa-server text-blue-600 mr-2"></i>
                            Configuração no cPanel
                        </h5>
                        <ol class="text-sm text-gray-700 space-y-2">
                            <li>1. Acesse <strong>cPanel → Cron Jobs</strong></li>
                            <li>2. Em "Add New Cron Job", configure:</li>
                        </ol>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Frequência:</label>
                        <div class="flex items-center gap-2">
                            <input type="text" value="*/10" readonly class="w-16 px-2 py-1 bg-gray-50 border rounded text-center font-mono text-sm">
                            <span class="text-xs">minutos</span>
                            <input type="text" value="*" readonly class="w-16 px-2 py-1 bg-gray-50 border rounded text-center font-mono text-sm">
                            <span class="text-xs">horas</span>
                            <input type="text" value="*" readonly class="w-16 px-2 py-1 bg-gray-50 border rounded text-center font-mono text-sm">
                            <span class="text-xs">dias</span>
                            <input type="text" value="*" readonly class="w-16 px-2 py-1 bg-gray-50 border rounded text-center font-mono text-sm">
                            <span class="text-xs">mês</span>
                            <input type="text" value="*" readonly class="w-16 px-2 py-1 bg-gray-50 border rounded text-center font-mono text-sm">
                            <span class="text-xs">dia semana</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Comando:</label>
                        <div class="flex items-center">
                            <textarea 
                                   readonly
                                   id="cronCommandCPanel"
                                   rows="2"
                                   class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-l-lg text-sm font-mono text-gray-700 resize-none">/usr/local/bin/php {{ str_replace('\\', '/', base_path()) }}/artisan notifications:send-scheduled</textarea>
                            <button type="button" 
                                    onclick="copyToClipboard('cronCommandCPanel')"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-r-lg hover:bg-purple-700 transition self-start">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Cole este comando no campo "Command" do cPanel
                        </p>
                    </div>
                    
                    <div class="bg-yellow-50 rounded-lg p-3 border border-yellow-200">
                        <p class="text-xs text-yellow-800">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Importante:</strong> Ajuste o caminho do PHP se necessário. Pode ser <code>/usr/bin/php</code> ou <code>/opt/alt/php80/usr/bin/php</code>
                        </p>
                    </div>
                </div>

                {{-- Linux/SSH Tab --}}
                <div x-show="tab === 'linux'" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Comando Completo:</label>
                        <div class="flex items-center">
                            <textarea 
                                   readonly
                                   id="cronCommandLinux"
                                   rows="2"
                                   class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-l-lg text-sm font-mono text-gray-700 resize-none">*/10 * * * * cd {{ str_replace('\\', '/', base_path()) }} && php artisan notifications:send-scheduled >> /dev/null 2>&1</textarea>
                            <button type="button" 
                                    onclick="copyToClipboard('cronCommandLinux')"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-r-lg hover:bg-purple-700 transition self-start">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <p class="text-xs text-gray-700 mb-2"><strong>Como configurar:</strong></p>
                        <ol class="text-xs text-gray-600 space-y-1 ml-4 list-decimal">
                            <li>Acesse o servidor via SSH</li>
                            <li>Execute: <code class="bg-white px-2 py-1 rounded">crontab -e</code></li>
                            <li>Cole a linha acima</li>
                            <li>Salve: <code class="bg-white px-2 py-1 rounded">:wq</code> (vim) ou <code class="bg-white px-2 py-1 rounded">Ctrl+X</code> (nano)</li>
                            <li>Verifique: <code class="bg-white px-2 py-1 rounded">crontab -l</code></li>
                        </ol>
                    </div>
                </div>

                {{-- Windows Tab --}}
                <div x-show="tab === 'windows'" class="space-y-3">
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <h5 class="text-sm font-bold text-gray-900 mb-2">
                            <i class="fab fa-windows text-blue-600 mr-2"></i>
                            Agendador de Tarefas do Windows
                        </h5>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Programa/Script:</label>
                        <div class="flex items-center">
                            <input type="text" 
                                   readonly
                                   id="windowsProgram"
                                   value="php.exe"
                                   class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-l-lg text-sm font-mono text-gray-700">
                            <button type="button" 
                                    onclick="copyToClipboard('windowsProgram')"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-r-lg hover:bg-purple-700 transition">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Argumentos:</label>
                        <div class="flex items-center">
                            <input type="text" 
                                   readonly
                                   id="windowsArgs"
                                   value="{{ base_path() }}\artisan notifications:send-scheduled"
                                   class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-l-lg text-sm font-mono text-gray-700">
                            <button type="button" 
                                    onclick="copyToClipboard('windowsArgs')"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-r-lg hover:bg-purple-700 transition">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Diretório Inicial:</label>
                        <div class="flex items-center">
                            <input type="text" 
                                   readonly
                                   id="windowsDir"
                                   value="{{ base_path() }}"
                                   class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-l-lg text-sm font-mono text-gray-700">
                            <button type="button" 
                                    onclick="copyToClipboard('windowsDir')"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-r-lg hover:bg-purple-700 transition">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <p class="text-xs text-gray-700 mb-2"><strong>Como configurar:</strong></p>
                        <ol class="text-xs text-gray-600 space-y-1 ml-4 list-decimal">
                            <li>Abra <strong>Agendador de Tarefas</strong> do Windows</li>
                            <li>Clique em <strong>"Criar Tarefa..."</strong></li>
                            <li>Aba <strong>Geral</strong>: Nome e descrição</li>
                            <li>Aba <strong>Disparadores</strong>: Novo → Repetir a cada 10 minutos</li>
                            <li>Aba <strong>Ações</strong>: Novo → Cole os valores acima</li>
                            <li>OK para salvar</li>
                        </ol>
                    </div>
                </div>
            </div>

            {{-- Nota Importante --}}
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h5 class="text-sm font-bold text-gray-900 mb-2 flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    Agora com suporte para cPanel!
                </h5>
                <p class="text-xs text-gray-700">
                    Selecione a aba acima de acordo com seu tipo de hospedagem:
                    <strong>cPanel</strong>, <strong>Linux/SSH</strong> ou <strong>Windows</strong>.
                    Cada ambiente tem instruções específicas e otimizadas.
                </p>
            </div>

            {{-- Teste Manual --}}
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200">
                <h4 class="text-sm font-bold text-gray-900 mb-2">
                    <i class="fas fa-flask text-blue-600 mr-2"></i>
                    Testar Manualmente
                </h4>
                <p class="text-sm text-gray-700 mb-3">Execute este comando para testar o envio de notificações:</p>
                <div class="flex items-center">
                    <input type="text" 
                           value="php artisan notifications:send-scheduled" 
                           readonly
                           id="testCommand"
                           class="flex-1 px-4 py-2 bg-white border border-gray-300 rounded-l-lg text-sm font-mono text-gray-700">
                    <button type="button" 
                            onclick="copyToClipboard('testCommand')"
                            class="px-4 py-2 bg-blue-600 text-white rounded-r-lg hover:bg-blue-700 transition">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>

            {{-- Frequências Comuns --}}
            <div>
                <h4 class="text-sm font-bold text-gray-900 mb-3">
                    <i class="fas fa-stopwatch text-purple-600 mr-2"></i>
                    Frequências Comuns de Cron:
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                    <div class="bg-gray-50 rounded p-2 border border-gray-200">
                        <code class="text-purple-600 font-bold">*/5 * * * *</code>
                        <span class="text-gray-600 ml-2">A cada 5 minutos</span>
                    </div>
                    <div class="bg-gray-50 rounded p-2 border border-gray-200">
                        <code class="text-purple-600 font-bold">*/10 * * * *</code>
                        <span class="text-gray-600 ml-2">A cada 10 minutos</span>
                    </div>
                    <div class="bg-gray-50 rounded p-2 border border-gray-200">
                        <code class="text-purple-600 font-bold">*/15 * * * *</code>
                        <span class="text-gray-600 ml-2">A cada 15 minutos</span>
                    </div>
                    <div class="bg-gray-50 rounded p-2 border border-gray-200">
                        <code class="text-purple-600 font-bold">0 * * * *</code>
                        <span class="text-gray-600 ml-2">A cada hora</span>
                    </div>
                    <div class="bg-gray-50 rounded p-2 border border-gray-200">
                        <code class="text-purple-600 font-bold">0 0 * * *</code>
                        <span class="text-gray-600 ml-2">Todo dia à meia-noite</span>
                    </div>
                    <div class="bg-gray-50 rounded p-2 border border-gray-200">
                        <code class="text-purple-600 font-bold">0 9 * * *</code>
                        <span class="text-gray-600 ml-2">Todo dia às 9h</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            element.setSelectionRange(0, 99999); // Para mobile
            
            navigator.clipboard.writeText(element.value).then(() => {
                // Toast notification
                if (typeof toastr !== 'undefined') {
                    toastr.success('Copiado para a área de transferência!');
                } else {
                    alert('Copiado para a área de transferência!');
                }
            }).catch(err => {
                console.error('Erro ao copiar:', err);
            });
        }
    </script>

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
