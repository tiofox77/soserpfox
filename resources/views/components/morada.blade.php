@props([
    // Os valores actuais. Os nomes das propriedades Livewire são fixos —
    // `country`, `province`, `municipality`, `neighbourhood`, `city`,
    // `address`, `postal_code` — de propósito: é o que impede cada formulário
    // de inventar os seus e voltar a divergir.
    'pais'         => \App\Support\Geografia::PAIS_PADRAO,
    'provincia'    => null,
    'municipio'    => null,
    'bairro'       => null,
    'cidade'       => null,
    'codigoPostal' => null,
    'morada'       => null,
    // O que mostrar. Nem todo o formulário quer a morada completa.
    'comMorada'       => true,
    'comCodigoPostal' => true,
    'comBairro'       => true,
    'obrigatorio'     => false,
    // Aspecto: os modais da facturação usam cantos redondos e anéis; os ecrãs
    // de definições usam a forma simples. Um só bloco, duas peles.
    'estilo' => 'simples',
])
@php
    use App\Support\Geografia;

    $ehAngola   = strtoupper((string) $pais) === Geografia::PAIS_PADRAO;
    $municipios = $ehAngola ? Geografia::municipios($provincia) : [];
    $bairros    = $ehAngola ? Geografia::bairros($municipio) : [];
    $novas      = Geografia::provinciasNovas();

    $classe = $estilo === 'modal'
        ? 'w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition'
        : 'w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm';
    $rotulo = 'block text-xs font-semibold text-gray-600 mb-1.5';
    $obr    = $obrigatorio ? '<span class="text-red-500">*</span>' : '';
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    @if($comMorada)
        <div class="md:col-span-2">
            <label class="{{ $rotulo }}">{{ __('Morada') }} {!! $obr !!}</label>
            <input wire:model="address" type="text" class="{{ $classe }}"
                   placeholder="{{ __('Rua, número, referência') }}">
            @error('address') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
    @endif

    {{-- O PAÍS MANDA NO RESTO, e por isso vem primeiro.
         É ele que decide se a morada é angolana (província → município →
         bairro, tudo escolhido de uma lista) ou estrangeira (texto livre).
         Vai gravado em ISO 3166-1 alfa-2 porque é assim que a AGT o quer. --}}
    <div>
        <label class="{{ $rotulo }}">
            {{ __('País') }} {!! $obr !!}
            <span class="font-normal text-gray-400">{{ __('(código ISO da AGT)') }}</span>
        </label>
        <select wire:model.live="country" class="{{ $classe }}">
            @foreach(Geografia::paises() as $codigo => $nome)
                <option value="{{ $codigo }}">{{ $nome }} ({{ $codigo }})</option>
            @endforeach
        </select>
        @error('country') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
    </div>

    @if($ehAngola)
        <div>
            <label class="{{ $rotulo }}">{{ __('Província') }} {!! $obr !!}</label>
            <select wire:model.live="province" class="{{ $classe }}">
                <option value="">{{ __('Escolha a província…') }}</option>
                @foreach(Geografia::provincias() as $p)
                    <option value="{{ $p }}">{{ $p }}@if(in_array($p, $novas, true)) &nbsp;· {{ __('nova em 2024') }}@endif</option>
                @endforeach
                {{-- Um valor gravado antes da reforma continua a aparecer, em
                     vez de o select o deitar fora em silêncio ao gravar. --}}
                @if($provincia && !in_array($provincia, Geografia::provincias(), true))
                    <option value="{{ $provincia }}">{{ $provincia }} · {{ __('divisão anterior') }}</option>
                @endif
            </select>
            @error('province') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="{{ $rotulo }}">{{ __('Município') }} {!! $obr !!}</label>
            <select wire:model.live="municipality" class="{{ $classe }}" @disabled(!$provincia)>
                <option value="">
                    {{ $provincia ? __('Escolha o município…') : __('Escolha primeiro a província') }}
                </option>
                @foreach($municipios as $m)
                    <option value="{{ $m }}">{{ $m }}</option>
                @endforeach
                @if($municipio && !in_array($municipio, $municipios, true))
                    <option value="{{ $municipio }}">{{ $municipio }}</option>
                @endif
            </select>
            @error('municipality') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        @if($comBairro)
            <div>
                <label class="{{ $rotulo }}">
                    {{ __('Bairro') }}
                    <span class="font-normal text-gray-400">{{ __('(sugestões — pode escrever outro)') }}</span>
                </label>
                {{-- SUGERE, NÃO FECHA. Angola não tem registo nacional de
                     bairros: nascem, mudam de nome e raramente entram numa
                     lista oficial. Um select fechado obrigava a escolher o
                     bairro errado por o certo não estar lá. --}}
                <input wire:model="neighbourhood" type="text" list="bairros-{{ Str::slug((string) $municipio) }}"
                       class="{{ $classe }}" placeholder="{{ __('Bairro ou zona') }}">
                <datalist id="bairros-{{ Str::slug((string) $municipio) }}">
                    @foreach($bairros as $b)
                        <option value="{{ $b }}">
                    @endforeach
                </datalist>
                @error('neighbourhood') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        @endif
    @else
        {{-- Fora de Angola não há divisão que se possa impor: escreve-se. --}}
        <div>
            <label class="{{ $rotulo }}">{{ __('Província / Estado') }}</label>
            <input wire:model="province" type="text" class="{{ $classe }}"
                   placeholder="{{ __('Província, estado ou região') }}">
            @error('province') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="{{ $rotulo }}">{{ __('Cidade') }}</label>
            <input wire:model="city" type="text" class="{{ $classe }}">
            @error('city') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
    @endif

    @if($comCodigoPostal)
        <div>
            <label class="{{ $rotulo }}">{{ __('Código postal') }}</label>
            <input wire:model="postal_code" type="text" class="{{ $classe }}">
            @error('postal_code') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
    @endif
</div>
