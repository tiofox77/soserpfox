<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Licença — soserp</title>
    <style>
        /* Página autónoma de propósito: tem de funcionar com o sistema
           bloqueado, sem depender do CSS/nav da aplicação. */
        :root { --fg:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f8fafc; --card:#fff; --brand:#0ea5e9; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--fg); }
        .wrap { max-width:680px; margin:6vh auto; padding:0 20px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:28px; box-shadow:0 10px 30px rgba(2,8,23,.06); }
        h1 { margin:0 0 4px; font-size:22px; }
        .sub { color:var(--muted); margin:0 0 20px; font-size:14px; }
        table { width:100%; border-collapse:collapse; margin:8px 0 20px; font-size:14px; }
        td { padding:8px 10px; border-bottom:1px solid var(--line); }
        td:first-child { color:var(--muted); width:42%; }
        .badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .b-ativa { background:#dcfce7; color:#166534; }
        .b-aviso { background:#fef9c3; color:#854d0e; }
        .b-bloq  { background:#fee2e2; color:#991b1b; }
        .fp { font-family:ui-monospace,Consolas,monospace; font-size:12px; background:#f1f5f9; padding:6px 10px; border-radius:8px; display:inline-block; }
        textarea { width:100%; min-height:120px; padding:12px; border:1px solid var(--line); border-radius:10px; font-family:ui-monospace,Consolas,monospace; font-size:12px; resize:vertical; }
        label { display:block; font-size:13px; font-weight:600; margin:16px 0 6px; }
        button { margin-top:14px; background:var(--brand); color:#fff; border:0; padding:11px 20px; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px; }
        button:hover { filter:brightness(.95); }
        .flash { padding:12px 14px; border-radius:10px; margin-bottom:16px; font-size:14px; }
        .flash-ok  { background:#dcfce7; color:#166534; }
        .flash-err { background:#fee2e2; color:#991b1b; }
        .hint { color:var(--muted); font-size:12px; margin-top:8px; }
        input { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px; font-size:14px; }
        .grelha { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .col2 { grid-column:1 / -1; }
        @media (max-width:560px) { .grelha { grid-template-columns:1fr; } }
        .abas { display:flex; gap:8px; margin:22px 0 14px; border-bottom:1px solid var(--line); }
        .aba { background:none; color:var(--muted); border:0; border-bottom:2px solid transparent;
               padding:10px 4px; margin:0 14px 0 0; font-weight:600; font-size:14px; cursor:pointer; border-radius:0; }
        .aba.activa { color:var(--brand); border-bottom-color:var(--brand); }
        .pedido { background:#f1f5f9; border:1px solid var(--line); border-radius:12px; padding:14px; margin-bottom:6px; font-size:14px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Licença do soserp</h1>
        <p class="sub">Estado da instalação e activação de licença.</p>

        @if (session('ok'))   <div class="flash flash-ok">{{ session('ok') }}</div>   @endif
        @if (session('erro')) <div class="flash flash-err">{{ session('erro') }}</div> @endif

        @php
            $classe = match($estado->estado) {
                \App\Services\Licensing\LicenseState::ATIVA => 'b-ativa',
                \App\Services\Licensing\LicenseState::AVISO,
                \App\Services\Licensing\LicenseState::BANNER,
                \App\Services\Licensing\LicenseState::SO_LEITURA => 'b-aviso',
                default => 'b-bloq',
            };
        @endphp

        <table>
            <tr><td>Estado</td><td><span class="badge {{ $classe }}">{{ strtoupper($estado->estado) }}</span></td></tr>
            <tr><td>Detalhe</td><td>{{ $estado->motivo }}</td></tr>
            @if($estado->payload)
                <tr><td>Empresa</td><td>{{ $estado->payload->empresa() ?? '—' }}</td></tr>
                <tr><td>Plano</td><td>{{ $estado->payload->plano() ?? '—' }}</td></tr>
                <tr><td>Módulos</td><td>{{ implode(', ', $estado->payload->modulos()) ?: '—' }}</td></tr>
                <tr><td>Dias para expirar</td><td>{{ $estado->diasParaExpirar ?? '—' }}</td></tr>
                <tr><td>Dias offline</td><td>{{ $estado->diasOffline ?? '—' }} / {{ $estado->gracaDias ?? '—' }}</td></tr>
            @endif
            <tr><td>Esta máquina</td><td><span class="fp">{{ $fingerprint }}</span></td></tr>
            <tr>
                <td>Ligação ao fornecedor</td>
                <td>
                    @if($ligacao['ok'])
                        <span class="badge b-ativa">LIGADO</span>
                    @elseif($ligacao['estado'] === 'sem_configuracao')
                        <span class="badge b-bloq">NÃO CONFIGURADO</span>
                    @else
                        <span class="badge b-aviso">SEM LIGAÇÃO</span>
                    @endif
                    <div class="hint" style="margin-top:4px">
                        {{ $ligacao['mensagem'] }}
                        @if($ligacao['url'])
                            <br><span class="fp" style="font-size:11px">{{ parse_url($ligacao['url'], PHP_URL_HOST) }}</span>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        @if(session('aviso'))
            <div class="flash" style="background:#fef9c3;color:#854d0e">{{ session('aviso') }}</div>
        @endif

        {{-- Estado do pedido já feito --}}
        @if($pedido)
            <div class="pedido">
                @if(!empty($pedido['codigo']))
                    <p><strong>Pedido enviado ao fornecedor.</strong><br>
                       Código: <span class="fp">{{ $pedido['codigo'] }}</span></p>
                    <form method="POST" action="{{ route('licenca.verificar') }}" style="margin-top:10px">
                        @csrf
                        <button type="submit">Já foi aprovado? Verificar agora</button>
                    </form>
                @elseif(!empty($pedido['pacote']))
                    <p><strong>O pedido não foi enviado automaticamente.</strong>
                       @if(!empty($pedido['motivo']))
                           <br><span style="color:#854d0e">Motivo: {{ $pedido['motivo'] }}</span>
                       @endif
                       <br>Envie este código por email ou WhatsApp para receber a licença:</p>
                    <textarea readonly onclick="this.select()" style="min-height:80px">{{ $pedido['pacote'] }}</textarea>
                @endif
            </div>
        @endif

        @php
            // Com uma licença a funcionar não se pede outra: mostra-se só a
            // substituição (renovação) e o caminho para entrar.
            $licencaBoa = !$estado->bloqueiaTudo();
        @endphp

        @if($licencaBoa)
            <div class="pedido" style="background:#dcfce7;border-color:#bbf7d0">
                <p style="margin:0"><strong>Esta instalação está licenciada.</strong>
                   Pode entrar no sistema — este ecrã serve agora só para substituir a licença.</p>
                <p style="margin:10px 0 0">
                    <a href="/login" style="display:inline-block;background:var(--brand);color:#fff;
                       padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:700">Entrar no soserp</a>
                </p>
            </div>
        @endif

        {{-- Separadores: pedir vs instalar --}}
        <div class="abas">
            @unless($licencaBoa)
                <button type="button" class="aba activa" onclick="mostrar('pedir', this)">Solicitar licença</button>
            @endunless
            <button type="button" class="aba {{ $licencaBoa ? 'activa' : '' }}" onclick="mostrar('instalar', this)">
                {{ $licencaBoa ? 'Substituir licença' : 'Já tenho uma licença' }}
            </button>
        </div>

        <div id="pedir" @if($licencaBoa) style="display:none" @endif>
            <form method="POST" action="{{ route('licenca.solicitar') }}">
                @csrf
                <div class="grelha">
                    <div class="col2">
                        <label for="empresa">Nome da empresa *</label>
                        <input id="empresa" name="empresa" value="{{ old('empresa') }}" required>
                        @error('empresa') <div class="hint" style="color:#991b1b">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="nif">NIF</label>
                        <input id="nif" name="nif" value="{{ old('nif') }}">
                    </div>
                    <div>
                        <label for="utilizadores">Nº de utilizadores</label>
                        <input id="utilizadores" name="utilizadores" type="number" min="1" value="{{ old('utilizadores', 5) }}">
                    </div>
                    <div>
                        <label for="responsavel">Responsável</label>
                        <input id="responsavel" name="responsavel" value="{{ old('responsavel') }}">
                    </div>
                    <div>
                        <label for="telefone">Telefone</label>
                        <input id="telefone" name="telefone" value="{{ old('telefone') }}">
                    </div>
                    <div class="col2">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}">
                    </div>
                    <div class="col2">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" style="min-height:60px">{{ old('observacoes') }}</textarea>
                    </div>
                </div>
                <button type="submit">Solicitar licença ao fornecedor</button>
                <p class="hint">
                    @if($temServidor)
                        O pedido segue para o fornecedor com a impressão desta máquina. Se não houver internet,
                        é gerado um código para enviar por outra via.
                    @else
                        Sem servidor configurado: será gerado um código para enviar ao fornecedor.
                    @endif
                </p>
            </form>
        </div>

        <div id="instalar" @unless($licencaBoa) style="display:none" @endunless>
            @if($temServidor)
                {{-- O fornecedor não consegue bater à porta desta máquina (está
                     numa rede local). Quem puxa é ela — este botão puxa já. --}}
                <form method="POST" action="{{ route('licenca.sincronizar') }}" style="margin-bottom:14px">
                    @csrf
                    <button type="submit">Sincronizar com o fornecedor agora</button>
                    <p class="hint">
                        Vai buscar a licença mais recente (prazo, módulos, bloqueios). É o mesmo que a
                        instalação faz sozinha de tempos a tempos — este botão não espera.
                    </p>
                </form>
            @endif

            <form method="POST" action="{{ route('licenca.guardar') }}">
                @csrf
                <label for="token">Instalar / substituir licença</label>
                <textarea id="token" name="token" placeholder="Cole aqui o token da licença (SOSERP-LIC.v1....)">{{ old('token') }}</textarea>
                @error('token') <div class="hint" style="color:#991b1b">{{ $message }}</div> @enderror
                <button type="submit">Instalar licença</button>
                <p class="hint">A licença é verificada localmente, sem internet.</p>
            </form>
        </div>

        <script>
            function mostrar(qual, botao) {
                document.getElementById('pedir').style.display = (qual === 'pedir') ? '' : 'none';
                document.getElementById('instalar').style.display = (qual === 'instalar') ? '' : 'none';
                document.querySelectorAll('.aba').forEach(function (b) { b.classList.remove('activa'); });
                botao.classList.add('activa');
            }
        </script>
    </div>
</div>
</body>
</html>
