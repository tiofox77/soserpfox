<div class="p-3 sm:p-6 max-w-4xl mx-auto">

    {{-- Cabeçalho --}}
    <div class="mb-6">
        <h2 class="text-xl sm:text-3xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-file-import mr-3 text-emerald-600"></i>
            {{ __('Importar Cópia Offline') }}
        </h2>
        <p class="text-gray-600 mt-1 text-xs sm:text-base">
            {{ __('Recupera vendas e documentos de um aparelho que não chegou a sincronizar.') }}
        </p>
    </div>

    {{-- Quando usar --}}
    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs sm:text-sm text-blue-800">
        <p class="font-bold mb-1"><i class="fas fa-circle-info mr-1"></i>{{ __('Quando usar isto') }}</p>
        <p>
            {{ __('O caminho normal é o aparelho sincronizar sozinho. Isto é para quando já não vai: o telemóvel partiu-se, o navegador limpou os dados, ou alguém apagou tudo antes de enviar. As vendas que lá estavam já aconteceram — sem o ficheiro não há de onde as tirar.') }}
        </p>
        <p class="mt-2">
            {!! __('O ficheiro tira-se no aparelho em <strong>POS Offline → Guardar cópia do que falta enviar</strong>.') !!}
        </p>
    </div>

    {{-- Escolher ficheiro --}}
    <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-6">
        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Ficheiro da cópia (.json)') }}</label>

        <input type="file" wire:model="ficheiro" accept=".json,application/json"
               class="w-full text-sm border-2 border-dashed border-gray-300 rounded-xl p-4 file:mr-4 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-emerald-600 file:text-white file:font-semibold hover:border-emerald-400 transition">

        <div wire:loading wire:target="ficheiro" class="mt-3 text-sm text-gray-500">
            <i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A ler o ficheiro…') }}
        </div>

        @error('ficheiro')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Erro --}}
    @if($erro)
        <div class="mb-6 bg-red-50 border-2 border-red-200 rounded-xl p-4">
            <p class="text-sm font-bold text-red-800">
                <i class="fas fa-triangle-exclamation mr-1"></i>{{ __('Não foi possível usar este ficheiro') }}
            </p>
            <p class="text-sm text-red-700 mt-1">{{ $erro }}</p>
        </div>
    @endif

    {{-- O que está na cópia --}}
    @if($inventario)
        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-box-open mr-2 text-emerald-600"></i>{{ __('O que está nesta cópia') }}
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                @foreach([
                    ['Vendas (FR)', $inventario['vendas'], 'emerald', 'fa-receipt'],
                    ['Rascunhos', $inventario['rascunhos'], 'blue', 'fa-file-lines'],
                    ['Clientes', $inventario['clientes'], 'purple', 'fa-users'],
                    ['Turnos', $inventario['turnos'], 'gray', 'fa-clock'],
                ] as [$rotulo, $quantos, $cor, $icone])
                    <div class="text-center p-3 bg-{{ $cor }}-50 rounded-xl border border-{{ $cor }}-200">
                        <i class="fas {{ $icone }} text-{{ $cor }}-600 mb-1"></i>
                        <div class="text-2xl font-bold text-{{ $cor }}-700">{{ $quantos }}</div>
                        <p class="text-xs text-{{ $cor }}-600">{{ __($rotulo) }}</p>
                    </div>
                @endforeach
            </div>

            <dl class="text-xs text-gray-500 space-y-1 mb-4">
                @if($inventario['gerado_em'])
                    <div class="flex gap-2">
                        <dt class="font-semibold">{{ __('Guardada em:') }}</dt>
                        <dd>{{ \Carbon\Carbon::parse($inventario['gerado_em'])->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
                @if($inventario['operador'])
                    <div class="flex gap-2">
                        <dt class="font-semibold">{{ __('Operador:') }}</dt>
                        <dd>{{ $inventario['operador'] }}</dd>
                    </div>
                @endif
            </dl>

            {{-- O que a importação faz e o que não faz. Escrito antes do
                 botão de propósito: quem recupera dados está com pressa, e é
                 aqui que tem de saber que os turnos ficam de fora. --}}
            <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-600 mb-4 space-y-1">
                <p>
                    <i class="fas fa-check text-emerald-600 mr-1"></i>
                    {{ __('As vendas (FR) entram como Faturas-Recibo reais, com número e assinatura AGT.') }}
                </p>
                <p>
                    <i class="fas fa-check text-emerald-600 mr-1"></i>
                    {!! __('Faturas, proformas e notas de crédito entram como <strong>rascunho</strong> — o número AGT é atribuído quando as finalizar na Faturação, tal como acontece na sincronização normal.') !!}
                </p>
                <p>
                    <i class="fas fa-check text-emerald-600 mr-1"></i>
                    {{ __('Importar duas vezes o mesmo ficheiro não duplica nada.') }}
                </p>
                @if($inventario['turnos'])
                    <p>
                        <i class="fas fa-minus text-gray-400 mr-1"></i>
                        {{ __('Os turnos de caixa não são importados: um turno de outro dia não se reabre. As vendas entram na mesma.') }}
                    </p>
                @endif
            </div>

            <button wire:click="importar" wire:loading.attr="disabled" wire:target="importar"
                    class="w-full px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold transition disabled:opacity-50">
                <span wire:loading.remove wire:target="importar">
                    <i class="fas fa-file-import mr-2"></i>{{ __('Importar para o sistema') }}
                </span>
                <span wire:loading wire:target="importar">
                    <i class="fas fa-spinner fa-spin mr-2"></i>{{ __('A importar…') }}
                </span>
            </button>
        </div>
    @endif

    {{-- Resultado --}}
    @if($resultado)
        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-clipboard-check mr-2 text-emerald-600"></i>{{ __('Resultado') }}
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
                @foreach([
                    ['Importadas', $resultado['importadas'], 'emerald'],
                    ['Rascunhos', $resultado['rascunhos'] ?? 0, 'blue'],
                    ['Clientes', $resultado['clientes'], 'purple'],
                    ['Já existiam', $resultado['ja_existiam'], 'gray'],
                    ['Falhadas', $resultado['falhadas'], 'red'],
                ] as [$rotulo, $quantos, $cor])
                    <div class="text-center p-3 bg-{{ $cor }}-50 rounded-xl border border-{{ $cor }}-200">
                        <div class="text-2xl font-bold text-{{ $cor }}-700">{{ $quantos }}</div>
                        <p class="text-xs text-{{ $cor }}-600">{{ __($rotulo) }}</p>
                    </div>
                @endforeach
            </div>

            @if($resultado['ja_existiam'])
                <p class="text-xs text-gray-500 mb-3">
                    {{ __('As que "já existiam" tinham sido sincronizadas antes — não foram duplicadas.') }}
                </p>
            @endif

            @if(!empty($resultado['detalhes']))
                <div class="border border-gray-200 rounded-xl overflow-hidden mb-4">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-3 py-2 font-semibold">{{ __('Documento') }}</th>
                                <th class="text-right px-3 py-2 font-semibold">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($resultado['detalhes'] as $doc)
                                <tr>
                                    <td class="px-3 py-2 font-mono">{{ $doc['numero'] }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($doc['total'], 2) }} Kz</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if(!empty($resultado['erros']))
                <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                    <p class="text-xs font-bold text-red-800 mb-1">{{ __('O que não entrou:') }}</p>
                    <ul class="text-xs text-red-700 space-y-1 list-disc list-inside">
                        @foreach($resultado['erros'] as $linha)
                            <li>{{ $linha }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
