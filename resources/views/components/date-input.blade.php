@props([
    'name' => '',
    'value' => '',
    'label' => '',
    'required' => false,
    'placeholder' => 'dd/mm/aaaa',
    'class' => '',
])

<div x-data="{ 
    value: '{{ $value }}',
    formatted: '',
    init() {
        if (this.value) {
            // Converter de Y-m-d para dd/mm/yyyy
            const parts = this.value.split('-');
            if (parts.length === 3) {
                this.formatted = parts[2] + '/' + parts[1] + '/' + parts[0];
            }
        }
    },
    updateValue(e) {
        const input = e.target.value;
        // Converter de dd/mm/yyyy para Y-m-d
        const parts = input.split('/');
        if (parts.length === 3 && parts[2].length === 4) {
            this.value = parts[2] + '-' + parts[1] + '-' + parts[0];
        }
        this.formatted = input;
        this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
    }
}">
    @if($label)
    <label class="block text-sm font-semibold text-gray-700 mb-1">
        {{ $label }}
        @if($required)<span class="text-red-500">*</span>@endif
    </label>
    @endif
    
    <input 
        type="text"
        x-model="formatted"
        @input="updateValue($event)"
        placeholder="{{ $placeholder }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500 ' . $class]) }}
    >
    
    <input type="hidden" name="{{ $name }}" x-ref="hidden" x-model="value" {{ $attributes->whereStartsWith('wire:') }}>
</div>
