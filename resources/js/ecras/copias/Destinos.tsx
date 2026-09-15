import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';

import type { Destino, FicheiroRemoto, Fornecedor, apiDasCopias } from '@/api/copias';
import { ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

import { dataHora, relativo, tamanho } from './comum';

type Api = ReturnType<typeof apiDasCopias>;

type Formulario = {
    id: number | null;
    tipo: string;
    nome: string;
    pasta: string;
    manter: number;
    activo: boolean;
    configuracao: Record<string, string | number | boolean | null>;
};

/**
 * OS DESTINOS — para onde vão as cópias além do servidor.
 *
 * O formulário desenha-se a partir do catálogo que vem do servidor: cada
 * fornecedor diz os campos que pede, se são segredos e a ajuda de cada um. As
 * senhas nunca chegam ao ecrã — vêm «••••••••» quando estão gravadas, e deixar
 * assim ao editar mantém a que lá está.
 */
export function Destinos({ api, destinos, fornecedores, retorno, ambito, aoMudar, aRestaurarDoDestino }: {
    api: Api;
    destinos: Destino[];
    fornecedores: Record<string, Fornecedor>;
    retorno: string;
    ambito: 'plataforma' | 'empresa';
    aoMudar: () => void;
    aRestaurarDoDestino: (destino: Destino, ficheiro: FicheiroRemoto) => void;
}) {
    const [escolher, porEscolher] = useState(false);
    const [form, porForm] = useState<Formulario | null>(null);
    const [aApagar, porAApagar] = useState<Destino | null>(null);
    const [aVer, porAVer] = useState<Destino | null>(null);
    const [ficheiros, porFicheiros] = useState<FicheiroRemoto[] | null>(null);

    const guardar = useMutation({
        mutationFn: (f: Formulario) => {
            const dados = { tipo: f.tipo, nome: f.nome, pasta: f.pasta, manter: f.manter, activo: f.activo, configuracao: f.configuracao };

            return f.id ? api.destinos.guardar(f.id, dados) : api.destinos.criar(dados);
        },
        onSuccess: () => { porForm(null); aoMudar(); },
    });
    const testar = useMutation({ mutationFn: (id: number) => api.destinos.testar(id), onSettled: aoMudar });
    const apagar = useMutation({ mutationFn: (id: number) => api.destinos.apagar(id), onSuccess: () => { porAApagar(null); aoMudar(); } });
    const ligar = useMutation({
        mutationFn: (id: number) => api.destinos.ligar(id),
        onSuccess: (r) => { window.location.href = r.url; },
        meta: { aviso: false },
    });
    const listar = useMutation({
        mutationFn: (id: number) => api.destinos.ficheiros(id),
        onSuccess: (r) => porFicheiros(r.ficheiros),
        meta: { aviso: false },
    });

    const abrirNovo = (tipo: string) => {
        const f = fornecedores[tipo];
        if (!f) return;
        const configuracao: Formulario['configuracao'] = {};
        f.campos.forEach((c) => { if (c.chave !== 'pasta') configuracao[c.chave] = c.omissao ?? (c.tipo === 'checkbox' ? false : ''); });
        const pasta = f.campos.find((c) => c.chave === 'pasta')?.omissao;
        porEscolher(false);
        porForm({ id: null, tipo, nome: f.nome, pasta: typeof pasta === 'string' ? pasta : '', manter: 10, activo: true, configuracao });
    };

    const abrirEdicao = (d: Destino) => porForm({
        id: d.id, tipo: d.tipo, nome: d.nome, pasta: d.pasta ?? '', manter: d.manter, activo: d.activo, configuracao: { ...d.configuracao },
    });

    const erros = (guardar.error instanceof ErroDaApi ? guardar.error.erros : {}) as Record<string, string[]>;
    const fornecedorDoForm = form ? fornecedores[form.tipo] : null;

    return (
        <section className={cls(CARTAO, 'entra p-5')} style={cascata(2)}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900">
                        <i className="fas fa-cloud-arrow-up text-emerald-500" aria-hidden="true" />{t('Destinos fora do servidor')}
                    </h2>
                    <p className="text-sm text-slate-500">{t('Cada cópia é enviada para todos os destinos activos.')}</p>
                </div>
                <Botao cor="bom" tom="solida" icone="fa-plus" onClick={() => porEscolher(true)}>{t('Adicionar destino')}</Botao>
            </div>

            {destinos.length === 0 ? (
                <div className="mt-4">
                    <SemNada icone="fa-cloud" titulo={t('Ainda não há destinos')} frase={t('Ligue um Google Drive, OneDrive, Dropbox, FTP, S3 ou WebDAV para as cópias sobreviverem ao servidor.')} />
                </div>
            ) : (
                <ul className="mt-4 grid gap-3 lg:grid-cols-2">
                    {destinos.map((d, i) => {
                        const precisaLigar = Boolean(d.oauth) && !d.ligado;

                        return (
                            <li key={d.id} className={cls('group min-w-0 border border-slate-200 p-4', RAIO, TRANSICAO, 'hover:-translate-y-0.5 hover:shadow-md', !d.activo && 'opacity-60')} style={cascata(i)}>
                                <div className="flex items-start gap-3">
                                    <span className={cls('grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br text-lg text-white shadow transition-transform duration-300 group-hover:scale-110', d.cor)}>
                                        <i className={d.icone} aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-semibold text-slate-900">{d.nome}</p>
                                        <p className="truncate text-xs text-slate-500">{d.fornecedor}{d.pasta ? ` · ${d.pasta}` : ''} · {t('guarda :n', { n: d.manter })}</p>
                                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                                            {!d.activo && <Etiqueta cor="neutra" icone="fa-pause">{t('Em pausa')}</Etiqueta>}
                                            {precisaLigar
                                                ? <Etiqueta cor="aviso" icone="fa-link-slash">{t('Por ligar')}</Etiqueta>
                                                : d.ultimo_erro
                                                    ? <Etiqueta cor="perigo" icone="fa-triangle-exclamation">{t('Com erro')}</Etiqueta>
                                                    : d.ligado
                                                        ? <Etiqueta cor="bom" ponto>{t('Ligado')}</Etiqueta>
                                                        : <Etiqueta cor="neutra" icone="fa-vial">{t('Por testar')}</Etiqueta>}
                                            {d.testado_em && <span className="text-[11px] text-slate-400">{t('testado :quando', { quando: relativo(d.testado_em) })}</span>}
                                        </div>
                                        {d.ultimo_erro && !precisaLigar && <p className="mt-1.5 line-clamp-2 break-words text-xs text-red-600" title={d.ultimo_erro}>{d.ultimo_erro}</p>}
                                    </div>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-1.5 border-t border-slate-100 pt-3">
                                    {d.oauth && (
                                        <Botao cor={precisaLigar ? 'aviso' : 'neutra'} tom={precisaLigar ? 'solida' : 'suave'} altura="pequeno" icone="fa-link"
                                            aTrabalhar={ligar.isPending && ligar.variables === d.id} onClick={() => ligar.mutate(d.id)}>
                                            {d.ligado ? t('Voltar a ligar') : t('Ligar conta')}
                                        </Botao>
                                    )}
                                    <Botao cor="primaria" altura="pequeno" icone="fa-vial" aTrabalhar={testar.isPending && testar.variables === d.id} onClick={() => testar.mutate(d.id)}>{t('Testar')}</Botao>
                                    <Botao cor="neutra" altura="pequeno" icone="fa-folder-open" aTrabalhar={listar.isPending && listar.variables === d.id}
                                        onClick={() => { porAVer(d); porFicheiros(null); listar.mutate(d.id); }}>{t('Ver cópias lá')}</Botao>
                                    <Botao cor="neutra" altura="pequeno" icone="fa-pen" onClick={() => abrirEdicao(d)}>{t('Editar')}</Botao>
                                    <Botao cor="perigo" altura="pequeno" icone="fa-trash" onClick={() => porAApagar(d)}>{t('Remover')}</Botao>
                                </div>
                                <AvisoDeErro erro={ligar.variables === d.id ? ligar.error : null} />
                            </li>
                        );
                    })}
                </ul>
            )}

            {/* ─── Escolher o fornecedor ─────────────────────────────── */}
            <Modal aberto={escolher} aoFechar={() => porEscolher(false)} titulo={t('Adicionar destino')} subtitulo={t('Para onde vão as cópias?')} icone="fa-cloud-arrow-up" cor="bom" largura="lg">
                <div className="grid gap-3 sm:grid-cols-2">
                    {Object.entries(fornecedores).map(([tipo, f], i) => {
                        const bloqueado = !f.disponivel || (Boolean(f.oauth) && !f.configurado);

                        return (
                            <button key={tipo} type="button" disabled={bloqueado} onClick={() => abrirNovo(tipo)} style={cascata(i)}
                                className={cls('entra group flex items-start gap-3 border border-slate-200 p-3 text-left', RAIO, TRANSICAO, FOCO,
                                    bloqueado ? 'cursor-not-allowed opacity-50' : 'hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md')}>
                                <span className={cls('grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br text-white shadow transition-transform duration-300 group-hover:scale-110', f.cor)}>
                                    <i className={f.icone} aria-hidden="true" />
                                </span>
                                <span className="min-w-0">
                                    <span className="block font-semibold text-slate-900">{f.nome}</span>
                                    <span className="block text-xs text-slate-500">{f.descricao}</span>
                                    {!f.disponivel && <span className="mt-1 block text-[11px] font-semibold text-red-600">{t('Este servidor não suporta este protocolo.')}</span>}
                                    {f.disponivel && f.oauth && !f.configurado && (
                                        <span className="mt-1 block text-[11px] font-semibold text-amber-700">
                                            {ambito === 'plataforma' ? t('Configure primeiro a aplicação OAuth (mais abaixo).') : t('O dono da plataforma ainda não activou este fornecedor.')}
                                        </span>
                                    )}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </Modal>

            {/* ─── O formulário do destino ───────────────────────────── */}
            <Modal
                aberto={form !== null}
                aoFechar={() => porForm(null)}
                titulo={form?.id ? t('Editar destino') : t('Novo destino')}
                subtitulo={fornecedorDoForm?.nome}
                icone="fa-cloud"
                cor="bom"
                largura="lg"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porForm(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="bom" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} onClick={() => form && guardar.mutate(form)}>{t('Guardar')}</Botao>
                    </div>
                }
            >
                {form && fornecedorDoForm && (
                    <div className="space-y-4">
                        {fornecedorDoForm.oauth && (
                            <p className="flex items-start gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                                <i className="fas fa-circle-info mt-0.5" aria-hidden="true" />
                                <span>{t('Depois de guardar, carregue em «Ligar conta» para autorizar o acesso. A senha da conta nunca é pedida nem guardada aqui.')}</span>
                            </p>
                        )}
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Campo etiqueta={t('Nome')} obrigatorio erro={erros.nome}>
                                <input className={entrada} value={form.nome} maxLength={120} onChange={(e) => porForm({ ...form, nome: e.target.value })} />
                            </Campo>
                            <Campo etiqueta={t('Quantas cópias guardar lá')} obrigatorio erro={erros.manter} ajuda={t('As mais antigas são apagadas do destino.')}>
                                <input type="number" min={1} max={100} className={entrada} value={form.manter} onChange={(e) => porForm({ ...form, manter: Number(e.target.value) })} />
                            </Campo>
                            {fornecedorDoForm.campos.map((c) => {
                                const erro = erros[`configuracao.${c.chave}`] ?? (c.chave === 'pasta' ? erros.pasta : undefined);
                                if (c.chave === 'pasta') {
                                    return (
                                        <Campo key={c.chave} etiqueta={c.rotulo} erro={erro} ajuda={c.ajuda} className="sm:col-span-2">
                                            <input className={entrada} value={form.pasta} maxLength={255} onChange={(e) => porForm({ ...form, pasta: e.target.value })} />
                                        </Campo>
                                    );
                                }
                                const valor = form.configuracao[c.chave];
                                const mudar = (v: string | number | boolean) => porForm({ ...form, configuracao: { ...form.configuracao, [c.chave]: v } });

                                if (c.tipo === 'checkbox') {
                                    return (
                                        <label key={c.chave} className={cls('flex cursor-pointer items-start gap-3 border border-slate-200 p-3 sm:col-span-2', RAIO, 'hover:bg-slate-50')}>
                                            <input type="checkbox" className="mt-1 h-4 w-4" checked={Boolean(valor)} onChange={(e) => mudar(e.target.checked)} />
                                            <span className="text-sm">
                                                <span className="font-semibold text-slate-800">{c.rotulo}</span>
                                                {c.ajuda && <span className="block text-xs text-slate-500">{c.ajuda}</span>}
                                            </span>
                                        </label>
                                    );
                                }

                                return (
                                    <Campo key={c.chave} etiqueta={c.rotulo} obrigatorio={c.obrigatorio && !(c.segredo && form.id)} erro={erro}
                                        ajuda={c.segredo && form.id ? t('Deixe como está para manter a que foi gravada.') : c.ajuda}
                                        className={c.chave === 'url' || c.chave === 'endpoint' ? 'sm:col-span-2' : undefined}>
                                        {c.tipo === 'select' ? (
                                            <select className={entrada} value={String(valor ?? '')} onChange={(e) => mudar(e.target.value)}>
                                                {c.opcoes?.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                                            </select>
                                        ) : (
                                            <input
                                                className={entrada}
                                                type={c.tipo === 'password' ? 'password' : c.tipo === 'number' ? 'number' : 'text'}
                                                autoComplete={c.segredo ? 'new-password' : 'off'}
                                                value={String(valor ?? '')}
                                                onFocus={(e) => { if (c.segredo && e.target.value === '••••••••') mudar(''); }}
                                                onChange={(e) => mudar(c.tipo === 'number' ? Number(e.target.value) : e.target.value)}
                                            />
                                        )}
                                    </Campo>
                                );
                            })}
                        </div>
                        <label className={cls('flex cursor-pointer items-center gap-3 border border-slate-200 p-3', RAIO, 'hover:bg-slate-50')}>
                            <input type="checkbox" className="h-4 w-4" checked={form.activo} onChange={(e) => porForm({ ...form, activo: e.target.checked })} />
                            <span className="text-sm font-semibold text-slate-800">{t('Activo — as novas cópias vão para aqui')}</span>
                        </label>
                        {fornecedorDoForm.oauth && ambito === 'plataforma' && (
                            <p className="text-xs text-slate-500">{t('Endereço de retorno a registar na aplicação:')} <code className="select-all rounded bg-slate-100 px-1.5 py-0.5">{retorno}</code></p>
                        )}
                        <AvisoDeErro erro={guardar.error} />
                    </div>
                )}
            </Modal>

            {/* ─── Remover ───────────────────────────────────────────── */}
            <Modal aberto={aApagar !== null} aoFechar={() => porAApagar(null)} titulo={t('Remover destino')} icone="fa-trash" cor="perigo"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar.id)}>{t('Remover')}</Botao>
                    </div>
                }>
                <p className="text-sm text-slate-600">{t('As novas cópias deixam de ir para «:nome». As cópias que já lá estão não são apagadas.', { nome: aApagar?.nome ?? '' })}</p>
            </Modal>

            {/* ─── O que está no destino ─────────────────────────────── */}
            <Modal aberto={aVer !== null} aoFechar={() => porAVer(null)} titulo={t('Cópias em :nome', { nome: aVer?.nome ?? '' })}
                subtitulo={t('Para repor mesmo quando o servidor já não tem a cópia.')} icone="fa-folder-open" cor="ciano" largura="lg">
                {listar.isPending && <p className="py-6 text-center text-sm text-slate-500"><i className="fas fa-spinner fa-spin mr-2" aria-hidden="true" />{t('A ler o destino…')}</p>}
                <AvisoDeErro erro={listar.error} />
                {ficheiros && ficheiros.length === 0 && <SemNada icone="fa-folder-open" titulo={t('Sem cópias neste destino')} frase={t('Ainda não foi enviada nenhuma cópia para aqui.')} />}
                {ficheiros && ficheiros.length > 0 && (
                    <ul className="divide-y divide-slate-100">
                        {ficheiros.map((f) => (
                            <li key={f.remoto} className="flex flex-wrap items-center gap-3 py-2.5">
                                <i className={cls('fas', f.nome.endsWith('.soscopia') ? 'fa-lock text-emerald-600' : 'fa-file-zipper text-slate-400')} aria-hidden="true" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-mono text-xs text-slate-700">{f.nome}</p>
                                    <p className="text-[11px] text-slate-400">{dataHora(f.data)} · {tamanho(f.tamanho)}</p>
                                </div>
                                <Botao cor="aviso" altura="pequeno" icone="fa-rotate-left" onClick={() => { if (aVer) aRestaurarDoDestino(aVer, f); porAVer(null); }}>{t('Repor esta')}</Botao>
                            </li>
                        ))}
                    </ul>
                )}
            </Modal>
        </section>
    );
}
