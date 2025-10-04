@props([
    'type' => 'submit',
    'action' => 'save',
    'loadingText' => 'Processando...',
    'icon' => null,
    'color' => 'purple', // purple, blue, green, red, etc.
])

@php
    $colorClasses = [
        'purple' => 'bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700',
        'blue' => 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700',
        'green' => 'bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700',
        'red' => 'bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700',
        'orange' => 'bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-700 hover:to-amber-700',
        'cyan' => 'bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700',
    ];
    
    $colorClass = $colorClasses[$color] ?? $colorClasses['purple'];
@endphp

<button 
    type="{{ $type }}" 
    wire:loading.attr="disabled"
    wire:target="{{ $action }}"
    {{ $attributes->merge(['class' => "px-6 py-2.5 {$colorClass} text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition disabled:opacity-70 disabled:cursor-not-allowed inline-flex items-center justify-center"]) }}>
    
    <span wire:loading.remove wire:target="{{ $action }}" class="inline-flex items-center">
        @if($icon)
            <i class="fas fa-{{ $icon }} mr-2"></i>
        @endif
        {{ $slot }}
    </span>
    
    <span wire:loading wire:target="{{ $action }}" class="inline-flex items-center">
        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        {{ $loadingText }}
    </span>
</button>
