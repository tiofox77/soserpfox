{{--
    Correcções de composição para o DomPDF — SÓ quando se está a gerar PDF.

    Estes modelos servem duas coisas:

      · a pré-visualização em /preview, que é o MESMO blade servido como HTML
        ao browser. É a que se usa no dia-a-dia.
      · o PDF, gerado pelo DomPDF.

    O que o browser precisa e o que o DomPDF precisa são opostos, e foi por
    isso que aplicar estas regras aos dois partiu a pré-visualização: o rodapé
    sobe ao fundo com margin-top:auto dentro de uma coluna flex, e ao desligar
    o flex ficava a meio da página.

    Tentou-se pô-las dentro de @media print, para o browser as ignorar. Não
    serve: o DomPDF também as ignora aí — medido, os documentos voltaram a sair
    em duas páginas. É a mesma razão pela qual o @page dentro de @media print
    nunca chegava a ser aplicado.

    Por isso quem CHAMA é que decide. Os controladores que geram PDF passam
    `paraPdf => true`; a pré-visualização não passa nada e fica intacta.

    Ao mexer aqui, confirme os dois: um PDF de cada tipo (tem de sair numa
    página) e a pré-visualização no browser (rodapé em baixo).
--}}
@if($paraPdf ?? false)
<style>
    /*
     * A caixa tinha 210 mm de largura fixa e as margens da página vinham por
     * omissão (cerca de 25 mm de cada lado). Num papel de 210 mm, 210 + 50 não
     * cabe: o que excedia caía fora e empurrava o resto para outra folha.
     */
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

    /* display:flex não existe no DomPDF. Duas colunas fazem-se com tabela. */
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
@endif
