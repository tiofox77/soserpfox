<div class="max-w-md mx-auto px-4 py-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                <i class="fas fa-key text-indigo-600"></i>
            </div>
            <div>
                <h1 class="text-lg font-semibold text-gray-900">{{ __('PIN de turno') }}</h1>
                <p class="text-sm text-gray-500">
                    {{ $jaTemPin ? __('Já tem um PIN definido. Pode mudá-lo aqui.') : __('Ainda não tem PIN.') }}
                </p>
            </div>
        </div>

        <p class="text-sm text-gray-600 mb-5">
            {{ __('O PIN abre o seu turno no POS quando não há internet. É diferente da palavra-passe da conta — nunca digite a palavra-passe da conta como PIN.') }}
        </p>

        <form wire:submit="guardar" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('PIN (4 a 6 dígitos)') }}</label>
                <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                       wire:model="pin" autocomplete="off"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg tracking-[0.4em] text-center text-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('pin') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Repita o PIN') }}</label>
                <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                       wire:model="pin_confirmation" autocomplete="off"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg tracking-[0.4em] text-center text-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="pt-2 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Confirme com a sua palavra-passe') }}</label>
                <input type="password" wire:model="password" autocomplete="current-password"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <button type="submit"
                    class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="guardar">{{ $jaTemPin ? __('Mudar PIN') : __('Definir PIN') }}</span>
                <span wire:loading wire:target="guardar">{{ __('A guardar…') }}</span>
            </button>
        </form>

        <p class="text-xs text-gray-400 mt-5">
            {{ __('Guardamos apenas um verificador do PIN, nunca o PIN em si. Ele só vai para os tablets da sua empresa e deixa de valer 14 dias após a última sincronização. O PIN identifica quem está na caixa; não substitui o cuidado de não deixar o aparelho em mãos alheias.') }}
        </p>
    </div>
</div>
