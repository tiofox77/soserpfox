import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { agt, type Ambiente, type EstadoDaAgt, type OpcoesDaAgt, type Submissao } from '@/api/agt';
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
 * AS CONFIGURAÇÕES AGT — os dois ambientes em separado.
 *
 * Há o ambiente ACTIVO, o que assina e transmite os documentos reais, e há
 * o que se está a VER. Ver produção não põe a empresa em produção: só o
 * botão «Passar a emitir aqui» o faz, e só com as chaves instaladas. Tudo o
 * que fala com a AGT é do servidor (`GestaoAgt`), o mesmo que o ecrã de
 * sempre usa.
 */
type Separador = 'definicoes' | 'chaves' | 'series' | 'submissoes' | 'logs' | 'consulta';

const SEPARADORES: Array<{ chave: Separador; rotulo: string; icone: string }> = [
    { chave: 'definicoes', rotulo: 'Definições', icone: 'fa-sliders' },
    { chave: 'chaves', rotulo: 'Chaves', icone: 'fa-key' },
    { chave: 'series', rotulo: 'Séries', icone: 'fa-hashtag' },
    { chave: 'submissoes', rotulo: 'Submissões', icone: 'fa-paper-plane' },
    { chave: 'logs', rotulo: 'Comunicações', icone: 'fa-list' },
    { chave: 'consulta', rotulo: 'Consultar a AGT', icone: 'fa-magnifying-glass' },
];

const ESTADOS: Record<string, { rotulo: string; cor: 'neutra' | 'primaria' | 'bom' | 'aviso' | 'perigo' }> = {
    pending: { rotulo: 'Pendente', cor: 'aviso' },
    submitted: { rotulo: 'Enviada', cor: 'primaria' },
    validated: { rotulo: 'Validada', cor: 'bom' },
    rejected: { rotulo: 'Rejeitada', cor: 'perigo' },
    cancelled: { rotulo: 'Anulada', cor: 'neutra' },
};

export default function Agt() {
    const [empresa, porEmpresa] = useState<number | undefined>(undefined);
    const [ambiente, porAmbiente] = useState<Ambiente | null>(null);
    const [separador, porSeparador] = useState<Separador>('definicoes');

    const opcoes = useQuery({ queryKey: ['agt', 'opcoes'], queryFn: agt.opcoes, staleTime: 5 * 60_000 });
    // Trocar de ambiente mantém o ecrã de pé com o que já lá estava até chegar o novo.
    const estado = useQuery({ queryKey: ['agt', 'estado', ambiente ?? 'activo', empresa ?? 0], queryFn: () => agt.estado(ambiente, empresa), enabled: opcoes.isSuccess, placeholderData: keepPreviousData });

    if (opcoes.isPending || estado.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError || estado.isError) {
        const erro = opcoes.error ?? estado.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir a configuração AGT</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    return (
        <Painel
            key={`${estado.data.empresa.id}-${estado.data.ambiente}`}
            o={opcoes.data}
            e={estado.data}
            aActualizar={estado.isFetching}
            empresa={empresa}
            separador={separador}
            porSeparador={porSeparador}
            aoEscolherEmpresa={(id) => { porEmpresa(id); porAmbiente(null); }}
            aoVer={porAmbiente}
        />
    );
}

function Painel({ o, e, aActualizar, empresa, separador, porSeparador, aoEscolherEmpresa, aoVer }: {
    o: OpcoesDaAgt; e: EstadoDaAgt; aActualizar: boolean; empresa: number | undefined;
    separador: Separador; porSeparador: (s: Separador) => void;
    aoEscolherEmpresa: (id: number | undefined) => void; aoVer: (a: Ambiente) => void;
}) {
    const cache = useQueryClient();
    const [recado, porRecado] = useState<{ tipo: 'bom' | 'mau'; texto: string } | null>(null);

    const aVer = e.ambiente;
    const activo = e.definicoes.agt_environment;
    const noActivo = aVer === activo;
    const podeEditar = o.permissoes.pode_editar;

    const feito = (texto: string) => { porRecado({ tipo: 'bom', texto }); void cache.invalidateQueries({ queryKey: ['agt', 'estado'] }); };
    const falhou = (erro: unknown) => porRecado({ tipo: 'mau', texto: typeof erro === 'string' ? erro : erro instanceof ErroDaApi ? erro.message : 'Não foi possível concluir.' });

    const activar = useMutation({ mutationFn: () => agt.activarAmbiente(aVer, empresa), onSuccess: (r) => feito(r.message), onError: falhou });

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border px-4 py-3 text-sm', RAIO, recado.tipo === 'bom' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                    <span><i className={cls('fas mr-2', recado.tipo === 'bom' ? 'fa-circle-check' : 'fa-circle-exclamation')} aria-hidden="true" />{recado.texto}</span>
                    <button type="button" onClick={() => porRecado(null)} aria-label="Fechar" className={cls('p-1', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-file-signature text-slate-400" aria-hidden="true" />AGT Angola</span>}
                accoes={o.permissoes.escolhe_empresa && (
                    <label className="text-sm"><span className="sr-only">Empresa</span>
                        <select value={empresa ?? e.empresa.id} onChange={(ev) => aoEscolherEmpresa(Number(ev.target.value) || undefined)} className={entrada} aria-label="Empresa">
                            {o.empresas.map((x) => <option key={x.id} value={x.id}>{x.nome}{x.nif ? ` · ${x.nif}` : ''}</option>)}
                        </select>
                    </label>
                )}
            >
                <p className="mb-4 text-sm text-slate-600">
                    <strong className="text-slate-900">{e.empresa.nome}</strong>{e.empresa.nif && <span className="ml-2 font-mono text-xs text-slate-500">NIF {e.empresa.nif}</span>}
                    <span className="ml-3" data-a-emitir>A emitir em <strong>{e.ambientes[activo].rotulo}</strong></span>
                </p>

                {/* Os dois ambientes lado a lado: ver um de cada vez escondia que produção ainda não tem chaves. */}
                <div className="grid gap-3 sm:grid-cols-2">
                    {(Object.keys(e.ambientes) as Ambiente[]).map((amb) => {
                        const x = e.ambientes[amb];
                        return (
                            <button key={amb} type="button" onClick={() => aoVer(amb)} aria-pressed={x.a_ver} data-ambiente={amb}
                                className={cls('flex flex-col items-start gap-2 border p-4 text-left', RAIO, FOCO, x.a_ver ? 'border-indigo-400 bg-indigo-50/60 ring-1 ring-indigo-300' : 'border-slate-200 bg-white hover:border-slate-300')}>
                                <span className="flex w-full items-center justify-between">
                                    <span className="text-base font-bold text-slate-900">{x.rotulo}</span>
                                    {x.activo ? <Etiqueta cor="bom" icone="fa-bolt">Activo</Etiqueta> : <Etiqueta>Inactivo</Etiqueta>}
                                </span>
                                <span className="flex flex-wrap gap-2 text-xs">
                                    <Etiqueta cor={x.chaves ? 'bom' : 'aviso'} icone={x.chaves ? 'fa-key' : 'fa-triangle-exclamation'}>{x.chaves ? 'Par RSA instalado' : 'Sem par RSA'}</Etiqueta>
                                    <Etiqueta cor={x.produtor ? 'bom' : 'aviso'} icone="fa-building">{x.produtor ? 'Produtor configurado' : 'Produtor por configurar'}</Etiqueta>
                                </span>
                            </button>
                        );
                    })}
                </div>

                {!noActivo && (
                    <div className={cls('mt-4 flex flex-wrap items-center justify-between gap-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                        <span>Está a ver <strong>{e.ambientes[aVer].rotulo}</strong>, mas a empresa emite em <strong>{e.ambientes[activo].rotulo}</strong>. As acções que escrevem na AGT ficam fechadas aqui.</span>
                        {podeEditar && <Botao cor="primaria" tom="solida" icone="fa-bolt" aTrabalhar={activar.isPending} onClick={() => activar.mutate()}>Passar a emitir aqui</Botao>}
                    </div>
                )}
            </Cartao>

            <RelatorioDeConformidade e={e} />

            <div className={cls('flex flex-wrap gap-1 border-b border-slate-200', aActualizar && 'opacity-60')} role="tablist">
                {SEPARADORES.map((s) => (
                    <button key={s.chave} type="button" role="tab" aria-selected={separador === s.chave} onClick={() => porSeparador(s.chave)}
                        className={cls('-mb-px flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-semibold', FOCO, separador === s.chave ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800')}>
                        <i className={cls('fas', s.icone)} aria-hidden="true" />{s.rotulo}
                    </button>
                ))}
            </div>

            {separador === 'definicoes' && <Definicoes o={o} e={e} empresa={empresa} podeEditar={podeEditar} feito={feito} falhou={falhou} />}
            {separador === 'chaves' && <Chaves e={e} empresa={empresa} podeEditar={podeEditar} feito={feito} falhou={falhou} />}
            {separador === 'series' && <Series e={e} empresa={empresa} podeEditar={podeEditar} noActivo={noActivo} feito={feito} falhou={falhou} />}
            {separador === 'submissoes' && <Submissoes e={e} empresa={empresa} podeEditar={podeEditar} noActivo={noActivo} feito={feito} falhou={falhou} />}
            {separador === 'logs' && <Comunicacoes e={e} />}
            {separador === 'consulta' && <Consulta o={o} e={e} empresa={empresa} />}
        </div>
    );
}

function RelatorioDeConformidade({ e }: { e: EstadoDaAgt }) {
    const r = e.relatorio;
    const cartoes = [
        { rotulo: 'Séries registadas', valor: `${r.series?.registered ?? 0} de ${r.series?.total ?? 0}`, icone: 'fa-hashtag' },
        { rotulo: 'Submissões validadas', valor: `${r.submissions?.validated ?? 0} de ${r.submissions?.total ?? 0}`, icone: 'fa-paper-plane' },
        { rotulo: 'Rejeitadas', valor: String(r.submissions?.rejected ?? 0), icone: 'fa-circle-xmark' },
        { rotulo: 'Facturas (30 dias) com ATCUD', valor: `${r.invoices_30_days?.with_atcud ?? 0} de ${r.invoices_30_days?.total ?? 0}`, icone: 'fa-file-invoice' },
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-relatorio>
            {cartoes.map((c) => (
                <div key={c.rotulo} className={cls('flex items-center gap-3 border border-slate-200 bg-white p-4', RAIO)}>
                    <i className={cls('fas text-xl text-slate-300', c.icone)} aria-hidden="true" />
                    <div><p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{c.rotulo}</p><p className="text-lg font-bold tabular-nums text-slate-900">{c.valor}</p></div>
                </div>
            ))}
        </div>
    );
}

type Accoes = { feito: (m: string) => void; falhou: (e: unknown) => void };

function Definicoes({ o, e, empresa, podeEditar, feito, falhou }: { o: OpcoesDaAgt; e: EstadoDaAgt; empresa?: number; podeEditar: boolean } & Accoes) {
    const [forma, porForma] = useState({ agt_auto_submit: e.definicoes.agt_auto_submit, agt_eac_code: e.definicoes.agt_eac_code, agt_require_validation: e.definicoes.agt_require_validation });
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const gravar = useMutation({ mutationFn: () => agt.guardar(forma, empresa), onSuccess: (r) => { feito(r.message); porErros({}); }, onError: (er) => { porErros(er instanceof ErroDaApi ? er.erros : {}); falhou(er); } });

    return (
        <Cartao titulo="Definições" accoes={podeEditar && <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>Guardar</Botao>}>
            <p className="mb-4 text-sm text-slate-500">Guardar aqui nunca muda o ambiente que emite. Isso é o botão «Passar a emitir aqui».</p>
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta="Código CAE (classe)" erro={erros.agt_eac_code} className="sm:col-span-2">
                    <select value={forma.agt_eac_code} onChange={(ev) => porForma({ ...forma, agt_eac_code: ev.target.value })} disabled={!podeEditar} className={entrada}>
                        <option value="">— sem código —</option>
                        {o.cae.map((c) => <option key={c.codigo} value={c.codigo}>{c.codigo} · {c.descricao}</option>)}
                    </select>
                </Campo>
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={forma.agt_auto_submit} disabled={!podeEditar} onChange={(ev) => porForma({ ...forma, agt_auto_submit: ev.target.checked })} className="h-4 w-4 rounded border-slate-300" />Enviar à AGT automaticamente ao emitir</label>
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={forma.agt_require_validation} disabled={!podeEditar} onChange={(ev) => porForma({ ...forma, agt_require_validation: ev.target.checked })} className="h-4 w-4 rounded border-slate-300" />Exigir validação prévia</label>
            </div>
        </Cartao>
    );
}

function Chaves({ e, empresa, podeEditar, feito, falhou }: { e: EstadoDaAgt; empresa?: number; podeEditar: boolean } & Accoes) {
    const [publica, porPublica] = useState('');
    const [privada, porPrivada] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aRemover, porARemover] = useState(false);
    const [ligacao, porLigacao] = useState<{ success?: boolean; error?: string } | null>(null);

    const guardar = useMutation({ mutationFn: () => agt.guardarChaves({ ambiente: e.ambiente, contributorPublicKey: publica, contributorPrivateKey: privada }, empresa), onSuccess: (r) => { feito(r.message); porPublica(''); porPrivada(''); porErros({}); }, onError: (er) => { porErros(er instanceof ErroDaApi ? er.erros : {}); falhou(er); } });
    const remover = useMutation({ mutationFn: () => agt.removerChaves(e.ambiente, empresa), onSuccess: (r) => { feito(r.message); porARemover(false); }, onError: falhou });
    const testar = useMutation({ mutationFn: () => agt.testarLigacao(e.ambiente, empresa), onSuccess: (r) => { porLigacao(r.data); if (r.data.success) feito(r.message); else falhou(r.message); }, onError: falhou });

    const rotulo = e.ambientes[e.ambiente].rotulo;

    return (
        <div className="space-y-4">
            <Cartao titulo={`Chaves de ${rotulo}`} accoes={<Botao icone="fa-plug" aTrabalhar={testar.isPending} onClick={() => testar.mutate()}>Testar ligação</Botao>}>
                <ul className="grid gap-2 sm:grid-cols-3" data-chaves>
                    <li><Etiqueta cor={e.chaves.publica ? 'bom' : 'aviso'} icone={e.chaves.publica ? 'fa-check' : 'fa-xmark'}>Chave pública {e.chaves.publica ? 'instalada' : 'em falta'}</Etiqueta></li>
                    <li><Etiqueta cor={e.chaves.privada ? 'bom' : 'aviso'} icone={e.chaves.privada ? 'fa-check' : 'fa-xmark'}>Chave privada {e.chaves.privada ? 'instalada' : 'em falta'}</Etiqueta></li>
                    <li><Etiqueta cor={e.chaves.produtor ? 'bom' : 'aviso'} icone={e.chaves.produtor ? 'fa-check' : 'fa-xmark'}>Credenciais do produtor {e.chaves.produtor ? 'presentes' : 'em falta'}</Etiqueta></li>
                </ul>
                {e.em_falta.length > 0 && <p className="mt-3 text-sm text-amber-800">Falta configurar: {e.em_falta.join(', ')}.</p>}
                {ligacao && (
                    <p role="status" className={cls('mt-3 border px-4 py-3 text-sm', RAIO, ligacao.success ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                        {ligacao.success ? 'A AGT respondeu.' : ligacao.error ?? 'A AGT não respondeu.'}
                    </p>
                )}
            </Cartao>

            {podeEditar && (
                <Cartao titulo={`Instalar o par RSA de ${rotulo}`} accoes={<span className="flex gap-2">{(e.chaves.publica || e.chaves.privada) && <Botao cor="perigo" icone="fa-trash" onClick={() => porARemover(true)}>Remover</Botao>}<Botao cor="primaria" tom="solida" icone="fa-key" aTrabalhar={guardar.isPending} disabled={!publica || !privada} onClick={() => guardar.mutate()}>Guardar par</Botao></span>}>
                    <p className="mb-4 text-sm text-slate-500">O par vem do Portal do Contribuinte, um por ambiente. Instalar as chaves de produção não põe a empresa a emitir por lá.</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta="Chave pública (PEM)" erro={erros.contributorPublicKey}><textarea value={publica} onChange={(ev) => porPublica(ev.target.value)} rows={8} spellCheck={false} className={cls(entrada, 'font-mono text-xs')} /></Campo>
                        <Campo etiqueta="Chave privada (PEM)" erro={erros.contributorPrivateKey}><textarea value={privada} onChange={(ev) => porPrivada(ev.target.value)} rows={8} spellCheck={false} className={cls(entrada, 'font-mono text-xs')} /></Campo>
                    </div>
                </Cartao>
            )}

            <Modal aberto={aRemover} aoFechar={() => porARemover(false)} titulo={`Remover as chaves de ${rotulo}?`} rodape={<><Botao onClick={() => porARemover(false)}>Cancelar</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={remover.isPending} onClick={() => remover.mutate()}>Remover</Botao></>}>
                <p className="text-sm text-slate-700">O outro ambiente não é tocado. Sem chaves, nenhum documento é assinado em {rotulo}.</p>
            </Modal>
        </div>
    );
}

function Series({ e, empresa, podeEditar, noActivo, feito, falhou }: { e: EstadoDaAgt; empresa?: number; podeEditar: boolean; noActivo: boolean } & Accoes) {
    const sincronizar = useMutation({ mutationFn: () => agt.sincronizarSeries(e.ambiente, empresa), onSuccess: (r) => feito(r.message), onError: falhou });

    return (
        <Cartao titulo="Séries" semPadding accoes={podeEditar && <Botao cor="primaria" tom="solida" icone="fa-rotate" aTrabalhar={sincronizar.isPending} disabled={!noActivo} onClick={() => sincronizar.mutate()}>{noActivo ? 'Sincronizar com a AGT' : 'Sincronizar (só no ambiente activo)'}</Botao>}>
            <table className="w-full text-sm">
                <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-4 py-3 font-semibold">Código</th><th className="px-4 py-3 font-semibold">Nome</th><th className="px-4 py-3 font-semibold">Tipo</th><th className="px-4 py-3 font-semibold">AGT</th></tr></thead>
                <tbody className="divide-y divide-slate-100">
                    {e.series.length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-slate-400">Sem séries activas.</td></tr>}
                    {e.series.map((s) => (
                        <tr key={s.id}><td className="px-4 py-2 font-mono font-semibold text-slate-900">{s.series_code}</td><td className="px-4 py-2">{s.name}</td><td className="px-4 py-2 text-slate-600">{s.document_type}</td><td className="px-4 py-2">{s.registada ? <Etiqueta cor="bom" icone="fa-shield">{s.agt_series_id}</Etiqueta> : <Etiqueta cor="aviso">Por registar</Etiqueta>}</td></tr>
                    ))}
                </tbody>
            </table>
        </Cartao>
    );
}

function Submissoes({ e, empresa, podeEditar, noActivo, feito, falhou }: { e: EstadoDaAgt; empresa?: number; podeEditar: boolean; noActivo: boolean } & Accoes) {
    const actualizar = useMutation({ mutationFn: () => agt.actualizarEstados(empresa), onSuccess: (r) => feito(r.message), onError: falhou });
    const reenviar = useMutation({ mutationFn: ({ s, repor }: { s: Submissao; repor: boolean }) => agt.reenviar(s.id, e.ambiente, repor, empresa), onSuccess: (r) => feito(r.message), onError: falhou });

    return (
        <Cartao titulo="Submissões" semPadding accoes={podeEditar && <Botao icone="fa-arrows-rotate" aTrabalhar={actualizar.isPending} onClick={() => actualizar.mutate()}>Actualizar estados</Botao>}>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-4 py-3 font-semibold">Quando</th><th className="px-4 py-3 font-semibold">Documento</th><th className="px-4 py-3 font-semibold">Estado</th><th className="px-4 py-3 font-semibold">Referência / ATCUD</th><th className="px-4 py-3 font-semibold">Erro</th><th className="px-4 py-3 text-right font-semibold">Tentativas</th><th className="w-40 px-4 py-3"></th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {e.submissoes.length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-slate-400">Nada submetido neste ambiente.</td></tr>}
                        {e.submissoes.map((s) => {
                            const est = ESTADOS[s.status] ?? { rotulo: s.status, cor: 'neutra' as const };
                            return (
                                <tr key={s.id}>
                                    <td className="whitespace-nowrap px-4 py-2 text-slate-600">{s.quando}</td>
                                    <td className="px-4 py-2 font-mono text-xs">{s.document_type_code} {s.document_number}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={est.cor}>{est.rotulo}</Etiqueta></td>
                                    <td className="px-4 py-2 font-mono text-xs text-slate-600">{s.agt_reference ?? '—'}{s.atcud && <span className="block text-slate-400">{s.atcud}</span>}</td>
                                    <td className="max-w-xs px-4 py-2 text-xs text-red-700">{s.error_message}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{s.retry_count}</td>
                                    <td className="px-4 py-2 text-right">
                                        {podeEditar && s.pode_reenviar && <Botao icone="fa-paper-plane" disabled={!noActivo} aTrabalhar={reenviar.isPending && reenviar.variables?.s.id === s.id} onClick={() => reenviar.mutate({ s, repor: false })}>Reenviar</Botao>}
                                        {podeEditar && s.esgotada && <Botao cor="primaria" icone="fa-rotate-left" disabled={!noActivo} aTrabalhar={reenviar.isPending && reenviar.variables?.s.id === s.id} onClick={() => reenviar.mutate({ s, repor: true })}>Repor e reenviar</Botao>}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </Cartao>
    );
}

function Comunicacoes({ e }: { e: EstadoDaAgt }) {
    return (
        <Cartao titulo="Comunicações com a AGT" semPadding>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-4 py-3 font-semibold">Quando</th><th className="px-4 py-3 font-semibold">Serviço</th><th className="px-4 py-3 font-semibold">Pedido</th><th className="px-4 py-3 font-semibold">Resposta</th><th className="px-4 py-3 text-right font-semibold">ms</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {e.logs.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-slate-400">Ainda não houve comunicações neste ambiente.</td></tr>}
                        {e.logs.map((l) => (
                            <tr key={l.id}>
                                <td className="whitespace-nowrap px-4 py-2 text-slate-600">{l.quando}</td>
                                <td className="px-4 py-2">{l.service}</td>
                                <td className="px-4 py-2 font-mono text-xs text-slate-600">{l.method} {l.endpoint}</td>
                                <td className="px-4 py-2">{l.success ? <Etiqueta cor="bom">{l.response_status ?? 'OK'}</Etiqueta> : <Etiqueta cor="perigo">{l.response_status ?? 'Falhou'}</Etiqueta>}{l.error_message && <span className="block text-xs text-red-700">{l.error_message}</span>}</td>
                                <td className="px-4 py-2 text-right tabular-nums text-slate-500">{l.response_time ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Cartao>
    );
}

function Consulta({ o, e, empresa }: { o: OpcoesDaAgt; e: EstadoDaAgt; empresa?: number }) {
    const hoje = new Date().toISOString().slice(0, 10);
    const [forma, porForma] = useState({ apiOperation: 'listarFacturas', apiRequestId: '', apiDocumentNo: '', apiDateFrom: hoje.slice(0, 8) + '01', apiDateTo: hoje });
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const consultar = useMutation({ mutationFn: () => agt.consultar({ ...forma, ambiente: e.ambiente }, empresa), onSuccess: () => porErros({}), onError: (er) => porErros(er instanceof ErroDaApi ? er.erros : {}) });
    const m = (chave: keyof typeof forma) => (ev: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => porForma({ ...forma, [chave]: ev.target.value });

    return (
        <Cartao titulo={`Consultar a AGT em ${e.ambientes[e.ambiente].rotulo}`} accoes={<Botao cor="primaria" tom="solida" icone="fa-magnifying-glass" aTrabalhar={consultar.isPending} onClick={() => consultar.mutate()}>Consultar</Botao>}>
            <AvisoDeErro erro={consultar.error} />
            <div className="grid gap-4 sm:grid-cols-3">
                <Campo etiqueta="Operação" erro={erros.apiOperation}><select value={forma.apiOperation} onChange={m('apiOperation')} className={entrada}>{o.operacoes.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}</select></Campo>
                {forma.apiOperation === 'obterEstado' && <Campo etiqueta="Request ID" erro={erros.apiRequestId} obrigatorio><input value={forma.apiRequestId} onChange={m('apiRequestId')} className={entrada} /></Campo>}
                {forma.apiOperation === 'consultarFactura' && <Campo etiqueta="Número do documento" erro={erros.apiDocumentNo} obrigatorio><input value={forma.apiDocumentNo} onChange={m('apiDocumentNo')} className={entrada} /></Campo>}
                {forma.apiOperation === 'listarFacturas' && <><Campo etiqueta="De" erro={erros.apiDateFrom} obrigatorio><input type="date" value={forma.apiDateFrom} onChange={m('apiDateFrom')} className={entrada} /></Campo><Campo etiqueta="Até" erro={erros.apiDateTo} obrigatorio><input type="date" value={forma.apiDateTo} onChange={m('apiDateTo')} className={entrada} /></Campo></>}
            </div>
            {consultar.data && (
                <pre className={cls('mt-4 max-h-96 overflow-auto border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800', RAIO)} data-resultado>{JSON.stringify(consultar.data.data, null, 2)}</pre>
            )}
        </Cartao>
    );
}
