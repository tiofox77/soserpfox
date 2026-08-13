{{--
    Região fiscal do documento.

    Cabinda tem regime de IVA próprio (AO-CAB) e o que o determina é o LOCAL DA
    OPERAÇÃO, não a sede da entidade: a mesma empresa pode comprar em Luanda e
    em Cabinda. Por isso escolhe-se aqui, e só na falta de escolha se deriva da
    província do cliente.

    Existia só nas facturas de venda. As proformas e as compras calculavam
    sempre à taxa continental — uma proposta feita em Cabinda saía com o
    imposto errado, e isso não é diferença de aspecto, é imposto errado.

    Parâmetros:
      $regiaoFiscal  obrigatório — a que vai ser aplicada (AO / AO-CAB)
      $cor           opcional    — cor de realce do selector
--}}
@php
    $__cor = $cor ?? 'amber';
@endphp

<div class="mt-2 flex flex-wrap items-center gap-2">
    <label class="text-xs font-semibold text-gray-600 whitespace-nowrap">
        <i class="fas fa-map-marker-alt mr-1 text-{{ $__cor }}-600"></i>{{ __('Região fiscal') }}
    </label>

    <select wire:model.live="tax_country_region"
            class="text-xs px-2 py-1.5 border-2 border-gray-200 rounded-lg focus:border-{{ $__cor }}-500">
        <option value="" @selected(($tax_country_region ?? '') === '')>{{ __('Automática (província do cliente)') }}</option>
        <option value="AO" @selected(($tax_country_region ?? '') === 'AO')>{{ __('AO — Angola continental') }}</option>
        <option value="AO-CAB" @selected(($tax_country_region ?? '') === 'AO-CAB')>{{ __('AO-CAB — Cabinda (regime próprio)') }}</option>
    </select>

    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $regiaoFiscal === 'AO-CAB' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
        a aplicar: {{ $regiaoFiscal }}
    </span>
</div>
