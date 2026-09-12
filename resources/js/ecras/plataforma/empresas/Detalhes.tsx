import { useQuery } from '@tanstack/react-query';

import { plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { cls, kz } from '@/ui/tokens';

import { corDaSubscricao } from './cores';

/**
 * A FICHA DE UMA EMPRESA, PARA LER.
 *
 * O modal antigo mostrava contactos, limites, utilizadores e módulos. Aqui
 * juntam-se três coisas que obrigavam a abrir outros ecrãs para as saber: a
 * morada e o país, porque é que está desactivada (e desde quando), e quantos
 * documentos já emitiu contra o tecto que tem.
 */
export function Detalhes({ id, aoFechar, aoEditar }: { id: number; aoFechar: () => void; aoEditar: () => void }) {
    const ficha = useQuery({
        queryKey: ['plataforma', 'empresas', 'ver', id],
        queryFn: () => plataforma.empresas.ver(id),
    });

    const e = ficha.data?.empresa;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={e?.nome ?? t('Empresa')}
            subtitulo={e?.slug}
            icone="fa-building"
            cor="primaria"
            largura="xl"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Fechar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-pen" onClick={aoEditar}>{t('Editar')}</Botao>
                </div>
            }
        >
            {ficha.isPending || !e ? (
                <Carregando linhas={8} />
            ) : (
                <div className="space-y-4">
                    <div className="flex flex-wrap gap-2">
                        <Etiqueta cor={e.activa ? 'bom' : 'neutra'} ponto>{e.activa ? t('Activa') : t('Desactivada')}</Etiqueta>
                        <Etiqueta cor={corDaSubscricao(e.subscricao.cor)} icone={e.subscricao.icone}>
                            {e.plano ?? e.subscricao.rotulo}
                            {e.subscricao.falta && <span className="font-normal">· {e.subscricao.falta}</span>}
                        </Etiqueta>
                        {e.vida && <Etiqueta cor="primaria" icone="fa-heart-pulse">{e.vida.texto}</Etiqueta>}
                    </div>

                    {!e.activa && e.motivo_da_desactivacao && (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            <p className="font-bold">
                                <i className="fas fa-power-off mr-2" aria-hidden="true" />
                                {e.desactivada_em ? t('Desactivada a :dia', { dia: e.desactivada_em }) : t('Desactivada')}
                            </p>
                            <p className="mt-1">{e.motivo_da_desactivacao}</p>
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-2">
                        <Bloco titulo={t('Contacto')} icone="fa-address-card" tom="bg-blue-50 text-blue-900">
                            <Linha rotulo={t('Email')} valor={e.email} />
                            <Linha rotulo={t('Telefone')} valor={e.telefone} />
                            <Linha rotulo={t('Morada')} valor={[e.morada, e.codigo_postal, e.cidade].filter(Boolean).join(', ') || null} />
                            <Linha rotulo={t('País')} valor={e.pais} />
                        </Bloco>

                        <Bloco titulo={t('Empresa')} icone="fa-briefcase" tom="bg-purple-50 text-purple-900">
                            <Linha rotulo={t('Razão social')} valor={e.razao_social} />
                            <Linha
                                rotulo={t('NIF')}
                                valor={e.nif ? (
                                    <>
                                        <span className="font-mono">{e.nif}</span>
                                        {e.nif_de_empresa === false && (
                                            <span className="ml-2 text-xs font-semibold text-amber-700">{t('não é NIF de empresa')}</span>
                                        )}
                                    </>
                                ) : null}
                            />
                            <Linha rotulo={t('Criada em')} valor={e.criada_em} />
                            <Linha rotulo={t('Actualizada em')} valor={e.actualizada_em} />
                        </Bloco>

                        <Bloco titulo={t('Limites')} icone="fa-chart-bar" tom="bg-emerald-50 text-emerald-900">
                            <Linha rotulo={t('Utilizadores na ficha')} valor={String(e.max_utilizadores)} />
                            <Linha rotulo={t('Limite que vale')} valor={e.limite_de_utilizadores === 0 ? t('sem limite') : String(e.limite_de_utilizadores)} />
                            {e.ficha_abaixo_do_plano && (
                                <p className="text-xs font-semibold text-amber-700">
                                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                                    {t('A ficha diz menos do que o plano dá — vale o plano.')}
                                </p>
                            )}
                            <Linha rotulo={t('Espaço')} valor={t(':n MB', { n: e.max_espaco_mb })} />
                            <Linha
                                rotulo={t('Documentos emitidos')}
                                valor={e.limite_de_documentos === null
                                    ? t(':n (sem tecto)', { n: kz(e.documentos_emitidos, 0) })
                                    : t(':n de :t', { n: kz(e.documentos_emitidos, 0), t: kz(e.limite_de_documentos, 0) })}
                            />
                        </Bloco>

                        <Bloco titulo={t('Utilizadores (:n)', { n: e.pessoas.length })} icone="fa-users" tom="bg-orange-50 text-orange-900">
                            {e.pessoas.length === 0 ? (
                                <p className="text-sm text-slate-500">{t('Ninguém tem acesso a esta empresa.')}</p>
                            ) : (
                                <ul className="max-h-40 space-y-1 overflow-y-auto text-sm">
                                    {e.pessoas.map((p) => (
                                        <li key={p.id} className="truncate">
                                            <b className="font-semibold text-slate-800">{p.nome}</b>
                                            <span className="ml-1.5 text-xs text-slate-500">{p.email}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Bloco>
                    </div>

                    <div>
                        <h4 className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-puzzle-piece mr-1.5 text-indigo-500" aria-hidden="true" />
                            {t('Módulos (:n)', { n: e.modulos_lista.length })}
                        </h4>
                        <div className="flex flex-wrap gap-2">
                            {e.modulos_lista.map((m) => (
                                <span
                                    key={m.id}
                                    className={cls(
                                        'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold',
                                        m.activo ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-400 line-through',
                                    )}
                                >
                                    <i className={cls('fas', m.icone)} aria-hidden="true" />
                                    {m.nome}
                                </span>
                            ))}
                            {e.modulos_lista.length === 0 && <span className="text-sm text-slate-400">{t('Sem módulos.')}</span>}
                        </div>
                    </div>
                </div>
            )}
        </Modal>
    );
}

function Bloco({ titulo, icone, tom, children }: { titulo: string; icone: string; tom: string; children: React.ReactNode }) {
    return (
        <section className={cls('space-y-2 rounded-xl p-4', tom)}>
            <h4 className="flex items-center gap-2 font-bold">
                <i className={cls('fas', icone)} aria-hidden="true" />{titulo}
            </h4>
            {children}
        </section>
    );
}

function Linha({ rotulo, valor }: { rotulo: string; valor: React.ReactNode | null }) {
    return (
        <div>
            <p className="text-xs font-semibold opacity-70">{rotulo}</p>
            <p className="text-sm text-slate-900">{valor ?? <span className="text-slate-400">—</span>}</p>
        </div>
    );
}
