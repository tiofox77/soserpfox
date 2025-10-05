<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Bloqueado - SOSERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-900 via-red-900 to-gray-900 min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-2xl w-full">
        <!-- Card Principal -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            
            <!-- Header com Ícone -->
            <div class="bg-gradient-to-r from-red-600 to-orange-600 p-8 text-center">
                <div class="w-24 h-24 mx-auto mb-4 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center animate-pulse">
                    <i class="fas fa-ban text-white text-5xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Acesso Bloqueado</h1>
                <p class="text-red-100 text-sm">Sua conta foi temporariamente desativada</p>
            </div>
            
            <!-- Body com Informações -->
            <div class="p-8">
                
                @if(session('tenant_deactivated'))
                    <!-- Informação do Tenant -->
                    <div class="mb-6 p-4 bg-red-50 border-2 border-red-200 rounded-xl">
                        <div class="flex items-start">
                            <i class="fas fa-building text-red-600 text-2xl mr-3 mt-1"></i>
                            <div class="flex-1">
                                <h3 class="font-bold text-red-900 mb-1">Empresa Desativada</h3>
                                <p class="text-red-800 text-sm">
                                    <strong>{{ session('tenant_name') }}</strong>
                                </p>
                                @if(session('deactivated_at'))
                                <p class="text-red-600 text-xs mt-1">
                                    <i class="fas fa-clock mr-1"></i>Desativado em: {{ session('deactivated_at') }}
                                </p>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <!-- Motivo da Desativação -->
                    @if(session('deactivation_reason'))
                    <div class="mb-6 p-4 bg-yellow-50 border-2 border-yellow-300 rounded-xl">
                        <div class="flex items-start">
                            <i class="fas fa-comment-dots text-yellow-600 text-2xl mr-3 mt-1"></i>
                            <div class="flex-1">
                                <h3 class="font-bold text-yellow-900 mb-2">Motivo da Desativação</h3>
                                <p class="text-yellow-800 text-sm leading-relaxed">
                                    {{ session('deactivation_reason') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif
                @endif
                
                <!-- O que aconteceu -->
                <div class="mb-6 p-4 bg-gray-50 border-2 border-gray-200 rounded-xl">
                    <h3 class="font-bold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                        O que aconteceu?
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start">
                            <i class="fas fa-times-circle text-red-600 mr-2 mt-0.5"></i>
                            <span>Sua empresa foi desativada pelo administrador do sistema</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-times-circle text-red-600 mr-2 mt-0.5"></i>
                            <span>Você foi desconectado automaticamente por segurança</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-times-circle text-red-600 mr-2 mt-0.5"></i>
                            <span>Todos os módulos e funcionalidades estão bloqueados</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-600 mr-2 mt-0.5"></i>
                            <span>Seus dados estão seguros e não foram excluídos</span>
                        </li>
                    </ul>
                </div>
                
                <!-- O que fazer -->
                <div class="mb-6 p-4 bg-blue-50 border-2 border-blue-200 rounded-xl">
                    <h3 class="font-bold text-blue-900 mb-3 flex items-center">
                        <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                        O que fazer agora?
                    </h3>
                    <ul class="space-y-2 text-sm text-blue-800">
                        <li class="flex items-start">
                            <i class="fas fa-phone text-blue-600 mr-2 mt-0.5"></i>
                            <span>Entre em contato com o administrador do sistema</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-envelope text-blue-600 mr-2 mt-0.5"></i>
                            <span>Envie um e-mail para suporte@soserp.ao</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-comments text-blue-600 mr-2 mt-0.5"></i>
                            <span>Resolva a situação descrita acima</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-600 mr-2 mt-0.5"></i>
                            <span>Após a reativação, você poderá acessar normalmente</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Botões de Ação -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('login') }}" 
                       class="flex-1 inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-indigo-700 shadow-lg hover:shadow-xl transition-all">
                        <i class="fas fa-sign-in-alt mr-2"></i>Voltar ao Login
                    </a>
                    <a href="mailto:suporte@soserp.ao" 
                       class="flex-1 inline-flex items-center justify-center px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition-all">
                        <i class="fas fa-envelope mr-2"></i>Contatar Suporte
                    </a>
                </div>
                
            </div>
        </div>
        
        <!-- Rodapé -->
        <div class="mt-6 text-center text-gray-400 text-sm">
            <p>© {{ date('Y') }} SOSERP - Sistema Operacional de Suporte Empresarial</p>
        </div>
    </div>
    
</body>
</html>
