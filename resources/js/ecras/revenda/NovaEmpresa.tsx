import { useMutation, useQuery } from '@tanstack/react-query';
import { useState, type FormEvent, type ReactNode } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { revenda, type NovaEmpresa as Dados } from '@/api/revenda';
import { t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

import { Cabecalho, Copiar, GRADIENTE_DO_PORTAL, kwanzas } from './comum';
import { Campo, entrada } from './SejaRevendedor';

/**
 * CRIAR A EMPRESA PELO CLIENTE (RV-09).
 *
 * Os mesmos dados que o registo pede (a empresa, o dono, o plano e, se o plano
 * é pago, a transferência), pelo mesmo caminho do servidor. O dono recebe por
 * email os dados de entrada; a senha aparece aqui uma vez, para o caso de o
 * email não chegar.
 */
export default function NovaEmpresa() {
    const opcoes = useQuery({ queryKey: ['revenda', 'opcoes'], queryFn: revenda.opcoes, staleTime: 5 * 60_000 });
    const [d, porD] = useState<Dados>({
        company_name: '', company_nif: '', company_regime: '', company_address: '', company_phone: '', company_email: '',
        name: '', email: '', selected_plan_id: '', payment_reference: '',
    });
    const [comprovativo, porComprovativo] = useState<File | null>(null);
    const campo = <K extends keyof Dados>(k: K, v: Dados[K]) => porD((a) => ({ ...a, [k]: v }));

    const criar = useMutation({
        mutationFn: () => revenda.criarEmpresa({ ...d, company_regime: d.company_regime || opcoes.data?.regime_padrao || '' }, comprovativo),
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-800">{t('Não foi possível abrir o formulário.')}</p>;

    const o = opcoes.data;
    const regime = d.company_regime || o.regime_padrao;
    const plano = o.planos.find((p) => p.id === d.selected_plan_id);
    const pago = plano ? plano.precos.monthly > 0 : false;
    const erros = criar.error instanceof ErroDaApi ? criar.error.erros : {};
    const erro = (k: string) => erros[k]?.[0];
    const submeter = (e: FormEvent) => { e.preventDefault(); criar.mutate(); };

    return (
        <div className="space-y-6">
            <Cabecalho titulo={t('Nova empresa')} subtitulo={t('Crie a conta pelo seu cliente — fica ligada a si')} icone="fa-building-circle-arrow-right" gradiente={GRADIENTE_DO_PORTAL}>
                <a href="/revendedor/empresas" className={cls('inline-flex items-center gap-2 bg-white/15 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/25', RAIO, TRANSICAO, FOCO)}>
                    <i className="fas fa-arrow-left" aria-hidden="true" />{t('Voltar')}
                </a>
            </Cabecalho>

            <form onSubmit={submeter} noValidate className="space-y-6">
                {criar.error && Object.keys(erros).length === 0 && (
                    <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{(criar.error as Error).message}</p>
                )}

                <Seccao i={0} icone="fa-building" titulo={t('A empresa')} cor="from-violet-500 to-purple-600">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo id="ne-nome" rotulo={t('Nome da empresa')} obrigatorio erro={erro('company_name')} icone="fa-signature">
                            <input id="ne-nome" value={d.company_name} onChange={(e) => campo('company_name', e.target.value)} className={entrada(erro('company_name'))} />
                        </Campo>
                        <Campo id="ne-nif" rotulo={t('NIF da empresa')} obrigatorio erro={erro('company_nif')} icone="fa-id-card">
                            <input id="ne-nif" inputMode="numeric" value={d.company_nif} onChange={(e) => campo('company_nif', e.target.value)} placeholder="5000000000" className={entrada(erro('company_nif'))} />
                        </Campo>
                        <Campo id="ne-tel" rotulo={t('Telefone da empresa')} erro={erro('company_phone')} icone="fa-phone">
                            <input id="ne-tel" type="tel" value={d.company_phone} onChange={(e) => campo('company_phone', e.target.value)} className={entrada(erro('company_phone'))} />
                        </Campo>
                        <Campo id="ne-email-emp" rotulo={t('Email da empresa')} erro={erro('company_email')} icone="fa-envelope">
                            <input id="ne-email-emp" type="email" value={d.company_email} onChange={(e) => campo('company_email', e.target.value)} placeholder={t('Se ficar vazio, usa-se o do dono')} className={entrada(erro('company_email'))} />
                        </Campo>
                        <Campo id="ne-morada" rotulo={t('Morada')} erro={erro('company_address')} icone="fa-location-dot" largo>
                            <input id="ne-morada" value={d.company_address} onChange={(e) => campo('company_address', e.target.value)} className={entrada(erro('company_address'))} />
                        </Campo>
                    </div>

                    <fieldset className="mt-5">
                        <legend className="mb-2 text-sm font-semibold text-gray-700"><i className="fas fa-scale-balanced mr-1.5 text-violet-500" aria-hidden="true" />{t('Regime fiscal')} <span className="text-red-500">*</span></legend>
                        <div className="grid gap-3 md:grid-cols-3">
                            {o.regimes.map((r) => (
                                <label key={r.valor} className={cls('flex cursor-pointer flex-col gap-1 border-2 p-3', RAIO, TRANSICAO,
                                    regime === r.valor ? 'border-violet-500 bg-violet-50 shadow-md' : 'border-gray-200 bg-white hover:border-violet-300')}>
                                    <span className="flex items-center gap-2 font-semibold text-gray-900">
                                        <input type="radio" name="ne-regime" value={r.valor} checked={regime === r.valor} onChange={() => campo('company_regime', r.valor)} className="h-4 w-4 text-violet-600" />
                                        {r.rotulo}
                                    </span>
                                    <span className="text-xs text-gray-600">{r.descricao}</span>
                                </label>
                            ))}
                        </div>
                        {erro('company_regime') && <p className="mt-1 text-xs text-red-600">{erro('company_regime')}</p>}
                    </fieldset>
                </Seccao>

                <Seccao i={1} icone="fa-user-tie" titulo={t('O dono da conta')} cor="from-sky-500 to-indigo-600"
                    frase={t('É quem entra no sistema e gere a empresa. Recebe por email o endereço, o email e a senha para entrar.')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo id="ne-dono" rotulo={t('Nome completo')} obrigatorio erro={erro('name')} icone="fa-user">
                            <input id="ne-dono" value={d.name} onChange={(e) => campo('name', e.target.value)} className={entrada(erro('name'))} />
                        </Campo>
                        <Campo id="ne-dono-email" rotulo={t('Email do dono')} obrigatorio erro={erro('email')} icone="fa-at">
                            <input id="ne-dono-email" type="email" value={d.email} onChange={(e) => campo('email', e.target.value)} className={entrada(erro('email'))} />
                        </Campo>
                    </div>
                </Seccao>

                <Seccao i={2} icone="fa-layer-group" titulo={t('O plano')} cor="from-emerald-500 to-teal-600">
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {o.planos.map((p) => {
                            const escolhido = d.selected_plan_id === p.id;
                            return (
                                <button key={p.id} type="button" onClick={() => campo('selected_plan_id', p.id)} aria-pressed={escolhido}
                                    className={cls('relative flex flex-col gap-1 border-2 p-4 text-left', RAIO, TRANSICAO, FOCO,
                                        escolhido ? 'border-emerald-500 bg-emerald-50 shadow-lg' : 'border-gray-200 bg-white hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md')}>
                                    {p.destaque && <span className="absolute -top-2.5 right-3 rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-950">{t('Popular')}</span>}
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="font-bold text-gray-900">{p.nome}</span>
                                        {escolhido && <i className="fas fa-circle-check text-emerald-600" aria-hidden="true" />}
                                    </span>
                                    <span className="text-lg font-black tabular-nums text-emerald-700">
                                        {p.precos.monthly > 0 ? <>{kwanzas(p.precos.monthly)}<span className="text-xs font-semibold text-gray-500"> / {t('mês')}</span></> : t('Grátis')}
                                    </span>
                                    <span className="text-xs text-gray-600">
                                        {[t(':n utilizadores', { n: p.utilizadores }), p.dias_de_teste > 0 ? t(':n dias de teste', { n: p.dias_de_teste }) : null].filter(Boolean).join(' · ')}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    {erro('selected_plan_id') && <p className="mt-2 text-xs text-red-600"><i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{erro('selected_plan_id')}</p>}
                </Seccao>

                {pago && (
                    <Seccao i={3} icone="fa-building-columns" titulo={t('Pagamento')} cor="from-amber-500 to-orange-600"
                        frase={t('Se o cliente já pagou, junte a referência e o comprovativo e o plano activa-se quando confirmarmos. Se não, pode enviá-los depois, na ficha da empresa.')}>
                        <div className={cls('mb-4 grid gap-2 border border-amber-200 bg-amber-50 p-4 text-sm sm:grid-cols-3', RAIO)}>
                            <p><span className="block text-xs text-amber-700">{t('Banco')}</span><b>{o.conta.banco}</b></p>
                            <p><span className="block text-xs text-amber-700">{t('Titular')}</span><b>{o.conta.titular}</b></p>
                            <p className="min-w-0"><span className="block text-xs text-amber-700">IBAN</span><b className="break-all font-mono">{o.conta.iban}</b> <Copiar texto={o.conta.iban} className="ml-1 !px-2 !py-1 !text-xs" /></p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo id="ne-ref" rotulo={t('Referência da transferência')} erro={erro('payment_reference')} icone="fa-hashtag">
                                <input id="ne-ref" value={d.payment_reference} onChange={(e) => campo('payment_reference', e.target.value)} className={entrada(erro('payment_reference'))} />
                            </Campo>
                            <Campo id="ne-comp" rotulo={t('Comprovativo (PDF, JPG ou PNG, até 5 MB)')} erro={erro('payment_proof')} icone="fa-paperclip">
                                <input id="ne-comp" type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => porComprovativo(e.target.files?.[0] ?? null)}
                                    className="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-100 file:px-3 file:py-2 file:font-semibold file:text-violet-700 hover:file:bg-violet-200" />
                            </Campo>
                        </div>
                    </Seccao>
                )}

                <div className="flex flex-wrap justify-end gap-3">
                    <a href="/revendedor/empresas" className={cls('inline-flex items-center gap-2 bg-slate-100 px-5 py-3 font-semibold text-slate-700 hover:bg-slate-200', RAIO, TRANSICAO, FOCO)}>{t('Cancelar')}</a>
                    <button type="submit" disabled={criar.isPending}
                        className={cls('inline-flex items-center gap-2 bg-gradient-to-r from-violet-600 to-emerald-600 px-6 py-3 font-bold text-white shadow-lg hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60', RAIO, TRANSICAO, FOCO)}>
                        <i className={cls('fas', criar.isPending ? 'fa-spinner fa-spin' : 'fa-check')} aria-hidden="true" />
                        {criar.isPending ? t('A criar...') : t('Criar empresa')}
                    </button>
                </div>
            </form>

            {criar.isSuccess && (
                <Modal aberto aoFechar={() => window.location.assign(`/revendedor/empresas/${criar.data.id}`)} titulo={t('Empresa criada')} icone="fa-circle-check" cor="bom" largura="md"
                    rodape={<a href={`/revendedor/empresas/${criar.data.id}`} className={cls('inline-flex items-center gap-2 bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700', RAIO, TRANSICAO, FOCO)}>{t('Abrir a empresa')}<i className="fas fa-arrow-right" aria-hidden="true" /></a>}>
                    <p className="text-sm text-gray-700">{criar.data.message}</p>
                    <p className={cls('mt-3 px-3 py-2 text-sm', RAIO, criar.data.email_enviado ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900')}>
                        <i className={cls('fas mr-1.5', criar.data.email_enviado ? 'fa-envelope-circle-check' : 'fa-triangle-exclamation')} aria-hidden="true" />
                        {criar.data.email_enviado ? t('Enviámos os dados de entrada para :email.', { email: d.email }) : t('O email não saiu. Entregue estes dados ao dono da empresa.')}
                    </p>
                    <dl className={cls('mt-4 grid gap-3 border border-gray-200 bg-gray-50 p-4 text-sm', RAIO)}>
                        <div><dt className="text-xs text-gray-500">{t('Email')}</dt><dd className="font-semibold">{d.email}</dd></div>
                        <div>
                            <dt className="text-xs text-gray-500">{t('Senha')}</dt>
                            <dd className="flex flex-wrap items-center gap-2"><span className="font-mono text-lg font-bold tracking-wider">{criar.data.senha}</span><Copiar texto={criar.data.senha} className="!bg-violet-100" /></dd>
                        </div>
                    </dl>
                    <p className="mt-3 text-xs text-gray-500">{t('Esta senha não volta a ser mostrada. O dono deve mudá-la quando entrar.')}</p>
                </Modal>
            )}
        </div>
    );
}

function Seccao({ i, icone, titulo, frase, cor, children }: { i: number; icone: string; titulo: string; frase?: string; cor: string; children: ReactNode }) {
    return (
        <section style={cascata(i)} className={cls(CARTAO, 'entra p-6')}>
            <header className="mb-5 flex items-start gap-3">
                <span className={cls('grid h-11 w-11 shrink-0 place-items-center bg-gradient-to-br text-white shadow', RAIO, cor)}>
                    <i className={cls('fas icon-float', icone)} aria-hidden="true" />
                </span>
                <div>
                    <h2 className="text-lg font-bold text-gray-900">{titulo}</h2>
                    {frase && <p className="text-sm text-gray-600">{frase}</p>}
                </div>
            </header>
            {children}
        </section>
    );
}
