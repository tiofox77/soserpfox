import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { restaurante, type Mesa } from '@/api/restaurant';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { FOCO, RAIO, cls, dataHora, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';
import { SemTurno } from './PecasDaComanda';

/**
 * O MAPA DA SALA — que mesas há, quais estão ocupadas, e o que fazer a cada uma.
 *
 * TRÊS FILAS QUE NINGUÉM VIA JUNTAS e que agora estão no mesmo ecrã, porque é
 * o mesmo empregado a olhar para as três:
 *
 *   · as MESAS, com a comanda que cada uma tem aberta;
 *   · os PEDIDOS DA CARTA ONLINE, à espera de alguém que os aceite;
 *   · a FILA À PORTA, de quem chegou sem reserva.
 *
 * A COR DA MESA É O ESTADO e o ícone repete-o: quem não distingue verde de
 * âmbar tem de saber na mesma qual é a mesa livre.
 */

type CorDaMesa = { fundo: string; texto: string; icone: string };

const LIVRE: CorDaMesa = { fundo: 'bg-emerald-50 border-emerald-200 hover:border-emerald-400', texto: 'text-emerald-700', icone: 'fa-circle-check' };

const CORES_DA_MESA: Record<string, CorDaMesa> = {
    available: { fundo: 'bg-emerald-50 border-emerald-200 hover:border-emerald-400', texto: 'text-emerald-700', icone: 'fa-circle-check' },
    reserved: { fundo: 'bg-indigo-50 border-indigo-200 hover:border-indigo-400', texto: 'text-indigo-700', icone: 'fa-bookmark' },
    occupied: { fundo: 'bg-amber-50 border-amber-200 hover:border-amber-400', texto: 'text-amber-700', icone: 'fa-users' },
    waiting_kitchen: { fundo: 'bg-orange-50 border-orange-200 hover:border-orange-400', texto: 'text-orange-700', icone: 'fa-fire-burner' },
    served: { fundo: 'bg-sky-50 border-sky-200 hover:border-sky-400', texto: 'text-sky-700', icone: 'fa-utensils' },
    billing: { fundo: 'bg-violet-50 border-violet-200 hover:border-violet-400', texto: 'text-violet-700', icone: 'fa-file-invoice' },
    cleaning: { fundo: 'bg-slate-100 border-slate-300 hover:border-slate-400', texto: 'text-slate-600', icone: 'fa-broom' },
    blocked: { fundo: 'bg-red-50 border-red-200 hover:border-red-400', texto: 'text-red-700', icone: 'fa-ban' },
};

const mesaVazia = () => ({ code: '', name: '', capacity: '4', area_id: '' });

export default function Sala() {
    const cache = useQueryClient();

    const [casa, porCasa] = useState<number | ''>('');
    const [zona, porZona] = useState<number | ''>('');
    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [semTurno, porSemTurno] = useState(false);

    const [aAbrir, porAAbrir] = useState<Mesa | null>(null);
    const [pessoas, porPessoas] = useState('2');
    const [notas, porNotas] = useState('');

    const [novaMesa, porNovaMesa] = useState<ReturnType<typeof mesaVazia> | null>(null);
    const [aMudarEstado, porAMudarEstado] = useState<Mesa | null>(null);
    const [naFila, porNaFila] = useState<{ guest_name: string; phone: string; guest_count: string } | null>(null);
    const [aSentar, porASentar] = useState<number | null>(null);

    const opcoes = useQuery({
        queryKey: ['restaurante', 'sala', 'opcoes'],
        queryFn: restaurante.sala.opcoes,
        staleTime: 5 * 60_000,
    });

    // A sala muda sozinha: uma comanda fechada noutro posto tem de aparecer
    // aqui sem ninguém carregar em nada.
    const mapa = useQuery({
        queryKey: ['restaurante', 'sala', 'mapa', casa, zona],
        queryFn: () => restaurante.sala.mapa(casa, zona),
        refetchInterval: 20_000,
    });

    useEffect(() => {
        if (casa === '' && opcoes.data?.estabelecimentos[0]) {
            porCasa(Number(opcoes.data.estabelecimentos[0].valor));
        }
    }, [opcoes.data]);

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['restaurante', 'sala'] });

    const falhou = (e: unknown) => {
        if (e instanceof ErroDaApi && e.estado === 409 && (e.corpo as { falta_turno?: boolean }).falta_turno) {
            porSemTurno(true);

            return;
        }

        porErro(e);
        porErros(e instanceof ErroDaApi ? e.erros : {});
    };

    const irParaComanda = (id: number) => { window.location.href = `/restaurant/orders?order=${id}`; };

    const abrir = useMutation({
        mutationFn: () => restaurante.sala.abrir({
            table_id: aAbrir!.id, guest_count: Number(pessoas), notes: notas || undefined,
        }),
        onSuccess: (r) => { porAAbrir(null); irParaComanda(r.order_id); },
        onError: falhou,
    });

    const criarMesa = useMutation({
        mutationFn: () => restaurante.sala.criarMesa({ ...novaMesa, venue_id: casa, capacity: Number(novaMesa!.capacity) }),
        onSuccess: (r) => { porNovaMesa(null); porErros({}); porErro(null); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const estado = useMutation({
        mutationFn: ({ id, estado }: { id: number; estado: string }) => restaurante.sala.estadoDaMesa(id, estado),
        onSuccess: (r) => { porAMudarEstado(null); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const limpar = useMutation({
        mutationFn: (id: number) => restaurante.sala.limpar(id),
        onSuccess: (r) => { porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const aceitar = useMutation({
        mutationFn: (id: number) => restaurante.sala.aceitarPedido(id, casa === '' ? undefined : casa),
        onSuccess: (r) => irParaComanda(r.order_id),
        onError: falhou,
    });

    const descartar = useMutation({
        mutationFn: (id: number) => restaurante.sala.descartarPedido(id),
        onSuccess: (r) => { porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const chegou = useMutation({
        mutationFn: () => restaurante.sala.chegouAFila({ ...naFila, venue_id: casa, guest_count: Number(naFila!.guest_count) }),
        onSuccess: (r) => { porNaFila(null); porErros({}); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const sentar = useMutation({
        mutationFn: ({ id, mesa }: { id: number; mesa: number }) => restaurante.sala.sentar(id, mesa),
        onSuccess: (r) => { porASentar(null); irParaComanda(r.order_id); },
        onError: falhou,
    });

    const desistiu = useMutation({
        mutationFn: (id: number) => restaurante.sala.desistiu(id),
        onSuccess: (r) => { porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const m = mapa.data;
    const livres = m?.contagem.available ?? 0;

    /** Um toque na mesa: sentar quem espera, ou o caminho normal. */
    const tocarNaMesa = (mesa: Mesa) => {
        if (aSentar !== null) {
            sentar.mutate({ id: aSentar, mesa: mesa.id });

            return;
        }

        if (mesa.comanda) {
            irParaComanda(mesa.comanda.id);

            return;
        }

        if (mesa.estado === 'cleaning') {
            limpar.mutate(mesa.id);

            return;
        }

        if (mesa.estado === 'blocked') {
            porAMudarEstado(mesa);

            return;
        }

        porPessoas('2');
        porNotas('');
        porErros({});
        porAAbrir(mesa);
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Sala e Mesas')}
                subtitulo={t('Toque numa mesa para abrir, ver ou limpar')}
                icone="fa-chair"
                cor="laranja"
                accoes={
                    <>
                        <a href="/restaurant/pos" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-cash-register" aria-hidden="true" />
                            {t('Balcão')}
                        </a>
                        {o.permissoes.pode_abrir && (
                            <button
                                type="button"
                                className={ACCAO_DA_FAIXA}
                                onClick={() => { porErros({}); porNaFila({ guest_name: '', phone: '', guest_count: '2' }); }}
                            >
                                <i className="fas fa-person-walking-arrow-right" aria-hidden="true" />
                                {t('Chegou sem reserva')}
                            </button>
                        )}
                        {o.permissoes.pode_gerir && (
                            <button
                                type="button"
                                className={ACCAO_DA_FAIXA}
                                onClick={() => { porErros({}); porNovaMesa(mesaVazia()); }}
                            >
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Nova mesa')}
                            </button>
                        )}
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-circle-check">
                        {t(':n livres', { n: String(livres) })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-users">
                        {t(':n ocupadas', { n: String((m?.mesas.length ?? 0) - livres) })}
                    </EstadoNaFaixa>
                    {(m?.espera.length ?? 0) > 0 && (
                        <EstadoNaFaixa icone="fa-hourglass-half">
                            {t(':n à espera', { n: String(m?.espera.length) })}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            {aSentar !== null && (
                <p role="status" className={cls('animate-fade-in flex items-center justify-between gap-3 border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-medium text-indigo-800', RAIO)}>
                    <span><i className="fas fa-hand-pointer mr-2" aria-hidden="true" />{t('Toque na mesa onde ficam.')}</span>
                    <Botao altura="pequeno" onClick={() => porASentar(null)}>{t('Cancelar')}</Botao>
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Estabelecimento')} className="w-56">
                    <select
                        value={casa}
                        onChange={(e) => { porCasa(e.target.value ? Number(e.target.value) : ''); porZona(''); }}
                        className={entrada}
                    >
                        {o.estabelecimentos.map((v) => <option key={v.valor} value={v.valor}>{v.rotulo}</option>)}
                    </select>
                </Campo>

                {(m?.zonas.length ?? 0) > 0 && (
                    <Campo etiqueta={t('Zona')} className="w-48">
                        <select value={zona} onChange={(e) => porZona(e.target.value ? Number(e.target.value) : '')} className={entrada}>
                            <option value="">{t('Todas')}</option>
                            {m?.zonas.map((z) => <option key={z.valor} value={z.valor}>{z.rotulo}</option>)}
                        </select>
                    </Campo>
                )}
            </div>

            {mapa.isPending ? (
                <Carregando linhas={5} />
            ) : (m?.mesas.length ?? 0) === 0 ? (
                <Cartao titulo={t('Mesas')} icone="fa-chair">
                    <SemNada
                        icone="fa-chair"
                        titulo={t('Ainda não há mesas')}
                        frase={t('Crie as mesas desta sala para começar a atender.')}
                        accao={o.permissoes.pode_gerir && (
                            <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porNovaMesa(mesaVazia())}>
                                {t('Nova mesa')}
                            </Botao>
                        )}
                    />
                </Cartao>
            ) : (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    {m?.mesas.map((mesa, i) => {
                        const cor = CORES_DA_MESA[mesa.estado] ?? LIVRE;

                        return (
                            <div key={mesa.id} style={cascata(i)} className="entra relative">
                                <button
                                    type="button"
                                    onClick={() => tocarNaMesa(mesa)}
                                    className={cls(
                                        'card-hover flex w-full flex-col items-start gap-1 border-2 p-3 text-left shadow-sm',
                                        RAIO, FOCO, cor.fundo,
                                    )}
                                >
                                    <span className="flex w-full items-center justify-between gap-2">
                                        <span className="truncate text-base font-bold text-slate-900">
                                            {mesa.nome || mesa.codigo}
                                        </span>
                                        <i className={cls('fas', cor.icone, cor.texto)} aria-hidden="true" />
                                    </span>

                                    <span className={cls('text-xs font-semibold', cor.texto)}>{mesa.estado_rotulo}</span>

                                    <span className="text-xs text-slate-500">
                                        <i className="fas fa-user-group mr-1" aria-hidden="true" />
                                        {mesa.lugares}
                                        {mesa.zona && ` · ${mesa.zona}`}
                                    </span>

                                    {mesa.comanda && (
                                        <span className="mt-1 w-full border-t border-white/60 pt-1 text-xs">
                                            <span className="block truncate font-semibold text-slate-700">{mesa.comanda.numero}</span>
                                            <span className="block font-bold tabular-nums text-slate-900">
                                                {kz(mesa.comanda.total)} Kz
                                            </span>
                                        </span>
                                    )}
                                </button>

                                {o.permissoes.pode_gerir && (
                                    <button
                                        type="button"
                                        onClick={() => porAMudarEstado(mesa)}
                                        title={t('Mudar estado')}
                                        aria-label={t('Mudar estado de :m', { m: mesa.nome || mesa.codigo })}
                                        className={cls(
                                            'absolute right-1.5 top-1.5 grid h-6 w-6 place-items-center rounded-lg bg-white/70 text-slate-500',
                                            'opacity-0 transition hover:bg-white hover:text-slate-800 focus-visible:opacity-100 group-hover:opacity-100',
                                            'hover:opacity-100', FOCO,
                                        )}
                                    >
                                        <i className="fas fa-ellipsis text-xs" aria-hidden="true" />
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            <div className="grid gap-5 lg:grid-cols-2">
                {(m?.pedidos_da_carta.length ?? 0) > 0 && (
                    <Cartao
                        titulo={t('Pedidos da carta online')}
                        subtitulo={t('Viram a carta no telemóvel e pediram — ainda não é comanda')}
                        icone="fa-mobile-screen"
                        semPadding
                    >
                        <ul className="divide-y divide-slate-100">
                            {m?.pedidos_da_carta.map((p, i) => (
                                <li key={p.id} style={cascata(i)} className="entra px-5 py-3">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-slate-800">
                                                {p.mesa ? t('Mesa :m', { m: p.mesa }) : t('Sem mesa')}
                                                {p.cliente && <span className="ml-2 font-normal text-slate-500">· {p.cliente}</span>}
                                            </p>
                                            <p className="text-xs text-slate-500">{dataHora(p.criado_em)}</p>
                                        </div>
                                        <span className="text-sm font-bold tabular-nums text-slate-900">
                                            {kz(p.total_previsto)} Kz
                                        </span>
                                    </div>

                                    <ul className="mt-1.5 flex flex-wrap gap-1.5">
                                        {p.artigos.map((a, j) => (
                                            <li key={j} className="rounded-lg bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                                {a.quantidade}× {a.nome}
                                            </li>
                                        ))}
                                    </ul>

                                    {p.observacoes && <p className="mt-1 text-xs italic text-amber-700">« {p.observacoes} »</p>}

                                    <div className="mt-2 flex gap-2">
                                        <Botao
                                            altura="pequeno" cor="bom" tom="solida" icone="fa-check"
                                            aTrabalhar={aceitar.isPending}
                                            onClick={() => aceitar.mutate(p.id)}
                                        >
                                            {t('Aceitar')}
                                        </Botao>
                                        <Botao altura="pequeno" icone="fa-xmark" onClick={() => descartar.mutate(p.id)}>
                                            {t('Descartar')}
                                        </Botao>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Cartao>
                )}

                {(m?.espera.length ?? 0) > 0 && (
                    <Cartao
                        titulo={t('À espera de mesa')}
                        subtitulo={t('A ordem de chegada manda')}
                        icone="fa-hourglass-half"
                        semPadding
                    >
                        <ul className="divide-y divide-slate-100">
                            {m?.espera.map((e, i) => (
                                <li key={e.id} style={cascata(i)} className="entra flex items-center justify-between gap-3 px-5 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-800">{e.nome}</p>
                                        <p className="text-xs text-slate-500">
                                            {t(':n pessoas', { n: String(e.pessoas) })}
                                            {e.telefone && ` · ${e.telefone}`}
                                        </p>
                                    </div>

                                    <Etiqueta cor={e.minutos >= 30 ? 'perigo' : e.minutos >= 15 ? 'aviso' : 'neutra'} icone="fa-clock">
                                        {t(':n min', { n: String(e.minutos) })}
                                    </Etiqueta>

                                    <div className="flex flex-none gap-1">
                                        <Botao altura="pequeno" cor="bom" icone="fa-chair" onClick={() => porASentar(e.id)}>
                                            {t('Sentar')}
                                        </Botao>
                                        <Botao
                                            altura="pequeno" cor="perigo" icone="fa-person-walking-dashed-line-arrow-right"
                                            onClick={() => desistiu.mutate(e.id)}
                                            aria-label={t('Desistiu')}
                                            title={t('Desistiu')}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Cartao>
                )}
            </div>

            {/* ─── Abrir atendimento ─── */}
            <Modal
                aberto={aAbrir !== null}
                aoFechar={() => porAAbrir(null)}
                titulo={t('Abrir atendimento')}
                subtitulo={aAbrir ? (aAbrir.nome || aAbrir.codigo) : undefined}
                icone="fa-utensils"
                cor="laranja"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAAbrir(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={abrir.isPending} onClick={() => abrir.mutate()}>
                            {t('Abrir comanda')}
                        </Botao>
                    </>
                }
            >
                <div className="space-y-4">
                    <Campo
                        etiqueta={t('Quantas pessoas')}
                        obrigatorio
                        erro={erros.guest_count}
                        ajuda={aAbrir ? t('Esta mesa tem :n lugares.', { n: String(aAbrir.lugares) }) : undefined}
                    >
                        <input
                            type="number" min="1" max="100"
                            value={pessoas}
                            onChange={(e) => porPessoas(e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Observações')} erro={erros.notes}>
                        <textarea
                            value={notas}
                            onChange={(e) => porNotas(e.target.value)}
                            rows={2}
                            className={cls(entrada, 'h-auto py-2')}
                        />
                    </Campo>
                </div>
            </Modal>

            {/* ─── Nova mesa ─── */}
            <Modal
                aberto={novaMesa !== null}
                aoFechar={() => porNovaMesa(null)}
                titulo={t('Nova mesa')}
                subtitulo={t('O código é único dentro do estabelecimento')}
                icone="fa-plus"
                cor="laranja"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porNovaMesa(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={criarMesa.isPending} onClick={() => criarMesa.mutate()}>
                            {t('Criar')}
                        </Botao>
                    </>
                }
            >
                {novaMesa && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Código')} obrigatorio erro={erros.code}>
                            <input
                                value={novaMesa.code}
                                onChange={(e) => porNovaMesa({ ...novaMesa, code: e.target.value })}
                                className={entrada}
                                placeholder="M01"
                            />
                        </Campo>

                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input
                                value={novaMesa.name}
                                onChange={(e) => porNovaMesa({ ...novaMesa, name: e.target.value })}
                                className={entrada}
                                placeholder={t('Mesa 1')}
                            />
                        </Campo>

                        <Campo etiqueta={t('Lugares')} obrigatorio erro={erros.capacity}>
                            <input
                                type="number" min="1" max="50"
                                value={novaMesa.capacity}
                                onChange={(e) => porNovaMesa({ ...novaMesa, capacity: e.target.value })}
                                className={entrada}
                            />
                        </Campo>

                        <Campo etiqueta={t('Zona')} erro={erros.area_id}>
                            <select
                                value={novaMesa.area_id}
                                onChange={(e) => porNovaMesa({ ...novaMesa, area_id: e.target.value })}
                                className={entrada}
                            >
                                <option value="">{t('Sem zona')}</option>
                                {m?.zonas.map((z) => <option key={z.valor} value={z.valor}>{z.rotulo}</option>)}
                            </select>
                        </Campo>
                    </div>
                )}
            </Modal>

            {/* ─── Mudar estado da mesa ─── */}
            <Modal
                aberto={aMudarEstado !== null}
                aoFechar={() => porAMudarEstado(null)}
                titulo={t('Estado da mesa')}
                subtitulo={aMudarEstado ? (aMudarEstado.nome || aMudarEstado.codigo) : undefined}
                icone="fa-sliders"
                cor="neutra"
                largura="sm"
            >
                <div className="grid grid-cols-2 gap-2">
                    {o.estados_da_mesa.map((e) => (
                        <button
                            key={e.valor}
                            type="button"
                            disabled={estado.isPending}
                            onClick={() => aMudarEstado && estado.mutate({ id: aMudarEstado.id, estado: e.valor })}
                            className={cls(
                                'flex items-center gap-2 border-2 px-3 py-2.5 text-sm font-semibold transition-all duration-200',
                                RAIO, FOCO,
                                aMudarEstado?.estado === e.valor
                                    ? 'border-slate-800 bg-slate-800 text-white'
                                    : cls((CORES_DA_MESA[e.valor] ?? LIVRE).fundo,
                                        (CORES_DA_MESA[e.valor] ?? LIVRE).texto),
                            )}
                        >
                            <i className={`fas ${(CORES_DA_MESA[e.valor] ?? LIVRE).icone}`} aria-hidden="true" />
                            {e.rotulo}
                        </button>
                    ))}
                </div>
            </Modal>

            {/* ─── Chegou sem reserva ─── */}
            <Modal
                aberto={naFila !== null}
                aoFechar={() => porNaFila(null)}
                titulo={t('Chegou sem reserva')}
                subtitulo={t('Entra na fila; senta-se quando houver mesa')}
                icone="fa-person-walking-arrow-right"
                cor="primaria"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porNaFila(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={chegou.isPending} onClick={() => chegou.mutate()}>
                            {t('Pôr na fila')}
                        </Botao>
                    </>
                }
            >
                {naFila && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.guest_name}>
                            <input
                                value={naFila.guest_name}
                                onChange={(e) => porNaFila({ ...naFila, guest_name: e.target.value })}
                                className={entrada}
                            />
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Telefone')} erro={erros.phone} ajuda={t('Para avisar quando houver mesa.')}>
                                <input
                                    value={naFila.phone}
                                    onChange={(e) => porNaFila({ ...naFila, phone: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>

                            <Campo etiqueta={t('Pessoas')} obrigatorio erro={erros.guest_count}>
                                <input
                                    type="number" min="1" max="100"
                                    value={naFila.guest_count}
                                    onChange={(e) => porNaFila({ ...naFila, guest_count: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>
                        </div>
                    </div>
                )}
            </Modal>

            <SemTurno aberto={semTurno} aoFechar={() => porSemTurno(false)} />
        </div>
    );
}
