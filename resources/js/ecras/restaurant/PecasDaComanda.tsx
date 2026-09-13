import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    restaurante,
    type ArtigoDaCarta,
    type FichaDaComanda,
    type OpcoesDasComandas,
} from '@/api/restaurant';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada } from '@/ui/SemNada';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * AS PEÇAS DA COMANDA, partilhadas pelo balcão e pela lista de comandas.
 *
 * São os dois ecrãs que atendem a mesma comanda com desenhos diferentes: a
 * lista serve para encontrar, o balcão serve para atender. O que fazem À
 * comanda — acrescentar, confirmar, transferir, juntar, anular, fechar — é
 * exactamente o mesmo, e por isso vive aqui uma vez só.
 *
 * Uma segunda cópia disto divergiria ao primeiro botão novo, e teríamos duas
 * regras de conta dividida no mesmo produto.
 */

export const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    draft: 'neutra',
    confirmed: 'primaria',
    in_preparation: 'aviso',
    ready: 'bom',
    served: 'primaria',
    partially_billed: 'aviso',
    billed: 'bom',
    cancelled: 'perigo',
};

export const ICONE_DO_CANAL: Record<string, string> = {
    table: 'fa-chair',
    counter: 'fa-cash-register',
    takeaway: 'fa-bag-shopping',
    delivery: 'fa-motorcycle',
};

/** A cozinha marca cada artigo; a cor diz em que pé está sem se ler. */
const COR_NA_COZINHA: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'neutra',
    queued: 'neutra',
    accepted: 'primaria',
    preparing: 'aviso',
    ready: 'bom',
    served: 'bom',
    voided: 'perigo',
};

const ROTULO_NA_COZINHA: Record<string, string> = {
    pending: 'Por enviar',
    queued: 'Na fila',
    accepted: 'Aceite',
    preparing: 'A preparar',
    ready: 'Pronto',
    served: 'Servido',
    voided: 'Anulado',
};

/** Uma chave por fecho, para o mesmo botão carregado duas vezes não facturar duas. */
function chaveNova(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}-4000-8000-${Math.random().toString(16).slice(2, 14)}`;
}

/* ─── A grelha de artigos ─────────────────────────────────────────────── */

export function GrelhaDeArtigos({
    categorias,
    aoEscolher,
    aoPedirNota,
    accoes,
}: {
    categorias: OpcoesDasComandas['categorias'];
    aoEscolher: (artigo: ArtigoDaCarta) => void;
    /**
     * «Sem cebola», «bem passado» — a observação que sai no bilhete da
     * cozinha. Tem botão próprio no canto do cartão: pô-la no toque normal
     * obrigava a passar por uma janela para acrescentar um refrigerante.
     */
    aoPedirNota?: (artigo: ArtigoDaCarta) => void;
    accoes?: React.ReactNode;
}) {
    const [procura, porProcura] = useState('');
    const [categoria, porCategoria] = useState<number | ''>('');
    const [atrasada, porAtrasada] = useState('');

    // O teclado do balcão escreve depressa; uma consulta por tecla era uma
    // consulta por tecla.
    useEffect(() => {
        const id = setTimeout(() => porAtrasada(procura), 250);

        return () => clearTimeout(id);
    }, [procura]);

    const artigos = useQuery({
        queryKey: ['restaurante', 'artigos', atrasada, categoria],
        queryFn: () => restaurante.comandas.artigos({ procura: atrasada, categoria }),
    });

    return (
        <div className="flex h-full min-h-0 flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative min-w-[12rem] flex-1">
                    <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                        type="search"
                        value={procura}
                        onChange={(e) => porProcura(e.target.value)}
                        placeholder={t('Procurar prato, código ou código de barras…')}
                        aria-label={t('Procurar artigo')}
                        className={cls(entrada, 'pl-9')}
                    />
                </div>
                {accoes}
            </div>

            <div className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1">
                <BotaoDeCategoria activa={categoria === ''} onClick={() => porCategoria('')} icone="fa-border-all">
                    {t('Tudo')}
                </BotaoDeCategoria>
                {categorias.map((c) => (
                    <BotaoDeCategoria
                        key={c.valor}
                        activa={categoria === Number(c.valor)}
                        onClick={() => porCategoria(Number(c.valor))}
                        icone={c.icone}
                        cor={c.cor}
                    >
                        {c.rotulo}
                    </BotaoDeCategoria>
                ))}
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto">
                {artigos.isPending ? (
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                        {Array.from({ length: 8 }).map((_, i) => (
                            <div key={i} className="h-24 animate-pulse rounded-xl bg-slate-100" />
                        ))}
                    </div>
                ) : (artigos.data?.data.length ?? 0) === 0 ? (
                    <SemNada icone="fa-utensils" frase={t('Nenhum artigo encontrado.')} />
                ) : (
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                        {artigos.data?.data.map((a) => (
                            <div key={a.id} className="relative">
                                <button
                                    type="button"
                                    onClick={() => aoEscolher(a)}
                                    className={cls(
                                        'card-hover group flex h-full w-full flex-col justify-between gap-1 border border-slate-200 bg-white p-3 text-left shadow-sm',
                                        RAIO, FOCO, 'hover:border-orange-300',
                                    )}
                                >
                                    <span className="line-clamp-2 pr-6 text-sm font-semibold leading-tight text-slate-800">
                                        {a.nome}
                                    </span>
                                    <span className="flex items-end justify-between gap-2">
                                        <span className="text-base font-bold tabular-nums text-orange-600">
                                            {kz(a.preco)}
                                        </span>
                                        <span className="icon-float grid h-7 w-7 flex-none place-items-center rounded-lg bg-orange-50 text-orange-600">
                                            <i className="fas fa-plus text-xs transition-transform duration-200 group-hover:rotate-90" aria-hidden="true" />
                                        </span>
                                    </span>
                                </button>

                                {aoPedirNota && (
                                    <button
                                        type="button"
                                        onClick={() => aoPedirNota(a)}
                                        title={t('Com observação')}
                                        aria-label={t('Acrescentar :nome com observação', { nome: a.nome })}
                                        className={cls(
                                            'absolute right-1.5 top-1.5 grid h-6 w-6 place-items-center rounded-lg text-slate-400',
                                            'transition hover:bg-amber-50 hover:text-amber-600', FOCO,
                                        )}
                                    >
                                        <i className="fas fa-comment-dots text-xs" aria-hidden="true" />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function BotaoDeCategoria({
    activa, onClick, icone, cor, children,
}: {
    activa: boolean; onClick: () => void; icone: string; cor?: string; children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cls(
                'inline-flex flex-none items-center gap-1.5 px-3 py-1.5 text-xs font-semibold transition-all duration-200',
                RAIO, FOCO,
                activa ? 'bg-slate-800 text-white shadow-md' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
            )}
        >
            <i className={`fas ${icone}`} aria-hidden="true" style={!activa && cor ? { color: cor } : undefined} />
            {children}
        </button>
    );
}

/* ─── O painel da comanda ─────────────────────────────────────────────── */

export function PainelDaComanda({
    id,
    opcoes,
    aoRecado,
    aoTrocarDeComanda,
    aoFechar,
    compacto = false,
}: {
    id: number;
    opcoes: OpcoesDasComandas;
    aoRecado: (frase: string) => void;
    /** Juntar comandas muda a comanda que se está a ver. */
    aoTrocarDeComanda?: (id: number) => void;
    /** Fechada a conta, o balcão volta às mesas. */
    aoFechar?: () => void;
    compacto?: boolean;
}) {
    const cache = useQueryClient();

    const [aTransferir, porATransferir] = useState(false);
    const [aAnularComanda, porAAnularComanda] = useState(false);
    const [aJuntar, porAJuntar] = useState(false);
    const [aAnular, porAAnular] = useState<number | null>(null);
    const [motivo, porMotivo] = useState('');
    const [destino, porDestino] = useState('');
    const [aFechar, porAFechar] = useState(false);
    const [emitida, porEmitida] = useState<{ id: number; numero: string } | null>(null);
    const [semTurno, porSemTurno] = useState(false);
    const [erro, porErro] = useState<unknown>(null);

    const ficha = useQuery({
        queryKey: ['restaurante', 'comanda', id],
        queryFn: () => restaurante.comandas.ficha(id),
    });

    const refrescar = () => {
        void cache.invalidateQueries({ queryKey: ['restaurante', 'comanda', id] });
        void cache.invalidateQueries({ queryKey: ['restaurante', 'comandas'] });
        void cache.invalidateQueries({ queryKey: ['restaurante', 'sala'] });
    };

    const comAviso = <T,>(fn: () => Promise<T & { message: string }>) =>
        fn().then((r) => { refrescar(); porErro(null); aoRecado(r.message); return r; })
            .catch((e) => { porErro(e); throw e; });

    const quantidade = useMutation({
        mutationFn: ({ item, delta }: { item: number; delta: number }) =>
            comAviso(() => restaurante.comandas.quantidade(id, item, { delta })),
    });

    const remover = useMutation({
        mutationFn: (item: number) => comAviso(() => restaurante.comandas.remover(id, item)),
    });

    const anular = useMutation({
        mutationFn: ({ item, reason }: { item: number; reason: string }) =>
            comAviso(() => restaurante.comandas.anular(id, item, reason)),
        onSuccess: () => { porAAnular(null); porMotivo(''); },
    });

    const confirmar = useMutation({ mutationFn: () => comAviso(() => restaurante.comandas.confirmar(id)) });
    const despachar = useMutation({ mutationFn: () => comAviso(() => restaurante.comandas.despachar(id)) });
    const libertar = useMutation({ mutationFn: () => comAviso(() => restaurante.comandas.libertarMesa(id)) });
    const anularComanda = useMutation({
        mutationFn: () => comAviso(() => restaurante.comandas.anularComanda(id)),
        onSuccess: () => porAAnularComanda(false),
    });

    const transferir = useMutation({
        mutationFn: (mesa: number) => comAviso(() => restaurante.comandas.transferir(id, mesa)),
        onSuccess: () => { porATransferir(false); porDestino(''); },
    });

    const juntar = useMutation({
        mutationFn: (alvo: number) => comAviso(() => restaurante.comandas.juntar(id, alvo)),
        onSuccess: (r) => { porAJuntar(false); porDestino(''); aoTrocarDeComanda?.(r.order_id); },
    });

    if (ficha.isPending) {
        return <div className="h-64 animate-pulse rounded-2xl bg-slate-100" />;
    }

    if (ficha.isError) return <AvisoDeErro erro={ficha.error} />;

    const f = ficha.data;
    const c = f.comanda;
    const porFacturar = f.artigos.filter((a) => a.por_facturar > 0.0001);
    const podeFacturar = ['ready', 'served', 'partially_billed'].includes(c.estado) && porFacturar.length > 0;
    // Vazia: nenhum artigo vivo (os anulados já têm o seu registo) e nada facturado.
    const vazia = f.artigos.every((a) => a.estado_na_cozinha === 'voided') && f.artigos.every((a) => a.facturada <= 0);

    return (
        <div className={cls('flex min-h-0 flex-col gap-3', compacto && 'h-full')}>
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="flex items-center gap-2 text-lg font-bold tracking-tight text-slate-900">
                        <i className={`fas ${ICONE_DO_CANAL[c.canal] ?? 'fa-receipt'} text-orange-500`} aria-hidden="true" />
                        {c.numero}
                    </h3>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {c.mesa ? t('Mesa :m', { m: c.mesa }) : c.canal_rotulo}
                        {c.pessoas > 1 && ` · ${t(':n pessoas', { n: String(c.pessoas) })}`}
                        {c.empregado && ` · ${c.empregado}`}
                    </p>
                </div>
                <Etiqueta cor={COR_DO_ESTADO[c.estado] ?? 'neutra'} ponto>{c.estado_rotulo}</Etiqueta>
            </header>

            {(c.cliente || c.telefone || c.morada) && (
                <div className={cls('border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600', RAIO)}>
                    {c.cliente && <p className="font-semibold text-slate-800">{c.cliente}</p>}
                    {c.telefone && <p><i className="fas fa-phone mr-1.5" aria-hidden="true" />{c.telefone}</p>}
                    {c.morada && <p><i className="fas fa-location-dot mr-1.5" aria-hidden="true" />{c.morada}</p>}
                </div>
            )}

            <AvisoDeErro erro={erro} />

            <div className="min-h-0 flex-1 overflow-y-auto">
                {f.artigos.length === 0 ? (
                    <SemNada icone="fa-utensils" frase={t('Comanda vazia — escolha os artigos.')} />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {f.artigos.map((a) => (
                            <li key={a.id} className="flex items-center gap-2 py-2">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">{a.nome}</p>
                                    <p className="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                        <span className="tabular-nums">{kz(a.preco)} Kz</span>
                                        {a.facturada > 0 && (
                                            <span className="text-emerald-600">
                                                · {t(':n já facturado', { n: kz(a.facturada, 0) })}
                                            </span>
                                        )}
                                        {a.estado_na_cozinha && a.estado_na_cozinha !== 'pending' && (
                                            <Etiqueta cor={COR_NA_COZINHA[a.estado_na_cozinha] ?? 'neutra'}>
                                                {t(ROTULO_NA_COZINHA[a.estado_na_cozinha] ?? a.estado_na_cozinha)}
                                            </Etiqueta>
                                        )}
                                    </p>
                                    {a.observacoes && (
                                        <p className="truncate text-xs italic text-amber-700">« {a.observacoes} »</p>
                                    )}
                                </div>

                                {c.aberta && opcoes.permissoes.pode_editar && (
                                    a.so_anulavel ? (
                                        // Já foi produzido: não se apaga, anula-se — e isso
                                        // escreve um desperdício com motivo.
                                        <Botao
                                            altura="pequeno" cor="perigo" icone="fa-ban"
                                            onClick={() => { porAAnular(a.id); porMotivo(''); }}
                                            title={t('Anular artigo produzido')}
                                        />
                                    ) : (
                                        <div className="flex flex-none items-center gap-1">
                                            <Botao
                                                altura="pequeno" icone="fa-minus"
                                                onClick={() => quantidade.mutate({ item: a.id, delta: -1 })}
                                                aria-label={t('Menos um')}
                                            />
                                            <span className="w-9 text-center text-sm font-bold tabular-nums text-slate-800">
                                                {a.quantidade}
                                            </span>
                                            <Botao
                                                altura="pequeno" icone="fa-plus"
                                                onClick={() => quantidade.mutate({ item: a.id, delta: 1 })}
                                                aria-label={t('Mais um')}
                                            />
                                            <Botao
                                                altura="pequeno" cor="perigo" icone="fa-trash"
                                                onClick={() => remover.mutate(a.id)}
                                                aria-label={t('Remover')}
                                            />
                                        </div>
                                    )
                                )}

                                <span className="w-24 flex-none text-right text-sm font-bold tabular-nums text-slate-900">
                                    {kz(a.total)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div className={cls('space-y-1 border-t border-slate-200 pt-3 text-sm')}>
                <Linha rotulo={t('Subtotal')} valor={c.subtotal} />
                <Linha rotulo={t('Imposto')} valor={c.imposto} />
                {c.taxa_de_entrega > 0 && <Linha rotulo={t('Entrega')} valor={c.taxa_de_entrega} />}
                {c.gorjeta > 0 && <Linha rotulo={t('Gorjeta')} valor={c.gorjeta} />}
                <div className="flex items-center justify-between pt-1 text-base font-bold text-slate-900">
                    <span>{t('Total')}</span>
                    <span className="tabular-nums">{kz(c.total)} <span className="text-xs font-normal text-slate-400">Kz</span></span>
                </div>
            </div>

            {c.aberta && (
                <div className="flex flex-wrap gap-2">
                    {opcoes.definicoes.cozinha && ['draft'].includes(c.estado) && (
                        <Botao
                            cor="primaria" tom="solida" icone="fa-fire-burner"
                            aTrabalhar={confirmar.isPending}
                            disabled={f.artigos.length === 0}
                            onClick={() => confirmar.mutate()}
                        >
                            {t('Enviar para a cozinha')}
                        </Botao>
                    )}

                    {podeFacturar && opcoes.permissoes.pode_facturar && (
                        <Botao cor="bom" tom="solida" icone="fa-file-invoice" onClick={() => porAFechar(true)}>
                            {t('Fechar conta')}
                        </Botao>
                    )}

                    {c.para_fora && !c.despachada_em && (
                        <Botao cor="aviso" icone="fa-motorcycle" aTrabalhar={despachar.isPending} onClick={() => despachar.mutate()}>
                            {t('Saiu para o cliente')}
                        </Botao>
                    )}

                    {c.table_id && opcoes.permissoes.pode_transferir && f.mesas_livres.length > 0 && (
                        <Botao icone="fa-right-left" onClick={() => { porDestino(''); porATransferir(true); }}>
                            {t('Mudar de mesa')}
                        </Botao>
                    )}

                    {opcoes.permissoes.pode_dividir && f.comandas_para_juntar.length > 0 && (
                        <Botao icone="fa-object-group" onClick={() => { porDestino(''); porAJuntar(true); }}>
                            {t('Juntar a outra')}
                        </Botao>
                    )}

                    {/* A comanda que nunca teve nada: sem isto a mesa ficava
                        ocupada para sempre, em todos os postos. */}
                    {vazia && opcoes.permissoes.pode_anular && (
                        <Botao cor="perigo" icone="fa-ban" onClick={() => porAAnularComanda(true)}>
                            {t('Anular comanda')}
                        </Botao>
                    )}
                </div>
            )}

            {!c.aberta && c.table_id && opcoes.permissoes.pode_libertar_mesa && (
                <Botao icone="fa-broom" aTrabalhar={libertar.isPending} onClick={() => libertar.mutate()}>
                    {t('Mesa limpa')}
                </Botao>
            )}

            {/* ─── Anular a comanda vazia ─── */}
            <Modal
                aberto={aAnularComanda}
                aoFechar={() => porAAnularComanda(false)}
                titulo={t('Anular a comanda :n?', { n: c.numero })}
                subtitulo={t('Não tem artigos nem nada facturado')}
                icone="fa-ban"
                cor="perigo"
                largura="sm"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porAAnularComanda(false)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-ban" aTrabalhar={anularComanda.isPending} onClick={() => anularComanda.mutate()}>
                            {t('Anular comanda')}
                        </Botao>
                    </div>
                }
            >
                <p className="text-sm text-slate-700">
                    {c.mesa
                        ? t('A comanda fica anulada e a mesa :m volta a ficar livre para os outros postos.', { m: c.mesa })
                        : t('A comanda fica anulada.')}
                </p>
                <AvisoDeErro erro={anularComanda.error} />
            </Modal>

            {/* ─── Mudar de mesa ─── */}
            <Modal
                aberto={aTransferir}
                aoFechar={() => porATransferir(false)}
                titulo={t('Mudar de mesa')}
                subtitulo={t('A comanda vai inteira, e a mesa antiga fica livre')}
                icone="fa-right-left"
                cor="laranja"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porATransferir(false)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="primaria" tom="solida" icone="fa-check"
                            disabled={!destino}
                            aTrabalhar={transferir.isPending}
                            onClick={() => transferir.mutate(Number(destino))}
                        >
                            {t('Mudar')}
                        </Botao>
                    </>
                }
            >
                <Campo etiqueta={t('Mesa de destino')} obrigatorio ajuda={t('Só aparecem as mesas livres deste estabelecimento.')}>
                    <select value={destino} onChange={(e) => porDestino(e.target.value)} className={entrada}>
                        <option value="">{t('Escolher…')}</option>
                        {f.mesas_livres.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                    </select>
                </Campo>
            </Modal>

            {/* ─── Juntar comandas ─── */}
            <Modal
                aberto={aJuntar}
                aoFechar={() => porAJuntar(false)}
                titulo={t('Juntar a outra comanda')}
                subtitulo={t('Os artigos passam para a comanda escolhida, e esta fecha-se')}
                icone="fa-object-group"
                cor="laranja"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAJuntar(false)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="primaria" tom="solida" icone="fa-check"
                            disabled={!destino}
                            aTrabalhar={juntar.isPending}
                            onClick={() => juntar.mutate(Number(destino))}
                        >
                            {t('Juntar')}
                        </Botao>
                    </>
                }
            >
                <Campo
                    etiqueta={t('Comanda de destino')}
                    obrigatorio
                    ajuda={t('Uma comanda com artigos já facturados não se junta.')}
                >
                    <select value={destino} onChange={(e) => porDestino(e.target.value)} className={entrada}>
                        <option value="">{t('Escolher…')}</option>
                        {f.comandas_para_juntar.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                    </select>
                </Campo>
            </Modal>

            {/* ─── Anular um artigo produzido ─── */}
            <Modal
                aberto={aAnular !== null}
                aoFechar={() => porAAnular(null)}
                titulo={t('Anular artigo produzido')}
                subtitulo={t('Fica registado como desperdício de produção')}
                icone="fa-ban"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAAnular(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo" tom="solida" icone="fa-ban"
                            disabled={motivo.trim().length < 3}
                            aTrabalhar={anular.isPending}
                            onClick={() => aAnular && anular.mutate({ item: aAnular, reason: motivo })}
                        >
                            {t('Anular')}
                        </Botao>
                    </>
                }
            >
                <Campo
                    etiqueta={t('Motivo')}
                    obrigatorio
                    ajuda={t('O prato já foi feito: o motivo é o que separa um engano de um erro de cozinha.')}
                >
                    <textarea
                        value={motivo}
                        onChange={(e) => porMotivo(e.target.value)}
                        rows={3}
                        className={cls(entrada, 'h-auto py-2')}
                    />
                </Campo>
            </Modal>

            <ModalDeFecho
                aberto={aFechar}
                aoFechar={() => porAFechar(false)}
                comanda={f}
                opcoes={opcoes}
                aoEmitir={(factura) => {
                    porAFechar(false);
                    porEmitida(factura);
                    refrescar();
                    aoFechar?.();
                }}
                aoFaltarTurno={() => { porAFechar(false); porSemTurno(true); }}
            />

            <ModalDoDocumento factura={emitida} aoFechar={() => porEmitida(null)} />
            <SemTurno aberto={semTurno} aoFechar={() => porSemTurno(false)} />
        </div>
    );
}

function Linha({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <div className="flex items-center justify-between text-slate-600">
            <span>{rotulo}</span>
            <span className="tabular-nums">{kz(valor)}</span>
        </div>
    );
}

/* ─── O fecho ─────────────────────────────────────────────────────────── */

export function ModalDeFecho({
    aberto, aoFechar, comanda, opcoes, aoEmitir, aoFaltarTurno,
}: {
    aberto: boolean;
    aoFechar: () => void;
    comanda: FichaDaComanda;
    opcoes: OpcoesDasComandas;
    aoEmitir: (factura: { id: number; numero: string }) => void;
    aoFaltarTurno: () => void;
}) {
    const porFacturar = useMemo(
        () => comanda.artigos.filter((a) => a.por_facturar > 0.0001),
        [comanda.artigos],
    );

    const [tipo, porTipo] = useState<'FR' | 'FT'>('FR');
    const [cliente, porCliente] = useState('');
    const [forma, porForma] = useState('');
    const [escolhidos, porEscolhidos] = useState<number[]>([]);
    const [multiplo, porMultiplo] = useState(false);
    const [formas, porFormas] = useState<Array<{ payment_method_id: string; amount: string }>>([]);
    const [gorjeta, porGorjeta] = useState('0');
    const [chave, porChave] = useState(chaveNova());
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);

    // Cada abertura do modal é um fecho novo: chave nova, todas as linhas
    // escolhidas, e a forma de pagamento de arranque.
    useEffect(() => {
        if (!aberto) return;

        porChave(chaveNova());
        porEscolhidos(porFacturar.map((a) => a.id));
        porTipo('FR');
        porCliente(comanda.comanda.client_id ? String(comanda.comanda.client_id) : '');
        porForma(opcoes.formas_de_pagamento[0]?.valor ?? '');
        porMultiplo(false);
        porFormas([]);
        porGorjeta('0');
        porErros({});
        porErro(null);
    }, [aberto]);

    const total = useMemo(
        () => porFacturar.filter((a) => escolhidos.includes(a.id)).reduce((s, a) => s + a.total, 0),
        [porFacturar, escolhidos],
    );

    const distribuido = formas.reduce((s, f) => s + (Number(f.amount) || 0), 0);
    const emFalta = Math.round((total - distribuido) * 100) / 100;

    const fechar = useMutation({
        mutationFn: () => restaurante.comandas.fechar(comanda.comanda.id, {
            document_type: tipo,
            client_id: cliente ? Number(cliente) : null,
            payment_method_id: forma ? Number(forma) : null,
            idempotency_key: chave,
            item_ids: escolhidos,
            payments: multiplo && tipo === 'FR'
                ? formas.filter((f) => Number(f.amount) > 0)
                    .map((f) => ({ payment_method_id: Number(f.payment_method_id), amount: Number(f.amount) }))
                : undefined,
            tip_amount: tipo === 'FR' ? Number(gorjeta) || 0 : 0,
        }),
        onSuccess: (r) => aoEmitir(r.factura),
        onError: (e) => {
            if (e instanceof ErroDaApi) {
                if (e.estado === 409 && (e.corpo as { falta_turno?: boolean }).falta_turno) {
                    aoFaltarTurno();

                    return;
                }

                porErros(e.erros);
            }

            porErro(e);
        },
    });

    const alternarLinha = (id: number) =>
        porEscolhidos((antes) => antes.includes(id) ? antes.filter((x) => x !== id) : [...antes, id]);

    const juntarForma = () => {
        const usadas = formas.map((f) => f.payment_method_id);
        const seguinte = opcoes.formas_de_pagamento.find((f) => !usadas.includes(f.valor));

        if (!seguinte) return;

        porFormas([...formas, { payment_method_id: seguinte.valor, amount: String(Math.max(0, emFalta)) }]);
    };

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Fechar conta')}
            subtitulo={comanda.comanda.numero}
            icone="fa-file-invoice"
            cor="bom"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="bom" tom="solida" icone="fa-check"
                        disabled={escolhidos.length === 0}
                        aTrabalhar={fechar.isPending}
                        onClick={() => fechar.mutate()}
                    >
                        {t('Emitir :tipo', { tipo })}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            <div className="grid gap-4 sm:grid-cols-2">
                <Campo
                    etiqueta={t('Documento')}
                    obrigatorio
                    erro={erros.document_type}
                    ajuda={t('FR é factura-recibo (paga já); FT fica por cobrar.')}
                >
                    <select value={tipo} onChange={(e) => porTipo(e.target.value as 'FR' | 'FT')} className={entrada}>
                        <option value="FR">{t('FR — Factura-Recibo')}</option>
                        <option value="FT">{t('FT — Factura')}</option>
                    </select>
                </Campo>

                <Campo
                    etiqueta={t('Cliente')}
                    erro={erros.client_id}
                    ajuda={tipo === 'FT' ? t('Uma factura por cobrar precisa de saber a quem.') : undefined}
                >
                    <select value={cliente} onChange={(e) => porCliente(e.target.value)} className={entrada}>
                        <option value="">{t('Consumidor final')}</option>
                        {opcoes.clientes.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            <fieldset className="mt-4">
                <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    {t('O que entra nesta factura')}
                </legend>

                {/* A CONTA DIVIDIDA: tiram-se as linhas que ficam para outra
                    factura, e a comanda segue parcialmente facturada. */}
                <ul className={cls('divide-y divide-slate-100 border border-slate-200 bg-white', RAIO)}>
                    {porFacturar.map((a) => (
                        <li key={a.id} className="flex items-center gap-3 px-3 py-2">
                            <input
                                type="checkbox"
                                id={`linha-${a.id}`}
                                checked={escolhidos.includes(a.id)}
                                onChange={() => alternarLinha(a.id)}
                                className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <label htmlFor={`linha-${a.id}`} className="min-w-0 flex-1 cursor-pointer">
                                <span className="block truncate text-sm font-medium text-slate-800">{a.nome}</span>
                                <span className="block text-xs text-slate-500">
                                    {a.por_facturar} × {kz(a.preco)} Kz
                                </span>
                            </label>
                            <span className="flex-none text-sm font-bold tabular-nums text-slate-900">{kz(a.total)}</span>
                        </li>
                    ))}
                </ul>

                {erros.item_ids?.[0] && (
                    <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros.item_ids[0]}</p>
                )}

                <div className="mt-2 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
                    <span className="text-sm font-semibold text-slate-700">{t('Total a facturar')}</span>
                    <span className="text-lg font-bold tabular-nums text-slate-900">
                        {kz(total)} <span className="text-xs font-normal text-slate-400">Kz</span>
                    </span>
                </div>
            </fieldset>

            {tipo === 'FR' && (
                <div className="mt-4 space-y-3">
                    {!multiplo ? (
                        <Campo etiqueta={t('Forma de pagamento')} obrigatorio erro={erros.payment_method_id}>
                            <select value={forma} onChange={(e) => porForma(e.target.value)} className={entrada}>
                                <option value="">{t('Escolher…')}</option>
                                {opcoes.formas_de_pagamento.map((f) => (
                                    <option key={f.valor} value={f.valor}>{f.rotulo}</option>
                                ))}
                            </select>
                        </Campo>
                    ) : (
                        <fieldset>
                            <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('Pagamento repartido')}
                            </legend>
                            <div className="space-y-2">
                                {formas.map((linha, i) => (
                                    <div key={i} className="flex items-center gap-2">
                                        <select
                                            value={linha.payment_method_id}
                                            onChange={(e) => porFormas(formas.map((x, j) =>
                                                j === i ? { ...x, payment_method_id: e.target.value } : x))}
                                            aria-label={t('Forma de pagamento')}
                                            className={cls(entrada, 'flex-1')}
                                        >
                                            {opcoes.formas_de_pagamento.map((f) => (
                                                <option key={f.valor} value={f.valor}>{f.rotulo}</option>
                                            ))}
                                        </select>
                                        <input
                                            type="number" step="0.01" min="0"
                                            value={linha.amount}
                                            onChange={(e) => porFormas(formas.map((x, j) =>
                                                j === i ? { ...x, amount: e.target.value } : x))}
                                            aria-label={t('Valor')}
                                            className={cls(entrada, 'w-32 text-right tabular-nums')}
                                        />
                                        <Botao
                                            altura="pequeno" cor="perigo" icone="fa-trash"
                                            onClick={() => porFormas(formas.filter((_, j) => j !== i))}
                                            aria-label={t('Retirar')}
                                        />
                                    </div>
                                ))}
                            </div>

                            <div className="mt-2 flex items-center justify-between">
                                <Botao
                                    altura="pequeno" icone="fa-plus"
                                    disabled={formas.length >= opcoes.formas_de_pagamento.length}
                                    onClick={juntarForma}
                                >
                                    {t('Outra forma')}
                                </Botao>
                                <span className={cls(
                                    'text-sm font-semibold tabular-nums',
                                    Math.abs(emFalta) <= 0.02 ? 'text-emerald-600' : 'text-amber-600',
                                )}>
                                    {emFalta > 0
                                        ? t('Falta distribuir :v Kz', { v: kz(emFalta) })
                                        : emFalta < 0
                                            ? t('Excede em :v Kz', { v: kz(Math.abs(emFalta)) })
                                            : t('Distribuído')}
                                </span>
                            </div>
                        </fieldset>
                    )}

                    <div className="flex flex-wrap items-end gap-4">
                        <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={multiplo}
                                onChange={(e) => {
                                    porMultiplo(e.target.checked);
                                    porFormas(e.target.checked
                                        ? [{ payment_method_id: forma || opcoes.formas_de_pagamento[0]?.valor || '', amount: String(total) }]
                                        : []);
                                }}
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            {t('O cliente paga em mais do que uma forma')}
                        </label>

                        {/* A GORJETA NÃO ENTRA NA FACTURA: é do pessoal, e só
                            passa pela caixa. A taxa de serviço, essa, é da casa
                            e vai no documento. */}
                        {opcoes.definicoes.gorjetas && (
                            <Campo etiqueta={t('Gorjeta')} erro={erros.tip_amount} className="w-40"
                                ajuda={t('Não entra na factura')}>
                                <input
                                    type="number" step="0.01" min="0"
                                    value={gorjeta}
                                    onChange={(e) => porGorjeta(e.target.value)}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>
                        )}
                    </div>

                    {opcoes.definicoes.taxa_de_servico > 0 && (
                        <p className={cls('border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900', RAIO)}>
                            <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                            {t('A taxa de serviço de :n% é acrescentada à factura.', {
                                n: String(opcoes.definicoes.taxa_de_servico),
                            })}
                        </p>
                    )}
                </div>
            )}
        </Modal>
    );
}

/** Emitido: o documento imprime-se daqui, e o balcão volta às mesas. */
function ModalDoDocumento({
    factura, aoFechar,
}: {
    factura: { id: number; numero: string } | null;
    aoFechar: () => void;
}) {
    return (
        <Modal
            aberto={factura !== null}
            aoFechar={aoFechar}
            titulo={t('Documento emitido')}
            subtitulo={factura?.numero}
            icone="fa-circle-check"
            cor="bom"
            largura="sm"
            rodape={<Botao cor="primaria" tom="solida" onClick={aoFechar}>{t('Concluir')}</Botao>}
        >
            <div className="py-2 text-center">
                <span className="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-emerald-50 text-3xl text-emerald-600">
                    <i className="fas fa-check" aria-hidden="true" />
                </span>
                <p className="text-lg font-bold text-slate-900">{factura?.numero}</p>
                <p className="mt-1 text-sm text-slate-500">{t('A conta está fechada.')}</p>

                <div className="mt-5 flex justify-center gap-2">
                    <a
                        href={factura ? `/restaurant/documents/${factura.id}/print` : '#'}
                        target="_blank"
                        rel="noopener"
                        className={cls(
                            'inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200',
                            FOCO,
                        )}
                    >
                        <i className="fas fa-print" aria-hidden="true" />
                        {t('Imprimir')}
                    </a>
                </div>
            </div>
        </Modal>
    );
}

/**
 * SEM TURNO NÃO SE VENDE.
 *
 * Um aviso que se apaga sozinho dizia o problema e não dizia para onde ir.
 * Isto diz as duas coisas, e leva lá.
 */
export function SemTurno({ aberto, aoFechar }: { aberto: boolean; aoFechar: () => void }) {
    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Abra o turno primeiro')}
            subtitulo={t('A caixa tem de estar aberta para se vender')}
            icone="fa-cash-register"
            cor="aviso"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Agora não')}</Botao>
                    <a
                        href="/restaurant/shifts"
                        className={cls(
                            'inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 px-4 text-sm font-semibold text-white shadow-md',
                            FOCO,
                        )}
                    >
                        <i className="fas fa-door-open" aria-hidden="true" />
                        {t('Abrir turno')}
                    </a>
                </>
            }
        >
            <p className="text-sm text-slate-600">
                {t('Vender com a caixa fechada é vender sem ninguém responder pelo dinheiro. Abra o seu turno e volte — a comanda fica onde está.')}
            </p>
        </Modal>
    );
}
