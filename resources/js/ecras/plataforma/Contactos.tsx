import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { type MensagemDeContacto, ferramentas } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, dataHora, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { BotaoDeIcone, Confirmar, Dado, ErroDoEcra, Paginas, Recado } from './comum';

const ESTADO = () => ({
    new: { rotulo: t('Nova'), cor: 'perigo' as const },
    read: { rotulo: t('Lida'), cor: 'aviso' as const },
    replied: { rotulo: t('Respondida'), cor: 'bom' as const },
});

/**
 * AS MENSAGENS DO FORMULÁRIO DE CONTACTO DO SITE.
 *
 * Os três cartões do topo contam sempre tudo e são também o filtro: carregar
 * em «Novas» mostra as novas. Abrir uma mensagem nova já não obriga a um
 * segundo clique para a dar como lida — o botão está na própria janela.
 */
export default function Contactos() {
    const fila = useQueryClient();
    const [filtros, porFiltros] = useState<{ procura?: string; estado?: string; pagina: number }>({ pagina: 1 });
    const [recado, porRecado] = useRecadoNoCanto(null);
    const [aVer, porAVer] = useState<MensagemDeContacto | null>(null);
    const [aApagar, porAApagar] = useState<MensagemDeContacto | null>(null);

    const lista = useQuery({
        queryKey: ['plataforma', 'contactos', filtros],
        queryFn: () => ferramentas.contactos.ler(filtros),
        placeholderData: keepPreviousData,
    });

    const feito = (m: string) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'contactos'] }); };

    const marcar = useMutation({
        mutationFn: ({ id, estado }: { id: number; estado: 'read' | 'replied' }) => ferramentas.contactos.marcar(id, estado),
        onSuccess: (r, v) => { feito(r.message); porAVer((a) => (a && a.id === v.id ? { ...a, estado: v.estado } : a)); },
    });
    const apagar = useMutation({
        mutationFn: (id: number) => ferramentas.contactos.apagar(id),
        onSuccess: (r) => { porAApagar(null); porAVer(null); feito(r.message); },
    });

    const mexer = (campo: 'procura' | 'estado', valor: string) => porFiltros((f) => ({ ...f, [campo]: valor || undefined, pagina: 1 }));

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <ErroDoEcra titulo={t('Não foi possível abrir as mensagens de contacto')} erro={lista.error} />;

    const d = lista.data;
    const estados = ESTADO();

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Mensagens de Contacto')} subtitulo={t('Mensagens recebidas pelo formulário de contacto do site')} icone="fa-comments" cor="ciano">
                <EstadoNaFaixa icone="fa-inbox">{t(':n no total', { n: d.numeros.total })}</EstadoNaFaixa>
                {d.numeros.novas > 0 && <EstadoNaFaixa icone="fa-bell">{t(':n por ler', { n: d.numeros.novas })}</EstadoNaFaixa>}
            </Faixa>

            <Recado texto={recado} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={marcar.error} />

            <div className="grid gap-4 sm:grid-cols-3">
                <CartaoNumero rotulo={t('Novas')} valor={kz(d.numeros.novas, 0)} icone="fa-envelope" tom="vermelho" aspecto="claro"
                    aoCarregar={() => mexer('estado', filtros.estado === 'new' ? '' : 'new')} className={cls(filtros.estado === 'new' && 'ring-2 ring-red-300')} />
                <CartaoNumero rotulo={t('Lidas')} valor={kz(d.numeros.lidas, 0)} icone="fa-eye" tom="ambar" aspecto="claro"
                    aoCarregar={() => mexer('estado', filtros.estado === 'read' ? '' : 'read')} className={cls(filtros.estado === 'read' && 'ring-2 ring-amber-300')} />
                <CartaoNumero rotulo={t('Respondidas')} valor={kz(d.numeros.respondidas, 0)} icone="fa-reply" tom="verde" aspecto="claro"
                    aoCarregar={() => mexer('estado', filtros.estado === 'replied' ? '' : 'replied')} className={cls(filtros.estado === 'replied' && 'ring-2 ring-emerald-300')} />
            </div>

            <div className={cls(CARTAO, 'grid gap-3 p-4 md:grid-cols-[1fr_14rem]')}>
                <label className="block">
                    <Rotulo>{t('Procurar')}</Rotulo>
                    <span className="relative block">
                        <i className="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" className={cls(entrada, 'pl-9')} placeholder={t('Buscar por nome, email ou empresa...')}
                            value={filtros.procura ?? ''} onChange={(e) => mexer('procura', e.target.value)} />
                    </span>
                </label>
                <label className="block">
                    <Rotulo>{t('Estado')}</Rotulo>
                    <select className={entrada} value={filtros.estado ?? ''} onChange={(e) => mexer('estado', e.target.value)}>
                        <option value="">{t('Todos os Status')}</option>
                        <option value="new">{t('Novas')}</option>
                        <option value="read">{t('Lidas')}</option>
                        <option value="replied">{t('Respondidas')}</option>
                    </select>
                </label>
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                <header className="flex items-center gap-2 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">
                    <i className="fas fa-inbox text-cyan-500" aria-hidden="true" />{t('Mensagens (:n)', { n: d.paginacao.total })}
                </header>

                {d.mensagens.length === 0 ? <SemNada icone="fa-inbox" frase={t('Nenhuma mensagem encontrada')} /> : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">{t('Nome')}</th>
                                    <th className="px-5 py-3">{t('Email')}</th>
                                    <th className="px-5 py-3">{t('Telefone')}</th>
                                    <th className="px-5 py-3">{t('Empresa')}</th>
                                    <th className="px-5 py-3">{t('Estado')}</th>
                                    <th className="px-5 py-3">{t('Data')}</th>
                                    <th className="px-5 py-3 text-center">{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.mensagens.map((m, i) => (
                                    <tr key={m.id} className={cls('entra hover:bg-slate-50', TRANSICAO, m.estado === 'new' && 'bg-red-50/40')} style={cascata(i)}>
                                        <td className="px-5 py-3 font-semibold text-slate-800">{m.nome}</td>
                                        <td className="px-5 py-3"><a className="text-cyan-700 hover:underline" href={`mailto:${m.email}`}>{m.email}</a></td>
                                        <td className="px-5 py-3 text-slate-600">{m.telefone ? <a className="hover:text-cyan-700" href={`tel:${m.telefone}`}>{m.telefone}</a> : '—'}</td>
                                        <td className="px-5 py-3 text-slate-600">{m.empresa ?? '—'}</td>
                                        <td className="px-5 py-3"><Etiqueta cor={estados[m.estado].cor} ponto>{estados[m.estado].rotulo}</Etiqueta></td>
                                        <td className="whitespace-nowrap px-5 py-3 text-xs text-slate-500">{dataHora(m.recebida_em)}</td>
                                        <td className="px-5 py-3">
                                            <div className="flex items-center justify-center gap-1">
                                                <BotaoDeIcone icone="fa-eye" rotulo={t('Ver detalhes')} cor="text-cyan-700 hover:bg-cyan-50" onClick={() => porAVer(m)} />
                                                {m.estado === 'new' && (
                                                    <BotaoDeIcone icone="fa-check" rotulo={t('Marcar como lida')} cor="text-amber-600 hover:bg-amber-50" onClick={() => marcar.mutate({ id: m.id, estado: 'read' })} />
                                                )}
                                                {m.estado !== 'replied' && (
                                                    <BotaoDeIcone icone="fa-reply" rotulo={t('Marcar como respondida')} cor="text-emerald-600 hover:bg-emerald-50" onClick={() => marcar.mutate({ id: m.id, estado: 'replied' })} />
                                                )}
                                                <BotaoDeIcone icone="fa-trash" rotulo={t('Excluir')} cor="text-red-500 hover:bg-red-50" onClick={() => porAApagar(m)} />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="px-5 pb-4">
                    <Paginas pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} aMudar={(p) => porFiltros((f) => ({ ...f, pagina: p }))} />
                </div>
            </section>

            <Modal
                aberto={aVer !== null}
                aoFechar={() => porAVer(null)}
                titulo={t('Mensagem de :nome', { nome: aVer?.nome ?? '' })}
                icone="fa-envelope-open-text"
                cor="ciano"
                largura="lg"
                rodape={aVer && (
                    <div className="flex flex-wrap justify-end gap-2">
                        {aVer.estado === 'new' && (
                            <Botao cor="aviso" icone="fa-check" aTrabalhar={marcar.isPending} onClick={() => marcar.mutate({ id: aVer.id, estado: 'read' })}>{t('Marcar como lida')}</Botao>
                        )}
                        {aVer.estado !== 'replied' && (
                            <Botao cor="bom" icone="fa-reply" aTrabalhar={marcar.isPending} onClick={() => marcar.mutate({ id: aVer.id, estado: 'replied' })}>{t('Marcar como respondida')}</Botao>
                        )}
                        <a href={`mailto:${aVer.email}`} className={cls('inline-flex h-10 items-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 text-sm font-semibold text-white shadow-md hover:-translate-y-0.5', RAIO, TRANSICAO, FOCO)}>
                            <i className="fas fa-paper-plane" aria-hidden="true" />{t('Responder por Email')}
                        </a>
                        <Botao cor="neutra" onClick={() => porAVer(null)}>{t('Fechar')}</Botao>
                    </div>
                )}
            >
                {aVer && (
                    <div className="space-y-4">
                        <dl className="grid gap-4 md:grid-cols-2">
                            <Dado rotulo={t('Nome')}>{aVer.nome}</Dado>
                            <Dado rotulo={t('Email')}><a className="text-cyan-700 hover:underline" href={`mailto:${aVer.email}`}>{aVer.email}</a></Dado>
                            <Dado rotulo={t('Telefone')}>{aVer.telefone || t('Não informado')}</Dado>
                            <Dado rotulo={t('Empresa')}>{aVer.empresa || t('Não informado')}</Dado>
                            <Dado rotulo={t('Data')}>{dataHora(aVer.recebida_em)}</Dado>
                            <Dado rotulo={t('Endereço IP')}>{aVer.ip || t('Não registado')}</Dado>
                        </dl>
                        <div>
                            <Rotulo>{t('Mensagem')}</Rotulo>
                            <p className={cls('whitespace-pre-wrap border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700', RAIO)}>{aVer.mensagem}</p>
                        </div>
                    </div>
                )}
            </Modal>

            <Confirmar
                aberto={aApagar !== null}
                titulo={t('Excluir a mensagem?')}
                subtitulo={aApagar?.nome}
                rotulo={t('Excluir')}
                aTrabalhar={apagar.isPending}
                erro={apagar.error}
                aoConfirmar={() => aApagar && apagar.mutate(aApagar.id)}
                aoFechar={() => porAApagar(null)}
            >
                <p>{t('Tem certeza que deseja excluir esta mensagem?')}</p>
            </Confirmar>
        </div>
    );
}
