import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { restaurante } from '@/api/restaurant';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, RAIO, cls, dataHora, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * O STOCK E O DESPERDÍCIO DA COZINHA.
 *
 * DUAS COISAS COM O MESMO NOME, lado a lado de propósito:
 *
 *   · o DESPERDÍCIO DE ARMAZÉM — caixa de tomate estragada, leite fora de
 *     prazo. Sai do stock e tem custo;
 *   · o DESPERDÍCIO DE PRODUÇÃO — o prato que foi feito e voltou para trás.
 *     Nasce ao anular um artigo já produzido, na comanda.
 *
 * O primeiro é um erro de compra, o segundo é um erro de serviço. Somá-los
 * daria um número que não quer dizer nada — por isso são duas listas.
 */

const formularioVazio = () => ({ product_id: '', warehouse_id: '', quantity: '1', reason: '' });

export default function Stock() {
    const cache = useQueryClient();

    const [aba, porAba] = useState('existencias');
    const [procura, porProcura] = useState('');
    const [atrasada, porAtrasada] = useState('');
    const [armazem, porArmazem] = useState<number | ''>('');
    const [formulario, porFormulario] = useState<ReturnType<typeof formularioVazio> | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    useEffect(() => {
        const id = setTimeout(() => porAtrasada(procura), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const opcoes = useQuery({
        queryKey: ['restaurante', 'stock', 'opcoes'],
        queryFn: restaurante.stock.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['restaurante', 'stock', 'lista', atrasada, armazem],
        queryFn: () => restaurante.stock.lista({ procura: atrasada, armazem }),
        placeholderData: keepPreviousData,
    });

    const lancar = useMutation({
        mutationFn: () => restaurante.stock.desperdicio({
            ...formulario,
            product_id: Number(formulario!.product_id),
            warehouse_id: Number(formulario!.warehouse_id),
            quantity: Number(formulario!.quantity),
        }),
        onSuccess: (r) => {
            porFormulario(null); porErros({}); porErro(null); porRecado(r.message);
            void cache.invalidateQueries({ queryKey: ['restaurante', 'stock'] });
        },
        onError: (e) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const d = lista.data;
    const emFalta = d?.data.filter((s) => s.em_falta).length ?? 0;
    const custoDoDesperdicio = d?.desperdicios.reduce((s, w) => s + w.custo, 0) ?? 0;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Stock e Desperdícios')}
                subtitulo={t('O que há no armazém, e o que se perdeu pelo caminho')}
                icone="fa-boxes-stacked"
                cor="teal"
                accoes={
                    <>
                        {o.permissoes.pode_lancar && (
                            <button
                                type="button"
                                className={ACCAO_DA_FAIXA}
                                onClick={() => {
                                    porErros({});
                                    porFormulario({
                                        ...formularioVazio(),
                                        warehouse_id: o.armazens.find((a) => a.padrao)?.valor ?? o.armazens[0]?.valor ?? '',
                                    });
                                }}
                            >
                                <i className="fas fa-trash-can" aria-hidden="true" />
                                {t('Lançar desperdício')}
                            </button>
                        )}
                        <a href="/invoicing/stock" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-warehouse" aria-hidden="true" />
                            {t('Stock completo')}
                        </a>
                    </>
                }
            >
                {emFalta > 0 && (
                    <EstadoNaFaixa icone="fa-triangle-exclamation">
                        {t(':n artigos abaixo do mínimo', { n: String(emFalta) })}
                    </EstadoNaFaixa>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <div className="grid gap-4 sm:grid-cols-3">
                <CartaoNumero aspecto="claro" rotulo={t('Artigos em stock')} valor={d?.data.length ?? 0} icone="fa-boxes-stacked" tom="teal" />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Abaixo do mínimo')} valor={emFalta}
                    icone="fa-triangle-exclamation" tom={emFalta > 0 ? 'vermelho' : 'verde'}
                    nota={t('É por aqui que a lista começa')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Custo dos desperdícios')} valor={kz(custoDoDesperdicio)} sufixo="Kz"
                    icone="fa-trash-can" tom="ambar" nota={t('Últimos 50 lançamentos de armazém')}
                />
            </div>

            <Separadores
                activa={aba}
                aoMudar={porAba}
                abas={[
                    { chave: 'existencias', rotulo: t('Existências'), icone: 'fa-boxes-stacked' },
                    { chave: 'armazem', rotulo: t('Desperdício de armazém'), icone: 'fa-trash-can' },
                    { chave: 'producao', rotulo: t('Desperdício de produção'), icone: 'fa-utensils' },
                ]}
            />

            <PainelDoSeparador chave="existencias" activa={aba}>
                <div className="space-y-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <Campo etiqueta={t('Procurar')} className="min-w-[12rem] flex-1">
                            <div className="relative">
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input
                                    type="search"
                                    value={procura}
                                    onChange={(e) => porProcura(e.target.value)}
                                    placeholder={t('Nome do artigo…')}
                                    className={cls(entrada, 'pl-9')}
                                />
                            </div>
                        </Campo>

                        <Campo etiqueta={t('Armazém')} className="w-52">
                            <select value={armazem} onChange={(e) => porArmazem(e.target.value ? Number(e.target.value) : '')} className={entrada}>
                                <option value="">{t('Todos')}</option>
                                {o.armazens.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                            </select>
                        </Campo>
                    </div>

                    <div className={cls(CARTAO, 'overflow-hidden')}>
                        {lista.isPending ? (
                            <div className="p-5"><Carregando linhas={6} /></div>
                        ) : (d?.data.length ?? 0) === 0 ? (
                            <SemNada icone="fa-boxes-stacked" frase={t('Nenhum artigo com stock neste filtro.')} />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[36rem] text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 bg-slate-50/60 text-left">
                                            <th scope="col" className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Artigo')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Armazém')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Disponível')}</th>
                                            <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Mínimo')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {d?.data.map((s, i) => (
                                            <tr key={s.id} style={cascata(i)} className={cls('entra', s.em_falta && 'bg-red-50/40')}>
                                                <td className="px-4 py-2.5">
                                                    <span className="font-medium text-slate-800">{s.artigo}</span>
                                                    {s.em_falta && (
                                                        <Etiqueta cor="perigo" icone="fa-triangle-exclamation">{t('repor')}</Etiqueta>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2.5 text-slate-600">{s.armazem ?? '—'}</td>
                                                <td className={cls('px-4 py-2.5 text-right font-bold tabular-nums', s.em_falta ? 'text-red-600' : 'text-slate-900')}>
                                                    {kz(s.disponivel, 2)} <span className="text-xs font-normal text-slate-400">{s.unidade}</span>
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums text-slate-500">{kz(s.minimo, 2)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </PainelDoSeparador>

            <PainelDoSeparador chave="armazem" activa={aba}>
                <Cartao
                    titulo={t('Desperdício de armazém')}
                    subtitulo={t('O que se estragou, partiu ou passou do prazo — sai do stock e tem custo')}
                    icone="fa-trash-can"
                    semPadding
                >
                    {(d?.desperdicios.length ?? 0) === 0 ? (
                        <SemNada icone="fa-trash-can" frase={t('Nenhum desperdício lançado.')} />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {d?.desperdicios.map((w, i) => (
                                <li key={w.id} style={cascata(i)} className="entra flex items-center justify-between gap-3 px-5 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-800">{w.artigo}</p>
                                        <p className="truncate text-xs text-slate-500">
                                            {dataHora(w.quando)}
                                            {w.motivo && ` · ${w.motivo}`}
                                        </p>
                                    </div>
                                    <div className="flex-none text-right">
                                        <p className="text-sm font-bold tabular-nums text-slate-900">
                                            {kz(w.quantidade, 2)} <span className="text-xs font-normal text-slate-400">{w.unidade}</span>
                                        </p>
                                        {w.custo > 0 && <p className="text-xs tabular-nums text-amber-600">{kz(w.custo)} Kz</p>}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </PainelDoSeparador>

            <PainelDoSeparador chave="producao" activa={aba}>
                <Cartao
                    titulo={t('Desperdício de produção')}
                    subtitulo={t('Pratos feitos que voltaram para trás — nascem ao anular um artigo na comanda')}
                    icone="fa-utensils"
                    semPadding
                >
                    {(d?.producao.length ?? 0) === 0 ? (
                        <SemNada icone="fa-utensils" frase={t('Nenhum prato anulado depois de produzido.')} />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {d?.producao.map((w, i) => (
                                <li key={w.id} style={cascata(i)} className="entra flex items-center justify-between gap-3 px-5 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-800">{w.artigo}</p>
                                        <p className="truncate text-xs text-slate-500">
                                            {w.comanda && `${w.comanda} · `}
                                            {dataHora(w.quando)}
                                            {w.quem && ` · ${w.quem}`}
                                        </p>
                                        <p className="truncate text-xs italic text-amber-700">« {w.motivo} »</p>
                                    </div>
                                    <p className="flex-none text-sm font-bold tabular-nums text-slate-900">
                                        {kz(w.quantidade, 2)} <span className="text-xs font-normal text-slate-400">{w.unidade}</span>
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </PainelDoSeparador>

            <Modal
                aberto={formulario !== null}
                aoFechar={() => porFormulario(null)}
                titulo={t('Lançar desperdício')}
                subtitulo={t('Sai do stock e fica com motivo — é o que separa uma perda de um roubo')}
                icone="fa-trash-can"
                cor="aviso"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porFormulario(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="aviso" tom="solida" icone="fa-check" aTrabalhar={lancar.isPending} onClick={() => lancar.mutate()}>
                            {t('Lançar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Artigo')} obrigatorio erro={erros.product_id}>
                            <select
                                value={formulario.product_id}
                                onChange={(e) => porFormulario({ ...formulario, product_id: e.target.value })}
                                className={entrada}
                            >
                                <option value="">{t('Escolher…')}</option>
                                {o.artigos.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                            </select>
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Armazém')} obrigatorio erro={erros.warehouse_id}>
                                <select
                                    value={formulario.warehouse_id}
                                    onChange={(e) => porFormulario({ ...formulario, warehouse_id: e.target.value })}
                                    className={entrada}
                                >
                                    {o.armazens.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                                </select>
                            </Campo>

                            <Campo
                                etiqueta={t('Quantidade')}
                                obrigatorio
                                erro={erros.quantity}
                                ajuda={t('Mínimo 0,01 — a coluna guarda dois decimais.')}
                            >
                                <input
                                    type="number" step="0.01" min="0.01"
                                    value={formulario.quantity}
                                    onChange={(e) => porFormulario({ ...formulario, quantity: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('Motivo')} obrigatorio erro={erros.reason}>
                            <textarea
                                value={formulario.reason}
                                onChange={(e) => porFormulario({ ...formulario, reason: e.target.value })}
                                rows={2}
                                className={cls(entrada, 'h-auto py-2')}
                                placeholder={t('Fora de prazo, partiu-se, estragou-se…')}
                            />
                        </Campo>
                    </div>
                )}
            </Modal>
        </div>
    );
}
