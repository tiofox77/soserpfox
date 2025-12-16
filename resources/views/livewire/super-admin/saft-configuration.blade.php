<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-key mr-3 text-purple-600"></i>
                    Configuração SAFT-AO
                </h2>
                <p class="text-gray-600 mt-1">Gerencie as chaves de assinatura digital conforme regulamento AGT Angola</p>
            </div>
        </div>
    </div>

    {{-- Status das Chaves --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {{-- Chave Pública --}}
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 {{ $publicKeyExists ? 'border-green-200' : 'border-gray-200' }}">
            <div class="bg-gradient-to-r {{ $publicKeyExists ? 'from-green-600 to-emerald-600' : 'from-gray-600 to-gray-700' }} px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <i class="fas fa-lock-open mr-2"></i>
                    Chave Pública
                </h3>
            </div>
            <div class="p-6">
                @if($publicKeyExists)
                    <div class="mb-4">
                        <div class="flex items-center text-green-600 mb-3">
                            <i class="fas fa-check-circle text-2xl mr-3"></i>
                            <span class="font-bold">Chave Gerada</span>
                        </div>
                        <div class="text-sm text-gray-600 space-y-2">
                            <p><strong>Data:</strong> {{ date('d/m/Y H:i', $publicKeyDate) }}</p>
                            <p><strong>Algoritmo:</strong> RSA-2048</p>
                            <p><strong>Hash:</strong> SHA-256</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg mb-4">
                        <p class="text-xs font-mono break-all text-gray-600">
                            {{ substr($publicKeyContent, 0, 100) }}...
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <button wire:click="downloadPublicKey" 
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition text-sm">
                            <i class="fas fa-download mr-1"></i>PEM
                        </button>
                        <button wire:click="downloadPublicKeyTxt" 
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold transition text-sm">
                            <i class="fas fa-file-alt mr-1"></i>TXT
                        </button>
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 font-semibold">Chave não gerada</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Chave Privada --}}
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 {{ $privateKeyExists ? 'border-red-200' : 'border-gray-200' }}">
            <div class="bg-gradient-to-r {{ $privateKeyExists ? 'from-red-600 to-rose-600' : 'from-gray-600 to-gray-700' }} px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <i class="fas fa-lock mr-2"></i>
                    Chave Privada
                </h3>
            </div>
            <div class="p-6">
                @if($privateKeyExists)
                    <div class="mb-4">
                        <div class="flex items-center text-red-600 mb-3">
                            <i class="fas fa-shield-alt text-2xl mr-3"></i>
                            <span class="font-bold">Chave Protegida</span>
                        </div>
                        <div class="text-sm text-gray-600 space-y-2">
                            <p><strong>Data:</strong> {{ date('d/m/Y H:i', $privateKeyDate) }}</p>
                            <p><strong>Algoritmo:</strong> RSA-2048</p>
                            <p><strong>Status:</strong> Armazenada com segurança</p>
                        </div>
                    </div>
                    
                    <div class="bg-red-50 border-2 border-red-200 p-3 rounded-lg mb-4">
                        <p class="text-xs text-red-700 flex items-start">
                            <i class="fas fa-exclamation-circle mr-2 mt-0.5"></i>
                            <span>Esta chave é confidencial e deve ser mantida em segurança absoluta</span>
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <button wire:click="downloadPrivateKey" 
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition text-sm">
                            <i class="fas fa-download mr-1"></i>PEM
                        </button>
                        <button wire:click="downloadPrivateKeyTxt" 
                                class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-semibold transition text-sm">
                            <i class="fas fa-file-alt mr-1"></i>TXT
                        </button>
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 font-semibold">Chave não gerada</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Download Completo --}}
    @if($publicKeyExists && $privateKeyExists)
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6">
        <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4">
            <h3 class="text-white font-bold text-lg flex items-center">
                <i class="fas fa-file-download mr-2"></i>
                Download Completo
            </h3>
        </div>
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h4 class="font-bold text-gray-900 mb-2">Baixar Ambas as Chaves em TXT</h4>
                    <p class="text-sm text-gray-600 mb-2">
                        Download de arquivo único contendo chave pública + privada com metadados completos
                    </p>
                    <div class="flex items-center text-xs text-gray-500 space-x-4">
                        <span><i class="fas fa-check text-green-600 mr-1"></i>Metadados inclusos</span>
                        <span><i class="fas fa-check text-green-600 mr-1"></i>Formato legível</span>
                        <span><i class="fas fa-shield-alt text-red-600 mr-1"></i>Mantenha seguro</span>
                    </div>
                </div>
                <button wire:click="downloadBothKeysTxt" 
                        class="ml-4 px-6 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white rounded-xl font-semibold transition shadow-lg flex items-center">
                    <i class="fas fa-download mr-2"></i>Download TXT Completo
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Ações --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
            <h3 class="text-white font-bold text-lg flex items-center">
                <i class="fas fa-cogs mr-2"></i>
                Ações
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @if(!$publicKeyExists || !$privateKeyExists)
                    {{-- Gerar Novas Chaves --}}
                    <div class="p-6 bg-blue-50 border-2 border-blue-200 rounded-xl">
                        <div class="flex items-start mb-4">
                            <i class="fas fa-plus-circle text-blue-600 text-3xl mr-4"></i>
                            <div>
                                <h4 class="font-bold text-gray-900 mb-2">Gerar Chaves SAFT-AO</h4>
                                <p class="text-sm text-gray-600 mb-4">
                                    Gera um par de chaves RSA-2048 conforme regulamento AGT Angola para assinatura de documentos fiscais.
                                </p>
                                <ul class="text-xs text-gray-600 space-y-1 mb-4">
                                    <li>✓ Algoritmo: RSA-2048 bits</li>
                                    <li>✓ Hash: SHA-256</li>
                                    <li>✓ Conformidade: SAFT-AO</li>
                                </ul>
                            </div>
                        </div>
                        <button wire:click="generateKeys" 
                                class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition shadow-lg">
                            <i class="fas fa-key mr-2"></i>Gerar Chaves
                        </button>
                    </div>
                @else
                    {{-- Regenerar Chaves --}}
                    <div class="p-6 bg-orange-50 border-2 border-orange-200 rounded-xl">
                        <div class="flex items-start mb-4">
                            <i class="fas fa-sync-alt text-orange-600 text-3xl mr-4"></i>
                            <div>
                                <h4 class="font-bold text-gray-900 mb-2">Regenerar Chaves</h4>
                                <p class="text-sm text-gray-600 mb-4">
                                    Gera um novo par de chaves. As chaves antigas serão arquivadas automaticamente.
                                </p>
                                <div class="bg-orange-100 border border-orange-300 rounded-lg p-3 mb-4">
                                    <p class="text-xs text-orange-800 flex items-start">
                                        <i class="fas fa-exclamation-triangle mr-2 mt-0.5"></i>
                                        <span><strong>Atenção:</strong> Todos os documentos assinados com as chaves antigas precisarão ser reassinados!</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <button wire:click="regenerateKeys" 
                                wire:confirm="Atenção! Regenerar as chaves invalidará todos os documentos assinados. Deseja continuar?"
                                class="w-full px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white rounded-xl font-semibold transition shadow-lg">
                            <i class="fas fa-redo mr-2"></i>Regenerar Chaves
                        </button>
                    </div>
                @endif
                
                {{-- Informações SAFT-AO --}}
                <div class="p-6 bg-purple-50 border-2 border-purple-200 rounded-xl">
                    <div class="flex items-start mb-4">
                        <i class="fas fa-info-circle text-purple-600 text-3xl mr-4"></i>
                        <div>
                            <h4 class="font-bold text-gray-900 mb-2">Sobre SAFT-AO</h4>
                            <p class="text-sm text-gray-600 mb-4">
                                Standard Audit File for Tax - Angola (SAFT-AO) é o formato padrão de arquivo de auditoria fiscal.
                            </p>
                            <ul class="text-xs text-gray-600 space-y-2">
                                <li class="flex items-start">
                                    <i class="fas fa-check text-purple-600 mr-2 mt-0.5"></i>
                                    <span>Chaves armazenadas em: <code class="bg-gray-200 px-1 rounded">storage/app/saft/</code></span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-purple-600 mr-2 mt-0.5"></i>
                                    <span>Backup automático das chaves antigas</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-purple-600 mr-2 mt-0.5"></i>
                                    <span>Hash exibido nos documentos fiscais</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-purple-600 mr-2 mt-0.5"></i>
                                    <span>Conformidade com AGT Angola</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Toastr Notifications --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('notify', (event) => {
                const data = event[0] || event;
                const type = data.type || 'info';
                const message = data.message || 'Ação realizada';
                
                if (typeof toastr !== 'undefined') {
                    toastr.options = {
                        "closeButton": true,
                        "progressBar": true,
                        "positionClass": "toast-top-right",
                        "timeOut": "5000"
                    };
                    
                    switch(type) {
                        case 'success':
                            toastr.success(message, 'Sucesso');
                            break;
                        case 'error':
                            toastr.error(message, 'Erro');
                            break;
                        case 'warning':
                            toastr.warning(message, 'Atenção');
                            break;
                        case 'info':
                            toastr.info(message, 'Info');
                            break;
                    }
                }
            });
        });
    </script>
</div>
