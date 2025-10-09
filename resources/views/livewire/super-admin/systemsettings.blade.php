<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center">
            <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4">
                <i class="fas fa-cog text-3xl"></i>
            </div>
            <div>
                <h2 class="text-3xl font-bold">Configurações do Sistema</h2>
                <p class="text-purple-100 text-sm mt-1">Gerencie todas as configurações globais da plataforma</p>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="bg-white rounded-t-2xl shadow-lg border-b-2 border-gray-200">
        <div class="flex flex-wrap gap-1 p-2">
            <button wire:click="$set('activeTab', 'general')" 
                    class="px-6 py-3 rounded-lg font-semibold transition {{ $activeTab === 'general' ? 'bg-purple-600 text-white shadow-lg' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas fa-info-circle mr-2"></i>Geral
            </button>
            <button wire:click="$set('activeTab', 'appearance')" 
                    class="px-6 py-3 rounded-lg font-semibold transition {{ $activeTab === 'appearance' ? 'bg-purple-600 text-white shadow-lg' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas fa-palette mr-2"></i>Aparência
            </button>
            <button wire:click="$set('activeTab', 'seo')" 
                    class="px-6 py-3 rounded-lg font-semibold transition {{ $activeTab === 'seo' ? 'bg-purple-600 text-white shadow-lg' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas fa-search mr-2"></i>SEO
            </button>
            <button wire:click="$set('activeTab', 'features')" 
                    class="px-6 py-3 rounded-lg font-semibold transition {{ $activeTab === 'features' ? 'bg-purple-600 text-white shadow-lg' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas fa-toggle-on mr-2"></i>Funcionalidades
            </button>
            <button wire:click="$set('activeTab', 'social')" 
                    class="px-6 py-3 rounded-lg font-semibold transition {{ $activeTab === 'social' ? 'bg-purple-600 text-white shadow-lg' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas fa-share-alt mr-2"></i>Redes Sociais
            </button>
            <button wire:click="$set('activeTab', 'schema')" 
                    class="px-6 py-3 rounded-lg font-semibold transition {{ $activeTab === 'schema' ? 'bg-purple-600 text-white shadow-lg' : 'text-gray-600 hover:bg-gray-100' }}">
                <i class="fas fa-code mr-2"></i>Schema.org
            </button>
        </div>
    </div>

    {{-- Tab Content --}}
    <div class="bg-white rounded-b-2xl shadow-lg p-6">
        
        {{-- TAB: GERAL --}}
        @if($activeTab === 'general')
        <div class="space-y-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-info-circle mr-3 text-purple-600"></i>
                Informações Gerais
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Nome da Aplicação --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-signature mr-1"></i>Nome da Aplicação *
                    </label>
                    <input type="text" wire:model="app_name" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="SOS ERP">
                </div>

                {{-- Versão --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-code-branch mr-1"></i>Versão
                    </label>
                    <input type="text" wire:model="app_version" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="5.0.0">
                </div>

                {{-- URL --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-link mr-1"></i>URL da Aplicação *
                    </label>
                    <input type="url" wire:model="app_url" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="https://soserp.com">
                </div>

                {{-- Email de Contato --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-1"></i>Email de Contato *
                    </label>
                    <input type="email" wire:model="contact_email" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="contato@soserp.com">
                </div>

                {{-- Telefone --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-phone mr-1"></i>Telefone de Contato
                    </label>
                    <input type="text" wire:model="contact_phone" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="+244 999 999 999">
                </div>

                {{-- Descrição --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-align-left mr-1"></i>Descrição da Aplicação
                    </label>
                    <textarea wire:model="app_description" rows="4"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                              placeholder="Sistema ERP completo para gestão empresarial..."></textarea>
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="saveGeneral" 
                        wire:loading.attr="disabled"
                        class="px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-bold shadow-lg transition">
                    <span wire:loading.remove wire:target="saveGeneral">
                        <i class="fas fa-save mr-2"></i>Salvar Configurações
                    </span>
                    <span wire:loading wire:target="saveGeneral">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </div>
        @endif

        {{-- TAB: APARÊNCIA --}}
        @if($activeTab === 'appearance')
        <div class="space-y-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-palette mr-3 text-purple-600"></i>
                Aparência e Identidade Visual
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Logo --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-image mr-1"></i>Logo da Aplicação
                    </label>
                    @if($current_logo)
                    <div class="mb-3 p-4 bg-gray-50 rounded-xl border-2 border-gray-200">
                        <p class="text-xs text-gray-600 mb-2">Logo Atual:</p>
                        <img src="{{ Storage::url($current_logo) }}" alt="Logo" class="max-h-20">
                    </div>
                    @endif
                    <input type="file" wire:model="app_logo" accept="image/*"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500">
                    <p class="text-xs text-gray-500 mt-2">Formatos: PNG, JPG, SVG. Recomendado: 200x60px</p>
                    @if($app_logo)
                    <p class="text-xs text-green-600 mt-2">
                        <i class="fas fa-check-circle mr-1"></i>Novo arquivo selecionado: {{ $app_logo->getClientOriginalName() }}
                    </p>
                    @endif
                </div>

                {{-- Favicon --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-star mr-1"></i>Favicon
                    </label>
                    @if($current_favicon)
                    <div class="mb-3 p-4 bg-gray-50 rounded-xl border-2 border-gray-200">
                        <p class="text-xs text-gray-600 mb-2">Favicon Atual:</p>
                        <img src="{{ Storage::url($current_favicon) }}" alt="Favicon" class="max-h-10">
                    </div>
                    @endif
                    <input type="file" wire:model="app_favicon" accept="image/*"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500">
                    <p class="text-xs text-gray-500 mt-2">Formatos: ICO, PNG. Recomendado: 32x32px ou 64x64px</p>
                    @if($app_favicon)
                    <p class="text-xs text-green-600 mt-2">
                        <i class="fas fa-check-circle mr-1"></i>Novo arquivo selecionado: {{ $app_favicon->getClientOriginalName() }}
                    </p>
                    @endif
                </div>

                {{-- Cor Primária --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-fill-drip mr-1"></i>Cor Primária
                    </label>
                    <div class="flex gap-3">
                        <input type="color" wire:model.live="primary_color" 
                               class="w-20 h-12 rounded-xl border-2 border-gray-300 cursor-pointer">
                        <input type="text" wire:model="primary_color" 
                               class="flex-1 px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 font-mono"
                               placeholder="#4F46E5">
                    </div>
                    <div class="mt-2 p-3 rounded-lg" style="background-color: {{ $primary_color }}">
                        <p class="text-white text-xs font-bold">Preview da Cor Primária</p>
                    </div>
                </div>

                {{-- Cor Secundária --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-fill-drip mr-1"></i>Cor Secundária
                    </label>
                    <div class="flex gap-3">
                        <input type="color" wire:model.live="secondary_color" 
                               class="w-20 h-12 rounded-xl border-2 border-gray-300 cursor-pointer">
                        <input type="text" wire:model="secondary_color" 
                               class="flex-1 px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 font-mono"
                               placeholder="#06B6D4">
                    </div>
                    <div class="mt-2 p-3 rounded-lg" style="background-color: {{ $secondary_color }}">
                        <p class="text-white text-xs font-bold">Preview da Cor Secundária</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="saveAppearance" 
                        wire:loading.attr="disabled"
                        class="px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-bold shadow-lg transition">
                    <span wire:loading.remove wire:target="saveAppearance">
                        <i class="fas fa-save mr-2"></i>Salvar Aparência
                    </span>
                    <span wire:loading wire:target="saveAppearance">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </div>
        @endif

        {{-- TAB: SEO --}}
        @if($activeTab === 'seo')
        <div class="space-y-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-search mr-3 text-purple-600"></i>
                SEO e Meta Tags
            </h3>

            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Dica:</strong> Estas configurações aparecem nos resultados de busca do Google e quando o site é compartilhado em redes sociais.
                </p>
            </div>

            <div class="space-y-6">
                {{-- SEO Title --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-heading mr-1"></i>Título SEO *
                    </label>
                    <input type="text" wire:model="seo_title" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="SOS ERP - Sistema de Gestão Empresarial">
                    <p class="text-xs text-gray-500 mt-1">Recomendado: 50-60 caracteres</p>
                </div>

                {{-- SEO Description --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-align-left mr-1"></i>Descrição SEO *
                    </label>
                    <textarea wire:model="seo_description" rows="3"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                              placeholder="Sistema ERP completo para gestão empresarial em Angola..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">Recomendado: 150-160 caracteres</p>
                </div>

                {{-- SEO Keywords --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-tags mr-1"></i>Palavras-chave (separadas por vírgula)
                    </label>
                    <input type="text" wire:model="seo_keywords" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="ERP, Angola, Faturação, Gestão, Software">
                </div>

                {{-- SEO Author --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-user-edit mr-1"></i>Autor
                    </label>
                    <input type="text" wire:model="seo_author" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="SOS ERP Team">
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="saveSEO" 
                        wire:loading.attr="disabled"
                        class="px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-bold shadow-lg transition">
                    <span wire:loading.remove wire:target="saveSEO">
                        <i class="fas fa-save mr-2"></i>Salvar SEO
                    </span>
                    <span wire:loading wire:target="saveSEO">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </div>
        @endif

        {{-- TAB: FUNCIONALIDADES --}}
        @if($activeTab === 'features')
        <div class="space-y-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-toggle-on mr-3 text-purple-600"></i>
                Funcionalidades do Sistema
            </h3>

            <div class="space-y-4">
                {{-- Registro --}}
                <div class="bg-gray-50 rounded-xl p-4 border-2 border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="font-bold text-gray-800">
                                <i class="fas fa-user-plus mr-2 text-blue-600"></i>
                                Permitir Registro de Novos Usuários
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                Habilitar formulário de registro público na página de login
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="enable_registration" class="sr-only peer">
                            <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                </div>

                {{-- Verificação de Email --}}
                <div class="bg-gray-50 rounded-xl p-4 border-2 border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="font-bold text-gray-800">
                                <i class="fas fa-envelope-open-text mr-2 text-green-600"></i>
                                Verificação de Email Obrigatória
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                Exigir verificação de email antes de acessar o sistema
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="enable_email_verification" class="sr-only peer">
                            <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                </div>

                {{-- Modo Manutenção --}}
                <div class="bg-yellow-50 rounded-xl p-4 border-2 border-yellow-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="font-bold text-gray-800">
                                <i class="fas fa-tools mr-2 text-orange-600"></i>
                                Modo de Manutenção
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                Desabilitar acesso ao sistema (exceto Super Admin)
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="maintenance_mode" class="sr-only peer">
                            <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-orange-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="saveFeatures" 
                        wire:loading.attr="disabled"
                        class="px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-bold shadow-lg transition">
                    <span wire:loading.remove wire:target="saveFeatures">
                        <i class="fas fa-save mr-2"></i>Salvar Funcionalidades
                    </span>
                    <span wire:loading wire:target="saveFeatures">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </div>
        @endif

        {{-- TAB: REDES SOCIAIS --}}
        @if($activeTab === 'social')
        <div class="space-y-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-share-alt mr-3 text-purple-600"></i>
                Redes Sociais e Links
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Facebook --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fab fa-facebook text-blue-600 mr-1"></i>Facebook
                    </label>
                    <input type="url" wire:model="facebook_url" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="https://facebook.com/soserp">
                </div>

                {{-- Instagram --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fab fa-instagram text-pink-600 mr-1"></i>Instagram
                    </label>
                    <input type="url" wire:model="instagram_url" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="https://instagram.com/soserp">
                </div>

                {{-- Twitter --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fab fa-twitter text-blue-400 mr-1"></i>Twitter / X
                    </label>
                    <input type="url" wire:model="twitter_url" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="https://twitter.com/soserp">
                </div>

                {{-- LinkedIn --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fab fa-linkedin text-blue-700 mr-1"></i>LinkedIn
                    </label>
                    <input type="url" wire:model="linkedin_url" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="https://linkedin.com/company/soserp">
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="saveSocial" 
                        wire:loading.attr="disabled"
                        class="px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-bold shadow-lg transition">
                    <span wire:loading.remove wire:target="saveSocial">
                        <i class="fas fa-save mr-2"></i>Salvar Redes Sociais
                    </span>
                    <span wire:loading wire:target="saveSocial">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </div>
        @endif

        {{-- TAB: SCHEMA.ORG --}}
        @if($activeTab === 'schema')
        <div class="space-y-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-code mr-3 text-purple-600"></i>
                Schema.org (JSON-LD)
            </h3>

            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Importante:</strong> Estas configurações são usadas para gerar o JSON-LD na landing page, melhorando o SEO e a exibição nos resultados de busca.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- App Name --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-tag mr-1"></i>Nome da Aplicação (Schema)
                    </label>
                    <input type="text" wire:model="schema_app_name" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="SOSERP">
                    <p class="text-xs text-gray-500 mt-1">Nome exibido no schema JSON-LD</p>
                </div>

                {{-- App URL --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-link mr-1"></i>URL da Aplicação (Schema)
                    </label>
                    <input type="url" wire:model="schema_app_url" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="https://soserp.vip">
                    <p class="text-xs text-gray-500 mt-1">URL canônica do site</p>
                </div>

                {{-- App Category --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-folder mr-1"></i>Categoria da Aplicação
                    </label>
                    <select wire:model="schema_app_category" 
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500">
                        <option value="BusinessApplication">BusinessApplication</option>
                        <option value="WebApplication">WebApplication</option>
                        <option value="MobileApplication">MobileApplication</option>
                        <option value="SoftwareApplication">SoftwareApplication</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Tipo de aplicação para schema.org</p>
                </div>

                {{-- Price --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-dollar-sign mr-1"></i>Preço Inicial
                    </label>
                    <input type="text" wire:model="schema_price" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="0">
                    <p class="text-xs text-gray-500 mt-1">Use "0" para grátis</p>
                </div>

                {{-- Currency --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-money-bill mr-1"></i>Moeda
                    </label>
                    <select wire:model="schema_currency" 
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500">
                        <option value="AOA">AOA (Kwanza Angolano)</option>
                        <option value="USD">USD (Dólar Americano)</option>
                        <option value="EUR">EUR (Euro)</option>
                        <option value="BRL">BRL (Real Brasileiro)</option>
                    </select>
                </div>

                {{-- Region --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1"></i>Região/País
                    </label>
                    <input type="text" wire:model="schema_region" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="Angola">
                    <p class="text-xs text-gray-500 mt-1">Região elegível para oferta</p>
                </div>

                {{-- Rating Value --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-star mr-1"></i>Avaliação (Rating)
                    </label>
                    <input type="number" step="0.1" min="0" max="5" wire:model="schema_rating_value" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="4.8">
                    <p class="text-xs text-gray-500 mt-1">Nota média (0-5)</p>
                </div>

                {{-- Review Count --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-comment mr-1"></i>Número de Avaliações
                    </label>
                    <input type="number" wire:model="schema_review_count" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="150">
                    <p class="text-xs text-gray-500 mt-1">Total de reviews/avaliações</p>
                </div>

                {{-- Creator Name --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-building mr-1"></i>Nome do Criador/Organização
                    </label>
                    <input type="text" wire:model="schema_creator_name" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="SOSERP">
                    <p class="text-xs text-gray-500 mt-1">Nome da organização criadora</p>
                </div>

                {{-- Creator URL --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-external-link-alt mr-1"></i>URL do Criador
                    </label>
                    <input type="url" wire:model="schema_creator_url" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                           placeholder="https://soserp.vip">
                    <p class="text-xs text-gray-500 mt-1">Website da organização</p>
                </div>

                {{-- App Description (full width) --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-align-left mr-1"></i>Descrição da Aplicação (Schema)
                    </label>
                    <textarea wire:model="schema_app_description" rows="4"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500"
                              placeholder="Sistema de Gestão Empresarial Multi-Tenant para empresas em Angola..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">Descrição completa para JSON-LD</p>
                </div>
            </div>

            {{-- Preview JSON-LD --}}
            <div class="bg-gray-900 rounded-xl p-4 mt-6">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-white font-bold">
                        <i class="fas fa-code mr-2"></i>Preview JSON-LD
                    </p>
                    <span class="text-xs text-gray-400">Este código será inserido no &lt;head&gt; da landing page</span>
                </div>
                <pre class="text-xs text-green-400 overflow-x-auto"><code>{
  "@@context": "https://schema.org",
  "@@type": "SoftwareApplication",
  "name": "{{ $schema_app_name }}",
  "description": "{{ $schema_app_description }}",
  "url": "{{ $schema_app_url }}",
  "applicationCategory": "{{ $schema_app_category }}",
  "offers": {
    "@@type": "Offer",
    "price": "{{ $schema_price }}",
    "priceCurrency": "{{ $schema_currency }}",
    "eligibleRegion": {
      "@@type": "Place",
      "name": "{{ $schema_region }}"
    }
  },
  "aggregateRating": {
    "@@type": "AggregateRating",
    "ratingValue": "{{ $schema_rating_value }}",
    "reviewCount": "{{ $schema_review_count }}"
  },
  "creator": {
    "@@type": "Organization",
    "name": "{{ $schema_creator_name }}",
    "url": "{{ $schema_creator_url }}"
  }
}</code></pre>
            </div>

            <div class="flex justify-end">
                <button wire:click="saveSchema" 
                        wire:loading.attr="disabled"
                        class="px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-bold shadow-lg transition">
                    <span wire:loading.remove wire:target="saveSchema">
                        <i class="fas fa-save mr-2"></i>Salvar Schema.org
                    </span>
                    <span wire:loading wire:target="saveSchema">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </div>
        @endif
        
    </div>
</div>

