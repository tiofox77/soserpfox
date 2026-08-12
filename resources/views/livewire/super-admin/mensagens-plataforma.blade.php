<div>
    {{-- Cabeçalho --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-bullhorn text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Mensagens às empresas</h2>
                    <p class="text-indigo-100 text-sm">Avisos, novidades e alertas que aparecem dentro do sistema</p>
                </div>
            </div>
            <button wire:click="nova"
                    class="bg-white text-indigo-700 px-5 py-2.5 rounded-xl font-bold shadow hover:bg-indigo-50 transition">
                <i class="fas fa-plus mr-2"></i>Escrever mensagem
            </button>
        </div>
    </div>

    {{-- Lista --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        @forelse($mensagens as $m)
            @php $e = $m->estilo(); @endphp
            <div wire:key="msg-{{ $m->id }}" class="p-5 border-b border-gray-100 last:border-0">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-{{ $e['cor'] }}-100 flex items-center justify-center shrink-0">
                        <i class="fas {{ $e['icone'] }} text-{{ $e['cor'] }}-600"></i>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <h3 class="font-bold text-gray-900">{{ $m->title }}</h3>

                            @if(!$m->is_active)
                                <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-xs font-bold">Retirada</span>
                            @elseif($m->ends_at && $m->ends_at->isPast())
                                <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-xs font-bold">Terminada</span>
                            @elseif($m->starts_at && $m->starts_at->isFuture())
                                <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">Agendada</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-bold">No ar</span>
                            @endif

                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs">
                                {{ $m->display === 'popup' ? 'Pop-up' : 'Barra' }}
                            </span>

                            @unless($m->dismissible)
                                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-bold">
                                    <i class="fas fa-lock mr-1"></i>Não dispensável
                                </span>
                            @endunless
                        </div>

                        <p class="text-sm text-gray-600 line-clamp-2 whitespace-pre-line">{{ $m->body }}</p>

                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-500 flex-wrap">
                            <span>
                                <i class="fas fa-users mr-1"></i>
                                {{ \App\Models\PlatformMessage::PUBLICOS[$m->audience] ?? $m->audience }}
                                ({{ $m->quantasEmpresas() }} empresa(s))
                            </span>
                            <span><i class="fas fa-eye mr-1"></i>{{ $m->vistas }} viram</span>
                            <span><i class="fas fa-check mr-1"></i>{{ $m->dispensadas }} dispensaram</span>
                            @if($m->starts_at || $m->ends_at)
                                <span>
                                    <i class="fas fa-calendar mr-1"></i>
                                    {{ $m->starts_at?->format('d/m/Y H:i') ?? 'desde já' }}
                                    até {{ $m->ends_at?->format('d/m/Y H:i') ?? 'sem fim' }}
                                </span>
                            @endif
                            @if($m->autor)
                                <span><i class="fas fa-user-pen mr-1"></i>{{ $m->autor->name }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button wire:click="verLeituras({{ $m->id }})"
                                class="px-3 py-1.5 bg-gray-50 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-100 transition">
                            <i class="fas fa-list-check mr-1"></i>Quem leu
                        </button>
                        <button wire:click="editar({{ $m->id }})"
                                class="px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-medium hover:bg-indigo-100 transition">
                            <i class="fas fa-edit mr-1"></i>Editar
                        </button>
                        <button wire:click="alternarActiva({{ $m->id }})"
                                class="px-3 py-1.5 bg-amber-50 text-amber-700 rounded-lg text-xs font-medium hover:bg-amber-100 transition">
                            <i class="fas {{ $m->is_active ? 'fa-eye-slash' : 'fa-eye' }} mr-1"></i>
                            {{ $m->is_active ? 'Retirar' : 'Pôr no ar' }}
                        </button>
                        <button wire:click="apagar({{ $m->id }})"
                                wire:confirm="Apagar esta mensagem? Perde-se também o registo de quem a leu."
                                class="px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-xs font-medium hover:bg-red-100 transition">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                {{-- Quem leu --}}
                @if($aVerLeiturasDe === $m->id)
                    <div class="mt-4 ml-14 bg-gray-50 rounded-xl p-4">
                        @if($leituras->isEmpty())
                            <p class="text-sm text-gray-500">Ainda ninguém abriu esta mensagem.</p>
                        @else
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-gray-500 uppercase">
                                        <th class="pb-2 font-semibold">Pessoa</th>
                                        <th class="pb-2 font-semibold">Viu</th>
                                        <th class="pb-2 font-semibold">Dispensou</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($leituras as $l)
                                        <tr wire:key="leitura-{{ $l->id }}">
                                            <td class="py-1.5">
                                                {{ $l->user?->name ?? 'Utilizador apagado' }}
                                                <span class="text-gray-500 text-xs">{{ $l->user?->email }}</span>
                                            </td>
                                            <td class="py-1.5 text-gray-600">{{ $l->seen_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                            <td class="py-1.5 text-gray-600">{{ $l->dismissed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="p-12 text-center">
                <i class="fas fa-bullhorn text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">Ainda não escreveu nenhuma mensagem.</p>
                <p class="text-sm text-gray-400 mt-1">
                    Serve para avisar de manutenções, mudanças de preços, obrigações novas da AGT —
                    ou para falar com uma empresa em particular.
                </p>
            </div>
        @endforelse

        @if($mensagens->hasPages())
            <div class="p-4 border-t border-gray-100">{{ $mensagens->links() }}</div>
        @endif
    </div>

    {{-- Modal de escrita --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="$wire.fechar()">
            <div class="flex items-start justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" wire:click="fechar"></div>

                <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                        <h3 class="text-lg font-bold text-white">
                            <i class="fas fa-bullhorn mr-2"></i>{{ $editandoId ? 'Editar mensagem' : 'Escrever mensagem' }}
                        </h3>
                        <button wire:click="fechar" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form wire:submit.prevent="guardar" class="p-6 max-h-[70vh] overflow-y-auto space-y-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Título *</label>
                            <input type="text" wire:model="title" placeholder="Ex.: Manutenção no domingo, das 22h à 1h"
                                   class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                            @error('title') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Mensagem *</label>
                            <textarea wire:model="body" rows="5"
                                      placeholder="O que precisa de ser dito, em português simples."
                                      class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"></textarea>
                            @error('body') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Tom</label>
                                <select wire:model="level" class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500">
                                    @foreach(\App\Models\PlatformMessage::NIVEIS as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Como aparece</label>
                                <select wire:model="display" class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500">
                                    @foreach(\App\Models\PlatformMessage::FORMAS as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Para quem</label>
                            <select wire:model.live="audience" class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500">
                                @foreach(\App\Models\PlatformMessage::PUBLICOS as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($audience === 'empresas')
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Empresas</label>
                                <div class="max-h-48 overflow-y-auto border-2 border-gray-200 rounded-xl p-3 space-y-1">
                                    @foreach($empresas as $emp)
                                        <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 px-2 py-1 rounded">
                                            <input type="checkbox" wire:model="tenant_ids" value="{{ $emp->id }}"
                                                   class="rounded text-indigo-600 focus:ring-indigo-500">
                                            {{ $emp->name }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('tenant_ids') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        @if($audience === 'planos')
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Planos</label>
                                <div class="border-2 border-gray-200 rounded-xl p-3 space-y-1">
                                    @foreach($planos as $p)
                                        <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 px-2 py-1 rounded">
                                            <input type="checkbox" wire:model="plan_ids" value="{{ $p->id }}"
                                                   class="rounded text-indigo-600 focus:ring-indigo-500">
                                            {{ $p->name }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('plan_ids') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Começa</label>
                                <input type="datetime-local" wire:model="starts_at"
                                       class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">Em branco: já.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Termina</label>
                                <input type="datetime-local" wire:model="ends_at"
                                       class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">Uma mensagem sem fim deixa de ser lida ao fim de dois dias.</p>
                                @error('ends_at') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Ligação (opcional)</label>
                                <input type="url" wire:model="link_url" placeholder="https://…"
                                       class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500">
                                @error('link_url') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Texto do botão</label>
                                <input type="text" wire:model="link_label" placeholder="Saber mais"
                                       class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="dismissible" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">
                                    <strong>Pode ser dispensada.</strong>
                                    Desligue só para mensagens que exijam que alguém faça alguma coisa —
                                    uma que não se fecha é uma que se odeia.
                                </span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="is_active" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700"><strong>No ar.</strong> Desligue para preparar sem publicar.</span>
                            </label>
                        </div>
                    </form>

                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                        <button type="button" wire:click="fechar"
                                class="px-5 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="button" wire:click="guardar" wire:loading.attr="disabled"
                                class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-semibold shadow hover:from-indigo-700 hover:to-purple-700 transition disabled:opacity-50">
                            <i class="fas fa-paper-plane mr-2"></i>{{ $editandoId ? 'Guardar' : 'Publicar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
