<div>
    <!-- Header — o mesmo desenho da página de Produtos, na cor do CRM -->
    <div class="mb-6 bg-gradient-to-r from-teal-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-user-plus text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Leads</h2>
                    <p class="text-teal-100 text-sm">Quem ainda não é cliente mas pode vir a ser</p>
                </div>
            </div>
            <a href="{{ route('crm.dashboard') }}" class="bg-white/15 hover:bg-white/25 px-5 py-3 rounded-xl font-semibold transition-all">
                <i class="fas fa-chart-pie mr-2"></i>Painel do CRM
            </a>
        </div>
    </div>

    <!-- Criar: uma linha, Enter, próximo -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <label class="block text-sm font-semibold text-gray-700 mb-3">
            <i class="fas fa-plus text-teal-500 mr-2"></i>Lead novo — alguém ligou a perguntar preços? Escreva-o aqui.
        </label>
        <div class="flex flex-wrap gap-3">
            <input wire:model="novoNome" wire:keydown.enter="criar" type="text" placeholder="Nome de quem ligou / apareceu"
                   class="flex-1 min-w-44 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all">
            <input wire:model="novoTelefone" wire:keydown.enter="criar" type="tel" placeholder="Telefone"
                   class="w-40 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all">
            <input wire:model="novaEmpresa" wire:keydown.enter="criar" type="text" placeholder="Empresa (opcional)"
                   class="w-52 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all">
            <select wire:model="novaOrigem" class="px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 transition-all">
                @foreach(\App\Models\CRM\Lead::ORIGENS as $codigo => $nome)
                    <option value="{{ $codigo }}">{{ $nome }}</option>
                @endforeach
            </select>
            <button wire:click="criar" wire:loading.attr="disabled"
                    class="bg-gradient-to-r from-teal-600 to-cyan-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-teal-700 hover:to-cyan-700 shadow-lg hover:shadow-xl transition disabled:opacity-50">
                <i class="fas fa-plus mr-2"></i>Entrar
            </button>
        </div>
        @error('novoNome') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
    </div>

    <!-- Pesquisa e filtros -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="relative mb-4">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
            </div>
            <input wire:model.live.debounce.300ms="procurar" type="text" placeholder="Pesquisar nome, empresa, telefone..."
                   class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all">
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach(['abertos' => 'Em jogo'] + \App\Models\CRM\Lead::ESTADOS as $codigo => $nome)
                <button wire:click="$set('filtroEstado', '{{ $codigo }}')"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition {{ $filtroEstado === $codigo ? 'bg-teal-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    {{ $nome }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-teal-600"></i>
                Lista de Leads ({{ $leads->total() }})
            </h3>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($leads as $lead)
                <div wire:key="lead-{{ $lead->id }}" class="group p-6 hover:bg-teal-50 transition-all duration-300 {{ $lead->status === 'perdido' ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-3 mb-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold
                                    {{ ['novo' => 'bg-blue-100 text-blue-700', 'contactado' => 'bg-amber-100 text-amber-700',
                                        'qualificado' => 'bg-violet-100 text-violet-700', 'convertido' => 'bg-green-100 text-green-700',
                                        'perdido' => 'bg-red-100 text-red-600'][$lead->status] }}">
                                    {{ strtoupper($lead->status_label) }}
                                </span>
                                <h4 class="text-lg font-bold text-gray-900">{{ $lead->name }}</h4>
                                @if($lead->company)<span class="text-sm text-gray-500">· {{ $lead->company }}</span>@endif
                            </div>
                            <div class="flex flex-wrap gap-3 text-sm">
                                @if($lead->phone)
                                    <a href="tel:{{ $lead->phone }}" class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-lg font-semibold hover:bg-green-200">
                                        <i class="fas fa-phone mr-1"></i>{{ $lead->phone }}
                                    </a>
                                @endif
                                @if($lead->email)
                                    <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-lg font-semibold">
                                        <i class="fas fa-envelope mr-1"></i>{{ $lead->email }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-lg font-semibold">
                                    <i class="fas fa-location-arrow mr-1"></i>{{ $lead->source_label }}
                                </span>
                                <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-lg font-semibold">
                                    <i class="fas fa-comments mr-1"></i>{{ $lead->activities_count }} interacção(ões)
                                </span>
                                @if($lead->client)
                                    <span class="inline-flex items-center px-3 py-1 bg-emerald-100 text-emerald-700 rounded-lg font-semibold">
                                        <i class="fas fa-user-check mr-1"></i>{{ $lead->client->name }}
                                    </span>
                                @endif
                            </div>
                            @if($lead->status === 'perdido' && $lead->lost_reason)
                                <p class="mt-2 text-sm font-semibold text-red-500"><i class="fas fa-circle-info mr-1"></i>{{ $lead->lost_reason }}</p>
                            @endif
                        </div>

                        <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                            <button wire:click="verConversa({{ $lead->id }})" class="px-3 py-1.5 bg-teal-500 hover:bg-teal-600 text-white rounded-lg text-xs font-semibold transition shadow-md" title="Ver conversa / responder">
                                <i class="fas fa-comments mr-1"></i>Conversa
                            </button>
                            @if(in_array($lead->status, ['novo', 'contactado'], true))
                                <button wire:click="avancar({{ $lead->id }})" class="px-3 py-1.5 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-xs font-semibold transition shadow-md" title="Avançar de estado">
                                    <i class="fas fa-arrow-right mr-1"></i>Avançar
                                </button>
                            @endif
                            <button wire:click="$set('paraActividade', {{ $lead->id }})" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-semibold transition shadow-md" title="Registar chamada/reunião">
                                <i class="fas fa-phone mr-1"></i>Interacção
                            </button>
                            @if(in_array($lead->status, \App\Models\CRM\Lead::ABERTOS, true))
                                <button wire:click="prepararConversao({{ $lead->id }})" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg text-xs font-semibold transition shadow-md">
                                    <i class="fas fa-user-check mr-1"></i>Converter
                                </button>
                                <button wire:click="$set('paraPerder', {{ $lead->id }})" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-semibold transition shadow-md">
                                    <i class="fas fa-xmark mr-1"></i>Perdido
                                </button>
                            @elseif($lead->status === 'perdido')
                                <button wire:click="reabrir({{ $lead->id }})" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-semibold transition shadow-md">
                                    <i class="fas fa-rotate-left mr-1"></i>Reabrir
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-plus text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ trim($procurar) !== '' ? 'Nenhum lead encontrado' : 'Ainda sem leads' }}</h3>
                    <p class="text-gray-500 mb-4">Alguém ligou a perguntar preços? É um lead — escreva-o na caixa acima.</p>
                </div>
            @endforelse
        </div>

        @if($leads->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $leads->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: a conversa do lead -->
    @if($conversaLeadId && $this->conversa)
        @php($c = $this->conversa)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50" wire:click="fecharConversa"></div>
            <div class="flex items-center justify-center min-h-screen px-4 py-6 sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full flex flex-col" style="max-height: 85vh;">
                    {{-- Cabeçalho --}}
                    <div class="bg-gradient-to-r from-teal-600 to-cyan-600 px-6 py-4 shrink-0">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-white flex items-center">
                                    <i class="fas fa-comments mr-3"></i>{{ $c['lead']->name }}
                                </h3>
                                <p class="text-teal-50 text-xs mt-0.5">
                                    <i class="fab fa-whatsapp mr-1"></i>{{ $c['numero'] ?: 'sem WhatsApp' }}
                                    <span class="mx-1">·</span>{{ \App\Models\CRM\Lead::ORIGENS[$c['lead']->source] ?? $c['lead']->source }}
                                </p>
                            </div>
                            <button wire:click="fecharConversa" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Conversa --}}
                    <div class="flex-1 overflow-y-auto p-4 space-y-2 bg-gray-50" style="min-height: 260px;">
                        @forelse($c['actividades'] as $a)
                            @if($a->direction === 'in' || $a->direction === 'out')
                                <div class="flex {{ $a->direction === 'out' ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-[75%] rounded-2xl px-4 py-2 shadow-sm {{ $a->direction === 'out' ? 'bg-teal-600 text-white rounded-br-sm' : 'bg-white text-gray-800 rounded-bl-sm border border-gray-100' }}">
                                        <p class="text-sm whitespace-pre-wrap">{{ $a->notes }}</p>
                                        <p class="text-[10px] mt-1 {{ $a->direction === 'out' ? 'text-teal-100' : 'text-gray-400' }}">{{ $a->created_at?->format('d/m H:i') }}</p>
                                    </div>
                                </div>
                            @else
                                {{-- Actividade sem sentido de conversa (chamada, nota…): ao centro. --}}
                                <div class="flex justify-center">
                                    <div class="text-[11px] text-gray-500 bg-gray-200 rounded-full px-3 py-1">
                                        <i class="fas fa-{{ $a->type === 'chamada' ? 'phone' : ($a->type === 'reuniao' ? 'users' : 'note-sticky') }} mr-1"></i>{{ $a->subject }}
                                        <span class="text-gray-400 ml-1">{{ $a->created_at?->format('d/m H:i') }}</span>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <p class="text-center text-sm text-gray-400 py-10">Ainda não há mensagens com este lead.</p>
                        @endforelse
                    </div>

                    {{-- Responder --}}
                    <div class="border-t border-gray-200 p-4 shrink-0 bg-white">
                        @if($c['podeWhatsapp'])
                            @if($erroResposta)
                                <p class="text-xs text-red-600 mb-2"><i class="fas fa-circle-exclamation mr-1"></i>{{ $erroResposta }}</p>
                            @endif
                            <div class="flex items-end gap-2">
                                <textarea wire:model="respostaTexto" rows="2" placeholder="Escreva a resposta…"
                                          class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-teal-500 resize-none"></textarea>
                                <button wire:click="responder" wire:loading.attr="disabled" wire:target="responder"
                                        class="px-4 py-2.5 bg-gradient-to-r from-teal-600 to-cyan-600 text-white rounded-xl font-bold hover:shadow-lg transition shrink-0">
                                    <span wire:loading.remove wire:target="responder"><i class="fab fa-whatsapp mr-1"></i>Enviar</span>
                                    <span wire:loading wire:target="responder"><i class="fas fa-spinner fa-spin"></i></span>
                                </button>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">O WhatsApp só deixa texto livre até 24h após a última mensagem do cliente.</p>
                        @else
                            <p class="text-sm text-gray-500 text-center">
                                <i class="fas fa-circle-info mr-1"></i>Para responder por WhatsApp, liga o WhatsApp em
                                <a href="{{ route('crm.integracoes') }}" class="text-teal-600 font-semibold underline">Integração Meta</a>
                                e o lead precisa de um número.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: converter em cliente -->
    @if($paraConverter)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-user-check mr-3"></i>Converter em cliente
                            </h3>
                            <button wire:click="$set('paraConverter', null)" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600 mb-4">Cria o cliente na Facturação (ou reaproveita um que já exista com este contacto) e, se quiser, abre já a oportunidade.</p>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-handshake text-teal-500 mr-2"></i>Oportunidade (opcional)
                            </label>
                            <input wire:model="convTitulo" type="text" placeholder="Deixe vazio para não abrir"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>Valor estimado (Kz)
                            </label>
                            <input wire:model="convValor" type="text" inputmode="decimal" placeholder="0,00"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-right font-bold focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button wire:click="$set('paraConverter', null)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button wire:click="converter" wire:loading.attr="disabled"
                                    class="px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-xl font-semibold hover:from-emerald-700 hover:to-teal-700 shadow-lg hover:shadow-xl transition disabled:opacity-50">
                                <i class="fas fa-user-check mr-2"></i>Converter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: perdido, com motivo -->
    @if($paraPerder)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-gradient-to-r from-red-600 to-rose-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-xmark mr-3"></i>Porque se perdeu?
                            </h3>
                            <button wire:click="$set('paraPerder', null)" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600 mb-4">Um motivo por lead não vale nada; cem somados dizem onde o negócio se perde.</p>
                        <input wire:model="motivoPerda" wire:keydown.enter="perder" type="text" placeholder="Ex.: preço, ficou com o concorrente, desistiu..."
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                        @error('motivoPerda') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button wire:click="$set('paraPerder', null)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button wire:click="perder" class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-rose-600 text-white rounded-xl font-semibold hover:from-red-700 hover:to-rose-700 shadow-lg transition">
                                <i class="fas fa-xmark mr-2"></i>Marcar perdido
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: interacção rápida -->
    @if($paraActividade)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-gradient-to-r from-teal-600 to-cyan-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-phone mr-3"></i>Registar interacção
                            </h3>
                            <button wire:click="$set('paraActividade', null)" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex gap-3">
                            <select wire:model="actTipo" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 transition">
                                @foreach(\App\Models\CRM\Activity::TIPOS as $codigo => $nome)
                                    <option value="{{ $codigo }}">{{ $nome }}</option>
                                @endforeach
                            </select>
                            <input wire:model="actAssunto" wire:keydown.enter="registarActividade" type="text" placeholder="O que se falou / combinou"
                                   class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                        </div>
                        @error('actAssunto') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button wire:click="$set('paraActividade', null)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button wire:click="registarActividade" class="px-6 py-2.5 bg-gradient-to-r from-teal-600 to-cyan-600 text-white rounded-xl font-semibold hover:from-teal-700 hover:to-cyan-700 shadow-lg transition">
                                <i class="fas fa-check mr-2"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
