import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useRef, useState, type DragEvent, type KeyboardEvent } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { fotografiasDaViatura, type DadosDaFoto, type Escolha, type FaseDaFoto, type FotoDaViatura } from '@/api/oficina';
import { avisar } from '@/casca/avisos';
import { t, tn } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ImagemRecusada, prepararImagem, tamanhoLegivel } from '@/ui/prepararImagem';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, dataHora } from '@/ui/tokens';

/**
 * AS FOTOGRAFIAS DA VIATURA — antes, durante, depois e danos (15/09/2026).
 *
 * Pedido: «uma área para juntar imagens da viatura, antes e depois,
 * principalmente para serviços de bate-chapa, pintura e outros». Três coisas:
 *
 *  1. JUNTAR — escolhe-se a fase, o serviço e a zona do carro UMA vez e depois
 *     tiram-se (ou arrastam-se) as fotografias todas desse lote. No telemóvel o
 *     botão «Tirar fotografia» abre logo a câmara de trás.
 *  2. GALERIA — filtrada pelo serviço e pela fase, a ampliar com setas.
 *  3. ANTES E DEPOIS — o «antes» e o «depois» da mesma zona, do mesmo serviço e
 *     da mesma folha de obra, sobrepostos com uma régua que se arrasta. É o que
 *     se mostra ao cliente para dizer «era assim, ficou assim».
 *
 * NUMA VIATURA NOVA ainda não há onde gravar: os lotes ficam à espera
 * (`pendentes`) e sobem assim que a viatura é criada — quem regista o carro à
 * entrada fotografa os danos no mesmo gesto.
 */

export type LoteParaSubir = { chave: string; ficheiros: File[]; dados: DadosDaFoto };

export type ListasDasFotos = { fases: Escolha[]; servicos: Escolha[]; zonas: Escolha[] };

/** O aspecto de cada fase — a cor diz a fase antes de se ler a palavra. */
export const ASPECTO_DA_FASE: Record<FaseDaFoto, { icone: string; faixa: string; suave: string; activa: string }> = {
    antes: { icone: 'fa-hourglass-start', faixa: 'bg-amber-500', suave: 'bg-amber-50 text-amber-800 ring-amber-200', activa: 'bg-amber-500 text-white ring-amber-500 shadow-amber-500/30' },
    durante: { icone: 'fa-screwdriver-wrench', faixa: 'bg-blue-500', suave: 'bg-blue-50 text-blue-800 ring-blue-200', activa: 'bg-blue-600 text-white ring-blue-600 shadow-blue-600/30' },
    depois: { icone: 'fa-circle-check', faixa: 'bg-emerald-500', suave: 'bg-emerald-50 text-emerald-800 ring-emerald-200', activa: 'bg-emerald-600 text-white ring-emerald-600 shadow-emerald-600/30' },
    dano: { icone: 'fa-car-burst', faixa: 'bg-red-500', suave: 'bg-red-50 text-red-800 ring-red-200', activa: 'bg-red-600 text-white ring-red-600 shadow-red-600/30' },
};

export const ICONE_DO_SERVICO: Record<string, string> = {
    bate_chapa: 'fa-hammer',
    pintura: 'fa-spray-can',
    polimento: 'fa-wand-magic-sparkles',
    mecanica: 'fa-gears',
    electrica: 'fa-bolt',
    vidros: 'fa-car-side',
    estofos: 'fa-couch',
    outros: 'fa-ellipsis',
};

const DADOS_INICIAIS: DadosDaFoto = { fase: 'antes', servico: 'bate_chapa', zona: '', ordem_id: '', descricao: '' };

const rotulo = (lista: Escolha[] | undefined, valor: string | null | undefined) =>
    (lista ?? []).find((e) => e.valor === valor)?.rotulo ?? valor ?? '';

export function FotografiasDaViatura({ id, podeEditar, listas: listasDeFora, pendentes = [], aoMudarPendentes }: {
    /** Nulo numa viatura nova: as fotografias ficam em `pendentes` até gravar. */
    id: number | null;
    podeEditar: boolean;
    listas?: ListasDasFotos;
    pendentes?: LoteParaSubir[];
    aoMudarPendentes?: (lotes: LoteParaSubir[]) => void;
}) {
    const cliente = useQueryClient();
    const chave = ['oficina', 'viatura', id, 'fotografias'];

    const dados = useQuery({
        queryKey: chave,
        queryFn: () => fotografiasDaViatura.ler(id as number),
        enabled: id !== null,
    });

    const listas: ListasDasFotos = dados.data?.listas ?? listasDeFora ?? { fases: [], servicos: [], zonas: [] };
    const ordens = dados.data?.ordens ?? [];
    const podeJuntar = podeEditar && (id === null ? Boolean(aoMudarPendentes) : (dados.data?.pode_editar ?? podeEditar));

    const [lote, porLote] = useState<DadosDaFoto>(DADOS_INICIAIS);
    const [escolhidos, porEscolhidos] = useState<Array<{ ficheiro: File; url: string }>>([]);
    const [aPreparar, porAPreparar] = useState(false);
    const [arrastar, porArrastar] = useState(false);
    const [filtroServico, porFiltroServico] = useState('');
    const [filtroFase, porFiltroFase] = useState<FaseDaFoto | ''>('');
    const [vista, porVista] = useState<'galeria' | 'comparar'>('galeria');
    const [aVer, porAVer] = useState<number | null>(null);
    const [aEditar, porAEditar] = useState<FotoDaViatura | null>(null);
    const [aTirar, porATirar] = useState<FotoDaViatura | null>(null);
    // A zona de juntar abre sozinha quando ainda não há fotografias; havendo, fica numa barra e a galeria vem primeiro.
    const [abrirLote, porAbrirLote] = useState<boolean | null>(null);

    const zonaDoLote = useRef<HTMLDivElement>(null);
    const escolher = useRef<HTMLInputElement>(null);
    const camara = useRef<HTMLInputElement>(null);

    // As pré-visualizações são URLs do browser: soltam-se quando saem.
    const escolhidosRef = useRef(escolhidos);
    escolhidosRef.current = escolhidos;
    useEffect(() => () => escolhidosRef.current.forEach((e) => URL.revokeObjectURL(e.url)), []);

    const invalidar = () => cliente.invalidateQueries({ queryKey: ['oficina', 'viatura', id] });

    const juntar = useMutation({
        mutationFn: () => fotografiasDaViatura.juntar(id as number, escolhidos.map((e) => e.ficheiro), lote),
        onSuccess: () => { limparEscolhidos(); porLote((l) => ({ ...l, descricao: '' })); porAbrirLote(false); invalidar(); },
    });

    const mudar = useMutation({
        mutationFn: ({ foto, novos }: { foto: FotoDaViatura; novos: DadosDaFoto }) => fotografiasDaViatura.mudar(id as number, Number(foto.id), novos),
        onSuccess: () => { porAEditar(null); invalidar(); },
    });

    const tirar = useMutation({
        mutationFn: (foto: FotoDaViatura) => fotografiasDaViatura.tirar(id as number, Number(foto.id)),
        onSuccess: () => { porATirar(null); porAVer(null); invalidar(); },
    });

    function limparEscolhidos() {
        escolhidos.forEach((e) => URL.revokeObjectURL(e.url));
        porEscolhidos([]);
    }

    async function receber(lista: FileList | File[] | null) {
        if (!lista || lista.length === 0) return;
        porAPreparar(true);

        const prontos: Array<{ ficheiro: File; url: string }> = [];
        for (const f of Array.from(lista)) {
            try {
                const pronto = await prepararImagem(f);
                prontos.push({ ficheiro: pronto, url: URL.createObjectURL(pronto) });
            } catch (e) {
                avisar(e instanceof ImagemRecusada ? e.message : t('Não foi possível ler «:nome».', { nome: f.name }), 'erro');
            }
        }

        const todos = [...escolhidosRef.current, ...prontos];
        if (todos.length > 12) {
            avisar(t('No máximo 12 fotografias de cada vez.'), 'aviso');
            todos.slice(12).forEach((e) => URL.revokeObjectURL(e.url));
        }

        porEscolhidos(todos.slice(0, 12));
        porAPreparar(false);
    }

    const soltar = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        porArrastar(false);
        void receber(e.dataTransfer.files);
    };

    const confirmarLote = () => {
        if (escolhidos.length === 0) return;

        if (id === null) {
            aoMudarPendentes?.([...pendentes, { chave: `${Date.now()}-${pendentes.length}`, ficheiros: escolhidos.map((e) => e.ficheiro), dados: lote }]);
            // Os URLs ficam: a lista de pendentes desenha-se a partir dos ficheiros.
            escolhidos.forEach((e) => URL.revokeObjectURL(e.url));
            porEscolhidos([]);
            avisar(tn(':n fotografia sobe ao gravar a viatura.|:n fotografias sobem ao gravar a viatura.', escolhidos.length, { n: escolhidos.length }), 'info');
            return;
        }

        juntar.mutate();
    };

    /** «Juntar o depois» a partir de um par incompleto: o lote vem já preenchido. */
    const prepararLote = (novos: Partial<DadosDaFoto>) => {
        porLote((l) => ({ ...l, ...novos }));
        porAbrirLote(true);
        window.setTimeout(() => zonaDoLote.current?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50);
    };

    const fotos = dados.data?.fotos ?? [];
    const filtradas = fotos.filter((f) => (!filtroServico || f.servico === filtroServico) && (!filtroFase || f.fase === filtroFase));
    const porServico = useMemo(() => {
        const contas: Record<string, number> = {};
        fotos.forEach((f) => { contas[f.servico] = (contas[f.servico] ?? 0) + 1; });

        return contas;
    }, [fotos]);

    const loteAberto = abrirLote ?? (id === null || (dados.isSuccess && fotos.length === 0));

    return (
        <div className="space-y-5">
            {podeJuntar && !loteAberto && (
                <div className={cls('group flex flex-wrap items-center gap-3 border border-dashed border-indigo-200 bg-indigo-50/50 p-3', RAIO_GRANDE, TRANSICAO, 'hover:border-indigo-400 hover:bg-indigo-50')}>
                    <span className="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow transition-transform duration-300 group-hover:scale-110 group-hover:rotate-6">
                        <i className="fas fa-camera" aria-hidden="true" />
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block text-sm font-semibold text-slate-800">{t('Juntar fotografias')}</span>
                        <span className="block text-xs text-slate-500">{t('Antes, durante, depois ou danos — bate-chapa, pintura e o resto.')}</span>
                    </span>
                    <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porAbrirLote(true)}>{t('Juntar fotografias')}</Botao>
                </div>
            )}
            {podeJuntar && loteAberto && (
                <section ref={zonaDoLote} className={cls('animate-fade-in relative border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4', RAIO_GRANDE)} aria-label={t('Juntar fotografias')}>
                    {id !== null && fotos.length > 0 && escolhidos.length === 0 && (
                        <button type="button" onClick={() => porAbrirLote(false)} aria-label={t('Fechar')} title={t('Fechar')}
                            className={cls('absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full text-slate-400 hover:rotate-90 hover:bg-slate-100 hover:text-slate-700', TRANSICAO, FOCO)}>
                            <i className="fas fa-xmark" aria-hidden="true" />
                        </button>
                    )}
                    {/* A FASE — quatro botões grandes, com a cor de cada uma. */}
                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Fase do trabalho')}</p>
                    <div role="radiogroup" aria-label={t('Fase do trabalho')} className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {listas.fases.map((f) => {
                            const fase = f.valor as FaseDaFoto;
                            const activa = lote.fase === fase;
                            const aspecto = ASPECTO_DA_FASE[fase];

                            return (
                                <button
                                    key={f.valor}
                                    type="button"
                                    role="radio"
                                    aria-checked={activa}
                                    onClick={() => porLote({ ...lote, fase })}
                                    className={cls('group flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold ring-1 ring-inset', RAIO, TRANSICAO, FOCO,
                                        activa ? cls(aspecto?.activa, 'shadow-lg') : 'bg-white text-slate-600 ring-slate-200 hover:-translate-y-0.5 hover:shadow-md')}
                                >
                                    <i className={cls('fas transition-transform duration-300 group-hover:scale-125', aspecto?.icone)} aria-hidden="true" />
                                    {f.rotulo}
                                </button>
                            );
                        })}
                    </div>

                    {/* O SERVIÇO — em pastilhas, bate-chapa e pintura à frente. */}
                    <p className="mb-2 mt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Serviço')}</p>
                    <div role="radiogroup" aria-label={t('Serviço')} className="flex flex-wrap gap-2">
                        {listas.servicos.map((s) => {
                            const activo = lote.servico === s.valor;

                            return (
                                <button
                                    key={s.valor}
                                    type="button"
                                    role="radio"
                                    aria-checked={activo}
                                    onClick={() => porLote({ ...lote, servico: s.valor })}
                                    className={cls('group inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset', TRANSICAO, FOCO,
                                        activo ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 ring-indigo-600' : 'bg-white text-slate-600 ring-slate-200 hover:bg-indigo-50 hover:text-indigo-700')}
                                >
                                    <i className={cls('fas transition-transform duration-300 group-hover:rotate-12', ICONE_DO_SERVICO[s.valor] ?? 'fa-tag')} aria-hidden="true" />
                                    {s.rotulo}
                                </button>
                            );
                        })}
                    </div>

                    <div className={cls('mt-4 grid gap-3', id !== null ? 'sm:grid-cols-3' : 'sm:grid-cols-2')}>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-location-crosshairs mr-1.5 text-slate-400" aria-hidden="true" />{t('Zona do carro')}</span>
                            <select value={lote.zona} onChange={(e) => porLote({ ...lote, zona: e.target.value })} className={cls('w-full border border-slate-300 bg-white px-3 py-2 text-sm', RAIO, FOCO)}>
                                <option value="">{t('— sem zona —')}</option>
                                {listas.zonas.map((z) => <option key={z.valor} value={z.valor}>{z.rotulo}</option>)}
                            </select>
                        </label>
                        {id !== null && (
                            <label className="block text-sm">
                                <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-clipboard-list mr-1.5 text-slate-400" aria-hidden="true" />{t('Folha de obra')}</span>
                                <select value={lote.ordem_id} onChange={(e) => porLote({ ...lote, ordem_id: e.target.value })} className={cls('w-full border border-slate-300 bg-white px-3 py-2 text-sm', RAIO, FOCO)}>
                                    <option value="">{t('— nenhuma —')}</option>
                                    {ordens.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                                </select>
                            </label>
                        )}
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-pen mr-1.5 text-slate-400" aria-hidden="true" />{t('Descrição')}</span>
                            <input value={lote.descricao} maxLength={500} onChange={(e) => porLote({ ...lote, descricao: e.target.value })} placeholder={t('Ex.: amolgadela na porta')} className={cls('w-full border border-slate-300 bg-white px-3 py-2 text-sm', RAIO, FOCO)} />
                        </label>
                    </div>
                    {id !== null && lote.ordem_id !== '' && (
                        <p className="mt-2 text-xs text-slate-500"><i className="fas fa-eye mr-1 text-indigo-400" aria-hidden="true" />{t('As fotografias ligadas a uma folha de obra aparecem ao cliente no portal.')}</p>
                    )}

                    {/* ONDE AS FOTOGRAFIAS CAEM — arrastar, escolher ou tirar com a câmara. */}
                    <div
                        onDragOver={(e) => { e.preventDefault(); porArrastar(true); }}
                        onDragLeave={() => porArrastar(false)}
                        onDrop={soltar}
                        className={cls('mt-4 border-2 border-dashed p-4 text-center', RAIO_GRANDE, TRANSICAO,
                            arrastar ? 'scale-[1.01] border-indigo-500 bg-indigo-50' : 'border-slate-300 bg-white')}
                    >
                        <input ref={escolher} type="file" accept="image/*" multiple className="hidden" onChange={(e) => { void receber(e.target.files); e.target.value = ''; }} />
                        <input ref={camara} type="file" accept="image/*" capture="environment" className="hidden" onChange={(e) => { void receber(e.target.files); e.target.value = ''; }} />

                        {escolhidos.length === 0 ? (
                            <div className="py-3">
                                <span className={cls('mx-auto mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-indigo-100 text-2xl text-indigo-600', arrastar ? 'animate-bounce' : 'icon-float')}>
                                    <i className={cls('fas', aPreparar ? 'fa-spinner fa-spin' : 'fa-images')} aria-hidden="true" />
                                </span>
                                <p className="text-sm font-semibold text-slate-700">{aPreparar ? t('A preparar as fotografias…') : t('Arraste as fotografias para aqui')}</p>
                                <p className="mt-0.5 text-xs text-slate-500">{t('JPG, PNG ou WebP · até 12 de cada vez · as grandes são reduzidas antes de subir')}</p>
                                <div className="mt-3 flex flex-wrap justify-center gap-2">
                                    <Botao cor="primaria" tom="solida" icone="fa-camera" onClick={() => camara.current?.click()} disabled={aPreparar}>{t('Tirar fotografia')}</Botao>
                                    <Botao cor="primaria" icone="fa-folder-open" onClick={() => escolher.current?.click()} disabled={aPreparar}>{t('Escolher ficheiros')}</Botao>
                                </div>
                            </div>
                        ) : (
                            <div>
                                <ul className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                    {escolhidos.map((e, i) => (
                                        <li key={e.url} style={cascata(i)} className={cls('entra group relative aspect-square overflow-hidden bg-slate-100', RAIO)}>
                                            <img src={e.url} alt={e.ficheiro.name} className="h-full w-full object-cover" />
                                            <span className={cls('absolute left-1 top-1 h-2 w-2 rounded-full ring-2 ring-white', ASPECTO_DA_FASE[lote.fase]?.faixa)} aria-hidden="true" />
                                            <span className="absolute inset-x-0 bottom-0 truncate bg-black/50 px-1 text-[10px] text-white">{tamanhoLegivel(e.ficheiro.size)}</span>
                                            <button
                                                type="button"
                                                onClick={() => { URL.revokeObjectURL(e.url); porEscolhidos(escolhidos.filter((x) => x !== e)); }}
                                                aria-label={t('Tirar :nome', { nome: e.ficheiro.name })}
                                                className={cls('absolute right-1 top-1 grid h-6 w-6 place-items-center rounded-full bg-white/90 text-xs text-red-600 opacity-0 shadow group-hover:opacity-100 focus:opacity-100', TRANSICAO, FOCO)}
                                            >
                                                <i className="fas fa-xmark" aria-hidden="true" />
                                            </button>
                                        </li>
                                    ))}
                                    {escolhidos.length < 12 && (
                                        <li>
                                            <button type="button" onClick={() => escolher.current?.click()} aria-label={t('Escolher mais')}
                                                className={cls('grid aspect-square w-full place-items-center border-2 border-dashed border-slate-300 text-slate-400 hover:border-indigo-400 hover:text-indigo-600', RAIO, TRANSICAO, FOCO)}>
                                                <i className={cls('fas text-xl', aPreparar ? 'fa-spinner fa-spin' : 'fa-plus')} aria-hidden="true" />
                                            </button>
                                        </li>
                                    )}
                                </ul>
                                <div className="mt-3 flex flex-wrap items-center justify-end gap-2">
                                    <span className="mr-auto text-xs text-slate-500">
                                        {tn(':n fotografia escolhida|:n fotografias escolhidas', escolhidos.length, { n: escolhidos.length })}
                                        {' · '}{rotulo(listas.fases, lote.fase)} · {rotulo(listas.servicos, lote.servico)}{lote.zona && ` · ${rotulo(listas.zonas, lote.zona)}`}
                                    </span>
                                    <Botao icone="fa-camera" onClick={() => camara.current?.click()} disabled={aPreparar || juntar.isPending}>{t('Tirar outra')}</Botao>
                                    <Botao onClick={limparEscolhidos} disabled={juntar.isPending}>{t('Limpar')}</Botao>
                                    <Botao cor="bom" tom="solida" icone={id === null ? 'fa-clock' : 'fa-cloud-arrow-up'} aTrabalhar={juntar.isPending} disabled={aPreparar} onClick={confirmarLote}>
                                        {id === null
                                            ? tn('Juntar :n fotografia|Juntar :n fotografias', escolhidos.length, { n: escolhidos.length })
                                            : tn('Enviar :n fotografia|Enviar :n fotografias', escolhidos.length, { n: escolhidos.length })}
                                    </Botao>
                                </div>
                            </div>
                        )}
                    </div>
                    {juntar.isError && (
                        <p role="alert" className={cls('mt-3 border border-red-200 bg-red-50 p-3 text-sm text-red-800', RAIO)}>
                            {juntar.error instanceof ErroDaApi ? (Object.values(juntar.error.erros ?? {})[0]?.[0] ?? juntar.error.message) : t('Não foi possível enviar as fotografias.')}
                        </p>
                    )}
                </section>
            )}

            {/* NUMA VIATURA NOVA — o que vai subir ao gravar. */}
            {id === null && (
                pendentes.length === 0 ? (
                    <SemNada icone="fa-camera" frase={podeJuntar ? t('As fotografias que juntar aqui sobem assim que gravar a viatura.') : t('Sem fotografias.')} />
                ) : (
                    <div className="space-y-3">
                        {pendentes.map((p, i) => (
                            <LotePendente key={p.chave} lote={p} i={i} listas={listas} aoTirar={() => aoMudarPendentes?.(pendentes.filter((x) => x !== p))} />
                        ))}
                    </div>
                )
            )}

            {id !== null && dados.isPending && <Carregando linhas={3} />}
            {id !== null && dados.isError && (
                <p role="alert" className={cls('border border-red-200 bg-red-50 p-4 text-sm text-red-800', RAIO)}>
                    {dados.error instanceof ErroDaApi ? dados.error.message : t('Não foi possível ler as fotografias.')}
                </p>
            )}

            {id !== null && dados.data && (
                fotos.length === 0 ? (
                    <SemNada icone="fa-images" titulo={t('Sem fotografias')} frase={t('Fotografe o carro à entrada, durante e à saída: o antes e o depois ficam aqui lado a lado.')} />
                ) : (
                    <section className="space-y-3" aria-label={t('Fotografias')}>
                        {/* OS FILTROS E A VISTA — numa fila só. */}
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="flex flex-wrap gap-1.5">
                                <Pastilha activa={filtroServico === ''} onClick={() => porFiltroServico('')} icone="fa-layer-group">{t('Todos')} <b className="tabular-nums">{fotos.length}</b></Pastilha>
                                {listas.servicos.filter((s) => porServico[s.valor]).map((s) => (
                                    <Pastilha key={s.valor} activa={filtroServico === s.valor} onClick={() => porFiltroServico(filtroServico === s.valor ? '' : s.valor)} icone={ICONE_DO_SERVICO[s.valor] ?? 'fa-tag'}>
                                        {s.rotulo} <b className="tabular-nums">{porServico[s.valor]}</b>
                                    </Pastilha>
                                ))}
                            </div>
                            <div className="ml-auto flex items-center gap-2">
                                {vista === 'galeria' && (
                                    <select value={filtroFase} onChange={(e) => porFiltroFase(e.target.value as FaseDaFoto | '')} aria-label={t('Fase')} className={cls('border border-slate-300 bg-white px-2 py-1.5 text-xs', RAIO, FOCO)}>
                                        <option value="">{t('Todas as fases')}</option>
                                        {listas.fases.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                                    </select>
                                )}
                                <div role="tablist" className={cls('inline-flex bg-slate-100 p-0.5', RAIO)}>
                                    {([['galeria', 'fa-table-cells', t('Galeria')], ['comparar', 'fa-left-right', t('Antes e depois')]] as const).map(([v, icone, nome]) => (
                                        <button key={v} type="button" role="tab" aria-selected={vista === v} onClick={() => porVista(v)}
                                            className={cls('inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold', TRANSICAO, FOCO,
                                                vista === v ? 'bg-white text-indigo-700 shadow' : 'text-slate-500 hover:text-slate-700')}>
                                            <i className={cls('fas', icone)} aria-hidden="true" />{nome}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {vista === 'galeria' ? (
                            filtradas.length === 0 ? (
                                <SemNada icone="fa-filter" frase={t('Nenhuma fotografia com estes filtros.')} />
                            ) : (
                                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                    {filtradas.map((f, i) => (
                                        <Miniatura key={f.id} f={f} i={i} listas={listas}
                                            podeMexer={podeJuntar && f.origem === 'viatura'}
                                            aoVer={() => porAVer(i)} aoEditar={() => porAEditar(f)} aoTirar={() => porATirar(f)} />
                                    ))}
                                </ul>
                            )
                        ) : (
                            <Comparacoes fotos={fotos.filter((f) => !filtroServico || f.servico === filtroServico)} listas={listas} podeJuntar={podeJuntar}
                                aoJuntarDepois={(g) => prepararLote({ fase: 'depois', servico: g.servico, zona: g.zona ?? '', ordem_id: g.ordem_id ? String(g.ordem_id) : '' })}
                                aoJuntarAntes={(g) => prepararLote({ fase: 'antes', servico: g.servico, zona: g.zona ?? '', ordem_id: g.ordem_id ? String(g.ordem_id) : '' })} />
                        )}
                    </section>
                )
            )}

            {aVer !== null && filtradas[aVer] && (
                <Ampliar fotos={filtradas} i={aVer} listas={listas} aoMudar={porAVer} aoFechar={() => porAVer(null)}
                    podeMexer={podeJuntar && filtradas[aVer].origem === 'viatura'}
                    aoEditar={(f) => porAEditar(f)} aoTirar={(f) => porATirar(f)} />
            )}

            {aEditar && (
                <EditarFoto f={aEditar} listas={listas} ordens={ordens} aGravar={mudar.isPending} erro={mudar.error}
                    aoFechar={() => { porAEditar(null); mudar.reset(); }}
                    aoGravar={(novos) => mudar.mutate({ foto: aEditar, novos })} />
            )}

            <Modal aberto={aTirar !== null} aoFechar={() => porATirar(null)} titulo={t('Tirar esta fotografia?')} icone="fa-trash" cor="perigo" largura="sm"
                rodape={<><Botao onClick={() => porATirar(null)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={tirar.isPending} onClick={() => aTirar && tirar.mutate(aTirar)}>{t('Tirar')}</Botao></>}>
                {aTirar && (
                    <div className="space-y-3">
                        <img src={aTirar.url} alt="" className={cls('max-h-56 w-full bg-slate-100 object-contain', RAIO)} />
                        <p className="text-sm text-slate-600">{t('A fotografia é apagada do servidor. Não há volta.')}</p>
                    </div>
                )}
            </Modal>
        </div>
    );
}

/* ─── Peças ─────────────────────────────────────────────────────────── */

function Pastilha({ activa, onClick, icone, children }: { activa: boolean; onClick: () => void; icone: string; children: React.ReactNode }) {
    return (
        <button type="button" aria-pressed={activa} onClick={onClick}
            className={cls('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset', TRANSICAO, FOCO,
                activa ? 'bg-slate-800 text-white ring-slate-800' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50')}>
            <i className={cls('fas', icone)} aria-hidden="true" />{children}
        </button>
    );
}

export function SeloDaFase({ fase, listas, pequeno = false }: { fase: FaseDaFoto; listas: ListasDasFotos; pequeno?: boolean }) {
    const a = ASPECTO_DA_FASE[fase] ?? ASPECTO_DA_FASE.antes;

    return (
        <span className={cls('inline-flex items-center gap-1 rounded-full font-bold uppercase tracking-wide text-white shadow', a.faixa, pequeno ? 'px-1.5 py-0.5 text-[9px]' : 'px-2 py-0.5 text-[10px]')}>
            <i className={cls('fas', a.icone)} aria-hidden="true" />{rotulo(listas.fases, fase)}
        </span>
    );
}

function Miniatura({ f, i, listas, podeMexer, aoVer, aoEditar, aoTirar }: {
    f: FotoDaViatura; i: number; listas: ListasDasFotos; podeMexer: boolean;
    aoVer: () => void; aoEditar: () => void; aoTirar: () => void;
}) {
    return (
        <li style={cascata(i)} className={cls('entra group overflow-hidden border border-slate-200 bg-white', RAIO_GRANDE, TRANSICAO, 'hover:-translate-y-1 hover:shadow-xl')}>
            <div className="relative aspect-[4/3] overflow-hidden bg-slate-100">
                <button type="button" onClick={aoVer} className={cls('block h-full w-full', FOCO)} aria-label={t('Ampliar')}>
                    <img src={f.url} alt={f.descricao ?? ''} loading="lazy" className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                </button>
                <span className="pointer-events-none absolute left-2 top-2"><SeloDaFase fase={f.fase} listas={listas} /></span>
                <span className="pointer-events-none absolute inset-0 grid place-items-center bg-black/0 text-2xl text-white opacity-0 transition-all duration-300 group-hover:bg-black/25 group-hover:opacity-100">
                    <i className="fas fa-magnifying-glass-plus" aria-hidden="true" />
                </span>
                {podeMexer && (
                    <span className="absolute right-2 top-2 flex gap-1 opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100">
                        <button type="button" onClick={aoEditar} aria-label={t('Editar')} title={t('Editar')} className={cls('grid h-7 w-7 place-items-center rounded-full bg-white/95 text-xs text-indigo-600 shadow hover:scale-110', TRANSICAO, FOCO)}>
                            <i className="fas fa-pen" aria-hidden="true" />
                        </button>
                        <button type="button" onClick={aoTirar} aria-label={t('Tirar')} title={t('Tirar')} className={cls('grid h-7 w-7 place-items-center rounded-full bg-white/95 text-xs text-red-600 shadow hover:scale-110', TRANSICAO, FOCO)}>
                            <i className="fas fa-trash" aria-hidden="true" />
                        </button>
                    </span>
                )}
            </div>
            <div className="space-y-0.5 p-2.5">
                <p className="flex items-center gap-1.5 truncate text-xs font-semibold text-slate-800">
                    <i className={cls('fas text-indigo-500', ICONE_DO_SERVICO[f.servico] ?? 'fa-tag')} aria-hidden="true" />
                    {rotulo(listas.servicos, f.servico)}
                    {f.zona && <span className="truncate font-normal text-slate-500">· {rotulo(listas.zonas, f.zona)}</span>}
                </p>
                {f.descricao && <p className="line-clamp-1 text-xs text-slate-500">{f.descricao}</p>}
                <p className="flex items-center gap-1.5 text-[11px] text-slate-400">
                    {f.ordem && <span className="inline-flex items-center gap-1 rounded bg-purple-50 px-1.5 font-mono text-purple-700"><i className="fas fa-clipboard-list" aria-hidden="true" />{f.ordem}</span>}
                    <span className="truncate">{dataHora(f.em)}</span>
                </p>
            </div>
        </li>
    );
}

function LotePendente({ lote, i, listas, aoTirar }: { lote: LoteParaSubir; i: number; listas: ListasDasFotos; aoTirar: () => void }) {
    const urls = useMemo(() => lote.ficheiros.map((f) => URL.createObjectURL(f)), [lote.ficheiros]);
    useEffect(() => () => urls.forEach((u) => URL.revokeObjectURL(u)), [urls]);

    return (
        <div style={cascata(i)} className={cls('entra flex flex-wrap items-center gap-3 border border-dashed border-amber-300 bg-amber-50/60 p-3', RAIO_GRANDE)}>
            <span className="grid h-9 w-9 place-items-center rounded-full bg-amber-100 text-amber-600"><i className="fas fa-clock" aria-hidden="true" /></span>
            <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800">
                    <SeloDaFase fase={lote.dados.fase} listas={listas} />
                    {rotulo(listas.servicos, lote.dados.servico)}{lote.dados.zona && ` · ${rotulo(listas.zonas, lote.dados.zona)}`}
                </p>
                <p className="text-xs text-amber-800">{tn(':n fotografia sobe ao gravar|:n fotografias sobem ao gravar', lote.ficheiros.length, { n: lote.ficheiros.length })}</p>
            </div>
            <div className="flex -space-x-3">
                {urls.slice(0, 5).map((u) => <img key={u} src={u} alt="" className="h-11 w-11 rounded-lg object-cover ring-2 ring-white" />)}
            </div>
            <button type="button" onClick={aoTirar} aria-label={t('Tirar este lote')} className={cls('grid h-8 w-8 place-items-center rounded-full text-red-600 hover:bg-red-100', TRANSICAO, FOCO)}>
                <i className="fas fa-trash" aria-hidden="true" />
            </button>
        </div>
    );
}

/* ─── Antes e depois ────────────────────────────────────────────────── */

type Grupo = { chave: string; servico: string; zona: string | null; ordem_id: number | null; ordem: string | null; antes: FotoDaViatura[]; depois: FotoDaViatura[]; durante: number };

/**
 * OS PARES — a mesma zona, o mesmo serviço e a mesma folha de obra. O «dano»
 * conta como antes: é a fotografia de como o carro chegou.
 */
export function agruparPares(fotos: FotoDaViatura[]): Grupo[] {
    const grupos = new Map<string, Grupo>();

    // Da mais antiga para a mais recente: o primeiro «antes» é o de entrada, o último «depois» o de saída.
    [...fotos].sort((a, b) => String(a.em ?? '').localeCompare(String(b.em ?? ''))).forEach((f) => {
        const chave = `${f.servico}|${f.zona ?? ''}|${f.ordem_id ?? ''}`;
        const g = grupos.get(chave) ?? { chave, servico: f.servico, zona: f.zona, ordem_id: f.ordem_id, ordem: f.ordem, antes: [], depois: [], durante: 0 };

        if (f.fase === 'antes' || f.fase === 'dano') g.antes.push(f);
        else if (f.fase === 'depois') g.depois.unshift(f);
        else g.durante++;

        grupos.set(chave, g);
    });

    return [...grupos.values()]
        .filter((g) => g.antes.length > 0 || g.depois.length > 0)
        .sort((a, b) => Number(b.antes.length > 0 && b.depois.length > 0) - Number(a.antes.length > 0 && a.depois.length > 0));
}

function Comparacoes({ fotos, listas, podeJuntar, aoJuntarDepois, aoJuntarAntes }: {
    fotos: FotoDaViatura[]; listas: ListasDasFotos; podeJuntar: boolean;
    aoJuntarDepois: (g: Grupo) => void; aoJuntarAntes: (g: Grupo) => void;
}) {
    const grupos = agruparPares(fotos);

    if (grupos.length === 0) {
        return <SemNada icone="fa-left-right" frase={t('Ainda não há fotografias de antes nem de depois.')} />;
    }

    return (
        <ul className="grid gap-4 lg:grid-cols-2">
            {grupos.map((g, i) => <Par key={g.chave} g={g} i={i} listas={listas} podeJuntar={podeJuntar} aoJuntarDepois={() => aoJuntarDepois(g)} aoJuntarAntes={() => aoJuntarAntes(g)} />)}
        </ul>
    );
}

function Par({ g, i, listas, podeJuntar, aoJuntarDepois, aoJuntarAntes }: {
    g: Grupo; i: number; listas: ListasDasFotos; podeJuntar: boolean; aoJuntarDepois: () => void; aoJuntarAntes: () => void;
}) {
    const [qualAntes, porQualAntes] = useState(0);
    const [qualDepois, porQualDepois] = useState(0);
    const antes = g.antes[Math.min(qualAntes, g.antes.length - 1)];
    const depois = g.depois[Math.min(qualDepois, g.depois.length - 1)];
    const completo = Boolean(antes && depois);

    return (
        <li style={cascata(i)} className={cls('entra overflow-hidden border border-slate-200 bg-white shadow-sm', RAIO_GRANDE, TRANSICAO, 'hover:shadow-lg')}>
            <header className="flex flex-wrap items-center gap-2 border-b border-slate-100 px-3 py-2">
                <span className="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-sm text-white shadow">
                    <i className={cls('fas', ICONE_DO_SERVICO[g.servico] ?? 'fa-tag')} aria-hidden="true" />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-semibold text-slate-800">{rotulo(listas.servicos, g.servico)}{g.zona && <span className="font-normal text-slate-500"> · {rotulo(listas.zonas, g.zona)}</span>}</span>
                    <span className="block text-[11px] text-slate-400">
                        {g.ordem ? <><i className="fas fa-clipboard-list mr-1" aria-hidden="true" />{g.ordem}</> : t('Sem folha de obra')}
                        {g.durante > 0 && <> · {tn(':n durante o trabalho|:n durante o trabalho', g.durante, { n: g.durante })}</>}
                    </span>
                </span>
                {completo
                    ? <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200"><i className="fas fa-check" aria-hidden="true" />{t('Par completo')}</span>
                    : <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-200"><i className="fas fa-hourglass-half" aria-hidden="true" />{depois ? t('Falta o antes') : t('Falta o depois')}</span>}
            </header>

            {completo ? (
                <Comparador antes={antes!} depois={depois!} listas={listas} />
            ) : (
                <div className="relative aspect-[4/3] bg-slate-100">
                    <img src={(antes ?? depois)!.url} alt={(antes ?? depois)!.descricao ?? ''} loading="lazy" className="h-full w-full object-cover" />
                    <span className="absolute left-2 top-2"><SeloDaFase fase={(antes ?? depois)!.fase} listas={listas} /></span>
                    {podeJuntar && (
                        <span className="absolute inset-x-0 bottom-0 flex justify-center bg-gradient-to-t from-black/60 to-transparent p-3">
                            <Botao cor="bom" tom="solida" altura="pequeno" icone="fa-camera" onClick={antes ? aoJuntarDepois : aoJuntarAntes}>
                                {antes ? t('Juntar o depois') : t('Juntar o antes')}
                            </Botao>
                        </span>
                    )}
                </div>
            )}

            {(g.antes.length > 1 || g.depois.length > 1) && (
                <div className="flex flex-wrap items-center gap-3 border-t border-slate-100 px-3 py-2">
                    <Escolher fotos={g.antes} activa={qualAntes} aoMudar={porQualAntes} fase="antes" />
                    <Escolher fotos={g.depois} activa={qualDepois} aoMudar={porQualDepois} fase="depois" />
                </div>
            )}
        </li>
    );
}

function Escolher({ fotos, activa, aoMudar, fase }: { fotos: FotoDaViatura[]; activa: number; aoMudar: (i: number) => void; fase: FaseDaFoto }) {
    if (fotos.length < 2) return null;

    return (
        <span className="flex items-center gap-1">
            <i className={cls('fas text-xs', ASPECTO_DA_FASE[fase].icone, fase === 'antes' ? 'text-amber-500' : 'text-emerald-500')} aria-hidden="true" />
            {fotos.map((f, i) => (
                <button key={f.id} type="button" onClick={() => aoMudar(i)} aria-pressed={activa === i} aria-label={t('Fotografia :n', { n: i + 1 })}
                    className={cls('h-8 w-8 overflow-hidden rounded-md ring-2', TRANSICAO, FOCO, activa === i ? 'ring-indigo-500' : 'opacity-60 ring-transparent hover:opacity-100')}>
                    <img src={f.url} alt="" className="h-full w-full object-cover" />
                </button>
            ))}
        </span>
    );
}

/**
 * A RÉGUA DO ANTES E DEPOIS — o «depois» por baixo, o «antes» por cima cortado
 * até à régua. Arrasta-se com o rato ou o dedo, e com as setas do teclado (é um
 * `<input type="range">` por cima de tudo, invisível).
 */
export function Comparador({ antes, depois, listas }: { antes: FotoDaViatura; depois: FotoDaViatura; listas: ListasDasFotos }) {
    const [pos, porPos] = useState(50);

    return (
        <div className="group relative aspect-[4/3] select-none overflow-hidden bg-slate-900">
            <img src={depois.url} alt={t('Depois')} loading="lazy" className="absolute inset-0 h-full w-full object-cover" draggable={false} />
            <img src={antes.url} alt={t('Antes')} loading="lazy" className="absolute inset-0 h-full w-full object-cover" draggable={false}
                style={{ clipPath: `inset(0 ${100 - pos}% 0 0)` }} />

            <span className="pointer-events-none absolute inset-y-0 w-0.5 bg-white shadow-[0_0_12px_rgba(0,0,0,.5)]" style={{ left: `${pos}%` }} aria-hidden="true">
                <span className="absolute left-1/2 top-1/2 grid h-10 w-10 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white text-indigo-600 shadow-xl transition-transform duration-200 group-hover:scale-110">
                    <i className="fas fa-left-right" aria-hidden="true" />
                </span>
            </span>

            <span className={cls('pointer-events-none absolute left-2 top-2 transition-opacity duration-200', pos < 12 && 'opacity-0')}><SeloDaFase fase={antes.fase} listas={listas} /></span>
            <span className={cls('pointer-events-none absolute right-2 top-2 transition-opacity duration-200', pos > 88 && 'opacity-0')}><SeloDaFase fase="depois" listas={listas} /></span>

            <input
                type="range"
                min={0}
                max={100}
                value={pos}
                onChange={(e) => porPos(Number(e.target.value))}
                aria-label={t('Comparar o antes e o depois')}
                className="absolute inset-0 h-full w-full cursor-ew-resize opacity-0"
            />
        </div>
    );
}

/* ─── Ampliar e editar ──────────────────────────────────────────────── */

function Ampliar({ fotos, i, listas, podeMexer, aoMudar, aoFechar, aoEditar, aoTirar }: {
    fotos: FotoDaViatura[]; i: number; listas: ListasDasFotos; podeMexer: boolean;
    aoMudar: (i: number) => void; aoFechar: () => void; aoEditar: (f: FotoDaViatura) => void; aoTirar: (f: FotoDaViatura) => void;
}) {
    const f = fotos[i];
    const andar = (passo: number) => aoMudar((i + passo + fotos.length) % fotos.length);
    const teclas = (e: KeyboardEvent<HTMLDivElement>) => {
        if (e.key === 'ArrowRight') { e.preventDefault(); andar(1); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); andar(-1); }
    };

    if (!f) return null;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={`${rotulo(listas.fases, f.fase)} · ${rotulo(listas.servicos, f.servico)}`}
            subtitulo={t(':n de :total', { n: i + 1, total: fotos.length })} icone="fa-image" cor="neutra" largura="xl"
            rodape={
                <>
                    <a href={f.url} target="_blank" rel="noreferrer" className={cls('inline-flex h-10 items-center justify-center gap-2 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:mr-auto', RAIO, FOCO)}>
                        <i className="fas fa-up-right-from-square" aria-hidden="true" />{t('Abrir o original')}
                    </a>
                    {podeMexer && <Botao cor="perigo" icone="fa-trash" onClick={() => aoTirar(f)}>{t('Tirar')}</Botao>}
                    {podeMexer && <Botao cor="primaria" icone="fa-pen" onClick={() => aoEditar(f)}>{t('Editar')}</Botao>}
                    <Botao onClick={aoFechar}>{t('Fechar')}</Botao>
                </>
            }>
            <div tabIndex={0} onKeyDown={teclas} className={cls('outline-none', FOCO)} aria-label={t('Use as setas para passar de fotografia')}>
                <div className="relative grid place-items-center overflow-hidden rounded-xl bg-slate-900">
                    <img key={f.url} src={f.url} alt={f.descricao ?? ''} className="animate-fade-in max-h-[65vh] w-auto max-w-full object-contain" />
                    {fotos.length > 1 && (
                        <>
                            <button type="button" onClick={() => andar(-1)} aria-label={t('Anterior')} className={cls('absolute left-2 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-700 shadow-lg hover:scale-110 hover:bg-white', TRANSICAO, FOCO)}>
                                <i className="fas fa-chevron-left" aria-hidden="true" />
                            </button>
                            <button type="button" onClick={() => andar(1)} aria-label={t('Seguinte')} className={cls('absolute right-2 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-700 shadow-lg hover:scale-110 hover:bg-white', TRANSICAO, FOCO)}>
                                <i className="fas fa-chevron-right" aria-hidden="true" />
                            </button>
                        </>
                    )}
                    <span className="absolute left-3 top-3"><SeloDaFase fase={f.fase} listas={listas} /></span>
                </div>
                <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
                    <div><dt className="text-xs text-slate-400">{t('Zona do carro')}</dt><dd className="font-medium text-slate-800">{f.zona ? rotulo(listas.zonas, f.zona) : '—'}</dd></div>
                    <div><dt className="text-xs text-slate-400">{t('Folha de obra')}</dt><dd className="font-mono font-medium text-slate-800">{f.ordem ?? '—'}</dd></div>
                    <div><dt className="text-xs text-slate-400">{t('Por')}</dt><dd className="font-medium text-slate-800">{f.por ?? '—'}</dd></div>
                    <div><dt className="text-xs text-slate-400">{t('Quando')}</dt><dd className="font-medium text-slate-800">{dataHora(f.em)}</dd></div>
                    {f.descricao && <div className="col-span-2 sm:col-span-4"><dt className="text-xs text-slate-400">{t('Descrição')}</dt><dd className="text-slate-700">{f.descricao}</dd></div>}
                    {f.origem === 'ordem' && <div className="col-span-2 sm:col-span-4 text-xs text-slate-500"><i className="fas fa-circle-info mr-1 text-indigo-400" aria-hidden="true" />{t('Anexo da folha de obra: muda-se ou tira-se na própria ordem de serviço.')}</div>}
                </dl>
                {fotos.length > 1 && (
                    <div className="mt-3 flex gap-2 overflow-x-auto pb-1">
                        {fotos.map((x, n) => (
                            <button key={x.id} type="button" onClick={() => aoMudar(n)} aria-label={t('Fotografia :n', { n: n + 1 })} aria-current={n === i}
                                className={cls('h-14 w-14 flex-none overflow-hidden rounded-lg ring-2', TRANSICAO, FOCO, n === i ? 'ring-indigo-500' : 'opacity-60 ring-transparent hover:opacity-100')}>
                                <img src={x.url} alt="" loading="lazy" className="h-full w-full object-cover" />
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </Modal>
    );
}

function EditarFoto({ f, listas, ordens, aGravar, erro, aoFechar, aoGravar }: {
    f: FotoDaViatura; listas: ListasDasFotos; ordens: Escolha[]; aGravar: boolean; erro: unknown;
    aoFechar: () => void; aoGravar: (d: DadosDaFoto) => void;
}) {
    const [d, porD] = useState<DadosDaFoto>({ fase: f.fase, servico: f.servico, zona: f.zona ?? '', ordem_id: f.ordem_id ? String(f.ordem_id) : '', descricao: f.descricao ?? '' });
    const caixa = cls('w-full border border-slate-300 bg-white px-3 py-2 text-sm', RAIO, FOCO);

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Editar fotografia')} icone="fa-pen" largura="md"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={aGravar} onClick={() => aoGravar(d)}>{t('Guardar')}</Botao></>}>
            <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                <img src={f.url} alt="" className={cls('aspect-square w-full bg-slate-100 object-cover', RAIO)} />
                <div className="space-y-3">
                    {erro instanceof ErroDaApi && <p role="alert" className={cls('border border-red-200 bg-red-50 p-2 text-sm text-red-800', RAIO)}>{Object.values(erro.erros ?? {})[0]?.[0] ?? erro.message}</p>}
                    <div role="radiogroup" aria-label={t('Fase do trabalho')} className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {listas.fases.map((x) => {
                            const fase = x.valor as FaseDaFoto;
                            return (
                                <button key={x.valor} type="button" role="radio" aria-checked={d.fase === fase} onClick={() => porD({ ...d, fase })}
                                    className={cls('flex items-center justify-center gap-1.5 px-2 py-2 text-xs font-semibold ring-1 ring-inset', RAIO, TRANSICAO, FOCO,
                                        d.fase === fase ? ASPECTO_DA_FASE[fase]?.activa : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50')}>
                                    <i className={cls('fas', ASPECTO_DA_FASE[fase]?.icone)} aria-hidden="true" />{x.rotulo}
                                </button>
                            );
                        })}
                    </div>
                    <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700">{t('Serviço')}</span>
                        <select value={d.servico} onChange={(e) => porD({ ...d, servico: e.target.value })} className={caixa}>
                            {listas.servicos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </label>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700">{t('Zona do carro')}</span>
                            <select value={d.zona} onChange={(e) => porD({ ...d, zona: e.target.value })} className={caixa}>
                                <option value="">{t('— sem zona —')}</option>
                                {listas.zonas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700">{t('Folha de obra')}</span>
                            <select value={d.ordem_id} onChange={(e) => porD({ ...d, ordem_id: e.target.value })} className={caixa}>
                                <option value="">{t('— nenhuma —')}</option>
                                {ordens.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                            </select>
                        </label>
                    </div>
                    <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700">{t('Descrição')}</span>
                        <textarea value={d.descricao} maxLength={500} rows={2} onChange={(e) => porD({ ...d, descricao: e.target.value })} className={caixa} />
                    </label>
                </div>
            </div>
        </Modal>
    );
}
