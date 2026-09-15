import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState, type MouseEvent } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { agendaDaOficina, ordens, type AgendaDaOficina, type MarcacaoDaAgenda, type MarcacaoParaGravar } from '@/api/oficina';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { etiquetaIntl, t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls } from '@/ui/tokens';

/**
 * A AGENDA DA OFICINA (15/09/2026, OF-05).
 *
 * DIA — uma coluna por elevador ou baia, as horas de cima a baixo, e cada
 * marcação é um bloco do tamanho do tempo que ocupa. Carregar num espaço vazio
 * marca ali mesmo. SEMANA — os sete dias lado a lado com a ocupação de cada um.
 * Quando o carro chega, «Chegou» abre a ordem de serviço.
 */

const PX_POR_HORA = 64;

const COR: Record<string, { bloco: string; ponto: string }> = {
    azul: { bloco: 'border-blue-400 bg-blue-50 text-blue-950', ponto: 'bg-blue-500' },
    verde: { bloco: 'border-emerald-400 bg-emerald-50 text-emerald-950', ponto: 'bg-emerald-500' },
    ambar: { bloco: 'border-amber-400 bg-amber-50 text-amber-950', ponto: 'bg-amber-500' },
    laranja: { bloco: 'border-orange-400 bg-orange-50 text-orange-950', ponto: 'bg-orange-500' },
    roxo: { bloco: 'border-purple-400 bg-purple-50 text-purple-950', ponto: 'bg-purple-500' },
    teal: { bloco: 'border-teal-400 bg-teal-50 text-teal-950', ponto: 'bg-teal-500' },
    vermelho: { bloco: 'border-red-400 bg-red-50 text-red-950', ponto: 'bg-red-500' },
    cinza: { bloco: 'border-slate-400 bg-slate-50 text-slate-900', ponto: 'bg-slate-400' },
};

const ICONE_DO_ESTADO: Record<string, string> = {
    marcada: 'fa-calendar', confirmada: 'fa-circle-check', chegou: 'fa-car-side', faltou: 'fa-user-xmark', cancelada: 'fa-ban',
};

/* ─── Datas, sempre na hora local ─── */
const dois = (n: number) => String(n).padStart(2, '0');
const diaIso = (d: Date) => `${d.getFullYear()}-${dois(d.getMonth() + 1)}-${dois(d.getDate())}`;
const deIso = (s: string) => { const [a, m, d] = s.slice(0, 10).split('-').map(Number); return new Date(a ?? 1970, (m ?? 1) - 1, d ?? 1); };
const somarDias = (d: Date, n: number) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
const segunda = (d: Date) => somarDias(d, -((d.getDay() + 6) % 7));
const minutos = (hhmm: string) => { const [h, m] = hhmm.split(':').map(Number); return (h ?? 0) * 60 + (m ?? 0); };
const horaDe = (iso: string) => iso.slice(11, 16);

const vazio = (dia: string, hora = '09:00', lugar = ''): MarcacaoParaGravar => ({
    vehicle_id: '', plate: '', customer_name: '', customer_phone: '', bay_id: lugar, mechanic_id: '', inicio: `${dia}T${hora}`, duracao: '60', service: '', notes: '',
});

export default function Agenda() {
    const cache = useQueryClient();
    const [vista, porVista] = useState<'dia' | 'semana'>(() => (typeof window !== 'undefined' && window.innerWidth < 640 ? 'semana' : 'dia'));
    const [dia, porDia] = useState(() => diaIso(new Date()));
    const [formulario, porFormulario] = useState<{ id: number | null; valores: MarcacaoParaGravar; marcacao: MarcacaoDaAgenda | null } | null>(null);

    const de = vista === 'dia' ? dia : diaIso(segunda(deIso(dia)));
    const ate = vista === 'dia' ? dia : diaIso(somarDias(segunda(deIso(dia)), 6));

    const q = useQuery({ queryKey: ['oficina', 'agenda', de, ate], queryFn: () => agendaDaOficina.ler(de, ate), placeholderData: keepPreviousData, refetchInterval: 120_000 });
    const opcoes = useQuery({ queryKey: ['oficina', 'ordens', 'opcoes'], queryFn: ordens.opcoes, staleTime: 5 * 60_000 });

    const refazer = () => void cache.invalidateQueries({ queryKey: ['oficina', 'agenda'] });

    const andar = (passo: number) => porDia(diaIso(somarDias(deIso(dia), vista === 'dia' ? passo : passo * 7)));

    if (q.isPending) return <Carregando linhas={10} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const d = q.data;
    const doDia = d.marcacoes.filter((m) => m.inicio.slice(0, 10) === dia);
    const activas = doDia.filter((m) => m.estado === 'marcada' || m.estado === 'confirmada' || m.estado === 'chegou');
    const capacidade = Math.max(1, d.lugares.length) * (minutos(d.horario.fecha) - minutos(d.horario.abre));
    const ocupacao = Math.min(100, Math.round((activas.filter((m) => m.bay_id).reduce((s, m) => s + m.duracao, 0) / capacidade) * 100));

    const titulo = vista === 'dia'
        ? deIso(dia).toLocaleDateString(etiquetaIntl(), { weekday: 'long', day: 'numeric', month: 'long' })
        : `${deIso(de).toLocaleDateString(etiquetaIntl(), { day: 'numeric', month: 'short' })} – ${deIso(ate).toLocaleDateString(etiquetaIntl(), { day: 'numeric', month: 'short', year: 'numeric' })}`;

    const abrirNova = (diaDaMarcacao = dia, hora = '09:00', lugar = '') => porFormulario({ id: null, valores: vazio(diaDaMarcacao, hora, lugar), marcacao: null });
    const abrirMarcacao = (m: MarcacaoDaAgenda) => porFormulario({
        id: m.id,
        marcacao: m,
        valores: {
            vehicle_id: m.vehicle_id ? String(m.vehicle_id) : '', plate: m.plate ?? '', customer_name: m.customer_name ?? '', customer_phone: m.customer_phone ?? '',
            bay_id: m.bay_id ? String(m.bay_id) : '', mechanic_id: m.mechanic_id ? String(m.mechanic_id) : '', inicio: m.inicio, duracao: String(m.duracao), service: m.servico, notes: m.notas ?? '',
        },
    });

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Agenda da Oficina')} subtitulo={t('As marcações por elevador e mecânico — e a ordem que se abre quando o carro chega')} icone="fa-calendar-days" cor="ciano"
                accoes={d.pode_criar && (
                    <button type="button" onClick={() => abrirNova()} className={cls(ACCAO_DA_FAIXA, 'group')}>
                        <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />{t('Nova marcação')}
                    </button>
                )} />

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" tom="indigo" icone="fa-calendar-day" rotulo={t('Marcações do dia')} valor={doDia.filter((m) => m.estado !== 'cancelada').length} />
                <CartaoNumero aspecto="claro" tom="laranja" icone="fa-phone" rotulo={t('Por confirmar')} valor={doDia.filter((m) => m.estado === 'marcada').length} />
                <CartaoNumero aspecto="claro" tom="verde" icone="fa-car-side" rotulo={t('Já chegaram')} valor={doDia.filter((m) => m.estado === 'chegou').length} />
                <CartaoNumero aspecto="claro" tom={ocupacao >= 90 ? 'vermelho' : 'teal'} icone="fa-gauge-high" rotulo={t('Ocupação dos lugares')} valor={`${ocupacao}%`}
                    nota={tn(':n lugar|:n lugares', d.lugares.length, { n: d.lugares.length })} />
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <div className={cls('inline-flex overflow-hidden border border-slate-200 bg-white shadow-sm', RAIO)}>
                    <button type="button" onClick={() => andar(-1)} aria-label={t('Anterior')} className={cls('px-3 py-2 text-slate-600 hover:bg-slate-50', FOCO)}><i className="fas fa-chevron-left" aria-hidden="true" /></button>
                    <button type="button" onClick={() => porDia(diaIso(new Date()))} className={cls('border-x border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', FOCO)}>{t('Hoje')}</button>
                    <button type="button" onClick={() => andar(1)} aria-label={t('Seguinte')} className={cls('px-3 py-2 text-slate-600 hover:bg-slate-50', FOCO)}><i className="fas fa-chevron-right" aria-hidden="true" /></button>
                </div>
                <input type="date" value={dia} onChange={(e) => e.target.value && porDia(e.target.value)} aria-label={t('Dia')} className={cls('border border-slate-300 bg-white px-3 py-2 text-sm', RAIO, FOCO)} />
                <h2 className="text-lg font-bold text-slate-800">{titulo.charAt(0).toUpperCase() + titulo.slice(1)}</h2>
                <div role="tablist" className={cls('ml-auto inline-flex bg-slate-100 p-0.5', RAIO)}>
                    {([['dia', 'fa-table-columns', t('Dia')], ['semana', 'fa-calendar-week', t('Semana')]] as const).map(([v, icone, nome]) => (
                        <button key={v} type="button" role="tab" aria-selected={vista === v} onClick={() => porVista(v)}
                            className={cls('inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold', TRANSICAO, FOCO, vista === v ? 'bg-white text-cyan-700 shadow' : 'text-slate-500 hover:text-slate-700')}>
                            <i className={cls('fas', icone)} aria-hidden="true" />{nome}
                        </button>
                    ))}
                </div>
            </div>

            {vista === 'dia'
                ? <VistaDoDia d={d} dia={dia} marcacoes={doDia} aoAbrir={abrirMarcacao} aoMarcar={(hora, lugar) => d.pode_criar && abrirNova(dia, hora, lugar)} />
                : <VistaDaSemana d={d} de={de} aoAbrir={abrirMarcacao} aoDia={(x) => { porDia(x); porVista('dia'); }} capacidade={capacidade} />}

            {formulario && opcoes.data && (
                <FormularioDaMarcacao d={d} opcoes={opcoes.data} f={formulario} aoFechar={() => porFormulario(null)} aoGravar={() => { porFormulario(null); refazer(); }} />
            )}
        </div>
    );
}

/* ─── O dia: uma coluna por lugar ─── */

function VistaDoDia({ d, dia, marcacoes, aoAbrir, aoMarcar }: {
    d: AgendaDaOficina; dia: string; marcacoes: MarcacaoDaAgenda[]; aoAbrir: (m: MarcacaoDaAgenda) => void; aoMarcar: (hora: string, lugar: string) => void;
}) {
    const abre = minutos(d.horario.abre);
    const fecha = minutos(d.horario.fecha);
    const horas = Array.from({ length: Math.ceil((fecha - abre) / 60) }, (_, i) => abre + i * 60);
    const altura = ((fecha - abre) / 60) * PX_POR_HORA;
    const semLugar = marcacoes.filter((m) => !m.bay_id);
    const colunas = [...d.lugares.map((l) => ({ valor: l.valor, rotulo: l.rotulo, cor: l.cor, tipo: l.tipo })), ...(semLugar.length ? [{ valor: '', rotulo: t('Sem lugar'), cor: 'cinza', tipo: '' }] : [])];

    const [agora, porAgora] = useState(() => new Date());
    useEffect(() => { const i = window.setInterval(() => porAgora(new Date()), 60_000); return () => window.clearInterval(i); }, []);
    const hoje = diaIso(agora) === dia;
    const linhaDeAgora = ((agora.getHours() * 60 + agora.getMinutes() - abre) / 60) * PX_POR_HORA;

    const clicar = (e: MouseEvent<HTMLDivElement>, lugar: string) => {
        if (e.target !== e.currentTarget) return;
        const y = e.clientY - e.currentTarget.getBoundingClientRect().top;
        const m = Math.max(abre, Math.min(fecha - 30, abre + Math.floor(((y / PX_POR_HORA) * 60) / 30) * 30));
        aoMarcar(`${dois(Math.floor(m / 60))}:${dois(m % 60)}`, lugar);
    };

    return (
        <div className={cls('overflow-x-auto border border-slate-200 bg-white shadow-sm', RAIO_GRANDE)}>
            <div className="flex min-w-max">
                <div className="w-14 flex-none border-r border-slate-100 pt-12">
                    <div className="relative" style={{ height: altura }}>
                        {horas.map((h, i) => <span key={h} style={{ top: i * PX_POR_HORA }} className="absolute right-2 -translate-y-1/2 text-[11px] tabular-nums text-slate-400">{`${dois(Math.floor(h / 60))}:00`}</span>)}
                    </div>
                </div>
                {colunas.map((c) => {
                    const daColuna = marcacoes.filter((m) => String(m.bay_id ?? '') === c.valor);
                    return (
                        <div key={c.valor || 'sem'} className="w-56 flex-none border-r border-slate-100 last:border-r-0">
                            <div className="sticky top-0 z-10 flex h-12 items-center gap-2 border-b border-slate-100 bg-white px-3">
                                <span className={cls('h-2.5 w-2.5 rounded-full', COR[c.cor]?.ponto)} aria-hidden="true" />
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-bold text-slate-800">{c.rotulo}</span>
                                    {c.tipo && <span className="block text-[10px] uppercase tracking-wide text-slate-400">{c.tipo}</span>}
                                </span>
                                <span className="ml-auto text-xs tabular-nums text-slate-400">{daColuna.filter((m) => m.estado !== 'cancelada').length}</span>
                            </div>
                            <div className={cls('relative', d.pode_criar && 'cursor-cell')} style={{ height: altura }} onClick={(e) => clicar(e, c.valor)}
                                role="presentation">
                                {horas.map((h, i) => <div key={h} className="pointer-events-none absolute inset-x-0 border-t border-slate-100" style={{ top: i * PX_POR_HORA }} aria-hidden="true"><div className="border-t border-dashed border-slate-50" style={{ marginTop: PX_POR_HORA / 2 }} /></div>)}
                                {hoje && linhaDeAgora > 0 && linhaDeAgora < altura && (
                                    <div className="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-red-500" style={{ top: linhaDeAgora }} aria-hidden="true"><span className="absolute -left-1 -top-1.5 h-3 w-3 rounded-full bg-red-500" /></div>
                                )}
                                {daColuna.map((m, i) => {
                                    const topo = Math.max(0, ((minutos(horaDe(m.inicio)) - abre) / 60) * PX_POR_HORA);
                                    const alto = Math.max(28, (m.duracao / 60) * PX_POR_HORA - 3);
                                    const apagada = m.estado === 'cancelada' || m.estado === 'faltou';
                                    return (
                                        <button key={m.id} type="button" onClick={() => aoAbrir(m)} style={{ top: topo, height: alto, ...cascata(i) }}
                                            className={cls('entra absolute inset-x-1.5 z-20 flex flex-col justify-start overflow-hidden border-l-4 p-1.5 text-left text-xs shadow-sm', RAIO, TRANSICAO, FOCO,
                                                'hover:z-30 hover:-translate-y-0.5 hover:shadow-lg', COR[m.cor ?? 'cinza']?.bloco,
                                                m.estado === 'marcada' && 'border-dashed', m.estado === 'chegou' && 'ring-2 ring-emerald-400', apagada && 'opacity-50')}>
                                            <span className="flex items-center gap-1 font-bold">
                                                <i className={cls('fas text-[10px]', ICONE_DO_ESTADO[m.estado])} aria-hidden="true" />
                                                <span className="tabular-nums">{horaDe(m.inicio)}–{horaDe(m.fim)}</span>
                                                <span className={cls('ml-auto truncate font-mono', apagada && 'line-through')}>{m.matricula}</span>
                                            </span>
                                            <span className={cls('block truncate', apagada && 'line-through')}>{m.servico}</span>
                                            {alto > 50 && <span className="block truncate opacity-70">{[m.cliente, m.mecanico].filter(Boolean).join(' · ')}</span>}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/* ─── A semana: sete colunas com a ocupação de cada dia ─── */

function VistaDaSemana({ d, de, capacidade, aoAbrir, aoDia }: {
    d: AgendaDaOficina; de: string; capacidade: number; aoAbrir: (m: MarcacaoDaAgenda) => void; aoDia: (dia: string) => void;
}) {
    const dias = useMemo(() => Array.from({ length: 7 }, (_, i) => diaIso(somarDias(deIso(de), i))), [de]);
    const hoje = diaIso(new Date());

    return (
        <div className="overflow-x-auto pb-2">
            <div className="grid min-w-[56rem] grid-cols-7 gap-2">
                {dias.map((x) => {
                    const doDia = d.marcacoes.filter((m) => m.inicio.slice(0, 10) === x);
                    const ocupado = doDia.filter((m) => m.bay_id && m.estado !== 'cancelada' && m.estado !== 'faltou').reduce((s, m) => s + m.duracao, 0);
                    const pct = Math.min(100, Math.round((ocupado / capacidade) * 100));
                    return (
                        <section key={x} className={cls('flex min-h-[18rem] flex-col border bg-white shadow-sm', RAIO_GRANDE, x === hoje ? 'border-cyan-400 ring-2 ring-cyan-100' : 'border-slate-200')}>
                            <button type="button" onClick={() => aoDia(x)} className={cls('border-b border-slate-100 p-2.5 text-left hover:bg-slate-50', FOCO)}>
                                <span className="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{deIso(x).toLocaleDateString(etiquetaIntl(), { weekday: 'short' })}</span>
                                <span className={cls('block text-xl font-bold', x === hoje ? 'text-cyan-700' : 'text-slate-800')}>{deIso(x).getDate()}</span>
                                <span className="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-slate-100" role="img" aria-label={t('Ocupação :p%', { p: pct })}>
                                    <span className={cls('block h-full transition-all duration-500', pct >= 90 ? 'bg-red-500' : pct >= 60 ? 'bg-amber-400' : 'bg-emerald-500')} style={{ width: `${pct}%` }} />
                                </span>
                                <span className="mt-0.5 block text-[10px] text-slate-400">{t('Ocupação :p%', { p: pct })}</span>
                            </button>
                            <ul className="flex-1 space-y-1.5 p-1.5">
                                {doDia.map((m, i) => (
                                    <li key={m.id} style={cascata(i)} className="entra">
                                        <button type="button" onClick={() => aoAbrir(m)}
                                            className={cls('block w-full border-l-4 p-1.5 text-left text-[11px] shadow-sm hover:shadow-md', RAIO, TRANSICAO, FOCO, COR[m.cor ?? 'cinza']?.bloco,
                                                m.estado === 'marcada' && 'border-dashed', (m.estado === 'cancelada' || m.estado === 'faltou') && 'opacity-50')}>
                                            <span className="flex items-center gap-1 font-bold"><i className={cls('fas text-[9px]', ICONE_DO_ESTADO[m.estado])} aria-hidden="true" />{horaDe(m.inicio)}<span className="ml-auto truncate font-mono">{m.matricula}</span></span>
                                            <span className="block truncate">{m.servico}</span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    );
                })}
            </div>
        </div>
    );
}

/* ─── Marcar e mudar ─── */

function FormularioDaMarcacao({ d, opcoes, f, aoFechar, aoGravar }: {
    d: AgendaDaOficina;
    opcoes: Awaited<ReturnType<typeof ordens.opcoes>>;
    f: { id: number | null; valores: MarcacaoParaGravar; marcacao: MarcacaoDaAgenda | null };
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const [v, porV] = useState<MarcacaoParaGravar>(f.valores);
    const [semViatura, porSemViatura] = useState(f.id !== null && !f.valores.vehicle_id);
    const [aApagar, porAApagar] = useState(false);
    const m = f.marcacao;
    const fechada = m?.estado === 'chegou';

    const gravar = useMutation({
        mutationFn: () => {
            const dados = { ...v, ...(semViatura ? { vehicle_id: '' } : { plate: '', customer_name: '' }) };
            return f.id ? agendaDaOficina.guardar(f.id, dados) : agendaDaOficina.criar(dados);
        },
        onSuccess: aoGravar,
    });
    const estado = useMutation({ mutationFn: (e: string) => agendaDaOficina.estado(f.id as number, e), onSuccess: aoGravar });
    const chegou = useMutation({ mutationFn: () => agendaDaOficina.chegou(f.id as number), onSuccess: (r) => window.location.assign(`/workshop/work-orders?ordem=${r.ordem_id}`) });
    const apagar = useMutation({ mutationFn: () => agendaDaOficina.apagar(f.id as number), onSuccess: aoGravar });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};
    const mudar = (k: keyof MarcacaoParaGravar, valor: string) => porV({ ...v, [k]: valor });
    const [data, hora] = [v.inicio.slice(0, 10), v.inicio.slice(11, 16)];
    const podeMexer = (f.id ? d.pode_editar : d.pode_criar) && !fechada;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={f.id ? t('Marcação') : t('Nova marcação')}
            subtitulo={m ? `${m.matricula ?? ''} · ${m.estado_rotulo}` : undefined} icone="fa-calendar-plus" cor="ciano" largura="lg"
            rodape={
                <>
                    {f.id && d.pode_editar && !fechada && <Botao cor="perigo" icone="fa-trash" className="sm:mr-auto" onClick={() => porAApagar(true)}>{t('Apagar')}</Botao>}
                    <Botao onClick={aoFechar}>{t('Fechar')}</Botao>
                    {podeMexer && <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar')}</Botao>}
                </>
            }>
            <div className="space-y-4">
                {/* O QUE SE FAZ A UMA MARCAÇÃO QUE JÁ EXISTE */}
                {m && (
                    <div className={cls('flex flex-wrap items-center gap-2 border border-slate-200 bg-slate-50 p-3', RAIO)}>
                        {fechada ? (
                            <a href={`/workshop/work-orders?ordem=${m.ordem_id}`} className={cls('inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:underline', FOCO, RAIO)}>
                                <i className="fas fa-clipboard-list" aria-hidden="true" />{t('Chegou — ordem :numero', { numero: m.ordem ?? '' })}<i className="fas fa-arrow-right text-xs" aria-hidden="true" />
                            </a>
                        ) : (
                            <>
                                {d.pode_criar && m.estado !== 'cancelada' && (
                                    <Botao cor="bom" tom="solida" icone="fa-car-side" aTrabalhar={chegou.isPending} onClick={() => chegou.mutate()}>{t('Chegou — abrir ordem')}</Botao>
                                )}
                                {d.pode_editar && m.estado === 'marcada' && <Botao cor="primaria" icone="fa-circle-check" onClick={() => estado.mutate('confirmada')}>{t('Confirmar')}</Botao>}
                                {d.pode_editar && (m.estado === 'marcada' || m.estado === 'confirmada') && <Botao cor="aviso" icone="fa-user-xmark" onClick={() => estado.mutate('faltou')}>{t('Faltou')}</Botao>}
                                {d.pode_editar && (m.estado === 'marcada' || m.estado === 'confirmada') && <Botao icone="fa-ban" onClick={() => estado.mutate('cancelada')}>{t('Cancelar marcação')}</Botao>}
                                {d.pode_editar && (m.estado === 'faltou' || m.estado === 'cancelada') && <Botao icone="fa-rotate-left" onClick={() => estado.mutate('marcada')}>{t('Voltar a marcar')}</Botao>}
                                {m.telefone && <a href={`tel:${m.telefone}`} className={cls('ml-auto inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-700 hover:underline', FOCO, RAIO)}><i className="fas fa-phone" aria-hidden="true" />{m.telefone}</a>}
                            </>
                        )}
                        <AvisoDeErro erro={estado.error ?? chegou.error} />
                    </div>
                )}

                <AvisoDeErro erro={gravar.error} />

                <fieldset disabled={!podeMexer} className="grid gap-4 sm:grid-cols-2">
                    <div className="flex items-center gap-2 sm:col-span-2">
                        <div role="tablist" className={cls('inline-flex bg-slate-100 p-0.5', RAIO)}>
                            {([[false, 'fa-car', t('Viatura da casa')], [true, 'fa-user-plus', t('Cliente novo')]] as const).map(([valor, icone, nome]) => (
                                <button key={String(valor)} type="button" role="tab" aria-selected={semViatura === valor} onClick={() => porSemViatura(valor)}
                                    className={cls('inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold', TRANSICAO, FOCO, semViatura === valor ? 'bg-white text-cyan-700 shadow' : 'text-slate-500')}>
                                    <i className={cls('fas', icone)} aria-hidden="true" />{nome}
                                </button>
                            ))}
                        </div>
                    </div>

                    {semViatura ? (
                        <>
                            <Campo etiqueta={t('Matrícula')} erro={erros.plate}><input value={v.plate} onChange={(e) => mudar('plate', e.target.value.toUpperCase())} className={cls(entrada, 'font-mono uppercase')} /></Campo>
                            <Campo etiqueta={t('Nome do cliente')} erro={erros.customer_name}><input value={v.customer_name} onChange={(e) => mudar('customer_name', e.target.value)} className={entrada} /></Campo>
                            <Campo etiqueta={t('Telefone')} erro={erros.customer_phone}><input type="tel" value={v.customer_phone} onChange={(e) => mudar('customer_phone', e.target.value)} className={entrada} /></Campo>
                        </>
                    ) : (
                        <Campo etiqueta={t('Viatura')} erro={erros.vehicle_id ?? erros.plate} className="sm:col-span-2">
                            <select value={v.vehicle_id} onChange={(e) => mudar('vehicle_id', e.target.value)} className={entrada}>
                                <option value="">—</option>
                                {opcoes.viaturas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}{x.dono ? ` · ${x.dono}` : ''}</option>)}
                            </select>
                        </Campo>
                    )}

                    <Campo etiqueta={t('Serviço')} erro={erros.service} className="sm:col-span-2">
                        <input value={v.service} list="servicos-da-agenda" onChange={(e) => mudar('service', e.target.value)} placeholder={t('Ex.: Revisão 10 000 km')} className={entrada} />
                        <datalist id="servicos-da-agenda">{opcoes.servicos.map((s) => <option key={s.valor} value={s.rotulo} />)}</datalist>
                    </Campo>

                    <Campo etiqueta={t('Dia')} erro={erros.inicio}><input type="date" value={data} onChange={(e) => mudar('inicio', `${e.target.value}T${hora}`)} className={entrada} /></Campo>
                    <div className="grid grid-cols-2 gap-3">
                        <Campo etiqueta={t('Hora')}><input type="time" step={900} value={hora} onChange={(e) => mudar('inicio', `${data}T${e.target.value}`)} className={entrada} /></Campo>
                        <Campo etiqueta={t('Duração')} erro={erros.duracao}>
                            <select value={v.duracao} onChange={(e) => mudar('duracao', e.target.value)} className={entrada}>
                                {[30, 60, 90, 120, 180, 240, 360, 480].map((n) => <option key={n} value={n}>{n < 60 ? `${n} min` : `${n / 60} h`}</option>)}
                            </select>
                        </Campo>
                    </div>

                    <Campo etiqueta={t('Elevador ou baia')} erro={erros.bay_id}>
                        <select value={v.bay_id} onChange={(e) => mudar('bay_id', e.target.value)} className={entrada}>
                            <option value="">{t('— sem lugar —')}</option>
                            {d.lugares.map((l) => <option key={l.valor} value={l.valor}>{l.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Mecânico')} erro={erros.mechanic_id}>
                        <select value={v.mechanic_id} onChange={(e) => mudar('mechanic_id', e.target.value)} className={entrada}>
                            <option value="">{t('Por atribuir')}</option>
                            {d.mecanicos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Notas')} className="sm:col-span-2">
                        <textarea rows={2} value={v.notes} onChange={(e) => mudar('notes', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                </fieldset>
            </div>

            <Modal aberto={aApagar} aoFechar={() => porAApagar(false)} titulo={t('Apagar esta marcação?')} icone="fa-trash" cor="perigo" largura="sm"
                rodape={<><Botao onClick={() => porAApagar(false)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => apagar.mutate()}>{t('Apagar')}</Botao></>}>
                <p className="text-sm text-slate-600">{t('Para guardar o registo de que o cliente não veio, use «Faltou» em vez de apagar.')}</p>
            </Modal>
        </Modal>
    );
}
