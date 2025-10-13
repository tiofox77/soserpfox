@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 px-4 py-12">
    <div class="max-w-2xl w-full">
        <!-- Card Principal -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header com Gradiente -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-12 text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 backdrop-blur-sm rounded-full mb-6 animate-bounce">
                    <i class="fas fa-tools text-5xl text-white"></i>
                </div>
                <h1 class="text-4xl font-bold text-white mb-3">
                    🚧 Em Construção
                </h1>
                <p class="text-blue-100 text-lg">
                    Estamos a trabalhar neste módulo para si
                </p>
            </div>

            <!-- Corpo do Card -->
            <div class="px-8 py-10">
                <!-- Módulo Info -->
                <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl p-6 mb-8 border-l-4 border-indigo-500">
                    <div class="flex items-center gap-3 mb-2">
                        <i class="fas fa-cube text-2xl text-indigo-600"></i>
                        <h2 class="text-2xl font-bold text-gray-800">
                            {{ $module }}
                        </h2>
                    </div>
                    <p class="text-gray-600 leading-relaxed">
                        Este módulo ainda não foi implementado, mas está no nosso plano de desenvolvimento.
                    </p>
                </div>

                <!-- Features Planejadas -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-list-check text-indigo-600"></i>
                        Funcionalidades Planeadas
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div>
                                <p class="font-medium text-gray-800">Interface Intuitiva</p>
                                <p class="text-sm text-gray-600">Design moderno e fácil de usar</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div>
                                <p class="font-medium text-gray-800">Automação de Processos</p>
                                <p class="text-sm text-gray-600">Redução de trabalho manual</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div>
                                <p class="font-medium text-gray-800">Relatórios Detalhados</p>
                                <p class="text-sm text-gray-600">Análises e insights em tempo real</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div>
                                <p class="font-medium text-gray-800">Integração Completa</p>
                                <p class="text-sm text-gray-600">Sincronização com outros módulos</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline Estimado -->
                <div class="bg-amber-50 border-l-4 border-amber-400 p-6 rounded-lg mb-8">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-calendar-alt text-2xl text-amber-600 mt-1"></i>
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2">Próximas Atualizações</h4>
                            <p class="text-gray-700 mb-3">
                                Estamos a desenvolver este módulo e será lançado em breve. 
                                Fique atento às notificações do sistema!
                            </p>
                            <div class="flex items-center gap-2 text-sm text-amber-700">
                                <i class="fas fa-clock"></i>
                                <span class="font-medium">Previsão: Próximas semanas</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ações -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="{{ route('home') }}" 
                       class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all transform hover:scale-105 shadow-lg">
                        <i class="fas fa-home"></i>
                        Voltar ao Início
                    </a>
                    <button onclick="alert('Em breve terá uma área para sugerir funcionalidades!')" 
                            class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-white text-indigo-600 font-semibold rounded-lg border-2 border-indigo-600 hover:bg-indigo-50 transition-all">
                        <i class="fas fa-lightbulb"></i>
                        Sugerir Funcionalidade
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-6 border-t border-gray-200">
                <div class="flex items-center justify-center gap-2 text-sm text-gray-600">
                    <i class="fas fa-info-circle text-blue-500"></i>
                    <span>
                        Tem dúvidas? Entre em contacto com o suporte:
                        <a href="mailto:suporte@soserp.com" class="text-indigo-600 hover:underline font-medium">
                            suporte@soserp.com
                        </a>
                    </span>
                </div>
            </div>
        </div>

        <!-- Cards de Status -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <div class="bg-white rounded-lg p-4 shadow-md text-center">
                <div class="text-3xl font-bold text-indigo-600 mb-1">9</div>
                <div class="text-sm text-gray-600">Módulos Disponíveis</div>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-md text-center">
                <div class="text-3xl font-bold text-green-600 mb-1">24/7</div>
                <div class="text-sm text-gray-600">Suporte Disponível</div>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-md text-center">
                <div class="text-3xl font-bold text-blue-600 mb-1">100%</div>
                <div class="text-sm text-gray-600">Segurança de Dados</div>
            </div>
        </div>
    </div>
</div>

<!-- Animação de Loading Sutil -->
<style>
    @keyframes pulse-glow {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.7;
        }
    }
    
    .animate-bounce {
        animation: bounce 2s infinite;
    }
    
    @keyframes bounce {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-10px);
        }
    }
</style>
@endsection
