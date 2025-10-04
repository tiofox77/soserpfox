<!-- Perfil Tab -->
<div class="bg-white rounded-2xl shadow-lg p-6">
    <div class="max-w-2xl mx-auto">
        <h2 class="text-xl font-bold text-gray-900 mb-6">
            <i class="fas fa-user-circle text-blue-500 mr-2"></i>
            Informações do Perfil
        </h2>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nome</label>
                <input type="text" value="{{ auth()->user()->name }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl" readonly>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                <input type="email" value="{{ auth()->user()->email }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl" readonly>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Último Login</label>
                <input type="text" value="{{ auth()->user()->last_login_at ? auth()->user()->last_login_at->format('d/m/Y H:i') : 'N/A' }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl" readonly>
            </div>

            <div class="pt-4">
                <button class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition">
                    <i class="fas fa-edit mr-2"></i>Editar Perfil
                </button>
            </div>
        </div>
    </div>
</div>
