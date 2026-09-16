import { useQuery } from '@tanstack/react-query';

import { revenda, type EmpresaDoRevendedor } from '@/api/revenda';
import { t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data } from '@/ui/tokens';

import { Cabecalho, EstadoDaComissao, EstadoDaEmpresaEtiquetas, GRADIENTE_DO_PORTAL, LinkDeAfiliado, Numero, kwanzas } from './comum';

/**
 * O PAINEL DO REVENDEDOR (RV-07).
 *
 * Primeiro o que pede acção (empresas por pagar, a vencer, vencidas), depois o
 * link para trazer mais, os números e o que entrou de comissão.
 */
export default function Painel() {
    const q = useQuery({ queryKey: ['revenda', 'painel'], queryFn: revenda.painel });

    if (q.isPending) return <Carregando linhas={6} />;
    if (q.isError) return <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-800">{t('Não foi possível abrir o painel.')}</p>;

    const d = q.data;
    const c = d.contagens;

    return (
        <div className="space-y-8">
            <Cabecalho titulo={t('Olá, :nome!', { nome: d.revendedor.nome })} subtitulo={d.revendedor.empresa ?? t('Programa de Revendedores')} icone="fa-handshake" gradiente={GRADIENTE_DO_PORTAL}>
                <a href="/revendedor/empresas/nova" className={cls('group inline-flex items-center gap-2 bg-white px-4 py-2.5 text-sm font-bold text-violet-700 shadow hover:-translate-y-0.5 hover:shadow-lg', RAIO, TRANSICAO, FOCO)}>
                    <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />{t('Nova empresa')}
                </a>
            </Cabecalho>

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <Numero i={0} cor="roxo" rotulo={t('Empresas')} valor={c.todas} nota={t(':n activas · :t em teste', { n: c.activa, t: c.teste })} icone="fa-building" />
                <Numero i={1} cor={c.por_pagar + c.vencida > 0 ? 'ambar' : 'verde'} rotulo={t('Pedem atenção')} valor={c.por_pagar + c.a_vencer + c.vencida}
                    nota={t(':p por pagar · :v a vencer · :x vencidas', { p: c.por_pagar, v: c.a_vencer, x: c.vencida })} icone="fa-bell" />
                <Numero i={2} cor="ambar" rotulo={t('Comissões por receber')} valor={<span className="text-2xl">{kwanzas(d.comissoes.por_pagar)}</span>}
                    nota={t(':n comissões', { n: d.comissoes.por_pagar_n })} icone="fa-hourglass-half" />
                <Numero i={3} cor="verde" rotulo={t('Já recebido')} valor={<span className="text-2xl">{kwanzas(d.comissoes.pago)}</span>}
                    nota={t('Este mês: :v', { v: kwanzas(d.comissoes.do_mes) })} icone="fa-sack-dollar" />
            </div>

            <LinkDeAfiliado link={d.revendedor.link} codigo={d.revendedor.codigo} regra={d.revendedor.regra} />

            <div className="grid gap-6 lg:grid-cols-2">
                <section className={cls(CARTAO, 'entra p-6')}>
                    <h2 className="mb-4 flex items-center gap-2 text-lg font-bold text-gray-900">
                        <i className="fas fa-triangle-exclamation text-amber-500" aria-hidden="true" />{t('Pedem atenção')}
                    </h2>
                    {d.atencao.length === 0
                        ? <SemNada icone="fa-circle-check" titulo={t('Tudo em dia')} frase={t('Nenhuma empresa sua está por pagar, a vencer ou vencida.')} />
                        : <ul className="space-y-2">{d.atencao.map((e, i) => <LinhaDeEmpresa key={e.id} e={e} i={i} />)}</ul>}
                </section>

                <section className={cls(CARTAO, 'entra p-6')}>
                    <div className="mb-4 flex items-center justify-between gap-2">
                        <h2 className="flex items-center gap-2 text-lg font-bold text-gray-900"><i className="fas fa-sack-dollar text-emerald-500" aria-hidden="true" />{t('Últimas comissões')}</h2>
                        <a href="/revendedor/comissoes" className="text-sm font-semibold text-violet-700 hover:text-violet-900">{t('Ver todas')}<i className="fas fa-arrow-right ml-1.5" aria-hidden="true" /></a>
                    </div>
                    {d.ultimas_comissoes.length === 0 ? (
                        <SemNada icone="fa-coins" titulo={t('Ainda sem comissões')} frase={t('Cada pagamento confirmado de uma empresa sua dá-lhe uma comissão.')} />
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {d.ultimas_comissoes.map((cm, i) => (
                                <li key={cm.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 py-3">
                                    <span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><i className="fas fa-coins" aria-hidden="true" /></span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-semibold text-gray-900">{cm.empresa}</span>
                                        <span className="block text-xs text-gray-500">{cm.origem_rotulo} · {data(cm.criada_em)}</span>
                                    </span>
                                    <span className="text-right">
                                        <span className="block font-bold tabular-nums text-gray-900">{kwanzas(cm.valor)}</span>
                                        <EstadoDaComissao c={cm} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <section className={cls(CARTAO, 'entra p-6')}>
                <div className="mb-4 flex items-center justify-between gap-2">
                    <h2 className="flex items-center gap-2 text-lg font-bold text-gray-900"><i className="fas fa-building text-violet-500" aria-hidden="true" />{t('Empresas mais recentes')}</h2>
                    <a href="/revendedor/empresas" className="text-sm font-semibold text-violet-700 hover:text-violet-900">{t('Ver todas')}<i className="fas fa-arrow-right ml-1.5" aria-hidden="true" /></a>
                </div>
                {d.recentes.length === 0 ? (
                    <SemNada icone="fa-building" titulo={t('Ainda sem empresas')}
                        frase={t('Partilhe o seu link, dê o seu código aos clientes ou crie a empresa por eles.')}
                        accao={<a href="/revendedor/empresas/nova" className={cls('inline-flex items-center gap-2 bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700', RAIO, TRANSICAO)}><i className="fas fa-plus" aria-hidden="true" />{t('Criar a primeira empresa')}</a>} />
                ) : (
                    <ul className="grid gap-2 md:grid-cols-2">{d.recentes.map((e, i) => <LinhaDeEmpresa key={e.id} e={e} i={i} />)}</ul>
                )}
            </section>
        </div>
    );
}

export function LinhaDeEmpresa({ e, i }: { e: EmpresaDoRevendedor; i: number }) {
    return (
        <li style={cascata(i)} className="entra">
            <a href={`/revendedor/empresas/${e.id}`} className={cls('group flex flex-wrap items-center gap-3 border border-gray-100 bg-white p-3 hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-md', RAIO_GRANDE, TRANSICAO, FOCO)}>
                <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 font-bold text-white shadow transition-transform duration-300 group-hover:scale-110">
                    {e.nome.slice(0, 2).toUpperCase()}
                </span>
                <span className="min-w-0 flex-1 basis-40">
                    <span className="block truncate font-semibold text-gray-900">{e.nome}</span>
                    <span className="block truncate text-xs text-gray-500">{[e.plano ?? t('Sem plano'), e.estado.detalhe].filter(Boolean).join(' · ')}</span>
                </span>
                <EstadoDaEmpresaEtiquetas e={e.estado} />
                <i className="fas fa-chevron-right text-gray-300 transition-transform group-hover:translate-x-1" aria-hidden="true" />
            </a>
        </li>
    );
}
