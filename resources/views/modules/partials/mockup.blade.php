{{-- SVG mockups realistas por módulo. Variável $slug deve estar disponível. --}}
@php $slug = $slug ?? 'vendas'; @endphp

<div class="rounded-2xl bg-white shadow-2xl overflow-hidden border border-white/20" style="aspect-ratio: 16/10;">
    {{-- Barra de janela --}}
    <div class="bg-gray-100 px-3 py-2 flex items-center gap-1.5 border-b">
        <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
        <span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
        <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
        <span class="ml-3 text-[10px] text-gray-500 font-mono">app.soserp.vip/{{ $slug }}</span>
    </div>

    {{-- Conteúdo por módulo --}}
    @switch($slug)
        @case('vendas')
            <svg viewBox="0 0 500 280" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                {{-- Sidebar --}}
                <rect x="0" y="0" width="80" height="280" fill="#1e293b"/>
                <circle cx="40" cy="30" r="14" fill="#ea580c"/>
                <text x="40" y="35" text-anchor="middle" font-size="14" fill="white" font-weight="bold">$</text>
                @foreach(['fa-home','fa-cart-shopping','fa-box','fa-users','fa-chart-bar'] as $i => $ic)
                    <rect x="15" y="{{ 65 + $i * 35 }}" width="50" height="28" rx="6" fill="{{ $i === 1 ? '#ea580c' : '#334155' }}"/>
                    <circle cx="30" cy="{{ 79 + $i * 35 }}" r="5" fill="white" opacity="0.8"/>
                    <rect x="40" y="{{ 76 + $i * 35 }}" width="20" height="6" rx="2" fill="white" opacity="0.6"/>
                @endforeach

                {{-- Top bar --}}
                <rect x="80" y="0" width="420" height="40" fill="white"/>
                <rect x="100" y="13" width="120" height="14" rx="7" fill="#e2e8f0"/>
                <circle cx="470" cy="20" r="12" fill="#ea580c"/>
                <text x="470" y="25" text-anchor="middle" font-size="11" fill="white" font-weight="bold">JS</text>

                {{-- Título --}}
                <text x="100" y="62" font-size="14" font-weight="bold" fill="#0f172a">POS — Ponto de Venda</text>

                {{-- Grid de produtos (lado esquerdo) --}}
                @for($r = 0; $r < 2; $r++)
                    @for($c = 0; $c < 3; $c++)
                        @php
                            $x = 100 + $c * 80;
                            $y = 80 + $r * 70;
                            $colors = ['#fed7aa','#fecaca','#fde68a','#bbf7d0','#bae6fd','#ddd6fe'];
                            $color = $colors[($r * 3 + $c) % 6];
                        @endphp
                        <rect x="{{ $x }}" y="{{ $y }}" width="72" height="62" rx="8" fill="white" stroke="#e2e8f0"/>
                        <rect x="{{ $x + 8 }}" y="{{ $y + 6 }}" width="56" height="32" rx="4" fill="{{ $color }}"/>
                        <circle cx="{{ $x + 36 }}" cy="{{ $y + 22 }}" r="8" fill="white" opacity="0.7"/>
                        <rect x="{{ $x + 10 }}" y="{{ $y + 44 }}" width="40" height="4" rx="2" fill="#94a3b8"/>
                        <rect x="{{ $x + 10 }}" y="{{ $y + 52 }}" width="30" height="5" rx="2" fill="#ea580c"/>
                    @endfor
                @endfor

                {{-- Carrinho à direita --}}
                <rect x="350" y="50" width="140" height="220" rx="10" fill="#f8fafc" stroke="#e2e8f0"/>
                <text x="360" y="68" font-size="11" font-weight="bold" fill="#0f172a">Carrinho (3)</text>

                @foreach([['T-Shirt', '2', '8.500'],['Café', '5', '2.500'],['Bolacha', '1', '350']] as $i => $itm)
                    <g transform="translate(360, {{ 80 + $i * 35 }})">
                        <rect x="0" y="0" width="120" height="28" rx="5" fill="white" stroke="#e2e8f0"/>
                        <circle cx="14" cy="14" r="8" fill="#fed7aa"/>
                        <text x="26" y="13" font-size="8" font-weight="bold" fill="#0f172a">{{ $itm[0] }}</text>
                        <text x="26" y="22" font-size="7" fill="#64748b">Qtd: {{ $itm[1] }}</text>
                        <text x="115" y="18" text-anchor="end" font-size="8" font-weight="bold" fill="#ea580c">{{ $itm[2] }}</text>
                    </g>
                @endforeach

                {{-- Total --}}
                <rect x="360" y="200" width="120" height="40" rx="6" fill="url(#vendaTotal)"/>
                <text x="370" y="218" font-size="9" fill="white" opacity="0.85">TOTAL</text>
                <text x="475" y="218" text-anchor="end" font-size="13" font-weight="bold" fill="white">21.350 Kz</text>
                <text x="370" y="232" font-size="7" fill="white" opacity="0.7">+ IVA 14%</text>

                <rect x="360" y="246" width="120" height="20" rx="5" fill="#10b981"/>
                <text x="420" y="259" text-anchor="middle" font-size="9" font-weight="bold" fill="white">✓ Finalizar Venda</text>

                <defs>
                    <linearGradient id="vendaTotal" x1="0" x2="1" y1="0" y2="0">
                        <stop offset="0" stop-color="#ea580c"/>
                        <stop offset="1" stop-color="#dc2626"/>
                    </linearGradient>
                </defs>
            </svg>
            @break

        @case('rh')
            <svg viewBox="0 0 500 280" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                {{-- Header --}}
                <rect x="0" y="0" width="500" height="50" fill="white"/>
                <text x="20" y="30" font-size="14" font-weight="bold" fill="#0f172a">Recursos Humanos</text>
                <rect x="380" y="15" width="100" height="22" rx="11" fill="#7c3aed"/>
                <text x="430" y="29" text-anchor="middle" font-size="9" fill="white" font-weight="bold">+ Funcionário</text>

                {{-- KPI Cards --}}
                @foreach([
                    ['icon' => 'fa-users','label' => 'Colaboradores','val' => '47','color' => '#7c3aed'],
                    ['icon' => 'fa-calendar','label' => 'Em Férias','val' => '3','color' => '#0891b2'],
                    ['icon' => 'fa-coins','label' => 'Folha Mês','val' => '12.4M','color' => '#10b981'],
                    ['icon' => 'fa-cake','label' => 'Aniversários','val' => '2','color' => '#ec4899'],
                ] as $i => $kpi)
                    <g transform="translate({{ 15 + $i * 120 }}, 60)">
                        <rect x="0" y="0" width="110" height="65" rx="10" fill="white" stroke="#e2e8f0"/>
                        <circle cx="20" cy="20" r="12" fill="{{ $kpi['color'] }}" opacity="0.15"/>
                        <circle cx="20" cy="20" r="6" fill="{{ $kpi['color'] }}"/>
                        <text x="40" y="22" font-size="8" fill="#64748b">{{ $kpi['label'] }}</text>
                        <text x="20" y="50" font-size="16" font-weight="bold" fill="#0f172a">{{ $kpi['val'] }}</text>
                    </g>
                @endforeach

                {{-- Tabela folha de pagamento --}}
                <text x="15" y="148" font-size="11" font-weight="bold" fill="#0f172a">Folha de Pagamento — Out/2025</text>
                <rect x="380" y="135" width="60" height="18" rx="9" fill="#10b98115"/>
                <text x="410" y="147" text-anchor="middle" font-size="8" fill="#10b981" font-weight="bold">✓ APROVADA</text>

                {{-- Cabeçalho tabela --}}
                <rect x="15" y="158" width="470" height="22" rx="4" fill="#f8fafc"/>
                <text x="25" y="172" font-size="8" font-weight="bold" fill="#64748b">COLABORADOR</text>
                <text x="220" y="172" font-size="8" font-weight="bold" fill="#64748b">SALÁRIO BASE</text>
                <text x="320" y="172" font-size="8" font-weight="bold" fill="#64748b">IRT + INSS</text>
                <text x="420" y="172" font-size="8" font-weight="bold" fill="#64748b">LÍQUIDO</text>

                {{-- Linhas --}}
                @foreach([
                    ['MS','Maria Santos','250.000','-43.250','206.750','#ef4444'],
                    ['JP','João Pereira','180.000','-29.880','150.120','#3b82f6'],
                    ['AF','Ana Ferreira','320.000','-58.880','261.120','#10b981'],
                    ['CR','Carlos Reis','150.000','-23.250','126.750','#f59e0b'],
                ] as $i => $row)
                    <g transform="translate(0, {{ 185 + $i * 22 }})">
                        <circle cx="25" cy="11" r="9" fill="{{ $row[5] }}"/>
                        <text x="25" y="14" text-anchor="middle" font-size="7" font-weight="bold" fill="white">{{ $row[0] }}</text>
                        <text x="40" y="14" font-size="8" fill="#0f172a">{{ $row[1] }}</text>
                        <text x="220" y="14" font-size="8" fill="#0f172a" font-weight="500">{{ $row[2] }} Kz</text>
                        <text x="320" y="14" font-size="8" fill="#dc2626">{{ $row[3] }} Kz</text>
                        <text x="420" y="14" font-size="8" font-weight="bold" fill="#10b981">{{ $row[4] }} Kz</text>
                    </g>
                @endforeach
            </svg>
            @break

        @case('hotel')
            <svg viewBox="0 0 500 280" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                <rect x="0" y="0" width="500" height="45" fill="#0891b2"/>
                <text x="20" y="28" font-size="14" font-weight="bold" fill="white">🏨 Hotel Mar Azul — Dashboard</text>
                <rect x="380" y="13" width="100" height="20" rx="10" fill="white" opacity="0.2"/>
                <text x="430" y="26" text-anchor="middle" font-size="9" fill="white" font-weight="bold">Out 2025</text>

                {{-- Ocupação % --}}
                <g transform="translate(20, 60)">
                    <text x="0" y="12" font-size="9" fill="#64748b" font-weight="bold">OCUPAÇÃO</text>
                    <text x="0" y="40" font-size="28" font-weight="bold" fill="#0f172a">87<tspan font-size="14" fill="#64748b">%</tspan></text>
                    <rect x="0" y="48" width="100" height="6" rx="3" fill="#e2e8f0"/>
                    <rect x="0" y="48" width="87" height="6" rx="3" fill="#10b981"/>
                </g>
                <g transform="translate(150, 60)">
                    <text x="0" y="12" font-size="9" fill="#64748b" font-weight="bold">ADR</text>
                    <text x="0" y="40" font-size="22" font-weight="bold" fill="#0f172a">45.000 <tspan font-size="10" fill="#64748b">Kz</tspan></text>
                    <text x="0" y="55" font-size="8" fill="#10b981">↑ +12% vs mês anterior</text>
                </g>
                <g transform="translate(290, 60)">
                    <text x="0" y="12" font-size="9" fill="#64748b" font-weight="bold">RevPAR</text>
                    <text x="0" y="40" font-size="22" font-weight="bold" fill="#0f172a">39.150 <tspan font-size="10" fill="#64748b">Kz</tspan></text>
                    <text x="0" y="55" font-size="8" fill="#10b981">↑ +18%</text>
                </g>
                <g transform="translate(420, 58)">
                    <rect x="0" y="0" width="70" height="60" rx="8" fill="url(#hotelGr)"/>
                    <text x="35" y="24" text-anchor="middle" font-size="9" fill="white" opacity="0.85">Reservas</text>
                    <text x="35" y="45" text-anchor="middle" font-size="22" font-weight="bold" fill="white">142</text>
                </g>

                {{-- Mapa de quartos --}}
                <text x="20" y="145" font-size="11" font-weight="bold" fill="#0f172a">Mapa de Quartos — Piso 1</text>

                @for($r = 0; $r < 2; $r++)
                    @for($c = 0; $c < 10; $c++)
                        @php
                            $idx = $r * 10 + $c;
                            $states = ['#10b981','#10b981','#ef4444','#10b981','#f59e0b','#ef4444','#10b981','#10b981','#3b82f6','#ef4444',
                                       '#ef4444','#10b981','#f59e0b','#10b981','#ef4444','#10b981','#3b82f6','#ef4444','#10b981','#10b981'];
                            $col = $states[$idx];
                            $num = 101 + $idx;
                        @endphp
                        <rect x="{{ 20 + $c * 47 }}" y="{{ 155 + $r * 48 }}" width="42" height="42" rx="6" fill="{{ $col }}" opacity="0.15"/>
                        <rect x="{{ 20 + $c * 47 }}" y="{{ 155 + $r * 48 }}" width="42" height="6" rx="3" fill="{{ $col }}"/>
                        <text x="{{ 41 + $c * 47 }}" y="{{ 180 + $r * 48 }}" text-anchor="middle" font-size="11" font-weight="bold" fill="#0f172a">{{ $num }}</text>
                        <circle cx="{{ 41 + $c * 47 }}" cy="{{ 189 + $r * 48 }}" r="2" fill="{{ $col }}"/>
                    @endfor
                @endfor

                {{-- Legenda --}}
                <g transform="translate(20, 256)" font-size="8" fill="#64748b">
                    <circle cx="5" cy="5" r="4" fill="#10b981"/><text x="14" y="8">Livre</text>
                    <circle cx="60" cy="5" r="4" fill="#ef4444"/><text x="69" y="8">Ocupado</text>
                    <circle cx="135" cy="5" r="4" fill="#f59e0b"/><text x="144" y="8">Limpeza</text>
                    <circle cx="200" cy="5" r="4" fill="#3b82f6"/><text x="209" y="8">Check-out hoje</text>
                </g>

                <defs>
                    <linearGradient id="hotelGr" x1="0" x2="1">
                        <stop offset="0" stop-color="#0891b2"/>
                        <stop offset="1" stop-color="#2563eb"/>
                    </linearGradient>
                </defs>
            </svg>
            @break

        @case('salao')
            <svg viewBox="0 0 500 280" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                <rect x="0" y="0" width="500" height="40" fill="url(#salaoBar)"/>
                <text x="20" y="26" font-size="13" font-weight="bold" fill="white">💇 Agenda — Sexta-feira, 11 Out</text>
                <rect x="395" y="11" width="85" height="20" rx="10" fill="white" opacity="0.25"/>
                <text x="437" y="24" text-anchor="middle" font-size="9" fill="white" font-weight="bold">+ Novo</text>

                {{-- Colunas de profissionais --}}
                @php $pros = [
                    ['name'=>'Ana','color'=>'#db2777'],
                    ['name'=>'Sofia','color'=>'#9333ea'],
                    ['name'=>'Beatriz','color'=>'#f59e0b'],
                    ['name'=>'Carlos','color'=>'#0891b2'],
                ]; @endphp
                @foreach($pros as $i => $pro)
                    <g transform="translate({{ 60 + $i * 110 }}, 50)">
                        <rect x="0" y="0" width="100" height="220" rx="6" fill="#f8fafc"/>
                        <rect x="0" y="0" width="100" height="28" rx="6" fill="{{ $pro['color'] }}"/>
                        <circle cx="18" cy="14" r="8" fill="white"/>
                        <text x="18" y="17" text-anchor="middle" font-size="8" font-weight="bold" fill="{{ $pro['color'] }}">{{ substr($pro['name'],0,1) }}</text>
                        <text x="32" y="18" font-size="9" font-weight="bold" fill="white">{{ $pro['name'] }}</text>
                    </g>
                @endforeach

                {{-- Horas (eixo esquerdo) --}}
                @foreach(['09:00','10:00','11:00','12:00','13:00','14:00','15:00'] as $i => $h)
                    <text x="50" y="{{ 90 + $i * 28 }}" text-anchor="end" font-size="7" fill="#64748b">{{ $h }}</text>
                    <line x1="55" y1="{{ 87 + $i * 28 }}" x2="495" y2="{{ 87 + $i * 28 }}" stroke="#e2e8f0" stroke-width="0.5"/>
                @endforeach

                {{-- Agendamentos coloridos --}}
                @php $appts = [
                    [0, 0, 50, 'Corte', '#db2777'],
                    [0, 80, 56, 'Coloração', '#db2777'],
                    [1, 28, 40, 'Manicure', '#9333ea'],
                    [1, 80, 50, 'Penteado', '#9333ea'],
                    [2, 50, 60, 'Tratamento', '#f59e0b'],
                    [2, 140, 30, 'Corte', '#f59e0b'],
                    [3, 0, 40, 'Barba', '#0891b2'],
                    [3, 60, 50, 'Corte+Barba', '#0891b2'],
                    [3, 130, 30, 'Sobrancelhas', '#0891b2'],
                ]; @endphp
                @foreach($appts as $a)
                    <g transform="translate({{ 65 + $a[0] * 110 }}, {{ 90 + $a[1] }})">
                        <rect x="0" y="0" width="90" height="{{ $a[2] }}" rx="4" fill="{{ $a[4] }}" opacity="0.85"/>
                        <rect x="0" y="0" width="3" height="{{ $a[2] }}" fill="{{ $a[4] }}"/>
                        <text x="7" y="11" font-size="7" font-weight="bold" fill="white">{{ $a[3] }}</text>
                        @if($a[2] > 35)
                            <text x="7" y="22" font-size="6" fill="white" opacity="0.85">Cliente #{{ rand(100,999) }}</text>
                        @endif
                    </g>
                @endforeach

                <defs>
                    <linearGradient id="salaoBar" x1="0" x2="1">
                        <stop offset="0" stop-color="#db2777"/>
                        <stop offset="1" stop-color="#9333ea"/>
                    </linearGradient>
                </defs>
            </svg>
            @break

        @case('oficina')
            <svg viewBox="0 0 500 280" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                <rect x="0" y="0" width="500" height="42" fill="white"/>
                <text x="20" y="27" font-size="13" font-weight="bold" fill="#0f172a">🔧 Ordens de Reparação</text>
                <rect x="395" y="13" width="85" height="20" rx="10" fill="#ea580c"/>
                <text x="437" y="26" text-anchor="middle" font-size="9" fill="white" font-weight="bold">+ Nova OS</text>

                {{-- Filtros --}}
                @php $filters = [['Todas','12','#64748b'],['Em curso','5','#3b82f6'],['Aguarda peças','3','#f59e0b'],['Concluídas','4','#10b981']]; @endphp
                @foreach($filters as $i => $f)
                    <g transform="translate({{ 15 + $i * 110 }}, 50)">
                        <rect x="0" y="0" width="100" height="32" rx="6" fill="{{ $f[2] }}" opacity="0.1"/>
                        <text x="10" y="14" font-size="8" fill="{{ $f[2] }}">{{ $f[0] }}</text>
                        <text x="10" y="26" font-size="14" font-weight="bold" fill="{{ $f[2] }}">{{ $f[1] }}</text>
                    </g>
                @endforeach

                {{-- Cards de OS --}}
                @php $orders = [
                    ['OS-00347','LD-43-67-AA','Toyota Corolla','Em curso','#3b82f6','João M.','75'],
                    ['OS-00346','LD-12-89-BC','Hyundai i10','Aguarda peças','#f59e0b','Pedro F.','40'],
                    ['OS-00345','LD-99-44-XY','Ford Ranger','Em curso','#3b82f6','Carlos R.','90'],
                ]; @endphp
                @foreach($orders as $i => $o)
                    <g transform="translate(15, {{ 95 + $i * 60 }})">
                        <rect x="0" y="0" width="470" height="52" rx="8" fill="white" stroke="#e2e8f0"/>

                        {{-- Carro icon --}}
                        <rect x="10" y="10" width="44" height="32" rx="6" fill="#fed7aa"/>
                        <path d="M22 32 L22 27 Q22 23 26 23 L38 23 Q42 23 42 27 L42 32 L48 32 L48 35 L16 35 L16 32 Z" fill="#ea580c"/>
                        <circle cx="24" cy="35" r="2.5" fill="#1e293b"/>
                        <circle cx="40" cy="35" r="2.5" fill="#1e293b"/>

                        {{-- Info --}}
                        <text x="65" y="20" font-size="10" font-weight="bold" fill="#0f172a">{{ $o[0] }} · {{ $o[1] }}</text>
                        <text x="65" y="34" font-size="8" fill="#64748b">{{ $o[2] }} · Mecânico: {{ $o[5] }}</text>

                        {{-- Status pill --}}
                        <rect x="270" y="15" width="80" height="18" rx="9" fill="{{ $o[4] }}" opacity="0.15"/>
                        <circle cx="282" cy="24" r="3" fill="{{ $o[4] }}"/>
                        <text x="290" y="27" font-size="8" font-weight="bold" fill="{{ $o[4] }}">{{ $o[3] }}</text>

                        {{-- Progresso --}}
                        <text x="365" y="22" font-size="7" fill="#64748b">Progresso</text>
                        <rect x="365" y="26" width="80" height="6" rx="3" fill="#e2e8f0"/>
                        <rect x="365" y="26" width="{{ $o[6] * 0.8 }}" height="6" rx="3" fill="{{ $o[4] }}"/>
                        <text x="450" y="40" text-anchor="end" font-size="7" fill="#0f172a" font-weight="bold">{{ $o[6] }}%</text>
                    </g>
                @endforeach
            </svg>
            @break

        @case('restaurant')
            <svg viewBox="0 0 500 280" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                <rect width="500" height="46" fill="url(#restaurantHeader)"/>
                <text x="20" y="29" font-size="14" font-weight="bold" fill="white">🍽️ Sala Principal — Restaurante</text>
                <rect x="390" y="12" width="90" height="22" rx="11" fill="white" opacity=".22"/>
                <text x="435" y="26" text-anchor="middle" font-size="9" font-weight="bold" fill="white">+ Comanda</text>

                <text x="18" y="68" font-size="10" font-weight="bold" fill="#0f172a">Mapa de Mesas</text>
                @php $restaurantTables = [
                    ['M01','Ocupada','#f97316'],['M02','Livre','#10b981'],['M03','Cozinha','#8b5cf6'],
                    ['M04','Reservada','#3b82f6'],['M05','Conta','#eab308'],['VIP 1','Livre','#10b981'],
                ]; @endphp
                @foreach($restaurantTables as $i => $table)
                    @php $x=18+($i%3)*104; $y=78+intdiv($i,3)*78; @endphp
                    <g transform="translate({{$x}},{{$y}})">
                        <rect width="92" height="66" rx="13" fill="{{$table[2]}}" opacity=".13" stroke="{{$table[2]}}"/>
                        <circle cx="18" cy="19" r="9" fill="{{$table[2]}}"/>
                        <text x="18" y="22" text-anchor="middle" font-size="8" font-weight="bold" fill="white">4</text>
                        <text x="33" y="22" font-size="10" font-weight="bold" fill="#0f172a">{{$table[0]}}</text>
                        <text x="12" y="48" font-size="8" font-weight="bold" fill="{{$table[2]}}">{{$table[1]}}</text>
                    </g>
                @endforeach

                <rect x="338" y="58" width="146" height="198" rx="14" fill="#fff" stroke="#e2e8f0"/>
                <text x="352" y="80" font-size="11" font-weight="bold" fill="#0f172a">Cozinha / KDS</text>
                @foreach([['M03','Muamba','8m','#ef4444'],['M01','Peixe grelhado','4m','#f97316'],['VIP 2','Bebidas','2m','#10b981']] as $i => $ticket)
                    <g transform="translate(350,{{92+$i*47}})">
                        <rect width="122" height="39" rx="8" fill="#f8fafc" stroke="#e2e8f0"/>
                        <rect width="4" height="39" rx="2" fill="{{$ticket[3]}}"/>
                        <text x="12" y="14" font-size="8" font-weight="bold" fill="#0f172a">{{$ticket[0]}} · {{$ticket[2]}}</text>
                        <text x="12" y="28" font-size="8" fill="#64748b">{{$ticket[1]}}</text>
                    </g>
                @endforeach
                <rect x="350" y="232" width="122" height="16" rx="8" fill="#10b981"/>
                <text x="411" y="243" text-anchor="middle" font-size="8" font-weight="bold" fill="white">Tudo sincronizado</text>
                <defs><linearGradient id="restaurantHeader" x1="0" x2="1"><stop stop-color="#ea580c"/><stop offset="1" stop-color="#dc2626"/></linearGradient></defs>
            </svg>
            @break

        @default
            <div class="flex items-center justify-center h-full text-gray-300">
                <i class="fas fa-image text-6xl"></i>
            </div>
    @endswitch
</div>
