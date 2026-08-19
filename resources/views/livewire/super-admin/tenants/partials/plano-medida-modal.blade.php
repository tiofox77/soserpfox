{{-- Montar um plano só para esta empresa.

     Escolhem-se os módulos, os limites e o preço; o plano é criado e
     atribuído de uma vez. Fica ACTIVO (para a subscrição funcionar) mas
     FORA DA MONTRA — não aparece na landing, no registo, nem aos outros
     clientes. --}}
@if($showMedidaModal)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showMedidaModal') }" x-show="show" x-cloak>
    <div class="flex items-start justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" wire:click="fecharPlanoAMedida"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8">

            <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-amber-500 to-orange-600 rounded-t-2xl">
                <div class="flex items-center gap-3 text-white">
                    <i class="fas fa-sliders text-xl"></i>
                    <div>
                        <h3 class="text-lg font-bold">Plano à medida</h3>
                        <p class="text-xs opacity-90">
                            {{ optional(\App\Models\Tenant::find($managingPlanTenantId))->name }}
                        </p>
                    </div>
                </div>
                <button wire:click="fecharPlanoAMedida" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
            </div>

            <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Nome do plano</label>
                    <input type="text" wire:model="medidaNome"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none">
                    @error('medidaNome') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                {{-- Módulos --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Módulos incluídos
                        <span class="font-normal text-gray-500">— as dependências entram sozinhas (quem leva Faturação leva Tesouraria)</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($modulosDisponiveis as $modulo)
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-amber-50 transition
                                          {{ in_array($modulo->slug, $medidaModulos ?? []) ? 'border-amber-400 bg-amber-50' : 'border-gray-200' }}">
                                <input type="checkbox" wire:model.live="medidaModulos" value="{{ $modulo->slug }}"
                                       class="rounded text-amber-600 focus:ring-amber-500">
                                <span class="text-sm text-gray-800 truncate">{{ $modulo->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('medidaModulos') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                {{-- Limites --}}
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Utilizadores</label>
                        <input type="number" min="1" wire:model="medidaUtilizadores"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none">
                        @error('medidaUtilizadores') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Empresas</label>
                        <input type="number" min="1" wire:model="medidaEmpresas"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Armazenamento (MB)</label>
                        <input type="number" min="100" step="100" wire:model="medidaArmazenamento"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none">
                    </div>
                </div>

                {{-- Preço --}}
                <div class="p-4 bg-gray-50 rounded-xl space-y-3">
                    <p class="text-sm font-bold text-gray-700">Quanto vai pagar</p>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Mensal (Kz)</label>
                            <input type="number" min="1" step="0.01" wire:model.live="medidaPrecoMensal"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-right font-bold focus:border-amber-500 focus:outline-none">
                            @error('medidaPrecoMensal') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">
                                Anual (Kz) <span class="font-normal text-gray-400">— em branco: 12×</span>
                            </label>
                            <input type="number" min="0" step="0.01" wire:model="medidaPrecoAnual"
                                   placeholder="{{ number_format($this->medidaAnualSugerido, 2) }}"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-right font-bold focus:border-amber-500 focus:outline-none">
                        </div>
                    </div>

                    <p class="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2">
                        <i class="fas fa-triangle-exclamation mr-1"></i>
                        A mensalidade tem de ser maior que zero. Um plano a zero é tratado em todo o
                        sistema como <strong>o plano gratuito</strong> e gasta a cortesia única do
                        cliente — para oferecer, ponha um valor simbólico e faça o desconto na cobrança.
                    </p>
                </div>

                {{-- Ciclo e teste --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Ciclo a aplicar agora</label>
                        <select wire:model="medidaCiclo"
                                class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none">
                            <option value="monthly">Mensal</option>
                            <option value="quarterly">Trimestral</option>
                            <option value="semiannual">Semestral</option>
                            <option value="yearly">Anual (12 + 2 meses de oferta)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Dias de teste</label>
                        <input type="number" min="0" max="365" wire:model="medidaDiasTeste"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none">
                        <p class="text-[11px] text-gray-400 mt-1">0 = entra a pagar (é negociado).</p>
                    </div>
                </div>

                <p class="text-[11px] text-gray-500">
                    O plano fica activo para esta empresa mas <strong>fora da montra</strong>: não aparece
                    na página de preços, no registo, nem aos outros clientes.
                </p>
            </div>

            <div class="flex gap-3 px-6 py-4 border-t border-gray-100">
                <button wire:click="fecharPlanoAMedida"
                        class="flex-1 py-2.5 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50">
                    Cancelar
                </button>
                <button wire:click="guardarPlanoAMedida" wire:loading.attr="disabled"
                        class="flex-1 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg disabled:opacity-60">
                    <span wire:loading.remove wire:target="guardarPlanoAMedida">
                        <i class="fas fa-check mr-1"></i>Criar e atribuir
                    </span>
                    <span wire:loading wire:target="guardarPlanoAMedida">
                        <i class="fas fa-spinner fa-spin mr-1"></i>A criar…
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
