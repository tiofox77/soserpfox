import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { agt, type Ambiente, type EstadoDaAgt, type OpcoesDaAgt, type Submissao } from '@/api/agt';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { useRecadoComTomNoCanto } from '@/ui/useRecadoNoCanto';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { t, tPartes } from '@/i18n';
import { EstadoNaFaixa, Faixa, SemNada, cascata } from './faixa';

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
    { chave: 'definicoes', rotulo: t('Definições'), icone: 'fa-sliders' },
    { chave: 'chaves', rotulo: t('Chaves'), icone: 'fa-key' },
    { chave: 'series', rotulo: t('Séries'), icone: 'fa-hashtag' },
    { chave: 'submissoes', rotulo: t('Submissões'), icone: 'fa-paper-plane' },
    { chave: 'logs', rotulo: t('Comunicações'), icone: 'fa-list' },
    { chave: 'consulta', rotulo: t('Consultar a AGT'), icone: 'fa-magnifying-glass' },
];

const ESTADOS: Record<string, { rotulo: string; cor: 'neutra' | 'primaria' | 'bom' | 'aviso' | 'perigo' }> = {
    pending: { rotulo: t('Pendente'), cor: 'aviso' },
    submitted: { rotulo: t('Enviada'), cor: 'primaria' },
    validated: { rotulo: t('Validada'), cor: 'bom' },
    rejected: { rotulo: t('Rejeitada'), cor: 'perigo' },
    cancelled: { rotulo: t('Anulada'), cor: 'neutra' },
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
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a configuração AGT')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
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
    const [recado, porRecado] = useRecadoComTomNoCanto<{ tipo: 'bom' | 'mau'; texto: string }>();

    const aVer = e.ambiente;
    const activo = e.definicoes.agt_environment;
    const noActivo = aVer === activo;
    const podeEditar = o.permissoes.pode_editar;

    const feito = (texto: string) => { porRecado({ tipo: 'bom', texto }); void cache.invalidateQueries({ queryKey: ['agt', 'estado'] }); };
    const falhou = (erro: unknown) => porRecado({ tipo: 'mau', texto: typeof erro === 'string' ? erro : erro instanceof ErroDaApi ? erro.message : t('Não foi possível concluir.') });

    const activar = useMutation({ mutationFn: () => agt.activarAmbiente(aVer, empresa), onSuccess: (r) => feito(r.message), onError: falhou });

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border px-4 py-3 text-sm', RAIO, recado.tipo === 'bom' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                    <span><i className={cls('fas mr-2', recado.tipo === 'bom' ? 'fa-circle-check' : 'fa-circle-exclamation')} aria-hidden="true" />{recado.texto}</span>
                    <button type="button" onClick={() => porRecado(null)} aria-label={t('Fechar')} className={cls('p-1', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            {/* A FAIXA DIZ SEMPRE QUAL É O AMBIENTE QUE EMITE, e não o que se
                está a ver. É a resposta à única pergunta que interessa nesta
                página: «os meus documentos estão a ir para a AGT a sério?». */}
            <Faixa
                icone="fa-file-signature"
                cor="laranja"
                titulo={t('AGT Angola')}
                subtitulo={t('Decreto Presidencial n.º 71/25 — Sistema de Facturação Electrónica')}
                accoes={
                    <EstadoNaFaixa icone={activo === 'production' ? 'fa-shield-halved' : 'fa-flask'}>
                        <span data-a-emitir>{t('A emitir em')} <strong>{e.ambientes[activo].rotulo}</strong></span>
                    </EstadoNaFaixa>
                }
            />

            <Cartao
                titulo={t('Contexto de operação')}
                icone="fa-building"
                accoes={o.permissoes.escolhe_empresa && (
                    <label className="text-sm"><span className="sr-only">{t('Empresa')}</span>
                        <select value={empresa ?? e.empresa.id} onChange={(ev) => aoEscolherEmpresa(Number(ev.target.value) || undefined)} className={entrada} aria-label={t('Empresa')}>
                            {o.empresas.map((x) => <option key={x.id} value={x.id}>{x.nome}{x.nif ? ` · ${x.nif}` : ''}</option>)}
                        </select>
                    </label>
                )}
            >
                <p className="mb-4 text-sm text-slate-600">
                    <strong className="text-slate-900">{e.empresa.nome}</strong>{e.empresa.nif && <span className="ml-2 font-mono text-xs text-slate-500">{t('NIF')} {e.empresa.nif}</span>}
                </p>

                {/* Os dois ambientes lado a lado: ver um de cada vez escondia que produção ainda não tem chaves. */}
                <div className="grid gap-3 sm:grid-cols-2">
                    {(Object.keys(e.ambientes) as Ambiente[]).map((amb, i) => {
                        const x = e.ambientes[amb];
                        return (
                            <button key={amb} type="button" onClick={() => aoVer(amb)} aria-pressed={x.a_ver} data-ambiente={amb} style={cascata(i)}
                                className={cls(
                                    'entra flex flex-col items-start gap-2 border p-4 text-left shadow-sm',
                                    RAIO, FOCO,
                                    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md',
                                    x.a_ver ? 'border-indigo-400 bg-indigo-50/60 ring-1 ring-indigo-300' : 'border-slate-200 bg-white hover:border-slate-300',
                                )}>
                                <span className="flex w-full items-center justify-between gap-2">
                                    <span className="flex items-center gap-2 text-base font-bold text-slate-900">
                                        <i className={cls('fas', amb === 'production' ? 'fa-shield-halved text-slate-400' : 'fa-flask text-slate-400')} aria-hidden="true" />
                                        {x.rotulo}
                                    </span>
                                    {x.activo ? <Etiqueta cor="bom" icone="fa-bolt" ponto>{t('Activo')}</Etiqueta> : <Etiqueta ponto>{t('Inactivo')}</Etiqueta>}
                                </span>
                                <span className="flex flex-wrap gap-2 text-xs">
                                    <Etiqueta cor={x.chaves ? 'bom' : 'aviso'} icone={x.chaves ? 'fa-key' : 'fa-triangle-exclamation'}>{x.chaves ? t('Par RSA instalado') : t('Sem par RSA')}</Etiqueta>
                                    <Etiqueta cor={x.produtor ? 'bom' : 'aviso'} icone="fa-building">{x.produtor ? t('Produtor configurado') : t('Produtor por configurar')}</Etiqueta>
                                </span>
                                {x.a_ver && <span className="text-xs font-semibold text-indigo-700"><i className="fas fa-eye mr-1" aria-hidden="true" />{t('a ver este')}</span>}
                            </button>
                        );
                    })}
                </div>

                {!noActivo && (
                    <div className={cls('mt-4 flex flex-wrap items-center justify-between gap-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                        <span><i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />{tPartes('Está a ver :aVer, mas a empresa emite em :activo. As acções que escrevem na AGT ficam fechadas aqui.', { aVer: <strong>{e.ambientes[aVer].rotulo}</strong>, activo: <strong>{e.ambientes[activo].rotulo}</strong> })}</span>
                        {podeEditar && <Botao cor="primaria" tom="solida" icone="fa-bolt" aTrabalhar={activar.isPending} onClick={() => activar.mutate()}>{t('Passar a emitir aqui')}</Botao>}
                    </div>
                )}
            </Cartao>

            <RelatorioDeConformidade e={e} />

            {/* Os separadores, sobre a mesma barra clara do ecrã de sempre: o
                que está aberto fica branco e destacado, e é essa diferença de
                fundo — não só a cor da letra — que se vê de longe. */}
            <div className={cls('flex flex-wrap gap-1 border border-slate-200 bg-slate-50 p-1 shadow-sm', RAIO, aActualizar && 'opacity-60')} role="tablist">
                {SEPARADORES.map((s) => (
                    <button key={s.chave} type="button" role="tab" aria-selected={separador === s.chave} onClick={() => porSeparador(s.chave)}
                        className={cls(
                            'flex items-center gap-2 px-4 py-2 text-sm font-semibold transition-all duration-200',
                            RAIO, FOCO,
                            separador === s.chave ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-indigo-200' : 'text-slate-500 hover:bg-white/70 hover:text-slate-800',
                        )}>
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

/**
 * O RELATÓRIO DE CONFORMIDADE, nos cartões de gradiente da casa.
 *
 * A COR SEGUE O QUE O NÚMERO QUER DIZER, como no resto do sistema: as
 * rejeitadas ficam vermelhas quando existem e cinzentas quando são zero —
 * um cartão vermelho a dizer «0» treina a pessoa a ignorar o vermelho.
 */
function RelatorioDeConformidade({ e }: { e: EstadoDaAgt }) {
    const r = e.relatorio;
    const rejeitadas = r.submissions?.rejected ?? 0;

    const cartoes: Array<{ rotulo: string; valor: string; icone: string; tom: TomDoCartao }> = [
        { rotulo: 'Séries registadas', valor: t(':quantas de :total', { quantas: r.series?.registered ?? 0, total: r.series?.total ?? 0 }), icone: 'fa-hashtag', tom: 'azul' },
        { rotulo: 'Submissões validadas', valor: t(':quantas de :total', { quantas: r.submissions?.validated ?? 0, total: r.submissions?.total ?? 0 }), icone: 'fa-paper-plane', tom: 'verde' },
        { rotulo: 'Rejeitadas', valor: String(rejeitadas), icone: rejeitadas > 0 ? 'fa-circle-xmark' : 'fa-check', tom: rejeitadas > 0 ? 'vermelho' : 'cinza' },
        { rotulo: 'Facturas (30 dias) com ATCUD', valor: t(':quantas de :total', { quantas: r.invoices_30_days?.with_atcud ?? 0, total: r.invoices_30_days?.total ?? 0 }), icone: 'fa-file-invoice', tom: 'indigo' },
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-relatorio>
            {cartoes.map((c, i) => (
                <div key={c.rotulo} className="entra" style={cascata(i)}>
                    <CartaoNumero rotulo={t(c.rotulo)} valor={c.valor} icone={c.icone} tom={c.tom} />
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
        <Cartao titulo={t('Definições')} icone="fa-sliders" accoes={podeEditar && <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar')}</Botao>}>
            <p className="mb-4 text-sm text-slate-500">{t('Guardar aqui nunca muda o ambiente que emite. Isso é o botão «Passar a emitir aqui».')}</p>
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Código CAE (classe)')} erro={erros.agt_eac_code} className="sm:col-span-2">
                    <select value={forma.agt_eac_code} onChange={(ev) => porForma({ ...forma, agt_eac_code: ev.target.value })} disabled={!podeEditar} className={entrada}>
                        <option value="">{t('— sem código —')}</option>
                        {o.cae.map((c) => <option key={c.codigo} value={c.codigo}>{c.codigo} · {c.descricao}</option>)}
                    </select>
                </Campo>
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={forma.agt_auto_submit} disabled={!podeEditar} onChange={(ev) => porForma({ ...forma, agt_auto_submit: ev.target.checked })} className="h-4 w-4 rounded border-slate-300" />{t('Enviar à AGT automaticamente ao emitir')}</label>
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={forma.agt_require_validation} disabled={!podeEditar} onChange={(ev) => porForma({ ...forma, agt_require_validation: ev.target.checked })} className="h-4 w-4 rounded border-slate-300" />{t('Exigir validação prévia')}</label>
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
            <Cartao titulo={t('Chaves de :ambiente', { ambiente: rotulo })} icone="fa-key" accoes={<Botao icone="fa-plug" aTrabalhar={testar.isPending} onClick={() => testar.mutate()}>{t('Testar ligação')}</Botao>}>
                <ul className="grid gap-2 sm:grid-cols-3" data-chaves>
                    <li><Etiqueta cor={e.chaves.publica ? 'bom' : 'aviso'} icone={e.chaves.publica ? 'fa-check' : 'fa-xmark'}>{e.chaves.publica ? t('Chave pública instalada') : t('Chave pública em falta')}</Etiqueta></li>
                    <li><Etiqueta cor={e.chaves.privada ? 'bom' : 'aviso'} icone={e.chaves.privada ? 'fa-check' : 'fa-xmark'}>{e.chaves.privada ? t('Chave privada instalada') : t('Chave privada em falta')}</Etiqueta></li>
                    <li><Etiqueta cor={e.chaves.produtor ? 'bom' : 'aviso'} icone={e.chaves.produtor ? 'fa-check' : 'fa-xmark'}>{e.chaves.produtor ? t('Credenciais do produtor presentes') : t('Credenciais do produtor em falta')}</Etiqueta></li>
                </ul>
                {e.em_falta.length > 0 && <p className="mt-3 text-sm text-amber-800">{t('Falta configurar: :lista.', { lista: e.em_falta.join(', ') })}</p>}
                {ligacao && (
                    <p role="status" className={cls('mt-3 border px-4 py-3 text-sm', RAIO, ligacao.success ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                        {ligacao.success ? t('A AGT respondeu.') : ligacao.error ?? t('A AGT não respondeu.')}
                    </p>
                )}
            </Cartao>

            {podeEditar && (
                <Cartao titulo={t('Instalar o par RSA de :ambiente', { ambiente: rotulo })} icone="fa-file-shield" accoes={<span className="flex gap-2">{(e.chaves.publica || e.chaves.privada) && <Botao cor="perigo" icone="fa-trash" onClick={() => porARemover(true)}>{t('Remover')}</Botao>}<Botao cor="primaria" tom="solida" icone="fa-key" aTrabalhar={guardar.isPending} disabled={!publica || !privada} onClick={() => guardar.mutate()}>{t('Guardar par')}</Botao></span>}>
                    <p className="mb-4 text-sm text-slate-500">{t('O par vem do Portal do Contribuinte, um por ambiente. Instalar as chaves de produção não põe a empresa a emitir por lá.')}</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Chave pública (PEM)')} erro={erros.contributorPublicKey}><textarea value={publica} onChange={(ev) => porPublica(ev.target.value)} rows={8} spellCheck={false} className={cls(entrada, 'font-mono text-xs')} /></Campo>
                        <Campo etiqueta={t('Chave privada (PEM)')} erro={erros.contributorPrivateKey}><textarea value={privada} onChange={(ev) => porPrivada(ev.target.value)} rows={8} spellCheck={false} className={cls(entrada, 'font-mono text-xs')} /></Campo>
                    </div>
                </Cartao>
            )}

            <Modal aberto={aRemover} aoFechar={() => porARemover(false)} titulo={t('Remover as chaves de :ambiente?', { ambiente: rotulo })} rodape={<><Botao onClick={() => porARemover(false)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={remover.isPending} onClick={() => remover.mutate()}>{t('Remover')}</Botao></>}>
                <p className="text-sm text-slate-700">{t('O outro ambiente não é tocado. Sem chaves, nenhum documento é assinado em :ambiente.', { ambiente: rotulo })}</p>
            </Modal>
        </div>
    );
}

function Series({ e, empresa, podeEditar, noActivo, feito, falhou }: { e: EstadoDaAgt; empresa?: number; podeEditar: boolean; noActivo: boolean } & Accoes) {
    const sincronizar = useMutation({ mutationFn: () => agt.sincronizarSeries(e.ambiente, empresa), onSuccess: (r) => feito(r.message), onError: falhou });

    return (
        <Cartao titulo={t('Séries')} icone="fa-hashtag" semPadding accoes={podeEditar && <Botao cor="primaria" tom="solida" icone="fa-rotate" aTrabalhar={sincronizar.isPending} disabled={!noActivo} onClick={() => sincronizar.mutate()}>{noActivo ? t('Sincronizar com a AGT') : t('Sincronizar (só no ambiente activo)')}</Botao>}>
            <table className="w-full text-sm">
                <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-semibold">{t('Código')}</th><th className="px-4 py-3 font-semibold">{t('Nome')}</th><th className="px-4 py-3 font-semibold">{t('Tipo')}</th><th className="px-4 py-3 font-semibold">AGT</th></tr></thead>
                <tbody className="divide-y divide-slate-100">
                    {e.series.length === 0 && <tr><td colSpan={4}><SemNada icone="fa-hashtag" titulo={t('Sem séries activas.')} frase={t('Crie a primeira série de documentos nas Séries de Documentos.')} /></td></tr>}
                    {e.series.map((s, i) => (
                        <tr key={s.id} className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)}><td className="px-4 py-2 font-mono font-semibold text-slate-900">{s.series_code}</td><td className="px-4 py-2">{s.name}</td><td className="px-4 py-2 text-slate-600">{s.document_type}</td><td className="px-4 py-2">{s.registada ? <Etiqueta cor="bom" icone="fa-shield">{s.agt_series_id}</Etiqueta> : <Etiqueta cor="aviso" icone="fa-clock">{t('Por registar')}</Etiqueta>}</td></tr>
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
        <Cartao titulo={t('Submissões')} icone="fa-paper-plane" semPadding accoes={podeEditar && <Botao icone="fa-arrows-rotate" aTrabalhar={actualizar.isPending} onClick={() => actualizar.mutate()}>{t('Actualizar estados')}</Botao>}>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-semibold">{t('Quando')}</th><th className="px-4 py-3 font-semibold">{t('Documento')}</th><th className="px-4 py-3 font-semibold">{t('Estado')}</th><th className="px-4 py-3 font-semibold">{t('Referência / ATCUD')}</th><th className="px-4 py-3 font-semibold">{t('Erro')}</th><th className="px-4 py-3 text-right font-semibold">{t('Tentativas')}</th><th className="w-40 px-4 py-3"></th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {e.submissoes.length === 0 && <tr><td colSpan={7}><SemNada icone="fa-paper-plane" titulo={t('Nada submetido neste ambiente.')} frase={t('Assim que um documento for emitido e comunicado, aparece aqui com o estado que a AGT devolveu.')} /></td></tr>}
                        {e.submissoes.map((s, i) => {
                            const est = ESTADOS[s.status] ?? { rotulo: s.status, cor: 'neutra' as const };
                            return (
                                <tr key={s.id} className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)}>
                                    <td className="whitespace-nowrap px-4 py-2 text-slate-600">{s.quando}</td>
                                    <td className="px-4 py-2 font-mono text-xs">{s.document_type_code} {s.document_number}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={est.cor} ponto>{est.rotulo}</Etiqueta></td>
                                    <td className="px-4 py-2 font-mono text-xs text-slate-600">{s.agt_reference ?? '—'}{s.atcud && <span className="block text-slate-400">{s.atcud}</span>}</td>
                                    <td className="max-w-xs px-4 py-2 text-xs text-red-700">{s.error_message}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{s.retry_count}</td>
                                    <td className="px-4 py-2 text-right">
                                        {podeEditar && s.pode_reenviar && <Botao icone="fa-paper-plane" disabled={!noActivo} aTrabalhar={reenviar.isPending && reenviar.variables?.s.id === s.id} onClick={() => reenviar.mutate({ s, repor: false })}>{t('Reenviar')}</Botao>}
                                        {podeEditar && s.esgotada && <Botao cor="primaria" icone="fa-rotate-left" disabled={!noActivo} aTrabalhar={reenviar.isPending && reenviar.variables?.s.id === s.id} onClick={() => reenviar.mutate({ s, repor: true })}>{t('Repor e reenviar')}</Botao>}
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
        <Cartao titulo={t('Comunicações com a AGT')} icone="fa-list" semPadding>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-semibold">{t('Quando')}</th><th className="px-4 py-3 font-semibold">{t('Serviço')}</th><th className="px-4 py-3 font-semibold">{t('Pedido')}</th><th className="px-4 py-3 font-semibold">{t('Resposta')}</th><th className="px-4 py-3 text-right font-semibold">ms</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {e.logs.length === 0 && <tr><td colSpan={5}><SemNada icone="fa-satellite-dish" titulo={t('Ainda não houve comunicações neste ambiente.')} frase={t('Cada ida à AGT — registar uma série, submeter um documento, consultar — fica registada aqui.')} /></td></tr>}
                        {e.logs.map((l, i) => (
                            <tr key={l.id} className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)}>
                                <td className="whitespace-nowrap px-4 py-2 text-slate-600">{l.quando}</td>
                                <td className="px-4 py-2">{l.service}</td>
                                <td className="px-4 py-2 font-mono text-xs text-slate-600">{l.method} {l.endpoint}</td>
                                <td className="px-4 py-2">{l.success ? <Etiqueta cor="bom" icone="fa-circle-check">{l.response_status ?? 'OK'}</Etiqueta> : <Etiqueta cor="perigo" icone="fa-circle-exclamation">{l.response_status ?? t('Falhou')}</Etiqueta>}{l.error_message && <span className="block text-xs text-red-700">{l.error_message}</span>}</td>
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
        <Cartao titulo={t('Consultar a AGT em :ambiente', { ambiente: e.ambientes[e.ambiente].rotulo })} icone="fa-magnifying-glass" accoes={<Botao cor="primaria" tom="solida" icone="fa-magnifying-glass" aTrabalhar={consultar.isPending} onClick={() => consultar.mutate()}>{t('Consultar')}</Botao>}>
            <AvisoDeErro erro={consultar.error} />
            <div className="grid gap-4 sm:grid-cols-3">
                <Campo etiqueta={t('Operação')} erro={erros.apiOperation}><select value={forma.apiOperation} onChange={m('apiOperation')} className={entrada}>{o.operacoes.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}</select></Campo>
                {forma.apiOperation === 'obterEstado' && <Campo etiqueta={t('Request ID')} erro={erros.apiRequestId} obrigatorio><input value={forma.apiRequestId} onChange={m('apiRequestId')} className={entrada} /></Campo>}
                {forma.apiOperation === 'consultarFactura' && <Campo etiqueta={t('Número do documento')} erro={erros.apiDocumentNo} obrigatorio><input value={forma.apiDocumentNo} onChange={m('apiDocumentNo')} className={entrada} /></Campo>}
                {forma.apiOperation === 'listarFacturas' && <><Campo etiqueta={t('De')} erro={erros.apiDateFrom} obrigatorio><input type="date" value={forma.apiDateFrom} onChange={m('apiDateFrom')} className={entrada} /></Campo><Campo etiqueta={t('Até')} erro={erros.apiDateTo} obrigatorio><input type="date" value={forma.apiDateTo} onChange={m('apiDateTo')} className={entrada} /></Campo></>}
            </div>
            {consultar.data && (
                <pre className={cls('mt-4 max-h-96 overflow-auto border border-slate-200 bg-slate-50 p-4 text-xs text-slate-800', RAIO)} data-resultado>{JSON.stringify(consultar.data.data, null, 2)}</pre>
            )}
        </Cartao>
    );
}
