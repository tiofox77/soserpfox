import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * O PAINEL DA PLATAFORMA — quantas empresas há, quanto entra e o que expira.
 *
 * O QUE MUDOU DE SUBSTÂNCIA está no servidor: o componente antigo embrulhava
 * cada consulta de dinheiro num `catch (\Exception $e) {}` VAZIO, pelo que uma
 * falha na consulta mostrava RECEITA ZERO — indistinguível de um mês sem vendas.
 *
 * E O QUE SAIU DAQUI: o botão de APAGAR UMA EMPRESA. Estava na lista de empresas
 * recentes deste painel, a um clique, com o id como único argumento e sem
 * pergunta nenhuma no componente. Apagar uma companhia inteira não é uma acção
 * de painel: vive no ecrã das Empresas, com as guardas que lá estão.
 */
export default function Painel() {
    const [aEntrar, porAEntrar] = useState<{ id: number; nome: string } | null>(null);

    const painel = useQuery({
        queryKey: ['plataforma', 'painel'],
        queryFn: plataforma.painel.ler,
        staleTime: 30_000,
    });

    const entrar = useMutation({
        mutationFn: (id: number) => plataforma.painel.entrarNaEmpresa(id),
        onSuccess: (r) => {
            // A sessão mudou de empresa: recarregar é o que põe o resto da
            // aplicação a olhar para a casa certa.
            window.location.href = r.seguir_para;
        },
    });

    if (painel.isPending) return <Carregando linhas={12} />;

    if (painel.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o painel')}</h2>
                <p className="text-sm text-red-800">
                    {painel.error instanceof ErroDaApi ? painel.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const p = painel.data;
    const n = p.numeros;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Painel da Plataforma')}
                subtitulo={t('Quantas empresas há, quanto entra e o que está a expirar')}
                icone="fa-gauge-high"
                cor="roxo"
                accoes={
                    <div className="flex flex-wrap items-center gap-2">
                        <a href="/superadmin/tenants" className={ACCAO}>
                            <i className="fas fa-building" aria-hidden="true" />
                            {t('Empresas')}
                        </a>
                        <a href="/superadmin/billing" className={ACCAO}>
                            <i className="fas fa-file-invoice-dollar" aria-hidden="true" />
                            {t('Facturação')}
                        </a>
                    </div>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-building">
                        {t(':n empresa(s) · :a activa(s)', { n: n.empresas, a: n.activas })}
                    </EstadoNaFaixa>

                    {/* OS PEDIDOS POR APROVAR são dinheiro à espera de alguém. */}
                    {n.pedidos_por_aprovar > 0 && (
                        <a href="/superadmin/billing" className={cls('inline-flex items-center gap-2 bg-white/20 px-3 py-1.5 text-sm font-semibold text-white', RAIO, FOCO)}>
                            <i className="fas fa-hourglass-half" aria-hidden="true" />
                            {t(':n pedido(s) por aprovar', { n: n.pedidos_por_aprovar })}
                        </a>
                    )}
                </div>
            </Faixa>

            <ErroDaAccao erro={entrar.error} />

            {/* OS NÚMEROS */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <CartaoNumero
                    rotulo={t('Empresas')}
                    valor={n.empresas.toLocaleString('pt-PT')}
                    icone="fa-building"
                    tom="roxo"
                    aspecto="claro"
                    nota={t(':a activas · :i inactivas', { a: n.activas, i: n.inactivas })}
                />
                <CartaoNumero
                    rotulo={t('Utilizadores')}
                    valor={n.utilizadores.toLocaleString('pt-PT')}
                    icone="fa-users"
                    tom="azul"
                    aspecto="claro"
                    nota={n.crescimento === null
                        ? t('Primeiro mês')
                        : t(':n% em empresas novas', { n: n.crescimento > 0 ? `+${n.crescimento}` : n.crescimento })}
                />
                <CartaoNumero
                    rotulo={t('Receita cobrada')}
                    valor={kz(n.receita_total)}
                    sufixo="Kz"
                    icone="fa-sack-dollar"
                    tom="verde"
                    aspecto="claro"
                    nota={t(':n Kz este mês', { n: kz(n.receita_do_mes) })}
                />
                <CartaoNumero
                    rotulo={t('Por cobrar')}
                    valor={kz(n.receita_por_cobrar)}
                    sufixo="Kz"
                    icone="fa-hourglass-half"
                    tom={n.receita_por_cobrar > 0 ? 'ambar' : 'verde'}
                    aspecto="claro"
                    nota={t('Facturas emitidas e não pagas')}
                />
                <CartaoNumero
                    rotulo={t('Subscrições activas')}
                    valor={n.subscricoes_activas.toLocaleString('pt-PT')}
                    icone="fa-file-signature"
                    tom="indigo"
                    aspecto="claro"
                    nota={n.subscricoes_em_ensaio > 0
                        ? t(':n em ensaio', { n: n.subscricoes_em_ensaio })
                        : undefined}
                />
                <CartaoNumero
                    rotulo={t('Módulos activos')}
                    valor={n.modulos.toLocaleString('pt-PT')}
                    icone="fa-puzzle-piece"
                    tom="teal"
                    aspecto="claro"
                />
            </div>

            {/* OS DOIS GRÁFICOS */}
            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao
                    titulo={t('Empresas novas por mês')}
                    icone="fa-chart-column"
                    subtitulo={t('Os últimos seis meses')}
                    accoes={n.crescimento !== null && (
                        <Etiqueta cor={n.crescimento >= 0 ? 'bom' : 'perigo'}>
                            {n.crescimento >= 0 ? '+' : ''}{n.crescimento}%
                        </Etiqueta>
                    )}
                >
                    <GraficoDeBarras
                        titulo={t('Empresas novas por mês')}
                        altura={200}
                        dados={p.crescimento_das_empresas.etiquetas.map((rotulo, i) => ({
                            rotulo, valor: p.crescimento_das_empresas.valores[i] ?? 0,
                        }))}
                    />
                </Cartao>

                <Cartao titulo={t('Receita cobrada por mês')} icone="fa-sack-dollar" subtitulo={t('Só o que foi pago')}>
                    <GraficoDeBarras
                        titulo={t('Receita cobrada por mês')}
                        altura={200}
                        dados={p.receita_mensal.etiquetas.map((rotulo, i) => ({
                            rotulo, valor: p.receita_mensal.valores[i] ?? 0,
                        }))}
                    />
                </Cartao>
            </div>

            {/* A EXPIRAR — é o cartão que faz alguém pegar no telefone hoje. */}
            {p.a_expirar.length > 0 && (
                <section className={cls(CARTAO, 'overflow-hidden border-2 border-amber-300')}>
                    <header className="flex flex-wrap items-center justify-between gap-3 bg-amber-50 px-5 py-3.5">
                        <h3 className="flex items-center gap-2 text-base font-bold text-amber-900">
                            <i className="fas fa-hourglass-half" aria-hidden="true" />
                            {t('A expirar nos próximos 30 dias')}
                        </h3>
                        <span className="text-sm font-semibold text-amber-800">
                            {t(':n subscrição(ões)', { n: p.a_expirar.length })}
                        </span>
                    </header>

                    <ul className="divide-y divide-slate-100">
                        {p.a_expirar.map((s, i) => (
                            <li key={s.id} style={cascata(i)} className="entra flex flex-wrap items-center justify-between gap-3 px-5 py-2.5">
                                <span className="min-w-0">
                                    <span className="block truncate font-semibold text-slate-900">{s.empresa ?? '—'}</span>
                                    <span className="block text-xs text-slate-400">{s.plano ?? '—'} · {s.dia}</span>
                                </span>

                                <span className="flex flex-none items-center gap-3">
                                    <span className="font-semibold tabular-nums text-slate-700">{kz(s.valor)} Kz</span>

                                    {/* QUANTOS DIAS FALTAM decide a quem se liga
                                        primeiro, e a cor diz-no sem contas. */}
                                    <Etiqueta
                                        cor={(s.dias ?? 99) <= 7 ? 'perigo' : (s.dias ?? 99) <= 15 ? 'aviso' : 'neutra'}
                                        ponto
                                    >
                                        {s.dias === null
                                            ? '—'
                                            : s.dias <= 0
                                                ? t('hoje')
                                                : t(':n dia(s)', { n: s.dias })}
                                    </Etiqueta>
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Empresas por plano')} icone="fa-layer-group" subtitulo={t('Só subscrições activas')}>
                    {p.planos.length === 0 ? (
                        <SemNada icone="fa-layer-group" titulo={t('Nenhum plano configurado')} />
                    ) : (
                        <GraficoHorizontal
                            titulo={t('Empresas por plano')}
                            unidade={t('empresas')}
                            dados={p.planos.map((x) => ({ rotulo: x.nome, valor: x.empresas }))}
                        />
                    )}
                </Cartao>

                <Cartao
                    titulo={t('Módulos mais usados')}
                    icone="fa-puzzle-piece"
                    subtitulo={t('Os do núcleo estão em todas por omissão')}
                >
                    {p.modulos.length === 0 ? (
                        <SemNada icone="fa-puzzle-piece" titulo={t('Nenhum módulo registado')} />
                    ) : (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {p.modulos.map((m, i) => (
                                <li key={m.id} style={cascata(i)} className="entra flex items-center justify-between gap-3 py-2">
                                    <span className="flex min-w-0 items-center gap-2.5">
                                        <i className={cls(m.icone, m.nucleo ? 'text-indigo-500' : 'text-slate-400')} aria-hidden="true" />
                                        <span className="truncate text-slate-800">{m.nome}</span>
                                        {m.nucleo && (
                                            <span className="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">
                                                {t('núcleo')}
                                            </span>
                                        )}
                                    </span>
                                    <span className="shrink-0 font-bold tabular-nums text-slate-900">{m.empresas}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                {/* AS EMPRESAS RECENTES, com a porta para entrar em casa delas. */}
                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h3 className="flex items-center gap-2 text-base font-bold text-slate-900">
                                <i className="fas fa-building text-purple-600" aria-hidden="true" />
                                {t('Empresas recentes')}
                            </h3>
                            <p className="text-xs text-slate-400">
                                {t('+:n este mês', { n: n.empresas_novas_do_mes })}
                            </p>
                        </div>
                        <a href="/superadmin/tenants" className={cls('text-sm font-semibold text-purple-700 hover:text-purple-900', FOCO, RAIO)}>
                            {t('Ver todas')} <i className="fas fa-arrow-right ml-1 text-xs" aria-hidden="true" />
                        </a>
                    </header>

                    {p.empresas_recentes.length === 0 ? (
                        <SemNada icone="fa-building" titulo={t('Nenhuma empresa registada')} />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {p.empresas_recentes.map((e, i) => (
                                <li key={e.id} style={cascata(i)} className="entra flex flex-wrap items-center justify-between gap-3 px-5 py-2.5">
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold text-slate-900">{e.nome}</span>
                                        <span className="block truncate text-xs text-slate-400">
                                            {[e.plano, t(':n utilizador(es)', { n: e.utilizadores }), e.criada_em]
                                                .filter(Boolean).join(' · ')}
                                        </span>
                                    </span>

                                    <span className="flex flex-none items-center gap-2">
                                        <Etiqueta cor={e.activa ? 'bom' : 'neutra'} ponto>
                                            {e.activa ? t('Activa') : t('Inactiva')}
                                        </Etiqueta>

                                        {/* ENTRAR NA CASA: o acto com mais poder
                                            que há, e por isso pergunta-se. */}
                                        <button
                                            type="button"
                                            onClick={() => porAEntrar({ id: e.id, nome: e.nome })}
                                            title={t('Entrar nesta empresa')}
                                            aria-label={t('Entrar em :empresa', { empresa: e.nome })}
                                            className={cls(BOTAO_DE_ACCAO, 'border-purple-200 bg-purple-50 text-purple-700')}
                                        >
                                            <i className="fas fa-right-to-bracket" aria-hidden="true" />
                                        </button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <h3 className="flex items-center gap-2 text-base font-bold text-slate-900">
                            <i className="fas fa-cart-shopping text-blue-600" aria-hidden="true" />
                            {t('Pedidos recentes')}
                        </h3>
                        <a href="/superadmin/billing" className={cls('text-sm font-semibold text-blue-700 hover:text-blue-900', FOCO, RAIO)}>
                            {t('Ver todos')} <i className="fas fa-arrow-right ml-1 text-xs" aria-hidden="true" />
                        </a>
                    </header>

                    {p.pedidos_recentes.length === 0 ? (
                        <SemNada icone="fa-cart-shopping" titulo={t('Nenhum pedido registado')} />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {p.pedidos_recentes.map((o, i) => (
                                <li key={o.id} style={cascata(i)} className="entra flex flex-wrap items-center justify-between gap-3 px-5 py-2.5">
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold text-slate-900">{o.empresa ?? '—'}</span>
                                        <span className="block text-xs text-slate-400">{o.plano ?? '—'} · {o.dia}</span>
                                    </span>
                                    <span className="flex flex-none items-center gap-3">
                                        <span className="font-semibold tabular-nums text-slate-700">{kz(o.valor)} Kz</span>
                                        <EtiquetaDoEstado estado={o.estado} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Maiores subscrições')} icone="fa-crown" subtitulo={t('Por valor mensal')}>
                    {p.maiores_subscricoes.length === 0 ? (
                        <SemNada icone="fa-crown" titulo={t('Nenhuma subscrição activa')} />
                    ) : (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {p.maiores_subscricoes.map((s, i) => (
                                <li key={s.id} style={cascata(i)} className="entra flex items-center justify-between gap-3 py-2">
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold text-slate-900">{s.empresa ?? '—'}</span>
                                        <span className="block text-xs text-slate-400">{s.plano ?? '—'}</span>
                                    </span>
                                    <span className="shrink-0 font-bold tabular-nums text-slate-900">{kz(s.valor)} Kz</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>

                <Cartao titulo={t('Facturas recentes')} icone="fa-file-invoice" subtitulo={t('Da plataforma às empresas')}>
                    {p.facturas_recentes.length === 0 ? (
                        <SemNada icone="fa-file-invoice" titulo={t('Nenhuma factura emitida')} />
                    ) : (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {p.facturas_recentes.map((f, i) => (
                                <li key={f.id} style={cascata(i)} className="entra flex items-center justify-between gap-3 py-2">
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold text-slate-900">{f.empresa ?? '—'}</span>
                                        <span className="block font-mono text-xs text-slate-400">{f.numero ?? '—'} · {f.dia}</span>
                                    </span>
                                    <span className="flex shrink-0 items-center gap-2">
                                        <span className="font-bold tabular-nums text-slate-900">{kz(f.valor)}</span>
                                        <EtiquetaDoEstado estado={f.estado} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </div>

            {/* ENTRAR NA CASA DE UMA EMPRESA — o que vai acontecer, dito antes. */}
            <Modal
                aberto={aEntrar !== null}
                aoFechar={() => porAEntrar(null)}
                titulo={t('Entrar nesta empresa')}
                subtitulo={aEntrar?.nome}
                icone="fa-right-to-bracket"
                cor="roxo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAEntrar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="primaria"
                            tom="solida"
                            icone="fa-right-to-bracket"
                            aTrabalhar={entrar.isPending}
                            onClick={() => aEntrar && entrar.mutate(aEntrar.id)}
                        >
                            {t('Entrar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('A partir daqui, tudo o que fizer aparece como sendo DENTRO desta empresa — incluindo o que gravar, emitir ou apagar.')}
                </p>

                <p className={cls('mt-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                    <i className="fas fa-fingerprint mr-2" aria-hidden="true" />
                    {t('A entrada fica na trilha de auditoria com o seu nome, e cada acto seguinte leva a marca de quem abriu a porta.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

const ACCAO = cls(
    'inline-flex items-center gap-2 bg-white/20 px-4 py-2 text-sm font-semibold text-white',
    'transition-all duration-200 hover:bg-white/30 hover:-translate-y-0.5',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70',
    RAIO,
);

const BOTAO_DE_ACCAO = cls(
    'inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-xs font-semibold',
    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm',
    RAIO,
    FOCO,
);

/** O estado de um pedido ou de uma factura, com a mesma paleta em todo o painel. */
function EtiquetaDoEstado({ estado }: { estado: string }) {
    const mapa: Record<string, { cor: 'bom' | 'aviso' | 'perigo' | 'neutra' | 'primaria'; rotulo: string }> = {
        paid: { cor: 'bom', rotulo: t('Paga') },
        pending: { cor: 'aviso', rotulo: t('Pendente') },
        approved: { cor: 'bom', rotulo: t('Aprovado') },
        rejected: { cor: 'perigo', rotulo: t('Rejeitado') },
        cancelled: { cor: 'neutra', rotulo: t('Cancelado') },
        overdue: { cor: 'perigo', rotulo: t('Vencida') },
    };

    const e = mapa[estado] ?? { cor: 'neutra' as const, rotulo: estado };

    return <Etiqueta cor={e.cor} ponto>{e.rotulo}</Etiqueta>;
}

function ErroDaAccao({ erro }: { erro: unknown }) {
    if (!erro) return null;

    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
            {daApi?.message ?? t('A operação não foi concluída.')}
        </div>
    );
}
