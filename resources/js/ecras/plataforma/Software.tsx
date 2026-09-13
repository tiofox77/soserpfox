import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type ProdutorAgt, definicoes } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ErroDoEcra, Interruptor, Recado } from './comum';

type Resultado = Record<string, unknown>;

/**
 * AS DEFINIÇÕES DO SOFTWARE — o SOS ERP enquanto produtor certificado pela AGT.
 *
 * O SELECTOR DE AMBIENTE só escolhe qual dos dois conjuntos se está a
 * preencher: não muda o ambiente de nenhuma empresa. A palavra-passe e a chave
 * privada nunca vêm para o ecrã. E a consola da AGT só lê — testar a ligação,
 * listar, consultar e obter o estado; não há botão que submeta um documento.
 */
export default function Software() {
    const fila = useQueryClient();
    const [ambiente, porAmbiente] = useState('sandbox');
    const [recado, porRecado] = useRecadoNoCanto(null);
    const [bloqueios, porBloqueios] = useState<Record<string, boolean>>({});
    const [cred, porCred] = useState({ username: '', password: '', certificacao: '' });
    const [aLimpar, porALimpar] = useState(false);

    const dados = useQuery({ queryKey: ['plataforma', 'software', ambiente], queryFn: () => definicoes.software.ler(ambiente) });

    // «Resetar» (como no ecrã antigo): o formulário volta ao que está guardado,
    // sem gravar nada. Serve para desfazer mudanças feitas por engano.
    const reporBloqueios = () => {
        if (dados.data) porBloqueios(Object.fromEntries(dados.data.bloqueios.map((b) => [b.chave, b.ligado])));
    };
    const reporProdutor = () => {
        if (dados.data) porCred({ username: dados.data.produtor.username, password: '', certificacao: dados.data.produtor.certificacao });
    };

    useEffect(() => {
        reporBloqueios();
        reporProdutor();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [dados.data]);

    const feito = (m: string) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'software'] }); };

    const guardarBloqueios = useMutation({ mutationFn: () => definicoes.software.guardarBloqueios(bloqueios), onSuccess: (r) => feito(r.message) });
    const guardarProdutor = useMutation({ mutationFn: () => definicoes.software.guardarProdutor({ ambiente, ...cred }), onSuccess: (r) => feito(r.message) });
    const limparProdutor = useMutation({ mutationFn: () => definicoes.software.limparProdutor(ambiente), onSuccess: (r) => { porALimpar(false); feito(r.message); } });

    if (dados.isPending) return <Carregando linhas={10} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir as definições do software')} erro={dados.error} />;

    const d = dados.data;
    const p = d.produtor;
    const errosProd = guardarProdutor.error instanceof ErroDaApi ? guardarProdutor.error.erros : {};
    const producao = ambiente === 'production';

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Definições do software')} subtitulo={t('O SOS ERP enquanto produtor de software certificado pela AGT')} icone="fa-microchip" cor="primaria">
                <EstadoNaFaixa icone={d.chaves_saft ? 'fa-key' : 'fa-triangle-exclamation'}>{d.chaves_saft ? t('Chaves do SAF-T instaladas') : t('Chaves do SAF-T por gerar')}</EstadoNaFaixa>
                <EstadoNaFaixa icone={d.certificado_global ? 'fa-certificate' : 'fa-triangle-exclamation'}>{d.certificado_global ? t('Certificado registado') : t('Certificado por registar')}</EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado} aoFechar={() => porRecado(null)} />

            {/* O PRODUTOR — por ambiente. */}
            <section className={cls(CARTAO, 'overflow-hidden')}>
                <header className={cls('flex flex-wrap items-center justify-between gap-3 px-5 py-4 text-white', producao ? 'bg-gradient-to-r from-red-600 to-rose-600' : 'bg-gradient-to-r from-sky-600 to-indigo-600')}>
                    <h2 className="flex items-center gap-2 text-lg font-bold"><i className="fas fa-user-shield" aria-hidden="true" />{t('Produtor de software (AGT)')}</h2>
                    <div className="flex rounded-xl bg-white/20 p-1" role="radiogroup" aria-label={t('Ambiente a configurar')}>
                        {[['sandbox', t('Homologação')], ['production', t('Produção')]].map(([valor, rotulo]) => (
                            <button key={valor} type="button" role="radio" aria-checked={ambiente === valor} onClick={() => porAmbiente(valor ?? 'sandbox')}
                                className={cls('rounded-lg px-3 py-1.5 text-sm font-semibold', TRANSICAO, FOCO, ambiente === valor ? 'bg-white text-slate-900 shadow' : 'text-white hover:bg-white/10')}>
                                {rotulo}
                            </button>
                        ))}
                    </div>
                </header>

                <div className="grid gap-6 p-5 lg:grid-cols-2">
                    <div className="space-y-4">
                        <p className={cls('border px-3 py-2 text-xs', RAIO, 'border-slate-200 bg-slate-50 text-slate-600')}>
                            <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                            {t('Este selector só escolhe qual dos dois conjuntos se está a preencher: não muda o ambiente de nenhuma empresa.')}
                        </p>
                        <AvisoDeErro erro={guardarProdutor.error ?? limparProdutor.error} />
                        <div className="flex flex-wrap gap-2">
                            <Etiqueta cor={p.tem_credenciais ? 'bom' : 'perigo'} ponto>{p.tem_credenciais ? t('Credenciais utilizáveis') : t('Sem credenciais')}</Etiqueta>
                            {p.tem_credenciais && <Etiqueta cor={p.credenciais_proprias ? 'primaria' : 'aviso'}>{p.credenciais_proprias ? t('próprias deste ambiente') : t('herdadas das partilhadas')}</Etiqueta>}
                        </div>
                        <Campo etiqueta={t('Utilizador')} obrigatorio erro={errosProd.username} ajuda={p.username_herdado ? t('A usar agora o herdado: :u', { u: p.username_herdado }) : undefined}>
                            <input className={entrada} autoComplete="off" value={cred.username} placeholder={p.username_herdado} onChange={(e) => porCred({ ...cred, username: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Palavra-passe')} obrigatorio={!p.credenciais_proprias} erro={errosProd.password}
                            ajuda={p.credenciais_proprias ? t('Guardada — deixe vazio para manter.') : t('Obrigatória: este ambiente ainda não tem a sua.')}>
                            <input type="password" autoComplete="new-password" className={entrada} value={cred.password} onChange={(e) => porCred({ ...cred, password: e.target.value })} />
                        </Campo>
                        <Campo etiqueta={t('Número do processo de certificação')} erro={errosProd.certificacao}
                            ajuda={p.certificacao_herdada ? t('A usar agora o partilhado: :n', { n: p.certificacao_herdada }) : t('A AGT emite um por ambiente.')}>
                            <input className={cls(entrada, 'font-mono')} value={cred.certificacao} placeholder={p.certificacao_herdada} onChange={(e) => porCred({ ...cred, certificacao: e.target.value })} />
                        </Campo>
                        <div className="flex flex-wrap justify-end gap-2">
                            {p.credenciais_proprias && <Botao cor="perigo" tom="suave" icone="fa-eraser" onClick={() => porALimpar(true)}>{t('Remover')}</Botao>}
                            <Botao cor="neutra" tom="suave" icone="fa-rotate-left" onClick={reporProdutor}>{t('Resetar')}</Botao>
                            <Botao cor={producao ? 'perigo' : 'primaria'} tom="solida" icone="fa-floppy-disk" aTrabalhar={guardarProdutor.isPending} onClick={() => guardarProdutor.mutate()}>
                                {producao ? t('Guardar para produção') : t('Guardar para homologação')}
                            </Botao>
                        </div>
                    </div>

                    <ChaveDoProdutor chave={p.chave} />
                </div>
            </section>

            <ConsolaAgt empresas={d.empresas} />

            <Cartao titulo={t('Bloquear o apagar de documentos')} icone="fa-lock" subtitulo={t('Em todas as empresas: um documento bloqueado não se apaga, anula-se')}>
                <div className="space-y-4">
                    <AvisoDeErro erro={guardarBloqueios.error} />
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {d.bloqueios.map((b) => (
                            <Interruptor key={b.chave} rotulo={b.rotulo} valor={Boolean(bloqueios[b.chave])} aoMudar={(v) => porBloqueios({ ...bloqueios, [b.chave]: v })} cor="red" />
                        ))}
                    </div>
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" tom="suave" icone="fa-rotate-left" onClick={reporBloqueios}>{t('Resetar')}</Botao>
                        <Botao cor="neutra" tom="suave" icone="fa-toggle-off" onClick={() => porBloqueios(Object.fromEntries(d.bloqueios.map((b) => [b.chave, false])))}>{t('Desligar todos')}</Botao>
                        <Botao cor="bom" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardarBloqueios.isPending} onClick={() => guardarBloqueios.mutate()}>{t('Guardar')}</Botao>
                    </div>
                </div>
            </Cartao>

            <Modal
                aberto={aLimpar}
                aoFechar={() => porALimpar(false)}
                titulo={t('Remover as credenciais do produtor?')}
                subtitulo={producao ? t('Produção') : t('Homologação')}
                icone="fa-eraser"
                cor="perigo"
                largura="sm"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porALimpar(false)}>{t('Deixar estar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-eraser" aTrabalhar={limparProdutor.isPending} onClick={() => limparProdutor.mutate()}>{t('Remover')}</Botao>
                    </div>
                }
            >
                <p className="text-sm text-slate-700">{t('Este ambiente passa a usar as credenciais partilhadas, se as houver. Sem elas, as empresas neste ambiente deixam de comunicar com a AGT.')}</p>
            </Modal>
        </div>
    );
}

function ChaveDoProdutor({ chave }: { chave: ProdutorAgt['chave'] }) {
    return (
        <div className={cls('space-y-3 border-2 p-4', RAIO, chave && !chave.erro ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40')}>
            <h3 className="flex items-center gap-2 font-bold text-slate-900"><i className="fas fa-key text-emerald-600 icon-float" aria-hidden="true" />{t('Chave RSA do produtor')}</h3>
            {!chave ? (
                <p className="text-sm text-amber-800">{t('Não há chave instalada neste ambiente. Gera-se fora deste ecrã (comando agt:producer-key).')}</p>
            ) : chave.erro ? (
                <p className="text-sm text-red-700">{chave.erro}</p>
            ) : (
                <dl className="grid grid-cols-2 gap-2 text-sm">
                    <div><dt className="text-xs text-slate-500">{t('Tipo')}</dt><dd className="font-semibold">{chave.tipo} · {chave.bits} bits</dd></div>
                    <div><dt className="text-xs text-slate-500">{t('Actualizada')}</dt><dd className="font-semibold">{chave.actualizada}</dd></div>
                    <div className="col-span-2"><dt className="text-xs text-slate-500">{t('Impressão digital')}</dt><dd className="break-all font-mono text-xs">{chave.impressao}</dd></div>
                    <div className="col-span-2"><Etiqueta cor={chave.propria ? 'primaria' : 'aviso'}>{chave.propria ? t('Própria deste ambiente') : t('Herdada da partilhada')}</Etiqueta></div>
                </dl>
            )}
            <p className="text-[11px] text-slate-500">{t('Só leitura: esta chave assina os documentos de todas as empresas, e trocá-la por engano aqui parava todas ao mesmo tempo.')}</p>
        </div>
    );
}

function ConsolaAgt({ empresas }: { empresas: Array<{ id: number; nome: string; nif: string | null; ambiente: string; submissao_automatica: boolean; series: number; chave_do_contribuinte: boolean }> }) {
    const [empresa, porEmpresa] = useState<number | null>(empresas[0]?.id ?? null);
    const [ambiente, porAmbiente] = useState(empresas[0]?.ambiente ?? 'sandbox');
    const [op, porOp] = useState({ operacao: 'listarFacturas', pedido: '', documento: '', de: diasAtras(30), ate: diasAtras(0) });
    const [teste, porTeste] = useState<Resultado | null>(null);
    const [resultado, porResultado] = useState<Resultado | null>(null);
    const [recado, porRecado] = useRecadoNoCanto(null);

    const prontidao = useQuery({
        queryKey: ['plataforma', 'software', 'prontidao', empresa],
        queryFn: () => definicoes.software.prontidao(empresa!),
        enabled: empresa !== null,
    });

    useEffect(() => { if (prontidao.data) porAmbiente(prontidao.data.ambiente); }, [prontidao.data]);

    const testar = useMutation({ mutationFn: () => definicoes.software.testarAgt(empresa!, ambiente), onSuccess: porTeste });
    const operar = useMutation({ mutationFn: () => definicoes.software.operacaoAgt({ empresa, ambiente, ...op }), onSuccess: porResultado });
    const aplicar = useMutation({ mutationFn: () => definicoes.software.aplicarAmbiente(empresa!, ambiente), onSuccess: (r) => porRecado(r.message) });

    const errosOp = operar.error instanceof ErroDaApi ? operar.error.erros : {};

    return (
        <Cartao titulo={t('Consola da AGT')} icone="fa-terminal" subtitulo={t('Só leitura: testar, listar, consultar e obter o estado — nada é submetido')}>
            <div className="space-y-5">
                <Recado texto={recado} aoFechar={() => porRecado(null)} />

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                <th className="px-3 py-2">{t('Empresa')}</th><th className="px-3 py-2">{t('Ambiente')}</th><th className="px-3 py-2">{t('Séries')}</th>
                                <th className="px-3 py-2">{t('Chave do contribuinte')}</th><th className="px-3 py-2">{t('Submissão automática')}</th><th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {empresas.map((e) => (
                                <tr key={e.id} className={cls('hover:bg-slate-50', empresa === e.id && 'bg-indigo-50/60')}>
                                    <td className="px-3 py-2"><span className="font-semibold text-slate-800">{e.nome}</span>{e.nif && <span className="ml-2 font-mono text-xs text-slate-500">{e.nif}</span>}</td>
                                    <td className="px-3 py-2"><Etiqueta cor={e.ambiente === 'production' ? 'perigo' : 'primaria'}>{e.ambiente === 'production' ? t('Produção') : t('Homologação')}</Etiqueta></td>
                                    <td className="px-3 py-2 tabular-nums">{e.series}</td>
                                    <td className="px-3 py-2"><i className={cls('fas', e.chave_do_contribuinte ? 'fa-circle-check text-emerald-600' : 'fa-circle-xmark text-slate-300')} aria-hidden="true" /></td>
                                    <td className="px-3 py-2">{e.submissao_automatica ? t('Sim') : t('Não')}</td>
                                    <td className="px-3 py-2 text-right"><Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-vial" onClick={() => { porEmpresa(e.id); porTeste(null); porResultado(null); }}>{t('Preparar teste')}</Botao></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Campo etiqueta={t('Empresa')}>
                        <select className={entrada} value={empresa ?? ''} onChange={(e) => { porEmpresa(e.target.value ? Number(e.target.value) : null); porTeste(null); porResultado(null); }}>
                            {empresas.map((e) => <option key={e.id} value={e.id}>{e.nome}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Ambiente do teste')}>
                        <select className={entrada} value={ambiente} onChange={(e) => porAmbiente(e.target.value)}>
                            <option value="sandbox">{t('Homologação')}</option>
                            <option value="production">{t('Produção')}</option>
                        </select>
                    </Campo>
                    <div className="flex items-end gap-2">
                        <Botao cor="aviso" tom="suave" icone="fa-arrow-right-arrow-left" disabled={!empresa} aTrabalhar={aplicar.isPending} onClick={() => aplicar.mutate()}>{t('Aplicar à empresa')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-plug" disabled={!empresa} aTrabalhar={testar.isPending} onClick={() => testar.mutate()}>{t('Testar ligação')}</Botao>
                    </div>
                </div>

                {prontidao.data && (
                    <ul className="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                        {prontidao.data.itens.map((x) => (
                            <li key={x.chave} className={cls('flex items-center gap-2 border px-3 py-2 text-xs', RAIO, x.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                                <i className={cls('fas', x.ok ? 'fa-circle-check' : 'fa-circle-xmark')} aria-hidden="true" />{x.rotulo}
                            </li>
                        ))}
                    </ul>
                )}

                <AvisoDeErro erro={testar.error ?? aplicar.error} />
                {teste && <Resposta r={teste} titulo={teste.success ? t('Ligação estabelecida') : t('Teste sem sucesso')} />}

                <div className="grid gap-4 border-t border-slate-100 pt-4 lg:grid-cols-4">
                    <Campo etiqueta={t('Operação')}>
                        <select className={entrada} value={op.operacao} onChange={(e) => porOp({ ...op, operacao: e.target.value })}>
                            <option value="listarFacturas">{t('Listar facturas')}</option>
                            <option value="consultarFactura">{t('Consultar factura')}</option>
                            <option value="obterEstado">{t('Obter estado de um pedido')}</option>
                        </select>
                    </Campo>
                    {op.operacao === 'listarFacturas' && (
                        <>
                            <Campo etiqueta={t('De')} erro={errosOp.de}><input type="date" className={entrada} value={op.de} onChange={(e) => porOp({ ...op, de: e.target.value })} /></Campo>
                            <Campo etiqueta={t('Até')} erro={errosOp.ate}><input type="date" className={entrada} value={op.ate} onChange={(e) => porOp({ ...op, ate: e.target.value })} /></Campo>
                        </>
                    )}
                    {op.operacao === 'consultarFactura' && (
                        <Campo etiqueta={t('Número do documento')} erro={errosOp.documento} className="lg:col-span-2"><input className={cls(entrada, 'font-mono')} value={op.documento} onChange={(e) => porOp({ ...op, documento: e.target.value })} /></Campo>
                    )}
                    {op.operacao === 'obterEstado' && (
                        <Campo etiqueta={t('Identificador do pedido')} erro={errosOp.pedido} className="lg:col-span-2"><input className={cls(entrada, 'font-mono')} value={op.pedido} onChange={(e) => porOp({ ...op, pedido: e.target.value })} /></Campo>
                    )}
                    <div className="flex items-end">
                        <Botao cor="primaria" tom="solida" icone="fa-play" disabled={!empresa} aTrabalhar={operar.isPending} onClick={() => operar.mutate()}>{t('Executar')}</Botao>
                    </div>
                </div>

                <AvisoDeErro erro={operar.error} />
                {resultado && <Resposta r={resultado} titulo={String(resultado.operation ?? '')} />}
            </div>
        </Cartao>
    );
}

function Resposta({ r, titulo }: { r: Resultado; titulo: string }) {
    const ok = Boolean(r.success);
    const corpo = r.data ?? r.response;

    return (
        <div className={cls('entra border p-4', RAIO, ok ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50')}>
            <p className={cls('flex items-center gap-2 text-sm font-bold', ok ? 'text-emerald-800' : 'text-red-800')}>
                <i className={cls('fas', ok ? 'fa-circle-check' : 'fa-circle-xmark')} aria-hidden="true" />{titulo}
            </p>
            <p className="mt-1 text-xs text-slate-700">{String(r.message ?? r.error ?? t('Sem detalhe adicional.'))}</p>
            <p className="mt-2 flex flex-wrap gap-x-4 text-[11px] text-slate-500">
                {r.environment ? <span>{t('Ambiente')}: {String(r.environment)}</span> : null}
                {r.http_status ? <span>HTTP: {String(r.http_status)}</span> : null}
                <span>{t('Tempo')}: {String(r.duracao_ms ?? 0)} ms</span>
                <span>{String(r.testado_em ?? '')}</span>
            </p>
            {corpo !== undefined && <pre className="mt-3 max-h-72 overflow-auto rounded-lg bg-slate-900 p-3 text-[10px] text-emerald-300">{JSON.stringify(corpo, null, 2)}</pre>}
        </div>
    );
}

function diasAtras(n: number): string {
    const d = new Date();
    d.setDate(d.getDate() - n);

    return d.toISOString().slice(0, 10);
}
