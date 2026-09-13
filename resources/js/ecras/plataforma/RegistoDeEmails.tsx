import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { type RegistoDeEmail, ferramentas } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, RAIO, TRANSICAO, cls, dataHora, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { BotaoDeIcone, Confirmar, Dado, ErroDoEcra, Paginas, Recado } from './comum';

type Filtros = { procura?: string; estado?: string; modelo?: string; de?: string; ate?: string; pagina: number };

const ESTADO = (e: string) => ({
    sent: { rotulo: t('Enviado'), cor: 'bom' as const, icone: 'fa-circle-check' },
    failed: { rotulo: t('Falhou'), cor: 'perigo' as const, icone: 'fa-circle-xmark' },
    pending: { rotulo: t('Pendente'), cor: 'aviso' as const, icone: 'fa-clock' },
}[e] ?? { rotulo: e, cor: 'neutra' as const, icone: 'fa-circle' });

/**
 * O REGISTO DOS EMAILS QUE A PLATAFORMA ENVIOU.
 *
 * Os cartões do topo contam tudo e filtram por estado. O detalhe abre por
 * pedido próprio — a lista não carrega os dados do modelo de cada linha — e os
 * campos de senha que o email de credenciais grava aparecem tapados.
 */
export default function RegistoDeEmails() {
    const fila = useQueryClient();
    const [filtros, porFiltros] = useState<Filtros>({ pagina: 1 });
    const [recado, porRecado] = useState<string | null>(null);
    const [aVer, porAVer] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<RegistoDeEmail | null>(null);
    const [aLimpar, porALimpar] = useState(false);

    const lista = useQuery({
        queryKey: ['plataforma', 'emails', filtros],
        queryFn: () => ferramentas.emails.ler(filtros),
        placeholderData: keepPreviousData,
    });

    const feito = (m: string) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'emails'] }); };

    const apagar = useMutation({ mutationFn: (id: number) => ferramentas.emails.apagar(id), onSuccess: (r) => { porAApagar(null); porAVer(null); feito(r.message); } });
    const limpar = useMutation({ mutationFn: ferramentas.emails.limparAntigos, onSuccess: (r) => { porALimpar(false); feito(r.message); } });

    const mexer = (campo: keyof Omit<Filtros, 'pagina'>, valor: string) => porFiltros((f) => ({ ...f, [campo]: valor || undefined, pagina: 1 }));

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <ErroDoEcra titulo={t('Não foi possível abrir o registo de emails')} erro={lista.error} />;

    const d = lista.data;
    const temFiltros = Boolean(filtros.procura || filtros.estado || filtros.modelo || filtros.de || filtros.ate);

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Logs de Email')}
                subtitulo={t('Histórico e rastreamento de emails enviados')}
                icone="fa-envelope-open-text"
                cor="primaria"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porALimpar(true)}>
                        <i className="fas fa-broom" aria-hidden="true" />{t('Limpar Logs Antigos')}
                    </button>
                }
            >
                <EstadoNaFaixa icone="fa-paper-plane">{t(':n emails registados', { n: d.numeros.total })}</EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado} aoFechar={() => porRecado(null)} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero rotulo={t('Total')} valor={kz(d.numeros.total, 0)} icone="fa-envelope" tom="azul" aspecto="claro" aoCarregar={() => mexer('estado', '')} />
                <CartaoNumero rotulo={t('Enviados')} valor={kz(d.numeros.enviados, 0)} icone="fa-circle-check" tom="verde" aspecto="claro"
                    aoCarregar={() => mexer('estado', filtros.estado === 'sent' ? '' : 'sent')} className={cls(filtros.estado === 'sent' && 'ring-2 ring-emerald-300')} />
                <CartaoNumero rotulo={t('Falhados')} valor={kz(d.numeros.falhados, 0)} icone="fa-circle-xmark" tom={d.numeros.falhados > 0 ? 'vermelho' : 'cinza'} aspecto="claro"
                    aoCarregar={() => mexer('estado', filtros.estado === 'failed' ? '' : 'failed')} className={cls(filtros.estado === 'failed' && 'ring-2 ring-red-300')} />
                <CartaoNumero rotulo={t('Pendentes')} valor={kz(d.numeros.pendentes, 0)} icone="fa-clock" tom="ambar" aspecto="claro"
                    aoCarregar={() => mexer('estado', filtros.estado === 'pending' ? '' : 'pending')} className={cls(filtros.estado === 'pending' && 'ring-2 ring-amber-300')} />
            </div>

            <div className={cls(CARTAO, 'grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-5')}>
                <label className="block">
                    <Rotulo>{t('Buscar')}</Rotulo>
                    <input type="search" className={entrada} placeholder={t('Email, assunto...')} value={filtros.procura ?? ''} onChange={(e) => mexer('procura', e.target.value)} />
                </label>
                <label className="block">
                    <Rotulo>{t('Estado')}</Rotulo>
                    <select className={entrada} value={filtros.estado ?? ''} onChange={(e) => mexer('estado', e.target.value)}>
                        <option value="">{t('Todos')}</option>
                        <option value="sent">{t('Enviados')}</option>
                        <option value="failed">{t('Falhados')}</option>
                        <option value="pending">{t('Pendentes')}</option>
                    </select>
                </label>
                <label className="block">
                    <Rotulo>{t('Template')}</Rotulo>
                    <select className={entrada} value={filtros.modelo ?? ''} onChange={(e) => mexer('modelo', e.target.value)}>
                        <option value="">{t('Todos')}</option>
                        {d.modelos.map((m) => <option key={m} value={m}>{m}</option>)}
                    </select>
                </label>
                <label className="block">
                    <Rotulo>{t('Data de')}</Rotulo>
                    <input type="date" className={entrada} value={filtros.de ?? ''} onChange={(e) => mexer('de', e.target.value)} />
                </label>
                <label className="block">
                    <Rotulo>{t('Data até')}</Rotulo>
                    <input type="date" className={entrada} value={filtros.ate ?? ''} onChange={(e) => mexer('ate', e.target.value)} />
                </label>
                {temFiltros && (
                    <div className="md:col-span-2 xl:col-span-5">
                        <Botao altura="pequeno" icone="fa-filter-circle-xmark" onClick={() => porFiltros({ pagina: 1 })}>{t('Limpar filtros')}</Botao>
                    </div>
                )}
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                {d.registos.length === 0 ? <SemNada icone="fa-envelope" frase={t('Nenhum log encontrado')} /> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">{t('Data/Hora')}</th>
                                    <th className="px-5 py-3">{t('Destinatário')}</th>
                                    <th className="px-5 py-3">{t('Assunto')}</th>
                                    <th className="px-5 py-3">{t('Template')}</th>
                                    <th className="px-5 py-3">{t('Estado')}</th>
                                    <th className="px-5 py-3 text-center">{t('Ações')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.registos.map((l, i) => {
                                    const e = ESTADO(l.estado);

                                    return (
                                        <tr key={l.id} className={cls('entra hover:bg-slate-50', TRANSICAO)} style={cascata(i)}>
                                            <td className="whitespace-nowrap px-5 py-3 text-xs tabular-nums text-slate-600">{dataHora(l.criado_em)}</td>
                                            <td className="px-5 py-3">
                                                <span className="block font-medium text-slate-900">{l.para}</span>
                                                {l.nome && <span className="text-xs text-slate-500">{l.nome}</span>}
                                            </td>
                                            <td className="max-w-xs truncate px-5 py-3 text-slate-800" title={l.assunto}>{l.assunto}</td>
                                            <td className="px-5 py-3">{l.modelo ? <code className="rounded bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">{l.modelo}</code> : '—'}</td>
                                            <td className="px-5 py-3"><Etiqueta cor={e.cor} icone={e.icone}>{e.rotulo}</Etiqueta></td>
                                            <td className="px-5 py-3">
                                                <div className="flex justify-center gap-1">
                                                    <BotaoDeIcone icone="fa-eye" rotulo={t('Ver detalhes')} cor="text-indigo-600 hover:bg-indigo-50" onClick={() => porAVer(l.id)} />
                                                    <BotaoDeIcone icone="fa-trash" rotulo={t('Excluir')} cor="text-red-500 hover:bg-red-50" onClick={() => porAApagar(l)} />
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="px-5 pb-4">
                    <Paginas pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} aMudar={(p) => porFiltros((f) => ({ ...f, pagina: p }))} />
                </div>
            </section>

            {aVer !== null && <Detalhe id={aVer} aoFechar={() => porAVer(null)} />}

            <Confirmar
                aberto={aApagar !== null}
                titulo={t('Excluir este log?')}
                subtitulo={aApagar?.para}
                rotulo={t('Excluir')}
                aTrabalhar={apagar.isPending}
                erro={apagar.error}
                aoConfirmar={() => aApagar && apagar.mutate(aApagar.id)}
                aoFechar={() => porAApagar(null)}
            >
                <p>{t('O registo deste envio deixa de existir. O email já enviado não é afectado.')}</p>
            </Confirmar>

            <Confirmar
                aberto={aLimpar}
                titulo={t('Limpar Logs Antigos')}
                rotulo={t('Excluir os antigos')}
                icone="fa-broom"
                aTrabalhar={limpar.isPending}
                erro={limpar.error}
                aoConfirmar={() => limpar.mutate()}
                aoFechar={() => porALimpar(false)}
            >
                <p>{t('Excluir logs mais antigos que 90 dias?')}</p>
            </Confirmar>
        </div>
    );
}

function Detalhe({ id, aoFechar }: { id: number; aoFechar: () => void }) {
    const pedido = useQuery({ queryKey: ['plataforma', 'emails', 'ver', id], queryFn: () => ferramentas.emails.ver(id) });
    const r = pedido.data?.registo;
    const e = r ? ESTADO(r.estado) : null;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Detalhes do Log #:id', { id })}
            icone="fa-file-lines"
            cor="primaria"
            largura="xl"
            rodape={<div className="flex justify-end"><Botao cor="neutra" onClick={aoFechar}>{t('Fechar')}</Botao></div>}
        >
            {pedido.isPending && <Carregando linhas={5} />}
            <AvisoDeErro erro={pedido.error} />
            {r && e && (
                <div className="space-y-5">
                    <div className={cls('border p-4', RAIO, { bom: 'border-emerald-200 bg-emerald-50', perigo: 'border-red-200 bg-red-50', aviso: 'border-amber-200 bg-amber-50', neutra: 'border-slate-200 bg-slate-50' }[e.cor])}>
                        <Etiqueta cor={e.cor} icone={e.icone}>
                            {r.estado === 'sent' ? t('Enviado em :data', { data: dataHora(r.enviado_em) })
                                : r.estado === 'failed' ? t('Falhou em :data', { data: dataHora(r.falhou_em) }) : e.rotulo}
                        </Etiqueta>
                        {r.erro && <p className="mt-2 break-words text-sm text-red-800"><strong>{t('Erro')}:</strong> {r.erro}</p>}
                    </div>

                    <dl className="grid gap-4 md:grid-cols-2">
                        <Dado rotulo={t('Destinatário')}>{r.para}{r.para_nome && <span className="block text-sm font-normal text-slate-500">{r.para_nome}</span>}</Dado>
                        <Dado rotulo={t('Remetente')}>{r.de ?? '—'}{r.de_nome && <span className="block text-sm font-normal text-slate-500">{r.de_nome}</span>}</Dado>
                        <Dado rotulo={t('Template')}>{r.modelo ? <code className="rounded bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">{r.modelo}</code> : '—'}{r.modelo_nome && <span className="ml-2 text-sm font-normal text-slate-500">{r.modelo_nome}</span>}</Dado>
                        <Dado rotulo={t('Data de criação')}>{dataHora(r.criado_em)}</Dado>
                    </dl>

                    <div>
                        <Rotulo>{t('Assunto')}</Rotulo>
                        <p className={cls('bg-slate-50 p-3 text-slate-900', RAIO)}>{r.assunto}</p>
                    </div>

                    {r.previa && (
                        <div>
                            <Rotulo>{t('Preview do Conteúdo')}</Rotulo>
                            <p className={cls('bg-slate-50 p-3 text-sm text-slate-700', RAIO)}>{r.previa}</p>
                        </div>
                    )}

                    {r.dados && (
                        <div>
                            <Rotulo>{t('Dados do Template')}</Rotulo>
                            <pre className={cls('overflow-x-auto bg-slate-900 p-3 font-mono text-xs text-emerald-400', RAIO)}>{JSON.stringify(r.dados, null, 2)}</pre>
                        </div>
                    )}

                    <div className="grid gap-3 md:grid-cols-3">
                        {r.empresa && <Relacao icone="fa-building" rotulo={t('Tenant')} valor={r.empresa} cor="border-blue-200 bg-blue-50 text-blue-900" />}
                        {r.utilizador && <Relacao icone="fa-user" rotulo={t('Usuário')} valor={r.utilizador} cor="border-emerald-200 bg-emerald-50 text-emerald-900" />}
                        {r.servidor && <Relacao icone="fa-server" rotulo={t('SMTP')} valor={r.servidor} cor="border-purple-200 bg-purple-50 text-purple-900" />}
                    </div>
                </div>
            )}
        </Modal>
    );
}

function Relacao({ icone, rotulo, valor, cor }: { icone: string; rotulo: string; valor: string; cor: string }) {
    return (
        <div className={cls('border p-3', RAIO, cor)}>
            <p className="text-xs font-semibold uppercase tracking-wider opacity-70"><i className={`fas ${icone} mr-1`} aria-hidden="true" />{rotulo}</p>
            <p className="mt-0.5 break-words text-sm font-semibold">{valor}</p>
        </div>
    );
}
