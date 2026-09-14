import { useState, type CSSProperties } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { lotes, type Lote, type OpcoesDosLotes } from '@/api/lotes';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { CORES, FOCO, RAIO, cls, data, kz, type Cor } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t, tPartes } from '@/i18n';

/**
 * OS LOTES E AS VALIDADES.
 *
 * O que está a expirar sobe ao topo. Corrigir a quantidade de um lote não
 * altera o que já saiu dele — é o `GestorDeLotes`, no servidor, que faz
 * essa conta, o mesmo que o ecrã Livewire chama.
 *
 * O ASPECTO É O DE SEMPRE: quatro cartões de gradiente no topo (activos,
 * a expirar, expirados e o total), a lista com o cabeçalho de fundo e as
 * linhas a entrar em cascata, e o estado vazio desenhado — o círculo com a
 * caixa aberta lá dentro e a frase que diz o que fazer a seguir.
 */

/** O atraso da linha `i` na entrada em cascata (ver `.entra` no layout). */
const cascata = (i: number) => ({ '--i': i }) as CSSProperties;

/** O quadrado de uma acção de linha: fundo suave da cor do que ela faz. */
const accao = (cor: Cor) =>
    cls('grid h-9 w-9 place-items-center rounded-lg transition-all duration-200 hover:scale-110', CORES[cor].suave, FOCO);

type Forma = { product_id: string; warehouse_id: string; batch_number: string; manufacturing_date: string; expiry_date: string; quantity: string; cost_price: string; alert_days: string; notes: string };
const VAZIA: Forma = { product_id: '', warehouse_id: '', batch_number: '', manufacturing_date: '', expiry_date: '', quantity: '', cost_price: '', alert_days: '30', notes: '' };

export default function Lotes() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState({ procura: '', produto: '', armazem: '', estado: '', page: 1 });
    const [aEditar, porAEditar] = useState<Lote | null>(null);
    const [forma, porForma] = useState<Forma | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<Lote | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['lotes', 'opcoes'], queryFn: lotes.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['lotes', filtros], queryFn: () => lotes.lista(filtros), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['lotes'] }); };

    const gravar = useMutation({
        mutationFn: (f: Forma) => {
            const corpo = { ...f, product_id: Number(f.product_id) || null, warehouse_id: Number(f.warehouse_id) || null, quantity: Number(f.quantity) || 0, cost_price: Number(f.cost_price) || 0, alert_days: Number(f.alert_days) || 30, manufacturing_date: f.manufacturing_date || null, expiry_date: f.expiry_date || null };
            return aEditar ? lotes.actualizar(aEditar.id, corpo) : lotes.guardar(corpo);
        },
        onSuccess: (r) => { feito(r.message); porForma(null); porAEditar(null); porErros({}); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const apagar = useMutation({ mutationFn: (l: Lote) => lotes.apagar(l.id), onSuccess: (r) => { feito(r.message); porAApagar(null); } });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os lotes')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    const abrirNovo = () => { porAEditar(null); porErros({}); porForma({ ...VAZIA }); };
    const abrirEdicao = (l: Lote) => {
        porAEditar(l); porErros({});
        porForma({ product_id: String(l.product_id), warehouse_id: l.warehouse_id ? String(l.warehouse_id) : '', batch_number: l.batch_number ?? '', manufacturing_date: l.manufacturing_date ?? '', expiry_date: l.expiry_date ?? '', quantity: String(l.quantity), cost_price: String(l.cost_price), alert_days: String(l.alert_days), notes: l.notes ?? '' });
    };

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}
            <AvisoDeErro erro={apagar.error} />

            {/* O verde é o que está bom, o âmbar o que está a chegar ao fim e o
                vermelho o que já lá chegou. O ícone diz o mesmo sem a cor. */}
            {resumo && (
                <div className={cls('grid grid-cols-2 gap-3 sm:grid-cols-4', lista.isFetching && 'opacity-70')}>
                    <CartaoNumero rotulo={t('Lotes activos')} valor={resumo.activos.toLocaleString('pt-PT')} icone="fa-circle-check" tom="verde" />
                    <CartaoNumero rotulo={t('A expirar em breve')} valor={resumo.a_expirar.toLocaleString('pt-PT')} icone="fa-hourglass-half" tom={resumo.a_expirar > 0 ? 'ambar' : 'cinza'} />
                    <CartaoNumero rotulo={t('Expirados')} valor={resumo.expirados.toLocaleString('pt-PT')} icone="fa-calendar-xmark" tom={resumo.expirados > 0 ? 'vermelho' : 'cinza'} />
                    <CartaoNumero rotulo={t('Total')} valor={(contas?.total ?? 0).toLocaleString('pt-PT')} icone="fa-layer-group" tom="azul" />
                </div>
            )}

            <Cartao
                titulo={t('Lotes e Validades')}
                icone="fa-calendar-check"
                accoes={o.permissoes.pode_criar && <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>{t('Novo lote')}</Botao>}
            >
                <div className="flex flex-wrap items-end gap-3">
                    <label className="min-w-[14rem] flex-1 text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span>
                        <input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder={t('Lote ou artigo')} className={entrada} />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Armazém')}</span>
                        <select value={filtros.armazem} onChange={(e) => porFiltros((f) => ({ ...f, armazem: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Estado')}</span>
                        <select value={filtros.estado} onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                        </select>
                    </label>
                </div>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">
                                <th className="px-4 py-3 font-bold"><i className="fas fa-hashtag mr-1.5 text-teal-500" aria-hidden="true" />{t('Lote')}</th>
                                <th className="px-4 py-3 font-bold"><i className="fas fa-box mr-1.5 text-indigo-500" aria-hidden="true" />{t('Artigo')}</th>
                                <th className="px-4 py-3 font-bold"><i className="fas fa-warehouse mr-1.5 text-blue-500" aria-hidden="true" />{t('Armazém')}</th>
                                {/* A FABRICAÇÃO, que a lista em Blade tinha: num
                                    lote de mercearia é ela que diz se a validade
                                    faz sentido, e é por ela que se separam duas
                                    remessas do mesmo artigo. */}
                                <th className="px-4 py-3 font-bold"><i className="fas fa-industry mr-1.5 text-slate-400" aria-hidden="true" />{t('Fabricação')}</th>
                                <th className="px-4 py-3 font-bold"><i className="fas fa-calendar-day mr-1.5 text-amber-500" aria-hidden="true" />{t('Validade')}</th>
                                <th className="px-4 py-3 text-right font-bold">{t('Disponível')}</th>
                                <th className="px-4 py-3 text-right font-bold">{t('Custo')}</th>
                                <th className="px-4 py-3 font-bold">{t('Estado')}</th>
                                <th className="w-24 px-4 py-3 text-right font-bold">{t('Acções')}</th>
                            </tr>
                        </thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-6 py-16">
                                        {lista.isPending ? (
                                            <p className="text-center text-slate-400">{t('A carregar…')}</p>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center text-center">
                                                <div className="mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                                    <i className="fas fa-box-open text-3xl text-slate-400" aria-hidden="true" />
                                                </div>
                                                <p className="text-lg font-semibold text-slate-500">{t('Nenhum lote encontrado')}</p>
                                                {/* A frase manda para o botão que já está em cima:
                                                    repeti-lo aqui punha dois botões iguais no
                                                    mesmo ecrã. */}
                                                <p className="mt-2 text-sm text-slate-400">{t('Crie um novo lote usando o botão acima')}</p>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            )}
                            {linhas.map((l, i) => (
                                <tr key={l.id} style={cascata(i)} className="entra transition-all duration-200 hover:bg-teal-50/60">
                                    <td className="px-4 py-2 font-mono font-semibold text-slate-900">{l.batch_number ?? <span className="text-slate-300">—</span>}</td>
                                    <td className="px-4 py-2 font-medium text-slate-800">{l.artigo}</td>
                                    <td className="px-4 py-2">{l.armazem ? <Etiqueta cor="primaria" icone="fa-warehouse">{l.armazem}</Etiqueta> : <span className="text-slate-300">—</span>}</td>
                                    <td className="px-4 py-2 tabular-nums text-slate-600">{l.manufacturing_date ? data(l.manufacturing_date) : <span className="text-slate-300">—</span>}</td>
                                    <td className="px-4 py-2 tabular-nums">
                                        {l.expiry_date ? data(l.expiry_date) : <span className="text-slate-300">—</span>}
                                        {l.dias !== null && <span className={cls('ml-2 text-xs font-semibold', l.dias < 0 ? 'text-red-600' : l.dias <= l.alert_days ? 'text-amber-600' : 'text-slate-400')}>{l.dias < 0 ? t('há :dias dias', { dias: -l.dias }) : t(':dias dias', { dias: l.dias })}</span>}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums"><span className="font-bold text-slate-900">{l.quantity_available.toLocaleString('pt-PT')}</span> <span className="text-xs text-slate-400">/ {l.quantity.toLocaleString('pt-PT')} {l.unidade}</span></td>
                                    <td className="px-4 py-2 text-right tabular-nums">{kz(l.cost_price)}</td>
                                    <td className="px-4 py-2">
                                        <Etiqueta
                                            cor={l.status === 'expired' ? 'perigo' : l.status === 'active' ? 'bom' : 'neutra'}
                                            icone={l.status === 'expired' ? 'fa-circle-xmark' : l.status === 'active' ? 'fa-circle-check' : 'fa-circle-minus'}
                                            ponto
                                        >
                                            {l.status === 'expired' ? t('Expirado') : l.status === 'active' ? t('Activo') : l.status}
                                        </Etiqueta>
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1.5">
                                            {o.permissoes.pode_editar && <button type="button" onClick={() => abrirEdicao(l)} aria-label={t('Editar lote :lote', { lote: l.batch_number ?? l.id })} className={accao('primaria')}><i className="fas fa-pen" aria-hidden="true" /></button>}
                                            {o.permissoes.pode_apagar && <button type="button" disabled={!l.pode_apagar} onClick={() => porAApagar(l)} title={l.pode_apagar ? t('Apagar') : t('Já usado — não se apaga')} aria-label={t('Apagar lote :lote', { lote: l.batch_number ?? l.id })} className={cls(accao('perigo'), 'disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:scale-100')}><i className="fas fa-trash" aria-hidden="true" /></button>}
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
                        aCarregar={lista.isFetching}
                        aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                    />
                )}
            </Cartao>

            {forma && <Formulario o={o} forma={forma} erros={erros} titulo={aEditar ? t('Editar lote :lote', { lote: aEditar.batch_number ?? '' }) : t('Novo lote')} icone={aEditar ? 'fa-pen' : 'fa-plus'} aGravar={gravar.isPending} erroGeral={gravar.error} aoMudar={porForma} aoFechar={() => { porForma(null); porAEditar(null); }} aoGravar={() => gravar.mutate(forma)} />}

            <Modal aberto={aApagar !== null} aoFechar={() => porAApagar(null)} titulo={t('Apagar o lote?')} icone="fa-trash" cor="perigo" rodape={<><Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Apagar')}</Botao></>}>
                <p className="text-sm text-slate-700">{tPartes('Vai apagar o lote :lote. Não há volta.', { lote: <strong>{aApagar?.batch_number ?? aApagar?.id}</strong> })}</p>
            </Modal>
        </div>
    );
}

function Formulario({ o, forma, erros, titulo, icone, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDosLotes; forma: Forma; erros: Record<string, string[]>; titulo: string; icone: string; aGravar: boolean; erroGeral: unknown;
    aoMudar: (f: Forma) => void; aoFechar: () => void; aoGravar: () => void;
}) {
    const m = (chave: keyof Forma) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => aoMudar({ ...forma, [chave]: e.target.value });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={titulo} subtitulo={t('Controle de validade e lotes de produtos')} icone={icone} largura="lg" rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={aGravar} onClick={aoGravar}>{t('Guardar')}</Botao></>}>
            <AvisoDeErro erro={erroGeral} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Artigo')} erro={erros.product_id} obrigatorio>
                    <select value={forma.product_id} onChange={m('product_id')} className={entrada}>
                        <option value="">{t('Escolher…')}</option>
                        {o.artigos.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id}>
                    <select value={forma.warehouse_id} onChange={m('warehouse_id')} className={entrada}>
                        <option value="">—</option>
                        {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Número do lote')} erro={erros.batch_number}><input value={forma.batch_number} onChange={m('batch_number')} className={entrada} /></Campo>
                <Campo etiqueta={t('Quantidade')} erro={erros.quantity} obrigatorio><input type="number" min="0" step="0.001" value={forma.quantity} onChange={m('quantity')} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta={t('Fabrico')} erro={erros.manufacturing_date}><input type="date" value={forma.manufacturing_date} onChange={m('manufacturing_date')} className={entrada} /></Campo>
                <Campo etiqueta={t('Validade')} erro={erros.expiry_date}><input type="date" value={forma.expiry_date} onChange={m('expiry_date')} className={entrada} /></Campo>
                <Campo etiqueta={t('Custo unitário')} erro={erros.cost_price}><input type="number" min="0" step="0.01" value={forma.cost_price} onChange={m('cost_price')} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta={t('Avisar com antecedência (dias)')} erro={erros.alert_days} obrigatorio><input type="number" min="1" max="365" value={forma.alert_days} onChange={m('alert_days')} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta={t('Notas')} erro={erros.notes} className="sm:col-span-2"><textarea rows={2} value={forma.notes} onChange={m('notes')} className={cls(entrada, 'h-auto py-2')} /></Campo>
            </div>
        </Modal>
    );
}
