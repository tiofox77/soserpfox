import { useMutation } from '@tanstack/react-query';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';

import { ErroDaApi, criarApi } from '@/api/cliente';
import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls, kz } from '@/ui/tokens';

import { Confirmar } from '../plataforma/comum';

const registo = criarApi('/register');

type Plano = {
    id: number;
    slug: string;
    nome: string;
    descricao: string | null;
    preco: number;
    gratuito: boolean;
    destaque: boolean;
    utilizadores: number;
    empresas: number;
    dias_de_teste: number;
    recusa: string | null;
    com_teste: boolean;
};

type Campos = {
    name: string;
    email: string;
    company_name: string;
    company_nif: string;
    company_regime: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    selected_plan_id: number | null;
    payment_method: string;
    payment_reference: string;
};

type Estado = {
    passo: number;
    autenticado: boolean;
    campos: Campos;
    plano_veio_do_link: boolean;
    passo_antes_da_senha: number | null;
    sem_teste: string | null;
    planos: Plano[];
    guardado_em: string | null;
    aviso: { tipo: 'info' | 'warning'; texto: string } | null;
};

type Regime = { valor: string; rotulo: string; descricao: string; volume: string };

type Props = {
    estado: Estado;
    regimes: Regime[];
    conta: { banco: string; titular: string; iban: string };
    site: string;
    entrar: string;
};

const CAMPO =
    'w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2';

/**
 * O REGISTO DE UMA CONTA NOVA — os quatro passos de sempre: os dados da pessoa,
 * a empresa, o plano e o pagamento.
 *
 * O servidor continua a decidir: cada «Próximo» leva o que está escrito, e a
 * resposta diz em que passo se fica, que planos estão fechados e porquê. O que
 * se escreve guarda-se sozinho na sessão (menos a palavra-passe), para que um
 * F5 a meio não deite nada fora.
 */
export default function Assistente({ estado: inicial, regimes, conta, site, entrar }: Props) {
    const [estado, porEstado] = useState<Estado>(inicial);
    const [campos, porCampos] = useState<Campos>(inicial.campos);
    const [senha, porSenha] = useState({ password: '', password_confirmation: '' });
    const [comprovativo, porComprovativo] = useState<File | null>(null);
    const [termos, porTermos] = useState(false);
    const [aviso, porAviso] = useState(inicial.aviso);
    const [aRecomecar, porARecomecar] = useState(false);
    const [guardadoEm, porGuardadoEm] = useState(inicial.guardado_em);

    const { passo, autenticado } = estado;
    const plano = estado.planos.find((p) => p.id === campos.selected_plan_id) ?? null;
    const naoHaNadaAPagar = plano?.gratuito ?? false;
    const temDireitoATeste = plano?.com_teste ?? false;
    const temPassoDePlano = !estado.plano_veio_do_link;

    const corpo = () => ({ ...campos, ...senha, passo });

    // O que o servidor devolve passa a ser o estado — menos a palavra-passe,
    // que nunca sai do browser para a sessão.
    const aplicar = (novo: Estado) => {
        porEstado(novo);
        porCampos(novo.campos);
        porGuardadoEm(novo.guardado_em);
        porAviso(novo.aviso);
        // O erro de uma acção anterior não fica pendurado no passo seguinte.
        [seguinte, anterior, outroPlano, registar].forEach((m) => m.reset());
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const seguinte = useMutation({ mutationFn: () => registo.criar<Estado>('/seguinte', corpo()), onSuccess: aplicar });
    const anterior = useMutation({ mutationFn: () => registo.criar<Estado>('/anterior', corpo()), onSuccess: aplicar });
    const outroPlano = useMutation({ mutationFn: () => registo.criar<Estado>('/outro-plano', corpo()), onSuccess: aplicar });
    const recomecar = useMutation({
        mutationFn: () => registo.apagar<Estado>('/progresso'),
        onSuccess: (novo) => { porSenha({ password: '', password_confirmation: '' }); porComprovativo(null); porTermos(false); porARecomecar(false); aplicar(novo); },
    });
    const registar = useMutation({
        mutationFn: () => {
            const dados = new FormData();
            Object.entries(corpo()).forEach(([k, v]) => dados.append(k, v === null ? '' : String(v)));
            dados.append('aceito_termos', termos ? '1' : '0');
            if (comprovativo) dados.append('payment_proof', comprovativo);
            return registo.enviar<{ ir_para: string }>('', dados);
        },
        onSuccess: (r) => window.location.assign(r.ir_para),
    });

    // Guarda o que se escreve, como o `updated()` do Livewire — com uma pausa,
    // para não ir ao servidor a cada tecla.
    const primeira = useRef(true);
    useEffect(() => {
        if (primeira.current) { primeira.current = false; return; }
        const relogio = window.setTimeout(() => {
            registo.guardar<{ guardado_em: string }>('/progresso', { ...campos, passo }).then((r) => porGuardadoEm(r.guardado_em)).catch(() => undefined);
        }, 800);
        return () => window.clearTimeout(relogio);
    }, [campos]); // eslint-disable-line react-hooks/exhaustive-deps

    const emCurso = [seguinte, anterior, outroPlano, registar].find((m) => m.isPending || m.error);
    const erroAtivo = emCurso?.error ?? null;
    const erros = erroAtivo instanceof ErroDaApi ? erroAtivo.erros : {};
    const erroSolto = erroAtivo && !Object.keys(erros).length ? erroAtivo.message : null;
    const ocupado = [seguinte, anterior, outroPlano, registar].some((m) => m.isPending);

    const muda = (chave: keyof Campos) => (e: { target: { value: string } }) => porCampos({ ...campos, [chave]: e.target.value });
    const submeter = (e: FormEvent) => {
        e.preventDefault();
        if (passo < 4) seguinte.mutate();
        else registar.mutate();
    };

    const numeroDoUltimo = (autenticado ? 3 : 4) - (temPassoDePlano ? 0 : 1);

    return (
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 p-4">
            <div className="w-full max-w-4xl">
                <div className="animate-fade-in mb-8 text-center">
                    <a href={site} className="group mb-4 inline-flex items-center">
                        <span className="mr-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-purple-600 shadow-lg transition-transform group-hover:rotate-6 group-hover:scale-105">
                            <i className="fas fa-chart-line text-2xl text-white" aria-hidden="true" />
                        </span>
                        <span className="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-3xl font-bold text-transparent">SOSERP</span>
                    </a>
                </div>

                {aviso && <Aviso tipo={aviso.tipo} texto={aviso.texto} aoFechar={() => porAviso(null)} />}
                {erroSolto && <Aviso tipo="erro" texto={erroSolto} />}

                <div className="animate-scale-in overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div className="bg-gradient-to-r from-blue-600 to-purple-600 px-4 py-6 sm:px-8">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                            {autenticado ? (
                                <span className="inline-flex items-center rounded-full bg-white/20 px-4 py-2 text-white backdrop-blur-sm">
                                    <i className="fas fa-user-check mr-2" aria-hidden="true" />
                                    <span className="text-sm font-semibold">{t('Logado como: :nome', { nome: campos.name })}</span>
                                </span>
                            ) : <span />}
                            <div className="flex items-center gap-3">
                                {guardadoEm && (
                                    <span className="animate-fade-in inline-flex items-center rounded-full bg-green-500/20 px-3 py-1 text-xs text-white backdrop-blur-sm">
                                        <i className="fas fa-check-circle mr-1" aria-hidden="true" />{t('Progresso salvo')}
                                    </span>
                                )}
                                <button type="button" onClick={() => porARecomecar(true)} title={t('Recomeçar wizard do zero')}
                                    className={cls('inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs text-white backdrop-blur-sm hover:bg-white/20', TRANSICAO, FOCO)}>
                                    <i className="fas fa-redo mr-1" aria-hidden="true" />{t('Recomeçar')}
                                </button>
                            </div>
                        </div>

                        <ol className="mx-auto flex max-w-2xl items-center justify-between" aria-label={t('Passos')}>
                            {!autenticado && (
                                <>
                                    <Passo numero={1} rotulo={t('Seus Dados')} actual={passo} passo={1} />
                                    <Linha cheia={passo >= 2} />
                                </>
                            )}
                            <Passo numero={autenticado ? 1 : 2} rotulo={t('Sua Empresa')} actual={passo} passo={2} />
                            <Linha cheia={passo >= 3} />
                            {temPassoDePlano && (
                                <>
                                    <Passo numero={autenticado ? 2 : 3} rotulo={t('Escolha o Plano')} actual={passo} passo={3} />
                                    <Linha cheia={passo >= 4} />
                                </>
                            )}
                            <Passo numero={numeroDoUltimo} rotulo={naoHaNadaAPagar ? t('Confirmação') : t('Pagamento')} actual={passo} passo={4} ultimo />
                        </ol>
                    </div>

                    <form onSubmit={submeter} noValidate className="p-5 sm:p-8">
                        <div key={passo} className="animate-fade-in">
                            {passo === 1 && (
                                <Seccao titulo={t('Crie sua conta')} nota={t('Comece informando seus dados pessoais')}>
                                    <Entrada icone="fa-user" rotulo={t('Nome Completo')} obrigatorio erro={erros.name}>
                                        <input className={cls(CAMPO, 'focus:border-blue-500 focus:ring-blue-500', erros.name && 'border-red-500')} autoComplete="name" placeholder="João Silva" value={campos.name} onChange={muda('name')} />
                                    </Entrada>
                                    <Entrada icone="fa-envelope" rotulo={t('Email')} obrigatorio erro={erros.email}>
                                        <input type="email" className={cls(CAMPO, 'focus:border-blue-500 focus:ring-blue-500', erros.email && 'border-red-500')} autoComplete="email" placeholder="joao@empresa.vip" value={campos.email} onChange={muda('email')} />
                                    </Entrada>
                                    <Entrada icone="fa-lock" rotulo={t('Senha')} obrigatorio erro={erros.password}>
                                        <input type="password" className={cls(CAMPO, 'focus:border-blue-500 focus:ring-blue-500', erros.password && 'border-red-500')} autoComplete="new-password" placeholder={t('Mínimo 6 caracteres')}
                                            value={senha.password} onChange={(e) => porSenha({ ...senha, password: e.target.value })} />
                                    </Entrada>
                                    <Entrada icone="fa-lock" rotulo={t('Confirmar Senha')} obrigatorio>
                                        <input type="password" className={cls(CAMPO, 'focus:border-blue-500 focus:ring-blue-500')} autoComplete="new-password" placeholder={t('Digite a senha novamente')}
                                            value={senha.password_confirmation} onChange={(e) => porSenha({ ...senha, password_confirmation: e.target.value })} />
                                    </Entrada>
                                </Seccao>
                            )}

                            {passo === 2 && (
                                <Seccao titulo={t('Dados da Empresa')} nota={t('Informe os dados da sua empresa')}>
                                    <Entrada icone="fa-building" cor="text-purple-500" rotulo={t('Nome da Empresa')} obrigatorio erro={erros.company_name}>
                                        <input className={cls(CAMPO, 'focus:border-purple-500 focus:ring-purple-500', erros.company_name && 'border-red-500')} autoComplete="organization" placeholder="Minha Empresa Lda" value={campos.company_name} onChange={muda('company_name')} />
                                    </Entrada>
                                    {/* O exemplo começa por 5: diz a regra do NIF de empresa sem ser preciso lê-la. */}
                                    <Entrada icone="fa-id-card" cor="text-purple-500" rotulo={t('NIF da empresa')} obrigatorio erro={erros.company_nif}
                                        ajuda={t('Nove ou dez dígitos, começados por 5. Não é o número do bilhete de identidade.')}>
                                        <input className={cls(CAMPO, 'focus:border-purple-500 focus:ring-purple-500', erros.company_nif && 'border-red-500')} inputMode="numeric" maxLength={14} placeholder="5417289442" value={campos.company_nif} onChange={muda('company_nif')} />
                                    </Entrada>

                                    <fieldset>
                                        <legend className="mb-2 block text-sm font-semibold text-gray-700">
                                            <i className="fas fa-scale-balanced mr-2 text-purple-500" aria-hidden="true" />{t('Regime fiscal (AGT)')} <span aria-hidden="true">*</span>
                                        </legend>
                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                            {regimes.map((r, i) => {
                                                const escolhido = campos.company_regime === r.valor;
                                                return (
                                                    <label key={r.valor} className="entra cursor-pointer" style={{ ['--i' as string]: i }}>
                                                        <input type="radio" name="company_regime" value={r.valor} checked={escolhido} onChange={muda('company_regime')} className="peer sr-only" />
                                                        <span className={cls('block h-full rounded-xl border-2 p-3 peer-focus-visible:ring-2 peer-focus-visible:ring-purple-500', TRANSICAO,
                                                            escolhido ? 'border-purple-500 bg-purple-50 shadow-md' : 'border-gray-200 hover:-translate-y-0.5 hover:border-purple-300')}>
                                                            <span className="flex items-start justify-between gap-2">
                                                                <span className="text-sm font-bold text-gray-900">{r.rotulo}</span>
                                                                {escolhido && <i className="fas fa-circle-check animate-scale-in text-purple-600" aria-hidden="true" />}
                                                            </span>
                                                            <span className="mt-1 block text-xs leading-snug text-gray-600">{r.descricao}</span>
                                                            <span className="mt-1 block text-[11px] text-gray-400">{r.volume}</span>
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                        <p className="mt-2 text-xs text-gray-500"><i className="fas fa-circle-info mr-1" aria-hidden="true" />{t('Na dúvida, confirme no seu cartão de contribuinte — pode alterar depois em Dados da Empresa.')}</p>
                                        <Erro erro={erros.company_regime} />
                                    </fieldset>

                                    <Entrada icone="fa-map-marker-alt" cor="text-purple-500" rotulo={t('Endereço')} erro={erros.company_address}>
                                        <input className={cls(CAMPO, 'focus:border-purple-500 focus:ring-purple-500')} autoComplete="street-address" placeholder="Rua exemplo, Luanda" value={campos.company_address} onChange={muda('company_address')} />
                                    </Entrada>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <Entrada icone="fa-phone" cor="text-purple-500" rotulo={t('Telefone')} erro={erros.company_phone}>
                                            <input type="tel" className={cls(CAMPO, 'focus:border-purple-500 focus:ring-purple-500')} autoComplete="tel" placeholder="+244 923 456 789" value={campos.company_phone} onChange={muda('company_phone')} />
                                        </Entrada>
                                        <Entrada icone="fa-envelope" cor="text-purple-500" rotulo={t('Email da Empresa')} erro={erros.company_email}>
                                            <input type="email" className={cls(CAMPO, 'focus:border-purple-500 focus:ring-purple-500', erros.company_email && 'border-red-500')} placeholder="contato@empresa.vip" value={campos.company_email} onChange={muda('company_email')} />
                                        </Entrada>
                                    </div>
                                </Seccao>
                            )}

                            {passo === 3 && (
                                <Seccao titulo={t('Escolha seu Plano')} nota={t('Selecione o plano ideal para seu negócio')} largo>
                                    {erros.selected_plan_id?.[0] && (
                                        <p role="alert" className="flex items-start rounded-xl border-2 border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                                            <i className="fas fa-circle-exclamation mr-3 mt-0.5" aria-hidden="true" />{erros.selected_plan_id[0]}
                                        </p>
                                    )}
                                    {/* A cortesia é uma só: quem já a gastou vê os planos todos, mas sem promessas que não se cumprem. */}
                                    {estado.sem_teste && (
                                        <p className="flex items-start rounded-xl border-2 border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                                            <i className="fas fa-circle-info mr-3 mt-0.5" aria-hidden="true" />{estado.sem_teste}
                                        </p>
                                    )}
                                    <div role="radiogroup" aria-label={t('Escolha seu Plano')} className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                        {estado.planos.map((p, i) => (
                                            <CartaoDoPlano key={p.id} plano={p} i={i} escolhido={campos.selected_plan_id === p.id}
                                                aoEscolher={() => porCampos({ ...campos, selected_plan_id: p.id })} />
                                        ))}
                                    </div>
                                </Seccao>
                            )}

                            {passo === 4 && (
                                <Seccao titulo={naoHaNadaAPagar ? t('Confirmação') : t('Método de Pagamento')}
                                    nota={naoHaNadaAPagar ? t('Este plano é gratuito — não há nada a pagar.') : t('Como deseja efetuar o pagamento?')}>
                                    {plano && (
                                        <div className="rounded-2xl bg-gradient-to-r from-purple-500 to-pink-500 p-6 text-white shadow-lg">
                                            <div className="flex flex-wrap items-center justify-between gap-4">
                                                <div>
                                                    <p className="mb-1 text-sm opacity-90">{t('Plano Selecionado')}</p>
                                                    <h3 className="text-2xl font-bold">{plano.nome}</h3>
                                                </div>
                                                <div className="text-right">
                                                    <p className="mb-1 text-sm opacity-90">{t('Valor Mensal')}</p>
                                                    {/* «0 Kz» lê-se como um preço por apurar. Escreve-se o que é. */}
                                                    <p className="text-3xl font-bold tabular-nums">{naoHaNadaAPagar ? t('Grátis') : `${kz(plano.preco, 0)} Kz`}</p>
                                                </div>
                                            </div>
                                            <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-white/20 pt-4">
                                                {plano.dias_de_teste > 0 && temDireitoATeste ? (
                                                    <span className="flex items-center text-sm"><i className="fas fa-gift icon-float mr-2" aria-hidden="true" />{t(':dias dias de teste grátis inclusos', { dias: plano.dias_de_teste })}</span>
                                                ) : plano.dias_de_teste > 0 ? (
                                                    <span className="flex items-center text-sm opacity-90"><i className="fas fa-circle-info mr-2" aria-hidden="true" />{t('Sem período de teste — já foi utilizado nesta conta')}</span>
                                                ) : <span />}
                                                {!temPassoDePlano && (
                                                    <button type="button" onClick={() => outroPlano.mutate()} disabled={ocupado}
                                                        className={cls('text-sm font-semibold underline underline-offset-2 hover:opacity-80', TRANSICAO)}>
                                                        <i className="fas fa-exchange-alt mr-1" aria-hidden="true" />{t('Escolher outro plano')}
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {naoHaNadaAPagar ? (
                                        <div className="rounded-xl border-2 border-green-200 bg-green-50 p-6 text-center">
                                            <span className="mx-auto mb-4 flex h-14 w-14 animate-scale-in items-center justify-center rounded-full bg-green-100">
                                                <i className="fas fa-check text-2xl text-green-600" aria-hidden="true" />
                                            </span>
                                            <h4 className="mb-1 font-bold text-gray-900">{t('Sem pagamento a efetuar')}</h4>
                                            <p className="text-sm text-gray-600">{t('O plano :plano não tem custo. A conta fica activa assim que concluir o registo.', { plano: plano?.nome ?? '' })}</p>
                                        </div>
                                    ) : (
                                        <Pagamento
                                            campos={campos}
                                            muda={muda}
                                            aoMetodo={(m) => porCampos({ ...campos, payment_method: m })}
                                            conta={conta}
                                            valor={plano?.preco ?? 0}
                                            obrigatorio={!temDireitoATeste}
                                            comprovativo={comprovativo}
                                            porComprovativo={porComprovativo}
                                            erros={erros}
                                        />
                                    )}

                                    <div className="rounded-xl border border-blue-200 bg-blue-50 p-4">
                                        <label className="flex cursor-pointer items-start">
                                            <input type="checkbox" checked={termos} onChange={(e) => porTermos(e.target.checked)} required
                                                className="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                            <span className="ml-3 text-sm text-gray-700">
                                                {t('Concordo com os')} <a href="/termos" target="_blank" rel="noreferrer" className="font-semibold text-blue-600 hover:text-blue-800">{t('Termos de Serviço')}</a>{' '}
                                                {t('e')} <a href="/privacidade" target="_blank" rel="noreferrer" className="font-semibold text-blue-600 hover:text-blue-800">{t('Política de Privacidade')}</a>
                                            </span>
                                        </label>
                                        <Erro erro={erros.aceito_termos} />
                                    </div>
                                </Seccao>
                            )}
                        </div>

                        <div className="mt-8 flex items-center justify-between border-t border-gray-200 pt-6">
                            {passo > (autenticado ? 2 : 1) ? (
                                <button type="button" onClick={() => anterior.mutate()} disabled={ocupado}
                                    className={cls('group flex items-center rounded-xl border-2 border-gray-300 px-6 py-3 font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50', TRANSICAO, FOCO)}>
                                    <i className={cls('fas mr-2 transition-transform group-hover:-translate-x-1', anterior.isPending ? 'fa-spinner fa-spin' : 'fa-arrow-left')} aria-hidden="true" />
                                    {t('Voltar')}
                                </button>
                            ) : (
                                <a href={entrar} className="px-2 py-3 font-semibold text-gray-600 transition hover:text-gray-900 hover:underline sm:px-6">
                                    {t('Já tem conta?')} <span className="underline">{t('Entre aqui')}</span>
                                </a>
                            )}

                            {passo < 4 ? (
                                <button type="submit" disabled={ocupado}
                                    className={cls('group flex items-center rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-3 font-semibold text-white hover:scale-105 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-75', TRANSICAO, FOCO)}>
                                    {seguinte.isPending ? <i className="fas fa-spinner fa-spin" aria-hidden="true" /> : <>{t('Próximo')}<i className="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1" aria-hidden="true" /></>}
                                </button>
                            ) : (
                                <button type="submit" disabled={ocupado || !termos}
                                    className={cls('group flex items-center rounded-xl bg-gradient-to-r from-green-600 to-green-700 px-8 py-3 font-semibold text-white hover:scale-105 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100', TRANSICAO, FOCO)}>
                                    {registar.isPending
                                        ? <><i className="fas fa-spinner fa-spin mr-2" aria-hidden="true" />{t('Processando...')}</>
                                        : <><i className="fas fa-check mr-2 transition-transform group-hover:scale-110" aria-hidden="true" />{t('Finalizar cadastro')}</>}
                                </button>
                            )}
                        </div>
                    </form>
                </div>

                <div className="mt-6 flex items-center justify-between">
                    <a href={site} className="group text-sm font-medium text-gray-600 hover:text-gray-900">
                        <i className="fas fa-arrow-left mr-2 transition-transform group-hover:-translate-x-1" aria-hidden="true" />{t('Voltar para o site')}
                    </a>
                    {guardadoEm && (
                        <button type="button" onClick={() => porARecomecar(true)} className="text-sm font-medium text-red-600 hover:text-red-900">
                            <i className="fas fa-redo mr-2" aria-hidden="true" />{t('Recomeçar do Início')}
                        </button>
                    )}
                </div>
            </div>

            <Confirmar
                aberto={aRecomecar}
                titulo={t('Recomeçar do Início')}
                rotulo={t('Recomeçar')}
                icone="fa-redo"
                aTrabalhar={recomecar.isPending}
                erro={recomecar.error}
                aoConfirmar={() => recomecar.mutate()}
                aoFechar={() => porARecomecar(false)}
            >
                <p className="text-sm text-gray-600">{t('Deseja realmente recomeçar? Todo o progresso será perdido.')}</p>
            </Confirmar>
        </div>
    );
}

function Aviso({ tipo, texto, aoFechar }: { tipo: 'info' | 'warning' | 'erro'; texto: string; aoFechar?: () => void }) {
    const estilo = {
        info: ['border-blue-500 bg-blue-50 text-blue-800', 'fa-info-circle', t('Informação:')],
        warning: ['border-yellow-500 bg-yellow-50 text-yellow-800', 'fa-exclamation-triangle', t('Atenção:')],
        erro: ['border-red-500 bg-red-50 text-red-800', 'fa-exclamation-circle', t('Erro:')],
    }[tipo];

    return (
        <div role={tipo === 'erro' ? 'alert' : 'status'} className={cls('animate-fade-in mb-6 flex items-center rounded-2xl border-2 p-4', estilo[0])}>
            <i className={cls('fas mr-3 text-2xl', estilo[1])} aria-hidden="true" />
            <p className="flex-1"><strong>{estilo[2]}</strong> {texto}</p>
            {aoFechar && (
                <button type="button" onClick={aoFechar} className="ml-3 opacity-60 hover:opacity-100" aria-label={t('Fechar')}>
                    <i className="fas fa-xmark" aria-hidden="true" />
                </button>
            )}
        </div>
    );
}

function Passo({ numero, rotulo, actual, passo, ultimo = false }: { numero: number; rotulo: string; actual: number; passo: number; ultimo?: boolean }) {
    const feito = !ultimo && actual > passo;
    const chegou = actual >= passo;

    return (
        <li className="flex shrink-0 items-center" aria-current={actual === passo ? 'step' : undefined}>
            <span className={cls('flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg font-bold transition-all duration-500 sm:h-12 sm:w-12',
                chegou ? 'bg-white text-purple-600 shadow-lg' : 'bg-white/30 text-white', actual === passo && 'scale-110 ring-4 ring-white/30')}>
                {feito ? <i className="fas fa-check animate-scale-in" aria-hidden="true" /> : numero}
            </span>
            <span className="ml-3 hidden whitespace-nowrap text-white md:block">
                <span className="block text-sm font-semibold">{t('Passo :n', { n: numero })}</span>
                <span className="block text-xs opacity-90">{rotulo}</span>
            </span>
        </li>
    );
}

function Linha({ cheia }: { cheia: boolean }) {
    return (
        <li aria-hidden="true" className="mx-2 h-1 min-w-4 flex-1 overflow-hidden rounded-full bg-white/30 sm:mx-4">
            <span className={cls('block h-full bg-white transition-all duration-700', cheia ? 'w-full' : 'w-0')} />
        </li>
    );
}

function Seccao({ titulo, nota, largo = false, children }: { titulo: string; nota: string; largo?: boolean; children: ReactNode }) {
    return (
        <div className={cls('mx-auto', largo ? 'max-w-4xl' : 'max-w-2xl')}>
            <div className="mb-8 text-center">
                <h2 className="text-3xl font-bold text-gray-900">{titulo}</h2>
                <p className="mt-2 text-gray-600">{nota}</p>
            </div>
            <div className="space-y-6">{children}</div>
        </div>
    );
}

function Entrada({ icone, cor = 'text-blue-500', rotulo, obrigatorio = false, erro, ajuda, children }: {
    icone: string; cor?: string; rotulo: string; obrigatorio?: boolean; erro?: string[]; ajuda?: string; children: ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-semibold text-gray-700">
                <i className={cls('fas mr-2', icone, cor)} aria-hidden="true" />{rotulo}{obrigatorio && <span aria-hidden="true"> *</span>}
            </span>
            {children}
            {erro?.[0] ? <Erro erro={erro} /> : ajuda && <span className="mt-2 block text-xs text-gray-500">{ajuda}</span>}
        </label>
    );
}

function Erro({ erro }: { erro?: string[] }) {
    if (!erro?.[0]) return null;

    return (
        <span role="alert" className="animate-fade-in mt-2 flex items-center text-sm text-red-600">
            <i className="fas fa-exclamation-circle mr-1" aria-hidden="true" />{erro[0]}
        </span>
    );
}

function CartaoDoPlano({ plano: p, i, escolhido, aoEscolher }: { plano: Plano; i: number; escolhido: boolean; aoEscolher: () => void }) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={escolhido}
            aria-disabled={!!p.recusa}
            title={p.recusa ?? undefined}
            onClick={() => { if (!p.recusa) aoEscolher(); }}
            style={{ ['--i' as string]: i }}
            className={cls('entra flex flex-col rounded-2xl border-2 p-6 text-left', TRANSICAO, FOCO,
                p.recusa ? 'cursor-not-allowed border-gray-200 bg-gray-50 opacity-60'
                    : escolhido ? 'scale-105 border-purple-500 bg-purple-50 shadow-lg' : 'card-hover border-gray-200 hover:border-purple-300 hover:shadow-md')}
        >
            {p.destaque && (
                <span className="mb-3 inline-block self-start rounded-full bg-gradient-to-r from-purple-600 to-pink-600 px-3 py-1 text-xs font-bold text-white">
                    <i className="fas fa-star mr-1" aria-hidden="true" />{t('POPULAR')}
                </span>
            )}
            <span className="mb-2 text-xl font-bold text-gray-900">{p.nome}</span>
            {p.descricao && <span className="mb-4 text-sm text-gray-600">{p.descricao}</span>}
            <span className="mb-4">
                <span className="text-3xl font-bold tabular-nums text-gray-900">{kz(p.preco, 0)}</span>
                <span className="text-sm text-gray-600"> {t('Kz/mês')}</span>
            </span>
            <span className="mb-4 space-y-2 text-sm">
                <span className="flex items-center text-gray-700"><i className="fas fa-check mr-2 text-xs text-green-500" aria-hidden="true" />{t(':n Utilizadores', { n: p.utilizadores })}</span>
                <span className="flex items-center text-gray-700"><i className="fas fa-check mr-2 text-xs text-green-500" aria-hidden="true" />{p.empresas >= 999 ? t('Ilimitadas Empresas') : t(':n Empresas', { n: p.empresas })}</span>
                {p.dias_de_teste > 0 && (
                    <span className={cls('flex items-center', p.com_teste ? 'text-gray-700' : 'text-gray-400 line-through')}>
                        <i className={cls('fas mr-2 text-xs', p.com_teste ? 'fa-check text-green-500' : 'fa-xmark text-gray-400')} aria-hidden="true" />
                        {t(':dias dias grátis', { dias: p.dias_de_teste })}
                    </span>
                )}
            </span>
            <span className="mt-auto">
                {p.recusa ? (
                    <>
                        <span className="block rounded-lg bg-gray-200 py-2 text-center text-sm font-semibold text-gray-600"><i className="fas fa-lock mr-2" aria-hidden="true" />{t('Já utilizado')}</span>
                        <span className="mt-2 block text-xs leading-snug text-gray-500">{p.recusa}</span>
                    </>
                ) : escolhido ? (
                    <span className="block animate-scale-in rounded-lg bg-purple-600 py-2 text-center font-semibold text-white"><i className="fas fa-check-circle mr-2" aria-hidden="true" />{t('Selecionado')}</span>
                ) : (
                    <span className="block rounded-lg bg-gray-100 py-2 text-center font-semibold text-gray-600">{t('Selecionar')}</span>
                )}
            </span>
        </button>
    );
}

function Pagamento({ campos, muda, aoMetodo, conta, valor, obrigatorio, comprovativo, porComprovativo, erros }: {
    campos: Campos;
    muda: (chave: keyof Campos) => (e: { target: { value: string } }) => void;
    aoMetodo: (m: string) => void;
    conta: { banco: string; titular: string; iban: string };
    valor: number;
    obrigatorio: boolean;
    comprovativo: File | null;
    porComprovativo: (f: File | null) => void;
    erros: Record<string, string[]>;
}) {
    const [arrastar, porArrastar] = useState(false);
    const escolhida = campos.payment_method === 'transfer';

    return (
        <>
            <fieldset>
                <legend className="mb-4 block text-sm font-semibold text-gray-700"><i className="fas fa-credit-card mr-2 text-green-500" aria-hidden="true" />{t('Selecione o Método de Pagamento')}</legend>
                <button type="button" role="radio" aria-checked={escolhida} onClick={() => aoMetodo('transfer')}
                    className={cls('flex w-full items-center justify-between rounded-xl border-2 p-4 text-left', TRANSICAO, FOCO, escolhida ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-green-300')}>
                    <span className="flex items-center">
                        <span className="mr-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-100"><i className="fas fa-university icon-float text-xl text-green-600" aria-hidden="true" /></span>
                        <span>
                            <span className="block font-bold text-gray-900">{t('Transferência Bancária')}</span>
                            <span className="block text-sm text-gray-600">{t('Pagamento por transferência')}</span>
                        </span>
                    </span>
                    {escolhida && <i className="fas fa-check-circle animate-scale-in text-2xl text-green-600" aria-hidden="true" />}
                </button>
                <Erro erro={erros.payment_method} />
            </fieldset>

            {escolhida && (
                <>
                    {/* A conta vem de um sítio só (ContaDaPlataforma): duas cópias do IBAN já não diziam o mesmo. */}
                    <div className="rounded-xl border-2 border-blue-200 bg-blue-50 p-6">
                        <h4 className="mb-4 flex items-center font-bold text-gray-900"><i className="fas fa-info-circle mr-2 text-blue-600" aria-hidden="true" />{t('Dados para Transferência')}</h4>
                        <dl className="space-y-3 text-sm">
                            <Linha2 rotulo={t('Banco:')} valor={conta.banco} />
                            <Linha2 rotulo={t('Titular:')} valor={conta.titular} />
                            <Linha2 rotulo="IBAN:" valor={<CopiarIban iban={conta.iban} />} />
                            <div className="flex justify-between py-2">
                                <dt className="text-gray-600">{t('Valor:')}</dt>
                                <dd className="text-lg font-bold tabular-nums text-green-600">{kz(valor)} Kz</dd>
                            </div>
                        </dl>
                        <p className="mt-4 rounded-lg border border-yellow-300 bg-yellow-100 p-3 text-xs text-yellow-800">
                            <i className="fas fa-exclamation-triangle mr-1" aria-hidden="true" />
                            <strong>{t('Importante:')}</strong> {t('Após efetuar a transferência, insira a referência e anexe o comprovativo abaixo.')}
                        </p>
                    </div>

                    <Entrada icone="fa-hashtag" rotulo={t('Referência da Transferência')} obrigatorio={obrigatorio} erro={erros.payment_reference}
                        ajuda={t('Número de referência da sua transferência bancária')}>
                        <input className={cls(CAMPO, 'focus:border-blue-500 focus:ring-blue-500', erros.payment_reference && 'border-red-500')} placeholder="Ex: TRF123456789" value={campos.payment_reference} onChange={muda('payment_reference')} />
                    </Entrada>

                    <div>
                        <p className="mb-2 block text-sm font-semibold text-gray-700">
                            <i className="fas fa-file-upload mr-2 text-blue-500" aria-hidden="true" />{t('Comprovativo de Pagamento')}{' '}
                            {obrigatorio ? <span className="text-red-600" aria-hidden="true">*</span> : <span className="text-xs text-gray-500">{t('(Opcional - Período Trial)')}</span>}
                        </p>

                        {!comprovativo ? (
                            <label
                                onDragOver={(e) => { e.preventDefault(); porArrastar(true); }}
                                onDragLeave={() => porArrastar(false)}
                                onDrop={(e) => { e.preventDefault(); porArrastar(false); const f = e.dataTransfer.files[0]; if (f) porComprovativo(f); }}
                                className={cls('block cursor-pointer rounded-xl border-2 border-dashed p-6 text-center', TRANSICAO, arrastar ? 'scale-[1.01] border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-400')}
                            >
                                <i className={cls('fas fa-cloud-upload-alt mb-3 text-4xl', arrastar ? 'animate-bounce text-blue-500' : 'text-gray-400')} aria-hidden="true" />
                                <span className="mb-2 block text-sm text-gray-600">{t('Arraste o ficheiro ou clique para selecionar')}</span>
                                <span className="mb-3 block text-xs text-gray-500">{t('PDF, JPG ou PNG até 5MB')}</span>
                                <input type="file" className="sr-only" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => porComprovativo(e.target.files?.[0] ?? null)} />
                                <span className="inline-flex items-center rounded-lg bg-blue-500 px-4 py-2 text-white transition hover:bg-blue-600"><i className="fas fa-upload mr-2" aria-hidden="true" />{t('Selecionar Ficheiro')}</span>
                            </label>
                        ) : (
                            <div className="animate-scale-in flex items-center justify-between rounded-xl border-2 border-green-300 bg-green-50 p-4">
                                <span className="flex items-center">
                                    <span className="mr-3 flex h-12 w-12 items-center justify-center rounded-lg bg-green-100"><i className="fas fa-file-alt text-xl text-green-600" aria-hidden="true" /></span>
                                    <span>
                                        <span className="block text-sm font-semibold text-gray-900">{comprovativo.name}</span>
                                        <span className="block text-xs text-gray-600">{kz(comprovativo.size / 1024)} KB</span>
                                    </span>
                                </span>
                                <button type="button" onClick={() => porComprovativo(null)} className="text-red-600 transition hover:scale-110 hover:text-red-800" aria-label={t('Remover ficheiro')}>
                                    <i className="fas fa-times-circle text-xl" aria-hidden="true" />
                                </button>
                            </div>
                        )}
                        <Erro erro={erros.payment_proof} />
                        <p className="mt-2 text-xs text-gray-500">
                            <i className="fas fa-info-circle mr-1" aria-hidden="true" />
                            {obrigatorio ? t('Comprovativo obrigatório para planos pagos') : t('Comprovativo opcional durante período trial')}
                        </p>
                    </div>
                </>
            )}
        </>
    );
}

function Linha2({ rotulo, valor }: { rotulo: string; valor: ReactNode }) {
    return (
        <div className="flex justify-between gap-4 border-b border-blue-200 py-2">
            <dt className="text-gray-600">{rotulo}</dt>
            <dd className="text-right font-semibold text-gray-900">{valor}</dd>
        </div>
    );
}

/** O IBAN copia-se com um toque: escrevê-lo à mão é onde os números se trocam. */
function CopiarIban({ iban }: { iban: string }) {
    const [copiado, porCopiado] = useState(false);

    return (
        <button type="button" title={t('Copiar')} className="group inline-flex items-center gap-2 font-mono"
            onClick={() => { void navigator.clipboard?.writeText(iban.replace(/\s+/g, '')).then(() => { porCopiado(true); window.setTimeout(() => porCopiado(false), 1500); }); }}>
            {iban}
            <i className={cls('fas text-xs', copiado ? 'fa-check animate-scale-in text-green-600' : 'fa-copy text-blue-500 opacity-60 group-hover:opacity-100')} aria-hidden="true" />
        </button>
    );
}
