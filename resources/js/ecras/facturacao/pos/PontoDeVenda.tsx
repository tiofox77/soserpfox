import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { keepPreviousData, useInfiniteQuery, useMutation, useQuery } from '@tanstack/react-query';

import { pos, type ArtigoDoPos, type CategoriaDoPos, type ClienteDoPos, type VendaFechada } from '@/api/pos';
import { ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { duracaoPara, useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { avisar } from '@/casca/avisos';
import { ModalDePagamento } from './ModalDePagamento';
import { ModalDeCliente } from './ModalDeCliente';
import { ModalDePreco } from './ModalDePreco';
import { ModalDoTalao } from './ModalDoTalao';
import { somDoBalcao } from './som';
import { identificadorDaVenda } from './uuid';

/**
 * O BALCÃO.
 *
 * O ecrã em Livewire funcionava, e o que aqui muda não é gosto — é o que
 * medi nele, com o cronómetro e a régua:
 *
 * 1. A **1366×768**, que é o ecrã de balcão mais comum, o botão «Confirmar
 *    Venda» ficava ABAIXO da janela: quem fecha uma venda tinha de rolar. Aqui
 *    a barra de acção está presa ao fundo e não sai de lá.
 * 2. As **30 categorias**, 26 delas vazias, empurravam a única com artigos
 *    para fora do carrossel. Só se mostram as que têm alguma coisa; as vazias
 *    ficam atrás de um botão.
 * 3. O crachá do artigo dizia **«1 no»** — «1 no carrinho» cortado a meio.
 * 4. O desconto era um `%` sem rótulo ao lado de uma caixa com `0`.
 *
 * E ganha três coisas que um balcão precisa e não tinha: ATALHOS DE TECLADO,
 * o LEITOR DE CÓDIGO DE BARRAS a fechar sozinho quando só há um resultado, e
 * uma venda IDEMPOTENTE — carregar duas vezes em Finalizar dá uma factura só.
 *
 * O que NÃO muda: o som, o espelho do carrinho em localStorage, o preço
 * perguntado ao balcão, o multi-pagamento e a impressão. Isso estava certo.
 */

/** Uma linha do carrinho. O preço vive aqui: pode ser mudado à mão. */
export type LinhaDoCarrinho = {
    id: number;
    nome: string;
    unidade: string | null;
    servico: boolean;
    preco: number;
    quantidade: number;
    /** Quanto há no armazém, para não se vender o que não existe. Nulo = sem limite. */
    stock: number | null;
    /**
     * A taxa do artigo, em percentagem — como o servidor a resolveu. Zero é
     * isento. Opcional porque um carrinho guardado no browser ANTES desta
     * correcção não a tem: nesse caso a linha conta como isenta até o artigo
     * voltar a entrar, e nunca se inventa uma taxa que ninguém declarou.
     */
    taxa?: number;
};

/**
 * A CHAVE DO ESPELHO DO CARRINHO — por empresa E por operador.
 *
 * Lia a empresa de uma `<meta name="tenant-id">` que NÃO EXISTE em lado nenhum
 * do layout: a chave saía sempre `pos_carrinho_0`, igual para todas as empresas
 * e para todos os operadores. Quem tem mais do que uma casa trocava de empresa
 * e levava o carrinho atrás — artigos escolhidos numa ficavam lá para serem
 * facturados na outra — e num balcão partilhado o turno seguinte apanhava o
 * carrinho que o anterior deixou a meio.
 *
 * Agora vem do servidor, nas opções, que é quem sabe a empresa activa e quem
 * está a vender.
 */
function chaveDoCarrinho(dono: { empresa: number; operador: number }, modulo: string | null = null): string {
    // O carrinho do salão não se mistura com o do balcão da facturação.
    return `pos_carrinho_t${dono.empresa}_u${dono.operador}${modulo ? `_${modulo}` : ''}`;
}

/**
 * `modulo="salon"` é o balcão do salão (`/salon/pos`): o mesmo ecrã, com o
 * separador dos serviços à frente — o POS do salão em Livewire tinha os dois.
 */
export default function PontoDeVenda({ modulo = null }: { modulo?: string | null }) {
    const opcoes = useQuery({ queryKey: ['pos', 'opcoes', modulo], queryFn: () => pos.opcoes(modulo), staleTime: 60_000 });

    if (opcoes.isPending) return <Carregando linhas={10} />;

    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o balcão')}</h2>
                <p className="text-sm text-red-800">
                    {opcoes.error instanceof ErroDaApi ? opcoes.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    /*
     * SEM TURNO NÃO SE VENDE, e diz-se porquê em vez de se desenhar um balcão
     * que não fecha nada. O ecrã de sempre redireccionava sem explicar.
     */
    if (!opcoes.data.turno) {
        return <SemTurno rota={opcoes.data.rota_dos_turnos} />;
    }

    return <Balcao o={opcoes.data} />;
}

function SemTurno({ rota }: { rota: string }) {
    return (
        <div className={cls('mx-auto max-w-lg border border-amber-200 bg-amber-50 p-8 text-center', RAIO)}>
            <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-amber-100">
                <i className="fas fa-cash-register text-3xl text-amber-500" aria-hidden="true" />
            </div>
            <h2 className="text-xl font-bold text-amber-900">{t('Não há turno de caixa aberto')}</h2>
            <p className="mt-2 text-sm text-amber-800">
                {t('O dinheiro de uma venda tem de cair no turno de alguém. Abra o seu turno para começar a vender.')}
            </p>
            <a
                href={rota}
                className={cls(
                    'mt-6 inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-3',
                    'font-semibold text-white shadow-lg transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl',
                    RAIO,
                    FOCO,
                )}
            >
                <i className="fas fa-door-open" aria-hidden="true" />
                {t('Abrir turno de caixa')}
            </a>
        </div>
    );
}

type Opcoes = NonNullable<ReturnType<typeof pos.opcoes> extends Promise<infer T> ? T : never>;

function Balcao({ o }: { o: Opcoes }) {
    const [procura, porProcura] = useState('');
    const salao = o.modulo === 'salon';
    /** No salão: os serviços ou os produtos. Fora dele há só produtos. */
    const [tipo, porTipoCru] = useState<'servicos' | 'produtos'>(salao ? 'servicos' : 'produtos');
    /** Os `ids` da categoria escolhida, separados por vírgula — ver CategoriaDoPos. */
    const [categoria, porCategoria] = useState<string | null>(null);
    // As categorias dos serviços e as dos produtos são listas diferentes.
    const porTipo = useCallback((novo: 'servicos' | 'produtos') => {
        porTipoCru(novo);
        porCategoria(null);
    }, []);
    const [todasAsCategorias, porTodasAsCategorias] = useState(false);
    const [linhas, porLinhas] = useState<LinhaDoCarrinho[]>([]);
    const [cliente, porCliente] = useState<ClienteDoPos | null>(null);
    const [descontoTipo, porDescontoTipo] = useState<'percentagem' | 'valor'>('percentagem');
    const [desconto, porDesconto] = useState('');
    /*
     * OS AVISOS DO BALCÃO SAEM NO CANTO, como todo o sistema.
     *
     * Eram uma faixa vermelha por cima do catálogo: empurrava a grelha para
     * baixo a cada «só há 1 no armazém», e ficava lá até alguém a fechar.
     * O stock e a receita avisam em âmbar; a venda que não fecha, a vermelho.
     */
    const porAviso = useCallback((texto: string, tom: 'aviso' | 'erro' = 'aviso') => {
        if (texto.trim() !== '') avisar(texto, tom, { duracao: duracaoPara(texto) });
    }, []);
    const [, porRecado] = useRecadoNoCanto('');

    // Os modais.
    const [pagar, porPagar] = useState(false);
    /** No telemóvel o carrinho é uma folha que sobe de baixo (ver BarraDoCarrinho). */
    const [folhaDoCarrinho, porFolhaDoCarrinho] = useState(false);
    const aoLado = useLarguraMinima(768);
    const [escolherCliente, porEscolherCliente] = useState(false);
    const [aPerguntarPreco, porAPerguntarPreco] = useState<ArtigoDoPos | null>(null);
    /** O psicotrópico à espera de alguém responder. */
    const [aConfirmarControlado, porAConfirmarControlado] = useState<ArtigoDoPos | null>(null);
    const [vendida, porVendida] = useState<VendaFechada | null>(null);
    const [confirmarLimpeza, porConfirmarLimpeza] = useState(false);

    const caixaDeProcura = useRef<HTMLInputElement>(null);

    /* ─── A ALTURA DO BALCÃO ──────────────────────────────────────────────
     *
     * O ecrã tem de caber na janela SEM ROLAR, porque o botão de fechar a
     * venda não pode fugir para baixo — foi exactamente esse o defeito do
     * ecrã de sempre: a 1366×768 o «Confirmar Venda» ficava em y 764, fora da
     * janela, e quem fechava uma venda tinha de rolar com o cliente à espera.
     *
     * A altura não se pode escrever à mão (`100vh - 16rem` e afins): o cromo
     * da aplicação — cabeçalho, avisos, migalhas — muda de tamanho conforme a
     * empresa e o que está a acontecer. Mede-se onde o balcão COMEÇA e
     * ocupa-se o que sobra. Volta a medir-se quando a janela muda.
     */
    const corpo = useRef<HTMLDivElement>(null);
    const [altura, porAltura] = useState<number | null>(null);

    useEffect(() => {
        function medir() {
            if (!corpo.current) return;

            const topo = corpo.current.getBoundingClientRect().top + window.scrollY;
            // A folga de baixo é o padding do contentor da página, que muda com
            // o tamanho do ecrã (12, 16 ou 24 px). Com 16 fixos, a 1024×768 o
            // «Finalizar» ficava 11 px abaixo da janela.
            const main = document.getElementById('app-main');
            const folga = main ? parseFloat(getComputedStyle(main).paddingBottom) || 16 : 16;
            // No telemóvel a barra do carrinho fica presa em baixo.
            const barra = window.innerWidth < 768 ? 76 : 0;

            porAltura(Math.max(300, window.innerHeight - topo - folga - barra));
        }

        medir();
        window.addEventListener('resize', medir);
        // O topo do balcão também muda sem a janela mudar (a faixa quebra
        // linha, um aviso aparece): mede-se quando o que está por cima cresce.
        const observador = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(medir) : null;
        if (corpo.current?.parentElement) observador?.observe(corpo.current.parentElement);

        return () => {
            window.removeEventListener('resize', medir);
            observador?.disconnect();
        };
    }, []);

    /* ─── O espelho do carrinho ───────────────────────────────────────────
     *
     * O carrinho vive em localStorage e volta ao recarregar. Não é conforto:
     * é o que impede uma sessão expirada, ou um F5 por engano, de deitar fora
     * o que o cliente já pôs em cima do balcão.
     */
    useEffect(() => {
        try {
            const guardado = localStorage.getItem(chaveDoCarrinho(o.dono_do_carrinho, o.modulo));

            if (guardado) {
                const { itens } = JSON.parse(guardado) as { itens?: LinhaDoCarrinho[] };

                if (Array.isArray(itens) && itens.length > 0) {
                    porLinhas(itens);
                    porRecado(t('O carrinho de antes foi recuperado.'));
                }
            }
        } catch {
            /* localStorage pode estar fechado (janela privada): segue-se sem ele. */
        }
    }, []);

    useEffect(() => {
        try {
            if (linhas.length > 0) {
                localStorage.setItem(chaveDoCarrinho(o.dono_do_carrinho, o.modulo), JSON.stringify({ ts: Date.now(), itens: linhas }));
            } else {
                localStorage.removeItem(chaveDoCarrinho(o.dono_do_carrinho, o.modulo));
            }
        } catch {
            /* idem */
        }
    }, [linhas]);

    /* ─── Os artigos ──────────────────────────────────────────────────── */

    /*
     * A GRELHA VEM AOS BOCADOS — e vem toda.
     *
     * O servidor mandava só os primeiros 50 e a grelha acabava ali: numa
     * farmácia com 259 ampolas, o resto só se via procurando pelo nome. Agora
     * pede 60 de cada vez e, quando se chega perto do fundo, pede os seguintes
     * sozinha (ver `Catalogo`).
     */
    const artigos = useInfiniteQuery({
        queryKey: ['pos', 'artigos', procura, categoria, o.armazem.id, o.modulo, tipo],
        queryFn: ({ pageParam }) =>
            pos.artigos({ procura, categoria, armazem: o.armazem.id, pagina: pageParam, ...(salao ? { modulo: o.modulo, tipo } : {}) }),
        initialPageParam: 1,
        getNextPageParam: (ultima) => (ultima.meta?.mais ? ultima.meta.pagina + 1 : undefined),
        placeholderData: keepPreviousData,
        staleTime: 15_000,
    });

    // As páginas numa lista só — e sem repetidos: um artigo que vende entre
    // um bocado e o seguinte pode escorregar de página e aparecer duas vezes.
    const lista = useMemo(() => {
        const vistos = new Set<number>();

        return (artigos.data?.pages ?? []).flatMap((p) => p.data).filter((a) => (vistos.has(a.id) ? false : (vistos.add(a.id), true)));
    }, [artigos.data]);

    /*
     * AS CATEGORIAS QUE TÊM ALGUMA COISA.
     *
     * São trinta, e vinte e seis estão vazias. No ecrã de sempre empurravam a
     * única com artigos para fora do carrossel — havia que arrastar para a
     * encontrar. As vazias continuam a poder ver-se, mas atrás de um botão.
     */
    const categoriasDoTipo = salao && tipo === 'servicos' ? o.categorias_de_servicos : o.categorias;
    const comArtigos = useMemo(() => categoriasDoTipo.filter((c) => c.artigos > 0), [categoriasDoTipo]);
    const vazias = categoriasDoTipo.length - comArtigos.length;
    const categoriasAMostrar = todasAsCategorias ? categoriasDoTipo : comArtigos;

    /* ─── O carrinho ──────────────────────────────────────────────────── */

    const juntar = useCallback((a: ArtigoDoPos, precoEscrito?: number) => {
        const preco = precoEscrito ?? a.preco;

        porLinhas((ls) => {
            const ja = ls.find((l) => l.id === a.id);

            if (ja) {
                // O stock manda: não se vende o que não há.
                if (ja.stock !== null && ja.quantidade + 1 > ja.stock) {
                    porAviso(t('Só há :n de :artigo no armazém.', { n: ja.stock, artigo: a.nome }));
                    somDoBalcao('erro');

                    return ls;
                }

                somDoBalcao('juntar');

                return ls.map((l) => (l.id === a.id ? { ...l, quantidade: l.quantidade + 1 } : l));
            }

            if (a.stock !== null && a.stock < 1) {
                porAviso(t(':artigo está sem stock neste armazém.', { artigo: a.nome }));
                somDoBalcao('erro');

                return ls;
            }

            somDoBalcao('juntar');

            /*
             * A RECEITA AVISA, NÃO TRAVA.
             *
             * O operador pode ter a receita na mão — e o aviso tem de aparecer
             * AGORA, quando o artigo entra no carrinho. Depois de emitida a
             * factura o medicamento já saiu da farmácia, e um aviso no fim não
             * serve para nada.
             */
            if (a.receita) {
                porAviso(t(':artigo exige RECEITA MÉDICA. Confirme que a tem antes de entregar.', { artigo: a.nome }));
            }

            return [
                ...ls,
                {
                    id: a.id,
                    nome: a.nome,
                    unidade: a.unidade,
                    servico: a.servico,
                    preco,
                    quantidade: 1,
                    stock: a.stock,
                    // A taxa viaja COM a linha: o carrinho tem de saber o
                    // imposto de cada artigo para o mostrar sem voltar ao
                    // servidor a cada peça que entra.
                    taxa: Number(a.taxa) || 0,
                },
            ];
        });
    }, []);

    /**
     * Clicar num artigo.
     *
     * Três caminhos: o CONTROLADO pergunta antes de entrar, o que PERGUNTA O
     * PREÇO abre o modal do preço, e o resto entra directo.
     */
    const escolher = useCallback(
        (a: ArtigoDoPos) => {
            /*
             * UM PSICOTRÓPICO NÃO ENTRA SEM RESPOSTA HUMANA.
             *
             * É uma substância cuja venda tem registo legal: ninguém a despacha
             * com um clique distraído no meio de uma grelha de artigos.
             *
             * Mas o stock manda primeiro: um controlado esgotado nem chega a
             * perguntar, que não há o que vender.
             */
            if (a.controlado && !(a.stock !== null && a.stock < 1)) {
                porAConfirmarControlado(a);

                return;
            }

            if (a.pergunta_preco) {
                porAPerguntarPreco(a);

                return;
            }

            juntar(a);
        },
        [juntar],
    );

    const mudarQuantidade = useCallback((id: number, delta: number) => {
        porLinhas((ls) =>
            ls
                .map((l) => {
                    if (l.id !== id) return l;

                    const nova = l.quantidade + delta;

                    if (l.stock !== null && nova > l.stock) {
                        porAviso(t('Só há :n no armazém.', { n: l.stock }));
                        somDoBalcao('erro');

                        return l;
                    }

                    return { ...l, quantidade: nova };
                })
                .filter((l) => l.quantidade > 0),
        );
    }, []);

    const tirar = useCallback((id: number) => {
        somDoBalcao('tirar');
        porLinhas((ls) => ls.filter((l) => l.id !== id));
    }, []);

    const limpar = useCallback(() => {
        porConfirmarLimpeza(true);
    }, []);

    const confirmarQueLimpa = useCallback(() => {
        porLinhas([]);
        porCliente(null);
        porDesconto('');
        porConfirmarLimpeza(false);
        somDoBalcao('tirar');
    }, []);

    /* ─── As contas ───────────────────────────────────────────────────────
     *
     * SÃO SÓ PARA MOSTRAR. O total que vale é o que o servidor calcula ao
     * fechar — com a taxa que está na base, e não com a que o ecrã acha. Aqui
     * chega uma aproximação para o operador saber o que pedir.
     */
    const subtotal = useMemo(
        () => linhas.reduce((s, l) => s + Math.round(l.preco * l.quantidade * 100) / 100, 0),
        [linhas],
    );

    const descontoValor = useMemo(() => {
        const n = Number(String(desconto).replace(',', '.')) || 0;

        if (n <= 0) return 0;

        return descontoTipo === 'percentagem'
            ? Math.round(subtotal * Math.min(n, 100)) / 100
            : Math.min(n, subtotal);
    }, [desconto, descontoTipo, subtotal]);

    const base = Math.max(0, subtotal - descontoValor);

    /**
     * O IMPOSTO, LINHA A LINHA — e pela mesma conta que o servidor faz.
     *
     * O balcão em Livewire mostrava «IVA (taxa) +X Kz» e um «TOTAL A PAGAR»
     * COM imposto. A migração para React (07/09/2026) perdeu a linha e deixou
     * o rótulo «A pagar» em cima da BASE: o operador dizia 17.000 ao cliente,
     * recebia 17.000, dava o troco sobre 17.000 — e a factura saía a 19.380,
     * porque o imposto é somado POR CIMA do preço do artigo.
     *
     * A conta é a do `InvoiceCalculationHelper`: o desconto do documento
     * reparte-se pelas linhas na proporção do que cada uma pesa, e a taxa
     * incide sobre o que sobra. A taxa vem do servidor por artigo — o ecrã
     * nunca a inventa, e uma linha sem taxa conta como ISENTA.
     */
    const imposto = useMemo(() => {
        if (subtotal <= 0) return 0;

        return linhas.reduce((soma, l) => {
            const taxa = Number(l.taxa) || 0;

            if (taxa <= 0) return soma;

            const bruto = Math.round(l.preco * l.quantidade * 100) / 100;
            const baseDaLinha = bruto - descontoValor * (bruto / subtotal);

            return soma + Math.round(baseDaLinha * (taxa / 100) * 100) / 100;
        }, 0);
    }, [linhas, subtotal, descontoValor]);

    const aPagar = Math.round((base + imposto) * 100) / 100;

    /** As taxas que o carrinho tem, para o rótulo dizer «IVA (14%)» ou só «IVA». */
    const taxasNoCarrinho = useMemo(
        () => [...new Set(linhas.map((l) => Number(l.taxa) || 0).filter((t) => t > 0))],
        [linhas],
    );

    const rotuloDoImposto =
        taxasNoCarrinho.length === 1
            ? t('IVA (:taxa%)', { taxa: String(taxasNoCarrinho[0]).replace('.', ',') })
            : t('IVA');

    /* ─── Atalhos de teclado ──────────────────────────────────────────────
     *
     * O balcão de sempre não tinha nenhum: tudo se fazia com o rato, e ao
     * balcão o rato é o caminho mais lento que há. F2 procura, F4 escolhe o
     * cliente, F9 paga, Escape fecha.
     */
    useEffect(() => {
        function aoTeclar(e: KeyboardEvent) {
            if (e.key === 'F2') {
                e.preventDefault();
                caixaDeProcura.current?.focus();
                caixaDeProcura.current?.select();
            }

            if (e.key === 'F4') {
                e.preventDefault();
                porEscolherCliente(true);
            }

            if (e.key === 'F9' && linhas.length > 0) {
                e.preventDefault();
                porPagar(true);
            }
        }

        window.addEventListener('keydown', aoTeclar);

        return () => window.removeEventListener('keydown', aoTeclar);
    }, [linhas.length]);

    /*
     * O LEITOR DE CÓDIGO DE BARRAS.
     *
     * Um leitor escreve o código e carrega em Enter. Se a procura deixou UM
     * artigo só à vista, esse entra no carrinho e a caixa limpa-se sozinha —
     * que é o que o operador faria a seguir, e é a diferença entre passar
     * trinta artigos por minuto e passar dez.
     */
    async function aoSubmeterProcura(e: React.FormEvent) {
        e.preventDefault();

        if (lista.length === 1) {
            const unico = lista[0];
            if (!unico) return;
            escolher(unico);
            porProcura('');

            return;
        }

        if (lista.length !== 0) return;

        /*
         * NADA NA GRELHA NÃO QUER DIZER «NÃO EXISTE».
         *
         * A grelha esconde o que está sem stock — numa das farmácias, 1415 de
         * 5729 artigos. Passar o leitor por um esgotado devolvia «nada
         * encontrado», indistinguível de um código desconhecido, e o operador
         * concluía que a leitura não funcionava. Com o produto na mão.
         *
         * Antes de dizer que não existe, pergunta-se ao catálogo INTEIRO.
         */
        try {
            const r = await pos.porCodigo(procura);

            if (r.estado === 'encontrado') {
                escolher(r.artigo);
                porProcura('');

                return;
            }

            if (r.estado === 'sem_stock' || r.estado === 'inactivo' || r.estado === 'de_modulo') {
                porAviso(r.message);
                somDoBalcao('erro');
                porProcura('');

                return;
            }
        } catch {
            /* Sem rede: fica o aviso genérico, que é melhor do que nada. */
        }

        porAviso(t('Nada encontrado para «:procura».', { procura }));
        somDoBalcao('erro');
    }

    /* ─── Fechar a venda ──────────────────────────────────────────────── */

    /*
     * UMA VENDA, UM IDENTIFICADOR — e um disparo de cada vez.
     *
     * O identificador nascia a cada «Confirmar». Dois disparos seguidos (duplo
     * clique, o Enter do leitor de códigos, o Enter repetido) levavam dois
     * identificadores, e o servidor, que só reconhece o mesmo, gravava DUAS
     * facturas: foi a FR 003253/003254 da Luk Simões, com um segundo de
     * diferença (23/09/2026). O `aTrabalhar` não chegava para travar, porque só
     * muda quando o ecrã se redesenha.
     *
     * Agora o identificador é da VENDA: fica o mesmo numa nova tentativa depois
     * de um erro (se a primeira chegou a gravar, o servidor devolve essa), e só
     * muda quando o carrinho muda ou a venda fecha.
     */
    const idDaVenda = useRef<string | null>(null);
    const aVender = useRef(false);

    useEffect(() => {
        idDaVenda.current = null;
    }, [linhas, cliente, desconto, descontoTipo]);

    const vender = useMutation({
        mutationFn: (corpo: Record<string, unknown>) => pos.vender(corpo),
        onSettled: () => {
            aVender.current = false;
        },
        onSuccess: (v) => {
            idDaVenda.current = null;
            somDoBalcao('venda');
            porPagar(false);
            porVendida(v);
            porLinhas([]);
            porCliente(null);
            porDesconto('');
            porRecado(v.message);
        },
        onError: (e) => porAviso(e instanceof ErroDaApi ? e.message : t('Não foi possível fechar a venda.'), 'erro'),
    });

    return (
        <div className="flex flex-col gap-3">
            {/* A FAIXA DO BALCÃO: quem está, em que turno, de que armazém sai a
                mercadoria. No ecrã de sempre o cabeçalho da página dizia
                «Dashboard — Bem-vindo ao sistema», que num POS não diz nada. */}
            <Faixa turno={o.turno!} armazem={o.armazem} />

            {/* O CORPO: catálogo à esquerda, carrinho à direita, e os dois a
                rolar POR DENTRO. A altura é a que sobra da janela — medida, não
                escrita à mão — para que o botão de fechar a venda esteja
                sempre no mesmo sítio, em qualquer ecrã. */}
            <div
                ref={corpo}
                className="grid min-h-0 gap-3 md:grid-cols-[minmax(0,1fr)_18.5rem] xl:grid-cols-[minmax(0,1fr)_minmax(340px,26rem)]"
                style={altura ? { height: `${altura}px` } : { minHeight: '32rem' }}
            >
                <Catalogo
                    separadores={salao ? { tipo, porTipo } : null}
                    procura={procura}
                    porProcura={porProcura}
                    caixaDeProcura={caixaDeProcura}
                    aoSubmeter={aoSubmeterProcura}
                    categorias={categoriasAMostrar}
                    vazias={vazias}
                    todas={todasAsCategorias}
                    porTodas={porTodasAsCategorias}
                    categoria={categoria}
                    porCategoria={porCategoria}
                    logotipo={o.logotipo}
                    artigos={lista}
                    aCarregar={artigos.isFetching && !artigos.isFetchingNextPage}
                    chaveDaLista={`${procura}|${categoria ?? ''}|${tipo}`}
                    haMais={Boolean(artigos.hasNextPage)}
                    aCarregarMais={artigos.isFetchingNextPage}
                    carregarMais={() => void artigos.fetchNextPage()}
                    linhas={linhas}
                    aoEscolher={escolher}
                />

                {aoLado && <Carrinho
                    linhas={linhas}
                    cliente={cliente}
                    subtotal={subtotal}
                    desconto={desconto}
                    porDesconto={porDesconto}
                    descontoTipo={descontoTipo}
                    porDescontoTipo={porDescontoTipo}
                    descontoValor={descontoValor}
                    base={base}
                    imposto={imposto}
                    rotuloDoImposto={rotuloDoImposto}
                    aPagar={aPagar}
                    aoMudarQuantidade={mudarQuantidade}
                    aoTirar={tirar}
                    aoLimpar={limpar}
                    aoEscolherCliente={() => porEscolherCliente(true)}
                    aoPagar={() => porPagar(true)}
                    podeVender={o.permissoes.pode_vender}
                />}
            </div>

            {/* O TELEMÓVEL: o catálogo no ecrã inteiro, e o carrinho numa barra
                presa em baixo que abre uma folha. Empilhados na mesma altura, o
                catálogo mostrava uma fila de artigos e o carrinho ficava
                espremido, com o «Finalizar Venda» cortado em baixo. */}
            {!aoLado && (
                <BarraDoCarrinho
                    pecas={linhas.reduce((n, l) => n + l.quantidade, 0)}
                    total={base}
                    podeVender={o.permissoes.pode_vender}
                    aoAbrir={() => porFolhaDoCarrinho(true)}
                    aoPagar={() => porPagar(true)}
                />
            )}
            {!aoLado && folhaDoCarrinho && (
                <div className="animate-fade-in fixed inset-0 z-40 bg-slate-900/40" onClick={() => porFolhaDoCarrinho(false)}>
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label={t('Carrinho')}
                        onClick={(e) => e.stopPropagation()}
                        className="animate-scale-in absolute inset-x-0 bottom-0 top-8 flex flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl"
                    >
                        <div className="relative flex items-center justify-center border-b border-slate-200 py-3">
                            <span className="h-1.5 w-10 rounded-full bg-slate-300" aria-hidden="true" />
                            <button
                                type="button"
                                onClick={() => porFolhaDoCarrinho(false)}
                                aria-label={t('Fechar')}
                                className={cls('absolute right-2 top-1.5 grid h-9 w-9 place-items-center text-slate-500 hover:bg-slate-100', RAIO, FOCO)}
                            >
                                <i className="fas fa-xmark" aria-hidden="true" />
                            </button>
                        </div>
                        <div className="flex min-h-0 flex-1 flex-col">
                            <Carrinho
                                linhas={linhas}
                                cliente={cliente}
                                subtotal={subtotal}
                                desconto={desconto}
                                porDesconto={porDesconto}
                                descontoTipo={descontoTipo}
                                porDescontoTipo={porDescontoTipo}
                                descontoValor={descontoValor}
                                base={base}
                                imposto={imposto}
                                rotuloDoImposto={rotuloDoImposto}
                                aPagar={aPagar}
                                aoMudarQuantidade={mudarQuantidade}
                                aoTirar={tirar}
                                aoLimpar={limpar}
                                aoEscolherCliente={() => porEscolherCliente(true)}
                                aoPagar={() => { porFolhaDoCarrinho(false); porPagar(true); }}
                                podeVender={o.permissoes.pode_vender}
                            />
                        </div>
                    </div>
                </div>
            )}

            {/* ─── Os modais ─────────────────────────────────────────────── */}

            <ModalDePreco
                artigo={aPerguntarPreco}
                aoFechar={() => porAPerguntarPreco(null)}
                aoConfirmar={(preco) => {
                    if (aPerguntarPreco) juntar(aPerguntarPreco, preco);
                    porAPerguntarPreco(null);
                }}
            />

            {/*
              * UM PSICOTRÓPICO NÃO ENTRA SEM RESPOSTA HUMANA.
              *
              * A venda de uma substância controlada tem registo legal. O modal
              * não é um obstáculo: é o momento em que alguém assume a venda,
              * antes de o artigo sair da farmácia.
              */}
            <Modal
                aberto={aConfirmarControlado !== null}
                aoFechar={() => porAConfirmarControlado(null)}
                titulo={t('Substância controlada')}
                subtitulo={aConfirmarControlado?.nome}
                icone="fa-triangle-exclamation"
                cor="aviso"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAConfirmarControlado(null)}>{t('Não vender')}</Botao>
                        <Botao
                            cor="aviso"
                            tom="solida"
                            icone="fa-check"
                            onClick={() => {
                                const a = aConfirmarControlado;

                                porAConfirmarControlado(null);

                                if (!a) return;

                                // O preço perguntado ao balcão vem depois: um
                                // artigo pode ser as duas coisas.
                                if (a.pergunta_preco) porAPerguntarPreco(a);
                                else juntar(a);
                            }}
                        >
                            {t('Confirmo a venda')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('Este artigo é de venda controlada e fica registado. Confirme a identificação de quem o leva e a receita, se for exigida, antes de continuar.')}
                </p>
            </Modal>

            <ModalDeCliente
                aberto={escolherCliente}
                podeCriar={o.permissoes.pode_criar_cliente}
                aoFechar={() => porEscolherCliente(false)}
                aoEscolher={(c) => {
                    porCliente(c);
                    porEscolherCliente(false);
                }}
            />

            <ModalDePagamento
                aberto={pagar}
                aoFechar={() => porPagar(false)}
                // O QUE SE COBRA, com imposto. Ia a `base` — sem ele — e era esse
                // o número que o operador dizia e recebia, enquanto a factura
                // saía com o imposto somado por cima.
                total={aPagar}
                subtotal={subtotal}
                desconto={descontoValor}
                imposto={imposto}
                rotuloDoImposto={rotuloDoImposto}
                formas={o.formas_de_pagamento}
                montantesRapidos={o.definicoes.montantes_rapidos}
                aTrabalhar={vender.isPending}
                aoConfirmar={(p) => {
                    // Um segundo disparo antes de o ecrã se redesenhar não é outra venda.
                    if (aVender.current) return;
                    aVender.current = true;
                    idDaVenda.current ??= identificadorDaVenda();

                    vender.mutate({
                        // Um por VENDA: é o que torna a venda idempotente.
                        local_uuid: idDaVenda.current,
                        modulo: o.modulo,
                        client_id: cliente?.id ?? null,
                        payment_method: p.payment_method,
                        payments: p.payments,
                        amount_received: p.amount_received,
                        // A percentagem vai como percentagem; o valor vai em Kz. Ia a zero e o
                        // desconto por valor não chegava à factura (ver PosSaleService).
                        discount_commercial: descontoTipo === 'percentagem' ? Math.min(Number(String(desconto).replace(',', '.')) || 0, 100) : 0,
                        discount_value: descontoTipo === 'valor' ? descontoValor : 0,
                        notes: p.notes,
                        items: linhas.map((l) => ({
                            product_id: l.id,
                            product_name: l.nome,
                            quantity: l.quantidade,
                            unit_price: l.preco,
                            is_service: l.servico,
                            unit: l.unidade,
                        })),
                    });
                }}
            />

            <ModalDoTalao venda={vendida} aoFechar={() => porVendida(null)} />

            <Modal
                aberto={confirmarLimpeza}
                aoFechar={() => porConfirmarLimpeza(false)}
                titulo={t('Limpar o carrinho')}
                subtitulo={t('Confirme antes de remover o atendimento em curso')}
                icone="fa-cart-arrow-down"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porConfirmarLimpeza(false)}>{t('Continuar a venda')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" onClick={confirmarQueLimpa}>
                            {t('Sim, limpar tudo')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('Os artigos, o cliente e o desconto desta venda serão removidos do carrinho.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── As peças ─────────────────────────────────────────────────────────── */

function Faixa({
    turno,
    armazem,
}: {
    turno: { numero: string; aberto_em: string | null };
    armazem: { nome: string | null };
}) {
    return (
        <div
            className={cls(
                'flex items-center justify-between gap-2 bg-gradient-to-r from-indigo-600 via-violet-600 to-purple-600 px-3 py-2 text-white shadow-lg sm:flex-wrap sm:gap-3 sm:px-5 sm:py-3',
                RAIO,
            )}
        >
            <div className="flex items-center gap-3">
                <div className="hidden h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/20 backdrop-blur-sm sm:grid">
                    <i className="fas fa-cash-register icon-float text-xl" aria-hidden="true" />
                </div>
                <div>
                    <h1 className="text-base font-bold leading-tight sm:text-lg">{t('Ponto de Venda')}</h1>
                    <p className="text-xs text-indigo-100">
                        {t('Turno :numero', { numero: turno.numero })}
                        {turno.aberto_em && ` · ${t('aberto às :hora', { hora: turno.aberto_em.slice(11, 16) })}`}
                    </p>
                </div>
            </div>

            <div className="flex min-w-0 flex-wrap items-center justify-end gap-2 text-xs">
                {armazem.nome && (
                    <span className="inline-flex min-w-0 max-w-[45vw] items-center gap-2 rounded-lg bg-white/15 px-2.5 py-1.5 font-semibold backdrop-blur-sm sm:max-w-none sm:px-3" title={armazem.nome}>
                        <i className="fas fa-warehouse shrink-0" aria-hidden="true" />
                        <span className="truncate">{armazem.nome}</span>
                    </span>
                )}
                {/* Os atalhos à vista: ao balcão o rato é o caminho mais lento. */}
                <span className="hidden items-center gap-2 rounded-lg bg-white/15 px-3 py-1.5 font-mono backdrop-blur-sm md:inline-flex">
                    F2 {t('procurar')} · F4 {t('cliente')} · F9 {t('pagar')}
                </span>
            </div>
        </div>
    );
}

function Catalogo({
    separadores,
    procura,
    porProcura,
    caixaDeProcura,
    aoSubmeter,
    categorias,
    vazias,
    todas,
    porTodas,
    categoria,
    porCategoria,
    logotipo,
    artigos,
    aCarregar,
    chaveDaLista,
    haMais,
    aCarregarMais,
    carregarMais,
    linhas,
    aoEscolher,
}: {
    procura: string;
    porProcura: (v: string) => void;
    caixaDeProcura: React.RefObject<HTMLInputElement | null>;
    aoSubmeter: (e: React.FormEvent) => void;
    categorias: CategoriaDoPos[];
    vazias: number;
    todas: boolean;
    porTodas: (v: boolean) => void;
    categoria: string | null;
    porCategoria: (v: string | null) => void;
    logotipo: string | null;
    artigos: ArtigoDoPos[];
    aCarregar: boolean;
    /** Muda com a procura, a categoria e o separador: a grelha volta ao topo. */
    chaveDaLista: string;
    haMais: boolean;
    aCarregarMais: boolean;
    carregarMais: () => void;
    linhas: LinhaDoCarrinho[];
    aoEscolher: (a: ArtigoDoPos) => void;
    separadores: { tipo: 'servicos' | 'produtos'; porTipo: (t: 'servicos' | 'produtos') => void } | null;
}) {
    const noCarrinho = useMemo(() => new Map(linhas.map((l) => [l.id, l.quantidade])), [linhas]);
    const grelha = useRef<HTMLDivElement>(null);
    const fundo = useRef<HTMLDivElement>(null);

    // Outra procura, outra categoria: começa-se do princípio da lista nova.
    useEffect(() => {
        grelha.current?.scrollTo({ top: 0 });
    }, [chaveDaLista]);

    /*
     * O FUNDO DA GRELHA PEDE MAIS.
     *
     * Um marcador invisível depois do último cartão; quando fica a 600 px de
     * aparecer, pede-se a página seguinte — chega antes de o operador lá
     * chegar, e a lista parece não ter fim até ter mesmo.
     */
    useEffect(() => {
        const alvo = fundo.current;

        if (!alvo || !haMais || aCarregarMais || typeof IntersectionObserver === 'undefined') return;

        const vigia = new IntersectionObserver(
            (entradas) => {
                if (entradas.some((e) => e.isIntersecting)) carregarMais();
            },
            { root: grelha.current, rootMargin: '0px 0px 600px 0px' },
        );

        vigia.observe(alvo);

        return () => vigia.disconnect();
    }, [haMais, aCarregarMais, carregarMais, artigos.length]);

    return (
        <section className={cls('flex min-h-0 flex-col overflow-hidden border border-slate-200 bg-white shadow-sm', RAIO)}>
            {/* A PROCURA. Grande de propósito: é o campo mais usado do ecrã, e
                é aqui que o leitor de código de barras escreve. */}
            <form onSubmit={aoSubmeter} className="border-b border-slate-200 bg-slate-50 p-3">
                {separadores && (
                    <div role="tablist" aria-label={t('O que vender')} className={cls('mb-2.5 grid grid-cols-2 gap-1 bg-slate-200/70 p-1', RAIO)}>
                        {(
                            [
                                { valor: 'servicos', rotulo: t('Serviços'), icone: 'fa-spa' },
                                { valor: 'produtos', rotulo: t('Produtos'), icone: 'fa-box' },
                            ] as const
                        ).map((s) => {
                            const activo = separadores.tipo === s.valor;

                            return (
                                <button
                                    key={s.valor}
                                    type="button"
                                    role="tab"
                                    aria-selected={activo}
                                    onClick={() => separadores.porTipo(s.valor)}
                                    className={cls(
                                        'flex items-center justify-center gap-2 py-2 text-sm font-semibold transition-all duration-200',
                                        activo
                                            ? 'bg-gradient-to-r from-pink-500 to-fuchsia-600 text-white shadow-md'
                                            : 'text-slate-600 hover:bg-white/80 hover:text-slate-900',
                                        RAIO,
                                        FOCO,
                                    )}
                                >
                                    <i className={cls('fas', s.icone, activo && 'animate-pulse')} aria-hidden="true" />
                                    {s.rotulo}
                                </button>
                            );
                        })}
                    </div>
                )}
                <div className="relative">
                    <i
                        className="fas fa-barcode pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        ref={caixaDeProcura}
                        type="search"
                        value={procura}
                        onChange={(e) => porProcura(e.target.value)}
                        placeholder={t('Procurar ou ler o código de barras…  (F2)')}
                        aria-label={t('Procurar artigo')}
                        autoFocus
                        className={cls(
                            'w-full border border-slate-300 bg-white py-3 pl-12 pr-4 text-base font-medium shadow-sm',
                            'placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200',
                            RAIO,
                            FOCO,
                        )}
                    />
                </div>

                <FaixaDeCategorias
                    categorias={categorias}
                    vazias={vazias}
                    todas={todas}
                    porTodas={porTodas}
                    categoria={categoria}
                    porCategoria={porCategoria}
                />
            </form>

            {/* A GRELHA. Cartões maiores do que os de antes: ao balcão acerta-se
                com o dedo, e um alvo pequeno é um artigo errado na factura. */}
            <div ref={grelha} className={cls('flex-1 overflow-y-auto p-3 transition-opacity duration-200', aCarregar && 'opacity-60')} data-grelha-do-pos>
                {artigos.length === 0 ? (
                    <div className="flex h-full flex-col items-center justify-center py-16 text-center">
                        <div className="mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                            <i className="fas fa-box-open text-3xl text-slate-400" aria-hidden="true" />
                        </div>
                        <p className="text-lg font-semibold text-slate-500">
                            {aCarregar ? t('A procurar…') : t('Nenhum artigo à vista')}
                        </p>
                        <p className="mt-1 text-sm text-slate-400">
                            {procura
                                ? t('Nada com «:procura». Tente o código, o código de barras ou o tamanho.', { procura })
                                : t('Escolha outra categoria ou escreva na caixa de procura.')}
                        </p>
                    </div>
                ) : (
                    // As colunas saem do ESPAÇO do catálogo, não da largura da
                    // janela: com a barra lateral aberta a 1024 px eram 4 colunas
                    // em 300 px (cartões de 59 px), e a 2560 eram 5 de 352 px.
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))] gap-2.5 sm:grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] 2xl:grid-cols-[repeat(auto-fill,minmax(11rem,1fr))]">
                        {artigos.map((a, i) => (
                            <CartaoDeArtigo
                                key={a.id}
                                a={a}
                                i={i}
                                noCarrinho={noCarrinho.get(a.id) ?? 0}
                                logotipo={logotipo}
                                onClick={() => aoEscolher(a)}
                            />
                        ))}
                    </div>
                )}

                {artigos.length > 0 && (
                    <div ref={fundo} className="flex min-h-16 items-center justify-center py-4" data-fundo-da-grelha>
                        {aCarregarMais ? (
                            <span className="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm" role="status">
                                <i className="fas fa-spinner fa-spin" aria-hidden="true" />
                                {t('A carregar mais artigos…')}
                            </span>
                        ) : haMais ? (
                            // O botão é a rede: se o browser não vigiar o fundo, carrega-se à mão.
                            <button
                                type="button"
                                onClick={carregarMais}
                                className={cls('inline-flex items-center gap-2 border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-indigo-50 hover:shadow-md', RAIO, FOCO)}
                            >
                                <i className="fas fa-arrow-down" aria-hidden="true" />
                                {t('Mostrar mais artigos')}
                            </button>
                        ) : (
                            <span className="inline-flex items-center gap-2 text-xs font-medium text-slate-400">
                                <i className="fas fa-circle-check text-emerald-400" aria-hidden="true" />
                                {t('São todos — :n artigo(s)', { n: artigos.length.toLocaleString('pt-PT') })}
                            </span>
                        )}
                    </div>
                )}
            </div>
        </section>
    );
}

/** A chave de uma categoria no filtro: os seus `ids`, separados por vírgula. */
const chaveDaCategoria = (c: CategoriaDoPos) => (c.ids?.length ? c.ids : [c.id]).join(',');

/**
 * AS CATEGORIAS NUMA LINHA SÓ, QUE ANDA DE LADO.
 *
 * Com setenta categorias (uma farmácia), os botões em várias linhas comiam
 * metade da altura do catálogo — ficavam seis linhas de botões por cima de
 * uma fila e meia de artigos. Voltam a ser o carrossel do balcão de sempre:
 *
 * - uma linha, que se arrasta com o dedo, anda com a roda do rato e com as
 *   setas das pontas (que só aparecem quando há mais para esse lado);
 * - «Todos» fica sempre à mão, à esquerda, fora do que anda;
 * - «Todas» abre o painel com as categorias todas em grelha e uma procura —
 *   é por onde se chega à septuagésima sem arrastar a lista inteira; e é lá
 *   que vivem as vazias;
 * - a escolhida vem sempre para a vista.
 */
function FaixaDeCategorias({
    categorias,
    vazias,
    todas,
    porTodas,
    categoria,
    porCategoria,
}: {
    categorias: CategoriaDoPos[];
    vazias: number;
    todas: boolean;
    porTodas: (v: boolean) => void;
    categoria: string | null;
    porCategoria: (v: string | null) => void;
}) {
    const faixa = useRef<HTMLDivElement>(null);
    const [pontas, porPontas] = useState({ esquerda: false, direita: false });
    const [painel, porPainel] = useState(false);
    const [filtro, porFiltro] = useState('');

    const medir = useCallback(() => {
        const el = faixa.current;
        if (!el) return;
        porPontas({
            esquerda: el.scrollLeft > 4,
            direita: el.scrollLeft + el.clientWidth < el.scrollWidth - 4,
        });
    }, []);

    useEffect(() => {
        medir();
        const el = faixa.current;
        if (!el || typeof ResizeObserver === 'undefined') return;
        const observador = new ResizeObserver(medir);
        observador.observe(el);
        return () => observador.disconnect();
    }, [medir, categorias]);

    // A roda do rato anda para os lados: num rato sem roda horizontal era a
    // única forma de chegar ao fim sem ir às setas.
    useEffect(() => {
        const el = faixa.current;
        if (!el) return;
        const rodar = (e: WheelEvent) => {
            if (Math.abs(e.deltaY) > Math.abs(e.deltaX) && el.scrollWidth > el.clientWidth) {
                e.preventDefault();
                el.scrollLeft += e.deltaY;
            }
        };
        el.addEventListener('wheel', rodar, { passive: false });
        return () => el.removeEventListener('wheel', rodar);
    }, []);

    // A escolhida (também a que veio do painel) vem para a vista.
    useEffect(() => {
        faixa.current
            ?.querySelector<HTMLElement>('[aria-pressed="true"]')
            ?.scrollIntoView?.({ block: 'nearest', inline: 'center', behavior: 'smooth' });
    }, [categoria]);

    const andar = (sentido: 1 | -1) => {
        const el = faixa.current;
        el?.scrollBy?.({ left: sentido * el.clientWidth * 0.75, behavior: 'smooth' });
    };

    const escolher = (chave: string | null) => {
        porCategoria(chave);
        porPainel(false);
        porFiltro('');
    };

    const termo = filtro.trim().toLocaleLowerCase('pt');
    const noPainel = termo === ''
        ? categorias
        : categorias.filter((c) => c.nome.toLocaleLowerCase('pt').normalize('NFD').replace(/\p{Diacritic}/gu, '')
            .includes(termo.normalize('NFD').replace(/\p{Diacritic}/gu, '')));

    return (
        <>
            <div className="mt-2.5 flex items-center gap-1.5">
                <Chip activo={categoria === null} onClick={() => escolher(null)}>
                    <i className="fas fa-border-all mr-1.5" aria-hidden="true" />
                    {t('Todos')}
                </Chip>

                <div className="relative min-w-0 flex-1">
                    <div
                        ref={faixa}
                        onScroll={medir}
                        role="group"
                        aria-label={t('Categorias')}
                        className="flex gap-1.5 overflow-x-auto scroll-smooth py-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                        {categorias.map((c) => (
                            <Chip key={c.id} activo={categoria === chaveDaCategoria(c)} onClick={() => escolher(chaveDaCategoria(c))}>
                                {c.nome}
                                <Contagem activo={categoria === chaveDaCategoria(c)} n={c.artigos} />
                            </Chip>
                        ))}
                    </div>

                    {pontas.esquerda && <SetaDaFaixa lado="esquerda" onClick={() => andar(-1)} />}
                    {pontas.direita && <SetaDaFaixa lado="direita" onClick={() => andar(1)} />}
                </div>

                <button
                    type="button"
                    onClick={() => porPainel((v) => !v)}
                    aria-expanded={painel}
                    className={cls(
                        'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap border px-3 py-1.5 text-xs font-semibold transition-all duration-200 active:scale-95',
                        painel
                            ? 'border-indigo-300 bg-indigo-50 text-indigo-700'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-600',
                        RAIO,
                        FOCO,
                    )}
                >
                    <i className={cls('fas transition-transform duration-200', painel ? 'fa-xmark rotate-90' : 'fa-table-cells-large')} aria-hidden="true" />
                    {t('Todas')}
                    <span className="rounded-full bg-slate-100 px-1.5 text-[10px] tabular-nums text-slate-500">{categorias.length}</span>
                </button>
            </div>

            {painel && (
                <div
                    className={cls('animate-fade-in mt-2 border border-slate-200 bg-white p-3 shadow-lg', RAIO)}
                    onKeyDown={(e) => e.key === 'Escape' && porPainel(false)}
                >
                    <div className="mb-2.5 flex flex-wrap items-center gap-2">
                        <div className="relative min-w-[12rem] flex-1">
                            <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true" />
                            <input
                                type="search"
                                value={filtro}
                                onChange={(e) => porFiltro(e.target.value)}
                                // Dentro do formulário da procura: o Enter escolhia
                                // nada e ia procurar artigos. Escolhe a primeira.
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        if (noPainel[0]) escolher(chaveDaCategoria(noPainel[0]));
                                    }
                                }}
                                placeholder={t('Procurar categoria…')}
                                aria-label={t('Procurar categoria')}
                                autoFocus
                                className={cls('w-full border border-slate-300 bg-white py-1.5 pl-8 pr-3 text-sm', RAIO, FOCO)}
                            />
                        </div>

                        {vazias > 0 && (
                            <button
                                type="button"
                                onClick={() => porTodas(!todas)}
                                className={cls('px-2 py-1 text-xs font-semibold text-slate-500 hover:text-indigo-600', FOCO, RAIO)}
                            >
                                <i className={cls('fas mr-1', todas ? 'fa-eye-slash' : 'fa-eye')} aria-hidden="true" />
                                {todas ? t('esconder as :n vazias', { n: vazias }) : t('ver as :n vazias', { n: vazias })}
                            </button>
                        )}
                    </div>

                    {noPainel.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-400">{t('Nenhuma categoria com «:procura».', { procura: filtro })}</p>
                    ) : (
                        <div className="grid max-h-64 grid-cols-2 gap-1.5 overflow-y-auto pr-1 sm:grid-cols-3 xl:grid-cols-4">
                            {noPainel.map((c) => {
                                const activa = categoria === chaveDaCategoria(c);

                                return (
                                    <button
                                        key={c.id}
                                        type="button"
                                        onClick={() => escolher(chaveDaCategoria(c))}
                                        aria-pressed={activa}
                                        title={c.nome}
                                        className={cls(
                                            'flex items-center justify-between gap-2 border px-2.5 py-2 text-left text-xs font-semibold transition-all duration-150 active:scale-[.98]',
                                            activa
                                                ? 'border-indigo-500 bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow'
                                                : 'border-slate-200 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50/60',
                                            c.artigos === 0 && !activa && 'text-slate-400',
                                            RAIO,
                                            FOCO,
                                        )}
                                    >
                                        <span className="truncate">{c.nome}</span>
                                        <Contagem activo={activa} n={c.artigos} />
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </>
    );
}

function Contagem({ activo, n }: { activo: boolean; n: number }) {
    return (
        <span className={cls('ml-1.5 rounded-full px-1.5 text-[10px] tabular-nums', activo ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-500')}>
            {n}
        </span>
    );
}

function SetaDaFaixa({ lado, onClick }: { lado: 'esquerda' | 'direita'; onClick: () => void }) {
    return (
        <div
            className={cls(
                'pointer-events-none absolute inset-y-0 flex w-12 items-center from-slate-50 via-slate-50/90 to-transparent',
                lado === 'esquerda' ? 'left-0 justify-start bg-gradient-to-r' : 'right-0 justify-end bg-gradient-to-l',
            )}
        >
            <button
                type="button"
                onClick={onClick}
                aria-label={lado === 'esquerda' ? t('Categorias anteriores') : t('Mais categorias')}
                className={cls(
                    'pointer-events-auto grid h-7 w-7 place-items-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:scale-110 hover:text-indigo-600',
                    FOCO,
                )}
            >
                <i className={cls('fas text-xs', lado === 'esquerda' ? 'fa-chevron-left' : 'fa-chevron-right')} aria-hidden="true" />
            </button>
        </div>
    );
}

function Chip({ activo, onClick, children }: { activo: boolean; onClick: () => void; children: React.ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={activo}
            className={cls(
                'inline-flex shrink-0 items-center whitespace-nowrap px-3 py-1.5 text-xs font-semibold transition-all duration-200 active:scale-95',
                activo
                    ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md'
                    : 'border border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-600',
                RAIO,
                FOCO,
            )}
        >
            {children}
        </button>
    );
}

function CartaoDeArtigo({
    a,
    i,
    noCarrinho,
    logotipo,
    onClick,
}: {
    a: ArtigoDoPos;
    i: number;
    noCarrinho: number;
    logotipo: string | null;
    onClick: () => void;
}) {
    const semStock = a.stock !== null && a.stock <= 0;
    // Uma imagem gravada cujo ficheiro já não existe: cai para o logótipo, em
    // vez do ícone de imagem partida do browser.
    const [imagemFalhou, porImagemFalhou] = useState(false);

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={semStock}
            /*
             * O CARTÃO TEM DE SE ANUNCIAR.
             *
             * Um botão feito de imagem, nome e preço não tem nome acessível
             * nenhum: quem usa leitor de ecrã ouve «botão», e mais nada. E ao
             * balcão há quem trabalhe com o ecrã por trás — o nome dito em voz
             * alta é o que confirma o artigo antes de ele entrar na factura.
             */
            aria-label={cls(
                a.pergunta_preco
                    ? t('Juntar :artigo — preço perguntado ao balcão', { artigo: a.nome })
                    : t('Juntar :artigo, :preco', { artigo: a.nome, preco: kz(a.preco) }),
                // O que o operador tem de saber antes de carregar — e quem usa
                // leitor de ecrã ouve junto com o nome, não depois.
                a.controlado ? `· ${t('venda controlada')}` : '',
                a.receita ? `· ${t('exige receita médica')}` : '',
            )}
            style={{ '--i': i } as React.CSSProperties}
            className={cls(
                'entra group relative flex flex-col overflow-hidden border bg-white p-2 text-left',
                'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg active:translate-y-0 active:scale-[0.98]',
                semStock
                    ? 'cursor-not-allowed border-slate-200 opacity-50'
                    : 'border-slate-200 hover:border-indigo-400',
                RAIO,
                FOCO,
            )}
        >
            {/* QUANTOS JÁ ESTÃO NO CARRINHO. No ecrã de sempre este crachá dizia
                «1 no» — o «carrinho» não cabia e cortava-se a meio. Aqui é só o
                número, dentro de um círculo, que é o que se lê de relance. */}
            {noCarrinho > 0 && (
                <span
                    className="absolute right-1.5 top-1.5 z-10 grid h-7 w-7 place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-xs font-bold text-white shadow-lg ring-2 ring-white"
                    aria-label={t(':n no carrinho', { n: noCarrinho })}
                >
                    {noCarrinho}
                </span>
            )}

            {/* AS MARCAS DO MEDICAMENTO, à vista antes do clique.
                Um aviso que só aparece depois de o artigo entrar no carrinho
                chega tarde para quem já estava a empacotar. */}
            {(a.controlado || a.receita) && (
                <span className="absolute left-1.5 top-1.5 z-10 flex gap-1">
                    {a.controlado && (
                        <span
                            title={t('Venda controlada — pergunta antes de entrar')}
                            className="grid h-6 w-6 place-items-center rounded-full bg-red-600 text-[10px] font-bold text-white shadow ring-2 ring-white"
                        >
                            <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                        </span>
                    )}
                    {a.receita && (
                        <span
                            title={t('Exige receita médica')}
                            className="grid h-6 w-6 place-items-center rounded-full bg-amber-500 text-[10px] font-bold text-white shadow ring-2 ring-white"
                        >
                            <i className="fas fa-file-prescription" aria-hidden="true" />
                        </span>
                    )}
                </span>
            )}

            <div className={cls('mb-2 aspect-square overflow-hidden bg-slate-100', RAIO)}>
                {a.imagem && !imagemFalhou ? (
                    <img
                        src={a.imagem}
                        alt=""
                        loading="lazy"
                        onError={() => porImagemFalhou(true)}
                        className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                ) : logotipo ? (
                    <div className="relative grid h-full w-full place-items-center bg-gradient-to-br from-slate-50 to-slate-100 p-5">
                        <img
                            src={logotipo}
                            alt=""
                            loading="lazy"
                            className="max-h-full max-w-full object-contain opacity-20 grayscale transition duration-300 group-hover:scale-105 group-hover:opacity-30"
                        />
                        {a.servico && (
                            <i className="fas fa-screwdriver-wrench absolute bottom-2 right-2 text-xs text-slate-300" aria-hidden="true" />
                        )}
                    </div>
                ) : (
                    <div className="grid h-full w-full place-items-center bg-gradient-to-br from-slate-50 to-slate-100">
                        <i
                            className={cls('text-2xl text-slate-300', a.servico ? 'fas fa-screwdriver-wrench' : 'fas fa-box')}
                            aria-hidden="true"
                        />
                    </div>
                )}
            </div>

            <p className="line-clamp-2 text-sm font-bold leading-tight text-slate-800">{a.nome}</p>

            <p className="mt-1 text-base font-bold tabular-nums text-indigo-600">
                {a.pergunta_preco ? (
                    <span className="text-sm text-amber-600">
                        <i className="fas fa-hand-pointer mr-1" aria-hidden="true" />
                        {t('preço ao balcão')}
                    </span>
                ) : (
                    kz(a.preco)
                )}
            </p>

            <div className="mt-1.5">
                {a.duracao ? (
                    <Etiqueta cor="neutra" icone="fa-clock">{t(':n min', { n: a.duracao })}</Etiqueta>
                ) : a.stock === null ? (
                    <Etiqueta cor="neutra" icone="fa-infinity">{t('sem gestão')}</Etiqueta>
                ) : semStock ? (
                    <Etiqueta cor="perigo" icone="fa-ban">{t('esgotado')}</Etiqueta>
                ) : (
                    <Etiqueta cor={a.stock <= 5 ? 'aviso' : 'bom'} icone="fa-cubes">
                        {String(a.stock)}
                    </Etiqueta>
                )}
            </div>
        </button>
    );
}

function Carrinho({
    linhas,
    cliente,
    subtotal,
    desconto,
    porDesconto,
    descontoTipo,
    porDescontoTipo,
    descontoValor,
    base,
    imposto,
    rotuloDoImposto,
    aPagar,
    aoMudarQuantidade,
    aoTirar,
    aoLimpar,
    aoEscolherCliente,
    aoPagar,
    podeVender,
}: {
    linhas: LinhaDoCarrinho[];
    cliente: ClienteDoPos | null;
    subtotal: number;
    desconto: string;
    porDesconto: (v: string) => void;
    descontoTipo: 'percentagem' | 'valor';
    porDescontoTipo: (v: 'percentagem' | 'valor') => void;
    descontoValor: number;
    base: number;
    imposto: number;
    rotuloDoImposto: string;
    aPagar: number;
    aoMudarQuantidade: (id: number, delta: number) => void;
    aoTirar: (id: number) => void;
    aoLimpar: () => void;
    aoEscolherCliente: () => void;
    aoPagar: () => void;
    podeVender: boolean;
}) {
    const pecas = linhas.reduce((s, l) => s + l.quantidade, 0);

    return (
        <aside className={cls('flex min-h-0 flex-col overflow-hidden border border-slate-200 bg-white shadow-sm', RAIO)}>
            {/* O CLIENTE. A linha inteira é o botão: ao balcão acerta-se com o
                dedo, e o lápis sozinho era um alvo de 12 pixels. */}
            <button
                type="button"
                onClick={aoEscolherCliente}
                className={cls(
                    'flex items-center justify-between gap-3 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white px-4 py-3 text-left',
                    'transition-colors duration-200 hover:bg-indigo-50',
                    FOCO,
                )}
            >
                <span className="flex min-w-0 items-center gap-3">
                    <span className="grid h-9 w-9 flex-none place-items-center rounded-full bg-indigo-100 text-indigo-600">
                        <i className="fas fa-user" aria-hidden="true" />
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-bold text-slate-800">
                            {cliente?.nome ?? t('Consumidor Final')}
                        </span>
                        <span className="block truncate text-xs text-slate-500">
                            {cliente?.nif ? `NIF: ${cliente.nif}` : t('sem NIF — carregue para escolher (F4)')}
                        </span>
                    </span>
                </span>
                <i className="fas fa-pen-to-square flex-none text-slate-400" aria-hidden="true" />
            </button>

            {/* AS LINHAS. É a parte que cresce; tudo o resto está preso. */}
            <div className="flex-1 overflow-y-auto">
                {linhas.length === 0 ? (
                    <div className="flex h-full flex-col items-center justify-center px-6 py-16 text-center">
                        <div className="mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                            <i className="fas fa-basket-shopping text-3xl text-slate-300" aria-hidden="true" />
                        </div>
                        <p className="font-semibold text-slate-500">{t('Carrinho vazio')}</p>
                        <p className="mt-1 text-sm text-slate-400">{t('Carregue num artigo ou leia o código de barras.')}</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {linhas.map((l, i) => (
                            <li key={l.id} style={{ '--i': i } as React.CSSProperties} className="entra p-3">
                                <div className="flex items-start justify-between gap-2">
                                    <p className="min-w-0 flex-1 text-sm font-bold leading-tight text-slate-800">{l.nome}</p>
                                    <button
                                        type="button"
                                        onClick={() => aoTirar(l.id)}
                                        title={t('Tirar do carrinho')}
                                        aria-label={t('Tirar :artigo do carrinho', { artigo: l.nome })}
                                        className={cls(
                                            'grid h-8 w-8 flex-none place-items-center text-red-500 transition-all duration-200 hover:scale-110 hover:bg-red-50',
                                            RAIO,
                                            FOCO,
                                        )}
                                    >
                                        <i className="fas fa-trash-can text-sm" aria-hidden="true" />
                                    </button>
                                </div>

                                <div className="mt-2 flex items-center justify-between gap-2">
                                    {/* O contador. Botões de 36px: é o tamanho a
                                        que um dedo acerta sem olhar. */}
                                    <div className={cls('flex items-center overflow-hidden border border-slate-200', RAIO)}>
                                        <button
                                            type="button"
                                            onClick={() => aoMudarQuantidade(l.id, -1)}
                                            aria-label={t('Menos um de :artigo', { artigo: l.nome })}
                                            className={cls('h-9 w-9 text-slate-600 transition-colors hover:bg-slate-100 active:bg-slate-200', FOCO)}
                                        >
                                            <i className="fas fa-minus text-xs" aria-hidden="true" />
                                        </button>
                                        <span className="min-w-[3rem] px-2 text-center text-sm font-bold tabular-nums text-slate-900">
                                            {l.quantidade}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => aoMudarQuantidade(l.id, 1)}
                                            aria-label={t('Mais um de :artigo', { artigo: l.nome })}
                                            className={cls('h-9 w-9 text-indigo-600 transition-colors hover:bg-indigo-50 active:bg-indigo-100', FOCO)}
                                        >
                                            <i className="fas fa-plus text-xs" aria-hidden="true" />
                                        </button>
                                    </div>

                                    <div className="text-right">
                                        <p className="text-sm font-bold tabular-nums text-slate-900">
                                            {kz(l.preco * l.quantidade)}
                                        </p>
                                        {l.quantidade > 1 && (
                                            <p className="text-[11px] tabular-nums text-slate-400">
                                                {kz(l.preco)} {t('cada')}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {/* O RODAPÉ, PRESO AO FUNDO.
                É a correcção que mais vale deste ecrã: a 1366×768 o botão de
                confirmar ficava abaixo da janela e era preciso rolar para
                fechar uma venda. Daqui não sai. */}
            <div className="flex-none border-t border-slate-200 bg-slate-50 p-3">
                {/* O DESCONTO, com rótulo. Antes era um `%` mudo ao lado de uma
                    caixa com «0», e ninguém sabia o que aquilo era. */}
                {/* O interruptor % / Kz tem largura própria e não encolhe: com a
                    caixa a crescer ao lado, o «Kz» ficava cortado a meio. A
                    unidade repete-se dentro da caixa, para se ler o que se
                    está a escrever sem olhar para o botão. */}
                <div className="mb-3">
                    <label htmlFor="desconto-do-balcao" className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <i className="fas fa-tags text-[10px] text-amber-500" aria-hidden="true" />
                        {t('Desconto')}
                    </label>
                    <div className="flex h-10 items-stretch gap-2">
                        <div
                            role="group"
                            aria-label={t('Tipo de desconto')}
                            className={cls('grid shrink-0 grid-cols-2 gap-0.5 border border-slate-200 bg-slate-100 p-0.5', RAIO)}
                        >
                            {(['percentagem', 'valor'] as const).map((tipo) => (
                                <button
                                    key={tipo}
                                    type="button"
                                    onClick={() => porDescontoTipo(tipo)}
                                    aria-pressed={descontoTipo === tipo}
                                    title={tipo === 'percentagem' ? t('Desconto em percentagem') : t('Desconto em kwanzas')}
                                    className={cls(
                                        'grid w-11 place-items-center rounded-lg text-sm font-bold transition-all duration-200',
                                        descontoTipo === tipo
                                            ? 'bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-sm'
                                            : 'text-slate-500 hover:bg-white hover:text-indigo-600',
                                        FOCO,
                                    )}
                                >
                                    {tipo === 'percentagem' ? <i className="fas fa-percent text-xs" aria-hidden="true" /> : 'Kz'}
                                </button>
                            ))}
                        </div>
                        <div className="relative min-w-0 flex-1">
                            <input
                                id="desconto-do-balcao"
                                type="text"
                                inputMode="decimal"
                                value={desconto}
                                onChange={(e) => porDesconto(e.target.value)}
                                placeholder="0"
                                aria-label={t('Valor do desconto')}
                                className={cls(
                                    'h-full w-full border border-slate-200 bg-white pl-3 pr-10 text-right text-sm font-semibold tabular-nums',
                                    'placeholder:text-slate-300 focus:border-indigo-400',
                                    RAIO,
                                    FOCO,
                                )}
                            />
                            <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-bold text-slate-400">
                                {descontoTipo === 'percentagem' ? '%' : 'Kz'}
                            </span>
                        </div>
                    </div>
                </div>

                <dl className="mb-3 space-y-1 text-sm">
                    <div className="flex justify-between">
                        <dt className="text-slate-500">{t('Subtotal')}</dt>
                        <dd className="font-semibold tabular-nums text-slate-700">{kz(subtotal)}</dd>
                    </div>
                    {descontoValor > 0 && (
                        <div className="flex justify-between text-amber-600">
                            <dt>{t('Desconto')}</dt>
                            <dd className="font-semibold tabular-nums">− {kz(descontoValor)}</dd>
                        </div>
                    )}
                    {/* O IMPOSTO À VISTA, como no balcão de sempre. Quando não há
                        nenhum diz-se ISENTO por extenso: calar-se deixava o
                        operador sem saber se era isento ou se o ecrã falhou. */}
                    {imposto > 0 ? (
                        <div className="flex justify-between text-blue-600">
                            <dt>{rotuloDoImposto}</dt>
                            <dd className="font-semibold tabular-nums">+ {kz(imposto)}</dd>
                        </div>
                    ) : (
                        linhas.length > 0 && (
                            <div className="flex justify-between text-slate-400">
                                <dt>{t('IVA')}</dt>
                                <dd className="font-semibold">{t('Isento')}</dd>
                            </div>
                        )
                    )}
                    <div className="flex items-baseline justify-between border-t border-slate-200 pt-2">
                        <dt className="font-bold text-slate-800">{t('A pagar')}</dt>
                        <dd className="text-2xl font-bold tabular-nums text-indigo-700">{kz(aPagar)}</dd>
                    </div>
                </dl>

                <p className="mb-3 text-center text-[11px] text-slate-400 [@media(max-height:820px)]:hidden">
                    {pecas > 0 ? t(':n peças no carrinho', { n: pecas }) : t('Carrinho vazio')}
                </p>

                <div className="flex gap-2">
                    <Botao
                        cor="perigo"
                        tom="suave"
                        icone="fa-trash"
                        onClick={aoLimpar}
                        disabled={linhas.length === 0}
                        aria-label={t('Limpar o carrinho')}
                    />
                    <button
                        type="button"
                        onClick={aoPagar}
                        disabled={linhas.length === 0 || !podeVender}
                        className={cls(
                            'flex flex-1 items-center justify-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3.5',
                            'text-base font-bold text-white shadow-lg transition-all duration-200',
                            'hover:-translate-y-0.5 hover:shadow-xl active:translate-y-0 active:scale-[0.99]',
                            'disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300 disabled:shadow-none disabled:hover:translate-y-0',
                            RAIO,
                            FOCO,
                        )}
                    >
                        <i className="fas fa-circle-check text-lg" aria-hidden="true" />
                        {t('Finalizar Venda')}
                        <kbd className="ml-1 hidden rounded bg-white/20 px-1.5 py-0.5 font-mono text-[10px] md:inline">F9</kbd>
                    </button>
                </div>
            </div>
        </aside>
    );
}

/** Verdadeiro enquanto a janela tiver pelo menos `px` de largura. */
function useLarguraMinima(px: number): boolean {
    const consulta = `(min-width: ${px}px)`;
    const [cumpre, porCumpre] = useState(() => typeof window === 'undefined' || !window.matchMedia || window.matchMedia(consulta).matches);

    useEffect(() => {
        if (!window.matchMedia) return;
        const mq = window.matchMedia(consulta);
        const mudar = () => porCumpre(mq.matches);
        mudar();
        mq.addEventListener?.('change', mudar);

        return () => mq.removeEventListener?.('change', mudar);
    }, [consulta]);

    return cumpre;
}

/**
 * A BARRA DO CARRINHO NO TELEMÓVEL — presa em baixo, sempre à vista.
 *
 * Quantas peças, quanto se paga, e os dois gestos que importam: ver o
 * carrinho e fechar a venda. O «Finalizar» não depende de rolar nem de abrir
 * nada, que é a regra do balcão em qualquer ecrã.
 */
function BarraDoCarrinho({ pecas, total, podeVender, aoAbrir, aoPagar }: {
    pecas: number;
    total: number;
    podeVender: boolean;
    aoAbrir: () => void;
    aoPagar: () => void;
}) {
    return (
        <div
            className="fixed inset-x-0 bottom-0 z-30 flex items-center gap-2 border-t border-slate-200 bg-white/95 px-3 py-2.5 shadow-[0_-8px_24px_-12px_rgba(15,23,42,.35)] backdrop-blur"
            style={{ paddingBottom: 'max(0.625rem, env(safe-area-inset-bottom))' }}
        >
            <button
                type="button"
                onClick={aoAbrir}
                aria-label={t('Ver o carrinho')}
                className={cls('relative flex min-w-0 flex-1 items-center gap-3 border border-slate-200 bg-slate-50 px-3 py-2 text-left transition active:scale-[.98]', RAIO, FOCO)}
            >
                <span className="relative grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-indigo-100 text-indigo-700">
                    <i className="fas fa-basket-shopping" aria-hidden="true" />
                    {pecas > 0 && (
                        <span className="absolute -right-1.5 -top-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white">{pecas}</span>
                    )}
                </span>
                <span className="min-w-0">
                    <span className="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">{t('A pagar')}</span>
                    <span className="block truncate text-lg font-bold tabular-nums text-indigo-700">{kz(total)}</span>
                </span>
            </button>
            <button
                type="button"
                onClick={aoPagar}
                disabled={pecas === 0 || !podeVender}
                className={cls(
                    'inline-flex h-14 shrink-0 items-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 text-sm font-bold text-white shadow-lg transition active:scale-95',
                    'disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300 disabled:shadow-none',
                    RAIO,
                    FOCO,
                )}
            >
                <i className="fas fa-circle-check" aria-hidden="true" />
                {t('Finalizar')}
            </button>
        </div>
    );
}