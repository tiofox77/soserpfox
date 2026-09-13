import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query';

import { pos, type ArtigoDoPos, type ClienteDoPos, type VendaFechada } from '@/api/pos';
import { ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
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
function chaveDoCarrinho(dono: { empresa: number; operador: number }): string {
    return `pos_carrinho_t${dono.empresa}_u${dono.operador}`;
}

export default function PontoDeVenda() {
    const opcoes = useQuery({ queryKey: ['pos', 'opcoes'], queryFn: pos.opcoes, staleTime: 60_000 });

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
    const [categoria, porCategoria] = useState<number | null>(null);
    const [todasAsCategorias, porTodasAsCategorias] = useState(false);
    const [linhas, porLinhas] = useState<LinhaDoCarrinho[]>([]);
    const [cliente, porCliente] = useState<ClienteDoPos | null>(null);
    const [descontoTipo, porDescontoTipo] = useState<'percentagem' | 'valor'>('percentagem');
    const [desconto, porDesconto] = useState('');
    const [aviso, porAviso] = useState('');
    const [recado, porRecado] = useRecadoNoCanto('');

    // Os modais.
    const [pagar, porPagar] = useState(false);
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

            // 16px de folga em baixo, para o balcão não colar ao rodapé.
            porAltura(Math.max(360, window.innerHeight - topo - 16));
        }

        medir();
        window.addEventListener('resize', medir);

        return () => window.removeEventListener('resize', medir);
    }, []);

    /* ─── O espelho do carrinho ───────────────────────────────────────────
     *
     * O carrinho vive em localStorage e volta ao recarregar. Não é conforto:
     * é o que impede uma sessão expirada, ou um F5 por engano, de deitar fora
     * o que o cliente já pôs em cima do balcão.
     */
    useEffect(() => {
        try {
            const guardado = localStorage.getItem(chaveDoCarrinho(o.dono_do_carrinho));

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
                localStorage.setItem(chaveDoCarrinho(o.dono_do_carrinho), JSON.stringify({ ts: Date.now(), itens: linhas }));
            } else {
                localStorage.removeItem(chaveDoCarrinho(o.dono_do_carrinho));
            }
        } catch {
            /* idem */
        }
    }, [linhas]);

    /* ─── Os artigos ──────────────────────────────────────────────────── */

    const artigos = useQuery({
        queryKey: ['pos', 'artigos', procura, categoria, o.armazem.id],
        queryFn: () => pos.artigos({ procura, categoria, armazem: o.armazem.id }),
        placeholderData: keepPreviousData,
        staleTime: 15_000,
    });

    const lista = artigos.data?.data ?? [];

    /*
     * AS CATEGORIAS QUE TÊM ALGUMA COISA.
     *
     * São trinta, e vinte e seis estão vazias. No ecrã de sempre empurravam a
     * única com artigos para fora do carrossel — havia que arrastar para a
     * encontrar. As vazias continuam a poder ver-se, mas atrás de um botão.
     */
    const comArtigos = useMemo(() => o.categorias.filter((c) => c.artigos > 0), [o.categorias]);
    const vazias = o.categorias.length - comArtigos.length;
    const categoriasAMostrar = todasAsCategorias ? o.categorias : comArtigos;

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

    const vender = useMutation({
        mutationFn: (corpo: Record<string, unknown>) => pos.vender(corpo),
        onSuccess: (v) => {
            somDoBalcao('venda');
            porPagar(false);
            porVendida(v);
            porLinhas([]);
            porCliente(null);
            porDesconto('');
            porRecado(v.message);
        },
        onError: (e) => porAviso(e instanceof ErroDaApi ? e.message : t('Não foi possível fechar a venda.')),
    });

    return (
        <div className="flex flex-col gap-3">
            {/* A FAIXA DO BALCÃO: quem está, em que turno, de que armazém sai a
                mercadoria. No ecrã de sempre o cabeçalho da página dizia
                «Dashboard — Bem-vindo ao sistema», que num POS não diz nada. */}
            <Faixa turno={o.turno!} armazem={o.armazem} />

            {(aviso || recado) && (
                <Recados aviso={aviso} recado={recado} aoFechar={() => { porAviso(''); porRecado(''); }} />
            )}

            {/* O CORPO: catálogo à esquerda, carrinho à direita, e os dois a
                rolar POR DENTRO. A altura é a que sobra da janela — medida, não
                escrita à mão — para que o botão de fechar a venda esteja
                sempre no mesmo sítio, em qualquer ecrã. */}
            <div
                ref={corpo}
                className="grid min-h-0 gap-3 lg:grid-cols-[1fr_minmax(340px,26rem)]"
                style={altura ? { height: `${altura}px` } : { minHeight: '32rem' }}
            >
                <Catalogo
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
                    artigos={lista}
                    aCarregar={artigos.isFetching}
                    linhas={linhas}
                    aoEscolher={escolher}
                />

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
                    aoMudarQuantidade={mudarQuantidade}
                    aoTirar={tirar}
                    aoLimpar={limpar}
                    aoEscolherCliente={() => porEscolherCliente(true)}
                    aoPagar={() => porPagar(true)}
                    podeVender={o.permissoes.pode_vender}
                />
            </div>

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
                total={base}
                subtotal={subtotal}
                desconto={descontoValor}
                formas={o.formas_de_pagamento}
                montantesRapidos={o.definicoes.montantes_rapidos}
                aTrabalhar={vender.isPending}
                aoConfirmar={(p) =>
                    vender.mutate({
                        // Um por tentativa: é o que torna a venda idempotente.
                        local_uuid: identificadorDaVenda(),
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
                            product_id: l.servico ? null : l.id,
                            product_name: l.nome,
                            quantity: l.quantidade,
                            unit_price: l.preco,
                            is_service: l.servico,
                            unit: l.unidade,
                        })),
                    })
                }
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
                'flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-indigo-600 via-violet-600 to-purple-600 px-5 py-3 text-white shadow-lg',
                RAIO,
            )}
        >
            <div className="flex items-center gap-3">
                <div className="grid h-11 w-11 place-items-center rounded-xl bg-white/20 backdrop-blur-sm">
                    <i className="fas fa-cash-register icon-float text-xl" aria-hidden="true" />
                </div>
                <div>
                    <h1 className="text-lg font-bold leading-tight">{t('Ponto de Venda')}</h1>
                    <p className="text-xs text-indigo-100">
                        {t('Turno :numero', { numero: turno.numero })}
                        {turno.aberto_em && ` · ${t('aberto às :hora', { hora: turno.aberto_em.slice(11, 16) })}`}
                    </p>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-2 text-xs">
                {armazem.nome && (
                    <span className="inline-flex items-center gap-2 rounded-lg bg-white/15 px-3 py-1.5 font-semibold backdrop-blur-sm">
                        <i className="fas fa-warehouse" aria-hidden="true" />
                        {armazem.nome}
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

function Recados({ aviso, recado, aoFechar }: { aviso: string; recado: string; aoFechar: () => void }) {
    const erro = aviso !== '';

    return (
        <div
            role={erro ? 'alert' : 'status'}
            className={cls(
                'flex items-center justify-between gap-3 border px-4 py-2.5 text-sm animate-fade-in',
                erro ? 'border-red-200 bg-red-50 text-red-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900',
                RAIO,
            )}
        >
            <span className="flex items-center gap-2">
                <i className={cls('fas', erro ? 'fa-circle-exclamation' : 'fa-circle-check')} aria-hidden="true" />
                {aviso || recado}
            </span>
            <button type="button" onClick={aoFechar} aria-label={t('Fechar')} className={cls('p-1', FOCO, RAIO)}>
                <i className="fas fa-times" aria-hidden="true" />
            </button>
        </div>
    );
}

function Catalogo({
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
    artigos,
    aCarregar,
    linhas,
    aoEscolher,
}: {
    procura: string;
    porProcura: (v: string) => void;
    caixaDeProcura: React.RefObject<HTMLInputElement | null>;
    aoSubmeter: (e: React.FormEvent) => void;
    categorias: Array<{ id: number; nome: string; artigos: number }>;
    vazias: number;
    todas: boolean;
    porTodas: (v: boolean) => void;
    categoria: number | null;
    porCategoria: (v: number | null) => void;
    artigos: ArtigoDoPos[];
    aCarregar: boolean;
    linhas: LinhaDoCarrinho[];
    aoEscolher: (a: ArtigoDoPos) => void;
}) {
    const noCarrinho = useMemo(() => new Map(linhas.map((l) => [l.id, l.quantidade])), [linhas]);

    return (
        <section className={cls('flex min-h-0 flex-col overflow-hidden border border-slate-200 bg-white shadow-sm', RAIO)}>
            {/* A PROCURA. Grande de propósito: é o campo mais usado do ecrã, e
                é aqui que o leitor de código de barras escreve. */}
            <form onSubmit={aoSubmeter} className="border-b border-slate-200 bg-slate-50 p-3">
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

                {/* AS CATEGORIAS QUE TÊM ARTIGOS. As vazias ficam atrás do botão
                    — eram 26 numa lista de 30, e empurravam a única útil para
                    fora do carrossel. */}
                <div className="mt-2.5 flex flex-wrap items-center gap-1.5">
                    <Chip activo={categoria === null} onClick={() => porCategoria(null)}>
                        <i className="fas fa-border-all mr-1.5" aria-hidden="true" />
                        {t('Todos')}
                    </Chip>

                    {categorias.map((c) => (
                        <Chip key={c.id} activo={categoria === c.id} onClick={() => porCategoria(c.id)}>
                            {c.nome}
                            <span className="ml-1.5 opacity-60">{c.artigos}</span>
                        </Chip>
                    ))}

                    {vazias > 0 && (
                        <button
                            type="button"
                            onClick={() => porTodas(!todas)}
                            className={cls(
                                'px-2.5 py-1.5 text-xs font-semibold text-slate-500 underline-offset-2 hover:text-indigo-600 hover:underline',
                                FOCO,
                                RAIO,
                            )}
                        >
                            {todas
                                ? t('esconder as :n vazias', { n: vazias })
                                : t('ver as :n vazias', { n: vazias })}
                        </button>
                    )}
                </div>
            </form>

            {/* A GRELHA. Cartões maiores do que os de antes: ao balcão acerta-se
                com o dedo, e um alvo pequeno é um artigo errado na factura. */}
            <div className={cls('flex-1 overflow-y-auto p-3', aCarregar && 'opacity-60')}>
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
                    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                        {artigos.map((a, i) => (
                            <CartaoDeArtigo
                                key={a.id}
                                a={a}
                                i={i}
                                noCarrinho={noCarrinho.get(a.id) ?? 0}
                                onClick={() => aoEscolher(a)}
                            />
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}

function Chip({ activo, onClick, children }: { activo: boolean; onClick: () => void; children: React.ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={activo}
            className={cls(
                'px-3 py-1.5 text-xs font-semibold transition-all duration-200 active:scale-95',
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
    onClick,
}: {
    a: ArtigoDoPos;
    i: number;
    noCarrinho: number;
    onClick: () => void;
}) {
    const semStock = a.stock !== null && a.stock <= 0;

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
                {a.imagem ? (
                    <img
                        src={a.imagem}
                        alt=""
                        className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
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
                {a.stock === null ? (
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
                <div className="mb-3">
                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {t('Desconto')}
                    </label>
                    <div className="flex gap-2">
                        <div className={cls('flex overflow-hidden border border-slate-200 bg-white', RAIO)}>
                            {(['percentagem', 'valor'] as const).map((tipo) => (
                                <button
                                    key={tipo}
                                    type="button"
                                    onClick={() => porDescontoTipo(tipo)}
                                    aria-pressed={descontoTipo === tipo}
                                    className={cls(
                                        'px-3 py-2 text-sm font-bold transition-colors',
                                        descontoTipo === tipo ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-50',
                                        FOCO,
                                    )}
                                >
                                    {tipo === 'percentagem' ? '%' : 'Kz'}
                                </button>
                            ))}
                        </div>
                        <input
                            type="text"
                            inputMode="decimal"
                            value={desconto}
                            onChange={(e) => porDesconto(e.target.value)}
                            placeholder="0"
                            aria-label={t('Valor do desconto')}
                            className={cls(
                                'w-full border border-slate-200 bg-white px-3 py-2 text-right text-sm font-semibold tabular-nums',
                                RAIO,
                                FOCO,
                            )}
                        />
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
                    <div className="flex items-baseline justify-between border-t border-slate-200 pt-2">
                        <dt className="font-bold text-slate-800">{t('A pagar')}</dt>
                        <dd className="text-2xl font-bold tabular-nums text-indigo-700">{kz(base)}</dd>
                    </div>
                </dl>

                {/* O IMPOSTO É DO SERVIDOR. Este ecrã mostra a base; o total com
                    imposto sai no modal de pagamento, calculado por quem manda. */}
                <p className="mb-3 text-center text-[11px] text-slate-400">
                    {pecas > 0
                        ? t(':n peças no carrinho · o imposto é somado ao pagar', { n: pecas })
                        : t('o imposto é somado ao pagar')}
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
