import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState, type ReactNode } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { revendedores, type FichaDeRevendedor, type LinhaDeRevendedor, type OpcoesDosRevendedores, type RegraDeComissao } from '@/api/revenda';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, data, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { BotaoDeIcone, Confirmar, Dado } from './comum';

/**
 * OS REVENDEDORES (programa de revendedores, 16/09/2026 — RV-03, RV-04, RV-12).
 *
 * Os pedidos por aprovar vêm primeiro. Aprovar pede o código e a comissão;
 * recusar pede o motivo (que o revendedor lê). Na ficha: os dados, a regra da
 * comissão (mudar só vale para os pagamentos seguintes), as empresas, as
 * comissões — escolhem-se as por pagar e regista-se o pagamento — e o
 * histórico dos pagamentos.
 */
const COR_DO_ESTADO: Record<LinhaDeRevendedor['estado'], 'aviso' | 'bom' | 'perigo' | 'neutra'> = {
    pendente: 'aviso', aprovado: 'bom', suspenso: 'perigo', recusado: 'neutra',
};
const ICONE_DO_ESTADO: Record<LinhaDeRevendedor['estado'], string> = {
    pendente: 'fa-hourglass-half', aprovado: 'fa-circle-check', suspenso: 'fa-ban', recusado: 'fa-circle-xmark',
};
const ABAS_DE_ESTADO = [
    { valor: '', rotulo: 'Todos' },
    { valor: 'pendente', rotulo: 'Por aprovar' },
    { valor: 'aprovado', rotulo: 'Aprovados' },
    { valor: 'suspenso', rotulo: 'Suspensos' },
    { valor: 'recusado', rotulo: 'Recusados' },
];

const kzs = (v: number) => `${kz(v)} Kz`;

export default function Revendedores() {
    const fila = useQueryClient();
    const [estado, porEstado] = useState('');
    const [procura, porProcura] = useState('');
    const [procurar, porProcurar] = useState('');
    const [pagina, porPagina] = useState(1);
    const [aVer, porAVer] = useState<number | null>(null);
    const [aAprovar, porAAprovar] = useState<LinhaDeRevendedor | null>(null);
    const [aRecusar, porARecusar] = useState<LinhaDeRevendedor | null>(null);
    const [aSuspender, porASuspender] = useState<LinhaDeRevendedor | null>(null);

    useEffect(() => {
        const espera = window.setTimeout(() => { porProcurar(procura); porPagina(1); }, 350);
        return () => window.clearTimeout(espera);
    }, [procura]);

    const lista = useQuery({
        queryKey: ['plataforma', 'revendedores', estado, procurar, pagina],
        queryFn: () => revendedores.lista({ estado, procura: procurar, pagina }),
        placeholderData: keepPreviousData,
    });
    const opcoes = useQuery({ queryKey: ['plataforma', 'revendedores', 'opcoes'], queryFn: revendedores.opcoes, staleTime: 5 * 60_000 });

    const refrescar = () => void fila.invalidateQueries({ queryKey: ['plataforma', 'revendedores'] });

    const reactivar = useMutation({ mutationFn: (id: number) => revendedores.reactivar(id), onSuccess: refrescar });
    const suspender = useMutation({ mutationFn: (id: number) => revendedores.suspender(id), onSuccess: () => { porASuspender(null); refrescar(); } });
    const [aAvisar, porAAvisar] = useState<LinhaDeRevendedor | null>(null);
    const enviarAcesso = useMutation({ mutationFn: (id: number) => revendedores.dadosDeAcesso(id), onSuccess: () => porAAvisar(null) });

    const c = lista.data?.contagens;

    return (
        <div className="space-y-6">
            <Faixa titulo={t('Revendedores')} subtitulo={t('Pedidos, comissões e pagamentos do programa de revendedores')} icone="fa-handshake" cor="roxo"
                accoes={<>
                    {c && c.pendente > 0 && <EstadoNaFaixa icone="fa-bell">{t(':n por aprovar', { n: c.pendente })}</EstadoNaFaixa>}
                    <a href="/revendedores" target="_blank" rel="noreferrer" className={ACCAO_DA_FAIXA}><i className="fas fa-arrow-up-right-from-square" aria-hidden="true" />{t('Página pública')}</a>
                </>} />

            {lista.data && (
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                    <CartaoNumero rotulo={t('Por aprovar')} valor={c?.pendente ?? 0} icone="fa-hourglass-half" tom="ambar" aoCarregar={() => { porEstado('pendente'); porPagina(1); }} />
                    <CartaoNumero rotulo={t('Aprovados')} valor={c?.aprovado ?? 0} icone="fa-circle-check" tom="verde" aoCarregar={() => { porEstado('aprovado'); porPagina(1); }} />
                    <CartaoNumero rotulo={t('Suspensos')} valor={c?.suspenso ?? 0} icone="fa-ban" tom="vermelho" aoCarregar={() => { porEstado('suspenso'); porPagina(1); }} />
                    <CartaoNumero rotulo={t('Empresas ligadas')} valor={lista.data.totais.empresas} icone="fa-building" tom="indigo" />
                    <CartaoNumero rotulo={t('Comissões por pagar')} valor={kz(lista.data.totais.por_pagar)} sufixo="Kz" icone="fa-coins" tom="laranja" />
                    <CartaoNumero rotulo={t('Comissões pagas')} valor={kz(lista.data.totais.pago)} sufixo="Kz" icone="fa-sack-dollar" tom="teal" />
                </div>
            )}

            <div className={cls(CARTAO, 'flex flex-wrap items-center gap-3 p-4')}>
                <div role="group" aria-label={t('Estado')} className="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
                    {ABAS_DE_ESTADO.map((a) => (
                        <button key={a.valor || 'todos'} type="button" aria-pressed={estado === a.valor} onClick={() => { porEstado(a.valor); porPagina(1); }}
                            className={cls('px-3 py-1.5 text-sm font-semibold', RAIO, TRANSICAO, FOCO, estado === a.valor ? 'bg-white text-purple-700 shadow' : 'text-slate-600 hover:text-slate-900')}>
                            {t(a.rotulo)}
                        </button>
                    ))}
                </div>
                <div className="relative min-w-[14rem] flex-1">
                    <label htmlFor="rvp-procura" className="sr-only">{t('Procurar')}</label>
                    <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input id="rvp-procura" type="search" value={procura} onChange={(e) => porProcura(e.target.value)} placeholder={t('Nome, email, código, telefone ou NIF')} className={cls(entrada, 'pl-9')} />
                </div>
            </div>

            {lista.isPending ? <Carregando linhas={6} /> : lista.isError ? <AvisoDeErro erro={lista.error} /> : lista.data.revendedores.length === 0 ? (
                <div className={CARTAO}>
                    <SemNada icone="fa-handshake" titulo={t('Sem revendedores')} frase={procurar || estado ? t('Nenhum revendedor com estes filtros.') : t('Os pedidos feitos na página «Seja revendedor» aparecem aqui.')} />
                </div>
            ) : (
                <div className={cls(CARTAO, 'overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3"><i className="fas fa-user mr-1.5 text-slate-400" aria-hidden="true" />{t('Revendedor')}</th>
                                    <th className="px-4 py-3"><i className="fas fa-hashtag mr-1.5 text-slate-400" aria-hidden="true" />{t('Código')}</th>
                                    <th className="px-4 py-3"><i className="fas fa-signal mr-1.5 text-slate-400" aria-hidden="true" />{t('Estado')}</th>
                                    <th className="px-4 py-3 text-right"><i className="fas fa-building mr-1.5 text-slate-400" aria-hidden="true" />{t('Empresas')}</th>
                                    <th className="px-4 py-3 text-right"><i className="fas fa-coins mr-1.5 text-slate-400" aria-hidden="true" />{t('Por pagar')}</th>
                                    <th className="px-4 py-3 text-right"><i className="fas fa-sack-dollar mr-1.5 text-slate-400" aria-hidden="true" />{t('Pago')}</th>
                                    <th className="px-4 py-3"><i className="fas fa-calendar mr-1.5 text-slate-400" aria-hidden="true" />{t('Pedido')}</th>
                                    <th className="px-4 py-3 text-right"><i className="fas fa-gear mr-1.5 text-slate-400" aria-hidden="true" />{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {lista.data.revendedores.map((r, i) => (
                                    <tr key={r.id} style={cascata(i)} className={cls('entra group hover:bg-purple-50/40', r.estado === 'pendente' && 'bg-amber-50/40')}>
                                        <td className="px-4 py-3">
                                            <button type="button" onClick={() => porAVer(r.id)} className={cls('flex items-center gap-3 text-left', FOCO, RAIO)}>
                                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-purple-600 to-pink-600 text-xs font-bold text-white shadow-sm transition-transform duration-200 group-hover:scale-110">{r.nome.slice(0, 2).toUpperCase()}</span>
                                                <span className="min-w-0">
                                                    <span className="block font-semibold text-slate-900 group-hover:text-purple-700">{r.empresa ?? r.nome}</span>
                                                    <span className="block text-xs text-slate-500">{[r.empresa ? r.nome : null, r.email, r.telefone].filter(Boolean).join(' · ')}</span>
                                                </span>
                                            </button>
                                        </td>
                                        <td className="px-4 py-3 font-mono font-bold text-purple-700">{r.codigo ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <Etiqueta cor={COR_DO_ESTADO[r.estado]} icone={ICONE_DO_ESTADO[r.estado]}>{r.estado_rotulo}</Etiqueta>
                                            {r.regra && <span className="mt-1 block max-w-[16rem] truncate text-xs text-slate-500" title={r.regra}>{r.regra}</span>}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">{r.empresas}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums font-semibold text-amber-700">{r.por_pagar > 0 ? kzs(r.por_pagar) : '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-emerald-700">{r.pago > 0 ? kzs(r.pago) : '—'}</td>
                                        <td className="px-4 py-3 text-slate-600">{data(r.pedido_em)}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <BotaoDeIcone icone="fa-eye" rotulo={t('Ver a ficha')} cor="text-slate-600 hover:bg-slate-100" onClick={() => porAVer(r.id)} />
                                                {(r.estado === 'pendente' || r.estado === 'recusado') && <BotaoDeIcone icone="fa-check" rotulo={t('Aprovar')} cor="text-emerald-600 hover:bg-emerald-50" onClick={() => porAAprovar(r)} />}
                                                {r.estado === 'pendente' && <BotaoDeIcone icone="fa-xmark" rotulo={t('Recusar')} cor="text-red-600 hover:bg-red-50" onClick={() => porARecusar(r)} />}
                                                {r.estado === 'aprovado' && <BotaoDeIcone icone="fa-paper-plane" rotulo={t('Enviar dados de acesso')} cor="text-violet-600 hover:bg-violet-50" onClick={() => { enviarAcesso.reset(); porAAvisar(r); }} />}
                                                {r.estado === 'aprovado' && <BotaoDeIcone icone="fa-ban" rotulo={t('Suspender')} cor="text-red-600 hover:bg-red-50" onClick={() => porASuspender(r)} />}
                                                {r.estado === 'suspenso' && <BotaoDeIcone icone="fa-rotate-left" rotulo={t('Reactivar')} cor="text-emerald-600 hover:bg-emerald-50" desligado={reactivar.isPending} onClick={() => reactivar.mutate(r.id)} />}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-slate-100 p-3">
                        <Paginacao pagina={lista.data.paginacao.pagina} ultima={lista.data.paginacao.ultima} aMudar={porPagina}
                            total={lista.data.paginacao.total} de={lista.data.paginacao.de} ate={lista.data.paginacao.ate} aCarregar={lista.isFetching} />
                    </div>
                </div>
            )}

            {aVer !== null && opcoes.data && <Ficha id={aVer} opcoes={opcoes.data} aoFechar={() => porAVer(null)} aoMudar={refrescar}
                aoAprovar={(r) => porAAprovar(r)} aoRecusar={(r) => porARecusar(r)} />}
            {aAprovar && opcoes.data && <Aprovar r={aAprovar} opcoes={opcoes.data} aoFechar={() => porAAprovar(null)} aoFeito={refrescar} />}
            {aRecusar && <Recusar r={aRecusar} aoFechar={() => porARecusar(null)} aoFeito={refrescar} />}
            <Confirmar aberto={aAvisar !== null} titulo={t('Enviar dados de acesso')} subtitulo={aAvisar?.empresa ?? aAvisar?.nome} rotulo={t('Enviar email')} icone="fa-paper-plane"
                aTrabalhar={enviarAcesso.isPending} erro={enviarAcesso.error} aoConfirmar={() => aAvisar && enviarAcesso.mutate(aAvisar.id)} aoFechar={() => porAAvisar(null)}>
                <p>{t('Vai para :email um email com o endereço do portal, o email de entrada, o código, o link de revendedor, a comissão e um guia rápido do portal.', { email: aAvisar?.email ?? '' })}</p>
                <p className="mt-2 text-xs text-slate-500"><i className="fas fa-lock mr-1" aria-hidden="true" />{t('A senha não vai no email: se não a tiver, o revendedor escolhe outra em «Esqueci a senha».')}</p>
            </Confirmar>
            <Confirmar aberto={aSuspender !== null} titulo={t('Suspender revendedor')} subtitulo={aSuspender?.empresa ?? aSuspender?.nome} rotulo={t('Suspender')} icone="fa-ban"
                aTrabalhar={suspender.isPending} erro={suspender.error} aoConfirmar={() => aSuspender && suspender.mutate(aSuspender.id)} aoFechar={() => porASuspender(null)}>
                <p>{t('O portal fecha já, e os pagamentos das empresas dele deixam de dar comissão enquanto estiver suspenso. As empresas continuam ligadas e as comissões já feitas ficam como estão.')}</p>
            </Confirmar>
        </div>
    );
}

/* ─── A regra da comissão ─────────────────────────────────────────────── */

function resumoDaRegra(r: RegraDeComissao, planos: OpcoesDosRevendedores['planos']): string {
    const v = Number(r.valor || 0);
    const quanto = r.tipo === 'fixo' ? t(':v Kz por pagamento', { v: kz(v) }) : `${v}% ${r.base === 'com_iva' ? t('sobre o valor com IVA') : t('sobre o valor sem IVA')}`;
    const quando = r.aplica === 'primeiro' ? t('só no primeiro pagamento de cada empresa') : r.aplica === 'meses' ? t('nos primeiros :n meses da empresa', { n: r.meses ?? 0 }) : t('em todos os pagamentos');
    const excepcoes = r.planos.filter((p) => p.plan_id).map((p) => `${planos.find((x) => x.valor === Number(p.plan_id))?.rotulo ?? '?'}: ${p.tipo === 'fixo' ? `${kz(Number(p.valor || 0))} Kz` : `${p.valor}%`}`);

    return `${quanto}, ${quando}${excepcoes.length ? ` · ${excepcoes.join(', ')}` : ''}`;
}

function EditorDaRegra({ regra, porRegra, opcoes, erros }: { regra: RegraDeComissao; porRegra: (r: RegraDeComissao) => void; opcoes: OpcoesDosRevendedores; erros: Record<string, string[]> }) {
    const mudar = <K extends keyof RegraDeComissao>(k: K, v: RegraDeComissao[K]) => porRegra({ ...regra, [k]: v });
    const mudarPlano = (i: number, k: 'plan_id' | 'tipo' | 'valor', v: string) =>
        mudar('planos', regra.planos.map((p, j) => (j === i ? { ...p, [k]: v } : p)));
    const exemplo = opcoes.planos[0];

    return (
        <div className="space-y-4">
            <div className={cls('border border-purple-200 bg-gradient-to-r from-purple-50 to-pink-50 p-3 text-sm text-purple-900', RAIO)}>
                <i className="fas fa-wand-magic-sparkles mr-1.5" aria-hidden="true" />{resumoDaRegra(regra, opcoes.planos)}
                {exemplo && exemplo.preco > 0 && (
                    <span className="mt-1 block text-xs text-purple-700">
                        {t('Exemplo: um pagamento de :p (:plano) dá :v de comissão.', {
                            p: `${kz(exemplo.preco)} Kz`, plano: exemplo.rotulo,
                            v: `${kz(regra.tipo === 'fixo' ? Number(regra.valor || 0) : (exemplo.preco * Number(regra.valor || 0)) / 100)} Kz`,
                        })}
                    </span>
                )}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <p className="mb-1.5 text-sm font-semibold text-slate-700">{t('Tipo')}</p>
                    <div className="flex gap-2">
                        {opcoes.tipos.map((o) => (
                            <button key={o.valor} type="button" aria-pressed={regra.tipo === o.valor} onClick={() => mudar('tipo', o.valor as RegraDeComissao['tipo'])}
                                className={cls('flex-1 border-2 px-3 py-2 text-sm font-semibold', RAIO, TRANSICAO, FOCO,
                                    regra.tipo === o.valor ? 'border-purple-500 bg-purple-50 text-purple-800' : 'border-slate-200 text-slate-600 hover:border-purple-300')}>
                                <i className={cls('fas mr-1.5', o.valor === 'fixo' ? 'fa-money-bill' : 'fa-percent')} aria-hidden="true" />{o.rotulo}
                            </button>
                        ))}
                    </div>
                </div>
                <Campo etiqueta={regra.tipo === 'fixo' ? t('Valor por pagamento (Kz)') : t('Percentagem (%)')} erro={erros['comissao.valor']} obrigatorio>
                    <input type="number" min="0" step="0.01" max={regra.tipo === 'percentagem' ? 100 : undefined} value={regra.valor} onChange={(e) => mudar('valor', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Quando')} erro={erros['comissao.aplica']}>
                    <select value={regra.aplica} onChange={(e) => mudar('aplica', e.target.value as RegraDeComissao['aplica'])} className={entrada}>
                        {opcoes.quando.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                    </select>
                </Campo>
                {regra.aplica === 'meses' ? (
                    <Campo etiqueta={t('Durante quantos meses')} erro={erros['comissao.meses']} obrigatorio ajuda={t('Contados desde que a empresa ficou ligada ao revendedor.')}>
                        <input type="number" min="1" max="120" value={regra.meses ?? ''} onChange={(e) => mudar('meses', e.target.value)} className={cls(entrada, 'text-right')} />
                    </Campo>
                ) : regra.tipo === 'percentagem' ? (
                    <Campo etiqueta={t('Base')} erro={erros['comissao.base']}>
                        <select value={regra.base} onChange={(e) => mudar('base', e.target.value as RegraDeComissao['base'])} className={entrada}>
                            {opcoes.bases.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                        </select>
                    </Campo>
                ) : <div />}
                {regra.aplica === 'meses' && regra.tipo === 'percentagem' && (
                    <Campo etiqueta={t('Base')} erro={erros['comissao.base']}>
                        <select value={regra.base} onChange={(e) => mudar('base', e.target.value as RegraDeComissao['base'])} className={entrada}>
                            {opcoes.bases.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                        </select>
                    </Campo>
                )}
            </div>

            <div>
                <div className="mb-2 flex items-center justify-between gap-2">
                    <p className="text-sm font-semibold text-slate-700"><i className="fas fa-layer-group mr-1.5 text-purple-500" aria-hidden="true" />{t('Excepções por plano')}</p>
                    <Botao altura="pequeno" cor="primaria" icone="fa-plus" onClick={() => mudar('planos', [...regra.planos, { plan_id: '', tipo: 'percentagem', valor: '' }])}>{t('Juntar excepção')}</Botao>
                </div>
                {regra.planos.length === 0 ? (
                    <p className="text-xs text-slate-500">{t('Sem excepções: a regra vale para todos os planos.')}</p>
                ) : (
                    <ul className="space-y-2">
                        {regra.planos.map((p, i) => (
                            <li key={i} className="animate-fade-in grid grid-cols-[1fr_9rem_7rem_auto] items-start gap-2">
                                <div>
                                    <select aria-label={t('Plano')} value={p.plan_id} onChange={(e) => mudarPlano(i, 'plan_id', e.target.value)} className={entrada}>
                                        <option value="">{t('Escolher o plano...')}</option>
                                        {opcoes.planos.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                                    </select>
                                    {erros[`comissao.planos.${i}.plan_id`] && <p className="mt-1 text-xs text-red-600">{erros[`comissao.planos.${i}.plan_id`]?.[0]}</p>}
                                </div>
                                <select aria-label={t('Tipo')} value={p.tipo} onChange={(e) => mudarPlano(i, 'tipo', e.target.value)} className={entrada}>
                                    {opcoes.tipos.map((o) => <option key={o.valor} value={o.valor}>{o.valor === 'fixo' ? t('Valor fixo') : '%'}</option>)}
                                </select>
                                <div>
                                    <input aria-label={t('Valor')} type="number" min="0" step="0.01" value={p.valor} onChange={(e) => mudarPlano(i, 'valor', e.target.value)} className={cls(entrada, 'text-right')} />
                                    {erros[`comissao.planos.${i}.valor`] && <p className="mt-1 text-xs text-red-600">{erros[`comissao.planos.${i}.valor`]?.[0]}</p>}
                                </div>
                                <BotaoDeIcone icone="fa-trash" rotulo={t('Tirar')} cor="mt-1 text-red-600 hover:bg-red-50" onClick={() => mudar('planos', regra.planos.filter((_, j) => j !== i))} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

/* ─── Aprovar e recusar ───────────────────────────────────────────────── */

function Aprovar({ r, opcoes, aoFechar, aoFeito }: { r: LinhaDeRevendedor; opcoes: OpcoesDosRevendedores; aoFechar: () => void; aoFeito: () => void }) {
    const [codigo, porCodigo] = useState(r.codigo ?? '');
    const [regra, porRegra] = useState<RegraDeComissao>({ ...opcoes.padrao, planos: [] });
    const aprovar = useMutation({
        mutationFn: () => revendedores.aprovar(r.id, { codigo, comissao: regra }),
        onSuccess: () => { aoFeito(); aoFechar(); },
    });
    const erros = aprovar.error instanceof ErroDaApi ? aprovar.error.erros : {};

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Aprovar revendedor')} subtitulo={r.empresa ?? r.nome} icone="fa-user-check" cor="bom" largura="lg"
            rodape={<>
                <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={aprovar.isPending} onClick={() => aprovar.mutate()}>{t('Aprovar e avisar')}</Botao>
            </>}>
            <div className="space-y-5">
                {aprovar.error && !Object.keys(erros).length && <AvisoDeErro erro={aprovar.error} />}
                <Campo etiqueta={t('Código do revendedor')} erro={erros.codigo} ajuda={t('É o que vai no link (/r/CÓDIGO) e no campo do registo. Vazio: geramos um a partir do nome.')}>
                    <input value={codigo} onChange={(e) => porCodigo(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))} maxLength={20} placeholder="JOAO4821" className={cls(entrada, 'font-mono font-bold tracking-widest')} />
                </Campo>
                <div className={cls('border border-slate-200 p-4', RAIO)}>
                    <p className="mb-3 font-bold text-slate-900"><i className="fas fa-percent mr-1.5 text-purple-500" aria-hidden="true" />{t('A comissão')}</p>
                    <EditorDaRegra regra={regra} porRegra={porRegra} opcoes={opcoes} erros={erros} />
                </div>
                <p className="text-xs text-slate-500">{t('O revendedor recebe por email o código, o link de revendedor e a comissão, e passa a poder entrar no portal.')}</p>
            </div>
        </Modal>
    );
}

function Recusar({ r, aoFechar, aoFeito }: { r: LinhaDeRevendedor; aoFechar: () => void; aoFeito: () => void }) {
    const [motivo, porMotivo] = useState('');
    const recusar = useMutation({ mutationFn: () => revendedores.recusar(r.id, motivo), onSuccess: () => { aoFeito(); aoFechar(); } });
    const erros = recusar.error instanceof ErroDaApi ? recusar.error.erros : {};

    return (
        <Confirmar aberto titulo={t('Recusar pedido')} subtitulo={r.empresa ?? r.nome} rotulo={t('Recusar')} icone="fa-xmark"
            aTrabalhar={recusar.isPending} erro={Object.keys(erros).length ? null : recusar.error} aoConfirmar={() => recusar.mutate()} aoFechar={aoFechar}>
            <Campo etiqueta={t('Motivo (o revendedor vai lê-lo)')} erro={erros.motivo} obrigatorio>
                <textarea rows={3} value={motivo} onChange={(e) => porMotivo(e.target.value)} className={entrada} />
            </Campo>
        </Confirmar>
    );
}

/* ─── A ficha ─────────────────────────────────────────────────────────── */

function Ficha({ id, opcoes, aoFechar, aoMudar, aoAprovar, aoRecusar }: {
    id: number; opcoes: OpcoesDosRevendedores; aoFechar: () => void; aoMudar: () => void;
    aoAprovar: (r: LinhaDeRevendedor) => void; aoRecusar: (r: LinhaDeRevendedor) => void;
}) {
    const fila = useQueryClient();
    const q = useQuery({ queryKey: ['plataforma', 'revendedores', 'ficha', id], queryFn: () => revendedores.ver(id) });
    const [aba, porAba] = useState('dados');

    const refrescar = () => { void fila.invalidateQueries({ queryKey: ['plataforma', 'revendedores'] }); aoMudar(); };

    const r = q.data?.revendedor;
    const abas = useMemo(() => [
        { chave: 'dados', rotulo: t('Dados e comissão'), icone: 'fa-id-card' },
        { chave: 'empresas', rotulo: `${t('Empresas')} (${q.data?.empresas.length ?? 0})`, icone: 'fa-building' },
        { chave: 'comissoes', rotulo: `${t('Comissões')} (${q.data?.comissoes.length ?? 0})`, icone: 'fa-coins' },
        { chave: 'pagamentos', rotulo: `${t('Pagamentos')} (${q.data?.pagamentos.length ?? 0})`, icone: 'fa-money-bill-transfer' },
    ], [q.data]);

    return (
        <Modal aberto aoFechar={aoFechar} titulo={r ? r.empresa ?? r.nome : t('Revendedor')} subtitulo={r ? [r.codigo, r.estado_rotulo].filter(Boolean).join(' · ') : undefined}
            icone="fa-handshake" cor="roxo" largura="xl"
            rodape={r && (r.estado === 'pendente' || r.estado === 'recusado') ? <>
                {r.estado === 'pendente' && <Botao cor="perigo" icone="fa-xmark" onClick={() => { aoFechar(); aoRecusar(r); }}>{t('Recusar')}</Botao>}
                <Botao cor="bom" tom="solida" icone="fa-check" onClick={() => { aoFechar(); aoAprovar(r); }}>{t('Aprovar')}</Botao>
            </> : undefined}>
            {q.isPending ? <Carregando linhas={6} /> : q.isError ? <AvisoDeErro erro={q.error} /> : (
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Mini icone="fa-building" rotulo={t('Empresas')} valor={String(q.data.revendedor.empresas)} tom="text-indigo-600" />
                        <Mini icone="fa-hourglass-half" rotulo={t('Por pagar')} valor={kzs(q.data.totais.por_pagar)} tom="text-amber-600" />
                        <Mini icone="fa-sack-dollar" rotulo={t('Pago')} valor={kzs(q.data.totais.pago)} tom="text-emerald-600" />
                        <Mini icone="fa-calendar-check" rotulo={t('Este mês')} valor={kzs(q.data.totais.do_mes)} tom="text-purple-600" />
                    </div>
                    <Separadores abas={abas} activa={aba} aoMudar={porAba} />
                    <PainelDoSeparador chave="dados" activa={aba}><DadosEComissao f={q.data} opcoes={opcoes} aoGuardar={refrescar} /></PainelDoSeparador>
                    <PainelDoSeparador chave="empresas" activa={aba}><EmpresasDoRevendedor f={q.data} /></PainelDoSeparador>
                    <PainelDoSeparador chave="comissoes" activa={aba}><ComissoesDoRevendedor f={q.data} opcoes={opcoes} aoMudar={refrescar} /></PainelDoSeparador>
                    <PainelDoSeparador chave="pagamentos" activa={aba}><PagamentosDoRevendedor f={q.data} /></PainelDoSeparador>
                </div>
            )}
        </Modal>
    );
}

function Mini({ icone, rotulo, valor, tom }: { icone: string; rotulo: string; valor: string; tom: string }) {
    return (
        <div className={cls('border border-slate-200 bg-white p-3', RAIO)}>
            <p className="text-xs font-semibold text-slate-500"><i className={cls('fas mr-1.5', icone, tom)} aria-hidden="true" />{rotulo}</p>
            <p className="mt-1 font-bold tabular-nums text-slate-900">{valor}</p>
        </div>
    );
}

function DadosEComissao({ f, opcoes, aoGuardar }: { f: FichaDeRevendedor; opcoes: OpcoesDosRevendedores; aoGuardar: () => void }) {
    const r = f.revendedor;
    const [d, porD] = useState({
        name: r.nome, company_name: r.empresa ?? '', nif: r.nif ?? '', email: r.email, phone: r.telefone ?? '',
        province: r.provincia ?? '', city: r.cidade ?? '', website: r.site ?? '', bank_name: r.banco ?? '', iban: r.iban ?? '',
        internal_notes: r.notas ?? '', codigo: r.codigo ?? '',
    });
    const [regra, porRegra] = useState<RegraDeComissao>({ ...r.comissao, planos: r.comissao.planos ?? [] });
    const guardar = useMutation({ mutationFn: () => revendedores.guardar(r.id, { ...d, comissao: regra }), onSuccess: aoGuardar });
    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const campo = (k: keyof typeof d) => ({ value: d[k], onChange: (e: { target: { value: string } }) => porD((a) => ({ ...a, [k]: e.target.value })) });

    return (
        <div className="space-y-5">
            {guardar.error && !Object.keys(erros).length && <AvisoDeErro erro={guardar.error} />}
            {r.motivacao && (
                <div className={cls('border-l-4 border-purple-400 bg-purple-50 p-3 text-sm text-slate-700', RAIO)}>
                    <p className="text-xs font-bold uppercase tracking-wider text-purple-700">{t('Como pensa revender')}</p>
                    <p className="mt-1 whitespace-pre-line">{r.motivacao}</p>
                </div>
            )}
            {r.motivo_da_recusa && r.estado === 'recusado' && (
                <p className={cls('bg-red-50 p-3 text-sm text-red-800', RAIO)}><b>{t('Recusado:')}</b> {r.motivo_da_recusa}</p>
            )}
            <dl className="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                <Dado rotulo={t('Pedido em')}>{data(r.pedido_em)}</Dado>
                <Dado rotulo={t('Aprovado em')}>{r.aprovado_em ? data(r.aprovado_em) : '—'}</Dado>
                <Dado rotulo={t('Última entrada')}>{r.ultima_entrada ? data(r.ultima_entrada) : t('Nunca')}</Dado>
                <Dado rotulo={t('Link')}>{r.link ? <a href={r.link} target="_blank" rel="noreferrer" className="break-all text-purple-700 hover:underline">{r.link}</a> : '—'}</Dado>
            </dl>

            <div className="grid gap-4 md:grid-cols-3">
                <Campo etiqueta={t('Nome')} erro={erros.name} obrigatorio><input {...campo('name')} className={entrada} /></Campo>
                <Campo etiqueta={t('Empresa')} erro={erros.company_name}><input {...campo('company_name')} className={entrada} /></Campo>
                <Campo etiqueta={t('NIF')} erro={erros.nif}><input {...campo('nif')} className={entrada} /></Campo>
                <Campo etiqueta={t('Email (a entrada)')} erro={erros.email} obrigatorio><input type="email" {...campo('email')} className={entrada} /></Campo>
                <Campo etiqueta={t('Telefone')} erro={erros.phone}><input {...campo('phone')} className={entrada} /></Campo>
                <Campo etiqueta={t('Site')} erro={erros.website}><input type="url" {...campo('website')} className={entrada} /></Campo>
                <Campo etiqueta={t('Província')} erro={erros.province}><input {...campo('province')} className={entrada} /></Campo>
                <Campo etiqueta={t('Cidade')} erro={erros.city}><input {...campo('city')} className={entrada} /></Campo>
                <Campo etiqueta={t('Código')} erro={erros.codigo} ajuda={r.estado === 'pendente' ? t('Nasce na aprovação.') : undefined}>
                    <input value={d.codigo} onChange={(e) => porD((a) => ({ ...a, codigo: e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '') }))} maxLength={20} className={cls(entrada, 'font-mono font-bold')} />
                </Campo>
                <Campo etiqueta={t('Banco')} erro={erros.bank_name}><input {...campo('bank_name')} className={entrada} /></Campo>
                <Campo etiqueta="IBAN" erro={erros.iban} className="md:col-span-2"><input {...campo('iban')} className={cls(entrada, 'font-mono')} /></Campo>
                <Campo etiqueta={t('Notas internas (o revendedor não as vê)')} erro={erros.internal_notes} className="md:col-span-3">
                    <textarea rows={2} {...campo('internal_notes')} className={entrada} />
                </Campo>
            </div>

            <div className={cls('border border-slate-200 p-4', RAIO)}>
                <p className="mb-1 font-bold text-slate-900"><i className="fas fa-percent mr-1.5 text-purple-500" aria-hidden="true" />{t('A comissão')}</p>
                <p className="mb-3 text-xs text-slate-500">{t('Mudar a regra só vale para os pagamentos seguintes: as comissões já feitas guardam a regra com que nasceram.')}</p>
                <EditorDaRegra regra={regra} porRegra={porRegra} opcoes={opcoes} erros={erros} />
            </div>

            <div className="flex justify-end">
                <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao>
            </div>
        </div>
    );
}

function EmpresasDoRevendedor({ f }: { f: FichaDeRevendedor }) {
    if (f.empresas.length === 0) return <SemNada icone="fa-building" frase={t('Ainda sem empresas ligadas.')} />;

    return (
        <Tabela cabecalho={[t('Empresa'), t('Plano'), t('Estado'), t('Ligada'), t('Último acesso')]}>
            {f.empresas.map((e, i) => (
                <tr key={e.id} style={cascata(i)} className="entra hover:bg-slate-50">
                    <td className="px-3 py-2.5"><span className="font-semibold text-slate-900">{e.nome}</span><span className="block text-xs text-slate-500">{e.nif}</span></td>
                    <td className="px-3 py-2.5">{e.plano ?? '—'}</td>
                    <td className="px-3 py-2.5">
                        <Etiqueta cor={e.cor === 'red' ? 'perigo' : e.cor === 'amber' ? 'aviso' : e.cor === 'gray' ? 'neutra' : e.cor === 'blue' ? 'primaria' : 'bom'} ponto>{e.estado}</Etiqueta>
                        {!e.activa && <span className="ml-1 text-xs text-red-600">{t('desactivada')}</span>}
                    </td>
                    <td className="px-3 py-2.5 text-slate-600">{e.via}<span className="block text-xs">{data(e.ligada_em)}</span></td>
                    <td className="px-3 py-2.5 text-slate-600">{e.ultima_entrada ? data(e.ultima_entrada) : t('Nunca')}</td>
                </tr>
            ))}
        </Tabela>
    );
}

function ComissoesDoRevendedor({ f, opcoes, aoMudar }: { f: FichaDeRevendedor; opcoes: OpcoesDosRevendedores; aoMudar: () => void }) {
    const [escolhidas, porEscolhidas] = useState<number[]>([]);
    const [aPagar, porAPagar] = useState(false);
    const [aAnular, porAAnular] = useState<number | null>(null);
    const porPagar = f.comissoes.filter((c) => c.estado === 'por_pagar');
    const soma = f.comissoes.filter((c) => escolhidas.includes(c.id)).reduce((s, c) => s + c.valor, 0);
    const alternar = (id: number) => porEscolhidas((e) => (e.includes(id) ? e.filter((x) => x !== id) : [...e, id]));

    if (f.comissoes.length === 0) return <SemNada icone="fa-coins" frase={t('Ainda sem comissões.')} />;

    return (
        <div className="space-y-3">
            <div className={cls('flex flex-wrap items-center gap-3 border border-emerald-200 bg-emerald-50 p-3', RAIO)}>
                <label className="flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <input type="checkbox" checked={porPagar.length > 0 && escolhidas.length === porPagar.length}
                        onChange={(e) => porEscolhidas(e.target.checked ? porPagar.map((c) => c.id) : [])} className="h-4 w-4 rounded text-emerald-600" />
                    {t('Todas as por pagar')}
                </label>
                <span className="flex-1 text-sm text-emerald-900">{t(':n escolhidas · :v', { n: escolhidas.length, v: kzs(soma) })}</span>
                <Botao cor="bom" tom="solida" icone="fa-money-bill-transfer" disabled={escolhidas.length === 0} onClick={() => porAPagar(true)}>{t('Registar pagamento')}</Botao>
            </div>
            <Tabela cabecalho={['', t('Data'), t('Empresa'), t('Origem'), t('Base'), t('Comissão'), t('Estado'), '']}>
                {f.comissoes.map((c, i) => (
                    <tr key={c.id} style={cascata(i)} className={cls('entra hover:bg-slate-50', escolhidas.includes(c.id) && 'bg-emerald-50/60')}>
                        <td className="px-3 py-2.5">
                            {c.estado === 'por_pagar' && <input type="checkbox" aria-label={t('Escolher')} checked={escolhidas.includes(c.id)} onChange={() => alternar(c.id)} className="h-4 w-4 rounded text-emerald-600" />}
                        </td>
                        <td className="px-3 py-2.5 text-slate-600">{data(c.criada_em)}</td>
                        <td className="px-3 py-2.5 font-semibold text-slate-900">{c.empresa}<span className="block text-xs font-normal text-slate-500">{c.plano}</span></td>
                        <td className="px-3 py-2.5 text-slate-600">{c.origem_rotulo}<span className="block text-xs">{c.regra}</span></td>
                        <td className="px-3 py-2.5 text-right tabular-nums">{kzs(c.base)}</td>
                        <td className="px-3 py-2.5 text-right font-bold tabular-nums">{kzs(c.valor)}</td>
                        <td className="px-3 py-2.5">
                            <Etiqueta cor={c.estado === 'paga' ? 'bom' : c.estado === 'anulada' ? 'neutra' : 'aviso'} ponto>{c.estado_rotulo}</Etiqueta>
                            {c.motivo && <span className="mt-1 block text-xs text-slate-500">{c.motivo}</span>}
                            {c.pagamento && <span className="mt-1 block text-xs text-slate-500">{data(c.pagamento.data)}</span>}
                        </td>
                        <td className="px-3 py-2.5 text-right">
                            {c.estado === 'por_pagar' && <BotaoDeIcone icone="fa-ban" rotulo={t('Anular')} cor="text-red-600 hover:bg-red-50" onClick={() => porAAnular(c.id)} />}
                        </td>
                    </tr>
                ))}
            </Tabela>

            {aPagar && <Pagar id={f.revendedor.id} ids={escolhidas} soma={soma} opcoes={opcoes} iban={f.revendedor.iban}
                aoFechar={() => porAPagar(false)} aoFeito={() => { porEscolhidas([]); aoMudar(); }} />}
            {aAnular !== null && <Anular id={f.revendedor.id} comissao={aAnular} aoFechar={() => porAAnular(null)} aoFeito={aoMudar} />}
        </div>
    );
}

function Pagar({ id, ids, soma, opcoes, iban, aoFechar, aoFeito }: { id: number; ids: number[]; soma: number; opcoes: OpcoesDosRevendedores; iban: string | null; aoFechar: () => void; aoFeito: () => void }) {
    const [d, porD] = useState({ method: 'transferencia', reference: '', paid_at: new Date().toISOString().slice(0, 10), notes: '' });
    const pagar = useMutation({ mutationFn: () => revendedores.pagar(id, { comissoes: ids, ...d }), onSuccess: () => { aoFeito(); aoFechar(); } });
    const erros = pagar.error instanceof ErroDaApi ? pagar.error.erros : {};

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Registar pagamento de comissões')} icone="fa-money-bill-transfer" cor="bom" largura="md"
            rodape={<>
                <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={pagar.isPending} onClick={() => pagar.mutate()}>{t('Registar :v', { v: kzs(soma) })}</Botao>
            </>}>
            <div className="space-y-4">
                {pagar.error && !Object.keys(erros).length && <AvisoDeErro erro={pagar.error} />}
                {erros.comissoes && <p className="text-sm text-red-700">{erros.comissoes[0]}</p>}
                <div className={cls('bg-gradient-to-r from-emerald-600 to-teal-600 p-4 text-white', RAIO)}>
                    <p className="text-xs text-white/80">{t(':n comissões', { n: ids.length })}</p>
                    <p className="text-3xl font-black tabular-nums">{kzs(soma)}</p>
                    <p className="mt-1 text-xs text-white/80">{iban ? `IBAN ${iban}` : t('O revendedor ainda não indicou o IBAN.')}</p>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Forma')} erro={erros.method} obrigatorio>
                        <select value={d.method} onChange={(e) => porD((a) => ({ ...a, method: e.target.value }))} className={entrada}>
                            {opcoes.metodos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Data')} erro={erros.paid_at} obrigatorio>
                        <input type="date" value={d.paid_at} max={new Date().toISOString().slice(0, 10)} onChange={(e) => porD((a) => ({ ...a, paid_at: e.target.value }))} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Referência')} erro={erros.reference} className="sm:col-span-2">
                        <input value={d.reference} onChange={(e) => porD((a) => ({ ...a, reference: e.target.value }))} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Notas')} erro={erros.notes} className="sm:col-span-2">
                        <textarea rows={2} value={d.notes} onChange={(e) => porD((a) => ({ ...a, notes: e.target.value }))} className={entrada} />
                    </Campo>
                </div>
                <p className="text-xs text-slate-500">{t('O revendedor recebe o aviso por email e vê o pagamento no portal.')}</p>
            </div>
        </Modal>
    );
}

function Anular({ id, comissao, aoFechar, aoFeito }: { id: number; comissao: number; aoFechar: () => void; aoFeito: () => void }) {
    const [motivo, porMotivo] = useState('');
    const anular = useMutation({ mutationFn: () => revendedores.anular(id, comissao, motivo), onSuccess: () => { aoFeito(); aoFechar(); } });
    const erros = anular.error instanceof ErroDaApi ? anular.error.erros : {};

    return (
        <Confirmar aberto titulo={t('Anular comissão')} rotulo={t('Anular')} icone="fa-ban" aTrabalhar={anular.isPending}
            erro={Object.keys(erros).length ? null : anular.error} aoConfirmar={() => anular.mutate()} aoFechar={aoFechar}>
            <p>{t('A comissão não se apaga: fica anulada, com o motivo, e deixa de contar.')}</p>
            <Campo etiqueta={t('Motivo')} erro={erros.motivo} obrigatorio>
                <textarea rows={2} value={motivo} onChange={(e) => porMotivo(e.target.value)} className={entrada} />
            </Campo>
        </Confirmar>
    );
}

function PagamentosDoRevendedor({ f }: { f: FichaDeRevendedor }) {
    if (f.pagamentos.length === 0) return <SemNada icone="fa-money-bill-transfer" frase={t('Ainda sem pagamentos registados.')} />;

    return (
        <Tabela cabecalho={[t('Data'), t('Valor'), t('Forma'), t('Referência'), t('Comissões'), t('Registado por')]}>
            {f.pagamentos.map((p, i) => (
                <tr key={p.id} style={cascata(i)} className="entra hover:bg-slate-50">
                    <td className="px-3 py-2.5">{data(p.data)}</td>
                    <td className="px-3 py-2.5 font-bold tabular-nums text-emerald-700">{kzs(p.valor)}</td>
                    <td className="px-3 py-2.5">{p.forma}</td>
                    <td className="px-3 py-2.5">{p.referencia ?? '—'}{p.notas && <span className="block text-xs text-slate-500">{p.notas}</span>}</td>
                    <td className="px-3 py-2.5 tabular-nums">{p.comissoes}</td>
                    <td className="px-3 py-2.5 text-slate-600">{p.por ?? '—'}</td>
                </tr>
            ))}
        </Tabela>
    );
}

function Tabela({ cabecalho, children }: { cabecalho: string[]; children: ReactNode }) {
    return (
        <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
            <table className="min-w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>{cabecalho.map((c, i) => <th key={i} className="px-3 py-2.5">{c}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-slate-100">{children}</tbody>
            </table>
        </div>
    );
}
