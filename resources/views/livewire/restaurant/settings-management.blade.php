<div class="min-h-screen bg-slate-100 p-3 sm:p-5"><div class="mx-auto max-w-6xl space-y-5">
<header><a href="{{route('restaurant.dashboard')}}" class="font-bold text-orange-600">← Restaurante</a><h1 class="mt-2 text-3xl font-black text-slate-900">Configurações do restaurante</h1><p class="text-slate-500">Operação, estabelecimentos, zonas, mesas e estações de produção.</p></header>
<section class="rounded-3xl bg-gradient-to-br from-slate-900 to-blue-950 p-6 text-white shadow"><div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-black uppercase tracking-widest text-orange-300">Ponto de integração</p><h2 class="mt-1 text-2xl font-black">Como o Restaurante comunica com o ERP</h2><p class="mt-2 max-w-3xl text-sm text-slate-300">O restaurante não cria dados isolados: usa os mesmos produtos, clientes, fornecedores, impostos, turnos, stock, faturação e tesouraria do SOSERP.</p></div><a href="{{route('restaurant.contacts')}}" class="rounded-xl bg-orange-500 px-5 py-3 font-black text-white"><i class="fas fa-address-book mr-2"></i>Clientes e fornecedores</a></div><div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach([['fa-cash-register','Turno e caixa','Obrigatório antes de abrir, receber ou faturar comandas.'],['fa-utensils','Cozinha / KDS','Envia os pratos às estações de preparação quando estiver ativo.'],['fa-boxes-stacked','Produtos e stock','Pratos vêm da Faturação; receitas reservam e consomem ingredientes.'],['fa-file-invoice-dollar','Faturação e tesouraria','Ao fechar, gera FR/FT fiscal e lança o recebimento no caixa ou banco.']] as $guide)<article class="rounded-2xl border border-white/10 bg-white/5 p-4"><i class="fas {{$guide[0]}} text-xl text-orange-300"></i><b class="mt-3 block">{{$guide[1]}}</b><p class="mt-1 text-xs leading-relaxed text-slate-300">{{$guide[2]}}</p></article>@endforeach</div></section>
<form wire:submit="save" class="rounded-3xl bg-white p-6 shadow"><h2 class="text-xl font-black">Operação e stock</h2><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="font-bold">Armazém de consumo<select wire:model="warehouseId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Nenhum</option>@foreach($warehouses as $w)<option value="{{$w->id}}">{{$w->name}}</option>@endforeach</select><span class="mt-1 block text-xs font-normal text-slate-500">De onde saem ingredientes e produtos vendidos.</span></label><label class="font-bold">Cliente padrão<select wire:model="clientId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Consumidor Final</option>@foreach($clients as $c)<option value="{{$c->id}}">{{$c->name}}</option>@endforeach</select><span class="mt-1 block text-xs font-normal text-slate-500">Usado quando não for identificado outro cliente no fecho.</span></label></div><div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"><i class="fas fa-shield-halved mr-2"></i><b>Turno e caixa obrigatórios:</b> nenhum utilizador consegue iniciar ou finalizar vendas sem o seu próprio turno aberto.</div><div class="mt-5 grid gap-3 sm:grid-cols-2">@foreach([['useKitchen','Usar cozinha / KDS'],['requireRecipes','Exigir ficha técnica nos pratos'],['reserveStock','Reservar stock ao confirmar'],['consumeStock','Consumir stock ao preparar/vender'],['allowNegative','Permitir stock negativo'],['kitchenAutoPrint','Imprimir talões da cozinha sozinho (arranque)']] as $o)<label class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 font-bold">{{$o[1]}}<input type="checkbox" wire:model="{{$o[0]}}" class="rounded text-orange-600"></label>@endforeach</div><div class="mt-4 rounded-2xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900"><b>Fluxo configurável:</b> com cozinha ativa, os artigos seguem para o KDS. Sem cozinha, o pedido fica pronto imediatamente para receber e faturar. A ficha técnica pode ser obrigatória ou os pratos podem ser vendidos diretamente como produtos simples.</div><div class="mt-5 rounded-2xl border border-slate-200 p-4"><h3 class="font-black text-slate-900">Taxa de serviço e gorjetas</h3><p class="mt-1 text-xs text-slate-500">São coisas diferentes: a <b>taxa de serviço</b> é cobrada pela casa, entra na factura e é tributada. A <b>gorjeta</b> é do pessoal — não entra na factura, mas é registada na caixa para o fecho do turno bater certo.</p><div class="mt-3 grid gap-4 sm:grid-cols-2"><label class="font-bold">Taxa de serviço (%)<input type="number" min="0" max="100" step="0.5" wire:model="serviceChargePercent" class="mt-2 w-full rounded-xl border-slate-300"><span class="mt-1 block text-xs font-normal text-slate-500">0 = não se cobra. Aplicada sobre os pratos, nunca sobre a taxa de entrega.</span></label><label class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 font-bold self-end">Aceitar gorjetas no fecho<input type="checkbox" wire:model="tipsEnabled" class="rounded text-orange-600"></label></div></div><button class="mt-5 rounded-xl bg-orange-600 px-6 py-3 font-black text-white">Guardar operação</button></form>

{{-- ============ MENU ONLINE ============
     A carta pública, e os QR das mesas.

     Está num formulário SEPARADO do de cima de propósito: publicar preços ao
     mundo é uma decisão diferente de escolher um armazém por omissão, e
     partilhar o botão de guardar fazia um clique numa caixa qualquer publicar
     a carta sem querer. --}}
<form wire:submit="guardarMenu" class="rounded-3xl bg-white p-6 shadow">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-black">{{ __('Menu online') }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('A carta que o cliente abre no telemóvel. Cole um QR em cada mesa e o pedido chega já a dizer de onde vem.') }}
            </p>
        </div>

        <label class="flex shrink-0 items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 font-bold">
            <input type="checkbox" wire:model.live="menuAtivo" class="rounded text-orange-600">
            {{ __('Carta publicada') }}
        </label>
    </div>

    @if(!$menuAtivo)
        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <i class="fas fa-eye-slash mr-2"></i>
            {{ __('A carta está fechada. Quem abrir o endereço vê uma página que não existe.') }}
        </div>
    @endif

    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <label class="font-bold">
            {{ __('Endereço da carta') }}
            <div class="mt-2 flex items-center rounded-xl border border-slate-300 bg-white px-3">
                <span class="shrink-0 text-xs text-slate-400">{{ rtrim(config('app.url'), '/') }}/menu/</span>
                <input wire:model="menuSlug" class="w-full min-w-0 border-0 py-2.5 focus:ring-0" placeholder="o-piteu">
            </div>
            @error('menuSlug')<span class="mt-1 block text-xs font-normal text-red-600">{{ $message }}</span>@enderror
            <span class="mt-1 block text-xs font-normal text-slate-500">
                {{ __('Só letras minúsculas, números e hífens. É único: não pode repetir o de outro restaurante.') }}
            </span>
        </label>

        <label class="font-bold">
            {{ __('Nome na carta') }}
            <input wire:model="menuTitulo" class="mt-2 w-full rounded-xl border-slate-300" placeholder="{{ __('O Pitéu') }}">
            <span class="mt-1 block text-xs font-normal text-slate-500">{{ __('O que aparece em grande no topo da página.') }}</span>
        </label>

        <label class="font-bold sm:col-span-2">
            {{ __('Descrição') }}
            <textarea wire:model="menuDescricao" rows="2" class="mt-2 w-full rounded-xl border-slate-300"
                      placeholder="{{ __('Cozinha angolana · Aberto todos os dias') }}"></textarea>
        </label>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <label class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 font-bold">
            {{ __('Mostrar preços na carta') }}
            <input type="checkbox" wire:model="menuMostrarPrecos" class="rounded text-orange-600">
        </label>

        <label class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 font-bold">
            {{ __('Pedidos por WhatsApp') }}
            <input type="checkbox" wire:model.live="menuWhatsapp" class="rounded text-orange-600">
        </label>
    </div>

    {{-- Pedir pela própria página. Fica separado e explicado porque muda quem
         inicia o pedido: deixa de ser alguém a mandar uma mensagem e passa a
         ser um registo que entra no ecrã da sala. --}}
    <label class="mt-3 flex items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4">
        <span>
            <span class="font-bold">{{ __('Aceitar pedidos pela página') }}</span>
            <span class="mt-1 block text-xs font-normal text-slate-500">
                {{ __('O cliente envia o pedido pelo QR da mesa e ele aparece no ecrã da sala. Um empregado aceita e a comanda abre com o turno dele — nada é vendido antes disso.') }}
            </span>
        </span>
        <input type="checkbox" wire:model="menuPedidos" class="mt-1 shrink-0 rounded text-orange-600">
    </label>

    @if($menuWhatsapp)
        <label class="mt-4 block font-bold">
            {{ __('Número que recebe os pedidos') }}
            <input wire:model="menuNumeroWhatsapp" class="mt-2 w-full rounded-xl border-slate-300" placeholder="+244 900 000 000">
            @error('menuNumeroWhatsapp')<span class="mt-1 block text-xs font-normal text-red-600">{{ $message }}</span>@enderror
            <span class="mt-1 block text-xs font-normal text-slate-500">
                {{ __('A mensagem sai pronta, com a mesa e os artigos. Quem lança a comanda continua a ser o restaurante.') }}
            </span>
        </label>
    @endif

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <button class="rounded-xl bg-orange-600 px-6 py-3 font-black text-white">{{ __('Guardar carta') }}</button>

        @if($this->urlDoMenu)
            <a href="{{ $this->urlDoMenu }}" target="_blank" rel="noopener"
               class="rounded-xl border-2 border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50">
                <i class="fas fa-up-right-from-square mr-2"></i>{{ __('Ver a carta') }}
            </a>

            {{-- Os QR abrem numa folha própria: é para imprimir, cortar e colar. --}}
            <a href="{{ route('restaurant.menu.qr') }}" target="_blank"
               class="rounded-xl border-2 border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50">
                <i class="fas fa-qrcode mr-2"></i>{{ __('QR das mesas (imprimir)') }}
            </a>
        @endif
    </div>
</form>
<section class="grid gap-5 lg:grid-cols-3"><div class="rounded-3xl bg-white p-5 shadow"><div class="flex items-start justify-between gap-3"><div><h2 class="font-black">Novo estabelecimento</h2><p class="mt-1 text-xs text-slate-500">Cada unidade física consome uma vaga da quota.</p></div><span class="shrink-0 rounded-full px-3 py-1 text-xs font-black {{$venues->count() >= $venueLimit ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700'}}">{{$venues->count()}} / {{$venueLimit}}</span></div>
@if($venues->count() < $venueLimit)
<form wire:submit="createVenue"><input wire:model="venueCode" class="mt-4 w-full rounded-xl border-slate-300" placeholder="Código"><input wire:model="venueName" class="mt-3 w-full rounded-xl border-slate-300" placeholder="Nome"><select wire:model="venueWarehouseId" class="mt-3 w-full rounded-xl border-slate-300"><option value="">Armazém padrão</option>@foreach($warehouses as $w)<option value="{{$w->id}}">{{$w->name}}</option>@endforeach</select><button class="mt-3 w-full rounded-xl bg-slate-900 p-3 font-black text-white">Criar estabelecimento</button></form>
@else
<div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4"><div class="flex gap-3"><i class="fas fa-lock mt-1 text-amber-600"></i><div><b class="text-amber-900">Limite atingido</b><p class="mt-1 text-sm text-amber-800">O seu acesso permite {{$venueLimit}} estabelecimento(s). Uma nova unidade exige autorização do administrador.</p></div></div></div>
@if($pendingVenueRequest)<div class="mt-3 rounded-xl bg-blue-50 p-3 text-sm font-bold text-blue-700"><i class="fas fa-clock mr-2"></i>Pedido pendente: aumento para {{$pendingVenueRequest->requested_limit}}.</div>@else<button type="button" wire:click="openVenueRequest" class="mt-3 w-full rounded-xl bg-orange-600 p-3 font-black text-white hover:bg-orange-500"><i class="fas fa-paper-plane mr-2"></i>Solicitar mais uma unidade</button>@endif
@endif
</div>
<form wire:submit="createArea" class="rounded-3xl bg-white p-5 shadow"><h2 class="font-black">Nova zona/sala</h2><select wire:model="selectedVenueId" class="mt-4 w-full rounded-xl border-slate-300">@foreach($venues as $v)<option value="{{$v->id}}">{{$v->name}}</option>@endforeach</select><input wire:model="areaName" class="mt-3 w-full rounded-xl border-slate-300" placeholder="Ex.: Esplanada"><button class="mt-3 w-full rounded-xl bg-slate-900 p-3 font-black text-white">Criar zona</button></form>
<form wire:submit="createStation" class="rounded-3xl bg-white p-5 shadow"><h2 class="font-black">Nova estação KDS</h2><select wire:model="selectedVenueId" class="mt-4 w-full rounded-xl border-slate-300">@foreach($venues as $v)<option value="{{$v->id}}">{{$v->name}}</option>@endforeach</select><div class="mt-3 grid grid-cols-3 gap-2"><input wire:model="stationCode" class="rounded-xl border-slate-300" placeholder="BAR"><input wire:model="stationName" class="col-span-2 rounded-xl border-slate-300" placeholder="Bar"></div><button class="mt-3 w-full rounded-xl bg-slate-900 p-3 font-black text-white">Criar estação</button></form></section>
<section class="rounded-3xl bg-white p-6 shadow"><h2 class="text-xl font-black">Estrutura operacional</h2><div class="mt-5 grid gap-4 lg:grid-cols-2">@forelse($venues as $venue)<article class="rounded-2xl border border-slate-200 p-4"><div class="flex justify-between gap-2"><div><h3 class="font-black">{{$venue->name}}</h3><p class="text-xs text-slate-500">{{$venue->code}} · {{$venue->areas->sum(fn($a)=>$a->tables->count())}} mesas</p></div><div class="flex gap-1"><button wire:click="startEdit('venue',{{$venue->id}})" class="rounded-lg bg-blue-50 px-2 text-blue-700"><i class="fas fa-pen"></i></button><button wire:click="toggle('venue',{{$venue->id}})" class="rounded-lg px-3 py-1 text-xs font-bold {{$venue->is_active?'bg-emerald-100 text-emerald-700':'bg-red-100 text-red-700'}}">{{$venue->is_active?'Ativo':'Inativo'}}</button><button wire:click="deleteRecord('venue',{{$venue->id}})" wire:confirm="Eliminar este estabelecimento?" class="rounded-lg bg-red-50 px-2 text-red-600"><i class="fas fa-trash"></i></button></div></div>@foreach($venue->areas as $area)<div class="mt-3 rounded-xl bg-slate-50 p-3"><div class="flex justify-between gap-2"><b>{{$area->name}}</b><div class="flex gap-2"><button wire:click="startEdit('area',{{$area->id}})" class="text-xs font-bold text-blue-600">Editar</button><button wire:click="toggle('area',{{$area->id}})" class="text-xs font-bold text-orange-600">{{$area->is_active?'Desativar':'Ativar'}}</button><button wire:click="deleteRecord('area',{{$area->id}})" wire:confirm="Eliminar esta zona?" class="text-xs font-bold text-red-600">Eliminar</button></div></div><div class="mt-2 flex flex-wrap gap-2">@foreach($area->tables as $table)<span class="inline-flex overflow-hidden rounded-lg border {{$table->is_active?'bg-white':'bg-red-50 text-red-600'}}"><button wire:click="toggle('table',{{$table->id}})" class="px-2 py-1 text-xs">{{$table->name}} · {{$table->capacity}}L</button><button wire:click="startEdit('table',{{$table->id}})" class="border-l px-2 text-xs text-blue-600"><i class="fas fa-pen"></i></button><button wire:click="deleteRecord('table',{{$table->id}})" wire:confirm="Eliminar esta mesa?" class="border-l px-2 text-xs text-red-600"><i class="fas fa-trash"></i></button></span>@endforeach</div></div>@endforeach</article>@empty<p class="text-slate-500">Crie o primeiro estabelecimento.</p>@endforelse</div></section>
<section class="rounded-3xl bg-white p-6 shadow"><h2 class="text-xl font-black">Estações de cozinha e bar</h2><div class="mt-4 flex flex-wrap gap-3">@foreach($stations as $station)<span class="inline-flex overflow-hidden rounded-xl border {{$station->is_active?'border-emerald-200 bg-emerald-50':'border-red-200 bg-red-50'}}"><button wire:click="toggle('station',{{$station->id}})" class="px-4 py-3 text-left"><b class="block">{{$station->name}}</b><span class="text-xs text-slate-500">{{$station->venue?->name}} · {{$station->code}}</span></button><button wire:click="startEdit('station',{{$station->id}})" class="border-l px-3 text-blue-600"><i class="fas fa-pen"></i></button><button wire:click="deleteRecord('station',{{$station->id}})" wire:confirm="Eliminar esta estação?" class="border-l px-3 text-red-600"><i class="fas fa-trash"></i></button></span>@endforeach</div></section>
@if($editType)<div class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/60 p-4"><form wire:submit="saveEdit" class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl"><div class="flex justify-between"><div><p class="text-xs font-black uppercase text-orange-600">Editar {{$editType}}</p><h2 class="text-2xl font-black">Atualizar registo</h2></div><button type="button" wire:click="cancelEdit" class="h-10 w-10 rounded-xl bg-slate-100">×</button></div><label class="mt-5 block font-bold">Nome<input wire:model="editName" class="mt-2 w-full rounded-xl border-slate-300"></label>@if(in_array($editType,['venue','table','station']))<label class="mt-3 block font-bold">Código<input wire:model="editCode" class="mt-2 w-full rounded-xl border-slate-300"></label>@endif @if($editType==='table')<label class="mt-3 block font-bold">Capacidade<input type="number" min="1" max="100" wire:model="editCapacity" class="mt-2 w-full rounded-xl border-slate-300"></label>@endif<div class="mt-5 flex gap-3"><button type="button" wire:click="cancelEdit" class="flex-1 rounded-xl bg-slate-100 p-3 font-black">Cancelar</button><button class="flex-1 rounded-xl bg-orange-600 p-3 font-black text-white">Guardar</button></div></form></div>@endif
@if($showVenueRequest)<div class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/60 p-4"><form wire:submit="requestVenueIncrease" class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl"><div class="flex justify-between gap-4"><div><p class="text-xs font-black uppercase text-orange-600">Aumento de quota</p><h2 class="text-2xl font-black text-slate-900">Solicitar estabelecimento</h2><p class="mt-1 text-sm text-slate-500">O super administrador analisará a necessidade e o plano contratado.</p></div><button type="button" wire:click="$set('showVenueRequest',false)" class="h-10 w-10 shrink-0 rounded-xl bg-slate-100">×</button></div><label class="mt-5 block font-bold">Total de estabelecimentos pretendido<input type="number" min="{{$venueLimit + 1}}" max="20" wire:model="requestedVenueLimit" class="mt-2 w-full rounded-xl border-slate-300"></label>@error('requestedVenueLimit')<p class="mt-1 text-sm text-red-600">{{$message}}</p>@enderror<label class="mt-4 block font-bold">Justificação <span class="font-normal text-slate-400">(opcional)</span><textarea wire:model="venueRequestReason" rows="4" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Ex.: abertura de uma segunda filial"></textarea></label><div class="mt-5 flex gap-3"><button type="button" wire:click="$set('showVenueRequest',false)" class="flex-1 rounded-xl bg-slate-100 p-3 font-black">Cancelar</button><button class="flex-1 rounded-xl bg-orange-600 p-3 font-black text-white">Enviar pedido</button></div></form></div>@endif
</div></div>
