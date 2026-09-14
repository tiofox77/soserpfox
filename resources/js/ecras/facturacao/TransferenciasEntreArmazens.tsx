import { useState, type CSSProperties } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { transferencias, type ItemDaTransferencia, type LoteDoHistorico, type OpcoesDasTransferencias, type Resumo } from '@/api/transferencias';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { CORES, FOCO, RAIO, cls, dataHora, type Cor } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';
import { Carrinho, Comprovativo } from '@/ecras/facturacao/transferencias/Carrinho';

/**
 * TRANSFERÊNCIAS ENTRE ARMAZÉNS E AJUSTES EM LOTE — e o histórico dos dois.
 *
 * Cada transferência ou ajuste é um lote com referência (MOV/AAAA/NNNNNN) e
 * comprovativo. O que acontece ao stock — os quatro saldos, as duas pernas,
 * os lotes por FEFO — é do servidor (`TransferenciaDeStock`), o mesmo que o
 * ecrã Livewire chama.
 *
 * O ASPECTO É O DE SEMPRE: o cabeçalho da tabela com fundo e ícones, as
 * linhas a entrar em cascata e a acender ao passar, e o estado vazio com o
 * relógio dentro do círculo a dizer o que fazer com os botões de cima.
 */

/** O atraso da linha `i` na entrada em cascata (ver `.entra` no layout). */
const cascata = (i: number) => ({ '--i': i }) as CSSProperties;

/** O quadrado de uma acção de linha: fundo suave da cor do que ela faz. */
const accao = (cor: Cor) =>
    cls('grid h-9 w-9 place-items-center rounded-lg transition-all duration-200 hover:scale-110', CORES[cor].suave, FOCO);

export default function TransferenciasEntreArmazens() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState({ procura: '', armazem: '', tipo: '', de: '', ate: '', page: 1 });
    const [modal, porModal] = useState<'transferir' | 'ajustar' | null>(null);
    const [detalhe, porDetalhe] = useState<LoteDoHistorico | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['transferencias', 'opcoes'], queryFn: transferencias.opcoes, staleTime: 5 * 60_000 });
    const historico = useQuery({ queryKey: ['transferencias', 'historico', filtros], queryFn: () => transferencias.historico(filtros), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['transferencias'] }); };

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || historico.isError) {
        const erro = opcoes.error ?? historico.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as transferências')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = historico.data?.data ?? [];
    const contas = historico.data?.meta;

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <Cartao
                titulo={t('Transferências e Ajustes de Stock')}
                icone="fa-exchange-alt"
                accoes={
                    <span className="flex gap-2">
                        {o.permissoes.pode_ajustar && <Botao icone="fa-sliders" onClick={() => porModal('ajustar')}>{t('Ajustar em lote')}</Botao>}
                        {o.permissoes.pode_transferir && <Botao cor="primaria" tom="solida" icone="fa-right-left" onClick={() => porModal('transferir')}>{t('Transferir')}</Botao>}
                    </span>
                }
            >
                <div className="flex flex-wrap items-end gap-3">
                    <label className="min-w-[14rem] flex-1 text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span><input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder={t('Artigo')} className={entrada} /></label>
                    <label className="text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Armazém')}</span>
                        <select value={filtros.armazem} onChange={(e) => porFiltros((f) => ({ ...f, armazem: e.target.value, page: 1 }))} className={entrada}><option value="">{t('Todos')}</option>{o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select>
                    </label>
                    <label className="text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Tipo')}</span>
                        <select value={filtros.tipo} onChange={(e) => porFiltros((f) => ({ ...f, tipo: e.target.value, page: 1 }))} className={entrada}><option value="">{t('Todos')}</option><option value="transfer">{t('Transferências')}</option><option value="adjustment">{t('Ajustes')}</option></select>
                    </label>
                    <label className="text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('De')}</span><input type="date" value={filtros.de} onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value, page: 1 }))} className={entrada} /></label>
                    <label className="text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Até')}</span><input type="date" value={filtros.ate} onChange={(e) => porFiltros((f) => ({ ...f, ate: e.target.value, page: 1 }))} className={entrada} /></label>
                </div>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-bold"><i className="fas fa-hashtag mr-1.5 text-purple-500" aria-hidden="true" />{t('Referência')}</th><th className="px-4 py-3 font-bold">{t('Tipo')}</th><th className="px-4 py-3 font-bold"><i className="fas fa-clock mr-1.5 text-slate-400" aria-hidden="true" />{t('Quando')}</th><th className="px-4 py-3 font-bold"><i className="fas fa-warehouse mr-1.5 text-blue-500" aria-hidden="true" />{t('Armazém')}</th><th className="px-4 py-3 text-right font-bold">{t('Artigos')}</th><th className="px-4 py-3 text-right font-bold">{t('Unidades')}</th><th className="px-4 py-3 font-bold">{t('Quem')}</th><th className="sticky right-0 z-10 w-24 bg-slate-50 px-4 py-3 text-right font-bold shadow-[-10px_0_12px_-10px_rgba(15,23,42,0.25)]">{t('Documento')}</th></tr></thead>
                        <tbody className={cls('divide-y divide-slate-100', historico.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-6 py-16">
                                        {historico.isPending ? (
                                            <p className="text-center text-slate-400">{t('A carregar…')}</p>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center text-center">
                                                <div className="mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                                    <i className="fas fa-clock-rotate-left text-3xl text-slate-400" aria-hidden="true" />
                                                </div>
                                                <p className="text-lg font-semibold text-slate-500">{t('Nenhuma movimentação encontrada')}</p>
                                                <p className="mt-2 text-sm text-slate-400">{t('Use os botões acima para criar transferências ou ajustes')}</p>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            )}
                            {linhas.map((l, i) => (
                                <tr key={l.id} style={cascata(i)} className="entra group transition-all duration-200 hover:bg-purple-50/60">
                                    <td className="px-4 py-2 font-mono font-semibold text-slate-900">
                                        {l.referencia ? (
                                            <span className="whitespace-nowrap">{l.referencia}</span>
                                        ) : (
                                            // Antigo, sem lote: diz o que foi em vez de um «#» vazio.
                                            <span className="block font-sans">
                                                <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                                    <i className="fas fa-clock-rotate-left" aria-hidden="true" />{l.movimento_id ? t('Avulso') : `#${l.reference_id ?? ''}`}
                                                </span>
                                                {l.artigo && <span className="mt-0.5 block max-w-[16rem] truncate text-xs font-medium text-slate-600" title={l.artigo}>{l.artigo}</span>}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2"><Etiqueta cor={l.tipo === 'transfer' ? 'primaria' : 'aviso'} icone={l.tipo === 'transfer' ? 'fa-exchange-alt' : 'fa-sliders'}>{l.tipo_rotulo}</Etiqueta></td>
                                    <td className="whitespace-nowrap px-4 py-2 tabular-nums text-slate-600">{dataHora(l.quando.replace(' ', 'T'))}</td>
                                    <td className="px-4 py-2">{l.armazem ? <Etiqueta cor="neutra" icone="fa-warehouse">{l.armazem}</Etiqueta> : <span className="text-slate-300">—</span>}</td>
                                    <td className="px-4 py-2 text-right font-semibold tabular-nums">{l.produtos}</td>
                                    <td className="px-4 py-2 text-right font-bold tabular-nums text-slate-900">{l.quantidade.toLocaleString('pt-PT')}</td>
                                    <td className="px-4 py-2">{l.quem}</td>
                                    {/* PRESA À DIREITA: numa janela estreita (a barra lateral
                                        aberta) a tabela rola para o lado, e era esta coluna — a
                                        dos papéis — que ficava fora da vista. Parecia que os
                                        ícones do PDF tinham desaparecido. */}
                                    <td className="sticky right-0 bg-white px-4 py-2 text-right shadow-[-10px_0_12px_-10px_rgba(15,23,42,0.25)] transition-colors duration-200 group-hover:bg-purple-50">
                                        <span className="flex justify-end gap-1.5">
                                            <button type="button" onClick={() => porDetalhe(l)} title={t('Detalhe')} aria-label={t('Detalhe de :referencia', { referencia: l.referencia ?? l.artigo ?? l.reference_id ?? '' })} className={accao('primaria')}><i className="fas fa-list" aria-hidden="true" /></button>
                                            {/* A PRÉ-VISUALIZAÇÃO abre no browser e é de lá
                                                que se imprime; o PDF descarrega. O ecrã de
                                                sempre tinha os dois, e a migração trouxe só o
                                                segundo — quem queria conferir antes de
                                                imprimir tinha de descarregar um ficheiro. */}
                                            {l.preview && <a href={l.preview} target="_blank" rel="noreferrer" title={t('Pré-visualizar / Imprimir')} aria-label={t('Pré-visualizar :referencia', { referencia: l.referencia ?? '' })} className={accao('bom')}><i className="fas fa-print" aria-hidden="true" /></a>}
                                            {l.pdf && <a href={l.pdf} target="_blank" rel="noreferrer" title={t('PDF')} aria-label={t('PDF de :referencia', { referencia: l.referencia ?? '' })} className={accao('perigo')}><i className="fas fa-file-pdf" aria-hidden="true" /></a>}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && (
                    <Paginacao
                        pagina={contas.current_page}
                        ultima={contas.last_page}
                        total={contas.total}
                        aCarregar={historico.isFetching}
                        aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                    />
                )}
            </Cartao>

            {modal === 'transferir' && <Transferir o={o} aoFechar={() => porModal(null)} aoFeito={feito} />}
            {modal === 'ajustar' && <Ajustar o={o} aoFechar={() => porModal(null)} aoFeito={feito} />}
            {detalhe && <Detalhe l={detalhe} aoFechar={() => porDetalhe(null)} />}
        </div>
    );
}

function Transferir({ o, aoFechar, aoFeito }: { o: OpcoesDasTransferencias; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [de, porDe] = useState('');
    const [para, porPara] = useState('');
    const [notas, porNotas] = useState('');
    const [itens, porItens] = useState<ItemDaTransferencia[]>([]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ referencia: string; resumo: Resumo[]; pdf: string } | null>(null);

    const gravar = useMutation({
        mutationFn: () => transferencias.entreArmazens({ de: Number(de) || null, para: Number(para) || null, notas: notas || null, itens: itens.map((i) => ({ product_id: i.product_id, product_name: i.product_name, product_code: i.product_code, quantity: Number(i.quantity) || 0 })) }),
        onSuccess: (r) => { porFeito(r); aoFeito(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={feito ? t('Transferência registada') : t('Transferir entre armazéns')} subtitulo={feito ? undefined : t('Gerir movimentações de stock entre armazéns')} icone={feito ? 'fa-circle-check' : 'fa-right-left'} cor={feito ? 'bom' : 'primaria'} largura="lg" rodape={feito ? <Botao onClick={aoFechar}>{t('Fechar')}</Botao> : <><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-right-left" aTrabalhar={gravar.isPending} disabled={itens.length === 0} onClick={() => gravar.mutate()}>{t('Transferir')}</Botao></>}>
            {feito ? (
                <Comprovativo titulo={t('Transferência registada')} referencias={[{ rotulo: t('Referência'), valor: feito.referencia }]} resumo={feito.resumo} pdf={feito.pdf} colunas={[{ chave: 'produto', rotulo: t('Artigo') }, { chave: 'quantidade', rotulo: t('Qtd.'), numero: true }, { chave: 'origem_antes', rotulo: t('Origem antes'), numero: true }, { chave: 'origem_depois', rotulo: t('Origem depois'), numero: true }, { chave: 'destino_antes', rotulo: t('Destino antes'), numero: true }, { chave: 'destino_depois', rotulo: t('Destino depois'), numero: true }]} />
            ) : (
                <div className="space-y-4">
                    <AvisoDeErro erro={gravar.error} />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Campo etiqueta={t('Do armazém')} erro={erros.de} obrigatorio><select value={de} onChange={(e) => { porDe(e.target.value); porItens([]); }} className={entrada}><option value="">{t('Escolher…')}</option>{o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select></Campo>
                        <Campo etiqueta={t('Para o armazém')} erro={erros.para} obrigatorio><select value={para} onChange={(e) => porPara(e.target.value)} className={entrada}><option value="">{t('Escolher…')}</option>{o.armazens.filter((a) => String(a.id) !== de).map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select></Campo>
                        <Campo etiqueta={t('Observações')} erro={erros.notas}><input value={notas} onChange={(e) => porNotas(e.target.value)} className={entrada} /></Campo>
                    </div>
                    <Carrinho armazem={de} itens={itens} aoMudar={porItens} comTecto />
                    {erros.itens?.[0] && <p role="alert" className="text-sm font-medium text-red-700">{erros.itens[0]}</p>}
                </div>
            )}
        </Modal>
    );
}

function Ajustar({ o, aoFechar, aoFeito }: { o: OpcoesDasTransferencias; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [armazem, porArmazem] = useState('');
    const [tipo, porTipo] = useState<'in' | 'out'>('in');
    const [motivo, porMotivo] = useState('');
    const [itens, porItens] = useState<ItemDaTransferencia[]>([]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ referencia: string; resumo: Resumo[]; pdf: string } | null>(null);

    const gravar = useMutation({
        mutationFn: () => transferencias.ajuste({ armazem: Number(armazem) || null, tipo, motivo, itens: itens.map((i) => ({ product_id: i.product_id, product_name: i.product_name, product_code: i.product_code, quantity: Number(i.quantity) || 0 })) }),
        onSuccess: (r) => { porFeito(r); aoFeito(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={feito ? t('Ajuste registado') : t('Ajustar stock em lote')} icone={feito ? 'fa-circle-check' : 'fa-sliders'} cor={feito ? 'bom' : 'aviso'} largura="lg" rodape={feito ? <Botao onClick={aoFechar}>{t('Fechar')}</Botao> : <><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-sliders" aTrabalhar={gravar.isPending} disabled={itens.length === 0} onClick={() => gravar.mutate()}>{t('Ajustar')}</Botao></>}>
            {feito ? (
                <Comprovativo titulo={t('Ajuste registado')} referencias={[{ rotulo: t('Referência'), valor: feito.referencia }]} resumo={feito.resumo} pdf={feito.pdf} colunas={[{ chave: 'produto', rotulo: t('Artigo') }, { chave: 'quantidade', rotulo: t('Qtd.'), numero: true }, { chave: 'antes', rotulo: t('Antes'), numero: true }, { chave: 'depois', rotulo: t('Depois'), numero: true }]} />
            ) : (
                <div className="space-y-4">
                    <AvisoDeErro erro={gravar.error} />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Campo etiqueta={t('Armazém')} erro={erros.armazem} obrigatorio><select value={armazem} onChange={(e) => { porArmazem(e.target.value); porItens([]); }} className={entrada}><option value="">{t('Escolher…')}</option>{o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select></Campo>
                        <Campo etiqueta={t('Sentido')} erro={erros.tipo} obrigatorio><select value={tipo} onChange={(e) => { porTipo(e.target.value as 'in' | 'out'); porItens([]); }} className={entrada}><option value="in">{t('Entrada (acrescenta)')}</option><option value="out">{t('Saída (retira)')}</option></select></Campo>
                        <Campo etiqueta={t('Motivo')} erro={erros.motivo} obrigatorio><input value={motivo} onChange={(e) => porMotivo(e.target.value)} placeholder={t('Contagem, avaria, oferta…')} className={entrada} /></Campo>
                    </div>
                    {/* O tecto só existe na saída: numa entrada acrescenta-se o que for preciso. */}
                    <Carrinho armazem={armazem} itens={itens} aoMudar={porItens} comTecto={tipo === 'out'} soComStock={tipo === 'out'} />
                    {erros.itens?.[0] && <p role="alert" className="text-sm font-medium text-red-700">{erros.itens[0]}</p>}
                </div>
            )}
        </Modal>
    );
}

function Detalhe({ l, aoFechar }: { l: LoteDoHistorico; aoFechar: () => void }) {
    const q = useQuery({
        queryKey: ['transferencias', 'detalhes', l.referencia, l.reference_id, l.movimento_id],
        queryFn: () => transferencias.detalhes(
            l.referencia ? { referencia: l.referencia } : l.movimento_id ? { movimento: l.movimento_id } : { reference_id: l.reference_id },
        ),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={l.referencia ? t('Lote :referencia', { referencia: l.referencia }) : l.movimento_id ? t('Movimento avulso') : t('Lote :referencia', { referencia: '#' + l.reference_id })} icone="fa-list" largura="lg" rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}>
            {q.isPending ? <Carregando linhas={4} /> : q.isError ? <AvisoDeErro erro={q.error} /> : (
                <table className="w-full text-sm">
                    <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-3 py-2 font-bold">{t('Artigo')}</th><th className="px-3 py-2 font-bold">{t('Armazém')}</th><th className="px-3 py-2 text-right font-bold">{t('Qtd.')}</th><th className="px-3 py-2 text-right font-bold">{t('Antes')}</th><th className="px-3 py-2 text-right font-bold">{t('Depois')}</th><th className="px-3 py-2 font-bold">{t('Notas')}</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {q.data.data.map((m, i) => (
                            <tr key={m.id} style={cascata(i)} className="entra transition-all duration-200 hover:bg-purple-50/60"><td className="px-3 py-2 font-medium text-slate-800">{m.artigo}</td><td className="px-3 py-2">{m.armazem}</td><td className={cls('px-3 py-2 text-right font-bold tabular-nums', m.quantidade < 0 ? 'text-red-600' : 'text-emerald-700')}><i className={cls('fas mr-1 text-[10px]', m.quantidade < 0 ? 'fa-arrow-down' : 'fa-arrow-up')} aria-hidden="true" />{m.quantidade.toLocaleString('pt-PT')}</td><td className="px-3 py-2 text-right tabular-nums text-slate-500">{m.antes?.toLocaleString('pt-PT') ?? ''}</td><td className="px-3 py-2 text-right tabular-nums">{m.depois?.toLocaleString('pt-PT') ?? ''}</td><td className="px-3 py-2 text-xs text-slate-500">{m.notas}</td></tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}
