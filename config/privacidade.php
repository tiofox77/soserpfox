<?php

/**
 * PRIVACIDADE E PROTECÇÃO DE DADOS — a fonte única.
 *
 * Pedido de 2026-09-15: aplicar ao que o sistema recolhe de cada pessoa
 * (localização, morada, IP e o resto) as regras do RGPD europeu (Regulamento
 * UE 2016/679), da LGPD brasileira (Lei 13.709/2018) e da Lei n.º 22/11 de
 * Angola. O que aqui está alimenta a Política de Privacidade, a página de
 * cookies, o aviso de consentimento, o separador «Privacidade» da Minha conta
 * e o comando de retenção — um prazo mudado aqui muda em todo o lado.
 */
return [

    /*
     | A versão das regras de consentimento. Mudá-la volta a pedir a escolha a
     | toda a gente: um consentimento dado a outra lista de finalidades não
     | vale para esta.
     */
    'versao' => env('PRIVACIDADE_VERSAO', '2026-09-15'),

    'actualizada_em' => '15 de Setembro de 2026',

    /* Quem responde pelos dados da conta e onde se exercem os direitos. */
    'responsavel' => [
        'nome' => 'Softec Angola',
        'morada' => 'Luanda, Angola',
        'email' => env('PRIVACIDADE_EMAIL', 'suporte@soserp.vip'),
    ],

    /* Prazo de resposta a um pedido do titular (RGPD art. 12.º: um mês). */
    'prazo_de_resposta_dias' => 30,

    /* O cookie da escolha — lido também do lado do servidor (sem cifra). */
    'cookie' => 'sos_consentimento',
    'cookie_dias' => 180,

    /*
     | Retenção, em dias. O comando `privacidade:reter` aplica-a (simulação
     | por omissão). Os documentos fiscais e a trilha de auditoria NÃO estão
     | aqui: a lei fiscal angolana obriga a conservá-los, e a trilha é a prova
     | de quem fez o quê.
     */
    'retencao' => [
        'analytics_eventos' => 395,          // 13 meses
        'analytics_ip' => 30,                // IP (já truncado) apagado ao fim de 30 dias
        'mensagens_de_contacto_ip' => 365,
        'convites_expirados' => 90,
        'registos_de_email_conteudo' => 365, // o assunto e o destinatário ficam; o corpo sai
        'consentimentos' => 1825,            // 5 anos — a prova de que houve consentimento
    ],

    /* As categorias de cookies do aviso. «necessarios» nunca se desliga. */
    'categorias' => [
        'necessarios' => [
            'nome' => 'Estritamente necessários',
            'descricao' => 'Manter a sessão iniciada, proteger os formulários (CSRF) e lembrar esta escolha. Sem eles o sistema não funciona.',
            'cookies' => [
                ['nome' => 'soserp_session / laravel_session', 'finalidade' => 'Sessão iniciada', 'duracao' => '2 horas de inactividade'],
                ['nome' => 'XSRF-TOKEN', 'finalidade' => 'Protecção de formulários', 'duracao' => '2 horas'],
                ['nome' => 'remember_web_*', 'finalidade' => '«Lembrar-me» ao entrar', 'duracao' => 'Até terminar sessão'],
                ['nome' => 'sos_consentimento', 'finalidade' => 'Guardar esta escolha', 'duracao' => '180 dias'],
                ['nome' => 'casca_aberta', 'finalidade' => 'Menu lateral aberto ou fechado', 'duracao' => '1 ano'],
            ],
        ],
        'estatisticas' => [
            'nome' => 'Estatísticas',
            'descricao' => 'Perceber que páginas são vistas, de onde vêm as visitas e o que não funciona. Sem esta opção contamos a visita sem cookies, sem IP e sem localização.',
            'cookies' => [
                ['nome' => 'sos_vid', 'finalidade' => 'Reconhecer a mesma visita em dias diferentes', 'duracao' => '1 ano'],
                ['nome' => 'sos_utm_*', 'finalidade' => 'Campanha de onde veio a visita', 'duracao' => '30 dias'],
                ['nome' => '_ga, _ga_*', 'finalidade' => 'Google Analytics (quando configurado)', 'duracao' => '2 anos'],
            ],
        ],
        'marketing' => [
            'nome' => 'Marketing',
            'descricao' => 'Medir campanhas no Facebook/Instagram e Google e mostrar publicidade relevante. Partilha dados de navegação com essas plataformas.',
            'cookies' => [
                ['nome' => '_fbp, fr', 'finalidade' => 'Meta Pixel (Facebook/Instagram)', 'duracao' => '90 dias'],
                ['nome' => '_gcl_*', 'finalidade' => 'Conversões do Google Ads', 'duracao' => '90 dias'],
            ],
        ],
    ],
];
