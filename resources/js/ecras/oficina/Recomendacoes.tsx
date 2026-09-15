import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useDeferredValue, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { recomendacoesAdiadas, type RecomendacaoAdiada, type ViaturaComRecomendacoes } from '@/api/oficina';
import { Faixa } from '@/ecras/facturacao/faixa';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { SemNada, cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data, kz } from '@/ui/tokens';

import { ChapaDaMatricula } from './ChapaDaMatricula';
import { TOM_DA_GRAVIDADE } from './RecomendacoesNaOrdem';

/**
 * AS RECOMENDAÇÕES ADIADAS (15/09/2026, OF-12).
 *
 * O que cada cliente recusou ou deixou para depois, por viatura: quanto vale,
 * de que visita veio e quando se volta a propor. É trabalho já diagnosticado
 * à espera de ser vendido — os cartões de cima dizem quanto.
 */

const ESTADOS = [
    { valor: 'pendente', rotulo: 'Por propor', icone: 'fa-hourglass-half' },
    { valor: 'aceite', rotulo: 'Aceites', icone: 'fa-circle-check' },
    { valor: 'descartada', rotulo: 'Descartadas', icone: 'fa-ban' },
    { valor: 'todas', rotulo: 'Todas', icone: 'fa-layer-group' },
];

const ICONE_DA_ORIGEM: Record<RecomendacaoAdiada['origem'], string> = {
    recusada: 'fa-thumbs-down', adiada: 'fa-clock', inspeccao: 'fa-list-check',
};

const hojeIso = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

function numeroParaWhatsApp(telefone: string | null): string {
    const digitos = (telefone ?? '').replace(/\D/g, '');
    if (digitos.length === 9 && digitos.startsWith('9')) return `244${digitos}`;
    return digitos.length >= 11 ? digitos : '';
}

export default function Recomendacoes() {
    const cache = useQueryClient();
    const [estado, porEstado] = useState('pendente');
    const [origem, porOrigem] = useState('');
    const [busca, porBusca] = useState('');
    const [pagina, porPagina] = useState(1);
    const [aEditar, porAEditar] = useState<RecomendacaoAdiada | null>(null);
    const [aDescartar, porADescartar] = useState<RecomendacaoAdiada | null>(null);
    const q = useDeferredValue(busca.trim());

    const lista = useQuery({
        queryKey: ['oficina', 'recomendacoes', estado, origem, q, pagina],
        queryFn: () => recomendacoesAdiadas.lista({ estado, origem, q, pagina }),
        placeholderData: keepPreviousData,
    });
    const refazer = () => void cache.invalidateQueries({ queryKey: ['oficina', 'recomendacoes'] });
    const reabrir = useMutation({ mutationFn: (r: RecomendacaoAdiada) => recomendacoesAdiadas.reabrir(r.id), onSuccess: refazer });

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <AvisoDeErro erro={lista.error} />;

    const d = lista.data;
    const filtrar = (fazer: () => void) => { fazer(); porPagina(1); };

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Recomendações Adiadas')} subtitulo={t('O que os clientes recusaram ou deixaram para depois — trabalho diagnosticado à espera de ser feito')} icone="fa-hourglass-half" cor="rosa" />

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" tom="roxo" icone="fa-hourglass-half" rotulo={t('Por propor')} valor={d.contas.pendentes}
                    nota={tn(':n para propor já|:n para propor já', d.contas.para_propor, { n: d.contas.para_propor })} aoCarregar={() => filtrar(() => porEstado('pendente'))} />
                <CartaoNumero aspecto="claro" tom="verde" icone="fa-sack-dollar" rotulo={t('Valor por vender')} valor={kz(d.contas.valor_pendente)} sufixo="Kz" />
                <CartaoNumero aspecto="claro" tom="vermelho" icone="fa-triangle-exclamation" rotulo={t('Urgentes')} valor={d.contas.urgentes} />
                <CartaoNumero aspecto="claro" tom="azul" icone="fa-circle-check" rotulo={t('Aceites (90 dias)')} valor={d.contas.aceites_90_dias} aoCarregar={() => filtrar(() => porEstado('aceite'))} />
            </div>

            <div className={cls('flex flex-col gap-3 border border-slate-200 bg-white p-3 shadow-sm lg:flex-row lg:items-center', RAIO_GRANDE)}>
                <div className="-mx-1 flex flex-wrap gap-1 px-1">
                    {ESTADOS.map((e) => (
                        <button key={e.valor} type="button" aria-pressed={estado === e.valor} onClick={() => filtrar(() => porEstado(e.valor))}
                            className={cls('inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO,
                                estado === e.valor ? 'bg-rose-600 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}>
                            <i className={cls('fas', e.icone)} aria-hidden="true" />{t(e.rotulo)}
                        </button>
                    ))}
                </div>
                <select value={origem} onChange={(e) => filtrar(() => porOrigem(e.target.value))} className={cls(entrada, 'lg:ml-auto lg:w-52')} aria-label={t('Origem')}>
                    <option value="">{t('Todas as origens')}</option>
                    {d.origens.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                </select>
                <input type="search" value={busca} onChange={(e) => filtrar(() => porBusca(e.target.value))} placeholder={t('Matrícula, dono ou trabalho')} className={cls(entrada, 'lg:w-60')} aria-label={t('Procurar')} />
            </div>

            {d.data.length === 0 ? (
                <SemNada icone="fa-hourglass-half" titulo={estado === 'pendente' ? t('Nada adiado') : t('Nada com estes filtros')}
                    frase={t('Uma linha recusada pelo cliente, uma linha adiada na ordem ou os pontos da inspecção passados às recomendações aparecem aqui.')} />
            ) : (
                <>
                    <ul className={cls('space-y-3 transition-opacity', lista.isPlaceholderData && 'opacity-60')}>
                        {d.data.map((v, i) => (
                            <CartaoDaViatura key={v.viatura_id} v={v} i={i} podeGerir={d.pode_gerir}
                                editar={porAEditar} descartar={porADescartar} reabrir={(r) => reabrir.mutate(r)} />
                        ))}
                    </ul>
                    <Paginacao emCartao pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} aMudar={porPagina} total={d.paginacao.total}
                        de={d.paginacao.de} ate={d.paginacao.ate} aCarregar={lista.isFetching} />
                </>
            )}

            {aEditar && <EditarRecomendacao r={aEditar} aoFechar={() => porAEditar(null)} aoGravar={() => { porAEditar(null); refazer(); }} />}
            {aDescartar && <DescartarRecomendacao r={aDescartar} aoFechar={() => porADescartar(null)} aoGravar={() => { porADescartar(null); refazer(); }} />}
        </div>
    );
}

function CartaoDaViatura({ v, i, podeGerir, editar, descartar, reabrir }: {
    v: ViaturaComRecomendacoes;
    i: number;
    podeGerir: boolean;
    editar: (r: RecomendacaoAdiada) => void;
    descartar: (r: RecomendacaoAdiada) => void;
    reabrir: (r: RecomendacaoAdiada) => void;
}) {
    const pendentes = v.itens.filter((r) => r.estado === 'pendente');
    const whatsapp = numeroParaWhatsApp(v.telefone);
    const mensagem = t('Olá :dono, na última visita da viatura :matricula ficaram trabalhos recomendados por fazer: :trabalhos. Quer marcar?', {
        dono: v.dono ?? '', matricula: v.matricula, trabalhos: pendentes.map((r) => r.nome).join(', '),
    });

    return (
        <li style={cascata(i)} className={cls('entra card-hover overflow-hidden border border-slate-200 bg-white shadow-sm', RAIO_GRANDE, TRANSICAO, 'hover:shadow-lg')}>
            <div className="flex flex-wrap items-center gap-3 border-b border-slate-100 bg-gradient-to-r from-rose-50/70 to-white px-4 py-3">
                <ChapaDaMatricula matricula={v.matricula} tamanho="pequeno" />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold text-slate-900">{v.marca_modelo}</p>
                    <p className="break-words text-xs text-slate-500">
                        <i className="fas fa-user mr-1" aria-hidden="true" />{v.dono ?? '—'}
                        {v.telefone && <> · <i className="fas fa-phone mx-1" aria-hidden="true" />{v.telefone}</>}
                    </p>
                </div>
                {v.valor > 0 && (
                    <span className="text-right">
                        <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-400">{t('Por vender')}</span>
                        <span className="block text-base font-extrabold tabular-nums text-rose-700">{kz(v.valor)}</span>
                    </span>
                )}
                {podeGerir && pendentes.length > 0 && (
                    <div className="flex w-full flex-wrap gap-1.5 sm:w-auto">
                        {whatsapp && (
                            <a href={`https://wa.me/${whatsapp}?text=${encodeURIComponent(mensagem)}`} target="_blank" rel="noopener noreferrer"
                                className={cls('inline-flex h-8 items-center gap-1.5 border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-700 hover:-translate-y-0.5 hover:bg-emerald-100 hover:shadow', RAIO, TRANSICAO, FOCO)}>
                                <i className="fab fa-whatsapp" aria-hidden="true" />WhatsApp
                            </a>
                        )}
                        <a href={`/workshop/schedule?viatura=${v.viatura_id}&servico=${encodeURIComponent(pendentes.map((r) => r.nome).join(', ').slice(0, 250))}`}
                            className={cls('inline-flex h-8 items-center gap-1.5 border border-cyan-200 bg-cyan-50 px-2.5 text-xs font-semibold text-cyan-700 hover:-translate-y-0.5 hover:bg-cyan-100 hover:shadow', RAIO, TRANSICAO, FOCO)}>
                            <i className="fas fa-calendar-plus" aria-hidden="true" />{t('Marcar na agenda')}
                        </a>
                    </div>
                )}
            </div>

            <ul className="divide-y divide-slate-100">
                {v.itens.map((r) => {
                    const atrasada = r.estado === 'pendente' && r.voltar_em !== null && r.voltar_em <= hojeIso();
                    return (
                        <li key={r.id} className={cls('group flex flex-wrap items-start gap-3 px-4 py-3 transition-colors hover:bg-slate-50', r.estado !== 'pendente' && 'opacity-70')}>
                            <span className={cls('grid h-8 w-8 flex-none place-items-center rounded-lg text-sm', r.tipo === 'service' ? 'bg-indigo-50 text-indigo-600' : 'bg-emerald-50 text-emerald-600')}>
                                <i className={cls('fas', r.tipo === 'service' ? 'fa-screwdriver-wrench' : 'fa-gear')} aria-hidden="true" />
                            </span>
                            <div className="min-w-0 flex-1 space-y-1">
                                <p className="flex flex-wrap items-center gap-1.5">
                                    <span className={cls('text-sm font-semibold', r.estado === 'descartada' ? 'text-slate-400 line-through' : 'text-slate-800')}>{r.nome}</span>
                                    {r.gravidade && <span className={cls('rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase', TOM_DA_GRAVIDADE[r.gravidade])}>{r.gravidade === 'urgente' ? t('Urgente') : t('Atenção')}</span>}
                                    {r.estado === 'aceite' && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                            <i className="fas fa-check" aria-hidden="true" />{r.resolvida_na_ordem ? t('Aceite na :ordem', { ordem: r.resolvida_na_ordem }) : r.estado_rotulo}
                                        </span>
                                    )}
                                    {r.estado === 'descartada' && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{r.estado_rotulo}</span>}
                                </p>
                                <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                    <span><i className={cls('fas mr-1', ICONE_DA_ORIGEM[r.origem])} aria-hidden="true" />{r.origem_rotulo}{r.ordem && ` · ${r.ordem}`}</span>
                                    {r.criada_em && <span><i className="fas fa-calendar mr-1" aria-hidden="true" />{data(r.criada_em)}</span>}
                                    {r.estado === 'pendente' && r.voltar_em && (
                                        <span className={cls(atrasada && 'font-bold text-rose-700')}>
                                            <i className="fas fa-bell mr-1" aria-hidden="true" />{t('Voltar a propor a :data', { data: data(r.voltar_em) })}
                                        </span>
                                    )}
                                </p>
                                {r.nota && <p className="text-xs italic text-slate-500">«{r.nota}»</p>}
                            </div>
                            <div className="text-right">
                                <p className="text-sm font-bold tabular-nums text-slate-900">{r.valor > 0 ? kz(r.valor) : '—'}</p>
                                {r.valor > 0 && <p className="text-[11px] tabular-nums text-slate-400">{r.quantidade} × {kz(r.preco)}</p>}
                            </div>
                            {podeGerir && (
                                <div className="flex gap-1 sm:opacity-60 sm:transition-opacity sm:group-hover:opacity-100">
                                    {r.estado === 'pendente' && (
                                        <>
                                            <button type="button" onClick={() => editar(r)} aria-label={t('Editar: :nome', { nome: r.nome })}
                                                className={cls('p-1.5 text-slate-400 transition-all hover:scale-110 hover:text-indigo-600', RAIO, FOCO)}>
                                                <i className="fas fa-pen" aria-hidden="true" />
                                            </button>
                                            <button type="button" onClick={() => descartar(r)} aria-label={t('Descartar: :nome', { nome: r.nome })}
                                                className={cls('p-1.5 text-slate-400 transition-all hover:scale-110 hover:text-red-600', RAIO, FOCO)}>
                                                <i className="fas fa-ban" aria-hidden="true" />
                                            </button>
                                        </>
                                    )}
                                    {r.estado === 'descartada' && (
                                        <button type="button" onClick={() => reabrir(r)} aria-label={t('Reabrir: :nome', { nome: r.nome })}
                                            className={cls('p-1.5 text-slate-400 transition-all hover:scale-110 hover:text-emerald-600', RAIO, FOCO)}>
                                            <i className="fas fa-rotate-left" aria-hidden="true" />
                                        </button>
                                    )}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </li>
    );
}

function EditarRecomendacao({ r, aoFechar, aoGravar }: { r: RecomendacaoAdiada; aoFechar: () => void; aoGravar: () => void }) {
    const [v, porV] = useState({ voltar_em: r.voltar_em ?? '', preco: String(r.preco), nota: r.nota ?? '' });
    const gravar = useMutation({
        mutationFn: () => recomendacoesAdiadas.guardar(r.id, { voltar_em: v.voltar_em || null, preco: v.preco === '' ? null : Number(v.preco), nota: v.nota }),
        onSuccess: aoGravar,
    });
    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Recomendação adiada')} subtitulo={r.nome} icone="fa-hourglass-half" cor="rosa"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar')}</Botao></>}>
            <div className="grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('Voltar a propor a')} ajuda={t('Nesta data entra nos Lembretes.')} erro={erros.voltar_em}>
                    <input type="date" value={v.voltar_em} onChange={(e) => porV({ ...v, voltar_em: e.target.value })} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Preço unitário')} ajuda={t('Os pontos da inspecção chegam sem preço.')} erro={erros.preco}>
                    <input type="number" min={0} step="0.01" value={v.preco} onChange={(e) => porV({ ...v, preco: e.target.value })} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Nota')} erro={erros.nota} className="sm:col-span-2">
                    <textarea rows={2} maxLength={500} value={v.nota} onChange={(e) => porV({ ...v, nota: e.target.value })} className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}

function DescartarRecomendacao({ r, aoFechar, aoGravar }: { r: RecomendacaoAdiada; aoFechar: () => void; aoGravar: () => void }) {
    const [nota, porNota] = useState('');
    const descartar = useMutation({ mutationFn: () => recomendacoesAdiadas.descartar(r.id, nota), onSuccess: aoGravar });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Descartar a recomendação')} subtitulo={r.nome} icone="fa-ban" cor="perigo"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-ban" aTrabalhar={descartar.isPending} onClick={() => descartar.mutate()}>{t('Descartar')}</Botao></>}>
            <div className="space-y-3">
                <p className="text-sm text-slate-600">{t('Deixa de ser proposta e sai dos Lembretes. Pode reabri-la depois.')}</p>
                <Campo etiqueta={t('Porquê')}>
                    <input value={nota} maxLength={500} onChange={(e) => porNota(e.target.value)} placeholder={t('Ex.: Já fez noutra oficina.')} className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}
