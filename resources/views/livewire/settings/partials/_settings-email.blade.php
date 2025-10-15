{{-- Email Settings --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Configurações SMTP --}}
    <div class="bg-white rounded-2xl shadow-lg border border-blue-100 p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-server text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900">Configurações SMTP</h3>
        </div>

        <div class="space-y-4">
            <div class="flex items-center p-3 bg-blue-50 rounded-lg">
                <input type="checkbox" wire:model="email_enabled" id="emailEnabled" class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                <label for="emailEnabled" class="ml-3 text-sm font-medium text-gray-900">
                    Ativar Notificações por Email
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Host SMTP</label>
                <input type="text" wire:model="smtp_host" placeholder="smtp.gmail.com" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Porta</label>
                    <input type="number" wire:model="smtp_port" placeholder="587" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Encriptação</label>
                    <select wire:model="smtp_encryption" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="">Nenhuma</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Usuário SMTP</label>
                <input type="text" wire:model="smtp_username" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Senha SMTP</label>
                <input type="password" wire:model="smtp_password" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email Remetente</label>
                <input type="email" wire:model="from_email" placeholder="noreply@empresa.com" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome Remetente</label>
                <input type="text" wire:model="from_name" placeholder="Sistema SOSERP" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <button type="button" wire:click="testEmailConnection" 
                    wire:loading.attr="disabled"
                    class="w-full bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-4 py-3 rounded-lg font-semibold hover:from-blue-600 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="testEmailConnection">
                    <i class="fas fa-paper-plane mr-2"></i>Testar Conexão
                </span>
                <span wire:loading wire:target="testEmailConnection">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Testando...
                </span>
            </button>
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
                    <input type="checkbox" wire:model="email_notifications.employee_created" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['employee_created'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.employee_created"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.advance_approved" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['advance_approved'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.advance_approved"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.advance_rejected" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['advance_rejected'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.advance_rejected"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.leave_approved" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['leave_approved'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.leave_approved"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.leave_rejected" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['leave_rejected'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.leave_rejected"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.payslip_ready" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['payslip_ready'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.payslip_ready"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.event_created" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                
                @if($email_notifications['event_created'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.event_created"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">-- Selecione um template --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.event_reminder" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['event_reminder'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.event_reminder"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.technician_assigned" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['technician_assigned'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.technician_assigned"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.event_cancelled" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['event_cancelled'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.event_cancelled"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.task_assigned" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['task_assigned'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.task_assigned"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
                    <input type="checkbox" wire:model="email_notifications.meeting_scheduled" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                </div>
                @if($email_notifications['meeting_scheduled'] ?? false)
                    <div class="mt-3 pl-8">
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            <i class="fas fa-file-alt text-indigo-600 mr-1"></i>
                            Selecione o Template:
                        </label>
                        <select wire:model="email_notification_templates.meeting_scheduled"
                                class="w-full text-sm px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Selecione --</option>
                            @foreach($availableNotificationTemplates as $tpl)
                                @if($tpl['email_enabled'])
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
