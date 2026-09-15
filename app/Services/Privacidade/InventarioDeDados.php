<?php

namespace App\Services\Privacidade;

/**
 * O QUE RECOLHEMOS DE CADA PESSOA — o inventário, numa lista só.
 *
 * O RGPD (arts. 13.º e 30.º), a LGPD (art. 9.º) e a Lei 22/11 pedem
 * o mesmo: dizer à pessoa, antes, que dados, para quê, com que fundamento, a
 * quem vão e durante quanto tempo. Esta lista é a Política de Privacidade
 * (secção «Que dados recolhemos») e é o separador «Privacidade» da Minha conta:
 * se a lista mudar aqui, mudam os dois — nunca mais um documento a prometer uma
 * coisa e o sistema a fazer outra.
 *
 * Cada entrada corresponde a tabelas reais (em `onde`), verificadas em
 * 2026-09-15 na auditoria de dados pessoais.
 */
class InventarioDeDados
{
    /**
     * @return list<array{chave: string, icone: string, titulo: string, dados: list<string>, finalidade: string, base_legal: string, retencao: string, destinatarios: string, onde: list<string>}>
     */
    public static function categorias(): array
    {
        $r = config('privacidade.retencao');

        return [
            [
                'chave' => 'conta',
                'icone' => 'fa-id-card',
                'titulo' => __('Identificação e conta'),
                'dados' => [__('Nome'), __('Email (é o login)'), __('Telefone'), __('Fotografia de perfil'), __('Biografia'), __('Língua preferida'), __('Palavra-passe (só a impressão cifrada, nunca legível)'), __('PIN de turno do POS (só a impressão cifrada)')],
                'finalidade' => __('Criar e manter a conta, entrar no sistema, contactar sobre o serviço.'),
                'base_legal' => __('Execução do contrato (RGPD art. 6.º-1-b; LGPD art. 7.º-V; Lei 22/11).'),
                'retencao' => __('Enquanto a conta existir; depois, só o necessário para prova e obrigações legais.'),
                'destinatarios' => __('Ninguém fora do SOSERP, salvo o fornecedor de email para os avisos.'),
                'onde' => ['users', 'tenant_user'],
            ],
            [
                'chave' => 'empresa',
                'icone' => 'fa-building',
                'titulo' => __('Empresa e morada'),
                'dados' => [__('Designação e NIF'), __('Morada, cidade, província, município e país'), __('Telefone e email da empresa'), __('Regime fiscal'), __('Logótipo')],
                'finalidade' => __('Emitir documentos fiscais válidos e comunicá-los à AGT; facturar a subscrição.'),
                'base_legal' => __('Obrigação legal (lei fiscal angolana) e execução do contrato.'),
                'retencao' => __('Pelo prazo de conservação dos documentos fiscais imposto pela lei angolana.'),
                'destinatarios' => __('Administração Geral Tributária (AGT).'),
                'onde' => ['tenants'],
            ],
            [
                'chave' => 'subscricao',
                'icone' => 'fa-file-invoice-dollar',
                'titulo' => __('Subscrição e pagamentos'),
                'dados' => [__('Plano e ciclo'), __('Pedidos de plano'), __('Referência e comprovativo de transferência'), __('Facturas da subscrição')],
                'finalidade' => __('Cobrar e comprovar o pagamento do serviço.'),
                'base_legal' => __('Execução do contrato e obrigação legal.'),
                'retencao' => __('Pelo prazo de conservação dos documentos contabilísticos imposto pela lei angolana.'),
                'destinatarios' => __('Ninguém fora do SOSERP.'),
                'onde' => ['subscriptions', 'orders', 'invoices'],
            ],
            [
                'chave' => 'acessos',
                'icone' => 'fa-right-to-bracket',
                'titulo' => __('Acessos e segurança'),
                'dados' => [__('Endereço IP completo de cada entrada, saída e tentativa falhada'), __('Browser e sistema (user agent)'), __('Data e hora do último acesso em cada empresa'), __('Sessões abertas e aparelhos do PWA'), __('Email escrito numa tentativa de entrada falhada')],
                'finalidade' => __('Proteger as contas: bloquear ataques (5 tentativas falhadas bloqueiam 10 minutos), detectar acessos indevidos, saber quem entrou.'),
                'base_legal' => __('Interesse legítimo na segurança do serviço e dos dados (RGPD art. 6.º-1-f; LGPD art. 7.º-IX).'),
                'retencao' => __('Sessões: 2 horas de inactividade. Registos de entrada: com a trilha de auditoria (prova de quem fez o quê).'),
                'destinatarios' => __('Ninguém fora do SOSERP.'),
                'onde' => ['sessions', 'audit_trail', 'pwa_devices', 'api_tokens'],
            ],
            [
                'chave' => 'auditoria',
                'icone' => 'fa-clipboard-list',
                'titulo' => __('Trilha de auditoria'),
                'dados' => [__('O que cada pessoa criou, alterou ou apagou, com o valor antes e depois'), __('IP e rota de cada operação'), __('Quem agiu em nome de quem (suporte da plataforma)')],
                'finalidade' => __('Integridade dos documentos e prova de quem fez o quê — exigência dos documentos fiscais certificados.'),
                'base_legal' => __('Obrigação legal e interesse legítimo.'),
                'retencao' => __('Pelo prazo dos documentos a que respeita. A trilha não se reescreve: é encadeada e selada.'),
                'destinatarios' => __('A empresa a que respeita; autoridades, quando exigido por lei.'),
                'onde' => ['audit_trail'],
            ],
            [
                'chave' => 'estatisticas',
                'icone' => 'fa-chart-line',
                'titulo' => __('Visitas ao site e utilização'),
                'dados' => [__('Páginas vistas e tempo em cada uma'), __('Origem da visita (site anterior, campanha)'), __('Aparelho, browser e sistema'), __('País'), __('Com o seu consentimento: cidade, IP truncado (x.x.x.0) e um identificador de visita num cookie de 1 ano'), __('Termos pesquisados no site')],
                'finalidade' => __('Perceber o que as pessoas procuram e melhorar o site e o serviço.'),
                'base_legal' => __('Consentimento para cookies e localização (RGPD art. 6.º-1-a; Directiva ePrivacy art. 5.º-3); sem consentimento conta-se a visita sem cookie, sem IP e sem cidade.'),
                'retencao' => sprintf(__('%d meses; o IP truncado apaga-se ao fim de %d dias.'), (int) round($r['analytics_eventos'] / 30.4), $r['analytics_ip']),
                'destinatarios' => __('Google Analytics e ip-api.com (localização aproximada), só com consentimento de estatísticas.'),
                'onde' => ['analytics_events'],
            ],
            [
                'chave' => 'marketing',
                'icone' => 'fa-bullhorn',
                'titulo' => __('Campanhas e publicidade'),
                'dados' => [__('Identificadores de clique de campanhas (fbclid, gclid)'), __('Eventos de visita e de conclusão do registo enviados ao Meta Pixel')],
                'finalidade' => __('Saber que campanhas trazem clientes.'),
                'base_legal' => __('Consentimento (pode retirá-lo a qualquer momento).'),
                'retencao' => __('13 meses.'),
                'destinatarios' => __('Meta Platforms (Facebook/Instagram) e Google, só com consentimento de marketing.'),
                'onde' => ['analytics_events'],
            ],
            [
                'chave' => 'comunicacoes',
                'icone' => 'fa-envelope',
                'titulo' => __('Comunicações e suporte'),
                'dados' => [__('Emails e SMS enviados (destinatário, assunto, início do texto)'), __('Pedidos de suporte e mensagens de contacto, com o IP de quem as enviou')],
                'finalidade' => __('Enviar avisos do serviço, responder a pedidos, provar que o aviso foi enviado.'),
                'base_legal' => __('Execução do contrato e interesse legítimo.'),
                'retencao' => sprintf(__('O conteúdo dos emails: %d meses. O IP das mensagens de contacto: %d meses.'), (int) round($r['registos_de_email_conteudo'] / 30.4), (int) round($r['mensagens_de_contacto_ip'] / 30.4)),
                'destinatarios' => __('Fornecedores de envio de email e SMS, que recebem apenas o destinatário e a mensagem.'),
                'onde' => ['email_logs', 'sms_logs', 'support_tickets', 'contact_messages'],
            ],
            [
                'chave' => 'consentimentos',
                'icone' => 'fa-handshake',
                'titulo' => __('Os seus consentimentos'),
                'dados' => [__('O que aceitou ou recusou, quando, em que versão das regras, IP truncado e browser')],
                'finalidade' => __('Provar que houve consentimento — obrigação do RGPD (art. 7.º-1).'),
                'base_legal' => __('Obrigação legal.'),
                'retencao' => sprintf(__('%d anos.'), (int) round($r['consentimentos'] / 365)),
                'destinatarios' => __('Ninguém fora do SOSERP.'),
                'onde' => ['consentimentos', 'pedidos_de_privacidade'],
            ],
        ];
    }

    /** Os direitos, com o artigo de cada lei — a mesma lista na política e no ecrã. */
    public static function direitos(): array
    {
        return [
            ['chave' => 'acesso', 'icone' => 'fa-eye', 'nome' => __('Acesso'), 'descricao' => __('Saber que dados temos sobre si e receber uma cópia.'), 'artigos' => 'RGPD 15.º · LGPD 18.º-II · Lei 22/11'],
            ['chave' => 'rectificacao', 'icone' => 'fa-pen', 'nome' => __('Rectificação'), 'descricao' => __('Corrigir dados errados ou incompletos.'), 'artigos' => 'RGPD 16.º · LGPD 18.º-III · Lei 22/11'],
            ['chave' => 'apagamento', 'icone' => 'fa-trash', 'nome' => __('Apagamento'), 'descricao' => __('Pedir que apaguemos os seus dados, salvo os que a lei nos obriga a guardar.'), 'artigos' => 'RGPD 17.º · LGPD 18.º-VI · Lei 22/11'],
            ['chave' => 'portabilidade', 'icone' => 'fa-file-export', 'nome' => __('Portabilidade'), 'descricao' => __('Receber os seus dados num formato legível por máquina (JSON).'), 'artigos' => 'RGPD 20.º · LGPD 18.º-V'],
            ['chave' => 'oposicao', 'icone' => 'fa-hand', 'nome' => __('Oposição e limitação'), 'descricao' => __('Opor-se a um tratamento ou pedir que fique suspenso.'), 'artigos' => 'RGPD 18.º e 21.º · LGPD 18.º-IV · Lei 22/11'],
            ['chave' => 'consentimento', 'icone' => 'fa-toggle-off', 'nome' => __('Retirar o consentimento'), 'descricao' => __('A qualquer momento, sem afectar o que foi feito antes.'), 'artigos' => 'RGPD 7.º-3 · LGPD 8.º-§5'],
            ['chave' => 'reclamacao', 'icone' => 'fa-scale-balanced', 'nome' => __('Reclamar'), 'descricao' => __('Junto da APD (Angola), da ANPD (Brasil) ou da autoridade de controlo do seu país na UE.'), 'artigos' => 'RGPD 77.º · LGPD 18.º-§1 · Lei 22/11'],
        ];
    }
}
