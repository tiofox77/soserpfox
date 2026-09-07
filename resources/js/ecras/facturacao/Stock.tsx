import { useEffect, useState, type CSSProperties } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { stock, type ArtigoParaLote, type ItemDoLote, type LinhaDeStock, type OpcoesDoStock } from '@/api/stock';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { CARTAO, CORES, FOCO, RAIO, cls, kz, type Cor } from '@/ui/tokens';
import { t, tPartes } from '@/i18n';

/**
 * A GESTÃO DE STOCK — o que há em cada armazém, e as três formas de lhe
 * mexer à mão: ajustar uma linha, transferir entre armazéns, e a
 * movimentação em lote (entradas e saídas com referência MOV/AAAA/NNNNNN).
 *
 * Os cartões e a lista saem da mesma consulta filtrada. As regras do stock
 * são do servidor (`MovimentacaoDeStock`, os ganchos do `StockMovement`):
 * este ecrã nunca soma nem subtrai — pede.
 *
 * O ASPECTO É O DE SEMPRE. Este era o ecrã com mais cartões de topo, e os
 * quatro tinham gradiente: azul para o que há, verde para as unidades, roxo
 * para o dinheiro e vermelho para o que falta. Ao passar para React tinham
 * ficado quatro caixas brancas com números pretos — a mesma informação, e
 * ninguém a lia. Voltam pelo `CartaoNumero`, com a tabela, o estado vazio
 * desenhado e a cascata das linhas por cima.
 */

/** O atraso da linha `i` na entrada em cascata (ver `.entra` no layout). */
const cascata = (i: number) => ({ '--i': i }) as CSSProperties;

/** O quadrado de uma acção de linha: fundo suave da cor do que ela faz. */
const accao = (cor: Cor) =>
    cls('grid h-9 w-9 place-items-center rounded-lg transition-all duration-200 hover:scale-110', CORES[cor].suave, FOCO);

export default function Stock() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState<{ procura: string; armazem: string; baixo: boolean; conservacao: string; page: number }>({ procura: '', armazem: '', baixo: false, conservacao: '', page: 1 });
    const [aAjustar, porAAjustar] = useState<LinhaDeStock | null>(null);
    const [aTransferir, porATransferir] = useState<LinhaDeStock | null>(null);
    const [movimentosDe, porMovimentosDe] = useState<LinhaDeStock | null>(null);
    const [lote, porLote] = useState(false);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({ queryKey: ['stock', 'opcoes'], queryFn: stock.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['stock', filtros], queryFn: () => stock.lista(filtros), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['stock'] }); };

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o stock')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;
    /* Uma lista vazia por causa de um filtro não é a mesma coisa que um
       armazém vazio, e a frase do estado vazio muda com isso. */
    const filtrado = filtros.procura !== '' || filtros.armazem !== '' || filtros.baixo || filtros.conservacao !== '';

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            {/* Os quatro do topo, com a cor a dizer o que o número quer dizer:
                o que há, o que pesa, o que vale, e o que falta. */}
            {resumo && (
                <div className={cls('grid grid-cols-2 gap-3 sm:grid-cols-4', lista.isFetching && 'opacity-70')}>
                    <CartaoNumero rotulo={t('Artigos')} valor={resumo.artigos.toLocaleString('pt-PT')} icone="fa-box" tom="azul" />
                    <CartaoNumero rotulo={t('Unidades')} valor={resumo.quantidade.toLocaleString('pt-PT')} icone="fa-cubes" tom="verde" />
                    <CartaoNumero rotulo={t('Valor ao custo')} valor={kz(resumo.valor)} sufixo="Kz" icone="fa-money-bill-wave" tom="roxo" />
                    {/* Vermelho só quando há mesmo alguma coisa em falta: um
                        zero em vermelho é um alarme que ninguém precisa de
                        ouvir, e o ícone diz o mesmo a quem não vê a cor. */}
                    <CartaoNumero
                        rotulo={t('Abaixo do mínimo')}
                        valor={resumo.baixo.toLocaleString('pt-PT')}
                        icone={resumo.baixo > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check'}
                        tom={resumo.baixo > 0 ? 'vermelho' : 'cinza'}
                    />
                </div>
            )}

            <Cartao
                titulo={t('Gestão de Stock')}
                icone="fa-boxes"
                accoes={o.permissoes.pode_editar && <Botao cor="primaria" tom="solida" icone="fa-truck-ramp-box" onClick={() => porLote(true)}>{t('Movimentação em lote')}</Botao>}
            >
                <div className="flex flex-wrap items-end gap-3">
                    <label className="min-w-[16rem] flex-1 text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span>
                        <input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder={t('Nome ou código do artigo')} className={entrada} />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Armazém')}</span>
                        <select value={filtros.armazem} onChange={(e) => porFiltros((f) => ({ ...f, armazem: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </label>
                    {o.mostra_conservacao && (
                        <label className="text-sm">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Conservação')}</span>
                            <select value={filtros.conservacao} onChange={(e) => porFiltros((f) => ({ ...f, conservacao: e.target.value, page: 1 }))} className={entrada}>
                                <option value="">{t('Todas')}</option>
                                {o.conservacao.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                            </select>
                        </label>
                    )}
                    <label className="flex items-center gap-2 pb-2 text-sm text-slate-700">
                        <input type="checkbox" checked={filtros.baixo} onChange={(e) => porFiltros((f) => ({ ...f, baixo: e.target.checked, page: 1 }))} className="h-4 w-4 rounded border-slate-300" />
                        {t('Só abaixo do mínimo')}
                    </label>
                </div>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            {/* O cabeçalho tem fundo e ícones, como sempre teve:
                                numa tabela de oito colunas é o que dá a ler o
                                título de relance em vez de o soletrar. */}
                            <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">
                                <th className="px-4 py-3 font-bold"><i className="fas fa-box mr-1.5 text-indigo-500" aria-hidden="true" />{t('Artigo')}</th>
                                <th className="px-4 py-3 font-bold"><i className="fas fa-warehouse mr-1.5 text-blue-500" aria-hidden="true" />{t('Armazém')}</th>
                                {o.mostra_conservacao && <th className="px-4 py-3 font-bold"><i className="fas fa-temperature-half mr-1.5 text-sky-500" aria-hidden="true" />{t('Conservação')}</th>}
                                <th className="px-4 py-3 text-right font-bold"><i className="fas fa-cubes mr-1.5 text-slate-400" aria-hidden="true" />{t('Quantidade')}</th>
                                <th className="px-4 py-3 text-right font-bold">{t('Mínimo')}</th>
                                <th className="px-4 py-3 text-right font-bold">{t('Custo')}</th>
                                <th className="px-4 py-3 text-right font-bold">{t('Valor')}</th>
                                <th className="w-36 px-4 py-3 text-right font-bold">{t('Acções')}</th>
                            </tr>
                        </thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={o.mostra_conservacao ? 8 : 7} className="px-6 py-16">
                                        {lista.isPending ? (
                                            <p className="text-center text-slate-400">{t('A carregar…')}</p>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center text-center">
                                                <div className="mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                                    <i className="fas fa-boxes text-4xl text-slate-300" aria-hidden="true" />
                                                </div>
                                                <p className="text-lg font-semibold text-slate-500">{t('Nenhum stock encontrado')}</p>
                                                {/* Duas frases diferentes, como no ecrã de sempre:
                                                    quem filtrou tem de saber que foi o filtro, e
                                                    quem não filtrou tem de saber o que fazer. O
                                                    botão não se repete aqui — é o mesmo que está
                                                    logo acima, e dois botões iguais no mesmo ecrã
                                                    fazem hesitar em vez de ajudar. */}
                                                <p className="mt-2 max-w-md text-sm text-slate-400">
                                                    {filtrado ? t('Sem stock com estes filtros.') : t('Adicione uma entrada de stock para um produto novo.')}
                                                </p>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            )}
                            {linhas.map((l, i) => (
                                <tr
                                    key={l.id}
                                    style={cascata(i)}
                                    className={cls('entra transition-all duration-200 hover:bg-indigo-50/60', l.baixo && 'bg-amber-50/40')}
                                >
                                    <td className="px-4 py-2">
                                        <div className="flex items-center gap-3">
                                            {/* O quadrado com o ícone: era a imagem do artigo no
                                                ecrã de sempre, e sem ela a coluna do nome perdia
                                                a âncora que a fazia encontrar de relance. */}
                                            <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md">
                                                <i className="fas fa-box" aria-hidden="true" />
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block font-semibold text-slate-900">{l.artigo}{l.conteudo && <span className="ml-1 text-xs font-normal text-slate-500">· {l.conteudo}</span>}</span>
                                                {l.codigo && <span className="block font-mono text-xs text-slate-400"><i className="fas fa-barcode mr-1" aria-hidden="true" />{l.codigo}</span>}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-2">{l.armazem ? <Etiqueta cor="primaria" icone="fa-warehouse">{l.armazem}</Etiqueta> : <span className="text-slate-300">—</span>}</td>
                                    {o.mostra_conservacao && (
                                        <td className="px-4 py-2">
                                            {l.conservacao_rotulo ? <Etiqueta icone="fa-temperature-half">{l.conservacao_rotulo}</Etiqueta> : <span className="text-slate-300"><i className="fas fa-minus-circle" aria-hidden="true" /></span>}
                                        </td>
                                    )}
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        <span className={cls('text-base font-bold', l.baixo ? 'text-red-600' : 'text-slate-900')}>{l.quantidade.toLocaleString('pt-PT')}</span>
                                        {l.unidade && <span className="ml-1 text-xs text-slate-400">{l.unidade}</span>}
                                        {l.baixo && <span className="ml-2"><Etiqueta cor="aviso" icone="fa-triangle-exclamation">{t('baixo')}</Etiqueta></span>}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums text-slate-500">{l.minimo.toLocaleString('pt-PT')}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{kz(l.custo)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums font-semibold">{kz(l.valor)}</td>
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1.5">
                                            <button type="button" onClick={() => porMovimentosDe(l)} title={t('Movimentos')} aria-label={t('Movimentos de :artigo', { artigo: l.artigo ?? '' })} className={accao('neutra')}><i className="fas fa-clock-rotate-left" aria-hidden="true" /></button>
                                            {o.permissoes.pode_editar && <button type="button" onClick={() => porAAjustar(l)} title={t('Ajustar')} aria-label={t('Ajustar :artigo', { artigo: l.artigo ?? '' })} className={accao('aviso')}><i className="fas fa-sliders" aria-hidden="true" /></button>}
                                            {o.permissoes.pode_transferir && o.armazens.length > 1 && <button type="button" onClick={() => porATransferir(l)} title={t('Transferir')} aria-label={t('Transferir :artigo', { artigo: l.artigo ?? '' })} className={accao('bom')}><i className="fas fa-right-left" aria-hidden="true" /></button>}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                        <span>{t('Página :pagina de :ultima · :linhas linhas', { pagina: contas.current_page, ultima: contas.last_page, linhas: contas.total })}</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>{t('Anterior')}</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>{t('Seguinte')}</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {aAjustar && <Ajustar l={aAjustar} aoFechar={() => porAAjustar(null)} aoFeito={(m) => { porAAjustar(null); feito(m); }} />}
            {aTransferir && <Transferir l={aTransferir} o={o} aoFechar={() => porATransferir(null)} aoFeito={(m) => { porATransferir(null); feito(m); }} />}
            {movimentosDe && <Movimentos l={movimentosDe} aoFechar={() => porMovimentosDe(null)} />}
            {lote && <Lote o={o} armazemInicial={filtros.armazem || (o.armazem_padrao ? String(o.armazem_padrao) : '')} aoFechar={() => porLote(false)} aoFeito={feito} />}
        </div>
    );
}

/* ─── Ajustar uma linha ─────────────────────────────────────────────── */

function Ajustar({ l, aoFechar, aoFeito }: { l: LinhaDeStock; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [nova, porNova] = useState(String(l.quantidade));
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});

    const gravar = useMutation({
        mutationFn: () => stock.ajustar({ stock_id: l.id, nova_quantidade: Number(nova) || 0, notas: notas || null }),
        onSuccess: (r) => aoFeito(r.message),
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Ajustar :artigo', { artigo: l.artigo ?? '' })} subtitulo={l.armazem ?? undefined} icone="fa-sliders" cor="aviso" rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-sliders" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Ajustar')}</Botao></>}>
            <AvisoDeErro erro={gravar.error} />
            <p className="mb-4 text-sm text-slate-600">{tPartes('No armazém :armazem há :quantidade. O ajuste fica registado como movimento.', { armazem: <strong>{l.armazem}</strong>, quantidade: <strong>{l.quantidade.toLocaleString('pt-PT')}</strong> })}</p>
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Quantidade certa')} erro={erros.nova_quantidade} obrigatorio>
                    <input type="number" min="0" step="0.01" value={nova} onChange={(e) => porNova(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Motivo')} erro={erros.notas}>
                    <input value={notas} onChange={(e) => porNotas(e.target.value)} placeholder={t('Contagem física, avaria…')} className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}

/* ─── Transferir entre armazéns ─────────────────────────────────────── */

function Transferir({ l, o, aoFechar, aoFeito }: { l: LinhaDeStock; o: OpcoesDoStock; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [para, porPara] = useState('');
    const [qtd, porQtd] = useState('');
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});

    const gravar = useMutation({
        mutationFn: () => stock.transferir({ stock_id: l.id, para_armazem_id: Number(para) || 0, quantidade: Number(qtd) || 0, notas: notas || null }),
        onSuccess: (r) => aoFeito(r.message),
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Transferir :artigo', { artigo: l.artigo ?? '' })} subtitulo={l.armazem ?? undefined} icone="fa-right-left" cor="bom" rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-right-left" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Transferir')}</Botao></>}>
            <AvisoDeErro erro={gravar.error} />
            <p className="mb-4 text-sm text-slate-600">{tPartes('De :armazem, onde há :disponivel disponível.', { armazem: <strong>{l.armazem}</strong>, disponivel: <strong>{l.disponivel.toLocaleString('pt-PT')}</strong> })}</p>
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Para o armazém')} erro={erros.para_armazem_id} obrigatorio>
                    <select value={para} onChange={(e) => porPara(e.target.value)} className={entrada}>
                        <option value="">{t('Escolher…')}</option>
                        {o.armazens.filter((a) => a.id !== l.warehouse_id).map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Quantidade')} erro={erros.quantidade} obrigatorio>
                    <input type="number" min="0.01" step="0.01" max={l.disponivel} value={qtd} onChange={(e) => porQtd(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Observações')} erro={erros.notas} className="sm:col-span-2">
                    <input value={notas} onChange={(e) => porNotas(e.target.value)} className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}

/* ─── Os movimentos de um artigo ────────────────────────────────────── */

function Movimentos({ l, aoFechar }: { l: LinhaDeStock; aoFechar: () => void }) {
    const q = useQuery({ queryKey: ['stock', 'movimentos', l.product_id], queryFn: () => stock.movimentos(l.product_id) });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Movimentos de :artigo', { artigo: l.artigo ?? '' })} icone="fa-clock-rotate-left" largura="lg" rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}>
            {q.isPending ? <Carregando linhas={5} /> : q.isError ? <AvisoDeErro erro={q.error} /> : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">
                            <th className="px-3 py-2 font-bold">{t('Quando')}</th>
                            <th className="px-3 py-2 font-bold">{t('Tipo')}</th>
                            <th className="px-3 py-2 font-bold">{t('Armazém')}</th>
                            <th className="px-3 py-2 text-right font-bold">{t('Qtd.')}</th>
                            <th className="px-3 py-2 text-right font-bold">{t('Saldo')}</th>
                            <th className="px-3 py-2 font-bold">{t('Notas')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {q.data.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-3 py-10">
                                    <div className="flex flex-col items-center justify-center text-center">
                                        <div className="mb-3 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                            <i className="fas fa-clock-rotate-left text-3xl text-slate-300" aria-hidden="true" />
                                        </div>
                                        <p className="text-sm font-semibold text-slate-500">{t('Sem movimentos.')}</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {q.data.data.map((m, i) => (
                            <tr key={m.id} style={cascata(i)} className="entra transition-all duration-200 hover:bg-indigo-50/60">
                                <td className="px-3 py-2 tabular-nums text-slate-600">{m.quando}</td>
                                <td className="px-3 py-2"><Etiqueta cor={m.tipo === 'in' ? 'bom' : m.tipo === 'out' ? 'perigo' : 'neutra'} icone={m.tipo === 'in' ? 'fa-arrow-down' : m.tipo === 'out' ? 'fa-arrow-up' : 'fa-sliders'}>{m.tipo_rotulo}</Etiqueta></td>
                                <td className="px-3 py-2">{m.armazem}</td>
                                <td className="px-3 py-2 text-right tabular-nums">{m.quantidade.toLocaleString('pt-PT')}</td>
                                <td className="px-3 py-2 text-right tabular-nums text-slate-500">{m.saldo_depois?.toLocaleString('pt-PT') ?? ''}</td>
                                <td className="px-3 py-2 text-xs text-slate-500">{m.lote && <span className="mr-1 font-mono">{m.lote}</span>}{m.notas}{m.quem && <span className="ml-1 text-slate-400">· {m.quem}</span>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}

/* ─── A movimentação em lote ────────────────────────────────────────── */

function Lote({ o, armazemInicial, aoFechar, aoFeito }: { o: OpcoesDoStock; armazemInicial: string; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [armazem, porArmazem] = useState(armazemInicial);
    const [procura, porProcura] = useState('');
    const [sugestoes, porSugestoes] = useState<ArtigoParaLote[]>([]);
    const [itens, porItens] = useState<ItemDoLote[]>([]);
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [resultado, porResultado] = useState<{ referencia: string; ok: number; erros: string[]; pdf: string } | null>(null);

    /* A procura pede ao servidor, com pausa. */
    useEffect(() => {
        const termo = procura.trim();
        if (termo === '') { porSugestoes([]); return; }
        let cancelado = false;
        const h = setTimeout(() => {
            stock.artigos(termo, armazem || undefined)
                .then((r) => { if (!cancelado) porSugestoes(r.data.filter((a) => !itens.some((i) => i.product_id === a.id))); })
                .catch(() => { if (!cancelado) porSugestoes([]); });
        }, 300);
        return () => { cancelado = true; clearTimeout(h); };
    }, [procura, armazem, itens]);

    const juntar = (a: ArtigoParaLote) => {
        porItens((ls) => [...ls, { product_id: a.id, product_name: a.name, code: a.code, unit: a.unit, op: 'add', quantity: 1, unit_cost: a.cost || '', actual: a.actual }]);
        porProcura('');
        porSugestoes([]);
    };

    const gravar = useMutation({
        mutationFn: () => stock.entrada({
            armazem_id: Number(armazem) || 0,
            itens: itens.map((i) => ({ product_id: i.product_id, product_name: i.product_name, op: i.op, quantity: Number(i.quantity) || 0, unit_cost: Number(i.unit_cost) || 0 })),
            notas: notas || null,
        }),
        onSuccess: (r) => { porResultado(r); porErros({}); aoFeito(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (resultado) {
        return (
            <Modal aberto aoFechar={aoFechar} titulo={t('Movimentação registada')} icone="fa-circle-check" cor="bom" rodape={<><Botao onClick={aoFechar}>{t('Fechar')}</Botao><Botao icone="fa-plus" onClick={() => { porResultado(null); porItens([]); porNotas(''); }}>{t('Outra')}</Botao><Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(resultado.pdf, '_blank')}>{t('PDF do lote')}</Botao></>}>
                <div className={cls(CARTAO, 'animate-scale-in p-6 text-center')}>
                    {/* O visto num círculo verde, como no ecrã de sempre: é o
                        sinal de que acabou, antes de se ler a referência. */}
                    <div className="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-emerald-100">
                        <i className="fas fa-check text-2xl text-emerald-600" aria-hidden="true" />
                    </div>
                    <p className="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1 font-mono text-lg font-bold text-slate-900" data-referencia>
                        <i className="fas fa-hashtag text-xs text-slate-400" aria-hidden="true" />{resultado.referencia}
                    </p>
                    <p className="mt-2 text-sm text-slate-500">{t(':quantos produto(s) actualizado(s).', { quantos: resultado.ok })}</p>
                    {resultado.erros.length > 0 && <ul className={cls('mt-3 list-inside list-disc border border-amber-200 bg-amber-50 p-3 text-left text-sm text-amber-800', RAIO)}>{resultado.erros.map((e, i) => <li key={i}>{e}</li>)}</ul>}
                </div>
            </Modal>
        );
    }

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Movimentação em lote')} icone="fa-truck-ramp-box" cor="bom" largura="lg" rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-truck-ramp-box" aTrabalhar={gravar.isPending} disabled={itens.length === 0} onClick={() => gravar.mutate()}>{t('Registar')}</Botao></>}>
            <AvisoDeErro erro={gravar.error} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Armazém')} erro={erros.armazem_id} obrigatorio>
                    <select value={armazem} onChange={(e) => porArmazem(e.target.value)} className={entrada}>
                        <option value="">{t('Escolher…')}</option>
                        {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Observações')} erro={erros.notas}>
                    <input value={notas} onChange={(e) => porNotas(e.target.value)} placeholder={t('Contentor, guia do fornecedor…')} className={entrada} />
                </Campo>
                <div className="relative sm:col-span-2">
                    <Campo etiqueta={t('Juntar artigo')}>
                        <input value={procura} onChange={(e) => porProcura(e.target.value)} placeholder={t('Nome, código ou código de barras')} className={entrada} />
                    </Campo>
                    {sugestoes.length > 0 && (
                        <ul className={cls('absolute z-10 mt-1 max-h-60 w-full overflow-auto border border-slate-200 bg-white shadow-lg', RAIO)} role="listbox">
                            {sugestoes.map((a, i) => (
                                <li key={a.id} style={cascata(i)} className="entra border-b border-slate-100 last:border-0">
                                    <button type="button" onClick={() => juntar(a)} className={cls('flex w-full items-center justify-between px-3 py-2 text-left text-sm transition-all duration-200 hover:bg-indigo-50/60', FOCO)}>
                                        <span>{a.name}{a.net_content && <span className="ml-1 text-xs text-slate-400">{a.net_content}</span>}{a.code && <span className="ml-2 font-mono text-xs text-slate-400">{a.code}</span>}</span>
                                        <span className="text-xs text-slate-500">{t('tem :quanto', { quanto: a.actual.toLocaleString('pt-PT') })}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            <div className={cls('mt-4 overflow-hidden border border-slate-200', RAIO)}>
                {itens.length === 0 ? (
                    /* A caixa a tracejado do ecrã de sempre: diz que falta
                       alguma coisa sem parecer um erro. */
                    <div className={cls('m-2 border-2 border-dashed border-slate-200 p-6 text-center text-slate-400', RAIO)}>
                        <i className="fas fa-inbox mb-2 text-3xl" aria-hidden="true" />
                        <p className="text-sm">{t('Sem artigos. Procure e junte.')}</p>
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-3 py-2 font-bold">{t('Artigo')}</th><th className="px-3 py-2 font-bold">{t('Tem')}</th><th className="px-3 py-2 font-bold">{t('Op.')}</th><th className="px-3 py-2 text-right font-bold">{t('Qtd.')}</th><th className="px-3 py-2 text-right font-bold">{t('Custo')}</th><th className="w-10 px-3 py-2"></th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {itens.map((i, k) => (
                                <tr key={i.product_id} style={cascata(k)} className="entra transition-all duration-200 hover:bg-indigo-50/60">
                                    <td className="px-3 py-2">{i.product_name}{i.code && <span className="ml-2 font-mono text-xs text-slate-400">{i.code}</span>}</td>
                                    <td className="px-3 py-2 tabular-nums text-slate-500">{i.actual.toLocaleString('pt-PT')}</td>
                                    <td className="px-3 py-2">
                                        <select value={i.op} onChange={(e) => porItens((ls) => ls.map((x, j) => (j === k ? { ...x, op: e.target.value as 'add' | 'sub' } : x)))} aria-label={t('Operação de :artigo', { artigo: i.product_name })} className={cls(entrada, 'h-8 py-0 text-xs')}>
                                            <option value="add">{t('+ entrada')}</option>
                                            <option value="sub">{t('− saída')}</option>
                                        </select>
                                    </td>
                                    <td className="px-3 py-2"><input type="number" min="0.01" step="0.01" value={i.quantity} onChange={(e) => porItens((ls) => ls.map((x, j) => (j === k ? { ...x, quantity: e.target.value } : x)))} aria-label={t('Quantidade de :artigo', { artigo: i.product_name })} className={cls(entrada, 'h-8 py-0 text-right tabular-nums')} /></td>
                                    <td className="px-3 py-2"><input type="number" min="0" step="0.01" value={i.unit_cost} onChange={(e) => porItens((ls) => ls.map((x, j) => (j === k ? { ...x, unit_cost: e.target.value } : x)))} aria-label={t('Custo de :artigo', { artigo: i.product_name })} className={cls(entrada, 'h-8 py-0 text-right tabular-nums')} /></td>
                                    <td className="px-3 py-2 text-right"><button type="button" onClick={() => porItens((ls) => ls.filter((_, j) => j !== k))} aria-label={t('Tirar :artigo', { artigo: i.product_name })} className={accao('perigo')}><i className="fas fa-trash" aria-hidden="true" /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
                {erros.itens?.[0] && <p role="alert" className="border-t border-red-100 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{erros.itens[0]}</p>}
            </div>
        </Modal>
    );
}
