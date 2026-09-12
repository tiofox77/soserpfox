import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type AvisoDaPlataforma, type FichaDoAviso, type Nomeado, ferramentas } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { Confirmar, ErroDoEcra, Interruptor, Paginas, Recado } from './comum';

const ESTILO: Record<string, { fundo: string; icone: string }> = {
    urgente: { fundo: 'bg-red-100 text-red-600', icone: 'fa-triangle-exclamation' },
    aviso: { fundo: 'bg-amber-100 text-amber-600', icone: 'fa-circle-exclamation' },
    sucesso: { fundo: 'bg-emerald-100 text-emerald-600', icone: 'fa-circle-check' },
    info: { fundo: 'bg-blue-100 text-blue-600', icone: 'fa-circle-info' },
};

const SITUACAO = (s: AvisoDaPlataforma['situacao']) => ({
    retirada: { rotulo: t('Retirada'), cor: 'neutra' as const },
    terminada: { rotulo: t('Terminada'), cor: 'neutra' as const },
    agendada: { rotulo: t('Agendada'), cor: 'primaria' as const },
    no_ar: { rotulo: t('No ar'), cor: 'bom' as const },
}[s]);

/** «2026-09-14 10:20» → «14/09/2026 10:20», sem passar por fuso nenhum: é a hora da parede. */
const parede = (valor: string | null) => (valor ? valor.replace(/^(\d{4})-(\d{2})-(\d{2})/, '$3/$2/$1') : null);

/**
 * AS MENSAGENS DO DONO DA PLATAFORMA PARA AS EMPRESAS.
 *
 * Antes disto uma paragem, uma mudança de preços ou uma obrigação nova da AGT
 * saíam por WhatsApp, empresa a empresa, e ninguém sabia quem tinha lido.
 * Cada mensagem diz a quantas empresas chega, quantas pessoas a viram e
 * quantas a dispensaram; e o formulário mostra o alcance ANTES de publicar.
 */
export default function Avisos() {
    const fila = useQueryClient();
    const [pagina, porPagina] = useState(1);
    const [recado, porRecado] = useState<string | null>(null);
    const [aEditar, porAEditar] = useState<number | 'nova' | null>(null);
    const [leiturasDe, porLeiturasDe] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<AvisoDaPlataforma | null>(null);

    const lista = useQuery({ queryKey: ['plataforma', 'avisos', pagina], queryFn: () => ferramentas.avisos.ler(pagina), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'avisos'] }); };

    const alternar = useMutation({ mutationFn: (id: number) => ferramentas.avisos.alternar(id), onSuccess: (r) => feito(r.message) });
    const apagar = useMutation({ mutationFn: (id: number) => ferramentas.avisos.apagar(id), onSuccess: (r) => { porAApagar(null); feito(r.message); } });

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <ErroDoEcra titulo={t('Não foi possível abrir as mensagens às empresas')} erro={lista.error} />;

    const d = lista.data;
    const noAr = d.mensagens.filter((m) => m.situacao === 'no_ar').length;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Mensagens às empresas')}
                subtitulo={t('Avisos, novidades e alertas que aparecem dentro do sistema')}
                icone="fa-bullhorn"
                cor="roxo"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porAEditar('nova')}>
                        <i className="fas fa-plus" aria-hidden="true" />{t('Escrever mensagem')}
                    </button>
                }
            >
                <EstadoNaFaixa icone="fa-tower-broadcast">{t(':n no ar nesta página', { n: noAr })}</EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={alternar.error} />

            <section className={cls(CARTAO, 'overflow-hidden')}>
                {d.mensagens.length === 0 ? (
                    <SemNada icone="fa-bullhorn" titulo={t('Ainda não escreveu nenhuma mensagem.')}
                        accao={<Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porAEditar('nova')}>{t('Escrever mensagem')}</Botao>} />
                ) : d.mensagens.map((m, i) => {
                    const e = ESTILO[m.nivel] ?? ESTILO.info!;
                    const s = SITUACAO(m.situacao);
                    const publico = d.opcoes.publicos.find((p) => p.valor === m.publico)?.rotulo ?? m.publico;

                    return (
                        <article key={m.id} className={cls('cascata border-b border-slate-100 p-5 last:border-0 hover:bg-slate-50/60', TRANSICAO)} style={cascata(i)}>
                            <div className="flex flex-wrap items-start gap-4">
                                <span className={cls('grid h-10 w-10 flex-none place-items-center', RAIO, e.fundo)}>
                                    <i className={cls('fas icon-float', e.icone)} aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="mb-1 flex flex-wrap items-center gap-2">
                                        <h3 className="font-bold text-slate-900">{m.titulo}</h3>
                                        <Etiqueta cor={s.cor} ponto>{s.rotulo}</Etiqueta>
                                        <Etiqueta icone={m.forma === 'popup' ? 'fa-window-restore' : 'fa-grip-lines'}>{m.forma === 'popup' ? t('Pop-up') : t('Barra')}</Etiqueta>
                                        {!m.dispensavel && <Etiqueta cor="aviso" icone="fa-lock">{t('Não se fecha')}</Etiqueta>}
                                    </div>
                                    <p className="line-clamp-2 whitespace-pre-line text-sm text-slate-600">{m.corpo}</p>
                                    <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-slate-500">
                                        <span><i className="fas fa-users mr-1" aria-hidden="true" />{publico} ({t(':n empresa(s)', { n: m.alcance })})</span>
                                        <span><i className="fas fa-eye mr-1" aria-hidden="true" />{t(':n viram', { n: m.vistas })}</span>
                                        <span><i className="fas fa-check mr-1" aria-hidden="true" />{t(':n dispensaram', { n: m.dispensadas })}</span>
                                        {(m.comeca || m.termina) && (
                                            <span><i className="fas fa-calendar mr-1" aria-hidden="true" />{parede(m.comeca) ?? t('desde já')} {t('até')} {parede(m.termina) ?? t('sem fim')}</span>
                                        )}
                                        {m.autor && <span><i className="fas fa-user-pen mr-1" aria-hidden="true" />{m.autor}</span>}
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Botao altura="pequeno" icone="fa-list-check" onClick={() => porLeiturasDe(leiturasDe === m.id ? null : m.id)} aria-expanded={leiturasDe === m.id}>{t('Quem viu')}</Botao>
                                    <Botao altura="pequeno" cor="primaria" icone="fa-pen" onClick={() => porAEditar(m.id)}>{t('Editar')}</Botao>
                                    <Botao altura="pequeno" cor={m.activa ? 'aviso' : 'bom'} icone={m.activa ? 'fa-eye-slash' : 'fa-eye'} aTrabalhar={alternar.isPending && alternar.variables === m.id} onClick={() => alternar.mutate(m.id)}>
                                        {m.activa ? t('Retirar') : t('Pôr no ar')}
                                    </Botao>
                                    <Botao altura="pequeno" cor="perigo" icone="fa-trash" onClick={() => porAApagar(m)}>{t('Apagar')}</Botao>
                                </div>
                            </div>
                            {leiturasDe === m.id && <Leituras id={m.id} />}
                        </article>
                    );
                })}
                <div className="px-5 pb-4">
                    <Paginas pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} aMudar={porPagina} />
                </div>
            </section>

            {aEditar !== null && (
                <Formulario
                    id={aEditar === 'nova' ? null : aEditar}
                    opcoes={d.opcoes}
                    aoFechar={() => porAEditar(null)}
                    aoGuardar={(msg) => { porAEditar(null); feito(msg); }}
                />
            )}

            <Confirmar
                aberto={aApagar !== null}
                titulo={t('Apagar a mensagem?')}
                subtitulo={aApagar?.titulo}
                rotulo={t('Apagar')}
                aTrabalhar={apagar.isPending}
                erro={apagar.error}
                aoConfirmar={() => aApagar && apagar.mutate(aApagar.id)}
                aoFechar={() => porAApagar(null)}
            >
                <p>{t('A mensagem sai do ar e perde-se o registo de quem a viu. Para só a esconder, use «Retirar».')}</p>
            </Confirmar>
        </div>
    );
}

function Leituras({ id }: { id: number }) {
    const pedido = useQuery({ queryKey: ['plataforma', 'avisos', 'leituras', id], queryFn: () => ferramentas.avisos.leituras(id) });

    return (
        <div className={cls('entra mt-4 border border-slate-200 bg-slate-50 p-4', RAIO)}>
            {pedido.isPending && <Carregando linhas={2} />}
            <AvisoDeErro erro={pedido.error} />
            {pedido.data && (pedido.data.leituras.length === 0 ? (
                <p className="text-sm text-slate-500">{t('Ainda ninguém abriu esta mensagem.')}</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="text-left text-xs uppercase text-slate-500">
                            <tr><th className="pb-2">{t('Pessoa')}</th><th className="pb-2">{t('Viu')}</th><th className="pb-2">{t('Dispensou')}</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {pedido.data.leituras.map((l) => (
                                <tr key={l.id}>
                                    <td className="py-1.5 text-slate-800">{l.nome ?? t('Utilizador apagado')} <span className="text-xs text-slate-500">{l.email}</span></td>
                                    <td className="py-1.5 tabular-nums text-slate-600">{parede(l.vista_em) ?? '—'}</td>
                                    <td className="py-1.5 tabular-nums text-slate-600">{parede(l.dispensada_em) ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ))}
        </div>
    );
}

const VAZIA: FichaDoAviso = {
    title: '', body: '', level: 'info', display: 'popup', audience: 'todas',
    tenant_ids: [], plan_ids: [], starts_at: '', ends_at: '',
    dismissible: true, link_url: '', link_label: '', is_active: true,
};

function Formulario({ id, opcoes, aoFechar, aoGuardar }: {
    id: number | null;
    opcoes: { niveis: Array<{ valor: string; rotulo: string }>; formas: Array<{ valor: string; rotulo: string }>; publicos: Array<{ valor: string; rotulo: string }>; empresas: Nomeado[]; planos: Nomeado[] };
    aoFechar: () => void;
    aoGuardar: (m: string) => void;
}) {
    const [f, porF] = useState<FichaDoAviso | null>(id ? null : VAZIA);
    const [procuraEmpresa, porProcuraEmpresa] = useState('');

    const ficha = useQuery({ queryKey: ['plataforma', 'avisos', 'ficha', id], queryFn: () => ferramentas.avisos.ficha(id as number), enabled: id !== null });
    useEffect(() => { if (ficha.data) porF(ficha.data.ficha); }, [ficha.data]);

    const alcance = useQuery({
        queryKey: ['plataforma', 'avisos', 'alcance', f?.audience, f?.tenant_ids, f?.plan_ids],
        queryFn: () => ferramentas.avisos.alcance({ audience: f!.audience, tenant_ids: f!.tenant_ids, plan_ids: f!.plan_ids }),
        enabled: f !== null,
        placeholderData: keepPreviousData,
    });

    const guardar = useMutation({ mutationFn: () => ferramentas.avisos.guardar(id, { ...f }), onSuccess: (r) => aoGuardar(r.message) });
    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};

    const alternarId = (campo: 'tenant_ids' | 'plan_ids', valor: number) => porF((a) => a && ({
        ...a, [campo]: a[campo].includes(valor) ? a[campo].filter((x) => x !== valor) : [...a[campo], valor],
    }));

    const empresas = opcoes.empresas.filter((e) => e.nome.toLowerCase().includes(procuraEmpresa.trim().toLowerCase()));

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={id ? t('Editar mensagem') : t('Escrever mensagem')}
            subtitulo={t('Avisos, novidades e alertas que aparecem dentro do sistema')}
            icone="fa-bullhorn"
            cor="roxo"
            largura="lg"
            rodape={
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-sm text-slate-600">
                        {alcance.data && <><i className="fas fa-users mr-1 text-indigo-500" aria-hidden="true" />{t('Chega a :n empresa(s)', { n: alcance.data.empresas })}</>}
                    </span>
                    <div className="flex gap-2">
                        <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-paper-plane" aTrabalhar={guardar.isPending} disabled={!f} onClick={() => guardar.mutate()}>
                            {id ? t('Guardar') : t('Publicar')}
                        </Botao>
                    </div>
                </div>
            }
        >
            {!f ? <Carregando linhas={6} /> : (
                <div className="space-y-4">
                    <AvisoDeErro erro={ficha.error ?? (Object.keys(erros).length ? null : guardar.error)} />

                    <Campo etiqueta={t('Título')} obrigatorio erro={erros.title}>
                        <input className={entrada} value={f.title} maxLength={255} onChange={(e) => porF({ ...f, title: e.target.value })} placeholder={t('Ex.: Manutenção no domingo, das 22h à 1h')} />
                    </Campo>

                    <Campo etiqueta={t('Mensagem')} obrigatorio erro={erros.body} ajuda={t(':n de 5000 caracteres', { n: f.body.length })}>
                        <textarea rows={5} className={entrada} maxLength={5000} value={f.body} onChange={(e) => porF({ ...f, body: e.target.value })} placeholder={t('O que precisa de ser dito, em português simples.')} />
                    </Campo>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Campo etiqueta={t('Tom')} erro={erros.level}>
                            <select className={entrada} value={f.level} onChange={(e) => porF({ ...f, level: e.target.value })}>
                                {opcoes.niveis.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Como aparece')} erro={erros.display}>
                            <select className={entrada} value={f.display} onChange={(e) => porF({ ...f, display: e.target.value })}>
                                {opcoes.formas.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                            </select>
                        </Campo>
                    </div>

                    <Campo etiqueta={t('Para quem')} erro={erros.audience}>
                        <select className={entrada} value={f.audience} onChange={(e) => porF({ ...f, audience: e.target.value })}>
                            {opcoes.publicos.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                        </select>
                    </Campo>

                    {f.audience === 'empresas' && (
                        <div>
                            <Rotulo>{t('Empresas')} ({f.tenant_ids.length})</Rotulo>
                            <input type="search" className={cls(entrada, 'mb-2')} placeholder={t('Procurar empresa…')} value={procuraEmpresa} onChange={(e) => porProcuraEmpresa(e.target.value)} />
                            <div className={cls('grid max-h-56 gap-1 overflow-y-auto border border-slate-200 p-2 sm:grid-cols-2', RAIO)}>
                                {empresas.map((e) => (
                                    <label key={e.id} className={cls('flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50', f.tenant_ids.includes(e.id) && 'bg-indigo-50')}>
                                        <input type="checkbox" className="rounded text-indigo-600" checked={f.tenant_ids.includes(e.id)} onChange={() => alternarId('tenant_ids', e.id)} />
                                        <span className="truncate">{e.nome}</span>
                                    </label>
                                ))}
                            </div>
                            {erros.tenant_ids && <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros.tenant_ids[0]}</p>}
                        </div>
                    )}

                    {f.audience === 'planos' && (
                        <div>
                            <Rotulo>{t('Planos')}</Rotulo>
                            <div className="flex flex-wrap gap-2">
                                {opcoes.planos.map((p) => (
                                    <label key={p.id} className={cls('flex cursor-pointer items-center gap-2 border px-3 py-2 text-sm', RAIO, TRANSICAO, f.plan_ids.includes(p.id) ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50')}>
                                        <input type="checkbox" className="rounded text-indigo-600" checked={f.plan_ids.includes(p.id)} onChange={() => alternarId('plan_ids', p.id)} />
                                        <span className="font-semibold text-slate-700">{p.nome}</span>
                                    </label>
                                ))}
                            </div>
                            {erros.plan_ids && <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros.plan_ids[0]}</p>}
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-2">
                        <Campo etiqueta={t('Começa')} erro={erros.starts_at} ajuda={t('Em branco: já.')}>
                            <input type="datetime-local" className={entrada} value={f.starts_at} onChange={(e) => porF({ ...f, starts_at: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Termina')} erro={erros.ends_at} ajuda={t('Uma mensagem sem fim deixa de ser lida ao fim de dois dias.')}>
                            <input type="datetime-local" className={entrada} value={f.ends_at} min={f.starts_at || undefined} onChange={(e) => porF({ ...f, ends_at: e.target.value })} />
                        </Campo>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Campo etiqueta={t('Ligação (opcional)')} erro={erros.link_url}>
                            <input type="url" className={entrada} value={f.link_url} onChange={(e) => porF({ ...f, link_url: e.target.value })} placeholder="https://…" />
                        </Campo>
                        <Campo etiqueta={t('Texto do botão')} erro={erros.link_label}>
                            <input className={entrada} maxLength={80} value={f.link_label} onChange={(e) => porF({ ...f, link_label: e.target.value })} placeholder={t('Saber mais')} />
                        </Campo>
                    </div>

                    <div className="grid gap-2 md:grid-cols-2">
                        <Interruptor cor="indigo" rotulo={t('Pode ser dispensada.')} valor={f.dismissible} aoMudar={(v) => porF({ ...f, dismissible: v })}
                            nota={t('Desligue só para mensagens que exijam que alguém faça alguma coisa — uma que não se fecha é uma que se odeia.')} />
                        <Interruptor rotulo={t('No ar.')} valor={f.is_active} aoMudar={(v) => porF({ ...f, is_active: v })} nota={t('Desligue para preparar sem publicar.')} />
                    </div>
                </div>
            )}
        </Modal>
    );
}
