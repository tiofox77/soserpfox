import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    conta as api,
    type EmpresaDaConta, type MinhaConta as Conta, type PedidoDaConta, type PlanoDisponivel,
} from '@/api/conta';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { Privacidade } from './Privacidade';
import { etiquetaIntl, t } from '@/i18n';

/**
 * A MINHA CONTA: as empresas, o plano, as facturas, o perfil e a senha.
 *
 * O QUE ESTAVA PARTIDO E NINGUÉM VIA: o botão de editar e o de remover uma
 * empresa apareciam quando `pivot.role_id == 2` — um número escrito à mão. Cada
 * empresa cria os seus próprios papéis, com os seus próprios ids; o do dono é o
 * 2 na primeira empresa da base e é outro em todas as seguintes. Quem tivesse
 * três empresas via os botões numa e não via em nenhuma das outras.
 *
 * O direito vem agora do NOME do papel, decidido no servidor — a mesma regra
 * que o caminho de remoção já usava.
 */

const CICLOS = [
    { valor: 'monthly', rotulo: () => t('Mensal'), icone: 'fa-calendar-day' },
    { valor: 'quarterly', rotulo: () => t('Trimestral'), icone: 'fa-calendar-week' },
    { valor: 'semiannual', rotulo: () => t('Semestral'), icone: 'fa-calendar' },
    { valor: 'yearly', rotulo: () => t('Anual'), icone: 'fa-calendar-check' },
] as const;

const COR_DO_PEDIDO: Record<string, 'primaria' | 'bom' | 'aviso' | 'neutra' | 'perigo'> = {
    pending: 'aviso',
    approved: 'primaria',
    processing: 'primaria',
    completed: 'bom',
    paid: 'bom',
    cancelled: 'perigo',
    failed: 'neutra',
    refunded: 'neutra',
};

const EMPRESA_VAZIA = { name: '', nif: '', regime: 'regime_geral', address: '', phone: '', email: '' };

export default function MinhaConta() {
    const cache = useQueryClient();

    const [aba, porAba] = useState('empresas');
    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);

    const [formularioDeEmpresa, porFormularioDeEmpresa] = useState<typeof EMPRESA_VAZIA | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aArquivar, porAArquivar] = useState<EmpresaDaConta | null>(null);
    const [confirmacao, porConfirmacao] = useState('');

    const [perfil, porPerfil] = useState<{ name: string; email: string; phone: string; bio: string } | null>(null);
    const [senha, porSenha] = useState({ actual: '', nova: '', nova_confirmation: '' });

    const [aContratar, porAContratar] = useState<PlanoDisponivel | null>(null);
    const [ciclo, porCiclo] = useState('monthly');
    const [passo, porPasso] = useState(1);
    const [comprovativo, porComprovativo] = useState<File | null>(null);
    const [aPagar, porAPagar] = useState<PedidoDaConta | null>(null);
    const [ficheiroDoPedido, porFicheiroDoPedido] = useState<File | null>(null);

    const ficha = useQuery({ queryKey: ['conta'], queryFn: () => api.ler() });
    const arquivo = useQuery({
        queryKey: ['conta', 'pode-arquivar', aArquivar?.id],
        queryFn: () => api.empresas.podeArquivar(aArquivar?.id as number),
        enabled: aArquivar !== null,
    });

    useEffect(() => {
        if (ficha.data && perfil === null) {
            porPerfil({
                name: ficha.data.perfil.nome,
                email: ficha.data.perfil.email,
                phone: ficha.data.perfil.telefone ?? '',
                bio: ficha.data.perfil.bio ?? '',
            });
        }
    }, [ficha.data, perfil]);

    // A morada pode chegar com `?tab=` ou `?select=` (dos e-mails de renovação).
    useEffect(() => {
        const q = new URLSearchParams(window.location.search);
        const t = q.get('tab');

        if (t && ['empresas', 'plano', 'facturas', 'perfil', 'seguranca', 'privacidade'].includes(t)) porAba(t);
        if (q.get('select')) porAba('plano');
    }, []);

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['conta'] });
    };

    const criarEmpresa = useMutation({
        mutationFn: (d: Record<string, unknown>) => api.empresas.criar(d),
        onSuccess: (r) => { feito(r.message); porFormularioDeEmpresa(null); },
        onError: porErro,
    });

    const editarEmpresa = useMutation({
        mutationFn: (d: Record<string, unknown>) => api.empresas.editar(aEditar as number, d),
        onSuccess: (r) => { feito(r.message); porFormularioDeEmpresa(null); porAEditar(null); },
        onError: porErro,
    });

    const arquivar = useMutation({
        mutationFn: () => api.empresas.arquivar(aArquivar?.id as number, confirmacao),
        onSuccess: (r) => {
            porAArquivar(null);
            porConfirmacao('');

            // O contexto inteiro mudou (empresa, permissões, menu): aterra-se
            // numa página NOVA, e não se recarrega a que estava aberta.
            if (r.trocou_para) {
                window.location.href = '/home';

                return;
            }

            feito(r.message);
        },
        onError: porErro,
    });

    const activar = useMutation({
        mutationFn: (id: number) => api.empresas.activar(id),
        // Trocar de empresa muda tudo: recarrega-se na morada nova.
        onSuccess: () => { window.location.href = '/my-account?tab=empresas'; },
        onError: porErro,
    });

    const logotipo = useMutation({
        mutationFn: (d: { id: number; ficheiro: File }) => api.empresas.logotipo(d.id, d.ficheiro),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const guardarPerfil = useMutation({
        mutationFn: () => api.perfil(perfil as unknown as Record<string, unknown>),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const enviarAvatar = useMutation({
        mutationFn: (f: File) => api.avatar(f),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const tirarAvatar = useMutation({
        mutationFn: () => api.apagarAvatar(),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const mudarSenha = useMutation({
        mutationFn: () => api.senha(senha),
        onSuccess: (r) => { feito(r.message); porSenha({ actual: '', nova: '', nova_confirmation: '' }); },
        onError: porErro,
    });

    const contratar = useMutation({
        mutationFn: () => api.contratar(aContratar?.id as number, ciclo, comprovativo),
        onSuccess: (r) => {
            porAContratar(null);
            porPasso(1);
            porComprovativo(null);

            // Plano activado agora: o menu e os módulos mudaram — página nova.
            if (r.activado) {
                window.location.href = '/home';

                return;
            }

            feito(r.message);
            porAba('facturas');
        },
        onError: porErro,
    });

    const anexar = useMutation({
        mutationFn: () => api.comprovativo(aPagar?.id as number, ficheiroDoPedido as File),
        onSuccess: (r) => { feito(r.message); porAPagar(null); porFicheiroDoPedido(null); },
        onError: porErro,
    });

    if (ficha.isPending || perfil === null) return <Carregando linhas={10} />;
    if (ficha.isError) return <AvisoDeErro erro={ficha.error} />;

    const c: Conta = ficha.data;
    const gere = c.permissoes.gerir_conta;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const erros = (e: unknown) => (e as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const abas = [
        ...(gere ? [
            { chave: 'empresas', rotulo: t('Empresas'), icone: 'fa-building' },
            { chave: 'plano', rotulo: t('Plano'), icone: 'fa-crown' },
            { chave: 'facturas', rotulo: t('Facturas'), icone: 'fa-file-invoice-dollar' },
        ] : []),
        { chave: 'perfil', rotulo: t('Perfil'), icone: 'fa-user-circle' },
        { chave: 'seguranca', rotulo: t('Segurança'), icone: 'fa-shield-halved' },
        { chave: 'privacidade', rotulo: t('Privacidade'), icone: 'fa-user-shield' },
    ];

    /*
     * A ABA PEDIDA, SE EXISTIR PARA ESTA PESSOA.
     *
     * Quem não gere a conta não tem os separadores das empresas, do plano e das
     * facturas — e uma morada com `?tab=plano` não lhe pode deixar o ecrã em
     * branco. Cai na primeira que tenha, que é a única que sobra fazer sentido.
     */
    const activa = abas.some((a) => a.chave === aba) ? aba : abas[0]?.chave ?? 'perfil';

    const abrirNovaEmpresa = () => {
        porAEditar(null);
        porFormularioDeEmpresa({ ...EMPRESA_VAZIA });
    };

    const abrirEdicao = (e: EmpresaDaConta) => {
        porAEditar(e.id);
        porFormularioDeEmpresa({
            name: e.nome, nif: e.nif ?? '', regime: e.regime,
            address: e.morada ?? '', phone: e.telefone ?? '', email: e.email ?? '',
        });
    };

    const porPagar = c.facturas.filter((f) => f.estado !== 'paid');

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('A Minha Conta')}
                subtitulo={t('As suas empresas, o plano e os seus dados')}
                icone="fa-user-circle"
                cor="primaria"
                accoes={
                    <>
                        <a href="/home" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-house" aria-hidden="true" />
                            {t('Início')}
                        </a>
                        {gere && c.limite.cabe_mais && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNovaEmpresa}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Nova empresa')}
                            </button>
                        )}
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-building">
                        {c.limite.maximo === null
                            ? t(':n empresas, sem limite', { n: numero(c.limite.usadas) })
                            : t(':usadas de :maximo empresas', {
                                usadas: numero(c.limite.usadas), maximo: numero(c.limite.maximo),
                            })}
                    </EstadoNaFaixa>
                    {c.plano && (
                        <EstadoNaFaixa icone="fa-crown">{c.plano.nome}</EstadoNaFaixa>
                    )}
                    {c.limite.excedido && (
                        <EstadoNaFaixa icone="fa-triangle-exclamation">{t('Limite excedido')}</EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <Separadores abas={abas} activa={activa} aoMudar={porAba} />

            {/* ─── As empresas ───────────────────────────────────────── */}

            <PainelDoSeparador chave="empresas" activa={activa}>
                <div className="space-y-5">
                    <div className={cls('p-4', c.limite.excedido ? 'border-2 border-red-200 bg-red-50' : CARTAO, RAIO)}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className={cls('text-sm font-semibold', c.limite.excedido ? 'text-red-900' : 'text-slate-700')}>
                                <i className={cls('mr-2 fas', c.limite.excedido ? 'fa-triangle-exclamation text-red-500' : 'fa-building text-indigo-500')} aria-hidden="true" />
                                {c.limite.excedido ? t('Limite de empresas excedido') : t('Empresas do plano')}
                            </p>
                            <p className="text-sm tabular-nums text-slate-600">
                                {c.limite.maximo === null
                                    ? t(':n · sem limite', { n: numero(c.limite.usadas) })
                                    : t(':usadas de :maximo', {
                                        usadas: numero(c.limite.usadas), maximo: numero(c.limite.maximo),
                                    })}
                            </p>
                        </div>

                        {c.limite.maximo !== null && (
                            <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className={cls(
                                        'h-full rounded-full transition-all duration-700',
                                        c.limite.excedido ? 'bg-red-500' : 'bg-indigo-500',
                                    )}
                                    style={{ width: `${Math.min(100, (c.limite.usadas / Math.max(c.limite.maximo, 1)) * 100)}%` }}
                                />
                            </div>
                        )}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {c.empresas.map((e, i) => (
                            <article key={e.id} style={cascata(i)}
                                className={cls(
                                    'entra relative overflow-hidden border-2 p-5 transition-all duration-200 hover:shadow-lg',
                                    RAIO,
                                    e.activa ? 'border-indigo-400 bg-indigo-50/60' : 'border-slate-200 bg-white',
                                )}>
                                {e.activa && (
                                    <span className="absolute right-3 top-3">
                                        <Etiqueta cor="bom" ponto>{t('Activa')}</Etiqueta>
                                    </span>
                                )}

                                <div className="mb-4 flex items-start gap-3">
                                    <span className={cls(
                                        'grid h-14 w-14 flex-none place-items-center overflow-hidden rounded-xl',
                                        e.activa ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-500',
                                    )}>
                                        {e.logo
                                            ? <img src={e.logo} alt="" className="h-full w-full object-contain" />
                                            : <i className="fas fa-building text-xl" aria-hidden="true" />}
                                    </span>
                                    <div className={cls('min-w-0 flex-1', e.activa && 'pr-16')}>
                                        <h3 className="truncate text-base font-bold text-slate-800">{e.nome}</h3>
                                        <p className="truncate text-xs text-slate-500">{e.designacao ?? e.nif}</p>
                                    </div>
                                </div>

                                <dl className="mb-4 space-y-2 text-sm">
                                    <Linha icone="fa-id-card" rotulo={t('NIF')} valor={e.nif ?? '—'} />
                                    <Linha icone="fa-users" rotulo={t('Utilizadores')} valor={numero(e.utilizadores)} />
                                    <Linha icone="fa-puzzle-piece" rotulo={t('Módulos activos')} valor={numero(e.modulos)} />
                                    {e.plano && <Linha icone="fa-crown" rotulo={t('Plano')} valor={e.plano} />}
                                </dl>

                                <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
                                    <span className="text-xs text-slate-400">
                                        {e.desde ? t('Desde :dia', { dia: e.desde }) : ''}
                                    </span>
                                    <Etiqueta cor={e.sou_dono ? 'primaria' : 'neutra'} icone="fa-user-shield">
                                        {e.papel}
                                    </Etiqueta>
                                </div>

                                <div className="mt-3 flex flex-wrap gap-2">
                                    {!e.activa && (
                                        <Botao cor="primaria" tom="solida" icone="fa-right-left"
                                            altura="pequeno" aTrabalhar={activar.isPending}
                                            onClick={() => activar.mutate(e.id)}>
                                            {t('Activar')}
                                        </Botao>
                                    )}

                                    {/* O DONO, PELO NOME DO PAPEL — era aqui o
                                        `role_id == 2` que escondia estes botões
                                        em todas as empresas menos numa. */}
                                    {e.sou_dono && (
                                        <>
                                            <Botao altura="pequeno" icone="fa-pen"
                                                onClick={() => abrirEdicao(e)}
                                                aria-label={t('Editar empresa')} />
                                            {c.empresas.length > 1 && !e.activa && (
                                                <Botao altura="pequeno" cor="perigo" icone="fa-box-archive"
                                                    onClick={() => { porAArquivar(e); porConfirmacao(''); }}
                                                    aria-label={t('Remover empresa')} />
                                            )}
                                        </>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                </div>
            </PainelDoSeparador>

            {/* ─── O plano ───────────────────────────────────────────── */}

            <PainelDoSeparador chave="plano" activa={activa}>
                <div className="space-y-5">
                    {c.plano ? (
                        <div className={cls('p-5', CARTAO)}>
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                                        {t('Plano actual')}
                                    </p>
                                    <h2 className="text-2xl font-bold text-slate-800">{c.plano.nome}</h2>
                                    <p className="text-sm text-slate-500">
                                        {t('Ciclo :ciclo', { ciclo: c.plano.ciclo_rotulo.toLowerCase() })}
                                        {c.plano.em_teste ? ` — ${t('em período de teste')}` : ''}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-2xl font-bold tabular-nums text-slate-800">{kz(c.plano.preco)}</p>
                                    <p className="text-xs text-slate-400">{t('por mês')}</p>
                                </div>
                            </div>

                            <div className="mt-4 grid gap-4 sm:grid-cols-3">
                                <CartaoNumero aspecto="claro" rotulo={t('Utilizadores')}
                                    valor={c.plano.max_utilizadores === null ? '∞' : numero(c.plano.max_utilizadores)}
                                    icone="fa-users" tom="indigo" />
                                <CartaoNumero aspecto="claro" rotulo={t('Empresas')}
                                    valor={c.plano.max_empresas === null ? '∞' : numero(c.plano.max_empresas)}
                                    icone="fa-building" tom="azul" />
                                <CartaoNumero aspecto="claro"
                                    rotulo={c.plano.em_teste ? t('Teste até') : t('Válido até')}
                                    valor={c.plano.termina_em ?? '—'}
                                    icone="fa-calendar-check"
                                    tom={c.plano.a_terminar ? 'ambar' : 'verde'}
                                    nota={c.plano.dias_que_faltam !== null
                                        ? (c.plano.dias_que_faltam < 0
                                            ? t('O período terminou')
                                            : t(':n dia(s) por correr', { n: numero(c.plano.dias_que_faltam) }))
                                        : undefined}
                                />
                            </div>

                            {/*
                              * A FACTURA DO PERÍODO SEGUINTE JÁ FOI EMITIDA.
                              *
                              * Pagá-la não encurta nada: os dias novos somam-se
                              * aos que ainda faltam. Dizê-lo evita que alguém
                              * espere pelo último dia e fique sem sistema.
                              */}
                            {porPagar.length > 0 && (
                                <div className={cls('mt-4 border-2 border-amber-300 bg-amber-50 p-4', RAIO)}>
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <p className="text-sm font-bold text-amber-900">
                                            <i className="fas fa-file-invoice-dollar mr-2" aria-hidden="true" />
                                            {t('Tem :n factura(s) por pagar, no total de :total', {
                                                n: numero(porPagar.length),
                                                total: kz(porPagar.reduce((s, f) => s + f.total, 0)),
                                            })}
                                        </p>
                                        <Botao cor="aviso" tom="solida" icone="fa-arrow-right"
                                            onClick={() => porAba('facturas')}>
                                            {t('Ver as facturas')}
                                        </Botao>
                                    </div>
                                    <p className="mt-2 border-t border-amber-200 pt-2 text-xs text-amber-800">
                                        {t('Pagando já, os dias novos somam-se aos que ainda tem — não perde nada por pagar cedo.')}
                                    </p>
                                </div>
                            )}

                            {c.plano.funcionalidades.length > 0 && (
                                <ul className="mt-4 grid gap-1.5 sm:grid-cols-2">
                                    {c.plano.funcionalidades.map((f) => (
                                        <li key={f} className="flex items-start gap-2 text-sm text-slate-600">
                                            <i className="fas fa-circle-check mt-0.5 text-emerald-500" aria-hidden="true" />
                                            {f}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    ) : (
                        <div className={cls('border-2 border-amber-300 bg-amber-50 p-5', RAIO)}>
                            <p className="text-sm font-bold text-amber-900">
                                <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                                {t('Esta empresa não tem subscrição activa.')}
                            </p>
                            <p className="mt-1 text-sm text-amber-800">
                                {t('Escolha um plano abaixo para voltar a ter o sistema completo.')}
                            </p>
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {c.planos.map((p, i) => (
                            <article key={p.id} style={cascata(i)}
                                className={cls(
                                    'entra relative flex flex-col border-2 p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg',
                                    RAIO,
                                    p.actual ? 'border-indigo-400 bg-indigo-50/50'
                                        : p.destaque ? 'border-amber-300 bg-white' : 'border-slate-200 bg-white',
                                )}>
                                {p.actual && (
                                    <span className="absolute right-3 top-3">
                                        <Etiqueta cor="primaria">{t('Actual')}</Etiqueta>
                                    </span>
                                )}
                                {!p.actual && p.destaque && (
                                    <span className="absolute right-3 top-3">
                                        <Etiqueta cor="aviso" icone="fa-star">{t('Recomendado')}</Etiqueta>
                                    </span>
                                )}

                                <h3 className="text-lg font-bold text-slate-800">{p.nome}</h3>
                                {p.descricao && <p className="mt-1 text-xs text-slate-500">{p.descricao}</p>}

                                <p className="mt-3 text-2xl font-bold tabular-nums text-slate-800">
                                    {kz(p.precos.monthly)}
                                    <span className="text-xs font-normal text-slate-400"> / {t('mês')}</span>
                                </p>

                                {p.dias_de_teste > 0 && (
                                    <p className="mt-1 text-xs font-semibold text-emerald-600">
                                        {t(':n dias de teste', { n: numero(p.dias_de_teste) })}
                                    </p>
                                )}

                                <ul className="mt-3 flex-1 space-y-1">
                                    {p.funcionalidades.slice(0, 5).map((f) => (
                                        <li key={f} className="flex items-start gap-2 text-xs text-slate-600">
                                            <i className="fas fa-check mt-0.5 text-emerald-500" aria-hidden="true" />
                                            {f}
                                        </li>
                                    ))}
                                    {p.funcionalidades.length > 5 && (
                                        <li className="text-xs text-slate-400">
                                            {t('+ :n mais', { n: numero(p.funcionalidades.length - 5) })}
                                        </li>
                                    )}
                                </ul>

                                {p.modulos.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-1">
                                        {p.modulos.slice(0, 4).map((m) => (
                                            <span key={m} className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">
                                                {m}
                                            </span>
                                        ))}
                                        {p.modulos.length > 4 && (
                                            <span className="text-[11px] text-slate-400">
                                                +{numero(p.modulos.length - 4)}
                                            </span>
                                        )}
                                    </div>
                                )}

                                <div className="mt-4">
                                    {/*
                                      * A CORTESIA É UMA SÓ, PARA SEMPRE.
                                      *
                                      * Plano gratuito ou período de teste: o
                                      * primeiro que se usa gasta o direito ao
                                      * outro — e diz-se porquê, em vez de o
                                      * botão simplesmente não fazer nada.
                                      */}
                                    {p.recusa ? (
                                        <p className={cls('border border-slate-200 bg-slate-50 p-2.5 text-xs text-slate-600', RAIO)}>
                                            <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                                            {p.recusa}
                                        </p>
                                    ) : (
                                        <Botao cor="primaria" tom="solida" icone="fa-arrow-up"
                                            className="w-full justify-center"
                                            onClick={() => {
                                                porAContratar(p);
                                                porPasso(1);
                                                porComprovativo(null);
                                                porCiclo(p.auto_activa && p.dias_de_teste > 0
                                                    ? (p.dias_de_teste >= 360 ? 'yearly'
                                                        : p.dias_de_teste >= 150 ? 'semiannual'
                                                            : p.dias_de_teste >= 80 ? 'quarterly' : 'monthly')
                                                    : 'monthly');
                                            }}>
                                            {p.actual ? t('Renovar') : t('Escolher este plano')}
                                        </Botao>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                </div>
            </PainelDoSeparador>

            {/* ─── As facturas ───────────────────────────────────────── */}

            <PainelDoSeparador chave="facturas" activa={activa}>
                <div className="space-y-5">
                    {c.facturas.length > 0 && (
                        <section className={cls('overflow-hidden', CARTAO)}>
                            <header className="border-b border-slate-200 bg-slate-50 px-5 py-3">
                                <h2 className="text-sm font-bold text-slate-800">
                                    <i className="fas fa-file-invoice-dollar mr-2 text-indigo-500" aria-hidden="true" />
                                    {t('Facturas da subscrição')}
                                </h2>
                                <p className="text-xs text-slate-500">
                                    {t('O que a plataforma cobra a esta empresa — as renovações emitem factura sem pedido nenhum.')}
                                </p>
                            </header>

                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                        <tr>
                                            <th className="px-5 py-2.5">{t('Factura')}</th>
                                            <th className="px-5 py-2.5">{t('Descrição')}</th>
                                            <th className="px-5 py-2.5 text-right">{t('Total')}</th>
                                            <th className="px-5 py-2.5">{t('Vence')}</th>
                                            <th className="px-5 py-2.5">{t('Estado')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {c.facturas.map((f) => (
                                            <tr key={f.id} className="transition hover:bg-slate-50">
                                                <td className="px-5 py-3">
                                                    <p className="font-semibold text-slate-800">{f.numero}</p>
                                                    <p className="text-xs text-slate-400">{f.dia}</p>
                                                </td>
                                                <td className="px-5 py-3 text-slate-600">{f.descricao}</td>
                                                <td className="px-5 py-3 text-right font-bold tabular-nums text-slate-800">
                                                    {kz(f.total)}
                                                </td>
                                                <td className={cls('px-5 py-3', f.vencida ? 'font-semibold text-rose-700' : 'text-slate-600')}>
                                                    {f.vence_em ?? '—'}
                                                </td>
                                                <td className="px-5 py-3">
                                                    <Etiqueta cor={f.estado === 'paid' ? 'bom' : f.vencida ? 'perigo' : 'aviso'}>
                                                        {f.estado === 'paid' ? t('Paga') : f.vencida ? t('Vencida') : t('Por pagar')}
                                                    </Etiqueta>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    <section className={cls('overflow-hidden', CARTAO)}>
                        <header className="border-b border-slate-200 bg-slate-50 px-5 py-3">
                            <h2 className="text-sm font-bold text-slate-800">
                                <i className="fas fa-receipt mr-2 text-indigo-500" aria-hidden="true" />
                                {t('Pedidos')}
                            </h2>
                        </header>

                        {c.pedidos.length === 0 ? (
                            <div className="p-5">
                                <SemNada icone="fa-receipt" titulo={t('Nenhum pedido')}
                                    frase={t('Um pedido nasce quando se contrata ou se renova um plano.')} />
                            </div>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {c.pedidos.map((p, i) => (
                                    <li key={p.id} style={cascata(i)}
                                        className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                        <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                                            <i className="fas fa-file-invoice" aria-hidden="true" />
                                        </span>

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-slate-800">
                                                {p.numero} · {p.plano ?? '—'}
                                            </p>
                                            <p className="truncate text-xs text-slate-500">
                                                {[p.empresa, p.ciclo_rotulo, p.quando].filter(Boolean).join(' · ')}
                                            </p>
                                        </div>

                                        <span className="flex-none text-sm font-bold tabular-nums text-slate-800">
                                            {kz(p.valor)}
                                        </span>

                                        {/* O comprovativo abre-se — o ecrã de sempre tinha «Baixar Comprovativo»,
                                            e a migração deixou só a etiqueta. */}
                                        {p.tem_comprovativo && p.comprovativo && (
                                            <a href={p.comprovativo} target="_blank" rel="noopener" title={t('Ver comprovativo')} className="transition-transform duration-200 hover:scale-105">
                                                <Etiqueta cor="bom" icone="fa-paperclip">{t('Ver comprovativo')}</Etiqueta>
                                            </a>
                                        )}

                                        <Etiqueta cor={COR_DO_PEDIDO[p.estado] ?? 'neutra'} ponto>
                                            {p.estado_rotulo}
                                        </Etiqueta>

                                        {p.estado === 'pending' && (
                                            <Botao altura="pequeno" cor="primaria" icone="fa-credit-card"
                                                onClick={() => { porAPagar(p); porFicheiroDoPedido(null); }}>
                                                {p.tem_comprovativo ? t('Trocar comprovativo') : t('Pagar')}
                                            </Botao>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </PainelDoSeparador>

            {/* ─── O perfil ──────────────────────────────────────────── */}

            <PainelDoSeparador chave="perfil" activa={activa}>
                <div className="space-y-5">
                    <div className={cls('flex flex-wrap items-center gap-5 p-5', CARTAO)}>
                        <span className="grid h-24 w-24 flex-none place-items-center overflow-hidden rounded-full border-4 border-white bg-slate-100 shadow-md">
                            {c.perfil.avatar
                                ? <img src={c.perfil.avatar} alt="" className="h-full w-full object-cover" />
                                : <i className="fas fa-user text-3xl text-slate-300" aria-hidden="true" />}
                        </span>

                        <div className="min-w-0 flex-1">
                            <h2 className="flex flex-wrap items-center gap-2 text-xl font-bold text-slate-800">
                                {c.perfil.nome}
                                {c.perfil.super_admin && (
                                    <Etiqueta cor="primaria" icone="fa-crown">{t('Super Admin')}</Etiqueta>
                                )}
                            </h2>
                            <p className="text-sm text-slate-500">{c.perfil.email}</p>
                            {c.perfil.ultimo_acesso && (
                                <p className="mt-1 text-xs text-slate-400">
                                    {t('Último acesso: :quando', { quando: c.perfil.ultimo_acesso })}
                                </p>
                            )}

                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <label className={cls(
                                    'inline-flex cursor-pointer items-center gap-2 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100',
                                    FOCO,
                                )}>
                                    <i className="fas fa-camera" aria-hidden="true" />
                                    {t('Trocar fotografia')}
                                    <input type="file" accept="image/*" className="hidden"
                                        onChange={(e) => {
                                            const f = e.target.files?.[0];

                                            if (f) enviarAvatar.mutate(f);
                                        }} />
                                </label>

                                {c.perfil.avatar && (
                                    <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                        aTrabalhar={tirarAvatar.isPending}
                                        onClick={() => tirarAvatar.mutate()}>
                                        {t('Remover')}
                                    </Botao>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className={cls('p-5', CARTAO)}>
                        <h3 className="mb-4 text-sm font-bold text-slate-800">
                            <i className="fas fa-user-pen mr-2 text-indigo-500" aria-hidden="true" />
                            {t('Os seus dados')}
                        </h3>

                        <AvisoDeErro erro={guardarPerfil.error} />

                        <div className="mt-3 grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Nome')} obrigatorio erro={erros(guardarPerfil.error).name}>
                                <input type="text" value={perfil.name}
                                    onChange={(e) => porPerfil({ ...perfil, name: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('E-mail')} obrigatorio erro={erros(guardarPerfil.error).email}
                                ajuda={t('É por aqui que entra no sistema.')}>
                                <input type="email" value={perfil.email}
                                    onChange={(e) => porPerfil({ ...perfil, email: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Telefone')} erro={erros(guardarPerfil.error).phone}>
                                <input type="text" value={perfil.phone}
                                    onChange={(e) => porPerfil({ ...perfil, phone: e.target.value })}
                                    placeholder="+244 900 000 000" className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Sobre si')} erro={erros(guardarPerfil.error).bio}
                                className="sm:col-span-2">
                                <textarea rows={3} value={perfil.bio}
                                    onChange={(e) => porPerfil({ ...perfil, bio: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <div className="mt-4">
                            <Botao cor="primaria" tom="solida" icone="fa-floppy-disk"
                                aTrabalhar={guardarPerfil.isPending} onClick={() => guardarPerfil.mutate()}>
                                {t('Guardar perfil')}
                            </Botao>
                        </div>
                    </div>
                </div>
            </PainelDoSeparador>

            {/* ─── A segurança ───────────────────────────────────────── */}

            <PainelDoSeparador chave="seguranca" activa={activa}>
                <div className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <CartaoNumero
                            rotulo={t('Último acesso')} valor={c.perfil.ultimo_acesso ?? t('Primeira vez')}
                            icone="fa-clock" tom="verde" />
                        <CartaoNumero
                            rotulo={t('Senha mudada')} valor={c.perfil.senha_mudada_em ?? t('Nunca mudada')}
                            icone="fa-key" tom={c.perfil.senha_mudada_em ? 'azul' : 'ambar'}
                            nota={c.perfil.senha_mudada_em ? undefined : t('Uma senha que nunca mudou é a mais fácil de adivinhar')} />
                    </div>

                    <div className={cls('p-5', CARTAO)}>
                        <h3 className="mb-4 text-sm font-bold text-slate-800">
                            <i className="fas fa-key mr-2 text-indigo-500" aria-hidden="true" />
                            {t('Mudar a senha')}
                        </h3>

                        <AvisoDeErro erro={mudarSenha.error} />

                        <div className="mt-3 grid gap-4 sm:grid-cols-3">
                            <Campo etiqueta={t('Senha actual')} obrigatorio erro={erros(mudarSenha.error).actual}>
                                <input type="password" autoComplete="current-password" value={senha.actual}
                                    onChange={(e) => porSenha({ ...senha, actual: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Senha nova')} obrigatorio erro={erros(mudarSenha.error).nova}
                                ajuda={t('Mínimo 8 caracteres, com letras e números, e diferente da actual.')}>
                                <input type="password" autoComplete="new-password" value={senha.nova}
                                    onChange={(e) => porSenha({ ...senha, nova: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Repetir a nova')} obrigatorio>
                                <input type="password" autoComplete="new-password" value={senha.nova_confirmation}
                                    onChange={(e) => porSenha({ ...senha, nova_confirmation: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <div className="mt-4">
                            <Botao cor="primaria" tom="solida" icone="fa-check"
                                disabled={!senha.actual || !senha.nova}
                                aTrabalhar={mudarSenha.isPending} onClick={() => mudarSenha.mutate()}>
                                {t('Mudar a senha')}
                            </Botao>
                        </div>
                    </div>

                    <div className={cls('border border-amber-200 bg-amber-50 p-4', RAIO)}>
                        <p className="text-sm font-bold text-amber-900">
                            <i className="fas fa-shield-halved mr-2" aria-hidden="true" />
                            {t('Três coisas que valem mais do que uma senha comprida')}
                        </p>
                        <ul className="mt-2 space-y-1 text-xs text-amber-800">
                            <li>{t('· Não use a mesma senha noutro serviço — se um deles for roubado, os outros vão atrás.')}</li>
                            <li>{t('· Ninguém do suporte lhe pede a senha. Ninguém, nunca.')}</li>
                            <li>{t('· Termine a sessão em computadores que não são seus.')}</li>
                        </ul>
                    </div>
                </div>
            </PainelDoSeparador>

            {/* ─── A privacidade (RGPD / LGPD / Lei 22/11) ────────────────
                Só se monta quando se abre: são sessões, entradas e o
                inventário, e ninguém os precisa para mudar o nome. */}
            <PainelDoSeparador chave="privacidade" activa={activa}>
                {activa === 'privacidade' && <Privacidade />}
            </PainelDoSeparador>

            {/* ─── A empresa ─────────────────────────────────────────── */}

            <Modal
                aberto={formularioDeEmpresa !== null}
                aoFechar={() => { porFormularioDeEmpresa(null); porAEditar(null); }}
                titulo={aEditar ? t('Editar empresa') : t('Nova empresa')}
                subtitulo={aEditar ? undefined : t('Nasce com o mesmo plano e os mesmos módulos da empresa activa')}
                icone="fa-building"
                cor="primaria"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porFormularioDeEmpresa(null); porAEditar(null); }}>
                            {t('Cancelar')}
                        </Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check"
                            aTrabalhar={criarEmpresa.isPending || editarEmpresa.isPending}
                            onClick={() => {
                                if (!formularioDeEmpresa) return;

                                if (aEditar) editarEmpresa.mutate(formularioDeEmpresa);
                                else criarEmpresa.mutate(formularioDeEmpresa);
                            }}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formularioDeEmpresa && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={aEditar ? editarEmpresa.error : criarEmpresa.error} />

                        {aEditar && (
                            <div className="flex flex-wrap items-center gap-3">
                                <label className={cls(
                                    'inline-flex cursor-pointer items-center gap-2 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100',
                                    FOCO,
                                )}>
                                    <i className="fas fa-image" aria-hidden="true" />
                                    {t('Trocar logótipo')}
                                    <input type="file" accept="image/*" className="hidden"
                                        onChange={(e) => {
                                            const f = e.target.files?.[0];

                                            if (f && aEditar) logotipo.mutate({ id: aEditar, ficheiro: f });
                                        }} />
                                </label>
                                {logotipo.isPending && (
                                    <span className="text-xs text-indigo-600">
                                        <i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A carregar…')}
                                    </span>
                                )}
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Nome')} obrigatorio className="sm:col-span-2"
                                erro={erros(aEditar ? editarEmpresa.error : criarEmpresa.error).name}>
                                <input type="text" value={formularioDeEmpresa.name}
                                    onChange={(e) => porFormularioDeEmpresa({ ...formularioDeEmpresa, name: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('NIF')} obrigatorio
                                ajuda={t('O NIF da EMPRESA — não o do bilhete de identidade de alguém.')}
                                erro={erros(aEditar ? editarEmpresa.error : criarEmpresa.error).nif}>
                                <input type="text" value={formularioDeEmpresa.nif}
                                    onChange={(e) => porFormularioDeEmpresa({ ...formularioDeEmpresa, nif: e.target.value.toUpperCase() })}
                                    className={cls(entrada, 'uppercase')} />
                            </Campo>

                            <Campo etiqueta={t('Regime fiscal')} obrigatorio
                                erro={erros(aEditar ? editarEmpresa.error : criarEmpresa.error).regime}>
                                <select value={formularioDeEmpresa.regime}
                                    onChange={(e) => porFormularioDeEmpresa({ ...formularioDeEmpresa, regime: e.target.value })}
                                    className={entrada}>
                                    <option value="regime_geral">{t('Regime Geral')}</option>
                                    <option value="regime_simplificado">{t('Regime Simplificado')}</option>
                                    <option value="regime_nao_sujeicao">{t('Regime de Não Sujeição')}</option>
                                </select>
                            </Campo>

                            <Campo etiqueta={t('Telefone')}>
                                <input type="text" value={formularioDeEmpresa.phone}
                                    onChange={(e) => porFormularioDeEmpresa({ ...formularioDeEmpresa, phone: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('E-mail')}
                                erro={erros(aEditar ? editarEmpresa.error : criarEmpresa.error).email}>
                                <input type="email" value={formularioDeEmpresa.email}
                                    onChange={(e) => porFormularioDeEmpresa({ ...formularioDeEmpresa, email: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Morada')} className="sm:col-span-2">
                                <input type="text" value={formularioDeEmpresa.address}
                                    onChange={(e) => porFormularioDeEmpresa({ ...formularioDeEmpresa, address: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ─── Arquivar ──────────────────────────────────────────── */}

            <Modal
                aberto={aArquivar !== null}
                aoFechar={() => { porAArquivar(null); porConfirmacao(''); }}
                titulo={t('Remover empresa da conta')}
                subtitulo={aArquivar?.nome}
                icone="fa-box-archive"
                cor="perigo"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porAArquivar(null); porConfirmacao(''); }}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-box-archive"
                            disabled={!arquivo.data?.pode || confirmacao.trim() !== (aArquivar?.nome ?? '').trim()}
                            aTrabalhar={arquivar.isPending}
                            onClick={() => arquivar.mutate()}>
                            {t('Remover e arquivar')}
                        </Botao>
                    </>
                }
            >
                {arquivo.isPending ? (
                    <Carregando linhas={3} />
                ) : arquivo.isError ? (
                    <AvisoDeErro erro={arquivo.error} />
                ) : arquivo.data ? (
                    <div className="space-y-4">
                        <AvisoDeErro erro={arquivar.error} />

                        <p className={cls('border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700', RAIO)}>
                            {t('A empresa sai da sua conta e fica ARQUIVADA: nada é destruído, e os dados podem ser recuperados.')}
                        </p>

                        {arquivo.data.razoes.length > 0 && (
                            <ul className={cls('space-y-1 border-2 border-red-200 bg-red-50 p-3 text-sm text-red-800', RAIO)}>
                                {arquivo.data.razoes.map((r) => (
                                    <li key={r}>
                                        <i className="fas fa-circle-xmark mr-1.5" aria-hidden="true" />{r}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {arquivo.data.clientes > 0 && arquivo.data.pode && (
                            <p className={cls('border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900', RAIO)}>
                                <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                                {t('Esta empresa tem :n cliente(s). Os dados ficam arquivados e recuperáveis.', {
                                    n: numero(arquivo.data.clientes),
                                })}
                            </p>
                        )}

                        {arquivo.data.pode && (
                            <Campo
                                etiqueta={t('Escreva o nome da empresa para confirmar')}
                                obrigatorio
                                erro={erros(arquivar.error).confirmacao}
                                ajuda={t('É a única confirmação que não se carrega por engano.')}
                            >
                                <input type="text" value={confirmacao}
                                    onChange={(e) => porConfirmacao(e.target.value)}
                                    placeholder={arquivo.data.nome} className={entrada} />
                            </Campo>
                        )}
                    </div>
                ) : null}
            </Modal>

            {/* ─── Contratar um plano ────────────────────────────────── */}

            <Modal
                aberto={aContratar !== null}
                aoFechar={() => { porAContratar(null); porPasso(1); porComprovativo(null); }}
                titulo={aContratar?.nome ?? ''}
                subtitulo={passo === 1 ? t('Escolha o período') : t('Como se paga')}
                icone="fa-crown"
                cor="primaria"
                largura="lg"
                rodape={
                    passo === 1 ? (
                        <>
                            <Botao onClick={() => porAContratar(null)}>{t('Cancelar')}</Botao>
                            <Botao cor="primaria" tom="solida" icone="fa-arrow-right"
                                onClick={() => porPasso(2)}>
                                {t('Continuar')}
                            </Botao>
                        </>
                    ) : (
                        <>
                            <Botao icone="fa-arrow-left" onClick={() => porPasso(1)}>{t('Voltar')}</Botao>
                            <Botao cor="primaria" tom="solida" icone="fa-check"
                                aTrabalhar={contratar.isPending} onClick={() => contratar.mutate()}>
                                {t('Confirmar pedido')}
                            </Botao>
                        </>
                    )
                }
            >
                {aContratar && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={contratar.error} />

                        {passo === 1 ? (
                            <>
                                {aContratar.auto_activa && aContratar.dias_de_teste > 0 && (
                                    <p className={cls('border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900', RAIO)}>
                                        <i className="fas fa-gift mr-2" aria-hidden="true" />
                                        {t('Este plano activa-se logo, com :n dias gratuitos. O período fica fixo e não se escolhe.', {
                                            n: numero(aContratar.dias_de_teste),
                                        })}
                                    </p>
                                )}

                                <div className="grid gap-3 sm:grid-cols-4">
                                    {CICLOS.map((x) => {
                                        const preco = aContratar.precos[x.valor as keyof typeof aContratar.precos];
                                        const fixo = aContratar.auto_activa && aContratar.dias_de_teste > 0;

                                        return (
                                            <button key={x.valor} type="button"
                                                disabled={fixo && ciclo !== x.valor}
                                                onClick={() => porCiclo(x.valor)}
                                                className={cls(
                                                    'flex flex-col items-center gap-1 border-2 p-3 transition-all duration-200',
                                                    RAIO, FOCO,
                                                    ciclo === x.valor
                                                        ? 'border-indigo-500 bg-indigo-50'
                                                        : 'border-slate-200 bg-white hover:border-indigo-300',
                                                    fixo && ciclo !== x.valor && 'cursor-not-allowed opacity-40',
                                                )}>
                                                <i className={cls('fas text-xl', x.icone, ciclo === x.valor ? 'text-indigo-600' : 'text-slate-400')} aria-hidden="true" />
                                                <span className="text-xs font-semibold text-slate-700">{x.rotulo()}</span>
                                                <span className="text-sm font-bold tabular-nums text-slate-800">{kz(preco)}</span>
                                            </button>
                                        );
                                    })}
                                </div>

                                {aContratar.funcionalidades.length > 0 && (
                                    <ul className="grid gap-1.5 sm:grid-cols-2">
                                        {aContratar.funcionalidades.map((f) => (
                                            <li key={f} className="flex items-start gap-2 text-sm text-slate-600">
                                                <i className="fas fa-circle-check mt-0.5 text-emerald-500" aria-hidden="true" />
                                                {f}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </>
                        ) : (
                            <>
                                <div className={cls('flex flex-wrap items-center justify-between gap-2 border-2 border-indigo-200 bg-indigo-50 p-4', RAIO)}>
                                    <p className="text-sm font-semibold text-indigo-900">
                                        {aContratar.nome} · {CICLOS.find((x) => x.valor === ciclo)?.rotulo()}
                                    </p>
                                    <p className="text-xl font-bold tabular-nums text-indigo-900">
                                        {kz(aContratar.precos[ciclo as keyof typeof aContratar.precos])}
                                    </p>
                                </div>

                                {/*
                                  * A CONTA PARA ONDE SE TRANSFERE.
                                  *
                                  * Estava escrita à mão em dois Blade diferentes
                                  * — e as duas cópias já não diziam o mesmo IBAN.
                                  * Agora vem das definições do sistema, de um
                                  * sítio só.
                                  */}
                                {c.conta_da_plataforma && (
                                    <div className={cls('p-4', CARTAO)}>
                                        <h4 className="mb-3 text-sm font-bold text-slate-800">
                                            <i className="fas fa-building-columns mr-2 text-emerald-600" aria-hidden="true" />
                                            {t('Dados para a transferência')}
                                        </h4>
                                        <dl className="space-y-2 text-sm">
                                            <DadoBancario rotulo={t('Banco')} valor={c.conta_da_plataforma.banco} />
                                            <DadoBancario rotulo={t('Titular')} valor={c.conta_da_plataforma.titular} />
                                            <DadoBancario rotulo={t('IBAN')} valor={c.conta_da_plataforma.iban} copiavel />
                                        </dl>
                                    </div>
                                )}

                                <Campo
                                    etiqueta={t('Comprovativo de pagamento')}
                                    erro={erros(contratar.error).comprovativo}
                                    ajuda={t('PDF, JPG ou PNG, até 5 MB. Pode anexá-lo mais tarde, em «Pagar».')}
                                >
                                    <input type="file" accept="image/*,application/pdf"
                                        onChange={(e) => porComprovativo(e.target.files?.[0] ?? null)}
                                        className={cls(entrada, 'file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700')} />
                                </Campo>

                                {comprovativo && (
                                    <p className="text-xs text-emerald-700">
                                        <i className="fas fa-circle-check mr-1.5" aria-hidden="true" />
                                        {comprovativo.name}
                                    </p>
                                )}
                            </>
                        )}
                    </div>
                )}
            </Modal>

            {/* ─── Anexar o comprovativo a um pedido ─────────────────── */}

            <Modal
                aberto={aPagar !== null}
                aoFechar={() => { porAPagar(null); porFicheiroDoPedido(null); }}
                titulo={t('Anexar comprovativo')}
                subtitulo={aPagar ? `${aPagar.numero} · ${kz(aPagar.valor)}` : ''}
                icone="fa-credit-card"
                cor="primaria"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => { porAPagar(null); porFicheiroDoPedido(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-paperclip"
                            disabled={!ficheiroDoPedido}
                            aTrabalhar={anexar.isPending} onClick={() => anexar.mutate()}>
                            {t('Anexar')}
                        </Botao>
                    </>
                }
            >
                {aPagar && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={anexar.error} />

                        {c.conta_da_plataforma && (
                            <div className={cls('p-4', CARTAO)}>
                                <dl className="space-y-2 text-sm">
                                    <DadoBancario rotulo={t('Banco')} valor={c.conta_da_plataforma.banco} />
                                    <DadoBancario rotulo={t('Titular')} valor={c.conta_da_plataforma.titular} />
                                    <DadoBancario rotulo={t('IBAN')} valor={c.conta_da_plataforma.iban} copiavel />
                                    {/* A REFERÊNCIA é o que liga o dinheiro que
                                        entrou ao pedido que o espera. */}
                                    <DadoBancario rotulo={t('Referência')} valor={aPagar.referencia} copiavel />
                                </dl>
                            </div>
                        )}

                        <Campo etiqueta={t('Comprovativo')} obrigatorio
                            erro={erros(anexar.error).comprovativo}
                            ajuda={t('PDF, JPG ou PNG, até 5 MB.')}>
                            <input type="file" accept="image/*,application/pdf"
                                onChange={(e) => porFicheiroDoPedido(e.target.files?.[0] ?? null)}
                                className={cls(entrada, 'file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700')} />
                        </Campo>
                    </div>
                )}
            </Modal>
        </div>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

function Linha({ icone, rotulo, valor }: { icone: string; rotulo: string; valor: string }) {
    return (
        <div className="flex items-center gap-3">
            <span className="grid h-8 w-8 flex-none place-items-center rounded-lg bg-slate-100 text-slate-500">
                <i className={`fas ${icone} text-xs`} aria-hidden="true" />
            </span>
            <div className="min-w-0">
                <dt className="text-[11px] text-slate-400">{rotulo}</dt>
                <dd className="truncate text-sm font-semibold text-slate-800">{valor}</dd>
            </div>
        </div>
    );
}

function DadoBancario({ rotulo, valor, copiavel }: { rotulo: string; valor: string; copiavel?: boolean }) {
    return (
        <div className={cls('flex items-center justify-between gap-3 bg-slate-50 px-3 py-2', RAIO)}>
            <div className="min-w-0">
                <dt className="text-[11px] text-slate-400">{rotulo}</dt>
                <dd className="truncate font-semibold text-slate-800">{valor}</dd>
            </div>
            {copiavel && (
                <Botao altura="pequeno" icone="fa-copy"
                    onClick={() => void navigator.clipboard?.writeText(valor)}
                    aria-label={t('Copiar')} />
            )}
        </div>
    );
}
