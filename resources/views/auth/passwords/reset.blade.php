<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Definir Palavra-passe - {{ app_name() }}</title>
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

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="mb-8 text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-green-100 to-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lock text-2xl text-green-600"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-900">Definir nova palavra-passe</h2>
                <p class="text-gray-600 mt-2">Escolha uma palavra-passe segura para a sua conta.</p>
            </div>

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">E-mail</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus autocomplete="email"
                            class="w-full pl-10 pr-4 py-3 rounded-xl border @error('email') border-red-400 @else border-gray-200 @enderror focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition"
                            placeholder="seu@email.com">
                    </div>
                    @error('email')
                        <p class="mt-2 text-sm text-red-600 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i>{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Nova palavra-passe</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input id="password" type="password" name="password" required autocomplete="new-password"
                            class="w-full pl-10 pr-4 py-3 rounded-xl border @error('password') border-red-400 @else border-gray-200 @enderror focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition"
                            placeholder="Mínimo 8 caracteres">
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i>{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Confirm -->
                <div>
                    <label for="password-confirm" class="block text-sm font-semibold text-gray-700 mb-2">Confirmar palavra-passe</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input id="password-confirm" type="password" name="password_confirmation" required autocomplete="new-password"
                            class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition"
                            placeholder="Repita a palavra-passe">
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit"
                    class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold hover:shadow-xl transition flex items-center justify-center gap-2">
                    <i class="fas fa-check"></i>
                    Redefinir palavra-passe
                </button>
            </form>

            <!-- Back to login -->
            <div class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-blue-600 transition inline-flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i>Voltar ao login
                </a>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-xs text-gray-500 mt-8">
            &copy; {{ date('Y') }} {{ app_name() }}. Todos os direitos reservados.
        </p>
    </div>

</body>
</html>
