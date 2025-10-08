<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convite Já Aceito - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-green-50 via-blue-50 to-purple-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
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

        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-6 text-center">
                <i class="fas fa-check-circle text-5xl mb-3"></i>
                <h2 class="text-2xl font-bold">Convite Já Aceito</h2>
            </div>

            <div class="p-6 text-center">
                <p class="text-gray-600 mb-4">
                    Este convite já foi aceito em {{ $invitation->accepted_at->format('d/m/Y') }}.
                </p>

                <div class="bg-green-50 border-2 border-green-200 rounded-xl p-4 mb-6 text-left">
                    <p class="text-sm text-gray-700 mb-2">
                        <strong>Email:</strong> {{ $invitation->email }}
                    </p>
                    <p class="text-sm text-gray-700">
                        <strong>Empresa:</strong> {{ $invitation->tenant->name }}
                    </p>
                </div>

                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 text-left text-sm text-blue-800 mb-6">
                    <p class="font-semibold mb-1">✅ Conta já criada!</p>
                    <p>Se você esqueceu sua senha, use a opção "Esqueci minha senha" na tela de login.</p>
                </div>

                <a href="{{ route('login') }}" 
                   class="inline-block px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Fazer Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
