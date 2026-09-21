import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { revenda, type FichaDaEmpresa, type OpcoesDoPortal } from '@/api/revenda';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, data } from '@/ui/tokens';

import { Cabecalho, Copiar, Dado, EstadoDaComissao, EstadoDaEmpresaEtiquetas, GRADIENTE_DO_PORTAL, dataOuTraco, kwanzas } from './comum';
import { PagarPeloCliente, type AlvoDoPagamento } from './PagarPeloCliente';
import { Campo, entrada } from './SejaRevendedor';

/**
 * A FICHA DE UMA EMPRESA DO REVENDEDOR (RV-08 e RV-10).
 *
 * A conta na plataforma e o que o revendedor pode fazer por ela: mudar ou
 * renovar o plano e mandar o comprovativo de um pedido por pagar. O super
 * admin aprova, como sempre.
 */
const COR_DO_PEDIDO = { pending: 'aviso', approved: 'bom', rejected: 'perigo' } as const;
const COR_DA_FACTURA: Record<string, 'aviso' | 'bom' | 'perigo' | 'neutra'> = { pending: 'aviso', paid: 'bom', overdue: 'perigo', cancelled: 'neutra' };

export default function Empresa({ id }: { id: number }) {
    const q = useQuery({ queryKey: ['revenda', 'empresa', id], queryFn: () => revenda.empresa(id) });
    const opcoes = useQuery({ queryKey: ['revenda', 'opcoes'], queryFn: revenda.opcoes, staleTime: 5 * 60_000 });
    const [aPedir, porAPedir] = useState(false);
    const [aPagar, porAPagar] = useState<AlvoDoPagamento | null>(null);

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) {
        return (
            <div className={CARTAO}>
                <SemNada icone="fa-building-circle-xmark" titulo={t('Empresa não encontrada')} frase={t('Esta empresa não existe ou não está ligada a si.')}
                    accao={<a href="/revendedor/empresas" className="font-semibold text-violet-700">{t('Voltar às empresas')}</a>} />
            </div>
        );
    }

    const f = q.data;
    const e = f.empresa;
    const pedidoPendente = f.pedidos.find((p) => p.estado === 'pending');
    const pagarPedido = (p: FichaDaEmpresa['pedidos'][number]) => porAPagar({
        tipo: 'pedido', id: p.id, empresaId: e.id, empresa: e.nome, descricao: [p.plano, p.ciclo].filter(Boolean).join(' · '), valor: p.valor, referencia: p.referencia,
    });
    const pagarFactura = (fa: FichaDaEmpresa['facturas'][number]) => porAPagar({
        tipo: 'factura', id: fa.id, empresaId: e.id, empresa: e.nome, descricao: fa.numero, valor: fa.total, referencia: fa.referencia,
    });

    return (
        <div className="space-y-6">
            <Cabecalho titulo={e.nome} subtitulo={[e.razao_social, e.nif].filter(Boolean).join(' · ') || t('Empresa')} icone="fa-building" gradiente={GRADIENTE_DO_PORTAL}>
                <div className="flex flex-wrap gap-2">
                    <a href="/revendedor/empresas" className={cls('inline-flex items-center gap-2 bg-white/15 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/25', RAIO, TRANSICAO, FOCO)}>
                        <i className="fas fa-arrow-left" aria-hidden="true" />{t('Empresas')}
                    </a>
                    {e.estado.chave !== 'suspensa' && (
                        <button type="button" onClick={() => porAPedir(true)} className={cls('group inline-flex items-center gap-2 bg-white px-4 py-2.5 text-sm font-bold text-violet-700 shadow hover:-translate-y-0.5 hover:shadow-lg', RAIO, TRANSICAO, FOCO)}>
                            <i className="fas fa-arrows-rotate transition-transform duration-500 group-hover:rotate-180" aria-hidden="true" />{t('Mudar ou renovar plano')}
                        </button>
                    )}
                </div>
            </Cabecalho>

            {pedidoPendente && (
                <div className={cls('entra flex flex-wrap items-center gap-3 border border-amber-200 bg-amber-50 p-4', RAIO)}>
                    <span className="grid h-11 w-11 place-items-center rounded-xl bg-amber-500 text-white shadow"><i className="fas fa-hourglass-half icon-float" aria-hidden="true" /></span>
                    <p className="min-w-0 flex-1 text-sm text-amber-900">
                        <b>{t('Pedido à espera de confirmação')}</b> — {pedidoPendente.plano} · {kwanzas(pedidoPendente.valor)} · {pedidoPendente.ciclo}.{' '}
                        {pedidoPendente.comprovativo ? t('O comprovativo já foi enviado; falta a confirmação.') : t('Falta o comprovativo da transferência.')}
                    </p>
                    <Botao cor="aviso" tom="solida" icone={pedidoPendente.comprovativo ? 'fa-rotate' : 'fa-money-bill-transfer'} onClick={() => pagarPedido(pedidoPendente)}>
                        {pedidoPendente.comprovativo ? t('Trocar o comprovativo') : t('Pagar pelo cliente')}
                    </Botao>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <Bloco i={0} icone="fa-layer-group" titulo={t('Subscrição')} className="lg:col-span-1">
                    <div className="mb-4"><EstadoDaEmpresaEtiquetas e={e.estado} /></div>
                    {f.subscricao ? (
                        <div className="grid grid-cols-2 gap-4">
                            <Dado rotulo={t('Plano')}>{f.subscricao.plano}</Dado>
                            <Dado rotulo={t('Ciclo')}>{f.subscricao.ciclo_rotulo}</Dado>
                            <Dado rotulo={t('Valor')}>{kwanzas(f.subscricao.valor)}</Dado>
                            <Dado rotulo={t('Até')}>{dataOuTraco(f.subscricao.fim)}</Dado>
                        </div>
                    ) : <p className="text-sm text-gray-600">{t('Esta empresa não tem nenhum plano activo.')}</p>}
                    <p className="mt-4 text-xs text-gray-500">{e.estado.detalhe}</p>
                </Bloco>

                <Bloco i={1} icone="fa-address-card" titulo={t('A conta')} className="lg:col-span-2">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <Dado rotulo={t('Dono')} icone="fa-user-tie">{e.dono ? <>{e.dono.nome}<span className="block text-xs font-normal text-gray-500">{e.dono.email}</span></> : null}</Dado>
                        <Dado rotulo={t('Contactos')} icone="fa-phone">{[e.telefone, e.email].filter(Boolean).join(' · ')}</Dado>
                        <Dado rotulo={t('Regime')} icone="fa-scale-balanced">{e.regime}</Dado>
                        <Dado rotulo={t('Morada')} icone="fa-location-dot">{e.morada}</Dado>
                        <Dado rotulo={t('Pessoas na conta')} icone="fa-users">{String(e.utilizadores)}</Dado>
                        <Dado rotulo={t('Último acesso')} icone="fa-clock-rotate-left">{e.ultima_entrada_ha ?? t('Nunca')}</Dado>
                        <Dado rotulo={t('Ligada a si')} icone="fa-link">{e.via_rotulo ? `${e.via_rotulo} · ${dataOuTraco(e.ligada_em)}` : null}</Dado>
                        <Dado rotulo={t('Criada em')} icone="fa-calendar-plus">{dataOuTraco(e.criada_em)}</Dado>
                    </div>
                </Bloco>
            </div>

            <Bloco i={2} icone="fa-receipt" titulo={t('Pedidos de plano')}>
                {f.pedidos.length === 0 ? <SemNada icone="fa-receipt" frase={t('Ainda não há pedidos.')} /> : (
                    <Tabela cabecalho={[t('Data'), t('Plano'), t('Valor'), t('Estado'), t('Comprovativo'), '']}>
                        {f.pedidos.map((p, i) => (
                            <tr key={p.id} style={cascata(i)} className="entra hover:bg-slate-50">
                                <td className="px-4 py-3 text-gray-700">{data(p.data)}</td>
                                <td className="px-4 py-3"><span className="font-semibold text-gray-900">{p.plano}</span><span className="block text-xs text-gray-500">{p.ciclo}</span></td>
                                <td className="px-4 py-3 tabular-nums font-semibold">{kwanzas(p.valor)}</td>
                                <td className="px-4 py-3">
                                    <Etiqueta cor={COR_DO_PEDIDO[p.estado]} ponto>{p.estado_rotulo}</Etiqueta>
                                    {p.motivo && <span className="mt-1 block text-xs text-red-700">{p.motivo}</span>}
                                </td>
                                <td className="px-4 py-3">
                                    {p.comprovativo
                                        ? <a href={p.comprovativo} target="_blank" rel="noreferrer" className="font-semibold text-violet-700 hover:underline"><i className="fas fa-file-arrow-down mr-1" aria-hidden="true" />{t('Ver')}</a>
                                        : <span className="text-gray-400">—</span>}
                                    {p.referencia && <span className="block text-xs text-gray-500">{p.referencia}</span>}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {p.estado === 'pending' && (
                                        <button type="button" onClick={() => pagarPedido(p)} className={cls('inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100', RAIO, TRANSICAO, FOCO)}>
                                            <i className="fas fa-money-bill-transfer" aria-hidden="true" />{p.comprovativo ? t('Trocar') : t('Pagar')}
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Tabela>
                )}
            </Bloco>

            <div className="grid gap-6 xl:grid-cols-2">
                <Bloco i={3} icone="fa-file-invoice-dollar" titulo={t('Facturas da subscrição')}>
                    {f.facturas.length === 0 ? <SemNada icone="fa-file-invoice" frase={t('Sem facturas da plataforma.')} /> : (
                        <Tabela cabecalho={[t('Número'), t('Vencimento'), t('Total'), t('Estado'), '']}>
                            {f.facturas.map((fa, i) => (
                                <tr key={fa.id} style={cascata(i)} className="entra hover:bg-slate-50">
                                    <td className="px-4 py-3"><span className="font-mono font-semibold">{fa.numero}</span><span className="block text-xs text-gray-500">{fa.descricao}</span></td>
                                    <td className="whitespace-nowrap px-4 py-3 text-gray-700">{dataOuTraco(fa.vencimento)}</td>
                                    <td className="whitespace-nowrap px-4 py-3 tabular-nums font-semibold">{kwanzas(fa.total)}</td>
                                    <td className="px-4 py-3">
                                        {fa.pagamento_enviado
                                            ? <Etiqueta cor="primaria" icone="fa-clock">{t('À espera de confirmação')}</Etiqueta>
                                            : <Etiqueta cor={COR_DA_FACTURA[fa.estado] ?? 'neutra'} ponto>{fa.estado_rotulo}</Etiqueta>}
                                        {fa.motivo_recusa && !fa.pagamento_enviado && <span className="mt-1 block text-xs text-red-700"><i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{t('Pagamento recusado: :m', { m: fa.motivo_recusa })}</span>}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        {fa.pode_pagar && (
                                            <button type="button" onClick={() => pagarFactura(fa)}
                                                className={cls('inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO,
                                                    fa.pagamento_enviado ? 'text-violet-700 hover:bg-violet-100' : 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700')}>
                                                <i className={cls('fas', fa.pagamento_enviado ? 'fa-rotate' : 'fa-money-bill-transfer')} aria-hidden="true" />
                                                {fa.pagamento_enviado ? t('Trocar') : t('Pagar')}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </Tabela>
                    )}
                </Bloco>

                <Bloco i={4} icone="fa-coins" titulo={t('As suas comissões desta empresa')}>
                    {f.comissoes.length === 0 ? <SemNada icone="fa-coins" frase={t('Ainda sem comissões desta empresa.')} /> : (
                        <Tabela cabecalho={[t('Data'), t('Origem'), t('Comissão'), t('Estado')]}>
                            {f.comissoes.map((c, i) => (
                                <tr key={c.id} style={cascata(i)} className="entra hover:bg-slate-50">
                                    <td className="px-4 py-3 text-gray-700">{data(c.criada_em)}</td>
                                    <td className="px-4 py-3"><span className="text-gray-900">{c.origem_rotulo}</span><span className="block text-xs text-gray-500">{c.regra} × {kwanzas(c.base)}</span></td>
                                    <td className="px-4 py-3 tabular-nums font-bold">{kwanzas(c.valor)}</td>
                                    <td className="px-4 py-3"><EstadoDaComissao c={c} /></td>
                                </tr>
                            ))}
                        </Tabela>
                    )}
                </Bloco>
            </div>

            {aPedir && opcoes.data && <PedirPlano id={id} ficha={f} opcoes={opcoes.data} aoFechar={() => porAPedir(false)} />}
            {aPagar && <PagarPeloCliente alvo={aPagar} conta={opcoes.data?.conta} aoFechar={() => porAPagar(null)} />}
        </div>
    );
}

function Bloco({ i, icone, titulo, className, children }: { i: number; icone: string; titulo: string; className?: string; children: ReactNode }) {
    return (
        <section style={cascata(i)} className={cls(CARTAO, 'entra p-6', className)}>
            <h2 className="mb-4 flex items-center gap-2 text-lg font-bold text-gray-900"><i className={cls('fas text-violet-500', icone)} aria-hidden="true" />{titulo}</h2>
            {children}
        </section>
    );
}

function Tabela({ cabecalho, children }: { cabecalho: string[]; children: ReactNode }) {
    return (
        <div className="-mx-6 overflow-x-auto">
            <table className="min-w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>{cabecalho.map((c, i) => <th key={i} className="px-4 py-2.5">{c}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-slate-100">{children}</tbody>
            </table>
        </div>
    );
}

function ContaParaTransferir({ conta }: { conta?: OpcoesDoPortal['conta'] }) {
    if (!conta) return null;

    return (
        <div className={cls('grid gap-2 border border-amber-200 bg-amber-50 p-3 text-sm sm:grid-cols-2', RAIO)}>
            <p><span className="block text-xs text-amber-700">{t('Banco')} · {t('Titular')}</span><b>{conta.banco}</b> · {conta.titular}</p>
            <p className="min-w-0"><span className="block text-xs text-amber-700">IBAN</span><b className="break-all font-mono">{conta.iban}</b> <Copiar texto={conta.iban} className="ml-1 !px-2 !py-1 !text-xs" /></p>
        </div>
    );
}

/** MUDAR OU RENOVAR O PLANO: o pedido que a empresa faria, pelo revendedor. */
function PedirPlano({ id, ficha, opcoes, aoFechar }: { id: number; ficha: FichaDaEmpresa; opcoes: OpcoesDoPortal; aoFechar: () => void }) {
    const cache = useQueryClient();
    const [plano, porPlano] = useState<number>(ficha.subscricao?.plano_id ?? opcoes.planos[0]?.id ?? 0);
    const [ciclo, porCiclo] = useState<string>(ficha.subscricao?.ciclo ?? 'monthly');
    const [referencia, porReferencia] = useState('');
    const [ficheiro, porFicheiro] = useState<File | null>(null);

    const enviar = useMutation({
        mutationFn: () => revenda.pedirPlano(id, { plan_id: plano, ciclo, referencia }, ficheiro),
        onSuccess: () => { void cache.invalidateQueries({ queryKey: ['revenda'] }); aoFechar(); },
    });
    const erros = enviar.error instanceof ErroDaApi ? enviar.error.erros : {};
    const escolhido = opcoes.planos.find((p) => p.id === plano);
    const tabela = escolhido?.precos[ciclo as keyof typeof escolhido.precos] ?? 0;

    /*
     * O QUE O REVENDEDOR TRANSFERE: o preço de revendedor DESTA empresa, vindo
     * do servidor. Sem ele (servidor antigo), fica a tabela — nunca se inventa
     * um desconto no browser.
     */
    const precoDe = (planoId: number) => ficha.precos_revendedor?.[planoId]?.[ciclo];
    const valor = (escolhido && precoDe(escolhido.id)) ?? tabela;
    const desconto = Math.max(0, Math.round((tabela - valor) * 100) / 100);

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Mudar ou renovar plano')} subtitulo={ficha.empresa.nome} icone="fa-arrows-rotate" cor="primaria" largura="lg"
            rodape={<>
                <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                <Botao cor="primaria" tom="solida" icone="fa-paper-plane" aTrabalhar={enviar.isPending} onClick={() => enviar.mutate()}>{t('Enviar pedido')}</Botao>
            </>}>
            <div className="space-y-5">
                {enviar.error && !Object.keys(erros).length && <p role="alert" className="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800">{(enviar.error as Error).message}</p>}
                <div className="grid gap-3 sm:grid-cols-2">
                    {opcoes.planos.map((p) => (
                        <button key={p.id} type="button" onClick={() => porPlano(p.id)} aria-pressed={plano === p.id}
                            className={cls('flex items-center justify-between gap-2 border-2 p-3 text-left', RAIO, TRANSICAO, FOCO,
                                plano === p.id ? 'border-violet-500 bg-violet-50 shadow' : 'border-gray-200 hover:border-violet-300')}>
                            <span>
                                <span className="block font-bold text-gray-900">{p.nome}{ficha.subscricao?.plano_id === p.id && <span className="ml-2 text-xs font-semibold text-emerald-700">{t('actual')}</span>}</span>
                                <span className="block text-xs text-gray-500">{t(':n utilizadores', { n: p.utilizadores })}</span>
                            </span>
                            <span className="text-right tabular-nums">
                                {(() => {
                                    const deTabela = p.precos[ciclo as keyof typeof p.precos];
                                    const doRevendedor = precoDe(p.id) ?? deTabela;

                                    return doRevendedor < deTabela ? (
                                        <>
                                            <span className="block text-xs text-gray-400 line-through">{kwanzas(deTabela)}</span>
                                            <span className="block text-sm font-bold text-emerald-700">{kwanzas(doRevendedor)}</span>
                                        </>
                                    ) : (
                                        <span className="text-sm font-bold text-violet-700">{kwanzas(deTabela)}</span>
                                    );
                                })()}
                            </span>
                        </button>
                    ))}
                </div>
                {erros.plan_id && <p className="text-xs text-red-600">{erros.plan_id[0]}</p>}

                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo id="pp-ciclo" rotulo={t('Ciclo')} erro={erros.ciclo?.[0]} icone="fa-calendar">
                        <select id="pp-ciclo" value={ciclo} onChange={(e) => porCiclo(e.target.value)} className={entrada(erros.ciclo?.[0])}>
                            {opcoes.ciclos.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                        </select>
                    </Campo>
                    <div className={cls('flex flex-col justify-center bg-gradient-to-r from-violet-600 to-emerald-600 px-4 py-2 text-white', RAIO)}>
                        <span className="text-xs text-white/80">{t('A pagar')}</span>
                        <span className="text-2xl font-black tabular-nums">{kwanzas(valor)}</span>
                        {/* O que ganhou já aqui: o desconto É a comissão dele. */}
                        {desconto > 0 && (
                            <span className="text-[11px] text-white/90">
                                {t('Preço de tabela :tabela · a sua comissão :desconto já descontada', { tabela: kwanzas(tabela), desconto: kwanzas(desconto) })}
                            </span>
                        )}
                    </div>
                </div>

                <ContaParaTransferir conta={opcoes.conta} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo id="pp-ref" rotulo={t('Referência da transferência')} erro={erros.referencia?.[0]} icone="fa-hashtag">
                        <input id="pp-ref" value={referencia} onChange={(e) => porReferencia(e.target.value)} className={entrada(erros.referencia?.[0])} />
                    </Campo>
                    <Campo id="pp-comp" rotulo={t('Comprovativo')} erro={erros.comprovativo?.[0]} icone="fa-paperclip">
                        <input id="pp-comp" type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => porFicheiro(e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-100 file:px-3 file:py-2 file:font-semibold file:text-violet-700 hover:file:bg-violet-200" />
                    </Campo>
                </div>
                <p className="text-xs text-gray-500">{t('Sem comprovativo, o pedido fica à espera: pode anexá-lo depois. O plano muda quando confirmarmos o pagamento.')}</p>
            </div>
        </Modal>
    );
}
