import { useCallback, useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';

import { t } from '@/i18n';

import { useAccao, useBaseViva } from '../../ganchos';
import { db, type Cliente, type Produto, type Registo, type Trabalho } from '../../motor/base';
import { partilharPdf } from '../../motor/documentos';
import { getFailedJobs, retryAllFailed, retryFailedJob } from '../../motor/fila';
import { sync } from '../../motor/sincronizar';
import { arredondar2, numero } from '../../motor/util';
import { createPosSaleOffline, getCompany, getPosSales, getWarehouse, produtoPeloCodigo, purgarVendasAntigas, totaisDaVenda } from '../../motor/vendas';
import { PosOfflineTicket } from '../../papel';
import { useTurno } from '../../turno/Turno';
import { avisar, confirmar, perguntarValor } from '../../ui/Dialogos';
import {
    categoriasDe, esgotado, filtrarProdutos, haSobrevenda, idDoArtigo, LOTE, metodosDePagamento, opcoesDeDinheiroRapido,
    precoEscrito, taxaDoArtigo, vibrar, type CodigoDoMetodo, type LinhaDoCarrinho, type LinhaDoPagamento,
} from './comum';
import { avisarCamaraSemSuporte, camaraSuportada, useLeitorDeTeclado } from './Leitor';

/** Uma lista vazia com identidade fixa — para os `useMemo` não se refazerem a cada desenho enquanto a base não responde. */
const SEM_PRODUTOS: Produto[] = [];

/** O que o modal do recibo precisa de lembrar da venda que acabou de sair. */
export interface ReciboDaVenda {
    uuid: string;
    /** O registo tal como saiu do motor — o vivo, da base, substitui-o quando sincroniza. */
    registo: Registo;
    cliente: string;
    itens: number;
    pagamento: string;
    total: number;
    /** Havia rede no momento da venda: decide se a mensagem é «a sincronizar» ou «guardada localmente». */
    online: boolean;
}

/**
 * O ESTADO E AS ACÇÕES DO BALCÃO — o que era o `posOffline()` do Alpine.
 *
 * As listas vêm da BASE VIVA: o ecrã antigo ouvia `pwa:synced`,
 * `pwa:pos-sale-synced` e `pwa:shift-synced` e relia tudo à mão; aqui a
 * sincronização escreve no Dexie e o ecrã redesenha sozinho — o número
 * provisório do recibo passa a fiscal sem ninguém o ir buscar.
 */
export function usePos() {
    const turno = useTurno();

    // `null` até a primeira leitura voltar: sem isto o ecrã dizia «Sem produtos
    // sincronizados» durante um instante a cada entrada, com o catálogo cheio.
    const produtosOuNada = useBaseViva<Produto[] | null>(() => db.products.toArray(), [], null);
    const produtos = produtosOuNada ?? SEM_PRODUTOS;
    const catalogoCarregado = produtosOuNada !== null;
    const clientes = useBaseViva<Cliente[]>(() => db.clients.toArray(), [], []);
    const empresa = useBaseViva<Registo | null>(() => getCompany(), [], null);
    const armazem = useBaseViva<Registo | null>(() => getWarehouse(), [], null);
    const vendas = useBaseViva<Registo[]>(() => getPosSales(), [], []);
    const falhados = useBaseViva<Trabalho[]>(() => getFailedJobs(), [], []);
    const metodos = useMemo(() => metodosDePagamento(), []);

    // ── Pesquisa, categorias e o «mostrar mais» ──────────────────────────
    const [pesquisa, setPesquisa] = useState('');
    const [categoria, setCategoria] = useState<string | null>(null);
    const [limite, setLimite] = useState(LOTE);
    // A lista filtra-se com a pesquisa ADIADA: com milhares de artigos, filtrar
    // a cada tecla fazia a caixa de pesquisa perder letras num Android barato.
    const pesquisaAdiada = useDeferredValue(pesquisa);

    const mudarPesquisa = useCallback((v: string) => { setPesquisa(v); setLimite(LOTE); }, []);
    const mudarCategoria = useCallback((v: string | null) => { setCategoria(v); setLimite(LOTE); }, []);

    const categorias = useMemo(() => categoriasDe(produtos), [produtos]);
    const filtrados = useMemo(() => filtrarProdutos(produtos, pesquisaAdiada, categoria), [produtos, pesquisaAdiada, categoria]);
    const visiveis = useMemo(() => filtrados.slice(0, limite), [filtrados, limite]);

    const carregarMais = useCallback(() => setLimite((l) => l + LOTE), []);
    /** Com guarda: só aumenta se há mais para mostrar (o scroll chama isto dezenas de vezes). */
    const totalFiltrados = useRef(0);
    totalFiltrados.current = filtrados.length;
    const carregarMaisSeHouver = useCallback(() => {
        setLimite((l) => (l < totalFiltrados.current ? l + LOTE : l));
    }, []);

    // ── Carrinho ─────────────────────────────────────────────────────────
    const [carrinho, setCarrinho] = useState<LinhaDoCarrinho[]>([]);
    const [clienteEscolhido, setClienteEscolhido] = useState<Cliente | null>(null);
    const [pagamento, setPagamento] = useState<CodigoDoMetodo>('cash');
    const [recebido, setRecebido] = useState('');
    const [desconto, setDesconto] = useState('');

    // ── Pagamento dividido ───────────────────────────────────────────────
    // O servidor aceita `payments[]` desde sempre e o POS online já o usava; só
    // este ecrã é que obrigava a um método único. Metade em dinheiro e metade a
    // cartão é venda de todos os dias.
    const [dividir, setDividir] = useState(false);
    const [pagamentos, setPagamentos] = useState<LinhaDoPagamento[]>([]);

    /*
     * O cliente escolhido, lido da lista VIVA.
     *
     * Um cliente criado sem rede nasce com id «local_…» e ganha o id do servidor
     * quando sobe. Guardar só a fotografia do momento em que foi escolhido
     * mandava a venda com o id velho; procurá-lo pelo `local_uuid` na lista viva
     * dá sempre o actual.
     */
    const cliente = useMemo(() => {
        if (!clienteEscolhido) return null;

        return clientes.find((c) => (clienteEscolhido.local_uuid && c.local_uuid === clienteEscolhido.local_uuid) || c.id === clienteEscolhido.id)
            ?? clienteEscolhido;
    }, [clientes, clienteEscolhido]);

    const totais = useMemo(() => totaisDaVenda(carrinho, desconto), [carrinho, desconto]);
    const contagem = useMemo(() => carrinho.reduce((s, i) => s + i.quantity, 0), [carrinho]);
    const sobrevenda = useMemo(() => haSobrevenda(carrinho, produtos), [carrinho, produtos]);
    const dinheiroRapido = useMemo(() => opcoesDeDinheiroRapido(totais.total), [totais.total]);

    const troco = useMemo(() => {
        const r = numero(recebido);

        return r ? Math.max(0, r - totais.total) : 0;
    }, [recebido, totais.total]);

    /** Quanto falta para as linhas somarem o total. Negativo = a mais. */
    const faltaPagar = useMemo(() => {
        const somado = pagamentos.reduce((s, p) => s + numero(p.amount), 0);

        return arredondar2(totais.total - somado);
    }, [pagamentos, totais.total]);

    /*
     * A divisão fecha? Só se somar o total ao cêntimo.
     *
     * O cêntimo de tolerância não é preguiça: os totais vêm de percentagens de
     * imposto e há arredondamentos por linha. Exigir igualdade exacta fazia o
     * botão recusar vendas correctas por 0,01 Kz.
     */
    const divisaoFechada = !dividir || Math.abs(faltaPagar) < 0.01;

    // ── Janelas ──────────────────────────────────────────────────────────
    const [mostrarCarrinho, setMostrarCarrinho] = useState(false);
    const [escolherCliente, setEscolherCliente] = useState(false);
    const [pesquisaCliente, setPesquisaCliente] = useState('');
    const [criarCliente, setCriarCliente] = useState(false);
    const [nomeDoNovoCliente, setNomeDoNovoCliente] = useState('');
    const [mostrarPendentes, setMostrarPendentes] = useState(false);
    const [lerCamara, setLerCamara] = useState(false);
    const [recibo, setRecibo] = useState<ReciboDaVenda | null>(null);

    // A venda do recibo, VIVA: quando sincroniza, o número provisório dá lugar
    // ao fiscal e o talão reimpresso já sai com o QR da AGT.
    const vivoDoRecibo = useBaseViva<Registo | undefined>(
        () => (recibo ? db.pos_sales.get(recibo.uuid) : Promise.resolve(undefined)),
        [recibo?.uuid],
        undefined,
    );
    const registoDoRecibo = recibo ? (vivoDoRecibo?.local_uuid === recibo.uuid ? vivoDoRecibo : recibo.registo) : null;

    // ── Aviso de receita médica ──────────────────────────────────────────
    // Aviso em curso (texto já traduzido) e o temporizador que o apaga. No
    // estado, e não num alert(), pela razão que está em `avisarReceita`.
    const [avisoReceita, setAvisoReceita] = useState<string | null>(null);
    const temporizadorDaReceita = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    /*
     * Aviso de receita: uma faixa que se apaga sozinha, e não uma pergunta.
     * Ao balcão, uma caixa de diálogo por cada caixa de antibiótico obriga a
     * duas acções por artigo — e o que se ganha em atenção volta a perder-se em
     * cliques dados sem ler. A pergunta bloqueante fica reservada ao
     * psicotrópico, onde é mesmo precisa.
     */
    const avisarReceita = useCallback((nome: string) => {
        setAvisoReceita(t(':artigo exige receita médica — confirme a receita antes de entregar.', { artigo: nome }));
        clearTimeout(temporizadorDaReceita.current);
        temporizadorDaReceita.current = setTimeout(() => setAvisoReceita(null), 8000);
        vibrar([40, 60, 40]);
    }, []);

    useEffect(() => () => clearTimeout(temporizadorDaReceita.current), []);

    const adicionar = useCallback(async (p: Produto) => {
        // Produto físico sem stock: não entra.
        if (esgotado(p)) return;

        const nome = String(p.name ?? '');

        // Psicotrópico / estupefaciente: confirmar ANTES de entrar no carrinho.
        // Estes artigos têm registo obrigatório e vendê-los por engano tem
        // consequência legal para a farmácia. Offline pesa ainda mais: não há
        // servidor nenhum a rever o que sai daqui, e o talão já foi impresso
        // quando a venda chega a sincronizar.
        if (p.is_controlled) {
            const sim = await confirmar(t('Psicotrópico ou estupefaciente — venda sujeita a registo obrigatório'), {
                texto: t(':artigo é um medicamento controlado (psicotrópico ou estupefaciente), de registo obrigatório. Confirma a venda?', { artigo: nome }),
                perigo: true,
                icone: 'fa-triangle-exclamation',
            });
            if (!sim) return;
        }

        /*
         * O PREÇO PERGUNTADO AO BALCÃO.
         *
         * Alguns artigos não têm preço fixo — vendem-se a peso, ao corte ou por
         * acordo — e trazem `preco_no_pos` ligado. O balcão online abre um modal
         * e pergunta; aqui pergunta-se também.
         *
         * Sem isto o artigo entrava ao preço de catálogo sem ninguém dar por
         * ela — e offline não há servidor nenhum a rever o que sai daqui, nem
         * forma de corrigir depois de o talão estar impresso.
         */
        let preco: number | null = null;

        if (p.preco_no_pos) {
            const resposta = await perguntarValor(t(':artigo tem o preço perguntado ao balcão. Preço unitário:', { artigo: nome }), {
                valor: String(numero(p.price)),
                tipo: 'text',
                inputMode: 'decimal',
                icone: 'fa-tag',
            });

            // Cancelar não vende: é diferente de escrever zero.
            if (resposta === null) return;

            preco = precoEscrito(resposta);

            if (!Number.isFinite(preco) || preco <= 0) {
                avisar(t('O preço tem de ser um número maior que zero.'), 'erro');

                return;
            }
        }

        const pid = idDoArtigo(p);
        const precoDaLinha = preco;

        setCarrinho((linhas) => {
            // O preço escrito ao balcão faz a sua PRÓPRIA linha quando é outro:
            // o ecrã antigo somava à linha que já lá estava e deitava fora o
            // preço acabado de perguntar.
            const i = linhas.findIndex((l) => l.product_id === pid && l.product_name === nome
                && (precoDaLinha === null || l.unit_price === precoDaLinha));

            if (i >= 0) return linhas.map((l, j) => (j === i ? { ...l, quantity: l.quantity + 1 } : l));

            return [...linhas, {
                product_id: pid,
                product_name: nome,
                quantity: 1,
                // O preço escrito ao balcão ganha ao de catálogo.
                unit_price: precoDaLinha ?? numero(p.price),
                tax_rate: taxaDoArtigo(p),
                discount_percent: 0,
            }];
        });

        // Avisa, não trava: o operador pode ter a receita na mão, e travar a
        // venda deixava a farmácia sem forma nenhuma de a fazer.
        if (p.requires_prescription) avisarReceita(nome);

        // Feedback táctil
        vibrar(30);
    }, [avisarReceita]);

    const produtosActuais = useRef(produtos);
    produtosActuais.current = produtos;

    /**
     * O código lido (pistola, câmara, ou Enter na pesquisa).
     *
     * Pelas FORMAS do código (`produtoPeloCodigo`): o mesmo artigo pode estar
     * guardado com o envelope GS1 ou só com o EAN-13 de dentro, e o leitor tanto
     * manda um como o outro. O ecrã antigo comparava o texto tal e qual, e o
     * artigo que o POS online achava aqui não aparecia.
     */
    const lerCodigo = useCallback((lido: string, doLeitor = false) => {
        const s = lido.trim();
        if (!s) return;

        const artigo = produtoPeloCodigo(produtosActuais.current, s);

        if (artigo) {
            mudarPesquisa('');

            if (doLeitor && esgotado(artigo)) {
                avisar(String(artigo.name ?? ''), 'aviso', { titulo: t('ESGOTADO') });

                return;
            }

            void adicionar(artigo);

            return;
        }

        // Sem artigo: o código fica na pesquisa, e a grelha diz «Nenhum produto encontrado».
        mudarPesquisa(s);

        if (doLeitor) {
            vibrar([80, 50, 80]);
            avisar(s, 'aviso', { titulo: t('Nenhum produto encontrado') });
        }
    }, [adicionar, mudarPesquisa]);

    const algumaJanela = escolherCliente || criarCliente || mostrarPendentes || lerCamara || !!recibo
        || turno.folhaDeAbrir || turno.folhaDeFechar;
    useLeitorDeTeclado((codigo) => lerCodigo(codigo, true), !algumaJanela);

    const abrirCamara = useCallback(() => {
        if (!camaraSuportada()) {
            avisarCamaraSemSuporte();

            return;
        }
        setLerCamara(true);
    }, []);

    const incrementar = useCallback((idx: number) => {
        setCarrinho((l) => l.map((x, j) => (j === idx ? { ...x, quantity: x.quantity + 1 } : x)));
        vibrar(15);
    }, []);

    const decrementar = useCallback((idx: number) => {
        setCarrinho((l) => {
            const linha = l[idx];
            if (!linha) return l;

            return linha.quantity > 1
                ? l.map((x, j) => (j === idx ? { ...x, quantity: x.quantity - 1 } : x))
                : l.filter((_, j) => j !== idx);
        });
    }, []);

    const definirQuantidade = useCallback((idx: number, valor: string) => {
        const q = parseInt(valor, 10);
        setCarrinho((l) => (!q || q < 1
            ? l.filter((_, j) => j !== idx)
            : l.map((x, j) => (j === idx ? { ...x, quantity: q } : x))));
    }, []);

    const [limparCarrinho] = useAccao(async () => {
        const sim = await confirmar(t('Limpar carrinho?'), { perigo: true, sim: t('Limpar'), icone: 'fa-trash' });
        if (!sim) return;
        setCarrinho([]);
        setClienteEscolhido(null);
        setRecebido('');
    });

    const abrirCriarCliente = useCallback(() => {
        // Pré-preenche com o termo pesquisado, se houver.
        setNomeDoNovoCliente(pesquisaCliente.trim());
        setEscolherCliente(false);
        setCriarCliente(true);
    }, [pesquisaCliente]);

    /**
     * Liga a divisão com a PRIMEIRA LINHA JÁ CHEIA.
     *
     * Abrir com duas linhas a zero obrigava o operador a somar de cabeça para
     * chegar ao total. Assim ele só tira do primeiro método o que vai pagar de
     * outra forma, e a segunda linha nasce com o que sobra.
     */
    const alternarDivisao = useCallback(() => {
        if (dividir) {
            setDividir(false);
            setPagamentos([]);

            return;
        }

        setDividir(true);
        setPagamentos([{ method: pagamento || 'cash', amount: String(arredondar2(totais.total)) }]);
    }, [dividir, pagamento, totais.total]);

    const juntarPagamento = useCallback(() => {
        setPagamentos((linhas) => {
            const usados = linhas.map((p) => p.method);
            const livre = metodos.find((m) => !usados.includes(m.code));
            if (!livre) return linhas;

            // A linha nova nasce com o que falta: é isso que o operador quer
            // escrever a seguir, e poupa-lhe a subtracção.
            const somado = linhas.reduce((s, p) => s + numero(p.amount), 0);
            const falta = arredondar2(totais.total - somado);

            return [...linhas, { method: livre.code, amount: falta > 0 ? String(falta) : '0' }];
        });
    }, [metodos, totais.total]);

    const removerPagamento = useCallback((i: number) => setPagamentos((l) => l.filter((_, j) => j !== i)), []);

    const mudarLinhaDoPagamento = useCallback((i: number, mudanca: Partial<LinhaDoPagamento>) => {
        setPagamentos((l) => l.map((p, j) => (j === i ? { ...p, ...mudanca } : p)));
    }, []);

    // ── Finalizar ────────────────────────────────────────────────────────
    const [finalizar, aGuardar] = useAccao(async () => {
        if (!carrinho.length) return;

        // SEM TURNO NÃO SE VENDE.
        //
        // Isto perguntava se queria continuar, e continuar era o caminho fácil:
        // a venda saía, mas ficava fora do fecho de caixa. Ao fim do dia o
        // dinheiro na gaveta não batia certo com o sistema e ninguém sabia de que
        // venda vinha a diferença — e uma caixa que não fecha não serve para
        // conferir ninguém.
        if (!turno.turno.open) {
            turno.abrirAbertura();

            return;
        }

        try {
            const metodo = metodos.find((m) => m.code === pagamento) ?? metodos[0]!;
            const recebidoN = pagamento === 'cash' && numero(recebido) > 0 ? numero(recebido) : totais.total;

            // 1) Cria a venda offline (idempotente, enfileirada para sincronização)
            const venda = await createPosSaleOffline({
                client_id: cliente && Number.isInteger(cliente.id) ? cliente.id : null,
                client_local_uuid: cliente && !Number.isInteger(cliente.id) ? cliente.local_uuid : null,
                // Daqui para baixo é a carga do documento fiscal: fica em
                // português em qualquer língua da interface, porque é isto que
                // sai impresso e vai para a AGT.
                client_name: cliente?.name || 'Consumidor Final',
                client_nif: cliente?.nif || '999999999',
                payment_method: pagamento,

                // AS LINHAS DA DIVISÃO, quando há. O servidor aceita-as desde
                // sempre; era este ecrã que nunca as mandava, e uma compra paga
                // em duas formas obrigava a duas vendas — e portanto a dois
                // documentos fiscais.
                //
                // O `payment_method` continua a ir: é o método principal, e é
                // dele que sai o texto do talão.
                payments: dividir
                    ? pagamentos
                        .filter((p) => numero(p.amount) > 0)
                        .map((p) => ({ method: p.method, amount: arredondar2(numero(p.amount)) }))
                    : null,

                amount_received: recebidoN,
                discount_commercial: numero(desconto),
                notes: 'POS Offline · Pagamento: ' + metodo.labelPt,
                items: carrinho.map((i) => ({
                    product_id: i.product_id,
                    product_name: i.product_name,
                    quantity: i.quantity,
                    unit_price: i.unit_price,
                    tax_rate: i.tax_rate,
                    is_service: false,
                    unit: 'UN',
                })),
            });

            // 2) Imprime IMEDIATAMENTE — funciona online ou offline
            PosOfflineTicket.print(venda, empresa || {});

            // 3) Mostra confirmação
            setRecibo({
                uuid: String(venda.local_uuid),
                registo: venda,
                cliente: cliente?.name || t('Consumidor Final'),
                itens: contagem,
                pagamento: metodo.label,
                total: totais.total,
                online: navigator.onLine,
            });

            setCarrinho([]);
            setClienteEscolhido(null);
            setPagamento('cash');
            setRecebido('');
            setDesconto('');
            setDividir(false);
            setPagamentos([]);
            setMostrarCarrinho(false);
        } catch (err) {
            console.error(err);
            avisar(t('Erro: :erro', { erro: (err as Error).message }), 'erro');
        }
    });

    // ── Talão: reimprimir e PDF ──────────────────────────────────────────
    const reimprimir = useCallback(async (venda: Registo) => {
        const fresca = await db.pos_sales.get(venda.local_uuid);
        PosOfflineTicket.print(fresca || venda, empresa || {});
    }, [empresa]);

    /*
     * O talão em PDF, para o WhatsApp. Com ou sem rede: sem rede faz-se no
     * aparelho; emitido e com rede, vai o PDF do servidor. Quem cancela a
     * folha de partilha não leva erro nenhum — cancelar não é falhar.
     */
    const [partilhar, aPartilhar] = useAccao(async (venda: Registo | null | undefined) => {
        if (!venda?.local_uuid) return;

        try {
            const r = await partilharPdf('venda', String(venda.local_uuid));
            if (r.modo === 'descarregado') avisar(t('PDF descarregado — anexe-o na conversa.'), 'ok');
        } catch (e) {
            if ((e as Error)?.name === 'AbortError') return;
            avisar(t('Não foi possível gerar o PDF: :erro', { erro: (e as Error).message }), 'erro');
        }
    });

    // ── Pendentes e trabalhos falhados ───────────────────────────────────
    const [sincronizarAgora, aSincronizar] = useAccao(() => sync(true));
    const [repetirTrabalho] = useAccao((id: number) => retryFailedJob(id));
    const [repetirTodos] = useAccao(() => retryAllFailed());

    // ── Arranque ─────────────────────────────────────────────────────────
    useEffect(() => {
        // As vendas JÁ sincronizadas com mais de 30 dias saem do aparelho.
        void purgarVendasAntigas();

        // Rede de segurança: catálogo vazio mas online → força a sincronização.
        // A lista é viva: quando os artigos chegam, a grelha enche-se sozinha.
        void db.products.count().then((n) => {
            if (!n && navigator.onLine) {
                sync(true).catch((e) => console.error('[POS] sync inicial falhou', e));
            }
        });
    }, []);

    // Aviso ao fechar/sair com documentos por sincronizar — ou com uma venda a meio.
    const pendentesActuais = useRef(0);
    pendentesActuais.current = turno.pendentes;
    const carrinhoActual = useRef(0);
    carrinhoActual.current = carrinho.length;

    useEffect(() => {
        const sair = (e: BeforeUnloadEvent) => {
            if (pendentesActuais.current > 0 || carrinhoActual.current > 0) {
                // Tenta sincronizar em segundo plano e pede para confirmar a saída.
                if (pendentesActuais.current > 0) {
                    try { if (navigator.onLine) void sync(true); } catch { /* sair não pode rebentar */ }
                }
                e.preventDefault();
                e.returnValue = '';

                return '';
            }

            return undefined;
        };
        window.addEventListener('beforeunload', sair);

        return () => window.removeEventListener('beforeunload', sair);
    }, []);

    // No telemóvel quem rola é a página: ao chegar ao fundo, carrega mais.
    useEffect(() => {
        const rolar = () => {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 320) carregarMaisSeHouver();
        };
        window.addEventListener('scroll', rolar, { passive: true });

        return () => window.removeEventListener('scroll', rolar);
    }, [carregarMaisSeHouver]);

    const ultimaSync = turno.ultimaSync;

    return {
        turno,
        produtos,
        catalogoCarregado,
        clientes,
        empresa,
        armazem,
        vendas,
        falhados,
        metodos,
        ultimaSync,

        pesquisa, mudarPesquisa,
        categoria, mudarCategoria,
        categorias, filtrados, visiveis, limite,
        carregarMais, carregarMaisSeHouver,

        carrinho, contagem, totais, sobrevenda, dinheiroRapido, troco,
        adicionar, lerCodigo, incrementar, decrementar, definirQuantidade, limparCarrinho,

        cliente, selecionarCliente: setClienteEscolhido,
        escolherCliente, setEscolherCliente,
        pesquisaCliente, setPesquisaCliente,
        criarCliente, setCriarCliente, nomeDoNovoCliente, abrirCriarCliente,

        pagamento, setPagamento,
        recebido, setRecebido,
        desconto, setDesconto,
        dividir, pagamentos, faltaPagar, divisaoFechada,
        alternarDivisao, juntarPagamento, removerPagamento, mudarLinhaDoPagamento,

        finalizar, aGuardar,
        recibo, registoDoRecibo, fecharRecibo: () => setRecibo(null),
        reimprimir, partilhar, aPartilhar,

        mostrarCarrinho, setMostrarCarrinho,
        mostrarPendentes, setMostrarPendentes,
        lerCamara, abrirCamara, fecharCamara: () => setLerCamara(false),
        avisoReceita, fecharAvisoReceita: () => setAvisoReceita(null),

        sincronizarAgora, aSincronizar, repetirTrabalho, repetirTodos,
    };
}

export type ControloDoPos = ReturnType<typeof usePos>;
