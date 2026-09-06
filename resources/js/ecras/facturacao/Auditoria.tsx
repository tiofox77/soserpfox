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
import { FOCO, RAIO, cls } from '@/ui/tokens';

/**
 * A TRILHA DE AUDITORIA — quem fez o quê, e quando.
 *
 * Só leitura, por construção: a tabela é append-only. Cada linha tem uma
 * frase legível e, ao abrir, os campos que mudaram. A cadeia de hashes
 * verifica-se a pedido, porque é uma varredura de todas as linhas.
 */
const ROTULOS: Record<string, string> = { created: 'Criou', updated: 'Alterou', deleted: 'Apagou', restored: 'Repôs', exportacao: 'Exportou', impressao: 'Imprimiu', login: 'Entrou', logout: 'Saiu' };
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
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir a auditoria</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={verificar.error} />

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-clipboard-list text-slate-400" aria-hidden="true" />Auditoria</span>}
                accoes={<Botao icone="fa-link" aTrabalhar={verificar.isPending} onClick={() => verificar.mutate()}>Verificar a cadeia</Botao>}
            >
                {integridade && (
                    <p role="status" className={cls('mb-4 border px-4 py-3 text-sm', RAIO, integridade.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')} data-integridade>
                        {integridade.ok ? `Cadeia íntegra, verificada às ${integridade.em}.` : `${integridade.total} problema(s) na cadeia, verificada às ${integridade.em}.`}
                    </p>
                )}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <Campo etiqueta="Procurar" className="lg:col-span-2"><input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder="Registo, pessoa ou modelo" className={entrada} /></Campo>
                    <Campo etiqueta="Evento"><select value={filtros.evento} onChange={(e) => porFiltros((f) => ({ ...f, evento: e.target.value, page: 1 }))} className={entrada}><option value="">Todos</option>{o.eventos.map((e) => <option key={e} value={e}>{ROTULOS[e] ?? e}</option>)}</select></Campo>
                    <Campo etiqueta="Canal"><select value={filtros.canal} onChange={(e) => porFiltros((f) => ({ ...f, canal: e.target.value, page: 1 }))} className={entrada}><option value="">Todos</option>{o.canais.map((c) => <option key={c} value={c}>{c}</option>)}</select></Campo>
                    <Campo etiqueta="Quem"><select value={filtros.actor} onChange={(e) => porFiltros((f) => ({ ...f, actor: e.target.value, page: 1 }))} className={entrada}><option value="">Todos</option>{o.actores.map((a) => <option key={a.id} value={a.id}>{a.nome}</option>)}</select></Campo>
                    <div className="flex items-end"><Botao icone="fa-eraser" onClick={() => porFiltros({ procura: '', evento: '', canal: '', actor: '', de: '', ate: '', page: 1 })}>Limpar</Botao></div>
                    <Campo etiqueta="De"><input type="date" value={filtros.de} onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value, page: 1 }))} className={entrada} /></Campo>
                    <Campo etiqueta="Até"><input type="date" value={filtros.ate} onChange={(e) => porFiltros((f) => ({ ...f, ate: e.target.value, page: 1 }))} className={entrada} /></Campo>
                </div>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-4 py-3 font-semibold">Quando</th><th className="px-4 py-3 font-semibold">Quem</th><th className="px-4 py-3 font-semibold">Evento</th><th className="px-4 py-3 font-semibold">O quê</th><th className="px-4 py-3 font-semibold">Canal</th><th className="w-16 px-4 py-3"></th></tr></thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && <tr><td colSpan={6} className="px-4 py-10 text-center text-slate-400">{lista.isPending ? 'A carregar…' : 'Nada registado com estes filtros.'}</td></tr>}
                            {linhas.map((r) => (
                                <tr key={r.id}>
                                    <td className="whitespace-nowrap px-4 py-2 tabular-nums text-slate-600">{r.quando}</td>
                                    <td className="px-4 py-2">{r.quem ?? <span className="text-slate-400">sistema</span>}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={CORES[r.evento] ?? 'neutra'}>{ROTULOS[r.evento] ?? r.evento}</Etiqueta></td>
                                    <td className="px-4 py-2"><span className="text-slate-800">{r.frase}</span></td>
                                    <td className="px-4 py-2 text-xs text-slate-500">{r.canal}</td>
                                    <td className="px-4 py-2 text-right"><button type="button" onClick={() => porAberto(r)} aria-label={`Abrir registo ${r.id}`} className={cls('p-2 text-slate-400 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-eye" aria-hidden="true" /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>Página {contas.current_page} de {contas.last_page} · {contas.total} registos</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>Anterior</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>Seguinte</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {aberto && <Detalhe r={aberto} aoFechar={() => porAberto(null)} />}
        </div>
    );
}

function Detalhe({ r, aoFechar }: { r: Registo; aoFechar: () => void }) {
    const q = useQuery({ queryKey: ['auditoria', 'registo', r.id], queryFn: () => auditoria.mostrar(r.id) });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={r.frase} largura="lg" rodape={<Botao onClick={aoFechar}>Fechar</Botao>}>
            {q.isPending ? <Carregando linhas={4} /> : q.isError ? <AvisoDeErro erro={q.error} /> : (
                <div className="space-y-3">
                    <p className="text-sm text-slate-500">{q.data.data.quando} · {q.data.data.quem ?? 'sistema'} · {q.data.data.canal}{q.data.data.ip ? ` · ${q.data.data.ip}` : ''}</p>
                    {(q.data.data.campos ?? []).length === 0 ? <p className="text-sm text-slate-400">Sem campos alterados.</p> : (
                        <table className="w-full text-sm">
                            <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-3 py-2">Campo</th><th className="px-3 py-2">Antes</th><th className="px-3 py-2">Depois</th></tr></thead>
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
