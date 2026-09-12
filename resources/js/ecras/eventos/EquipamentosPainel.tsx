import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';

import { eventos } from '@/api/eventos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { RAIO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PAINEL DOS EQUIPAMENTOS.
 *
 * Este ecrã já existia, e ia buscar o Chart.js A UM CDN: numa instalação sem
 * internet — que é a versão on-premise — os dois gráficos ficavam quadrados
 * brancos sem aviso nenhum. Agora são componentes da casa, e não dependem de
 * nada de fora.
 *
 * A TAXA DE USO é o número que este painel existe para dar: quanto do parque
 * está parado. É o que diz se vale a pena comprar mais um ou se já há de sobra.
 */
export default function PainelDosEquipamentos() {
    const [dias, porDias] = useState(30);

    const painel = useQuery({
        queryKey: ['eventos', 'equipamentos', 'painel', dias],
        queryFn: () => eventos.equipamentos.painel(dias),
    });

    if (painel.isPending) return <Carregando linhas={8} />;
    if (painel.isError) return <AvisoDeErro erro={painel.error} />;

    const p = painel.data;
    const r = p.resumo;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Painel dos Equipamentos')}
                subtitulo={t('Quanto do parque está a trabalhar')}
                icone="fa-chart-pie"
                cor="roxo"
                accoes={
                    <a href="/events/equipment" className={ACCAO_DA_FAIXA}>
                        <i className="fas fa-boxes-stacked" aria-hidden="true" />
                        {t('Equipamentos')}
                    </a>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    {[7, 30, 90, 365].map((n) => (
                        <button
                            key={n}
                            type="button"
                            onClick={() => porDias(n)}
                            className={cls(
                                'px-3 py-1.5 text-sm font-semibold text-white transition', RAIO,
                                dias === n ? 'bg-white/40' : 'bg-white/20 hover:bg-white/30',
                            )}
                        >
                            {n === 365 ? t('1 ano') : t(':n dias', { n: String(n) })}
                        </button>
                    ))}
                    <EstadoNaFaixa icone="fa-gauge">
                        {t(':n% em serviço', { n: String(r.taxa_de_uso) })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            {p.avisos.length > 0 && (
                <ul className="space-y-2">
                    {p.avisos.slice(0, 6).map((a, i) => (
                        <li
                            key={`${a.equipamento_id}-${i}`}
                            style={cascata(i)}
                            className={cls(
                                'entra flex items-center gap-3 border px-4 py-2.5 text-sm font-medium', RAIO,
                                a.tipo === 'perigo'
                                    ? 'border-red-200 bg-red-50 text-red-800'
                                    : 'border-amber-200 bg-amber-50 text-amber-800',
                            )}
                        >
                            <i className={`fas ${a.icone}`} aria-hidden="true" />
                            {a.mensagem}
                        </li>
                    ))}
                </ul>
            )}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro" rotulo={t('No parque')} valor={numero(r.total)}
                    icone="fa-boxes-stacked" tom="roxo"
                    nota={t(':d disponíveis', { d: numero(r.disponivel) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Taxa de uso')} valor={String(r.taxa_de_uso)} sufixo="%"
                    icone="fa-gauge" tom={r.taxa_de_uso >= 70 ? 'ambar' : 'verde'}
                    nota={t('Em uso ou emprestado, sobre o parque todo')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Parado')} valor={numero(r.manutencao + r.avariado)}
                    icone="fa-screwdriver-wrench" tom={r.avariado > 0 ? 'vermelho' : 'indigo'}
                    nota={t(':m em manutenção · :a avariados', { m: numero(r.manutencao), a: numero(r.avariado) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Valor do parque')} valor={kz(r.valor)} sufixo="Kz"
                    icone="fa-sack-dollar" tom="verde"
                />
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao
                    titulo={t('Movimentos no período')}
                    subtitulo={t('Saídas, devoluções e manutenções, dia a dia')}
                    icone="fa-chart-column"
                >
                    <GraficoDeBarras
                        titulo={t('Movimentos por dia')}
                        dados={p.movimentos.etiquetas.map((e, i) => ({ rotulo: e, valor: p.movimentos.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Que categorias mais trabalham')} icone="fa-tags">
                    <GraficoHorizontal
                        titulo={t('Utilizações por categoria')}
                        unidade=""
                        vazio={t('Ainda não houve utilizações.')}
                        dados={p.por_categoria.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_categoria.valores[i] ?? 0 }))}
                    />
                </Cartao>
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao titulo={t('O material que mais sai')} icone="fa-fire" semPadding>
                    {p.mais_usados.length === 0 ? (
                        <div className="p-5">
                            <SemNada icone="fa-fire" titulo={t('Sem utilizações')}
                                frase={t('Cada devolução conta uma utilização — é isso que alimenta esta lista.')} />
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {p.mais_usados.map((e, i) => (
                                <li key={e.id} style={cascata(i)} className="entra flex items-center gap-3 px-5 py-2.5">
                                    <span className="w-6 flex-none text-center text-sm font-bold tabular-nums text-slate-400">
                                        {i + 1}
                                    </span>
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium text-slate-700">{e.nome}</span>
                                    <Etiqueta cor="neutra">{e.estado_rotulo}</Etiqueta>
                                    <span className="flex-none text-sm font-bold tabular-nums text-slate-900">
                                        {t(':n×', { n: String(e.utilizacoes) })}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>

                <Cartao titulo={t('Manutenções marcadas')} icone="fa-screwdriver-wrench" semPadding>
                    {p.manutencoes.length === 0 ? (
                        <div className="p-5">
                            <SemNada icone="fa-screwdriver-wrench" titulo={t('Nada marcado')}
                                frase={t('Marque a próxima manutenção na ficha do equipamento e ela aparece aqui.')} />
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {p.manutencoes.map((m, i) => (
                                <li key={m.id} style={cascata(i)} className="entra flex items-center gap-3 px-5 py-2.5">
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium text-slate-700">{m.nome}</span>
                                    <Etiqueta cor={m.atrasada ? 'perigo' : 'aviso'} icone="fa-calendar-day">
                                        {m.quando ?? '—'}
                                    </Etiqueta>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </div>

            <Cartao titulo={t('O que aconteceu ao material')} subtitulo={t('Os últimos vinte movimentos')} icone="fa-clock-rotate-left" semPadding>
                {p.actividade.length === 0 ? (
                    <div className="p-5">
                        <SemNada icone="fa-clock-rotate-left" titulo={t('Sem movimentos')}
                            frase={t('Emprestar, devolver e pôr em manutenção deixam rasto aqui.')} />
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {p.actividade.map((a, i) => (
                            <li key={a.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-2.5">
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium text-slate-700">
                                        {a.equipamento ?? '—'}
                                    </span>
                                    <span className="block truncate text-xs text-slate-500">
                                        {[a.cliente, a.quem, a.notas].filter(Boolean).join(' · ')}
                                    </span>
                                </span>
                                <Etiqueta cor="neutra">{a.accao}</Etiqueta>
                                <span className="flex-none text-xs tabular-nums text-slate-500">{a.quando}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </Cartao>

            <div className="flex justify-end">
                <a href="/events/equipment">
                    <Botao icone="fa-arrow-right">{t('Ir para os equipamentos')}</Botao>
                </a>
            </div>
        </div>
    );
}
