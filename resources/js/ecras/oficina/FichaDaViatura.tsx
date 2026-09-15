import { useQuery } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';

import { folhasDaViatura, type FolhaDaViatura } from '@/api/oficina';
import { ErroDaApi } from '@/api/cliente';
import { t, tn } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { Separadores } from '@/ui/Separadores';
import { FOCO, RAIO, TRANSICAO, cls, data, kz, type CorDeEcra } from '@/ui/tokens';

/**
 * A FICHA DA VIATURA — os dados e as folhas de obra, com a factura de cada uma.
 *
 * Pedido de 15/09/2026: «na modal de ver, mostrar as facturas vinculadas». O
 * que se quer saber de um carro que entra pela porta é o que já se lhe fez,
 * quanto se facturou e o que ainda está por receber — sem ir às ordens de
 * serviço procurar pela matrícula e depois às facturas procurar pelo número.
 *
 * ABRE NAS FOLHAS DE OBRA, porque é por elas que se vem aqui; os dados da
 * viatura estão no separador ao lado, desenhados pelo mesmo esquema do
 * formulário (quem chama passa-os em `ficha`).
 */
const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'aviso',
    scheduled: 'primaria',
    in_progress: 'primaria',
    waiting_parts: 'aviso',
    completed: 'bom',
    delivered: 'bom',
    cancelled: 'perigo',
};

export function FichaDaViatura({ aberto, id, titulo, subtitulo, cor, ficha, fotografias, podeEditar, aoEditar, aoFechar }: {
    aberto: boolean;
    id: number | null;
    titulo: string;
    subtitulo?: string;
    cor: CorDeEcra;
    ficha: ReactNode;
    /** O separador das fotografias — antes, durante, depois e danos. */
    fotografias?: ReactNode;
    podeEditar: boolean;
    aoEditar: () => void;
    aoFechar: () => void;
}) {
    const [aba, porAba] = useState('folhas');

    const dados = useQuery({
        queryKey: ['oficina', 'viatura', id, 'folhas'],
        queryFn: () => folhasDaViatura(id as number),
        enabled: aberto && id !== null,
    });

    const r = dados.data?.resumo;

    return (
        <Modal
            aberto={aberto}
            aoFechar={() => { porAba('folhas'); aoFechar(); }}
            titulo={titulo}
            subtitulo={subtitulo}
            icone="fa-car"
            cor={cor}
            largura="xl"
            rodape={
                <div className="flex flex-wrap justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Fechar')}</Botao>
                    {podeEditar && <Botao cor="primaria" tom="solida" icone="fa-pen" onClick={aoEditar}>{t('Editar')}</Botao>}
                </div>
            }
        >
            <Separadores
                abas={[
                    { chave: 'folhas', rotulo: r ? t('Folhas de obra (:n)', { n: r.ordens }) : t('Folhas de obra'), icone: 'fa-clipboard-list' },
                    { chave: 'viatura', rotulo: t('Viatura'), icone: 'fa-car' },
                    ...(fotografias ? [{ chave: 'fotografias', rotulo: r && r.fotos > 0 ? t('Fotografias (:n)', { n: r.fotos }) : t('Fotografias'), icone: 'fa-camera' }] : []),
                ]}
                activa={aba}
                aoMudar={porAba}
            />

            <div className="mt-4">
                {aba === 'viatura' && <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">{ficha}</div>}
                {aba === 'fotografias' && <div className="animate-fade-in">{fotografias}</div>}

                {aba === 'folhas' && (
                    <>
                        {dados.isPending && <Carregando linhas={4} />}
                        {dados.isError && (
                            <p role="alert" className={cls('border border-red-200 bg-red-50 p-4 text-sm text-red-800', RAIO)}>
                                {dados.error instanceof ErroDaApi ? dados.error.message : t('Não foi possível ler as folhas de obra.')}
                            </p>
                        )}

                        {dados.data && r && (
                            <div className="space-y-4">
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <CartaoNumero aspecto="claro" tom="indigo" icone="fa-clipboard-list" rotulo={t('Folhas de obra')} valor={r.ordens}
                                        nota={r.abertas > 0 ? tn(':n aberta|:n abertas', r.abertas, { n: r.abertas }) : t('Nenhuma aberta')} />
                                    <CartaoNumero aspecto="claro" tom="verde" icone="fa-file-invoice" rotulo={t('Facturado')} valor={<span className="text-2xl sm:text-3xl">{kz(r.facturado)}</span>} sufixo="Kz"
                                        nota={tn(':n factura|:n facturas', r.facturas, { n: r.facturas })} />
                                    <CartaoNumero aspecto="claro" tom={r.por_receber > 0 ? 'vermelho' : 'teal'} icone="fa-hand-holding-dollar" rotulo={t('Por receber')}
                                        valor={<span className="text-2xl sm:text-3xl">{kz(r.por_receber)}</span>} sufixo="Kz" nota={r.por_receber > 0 ? t('Em facturas desta viatura') : t('Nada em falta')} />
                                    <CartaoNumero aspecto="claro" tom="cinza" icone="fa-calendar-check" rotulo={t('Última visita')}
                                        valor={<span className="text-2xl">{r.ultima_visita ? data(r.ultima_visita) : '—'}</span>}
                                        nota={dados.data.viatura.km > 0 ? t(':km km', { km: dados.data.viatura.km.toLocaleString() }) : undefined} />
                                </div>

                                {dados.data.ordens.length === 0 ? (
                                    <SemNada icone="fa-clipboard-list" titulo={t('Sem folhas de obra')} frase={t('Ainda não se abriu nenhuma ordem de serviço para esta viatura.')} />
                                ) : (
                                    <ul className="space-y-2">
                                        {dados.data.ordens.map((f, i) => <Folha key={f.id} f={f} i={i} />)}
                                    </ul>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </Modal>
    );
}

function Folha({ f, i }: { f: FolhaDaViatura; i: number }) {
    const factura = f.factura;

    return (
        <li style={cascata(i)} className={cls('entra group border border-slate-200 bg-white p-4', RAIO, TRANSICAO, 'hover:-translate-y-0.5 hover:shadow-md')}>
            <div className="flex flex-wrap items-start gap-3">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-purple-600 to-pink-600 text-white shadow transition-transform duration-300 group-hover:scale-110">
                    <i className="fas fa-clipboard-list" aria-hidden="true" />
                </span>

                <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">{t('Folha de obra')}</span>
                        <a href={`/workshop/work-orders?ordem=${f.id}`} className={cls('font-mono text-base font-bold text-slate-900 hover:text-indigo-700 hover:underline', FOCO)}>
                            {f.numero}
                        </a>
                        <Etiqueta cor={COR_DO_ESTADO[f.estado] ?? 'neutra'}>{f.estado_rotulo}</Etiqueta>
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {data(f.entrada)}
                        {f.mecanico && <> · <i className="fas fa-user-gear mr-0.5" aria-hidden="true" />{f.mecanico}</>}
                        {f.km > 0 && <> · {t(':km km', { km: f.km.toLocaleString() })}</>}
                    </p>
                    {f.problema && <p className="mt-1 line-clamp-2 text-sm text-slate-600">{f.problema}</p>}
                </div>

                <div className="text-right">
                    <p className="text-xs text-slate-400">{t('Total da ordem')}</p>
                    <p className="font-bold tabular-nums text-slate-900">{kz(f.total)} <span className="text-xs font-normal text-slate-400">Kz</span></p>
                </div>
            </div>

            {/* A FACTURA LIGADA — número, estado e o que falta receber. */}
            <div className={cls('mt-3 flex flex-wrap items-center gap-2 border-t border-dashed border-slate-200 pt-3 text-sm')}>
                {factura ? (
                    <>
                        <i className="fas fa-file-invoice text-emerald-600" aria-hidden="true" />
                        <span className="text-slate-500">{t('Factura')}</span>
                        {factura.preview
                            ? <a href={factura.preview} target="_blank" rel="noreferrer" className={cls('font-mono font-bold text-indigo-700 hover:underline', FOCO)}>{factura.numero}</a>
                            : <span className="font-mono font-bold text-slate-800">{factura.numero}</span>}
                        {factura.pdf && (
                            <a href={factura.pdf} target="_blank" rel="noreferrer" title={t('PDF')} aria-label={t('PDF da factura :n', { n: factura.numero })}
                                className={cls('grid h-7 w-7 place-items-center bg-red-50 text-red-600 hover:bg-red-100', RAIO, TRANSICAO, FOCO)}>
                                <i className="fas fa-file-pdf" aria-hidden="true" />
                            </a>
                        )}
                        <Etiqueta cor={factura.estado === 'paid' ? 'bom' : factura.estado === 'cancelled' || factura.estado === 'credited' ? 'perigo' : factura.estado === 'draft' ? 'neutra' : 'aviso'}>
                            {factura.estado_rotulo}
                        </Etiqueta>
                        <span className="text-xs text-slate-500">{data(factura.data)}</span>
                        <span className="ml-auto flex flex-wrap items-center gap-2">
                            <span className="text-xs text-slate-500">{kz(factura.total)} Kz</span>
                            {factura.falta > 0 ? (
                                <Etiqueta cor={factura.vencida ? 'perigo' : 'aviso'} icone={factura.vencida ? 'fa-triangle-exclamation' : 'fa-hourglass-half'}>
                                    {factura.vencida
                                        ? t('Vencida · falta :v Kz', { v: kz(factura.falta) })
                                        : t('Falta :v Kz', { v: kz(factura.falta) })}
                                </Etiqueta>
                            ) : factura.estado !== 'draft' && factura.estado !== 'cancelled' ? (
                                <Etiqueta cor="bom" icone="fa-check">{t('Nada em falta')}</Etiqueta>
                            ) : null}
                        </span>
                    </>
                ) : (
                    <span className="flex items-center gap-2 text-slate-400">
                        <i className="fas fa-file-circle-xmark" aria-hidden="true" />
                        {t('Ainda sem factura')}
                    </span>
                )}
            </div>
        </li>
    );
}
