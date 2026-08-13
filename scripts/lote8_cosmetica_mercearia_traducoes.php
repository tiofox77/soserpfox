<?php

/**
 * Traduções dos perfis de cosmética e mercearia.
 *
 * Notas que não são óbvias:
 *
 *   · INCI é a nomenclatura internacional dos ingredientes cosméticos. É a
 *     mesma sigla em todo o lado — não se traduz, tal como não se traduz AGT.
 *
 *   · PAO (Period After Opening) é o frasco aberto com "12M". Em inglês é
 *     mesmo PAO; em francês usa-se PAO ou "période après ouverture".
 *
 *   · "Conteúdo líquido" é a quantidade dentro da embalagem — net content /
 *     contenu net. Nada a ver com liquidez financeira nem com estado físico:
 *     um pacote de arroz também tem conteúdo líquido.
 *
 *   · "Frutos de casca rija" são os nuts do rótulo alimentar europeu. Em
 *     inglês "tree nuts", em francês "fruits à coque" — o termo legal.
 *
 * Uso:  php scripts/lote8_cosmetica_mercearia_traducoes.php
 */

$raiz = dirname(__DIR__);

$en = [
    '1 mês após abertura'   => '1 month after opening',
    ':meses meses após abertura' => ':meses months after opening',
    'Alergénios'            => 'Allergens',
    'Alergénios e país de origem' => 'Allergens and country of origin',
    'ambiente, refrigerado ou congelado à vista de quem arruma a mercadoria: o que vai ao frio sabe-se antes de abrir artigo a artigo, que é quando ainda dá para evitar o prejuízo.'
        => 'ambient, chilled or frozen, in plain sight for whoever puts the goods away: what goes in the cold is known before opening item by item, which is while there is still time to avoid the loss.',
    'Bens alimentares, bebidas e produtos de casa' => 'Food, drinks and household goods',
    'Congelado'             => 'Frozen',
    'Conservação'           => 'Storage',
    'Conservação no stock, e não só na ficha' => 'Storage shown in stock, not only on the item form',
    'Conteúdo líquido'      => 'Net content',
    'Conteúdo líquido aberto de raiz na ficha do artigo' => 'Net content open by default on the item form',
    'Conteúdo líquido na ficha do artigo' => 'Net content on the item form',
    'Cosmética'             => 'Cosmetics',
    'Cremes, perfumaria, cuidado do cabelo e maquilhagem' => 'Creams, perfumery, haircare and make-up',
    'Desligar um perfil não esconde o que já está preenchido: um artigo que já tenha dosagem, tamanho, conteúdo líquido ou alergénios continua a mostrar esses campos, para poderem ser vistos e corrigidos.'
        => 'Turning a profile off does not hide what is already filled in: an item that already has a strength, size, net content or allergens keeps showing those fields, so they can be seen and corrected.',
    'Detalhes específicos do artigo' => 'Item-specific details',
    'Diga com o que trabalha e o sistema mostra os campos certos por omissão. Pode ligar mais do que um: um supermercado com balcão de farmácia e prateleira de cosmética é as três coisas.'
        => 'Tell us what you deal in and the system shows the right fields by default. You can turn on more than one: a supermarket with a pharmacy counter and a cosmetics shelf is all three.',
    'duas embalagens do mesmo champô só se distinguem por isto: sem o campo, "Champô Suave" aparece duas vezes na lista e ninguém sabe qual é o de 200ml.'
        => 'two packs of the same shampoo differ only by this: without the field, "Gentle Shampoo" shows up twice in the list and nobody knows which is the 200ml.',
    'Embalagem'             => 'Packaging',
    'Este artigo também é cosmética' => 'This item is also cosmetics',
    'Este artigo também é mercearia' => 'This item is also groceries',
    'Este artigo tem campos próprios do ramo (receita, tamanho, alergénios…)?'
        => 'Does this item have trade-specific fields (prescription, size, allergens…)?',
    'Ex: 12'                => 'e.g. 12',
    'Ex: 50ml, 200g, 1kg'   => 'e.g. 50ml, 200g, 1kg',
    'Ex: Angola, Portugal, Brasil' => 'e.g. Angola, Portugal, Brazil',
    'Ex: Aqua, Glycerin, Parfum, Sodium Chloride' => 'e.g. Aqua, Glycerin, Parfum, Sodium Chloride',
    'Ex: glúten, leite, frutos de casca rija' => 'e.g. gluten, milk, tree nuts',
    'informação obrigatória no rótulo alimentar, e a pergunta que se faz ao balcão com o cliente à espera: "isto leva glúten?".'
        => 'information the food label is required to carry, and the question asked at the counter with the customer waiting: "does this contain gluten?".',
    'Informação obrigatória no rótulo e pergunta de balcão.'
        => 'Required on the label, and a counter question.',
    'Lista INCI'            => 'INCI list',
    'Lista INCI de ingredientes' => 'INCI ingredient list',
    'Lista normalizada de ingredientes, tal como vem no rótulo.'
        => 'Standardised ingredient list, exactly as printed on the label.',
    'Lotes e validades dependem do rastreio ligado em cada artigo, não deste perfil: continuam a funcionar com ele ligado ou desligado.'
        => 'Batches and expiry dates depend on the tracking turned on for each item, not on this profile: they keep working with it on or off.',
    'Mercearia'             => 'Groceries',
    'Meses após abertura (PAO)' => 'Months after opening (PAO)',
    'Mostrar'               => 'Show',
    'Não indicado'          => 'Not stated',
    'Obrigatório no rótulo alimentar.' => 'Required on the food label.',
    'o pacote de 1kg e o de 5kg são artigos diferentes com preço e stock diferentes, e só isto os separa na lista.'
        => 'the 1kg pack and the 5kg pack are different items with different prices and stock, and only this tells them apart in the list.',
    'O tom regista-se no campo Cor.' => 'The shade goes in the Colour field.',
    'O tom usa o campo de cor que já existe — é a mesma informação com outro nome, e dois campos para a mesma coisa acabam sempre com metade do catálogo preenchido em cada um.'
        => 'The shade uses the existing colour field — it is the same information under another name, and two fields for one thing always end up with half the catalogue in each.',
    'País de origem'        => 'Country of origin',
    'Refrigerado'           => 'Chilled',
    'Sem dados de cosmética neste artigo.' => 'No cosmetics details on this item.',
    'Sem dados de mercearia neste artigo.' => 'No grocery details on this item.',
    'Sem nenhum perfil ligado o sistema funciona normalmente — é o caso da maioria das empresas. Na ficha do artigo estes campos ficam recolhidos atrás de uma pergunta sobre o tipo de artigo, a um clique de distância para o caso isolado.'
        => 'With no profile on the system works as usual — which is the case for most companies. On the item form these fields sit behind a question about the kind of item, one click away for the odd exception.',
    'Tom'                   => 'Shade',
    'Trabalha com cosmética' => 'You deal in cosmetics',
    'Trabalha com mercearia' => 'You deal in groceries',
    'Validade depois de aberto, que é diferente do prazo por abrir.'
        => 'Shelf life once opened, which is not the same as the unopened expiry date.',
    'É o que distingue duas embalagens do mesmo produto.'
        => 'It is what tells two packs of the same product apart.',
    'é o frasco aberto com "12M" no rótulo, e não é o prazo de validade: por abrir o creme dura até à data da caixa, aberto dura estes meses. A loja precisa dos dois números.'
        => 'it is the open-jar symbol with "12M" on the label, and it is not the expiry date: unopened, the cream lasts until the date on the box; opened, it lasts these months. The shop needs both numbers.',
    'é o que responde a "isto tem parabenos?" com o cliente à frente, sem ir buscar a caixa ao armazém.'
        => 'it is what answers "does this have parabens?" with the customer in front of you, without fetching the box from the stockroom.',
];

$fr = [
    '1 mês após abertura'   => '1 mois après ouverture',
    ':meses meses após abertura' => ':meses mois après ouverture',
    'Alergénios'            => 'Allergènes',
    'Alergénios e país de origem' => 'Allergènes et pays d\'origine',
    'ambiente, refrigerado ou congelado à vista de quem arruma a mercadoria: o que vai ao frio sabe-se antes de abrir artigo a artigo, que é quando ainda dá para evitar o prejuízo.'
        => 'ambiant, réfrigéré ou congelé, sous les yeux de celui qui range la marchandise : ce qui va au froid se sait avant d\'ouvrir article par article, c\'est-à-dire tant qu\'il est encore temps d\'éviter la perte.',
    'Bens alimentares, bebidas e produtos de casa' => 'Alimentation, boissons et produits ménagers',
    'Congelado'             => 'Congelé',
    'Conservação'           => 'Conservation',
    'Conservação no stock, e não só na ficha' => 'Conservation visible dans le stock, et pas seulement sur la fiche',
    'Conteúdo líquido'      => 'Contenu net',
    'Conteúdo líquido aberto de raiz na ficha do artigo' => 'Contenu net ouvert d\'emblée sur la fiche article',
    'Conteúdo líquido na ficha do artigo' => 'Contenu net sur la fiche article',
    'Cosmética'             => 'Cosmétique',
    'Cremes, perfumaria, cuidado do cabelo e maquilhagem' => 'Crèmes, parfumerie, soins capillaires et maquillage',
    'Desligar um perfil não esconde o que já está preenchido: um artigo que já tenha dosagem, tamanho, conteúdo líquido ou alergénios continua a mostrar esses campos, para poderem ser vistos e corrigidos.'
        => 'Désactiver un profil ne masque pas ce qui est déjà renseigné : un article qui a déjà un dosage, une taille, un contenu net ou des allergènes continue d\'afficher ces champs, pour qu\'ils restent consultables et modifiables.',
    'Detalhes específicos do artigo' => 'Détails propres à l\'article',
    'Diga com o que trabalha e o sistema mostra os campos certos por omissão. Pode ligar mais do que um: um supermercado com balcão de farmácia e prateleira de cosmética é as três coisas.'
        => 'Indiquez ce que vous vendez et le système affiche les bons champs par défaut. Vous pouvez en activer plusieurs : un supermarché avec un comptoir de pharmacie et un rayon cosmétique est les trois à la fois.',
    'duas embalagens do mesmo champô só se distinguem por isto: sem o campo, "Champô Suave" aparece duas vezes na lista e ninguém sabe qual é o de 200ml.'
        => 'deux conditionnements du même shampooing ne se distinguent que par cela : sans ce champ, « Shampooing doux » apparaît deux fois dans la liste et personne ne sait lequel fait 200 ml.',
    'Embalagem'             => 'Conditionnement',
    'Este artigo também é cosmética' => 'Cet article relève aussi de la cosmétique',
    'Este artigo também é mercearia' => 'Cet article relève aussi de l\'alimentaire',
    'Este artigo tem campos próprios do ramo (receita, tamanho, alergénios…)?'
        => 'Cet article a-t-il des champs propres au métier (ordonnance, taille, allergènes…) ?',
    'Ex: 12'                => 'ex. : 12',
    'Ex: 50ml, 200g, 1kg'   => 'ex. : 50 ml, 200 g, 1 kg',
    'Ex: Angola, Portugal, Brasil' => 'ex. : Angola, Portugal, Brésil',
    'Ex: Aqua, Glycerin, Parfum, Sodium Chloride' => 'ex. : Aqua, Glycerin, Parfum, Sodium Chloride',
    'Ex: glúten, leite, frutos de casca rija' => 'ex. : gluten, lait, fruits à coque',
    'informação obrigatória no rótulo alimentar, e a pergunta que se faz ao balcão com o cliente à espera: "isto leva glúten?".'
        => 'information obligatoire sur l\'étiquette alimentaire, et la question posée au comptoir avec le client qui attend : « est-ce que ça contient du gluten ? ».',
    'Informação obrigatória no rótulo e pergunta de balcão.'
        => 'Obligatoire sur l\'étiquette, et question de comptoir.',
    'Lista INCI'            => 'Liste INCI',
    'Lista INCI de ingredientes' => 'Liste INCI des ingrédients',
    'Lista normalizada de ingredientes, tal como vem no rótulo.'
        => 'Liste normalisée des ingrédients, telle qu\'elle figure sur l\'étiquette.',
    'Lotes e validades dependem do rastreio ligado em cada artigo, não deste perfil: continuam a funcionar com ele ligado ou desligado.'
        => 'Les lots et péremptions dépendent du suivi activé sur chaque article, pas de ce profil : ils continuent de fonctionner qu\'il soit activé ou non.',
    'Mercearia'             => 'Alimentation',
    'Meses após abertura (PAO)' => 'Mois après ouverture (PAO)',
    'Mostrar'               => 'Afficher',
    'Não indicado'          => 'Non précisé',
    'Obrigatório no rótulo alimentar.' => 'Obligatoire sur l\'étiquette alimentaire.',
    'o pacote de 1kg e o de 5kg são artigos diferentes com preço e stock diferentes, e só isto os separa na lista.'
        => 'le paquet de 1 kg et celui de 5 kg sont des articles différents, avec des prix et des stocks différents, et seul cela les sépare dans la liste.',
    'O tom regista-se no campo Cor.' => 'La teinte se saisit dans le champ Couleur.',
    'O tom usa o campo de cor que já existe — é a mesma informação com outro nome, e dois campos para a mesma coisa acabam sempre com metade do catálogo preenchido em cada um.'
        => 'La teinte utilise le champ couleur existant — c\'est la même information sous un autre nom, et deux champs pour une seule chose finissent toujours avec la moitié du catalogue dans chacun.',
    'País de origem'        => 'Pays d\'origine',
    'Refrigerado'           => 'Réfrigéré',
    'Sem dados de cosmética neste artigo.' => 'Aucune donnée cosmétique sur cet article.',
    'Sem dados de mercearia neste artigo.' => 'Aucune donnée alimentaire sur cet article.',
    'Sem nenhum perfil ligado o sistema funciona normalmente — é o caso da maioria das empresas. Na ficha do artigo estes campos ficam recolhidos atrás de uma pergunta sobre o tipo de artigo, a um clique de distância para o caso isolado.'
        => 'Sans aucun profil activé, le système fonctionne normalement — c\'est le cas de la plupart des entreprises. Sur la fiche article, ces champs restent repliés derrière une question sur le type d\'article, à un clic pour le cas isolé.',
    'Tom'                   => 'Teinte',
    'Trabalha com cosmética' => 'Vous vendez des cosmétiques',
    'Trabalha com mercearia' => 'Vous vendez de l\'alimentaire',
    'Validade depois de aberto, que é diferente do prazo por abrir.'
        => 'Durée de conservation après ouverture, qui n\'est pas la date de péremption avant ouverture.',
    'É o que distingue duas embalagens do mesmo produto.'
        => 'C\'est ce qui distingue deux conditionnements du même produit.',
    'é o frasco aberto com "12M" no rótulo, e não é o prazo de validade: por abrir o creme dura até à data da caixa, aberto dura estes meses. A loja precisa dos dois números.'
        => 'c\'est le symbole du pot ouvert avec « 12M » sur l\'étiquette, et ce n\'est pas la date de péremption : non ouverte, la crème se garde jusqu\'à la date de la boîte ; ouverte, elle se garde ces mois-là. La boutique a besoin des deux chiffres.',
    'é o que responde a "isto tem parabenos?" com o cliente à frente, sem ir buscar a caixa ao armazém.'
        => 'c\'est ce qui répond à « est-ce que ça contient des parabènes ? » avec le client en face, sans aller chercher la boîte en réserve.',
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
