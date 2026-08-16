<?php

/**
 * Traduções do painel de SMS às empresas.
 *
 * Notas que não são óbvias:
 *
 *   · "parte(s) de SMS" é a unidade que se paga. Em inglês a palavra do
 *     mercado é "segment", não "part";
 *
 *   · ":n empresa(s)" fica com o plural do português no molde porque é assim
 *     que o resto deste ecrã está escrito; nas outras línguas resolve-se com
 *     a forma que soa natural.
 *
 * Uso:  php scripts/lote12_sms_empresas_traducoes.php
 */

$raiz = dirname(__DIR__);

$en = [
    'SMS às empresas'                => 'SMS to companies',
    'Pela configuração D7 da plataforma' => 'Using the platform D7 settings',
    'SMS não configurado'            => 'SMS not configured',
    'Não há configuração de SMS activa na plataforma. Configure-a antes de enviar.'
        => 'There is no active SMS configuration on the platform. Set it up before sending.',
    'Abrir definições de SMS'        => 'Open SMS settings',
    'Enviado a :n empresa(s)'        => 'Sent to :n company(ies)',
    ':n parte(s) de SMS'             => ':n SMS segment(s)',
    'Falharam:'                      => 'Failed:',
    'Mensagem'                       => 'Message',
    'Escreva o que quer dizer às empresas...' => 'Write what you want to tell the companies...',
    ':n caracteres'                  => ':n characters',
    ':n parte(s) por destinatário'   => ':n segment(s) per recipient',
    'Quem recebe'                    => 'Who receives it',
    'Todas as empresas'              => 'All companies',
    'Empresas escolhidas'            => 'Selected companies',
    'Por plano'                      => 'By plan',
    'Empresas'                       => 'Companies',
    'sem telefone'                   => 'no phone number',
    'Planos'                         => 'Plans',
    'Empresas escolhidas'            => 'Selected companies',
    'Com telefone'                   => 'With a phone number',
    ':n não recebem por não terem telefone registado.' => ':n will not receive it: no phone number on record.',
    'Rever antes de enviar'          => 'Review before sending',
    'Enviar a :empresas empresa(s), num total de :partes parte(s) de SMS?'
        => 'Send to :empresas company(ies), for a total of :partes SMS segment(s)?',
    'Isto não se pode desfazer.'     => 'This cannot be undone.',
    'Enviar agora'                   => 'Send now',
    'A enviar...'                    => 'Sending...',
    'Cancelar'                       => 'Cancel',
];

$fr = [
    'SMS às empresas'                => 'SMS aux entreprises',
    'Pela configuração D7 da plataforma' => 'Via la configuration D7 de la plateforme',
    'SMS não configurado'            => 'SMS non configuré',
    'Não há configuração de SMS activa na plataforma. Configure-a antes de enviar.'
        => 'Aucune configuration SMS active sur la plateforme. Configurez-la avant d\'envoyer.',
    'Abrir definições de SMS'        => 'Ouvrir les paramètres SMS',
    'Enviado a :n empresa(s)'        => 'Envoyé à :n entreprise(s)',
    ':n parte(s) de SMS'             => ':n segment(s) SMS',
    'Falharam:'                      => 'Échecs :',
    'Mensagem'                       => 'Message',
    'Escreva o que quer dizer às empresas...' => 'Écrivez ce que vous voulez dire aux entreprises...',
    ':n caracteres'                  => ':n caractères',
    ':n parte(s) por destinatário'   => ':n segment(s) par destinataire',
    'Quem recebe'                    => 'Qui le reçoit',
    'Todas as empresas'              => 'Toutes les entreprises',
    'Empresas escolhidas'            => 'Entreprises sélectionnées',
    'Por plano'                      => 'Par forfait',
    'Empresas'                       => 'Entreprises',
    'sem telefone'                   => 'sans téléphone',
    'Planos'                         => 'Forfaits',
    'Com telefone'                   => 'Avec téléphone',
    ':n não recebem por não terem telefone registado.' => ':n ne le recevront pas : aucun téléphone enregistré.',
    'Rever antes de enviar'          => 'Vérifier avant d\'envoyer',
    'Enviar a :empresas empresa(s), num total de :partes parte(s) de SMS?'
        => 'Envoyer à :empresas entreprise(s), pour un total de :partes segment(s) SMS ?',
    'Isto não se pode desfazer.'     => 'Cette action est irréversible.',
    'Enviar agora'                   => 'Envoyer maintenant',
    'A enviar...'                    => 'Envoi en cours...',
    'Cancelar'                       => 'Annuler',
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
