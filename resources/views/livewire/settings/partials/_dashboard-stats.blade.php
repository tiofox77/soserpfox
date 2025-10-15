{{-- Stats Cards --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    {{-- Email Card --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50">
                <i class="fas fa-envelope text-white text-2xl"></i>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $email_enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                {{ $email_enabled ? 'Ativo' : 'Inativo' }}
            </span>
        </div>
        <p class="text-sm text-blue-600 font-semibold mb-2">Email</p>
        <p class="text-4xl font-bold text-gray-900 mb-1">
            {{ count(array_filter($email_notifications ?? [])) }}
        </p>
        <p class="text-xs text-gray-500">Notificações ativas</p>
    </div>

    {{-- SMS Card --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50">
                <i class="fas fa-sms text-white text-2xl"></i>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $sms_enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                {{ $sms_enabled ? 'Ativo' : 'Inativo' }}
            </span>
        </div>
        <p class="text-sm text-purple-600 font-semibold mb-2">SMS</p>
        <p class="text-4xl font-bold text-gray-900 mb-1">
            {{ count(array_filter($sms_notifications ?? [])) }}
        </p>
        <p class="text-xs text-gray-500">Notificações ativas</p>
    </div>

    {{-- WhatsApp Card --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50">
                <i class="fab fa-whatsapp text-white text-2xl"></i>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $whatsapp_enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                {{ $whatsapp_enabled ? 'Ativo' : 'Inativo' }}
            </span>
        </div>
        <p class="text-sm text-green-600 font-semibold mb-2">WhatsApp</p>
        <p class="text-4xl font-bold text-gray-900 mb-1">
            {{ count(array_filter($whatsapp_notifications ?? [])) }}
        </p>
        <p class="text-xs text-gray-500">Notificações ativas</p>
    </div>

    {{-- Templates Card --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 border border-orange-100 overflow-hidden hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/50">
                <i class="fas fa-file-alt text-white text-2xl"></i>
            </div>
        </div>
        <p class="text-sm text-orange-600 font-semibold mb-2">Templates</p>
        <p class="text-4xl font-bold text-gray-900 mb-1">
            {{ count($whatsapp_templates ?? []) }}
        </p>
        <p class="text-xs text-gray-500">WhatsApp templates</p>
    </div>
</div>
