import { useQuery } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';

import { revenda, type PagamentoDoRevendedor } from '@/api/revenda';
import { t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, data } from '@/ui/tokens';

import { Cabecalho, Copiar, GRADIENTE_DO_PORTAL, Numero, dataOuTraco, kwanzas } from './comum';
import { PagarPeloCliente, type AlvoDoPagamento } from './PagarPeloCliente';

/**
 * OS PAGAMENTOS DO REVENDEDOR (16/09/2026) — pagar pelo cliente num sítio só.
 *
 * O que as empresas dele têm por pagar (pedidos de plano sem comprovativo e
 * facturas de renovação), o que ele já enviou e espera a confirmação do super
 * admin, e o que foi confirmado ou recusado.
 */
const ESTADO: Record<PagamentoDoRevendedor['estado'], { cor: 'aviso' | 'perigo' | 'primaria' | 'bom' | 'neutra'; icone: string; rotulo: string }> = {
    por_pagar: { cor: 'aviso', icone: 'fa-hourglass-half', rotulo: 'Por pagar' },
    vencida: { cor: 'perigo', icone: 'fa-triangle-exclamation', rotulo: 'Vencida' },
    por_confirmar: { cor: 'primaria', icone: 'fa-clock', rotulo: 'À espera de confirmação' },
    confirmado: { cor: 'bom', icone: 'fa-circle-check', rotulo: 'Confirmado' },
    recusado: { cor: 'perigo', icone: 'fa-ban', rotulo: 'Recusado' },
};

const alvoDe = (p: PagamentoDoRevendedor): AlvoDoPagamento => ({
    tipo: p.tipo, id: p.id, empresaId: p.empresa_id, empresa: p.empresa, descricao: p.descricao, valor: p.valor, referencia: p.referencia,
});

export default function Pagamentos() {
    const q = useQuery({ queryKey: ['revenda', 'pagamentos'], queryFn: revenda.pagamentos });
    const [aPagar, porAPagar] = useState<AlvoDoPagamento | null>(null);

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) return <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-800">{t('Não foi possível carregar os pagamentos.')}</p>;

    const d = q.data;

    return (
        <div className="space-y-6">
            <Cabecalho titulo={t('Pagamentos')} subtitulo={t('Pague pelos seus clientes: transfira, envie o comprovativo e nós confirmamos')} icone="fa-file-invoice-dollar" gradiente={GRADIENTE_DO_PORTAL} />

            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Numero i={0} cor={d.totais.por_pagar_n > 0 ? 'ambar' : 'verde'} rotulo={t('Por pagar')} valor={<span className="text-2xl">{kwanzas(d.totais.por_pagar)}</span>}
                    nota={d.totais.por_pagar_n > 0 ? t(':n pagamento(s) por fazer', { n: d.totais.por_pagar_n }) : t('Nada por pagar')} icone="fa-hand-holding-dollar" />
                <Numero i={1} cor="indigo" rotulo={t('À espera de confirmação')} valor={<span className="text-2xl">{kwanzas(d.totais.por_confirmar)}</span>}
                    nota={t(':n enviado(s)', { n: d.totais.por_confirmar_n })} icone="fa-clock" />
                <div style={cascata(2)} className={cls('entra border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-5 shadow-lg', 'rounded-2xl')}>
                    <p className="mb-2 text-sm font-semibold text-amber-800"><i className="fas fa-building-columns mr-1.5" aria-hidden="true" />{t('Conta para transferir')}</p>
                    <p className="text-sm text-gray-700"><b>{d.conta.banco}</b> · {d.conta.titular}</p>
                    <p className="mt-1 break-all font-mono text-sm font-bold text-gray-900">{d.conta.iban}</p>
                    <div className="mt-2"><Copiar texto={d.conta.iban} rotulo={t('Copiar IBAN')} className="!bg-white !text-amber-800" /></div>
                </div>
            </div>

            <Bloco icone="fa-hand-holding-dollar" titulo={t('Por pagar')} n={d.por_pagar.length}>
                {d.por_pagar.length === 0
                    ? <SemNada icone="fa-circle-check" titulo={t('Tudo pago')} frase={t('Nenhuma empresa sua tem pagamentos por fazer.')} />
                    : <Lista linhas={d.por_pagar} accao={(p) => (
                        <button type="button" onClick={() => porAPagar(alvoDe(p))}
                            className={cls('inline-flex items-center gap-1.5 bg-gradient-to-r from-emerald-600 to-teal-600 px-3.5 py-2 text-sm font-bold text-white shadow hover:-translate-y-0.5 hover:shadow-md', RAIO, TRANSICAO, FOCO)}>
                            <i className="fas fa-money-bill-transfer" aria-hidden="true" />{t('Pagar')}
                        </button>
                    )} />}
            </Bloco>

            <Bloco icone="fa-clock" titulo={t('À espera de confirmação')} n={d.por_confirmar.length}>
                {d.por_confirmar.length === 0
                    ? <SemNada icone="fa-inbox" frase={t('Nenhum pagamento à espera de confirmação.')} />
                    : <Lista linhas={d.por_confirmar} accao={(p) => (
                        <button type="button" onClick={() => porAPagar(alvoDe(p))}
                            className={cls('inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100', RAIO, TRANSICAO, FOCO)}>
                            <i className="fas fa-rotate" aria-hidden="true" />{t('Trocar o comprovativo')}
                        </button>
                    )} />}
            </Bloco>

            <Bloco icone="fa-clock-rotate-left" titulo={t('Histórico')} n={d.historico.length}>
                {d.historico.length === 0
                    ? <SemNada icone="fa-receipt" frase={t('Os pagamentos que enviar aparecem aqui depois de confirmados ou recusados.')} />
                    : <Lista linhas={d.historico} />}
            </Bloco>

            {aPagar && <PagarPeloCliente alvo={aPagar} conta={d.conta} aoFechar={() => porAPagar(null)} />}
        </div>
    );
}

function Bloco({ icone, titulo, n, children }: { icone: string; titulo: string; n: number; children: ReactNode }) {
    return (
        <section className={cls(CARTAO, 'entra overflow-hidden')}>
            <h2 className="flex items-center gap-2 border-b border-slate-100 px-5 py-4 text-lg font-bold text-gray-900">
                <i className={cls('fas text-violet-500', icone)} aria-hidden="true" />{titulo}
                {n > 0 && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{n}</span>}
            </h2>
            {children}
        </section>
    );
}

function Estado({ p }: { p: PagamentoDoRevendedor }) {
    const e = ESTADO[p.estado];
    return (
        <>
            <Etiqueta cor={e.cor} icone={e.icone}>{t(e.rotulo)}</Etiqueta>
            {p.motivo && <span className="mt-1 block max-w-xs text-xs text-red-700"><i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{p.motivo}</span>}
            {p.comprovativo && <a href={p.comprovativo} target="_blank" rel="noreferrer" className="mt-1 block text-xs font-semibold text-violet-700 hover:underline"><i className="fas fa-paperclip mr-1" aria-hidden="true" />{t('Ver comprovativo')}</a>}
            {p.estado === 'confirmado' || p.estado === 'recusado' ? <span className="mt-1 block text-xs text-gray-500">{data(p.data)}</span> : null}
        </>
    );
}

function OQue({ p }: { p: PagamentoDoRevendedor }) {
    return (
        <>
            <i className={cls('fas mr-1.5 text-slate-400', p.tipo === 'factura' ? 'fa-file-invoice' : 'fa-layer-group')} aria-hidden="true" />
            {p.tipo === 'factura' ? t('Factura') : t('Pedido de plano')} · {p.descricao}
            {p.referencia && <span className="block text-xs text-gray-500">{t('Ref.')} {p.referencia}</span>}
        </>
    );
}

function Lista({ linhas, accao }: { linhas: PagamentoDoRevendedor[]; accao?: (p: PagamentoDoRevendedor) => ReactNode }) {
    return (
        <>
            {/* No telemóvel, um cartão por pagamento: a tabela ficava espremida. */}
            <ul className="divide-y divide-slate-100 md:hidden">
                {linhas.map((p, i) => (
                    <li key={`${p.tipo}-${p.id}`} style={cascata(i)} className="entra space-y-2 px-4 py-4">
                        <div className="flex items-start justify-between gap-3">
                            <a href={`/revendedor/empresas/${p.empresa_id}`} className="font-semibold text-gray-900 hover:text-violet-700">{p.empresa ?? '—'}</a>
                            <span className="whitespace-nowrap text-right"><Valor p={p} /></span>
                        </div>
                        <p className="text-sm text-gray-700"><OQue p={p} /></p>
                        {p.vence && (
                            <p className={cls('text-xs', p.estado === 'vencida' ? 'font-semibold text-red-700' : 'text-gray-500')}>
                                <i className="fas fa-calendar-day mr-1" aria-hidden="true" />{t('Vence')} {dataOuTraco(p.vence)}
                            </p>
                        )}
                        <div className="flex flex-wrap items-end justify-between gap-2">
                            <div><Estado p={p} /></div>
                            {accao?.(p)}
                        </div>
                    </li>
                ))}
            </ul>
            <div className="hidden overflow-x-auto md:block">
            <table className="min-w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th className="px-4 py-3">{t('Empresa')}</th>
                        <th className="px-4 py-3">{t('O quê')}</th>
                        <th className="px-4 py-3">{t('Vence')}</th>
                        <th className="px-4 py-3 text-right">{t('Valor')}</th>
                        <th className="px-4 py-3">{t('Estado')}</th>
                        <th className="px-4 py-3" aria-label={t('Acções')} />
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {linhas.map((p, i) => (
                        <tr key={`${p.tipo}-${p.id}`} style={cascata(i)} className="entra hover:bg-violet-50/40">
                            <td className="px-4 py-3">
                                <a href={`/revendedor/empresas/${p.empresa_id}`} className="font-semibold text-gray-900 hover:text-violet-700">{p.empresa ?? '—'}</a>
                            </td>
                            <td className="px-4 py-3 text-gray-700"><OQue p={p} /></td>
                            <td className={cls('whitespace-nowrap px-4 py-3', p.estado === 'vencida' ? 'font-semibold text-red-700' : 'text-gray-700')}>{p.vence ? dataOuTraco(p.vence) : '—'}</td>
                            <td className="whitespace-nowrap px-4 py-3 text-right"><Valor p={p} /></td>
                            <td className="px-4 py-3"><Estado p={p} /></td>
                            <td className="whitespace-nowrap px-4 py-3 text-right">{accao?.(p)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            </div>
        </>
    );
}

/**
 * O QUE TRANSFERE, e de onde vem.
 *
 * Numa renovação, o valor já é o preço de revendedor: a factura (documento
 * fiscal) fica ao preço de tabela, e a diferença é a comissão dele, ganha à
 * cabeça. Mostra-se a tabela riscada por cima para se perceber porque é que
 * o número não é o da factura.
 */
function Valor({ p }: { p: PagamentoDoRevendedor }) {
    const desconto = p.desconto ?? 0;

    return (
        <>
            {desconto > 0 && p.valor_tabela != null && (
                <span className="block text-xs tabular-nums text-gray-400 line-through">{kwanzas(p.valor_tabela)}</span>
            )}
            <span className={cls("block font-bold tabular-nums", desconto > 0 ? "text-emerald-700" : "text-gray-900")}>{kwanzas(p.valor)}</span>
        </>
    );
}
