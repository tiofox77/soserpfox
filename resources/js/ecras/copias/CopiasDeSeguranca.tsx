import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';

import { apiDasCopias, type Ambito, type Copia, type PainelDasCopias } from '@/api/copias';
import { ErroDaApi } from '@/api/cliente';
import { avisar } from '@/casca/avisos';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { AplicacoesOAuth } from './AplicacoesOAuth';
import { ORIGENS, dataHora, relativo, tamanho } from './comum';
import { Destinos } from './Destinos';
import { ModalDeRepor, estadoDoRestauro, type OrigemDoRestauro } from './Repor';

/**
 * AS CÓPIAS DE SEGURANÇA — o mesmo ecrã para o dono da plataforma e para cada empresa.
 *
 * Na plataforma copia-se a base de dados INTEIRA (de 6 em 6 horas por
 * omissão); numa empresa, só os dados dessa empresa. O que muda é a raiz da
 * API e o texto — a agenda, os destinos (Google Drive, OneDrive, Dropbox, FTP,
 * SFTP, S3, WebDAV), o histórico e a reposição são os mesmos.
 */
export function CopiasDeSeguranca({ ambito }: { ambito: Ambito }) {
    const api = useMemo(() => apiDasCopias(ambito), [ambito]);
    const cliente = useQueryClient();
    const [pagina, porPagina] = useState(1);
    const [repor, porRepor] = useState<OrigemDoRestauro | null>(null);
    const [aApagar, porAApagar] = useState<Copia | null>(null);

    const painel = useQuery({
        queryKey: ['copias', ambito, pagina],
        queryFn: () => api.ler(pagina),
        placeholderData: keepPreviousData,
        // Enquanto uma cópia corre, a lista mexe-se sozinha.
        refetchInterval: (q) => {
            const d = q.state.data;

            return d && (d.a_correr || d.copias.some((c) => c.estado === 'a_correr')) ? 3000 : 60_000;
        },
    });
    const recarregar = () => cliente.invalidateQueries({ queryKey: ['copias', ambito] });

    // O regresso do Google/Microsoft/Dropbox chega com `?oauth=` — diz-se no canto e limpa-se o endereço.
    useEffect(() => {
        const url = new URL(window.location.href);
        const r = url.searchParams.get('oauth');
        if (!r) return;
        if (r === 'ligado') avisar(t('Conta ligada. As próximas cópias já vão para lá.'), 'ok');
        else if (r === 'recusado') avisar(t('A autorização foi recusada na conta do fornecedor.'), 'aviso');
        else avisar(url.searchParams.get('mensagem') ?? t('Não foi possível ligar a conta. Tente outra vez.'), 'erro');
        url.searchParams.delete('oauth');
        url.searchParams.delete('mensagem');
        window.history.replaceState(null, '', url.toString());
    }, []);

    const fazer = useMutation({ mutationFn: api.fazer, onSettled: recarregar });
    const reenviar = useMutation({ mutationFn: (id: number) => api.reenviar(id), onSettled: recarregar });
    const apagar = useMutation({ mutationFn: (id: number) => api.apagar(id), onSuccess: () => { porAApagar(null); recarregar(); } });

    if (painel.isPending) return <Carregando linhas={8} />;
    if (painel.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as cópias de segurança')}</h2>
                <p className="text-sm text-red-800">{painel.error instanceof ErroDaApi ? painel.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const d = painel.data;
    const aCorrer = d.a_correr || fazer.isPending;
    const ligados = d.destinos.filter((x) => x.activo && (!x.oauth || x.ligado) && !x.ultimo_erro).length;
    const plataforma = ambito === 'plataforma';

    return (
        <div className="space-y-6">
            <Faixa
                titulo={t('Cópias de segurança')}
                subtitulo={plataforma ? t('A base de dados inteira, guardada no servidor e fora dele — e reposta quando for preciso.') : t('Os dados desta empresa, guardados no servidor e fora dele — e repostos quando for preciso.')}
                icone="fa-shield-halved"
                cor="bom"
                accoes={
                    <>
                        <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porRepor({ tipo: 'carregado' })}>
                            <i className="fas fa-upload" aria-hidden="true" />{t('Repor de um ficheiro')}
                        </button>
                        <button type="button" className={cls('inline-flex items-center gap-2 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-md', RAIO, TRANSICAO, 'hover:-translate-y-0.5 hover:shadow-lg disabled:cursor-wait disabled:opacity-80 disabled:transform-none', 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70')} disabled={aCorrer} onClick={() => fazer.mutate()}>
                            <i className={cls('fas', aCorrer ? 'fa-spinner fa-spin' : 'fa-floppy-disk')} aria-hidden="true" />
                            {aCorrer ? t('A fazer a cópia…') : t('Fazer cópia agora')}
                        </button>
                    </>
                }
            >
                <div className="flex flex-wrap gap-2">
                    <EstadoNaFaixa icone={d.agenda.activa ? 'fa-clock' : 'fa-pause'}>
                        {d.agenda.activa ? t('Automática de :h em :h horas', { h: d.agenda.intervalo_horas }) : t('Automática desligada')}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone={d.agenda.cifrar ? 'fa-lock' : 'fa-lock-open'}>{d.agenda.cifrar ? t('Cifradas') : t('Sem cifra')}</EstadoNaFaixa>
                    {plataforma && <EstadoNaFaixa icone="fa-database">{t('Base de dados inteira')}</EstadoNaFaixa>}
                </div>
            </Faixa>

            {d.avisos.map((a, i) => (
                <div key={i} role="alert" style={cascata(i)} className={cls('entra flex items-start gap-3 border px-4 py-3 text-sm', RAIO,
                    a.cor === 'perigo' ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900')}>
                    <i className={cls('fas mt-0.5', a.cor === 'perigo' ? 'fa-circle-exclamation' : 'fa-triangle-exclamation')} aria-hidden="true" />
                    <span>{a.texto}</span>
                </div>
            ))}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero aspecto="claro" tom="verde" icone="fa-clock-rotate-left" rotulo={t('Última cópia')}
                    valor={<span className="text-2xl sm:text-3xl">{d.agenda.ultima_em ? relativo(d.agenda.ultima_em) : '—'}</span>}
                    nota={d.agenda.ultima_em ? dataHora(d.agenda.ultima_em) : t('Ainda não se fez nenhuma')} />
                <CartaoNumero aspecto="claro" tom="indigo" icone="fa-calendar-check" rotulo={t('Próxima cópia')}
                    valor={<span className="text-2xl sm:text-3xl">{!d.agenda.activa ? t('Desligada') : d.agenda.proxima_em ? relativo(d.agenda.proxima_em) : t('Em breve')}</span>}
                    nota={d.agenda.activa ? dataHora(d.agenda.proxima_em) : t('Ligue a agenda abaixo')} />
                <CartaoNumero aspecto="claro" tom={ligados > 0 ? 'teal' : 'ambar'} icone="fa-cloud-arrow-up" rotulo={t('Destinos a funcionar')}
                    valor={`${ligados}/${d.destinos.length}`} nota={ligados > 0 ? t('Cópias fora do servidor') : t('Só no servidor — ligue um destino')} />
                <CartaoNumero aspecto="claro" tom="roxo" icone="fa-hard-drive" rotulo={t('Espaço no servidor')}
                    valor={tamanho(d.espaco_bytes)} nota={tn(':n cópia|:n cópias', d.paginacao.total, { n: d.paginacao.total })} />
            </div>

            <div className="grid grid-cols-1 items-start gap-6 xl:grid-cols-3">
                <Agenda api={api} dados={d} plataforma={plataforma} aoMudar={recarregar} />
                <div className="min-w-0 xl:col-span-2">
                    <Destinos api={api} destinos={d.destinos} fornecedores={d.fornecedores} retorno={d.retorno_oauth} ambito={ambito}
                        aoMudar={recarregar} aRestaurarDoDestino={(destino, ficheiro) => porRepor({ tipo: 'remoto', destino, ficheiro })} />
                </div>
            </div>

            {plataforma && <AplicacoesOAuth api={api} aoMudar={recarregar} />}

            {/* ─── O histórico ───────────────────────────────────────── */}
            <section className={cls(CARTAO, 'entra overflow-hidden')} style={cascata(3)}>
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                    <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900">
                        <i className="fas fa-box-archive text-indigo-500" aria-hidden="true" />{t('Histórico das cópias')}
                    </h2>
                    {painel.isFetching && <span className="text-xs text-slate-400"><i className="fas fa-rotate fa-spin mr-1" aria-hidden="true" />{t('A actualizar')}</span>}
                </div>

                {d.copias.length === 0 ? (
                    <SemNada icone="fa-box-archive" titulo={t('Ainda não há cópias')} frase={t('A primeira cópia automática faz-se sozinha — ou carregue em «Fazer cópia agora».')}
                        accao={<Botao cor="bom" tom="solida" icone="fa-floppy-disk" aTrabalhar={aCorrer} onClick={() => fazer.mutate()}>{t('Fazer cópia agora')}</Botao>} />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {d.copias.map((c, i) => <LinhaDaCopia key={c.id} c={c} i={i} api={api}
                            aReenviar={reenviar.isPending && reenviar.variables === c.id} temDestinos={d.destinos.some((x) => x.activo)}
                            reenviar={() => reenviar.mutate(c.id)} repor={() => porRepor({ tipo: 'copia', copia: c })} apagar={() => porAApagar(c)} />)}
                    </ul>
                )}
                {d.paginacao.ultima > 1 && (
                    <Paginacao pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} total={d.paginacao.total}
                        de={(d.paginacao.pagina - 1) * 10 + 1} ate={Math.min(d.paginacao.pagina * 10, d.paginacao.total)}
                        aCarregar={painel.isPlaceholderData} aMudar={porPagina} />
                )}
            </section>

            {/* ─── As reposições ─────────────────────────────────────── */}
            {d.restauros.length > 0 && (
                <section className={cls(CARTAO, 'entra p-5')} style={cascata(4)}>
                    <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900">
                        <i className="fas fa-rotate-left text-amber-500" aria-hidden="true" />{t('Reposições')}
                    </h2>
                    <ul className="mt-3 space-y-2">
                        {d.restauros.map((r, i) => {
                            const e = estadoDoRestauro(r.estado);

                            return (
                                <li key={r.id} style={cascata(i)} className={cls('entra flex flex-wrap items-start gap-3 border border-slate-100 p-3', RAIO)}>
                                    <i className={cls('fas mt-1 text-lg', e.icone, e.cor)} aria-hidden="true" />
                                    <div className="min-w-0 flex-1">
                                        <p className="font-semibold text-slate-800">{e.rotulo()} <span className="font-normal text-slate-400">· {dataHora(r.iniciado_em)}</span></p>
                                        <p className="truncate font-mono text-xs text-slate-500">{r.ficheiro ?? (r.copia_id ? t('Cópia n.º :n', { n: r.copia_id }) : '—')}</p>
                                        {r.erro && <p className="mt-1 break-words text-xs text-red-600">{r.erro}</p>}
                                    </div>
                                    {r.copia_previa_id && <Etiqueta cor="aviso" icone="fa-life-ring">{t('Estado anterior: cópia n.º :n', { n: r.copia_previa_id })}</Etiqueta>}
                                </li>
                            );
                        })}
                    </ul>
                </section>
            )}

            <ModalDeRepor api={api} ambito={ambito} origem={repor} aoFechar={() => porRepor(null)} aoMudar={recarregar} />

            <Modal aberto={aApagar !== null} aoFechar={() => porAApagar(null)} titulo={t('Apagar cópia')} icone="fa-trash" cor="perigo"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar.id)}>{t('Apagar')}</Botao>
                    </div>
                }>
                <p className="text-sm text-slate-600">{t('A cópia de :data é apagada do servidor e dos destinos para onde foi enviada. Não se pode desfazer.', { data: dataHora(aApagar?.iniciada_em) })}</p>
                <AvisoDeErro erro={apagar.error} />
            </Modal>
        </div>
    );
}

function LinhaDaCopia({ c, i, api, aReenviar, temDestinos, reenviar, repor, apagar }: {
    c: Copia; i: number; api: ReturnType<typeof apiDasCopias>; aReenviar: boolean; temDestinos: boolean;
    reenviar: () => void; repor: () => void; apagar: () => void;
}) {
    const origem = ORIGENS[c.origem] ?? { rotulo: () => c.origem, icone: 'fa-box-archive', cor: 'bg-slate-100 text-slate-700' };
    const falhouEnvio = c.envios.some((e) => e.estado === 'falhou');

    return (
        <li style={cascata(i)} className={cls('entra group flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center', TRANSICAO, 'hover:bg-slate-50/70')}>
            <div className="flex min-w-0 flex-1 items-start gap-3">
                <span className={cls('grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white shadow transition-transform duration-300 group-hover:scale-110',
                    c.estado === 'concluida' ? 'bg-gradient-to-br from-emerald-500 to-teal-600' : c.estado === 'falhou' ? 'bg-gradient-to-br from-red-500 to-rose-600' : 'bg-gradient-to-br from-indigo-500 to-violet-600')}>
                    <i className={cls('fas', c.estado === 'concluida' ? (c.cifrada ? 'fa-lock' : 'fa-box-archive') : c.estado === 'falhou' ? 'fa-xmark' : 'fa-spinner fa-spin')} aria-hidden="true" />
                </span>
                <div className="min-w-0">
                    <p className="flex flex-wrap items-center gap-2 font-semibold text-slate-900">
                        {dataHora(c.iniciada_em)}
                        <span className={cls('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold', origem.cor)}>
                            <i className={`fas ${origem.icone}`} aria-hidden="true" />{origem.rotulo()}
                        </span>
                        {c.estado === 'a_correr' && <Etiqueta cor="primaria" ponto>{t('A fazer…')}</Etiqueta>}
                        {c.estado === 'falhou' && <Etiqueta cor="perigo" icone="fa-circle-xmark">{t('Falhou')}</Etiqueta>}
                        {c.cifrada && <Etiqueta cor="bom" icone="fa-lock">{t('Cifrada')}</Etiqueta>}
                    </p>
                    <p className="truncate text-xs text-slate-500">
                        {c.estado === 'concluida' && <>{tamanho(c.tamanho)}{c.tabelas !== null && ` · ${tn(':n tabela|:n tabelas', c.tabelas, { n: c.tabelas })}`}{c.linhas !== null && ` · ${tn(':n linha|:n linhas', c.linhas, { n: c.linhas.toLocaleString() })}`}{c.duracao_s !== null && ` · ${c.duracao_s}s`}</>}
                        {!c.no_servidor && c.estado === 'concluida' && <span className="ml-1 text-amber-600">· {t('já não está no servidor')}</span>}
                    </p>
                    {c.erro && <p className="mt-1 line-clamp-2 break-words text-xs text-red-600" title={c.erro}>{c.erro}</p>}
                    {c.envios.length > 0 && (
                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                            {c.envios.map((e) => (
                                <span key={e.destino_id} title={e.erro ?? (e.enviado_em ? dataHora(e.enviado_em) : '')}
                                    className={cls('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset',
                                        e.estado === 'enviado' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                            : e.estado === 'falhou' ? 'bg-red-50 text-red-700 ring-red-200'
                                                : e.estado === 'apagado' ? 'bg-slate-50 text-slate-400 ring-slate-200 line-through'
                                                    : 'bg-indigo-50 text-indigo-700 ring-indigo-200')}>
                                    <i className={cls('fas', e.estado === 'enviado' ? 'fa-check' : e.estado === 'falhou' ? 'fa-xmark' : e.estado === 'apagado' ? 'fa-trash' : 'fa-spinner fa-spin')} aria-hidden="true" />
                                    {e.destino ?? t('Destino removido')}
                                </span>
                            ))}
                        </div>
                    )}
                </div>
            </div>
            {c.estado === 'concluida' && (
                <div className="flex flex-wrap gap-1.5 lg:justify-end">
                    {c.no_servidor && (
                        <a href={api.descarregar(c.id)} className={cls('inline-flex h-8 items-center gap-2 bg-slate-100 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-200', RAIO, TRANSICAO, FOCO)}>
                            <i className="fas fa-download" aria-hidden="true" />{t('Descarregar')}
                        </a>
                    )}
                    {c.no_servidor && temDestinos && (
                        <Botao cor={falhouEnvio ? 'aviso' : 'neutra'} altura="pequeno" icone="fa-paper-plane" aTrabalhar={aReenviar} onClick={reenviar}>
                            {falhouEnvio ? t('Reenviar') : t('Enviar outra vez')}
                        </Botao>
                    )}
                    <Botao cor="aviso" altura="pequeno" icone="fa-rotate-left" disabled={!c.no_servidor} title={!c.no_servidor ? t('Reponha pelo destino («Ver cópias lá»).') : undefined} onClick={repor}>{t('Repor')}</Botao>
                    <Botao cor="perigo" altura="pequeno" icone="fa-trash" onClick={apagar} aria-label={t('Apagar')} />
                </div>
            )}
            {c.estado === 'falhou' && (
                <div className="flex lg:justify-end">
                    <Botao cor="perigo" altura="pequeno" icone="fa-trash" onClick={apagar}>{t('Apagar')}</Botao>
                </div>
            )}
        </li>
    );
}

/** A AGENDA: de quantas em quantas horas, quantas guardar no servidor, e se vão cifradas. */
function Agenda({ api, dados, plataforma, aoMudar }: {
    api: ReturnType<typeof apiDasCopias>; dados: PainelDasCopias; plataforma: boolean; aoMudar: () => void;
}) {
    const a = dados.agenda;
    const [form, porForm] = useState({ activa: a.activa, intervalo_horas: a.intervalo_horas, manter_locais: a.manter_locais, cifrar: a.cifrar, frase: '' });
    const [verFrase, porVerFrase] = useState(false);

    useEffect(() => {
        porForm({ activa: a.activa, intervalo_horas: a.intervalo_horas, manter_locais: a.manter_locais, cifrar: a.cifrar, frase: '' });
    }, [a.activa, a.intervalo_horas, a.manter_locais, a.cifrar]);

    const guardar = useMutation({ mutationFn: () => api.agenda(form), onSuccess: () => { porForm((f) => ({ ...f, frase: '' })); aoMudar(); } });
    const erros = (guardar.error instanceof ErroDaApi ? guardar.error.erros : {}) as Record<string, string[]>;
    const rotuloDoIntervalo = (h: number) => (h === 168 ? t('Uma vez por semana') : h === 24 ? t('Uma vez por dia') : h === 48 ? t('De 2 em 2 dias') : tn('A cada hora|De :n em :n horas', h, { n: h }));

    return (
        <section className={cls(CARTAO, 'entra p-5')} style={cascata(1)}>
            <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900">
                <i className="fas fa-calendar-days text-indigo-500" aria-hidden="true" />{t('Agenda')}
            </h2>
            <p className="text-sm text-slate-500">{plataforma ? t('Corre sozinha, com o tráfego do site ou pelo agendador do servidor.') : t('Corre sozinha enquanto a empresa estiver activa.')}</p>

            <div className="mt-4 space-y-4">
                <button type="button" role="switch" aria-checked={form.activa} onClick={() => porForm({ ...form, activa: !form.activa })}
                    className={cls('flex w-full items-center justify-between gap-3 border p-3 text-left', RAIO, TRANSICAO, FOCO, form.activa ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200')}>
                    <span className="text-sm">
                        <span className="block font-semibold text-slate-800">{t('Cópias automáticas')}</span>
                        <span className="block text-xs text-slate-500">{form.activa ? t('Ligadas') : t('Desligadas — só manuais')}</span>
                    </span>
                    <span className={cls('relative h-6 w-11 shrink-0 rounded-full transition-colors duration-300', form.activa ? 'bg-emerald-500' : 'bg-slate-300')}>
                        <span className={cls('absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all duration-300', form.activa ? 'left-[22px]' : 'left-0.5')} />
                    </span>
                </button>

                <Campo etiqueta={t('Frequência')} obrigatorio erro={erros.intervalo_horas}>
                    <select className={entrada} value={form.intervalo_horas} onChange={(e) => porForm({ ...form, intervalo_horas: Number(e.target.value) })}>
                        {dados.intervalos.map((h) => <option key={h} value={h}>{rotuloDoIntervalo(h)}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Cópias a guardar no servidor')} obrigatorio erro={erros.manter_locais} ajuda={t('As mais antigas apagam-se sozinhas. As de antes de uma reposição ficam 48 horas.')}>
                    <input type="number" min={1} max={plataforma ? 60 : 30} className={entrada} value={form.manter_locais} onChange={(e) => porForm({ ...form, manter_locais: Number(e.target.value) })} />
                </Campo>

                <label className={cls('flex cursor-pointer items-start gap-3 border p-3', RAIO, TRANSICAO, form.cifrar ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 hover:bg-slate-50')}>
                    <input type="checkbox" className="mt-1 h-4 w-4" checked={form.cifrar} onChange={(e) => porForm({ ...form, cifrar: e.target.checked })} />
                    <span className="text-sm">
                        <span className="flex items-center gap-1.5 font-semibold text-slate-800"><i className="fas fa-lock text-emerald-600" aria-hidden="true" />{t('Cifrar as cópias')}</span>
                        <span className="block text-xs text-slate-500">{t('XChaCha20-Poly1305 com uma frase-passe. Quem abrir o Drive ou o FTP não lê nada.')}</span>
                    </span>
                </label>

                {form.cifrar && (
                    <Campo etiqueta={a.tem_frase ? t('Mudar a frase-passe') : t('Frase-passe')} obrigatorio={!a.tem_frase} erro={erros.frase}
                        ajuda={a.tem_frase ? t('Já existe uma frase gravada. Deixe em branco para a manter; as cópias antigas continuam a precisar da antiga.') : t('12 caracteres ou mais. Guarde-a fora do sistema: sem ela as cópias cifradas não se abrem.')}>
                        <span className="relative block">
                            <input type={verFrase ? 'text' : 'password'} className={cls(entrada, 'pr-10')} autoComplete="new-password" value={form.frase} onChange={(e) => porForm({ ...form, frase: e.target.value })} />
                            <button type="button" onClick={(e) => { e.preventDefault(); porVerFrase(!verFrase); }} aria-label={verFrase ? t('Esconder') : t('Mostrar')}
                                className="absolute inset-y-0 right-0 grid w-10 place-items-center text-slate-400 hover:text-slate-700">
                                <i className={cls('fas', verFrase ? 'fa-eye-slash' : 'fa-eye')} aria-hidden="true" />
                            </button>
                        </span>
                    </Campo>
                )}

                <AvisoDeErro erro={guardar.error} />
                <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" className="w-full" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar agenda')}</Botao>
            </div>
        </section>
    );
}
