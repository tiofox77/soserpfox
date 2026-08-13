<?php

/**
 * Traduções dos campos de farmácia e vestuário.
 *
 * Notas que não são óbvias:
 *
 *   · ARMED é o regulador angolano do medicamento. Não se traduz, tal como
 *     não se traduz AGT — é o nome de uma instituição.
 *
 *   · DCI (Denominação Comum Internacional) é INN em inglês e DCI em
 *     francês. É o nome científico da substância, o que permite trocar uma
 *     marca por um genérico — traduzir a sigla à letra tirava-lhe o sentido.
 *
 *   · "Psicotrópico / estupefaciente" é a categoria legal. Em inglês
 *     "controlled substance" é o termo corrente e é o que a farmácia
 *     reconhece; em francês, "stupéfiant / psychotrope".
 *
 *   · As formas farmacêuticas ficam em minúscula porque aparecem numa lista
 *     de sugestões, a meio de uma frase, e não como título.
 *
 * Uso:  php scripts/lote6_farmacia_vestuario_traducoes.php
 */

$raiz = dirname(__DIR__);

$en = [
    ':artigo exige receita médica — confirme a receita antes de entregar.'
        => ':artigo requires a prescription — check the prescription before handing it over.',
    ':artigo é um medicamento controlado (psicotrópico ou estupefaciente), de registo obrigatório. Confirma a venda?'
        => ':artigo is a controlled medicine (psychotropic or narcotic) subject to mandatory recording. Confirm the sale?',
    'Adicionado — :artigo exige RECEITA MÉDICA. Confirme a receita antes de entregar.'
        => 'Added — :artigo requires a PRESCRIPTION. Check the prescription before handing it over.',
    'Campos opcionais — preencha apenas o que se aplica a este artigo'
        => 'Optional fields — fill in only what applies to this item',
    'Composição'                       => 'Composition',
    'comprimido'                       => 'tablet',
    'CONTROLADO'                       => 'CONTROLLED',
    'Controlado'                       => 'Controlled',
    'creme'                            => 'cream',
    'Criança'                          => 'Children',
    'cápsula'                          => 'capsule',
    'Dosagem'                          => 'Strength',
    'Ex: 100% algodão'                 => 'e.g. 100% cotton',
    'Ex: 500mg, 5mg/ml'                => 'e.g. 500mg, 5mg/ml',
    'Ex: azul-marinho'                 => 'e.g. navy blue',
    'Ex: comprimido, xarope, injectável' => 'e.g. tablet, syrup, injection',
    'Ex: Paracetamol'                  => 'e.g. Paracetamol',
    'Ex: S, M, L, 38, 40'              => 'e.g. S, M, L, 38, 40',
    'Exige receita'                    => 'Prescription required',
    'Exige receita médica'             => 'Requires a prescription',
    'Feminino'                         => 'Female',
    'Forma farmacêutica'               => 'Pharmaceutical form',
    'gotas'                            => 'drops',
    'Género'                           => 'Gender',
    'injectável'                       => 'injection',
    'Masculino'                        => 'Male',
    'Medicamento'                      => 'Medicine',
    'Medicamento e Vestuário'          => 'Medicine and Clothing',
    'N.º de registo ARMED'             => 'ARMED registration no.',
    'pomada'                           => 'ointment',
    'Psicotrópico / estupefaciente'    => 'Psychotropic / narcotic',
    'Psicotrópico ou estupefaciente — venda sujeita a registo obrigatório'
        => 'Psychotropic or narcotic — the sale must be recorded',
    'RECEITA'                          => 'PRESCRIPTION',
    'Registo na ARMED (Angola)'        => 'ARMED registration (Angola)',
    'Substância activa (DCI)'          => 'Active ingredient (INN)',
    'Substância sujeita a controlo especial' => 'Substance under special control',
    'supositório'                      => 'suppository',
    'suspensão'                        => 'suspension',
    'Só pode ser dispensado com apresentação de receita'
        => 'Can only be dispensed against a prescription',
    'Tamanho'                          => 'Size',
    'Unissexo'                         => 'Unisex',
    'Venda livre'                      => 'Over the counter',
    'Vestuário'                        => 'Clothing',
    'xarope'                           => 'syrup',
];

$fr = [
    ':artigo exige receita médica — confirme a receita antes de entregar.'
        => ':artigo exige une ordonnance — vérifiez l\'ordonnance avant de le remettre.',
    ':artigo é um medicamento controlado (psicotrópico ou estupefaciente), de registo obrigatório. Confirma a venda?'
        => ':artigo est un médicament sous contrôle (psychotrope ou stupéfiant), soumis à enregistrement obligatoire. Confirmer la vente ?',
    'Adicionado — :artigo exige RECEITA MÉDICA. Confirme a receita antes de entregar.'
        => 'Ajouté — :artigo exige une ORDONNANCE. Vérifiez l\'ordonnance avant de le remettre.',
    'Campos opcionais — preencha apenas o que se aplica a este artigo'
        => 'Champs facultatifs — ne remplissez que ce qui s\'applique à cet article',
    'Composição'                       => 'Composition',
    'comprimido'                       => 'comprimé',
    'CONTROLADO'                       => 'SOUS CONTRÔLE',
    'Controlado'                       => 'Sous contrôle',
    'creme'                            => 'crème',
    'Criança'                          => 'Enfant',
    'cápsula'                          => 'gélule',
    'Dosagem'                          => 'Dosage',
    'Ex: 100% algodão'                 => 'ex. : 100 % coton',
    'Ex: 500mg, 5mg/ml'                => 'ex. : 500 mg, 5 mg/ml',
    'Ex: azul-marinho'                 => 'ex. : bleu marine',
    'Ex: comprimido, xarope, injectável' => 'ex. : comprimé, sirop, injectable',
    'Ex: Paracetamol'                  => 'ex. : Paracétamol',
    'Ex: S, M, L, 38, 40'              => 'ex. : S, M, L, 38, 40',
    'Exige receita'                    => 'Sur ordonnance',
    'Exige receita médica'             => 'Exige une ordonnance',
    'Feminino'                         => 'Femme',
    'Forma farmacêutica'               => 'Forme pharmaceutique',
    'gotas'                            => 'gouttes',
    'Género'                           => 'Genre',
    'injectável'                       => 'injectable',
    'Masculino'                        => 'Homme',
    'Medicamento'                      => 'Médicament',
    'Medicamento e Vestuário'          => 'Médicament et vêtement',
    'N.º de registo ARMED'             => 'N° d\'enregistrement ARMED',
    'pomada'                           => 'pommade',
    'Psicotrópico / estupefaciente'    => 'Psychotrope / stupéfiant',
    'Psicotrópico ou estupefaciente — venda sujeita a registo obrigatório'
        => 'Psychotrope ou stupéfiant — la vente doit être enregistrée',
    'RECEITA'                          => 'ORDONNANCE',
    'Registo na ARMED (Angola)'        => 'Enregistrement ARMED (Angola)',
    'Substância activa (DCI)'          => 'Substance active (DCI)',
    'Substância sujeita a controlo especial' => 'Substance soumise à un contrôle spécial',
    'supositório'                      => 'suppositoire',
    'suspensão'                        => 'suspension',
    'Só pode ser dispensado com apresentação de receita'
        => 'Ne peut être délivré que sur présentation d\'une ordonnance',
    'Tamanho'                          => 'Taille',
    'Unissexo'                         => 'Unisexe',
    'Venda livre'                      => 'Vente libre',
    'Vestuário'                        => 'Vêtement',
    'xarope'                           => 'sirop',
];

foreach (['en' => $en, 'fr' => $fr] as $lingua => $novas) {
    $caminho = "{$raiz}/lang/{$lingua}.json";
    $dicionario = json_decode(file_get_contents($caminho), true) ?: [];

    $acrescentadas = 0;
    $conflitos = [];

    foreach ($novas as $pt => $traduzida) {
        if (isset($dicionario[$pt])) {
            if ($dicionario[$pt] !== $traduzida) {
                $conflitos[$pt] = $dicionario[$pt];
            }
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

    foreach ($conflitos as $pt => $antiga) {
        printf("    já existia \"%s\" = \"%s\" — ficou a antiga\n", $pt, $antiga);
    }
}
