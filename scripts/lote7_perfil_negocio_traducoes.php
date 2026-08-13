<?php

/**
 * Traduções do perfil do negócio.
 *
 * O texto destes cartões é sobretudo explicação, não rótulos. Traduziu-se a
 * explicar, não à letra: uma frase que em português diz "ninguém se lembra de
 * como escreveu azul-marinho da última vez" tem de continuar a soar a alguém
 * que trabalha numa loja, e não a manual técnico.
 *
 * Uso:  php scripts/lote7_perfil_negocio_traducoes.php
 */

$raiz = dirname(__DIR__);

$en = [
    'Ainda sem cores registadas'    => 'No colours recorded yet',
    'Ainda sem tamanhos registados' => 'No sizes recorded yet',
    'Campos de medicamento abertos de raiz na ficha do artigo'
        => 'Medicine fields open by default on the item form',
    'Campos de tamanho, cor, género e composição'
        => 'Size, colour, gender and composition fields',
    'com os valores reais do catálogo: ninguém se lembra de como escreveu "azul-marinho" da última vez.'
        => 'with the real values from your catalogue: nobody remembers how they spelled "navy blue" last time.',
    'Desligar um perfil não esconde o que já está preenchido: um artigo que já tenha dosagem, tamanho ou cor continua a mostrar esses campos, para poderem ser vistos e corrigidos.'
        => 'Turning a profile off does not hide what is already filled in: an item that already has a strength, size or colour keeps showing those fields, so they can be seen and corrected.',
    'Diga com o que trabalha e o sistema mostra os campos certos por omissão. Pode ligar os dois: um supermercado com balcão de farmácia é as duas coisas.'
        => 'Tell us what you deal in and the system shows the right fields by default. You can turn on both: a supermarket with a pharmacy counter is both things.',
    'Este artigo também é medicamento' => 'This item is also a medicine',
    'Este artigo também é vestuário'   => 'This item is also clothing',
    'Este artigo é medicamento ou vestuário?' => 'Is this item a medicine or clothing?',
    'Farmácia, parafarmácia ou balcão de medicamentos'
        => 'Pharmacy, drugstore or medicines counter',
    'Filtro de "exige receita" na lista de artigos'
        => '"Prescription required" filter in the item list',
    'Filtros de tamanho e de cor na lista' => 'Size and colour filters in the list',
    'Lotes e validades com saída FIFO pela data de expiração, e o relatório de validade, dependem do rastreio de lotes de cada artigo — não deste perfil.'
        => 'Batches and expiry dates with FIFO by expiry date, and the expiry report, depend on each item\'s batch tracking — not on this profile.',
    'na ficha do artigo, para distinguir duas peças que se chamam igual.'
        => 'on the item form, to tell apart two pieces with the same name.',
    'O que fica activo' => 'What this turns on',
    'Os avisos do POS funcionam sempre.' => 'POS warnings always work.',
    'Perfil do Negócio' => 'Business Profile',
    'Procurar no POS pela substância activa também funciona sempre: quem chega com uma receita de paracetamol não sabe se a caixa diz Ben-u-ron ou Panadol.'
        => 'Searching the POS by active ingredient also always works: someone arriving with a prescription for paracetamol does not know whether the box says Ben-u-ron or Panadol.',
    'Procurar pelo tamanho no POS funciona sempre, com este perfil ligado ou desligado: "t-shirt" sozinho não chega para escolher; "t-shirt M" chega.'
        => 'Searching by size in the POS always works, with this profile on or off: "t-shirt" on its own is not enough to pick one; "t-shirt M" is.',
    'Receita médica e psicotrópico avisam com este perfil ligado ou desligado, porque seguem os dados do artigo e não esta definição. Uma protecção que se desliga numa definição de visualização não é protecção.'
        => 'Prescription and psychotropic warnings fire with this profile on or off, because they follow the item\'s data and not this setting. A safeguard that can be switched off in a display setting is not a safeguard.',
    'Roupa, calçado e acessórios' => 'Clothing, footwear and accessories',
    'Sem dados de medicamento neste artigo.' => 'No medicine details on this item.',
    'Sem dados de vestuário neste artigo.'   => 'No clothing details on this item.',
    'Sem nenhum perfil ligado o sistema funciona normalmente — é o caso da maioria das empresas. Na ficha do artigo estes campos ficam recolhidos atrás da pergunta "Este artigo é medicamento ou vestuário?", a um clique de distância para o caso isolado.'
        => 'With no profile on the system works as usual — which is the case for most companies. On the item form these fields sit behind the question "Is this item a medicine or clothing?", one click away for the odd exception.',
    'separa num clique o que não pode sair do balcão sem receita.'
        => 'tells apart, in one click, what cannot leave the counter without a prescription.',
    'substância activa, dosagem, forma farmacêutica e n.º ARMED, em vez de recolhidos atrás de uma pergunta.'
        => 'active ingredient, strength, pharmaceutical form and ARMED number, instead of tucked behind a question.',
    'Trabalha com medicamentos' => 'You deal in medicines',
    'Trabalha com vestuário'    => 'You deal in clothing',
];

$fr = [
    'Ainda sem cores registadas'    => 'Aucune couleur enregistrée pour l\'instant',
    'Ainda sem tamanhos registados' => 'Aucune taille enregistrée pour l\'instant',
    'Campos de medicamento abertos de raiz na ficha do artigo'
        => 'Champs médicament ouverts d\'emblée sur la fiche article',
    'Campos de tamanho, cor, género e composição'
        => 'Champs taille, couleur, genre et composition',
    'com os valores reais do catálogo: ninguém se lembra de como escreveu "azul-marinho" da última vez.'
        => 'avec les valeurs réelles de votre catalogue : personne ne se souvient de la façon dont il a écrit « bleu marine » la dernière fois.',
    'Desligar um perfil não esconde o que já está preenchido: um artigo que já tenha dosagem, tamanho ou cor continua a mostrar esses campos, para poderem ser vistos e corrigidos.'
        => 'Désactiver un profil ne masque pas ce qui est déjà renseigné : un article qui a déjà un dosage, une taille ou une couleur continue d\'afficher ces champs, pour qu\'ils restent consultables et modifiables.',
    'Diga com o que trabalha e o sistema mostra os campos certos por omissão. Pode ligar os dois: um supermercado com balcão de farmácia é as duas coisas.'
        => 'Indiquez ce que vous vendez et le système affiche les bons champs par défaut. Vous pouvez activer les deux : un supermarché avec un comptoir de pharmacie est les deux à la fois.',
    'Este artigo também é medicamento' => 'Cet article est aussi un médicament',
    'Este artigo também é vestuário'   => 'Cet article est aussi un vêtement',
    'Este artigo é medicamento ou vestuário?' => 'Cet article est-il un médicament ou un vêtement ?',
    'Farmácia, parafarmácia ou balcão de medicamentos'
        => 'Pharmacie, parapharmacie ou comptoir de médicaments',
    'Filtro de "exige receita" na lista de artigos'
        => 'Filtre « sur ordonnance » dans la liste des articles',
    'Filtros de tamanho e de cor na lista' => 'Filtres de taille et de couleur dans la liste',
    'Lotes e validades com saída FIFO pela data de expiração, e o relatório de validade, dependem do rastreio de lotes de cada artigo — não deste perfil.'
        => 'Les lots et péremptions avec sortie FIFO par date de péremption, ainsi que le rapport de péremption, dépendent du suivi par lot de chaque article — pas de ce profil.',
    'na ficha do artigo, para distinguir duas peças que se chamam igual.'
        => 'sur la fiche article, pour distinguer deux pièces qui portent le même nom.',
    'O que fica activo' => 'Ce que cela active',
    'Os avisos do POS funcionam sempre.' => 'Les alertes du POS fonctionnent toujours.',
    'Perfil do Negócio' => 'Profil de l\'activité',
    'Procurar no POS pela substância activa também funciona sempre: quem chega com uma receita de paracetamol não sabe se a caixa diz Ben-u-ron ou Panadol.'
        => 'La recherche par substance active dans le POS fonctionne elle aussi toujours : celui qui arrive avec une ordonnance de paracétamol ne sait pas si la boîte indique Ben-u-ron ou Panadol.',
    'Procurar pelo tamanho no POS funciona sempre, com este perfil ligado ou desligado: "t-shirt" sozinho não chega para escolher; "t-shirt M" chega.'
        => 'La recherche par taille dans le POS fonctionne toujours, ce profil activé ou non : « t-shirt » seul ne suffit pas pour choisir ; « t-shirt M » suffit.',
    'Receita médica e psicotrópico avisam com este perfil ligado ou desligado, porque seguem os dados do artigo e não esta definição. Uma protecção que se desliga numa definição de visualização não é protecção.'
        => 'Les alertes ordonnance et psychotrope se déclenchent que ce profil soit activé ou non, car elles suivent les données de l\'article et non ce paramètre. Une protection que l\'on peut désactiver dans un paramètre d\'affichage n\'est pas une protection.',
    'Roupa, calçado e acessórios' => 'Vêtements, chaussures et accessoires',
    'Sem dados de medicamento neste artigo.' => 'Aucune donnée médicament sur cet article.',
    'Sem dados de vestuário neste artigo.'   => 'Aucune donnée vêtement sur cet article.',
    'Sem nenhum perfil ligado o sistema funciona normalmente — é o caso da maioria das empresas. Na ficha do artigo estes campos ficam recolhidos atrás da pergunta "Este artigo é medicamento ou vestuário?", a um clique de distância para o caso isolado.'
        => 'Sans aucun profil activé, le système fonctionne normalement — c\'est le cas de la plupart des entreprises. Sur la fiche article, ces champs restent repliés derrière la question « Cet article est-il un médicament ou un vêtement ? », à un clic pour le cas isolé.',
    'separa num clique o que não pode sair do balcão sem receita.'
        => 'distingue en un clic ce qui ne peut pas quitter le comptoir sans ordonnance.',
    'substância activa, dosagem, forma farmacêutica e n.º ARMED, em vez de recolhidos atrás de uma pergunta.'
        => 'substance active, dosage, forme pharmaceutique et n° ARMED, au lieu d\'être repliés derrière une question.',
    'Trabalha com medicamentos' => 'Vous vendez des médicaments',
    'Trabalha com vestuário'    => 'Vous vendez des vêtements',
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
