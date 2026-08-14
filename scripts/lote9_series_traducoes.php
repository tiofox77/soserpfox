<?php

/**
 * Traduções dos avisos e rótulos das séries documentais.
 *
 * Notas que não são óbvias:
 *
 *   · os códigos de documento (FT, FR, PR, NC, ND, RC, FC, AD, GT) e o erro
 *     E32 são da AGT e não se traduzem — traduzi-los tornava-os inválidos;
 *
 *   · "Fatura-Recibo" é o documento angolano pago no acto. Em inglês
 *     Invoice-Receipt e em francês Facture-reçu, que é como já está no resto
 *     do dicionário — a coerência aqui vale mais do que a elegância;
 *
 *   · "[TIPO] [SÉRIE]/[NÚMERO]" é um molde e as chavetas são o que o
 *     utilizador vê; traduzem-se as palavras, mantém-se a forma;
 *
 *   · SAFT fica SAFT.
 *
 * Uso:  php scripts/lote9_series_traducoes.php
 */

$raiz = dirname(__DIR__);

$en = [
    '(vazio)'  => '(empty)',
    'vazio'    => 'empty',
    ':prefixo = Fatura-Recibo (fixo, do catálogo AGT)' => ':prefixo = Invoice-Receipt (fixed, from the AGT catalogue)',
    'Adiantamento'  => 'Advance',
    'ainda por criar' => 'not created yet',
    'Assim sai o primeiro número:' => 'The first number comes out like this:',
    'A série :nova está por estrear (próximo número :nova_proximo), mas :em_uso já vai no :em_uso_proximo.'
        => 'Series :nova has not been used yet (next number :nova_proximo), but :em_uso is already at :em_uso_proximo.',
    'A série padrão :padrao está por estrear (próximo :proximo), mas :outra já vai no :numero. O que emitir a seguir sai pela padrão e começa uma segunda numeração em paralelo.'
        => 'The default series :padrao has not been used yet (next :proximo), but :outra is already at :numero. Whatever you issue next goes out on the default series and starts a second numbering in parallel.',
    'Continuar mesmo assim' => 'Continue anyway',
    'Definir :serie como série padrão para :tipo?' => 'Set :serie as the default series for :tipo?',
    'Depois vem a série e, a seguir à barra, a numeração sequencial.'
        => 'Then comes the series and, after the slash, the sequential number.',
    'Documento interno — não se comunica à AGT' => 'Internal document — not reported to AGT',
    'Duas numerações a andar ao mesmo tempo' => 'Two numberings running at the same time',
    'Esta série está gravada com :gravado, mas o catálogo AGT deste tipo de documento é :esperado. É o valor gravado que entra no número do documento.'
        => 'This series is stored as :gravado, but the AGT catalogue for this document type says :esperado. It is the stored value that goes into the document number.',
    'Fatura-Recibo (POS)'  => 'Invoice-Receipt (POS)',
    'Fatura-Recibo POS'    => 'POS Invoice-Receipt',
    'Fatura de Compra'     => 'Purchase Invoice',
    'Fatura de Venda'      => 'Sales Invoice',
    'Isto abre uma segunda numeração' => 'This opens a second numbering',
    'O prefixo deste tipo de documento é fixado pela AGT (:prefixo) e não pode ser outro.'
        => 'The prefix for this document type is fixed by AGT (:prefixo) and cannot be anything else.',
    'O primeiro bloco é o tipo de documento: é por ele que a AGT classifica o documento, e um valor fora do catálogo faz a submissão ser recusada.'
        => 'The first block is the document type: it is what AGT uses to classify the document, and a value outside the catalogue gets the submission rejected.',
    'Outras séries'        => 'Other series',
    'Prefixo AGT (fixado pelo tipo de documento)' => 'AGT prefix (fixed by document type)',
    'Prefixo errado: :gravado — a AGT espera :esperado' => 'Wrong prefix: :gravado — AGT expects :esperado',
    'Proforma'             => 'Proforma',
    'Proforma de Venda'    => 'Sales Proforma',
    'Próximo número desta série:' => 'Next number in this series:',
    'Se continuar, tudo o que emitir a seguir sai pela nova série e começa a contar do início, ao lado da numeração que já existe. Duas sequências do mesmo tipo de documento no mesmo exercício não passam num SAFT.'
        => 'If you continue, everything you issue from now on goes out on the new series and starts counting from the beginning, alongside the numbering that already exists. Two sequences of the same document type in the same financial year do not pass in a SAFT.',
    'Se é mesmo uma mudança de série — no início do ano, por exemplo — pode continuar.'
        => 'If this really is a change of series — at the start of the year, for instance — you can continue.',
    'Tipo de documento desconhecido' => 'Unknown document type',
    'Tipo de documento desconhecido (:tipo). A série não foi criada.'
        => 'Unknown document type (:tipo). The series was not created.',
    'Tipos de documento que não têm cartão próprio acima' => 'Document types without a card of their own above',
    '[TIPO] [SÉRIE]/[NÚMERO]' => '[TYPE] [SERIES]/[NUMBER]',
    'É o primeiro bloco do número que a AGT lê para classificar o documento. Com este valor, a submissão é recusada (erro E32).'
        => 'It is the first block of the number that AGT reads to classify the document. With this value, the submission is rejected (error E32).',
];

$fr = [
    '(vazio)'  => '(vide)',
    'vazio'    => 'vide',
    ':prefixo = Fatura-Recibo (fixo, do catálogo AGT)' => ':prefixo = Facture-reçu (fixe, issu du catalogue AGT)',
    'Adiantamento'  => 'Acompte',
    'ainda por criar' => 'pas encore créée',
    'Assim sai o primeiro número:' => 'Voici à quoi ressemble le premier numéro :',
    'A série :nova está por estrear (próximo número :nova_proximo), mas :em_uso já vai no :em_uso_proximo.'
        => 'La série :nova n\'a pas encore servi (numéro suivant :nova_proximo), mais :em_uso en est déjà à :em_uso_proximo.',
    'A série padrão :padrao está por estrear (próximo :proximo), mas :outra já vai no :numero. O que emitir a seguir sai pela padrão e começa uma segunda numeração em paralelo.'
        => 'La série par défaut :padrao n\'a pas encore servi (suivant :proximo), mais :outra en est déjà à :numero. Tout ce que vous émettrez ensuite sortira sur la série par défaut et démarrera une seconde numérotation en parallèle.',
    'Continuar mesmo assim' => 'Continuer quand même',
    'Definir :serie como série padrão para :tipo?' => 'Définir :serie comme série par défaut pour :tipo ?',
    'Depois vem a série e, a seguir à barra, a numeração sequencial.'
        => 'Vient ensuite la série puis, après la barre oblique, la numérotation séquentielle.',
    'Documento interno — não se comunica à AGT' => 'Document interne — non déclaré à l\'AGT',
    'Duas numerações a andar ao mesmo tempo' => 'Deux numérotations en cours en même temps',
    'Esta série está gravada com :gravado, mas o catálogo AGT deste tipo de documento é :esperado. É o valor gravado que entra no número do documento.'
        => 'Cette série est enregistrée avec :gravado, alors que le catalogue AGT de ce type de document indique :esperado. C\'est la valeur enregistrée qui entre dans le numéro du document.',
    'Fatura-Recibo (POS)'  => 'Facture-reçu (POS)',
    'Fatura-Recibo POS'    => 'Facture-reçu POS',
    'Fatura de Compra'     => 'Facture d\'achat',
    'Fatura de Venda'      => 'Facture de vente',
    'Isto abre uma segunda numeração' => 'Cela ouvre une seconde numérotation',
    'O prefixo deste tipo de documento é fixado pela AGT (:prefixo) e não pode ser outro.'
        => 'Le préfixe de ce type de document est fixé par l\'AGT (:prefixo) et ne peut pas être autre.',
    'O primeiro bloco é o tipo de documento: é por ele que a AGT classifica o documento, e um valor fora do catálogo faz a submissão ser recusada.'
        => 'Le premier bloc est le type de document : c\'est par lui que l\'AGT classe le document, et une valeur hors catalogue fait rejeter la soumission.',
    'Outras séries'        => 'Autres séries',
    'Prefixo AGT (fixado pelo tipo de documento)' => 'Préfixe AGT (fixé par le type de document)',
    'Prefixo errado: :gravado — a AGT espera :esperado' => 'Préfixe erroné : :gravado — l\'AGT attend :esperado',
    'Proforma'             => 'Proforma',
    'Proforma de Venda'    => 'Proforma de vente',
    'Próximo número desta série:' => 'Numéro suivant de cette série :',
    'Se continuar, tudo o que emitir a seguir sai pela nova série e começa a contar do início, ao lado da numeração que já existe. Duas sequências do mesmo tipo de documento no mesmo exercício não passam num SAFT.'
        => 'Si vous continuez, tout ce que vous émettrez ensuite sortira sur la nouvelle série et repartira de zéro, à côté de la numérotation existante. Deux séquences du même type de document sur le même exercice ne passent pas dans un SAFT.',
    'Se é mesmo uma mudança de série — no início do ano, por exemplo — pode continuar.'
        => 'S\'il s\'agit vraiment d\'un changement de série — en début d\'année, par exemple — vous pouvez continuer.',
    'Tipo de documento desconhecido' => 'Type de document inconnu',
    'Tipo de documento desconhecido (:tipo). A série não foi criada.'
        => 'Type de document inconnu (:tipo). La série n\'a pas été créée.',
    'Tipos de documento que não têm cartão próprio acima' => 'Types de document sans carte dédiée ci-dessus',
    '[TIPO] [SÉRIE]/[NÚMERO]' => '[TYPE] [SÉRIE]/[NUMÉRO]',
    'É o primeiro bloco do número que a AGT lê para classificar o documento. Com este valor, a submissão é recusada (erro E32).'
        => 'C\'est le premier bloc du numéro que l\'AGT lit pour classer le document. Avec cette valeur, la soumission est rejetée (erreur E32).',
];

foreach (['en' => $en, 'fr' => $fr] as $lingua => $novas) {
    $caminho = "{$raiz}/lang/{$lingua}.json";
    $dicionario = json_decode(file_get_contents($caminho), true) ?: [];

    $acrescentadas = 0;

    foreach ($novas as $pt => $traduzida) {
        if (isset($dicionario[$pt])) {
            continue;
        }

        $dicionario[$pt] = $traduzida;
        $acrescentadas++;
    }

    ksort($dicionario, SORT_NATURAL | SORT_FLAG_CASE);

    file_put_contents(
        $caminho,
        json_encode($dicionario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );

    printf("lang/%s.json: +%d (total %d)\n", $lingua, $acrescentadas, count($dicionario));
}
