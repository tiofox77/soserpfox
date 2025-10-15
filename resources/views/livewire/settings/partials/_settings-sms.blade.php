{{-- SMS Settings --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Configurações SMS --}}
    <div class="bg-white rounded-2xl shadow-lg border border-purple-100 p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-sms text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900">Configurações SMS</h3>
        </div>

        <div class="space-y-4">
            <div class="flex items-center p-3 bg-purple-50 rounded-lg">
                <input type="checkbox" wire:model="sms_enabled" id="smsEnabled" class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                <label for="smsEnabled" class="ml-3 text-sm font-medium text-gray-900">
                    Ativar Notificações por SMS
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Provedor SMS</label>
                <select wire:model.live="sms_provider" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione o provedor</option>
                    <option value="twilio">Twilio</option>
                    <option value="d7networks">D7 Networks</option>
                    <option value="nexmo">Nexmo / Vonage</option>
                    <option value="other">Outro</option>
                </select>
            </div>

            @if($sms_provider === 'twilio')
                {{-- Twilio Fields --}}
                <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-2"></i>
                        <div class="text-sm text-blue-800">
                            <p class="font-semibold mb-1">Twilio SMS</p>
                            <p>Obtenha suas credenciais em <a href="https://console.twilio.com/" target="_blank" class="underline hover:text-blue-900">console.twilio.com</a></p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Account SID
                        <span class="text-xs text-gray-500">(Inicia com AC...)</span>
                    </label>
                    <input type="text" wire:model="sms_account_sid" 
                           placeholder="AC..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Auth Token</label>
                    <input type="password" wire:model="sms_auth_token" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        From Phone Number
                        <span class="text-xs text-gray-500">(Formato: +1234567890)</span>
                    </label>
                    <input type="text" wire:model="sms_from_number" 
                           placeholder="+1234567890" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Número Twilio verificado ou Messaging Service SID</p>
                </div>
            @elseif($sms_provider === 'd7networks')
                {{-- D7 Networks Fields --}}
                <div class="p-4 bg-purple-50 rounded-lg border border-purple-200">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-purple-600 mt-1 mr-2"></i>
                        <div class="text-sm text-purple-800">
                            <p class="font-semibold mb-1">D7 Networks</p>
                            <p>Obtenha suas credenciais em <a href="https://app.d7networks.com/" target="_blank" class="underline hover:text-purple-900">app.d7networks.com</a></p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        API Token
                        <span class="text-xs text-gray-500">(Bearer Token)</span>
                    </label>
                    <input type="password" wire:model="sms_api_token" 
                           placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Sender ID
                        <span class="text-xs text-gray-500">(Opcional - Nome do remetente)</span>
                    </label>
                    <input type="text" wire:model="sms_sender_id" 
                           placeholder="SOSERP"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Alfanumérico, até 11 caracteres</p>
                </div>
            @elseif($sms_provider === 'nexmo')
                {{-- Nexmo / Vonage Fields --}}
                <div class="p-4 bg-orange-50 rounded-lg border border-orange-200">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-orange-600 mt-1 mr-2"></i>
                        <div class="text-sm text-orange-800">
                            <p class="font-semibold mb-1">Nexmo / Vonage</p>
                            <p>Obtenha suas credenciais em <a href="https://dashboard.nexmo.com/" target="_blank" class="underline hover:text-orange-900">dashboard.nexmo.com</a></p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        API Key
                    </label>
                    <input type="text" wire:model="sms_account_sid" 
                           placeholder="abc12345"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        API Secret
                    </label>
                    <input type="password" wire:model="sms_auth_token" 
                           placeholder="AbCdEfGhIjKlMnOp"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Sender ID
                        <span class="text-xs text-gray-500">(Nome ou número)</span>
                    </label>
                    <input type="text" wire:model="sms_sender_id" 
                           placeholder="SOSERP" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Alfanumérico (até 11 chars) ou número com código do país</p>
                </div>
            @elseif($sms_provider === 'other')
                {{-- Generic Provider Fields --}}
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-gray-600 mt-1 mr-2"></i>
                        <div class="text-sm text-gray-800">
                            <p class="font-semibold mb-1">Outro Provedor</p>
                            <p>Configure as credenciais conforme seu provedor SMS</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        API Key / Account ID
                    </label>
                    <input type="text" wire:model="sms_account_sid" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        API Secret / Token
                    </label>
                    <input type="password" wire:model="sms_auth_token" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Sender ID / From Number
                    </label>
                    <input type="text" wire:model="sms_sender_id" 
                           placeholder="SOSERP ou +244..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            @endif

            @if($sms_provider)
                <div class="border-t border-gray-200 pt-4">
                    <button type="button" wire:click="testSmsConnection" 
                            wire:loading.attr="disabled"
                            class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white px-4 py-3 rounded-lg font-semibold hover:from-purple-600 hover:to-pink-700 transition-all shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="testSmsConnection">
                            <i class="fas fa-vial mr-2"></i>Testar Conexão
                        </span>
                        <span wire:loading wire:target="testSmsConnection">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Testando...
                        </span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Tipos de Notificação --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-bell text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900">Tipos de Notificação</h3>
        </div>

        <div class="space-y-3">
            {{-- Funcionário Criado --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-user-plus text-blue-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Funcionário Criado</span>
                    </div>
                    <input type="checkbox" wire:model="sms_notifications.employee_created" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['employee_created'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.employee_created"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Adiantamento Aprovado --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-dollar-sign text-green-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Adiantamento Aprovado</span>
                    </div>
                    <input type="checkbox" wire:model="sms_notifications.advance_approved" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['advance_approved'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.advance_approved"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Adiantamento Rejeitado --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-times-circle text-red-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Adiantamento Rejeitado</span>
                    </div>
                    <input type="checkbox" wire:model="sms_notifications.advance_rejected" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['advance_rejected'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.advance_rejected"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Férias Aprovadas --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-umbrella-beach text-yellow-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Férias Aprovadas</span>
                    </div>
                    <input type="checkbox" wire:model="sms_notifications.leave_approved" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['leave_approved'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.leave_approved"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Férias Rejeitadas --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-ban text-gray-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Férias Rejeitadas</span>
                    </div>
                    <input type="checkbox" wire:model="sms_notifications.leave_rejected" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['leave_rejected'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.leave_rejected"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Recibo de Pagamento --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-file-invoice text-purple-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Recibo de Pagamento</span>
                    </div>
                    <input type="checkbox" wire:model="sms_notifications.payslip_ready" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['payslip_ready'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.payslip_ready"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Evento Criado --}}
            <div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-plus text-teal-500 mr-3"></i>
                        <span class="text-sm font-medium text-gray-900">Evento Criado</span>
                    </div>
                    <input type="checkbox" wire:model="sms_notifications.event_created" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                
                @if($sms_notifications['event_created'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.event_created"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">-- Selecione um template --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">
                                        {{ $tpl['name'] }} ({{ ucfirst($tpl['module']) }})
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
                    <input type="checkbox" wire:model="sms_notifications.event_reminder" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['event_reminder'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.event_reminder"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
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
                    <input type="checkbox" wire:model="sms_notifications.technician_assigned" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['technician_assigned'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.technician_assigned"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
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
                    <input type="checkbox" wire:model="sms_notifications.event_cancelled" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['event_cancelled'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.event_cancelled"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
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
                    <input type="checkbox" wire:model="sms_notifications.task_assigned" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['task_assigned'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.task_assigned"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
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
                    <input type="checkbox" wire:model="sms_notifications.meeting_scheduled" 
                           class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                </div>
                @if($sms_notifications['meeting_scheduled'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="sms_notification_templates.meeting_scheduled"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['sms_enabled'])
                                    <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
