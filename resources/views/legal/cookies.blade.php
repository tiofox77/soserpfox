@extends('layouts.legal')

@section('titulo', 'Política de Cookies')
@section('descricao', 'Que cookies o SOSERP usa, para quê, durante quanto tempo, e como escolher quais autoriza.')
@section('atualizado', config('privacidade.actualizada_em'))

@php
    $categorias = config('privacidade.categorias');
    $icones = ['necessarios' => 'fa-shield-halved', 'estatisticas' => 'fa-chart-line', 'marketing' => 'fa-bullhorn'];
@endphp

@section('conteudo')

<p>
    Um <strong>cookie</strong> é um pequeno ficheiro que o site guarda no seu browser. Pela Directiva europeia
    ePrivacy, pelo RGPD, pela LGPD e pela Lei n.º 22/11, <strong>só os estritamente necessários</strong> podem ser
    usados sem o seu consentimento. Todos os outros esperam pela sua escolha — e recusar é tão fácil como aceitar.
</p>

<div class="destaque">
    <p><strong><i class="fas fa-sliders"></i> A sua escolha, a qualquer momento.</strong> Pode aceitar tudo,
    ficar só com os necessários, ou escolher categoria a categoria. Ao retirar uma autorização, apagamos os
    cookies dessa categoria deste browser.</p>
    <p><a href="#" data-abrir-consentimento class="botao-doc"><i class="fas fa-cookie-bite"></i> Abrir as preferências de cookies</a></p>
</div>

@foreach($categorias as $chave => $c)
    <h2><i class="fas {{ $icones[$chave] ?? 'fa-cookie' }}" style="color:#ea580c;margin-right:.4rem"></i>{{ $c['nome'] }}</h2>
    <p>{{ $c['descricao'] }}</p>
    <p>
        @if($chave === 'necessarios')
            <strong>Sempre activos.</strong> Sem eles não é possível entrar nem enviar formulários.
        @else
            <strong>Só com o seu consentimento.</strong>
        @endif
    </p>
    <div class="tabela">
        <table>
            <thead><tr><th>Cookie</th><th>Para quê</th><th>Duração</th></tr></thead>
            <tbody>
                @foreach($c['cookies'] as $k)
                    <tr><td><code>{{ $k['nome'] }}</code></td><td>{{ $k['finalidade'] }}</td><td>{{ $k['duracao'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

<h2>Sem consentimento de estatísticas</h2>
<p>
    Continuamos a contar a visita — que página, de que país, em que tipo de aparelho — mas <strong>sem guardar
    nenhum cookie nem identificador no seu browser, sem o seu IP e sem a sua cidade</strong>. Não conseguimos saber
    se duas visitas são da mesma pessoa.
</p>

<h2>Bloquear no browser</h2>
<p>
    Também pode bloquear ou apagar cookies nas definições do browser. Se bloquear os necessários, não conseguirá
    iniciar sessão.
</p>

<h2>Mais informação</h2>
<p>
    Que outros dados tratamos e como exercer os seus direitos: <a href="{{ route('legal.privacidade') }}">Política
    de Privacidade</a>. Dúvidas: <a href="mailto:{{ config('privacidade.responsavel.email') }}">{{ config('privacidade.responsavel.email') }}</a>.
</p>

@endsection
