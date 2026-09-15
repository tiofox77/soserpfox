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
        'health:read'     => 'Ver inconsistências do sistema, o diagnóstico de uma empresa e a AGT por ambiente',
        'orders:read'     => 'Ver pedidos de plano',
        'orders:note'     => 'Deixar recomendação num pedido, sem decidir',
        'orders:approve'  => 'Aprovar pedidos de plano',
        'orders:reject'   => 'Recusar pedidos de plano',
        'followup:read'   => 'Ver modelos, destinatários e histórico de envios',
        'followup:email'  => 'Enviar email de seguimento',
        'followup:sms'    => 'Enviar SMS de seguimento',
        'followup:free'   => 'Enviar email e SMS com texto livre para destinatários autorizados',

        // Contactos REAIS, por mascarar. Existe para o agente poder mandar
        // WhatsApp, que sai do lado dele e precisa do número inteiro. Fica
        // num escopo à parte de propósito: sem ele a API continua a devolver
        // tudo mascarado, que é o que deve acontecer a qualquer token que
        // não precise de contactar ninguém.
        'contacts:read'   => 'Ver contactos reais (email e telefone) para contacto directo',

        // Operação da plataforma.
        'logs:read'       => 'Ver erros, auditoria, acessos (login/logout, resumo de falhas, quem está online, acessos por empresa), personificações e pedidos do agente',
        'logs:write'      => 'Marcar erros como vistos ou resolvidos',
        'billing:read'    => 'Ver subscrições, facturas e o estado do ciclo',
        'billing:write'   => 'Emitir facturas de renovação e disparar avisos ao cliente',
        'support:read'    => 'Ver pedidos de suporte, sugestões e mensagens de contacto',
        'support:write'   => 'Responder e mudar o estado de pedidos de suporte',
        'tenants:write'   => 'Criar, editar, suspender e reactivar empresas',
        // Apagar é irreversível e leva os documentos fiscais com ela. Escopo
        // PRÓPRIO, separado do `tenants:write`, para que dar ao agente a
        // capacidade de editar não lhe dê a de destruir.
        'tenants:delete'  => 'Apagar empresas — irreversível',
        'plans:read'      => 'Ver o catálogo de planos e os módulos de cada um',
        'plans:write'     => 'Criar e editar planos',
        'analytics:read'  => 'Ver utilização, crescimento, adopção, recomendações, relatórios (plataforma, documentos, vendas por empresa) e o analytics do site',
        'system:read'     => 'Ver o estado técnico da aplicação: deploy, migrações, relógios, OPcache, disco, log, filas, cache e base de dados',
        'system:write'    => 'Executar apenas acções operacionais da lista segura',
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
     | Erros do sistema
     |--------------------------------------------------------------------
     | O log tem 2000 linhas das quais 1972 são INFO de rotina: um erro a
     | sério afoga-se lá dentro e ninguém o vê. Os erros passam a ser
     | agrupados por PROBLEMA na tabela `erros_do_sistema` — uma linha com um
     | contador, não mil linhas — e o agente externo avisa uma vez por
     | problema.
     |
     | `capturar` e `notificar` são interruptores SEPARADOS: guardar os erros
     | é barato e não incomoda ninguém; empurrá-los para fora é um pedido HTTP
     | para outra máquina e uma mensagem a alguém.
     */
    'erros' => [
        'capturar'  => env('AGENT_ERROS_CAPTURAR', true),
        'notificar' => env('AGENT_ERROS_NOTIFICAR', false),

        // Para onde. Sem isto configurado, nada é empurrado — o agente pode
        // à mesma perguntar por GET /logs/errors.
        'webhook_url'    => env('AGENT_ERROS_WEBHOOK', ''),

        // Assina o corpo com HMAC-SHA256 sobre "timestamp.corpo", no
        // cabeçalho X-Soserp-Signature. É o que permite ao agente saber que a
        // mensagem veio daqui e não de quem descobriu o URL dele.
        'webhook_secret' => env('AGENT_ERROS_SEGREDO', ''),

        // Uma avaria que produza cinquenta problemas distintos em dois
        // minutos não pode virar cinquenta mensagens.
        'max_por_passagem'    => 10,
        'intervalo_segundos'  => 300,
        'timeout'             => 8,
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
