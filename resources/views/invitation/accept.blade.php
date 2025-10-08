<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceitar Convite - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            @if(app_logo())
                <img src="{{ app_logo() }}" alt="{{ config('app.name') }}" class="h-16 mx-auto mb-4">
            @else
                <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-crown text-white text-3xl"></i>
                </div>
            @endif
            <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                {{ config('app.name') }}
            </h1>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 text-center">
                <i class="fas fa-user-check text-5xl mb-3"></i>
                <h2 class="text-2xl font-bold">Você Foi Convidado!</h2>
                <p class="text-sm text-blue-100 mt-2">Complete seu cadastro para aceitar</p>
            </div>

            <!-- Body -->
            <div class="p-6">
                @if(session('error'))
                    <div class="mb-4 bg-red-50 border-2 border-red-500 rounded-xl p-4 text-red-800 flex items-center">
                        <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <!-- Info do Convite -->
                <div class="bg-gradient-to-r from-blue-50 to-purple-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center">
                            <i class="fas fa-user w-6 text-blue-600"></i>
                            <span class="text-gray-700"><strong>Nome:</strong> {{ $invitation->name }}</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-envelope w-6 text-blue-600"></i>
                            <span class="text-gray-700"><strong>Email:</strong> {{ $invitation->email }}</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-building w-6 text-blue-600"></i>
                            <span class="text-gray-700"><strong>Empresa:</strong> {{ $invitation->tenant->name }}</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-user-tag w-6 text-blue-600"></i>
                            <span class="text-gray-700"><strong>Convidado por:</strong> {{ $invitation->invitedBy->name }}</span>
                        </div>
                        @if($invitation->role)
                            <div class="flex items-center">
                                <i class="fas fa-id-badge w-6 text-blue-600"></i>
                                <span class="text-gray-700"><strong>Função:</strong> {{ $invitation->role }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Formulário -->
                <form method="POST" action="{{ route('invitation.accept.post', $invitation->token) }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Criar Senha *
                        </label>
                        <input type="password" 
                               name="password" 
                               required
                               minlength="6"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Mínimo 6 caracteres">
                        @error('password')
                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Confirmar Senha *
                        </label>
                        <input type="password" 
                               name="password_confirmation" 
                               required
                               minlength="6"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Digite a senha novamente">
                    </div>

                    <button type="submit" 
                            class="w-full py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-lg font-bold shadow-lg hover:shadow-xl transition">
                        <i class="fas fa-check-circle mr-2"></i>
                        Aceitar Convite e Criar Conta
                    </button>
                </form>

                <!-- Info -->
                <div class="mt-6 text-center text-xs text-gray-500">
                    <p>Ao aceitar, você concorda com os termos de uso</p>
                    <p class="mt-1">Este convite expira em {{ $invitation->expires_at->diffForHumans() }}</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
