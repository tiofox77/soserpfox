<div class="min-h-screen bg-gray-50 flex items-center justify-center py-10 px-4">
    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl border border-gray-100 p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuração inicial</h1>
            <p class="text-sm text-gray-500">Crie a sua empresa e o utilizador administrador. Alguns dados vêm da licença — reveja e complete.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                Corrija os campos assinalados abaixo.
            </div>
        @endif

        <form wire:submit="finalizar" class="space-y-6">
            {{-- Empresa --}}
            <div>
                <h2 class="text-sm font-bold text-gray-700 uppercase mb-3">Empresa</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Nome da empresa *</label>
                        <input type="text" wire:model="empresa" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('empresa') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">NIF</label>
                        <input type="text" wire:model="nif" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('nif') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Regime fiscal *</label>
                        <select wire:model="regime" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="regime_geral">Regime Geral</option>
                            <option value="regime_simplificado">Regime Simplificado</option>
                            <option value="regime_nao_sujeicao">Não Sujeição</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Telefone</label>
                        <input type="text" wire:model="telefone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Email da empresa</label>
                        <input type="email" wire:model="emailEmpresa" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('emailEmpresa') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Endereço</label>
                        <input type="text" wire:model="endereco" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    @if($plano)
                        <div class="md:col-span-2 text-xs text-gray-500">Plano da licença: <span class="font-semibold text-gray-700">{{ $plano }}</span></div>
                    @endif
                </div>
            </div>

            {{-- Administrador --}}
            <div>
                <h2 class="text-sm font-bold text-gray-700 uppercase mb-3">Administrador</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Nome *</label>
                        <input type="text" wire:model="adminNome" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('adminNome') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Email (login) *</label>
                        <input type="email" wire:model="adminEmail" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('adminEmail') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Password *</label>
                        <input type="password" wire:model="adminPassword" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @error('adminPassword') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Confirmar password *</label>
                        <input type="password" wire:model="adminPassword_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" wire:loading.attr="disabled"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
                    <span wire:loading.remove wire:target="finalizar">Criar empresa e entrar</span>
                    <span wire:loading wire:target="finalizar">A criar...</span>
                </button>
            </div>
        </form>
    </div>
</div>
