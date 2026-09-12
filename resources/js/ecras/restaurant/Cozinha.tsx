import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { restaurante, type Bilhete } from '@/api/restaurant';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * O ECRÃ DA COZINHA.
 *
 * O REFRESCO É POR PULSO E NÃO POR RELÓGIO. O ecrã em Blade refazia-se de
 * quinze em quinze segundos, estivesse ou não a acontecer alguma coisa — duas
 * contas más ao mesmo tempo: um prato podia esperar quinze segundos para ser
 * visto, e uma cozinha parada às três da tarde refazia na mesma a consulta
 * grande a cada quinze segundos.
 *
 * Aqui pergunta-se de três em três segundos a pergunta barata — «mudou alguma
 * coisa?», uma agregação sobre uma tabela — e só se vai buscar a lista quando
 * a resposta muda. O atraso cai para três segundos E o trabalho cai a quase
 * nada quando não há nada a acontecer.
 *
 * NÃO SE USA WEBSOCKET de propósito: esta aplicação também corre em casa do
 * cliente, sem internet e sem servidor de eventos.
 */

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom'> = {
    queued: 'neutra',
    accepted: 'primaria',
    preparing: 'aviso',
    ready: 'bom',
};

const COLUNAS = ['queued', 'accepted', 'preparing', 'ready'] as const;

const ICONE_DA_COLUNA: Record<string, string> = {
    queued: 'fa-inbox',
    accepted: 'fa-hand',
    preparing: 'fa-fire-burner',
    ready: 'fa-bell-concierge',
};

/** Ao fim de tanto tempo, um bilhete deixa de ser «a caminho» e passa a ser um problema. */
const ATRASO_AMARELO = 10;
const ATRASO_VERMELHO = 20;

/** A morada do talão de um bilhete, e a da segunda via. */
const talao = (id: number, copia = false) =>
    `/restaurant/kitchen/tickets/${id}/print${copia ? '?copy=1' : ''}`;

const CHAVE_DA_IMPRESSAO = 'kds-imprimir-sozinho';

/**
 * IMPRIMIR NUM IFRAME ESCONDIDO.
 *
 * O `print()` abre o diálogo do sistema — silencioso só quando o Chrome do
 * posto corre em modo quiosque (`--kiosk-printing`), que é o normal numa
 * impressora térmica de cozinha. Fora disso aparece o diálogo, que ainda assim
 * poupa o abrir-a-página-e-carregar-em-imprimir.
 */
function imprimirEmSilencio(url: string): void {
    const iframe = document.createElement('iframe');

    iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;visibility:hidden;';
    iframe.src = url;

    iframe.onload = () => {
        try {
            iframe.contentWindow?.print();
        } catch {
            /* o browser recusou — o talão continua a abrir-se à mão */
        }

        // Tempo de sobra para o spooler receber antes de deitar fora.
        setTimeout(() => iframe.remove(), 20000);
    };

    document.body.appendChild(iframe);
}

export default function Cozinha() {
    const cache = useQueryClient();

    const [posto, porPosto] = useState<number | ''>('');
    const [erro, porErro] = useState<unknown>(null);
    const pulsoAnterior = useRef<string | null>(null);

    /**
     * A IMPRESSÃO AUTOMÁTICA É POR APARELHO, guardada no próprio: a impressora
     * está num posto só, e a definição da casa é apenas o valor de arranque.
     * Ligada em todos os ecrãs, cada um imprimia a sua cópia do mesmo talão.
     */
    const [imprimirSozinho, porImprimirSozinho] = useState<boolean | null>(null);

    /**
     * O que já se viu não se volta a imprimir — e, no PRIMEIRO carregamento,
     * dá-se a fila inteira como vista: ligar a impressão não pode despejar
     * vinte talões de uma vez na impressora.
     */
    const vistos = useRef<Set<number> | null>(null);

    const opcoes = useQuery({
        queryKey: ['restaurante', 'cozinha', 'opcoes'],
        queryFn: restaurante.cozinha.opcoes,
        staleTime: 5 * 60_000,
    });

    const bilhetes = useQuery({
        queryKey: ['restaurante', 'cozinha', 'bilhetes', posto],
        queryFn: () => restaurante.cozinha.bilhetes(posto),
    });

    /*
     * O PULSO. A rota vive sob `/restaurant/` e não sob a API dos ecrãs — é a
     * que a cozinha sempre usou, e mudá-la de sítio partia o único caminho
     * barato que ela tem. O cliente da API trata do resto (sessão, CSRF, o
     * erro que chega em HTML).
     */
    useEffect(() => {
        let vivo = true;

        const bater = async () => {
            // Com o separador escondido não vale a pena: ninguém está a olhar,
            // e um ecrã de cozinha fica aberto o dia todo.
            if (document.hidden) return;

            try {
                const { pulso } = await restaurante.cozinha.pulso(posto);

                if (!vivo) return;

                if (pulsoAnterior.current !== null && pulsoAnterior.current !== pulso) {
                    void cache.invalidateQueries({ queryKey: ['restaurante', 'cozinha', 'bilhetes'] });
                }

                pulsoAnterior.current = pulso;
            } catch {
                /* a rede caiu por um instante; o próximo batimento tenta outra vez */
            }
        };

        void bater();

        const id = setInterval(bater, 3000);

        // Voltar ao ecrã depois de ele ter estado escondido é o momento em que
        // é mais provável ter mudado alguma coisa — pergunta-se logo.
        const aoVoltar = () => { if (!document.hidden) void bater(); };

        document.addEventListener('visibilitychange', aoVoltar);

        return () => {
            vivo = false;
            clearInterval(id);
            document.removeEventListener('visibilitychange', aoVoltar);
        };
    }, [posto, cache]);

    // O valor de arranque é o da casa; a escolha deste aparelho manda por cima.
    useEffect(() => {
        if (imprimirSozinho !== null || !opcoes.data) return;

        let guardado: string | null = null;

        try {
            guardado = localStorage.getItem(CHAVE_DA_IMPRESSAO);
        } catch {
            /* o browser pode recusar o armazenamento; fica o valor da casa */
        }

        porImprimirSozinho(guardado === null ? opcoes.data.impressao_automatica : guardado === '1');
    }, [opcoes.data, imprimirSozinho]);

    const escolherImpressao = (v: boolean) => {
        porImprimirSozinho(v);

        try {
            localStorage.setItem(CHAVE_DA_IMPRESSAO, v ? '1' : '0');
        } catch {
            /* sem armazenamento, vale só para esta sessão */
        }
    };

    /* O que apareceu desde a última vista imprime-se — um de cada vez. */
    useEffect(() => {
        const lista = bilhetes.data?.data;

        if (!lista) return;

        const primeiraVez = vistos.current === null;

        if (primeiraVez) vistos.current = new Set();

        const novos = lista.filter((b) => !vistos.current!.has(b.id));

        for (const b of lista) vistos.current!.add(b.id);

        if (primeiraVez || !imprimirSozinho) return;

        novos.forEach((b, i) => setTimeout(() => imprimirEmSilencio(talao(b.id)), i * 1500));
    }, [bilhetes.data, imprimirSozinho]);

    const avancar = useMutation({
        mutationFn: (id: number) => restaurante.cozinha.avancar(id),
        onSuccess: () => {
            porErro(null);
            void cache.invalidateQueries({ queryKey: ['restaurante', 'cozinha', 'bilhetes'] });
        },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const lista = bilhetes.data?.data ?? [];

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Cozinha')}
                subtitulo={t('Actualiza-se sozinho — de três em três segundos')}
                icone="fa-fire-burner"
                cor="perigo"
                accoes={
                    <a href="/restaurant/orders" className={ACCAO_DA_FAIXA}>
                        <i className="fas fa-receipt" aria-hidden="true" />
                        {t('Comandas')}
                    </a>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-ticket">
                        {t(':n bilhetes em curso', { n: String(lista.length) })}
                    </EstadoNaFaixa>

                    {/* POR APARELHO e não pela casa: a impressora está num
                        posto só, e ligada em todos os ecrãs cada um imprimia a
                        sua cópia do mesmo talão. */}
                    <label
                        className={cls(
                            'inline-flex cursor-pointer items-center gap-2 bg-white/20 px-3 py-1.5 text-sm font-semibold text-white backdrop-blur-sm',
                            RAIO,
                        )}
                    >
                        <input
                            type="checkbox"
                            checked={!!imprimirSozinho}
                            onChange={(e) => escolherImpressao(e.target.checked)}
                            className="h-4 w-4 rounded border-white/40 bg-white/20 text-red-600 focus:ring-white/70"
                        />
                        <i className="fas fa-print" aria-hidden="true" />
                        {t('Imprimir talões novos neste aparelho')}
                    </label>

                    {o.postos.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            <BotaoDePosto activa={posto === ''} onClick={() => porPosto('')}>
                                {t('Todos os postos')}
                            </BotaoDePosto>
                            {o.postos.map((p) => (
                                <BotaoDePosto
                                    key={p.valor}
                                    activa={posto === Number(p.valor)}
                                    onClick={() => porPosto(Number(p.valor))}
                                >
                                    {p.rotulo}
                                </BotaoDePosto>
                            ))}
                        </div>
                    )}
                </div>
            </Faixa>

            <AvisoDeErro erro={erro} />

            {bilhetes.isPending ? (
                <Carregando linhas={6} />
            ) : lista.length === 0 ? (
                <div className={CARTAO}>
                    <SemNada
                        icone="fa-bell-concierge"
                        titulo={t('Cozinha em dia')}
                        frase={t('Não há nada à espera. Os bilhetes aparecem aqui assim que as comandas forem enviadas.')}
                    />
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {COLUNAS.map((coluna) => {
                        const daColuna = lista.filter((b) => b.estado === coluna);

                        return (
                            <section key={coluna} className="flex min-w-0 flex-col gap-2">
                                <header className="flex items-center justify-between gap-2 rounded-xl bg-slate-100 px-3 py-2">
                                    <span className="flex items-center gap-2 text-sm font-bold text-slate-700">
                                        <i className={`fas ${ICONE_DA_COLUNA[coluna]}`} aria-hidden="true" />
                                        {o.estados.find((e) => e.valor === coluna)?.rotulo ?? coluna}
                                    </span>
                                    <span className="rounded-full bg-white px-2 py-0.5 text-xs font-bold tabular-nums text-slate-600">
                                        {daColuna.length}
                                    </span>
                                </header>

                                {daColuna.length === 0 ? (
                                    <p className="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">
                                        {t('Nada aqui.')}
                                    </p>
                                ) : (
                                    daColuna.map((b) => (
                                        <CartaoDoBilhete
                                            key={b.id}
                                            bilhete={b}
                                            podeGerir={o.permissoes.pode_gerir}
                                            aTrabalhar={avancar.isPending && avancar.variables === b.id}
                                            aoAvancar={() => avancar.mutate(b.id)}
                                        />
                                    ))
                                )}
                            </section>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function BotaoDePosto({ activa, onClick, children }: { activa: boolean; onClick: () => void; children: React.ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cls(
                'px-3 py-1.5 text-xs font-semibold transition-all duration-200', RAIO,
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70',
                activa ? 'bg-white text-red-700 shadow-md' : 'bg-white/20 text-white hover:bg-white/30',
            )}
        >
            {children}
        </button>
    );
}

function CartaoDoBilhete({
    bilhete, podeGerir, aTrabalhar, aoAvancar,
}: {
    bilhete: Bilhete; podeGerir: boolean; aTrabalhar: boolean; aoAvancar: () => void;
}) {
    const atrasado = bilhete.minutos >= ATRASO_VERMELHO;
    const aDemorar = bilhete.minutos >= ATRASO_AMARELO;

    return (
        <article
            className={cls(
                'entra flex flex-col gap-2 border-2 bg-white p-3 shadow-sm transition',
                RAIO,
                atrasado ? 'border-red-300' : aDemorar ? 'border-amber-300' : 'border-slate-200',
            )}
        >
            <header className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate text-sm font-bold text-slate-900">
                        {bilhete.mesa ?? bilhete.comanda ?? bilhete.numero}
                    </p>
                    <p className="truncate text-xs text-slate-500">
                        {bilhete.comanda}
                        {bilhete.posto && ` · ${bilhete.posto}`}
                    </p>
                </div>

                {/* O tempo à espera é o que decide a ordem de trabalho — e o
                    que se vê de longe, sem ler a hora e fazer a conta. */}
                <span
                    className={cls(
                        'flex-none rounded-lg px-2 py-1 text-xs font-bold tabular-nums',
                        atrasado ? 'animate-pulse bg-red-100 text-red-700'
                            : aDemorar ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600',
                    )}
                >
                    <i className="fas fa-clock mr-1" aria-hidden="true" />
                    {bilhete.minutos}′
                </span>
            </header>

            <ul className="space-y-1">
                {bilhete.artigos.map((a) => (
                    <li key={a.id} className="text-sm">
                        <span className="font-semibold tabular-nums text-slate-900">{a.quantidade}×</span>{' '}
                        <span className="text-slate-800">{a.nome}</span>
                        {a.observacoes && (
                            <span className="ml-1 block text-xs font-medium italic text-amber-700">
                                <i className="fas fa-comment-dots mr-1" aria-hidden="true" />« {a.observacoes} »
                            </span>
                        )}
                    </li>
                ))}
            </ul>

            <div className="flex gap-2">
                <a
                    href={talao(bilhete.id)}
                    target="_blank"
                    rel="noopener"
                    className={cls(
                        'flex-1 rounded-lg bg-slate-100 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-slate-200',
                        FOCO,
                    )}
                >
                    <i className="fas fa-print mr-1" aria-hidden="true" />
                    {t('Imprimir')}
                </a>
                <a
                    href={talao(bilhete.id, true)}
                    target="_blank"
                    rel="noopener"
                    className={cls(
                        'flex-1 rounded-lg bg-slate-100 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-slate-200',
                        FOCO,
                    )}
                >
                    <i className="fas fa-copy mr-1" aria-hidden="true" />
                    {t('Segunda via')}
                </a>
            </div>

            <footer className="flex items-center justify-between gap-2 pt-1">
                <Etiqueta cor={COR_DO_ESTADO[bilhete.estado] ?? 'neutra'} ponto>{bilhete.estado_rotulo}</Etiqueta>

                {podeGerir && bilhete.seguinte && (
                    <Botao
                        altura="pequeno"
                        cor={bilhete.estado === 'preparing' ? 'bom' : 'primaria'}
                        tom="solida"
                        icone="fa-arrow-right"
                        aTrabalhar={aTrabalhar}
                        onClick={aoAvancar}
                    >
                        {bilhete.seguinte_rotulo}
                    </Botao>
                )}
            </footer>
        </article>
    );
}
