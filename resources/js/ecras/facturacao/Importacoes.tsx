import { useState, type CSSProperties } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { importacoes, type Importacao, type OpcoesDasImportacoes } from '@/api/importacoes';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { CORES, FOCO, RAIO, cls, data, kz, type Cor } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t, tPartes } from '@/i18n';

/**
 * AS IMPORTAÇÕES — o processo de trazer mercadoria de fora.
 *
 * Uma importação nasce em cotação e anda pelos estados até concluída. O CIF
 * é sempre FOB + frete + seguro: o ecrã mostra a soma para o utilizador ver
 * o que vai gravar, mas quem a grava é o servidor (`GestorDeImportacoes`),
 * o mesmo que o ecrã Livewire chama.
 *
 * O ASPECTO É O DE SEMPRE: os quatro cartões de gradiente do topo (quantas
 * são, quantas andam no mar, quantas estão presas na alfândega e quanto
 * dinheiro está lá fora), a tabela com o cabeçalho de fundo, e o navio
 * dentro do círculo quando não há nenhuma.
 */

/** O atraso da linha `i` na entrada em cascata (ver `.entra` no layout). */
const cascata = (i: number) => ({ '--i': i }) as CSSProperties;

/** O quadrado de uma acção de linha: fundo suave da cor do que ela faz. */
const accao = (cor: Cor) =>
    cls('grid h-9 w-9 place-items-center rounded-lg transition-all duration-200 hover:scale-110', CORES[cor].suave, FOCO);

type Forma = {
    supplier_id: string; warehouse_id: string; reference: string; order_date: string; expected_arrival_date: string;
    origin_country: string; origin_port: string; destination_port: string; shipping_company: string;
    transport_type: string; fob_value: string; freight_cost: string; insurance_cost: string; notes: string;
};

const VAZIA: Forma = {
    supplier_id: '', warehouse_id: '', reference: '', order_date: new Date().toISOString().slice(0, 10), expected_arrival_date: '',
    origin_country: '', origin_port: '', destination_port: 'Luanda', shipping_company: '',
    transport_type: 'maritime', fob_value: '0', freight_cost: '0', insurance_cost: '0', notes: '',
};

const COR: Record<string, 'neutra' | 'primaria' | 'bom' | 'aviso' | 'perigo'> = {
    gray: 'neutra', blue: 'primaria', yellow: 'aviso', green: 'bom', cyan: 'primaria', orange: 'aviso', emerald: 'bom', red: 'perigo',
};

export default function Importacoes() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState<{ procura: string; estado: string; fornecedor: string; page: number }>({ procura: '', estado: '', fornecedor: '', page: 1 });
    const [aEditar, porAEditar] = useState<Importacao | null>(null);
    const [forma, porForma] = useState<Forma | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<Importacao | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['importacoes', 'opcoes'], queryFn: importacoes.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['importacoes', filtros], queryFn: () => importacoes.lista(filtros), placeholderData: keepPreviousData });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['importacoes'] });

    const gravar = useMutation({
        mutationFn: (f: Forma) => {
            const corpo = { ...f, supplier_id: Number(f.supplier_id) || null, warehouse_id: Number(f.warehouse_id) || null, expected_arrival_date: f.expected_arrival_date || null };
            return aEditar ? importacoes.actualizar(aEditar.id, corpo) : importacoes.guardar(corpo);
        },
        onSuccess: (r) => { invalidar(); porForma(null); porAEditar(null); porErros({}); porRecado(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const estado = useMutation({
        mutationFn: ({ i, e }: { i: Importacao; e: string }) => importacoes.estado(i.id, e),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    const apagar = useMutation({
        mutationFn: (i: Importacao) => importacoes.apagar(i.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as importações')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    const abrirNova = () => { porAEditar(null); porErros({}); porForma({ ...VAZIA }); };
    const abrirEdicao = (i: Importacao) => {
        porAEditar(i); porErros({});
        porForma({
            supplier_id: String(i.supplier_id), warehouse_id: i.warehouse_id ? String(i.warehouse_id) : '', reference: i.reference ?? '',
            order_date: i.order_date ?? '', expected_arrival_date: i.expected_arrival_date ?? '', origin_country: i.origin_country ?? '',
            origin_port: i.origin_port ?? '', destination_port: i.destination_port ?? 'Luanda', shipping_company: i.shipping_company ?? '',
            transport_type: i.transport_type, fob_value: String(i.fob_value), freight_cost: String(i.freight_cost), insurance_cost: String(i.insurance_cost), notes: i.notes ?? '',
        });
    };

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <AvisoDeErro erro={estado.error ?? apagar.error} />

            {/* O que está a andar é azul, o que está preso na alfândega é
                âmbar, e o dinheiro lá fora é verde. */}
            {resumo && (
                <div className={cls('grid grid-cols-2 gap-3 sm:grid-cols-4', lista.isFetching && 'opacity-70')}>
                    <CartaoNumero rotulo={t('Importações')} valor={resumo.total.toLocaleString('pt-PT')} icone="fa-ship" tom="indigo" />
                    <CartaoNumero rotulo={t('Em trânsito')} valor={resumo.em_transito.toLocaleString('pt-PT')} icone="fa-route" tom="azul" />
                    <CartaoNumero rotulo={t('Na alfândega')} valor={resumo.na_alfandega.toLocaleString('pt-PT')} icone="fa-landmark" tom={resumo.na_alfandega > 0 ? 'ambar' : 'cinza'} />
                    <CartaoNumero rotulo={t('CIF em curso')} valor={kz(resumo.valor_em_curso)} sufixo="Kz" icone="fa-money-bill-wave" tom="verde" />
                </div>
            )}

            <Cartao
                titulo={t('Importações')}
                icone="fa-ship"
                accoes={o.permissoes.pode_criar && <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNova}>{t('Nova importação')}</Botao>}
            >
                <div className="flex flex-wrap items-end gap-3">
                    <label className="min-w-[16rem] flex-1 text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span>
                        <input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder={t('Número, referência, contentor ou fornecedor')} className={entrada} />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Estado')}</span>
                        <select value={filtros.estado} onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                        </select>
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Fornecedor')}</span>
                        <select value={filtros.fornecedor} onChange={(e) => porFiltros((f) => ({ ...f, fornecedor: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.fornecedores.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                        </select>
                    </label>
                </div>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">
                                <th className="px-4 py-3 font-bold"><i className="fas fa-hashtag mr-1.5 text-cyan-500" aria-hidden="true" />{t('Número')}</th>
                                <th className="px-4 py-3 font-bold"><i className="fas fa-industry mr-1.5 text-indigo-500" aria-hidden="true" />{t('Fornecedor')}</th>
                                <th className="px-4 py-3 font-bold"><i className="fas fa-earth-americas mr-1.5 text-blue-500" aria-hidden="true" />{t('Origem')}</th>
                                <th className="px-4 py-3 font-bold">{t('Pedido')}</th>
                                <th className="px-4 py-3 font-bold">{t('Chegada prevista')}</th>
                                <th className="px-4 py-3 text-right font-bold">CIF</th>
                                <th className="px-4 py-3 font-bold">{t('Estado')}</th>
                                <th className="w-32 px-4 py-3 text-right font-bold">{t('Acções')}</th>
                            </tr>
                        </thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-6 py-16">
                                        {lista.isPending ? (
                                            <p className="text-center text-slate-400">{t('A carregar…')}</p>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center text-center">
                                                <div className="mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                                    <i className="fas fa-ship text-3xl text-slate-400" aria-hidden="true" />
                                                </div>
                                                <p className="text-lg font-semibold text-slate-500">{t('Nenhuma importação encontrada')}</p>
                                                <p className="mt-2 text-sm text-slate-400">{t('Crie a sua primeira importação para começar')}</p>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            )}
                            {linhas.map((i, k) => (
                                <tr key={i.id} style={cascata(k)} className="entra transition-all duration-200 hover:bg-cyan-50/60">
                                    <td className="px-4 py-2 font-mono font-semibold text-slate-900">{i.numero}{i.reference && <span className="block font-sans text-xs font-normal text-slate-400">{i.reference}</span>}</td>
                                    <td className="px-4 py-2 font-medium text-slate-800">{i.fornecedor}</td>
                                    <td className="px-4 py-2">{i.origin_country}{i.origin_port ? ` · ${i.origin_port}` : ''}</td>
                                    <td className="px-4 py-2 tabular-nums">{i.order_date ? data(i.order_date) : ''}</td>
                                    <td className="px-4 py-2 tabular-nums">{i.expected_arrival_date ? data(i.expected_arrival_date) : ''}</td>
                                    <td className="px-4 py-2 text-right font-bold tabular-nums text-slate-900">{kz(i.cif_value)}</td>
                                    <td className="px-4 py-2">
                                        {o.permissoes.pode_editar ? (
                                            <select value={i.estado} onChange={(e) => estado.mutate({ i, e: e.target.value })} aria-label={t('Estado de :numero', { numero: i.numero })} className={cls(entrada, 'h-8 py-0 text-xs')}>
                                                {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                                            </select>
                                        ) : (
                                            <Etiqueta cor={COR[i.estado_cor] ?? 'neutra'} ponto>{i.estado_rotulo}</Etiqueta>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1.5">
                                            {o.permissoes.pode_editar && <button type="button" onClick={() => abrirEdicao(i)} aria-label={t('Editar :numero', { numero: i.numero })} className={accao('primaria')}><i className="fas fa-pen" aria-hidden="true" /></button>}
                                            {o.permissoes.pode_apagar && <button type="button" onClick={() => porAApagar(i)} aria-label={t('Apagar :numero', { numero: i.numero })} className={accao('perigo')}><i className="fas fa-trash" aria-hidden="true" /></button>}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                        <span>{t('Página :actual de :total · :quantas', { actual: contas.current_page, total: contas.last_page, quantas: contas.total })}</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>{t('Anterior')}</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>{t('Seguinte')}</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {forma && (
                <Formulario o={o} forma={forma} erros={erros} titulo={aEditar ? t('Editar :numero', { numero: aEditar.numero }) : t('Nova importação')} icone={aEditar ? 'fa-pen' : 'fa-ship'} aGravar={gravar.isPending} erroGeral={gravar.error} aoMudar={porForma} aoFechar={() => { porForma(null); porAEditar(null); }} aoGravar={() => gravar.mutate(forma)} />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar a importação?')}
                icone="fa-trash"
                cor="perigo"
                rodape={<><Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Eliminar')}</Botao></>}
            >
                <p className="text-sm text-slate-700">{tPartes('Vai eliminar :numero. Não há volta.', { numero: <strong>{aApagar?.numero}</strong> })}</p>
            </Modal>
        </div>
    );
}

function Formulario({ o, forma, erros, titulo, icone, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDasImportacoes; forma: Forma; erros: Record<string, string[]>; titulo: string; icone: string; aGravar: boolean; erroGeral: unknown;
    aoMudar: (f: Forma) => void; aoFechar: () => void; aoGravar: () => void;
}) {
    const m = (chave: keyof Forma) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => aoMudar({ ...forma, [chave]: e.target.value });
    const cif = (Number(forma.fob_value) || 0) + (Number(forma.freight_cost) || 0) + (Number(forma.insurance_cost) || 0);

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={titulo}
            subtitulo={t('Gestão completa do processo de importação - Angola')}
            icone={icone}
            largura="lg"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={aGravar} onClick={aoGravar}>{t('Guardar')}</Botao></>}
        >
            <AvisoDeErro erro={erroGeral} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Fornecedor')} erro={erros.supplier_id} obrigatorio>
                    <select value={forma.supplier_id} onChange={m('supplier_id')} className={entrada}>
                        <option value="">{t('Escolher…')}</option>
                        {o.fornecedores.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Armazém de destino')} erro={erros.warehouse_id}>
                    <select value={forma.warehouse_id} onChange={m('warehouse_id')} className={entrada}>
                        <option value="">—</option>
                        {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Referência')} erro={erros.reference}><input value={forma.reference} onChange={m('reference')} className={entrada} /></Campo>
                <Campo etiqueta={t('Transporte')} erro={erros.transport_type} obrigatorio>
                    <select value={forma.transport_type} onChange={m('transport_type')} className={entrada}>
                        {o.transportes.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Data do pedido')} erro={erros.order_date} obrigatorio><input type="date" value={forma.order_date} onChange={m('order_date')} className={entrada} /></Campo>
                <Campo etiqueta={t('Chegada prevista')} erro={erros.expected_arrival_date}><input type="date" value={forma.expected_arrival_date} onChange={m('expected_arrival_date')} className={entrada} /></Campo>
                <Campo etiqueta={t('País de origem')} erro={erros.origin_country} obrigatorio><input value={forma.origin_country} onChange={m('origin_country')} className={entrada} /></Campo>
                <Campo etiqueta={t('Porto de origem')} erro={erros.origin_port}><input value={forma.origin_port} onChange={m('origin_port')} className={entrada} /></Campo>
                <Campo etiqueta={t('Porto de destino')} erro={erros.destination_port}><input value={forma.destination_port} onChange={m('destination_port')} className={entrada} /></Campo>
                <Campo etiqueta={t('Transportadora')} erro={erros.shipping_company}><input value={forma.shipping_company} onChange={m('shipping_company')} className={entrada} /></Campo>
                <Campo etiqueta="FOB" erro={erros.fob_value} obrigatorio><input type="number" min="0" step="0.01" value={forma.fob_value} onChange={m('fob_value')} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta={t('Frete')} erro={erros.freight_cost}><input type="number" min="0" step="0.01" value={forma.freight_cost} onChange={m('freight_cost')} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta={t('Seguro')} erro={erros.insurance_cost}><input type="number" min="0" step="0.01" value={forma.insurance_cost} onChange={m('insurance_cost')} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                {/* A soma à vista enquanto se escreve, no verde do dinheiro:
                    é o número que o servidor vai gravar, e vê-se antes de
                    carregar em Guardar. */}
                <div className={cls('flex items-center justify-between border border-emerald-200 bg-gradient-to-r from-emerald-50 to-teal-50 px-4 py-2.5', RAIO)}>
                    <span className="text-sm font-medium text-emerald-800"><i className="fas fa-calculator mr-1.5 text-emerald-500" aria-hidden="true" />{t('CIF = FOB + frete + seguro')}</span>
                    <span className="text-lg font-bold tabular-nums text-emerald-900" data-cif>{kz(cif)}</span>
                </div>
                <Campo etiqueta={t('Observações')} erro={erros.notes} className="sm:col-span-2">
                    <textarea rows={2} value={forma.notes} onChange={m('notes')} className={cls(entrada, 'h-auto py-2')} />
                </Campo>
            </div>
        </Modal>
    );
}
