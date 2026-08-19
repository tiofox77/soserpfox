{{-- Montar um plano só para esta empresa.

     Cada módulo tem preço próprio e pode levar dias de teste; a mensalidade
     é a SOMA do que se escolhe. Os módulos que a empresa já tem aparecem
     marcados, para se ver o que se está a acrescentar ou a tirar.

     O plano fica ACTIVO (para a subscrição funcionar) mas FORA DA MONTRA —
     não aparece na landing, no registo, nem aos outros clientes. --}}
@if($showMedidaModal)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showMedidaModal') }" x-show="show" x-cloak>
    <div class="flex items-start justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" wire:click="fecharPlanoAMedida"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-8">

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

            <div class="p-6 space-y-5 max-h-[68vh] overflow-y-auto">

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Nome do plano</label>
                    <input type="text" wire:model="medidaNome"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none">
                    @error('medidaNome') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                {{-- Módulos: escolher, pôr preço e dias de teste --}}
                <div>
                    <div class="flex items-baseline justify-between mb-2">
                        <label class="text-sm font-bold text-gray-700">Módulos, preço e teste</label>
                        <span class="text-xs text-gray-500">
                            as dependências entram sozinhas (quem leva Faturação leva Tesouraria)
                        </span>
                    </div>

                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="grid grid-cols-12 gap-2 px-3 py-2 bg-gray-50 text-[11px] font-bold text-gray-500 uppercase">
                            <div class="col-span-6">Módulo</div>
                            <div class="col-span-3 text-right">Preço mensal (Kz)</div>
                            <div class="col-span-3 text-right">Dias de teste</div>
                        </div>

                        <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                            @foreach($modulosDisponiveis as $modulo)
                                @php
                                    $escolhido = in_array($modulo->slug, $medidaModulos ?? []);
                                    $jaTem = in_array($modulo->slug, $medidaJaTem ?? []);
                                @endphp
                                <div class="grid grid-cols-12 gap-2 items-center px-3 py-2 {{ $escolhido ? 'bg-amber-50/60' : '' }}">
                                    <label class="col-span-6 flex items-center gap-2 cursor-pointer min-w-0">
                                        <input type="checkbox" wire:model.live="medidaModulos" value="{{ $modulo->slug }}"
                                               class="rounded text-amber-600 focus:ring-amber-500 flex-shrink-0">
                                        <span class="text-sm text-gray-800 truncate">{{ $modulo->name }}</span>
                                        @if($jaTem)
                                            <span class="text-[10px] font-bold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full flex-shrink-0"
                                                  title="A empresa já tem este módulo activo">já tem</span>
                                        @endif
                                    </label>

                                    <div class="col-span-3">
                                        <input type="number" min="0" step="100"
                                               wire:model.live.debounce.400ms="medidaPrecos.{{ $modulo->slug }}"
                                               @disabled(!$escolhido)
                                               class="w-full px-2 py-1.5 border rounded-lg text-right text-sm
                                                      {{ $escolhido ? 'border-gray-300' : 'border-gray-100 bg-gray-50 text-gray-300' }}">
                                    </div>

                                    <div class="col-span-3">
                                        <input type="number" min="0" max="365"
                                               wire:model="medidaTestes.{{ $modulo->slug }}"
                                               @disabled(!$escolhido)
                                               placeholder="0"
                                               class="w-full px-2 py-1.5 border rounded-lg text-right text-sm
                                                      {{ $escolhido ? 'border-gray-300' : 'border-gray-100 bg-gray-50 text-gray-300' }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @error('medidaModulos') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror

                    <p class="text-[11px] text-gray-500 mt-2">
                        <i class="fas fa-circle-info mr-1"></i>
                        Dias de teste por módulo: passado o prazo, <strong>só esse módulo</strong> deixa de
                        estar disponível — o resto do plano continua. Deixe a 0 para o módulo valer enquanto o plano valer.
                    </p>
                </div>

                {{-- Total --}}
                <div class="p-4 bg-gradient-to-r from-amber-50 to-orange-50 border-2 border-amber-200 rounded-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-gray-700">Mensalidade</p>
                            <p class="text-[11px] text-gray-500">
                                soma de {{ count($medidaModulos ?? []) }} módulo(s) escolhido(s)
                            </p>
                        </div>
                        <p class="text-3xl font-extrabold text-amber-700">
                            {{ number_format($this->medidaTotalMensal, 2) }} <span class="text-base font-bold">Kz</span>
                        </p>
                    </div>

                    <div class="mt-3 pt-3 border-t border-amber-200 flex items-center justify-between gap-3">
                        <label class="text-xs font-bold text-gray-600 whitespace-nowrap">
                            Anual (Kz) <span class="font-normal text-gray-400">— em branco: 12×</span>
                        </label>
                        <input type="number" min="0" step="0.01" wire:model="medidaPrecoAnual"
                               placeholder="{{ number_format($this->medidaAnualSugerido, 2) }}"
                               class="w-44 px-3 py-2 border-2 border-amber-200 rounded-lg text-right font-bold focus:border-amber-500 focus:outline-none">
                    </div>

                    @error('medidaPrecos')
                        <p class="text-xs text-rose-600 mt-2 font-semibold">{{ $message }}</p>
                    @enderror

                    <p class="text-[11px] text-amber-800 mt-2">
                        <i class="fas fa-triangle-exclamation mr-1"></i>
                        A soma tem de ser maior que zero. Um plano a zero é tratado em todo o sistema como
                        <strong>o plano gratuito</strong> e gasta a cortesia única do cliente — para oferecer,
                        ponha um valor simbólico e faça o desconto na cobrança.
                    </p>
                </div>

                {{-- Limites e ciclo --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
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
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Ciclo a aplicar</label>
                        <select wire:model="medidaCiclo"
                                class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none">
                            <option value="monthly">Mensal</option>
                            <option value="quarterly">Trimestral</option>
                            <option value="semiannual">Semestral</option>
                            <option value="yearly">Anual (12 + 2)</option>
                        </select>
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
