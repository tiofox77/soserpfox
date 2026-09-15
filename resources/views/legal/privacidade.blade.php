@extends('layouts.legal')

@section('titulo', 'Política de Privacidade')
@section('descricao', 'Que dados pessoais o SOSERP recolhe (incluindo IP, localização e morada), para quê, com que fundamento, durante quanto tempo e como exercer os seus direitos — Lei n.º 22/11 de Angola, RGPD e LGPD.')
@section('atualizado', config('privacidade.actualizada_em'))

@php
    $responsavel = config('privacidade.responsavel');
    $categorias = \App\Services\Privacidade\InventarioDeDados::categorias();
    $direitos = \App\Services\Privacidade\InventarioDeDados::direitos();
    $prazo = (int) config('privacidade.prazo_de_resposta_dias', 30);
@endphp

@section('conteudo')

<p>
    Esta Política explica como a <strong>{{ $responsavel['nome'] }}</strong> trata dados pessoais no âmbito do
    <strong>SOSERP</strong>. Aplicamos a <strong>Lei n.º 22/11, de 17 de Junho — Lei de Protecção de Dados
    Pessoais</strong> de Angola e, a quem esteja na União Europeia ou no Brasil, também o
    <strong>Regulamento Geral sobre a Protecção de Dados (RGPD, Regulamento UE 2016/679)</strong> e a
    <strong>Lei Geral de Protecção de Dados (LGPD, Lei n.º 13.709/2018)</strong>. Onde as regras diferem, seguimos
    a mais protectora para si.
</p>

<div class="destaque">
    <p><strong><i class="fas fa-shield-halved"></i> Em resumo:</strong> não vendemos dados; o IP e a localização
    das visitas ao site só se guardam com o seu consentimento (e o IP sempre truncado); os seus dados podem ser
    vistos, descarregados e o apagamento pedido em <strong>Minha conta → Privacidade</strong>; respondemos a
    qualquer pedido em até <strong>{{ $prazo }} dias</strong>.</p>
</div>

<h2>1. Quem é o responsável — e os dois papéis</h2>
<p>
    Responsável pelo tratamento: <strong>{{ $responsavel['nome'] }}</strong>, {{ $responsavel['morada'] }}.
    Contacto para protecção de dados: <a href="mailto:{{ $responsavel['email'] }}">{{ $responsavel['email'] }}</a>.
</p>
<ul>
    <li>
        <strong>Como responsáveis pelo tratamento</strong> — pelos dados da <strong>conta</strong>: quem se regista,
        a empresa subscritora, a subscrição, os acessos ao serviço e as visitas ao site.
    </li>
    <li>
        <strong>Como subcontratantes</strong> (operador, na LGPD) — pelos <strong>dados que o cliente
        introduz</strong> no sistema: os clientes, fornecedores, trabalhadores, hóspedes ou pacientes dele. Esses
        dados são do cliente; tratamo-los por conta e segundo instruções dele, que é o responsável e deve ter
        fundamento legal para os recolher.
    </li>
</ul>

<h2>2. Que dados recolhemos, para quê e durante quanto tempo</h2>
<p>
    Esta lista é a mesma que cada utilizador vê sobre si próprio em <strong>Minha conta → Privacidade</strong>.
    Inclui, em concreto, <strong>o endereço IP, a localização aproximada, a morada, o aparelho e o browser</strong>
    — onde e porquê cada um é guardado.
</p>

<div class="fichas">
    @foreach($categorias as $c)
        <section class="ficha">
            <h3><i class="fas {{ $c['icone'] }}"></i>{{ $c['titulo'] }}</h3>
            <ul>
                @foreach($c['dados'] as $dado)
                    <li>{{ $dado }}</li>
                @endforeach
            </ul>
            <dl>
                <dt>Para quê</dt><dd>{{ $c['finalidade'] }}</dd>
                <dt>Fundamento</dt><dd>{{ $c['base_legal'] }}</dd>
                <dt>Quanto tempo</dt><dd>{{ $c['retencao'] }}</dd>
                <dt>Quem recebe</dt><dd>{{ $c['destinatarios'] }}</dd>
            </dl>
        </section>
    @endforeach
</div>

<h3>2.1 IP e localização, em pormenor</h3>
<ul>
    <li><strong>Segurança das contas</strong> — o IP completo de cada entrada, saída e tentativa falhada fica
        registado. É o que permite bloquear um ataque: <strong>5 tentativas falhadas bloqueiam a entrada durante
        10 minutos</strong>, e 20 falhas do mesmo IP também. Fundamento: interesse legítimo na segurança.</li>
    <li><strong>Visitas ao site</strong> — sem consentimento: a visita conta-se <strong>sem cookie, sem IP e
        sem cidade</strong> (só o país, dado pelo nosso fornecedor de rede). Com consentimento de estatísticas:
        guardamos o IP <strong>truncado</strong> (por exemplo 102.140.12.<strong>0</strong>), que deixa de
        identificar a sua ligação, e dele deduzimos a cidade aproximada através do serviço ip-api.com. O IP truncado
        apaga-se ao fim de {{ config('privacidade.retencao.analytics_ip') }} dias.</li>
    <li><strong>Não usamos a localização GPS</strong> do seu aparelho.</li>
    <li><strong>Morada</strong> — a da empresa, porque a lei fiscal a exige nos documentos. Das pessoas, só se as
        próprias empresas a registarem sobre os clientes delas (caso em que são elas as responsáveis).</li>
</ul>

<h3>2.2 Dados introduzidos pelo cliente</h3>
<p>
    Os que o cliente decidir registar no exercício da sua actividade. <strong>Não os usamos para fins
    próprios.</strong>
</p>

<h2>3. Decisões automatizadas</h2>
<p>
    Não tomamos decisões com efeitos jurídicos sobre si baseadas apenas em tratamento automatizado. Os
    bloqueios de segurança por tentativas falhadas são temporários (10 minutos) e podem ser ultrapassados
    recuperando a palavra-passe ou contactando-nos.
</p>

<h2>4. Com quem partilhamos</h2>
<ul>
    <li><strong>Administração Geral Tributária (AGT)</strong> — os elementos dos documentos fiscais, por
        imposição legal;</li>
    <li><strong>Fornecedores de infra-estrutura</strong> (alojamento e cópias de segurança);</li>
    <li><strong>Fornecedores de comunicações</strong> (email e SMS), que recebem apenas o destinatário e a
        mensagem;</li>
    <li><strong>Google</strong> (Analytics) e <strong>ip-api.com</strong> — só com o seu consentimento de
        estatísticas;</li>
    <li><strong>Meta Platforms</strong> (Facebook/Instagram) e <strong>Google Ads</strong> — só com o seu
        consentimento de marketing;</li>
    <li><strong>Autoridades públicas</strong>, quando legalmente exigido.</li>
</ul>
<p><strong>Não vendemos dados pessoais.</strong></p>

<h2>5. Transferências internacionais</h2>
<p>
    Alguns destes fornecedores podem tratar dados fora de Angola, da União Europeia ou do Brasil (por exemplo
    nos Estados Unidos). Nesses casos recorremos às garantias previstas na lei aplicável — decisões de
    adequação, cláusulas contratuais-tipo ou o seu consentimento explícito — e limitamos o que é transferido ao
    mínimo necessário.
</p>
<p>
    Na versão <strong>local</strong> do SOSERP os dados ficam no equipamento do cliente, com excepção de
    informação mínima de licenciamento (identificador do equipamento, versão e data de contacto).
</p>

<h2>6. Cookies</h2>
<p>
    Usamos cookies estritamente necessários (sessão, protecção de formulários, a sua escolha) e, só com
    autorização, cookies de estatísticas e de marketing. A lista completa, com a duração de cada um, está na
    <a href="{{ route('legal.cookies') }}">Política de Cookies</a>.
</p>
<p><a href="#" data-abrir-consentimento class="botao-doc"><i class="fas fa-sliders"></i> Mudar as minhas preferências de cookies</a></p>

<h2>7. Segurança</h2>
<ul>
    <li>Ligações cifradas (HTTPS) e cabeçalhos de segurança;</li>
    <li>Palavras-passe e PINs guardados com algoritmos de <em>hash</em> — <strong>nunca legíveis</strong>; senhas
        com pelo menos 8 caracteres, letras e números;</li>
    <li>Bloqueio de 10 minutos após 5 tentativas de entrada falhadas; limites contra pedidos em massa no registo
        e na recuperação de senha, sem revelar que emails têm conta;</li>
    <li>Ao redefinir a palavra-passe, as sessões abertas noutros aparelhos terminam;</li>
    <li>Separação lógica dos dados de cada empresa e controlo de acessos por perfis e permissões;</li>
    <li>Trilha de auditoria encadeada, que não permite reescrita silenciosa;</li>
    <li>Cópias de segurança periódicas.</li>
</ul>
<p>
    Em caso de violação de dados com risco para os seus direitos, informaremos a autoridade de controlo e as
    pessoas afectadas nos prazos que a lei aplicável impõe.
</p>

<h2>8. Os seus direitos</h2>
<div class="tabela">
    <table>
        <thead><tr><th>Direito</th><th>O que significa</th><th>Onde está na lei</th></tr></thead>
        <tbody>
            @foreach($direitos as $d)
                <tr>
                    <td><strong><i class="fas {{ $d['icone'] }}" style="color:#ea580c;margin-right:.35rem"></i>{{ $d['nome'] }}</strong></td>
                    <td>{{ $d['descricao'] }}</td>
                    <td>{{ $d['artigos'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<h3>8.1 Como exercer</h3>
<ul>
    <li><strong>Com conta:</strong> em <strong>Minha conta → Privacidade</strong> vê os seus dados, descarrega uma
        cópia em JSON, muda os consentimentos, termina sessões noutros aparelhos e faz pedidos de rectificação,
        apagamento, oposição ou limitação;</li>
    <li><strong>Sem conta:</strong> por email para <a href="mailto:{{ $responsavel['email'] }}">{{ $responsavel['email'] }}</a>.</li>
</ul>
<p>
    Respondemos em até <strong>{{ $prazo }} dias</strong>. Podemos pedir-lhe que confirme a sua identidade.
    O apagamento não abrange o que a lei nos obriga a conservar — por exemplo documentos fiscais e os registos
    que os comprovam; nesses casos dizemos-lhe o que fica, porquê e até quando.
</p>
<p>
    <strong>Nota:</strong> se os seus dados estão no SOSERP porque uma empresa nossa cliente os registou (é
    cliente ou trabalhador dessa empresa), o pedido deve ser dirigido <strong>a essa empresa</strong>. Se nos
    chegar, encaminhamo-lo.
</p>
<p>
    Pode ainda reclamar junto da <strong>Agência de Protecção de Dados (APD)</strong> de Angola, da
    <strong>Autoridade Nacional de Proteção de Dados (ANPD)</strong> do Brasil ou da autoridade de controlo do
    seu país na União Europeia.
</p>

<h2>9. Menores</h2>
<p>
    O SOSERP destina-se a utilização profissional e não é dirigido a menores. Não recolhemos conscientemente
    dados de menores para contas de utilizador.
</p>

<h2>10. Alterações</h2>
<p>
    Esta Política pode ser actualizada; a data consta no topo. Quando uma alteração mudar o que pedimos
    com consentimento, voltamos a pedi-lo.
</p>

<h2>11. Contactos</h2>
<p>
    {{ $responsavel['nome'] }} — {{ $responsavel['morada'] }}<br>
    Protecção de dados: <a href="mailto:{{ $responsavel['email'] }}">{{ $responsavel['email'] }}</a><br>
    Site: <a href="https://soserp.vip">soserp.vip</a>
</p>

@endsection
