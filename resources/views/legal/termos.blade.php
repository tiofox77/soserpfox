@extends('layouts.legal')

@section('titulo', 'Termos de Utilização')
@section('descricao', 'Termos e condições de utilização do SOSERP — software de gestão empresarial e facturação certificada para Angola.')

@section('conteudo')

<p>
    Estes Termos regulam a utilização do <strong>SOSERP</strong>, plataforma de gestão empresarial
    e facturação desenvolvida por <strong>Softec Angola</strong> ("nós"), disponível em
    <a href="https://soserp.vip">soserp.vip</a> e, na versão local, instalada nos equipamentos do
    cliente.
</p>
<p>
    Ao criar uma conta, instalar o software ou utilizar qualquer funcionalidade, o cliente
    ("utilizador") aceita estes Termos. Se não concordar, não deve utilizar o serviço.
</p>

<h2>1. O que é o serviço</h2>
<p>
    O SOSERP é um sistema de gestão com módulos de facturação, stock, tesouraria, compras,
    contabilidade, recursos humanos, ponto de venda (POS) e módulos sectoriais (restauração,
    hotelaria, salão, oficina, eventos, entre outros). Os módulos efectivamente disponíveis
    dependem do <strong>plano contratado</strong> e, na versão local, da <strong>licença
    emitida</strong>.
</p>
<p>É disponibilizado em duas formas:</p>
<ul>
    <li><strong>Nuvem (cloud):</strong> alojado por nós e acedido pela internet;</li>
    <li><strong>Local (on-premise):</strong> instalado no equipamento do cliente, funcionando sem
        ligação permanente à internet, mediante licença.</li>
</ul>

<h2>2. Conta e credenciais</h2>
<ul>
    <li>O cliente é responsável pela veracidade dos dados de registo, incluindo a
        <strong>designação social e o NIF</strong>.</li>
    <li>As credenciais são pessoais. O cliente responde por todas as acções praticadas com as
        contas da sua empresa e deve comunicar-nos de imediato qualquer uso indevido.</li>
    <li>O número de utilizadores está limitado ao plano ou licença contratada.</li>
</ul>

<h2>3. Planos, pagamento e renovação</h2>
<ul>
    <li>Os preços vigentes são os publicados no site à data da contratação, em Kwanzas (AOA).</li>
    <li>A subscrição é <strong>periódica</strong> e renova-se por períodos iguais, sendo emitida a
        respectiva factura.</li>
    <li>Em caso de <strong>falta de pagamento</strong>, a conta pode ser <strong>suspensa</strong>
        após aviso. A suspensão impede o acesso, mas <strong>não apaga os dados</strong>.</li>
    <li>Períodos de teste, quando oferecidos, terminam automaticamente na data indicada.</li>
</ul>

<h2>4. Facturação electrónica e obrigações fiscais</h2>
<p>
    O SOSERP integra a comunicação de documentos à <strong>Administração Geral Tributária
    (AGT)</strong> nos termos da legislação angolana aplicável à facturação electrónica.
</p>
<ul>
    <li>Os <strong>dados fiscais introduzidos são da responsabilidade do cliente</strong> —
        designadamente NIF, regime de tributação, taxas, isenções e o conteúdo dos documentos.</li>
    <li>Cabe ao cliente manter credenciais e certificados válidos junto da AGT e garantir que os
        documentos emitidos reflectem operações reais.</li>
    <li>Não somos responsáveis por coimas, correcções ou consequências resultantes de dados
        incorrectos introduzidos pelo cliente, nem por indisponibilidades dos sistemas da AGT.</li>
    <li>Quando não houver ligação, os documentos ficam <strong>em fila</strong> e são comunicados
        assim que a ligação for restabelecida.</li>
</ul>

<h2>5. Utilização aceitável</h2>
<p>O cliente compromete-se a não:</p>
<ul>
    <li>utilizar o serviço para fins ilícitos ou para emitir documentos que não correspondam a
        operações reais;</li>
    <li>tentar aceder a dados de outras empresas, contornar limites do plano ou os mecanismos de
        licenciamento;</li>
    <li>copiar, descompilar, modificar, revender ou sublicenciar o software;</li>
    <li>sobrecarregar deliberadamente a infra-estrutura ou automatizar acessos de forma abusiva.</li>
</ul>

<h2>6. Versão local e licenciamento</h2>
<ul>
    <li>A instalação local exige uma <strong>licença digital</strong> emitida por nós, podendo estar
        associada a um equipamento específico.</li>
    <li>A licença é <strong>de utilização</strong>, não de propriedade: é intransmissível sem o
        nosso acordo escrito.</li>
    <li>A instalação valida periodicamente a licença. Após o <strong>período de tolerância sem
        ligação</strong> definido na licença, o acesso é limitado até nova validação.</li>
    <li>A adulteração dos ficheiros do programa ou dos mecanismos de licença determina o bloqueio
        e pode implicar a cessação do contrato.</li>
</ul>

<h2>7. Disponibilidade e suporte</h2>
<p>
    Esforçamo-nos por manter o serviço disponível de forma contínua, mas <strong>não garantimos
    ausência total de interrupções</strong>. Podem ocorrer paragens para manutenção, actualização
    ou por causas alheias (falhas de energia, telecomunicações ou de terceiros).
</p>
<p>
    O suporte é prestado nos canais e horários publicados. Serviços de formação e assistência
    dedicada podem ser contratados à parte.
</p>

<h2>8. Actualizações</h2>
<p>
    O serviço é actualizado periodicamente. Nas instalações locais, as actualizações são
    <strong>assinadas digitalmente</strong> e podem ser disponibilizadas de forma faseada.
    Recomenda-se manter a versão actualizada — algumas actualizações são necessárias para cumprir
    alterações legais.
</p>

<h2>9. Dados do cliente</h2>
<ul>
    <li>Os dados introduzidos <strong>pertencem ao cliente</strong>.</li>
    <li>Tratamos esses dados apenas para prestar o serviço, nos termos da
        <a href="{{ route('legal.privacidade') }}">Política de Privacidade</a>.</li>
    <li>O cliente pode exportar os seus dados através das funcionalidades disponíveis.</li>
    <li><strong>Cópias de segurança:</strong> na nuvem realizamos cópias de segurança periódicas;
        na versão local, a responsabilidade pelas cópias é do cliente, sem prejuízo das cópias
        automáticas efectuadas pelo instalador antes de actualizações.</li>
</ul>

<h2>10. Propriedade intelectual</h2>
<p>
    O software, a marca, o código-fonte, a documentação e os materiais associados são propriedade
    da Softec Angola. Nada nestes Termos transfere direitos de propriedade intelectual para o
    cliente, sendo concedido apenas um direito de utilização durante a vigência do contrato.
</p>

<h2>11. Limitação de responsabilidade</h2>
<p>
    Na medida permitida pela lei, a nossa responsabilidade total perante o cliente está limitada ao
    <strong>valor pago nos 12 meses anteriores</strong> ao facto que a originou. Não respondemos por
    lucros cessantes, perda de oportunidades de negócio ou danos indirectos.
</p>
<p>
    Não respondemos por perdas resultantes de: dados incorrectos introduzidos pelo cliente, uso
    indevido de credenciais, falta de cópias de segurança na versão local, ou intervenções de
    terceiros nos equipamentos do cliente.
</p>

<h2>12. Vigência e cessação</h2>
<ul>
    <li>O contrato vigora enquanto a subscrição estiver activa.</li>
    <li>O cliente pode cancelar a qualquer momento; o cancelamento produz efeitos no fim do período
        já pago, não havendo lugar a reembolso de períodos iniciados, salvo imposição legal.</li>
    <li>Podemos suspender ou cessar o serviço em caso de incumprimento grave, uso ilícito ou falta
        de pagamento, precedido de aviso sempre que possível.</li>
    <li>Após a cessação, os dados ficam disponíveis para exportação durante um período razoável,
        findo o qual podem ser eliminados, salvo obrigação legal de conservação.</li>
</ul>

<h2>13. Alterações a estes Termos</h2>
<p>
    Podemos alterar estes Termos, por exemplo para reflectir alterações legais ou novas
    funcionalidades. As alterações relevantes serão comunicadas com antecedência razoável. A
    continuação da utilização após a entrada em vigor significa aceitação.
</p>

<h2>14. Lei aplicável e foro</h2>
<p>
    Estes Termos regem-se pela <strong>lei angolana</strong>. Para a resolução de litígios é
    competente o foro da comarca de <strong>Luanda</strong>, com renúncia a qualquer outro, sem
    prejuízo das normas imperativas de defesa do consumidor.
</p>

<h2>15. Contactos</h2>
<p>
    Softec Angola — Luanda, Angola<br>
    Site: <a href="https://soserp.vip">soserp.vip</a><br>
    Para questões sobre estes Termos, utilize os contactos publicados no site.
</p>

@endsection
