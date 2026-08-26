{{-- Editor visual de modelo de proposta.

     Sem bibliotecas novas de propósito: o texto rico usa `contenteditable` +
     document.execCommand, e a reordenação usa o arrastar nativo do HTML5.
     Uma dependência de CDN aqui deixaria este ecrã inútil na versão
     on-premise, que corre sem internet. --}}
<div x-data="editorProposta(@js($blocos), @js($catalogo), @js($blocoSeleccionado), @js((int)($estilos['paginas'] ?? 1)))"
     class="pb-6" @keydown.window="atalho($event)"
     @canvas-atualizado.window="sincronizarDoServidor($event.detail)">

    {{-- Barra de topo --}}
    <div class="mb-5 bg-gradient-to-r from-violet-600 to-indigo-600 rounded-2xl shadow-lg p-5 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0 flex-1">
                <a href="{{ route('invoicing.sales.quote-templates') }}"
                   class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center hover:bg-white/30 transition flex-shrink-0">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="min-w-0 flex-1">
                    <input type="text" wire:model.blur="nome" wire:change="guardar"
                           class="w-full bg-transparent border-0 border-b border-white/30 focus:border-white focus:ring-0 text-xl font-bold text-white placeholder-violet-200 px-0 py-1"
                           placeholder="Nome do modelo">
                    <input type="text" wire:model.blur="descricao" wire:change="guardar"
                           class="w-full bg-transparent border-0 focus:ring-0 text-sm text-violet-100 placeholder-violet-300 px-0 py-0.5"
                           placeholder="Descrição (opcional)">
                </div>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
                <span wire:loading class="text-xs text-violet-100"><i class="fas fa-circle-notch fa-spin mr-1"></i>a guardar…</span>
                <a href="{{ route('invoicing.sales.quote-templates.preview', $modeloId) }}" target="_blank"
                   class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-xl text-sm font-semibold transition">
                    <i class="fas fa-file-pdf mr-1"></i>Ver PDF
                </a>
                <button wire:click="guardar" class="px-4 py-2 bg-white text-violet-700 hover:bg-violet-50 rounded-xl text-sm font-bold transition shadow">
                    <i class="fas fa-check mr-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">

        {{-- ── ESQUERDA: peças e ordem ─────────────────────────────────── --}}
        <div class="xl:col-span-3 space-y-5">

            <div class="bg-white rounded-2xl shadow p-4">
                <h3 class="text-xs font-bold text-gray-500 uppercase mb-3">Acrescentar secção</h3>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($catalogo as $tipo => $t)
                        <button wire:click="adicionarBloco('{{ $tipo }}')" wire:loading.attr="disabled"
                                title="{{ $t['ajuda'] }}"
                                class="flex flex-col items-center gap-1 p-2.5 border border-gray-200 hover:border-violet-400 hover:bg-violet-50 rounded-xl transition text-center disabled:opacity-50">
                            <i class="fas {{ $t['icone'] }} text-violet-600"></i>
                            <span class="text-[11px] font-semibold text-gray-700 leading-tight">{{ $t['nome'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow p-4">
                <h3 class="text-xs font-bold text-gray-500 uppercase mb-3">
                    Ordem das secções
                    <span class="font-normal normal-case text-gray-400">— arraste para mudar</span>
                </h3>

                <div class="space-y-1.5" @dragover.prevent>
                    @foreach($blocos as $i => $b)
                        <div draggable="true"
                             @dragstart="arrastar('{{ $b['id'] }}')"
                             @dragover.prevent="sobre('{{ $b['id'] }}')"
                             @drop.prevent="largar()"
                             @dragend="cancelar()"
                             wire:key="ord-{{ $b['id'] }}"
                             wire:click="seleccionar('{{ $b['id'] }}')"
                             class="group flex items-center gap-2 px-2.5 py-2 rounded-lg cursor-move border transition
                                    {{ $blocoSeleccionado === $b['id'] ? 'bg-violet-50 border-violet-300' : 'bg-gray-50 border-transparent hover:bg-gray-100' }}">
                            <i class="fas fa-grip-vertical text-gray-300 text-xs"></i>
                            <i class="fas {{ $catalogo[$b['tipo']]['icone'] ?? 'fa-square' }} text-xs text-violet-500"></i>
                            <span class="text-xs font-medium text-gray-700 truncate flex-1">
                                {{ $b['titulo'] ?? $b['texto'] ?? $b['rotulo'] ?? ($catalogo[$b['tipo']]['nome'] ?? $b['tipo']) }}
                            </span>
                            <span class="hidden group-hover:flex items-center gap-0.5">
                                <button wire:click.stop="moverBloco('{{ $b['id'] }}', -1)" class="text-gray-400 hover:text-gray-700 px-1" title="Subir">
                                    <i class="fas fa-chevron-up text-[10px]"></i>
                                </button>
                                <button wire:click.stop="moverBloco('{{ $b['id'] }}', 1)" class="text-gray-400 hover:text-gray-700 px-1" title="Descer">
                                    <i class="fas fa-chevron-down text-[10px]"></i>
                                </button>
                                <button wire:click.stop="duplicarBloco('{{ $b['id'] }}')" class="text-gray-400 hover:text-indigo-600 px-1" title="Duplicar">
                                    <i class="fas fa-copy text-[10px]"></i>
                                </button>
                                <button wire:click.stop="removerBloco('{{ $b['id'] }}')" class="text-gray-400 hover:text-red-600 px-1" title="Remover">
                                    <i class="fas fa-trash text-[10px]"></i>
                                </button>
                            </span>
                        </div>
                    @endforeach

                    @if(empty($blocos))
                        <p class="text-xs text-gray-400 text-center py-4">Sem secções. Acrescente uma acima.</p>
                    @endif
                </div>
            </div>

            {{-- Estilo do documento --}}
            <div class="bg-white rounded-2xl shadow p-4 space-y-3">
                <h3 class="text-xs font-bold text-gray-500 uppercase">Estilo do documento</h3>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">Cor principal</label>
                    <div class="flex items-center gap-2">
                        <input type="color" value="{{ $estilos['cor_principal'] }}"
                               wire:change="actualizarEstilo('cor_principal', $event.target.value)"
                               class="w-10 h-9 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                        <div class="flex gap-1">
                            @foreach(['#4f46e5','#0891b2','#16a34a','#db2777','#ea580c','#111827'] as $c)
                                <button type="button" wire:click="actualizarEstilo('cor_principal', '{{ $c }}')"
                                        class="w-6 h-6 rounded-md border-2 {{ $estilos['cor_principal'] === $c ? 'border-gray-800' : 'border-white' }} shadow"
                                        style="background: {{ $c }}"></button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Letra</label>
                        <select wire:change="actualizarEstilo('fonte', $event.target.value)"
                                class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                            @foreach(['Arial','Helvetica','Times New Roman','Georgia','Courier New'] as $f)
                                <option value="{{ $f }}" @selected($estilos['fonte'] === $f)>{{ $f }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Tamanho</label>
                        <select wire:change="actualizarEstilo('tamanho_base', $event.target.value)"
                                class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                            @foreach([10,11,12,13,14] as $t)
                                <option value="{{ $t }}" @selected((int) $estilos['tamanho_base'] === $t)>{{ $t }}px</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" @checked($estilos['mostrar_rodape'])
                           wire:change="actualizarEstilo('mostrar_rodape', $event.target.checked)"
                           class="w-4 h-4 rounded border-gray-300 text-violet-600">
                    <span class="text-sm text-gray-700">Rodapé em todas as páginas</span>
                </label>

                @if($estilos['mostrar_rodape'])
                    <input type="text" value="{{ $estilos['texto_rodape'] }}"
                           wire:change="actualizarEstilo('texto_rodape', $event.target.value)"
                           class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-mono"
                           placeholder="@{{empresa.nome}}">
                @endif
            </div>
        </div>

        {{-- ── CENTRO: editor A4 livre ─────────────────────────────────── --}}
        <div class="xl:col-span-6 min-w-0">
            <div class="bg-slate-200 rounded-2xl overflow-hidden sticky top-4 border border-slate-300 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-white border-b border-slate-200 px-3 py-2">
                    <div class="flex items-center gap-1">
                        <button type="button" @click="desfazer" :disabled="historico.length < 2" class="editor-tool" title="Desfazer (Ctrl+Z)"><i class="fas fa-rotate-left"></i></button>
                        <button type="button" @click="refazer" :disabled="futuro.length === 0" class="editor-tool" title="Refazer"><i class="fas fa-rotate-right"></i></button>
                        <span class="h-5 w-px bg-slate-200 mx-1"></span>
                        <button type="button" @click="zoom = Math.max(.45, zoom - .1)" class="editor-tool" title="Diminuir zoom"><i class="fas fa-minus"></i></button>
                        <span class="text-[11px] font-semibold text-slate-600 w-12 text-center" x-text="Math.round(zoom*100)+'%'"></span>
                        <button type="button" @click="zoom = Math.min(1.35, zoom + .1)" class="editor-tool" title="Aumentar zoom"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] text-slate-600">
                        <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" x-model="grelha" class="rounded text-violet-600"> Grelha</label>
                        <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" x-model="encaixar" class="rounded text-violet-600"> Encaixar</label>
                        <button type="button" wire:click="adicionarPagina" class="px-2.5 py-1.5 rounded-lg bg-violet-50 text-violet-700 font-bold hover:bg-violet-100"><i class="fas fa-file-circle-plus mr-1"></i>Página</button>
                    </div>
                </div>

                <div class="max-h-[78vh] min-h-[620px] overflow-auto p-6" wire:ignore>
                    <div class="mx-auto origin-top" :style="`width:${794*zoom}px`">
                        <template x-for="pagina in totalPaginas" :key="pagina">
                            <div class="mb-6 relative bg-white shadow-xl border border-slate-300 overflow-hidden"
                                 :class="grelha ? 'editor-grid' : ''"
                                 :style="`width:${794*zoom}px;height:${1123*zoom}px`">
                                <div class="absolute top-2 left-2 z-[1000] px-2 py-1 rounded bg-slate-900/60 text-white text-[10px] pointer-events-none" x-text="'Página '+pagina"></div>
                                <div class="absolute inset-0 origin-top-left" :style="`width:794px;height:1123px;transform:scale(${zoom})`">
                                    <template x-for="item in blocos.filter(b => Number(b.layout?.pagina || 1) === pagina)" :key="item.id">
                                        <div class="proposal-node group" :class="{'is-selected': seleccionado === item.id, 'opacity-70': item.layout?.bloqueado}"
                                             :style="estilo(item)" @pointerdown.stop="iniciarMover($event,item)" @click.stop="seleccionarCanvas(item)">
                                            <div class="h-full w-full overflow-hidden pointer-events-none p-2 text-slate-800 bg-white/95">
                                                <div class="text-[10px] uppercase tracking-wide text-violet-500 font-bold mb-1" x-text="nomeTipo(item.tipo)"></div>
                                                <div class="text-sm font-semibold leading-tight" x-text="resumo(item)"></div>
                                                <div x-show="item.tipo === 'itens'" class="mt-2 space-y-1"><div class="h-2 bg-slate-100 rounded"></div><div class="h-2 bg-slate-100 rounded"></div><div class="h-2 bg-slate-100 rounded"></div></div>
                                            </div>
                                            <div x-show="seleccionado === item.id" class="node-actions">
                                                <button type="button" @pointerdown.stop @click.stop="alternarBloqueio(item)" :title="item.layout?.bloqueado ? 'Desbloquear' : 'Bloquear'"><i :class="item.layout?.bloqueado ? 'fas fa-lock' : 'fas fa-lock-open'"></i></button>
                                                <button type="button" @pointerdown.stop @click.stop="$wire.duplicarBloco(item.id)" title="Duplicar"><i class="fas fa-copy"></i></button>
                                                <button type="button" @pointerdown.stop @click.stop="$wire.removerBloco(item.id)" class="!bg-red-500" title="Eliminar"><i class="fas fa-trash"></i></button>
                                            </div>
                                            <button x-show="seleccionado === item.id && !item.layout?.bloqueado" type="button" class="resize-handle" @pointerdown.stop.prevent="iniciarResize($event,item)" aria-label="Redimensionar"></button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="bg-white border-t border-slate-200 px-3 py-2 flex justify-between text-[11px] text-slate-500">
                    <span><i class="fas fa-arrows-up-down-left-right mr-1"></i>Arraste livremente; setas movem 1 px, Shift + setas movem 10 px</span>
                    <a href="{{ route('invoicing.sales.quote-templates.preview', $modeloId) }}" target="_blank" class="font-bold text-violet-700">Pré-visualizar PDF</a>
                </div>
            </div>
        </div>

        {{-- ── DIREITA: opções do bloco escolhido ──────────────────────── --}}
        <div class="xl:col-span-3">
            <div class="bg-white rounded-2xl shadow p-4 sticky top-4">
                @if(!$bloco)
                    <div class="text-center py-10">
                        <i class="fas fa-hand-pointer text-gray-300 text-3xl mb-3"></i>
                        <p class="text-sm text-gray-500">Escolha uma secção à esquerda para a editar.</p>
                    </div>
                @else
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-gray-100">
                        <i class="fas {{ $catalogo[$bloco['tipo']]['icone'] ?? 'fa-square' }} text-violet-600"></i>
                        <h3 class="font-bold text-gray-900">{{ $catalogo[$bloco['tipo']]['nome'] ?? $bloco['tipo'] }}</h3>
                    </div>

                    @php $chaveBloco = $bloco['id']; @endphp

                    <div class="mb-4 rounded-xl border border-violet-200 bg-violet-50 p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase text-violet-700">Posição e tamanho</span>
                            <span class="text-[10px] text-violet-500">px · A4 794 × 1123</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['pagina' => 'Página', 'x' => 'X', 'y' => 'Y', 'largura' => 'Largura', 'altura' => 'Altura', 'z' => 'Camada'] as $campoGeo => $rotuloGeo)
                                <label class="text-[10px] font-semibold text-slate-600">{{ $rotuloGeo }}
                                    <input type="number" value="{{ data_get($bloco, 'layout.'.$campoGeo, $campoGeo === 'pagina' ? 1 : 0) }}"
                                           min="{{ in_array($campoGeo, ['pagina','z']) ? 1 : 0 }}"
                                           @change="actualizarGeometria('{{ $chaveBloco }}','{{ $campoGeo }}',$event.target.value)"
                                           class="mt-1 w-full rounded-lg border-violet-200 bg-white px-2 py-1.5 text-xs focus:border-violet-500 focus:ring-violet-500">
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[10px] leading-relaxed text-violet-600">Use os campos para precisão absoluta. No canvas pode arrastar e redimensionar visualmente.</p>
                    </div>

                    {{-- ---- CAPA ---- --}}
                    @if($bloco['tipo'] === 'capa')
                        <div class="space-y-3">
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'titulo', 'rotulo' => 'Título', 'valor' => $bloco['titulo'] ?? ''])
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'subtitulo', 'rotulo' => 'Subtítulo', 'valor' => $bloco['subtitulo'] ?? ''])
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Cor de fundo</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" value="{{ $bloco['cor_fundo'] ?: '#ffffff' }}"
                                           wire:change="actualizarCampo('cor_fundo', $event.target.value)"
                                           class="w-10 h-9 rounded-lg border border-gray-300 p-0.5 cursor-pointer">
                                    <button wire:click="actualizarCampo('cor_fundo', '')"
                                            class="text-xs text-gray-500 hover:text-gray-800 underline">sem fundo</button>
                                </div>
                            </div>
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'mostrar_logo', 'rotulo' => 'Mostrar logótipo', 'valor' => $bloco['mostrar_logo'] ?? true])
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'mostrar_dados', 'rotulo' => 'Mostrar número e data', 'valor' => $bloco['mostrar_dados'] ?? true])
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'quebrar_depois', 'rotulo' => 'Página nova a seguir', 'valor' => $bloco['quebrar_depois'] ?? true])
                        </div>

                    {{-- ---- TÍTULO ---- --}}
                    @elseif($bloco['tipo'] === 'titulo')
                        <div class="space-y-3">
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'texto', 'rotulo' => 'Texto', 'valor' => $bloco['texto'] ?? ''])
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'numerar', 'rotulo' => 'Numerar (1., 2., 3.…)', 'valor' => $bloco['numerar'] ?? true])
                        </div>

                    {{-- ---- TEXTO / CONDIÇÕES (rico) ---- --}}
                    @elseif(in_array($bloco['tipo'], ['texto', 'condicoes'], true))
                        <div class="space-y-3">
                            @if($bloco['tipo'] === 'condicoes')
                                @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'titulo', 'rotulo' => 'Título da secção', 'valor' => $bloco['titulo'] ?? ''])
                                <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">
                                    Se o orçamento tiver condições próprias escritas, são essas que saem — este texto é o que vale quando não há.
                                </p>
                            @endif

                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Conteúdo</label>
                                <div class="border border-gray-300 rounded-xl overflow-hidden">
                                    <div class="flex flex-wrap items-center gap-0.5 bg-gray-50 border-b border-gray-200 px-1.5 py-1">
                                        @foreach([['bold','fa-bold','Negrito'],['italic','fa-italic','Itálico'],['underline','fa-underline','Sublinhado'],['insertUnorderedList','fa-list-ul','Lista'],['insertOrderedList','fa-list-ol','Lista numerada']] as [$cmd,$ic,$tt])
                                            <button type="button" @mousedown.prevent="cmd('{{ $cmd }}')" title="{{ $tt }}"
                                                    class="w-7 h-7 rounded hover:bg-gray-200 text-gray-600 text-xs">
                                                <i class="fas {{ $ic }}"></i>
                                            </button>
                                        @endforeach
                                        <span class="w-px h-4 bg-gray-300 mx-1"></span>
                                        <button type="button" @mousedown.prevent="cmd('formatBlock','<h3>')" title="Subtítulo"
                                                class="px-2 h-7 rounded hover:bg-gray-200 text-gray-600 text-[11px] font-bold">H</button>
                                        <button type="button" @mousedown.prevent="cmd('removeFormat')" title="Limpar formatação"
                                                class="w-7 h-7 rounded hover:bg-gray-200 text-gray-600 text-xs">
                                            <i class="fas fa-eraser"></i>
                                        </button>
                                        <button type="button" @click="varsAbertas = !varsAbertas" title="Inserir variável"
                                                class="ml-auto px-2 h-7 rounded hover:bg-violet-100 text-violet-700 text-[11px] font-semibold">
                                            <i class="fas fa-code mr-1"></i>Variável
                                        </button>
                                    </div>

                                    <div x-show="varsAbertas" x-cloak class="max-h-40 overflow-y-auto bg-violet-50 border-b border-violet-100 p-2 space-y-2">
                                        @foreach($variaveis as $grupo => $lista)
                                            <div>
                                                <p class="text-[10px] font-bold text-violet-500 uppercase mb-1">{{ $grupo }}</p>
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($lista as $var => $desc)
                                                        <button type="button" title="{{ $desc }}"
                                                                @mousedown.prevent="inserir(@js($var))"
                                                                class="px-1.5 py-0.5 bg-white border border-violet-200 rounded text-[10px] font-mono text-violet-700 hover:bg-violet-100">{{ $var }}</button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div contenteditable="true"
                                         x-ref="rico"
                                         wire:key="rico-{{ $chaveBloco }}"
                                         @input.debounce.600ms="$wire.actualizarCampo('html', $refs.rico.innerHTML)"
                                         @focus="activo = $refs.rico"
                                         class="p-3 min-h-[160px] max-h-[320px] overflow-y-auto text-sm focus:outline-none prose-sm">{!! $bloco['html'] ?? '' !!}</div>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">Escreva à vontade. As variáveis são substituídas ao gerar o PDF.</p>
                            </div>
                        </div>

                    {{-- ---- CAMPO A PREENCHER ---- --}}
                    @elseif($bloco['tipo'] === 'campo_livre')
                        <div class="space-y-3">
                            <p class="text-[11px] text-violet-800 bg-violet-50 border border-violet-200 rounded-lg p-2">
                                Este bloco fica <strong>vazio no modelo</strong>. Ao fazer cada orçamento aparece uma
                                caixa com este rótulo, e o que lá se escrever entra aqui.
                            </p>
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'titulo', 'rotulo' => 'Título da secção no PDF', 'valor' => $bloco['titulo'] ?? ''])
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'rotulo', 'rotulo' => 'Rótulo (o que se pede a quem preenche)', 'valor' => $bloco['rotulo'] ?? ''])
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'ajuda', 'rotulo' => 'Ajuda', 'valor' => $bloco['ajuda'] ?? ''])
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Chave <span class="text-gray-400">— nome interno</span></label>
                                <input type="text" value="{{ $bloco['chave'] ?? '' }}"
                                       wire:change="actualizarCampo('chave', $event.target.value)"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Altura da caixa</label>
                                <select wire:change="actualizarCampo('linhas', $event.target.value)"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                                    @foreach([3,4,5,6,8,10] as $l)
                                        <option value="{{ $l }}" @selected((int) ($bloco['linhas'] ?? 4) === $l)>{{ $l }} linhas</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    {{-- ---- DADOS DO CLIENTE ---- --}}
                    @elseif($bloco['tipo'] === 'dados_cliente')
                        <div class="space-y-3">
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'mostrar_nif', 'rotulo' => 'Mostrar NIF', 'valor' => $bloco['mostrar_nif'] ?? true])
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'mostrar_validade', 'rotulo' => 'Mostrar validade', 'valor' => $bloco['mostrar_validade'] ?? true])
                        </div>

                    {{-- ---- ITENS ---- --}}
                    @elseif($bloco['tipo'] === 'itens')
                        <div class="space-y-3">
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'titulo', 'rotulo' => 'Título (vazio = sem título)', 'valor' => $bloco['titulo'] ?? ''])
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'mostrar_descricao', 'rotulo' => 'Descrição de cada linha', 'valor' => $bloco['mostrar_descricao'] ?? true])
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'mostrar_desconto', 'rotulo' => 'Coluna de desconto', 'valor' => $bloco['mostrar_desconto'] ?? true])
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'mostrar_imposto', 'rotulo' => 'Coluna de imposto', 'valor' => $bloco['mostrar_imposto'] ?? true])
                        </div>

                    {{-- ---- ASSINATURAS ---- --}}
                    @elseif($bloco['tipo'] === 'assinaturas')
                        <div class="space-y-3">
                            @include('livewire.invoicing.propostas.partials.campo-check', ['campo' => 'duas', 'rotulo' => 'Duas assinaturas', 'valor' => $bloco['duas'] ?? true])
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'esquerda', 'rotulo' => 'Esquerda', 'valor' => $bloco['esquerda'] ?? ''])
                            @if($bloco['duas'] ?? true)
                                @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'direita', 'rotulo' => 'Direita', 'valor' => $bloco['direita'] ?? ''])
                            @endif
                        </div>

                    {{-- ---- IMAGEM ---- --}}
                    @elseif($bloco['tipo'] === 'imagem')
                        <div class="space-y-3">
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'url', 'rotulo' => 'Endereço da imagem', 'valor' => $bloco['url'] ?? ''])
                            @include('livewire.invoicing.propostas.partials.campo-texto', ['campo' => 'legenda', 'rotulo' => 'Legenda', 'valor' => $bloco['legenda'] ?? ''])
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Largura: {{ $bloco['largura'] ?? 100 }}%</label>
                                <input type="range" min="20" max="100" step="5" value="{{ $bloco['largura'] ?? 100 }}"
                                       wire:change="actualizarCampo('largura', $event.target.value)" class="w-full">
                            </div>
                        </div>

                    @else
                        <p class="text-sm text-gray-500">Esta secção não tem opções — o seu conteúdo vem do orçamento.</p>
                    @endif

                    <div class="mt-5 pt-3 border-t border-gray-100 flex gap-2">
                        <button wire:click="duplicarBloco('{{ $chaveBloco }}')"
                                class="flex-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition">
                            <i class="fas fa-copy mr-1"></i>Duplicar
                        </button>
                        <button wire:click="removerBloco('{{ $chaveBloco }}')"
                                class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-semibold transition">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

{{-- O <style> vive DENTRO da raiz. Fora dela era um segundo elemento de topo,
     e o Livewire só aceita um: o componente rebentava com "Multiple root
     elements detected" e o ecrã caía na página de offline do PWA — que
     esconde o erro e faz parecer falha de rede. --}}
<style>
    .editor-tool{width:32px;height:32px;border-radius:8px;color:#475569;transition:.15s}.editor-tool:hover:not(:disabled){background:#f1f5f9;color:#6d28d9}.editor-tool:disabled{opacity:.3}
    .editor-grid{background-image:linear-gradient(to right,rgba(99,102,241,.10) 1px,transparent 1px),linear-gradient(to bottom,rgba(99,102,241,.10) 1px,transparent 1px);background-size:10px 10px}
    .proposal-node{position:absolute;border:1px dashed #cbd5e1;box-sizing:border-box;cursor:move;touch-action:none;user-select:none}
    .proposal-node:hover{border-color:#8b5cf6}.proposal-node.is-selected{border:2px solid #6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.18)}
    .resize-handle{position:absolute;right:-6px;bottom:-6px;width:13px;height:13px;border:2px solid white;background:#6366f1;border-radius:3px;cursor:nwse-resize}
    .node-actions{position:absolute;left:50%;bottom:-42px;transform:translateX(-50%);display:flex;gap:5px;z-index:1100}
    .node-actions button{width:30px;height:30px;border-radius:999px;background:#334155;color:white;box-shadow:0 3px 10px rgba(15,23,42,.25)}
</style>
</div>

@script
<script>
    Alpine.data('editorProposta', (blocosIniciais, catalogo, seleccionadoInicial, paginasIniciais) => ({
        varsAbertas: false,
        activo: null,
        aArrastar: null,
        alvo: null,
        blocos: JSON.parse(JSON.stringify(blocosIniciais || [])),
        catalogo,
        seleccionado: seleccionadoInicial,
        totalPaginas: Math.max(1, paginasIniciais || 1),
        zoom: .68,
        grelha: true,
        encaixar: true,
        historico: [],
        futuro: [],
        gesto: null,
        timerGuardar: null,

        init() {
            this.guardarEstado();
            window.addEventListener('pointermove', e => this.moverPonteiro(e));
            window.addEventListener('pointerup', () => this.terminarGesto());
        },

        sincronizarDoServidor(detalhe) {
            const d=Array.isArray(detalhe) ? detalhe[0] : detalhe;
            if(!d?.blocos) return;
            this.blocos=JSON.parse(JSON.stringify(d.blocos));
            this.totalPaginas=Math.max(1,Number(d.paginas||1));
            this.seleccionado=d.seleccionado||null;
            this.guardarEstado();
        },

        nomeTipo(tipo) { return this.catalogo?.[tipo]?.nome || tipo; },
        resumo(item) {
            if (item.tipo === 'imagem') return item.legenda || item.url || 'Imagem por escolher';
            if (item.tipo === 'dados_cliente') return 'Cliente exemplo, Lda · NIF 5000000000';
            if (item.tipo === 'itens') return item.titulo || 'Tabela de itens do orçamento';
            if (item.tipo === 'totais') return 'Subtotal · Imposto · Total';
            if (item.tipo === 'assinaturas') return 'Assinaturas';
            if (item.tipo === 'quebra') return 'Quebra de página';
            return String(item.titulo || item.texto || item.rotulo || (item.html || '').replace(/<[^>]*>/g,' ') || this.nomeTipo(item.tipo)).trim();
        },
        estilo(item) {
            const l = item.layout || {};
            return `left:${l.x||0}px;top:${l.y||0}px;width:${l.largura||300}px;height:${l.altura||100}px;z-index:${l.z||1}`;
        },
        seleccionarCanvas(item) { this.seleccionado = item.id; this.$wire.seleccionar(item.id); },
        snap(v) { return this.encaixar ? Math.round(v / 10) * 10 : Math.round(v); },
        iniciarMover(e,item) {
            this.seleccionarCanvas(item);
            if (item.layout?.bloqueado) return;
            this.gesto={tipo:'mover',item,sx:e.clientX,sy:e.clientY,x:item.layout.x,y:item.layout.y};
        },
        iniciarResize(e,item) {
            this.gesto={tipo:'resize',item,sx:e.clientX,sy:e.clientY,w:item.layout.largura,h:item.layout.altura};
        },
        moverPonteiro(e) {
            if (!this.gesto) return;
            const g=this.gesto, dx=(e.clientX-g.sx)/this.zoom, dy=(e.clientY-g.sy)/this.zoom;
            if (g.tipo==='mover') {
                g.item.layout.x=Math.max(0,Math.min(794-g.item.layout.largura,this.snap(g.x+dx)));
                g.item.layout.y=Math.max(0,Math.min(1123-g.item.layout.altura,this.snap(g.y+dy)));
            } else {
                g.item.layout.largura=Math.max(40,Math.min(794-g.item.layout.x,this.snap(g.w+dx)));
                g.item.layout.altura=Math.max(28,Math.min(1123-g.item.layout.y,this.snap(g.h+dy)));
            }
        },
        terminarGesto() {
            if (!this.gesto) return;
            const item=this.gesto.item; this.gesto=null; this.persistir(item); this.guardarEstado();
        },
        persistir(item) {
            clearTimeout(this.timerGuardar);
            this.timerGuardar=setTimeout(()=>this.$wire.actualizarLayout(item.id,item.layout),180);
        },
        actualizarGeometria(id,campo,valor) {
            const item=this.blocos.find(b=>b.id===id); if(!item) return;
            item.layout[campo]=campo==='bloqueado' ? !!valor : Number(valor);
            if(campo==='pagina') this.totalPaginas=Math.max(this.totalPaginas,item.layout.pagina);
            this.persistir(item); this.guardarEstado();
        },
        alternarBloqueio(item) { item.layout.bloqueado=!item.layout.bloqueado; this.persistir(item); },
        guardarEstado() {
            const estado=JSON.stringify(this.blocos.map(b=>({id:b.id,layout:b.layout})));
            if(this.historico.at(-1)!==estado) this.historico.push(estado);
            if(this.historico.length>60) this.historico.shift();
        },
        aplicarEstado(estado) {
            const layouts=JSON.parse(estado); layouts.forEach(s=>{const b=this.blocos.find(x=>x.id===s.id);if(b)b.layout=s.layout});
            layouts.forEach(s=>{const b=this.blocos.find(x=>x.id===s.id);if(b)this.$wire.actualizarLayout(b.id,b.layout)});
        },
        desfazer() { if(this.historico.length<2)return;this.futuro.push(this.historico.pop());this.aplicarEstado(this.historico.at(-1)); },
        refazer() { if(!this.futuro.length)return;const s=this.futuro.pop();this.historico.push(s);this.aplicarEstado(s); },
        atalho(e) {
            if(['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)||e.target.isContentEditable)return;
            if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='z'){e.preventDefault();return e.shiftKey?this.refazer():this.desfazer()}
            const item=this.blocos.find(b=>b.id===this.seleccionado);if(!item||item.layout?.bloqueado)return;
            const d=e.shiftKey?10:1; let mudou=true;
            if(e.key==='ArrowLeft')item.layout.x=Math.max(0,item.layout.x-d);else if(e.key==='ArrowRight')item.layout.x=Math.min(794-item.layout.largura,item.layout.x+d);else if(e.key==='ArrowUp')item.layout.y=Math.max(0,item.layout.y-d);else if(e.key==='ArrowDown')item.layout.y=Math.min(1123-item.layout.altura,item.layout.y+d);else mudou=false;
            if(mudou){e.preventDefault();this.persistir(item)}
        },

        // Texto rico sem biblioteca. execCommand está marcado como obsoleto
        // mas continua a ser o único caminho que todos os browsers suportam
        // sem carregar nada — e o alvo aqui é texto de proposta, não um
        // editor de código.
        cmd(comando, valor = null) {
            if (this.activo) this.activo.focus();
            document.execCommand(comando, false, valor);
            this.emitir();
        },

        inserir(texto) {
            if (this.activo) this.activo.focus();
            document.execCommand('insertText', false, texto);
            this.emitir();
        },

        emitir() {
            if (this.$refs.rico) {
                this.$wire.actualizarCampo('html', this.$refs.rico.innerHTML);
            }
        },

        // Reordenar por arrastar, sem biblioteca: guarda-se de onde se saiu e
        // por cima de quem se está, e no largar manda-se a ordem nova inteira.
        arrastar(id) { this.aArrastar = id; },
        sobre(id) { this.alvo = id; },
        cancelar() { this.aArrastar = null; this.alvo = null; },

        largar() {
            if (!this.aArrastar || !this.alvo || this.aArrastar === this.alvo) return this.cancelar();

            const ids = [...this.$el.querySelectorAll('[draggable="true"]')]
                .map(e => e.getAttribute('wire:key').replace('ord-', ''));

            const de = ids.indexOf(this.aArrastar);
            const para = ids.indexOf(this.alvo);
            if (de < 0 || para < 0) return this.cancelar();

            ids.splice(para, 0, ids.splice(de, 1)[0]);
            this.$wire.reordenar(ids);
            this.cancelar();
        },
    }));
</script>
@endscript
