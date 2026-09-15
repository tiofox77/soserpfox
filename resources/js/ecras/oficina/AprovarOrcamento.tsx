import { useMutation, useQuery } from '@tanstack/react-query';
import { useRef, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { orcamentoPeloLink } from '@/api/oficina';
import { t, tn } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { QuadroDeAssinatura, type QuadroDeAssinaturaRef } from '@/ui/QuadroDeAssinatura';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, dataHora, kz } from '@/ui/tokens';

import { ChapaDaMatricula } from './ChapaDaMatricula';

/**
 * O ORÇAMENTO DA OFICINA, VISTO PELO CLIENTE (15/09/2026, OF-03).
 *
 * A página que o cliente abre pelo link que a oficina lhe mandou — sem conta,
 * sem senha. Vê o carro, o que se encontrou e o que se propõe fazer, aprova ou
 * recusa cada linha, vê o total mudar, escreve o nome e assina com o dedo.
 */
type Decisao = 'approved' | 'declined';

export default function AprovarOrcamento({ token }: { token: string }) {
    const q = useQuery({ queryKey: ['orcamento-pelo-link', token], queryFn: () => orcamentoPeloLink.ver(token), retry: false });
    const [decisoes, porDecisoes] = useState<Record<number, Decisao>>({});
    const [nome, porNome] = useState('');
    const [riscado, porRiscado] = useState(false);
    const quadro = useRef<QuadroDeAssinaturaRef>(null);

    const responder = useMutation({
        mutationFn: () => orcamentoPeloLink.responder(token, decisoes, nome, quadro.current?.imagem() ?? ''),
        meta: { aviso: false },
    });

    const casca = (conteudo: React.ReactNode) => (
        <div className="min-h-screen px-4 py-6 sm:py-10">
            <div className="mx-auto w-full max-w-2xl space-y-4">{conteudo}</div>
            <p className="mt-8 text-center text-xs text-slate-400"><i className="fas fa-lock mr-1" aria-hidden="true" />{t('Página segura da oficina · soserp')}</p>
        </div>
    );

    if (q.isPending) return casca(<Carregando linhas={6} />);
    if (q.isError) {
        return casca(
            <Aviso icone="fa-link-slash" cor="vermelho" titulo={t('Link inválido')}
                frase={q.error instanceof ErroDaApi && q.error.estado === 429 ? t('Demasiadas tentativas. Espere um minuto e volte a abrir o link.') : t('Este link não existe ou foi anulado pela oficina. Peça um novo.')} />,
        );
    }

    const d = q.data;

    if (responder.isSuccess) {
        return casca(
            <Aviso icone="fa-circle-check" cor="verde" titulo={t('Decisão enviada')} frase={responder.data.message}>
                <p className="mt-2 text-sm text-slate-600">{d.empresa.nome}{d.empresa.telefone && <> · <a href={`tel:${d.empresa.telefone}`} className="font-semibold text-indigo-700">{d.empresa.telefone}</a></>}</p>
            </Aviso>,
        );
    }

    const aEspera = d.linhas.filter((l) => l.aprovacao === 'pending');
    const decididas = d.linhas.filter((l) => l.aprovacao !== 'pending');
    const aprovadoAgora = aEspera.filter((l) => decisoes[l.id] === 'approved').reduce((s, l) => s + l.subtotal, 0);
    const faltam = aEspera.filter((l) => !decisoes[l.id]).length;
    const pronto = d.aberto && faltam === 0 && nome.trim() !== '' && riscado;
    const erros = responder.error instanceof ErroDaApi ? Object.values(responder.error.erros ?? {}).flat() : [];

    return casca(
        <>
            {/* A OFICINA E O CARRO */}
            <header className={cls('animate-fade-in overflow-hidden bg-gradient-to-br from-slate-800 via-slate-900 to-indigo-950 p-5 text-white shadow-xl', RAIO_GRANDE)}>
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-200">{t('Orçamento para aprovação')}</p>
                <h1 className="mt-1 text-2xl font-bold">{d.empresa.nome}</h1>
                <div className="mt-4 flex flex-wrap items-center gap-4">
                    {d.ordem.matricula && <ChapaDaMatricula matricula={d.ordem.matricula} />}
                    <div className="min-w-0">
                        <p className="font-semibold">{d.ordem.viatura}</p>
                        <p className="text-sm text-white/70">{d.ordem.dono}{d.ordem.dono && ' · '}<span className="font-mono">{d.ordem.numero}</span></p>
                    </div>
                </div>
            </header>

            {(d.ordem.problema || d.ordem.diagnostico) && (
                <section className={cls('grid gap-3 border border-slate-200 bg-white p-4 sm:grid-cols-2', RAIO_GRANDE)}>
                    {d.ordem.problema && <div><p className="text-xs font-bold uppercase tracking-wide text-red-600"><i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{t('O que nos disse')}</p><p className="mt-1 text-sm text-slate-700">{d.ordem.problema}</p></div>}
                    {d.ordem.diagnostico && <div><p className="text-xs font-bold uppercase tracking-wide text-blue-600"><i className="fas fa-stethoscope mr-1" aria-hidden="true" />{t('O que encontrámos')}</p><p className="mt-1 text-sm text-slate-700">{d.ordem.diagnostico}</p></div>}
                </section>
            )}

            {!d.aberto && (
                <Aviso icone={d.assinado_por ? 'fa-circle-check' : 'fa-clock'} cor={d.assinado_por ? 'verde' : 'ambar'}
                    titulo={d.assinado_por ? t('Orçamento já respondido') : t('Link fechado')}
                    frase={d.assinado_por ? t(':nome respondeu a :quando.', { nome: d.assinado_por, quando: dataHora(d.assinado_em) }) : (d.motivo ?? '')} />
            )}

            {/* O QUE ESPERA PELA SUA DECISÃO */}
            {d.aberto && aEspera.length > 0 && (
                <section className="space-y-2" aria-label={t('À espera da sua decisão')}>
                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <h2 className="text-lg font-bold text-slate-900">{tn(':n trabalho à espera da sua decisão|:n trabalhos à espera da sua decisão', aEspera.length, { n: aEspera.length })}</h2>
                        <button type="button" onClick={() => porDecisoes(Object.fromEntries(aEspera.map((l) => [l.id, 'approved' as Decisao])))}
                            className={cls('text-sm font-semibold text-emerald-700 hover:underline', FOCO, RAIO)}>
                            <i className="fas fa-check-double mr-1" aria-hidden="true" />{t('Aprovar tudo')}
                        </button>
                    </div>
                    <ul className="space-y-2">
                        {aEspera.map((l, i) => {
                            const dec = decisoes[l.id];
                            return (
                                <li key={l.id} style={cascata(i)} className={cls('entra border bg-white p-4 shadow-sm', RAIO_GRANDE, TRANSICAO,
                                    dec === 'approved' ? 'border-emerald-300 ring-2 ring-emerald-100' : dec === 'declined' ? 'border-slate-200 opacity-70' : 'border-amber-300')}>
                                    <div className="flex flex-wrap items-start gap-3">
                                        <span className={cls('grid h-10 w-10 flex-none place-items-center rounded-xl text-white shadow', l.tipo === 'service' ? 'bg-gradient-to-br from-indigo-500 to-purple-600' : 'bg-gradient-to-br from-emerald-500 to-teal-600')}>
                                            <i className={cls('fas', l.tipo === 'service' ? 'fa-screwdriver-wrench' : 'fa-gear')} aria-hidden="true" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className={cls('font-semibold text-slate-900', dec === 'declined' && 'line-through')}>{l.nome}</p>
                                            {l.descricao && <p className="text-sm text-slate-600">{l.descricao}</p>}
                                            <p className="text-xs text-slate-500 tabular-nums">{l.quantidade} × {kz(l.preco)} Kz{l.desconto > 0 && ` · −${l.desconto}%`}</p>
                                        </div>
                                        <p className="text-right text-lg font-bold tabular-nums text-slate-900">{kz(l.subtotal)} <span className="text-xs font-normal text-slate-500">Kz</span></p>
                                    </div>
                                    <div role="radiogroup" aria-label={l.nome} className="mt-3 grid grid-cols-2 gap-2">
                                        {([['approved', 'fa-check', t('Aprovar'), 'bg-emerald-600 text-white ring-emerald-600 shadow-lg shadow-emerald-600/30'], ['declined', 'fa-xmark', t('Recusar'), 'bg-slate-700 text-white ring-slate-700 shadow-lg']] as const).map(([valor, icone, rotulo, activo]) => (
                                            <button key={valor} type="button" role="radio" aria-checked={dec === valor} onClick={() => porDecisoes({ ...decisoes, [l.id]: valor })}
                                                className={cls('flex h-11 items-center justify-center gap-2 text-sm font-bold ring-1 ring-inset', RAIO, TRANSICAO, FOCO,
                                                    dec === valor ? cls(activo, 'scale-[1.02]') : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50')}>
                                                <i className={cls('fas', icone)} aria-hidden="true" />{rotulo}
                                            </button>
                                        ))}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </section>
            )}

            {/* O QUE JÁ ESTÁ DECIDIDO */}
            {decididas.length > 0 && (
                <section className={cls('border border-slate-200 bg-white p-4', RAIO_GRANDE)}>
                    <h2 className="mb-2 text-sm font-bold text-slate-700">{t('Já decidido')}</h2>
                    <ul className="divide-y divide-slate-100">
                        {decididas.map((l) => (
                            <li key={l.id} className="flex items-center gap-3 py-2 text-sm">
                                <i className={cls('fas', l.aprovacao === 'approved' ? 'fa-circle-check text-emerald-500' : 'fa-ban text-slate-400')} aria-hidden="true" />
                                <span className={cls('min-w-0 flex-1', l.aprovacao === 'declined' && 'text-slate-400 line-through')}>{l.nome}</span>
                                <span className="tabular-nums text-slate-600">{kz(l.subtotal)} Kz</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {/* AS CONTAS E A ASSINATURA */}
            {d.aberto && (
                <section className={cls('space-y-4 border border-slate-200 bg-white p-4 shadow-sm', RAIO_GRANDE)}>
                    <dl className="grid grid-cols-2 gap-3 text-sm">
                        <div className={cls('bg-slate-50 p-3', RAIO)}><dt className="text-xs text-slate-500">{t('Já aprovado')}</dt><dd className="text-lg font-bold tabular-nums">{kz(d.contas.aprovado)} Kz</dd></div>
                        <div className={cls('bg-emerald-50 p-3', RAIO)}><dt className="text-xs text-emerald-700">{t('Aprova agora')}</dt><dd className="text-lg font-bold tabular-nums text-emerald-800">{kz(aprovadoAgora)} Kz</dd></div>
                    </dl>
                    <p className="text-xs text-slate-500">{t('Valores sem impostos. A factura final pode incluir o IVA e descontos acordados com a oficina.')}</p>

                    <label className="block text-sm">
                        <span className="mb-1 block font-medium text-slate-700">{t('O seu nome')}</span>
                        <input value={nome} maxLength={150} onChange={(e) => porNome(e.target.value)} autoComplete="name"
                            className={cls('w-full border border-slate-300 px-3 py-2.5 text-base', RAIO, FOCO)} />
                    </label>
                    <div>
                        <span className="mb-1 block text-sm font-medium text-slate-700">{t('A sua assinatura')}</span>
                        <QuadroDeAssinatura ref={quadro} aoRiscar={porRiscado} altura="h-40" />
                        {riscado && <button type="button" onClick={() => quadro.current?.limpar()} className={cls('mt-1 text-xs font-semibold text-slate-500 hover:text-slate-800', FOCO, RAIO)}><i className="fas fa-eraser mr-1" aria-hidden="true" />{t('Limpar')}</button>}
                    </div>

                    {erros.length > 0 && <p role="alert" className={cls('border border-red-200 bg-red-50 p-3 text-sm text-red-800', RAIO)}>{erros[0]}</p>}
                    {responder.isError && erros.length === 0 && <p role="alert" className={cls('border border-red-200 bg-red-50 p-3 text-sm text-red-800', RAIO)}>{responder.error instanceof Error ? responder.error.message : t('Não foi possível enviar.')}</p>}

                    <Botao cor="bom" tom="solida" altura="grande" icone="fa-paper-plane" className="w-full" disabled={!pronto} aTrabalhar={responder.isPending} onClick={() => responder.mutate()}>
                        {faltam > 0 ? tn('Falta decidir :n trabalho|Falta decidir :n trabalhos', faltam, { n: faltam }) : t('Enviar a minha decisão')}
                    </Botao>
                    {d.expira_em && <p className="text-center text-xs text-slate-400">{t('Este link é válido até :data.', { data: dataHora(d.expira_em) })}</p>}
                </section>
            )}
        </>,
    );
}

function Aviso({ icone, cor, titulo, frase, children }: { icone: string; cor: 'verde' | 'vermelho' | 'ambar'; titulo: string; frase: string; children?: React.ReactNode }) {
    const tons = { verde: 'from-emerald-500 to-teal-600', vermelho: 'from-red-500 to-rose-600', ambar: 'from-amber-400 to-orange-500' };
    return (
        <div className={cls('animate-scale-in border border-slate-200 bg-white p-8 text-center shadow-lg', RAIO_GRANDE)}>
            <span className={cls('mx-auto grid h-16 w-16 place-items-center rounded-full bg-gradient-to-br text-2xl text-white shadow-lg', tons[cor])}>
                <i className={cls('fas', icone)} aria-hidden="true" />
            </span>
            <h1 className="mt-4 text-xl font-bold text-slate-900">{titulo}</h1>
            <p className="mt-1 text-slate-600">{frase}</p>
            {children}
        </div>
    );
}
