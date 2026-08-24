<div class="min-h-screen bg-slate-100 py-10 px-4"
     style="background-image:radial-gradient(at 20% 0%, #e0e7ff 0px, transparent 55%), radial-gradient(at 90% 10%, #dbeafe 0px, transparent 45%);">
    <div class="w-full max-w-3xl mx-auto">

        {{-- Marca + passos --}}
        <div class="text-center mb-8">
            <img src="/brand/soserp-logo.png" alt="SOSERP" class="h-12 mx-auto mb-4"
                 onerror="this.style.display='none'">
            <h1 class="text-3xl font-bold text-slate-900">Bem-vindo ao SOSERP</h1>
            <p class="text-slate-500 mt-2">Falta um passo: criar a sua empresa e o utilizador administrador.</p>

            <div class="flex items-center justify-center gap-2 mt-6 text-xs font-semibold">
                <span class="flex items-center gap-2 text-emerald-600">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center">
                        <i class="fas fa-check text-[10px]"></i>
                    </span>Licença
                </span>
                <span class="w-10 h-px bg-slate-300"></span>
                <span class="flex items-center gap-2 text-blue-600">
                    <span class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center">2</span>
                    Empresa
                </span>
                <span class="w-10 h-px bg-slate-300"></span>
                <span class="flex items-center gap-2 text-slate-400">
                    <span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center">3</span>
                    Entrar
                </span>
            </div>
        </div>

        <form wire:submit="finalizar" class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            @if ($errors->any())
                <div class="bg-red-50 border-b border-red-200 px-8 py-4">
                    <p class="text-sm font-semibold text-red-800 flex items-center">
                        <i class="fas fa-circle-exclamation mr-2"></i>
                        {{ $errors->first() }}
                    </p>
                </div>
            @endif

            {{-- Empresa --}}
            <div class="px-8 pt-8 pb-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-slate-900">A sua empresa</h2>
                        <p class="text-xs text-slate-500">Alguns dados vieram da licença — reveja e complete.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nome da empresa <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="empresa"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        @error('empresa') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">NIF</label>
                        <input type="text" wire:model="nif"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        @error('nif') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Regime fiscal <span class="text-red-500">*</span></label>
                        <select wire:model="regime"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="regime_geral">Regime Geral</option>
                            <option value="regime_simplificado">Regime Simplificado</option>
                            <option value="regime_nao_sujeicao">Não Sujeição</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Decide os impostos com que a empresa nasce.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Telefone</label>
                        <input type="text" wire:model="telefone"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Email da empresa</label>
                        <input type="email" wire:model="emailEmpresa"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        @error('emailEmpresa') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Endereço</label>
                        <input type="text" wire:model="endereco"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                </div>

                @if($plano)
                    <div class="mt-4 inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 rounded-full px-3 py-1.5 text-xs font-semibold">
                        <i class="fas fa-certificate"></i>Plano da licença: {{ $plano }}
                    </div>
                @endif
            </div>

            <div class="border-t border-slate-100"></div>

            {{-- Administrador --}}
            <div class="px-8 pt-6 pb-8">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-slate-900">Utilizador administrador</h2>
                        <p class="text-xs text-slate-500">É com esta conta que vai entrar no sistema.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nome <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="adminNome"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                        @error('adminNome') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Email (login) <span class="text-red-500">*</span></label>
                        <input type="email" wire:model="adminEmail"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                        @error('adminEmail') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="adminPassword"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                        @error('adminPassword') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-xs text-slate-400 mt-1">Mínimo 8 caracteres.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirmar password <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="adminPassword_confirmation"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 border-t border-slate-200 px-8 py-5 flex items-center justify-between">
                <p class="text-xs text-slate-500 hidden sm:block">
                    <i class="fas fa-lock mr-1"></i>Os dados ficam nesta máquina.
                </p>
                <button type="submit" wire:loading.attr="disabled"
                        class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-semibold shadow-lg shadow-blue-600/25 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="finalizar">
                        Criar empresa e entrar <i class="fas fa-arrow-right ml-1"></i>
                    </span>
                    <span wire:loading wire:target="finalizar">
                        <i class="fas fa-spinner fa-spin mr-1"></i>A criar...
                    </span>
                </button>
            </div>
        </form>

        <p class="text-center text-xs text-slate-400 mt-6">SOSERP · Softec Angola</p>
    </div>
</div>
