<div class="max-w-5xl mx-auto space-y-6" x-data="{ copiado: false }">

    {{-- Cabeçalho --}}
    <div class="bg-gradient-to-r from-teal-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                <i class="fab fa-facebook text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black">Integração Meta</h1>
                <p class="text-teal-50 text-sm">Liga o Facebook, o Instagram e o WhatsApp ao teu CRM — as mensagens e os anúncios viram leads.</p>
            </div>
        </div>
    </div>

    {{-- Como ligar (webhook) --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-plug text-teal-600"></i>
            <h2 class="text-lg font-bold text-gray-800">Ligação (webhook)</h2>
        </div>
        <p class="text-sm text-gray-500 mb-4">
            No painel do Meta (Meta for Developers → a tua App → Webhooks), cola este endereço e este token de verificação.
            É por aqui que o Meta te entrega as mensagens e os leads.
        </p>

        <label class="block text-sm font-semibold text-gray-700 mb-1"><i class="fas fa-link text-teal-500 mr-1"></i>URL do Webhook (Callback URL)</label>
        <div class="flex gap-2 mb-4">
            <input type="text" readonly value="{{ $urlWebhook }}"
                   class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-gray-700 text-sm font-mono"
                   x-ref="url">
            <button type="button"
                    @click="navigator.clipboard.writeText($refs.url.value); copiado = true; setTimeout(() => copiado = false, 1500)"
                    class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-semibold text-gray-700 whitespace-nowrap">
                <span x-show="!copiado"><i class="far fa-copy mr-1"></i>Copiar</span>
                <span x-show="copiado" x-cloak class="text-green-600"><i class="fas fa-check mr-1"></i>Copiado</span>
            </button>
        </div>

        <label class="block text-sm font-semibold text-gray-700 mb-1"><i class="fas fa-key text-teal-500 mr-1"></i>Token de Verificação (Verify Token)</label>
        <div class="flex gap-2">
            <input type="text" wire:model="webhook_verify_token"
                   class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-teal-500">
            <button type="button" wire:click="gerarTokenVerificacao"
                    class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-semibold text-gray-700 whitespace-nowrap">
                <i class="fas fa-rotate mr-1"></i>Gerar novo
            </button>
        </div>
        @error('webhook_verify_token') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>

    {{-- App do Meta --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-cube text-teal-600"></i>
            <h2 class="text-lg font-bold text-gray-800">App do Meta</h2>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">App ID</label>
                <input type="text" wire:model="app_id" placeholder="ex.: 1234567890"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    App Secret
                    @if($temAppSecret) <span class="text-xs font-normal text-green-600">(configurado — deixe em branco para manter)</span> @endif
                </label>
                <input type="password" wire:model="app_secret" placeholder="{{ $temAppSecret ? '••••••••••••' : 'App secret da tua App' }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500">
                <p class="text-xs text-gray-400 mt-1">Guardado cifrado. Serve para validar a assinatura dos webhooks.</p>
            </div>
        </div>
    </div>

    {{-- WhatsApp --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="fab fa-whatsapp text-green-500 text-xl"></i>
                <h2 class="text-lg font-bold text-gray-800">WhatsApp Cloud API</h2>
            </div>
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model="whatsapp_enabled" class="sr-only peer">
                <div class="relative w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                <span class="ml-2 text-sm font-semibold text-gray-600">Activo</span>
            </label>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Phone Number ID</label>
                <input type="text" wire:model="whatsapp_phone_number_id"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">WhatsApp Business Account ID</label>
                <input type="text" wire:model="whatsapp_business_account_id"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Número (para mostrar)</label>
                <input type="text" wire:model="whatsapp_display_number" placeholder="+244 9…"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Token de Acesso
                    @if($temWaToken) <span class="text-xs font-normal text-green-600">(configurado)</span> @endif
                </label>
                <input type="password" wire:model="whatsapp_token" placeholder="{{ $temWaToken ? '••••••••••••' : 'Permanent token' }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500">
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3">
            <button type="button" wire:click="testarWhatsApp" wire:loading.attr="disabled" wire:target="testarWhatsApp"
                    class="px-4 py-2 bg-green-50 hover:bg-green-100 text-green-700 rounded-lg text-sm font-semibold border border-green-200">
                <span wire:loading.remove wire:target="testarWhatsApp"><i class="fas fa-vial mr-1"></i>Testar ligação</span>
                <span wire:loading wire:target="testarWhatsApp"><i class="fas fa-spinner fa-spin mr-1"></i>A testar…</span>
            </button>
            @if($resultadoTeste)
                @php([$tipo, $texto] = explode(':', $resultadoTeste, 2))
                <span class="text-sm font-semibold {{ $tipo === 'ok' ? 'text-green-600' : 'text-red-600' }}">
                    <i class="fas {{ $tipo === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation' }} mr-1"></i>{{ $texto }}
                </span>
            @endif
        </div>
    </div>

    {{-- Facebook --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="fab fa-facebook text-blue-600 text-xl"></i>
                <h2 class="text-lg font-bold text-gray-800">Facebook (Página / Messenger)</h2>
            </div>
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model="facebook_enabled" class="sr-only peer">
                <div class="relative w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                <span class="ml-2 text-sm font-semibold text-gray-600">Activo</span>
            </label>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Page ID</label>
                <input type="text" wire:model="facebook_page_id"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nome da Página</label>
                <input type="text" wire:model="facebook_page_name"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Page Token @if($temPageToken) <span class="text-xs font-normal text-green-600">(configurado)</span> @endif
                </label>
                <input type="password" wire:model="facebook_page_token" placeholder="{{ $temPageToken ? '••••••••••••' : 'Page access token' }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    </div>

    {{-- Instagram --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="fab fa-instagram text-pink-600 text-xl"></i>
                <h2 class="text-lg font-bold text-gray-800">Instagram (mensagens directas)</h2>
            </div>
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model="instagram_enabled" class="sr-only peer">
                <div class="relative w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-600"></div>
                <span class="ml-2 text-sm font-semibold text-gray-600">Activo</span>
            </label>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Instagram Account ID</label>
                <input type="text" wire:model="instagram_account_id"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-pink-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">@username</label>
                <input type="text" wire:model="instagram_username"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-pink-500">
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">O Instagram usa o mesmo Page Token do Facebook e a conta tem de estar ligada à Página.</p>
    </div>

    {{-- Comportamento + Guardar --}}
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-sliders text-teal-600"></i>
            <h2 class="text-lg font-bold text-gray-800">O que fazer com o que chega</h2>
        </div>
        <label class="flex items-start gap-3 mb-3 cursor-pointer">
            <input type="checkbox" wire:model="criar_leads" class="mt-1 rounded text-teal-600 focus:ring-teal-500">
            <span class="text-sm text-gray-700"><strong>Criar leads automaticamente.</strong> Uma mensagem de um contacto novo abre um lead no CRM.</span>
        </label>
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" wire:model="lead_ads_enabled" class="mt-1 rounded text-teal-600 focus:ring-teal-500">
            <span class="text-sm text-gray-700"><strong>Anúncios de leads (Lead Ads).</strong> Os formulários dos teus anúncios entram directos como leads.</span>
        </label>

        <div class="border-t mt-6 pt-4 flex justify-end">
            <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="px-6 py-2.5 bg-gradient-to-r from-teal-600 to-cyan-600 text-white rounded-lg font-bold hover:shadow-lg transition">
                <span wire:loading.remove wire:target="save"><i class="fas fa-save mr-1"></i>Guardar</span>
                <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-1"></i>A guardar…</span>
            </button>
        </div>
    </div>
</div>
