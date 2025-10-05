<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - {{ app_name() }}</title>
    @if(app_favicon())
    <link rel="icon" type="image/x-icon" href="{{ app_favicon() }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="{{ route('landing.home') }}" class="inline-flex items-center justify-center mb-4">
                @if(app_logo())
                    <img src="{{ app_logo() }}" alt="{{ app_name() }}" class="h-16 w-auto">
                @else
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-chart-line text-white text-2xl"></i>
                    </div>
                    <span class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">{{ app_name() }}</span>
                @endif
            </a>
        </div>

        <!-- Register Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900 text-center">Criar Conta</h2>
                <p class="text-gray-600 text-center mt-2">Comece grátis por 14 dias</p>
            </div>

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <!-- Name -->
                <div class="mb-6">
                    <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user text-blue-500 mr-2"></i>Nome Completo
                    </label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('name') border-red-500 @enderror"
                           placeholder="João Silva">
                    @error('name')
                        <p class="mt-2 text-sm text-red-600 flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Email -->
                <div class="mb-6">
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-envelope text-blue-500 mr-2"></i>Email
                    </label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('email') border-red-500 @enderror"
                           placeholder="joao@empresa.ao">
                    @error('email')
                        <p class="mt-2 text-sm text-red-600 flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-blue-500 mr-2"></i>Senha
                    </label>
                    <input id="password" type="password" name="password" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('password') border-red-500 @enderror"
                           placeholder="Mínimo 8 caracteres">
                    @error('password')
                        <p class="mt-2 text-sm text-red-600 flex items-center">
                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="mb-6">
                    <label for="password-confirm" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-blue-500 mr-2"></i>Confirmar Senha
                    </label>
                    <input id="password-confirm" type="password" name="password_confirmation" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                           placeholder="Digite a senha novamente">
                </div>

                <!-- Terms -->
                <div class="mb-6">
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" required
                               class="w-4 h-4 mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-600">
                            Concordo com os <a href="#" class="text-blue-600 hover:text-blue-800 font-medium">Termos de Serviço</a> 
                            e <a href="#" class="text-blue-600 hover:text-blue-800 font-medium">Política de Privacidade</a>
                        </span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-3 rounded-xl transition duration-200 flex items-center justify-center shadow-lg hover:shadow-xl">
                    <i class="fas fa-rocket mr-2"></i>Criar Conta Grátis
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-4 bg-white text-gray-500">Já tem conta?</span>
                </div>
            </div>

            <!-- Login Link -->
            <a href="{{ route('login') }}" class="block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold py-3 rounded-xl transition">
                <i class="fas fa-sign-in-alt mr-2"></i>Entrar
            </a>

            <!-- Benefits -->
            <div class="mt-6 space-y-2">
                <p class="text-xs text-gray-600 flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    14 dias de teste grátis
                </p>
                <p class="text-xs text-gray-600 flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    Sem cartão de crédito necessário
                </p>
                <p class="text-xs text-gray-600 flex items-center">
                    <i class="fas fa-check text-green-500 mr-2"></i>
                    Suporte 24/7
                </p>
            </div>
        </div>

        <!-- Back to Landing -->
        <div class="text-center mt-6">
            <a href="{{ route('landing.home') }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Voltar para o site
            </a>
        </div>
    </div>

</body>
</html>
