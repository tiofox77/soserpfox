<div class="p-4 sm:p-6 max-w-6xl mx-auto">

    {{-- ─────────── Cabeçalho ─────────── --}}
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shadow-lg">
                <i class="fas fa-building text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900">Dados da Empresa</h1>
                <p class="text-sm text-gray-500">Atualize a identificação, contactos e o regime fiscal usado nos documentos.</p>
            </div>
        </div>
    </div>

    {{-- ─────────── Resumo fiscal atual ─────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Regime atual</p>
            <p class="text-sm font-extrabold text-gray-900 mt-1">{{ \App\Models\Tenant::REGIMES[$currentRegime]['short'] }}</p>
            <p class="text-[11px] text-gray-500 mt-0.5">
                {{ \App\Models\Tenant::REGIMES[$currentRegime]['exempt'] ? 'Sem IVA (isento)' : 'IVA ' . rtrim(rtrim(number_format(\App\Models\Tenant::REGIMES[$currentRegime]['default_rate'], 2, ',', ''), '0'), ',') . '%' }}
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Imposto por omissão</p>
            <p class="text-sm font-extrabold text-gray-900 mt-1">{{ $taxDefault->name ?? '— não definido —' }}</p>
            <p class="text-[11px] text-gray-500 mt-0.5">
                @if($taxDefault)
                    {{ rtrim(rtrim(number_format($taxDefault->rate, 2, ',', ''), '0'), ',') }}% · SAFT {{ $taxDefault->saft_type }}{{ $taxDefault->exemption_code ? ' · ' . $taxDefault->exemption_code : '' }}
                @else
                    <span class="text-red-500 font-semibold">Configure em Faturação → Configurações</span>
                @endif
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Produtos</p>
            <p class="text-sm font-extrabold text-gray-900 mt-1">{{ number_format($productStats['total'], 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-500 mt-0.5">
                {{ number_format($productStats['com_iva'], 0, ',', '.') }} c/ IVA ·
                {{ number_format($productStats['isentos'], 0, ',', '.') }} isentos
            </p>
        </div>
        <div class="bg-white rounded-xl border {{ $productStats['isentos_s_cod'] > 0 ? 'border-red-300 bg-red-50' : 'border-gray-200' }} p-4">
            <p class="text-[11px] font-bold {{ $productStats['isentos_s_cod'] > 0 ? 'text-red-500' : 'text-gray-400' }} uppercase tracking-wide">Isentos sem motivo</p>
            <p class="text-sm font-extrabold {{ $productStats['isentos_s_cod'] > 0 ? 'text-red-700' : 'text-gray-900' }} mt-1">
                {{ number_format($productStats['isentos_s_cod'], 0, ',', '.') }}
            </p>
            <p class="text-[11px] {{ $productStats['isentos_s_cod'] > 0 ? 'text-red-600' : 'text-gray-500' }} mt-0.5">
                {{ $productStats['isentos_s_cod'] > 0 ? 'A AGT rejeita documentos isentos sem código' : 'Tudo conforme a AGT' }}
            </p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-5">

        {{-- ─────────── Identificação ─────────── --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center gap-2">
                <i class="fas fa-id-card text-blue-500"></i>
                <h2 class="text-sm font-bold text-gray-800">Identificação</h2>
            </div>
            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nome comercial <span class="text-red-500">*</span></label>
                    <input wire:model="name" type="text" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Designação social (nome fiscal)</label>
                    <input wire:model="company_name" type="text" placeholder="Ex.: Farmácia Exemplo, Lda." class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-[11px] text-gray-400 mt-1">Aparece nas faturas e no SAFT-AO. Se vazio, é usado o nome comercial.</p>
                    @error('company_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">NIF</label>
                    <input wire:model="nif" type="text" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm uppercase focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @error('nif') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email</label>
                        <input wire:model="email" type="email" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Telefone</label>
                        <input wire:model="phone" type="text" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @error('phone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ─────────── Endereço ─────────── --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center gap-2">
                <i class="fas fa-map-marker-alt text-emerald-500"></i>
                <h2 class="text-sm font-bold text-gray-800">Endereço</h2>
            </div>
            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Morada</label>
                    <textarea wire:model="address" rows="2" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent"></textarea>
                    @error('address') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Código postal</label>
                        <input wire:model="postal_code" type="text" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('postal_code') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Cidade</label>
                        <input wire:model="city" type="text" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('city') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">País <span class="text-red-500">*</span></label>
                    <input wire:model="country" type="text" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    @error('country') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- ─────────── Regime fiscal AGT ─────────── --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-200 flex items-center gap-2">
                <i class="fas fa-balance-scale text-amber-600"></i>
                <h2 class="text-sm font-bold text-gray-800">Regime Fiscal (AGT)</h2>
                <span class="ml-auto text-[11px] font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Afeta impostos e produtos</span>
            </div>
            <div class="p-5">
                <p class="text-xs text-gray-500 mb-4">
                    Escolha o regime em que a empresa está enquadrada na AGT. O regime define a taxa aplicada nos
                    documentos e, no caso da não sujeição, o motivo de isenção obrigatório.
                </p>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                    @foreach(\App\Models\Tenant::REGIMES as $key => $meta)
                        <label class="relative flex flex-col cursor-pointer rounded-xl border-2 p-4 transition
                            {{ $regime === $key ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-100' : 'border-gray-200 hover:border-gray-300 bg-white' }}">
                            <div class="flex items-start gap-3">
                                <input type="radio" wire:model.live="regime" value="{{ $key }}" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-gray-900">{{ $meta['label'] }}</p>
                                    <p class="text-[11px] font-semibold {{ $meta['exempt'] ? 'text-orange-600' : 'text-blue-600' }} mt-0.5">
                                        {{ $meta['exempt']
                                            ? 'Sem IVA · motivo ' . $meta['exemption_code']
                                            : 'IVA ' . rtrim(rtrim(number_format($meta['default_rate'], 2, ',', ''), '0'), ',') . '%' }}
                                    </p>
                                </div>
                            </div>
                            <p class="text-[11px] text-gray-600 mt-2 leading-relaxed">{{ $meta['description'] }}</p>
                            <p class="text-[11px] text-gray-400 mt-1.5 flex items-start gap-1">
                                <i class="fas fa-chart-line mt-0.5"></i>
                                <span>{{ $meta['turnover'] }}</span>
                            </p>
                            @if($currentRegime === $key)
                                <span class="absolute top-2 right-2 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">ATUAL</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('regime') <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span> @enderror

                {{-- Aviso de consequências quando o regime muda --}}
                @if($this->regimeChanged)
                    <div class="mt-4 rounded-xl border-2 border-amber-300 bg-amber-50 p-4">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5"></i>
                            <div class="text-xs text-amber-900 space-y-1.5">
                                <p class="font-bold">Está a mudar de regime: {{ \App\Models\Tenant::REGIMES[$currentRegime]['label'] }} → {{ $this->selectedRegimeMeta['label'] }}</p>
                                <p>Ao guardar, o sistema vai aplicar automaticamente:</p>
                                <ul class="list-disc list-inside space-y-0.5 ml-1">
                                    @if($this->selectedRegimeMeta['exempt'])
                                        <li>Imposto por omissão passa a <strong>Isento 0% (SAFT ISE)</strong>;</li>
                                        <li>Todos os produtos passam a <strong>isentos</strong> com o motivo <strong>{{ $this->selectedRegimeMeta['exemption_code'] }}</strong>;</li>
                                        <li>Os documentos deixam de liquidar IVA.</li>
                                    @else
                                        <li>Imposto por omissão passa a <strong>IVA {{ rtrim(rtrim(number_format($this->selectedRegimeMeta['default_rate'], 2, ',', ''), '0'), ',') }}%</strong>;</li>
                                        <li>Os documentos passam a liquidar IVA à taxa do regime;</li>
                                        <li>Reveja os produtos que estavam isentos — podem precisar de taxa.</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Confirmação explícita --}}
                @if($showRegimeConfirm)
                    <div class="mt-3 rounded-xl border-2 border-red-300 bg-red-50 p-4">
                        <p class="text-xs font-bold text-red-800 mb-3">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Confirma a alteração do regime fiscal? Os produtos serão atualizados em massa.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg">
                                <i class="fas fa-check mr-1"></i> Sim, alterar regime e guardar
                            </button>
                            <button type="button" wire:click="cancelRegimeChange" class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-bold rounded-lg">
                                Manter {{ \App\Models\Tenant::REGIMES[$currentRegime]['short'] }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ─────────── Logótipo ─────────── --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center gap-2">
                <i class="fas fa-image text-purple-500"></i>
                <h2 class="text-sm font-bold text-gray-800">Logótipo</h2>
            </div>
            <div class="p-5 flex flex-wrap items-center gap-5">
                <div class="w-24 h-24 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden shrink-0">
                    @if($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Pré-visualização" class="w-full h-full object-contain">
                    @elseif($currentLogo)
                        <img src="{{ asset('storage/' . $currentLogo) }}" alt="Logótipo" class="w-full h-full object-contain">
                    @else
                        <i class="fas fa-building text-gray-300 text-2xl"></i>
                    @endif
                </div>
                <div class="flex-1 min-w-[220px]">
                    <input wire:model="logo" type="file" accept="image/*" class="block w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                    <p class="text-[11px] text-gray-400 mt-1.5">PNG ou JPG, até 2 MB. Usado nas faturas e recibos.</p>
                    @error('logo') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="logo" class="text-[11px] text-purple-600 mt-1">
                        <i class="fas fa-spinner fa-spin mr-1"></i> A carregar…
                    </div>
                </div>
                @if($currentLogo)
                    <button type="button" wire:click="removeLogo" class="px-3 py-2 bg-white border border-red-200 hover:bg-red-50 text-red-600 text-xs font-bold rounded-lg">
                        <i class="fas fa-trash mr-1"></i> Remover
                    </button>
                @endif
            </div>
        </div>

        {{-- ─────────── Ações + atalhos ─────────── --}}
        <div class="flex flex-wrap items-center gap-3 pb-2">
            <button type="submit" wire:loading.attr="disabled"
                class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-bold rounded-xl shadow-lg disabled:opacity-60">
                <span wire:loading.remove wire:target="save"><i class="fas fa-save mr-2"></i>Guardar alterações</span>
                <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-2"></i>A guardar…</span>
            </button>

            @can('invoicing.settings.view')
                <a href="{{ route('invoicing.settings') }}" class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-bold rounded-xl">
                    <i class="fas fa-cog mr-1.5"></i> Configurações de Faturação
                </a>
            @endcan
            <a href="{{ route('my-account') }}" class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-bold rounded-xl">
                <i class="fas fa-user-circle mr-1.5"></i> A Minha Conta
            </a>

            @if($tenant)
                <span class="ml-auto text-[11px] text-gray-400">
                    Identificador: <code class="bg-gray-100 px-1.5 py-0.5 rounded">{{ $tenant->slug }}</code>
                    · atualizado {{ $tenant->updated_at?->diffForHumans() }}
                </span>
            @endif
        </div>
    </form>
</div>
