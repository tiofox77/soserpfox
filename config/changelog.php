<?php

/**
 * Changelog do SOS ERP
 *
 * Como adicionar uma nova versão:
 *   1. Insira UMA NOVA ENTRADA no TOPO do array 'releases'.
 *   2. Defina 'version' (semver), 'date' (Y-m-d) e 'type' ('major'|'minor'|'patch').
 *   3. Liste as alterações por categoria: 'features', 'improvements', 'fixes', 'security'.
 *   4. Actualize 'current' para reflectir a versão mais recente.
 *
 * A página /changelog mostra esta informação em formato timeline.
 */

return [

    // Versão actualmente em produção (mostrada no badge)
    //
    // O PWA JÁ NÃO DEPENDE DISTO: o aquecimento do cache das páginas usava este
    // valor como chave e passou a usar a assinatura da versão do próprio PWA
    // (resources/js/pwa/aquecer.ts), que muda sozinha quando os ficheiros mudam.
    'current' => '2026.09.13.1',

    'releases' => [

        [
            'version'      => '2026.09.13.1',
            'date'         => '2026-09-13',
            'type'         => 'major',
            'title'        => 'O sistema inteiro num ecrã novo — mais rápido, com os mesmos campos, e as contas certas',
            'features'     => [
                ['produto' => 'web', 'texto' => 'Todos os módulos passam a ter ecrãs novos, mais rápidos: facturação, POS, stock, tesouraria, contabilidade, RH, hotel, restaurante, salão, oficina, eventos, CRM, compras, inventário e projectos — e também a minha conta, os utilizadores, a empresa, o suporte, as notificações e o painel da plataforma. Os endereços de sempre continuam a funcionar.'],
                ['produto' => 'web', 'texto' => 'Os ecrãs abrem sem recarregar a página inteira, com os cartões, as animações e os ícones de antes — e os campos de todos os formulários e janelas mantêm-se.'],
                ['produto' => 'ambos', 'texto' => 'Sempre que se cria, guarda ou apaga alguma coisa aparece um aviso no canto superior direito — verde quando correu bem, vermelho com o motivo quando não.'],
                ['produto' => 'pwa', 'texto' => 'O modo offline foi reescrito: um só ecrã para vender, facturar e fechar o turno sem rede, e a sincronização passa a esperar pela actualização do aparelho antes de enviar — acabaram as vendas que subiam antes de o catálogo estar pronto.'],
                ['produto' => 'web', 'texto' => 'As páginas públicas (entrada, registo, portal do cliente, carta do restaurante e marcações do salão) passam também para o ecrã novo.'],
                ['produto' => 'web', 'texto' => 'Os ícones escolhem-se de uma galeria, em vez de se escrever o código.'],
            ],
            'improvements' => [
                ['produto' => 'web', 'texto' => 'Os 24 relatórios da facturação num ecrã só, e cinco listas de documentos com os mesmos filtros, colunas e botões.'],
                ['produto' => 'web', 'texto' => 'A factura, a proforma e a factura de compra mostram o resumo à direita enquanto se preenche, com os três descontos e o armazém já escolhido.'],
                ['produto' => 'web', 'texto' => 'A taxa do IVA e a retenção na fonte passam a sair do catálogo de impostos da empresa, e não de um número escrito à mão.'],
                ['produto' => 'web', 'texto' => 'A contabilidade fica completa: diários, centros de custo, períodos, moedas, reconciliação, orçamentos com o realizado e imobilizado.'],
                ['produto' => 'web', 'texto' => 'Stock: filtro por existência, e o mapa em papel e em Excel.'],
            ],
            'fixes'        => [
                ['produto' => 'ambos', 'texto' => 'CRÍTICO: nas facturas de serviço com retenção IRT, o «Total a Pagar» do PDF e do talão descontava a retenção duas vezes (2.150 Kz apareciam como 2.020). O documento passa a mostrar o total antes da retenção, a retenção e o valor a pagar certos.'],
                ['produto' => 'ambos', 'texto' => 'No POS, o desconto em percentagem chegava à factura como kwanzas: 10% num talão de 5.000 Kz saía como 10 Kz de desconto.'],
                ['produto' => 'web', 'texto' => 'A nota de crédito feita no POS entra no turno, e o dinheiro esperado no fecho de caixa passa a bater certo.'],
                ['produto' => 'web', 'texto' => 'A verificação de conformidade AGT deixava de acusar «totais inconsistentes» em todas as facturas com retenção.'],
                ['produto' => 'web', 'texto' => 'Um rascunho deixa de contar como dívida no painel e no «por receber».'],
                ['produto' => 'web', 'texto' => 'Várias páginas que davam erro 500 (portal do cliente, alguns PDF, gravar equipamento nos eventos) e o ecrã de ordens de manutenção do hotel, que nunca chegava a gravar.'],
                ['produto' => 'web', 'texto' => 'A unidade do artigo deixava de se trocar sozinha ao abrir a ficha.'],
            ],
            'security'     => [
                ['produto' => 'web', 'texto' => 'Todas as páginas dos módulos passam a pedir a permissão certa (mais de 130 rotas não pediam nenhuma), e os modelos do hotel, da oficina e do salão ficam presos à empresa.'],
                ['produto' => 'web', 'texto' => 'O regime fiscal da empresa só se muda com uma confirmação escrita, e a carta pública do restaurante deixa de levar no HTML os preços escondidos.'],
            ],
        ],

        [
            'version'      => '2026.08.18.1',
            'date'         => '2026-08-18',
            'type'         => 'minor',
            'title'        => 'Entrada própria do PWA, e o imposto certo em cada empresa',
            'features'     => [
                ['produto' => 'pwa', 'texto' => 'Entrada própria da aplicação: entra-se com rede ou sem ela. Sair passa a terminar mesmo a sessão e a devolver a este ecrã — antes saltava para a aplicação web, que sem internet não abre, e quem saísse ficava sem forma de voltar a entrar.'],
                ['produto' => 'pwa', 'texto' => 'Com rede, a venda sai já com o número fiscal em vez de um número provisório. Sem rede continua a ir para a fila, como sempre.'],
                ['produto' => 'pwa', 'texto' => 'O painel mostra as facturas do dia e a fila de envio item a item, com o erro de cada trabalho que não tenha subido.'],
            ],
            'improvements' => [
                ['produto' => 'pwa', 'texto' => 'O cabeçalho passa a dizer a data e a hora da última actualização deste aparelho, ao lado da versão. Antes eram dois códigos que não respondiam à única pergunta que se faz ali: já tenho a correcção de hoje?'],
                ['produto' => 'pwa', 'texto' => 'O leitor de código de barras encontra o artigo venha o código na forma completa ou só com os treze dígitos. Uma caixa lida numa forma e guardada na outra deixava de ser encontrada.'],
                ['produto' => 'ambos', 'texto' => 'A numeração fiscal, o selo e a comunicação à AGT passam a viver num sítio só, partilhado por todos os documentos.'],
            ],
            'fixes'        => [
                ['produto' => 'pwa', 'texto' => 'CRÍTICO: as taxas de IVA oferecidas eram sempre as mesmas quatro — 0, 5, 7 e 14 — a qualquer empresa. Numa empresa isenta, bastava escolher para emitir um documento com IVA que ela não pode cobrar. Passam a ser as taxas da própria empresa.'],
                ['produto' => 'pwa', 'texto' => 'Sem turno aberto já não se vende. Antes perguntava se queria continuar, e a venda saía fora do fecho de caixa — ao fim do dia o dinheiro na gaveta não batia certo e não se sabia porquê.'],
                ['produto' => 'pwa', 'texto' => 'A lista de vendas por enviar mostrava também as que já tinham sido entregues ao servidor. Aparecia dinheiro por cobrar que afinal já estava cobrado.'],
                ['produto' => 'pwa', 'texto' => 'Sem sessão e com internet, aparecia o POS guardado em vez do ecrã de entrada: parecia funcionar e a primeira venda falhava.'],
                ['produto' => 'pwa', 'texto' => 'Um artigo esgotado deixava de ser enviado ao aparelho e ficava lá com o stock e o imposto antigos — vendável offline com dados velhos.'],
                ['produto' => 'web', 'texto' => 'Os dados de qualquer ecrã só apareciam actualizados à segunda vez que se recarregava a página.'],
                ['produto' => 'web', 'texto' => 'Converter uma proforma em factura copiava a taxa gravada na proforma. Uma proforma feita antes de a empresa mudar de regime dava, com um clique, uma factura nova a liquidar imposto que a empresa já não pode cobrar.'],
                ['produto' => 'web', 'texto' => 'Os cartões da Gestão de Stock não acompanhavam os filtros do ecrã: escolher um armazém filtrava a lista e deixava os totais a falar de todos.'],
                ['produto' => 'web', 'texto' => 'A empresa passa a escolher se sai o nome comercial ou a designação social nos documentos. Antes a factura imprimia um e o talão do POS imprimia o outro.'],
            ],
            'security'     => [
                ['produto' => 'pwa', 'texto' => 'A entrada sem rede confere a palavra-passe contra um verificador guardado no aparelho, nunca contra a palavra-passe em si.'],
            ],
        ],

        [
            'version'      => '2026.08.16.1',
            'date'         => '2026-08-16',
            'type'         => 'minor',
            'title'        => 'Modo offline à prova de servidor em baixo',
            'features'     => [
                ['produto' => 'web', 'texto' => 'Aviso por email e SMS a quem administra a plataforma sempre que uma empresa nova se regista. Antes não era avisado ninguém, por nenhuma das vias de registo.'],
                ['produto' => 'web', 'texto' => 'Novo ecrã para enviar um SMS às empresas — a todas, às escolhidas uma a uma, ou às de certos planos. Mostra quantas recebem e quantas ficam de fora por não terem telefone, e conta o custo em partes de SMS antes de enviar.'],
            ],
            'improvements' => [
                ['produto' => 'pwa', 'texto' => 'O catálogo passa a ser refrescado sozinho de cinco em cinco minutos, e ao voltar à aplicação. Antes só era descarregado quando havia vendas por enviar: um posto que não vendesse nada ficava com os artigos e os preços do dia anterior.'],
                ['produto' => 'pwa', 'texto' => 'Em Android com a aplicação instalada, as páginas do modo offline são refrescadas em segundo plano mesmo com a aplicação fechada.'],
            ],
            'fixes'        => [
                ['produto' => 'pwa', 'texto' => 'CRÍTICO: com o servidor em baixo, a aplicação offline não abria — apesar de ter tudo guardado. Um servidor avariado responde com um erro, e não com silêncio; a aplicação tomava esse erro por resposta boa e mostrava-o em vez de usar o que tinha guardado. Foi o que deixou todos os balcões parados no sábado.'],
                ['produto' => 'pwa', 'texto' => 'Cada actualização do sistema apagava as páginas guardadas para uso offline, incluindo o POS. Até alguém voltar a abri-las com internet, o aparelho ficava sem rede de segurança. As páginas passam a atravessar as actualizações.'],
                ['produto' => 'pwa', 'texto' => 'Com a sessão expirada, a página de entrada ficava guardada por cima do POS e passava a ser ela a aparecer sem internet.'],
                ['produto' => 'pwa', 'texto' => 'Bastava um dos componentes externos falhar para nada ficar guardado na instalação — nem a página de emergência nem os ícones. Passa a guardar-se o que se consegue.'],
                ['produto' => 'web', 'texto' => 'O ecrã de Configurações de Faturação dava erro a quem não tem empresa activa. Passa a explicar que é preciso escolher uma empresa primeiro.'],
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.08.14.1',
            'date'         => '2026-08-14',
            'type'         => 'minor',
            'title'        => 'Hora de Angola, e séries de documentos sem ambiguidade',
            'features'     => [
                ['produto' => 'web', 'texto' => 'Painel de avisos da plataforma no ecrã de entrada: as comunicações da equipa SOS ERP ficam ali para consulta, mesmo depois de dispensada a barra do topo.'],
            ],
            'improvements' => [
                ['produto' => 'ambos', 'texto' => 'O sistema passa a trabalhar em hora de Angola. As horas que aparecem — de uma venda, de um turno, de um movimento — deixam de estar uma hora atrasadas. Os documentos anteriores a esta actualização mantêm a hora com que foram gravados.'],
                ['produto' => 'web', 'texto' => 'O prefixo AGT de cada tipo de documento passa a ter uma única origem no sistema, e o ecrã das séries mostra como fica o próximo número antes de gravar.'],
            ],
            'fixes'        => [
                ['produto' => 'web', 'texto' => 'IMPORTANTE: séries gravadas com um prefixo fora do catálogo da AGT — o ecrã chegou a sugerir "PRF" onde a AGT espera "PR" — faziam a submissão ser recusada com erro E32. Corrigidas 24 séries.'],
                ['produto' => 'web', 'texto' => 'Havia empresas com duas séries marcadas como padrão para o mesmo tipo de documento: qual delas numerava o documento seguinte era imprevisível. Passa a ser impossível ter duas.'],
                ['produto' => 'web', 'texto' => 'Uma mensagem da plataforma marcada para as 10:20 só aparecia às 11:20, por a hora escrita ser lida como se fosse de outro fuso.'],
                ['produto' => 'web', 'texto' => 'Com todas as séries de um tipo desactivadas, o POS deixava de abrir com erro de numeração.'],
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.08.13.1',
            'date'         => '2026-08-13',
            'type'         => 'major',
            'title'        => 'Três línguas, perfis de negócio, lotes e validades',
            'features'     => [
                ['produto' => 'ambos', 'texto' => 'O sistema passa a falar português, inglês e francês — facturação, POS (offline incluído), stock, catálogo, compras, relatórios e definições.'],
                ['produto' => 'web', 'texto' => 'Perfis de negócio nas definições: farmácia, boutique, cosmética ou mercearia. Ao activar um, o cadastro de artigos e o stock passam a mostrar os campos daquele ramo — princípio activo e receita obrigatória na farmácia, tamanho e cor no vestuário, e assim por diante.'],
                ['produto' => 'pwa', 'texto' => 'Cópia de segurança do modo offline: exportar no aparelho e importar no sistema, para não se perder o trabalho de um telemóvel avariado.'],
                ['produto' => 'web', 'texto' => 'O assistente de registo passa a perguntar o regime fiscal da empresa, e a lista de empresas do super admin ganhou filtros, ordenação e sinais de actividade.'],
            ],
            'improvements' => [
                ['produto' => 'pwa', 'texto' => 'As vendas feitas offline passam a consumir os lotes, pelo mais próximo da validade e saltando o que já expirou.'],
            ],
            'fixes'        => [
                ['produto' => 'web', 'texto' => 'Lotes e validades: nove defeitos corrigidos, entre eles um lote expirado impedir a venda de todo o artigo, e a correcção de uma quantidade inventar stock que não existia.'],
                ['produto' => 'pwa', 'texto' => 'Dois postos a sincronizar ao mesmo tempo podiam duplicar clientes ou perder vendas. A sincronização passa a aguentar o regresso simultâneo de vários aparelhos.'],
                ['produto' => 'pwa', 'texto' => 'Cinco defeitos silenciosos no que o aparelho descarrega: artigos repostos não voltavam, artigos desactivados nunca desapareciam, e clientes sem NIF nunca chegavam a sincronizar.'],
            ],
            'security'     => [
                ['produto' => 'web', 'texto' => 'Os lotes de um artigo podiam ser vistos e alterados a partir de outra empresa.'],
            ],
        ],

        [
            'version'      => '2026.08.12.1',
            'date'         => '2026-08-12',
            'type'         => 'major',
            'title'        => 'Plataforma, notificações e auditoria',
            'features'     => [
                ['produto' => 'web', 'texto' => 'Mensagens da plataforma às empresas: avisos em barra ou em janela, dirigidos a todas, a algumas, ou às de um plano, com registo de quem leu.'],
                ['produto' => 'web', 'texto' => 'Aplicação móvel de facturação (Flutter), a acompanhar o modo offline do browser.'],
                ['produto' => 'web', 'texto' => 'Analítica de visitas com origem e região, e aviso por SMS dos pagamentos por aprovar.'],
            ],
            'improvements' => [
                ['produto' => 'web', 'texto' => 'A trilha de auditoria passa a dizer o que aconteceu em palavras, e não apenas identificadores.'],
                ['produto' => 'web', 'texto' => 'O módulo de notificações passa a funcionar sem depender de processos em segundo plano.'],
                ['produto' => 'web', 'texto' => 'Plano FOX Friendly de 6 para 3 meses, uma cortesia por cliente, e preços multi-empresa revistos.'],
            ],
            'fixes'        => [
                ['produto' => 'pwa', 'texto' => 'Uma sessão expirada congelava o balcão: o POS deixava de responder em vez de pedir para entrar de novo. O carrinho passa também a ser guardado no aparelho e reposto ao voltar.'],
                ['produto' => 'web', 'texto' => 'No super admin, apagar uma empresa passa a apagar mesmo, e os limites dos planos passam a ser respeitados.'],
                ['produto' => 'web', 'texto' => 'As definições de recursos humanos existiam apenas para a primeira empresa.'],
                ['produto' => 'web', 'texto' => 'As transferências entre armazéns ganharam rastreio completo, correcção de quantidades e mais velocidade.'],
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.08.07.1',
            'date'         => '2026-08-07',
            'type'         => 'minor',
            'title'        => 'Impostos por linha e região fiscal',
            'features'     => [
                ['produto' => 'web', 'texto' => 'Imposto Especial de Consumo e Imposto de Selo por linha de documento, com os códigos SAFT que faltavam nas compras.'],
                ['produto' => 'web', 'texto' => 'Região fiscal nas proformas e nas compras — antes só a factura de venda a tinha.'],
            ],
            'improvements' => [
                ['produto' => 'web', 'texto' => 'A regra dos impostos por linha passou a viver num só sítio, partilhado por todos os documentos.'],
                ['produto' => 'web', 'texto' => 'Cabeçalho único nos quatro ecrãs de criação de documentos.'],
            ],
            'fixes'        => [
                ['produto' => 'web', 'texto' => 'A devolução feita no POS não era enviada à AGT, e um pedido para abrandar era tomado por recusa definitiva.'],
                ['produto' => 'web', 'texto' => 'A região fiscal escolhida na proforma de venda não chegava a ser gravada.'],
                ['produto' => 'ambos', 'texto' => 'O carrinho do POS passa a ser por empresa, e o fecho da venda verifica se os artigos são mesmo dela.'],
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.08.06.1',
            'date'         => '2026-08-06',
            'type'         => 'minor',
            'title'        => 'AGT — numeração aceite e envio automático',
            'features'     => [
                ['produto' => 'web', 'texto' => 'O envio automático à AGT passa a valer para todos os documentos fiscais, e as submissões andam com o tráfego normal, sem depender de processos em segundo plano.'],
            ],
            'improvements' => [
                ['produto' => 'ambos', 'texto' => 'O QR code e o rodapé do talão seguem o ambiente configurado na empresa.'],
            ],
            'fixes'        => [
                ['produto' => 'web', 'texto' => 'CRÍTICO: o número do documento começava por "SOS" em vez do tipo, e a AGT recusava-o. Corrigidos também os números já emitidos.'],
                ['produto' => 'ambos', 'texto' => 'As facturas do POS eram enviadas à AGT sem uma única linha.'],
                ['produto' => 'web', 'texto' => 'Uma falha de rede deixava o documento marcado como recusado para sempre, mesmo tendo sido aceite.'],
                ['produto' => 'web', 'texto' => 'PDF das notas de crédito e dos restantes documentos: saíam cortados à direita e a passar para a segunda página.'],
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.08.05.1',
            'date'         => '2026-08-05',
            'type'         => 'minor',
            'title'        => 'AGT — homologação e produção lado a lado',
            'features'     => [
                ['produto' => 'web', 'texto' => 'Credenciais e chave passam a ter os dois ambientes — homologação e produção — configurados em separado, sem se pisarem.'],
            ],
            'improvements' => [
                ['produto' => 'web', 'texto' => 'O histórico de stock passa a mostrar saldo anterior, quantidade e saldo final de cada movimento.'],
                ['produto' => 'web', 'texto' => 'O comando de reconciliação de stock passa a simular por omissão: só altera quando lhe for pedido.'],
            ],
            'fixes'        => [
                ['produto' => 'web', 'texto' => 'Erro E39 da AGT: era enviado o número de certificação de produção quando se estava em homologação.'],
                ['produto' => 'web', 'texto' => 'Sem chave da empresa, os documentos eram assinados com a do produtor sem que nada o dissesse.'],
                ['produto' => 'web', 'texto' => 'O selector de ambiente voltava sozinho a homologação, e chegava a mudar o rótulo sem mudar o campo.'],
                ['produto' => 'ambos', 'texto' => 'Uma venda descontava mais do que havia e deixava o armazém negativo.'],
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.08.04.1',
            'date'         => '2026-08-04',
            'type'         => 'minor',
            'title'        => 'Extractos de conta corrente e mapa de ajustes',
            'features'     => [
                ['produto' => 'web', 'texto' => 'Extracto de conta corrente de clientes e fornecedores: tudo o que mexeu na conta pela ordem em que aconteceu, com saldo acumulado e saldo anterior ao período. Exporta para PDF.'],
                ['produto' => 'web', 'texto' => 'Mapa de seguimento dos ajustes de stock: o que foi mexido à mão num período, por operador, com resumo de quem concentra mais ajustes.'],
                ['produto' => 'ambos', 'texto' => 'Leitor de código de barras no POS.'],
            ],
            'improvements' => [
                ['produto' => 'web', 'texto' => 'O relatório do POS passa a incluir as notas de crédito e a filtrar por tipo de documento.'],
                ['produto' => 'web', 'texto' => 'O modo de manutenção deixa de trancar a porta por dentro: dá para voltar a ligar o sistema sem acesso aos ficheiros.'],
            ],
            'fixes'        => [
                ['produto' => 'web', 'texto' => 'O aviso de stock baixo nunca chegou a disparar.'],
                ['produto' => 'web', 'texto' => 'As facturas de compra podiam ser apagadas.'],
                ['produto' => 'web', 'texto' => 'Um separador antigo aberto no browser dava erro em vez de recarregar.'],
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.08.03.1',
            'date'         => '2026-08-03',
            'type'         => 'minor',
            'title'        => 'Movimentação de stock em lote, com documento',
            'features'     => [
                ['produto' => 'web', 'texto' => 'A movimentação de stock em lote passa a gerar um documento com referência própria e comprovativo em PDF, reimprimível a qualquer altura. Cada movimento guarda o saldo do momento, para a reimpressão dizer o que se passou nessa altura.'],
            ],
            'improvements' => [
                ['produto' => 'web', 'texto' => 'No histórico, transferências e ajustes passam a ser identificados à parte e ficam fora dos totais — uma transferência muda o artigo de armazém sem alterar o que a empresa tem.'],
            ],
            'fixes'        => [
                ['produto' => 'web', 'texto' => 'As vendas do hotel, da oficina e do restaurante emitiam factura sem dar baixa no stock.'],
                ['produto' => 'ambos', 'texto' => 'Uma transferência sem quantidade suficiente deixava um registo vazio no destino que fazia o artigo aparecer esgotado na caixa e no POS offline.'],
                ['produto' => 'ambos', 'texto' => 'Enquanto se registava uma movimentação em lote, ficavam travadas as vendas e a sincronização do POS offline de toda a empresa.'],
            ],
            'security'     => [
                ['produto' => 'web', 'texto' => 'Quem tinha acesso a mais do que uma empresa podia, ao trabalhar na segunda, executar acções com as permissões da primeira — e ver-se recusado onde tinha mesmo autorização.'],
                ['produto' => 'web', 'texto' => 'No ecrã de Gestão de Stock, um pedido adulterado podia dar entrada de mercadoria num armazém de outra empresa. O ecrã passa também a exigir permissão de consulta de stock.'],
                ['produto' => 'ambos', 'texto' => 'A trilha de auditoria não guardava os movimentos de stock gerados pelas vendas — precisamente os que mais interessa poder consultar.'],
            ],
        ],

        [
            'version'      => '2026.06.12.2',
            'date'         => '2026-06-12',
            'type'         => 'minor',
            'title'        => 'Stock — reconciliação automática e comando artisan',
            'features'     => [
                'StockObserver: sempre que uma linha em invoicing_stocks é criada/alterada/apagada, o agregado invoicing_products.stock_quantity é recalculado automaticamente (soma de todas as linhas do produto). Impede divergências futuras.',
                'Comando `php artisan stock:reconcile`: corrige divergências existentes em todos os tenants (ou um específico com --tenant=ID). Suporta --dry-run para apenas listar sem alterar.',
            ],
            'improvements' => [],
            'fixes'        => [],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.12.1',
            'date'         => '2026-06-12',
            'type'         => 'patch',
            'title'        => 'POS — fix stock fantasma com multi-armazém',
            'features'     => [],
            'improvements' => [],
            'fixes'        => [
                'CRÍTICO: POS (online + PWA offline) mostrava stock incorreto para produtos cujo armazém ativo não tinha linha em invoicing_stocks mas o agregado legado invoicing_products.stock_quantity era > 0. A query caía indevidamente no fallback agregado e ignorava a realidade do armazém. Agora: se o produto já tem linhas em invoicing_stocks (qualquer armazém), o stock no armazém ativo é 0 quando não houver linha — só usa o agregado em tenants legados sem multi-armazém. Aplicado em POSSystem.php (render + stockInWarehouse) e SyncController (catálogo offline).',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11.4',
            'date'         => '2026-06-11',
            'type'         => 'patch',
            'title'        => 'Criação rápida de cliente — fix ENUM type',
            'features'     => [],
            'improvements' => [],
            'fixes'        => [
                'POS online — "Novo Cliente" rebentava com SQLSTATE 1265 "Data truncated for column type" porque enviava type=individual; a coluna invoicing_clients.type é ENUM(pessoa_fisica, pessoa_juridica). Corrigido em POSSystem, GuestManagement e ReservationManagement.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11.3',
            'date'         => '2026-06-11',
            'type'         => 'patch',
            'title'        => 'PWA — auto-update do service worker + botão Atualizar App',
            'features'     => [
                'Botão "Atualizar App" no painel de manutenção: apaga Cache Storage, força verificação do service worker (update + SKIP_WAITING) e recarrega com cache-bust — sem tocar nos dados do IndexedDB.',
                'Indicação da versão instalada no painel de manutenção.',
            ],
            'improvements' => [],
            'fixes'        => [
                'CRÍTICO: o layout do PWA nunca registava o service worker (faltava o partial pwa-register) — sem registo não havia deteção de novas versões nem prompt de atualização. O PWA agora verifica updates ao carregar, a cada 30 min e quando a janela volta a ficar visível.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11.2',
            'date'         => '2026-06-11',
            'type'         => 'patch',
            'title'        => 'PWA — painel de manutenção e exibição de isenção',
            'features'     => [
                'Painel "Manutenção & Sincronização" na página inicial do PWA: Sync Parcial (incremental), Sync Completa (re-descarrega catálogo), Limpar Catálogo (preserva pendentes) e Reset Total (com dupla confirmação se houver pendentes).',
                'Secção "Ver detalhes" com raio-X do cache local: produtos isentos vs com IVA, clientes/vendas por sincronizar, fila de sync, estado do login offline e turno.',
            ],
            'improvements' => [
                'POS offline: itens e totais mostram "Isento" em vez de "IVA 0%" quando aplicável.',
            ],
            'fixes'        => [
                'KPI "Rascunhos locais" da página inicial lia tabela inexistente (draft_invoices → draft_documents).',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11.1',
            'date'         => '2026-06-11',
            'type'         => 'patch',
            'title'        => 'POS Offline — herdar impostos da empresa/produtos',
            'features'     => [],
            'improvements' => [
                'O catálogo offline agora espelha exatamente a lógica de impostos do POS online: produto isento → 0%, senão taxa vinculada ao produto, senão tax default do tenant.',
                'Empresas em regime de isenção (tax default ISE): TODOS os produtos chegam ao PWA com IVA 0% e tax_type=isento.',
            ],
            'fixes'        => [
                'CRÍTICO: PWA offline cobrava IVA 14% mesmo quando a empresa/produtos eram isentos — o fallback "|| 14" convertia 0% (falsy) em 14% em três sítios (POS, rascunhos, payload de venda).',
                'API de sync deixou de usar 14% hardcoded como fallback — usa a tax default do tenant.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.11',
            'date'         => '2026-06-11',
            'type'         => 'minor',
            'title'        => 'PWA — Login offline com sincronização',
            'features'     => [
                'Login offline completo: PWA permite autenticar localmente quando não há internet, usando hash PBKDF2 (SHA-256, 10 000 rounds) com salt aleatório por dispositivo.',
                'Setup opt-in: após sync online o PWA mostra banner "Ativar login offline" — o utilizador insere a sua password e fica habilitado por 90 dias.',
                'Overlay de gate: se a sessão Laravel expira ou o tab é aberto offline, mostra ecrã de bloqueio fullscreen pedindo email + password.',
                'Re-autenticação assíncrona: quando volta online, o sync valida a sessão Laravel; se 401/419 o overlay reaparece para re-login local.',
                'sessionStorage.pwa_unlocked: desbloqueio é por aba, garantindo logout efetivo ao fechar a janela.',
            ],
            'improvements' => [
                'Wipe ao trocar utilizador agora também limpa auth_cache e sessionStorage.',
                'Background Sync API adicionado ao sw.js correto (resources/pwa/sw.js) — sincroniza com app fechada.',
                'Helpers públicos: SosPwa.enableOfflineAuth, verifyOfflineAuth, isOfflineAuthEnabled, getOfflineAuthInfo, clearOfflineAuth, isPwaUnlocked.',
            ],
            'fixes'        => [
                'public/sw.js (stub legado) removido — o controller PwaController serve sempre o ficheiro real de resources/pwa/sw.js.',
            ],
            'security'     => [
                'Password nunca é armazenada em claro: apenas hash + salt único por instalação, válidos 90 dias.',
                'Sessão expira automaticamente ao fechar o tab (sessionStorage).',
            ],
        ],

        [
            'version'      => '2026.06.10.1',
            'date'         => '2026-06-10',
            'type'         => 'patch',
            'title'        => 'POS Offline — polimento e UX',
            'features'     => [
                'Campo de desconto comercial (%) na UI do POS offline — aplica-se ao subtotal e recalcula IVA proporcionalmente.',
                'Leitor de código de barras via câmara (BarcodeDetector API) — botão de câmara na barra de pesquisa para PDAs/telemóveis sem scanner físico.',
                'Quantidade editável por input direto — toque no número para digitar a quantidade em vez de +/− repetidos.',
                'Auto-purge de vendas sincronizadas com mais de 30 dias — limpa automaticamente o IndexedDB ao arranque.',
                'Indicador "última sync há X min" no header do PWA — atualiza a cada 30s para dar confiança ao operador.',
                'Feedback tátil (vibração) ao adicionar produto e ao incrementar quantidade (navigator.vibrate).',
            ],
            'improvements' => [
                'Produtos esgotados (stock 0) ficam com overlay "ESGOTADO" e botão desabilitado — impossível adicionar.',
                'Totais do carrinho mostram a linha de desconto quando > 0%.',
                'Botão câmara com overlay fullscreen, detecção de EAN-13/8, Code-128/39, UPC-A/E, QR.',
                'clearCart e checkout repõem desconto a 0.',
            ],
            'fixes'        => [],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.10',
            'date'         => '2026-06-10',
            'type'         => 'minor',
            'title'        => 'POS Offline — melhorias críticas e médias',
            'features'     => [
                'Stock local decrementa ao vender offline — grid bloqueia produtos esgotados e mostra badge "ESGOTADO".',
                'Aviso no carrinho quando quantidade excede stock local disponível.',
                'Wipe automático do IndexedDB ao trocar de utilizador (preserva fila de sincronização).',
                'Secção "Com erro" no drawer de pendentes — mostra jobs falhados com mensagem de erro e botão Retry individual/global.',
                'Background Sync API (sw.js) — sincroniza mesmo com o PWA fechado (Chrome/Android).',
                'Verificação real de conectividade via /api/v1/invoicing/ping — elimina falsos positivos do navigator.onLine.',
                'Deteção de sessão expirada (401/419) — banner "Sessão expirada" com redirect para login.',
                'Relatório X/Z de fecho de turno offline — ticket 80mm impresso automaticamente ao fechar turno.',
                'Data da última sincronização visível no modal de fecho e no drawer de pendentes.',
                'Aviso "Valores locais podem não incluir outros dispositivos" quando há pendentes.',
            ],
            'improvements' => [
                'Auto-sync periódico (45s) e ao voltar à app usam ping real em vez de navigator.onLine.',
                'Service Worker completo com cache offline (cache-first para assets, network-first para páginas).',
                'enqueue() regista Background Sync no SW para sincronizar com app fechada.',
                'Sessão expirada para a queue imediatamente sem consumir retries.',
                'Botão de fecho de turno agora diz "Fechar e Imprimir".',
            ],
            'fixes'        => [
                'Stock no grid POS não atualizava após venda offline — agora decrementa localmente.',
                'Drawer de pendentes renomeado para "Documentos Offline" com informação mais completa.',
            ],
            'security'     => [
                'Dados sensíveis (vendas, clientes, turno) limpos automaticamente ao trocar de operador no mesmo dispositivo.',
            ],
        ],

        [
            'version'      => '2026.06.05.3',
            'date'         => '2026-06-05',
            'type'         => 'patch',
            'title'        => 'PWA abre directamente no POS Offline',
            'features'     => [
                'Página inicial do PWA passa a ser o POS Offline — abre mesmo sem internet.',
            ],
            'improvements' => [
                'Service Worker serve o POS Offline cached quando /dashboard, /pos ou /invoicing/offline/* são abertos sem rede.',
            ],
            'fixes'        => [
                'PWAs já instalados (start_url=/dashboard) passam a abrir no POS Offline em vez de outra página.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.05.2',
            'date'         => '2026-06-05',
            'type'         => 'patch',
            'title'        => 'Correção 404 ao abrir o PWA instalado',
            'features'     => [
                'Novo shortcut "PWA Offline" no manifest (acesso rápido ao modo offline a partir do atalho do telefone).',
            ],
            'improvements' => [
                'Manifest dinâmico: start_url corrigido (era /dashboard, rota não existente no topo).',
                'Shortcuts apontam para rotas válidas (/invoicing/pos em vez de /pos).',
            ],
            'fixes'        => [
                'PWAs já instalados que abriam com 404 — adicionados redirects /dashboard e /pos.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.05.1',
            'date'         => '2026-06-05',
            'type'         => 'patch',
            'title'        => 'PWA Offline robusto + warmup automático',
            'features'     => [
                'Warmup automático: ao entrar online no PWA, todas as páginas e scripts críticos são pré-cacheados — agora o PWA abre sempre offline, mesmo na primeira tentativa.',
                'Versão do sistema visível no header do PWA (sub-título).',
            ],
            'improvements' => [
                'Service Worker: fallback inteligente — se uma rota /invoicing/offline/* não tem cache, devolve qualquer outra rota PWA cached em vez de "Sem conexão".',
                'Cache HTML aumentado de 80 → 250 entradas para dar espaço a todas as páginas do PWA.',
                'Cache de imagens aumentado de 120 → 200.',
            ],
            'fixes'        => [
                'PWA não carregava produtos/interface ao abrir offline em algumas situações (faltava precache das páginas) — corrigido com warmup.',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.06.05',
            'date'         => '2026-06-05',
            'type'         => 'minor',
            'title'        => 'PWA dinâmico, POS+ e criação rápida de clientes',
            'features'     => [
                'Página de Atualizações (/changelog) com histórico de versões.',
                'Criação rápida de cliente no POS — botão "Novo Cliente" no modal de selecção.',
                'Manifest PWA e ícones gerados dinamicamente a partir do logo do sistema.',
                'Service Worker com auto-update — notificação "Nova versão disponível" com botão para atualizar.',
                'Setting POS: Ocultar produtos esgotados (configurável em Faturação → Configurações → POS).',
            ],
            'improvements' => [
                'Auto-cleanup transparente dos ficheiros estáticos PWA legados (manifest.json/sw.js).',
                'POS: serviços (type=servico) deixam de ser ocultados por falta de stock.',
                'CACHE_VERSION do Service Worker atrelada a deploy + logo (hash automático).',
                'Sincronização do PWA Offline (SyncController) respeita a flag pos_hide_out_of_stock.',
            ],
            'fixes'        => [
                'Erro 500 em /invoicing/pos com MySQL ONLY_FULL_GROUP_BY (HAVING → WHERE com COALESCE).',
                'Manifest PWA passa a usar o logo configurado pelo tenant (antes era genérico).',
            ],
            'security'     => [],
        ],

        [
            'version'      => '2026.05.25',
            'date'         => '2026-05-25',
            'type'         => 'minor',
            'title'        => 'Integração AGT e notificações',
            'features'     => [
                'Notificação por email para validações AGT (configurável em Settings).',
                'Campo agt_notification_emails em invoicing_settings.',
            ],
            'improvements' => [
                'Reforço da validação SAFT antes do submit AGT.',
            ],
            'fixes'        => [],
            'security'     => [],
        ],

        [
            'version'      => '2025.10.05',
            'date'         => '2025-10-05',
            'type'         => 'minor',
            'title'        => 'POS Pro: stock, formato numérico e séries',
            'features'     => [
                'Configurações POS dedicadas (auto-print, sons, validação de stock, imagens, produtos por página).',
                'Séries POS independentes (POS prefix configurável).',
                'Formato numérico Angola/Internacional + casas decimais configuráveis.',
            ],
            'improvements' => [
                'POS valida stock por armazém default do tenant (fonte única de verdade).',
            ],
            'fixes'        => [],
            'security'     => [],
        ],

        [
            'version'      => '2025.10.03',
            'date'         => '2025-10-03',
            'type'         => 'major',
            'title'        => 'Faturação multi-tenant',
            'features'     => [
                'Módulo de Faturação completo: PROFORMA, FT, RC com séries por tipo.',
                'Tabela invoicing_settings por tenant (defaults: armazém, cliente, fornecedor, imposto, moeda).',
                'Multi-armazém com stock por linha (invoicing_stocks).',
            ],
            'improvements' => [],
            'fixes'        => [],
            'security'     => [],
        ],

    ],

];
