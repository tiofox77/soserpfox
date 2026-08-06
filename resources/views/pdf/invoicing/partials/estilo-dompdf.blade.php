{{--
    Correcções de composição para o DomPDF.

    Os modelos destes documentos são cópias uns dos outros e foram escritos
    para o browser. O PDF é feito pelo DomPDF, que não percebe metade do que
    lá está — e o resultado era o documento sair cortado à direita e a passar
    para uma segunda página em branco.

    Duas causas, iguais em todos:

      · a caixa tinha 210 mm de largura FIXA e as margens da página vinham por
        omissão (cerca de 25 mm de cada lado). Num papel de 210 mm, 210 + 50
        não cabe, e o que excedia caía fora. O `@page` que anulava essas
        margens estava declarado dentro de `@media print`, onde nunca chegava
        a ser aplicado.

      · `display: flex` não existe no DomPDF. Os blocos que dependiam dele —
        cabeçalho com logótipo à esquerda e QR à direita, resumo de impostos ao
        lado dos totais — desmontavam-se e empilhavam-se.

    Isto entra DEPOIS do estilo de cada modelo, de propósito: sobrepõe-se-lhe
    sem obrigar a reescrever seis ficheiros, e sai de cena se algum deles
    passar a ter folha própria.

    Ao mexer aqui, lembre-se de que muda TODOS os documentos fiscais ao mesmo
    tempo. Confirme com um PDF de cada tipo antes de dar por bom.
--}}
<style>
    @page {
        size: A4 portrait;
        margin: 10mm 12mm;
    }

    body {
        background: #fff;
        margin: 0;
        padding: 0;
        min-height: 0;
        display: block;
    }

    /* A página é o papel; a caixa ocupa o que ele deixa. */
    .page-wrapper {
        width: 100%;
        min-height: 0;
        max-height: none;
        margin: 0;
        padding: 0;
        display: block;
        box-shadow: none;
        overflow: visible;
    }

    .main-content {
        display: block;
    }

    /* Duas colunas: tabela, que é o que o DomPDF compõe. */
    .header-section,
    .bottom-section {
        display: table;
        width: 100%;
    }

    .company-info {
        display: table-cell;
        width: 62%;
        vertical-align: top;
    }

    .right-section {
        display: table-cell;
        width: 38%;
        vertical-align: top;
        text-align: right;
    }

    .left-bottom {
        display: table-cell;
        vertical-align: top;
        padding-right: 15px;
    }

    .right-bottom {
        display: table-cell;
        width: 200px;
        vertical-align: top;
    }

    /* Empilhados: o flex aqui só servia para afastar rótulo e valor. */
    .logo-section,
    .summary-row {
        display: block;
        overflow: hidden;
    }
</style>
