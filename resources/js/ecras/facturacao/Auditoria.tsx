import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query';

import { auditoria, type Integridade, type Registo } from '@/api/auditoria';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';
import { ACCAO_DA_FAIXA, Faixa, SemNada, cascata } from './faixa';

/**
 * A TRILHA DE AUDITORIA — quem fez o quê, e quando.
 *
 * Só leitura, por construção: a tabela é append-only. Cada linha tem uma
 * frase legível e, ao abrir, os campos que mudaram. A cadeia de hashes
 * verifica-se a pedido, porque é uma varredura de todas as linhas.
 */
const ROTULOS: Record<string, string> = { created: t('Criou'), updated: t('Alterou'), deleted: t('Apagou'), restored: t('Repôs'), exportacao: t('Exportou'), impressao: t('Imprimiu'), login: t('Entrou'), logout: t('Saiu') };
const CORES: Record<string, 'neutra' | 'primaria' | 'bom' | 'aviso' | 'perigo'> = { created: 'bom', updated: 'primaria', deleted: 'perigo', restored: 'aviso', exportacao: 'aviso', impressao: 'neutra' };

export default function Auditoria() {
    const [filtros, porFiltros] = useState({ procura: '', evento: '', canal: '', actor: '', de: '', ate: '', page: 1 });
    const [aberto, porAberto] = useState<Registo | null>(null);
    const [integridade, porIntegridade] = useState<Integridade | null>(null);

    const opcoes = useQuery({ queryKey: ['auditoria', 'opcoes'], queryFn: auditoria.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['auditoria', filtros], queryFn: () => auditoria.lista(filtros), placeholderData: keepPreviousData });
    const verificar = useMutation({ mutationFn: auditoria.integridade, onSuccess: porIntegridade });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a auditoria')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={verificar.error} />

            {/* A cadeia verifica-se a partir da faixa, como no ecrã de sempre:
                é a acção da página, não mais um botão numa barra de filtros. */}
            <Faixa
                icone="fa-clipboard-list"
                cor="neutra"
                titulo={t('Auditoria')}
                subtitulo={t('Quem fez o quê, quando e por que caminho')}
                accoes={
                    <button type="button" onClick={() => verificar.mutate()} disabled={verificar.isPending} className={cls(ACCAO_DA_FAIXA, 'disabled:opacity-60')}>
                        <i className={cls('fas', verificar.isPending ? 'fa-spinner fa-spin' : 'fa-shield-halved')} aria-hidden="true" />
                        {t('Verificar a cadeia')}
                    </button>
                }
            />

            {/* O RESULTADO DA VERIFICAÇÃO FICA À VISTA, e diz-se por ícone e
                por palavras — não só pelo verde ou pelo vermelho. */}
            {integridade && (
                <p role="status" className={cls('flex items-start gap-3 border-2 px-4 py-3.5 text-sm font-medium', RAIO, integridade.ok ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900')} data-integridade>
                    <i className={cls('fas mt-0.5 text-lg', integridade.ok ? 'fa-circle-check' : 'fa-triangle-exclamation')} aria-hidden="true" />
                    <span>{integridade.ok ? t('Cadeia íntegra, verificada às :quando.', { quando: integridade.em }) : t(':total problema(s) na cadeia, verificada às :quando.', { total: integridade.total, quando: integridade.em })}</span>
                </p>
            )}

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <Campo etiqueta={t('Procurar')} className="lg:col-span-2"><input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder={t('Registo, pessoa ou modelo')} className={entrada} /></Campo>
                    <Campo etiqueta={t('Evento')}><select value={filtros.evento} onChange={(e) => porFiltros((f) => ({ ...f, evento: e.target.value, page: 1 }))} className={entrada}><option value="">{t('Todos')}</option>{o.eventos.map((e) => <option key={e} value={e}>{ROTULOS[e] ?? e}</option>)}</select></Campo>
                    <Campo etiqueta={t('Canal')}><select value={filtros.canal} onChange={(e) => porFiltros((f) => ({ ...f, canal: e.target.value, page: 1 }))} className={entrada}><option value="">{t('Todos')}</option>{o.canais.map((c) => <option key={c} value={c}>{c}</option>)}</select></Campo>
                    <Campo etiqueta={t('Quem')}><select value={filtros.actor} onChange={(e) => porFiltros((f) => ({ ...f, actor: e.target.value, page: 1 }))} className={entrada}><option value="">{t('Todos')}</option>{o.actores.map((a) => <option key={a.id} value={a.id}>{a.nome}</option>)}</select></Campo>
                    <div className="flex items-end"><Botao icone="fa-eraser" onClick={() => porFiltros({ procura: '', evento: '', canal: '', actor: '', de: '', ate: '', page: 1 })}>{t('Limpar')}</Botao></div>
                    <Campo etiqueta={t('De')}><input type="date" value={filtros.de} onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value, page: 1 }))} className={entrada} /></Campo>
                    <Campo etiqueta={t('Até')}><input type="date" value={filtros.ate} onChange={(e) => porFiltros((f) => ({ ...f, ate: e.target.value, page: 1 }))} className={entrada} /></Campo>
                </div>
            </Cartao>

            <Cartao titulo={t('Registos')} icone="fa-list" semPadding accoes={contas && <span className="text-sm text-slate-500">{t(':total no total', { total: contas.total })}</span>}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-semibold">{t('Quando')}</th><th className="px-4 py-3 font-semibold">{t('Quem')}</th><th className="px-4 py-3 font-semibold">{t('Evento')}</th><th className="px-4 py-3 font-semibold">{t('O quê')}</th><th className="px-4 py-3 font-semibold">{t('Canal')}</th><th className="w-16 px-4 py-3"></th></tr></thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={6}>
                                        {lista.isPending
                                            ? <p className="px-4 py-10 text-center text-slate-400"><i className="fas fa-spinner fa-spin mr-2" aria-hidden="true" />{t('A carregar…')}</p>
                                            : <SemNada icone="fa-clipboard-list" titulo={t('Sem registos')} frase={t('Nada registado com estes filtros. Ou não houve actividade no período escolhido, ou a auditoria não está a registar.')} />}
                                    </td>
                                </tr>
                            )}
                            {linhas.map((r, i) => (
                                <tr key={r.id} className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)}>
                                    <td className="whitespace-nowrap px-4 py-2 tabular-nums text-slate-600">{r.quando}</td>
                                    <td className="px-4 py-2">{r.quem ?? <span className="text-slate-400">{t('sistema')}</span>}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={CORES[r.evento] ?? 'neutra'} ponto>{ROTULOS[r.evento] ?? r.evento}</Etiqueta></td>
                                    <td className="px-4 py-2"><span className="text-slate-800">{r.frase}</span></td>
                                    <td className="px-4 py-2 text-xs text-slate-500">{r.canal}</td>
                                    <td className="px-4 py-2 text-right"><button type="button" onClick={() => porAberto(r)} aria-label={t('Abrir registo :id', { id: r.id })} className={cls('p-2 text-slate-400 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-eye" aria-hidden="true" /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {/* A meta não traz `from`/`to`: tiram-se da página e do tamanho dela. */}
                {contas && (
                    <Paginacao
                        pagina={contas.current_page}
                        ultima={contas.last_page}
                        aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                        total={contas.total}
                        de={(contas.current_page - 1) * contas.per_page + 1}
                        ate={Math.min(contas.current_page * contas.per_page, contas.total)}
                        aCarregar={lista.isFetching}
                    />
                )}
            </Cartao>

            {aberto && <Detalhe r={aberto} aoFechar={() => porAberto(null)} />}
        </div>
    );
}

function Detalhe({ r, aoFechar }: { r: Registo; aoFechar: () => void }) {
    const q = useQuery({ queryKey: ['auditoria', 'registo', r.id], queryFn: () => auditoria.mostrar(r.id) });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={r.frase} largura="lg" rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}>
            {q.isPending ? <Carregando linhas={4} /> : q.isError ? <AvisoDeErro erro={q.error} /> : (
                <div className="space-y-3">
                    <p className="text-sm text-slate-500">{q.data.data.quando} · {q.data.data.quem ?? t('sistema')} · {q.data.data.canal}{q.data.data.ip ? ` · ${q.data.data.ip}` : ''}</p>
                    {(q.data.data.campos ?? []).length === 0 ? <p className="text-sm text-slate-400">{t('Sem campos alterados.')}</p> : (
                        <table className="w-full text-sm">
                            <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-3 py-2">{t('Campo')}</th><th className="px-3 py-2">{t('Antes')}</th><th className="px-3 py-2">{t('Depois')}</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {(q.data.data.campos ?? []).map((c) => (
                                    <tr key={c.campo}><td className="px-3 py-2 font-medium text-slate-700">{c.rotulo}</td><td className="px-3 py-2 text-red-700 line-through decoration-red-300">{c.antes ?? <span className="text-slate-300">—</span>}</td><td className="px-3 py-2 text-emerald-800">{c.depois ?? <span className="text-slate-300">—</span>}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}
        </Modal>
    );
}
