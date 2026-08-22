@props(['value' => 0])
@php
    // Configuração da máscara do tenant activo (liga/desliga + separadores +
    // casas). Quando desligada, cai no input numérico simples de sempre.
    $__cfg = moeda_config();
@endphp
@if($__cfg['on'])
    <input
        type="text"
        inputmode="decimal"
        autocomplete="off"
        data-moeda
        data-milhar="{{ $__cfg['milhar'] }}"
        data-decimal="{{ $__cfg['decimal'] }}"
        data-casas="{{ $__cfg['casas'] }}"
        value="{{ \App\Helpers\MoneyHelper::format($value, $__cfg) }}"
        {{ $attributes }}
    />
@else
    <input
        type="number"
        step="0.01"
        min="0"
        value="{{ $value }}"
        {{ $attributes }}
    />
@endif
