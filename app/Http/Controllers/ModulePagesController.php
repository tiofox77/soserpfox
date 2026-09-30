<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\DadosEstruturados;

class ModulePagesController extends Controller
{
    /**
     * Páginas dedicadas a cada módulo, com features ilustradas.
     */
    protected array $modules = [
        // ============================================================
        'vendas' => [
            'slug' => 'vendas',
            'plan_slug' => 'pacote-vendas',
            'name' => '📊 Vendas & Faturação',
            'tagline' => 'POS, faturação e gestão de clientes em qualquer lugar — mesmo sem internet',
            'description' => 'Solução completa de ponto de venda e faturação certificada AGT. Trabalha online e offline.',
            'icon' => 'fa-cash-register',
            'gradient_from' => '#ea580c',
            'gradient_to' => '#dc2626',
            'whatsapp' => '244939729902',
            'hero_features' => [
                'POS Offline (funciona sem internet)',
                'Faturação certificada AGT',
                'Multi-utilizador e multi-dispositivo',
                'Relatórios em tempo real',
            ],
            'targets' => [
                ['label' => 'Lojas e supermercados', 'icon' => 'fa-store'],
                ['label' => 'Padarias, farmácias e mercearias', 'icon' => 'fa-store'],
                ['label' => 'Distribuição e atacado', 'icon' => 'fa-boxes-stacked'],
                ['label' => 'Atividades com balcão e equipa móvel', 'icon' => 'fa-users'],
            ],
            'workflow' => [
                ['icone' => 'fa-box-open', 'passo' => 'Cadastro', 'detalhe' => 'Produtos, preços, clientes e regras fiscais carregados uma vez.'],
                ['icone' => 'fa-cash-register', 'passo' => 'Venda', 'detalhe' => 'Emite FR/RC no balcão, em mesa ou na rua, mesmo sem internet.'],
                ['icone' => 'fa-arrows-rotate', 'passo' => 'Sincronização', 'detalhe' => 'Os documentos em fila sincronizam automaticamente quando há sinal disponível.'],
                ['icone' => 'fa-file-lines', 'passo' => 'Controlo', 'detalhe' => 'Consulta relatórios e acompanha margem, fluxo de caixa e contas por pagar/receber.'],
            ],
            'metrics' => [
                ['valor' => 'Redução de atraso', 'texto' => 'Emissões em segundos, sem perda de documentos offline'],
                ['valor' => 'Maior controlo', 'texto' => 'Stock e caixa consolidados por utilizador e turno'],
                ['valor' => 'Conformidade', 'texto' => 'Documentação fiscal acompanhando o AGT e as séries corretas'],
            ],
            'integrations' => ['RH', 'Tesouraria', 'Inventário', 'BI/Indicadores'],
            'features' => [
                ['icon' => 'fa-file-invoice', 'title' => 'Faturação Completa', 'desc' => 'Emite FT, FR, NC e Proformas com numeração legal AGT, hash SAFT e séries fiscais.'],
                ['icon' => 'fa-mobile-screen-button', 'title' => 'POS Offline (PWA)', 'desc' => 'App instalável no telemóvel/tablet. Vende sem internet — sincroniza automaticamente ao reconectar.'],
                ['icon' => 'fa-users', 'title' => 'Gestão de Clientes', 'desc' => 'Base de dados de clientes com NIF, histórico de compras, conta-corrente e extracto.'],
                ['icon' => 'fa-boxes-stacked', 'title' => 'Catálogo de Produtos', 'desc' => 'Produtos e serviços com imagens, SKU, código de barras, categorias e taxas de IVA.'],
                ['icon' => 'fa-warehouse', 'title' => 'Controlo de Stock', 'desc' => 'Inventário multi-armazém com movimentos automáticos por venda.'],
                ['icon' => 'fa-receipt', 'title' => 'Recibos & Pagamentos', 'desc' => 'Múltiplos métodos: dinheiro, multicaixa, cartão, transferência. Vista de tesouraria.'],
                ['icon' => 'fa-chart-line', 'title' => 'Relatórios de Vendas', 'desc' => 'Vendas por dia, produto, cliente e utilizador, e um painel com o que entrou e o que está por receber.'],
                ['icon' => 'fa-shield-halved', 'title' => 'Certificação AGT', 'desc' => 'Documentos assinados, séries fiscais, comunicação à AGT e o ficheiro SAFT-AO gerado pelo sistema.'],
                ['icon' => 'fa-print', 'title' => 'Impressão & PDF', 'desc' => 'Imprime em impressoras térmicas (POS) ou gera PDF profissional para envio por email.'],
            ],
            'screenshots' => [
                ['title' => 'Dashboard de Vendas', 'desc' => 'Vista consolidada de KPIs, vendas do dia e ranking', 'icon' => 'fa-chart-pie'],
                ['title' => 'POS Touch', 'desc' => 'Grid de produtos com pesquisa rápida e scan de código de barras', 'icon' => 'fa-cash-register'],
                ['title' => 'Faturação', 'desc' => 'Editor de fatura com IVA, descontos e múltiplos itens', 'icon' => 'fa-file-invoice-dollar'],
            ],
        ],

        // ============================================================
        'rh' => [
            'slug' => 'rh',
            'plan_slug' => 'pacote-rh',
            'name' => '👥 Recursos Humanos',
            'tagline' => 'Folha de pagamento angolana automática — INSS, IRT e segurança social descontados em segundos',
            'description' => 'Gestão completa de colaboradores, assiduidade, férias e processamento salarial.',
            'icon' => 'fa-users',
            'gradient_from' => '#7c3aed',
            'gradient_to' => '#2563eb',
            'whatsapp' => '244939729902',
            'hero_features' => [
                'Folha de pagamento automática',
                'INSS e IRT angolanos integrados',
                'Recibo de vencimento em PDF',
                'Ficha do trabalhador completa',
            ],
            'targets' => [
                ['label' => 'Empresas com funcionários fixos e eventuais', 'icon' => 'fa-user-tie'],
                ['label' => 'Serviços de produção, comércio e retalho', 'icon' => 'fa-industry'],
                ['label' => 'Pequenas e médias empresas', 'icon' => 'fa-building'],
                ['label' => 'Equipas com horários de turno', 'icon' => 'fa-business-time'],
            ],
            'workflow' => [
                ['icone' => 'fa-user', 'passo' => 'Cadastro', 'detalhe' => 'Regista empregados, contratos, turnos e rubricas aplicáveis por cargo.'],
                ['icone' => 'fa-clock', 'passo' => 'Registo diário', 'detalhe' => 'Controla assiduidade, atrasos, faltas e horas extra por pessoa.'],
                ['icone' => 'fa-money-bill-wave', 'passo' => 'Processamento', 'detalhe' => 'Gera remuneração com INSS + IRT e aplica adiantamentos/ descontos.'],
                ['icone' => 'fa-file-invoice', 'passo' => 'Validação', 'detalhe' => 'Valida folha, emite recibos e partilha com o gestor responsável.'],
            ],
            'metrics' => [
                ['valor' => 'Cálculo ágil', 'texto' => 'Folha mensal calculada com regras angolanas em minutos'],
                ['valor' => 'Erros reduzidos', 'texto' => 'Processo único e auditável em vez de cálculo manual'],
                ['valor' => 'Transparência', 'texto' => 'Registo de aprovação, adiantamentos e histórico completo por trabalhador'],
            ],
            'integrations' => ['Faturação', 'Tesouraria', 'Contabilidade', 'Portal do colaborador'],
            'features' => [
                ['icon' => 'fa-id-card', 'title' => 'Ficha do Trabalhador', 'desc' => 'Dados pessoais, documentos, dependentes, contactos de emergência e formação académica.'],
                ['icon' => 'fa-clock', 'title' => 'Assiduidade', 'desc' => 'Registo de entrada/saída, faltas, atrasos e horas extras. Relatórios mensais.'],
                ['icon' => 'fa-umbrella-beach', 'title' => 'Férias e Licenças', 'desc' => 'Pedidos de férias, licenças e faltas, aprovados por quem tem essa permissão, e o saldo de férias de cada trabalhador.'],
                ['icon' => 'fa-file-invoice-dollar', 'title' => 'Processamento Salarial', 'desc' => 'IRT pela tabela angolana e INSS (3% do trabalhador, 8% da empresa) calculados em cada recibo, com subsídios e descontos.'],
                ['icon' => 'fa-receipt', 'title' => 'Recibo de Vencimento', 'desc' => 'O recibo de cada trabalhador, ou os de toda a folha do mês, em PDF pronto a imprimir ou a partilhar.'],
                ['icon' => 'fa-sitemap', 'title' => 'Departamentos & Cargos', 'desc' => 'Estrutura organizacional, hierarquia e gestão de cargos com tabela salarial.'],
                ['icon' => 'fa-hand-holding-dollar', 'title' => 'Adiantamentos', 'desc' => 'Pedidos de adiantamento salarial com aprovação e desconto automático no próximo recibo.'],
                ['icon' => 'fa-business-time', 'title' => 'Horas Extras e Descontos', 'desc' => 'Horas extras, turno nocturno e descontos salariais com aprovação, que entram sozinhos na folha do mês.'],
                ['icon' => 'fa-chart-bar', 'title' => 'Relatórios RH', 'desc' => 'Mapa de salários, custo por departamento, resumo de presenças, saldo de férias e evolução do quadro de pessoal.'],
            ],
            'screenshots' => [
                ['title' => 'Painel de RH', 'desc' => 'Presenças do dia, pedidos por aprovar e aniversariantes', 'icon' => 'fa-users'],
                ['title' => 'Processamento Salarial', 'desc' => 'Cálculo automático de IRT e INSS em conformidade com a lei', 'icon' => 'fa-calculator'],
                ['title' => 'Ficha do Trabalhador', 'desc' => 'Dossier completo do colaborador com PDF exportável', 'icon' => 'fa-file-lines'],
            ],
        ],

        // ============================================================
        'hotel' => [
            'slug' => 'hotel',
            'plan_slug' => 'pacote-hotel',
            'name' => '🏨 Gestão de Hotel',
            'tagline' => 'Booking engine, reservas, check-in e channel manager — tudo no mesmo sistema',
            'description' => 'Solução completa para hotéis, pousadas e residenciais. Aumenta a tua ocupação com motor de reservas online.',
            'icon' => 'fa-hotel',
            'gradient_from' => '#0891b2',
            'gradient_to' => '#2563eb',
            'whatsapp' => '244939729902',
            'hero_features' => [
                'Motor de reservas online (booking engine)',
                'Channel Manager (Booking.com, Airbnb)',
                'Check-in / Check-out rápido',
                'Housekeeping e gestão de quartos',
            ],
            'targets' => [
                ['label' => 'Hotéis e pousadas independentes', 'icon' => 'fa-hotel'],
                ['label' => 'Hostels e residenciais', 'icon' => 'fa-house-user'],
                ['label' => 'Apartamentos e alojamentos', 'icon' => 'fa-building'],
                ['label' => 'Propriedade com receitas de serviços auxiliares', 'icon' => 'fa-concierge-bell'],
            ],
            'workflow' => [
                ['icone' => 'fa-calendar-check', 'passo' => 'Entrada de reserva', 'detalhe' => 'Reserva direta ou por canais, com regras de política comercial e política de cancelamento.'],
                ['icone' => 'fa-door-open', 'passo' => 'Alocação', 'detalhe' => 'Atribui quartos por tipo, vista e status de arrumação em tempo real.'],
                ['icone' => 'fa-clipboard-list', 'passo' => 'Consumo', 'detalhe' => 'Consumos (restaurante, minibar, serviços) agregados à estada.'],
                ['icone' => 'fa-file-invoice', 'passo' => 'Fecho', 'detalhe' => 'Gera conta/receita do hóspede e faturação com histórico por reserva.'],
            ],
            'metrics' => [
                ['valor' => 'Mais ocupação', 'texto' => 'Reservas diretas com menos dependência de comissões externas'],
                ['valor' => 'Operação fluida', 'texto' => 'Menos duplos registos entre receção, serviço e faturação'],
                ['valor' => 'Rentabilidade', 'texto' => 'Cálculo de ADR e RevPAR por período com filtros de origem'],
            ],
            'integrations' => ['Restaurante', 'Contabilidade', 'Tesouraria', 'Faturação'],
            'features' => [
                ['icon' => 'fa-globe', 'title' => 'Booking Engine', 'desc' => 'Motor de reservas online no teu website. Recebe reservas diretas 24/7 sem comissões a terceiros.'],
                ['icon' => 'fa-network-wired', 'title' => 'Channel Manager', 'desc' => 'Sincronização com Booking.com, Airbnb, Expedia. Disponibilidade e preços em tempo real.'],
                ['icon' => 'fa-door-open', 'title' => 'Gestão de Quartos', 'desc' => 'Mapa visual por piso, status (livre/ocupado/limpeza), tipos de quarto e tarifas dinâmicas.'],
                ['icon' => 'fa-calendar-check', 'title' => 'Reservas', 'desc' => 'Calendário multi-vista (dia/semana/mês), holdings, walk-ins e gestão de grupos.'],
                ['icon' => 'fa-sign-in-alt', 'title' => 'Check-in / Check-out', 'desc' => 'Processo rápido com leitura de BI/passaporte, assinatura digital e ficha do hóspede.'],
                ['icon' => 'fa-broom', 'title' => 'Housekeeping', 'desc' => 'Atribuição de quartos a governantas, status de limpeza e checklist de manutenção.'],
                ['icon' => 'fa-utensils', 'title' => 'Charges & Extras', 'desc' => 'Restaurante, mini-bar, lavandaria. Tudo na conta do quarto, faturado no check-out.'],
                ['icon' => 'fa-credit-card', 'title' => 'Faturação Integrada', 'desc' => 'Folha de conta detalhada com FT/FR automática. Garantias e pré-autorizações de cartão.'],
                ['icon' => 'fa-chart-area', 'title' => 'Analytics & RevPAR', 'desc' => 'Ocupação, ADR, RevPAR, origem das reservas, taxa de conversão e forecasting.'],
            ],
            'screenshots' => [
                ['title' => 'Mapa de Quartos', 'desc' => 'Vista por piso com status visual em tempo real', 'icon' => 'fa-hotel'],
                ['title' => 'Calendário de Reservas', 'desc' => 'Gantt-style com drag-and-drop entre quartos', 'icon' => 'fa-calendar-days'],
                ['title' => 'Booking Engine', 'desc' => 'Página pública para reservas diretas no teu site', 'icon' => 'fa-globe'],
            ],
        ],

        // ============================================================
        'salao' => [
            'slug' => 'salao',
            'plan_slug' => 'pacote-salao',
            'name' => '💇 Salão de Beleza',
            'tagline' => 'Agendamento online, comissões automáticas e clientes fidelizados',
            'description' => 'Para salões, barbearias e spas. Clientes marcam pelo telemóvel, profissionais recebem comissões automaticamente.',
            'icon' => 'fa-spa',
            'gradient_from' => '#db2777',
            'gradient_to' => '#9333ea',
            'whatsapp' => '244939729902',
            'hero_features' => [
                'Agendamento online 24/7',
                'Cálculo automático de comissões',
                'Fidelização de clientes',
                'Lembretes por SMS / WhatsApp',
            ],
            'targets' => [
                ['label' => 'Salões e barbearias', 'icon' => 'fa-chair'],
                ['label' => 'Spas e estúdios de beleza', 'icon' => 'fa-spa'],
                ['label' => 'Serviços de estética e bem-estar', 'icon' => 'fa-heart'],
                ['label' => 'Equipas com comissão por serviço', 'icon' => 'fa-hand-holding-dollar'],
            ],
            'workflow' => [
                ['icone' => 'fa-calendar-plus', 'passo' => 'Agendamento', 'detalhe' => 'Cliente marca em 24h e entra na agenda com profissional disponível.'],
                ['icone' => 'fa-scissors', 'passo' => 'Execução', 'detalhe' => 'Atendimento no ponto de venda com ficha e serviços executados.'],
                ['icone' => 'fa-wallet', 'passo' => 'Pagamento', 'detalhe' => 'Fecho rápido com métodos múltiplos e venda de produtos de apoio.'],
                ['icone' => 'fa-gift', 'passo' => 'Retenção', 'detalhe' => 'Programa de fidelização e histórico de atendimento para retorno recorrente.'],
            ],
            'metrics' => [
                ['valor' => 'Mais agendamentos', 'texto' => 'Menos cancelamentos por confirmação automática'],
                ['valor' => 'Produtividade', 'texto' => 'Agenda otimizada por profissional e duração padrão por serviço'],
                ['valor' => 'Ticket médio', 'texto' => 'Vendas adicionais de serviços e produtos integradas no checkout'],
            ],
            'integrations' => ['POS', 'RH', 'Marketing', 'Faturação'],
            'features' => [
                ['icon' => 'fa-calendar-plus', 'title' => 'Agendamento Online', 'desc' => 'Os clientes marcam pelo telemóvel a qualquer hora. Vê a agenda dos profissionais em tempo real.'],
                ['icon' => 'fa-user-tie', 'title' => 'Gestão de Profissionais', 'desc' => 'Horário, especialidades, comissões por serviço e disponibilidade individual.'],
                ['icon' => 'fa-percent', 'title' => 'Comissões Automáticas', 'desc' => 'Calcula a comissão de cada profissional sobre serviços e produtos. Mapa mensal de pagamento.'],
                ['icon' => 'fa-scissors', 'title' => 'Catálogo de Serviços', 'desc' => 'Cortes, manicure, tratamentos, massagens. Duração, preço e categorias coloridas.'],
                ['icon' => 'fa-heart', 'title' => 'Fidelização', 'desc' => 'Cartão de pontos, descontos para clientes habituais e campanhas promocionais.'],
                ['icon' => 'fa-bell', 'title' => 'Lembretes Automáticos', 'desc' => 'Envia SMS ou WhatsApp ao cliente 24h antes da marcação. Reduz no-shows em 60%.'],
                ['icon' => 'fa-cash-register', 'title' => 'POS para Receção', 'desc' => 'Checkout rápido após o serviço. Aceita gorjetas, vendas de produtos e fatura na hora.'],
                ['icon' => 'fa-history', 'title' => 'Histórico do Cliente', 'desc' => 'Vê todos os serviços anteriores, profissional preferido, produtos comprados e notas.'],
                ['icon' => 'fa-chart-pie', 'title' => 'Relatórios', 'desc' => 'Receita por profissional/serviço, tempo médio de espera, taxa de ocupação da agenda.'],
            ],
            'screenshots' => [
                ['title' => 'Agenda Multi-profissional', 'desc' => 'Vista diária com colunas por funcionária e drag-drop', 'icon' => 'fa-calendar-week'],
                ['title' => 'Booking Online', 'desc' => 'Cliente escolhe serviço, profissional e horário pelo telemóvel', 'icon' => 'fa-mobile-screen'],
                ['title' => 'Mapa de Comissões', 'desc' => 'Cálculo automático mensal com detalhe por colaboradora', 'icon' => 'fa-coins'],
            ],
        ],

        // ============================================================
        'oficina' => [
            'slug' => 'oficina',
            'plan_slug' => 'pacote-oficina',
            'name' => '🔧 Oficina Auto',
            'tagline' => 'Ordens de reparação, peças, mão-de-obra e faturação — gestão completa de oficinas',
            'description' => 'Para oficinas auto, mecânicas e centros de inspeção. Controlo total de veículos, OS e equipa.',
            'icon' => 'fa-wrench',
            'gradient_from' => '#ea580c',
            'gradient_to' => '#854d0e',
            'whatsapp' => '244939729902',
            'hero_features' => [
                'Ordens de Reparação (OS) digitais',
                'Orçamentos com aprovação por SMS',
                'Histórico completo por viatura',
                'Gestão de mecânicos e tempo',
            ],
            'targets' => [
                ['label' => 'Oficinas mecânicas', 'icon' => 'fa-wrench'],
                ['label' => 'Centros de inspeção e manutenção', 'icon' => 'fa-car-burst'],
                ['label' => 'Frotas e locadoras', 'icon' => 'fa-people-carry-box'],
                ['label' => 'Auto-varejo de peças e serviços', 'icon' => 'fa-cogs'],
            ],
            'workflow' => [
                ['icone' => 'fa-clipboard-list', 'passo' => 'Diagnóstico', 'detalhe' => 'Regista a entrada, sintomas e responsável técnico por viatura.'],
                ['icone' => 'fa-file-signature', 'passo' => 'Orçamento', 'detalhe' => 'Gera proposta detalhada e aguarda validação do cliente.'],
                ['icone' => 'fa-screwdriver-wrench', 'passo' => 'Execução', 'detalhe' => 'Acompanha peças, tempo de mão de obra e regista evolução por técnico.'],
                ['icone' => 'fa-file-invoice', 'passo' => 'Encerramento', 'detalhe' => 'Conclui OS, entrega documento e fatura com IVA e detalhado de serviços.'],
            ],
            'metrics' => [
                ['valor' => 'Mais controlo', 'texto' => 'Todas as viaturas e ordens em histórico único e rastreável'],
                ['valor' => 'Mais margem', 'texto' => 'Custos por peça e mão de obra visíveis antes do fecho final'],
                ['valor' => 'Mais confiança', 'texto' => 'Orçamento aprovado e documentação completa para o cliente'],
            ],
            'integrations' => ['Inventário', 'Faturação', 'Tesouraria', 'RH'],
            'features' => [
                ['icon' => 'fa-car', 'title' => 'Base de Veículos', 'desc' => 'Cadastro com matrícula, VIN, marca/modelo, seguros e inspeção. Alertas de vencimento.'],
                ['icon' => 'fa-clipboard-list', 'title' => 'Ordens de Reparação', 'desc' => 'OS digital com problema, diagnóstico, trabalhos e peças. Estados visuais (em curso, aguarda peças, etc).'],
                ['icon' => 'fa-file-contract', 'title' => 'Orçamentos', 'desc' => 'Gera orçamento em PDF profissional, envia ao cliente para aprovação por SMS/WhatsApp.'],
                ['icon' => 'fa-screwdriver-wrench', 'title' => 'Catálogo de Serviços', 'desc' => 'Mão-de-obra (revisão, mecânica, chapa, pintura, elétrica) com custo e tempo estimado.'],
                ['icon' => 'fa-cogs', 'title' => 'Gestão de Peças', 'desc' => 'Stock de peças com SKU, marca, original/genérico. Baixa automática ao concluir a OS.'],
                ['icon' => 'fa-hard-hat', 'title' => 'Mecânicos & Equipa', 'desc' => 'Atribuição de OS a mecânicos, horas trabalhadas e produtividade por colaborador.'],
                ['icon' => 'fa-camera', 'title' => 'Fotos & Anexos', 'desc' => 'Anexa fotos antes/depois, faturas de peças, documentos da viatura. Tudo no histórico.'],
                ['icon' => 'fa-file-invoice', 'title' => 'Faturação Integrada', 'desc' => 'Conclui a OS e fatura automaticamente com IVA, peças e mão-de-obra detalhados.'],
                ['icon' => 'fa-chart-line', 'title' => 'Relatórios', 'desc' => 'Faturação por mecânico, serviços mais procurados, viaturas mais rentáveis, tempo médio.'],
            ],
            'screenshots' => [
                ['title' => 'Dashboard Oficina', 'desc' => 'KPIs de OS abertas, aguarda peças, faturação do mês', 'icon' => 'fa-tachometer-alt'],
                ['title' => 'OS Detalhada', 'desc' => 'Diagnóstico, itens (peças + serviços), fotos e histórico', 'icon' => 'fa-clipboard-check'],
                ['title' => 'Histórico do Veículo', 'desc' => 'Todas as intervenções, custos e quilometragem', 'icon' => 'fa-car-side'],
            ],
        ],

        // ============================================================
        'restaurant' => [
            'slug' => 'restaurant',
            'plan_slug' => 'pacote-restaurante',
            'name' => '🍽️ Gestão de Restaurante',
            'tagline' => 'Sala, comandas, cozinha e faturação num único sistema preparado para Angola',
            'description' => 'Solução completa para restaurantes, bares, cafés e pastelarias, ligada à Faturação AGT, Tesouraria e Stock.',
            'icon' => 'fa-utensils',
            'gradient_from' => '#ea580c',
            'gradient_to' => '#dc2626',
            'whatsapp' => '244939729902',
            'hero_features' => [
                'Mapa de sala e mesas em tempo real',
                'Comandas digitais e conta dividida',
                'Cozinha/KDS com tickets e tempos',
                'Faturação AGT e pagamentos integrados',
            ],
            'targets' => [
                ['label' => 'Restaurantes familiares', 'icon' => 'fa-utensils'],
                ['label' => 'Bares e cafés', 'icon' => 'fa-mug-hot'],
                ['label' => 'Pastelarias e fast-food', 'icon' => 'fa-burger'],
                ['label' => 'Delivery e takeaway', 'icon' => 'fa-motorcycle'],
            ],
            'workflow' => [
                ['icone' => 'fa-chair', 'passo' => 'Abertura', 'detalhe' => 'Define turno, mesa, equipa e estado inicial da sala.'],
                ['icone' => 'fa-receipt', 'passo' => 'Pedido', 'detalhe' => 'Cria comanda por mesa ou balcão com observações e taxas aplicadas.'],
                ['icone' => 'fa-fire-burner', 'passo' => 'Preparação', 'detalhe' => 'O KDS repassa para cozinha com prioridade por tempo e origem do pedido.'],
                ['icone' => 'fa-wallet', 'passo' => 'Entrega e fecho', 'detalhe' => 'Entrega para mesa, divide a conta e emite fatura certificada.'],
            ],
            'metrics' => [
                ['valor' => 'Mais velocidade', 'texto' => 'Comandas aprovadas em segundos, sem filas desnecessárias'],
                ['valor' => 'Menos erros', 'texto' => 'KDS e observações claras para equipa de cozinha'],
                ['valor' => 'Maior receita', 'texto' => 'Up-sell de cartas, receitas controladas e rastreio por prato'],
            ],
            'integrations' => ['POS', 'Faturação', 'Tesouraria', 'Stock'],
            'features' => [
                ['icon' => 'fa-chair', 'title' => 'Sala e Mesas', 'desc' => 'Zonas, capacidade, estados, reservas, transferência e junção de mesas numa vista tátil e responsiva.'],
                ['icon' => 'fa-receipt', 'title' => 'Comandas Digitais', 'desc' => 'Pedidos por mesa, balcão, takeaway ou delivery, com observações, histórico e auditoria.'],
                ['icon' => 'fa-fire-burner', 'title' => 'Cozinha / KDS', 'desc' => 'Tickets por estação, fila de preparação, tempos, impressão e estados até à entrega.'],
                ['icon' => 'fa-calendar-check', 'title' => 'Reservas', 'desc' => 'Agenda de reservas, conflitos de horário, capacidade, confirmação, no-show e libertação da mesa.'],
                ['icon' => 'fa-book-open', 'title' => 'Fichas Técnicas', 'desc' => 'Receitas, rendimento, ingredientes, unidades e desperdício técnico por prato.'],
                ['icon' => 'fa-boxes-stacked', 'title' => 'Stock e Desperdícios', 'desc' => 'Consumo automático por receita, movimentos auditados e controlo de ruturas.'],
                ['icon' => 'fa-money-bill-transfer', 'title' => 'Conta e Pagamentos', 'desc' => 'Divisão por artigos e pagamentos mistos com métodos oficiais da Tesouraria.'],
                ['icon' => 'fa-file-invoice', 'title' => 'Faturação AGT', 'desc' => 'O fecho da conta emite fatura ou fatura-recibo pela faturação certificada, com as séries e o IVA da empresa.'],
                ['icon' => 'fa-chart-line', 'title' => 'Relatórios', 'desc' => 'Vendas, comandas, ticket médio, reservas, desperdícios e produtos mais vendidos.'],
            ],
            'screenshots' => [
                ['title' => 'Mapa de Sala', 'desc' => 'Mesas e estados operacionais por zona', 'icon' => 'fa-chair'],
                ['title' => 'Cozinha / KDS', 'desc' => 'Fila de tickets com tempos e prioridade', 'icon' => 'fa-fire-burner'],
                ['title' => 'Fecho da Conta', 'desc' => 'Divisão por artigos, pagamentos mistos e FR/FT', 'icon' => 'fa-cash-register'],
            ],
        ],
    ];

    /**
     * O QUE CADA PÁGINA DIZ A QUEM PESQUISA — título, descrição, categoria e
     * as perguntas frequentes (que a página mostra e o JSON-LD repete).
     *
     * Só as páginas trabalhadas uma a uma estão aqui; as outras continuam com
     * o nome e a descrição do módulo. Cada resposta foi confirmada contra o
     * que o sistema faz — o recibo «enviado por email com QR code» saiu da
     * página de RH porque não existe.
     */
    protected array $paginas = [
        'hotel' => [
            'titulo' => 'Software de Gestão Hoteleira em Angola',
            'descricao' => 'Gerencie reservas, quartos, check-in, check-out e faturação do seu hotel em Angola com o SOSERP. Consulte funcionalidades e planos de hotelaria.',
            'categoria' => 'Gestão hoteleira',
            'migalha' => 'Hotel',
        ],
        'salao' => [
            'titulo' => 'Software para Salão de Beleza em Angola',
            'descricao' => 'Organize marcações, serviços, profissionais e faturação do seu salão de beleza em Angola. Conheça o módulo SOSERP para salões e barbearias.',
            'categoria' => 'Gestão de salão de beleza',
            'migalha' => 'Salão de beleza',
        ],
        'oficina' => [
            'titulo' => 'Software de Gestão de Oficinas Auto em Angola',
            'descricao' => 'Acompanhe viaturas, ordens de reparação, peças e faturação da sua oficina em Angola. Conheça as funcionalidades e os planos do SOSERP.',
            'categoria' => 'Gestão de oficina auto',
            'migalha' => 'Oficina auto',
        ],
        'vendas' => [
            'titulo' => 'Software de Faturação e POS em Angola, certificado AGT',
            'descricao' => 'Faturação certificada pela AGT, POS que vende sem internet, stock por armazém e SAFT-AO. Para lojas, farmácias e mercearias em Angola. Experimente grátis.',
            'categoria' => 'Faturação e ponto de venda',
            'migalha' => 'Faturação e POS',
            'perguntas' => [
                ['O POS funciona sem internet?', 'Sim. O POS instala-se como aplicação no telemóvel, tablet ou computador e continua a vender sem rede; as vendas sincronizam com o servidor quando a ligação volta.'],
                ['A faturação é certificada pela AGT?', 'Sim. O SOSERP é software de faturação certificado pela AGT (:certificado): os documentos saem assinados, com numeração por série, e o ficheiro SAFT-AO é gerado pelo próprio sistema.'],
                ['Que documentos posso emitir?', 'Faturas, faturas-recibo, recibos, notas de crédito e de débito, proformas, orçamentos, adiantamentos e guias de transporte.'],
                ['Posso controlar o stock de vários armazéns?', 'Sim. O stock é controlado por armazém, com transferências entre armazéns, lotes e prazos de validade, e baixa automática a cada venda.'],
            ],
        ],
        'rh' => [
            'titulo' => 'Software de RH e Folha de Pagamento em Angola',
            'descricao' => 'Processamento salarial com IRT e INSS angolanos, assiduidade, férias, horas extras e adiantamentos, com recibos de vencimento em PDF. Experimente grátis.',
            'categoria' => 'Recursos humanos e processamento salarial',
            'migalha' => 'Recursos Humanos',
            'perguntas' => [
                ['O IRT e o INSS são calculados automaticamente?', 'Sim. O processamento salarial aplica a tabela de IRT angolana e as contribuições para o INSS — 3% do trabalhador e 8% da entidade empregadora — em cada recibo.'],
                ['Posso emitir os recibos de vencimento?', 'Sim. Pode tirar o recibo de cada trabalhador ou os de toda a folha do mês, em PDF.'],
                ['Controla férias, faltas e horas extras?', 'Sim. Assiduidade, férias, licenças e faltas, horas extras, adiantamentos e descontos salariais, com quem pede separado de quem aprova.'],
                ['Os adiantamentos são descontados no salário?', 'Sim. O adiantamento aprova-se por um valor e em prestações, e cada prestação é descontada na folha dos meses seguintes.'],
            ],
        ],
        'restaurant' => [
            'titulo' => 'Software para Restaurante em Angola: Sala, Comandas e Cozinha',
            'descricao' => 'Mapa de sala, comandas, cozinha (KDS), reservas, fichas técnicas e fecho da conta com faturação AGT. Para restaurantes, bares e cafés em Angola.',
            'categoria' => 'Gestão de restaurante',
            'migalha' => 'Restaurante',
            'perguntas' => [
                ['Os pedidos chegam à cozinha?', 'Sim. Cada comanda gera tickets no ecrã da cozinha (KDS), com o estado de cada prato até à entrega na mesa.'],
                ['É possível dividir a conta?', 'Sim. A conta divide-se por artigos e aceita pagamento com vários métodos, como numerário e multicaixa.'],
                ['Os clientes podem ver a carta no telemóvel?', 'Sim. Cada mesa tem um QR que abre a carta digital; o cliente envia o pedido por WhatsApp ou, se o restaurante ligar essa opção, directamente para a sala.'],
                ['O fecho da conta emite fatura certificada?', 'Sim. Fechar a conta emite fatura ou fatura-recibo pela faturação certificada pela AGT do SOSERP.'],
            ],
        ],
    ];

    public function show(string $slug)
    {
        if (!isset($this->modules[$slug])) {
            abort(404);
        }

        $module = $this->modules[$slug];
        // Só um plano que se vende: um pacote desactivado ou escondido não
        // pode continuar a mostrar preço aqui nem a ir para os dados estruturados.
        $plan = Plan::publico()->where('slug', $module['plan_slug'])->first();

        $pagina = $this->paginas[$slug] ?? null;
        $url = DadosEstruturados::raiz() . '/modulos/' . $slug;
        $nomeLimpo = DadosEstruturados::semEmoji($module['name']);

        $perguntas = collect($pagina['perguntas'] ?? [])
            ->map(fn ($p) => [$p[0], str_replace(':certificado', \App\Helpers\AGTHelper::softwareValidationNumber(), $p[1])])
            ->all();

        if ($pagina && $plan) {
            $dias = (int) ($plan->trial_days ?: 14);
            $perguntas[] = ['Quanto custa?', 'O ' . DadosEstruturados::semEmoji($plan->name) . ' custa '
                . number_format((float) $plan->price_monthly, 0, ',', '.') . ' Kz por mês'
                . ((float) $plan->getRawOriginal('price_yearly') > 0 ? ' ou ' . number_format((float) $plan->getRawOriginal('price_yearly'), 0, ',', '.') . ' Kz por ano' : '')
                . ", com {$dias} dias grátis para experimentar."];
        }

        $seo = [
            'titulo' => $pagina['titulo'] ?? $nomeLimpo,
            'descricao' => $pagina['descricao'] ?? $module['description'],
            'url' => $url,
        ];

        $migalhas = [
            ['SOSERP', DadosEstruturados::raiz() . '/'],
            ['Módulos', DadosEstruturados::raiz() . '/modulos'],
            [$pagina['migalha'] ?? $nomeLimpo, $url],
        ];

        $oferta = $plan ? DadosEstruturados::oferta($plan, $url . '#pricing') + ['category' => $pagina['categoria'] ?? $nomeLimpo] : null;

        $dadosEstruturados = DadosEstruturados::script([
            DadosEstruturados::organizacao(),
            DadosEstruturados::site(),
            DadosEstruturados::software(false, $oferta ? [$oferta] : null),
            DadosEstruturados::pagina($url, $seo['titulo'], $seo['descricao'], $migalhas, [
                'mainEntity' => ['@id' => DadosEstruturados::id('software')],
                'hasPart' => ['@id' => $url . '#funcionalidades'],
                'keywords' => $pagina['categoria'] ?? $nomeLimpo,
            ]),
            DadosEstruturados::migalhas($url, $migalhas),
            DadosEstruturados::funcionalidades($url, 'Funcionalidades — ' . $nomeLimpo, $module['features']),
            DadosEstruturados::perguntas($url, $perguntas),
        ]);

        return view('modules.show', compact('module', 'plan', 'seo', 'migalhas', 'perguntas', 'dadosEstruturados'));
    }

    public function index()
    {
        $modules = array_map(fn($m) => [
            'slug' => $m['slug'],
            'name' => $m['name'],
            'tagline' => $m['tagline'],
            'icon' => $m['icon'],
            'gradient_from' => $m['gradient_from'],
            'gradient_to' => $m['gradient_to'],
            'plan' => Plan::publico()->where('slug', $m['plan_slug'])->first(),
        ], $this->modules);

        return view('modules.index', compact('modules'));
    }
}
