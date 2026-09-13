import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { series, type OpcoesDasSeries, type Serie } from '@/api/series';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t, tPartes } from '@/i18n';
import { Faixa, SemNada, cascata } from './faixa';

/**
 * A GESTÃO DAS SÉRIES DE DOCUMENTOS — a ficha inteira.
 *
 * O prefixo é um código fiscal: mostra-se pelo tipo, não se escreve. Uma
 * série registada na AGT quase não se mexe (nome, descrição e padrão) e não
 * se elimina. Tudo isso é do servidor (`GestaoDeSeries`), o mesmo que o
 * ecrã Livewire chama.
 */

type Forma = {
    document_type: string; series_code: string; name: string; prefix: string; include_year: boolean; next_number: string; number_padding: string;
    is_default: boolean; is_active: boolean; reset_yearly: boolean; description: string; series_year: string; establishment_number: string; invoicing_method: string;
};

export default function Series() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState({ procura: '', tipo: '', page: 1 });
    const [aEditar, porAEditar] = useState<Serie | null>(null);
    const [forma, porForma] = useState<Forma | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aEliminar, porAEliminar] = useState<Serie | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['series', 'opcoes'], queryFn: series.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['series', filtros], queryFn: () => series.lista(filtros), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['series'] }); };

    const gravar = useMutation({
        mutationFn: (f: Forma) => {
            const corpo = { ...f, next_number: Number(f.next_number) || 1, number_padding: Number(f.number_padding) || 6, series_year: Number(f.series_year) || null, invoicing_method: f.invoicing_method || null };
            return aEditar ? series.actualizar(aEditar.id, corpo) : series.guardar(corpo);
        },
        onSuccess: (r) => { feito(r.message); porForma(null); porAEditar(null); porErros({}); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const eliminar = useMutation({ mutationFn: (s: Serie) => series.eliminar(s.id), onSuccess: (r) => { feito(r.message); porAEliminar(null); } });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as séries')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const prefixoDe = (tipo: string) => o.tipos.find((t) => t.valor === tipo)?.prefixo ?? '';

    const abrirNova = () => {
        porAEditar(null); porErros({});
        porForma({ document_type: 'invoice', series_code: '', name: '', prefix: prefixoDe('invoice'), include_year: true, next_number: '1', number_padding: '6', is_default: false, is_active: true, reset_yearly: true, description: '', series_year: String(o.ano), establishment_number: 'SEDE', invoicing_method: o.metodo_padrao });
    };
    const abrirEdicao = (s: Serie) => {
        porAEditar(s); porErros({});
        porForma({ document_type: s.document_type, series_code: s.series_code, name: s.name, prefix: s.prefix, include_year: s.include_year, next_number: String(s.next_number), number_padding: String(s.number_padding), is_default: s.is_default, is_active: s.is_active, reset_yearly: s.reset_yearly, description: s.description ?? '', series_year: String(s.series_year ?? o.ano), establishment_number: s.establishment_number ?? 'SEDE', invoicing_method: s.invoicing_method ?? o.metodo_padrao });
    };

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}
            <AvisoDeErro erro={eliminar.error} />

            <Faixa
                icone="fa-hashtag"
                titulo={t('Séries de Documentos')}
                subtitulo={t('Configure as séries e numeração dos documentos fiscais')}
            />

            {/* O título vive na faixa e só lá: escrito duas vezes, a página
                passa a ter dois cabeçalhos a dizer o mesmo. */}
            <Cartao
                titulo={t('Filtros')}
                icone="fa-filter"
                accoes={o.permissoes.pode_escrever && <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNova}>{t('Nova série')}</Botao>}
            >
                <div className="flex flex-wrap items-end gap-3">
                    <label className="min-w-[14rem] flex-1 text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span><input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder={t('Nome ou código')} className={entrada} /></label>
                    <label className="text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Tipo')}</span>
                        <select value={filtros.tipo} onChange={(e) => porFiltros((f) => ({ ...f, tipo: e.target.value, page: 1 }))} className={entrada}><option value="">{t('Todos')}</option>{o.tipos.map((t) => <option key={t.valor} value={t.valor}>{t.rotulo}</option>)}</select>
                    </label>
                </div>
            </Cartao>

            <Cartao titulo={t('Séries')} icone="fa-list" semPadding accoes={contas && <span className="text-sm text-slate-500">{t(':total no total', { total: contas.total })}</span>}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-semibold">{t('Tipo')}</th><th className="px-4 py-3 font-semibold">{t('Código')}</th><th className="px-4 py-3 font-semibold">{t('Nome')}</th><th className="px-4 py-3 font-semibold">{t('Próximo número')}</th><th className="px-4 py-3 text-right font-semibold">{t('Emitidos')}</th><th className="px-4 py-3 font-semibold">AGT</th><th className="w-28 px-4 py-3"></th></tr></thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={7}>
                                        {lista.isPending
                                            ? <p className="px-4 py-10 text-center text-slate-400"><i className="fas fa-spinner fa-spin mr-2" aria-hidden="true" />{t('A carregar…')}</p>
                                            : <SemNada icone="fa-hashtag" titulo={t('Nenhuma série encontrada')} frase={t('Crie a primeira série de documentos.')} accao={o.permissoes.pode_escrever ? <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNova}>{t('Criar a primeira')}</Botao> : undefined} />}
                                    </td>
                                </tr>
                            )}
                            {linhas.map((s, i) => (
                                <tr key={s.id} className={cls('entra transition-all duration-200 hover:bg-indigo-50/60', !s.is_active && 'text-slate-400')} style={cascata(i)}>
                                    <td className="px-4 py-2">{s.tipo_rotulo}<span className="ml-2 font-mono text-xs text-slate-400">{s.prefix}</span></td>
                                    <td className="px-4 py-2 font-mono font-semibold text-slate-900">{s.series_code}{s.is_default && <span className="ml-2"><Etiqueta cor="aviso" icone="fa-star">{t('Padrão')}</Etiqueta></span>}</td>
                                    <td className="px-4 py-2">{s.name}{!s.is_active && <span className="ml-2 text-xs">{t('(inactiva)')}</span>}</td>
                                    <td className="px-4 py-2 font-mono text-xs text-slate-600">{s.exemplo}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{s.emitidos}</td>
                                    <td className="px-4 py-2">{s.registada ? <Etiqueta cor="primaria" icone="fa-shield">{s.agt_series_id}</Etiqueta> : <Etiqueta icone="fa-clock">{t('Por registar')}</Etiqueta>}</td>
                                    <td className="px-4 py-2 text-right">
                                        {o.permissoes.pode_escrever && (
                                            <span className="flex justify-end gap-1">
                                                <button type="button" onClick={() => abrirEdicao(s)} aria-label={t('Editar série :codigo', { codigo: s.series_code })} className={cls('p-2 text-slate-400 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-pen" aria-hidden="true" /></button>
                                                <button type="button" disabled={s.registada} onClick={() => porAEliminar(s)} title={s.registada ? t('Registada na AGT — não se elimina') : t('Eliminar')} aria-label={t('Eliminar série :codigo', { codigo: s.series_code })} className={cls('p-2 text-slate-400 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button>
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>{t('Página :pagina de :paginas', { pagina: contas.current_page, paginas: contas.last_page })}</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>{t('Anterior')}</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>{t('Seguinte')}</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {forma && <Formulario o={o} forma={forma} registada={aEditar?.registada ?? false} erros={erros} titulo={aEditar ? t('Editar série :codigo', { codigo: aEditar.series_code }) : t('Nova série')} aGravar={gravar.isPending} erroGeral={gravar.error} aoMudar={(f) => porForma({ ...f, prefix: prefixoDe(f.document_type) || f.prefix })} aoFechar={() => { porForma(null); porAEditar(null); }} aoGravar={() => gravar.mutate(forma)} />}

            <Modal aberto={aEliminar !== null} aoFechar={() => porAEliminar(null)} titulo={t('Eliminar a série?')} rodape={<><Botao onClick={() => porAEliminar(null)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={eliminar.isPending} onClick={() => aEliminar && eliminar.mutate(aEliminar)}>{t('Eliminar')}</Botao></>}>
                <p className="text-sm text-slate-700">{tPartes('Vai eliminar a série :codigo. Não há volta.', { codigo: <strong>{aEliminar?.series_code}</strong> })}</p>
            </Modal>
        </div>
    );
}

function Formulario({ o, forma, registada, erros, titulo, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDasSeries; forma: Forma; registada: boolean; erros: Record<string, string[]>; titulo: string; aGravar: boolean; erroGeral: unknown;
    aoMudar: (f: Forma) => void; aoFechar: () => void; aoGravar: () => void;
}) {
    const m = (chave: keyof Forma) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => aoMudar({ ...forma, [chave]: e.target.value });
    const b = (chave: keyof Forma) => (e: React.ChangeEvent<HTMLInputElement>) => aoMudar({ ...forma, [chave]: e.target.checked });
    const temPrefixoFiscal = (o.tipos.find((t) => t.valor === forma.document_type)?.prefixo ?? null) !== null;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={titulo} largura="lg" rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={aGravar} onClick={aoGravar}>{t('Guardar')}</Botao></>}>
            <AvisoDeErro erro={erroGeral} />
            {registada && <p role="status" className={cls('mb-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>{t('Série registada na AGT: só o nome, a descrição e a preferência de padrão se alteram.')}</p>}
            <div className="grid gap-4 sm:grid-cols-3">
                <Campo etiqueta={t('Tipo de documento')} erro={erros.document_type} obrigatorio>
                    <select value={forma.document_type} onChange={m('document_type')} disabled={registada} className={entrada}>{o.tipos.map((t) => <option key={t.valor} value={t.valor}>{t.rotulo}</option>)}</select>
                </Campo>
                {/* O prefixo mostra-se pelo tipo; só os tipos internos o deixam escrever. */}
                <Campo etiqueta={t('Prefixo')} erro={erros.prefix} obrigatorio>
                    <input value={forma.prefix} onChange={m('prefix')} readOnly={temPrefixoFiscal || registada} className={cls(entrada, (temPrefixoFiscal || registada) && 'bg-slate-50 text-slate-500')} />
                </Campo>
                <Campo etiqueta={t('Código')} erro={erros.series_code} obrigatorio><input value={forma.series_code} onChange={m('series_code')} maxLength={10} readOnly={registada} className={cls(entrada, registada && 'bg-slate-50 text-slate-500')} /></Campo>
                <Campo etiqueta={t('Nome')} erro={erros.name} obrigatorio className="sm:col-span-2"><input value={forma.name} onChange={m('name')} maxLength={100} className={entrada} /></Campo>
                <Campo etiqueta={t('Exercício')} erro={erros.series_year}><input type="number" min="2024" max="2099" value={forma.series_year} onChange={m('series_year')} readOnly={registada} className={cls(entrada, 'text-right tabular-nums', registada && 'bg-slate-50 text-slate-500')} /></Campo>
                <Campo etiqueta={t('Próximo número')} erro={erros.next_number} obrigatorio><input type="number" min="1" value={forma.next_number} onChange={m('next_number')} readOnly={registada} className={cls(entrada, 'text-right tabular-nums', registada && 'bg-slate-50 text-slate-500')} /></Campo>
                <Campo etiqueta={t('Dígitos do número')} erro={erros.number_padding} obrigatorio><input type="number" min="1" max="10" value={forma.number_padding} onChange={m('number_padding')} readOnly={registada} className={cls(entrada, 'text-right tabular-nums', registada && 'bg-slate-50 text-slate-500')} /></Campo>
                <Campo etiqueta={t('Estabelecimento')} erro={erros.establishment_number}><input value={forma.establishment_number} onChange={m('establishment_number')} readOnly={registada} className={cls(entrada, registada && 'bg-slate-50 text-slate-500')} /></Campo>
                <Campo etiqueta={t('Método de facturação')} erro={erros.invoicing_method}>
                    <select value={forma.invoicing_method} onChange={m('invoicing_method')} disabled={registada} className={entrada}>{o.metodos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}</select>
                </Campo>
                <Campo etiqueta={t('Descrição')} erro={erros.description} className="sm:col-span-3"><input value={forma.description} onChange={m('description')} className={entrada} /></Campo>
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={forma.include_year} onChange={b('include_year')} disabled={registada} className="h-4 w-4 rounded border-slate-300" />{t('Ano no número')}</label>
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={forma.reset_yearly} onChange={b('reset_yearly')} disabled={registada} className="h-4 w-4 rounded border-slate-300" />{t('Recomeça cada ano')}</label>
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={forma.is_active} onChange={b('is_active')} disabled={registada} className="h-4 w-4 rounded border-slate-300" />{t('Activa')}</label>
                <label className="flex items-center gap-2 text-sm font-semibold text-slate-800 sm:col-span-3"><input type="checkbox" checked={forma.is_default} onChange={b('is_default')} className="h-4 w-4 rounded border-slate-300" />{t('Série padrão deste tipo de documento')}</label>
            </div>
        </Modal>
    );
}
