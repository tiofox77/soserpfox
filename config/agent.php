<?php

/**
 * API do agente externo (openclaw).
 *
 * Tudo o que o agente pode fazer está declarado AQUI, numa lista fechada.
 * O que não estiver escrito neste ficheiro, o agente não faz — não há
 * "por omissão permitido" em lado nenhum desta API.
 */
return [

    /*
     |--------------------------------------------------------------------
     | Interruptor geral
     |--------------------------------------------------------------------
     | Desliga a API inteira sem deploy e sem revogar tokens. Serve para
     | cortar o agente em segundos se ele começar a fazer asneira.
     */
    'activa' => env('AGENT_API_ENABLED', true),

    /*
     |--------------------------------------------------------------------
     | Escopos
     |--------------------------------------------------------------------
     | Nenhum é concedido por omissão. Ler nunca implica escrever, e poder
     | escrever num domínio nunca implica poder escrever noutro: um token
     | pode ler tudo e não aprovar nada, ou aprovar e não enviar mensagens.
     */
    'escopos' => [
        'tenants:read'    => 'Ver empresas e o estado da subscrição',
        'health:read'     => 'Ver inconsistências do sistema',
        'orders:read'     => 'Ver pedidos de plano',
        'orders:note'     => 'Deixar recomendação num pedido, sem decidir',
        'orders:approve'  => 'Aprovar pedidos de plano',
        'orders:reject'   => 'Recusar pedidos de plano',
        'followup:read'   => 'Ver modelos, destinatários e histórico de envios',
        'followup:email'  => 'Enviar email de seguimento',
        'followup:sms'    => 'Enviar SMS de seguimento',
    ],

    /*
     |--------------------------------------------------------------------
     | Tokens
     |--------------------------------------------------------------------
     */
    'token' => [
        'prefixo'          => 'oclaw',
        'validade_maxima'  => 90,   // dias; expires_at é obrigatório
        'exigir_ips'       => env('AGENT_REQUIRE_IPS', true),
    ],

    /*
     |--------------------------------------------------------------------
     | Aprovação de pedidos
     |--------------------------------------------------------------------
     | Aprovar mexe em dinheiro e em dias de plano, e não é tecnicamente
     | reversível: o observer cancela a subscrição anterior. Por isso há
     | tectos, e acima deles o agente só pode recomendar.
     */
    'aprovacao' => [
        'valor_maximo'          => env('AGENT_APPROVE_MAX', 50000),
        'dias_pagos_intocaveis' => 7,   // recusa se queimar mais do que isto
        'por_hora'              => 5,
        'por_dia'               => 20,
    ],

    /*
     |--------------------------------------------------------------------
     | Seguimento (email / SMS)
     |--------------------------------------------------------------------
     | Comunicação externa é irreversível: uma vez enviada, não se chama de
     | volta. Daí os tectos, o arrefecimento por empresa e o silêncio
     | nocturno. O SMS tem contador próprio porque é dinheiro real.
     */
    'followup' => [
        'email_por_dia'        => 30,
        'sms_por_dia'          => 10,
        'arrefecimento_horas'  => 72,   // mesmo template, mesma empresa
        'maximo_por_empresa_30d' => 3,
        'silencio' => [
            'fuso'    => 'Africa/Luanda',
            'inicio'  => 21,   // 21:00
            'fim'     => 7,    // 07:00
            'domingo' => true, // nada aos domingos
        ],

        /*
         | Allowlist de modelos. O agente ESCOLHE de aqui; não escreve texto.
         | 'variaveis' são as que o template exige e que serão validadas.
         */
        'templates' => [
            'followup_teste_a_terminar' => [
                'canais'    => ['email', 'sms'],
                'descricao' => 'O período de teste está a acabar',
                'variaveis' => ['nome_empresa', 'dias'],
            ],
            'followup_plano_expirado' => [
                'canais'    => ['email', 'sms'],
                'descricao' => 'O plano expirou e o acesso vai ser limitado',
                'variaveis' => ['nome_empresa'],
            ],
            'followup_pagamento_por_confirmar' => [
                'canais'    => ['email'],
                'descricao' => 'Falta comprovativo de pagamento',
                'variaveis' => ['nome_empresa', 'plano'],
            ],
            'followup_sem_actividade' => [
                'canais'    => ['email'],
                'descricao' => 'Conta sem utilização — oferta de ajuda',
                'variaveis' => ['nome_empresa'],
            ],
        ],
    ],

    /*
     |--------------------------------------------------------------------
     | Verificações de saúde
     |--------------------------------------------------------------------
     | Lista fechada, TODAS só de leitura. O agente detecta e descreve;
     | corrigir é sempre de um humano. Precedente que justifica a regra:
     | uma rota de manutenção sem argumentos fez um reconcile reescrever
     | 335 produtos em produção sem ninguém pedir.
     */
    'verificacoes' => [
        'subscricao_expirada_activa',
        'teste_expirado_em_uso',
        'subscricao_sem_prazo',
        'pedidos_pendentes_antigos',
        'pedidos_duplicados',
        'tenant_sem_subscricao',
        'nif_invalido',
        'agt_fila_parada',
        'documentos_por_comunicar',
        'stock_negativo',
    ],

    /*
     |--------------------------------------------------------------------
     | Limites de chamadas
     |--------------------------------------------------------------------
     */
    'limites' => [
        'leitura_por_minuto' => 120,
        'escrita_por_minuto' => 10,
    ],
];
