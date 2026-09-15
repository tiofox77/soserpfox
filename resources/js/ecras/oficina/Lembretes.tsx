import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import {
    lembretesDaOficina,
    type CanalDeContacto,
    type DefinicoesDosLembretes,
    type Lembrete,
    type LembretesDaOficina,
    type TipoDeLembrete,
} from '@/api/oficina';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { SemNada, cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data, haQuanto, kz } from '@/ui/tokens';

import { ChapaDaMatricula } from './ChapaDaMatricula';

/**
 * OS LEMBRETES DE MANUTENÇÃO (15/09/2026, OF-11).
 *
 * A lista de quem chamar hoje: revisões a chegar — pela data ou pelos km que o
 * carro terá agora, estimados pelo andamento entre visitas — e seguro,
 * inspecção e livrete a caducar. Cada linha diz o que vence, quando, e se já
 * alguém ligou; os botões chamam o cliente pelo canal que houver e registam o
 * contacto contra ESSE vencimento.
 */

type Filtro = 'todos' | 'vencidos' | 'documentos' | TipoDeLembrete;

const POR_PAGINA = 12;

const ASPECTO: Record<TipoDeLembrete, { icone: string; fundo: string; texto: string }> = {
    revisao: { icone: 'fa-oil-can', fundo: 'from-indigo-500 to-violet-600', texto: 'text-indigo-700' },
    seguro: { icone: 'fa-shield-halved', fundo: 'from-sky-500 to-blue-600', texto: 'text-sky-700' },
    inspeccao: { icone: 'fa-clipboard-check', fundo: 'from-emerald-500 to-teal-600', texto: 'text-emerald-700' },
    livrete: { icone: 'fa-id-card', fundo: 'from-amber-500 to-orange-500', texto: 'text-amber-700' },
    recomendacao: { icone: 'fa-hourglass-half', fundo: 'from-rose-500 to-pink-600', texto: 'text-rose-700' },
};

const ICONE_DO_CANAL: Record<CanalDeContacto, string> = {
    sms: 'fa-comment-sms', email: 'fa-envelope', whatsapp: 'fa-whatsapp', telefone: 'fa-phone', nota: 'fa-note-sticky',
};

const milhares = (n: number) => n.toLocaleString('pt-PT');

/** 923 456 789 → 244923456789 (o wa.me quer o indicativo). */
function numeroParaWhatsApp(telefone: string | null): string {
    const digitos = (telefone ?? '').replace(/\D/g, '');
    if (digitos.length === 9 && digitos.startsWith('9')) return `244${digitos}`;
    return digitos.length >= 11 ? digitos : '';
}

/** «Venceu há 3 dias», «Hoje», «Daqui a 12 dias» — e na revisão só por km, os km. */
function prazo(l: Lembrete): { texto: string; tom: 'vermelho' | 'ambar' | 'cinza' } {
    if (l.tipo === 'revisao' && l.faltam_km !== null && (l.dias === null || (l.faltam_km <= 0 && (l.dias ?? 0) > 0))) {
        return l.faltam_km <= 0
            ? { texto: t('Passou :km km', { km: milhares(-l.faltam_km) }), tom: 'vermelho' }
            : { texto: t('Faltam :km km', { km: milhares(l.faltam_km) }), tom: 'ambar' };
    }
    if (l.dias === null) return { texto: '—', tom: 'cinza' };
    if (l.dias < 0) return { texto: tn('Venceu há :n dia|Venceu há :n dias', -l.dias, { n: -l.dias }), tom: 'vermelho' };
    if (l.dias === 0) return { texto: t('Vence hoje'), tom: 'vermelho' };

    return { texto: tn('Daqui a :n dia|Daqui a :n dias', l.dias, { n: l.dias }), tom: l.dias <= 7 ? 'ambar' : 'cinza' };
}

function oQueVence(l: Lembrete): string {
    switch (l.tipo) {
        case 'revisao': {
            const partes = [l.km_previstos ? t('aos :km km', { km: milhares(l.km_previstos) }) : null, l.data ? t('a :data', { data: data(l.data) }) : null].filter(Boolean);
            return t('Revisão :quando', { quando: partes.join(` ${t('ou')} `) });
        }
        case 'seguro': return t('Seguro caduca a :data', { data: data(l.data) });
        case 'inspeccao': return t('Inspecção caduca a :data', { data: data(l.data) });
        case 'recomendacao': return tn(':n trabalho adiado por fazer|:n trabalhos adiados por fazer', l.trabalhos?.length ?? 0, { n: l.trabalhos?.length ?? 0 });
        default: return t('Livrete caduca a :data', { data: data(l.data) });
    }
}

export default function Lembretes() {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['oficina', 'lembretes'], queryFn: lembretesDaOficina.ler });
    const [filtro, porFiltro] = useState<Filtro>('todos');
    const [verContactados, porVerContactados] = useState(false);
    const [busca, porBusca] = useState('');
    const [pagina, porPagina] = useState(1);
    const [definicoes, porDefinicoes] = useState(false);
    const [aRever, porARever] = useState<Lembrete | null>(null);
    const [aNotar, porANotar] = useState<Lembrete | null>(null);

    const refazer = () => void cache.invalidateQueries({ queryKey: ['oficina', 'lembretes'] });
    const contacto = useMutation({
        mutationFn: (p: { l: Lembrete; canal: CanalDeContacto; nota?: string }) => lembretesDaOficina.contacto(p.l.viatura_id, { tipo: p.l.tipo, canal: p.canal, nota: p.nota }),
        onSuccess: () => { porANotar(null); refazer(); },
    });
    const adiar = useMutation({ mutationFn: (p: { l: Lembrete; dias: number }) => lembretesDaOficina.adiar(p.l.viatura_id, p.dias), onSuccess: refazer });

    const itens = q.data?.data;
    const visiveis = useMemo(() => {
        const termo = busca.trim().toLowerCase();
        return (itens ?? []).filter((l) => {
            if (!verContactados && l.ultimo_contacto) return false;
            if (filtro === 'vencidos' && !l.vencido) return false;
            if (filtro === 'documentos' && l.tipo === 'revisao') return false;
            if (filtro !== 'todos' && filtro !== 'vencidos' && filtro !== 'documentos' && l.tipo !== filtro) return false;
            return !termo || [l.matricula, l.dono, l.marca_modelo, l.telefone].some((x) => (x ?? '').toLowerCase().includes(termo));
        });
    }, [itens, filtro, verContactados, busca]);

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const d = q.data;
    const porChamar = d.data.filter((l) => !l.ultimo_contacto);
    const ultima = Math.max(1, Math.ceil(visiveis.length / POR_PAGINA));
    const actual = Math.min(pagina, ultima);
    const daPagina = visiveis.slice((actual - 1) * POR_PAGINA, actual * POR_PAGINA);
    const mudarFiltro = (f: Filtro) => { porFiltro(f); porPagina(1); };
    const semCanais = !d.canais.sms && !d.canais.email;

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Lembretes de Manutenção')} subtitulo={t('Quem chamar para a revisão e os documentos das viaturas a caducar')} icone="fa-bell" cor="aviso"
                accoes={d.pode_gerir && (
                    <button type="button" onClick={() => porDefinicoes(true)} className={cls(ACCAO_DA_FAIXA, 'group')}>
                        <i className="fas fa-sliders transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />{t('Definições')}
                    </button>
                )} />

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" tom="indigo" icone="fa-oil-can" rotulo={t('Revisões a chamar')} valor={porChamar.filter((l) => l.tipo === 'revisao').length} aoCarregar={() => mudarFiltro('revisao')} />
                <CartaoNumero aspecto="claro" tom="ambar" icone="fa-id-card" rotulo={t('Documentos a caducar')} valor={porChamar.filter((l) => l.tipo !== 'revisao').length} aoCarregar={() => mudarFiltro('documentos')} />
                <CartaoNumero aspecto="claro" tom="vermelho" icone="fa-triangle-exclamation" rotulo={t('Já vencidos')} valor={porChamar.filter((l) => l.vencido).length} aoCarregar={() => mudarFiltro('vencidos')} />
                <CartaoNumero aspecto="claro" tom="verde" icone="fa-circle-check" rotulo={t('Já contactados')} valor={d.data.length - porChamar.length} aoCarregar={() => { porVerContactados(true); porPagina(1); }} />
            </div>

            {/* COMO SAEM OS AVISOS — sem isto, «SMS» desligado parece defeito. */}
            <p className={cls('flex flex-wrap items-center gap-2 border px-3 py-2 text-xs', RAIO,
                d.definicoes.auto_reminders && !semCanais ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600')}>
                <i className={cls('fas', d.definicoes.auto_reminders && !semCanais ? 'fa-robot icon-float' : 'fa-circle-info')} aria-hidden="true" />
                {semCanais
                    ? t('SMS e email precisam do módulo Notificações configurado; o WhatsApp e o telefone funcionam sempre, a partir deste aparelho.')
                    : d.definicoes.auto_reminders
                        ? t('Os lembretes saem sozinhos uma vez por dia, por :canais, a quem ainda não foi contactado.', { canais: [d.canais.sms && 'SMS', d.canais.email && 'email'].filter(Boolean).join(` ${t('e')} `) })
                        : t('Os lembretes saem quando carrega nos botões. Nas Definições podem passar a sair sozinhos.')}
            </p>

            <div className={cls('flex flex-col gap-3 border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center', RAIO_GRANDE)}>
                <div className="-mx-1 flex flex-1 flex-wrap gap-1 px-1">
                    {([['todos', t('Todos'), 'fa-layer-group'], ['vencidos', t('Vencidos'), 'fa-triangle-exclamation'], ...d.tipos.map((x) => [x.valor, x.rotulo, ASPECTO[x.valor as TipoDeLembrete].icone])] as Array<[Filtro, string, string]>).map(([valor, rotulo, icone]) => (
                        <button key={valor} type="button" onClick={() => mudarFiltro(valor)} aria-pressed={filtro === valor}
                            className={cls('inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO,
                                filtro === valor ? 'bg-amber-500 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}>
                            <i className={cls('fas', icone)} aria-hidden="true" />{rotulo}
                        </button>
                    ))}
                </div>
                <label className="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" checked={verContactados} onChange={(e) => { porVerContactados(e.target.checked); porPagina(1); }} className="h-4 w-4 rounded border-slate-300 text-amber-500" />
                    {t('Mostrar os já contactados')}
                </label>
                <input type="search" value={busca} onChange={(e) => { porBusca(e.target.value); porPagina(1); }} placeholder={t('Matrícula, dono ou telefone')} className={cls(entrada, 'sm:w-56')} aria-label={t('Procurar')} />
            </div>

            {visiveis.length === 0 ? (
                <SemNada icone="fa-bell-slash" titulo={d.data.length === 0 ? t('Nada a lembrar') : t('Nenhum lembrete com estes filtros')}
                    frase={d.data.length === 0
                        ? t('Quando uma ordem fica concluída, a próxima revisão marca-se sozinha; as viaturas aparecem aqui quando ela ou um documento se aproximar.')
                        : t('Mude o filtro ou mostre os já contactados.')} />
            ) : (
                <>
                    <ul className="grid gap-3 xl:grid-cols-2">
                        {daPagina.map((l, i) => (
                            <LinhaDoLembrete key={l.chave} l={l} i={i} d={d} aTrabalhar={contacto.isPending || adiar.isPending}
                                contactar={(canal) => contacto.mutate({ l, canal })}
                                adiar={(dias) => adiar.mutate({ l, dias })}
                                rever={() => porARever(l)} notar={() => porANotar(l)} />
                        ))}
                    </ul>
                    <Paginacao emCartao pagina={actual} ultima={ultima} aMudar={porPagina} total={visiveis.length}
                        de={(actual - 1) * POR_PAGINA + 1} ate={Math.min(actual * POR_PAGINA, visiveis.length)} />
                </>
            )}

            {definicoes && <Definicoes inicial={d.definicoes} semCanais={semCanais} aoFechar={() => porDefinicoes(false)} aoGravar={() => { porDefinicoes(false); refazer(); }} />}
            {aRever && <ProximaRevisao l={aRever} aoFechar={() => porARever(null)} aoGravar={() => { porARever(null); refazer(); }} />}
            {aNotar && <NotaDoContacto l={aNotar} aTrabalhar={contacto.isPending} aoFechar={() => porANotar(null)} aoGravar={(nota) => contacto.mutate({ l: aNotar, canal: 'nota', nota })} />}
        </div>
    );
}

const BOTAO_PEQUENO = cls('inline-flex h-8 items-center gap-1.5 border px-2.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO, 'hover:-translate-y-0.5 hover:shadow active:translate-y-0 disabled:opacity-50');

function LinhaDoLembrete({ l, i, d, aTrabalhar, contactar, adiar, rever, notar }: {
    l: Lembrete;
    i: number;
    d: LembretesDaOficina;
    aTrabalhar: boolean;
    contactar: (canal: CanalDeContacto) => void;
    adiar: (dias: number) => void;
    rever: () => void;
    notar: () => void;
}) {
    const [menu, porMenu] = useState(false);
    const a = ASPECTO[l.tipo];
    const p = prazo(l);
    const whatsapp = numeroParaWhatsApp(l.telefone);
    const servico = l.tipo === 'revisao' ? t('Revisão') : l.tipo === 'inspeccao' ? t('Preparar para a inspecção') : l.tipo === 'recomendacao' ? (l.trabalhos ?? []).join(', ').slice(0, 250) : '';

    return (
        <li style={cascata(i)} className={cls('entra group card-hover flex flex-col gap-3 border bg-white p-4 shadow-sm', RAIO_GRANDE, TRANSICAO, 'hover:-translate-y-0.5 hover:shadow-lg',
            l.vencido && !l.ultimo_contacto ? 'border-red-200' : 'border-slate-200', (l.ultimo_contacto || l.pausado_ate) && 'opacity-80')}>
            <div className="flex items-start gap-3">
                <span className={cls('relative grid h-11 w-11 flex-none place-items-center rounded-xl bg-gradient-to-br text-lg text-white shadow', a.fundo)}>
                    <i className={cls('fas icon-float', a.icone)} aria-hidden="true" />
                    {l.vencido && (
                        <span className="absolute -right-1 -top-1 flex h-3 w-3" aria-hidden="true">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75" />
                            <span className="relative inline-flex h-3 w-3 rounded-full border-2 border-white bg-red-500" />
                        </span>
                    )}
                </span>
                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <ChapaDaMatricula matricula={l.matricula} tamanho="pequeno" />
                        <span className="truncate text-sm font-semibold text-slate-800">{l.marca_modelo}</span>
                    </div>
                    <p className={cls('text-sm font-bold', a.texto)}>{oQueVence(l)}</p>
                    {l.tipo === 'recomendacao' && l.trabalhos && (
                        <p className="text-xs text-slate-500">
                            <i className="fas fa-screwdriver-wrench mr-1" aria-hidden="true" />{l.trabalhos.slice(0, 3).join(' · ')}{l.trabalhos.length > 3 && ` +${l.trabalhos.length - 3}`}
                            {Boolean(l.valor) && <span className="ml-1 font-semibold text-slate-700">({kz(l.valor)})</span>}
                        </p>
                    )}
                    {l.tipo === 'revisao' && l.km_estimados !== null && l.km_previstos !== null && (
                        <p className="text-xs text-slate-500">
                            <i className="fas fa-gauge-high mr-1" aria-hidden="true" />
                            {l.km_por_dia
                                ? t('Hoje terá cerca de :km km (≈ :dia km por dia)', { km: milhares(l.km_estimados), dia: milhares(Math.round(l.km_por_dia)) })
                                : t('Última leitura: :km km', { km: milhares(l.km_estimados) })}
                        </p>
                    )}
                    <p className="break-words text-xs text-slate-500">
                        <i className="fas fa-user mr-1" aria-hidden="true" />{l.dono ?? '—'}
                        {l.telefone && <> · <i className="fas fa-phone mx-1" aria-hidden="true" />{l.telefone}</>}
                    </p>
                </div>
                <span className={cls('flex-none rounded-full px-2.5 py-1 text-[11px] font-bold',
                    p.tom === 'vermelho' ? 'bg-red-100 text-red-700' : p.tom === 'ambar' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600')}>
                    {p.texto}
                </span>
            </div>

            {(l.na_oficina || l.pausado_ate || l.ultimo_contacto) && (
                <div className="flex flex-wrap gap-1.5 text-[11px]">
                    {l.na_oficina && <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 font-semibold text-blue-700"><i className="fas fa-warehouse" aria-hidden="true" />{t('Está na oficina')}</span>}
                    {l.pausado_ate && <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600"><i className="fas fa-pause" aria-hidden="true" />{t('Adiado até :data', { data: data(l.pausado_ate) })}</span>}
                    {l.ultimo_contacto && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700" title={l.ultimo_contacto.nota ?? undefined}>
                            <i className={cls(l.ultimo_contacto.canal === 'whatsapp' ? 'fab' : 'fas', ICONE_DO_CANAL[l.ultimo_contacto.canal])} aria-hidden="true" />
                            {l.ultimo_contacto.canal_rotulo} · {haQuanto(l.ultimo_contacto.quando)}{l.ultimo_contacto.por ? ` · ${l.ultimo_contacto.por}` : ''}
                            {l.contactos > 1 && ` (${l.contactos})`}
                        </span>
                    )}
                </div>
            )}

            {d.pode_gerir && (
                <div className="flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3">
                    {d.canais.sms && l.telefone && (
                        <button type="button" disabled={aTrabalhar} onClick={() => contactar('sms')} className={cls(BOTAO_PEQUENO, 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100')}>
                            <i className="fas fa-comment-sms" aria-hidden="true" />{t('SMS')}
                        </button>
                    )}
                    {d.canais.email && l.email && (
                        <button type="button" disabled={aTrabalhar} onClick={() => contactar('email')} className={cls(BOTAO_PEQUENO, 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100')}>
                            <i className="fas fa-envelope" aria-hidden="true" />{t('Email')}
                        </button>
                    )}
                    {whatsapp && (
                        <a href={`https://wa.me/${whatsapp}?text=${encodeURIComponent(l.mensagem)}`} target="_blank" rel="noopener noreferrer" onClick={() => contactar('whatsapp')}
                            className={cls(BOTAO_PEQUENO, 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100')}>
                            <i className="fab fa-whatsapp" aria-hidden="true" />WhatsApp
                        </a>
                    )}
                    {l.telefone && (
                        <a href={`tel:${l.telefone.replace(/\s/g, '')}`} onClick={() => contactar('telefone')} className={cls(BOTAO_PEQUENO, 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50')}>
                            <i className="fas fa-phone" aria-hidden="true" />{t('Telefonar')}
                        </a>
                    )}
                    <a href={`/workshop/schedule?viatura=${l.viatura_id}${servico ? `&servico=${encodeURIComponent(servico)}` : ''}`}
                        className={cls(BOTAO_PEQUENO, 'border-cyan-200 bg-cyan-50 text-cyan-700 hover:bg-cyan-100')}>
                        <i className="fas fa-calendar-plus" aria-hidden="true" />{t('Marcar na agenda')}
                    </a>
                    <div className="relative ml-auto">
                        <button type="button" onClick={() => porMenu(!menu)} aria-expanded={menu} aria-label={t('Mais acções')}
                            className={cls(BOTAO_PEQUENO, 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50')}>
                            <i className="fas fa-ellipsis" aria-hidden="true" />
                        </button>
                        {menu && (
                            <div role="menu" onMouseLeave={() => porMenu(false)}
                                className={cls('entra absolute bottom-full right-0 z-20 mb-1 w-52 overflow-hidden border border-slate-200 bg-white py-1 text-sm shadow-xl', RAIO)}>
                                {l.tipo === 'revisao' && <ItemDoMenu icone="fa-oil-can" aoCarregar={() => { porMenu(false); rever(); }}>{t('Mudar a próxima revisão')}</ItemDoMenu>}
                                <ItemDoMenu icone="fa-note-sticky" aoCarregar={() => { porMenu(false); notar(); }}>{t('Registar um contacto')}</ItemDoMenu>
                                {l.pausado_ate
                                    ? <ItemDoMenu icone="fa-play" aoCarregar={() => { porMenu(false); adiar(0); }}>{t('Retomar os lembretes')}</ItemDoMenu>
                                    : [7, 30].map((n) => <ItemDoMenu key={n} icone="fa-clock" aoCarregar={() => { porMenu(false); adiar(n); }}>{tn('Adiar :n dia|Adiar :n dias', n, { n })}</ItemDoMenu>)}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </li>
    );
}

function ItemDoMenu({ icone, aoCarregar, children }: { icone: string; aoCarregar: () => void; children: string }) {
    return (
        <button type="button" role="menuitem" onClick={aoCarregar} className="flex w-full items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50">
            <i className={cls('fas w-4 text-center text-slate-400', icone)} aria-hidden="true" />{children}
        </button>
    );
}

function Definicoes({ inicial, semCanais, aoFechar, aoGravar }: { inicial: DefinicoesDosLembretes; semCanais: boolean; aoFechar: () => void; aoGravar: () => void }) {
    const [v, porV] = useState(inicial);
    const gravar = useMutation({ mutationFn: () => lembretesDaOficina.definicoes(v), onSuccess: aoGravar });
    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};
    const numero = (k: keyof DefinicoesDosLembretes, rotulo: string, ajuda: string, passo = 1) => (
        <Campo etiqueta={rotulo} ajuda={ajuda} erro={erros[k]}>
            <input type="number" min={0} step={passo} value={String(v[k])} onChange={(e) => porV({ ...v, [k]: Number(e.target.value || 0) })} className={entrada} />
        </Campo>
    );

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Definições dos lembretes')} subtitulo={t('O intervalo de revisão da oficina e com quanta antecedência se chama o cliente')} icone="fa-sliders" cor="aviso" largura="lg"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar')}</Botao></>}>
            <div className="space-y-5">
                <fieldset className="grid gap-3 sm:grid-cols-2">
                    <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500"><i className="fas fa-oil-can mr-1" aria-hidden="true" />{t('Revisão')}</legend>
                    {numero('service_interval_km', t('Revisão a cada (km)'), t('0 = não conta os km. Cada viatura pode ter o seu.'), 500)}
                    {numero('service_interval_months', t('Revisão a cada (meses)'), t('0 = não conta o tempo.'))}
                    {numero('remind_km_before', t('Chamar quando faltarem (km)'), t('Pelos km estimados de hoje.'), 100)}
                    {numero('remind_days_before', t('Chamar com antecedência (dias)'), t('Antes da data da revisão.'))}
                </fieldset>
                <fieldset className="grid gap-3 sm:grid-cols-2">
                    <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500"><i className="fas fa-id-card mr-1" aria-hidden="true" />{t('Documentos')}</legend>
                    {numero('documents_days_before', t('Avisar antes de caducar (dias)'), t('Seguro, inspecção e livrete.'))}
                </fieldset>
                <label className={cls('flex items-start gap-3 border p-3', RAIO, v.auto_reminders ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50')}>
                    <input type="checkbox" checked={v.auto_reminders} onChange={(e) => porV({ ...v, auto_reminders: e.target.checked })} className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600" />
                    <span className="text-sm">
                        <span className="block font-semibold text-slate-800">{t('Enviar os lembretes sozinhos')}</span>
                        <span className="block text-xs text-slate-500">
                            {semCanais
                                ? t('Precisa do módulo Notificações com o SMS ou o email configurados — até lá não sai nada.')
                                : t('Uma vez por dia, por SMS/email, a quem ainda não foi contactado para esse vencimento. Cada SMS é custo da empresa.')}
                        </span>
                    </span>
                </label>
            </div>
        </Modal>
    );
}

function ProximaRevisao({ l, aoFechar, aoGravar }: { l: Lembrete; aoFechar: () => void; aoGravar: () => void }) {
    const [v, porV] = useState({ proxima_data: l.data ?? '', proximo_km: l.km_previstos ? String(l.km_previstos) : '', intervalo_km: '', intervalo_meses: '' });
    const pedido = (feita: boolean) => lembretesDaOficina.revisao(l.viatura_id, {
        proxima_data: v.proxima_data || null,
        proximo_km: v.proximo_km === '' ? null : Number(v.proximo_km),
        intervalo_km: v.intervalo_km === '' ? null : Number(v.intervalo_km),
        intervalo_meses: v.intervalo_meses === '' ? null : Number(v.intervalo_meses),
        feita,
    });
    const gravar = useMutation({ mutationFn: pedido, onSuccess: aoGravar });
    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Próxima revisão')} subtitulo={`${l.matricula} · ${l.marca_modelo}`} icone="fa-oil-can" cor="primaria"
            rodape={
                <>
                    <Botao cor="bom" icone="fa-check-double" className="sm:mr-auto" aTrabalhar={gravar.isPending && gravar.variables === true} onClick={() => gravar.mutate(true)}>{t('Revisão feita hoje')}</Botao>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending && gravar.variables === false} onClick={() => gravar.mutate(false)}>{t('Guardar')}</Botao>
                </>
            }>
            <div className="grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('Aos km')} erro={erros.proximo_km}><input type="number" min={0} step={500} value={v.proximo_km} onChange={(e) => porV({ ...v, proximo_km: e.target.value })} className={entrada} /></Campo>
                <Campo etiqueta={t('Na data')} erro={erros.proxima_data}><input type="date" value={v.proxima_data} onChange={(e) => porV({ ...v, proxima_data: e.target.value })} className={entrada} /></Campo>
                <Campo etiqueta={t('Intervalo desta viatura (km)')} ajuda={t('Vazio = o da oficina.')} erro={erros.intervalo_km}><input type="number" min={0} step={500} value={v.intervalo_km} onChange={(e) => porV({ ...v, intervalo_km: e.target.value })} className={entrada} /></Campo>
                <Campo etiqueta={t('Intervalo desta viatura (meses)')} ajuda={t('Vazio = o da oficina.')} erro={erros.intervalo_meses}><input type="number" min={0} max={60} value={v.intervalo_meses} onChange={(e) => porV({ ...v, intervalo_meses: e.target.value })} className={entrada} /></Campo>
                <p className="text-xs text-slate-500 sm:col-span-2">
                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                    {t('«Revisão feita hoje» serve para quando a revisão se fez noutro lado: conta a partir dos km de agora e do intervalo.')}
                </p>
            </div>
        </Modal>
    );
}

function NotaDoContacto({ l, aTrabalhar, aoFechar, aoGravar }: { l: Lembrete; aTrabalhar: boolean; aoFechar: () => void; aoGravar: (nota: string) => void }) {
    const [nota, porNota] = useState('');

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Registar um contacto')} subtitulo={`${l.matricula} · ${oQueVence(l)}`} icone="fa-note-sticky" cor="aviso"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={aTrabalhar} onClick={() => aoGravar(nota)}>{t('Registar')}</Botao></>}>
            <Campo etiqueta={t('O que ficou combinado')} ajuda={t('Ex.: Ligou a dizer que marca para a semana.')}>
                <textarea rows={3} maxLength={500} value={nota} onChange={(e) => porNota(e.target.value)} className={entrada} />
            </Campo>
        </Modal>
    );
}
