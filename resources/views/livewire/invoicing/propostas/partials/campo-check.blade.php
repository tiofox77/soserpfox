<label class="flex items-center gap-2 cursor-pointer">
    <input type="checkbox" @checked($valor)
           wire:change="actualizarCampo('{{ $campo }}', $event.target.checked)"
           class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
    <span class="text-sm text-gray-700">{{ $rotulo }}</span>
</label>
