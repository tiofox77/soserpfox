import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import {
    type FacturaDaPlataforma, type FacturacaoDaPlataforma, type FichaDaFactura, type PedidoPendente,
    type SubscricaoDaLista, plataforma,
} from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';

type Cor = 'bom' | 'aviso' | 'perigo' | 'neutra' | 'primaria';

/**
 * A FACTURAÇÃO DA PLATAFORMA — onde o dono do SaaS cobra aos clientes.
 *
 * Três separadores (pedidos à espera, subscrições, facturas) e, por cima, a
 * configuração do SAF-T do software, que é global.
 *
 * O QUE MELHOROU:
 *
 *  · A LISTA DAS SUBSCRIÇÕES vinha inteira, sem paginação, a cada render — com o
 *    separador fechado ou aberto. Pagina-se, e só se pede quando se abre.
 *  · O «EXCLUIR ESTA FATURA?» do `confirm()` do browser passou a uma janela do
 *    ecrã, que num telemóvel não se aceita sem ler.
 *  · O RESUMO DA SUBSCRIÇÃO vem do servidor, pela regra que grava.
 */
export default function Facturacao() {
    const fila = useQueryClient();
    const [aba, porAba] = useState('pedidos');
    const [recado, porRecado] = useRecadoNoCanto(null);

    const dados = useQuery({
        queryKey: ['plataforma', 'facturacao'],
        queryFn: plataforma.facturacao.ler,
        staleTime: 15_000,
    });

    const feito = (m: string) => {
        porRecado(m);
        void fila.invalidateQueries({ queryKey: ['plataforma', 'facturacao'] });
    };

    if (dados.isPending) return <Carregando linhas={10} />;

    if (dados.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a facturação')}</h2>
                <p className="text-sm text-red-800">
                    {dados.error instanceof ErroDaApi ? dados.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const d = dados.data;
    const n = d.numeros;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Facturação da plataforma')}
                subtitulo={t('Pedidos, subscrições e facturas dos clientes')}
                icone="fa-file-invoice-dollar"
                cor="teal"
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-hourglass-half">
                        {t(':n pedido(s) por aprovar', { n: n.pedidos_pendentes })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-crown">{t(':n subscrição(ões)', { n: n.subscricoes })}</EstadoNaFaixa>
                </div>
            </Faixa>

            {recado && (
                <div role="status" className={cls('entra flex items-start justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado(null)} className={cls('text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-xmark" aria-hidden="true" /><span className="sr-only">{t('Fechar')}</span>
                    </button>
                </div>
            )}

            <Saft saft={d.saft} aoGuardar={feito} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero rotulo={t('Receita cobrada')} valor={kz(n.cobrado)} sufixo="Kz" icone="fa-circle-check" tom="verde" aspecto="claro" />
                <CartaoNumero rotulo={t('Pendente')} valor={kz(n.pendente)} sufixo="Kz" icone="fa-clock" tom="ambar" aspecto="claro" />
                <CartaoNumero rotulo={t('Vencido')} valor={kz(n.vencido)} sufixo="Kz" icone="fa-triangle-exclamation" tom={n.vencido > 0 ? 'vermelho' : 'cinza'} aspecto="claro" />
                <CartaoNumero rotulo={t('Facturas')} valor={String(n.facturas)} icone="fa-file-invoice" tom="azul" aspecto="claro" nota={t('documentos')} />
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <Separadores
                    abas={[
                        { chave: 'pedidos', rotulo: t('Pedidos pendentes (:n)', { n: d.pedidos.length }), icone: 'fa-hourglass-half' },
                        { chave: 'subscricoes', rotulo: t('Subscrições (:n)', { n: n.subscricoes }), icone: 'fa-crown' },
                        { chave: 'facturas', rotulo: t('Facturas (:n)', { n: n.facturas }), icone: 'fa-file-invoice' },
                    ]}
                    activa={aba}
                    aoMudar={porAba}
                />

                <div className="pt-4">
                    <PainelDoSeparador chave="pedidos" activa={aba}>
                        <Pedidos pedidos={d.pedidos} aoFazer={feito} />
                    </PainelDoSeparador>
                    <PainelDoSeparador chave="subscricoes" activa={aba}>
                        {aba === 'subscricoes' && <Subscricoes opcoes={d.opcoes} aoFazer={feito} />}
                    </PainelDoSeparador>
                    <PainelDoSeparador chave="facturas" activa={aba}>
                        {aba === 'facturas' && <Facturas opcoes={d.opcoes} aoFazer={feito} />}
                    </PainelDoSeparador>
                </div>
            </div>
        </div>
    );
}

/* ─── O SAF-T do software ─────────────────────────────────────────────── */

/**
 * CONFIGURAÇÃO GLOBAL DO SAF-T — o certificado e o identificador do software
 * junto da AGT. Vale para todas as empresas, e por isso vive fechada.
 */
function Saft({ saft, aoGuardar }: { saft: FacturacaoDaPlataforma['saft']; aoGuardar: (m: string) => void }) {
    const [aberto, porAberto] = useState(false);
    const [f, porF] = useState(saft);

    useEffect(() => porF(saft), [saft]);

    const guardar = useMutation({
        mutationFn: () => plataforma.facturacao.guardarSaft(f),
        onSuccess: (r) => aoGuardar(r.message),
    });

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};

    return (
        <section className={cls(CARTAO, 'border-2 border-red-200')}>
            <button
                type="button"
                onClick={() => porAberto((a) => !a)}
                aria-expanded={aberto}
                className={cls('flex w-full items-center justify-between gap-3 px-5 py-4 text-left', FOCO, RAIO)}
            >
                <span className="flex items-center gap-3">
                    <span className="grid h-10 w-10 place-items-center rounded-xl bg-red-100 text-red-600">
                        <i className="fas fa-certificate" aria-hidden="true" />
                    </span>
                    <span>
                        <span className="block font-bold text-slate-900">{t('Software certificado (SAF-T AO)')}</span>
                        <span className="block text-xs text-slate-500">{t('Vale para todas as empresas. Só o dono da plataforma mexe aqui.')}</span>
                    </span>
                </span>
                <i className={cls('fas fa-chevron-down text-slate-400 transition-transform', aberto && 'rotate-180')} aria-hidden="true" />
            </button>

            {aberto && (
                <div className="entra space-y-4 border-t border-red-100 px-5 py-4">
                    <AvisoDeErro erro={guardar.error} />
                    <div className="grid gap-4 md:grid-cols-3">
                        <Campo etiqueta={t('Certificado do software')} erro={erros.certificado}>
                            <input className={entrada} value={f.certificado} placeholder="AGT/2024/XXXX" onChange={(e) => porF({ ...f, certificado: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Identificador do produto')} erro={erros.produto}>
                            <input className={entrada} value={f.produto} placeholder="SOSERP/v1.0" onChange={(e) => porF({ ...f, produto: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Versão do formato')} obrigatorio erro={erros.versao}>
                            <input className={entrada} value={f.versao} placeholder="1.0.0" onChange={(e) => porF({ ...f, versao: e.target.value })} />
                        </Campo>
                    </div>
                    <div className="flex justify-end">
                        <Botao cor="perigo" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>
                            {t('Guardar configuração do SAF-T')}
                        </Botao>
                    </div>
                </div>
            )}
        </section>
    );
}

/* ─── Os pedidos ──────────────────────────────────────────────────────── */

function Pedidos({ pedidos, aoFazer }: { pedidos: PedidoPendente[]; aoFazer: (m: string) => void }) {
    const [aRecusar, porARecusar] = useState<PedidoPendente | null>(null);

    const aprovar = useMutation({
        mutationFn: (id: number) => plataforma.facturacao.aprovarPedido(id),
        onSuccess: (r) => aoFazer(r.message),
    });

    if (pedidos.length === 0) {
        return <SemNada icone="fa-inbox" titulo={t('Nenhum pedido à espera')} frase={t('Os pedidos de subscrição dos clientes aparecem aqui para aprovar.')} />;
    }

    return (
        <div className="space-y-3">
            <AvisoDeErro erro={aprovar.error} />

            {pedidos.map((p, i) => (
                <article key={p.id} className={cls('entra border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-5', RAIO)} style={cascata(i)}>
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="min-w-0 flex-1 space-y-3">
                            <div className="flex items-center gap-3">
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-orange-500 text-white">
                                    <i className="fas fa-building" aria-hidden="true" />
                                </span>
                                <div className="min-w-0">
                                    <h4 className="truncate font-bold text-slate-900">
                                        {p.empresa ?? t('— empresa apagada —')}
                                        {p.empresa_apagada && <span className="ml-2"><Etiqueta cor="perigo">{t('apagada')}</Etiqueta></span>}
                                    </h4>
                                    <p className="truncate text-xs text-slate-600">{p.pessoa ?? t('utilizador apagado')} · {p.email ?? '—'}</p>
                                </div>
                            </div>

                            <dl className="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                                <Dado rotulo={t('Plano')} valor={p.plano ?? t('— plano apagado —')} />
                                <Dado rotulo={t('Valor')} valor={`${kz(p.valor)} Kz`} />
                                <Dado rotulo={t('Ciclo')} valor={p.ciclo} />
                                <Dado rotulo={t('Data')} valor={p.dia ?? '—'} />
                            </dl>

                            {/* COMO PAGOU, E A PROVA. Silêncio não se distingue de
                                «ainda não verifiquei». */}
                            <div className="flex flex-wrap items-center gap-2 text-xs">
                                {p.metodo && <Etiqueta cor="neutra" icone="fa-money-bill-wave">{p.metodo}</Etiqueta>}
                                {p.referencia && <Etiqueta cor="neutra" icone="fa-hashtag">{p.referencia}</Etiqueta>}
                                {p.comprovativo ? (
                                    <a href={p.comprovativo} target="_blank" rel="noreferrer" className={cls('inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-200 hover:bg-emerald-200', TRANSICAO, FOCO)}>
                                        <i className="fas fa-file-invoice-dollar" aria-hidden="true" />{t('Ver comprovativo')}
                                    </a>
                                ) : (
                                    <Etiqueta cor="aviso" icone="fa-triangle-exclamation">{t('Sem comprovativo anexado')}</Etiqueta>
                                )}
                            </div>
                        </div>

                        <div className="flex shrink-0 gap-2 lg:flex-col">
                            <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={aprovar.isPending && aprovar.variables === p.id} onClick={() => aprovar.mutate(p.id)}>
                                {t('Aprovar')}
                            </Botao>
                            <Botao cor="perigo" tom="suave" icone="fa-xmark" onClick={() => porARecusar(p)}>
                                {t('Recusar')}
                            </Botao>
                        </div>
                    </div>
                </article>
            ))}

            {aRecusar && <Recusar pedido={aRecusar} aoFechar={() => porARecusar(null)} aoFazer={(m) => { porARecusar(null); aoFazer(m); }} />}
        </div>
    );
}

/**
 * RECUSAR PEDE O MOTIVO. A coluna existia desde sempre e nunca era preenchida:
 * o cliente recebia «Não especificado» e ficava sem saber o que correu mal.
 */
function Recusar({ pedido, aoFechar, aoFazer }: { pedido: PedidoPendente; aoFechar: () => void; aoFazer: (m: string) => void }) {
    const [motivo, porMotivo] = useState('');

    const recusar = useMutation({
        mutationFn: () => plataforma.facturacao.recusarPedido(pedido.id, motivo),
        onSuccess: (r) => aoFazer(r.message),
    });

    const erros = recusar.error instanceof ErroDaApi ? recusar.error.erros : {};

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Recusar o pedido')}
            subtitulo={`${pedido.empresa ?? '—'} · ${pedido.plano ?? '—'} · ${kz(pedido.valor)} Kz`}
            icone="fa-ban"
            cor="perigo"
            largura="md"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="perigo" tom="solida" icone="fa-xmark" aTrabalhar={recusar.isPending} onClick={() => recusar.mutate()}>{t('Recusar pedido')}</Botao>
                </div>
            }
        >
            <div className="space-y-3">
                <AvisoDeErro erro={recusar.error} />
                <Campo etiqueta={t('Motivo')} obrigatorio erro={erros.motivo} ajuda={t('É o que o cliente vai ler no email.')}>
                    <textarea rows={4} className={cls(entrada, 'h-auto py-2')} value={motivo} onChange={(e) => porMotivo(e.target.value)} placeholder={t('Ex.: o valor transferido não bate certo com o plano escolhido.')} />
                </Campo>
            </div>
        </Modal>
    );
}

/* ─── As subscrições ──────────────────────────────────────────────────── */

function Subscricoes({ opcoes, aoFazer }: { opcoes: FacturacaoDaPlataforma['opcoes']; aoFazer: (m: string) => void }) {
    const fila = useQueryClient();
    const [f, porF] = useState<{ procura?: string; estado?: string; pagina: number }>({ pagina: 1 });
    const [aEditar, porAEditar] = useState<number | 'nova' | null>(null);
    const [aApagar, porAApagar] = useState<SubscricaoDaLista | null>(null);
    const [aberta, porAberta] = useState<number | null>(null);

    const lista = useQuery({
        queryKey: ['plataforma', 'facturacao', 'subscricoes', f],
        queryFn: () => plataforma.facturacao.subscricoes(f),
        placeholderData: keepPreviousData,
    });

    const refrescar = (m: string) => {
        aoFazer(m);
        void fila.invalidateQueries({ queryKey: ['plataforma', 'facturacao', 'subscricoes'] });
    };

    const cancelar = useMutation({
        mutationFn: (id: number) => plataforma.facturacao.cancelarSubscricao(id),
        onSuccess: (r) => refrescar(r.message),
    });

    const apagar = useMutation({
        mutationFn: (id: number) => plataforma.facturacao.apagarSubscricao(id),
        onSuccess: (r) => { porAApagar(null); refrescar(r.message); },
    });

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end gap-3">
                <label className="block min-w-[14rem] flex-1">
                    <Rotulo>{t('Procurar')}</Rotulo>
                    <input type="search" className={entrada} placeholder={t('Empresa ou plano…')} value={f.procura ?? ''} onChange={(e) => porF({ ...f, procura: e.target.value || undefined, pagina: 1 })} />
                </label>
                <label className="block w-48">
                    <Rotulo>{t('Estado')}</Rotulo>
                    <select className={entrada} value={f.estado ?? ''} onChange={(e) => porF({ ...f, estado: e.target.value || undefined, pagina: 1 })}>
                        <option value="">{t('Todos')}</option>
                        {ESTADOS_DA_SUBSCRICAO().map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                    </select>
                </label>
                <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porAEditar('nova')}>{t('Nova subscrição')}</Botao>
            </div>

            <AvisoDeErro erro={cancelar.error ?? apagar.error} />

            {lista.isPending ? <Carregando linhas={5} /> : !lista.data || lista.data.subscricoes.length === 0 ? (
                <SemNada icone="fa-crown" frase={t('Nenhuma subscrição com estes filtros.')} />
            ) : (
                <div className="space-y-3">
                    {lista.data.subscricoes.map((s, i) => {
                        const estado = ESTADOS_DA_SUBSCRICAO().find((e) => e.valor === s.estado);

                        return (
                            <article key={s.id} className={cls(CARTAO, 'card-hover cascata p-5')} style={cascata(i)}>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span className={cls('grid h-10 w-10 shrink-0 place-items-center rounded-lg text-white bg-gradient-to-br', s.estado === 'active' ? 'from-purple-500 to-blue-600' : 'from-slate-400 to-slate-500')}>
                                            <i className="fas fa-crown" aria-hidden="true" />
                                        </span>
                                        <div className="min-w-0">
                                            <h4 className="truncate font-bold text-slate-900">{s.empresa ?? t('— empresa apagada —')}</h4>
                                            <p className="text-sm text-slate-600">{t('Plano')}: <b className="text-purple-700">{s.plano ?? t('— plano apagado —')}</b></p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Etiqueta cor={estado?.cor ?? 'neutra'} ponto>{estado?.rotulo ?? s.estado}</Etiqueta>
                                        {s.cancelada_em && <Etiqueta cor="aviso" icone="fa-ban">{t('cancelada a :dia', { dia: s.cancelada_em })}</Etiqueta>}
                                        <button type="button" onClick={() => porAberta(aberta === s.id ? null : s.id)} aria-expanded={aberta === s.id} className={cls('grid h-8 w-8 place-items-center text-slate-400 hover:bg-slate-100', RAIO, FOCO)}>
                                            <i className={cls('fas fa-chevron-down transition-transform', aberta === s.id && 'rotate-180')} aria-hidden="true" />
                                            <span className="sr-only">{t('Ver detalhes')}</span>
                                        </button>
                                    </div>
                                </div>

                                <dl className="mt-3 grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
                                    <Dado rotulo={t('Valor')} valor={`${kz(s.valor)} Kz`} />
                                    <Dado rotulo={t('Ciclo')} valor={s.dias_personalizados ? t(':n dias à medida', { n: s.dias_personalizados }) : s.ciclo} />
                                    <Dado rotulo={t('Início')} valor={s.inicio ?? '—'} />
                                    <Dado rotulo={t('Renovação')} valor={s.renovacao ?? '—'} />
                                    <Dado rotulo={t('Dias restantes')} valor={<DiasRestantes dias={s.dias} />} />
                                </dl>

                                {aberta === s.id && (
                                    <div className="entra mt-4 space-y-4 border-t border-slate-100 pt-4">
                                        {s.limites && (
                                            <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                                                <Limite rotulo={t('Utilizadores')} valor={String(s.limites.utilizadores)} />
                                                <Limite rotulo={t('Empresas')} valor={s.limites.empresas >= 999 ? '∞' : String(s.limites.empresas)} />
                                                <Limite rotulo={t('Espaço')} valor={s.limites.espaco_mb >= 1024 ? t(':n GB', { n: Math.round(s.limites.espaco_mb / 102.4) / 10 }) : t(':n MB', { n: s.limites.espaco_mb })} />
                                                <Limite rotulo={t('Dias de ensaio')} valor={String(s.limites.dias_de_ensaio)} />
                                            </div>
                                        )}
                                        {s.preco_por_utilizador !== null && (
                                            <p className="text-xs text-slate-600">{t(':n utilizador(es) × :p Kz', { n: s.utilizadores_cobrados ?? 0, p: kz(s.preco_por_utilizador) })}</p>
                                        )}
                                        {s.modulos.length > 0 && (
                                            <div className="flex flex-wrap gap-1.5">
                                                {s.modulos.map((m) => (
                                                    <span key={m.nome} className="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">
                                                        <i className={cls('fas', m.icone)} aria-hidden="true" />{m.nome}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                        {s.funcionalidades.length > 0 && (
                                            <ul className="grid gap-1 text-xs text-slate-700 md:grid-cols-2">
                                                {s.funcionalidades.map((x) => <li key={x}><i className="fas fa-check mr-1.5 text-emerald-500" aria-hidden="true" />{x}</li>)}
                                            </ul>
                                        )}
                                    </div>
                                )}

                                <div className="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                                    <Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-pen" onClick={() => porAEditar(s.id)}>{t('Editar')}</Botao>
                                    {['active', 'trial'].includes(s.estado) && !s.cancelada_em && (
                                        <Botao cor="aviso" tom="suave" altura="pequeno" icone="fa-ban" aTrabalhar={cancelar.isPending && cancelar.variables === s.id} onClick={() => cancelar.mutate(s.id)}>
                                            {t('Cancelar')}
                                        </Botao>
                                    )}
                                    {s.pode_apagar && (
                                        <Botao cor="perigo" tom="suave" altura="pequeno" icone="fa-trash" onClick={() => porAApagar(s)}>{t('Apagar')}</Botao>
                                    )}
                                </div>
                            </article>
                        );
                    })}
                    <Paginas pagina={lista.data.paginacao.pagina} ultima={lista.data.paginacao.ultima} aMudar={(p) => porF({ ...f, pagina: p })} />
                </div>
            )}

            {aEditar !== null && (
                <FormularioDaSubscricao
                    id={aEditar === 'nova' ? null : aEditar}
                    opcoes={opcoes}
                    aoFechar={() => porAEditar(null)}
                    aoGuardar={(m) => { porAEditar(null); refrescar(m); }}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar a subscrição?')}
                subtitulo={aApagar ? `${aApagar.empresa ?? '—'} · ${aApagar.plano ?? '—'}` : undefined}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porAApagar(null)}>{t('Deixar estar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar.id)}>{t('Apagar')}</Botao>
                    </div>
                }
            >
                <p className="text-sm text-slate-700">{t('A subscrição sai do registo. As facturas que emitiu ficam.')}</p>
            </Modal>
        </div>
    );
}

function FormularioDaSubscricao({ id, opcoes, aoFechar, aoGuardar }: {
    id: number | null;
    opcoes: FacturacaoDaPlataforma['opcoes'];
    aoFechar: () => void;
    aoGuardar: (m: string) => void;
}) {
    const [empresa, porEmpresa] = useState('');
    const [plano, porPlano] = useState<number | null>(null);
    const [ciclo, porCiclo] = useState('monthly');
    const [comOferta, porComOferta] = useState(true);
    const [dias, porDias] = useState('');
    const [preco, porPreco] = useState('');
    const [utilizadores, porUtilizadores] = useState('');
    const [pago, porPago] = useState(true);
    const [metodo, porMetodo] = useState('bank_transfer');
    const [referencia, porReferencia] = useState('');
    const [nomeDaEmpresa, porNomeDaEmpresa] = useState<string | null>(null);

    const ficha = useQuery({
        queryKey: ['plataforma', 'facturacao', 'subscricao', id],
        queryFn: () => plataforma.facturacao.fichaDaSubscricao(id!),
        enabled: id !== null,
    });

    useEffect(() => {
        const s = ficha.data?.ficha;

        if (!s) return;

        porEmpresa(String(s.tenant_id));
        porNomeDaEmpresa(s.empresa);
        porPlano(s.plan_id);
        porCiclo(s.billing_cycle);
        porComOferta(s.com_oferta);
        porDias(s.dias ? String(s.dias) : '');
        porPreco(s.preco_por_utilizador !== null ? String(s.preco_por_utilizador) : '');
        porUtilizadores(s.utilizadores ? String(s.utilizadores) : '');
        porPago(s.pago);
    }, [ficha.data]);

    // UM AVISO AO ESCOLHER A EMPRESA: já tem uma subscrição activa?
    const activa = useQuery({
        queryKey: ['plataforma', 'facturacao', 'activa', empresa],
        queryFn: () => plataforma.facturacao.activaDe(Number(empresa)),
        enabled: id === null && empresa !== '',
    });

    const escolha = useMemo(() => ({
        plan_id: plano,
        billing_cycle: ciclo,
        com_oferta: comOferta,
        dias: dias === '' ? null : Number(dias),
        preco_por_utilizador: preco === '' ? null : Number(preco),
        utilizadores: utilizadores === '' ? null : Number(utilizadores),
    }), [plano, ciclo, comOferta, dias, preco, utilizadores]);

    const [atrasada, porAtrasada] = useState(escolha);

    useEffect(() => {
        const r = window.setTimeout(() => porAtrasada(escolha), 400);

        return () => window.clearTimeout(r);
    }, [escolha]);

    const resumo = useQuery({
        queryKey: ['plataforma', 'facturacao', 'resumo', atrasada],
        queryFn: () => plataforma.facturacao.resumo(atrasada),
        enabled: atrasada.plan_id !== null,
        placeholderData: keepPreviousData,
        retry: false,
    });

    const guardar = useMutation({
        mutationFn: () => plataforma.facturacao.guardarSubscricao(id, {
            ...escolha,
            tenant_id: empresa === '' ? null : Number(empresa),
            pago,
            metodo: pago ? metodo : null,
            referencia: pago ? referencia || null : null,
        }),
        onSuccess: (r) => aoGuardar(r.message),
    });

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const r = resumo.data;
    const planoEscolhido = opcoes.planos.find((p) => p.id === plano);

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={id ? t('Editar subscrição') : t('Nova subscrição')}
            subtitulo={t('O plano, o ciclo, o acordo e se já está pago')}
            icone="fa-crown"
            cor="roxo"
            largura="xl"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" disabled={!plano} aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao>
                </div>
            }
        >
            {id !== null && ficha.isPending ? <Carregando linhas={8} /> : (
                <div className="space-y-5">
                    <AvisoDeErro erro={guardar.error} />

                    {/* A EDITAR, A EMPRESA ESTÁ FECHADA: uma subscrição não muda de dono. */}
                    {id !== null ? (
                        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                            <p className="font-semibold text-slate-800"><i className="fas fa-lock mr-2 text-slate-400" aria-hidden="true" />{nomeDaEmpresa ?? t('Empresa #:id', { id: empresa })}</p>
                            <p className="text-xs text-slate-500">{t('A empresa de uma subscrição não se altera.')}</p>
                        </div>
                    ) : (
                        <Campo etiqueta={t('Empresa')} obrigatorio erro={erros.tenant_id}>
                            <select className={entrada} value={empresa} onChange={(e) => porEmpresa(e.target.value)}>
                                <option value="">{t('Escolha uma empresa…')}</option>
                                {opcoes.empresas.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                            </select>
                        </Campo>
                    )}

                    {activa.data?.activa && (
                        <p className="entra rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                            {t('Esta empresa já tem uma subscrição activa (:plano). Ao continuar, renova ou actualiza essa.', { plano: activa.data.activa.plano ?? '—' })}
                        </p>
                    )}

                    <fieldset>
                        <legend className="mb-2 text-sm font-bold text-slate-800">{t('Plano')}</legend>
                        {erros.plan_id && <p className="mb-2 text-xs text-red-600">{erros.plan_id[0]}</p>}
                        <div className="grid gap-2 md:grid-cols-2" role="radiogroup">
                            {opcoes.planos.map((p) => (
                                <button key={p.id} type="button" role="radio" aria-checked={plano === p.id} onClick={() => porPlano(p.id)}
                                    className={cls('border-2 p-3 text-left', RAIO, TRANSICAO, FOCO, plano === p.id ? 'border-purple-600 bg-purple-50' : 'border-slate-200 hover:border-purple-300')}>
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="font-bold text-slate-900">{p.nome}</span>
                                        <span className="text-sm font-bold text-purple-700">{kz(p.preco_mensal)} Kz</span>
                                    </span>
                                    <span className="mt-1 block text-xs text-slate-500">
                                        {t(':u utilizadores · :e MB · :m módulos', { u: p.max_utilizadores, e: p.max_espaco_mb, m: p.modulos })}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend className="mb-2 text-sm font-bold text-slate-800">{t('Ciclo de facturação')}</legend>
                        <div className="grid grid-cols-2 gap-2 md:grid-cols-4" role="radiogroup">
                            {[
                                ['monthly', t('Mensal'), planoEscolhido?.preco_mensal],
                                ['quarterly', t('Trimestral'), planoEscolhido?.preco_trimestral],
                                ['semiannual', t('Semestral'), planoEscolhido?.preco_semestral],
                                ['yearly', t('Anual'), planoEscolhido?.preco_anual],
                            ].map(([valor, rotulo, preco]) => (
                                <button key={String(valor)} type="button" role="radio" aria-checked={ciclo === valor} onClick={() => porCiclo(String(valor))}
                                    className={cls('border-2 p-3 text-left', RAIO, TRANSICAO, FOCO, ciclo === valor ? 'border-indigo-600 bg-indigo-50' : 'border-slate-200 hover:border-indigo-300')}>
                                    <span className="block text-sm font-bold text-slate-900">{String(rotulo)}</span>
                                    {preco !== undefined && <span className="block text-xs text-slate-500">{kz(Number(preco))} Kz</span>}
                                </button>
                            ))}
                        </div>
                    </fieldset>

                    <fieldset className="space-y-3 rounded-xl border-2 border-slate-200 bg-slate-50 p-4">
                        <legend className="px-1 text-sm font-bold text-slate-800">{t('Condições do acordo')}</legend>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Campo etiqueta={t('Período em dias')} erro={erros.dias} ajuda={t('Se preencher, ganha ao ciclo: o período acaba daqui a N dias.')}>
                                <input type="number" min="1" max="3660" className={entrada} value={dias} onChange={(e) => porDias(e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('Preço por utilizador (Kz)')} erro={erros.preco_por_utilizador} ajuda={t('Se preencher, o valor passa a ser N utilizadores × este preço.')}>
                                <input type="number" min="0" step="0.01" className={entrada} value={preco} onChange={(e) => porPreco(e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('Utilizadores a cobrar')} erro={erros.utilizadores} ajuda={t('Vazio: os utilizadores do plano escolhido.')}>
                                <input type="number" min="1" className={entrada} value={utilizadores} onChange={(e) => porUtilizadores(e.target.value)} />
                            </Campo>
                        </div>
                        {ciclo === 'yearly' && dias === '' && (
                            <label className="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" className="mt-0.5 h-4 w-4 rounded border-slate-300 text-purple-600" checked={comOferta} onChange={(e) => porComOferta(e.target.checked)} />
                                <span><b>{t('Oferecer os 2 meses do anual')}</b> — {t('14 meses pelo preço de 12.')}</span>
                            </label>
                        )}
                    </fieldset>

                    <label className={cls('flex cursor-pointer items-start gap-3 border-2 p-3', RAIO, TRANSICAO, pago ? 'border-emerald-300 bg-emerald-50' : 'border-amber-300 bg-amber-50')}>
                        <input type="checkbox" className="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600" checked={pago} onChange={(e) => porPago(e.target.checked)} />
                        <span>
                            <span className="block font-bold text-slate-800">{t('Marcar como pago')}</span>
                            <span className="block text-xs text-slate-600">
                                {pago
                                    ? t('A subscrição fica activa, os módulos do plano ligam-se e sai uma factura paga.')
                                    : t('Fica à espera do pagamento. Se a empresa tem uma subscrição em vigor, o acesso actual mantém-se.')}
                            </span>
                        </span>
                    </label>

                    {pago && (
                        <div className="entra grid gap-4 md:grid-cols-2">
                            <Campo etiqueta={t('Método de pagamento')} erro={erros.metodo}>
                                <select className={entrada} value={metodo} onChange={(e) => porMetodo(e.target.value)}>
                                    {opcoes.metodos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta={t('Referência')} erro={erros.referencia}>
                                <input className={entrada} value={referencia} placeholder="TRF-2026-001" onChange={(e) => porReferencia(e.target.value)} />
                            </Campo>
                        </div>
                    )}

                    {planoEscolhido && r && (
                        <div className={cls('entra flex flex-wrap items-center justify-between gap-3 bg-gradient-to-br from-purple-600 to-indigo-700 p-5 text-white', RAIO, resumo.isFetching && 'opacity-80')}>
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-purple-200">{t('Total a pagar')}</p>
                                <p className="text-sm text-purple-100">{planoEscolhido.nome} · {r.ciclo_nome}</p>
                                <p className="mt-1 text-xs text-purple-200">{r.base} · {t('Período: hoje → :fim (:n dias)', { fim: r.fim, n: r.dias })}</p>
                            </div>
                            <p className="text-3xl font-bold tabular-nums">{kz(r.valor)} <span className="text-lg">Kz</span></p>
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}

/* ─── As facturas ─────────────────────────────────────────────────────── */

function Facturas({ opcoes, aoFazer }: { opcoes: FacturacaoDaPlataforma['opcoes']; aoFazer: (m: string) => void }) {
    const fila = useQueryClient();
    const [f, porF] = useState<{ procura?: string; estado?: string; pagina: number }>({ pagina: 1 });
    const [aEditar, porAEditar] = useState<number | 'nova' | null>(null);
    const [aApagar, porAApagar] = useState<FacturaDaPlataforma | null>(null);

    const lista = useQuery({
        queryKey: ['plataforma', 'facturacao', 'facturas', f],
        queryFn: () => plataforma.facturacao.facturas(f),
        placeholderData: keepPreviousData,
    });

    const refrescar = (m: string) => {
        aoFazer(m);
        void fila.invalidateQueries({ queryKey: ['plataforma', 'facturacao', 'facturas'] });
    };

    const pagar = useMutation({
        mutationFn: (id: number) => plataforma.facturacao.pagarFactura(id),
        onSuccess: (r) => refrescar(r.message),
    });

    const apagar = useMutation({
        mutationFn: (id: number) => plataforma.facturacao.apagarFactura(id),
        onSuccess: (r) => { porAApagar(null); refrescar(r.message); },
    });

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end gap-3">
                <label className="block min-w-[14rem] flex-1">
                    <Rotulo>{t('Procurar')}</Rotulo>
                    <input type="search" className={entrada} placeholder={t('Número ou empresa…')} value={f.procura ?? ''} onChange={(e) => porF({ ...f, procura: e.target.value || undefined, pagina: 1 })} />
                </label>
                <label className="block w-48">
                    <Rotulo>{t('Estado')}</Rotulo>
                    <select className={entrada} value={f.estado ?? ''} onChange={(e) => porF({ ...f, estado: e.target.value || undefined, pagina: 1 })}>
                        <option value="">{t('Todos')}</option>
                        {ESTADOS_DA_FACTURA().map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                    </select>
                </label>
                <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porAEditar('nova')}>{t('Nova factura')}</Botao>
            </div>

            <AvisoDeErro erro={pagar.error ?? apagar.error} />

            {lista.isPending ? <Carregando linhas={5} /> : !lista.data || lista.data.facturas.length === 0 ? (
                <SemNada icone="fa-file-invoice" frase={t('Nenhuma factura com estes filtros.')} />
            ) : (
                <div className="space-y-2">
                    {lista.data.facturas.map((x, i) => {
                        const estado = ESTADOS_DA_FACTURA().find((e) => e.valor === x.estado);

                        return (
                            <article key={x.id} className={cls(CARTAO, 'entra flex flex-wrap items-center justify-between gap-3 p-4', TRANSICAO, 'hover:shadow-md')} style={cascata(i)}>
                                <div className="flex min-w-0 items-center gap-3">
                                    <span className={cls('grid h-10 w-10 shrink-0 place-items-center rounded-lg text-white bg-gradient-to-br', estado?.fundo ?? 'from-slate-400 to-slate-500')}>
                                        <i className="fas fa-file-invoice" aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0">
                                        <h4 className="font-bold text-slate-900">{x.numero}</h4>
                                        <p className="truncate text-xs text-slate-500">
                                            {x.empresa ?? t('— empresa apagada —')} · {x.dia ?? '—'}
                                            {x.descricao && <> · {x.descricao}</>}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-lg font-bold tabular-nums text-slate-900">{kz(x.total)} Kz</span>
                                    <Etiqueta cor={estado?.cor ?? 'neutra'} ponto>{estado?.rotulo ?? x.estado}</Etiqueta>
                                    <Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-pen" onClick={() => porAEditar(x.id)}>{t('Editar')}</Botao>
                                    {x.estado !== 'paid' && (
                                        <Botao cor="bom" tom="suave" altura="pequeno" icone="fa-check" aTrabalhar={pagar.isPending && pagar.variables === x.id} onClick={() => pagar.mutate(x.id)}>{t('Marcar paga')}</Botao>
                                    )}
                                    <Botao cor="perigo" tom="suave" altura="pequeno" icone="fa-trash" onClick={() => porAApagar(x)}><span className="sr-only">{t('Apagar')}</span></Botao>
                                </div>
                            </article>
                        );
                    })}
                    <Paginas pagina={lista.data.paginacao.pagina} ultima={lista.data.paginacao.ultima} aMudar={(p) => porF({ ...f, pagina: p })} />
                </div>
            )}

            {aEditar !== null && (
                <FormularioDaFactura
                    id={aEditar === 'nova' ? null : aEditar}
                    empresas={opcoes.empresas}
                    aoFechar={() => porAEditar(null)}
                    aoGuardar={(m) => { porAEditar(null); refrescar(m); }}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar a factura?')}
                subtitulo={aApagar?.numero}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porAApagar(null)}>{t('Deixar estar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar.id)}>{t('Apagar')}</Botao>
                    </div>
                }
            >
                <p className="text-sm text-slate-700">{t('A factura sai da lista e deixa de contar na receita.')}</p>
            </Modal>
        </div>
    );
}

function FormularioDaFactura({ id, empresas, aoFechar, aoGuardar }: {
    id: number | null;
    empresas: Array<{ valor: string; rotulo: string }>;
    aoFechar: () => void;
    aoGuardar: (m: string) => void;
}) {
    const [f, porF] = useState<FichaDaFactura | null>(null);

    const ficha = useQuery({
        queryKey: ['plataforma', 'facturacao', 'factura', id],
        queryFn: () => (id ? plataforma.facturacao.fichaDaFactura(id) : plataforma.facturacao.novaFactura()),
        gcTime: 0,
    });

    useEffect(() => {
        if (ficha.data) porF(ficha.data.ficha);
    }, [ficha.data]);

    const guardar = useMutation({
        mutationFn: () => plataforma.facturacao.guardarFactura(id, { ...f }),
        onSuccess: (r) => aoGuardar(r.message),
    });

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};

    // O TOTAL MOSTRA-SE, NÃO SE ESCREVE: o servidor soma. E um campo apagado
    // conta zero — o `"" + ""` rebentava o modal antigo a meio de escrever.
    const total = f ? Math.round(((Number(f.subtotal) || 0) + (Number(f.tax) || 0)) * 100) / 100 : 0;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={id ? t('Editar factura') : t('Nova factura')}
            subtitulo={f?.invoice_number}
            icone="fa-file-invoice"
            cor="primaria"
            largura="lg"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" disabled={!f} aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao>
                </div>
            }
        >
            {!f ? <Carregando linhas={6} /> : (
                <div className="space-y-4">
                    <AvisoDeErro erro={guardar.error} />
                    <div className="grid gap-4 md:grid-cols-3">
                        <Campo etiqueta={t('Empresa')} obrigatorio erro={erros.tenant_id} className="md:col-span-2">
                            <select className={entrada} value={f.tenant_id ?? ''} onChange={(e) => porF({ ...f, tenant_id: e.target.value ? Number(e.target.value) : null })}>
                                <option value="">{t('Escolha uma empresa…')}</option>
                                {empresas.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Estado')} obrigatorio erro={erros.status}>
                            <select className={entrada} value={f.status} onChange={(e) => porF({ ...f, status: e.target.value })}>
                                {ESTADOS_DA_FACTURA().map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Número')} obrigatorio erro={erros.invoice_number}>
                            <input className={cls(entrada, 'font-mono')} value={f.invoice_number} onChange={(e) => porF({ ...f, invoice_number: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Data')} obrigatorio erro={erros.invoice_date}>
                            <input type="date" className={entrada} value={f.invoice_date} onChange={(e) => porF({ ...f, invoice_date: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Vencimento')} obrigatorio erro={erros.due_date}>
                            <input type="date" className={entrada} value={f.due_date} onChange={(e) => porF({ ...f, due_date: e.target.value })} />
                        </Campo>
                    </div>
                    <Campo etiqueta={t('Descrição')} obrigatorio erro={erros.description}>
                        <textarea rows={2} className={cls(entrada, 'h-auto py-2')} value={f.description} onChange={(e) => porF({ ...f, description: e.target.value })} />
                    </Campo>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Campo etiqueta={t('Subtotal (Kz)')} obrigatorio erro={erros.subtotal}>
                            <input type="number" min="0" step="0.01" className={entrada} value={f.subtotal} onChange={(e) => porF({ ...f, subtotal: e.target.value === '' ? 0 : Number(e.target.value) })} />
                        </Campo>
                        <Campo etiqueta={t('Imposto (Kz)')} obrigatorio erro={erros.tax}>
                            <input type="number" min="0" step="0.01" className={entrada} value={f.tax} onChange={(e) => porF({ ...f, tax: e.target.value === '' ? 0 : Number(e.target.value) })} />
                        </Campo>
                        <div>
                            <Rotulo>{t('Total (Kz)')}</Rotulo>
                            <p className="flex h-10 items-center rounded-xl bg-blue-50 px-3 font-bold tabular-nums text-blue-900">{kz(total)}</p>
                        </div>
                    </div>
                </div>
            )}
        </Modal>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

function Dado({ rotulo, valor }: { rotulo: string; valor: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-slate-500">{rotulo}</dt>
            <dd className="font-semibold text-slate-900">{valor}</dd>
        </div>
    );
}

function Limite({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div className="rounded-lg bg-slate-50 p-3 text-center">
            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{rotulo}</p>
            <p className="text-lg font-bold text-slate-800">{valor}</p>
        </div>
    );
}

/** Negativo é «vencida há N dias», e não um número em verde. */
function DiasRestantes({ dias }: { dias: number | null }) {
    if (dias === null) return <span className="text-slate-400">—</span>;
    if (dias < 0) return <span className="text-red-600">{t('Vencida há :n dia(s)', { n: Math.abs(dias) })}</span>;
    if (dias === 0) return <span className="text-orange-600">{t('Termina hoje')}</span>;

    return <span className={dias <= 7 ? 'text-orange-600' : 'text-emerald-600'}>{t(':n dia(s)', { n: dias })}</span>;
}

function Paginas({ pagina, ultima, aMudar }: { pagina: number; ultima: number; aMudar: (p: number) => void }) {
    if (ultima <= 1) return null;

    return (
        <nav className="flex items-center justify-between pt-2" aria-label={t('Páginas')}>
            <Botao icone="fa-chevron-left" altura="pequeno" disabled={pagina <= 1} onClick={() => aMudar(pagina - 1)}>{t('Anterior')}</Botao>
            <span className="text-sm tabular-nums text-slate-600">{t('Página :pagina de :paginas', { pagina, paginas: ultima })}</span>
            <Botao icone="fa-chevron-right" altura="pequeno" disabled={pagina >= ultima} onClick={() => aMudar(pagina + 1)}>{t('Seguinte')}</Botao>
        </nav>
    );
}

const ESTADOS_DA_SUBSCRICAO = (): Array<{ valor: string; rotulo: string; cor: Cor }> => [
    { valor: 'active', rotulo: t('Activa'), cor: 'bom' },
    { valor: 'trial', rotulo: t('Em ensaio'), cor: 'primaria' },
    { valor: 'pending', rotulo: t('Pendente'), cor: 'aviso' },
    { valor: 'suspended', rotulo: t('Suspensa'), cor: 'perigo' },
    { valor: 'cancelled', rotulo: t('Cancelada'), cor: 'neutra' },
    { valor: 'expired', rotulo: t('Expirada'), cor: 'neutra' },
];

const ESTADOS_DA_FACTURA = (): Array<{ valor: string; rotulo: string; cor: Cor; fundo: string }> => [
    { valor: 'pending', rotulo: t('Pendente'), cor: 'aviso', fundo: 'from-amber-400 to-amber-600' },
    { valor: 'paid', rotulo: t('Paga'), cor: 'bom', fundo: 'from-emerald-400 to-emerald-600' },
    { valor: 'overdue', rotulo: t('Vencida'), cor: 'perigo', fundo: 'from-red-400 to-red-600' },
    { valor: 'cancelled', rotulo: t('Cancelada'), cor: 'neutra', fundo: 'from-slate-400 to-slate-500' },
];
