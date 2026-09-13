import { useQuery } from '@tanstack/react-query';
import { useState, type CSSProperties, type ReactNode } from 'react';

import { casca, type AcessoRapido, type PaginaInicial } from '@/api/casca';
import { etiquetaIntl, t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { cascata } from '@/ui/SemNada';
import { cls, data } from '@/ui/tokens';

import Avisos from './Avisos';

const PADRAO = "url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")";

const COR_DO_ACESSO: Record<AcessoRapido['cor'], { borda: string; fundo: string; icone: string; seta: string }> = {
    verde: { borda: 'border-green-200', fundo: 'from-green-50 to-emerald-50', icone: 'from-green-500 to-emerald-600 shadow-green-500/50', seta: 'text-green-400' },
    roxo: { borda: 'border-purple-200', fundo: 'from-purple-50 to-pink-50', icone: 'from-purple-500 to-pink-600 shadow-purple-500/50', seta: 'text-purple-400' },
    azul: { borda: 'border-blue-200', fundo: 'from-blue-50 to-cyan-50', icone: 'from-blue-500 to-cyan-600 shadow-blue-500/50', seta: 'text-blue-400' },
    amarelo: { borda: 'border-yellow-200', fundo: 'from-yellow-50 to-orange-50', icone: 'from-yellow-500 to-orange-500 shadow-yellow-500/50', seta: 'text-yellow-400' },
};

const kz = (n: number) => n.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 0 });

/**
 * A PÁGINA INICIAL (`/home`) de quem trabalha numa empresa.
 *
 * O que ela diz vem de `/api/v1/casca/inicio` (App\Services\Casca\PaginaInicial),
 * que já sabe o que cada um pode ver: o pacote só a quem trata dele, os números
 * só a quem os pode abrir, as facturas do mês pela regra do autor.
 *
 * O PAGAMENTO EM ANÁLISE: o Blade ia buscar a própria página de 15 em 15
 * segundos e procurava o aviso no HTML. Aqui pergunta-se ao servidor com a
 * mesma cadência, e o aviso muda sozinho quando o pedido é aprovado.
 */
export default function Inicio({ estado = null }: { estado?: string | null }) {
    const pedido = useQuery({
        queryKey: ['casca', 'inicio'],
        queryFn: casca.inicio,
        refetchInterval: (q) => (q.state.data?.avisos?.pedido_pendente ? 15_000 : false),
    });
    const [foxVisivel, porFoxVisivel] = useState(true);

    if (pedido.isPending) return <Carregando linhas={8} />;
    if (pedido.isError) return <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-700">{t('Não foi possível abrir a página inicial.')}</p>;

    const d = pedido.data;
    // Só a primeira letra em maiúscula: o `capitalize` do CSS dava «13 De Setembro De 2026».
    const dia = new Date(`${d.hoje}T12:00:00`).toLocaleDateString(etiquetaIntl(), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const hoje = dia.charAt(0).toLocaleUpperCase() + dia.slice(1);

    return (
        <div className="mx-auto max-w-7xl">
            {estado && (
                <div role="status" className="animate-fade-in mb-6 flex items-center rounded-xl border-l-4 border-green-500 bg-green-100 px-6 py-4 text-green-700 shadow-lg">
                    <i className="fas fa-check-circle mr-3 text-2xl" aria-hidden="true" /><span className="font-medium">{estado}</span>
                </div>
            )}

            {/* Os avisos do dono da plataforma, em cima de tudo. */}
            <Avisos />

            {d.avisos?.fox && foxVisivel && <BannerFox fox={d.avisos.fox} aoFechar={() => porFoxVisivel(false)} />}

            {d.sem_empresa && (
                <Alerta gradiente="from-red-500 to-orange-500" icone="fa-exclamation-triangle" titulo={t('Nenhuma Empresa Configurada')} tituloIcone="fa-building" pulsa>
                    <p className="mb-4 text-red-100">{t('Você ainda não tem nenhuma empresa cadastrada. Para começar a usar o sistema, é necessário criar sua primeira empresa.')}</p>
                    <div className="flex flex-wrap gap-3">
                        <BotaoBranco href="/register" tom="text-red-600 hover:bg-red-50" icone="fa-plus-circle">{t('Criar Empresa Agora')}</BotaoBranco>
                        <BotaoVidro href="/my-account?tab=companies" icone="fa-cog">{t('Gerenciar Conta')}</BotaoVidro>
                    </div>
                </Alerta>
            )}

            {d.avisos?.pedido_pendente && (
                <Alerta gradiente="from-orange-500 to-yellow-500" icone="fa-clock" titulo={t('Pagamento em Análise')} tituloIcone="fa-hourglass-half" iconePulsa>
                    <p className="mb-2 text-orange-100">{t('Seu pagamento foi recebido e está sendo verificado por nossa equipe. Você receberá acesso total assim que for aprovado (geralmente em até 24h).')}</p>
                    <p className="mb-4 text-sm text-orange-200"><i className="fas fa-sync-alt fa-spin mr-1" style={{ animationDuration: '3s' }} aria-hidden="true" />{t('Esta página será atualizada automaticamente quando seu pedido for aprovado.')}</p>
                    <BotaoBranco href="/my-account?tab=plan" tom="text-orange-600 hover:bg-orange-50" icone="fa-file-invoice">{t('Ver Status do Pedido')}</BotaoBranco>
                </Alerta>
            )}

            {d.avisos?.sem_plano && (
                <Alerta gradiente="from-yellow-500 to-orange-500" icone="fa-crown" titulo={t('Nenhum Plano Ativo')} tituloIcone="fa-star">
                    <p className="mb-4 text-yellow-100">{t('Sua empresa :empresa não possui um plano ativo. Escolha um plano para desbloquear todos os recursos do sistema.', { empresa: d.avisos.empresa ?? t('atual') })}</p>
                    <div className="flex flex-wrap gap-3">
                        <BotaoBranco href="/#planos" tom="text-yellow-600 hover:bg-yellow-50" icone="fa-shopping-cart">{t('Ver Planos')}</BotaoBranco>
                        <BotaoVidro href="/my-account?tab=plan" icone="fa-info-circle">{t('Meu Plano Atual')}</BotaoVidro>
                    </div>
                </Alerta>
            )}

            {d.avisos?.em_teste && (
                <Alerta gradiente="from-blue-500 to-purple-500" icone="fa-clock" titulo={t('Período de Teste Ativo')} tituloIcone="fa-gift">
                    <p className="mb-4 text-blue-100">
                        {t('Você está no período de teste de :dias dias do plano :plano.', { dias: d.avisos.em_teste.dias, plano: d.avisos.em_teste.plano ?? '' })}
                        {d.avisos.em_teste.termina_em && <> {t('Expira em: :data', { data: data(d.avisos.em_teste.termina_em) })} ({diasAte(d.avisos.em_teste.termina_em)})</>}
                    </p>
                    <BotaoBranco href="/my-account?tab=plan" tom="text-blue-600 hover:bg-blue-50" icone="fa-arrow-up">{t('Fazer Upgrade')}</BotaoBranco>
                </Alerta>
            )}

            {/* Boas-vindas */}
            <div className="card-hover animate-fade-in relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-blue-600 via-purple-600 to-pink-500 p-8 shadow-2xl">
                <div className="absolute inset-0 opacity-10" style={{ backgroundImage: PADRAO }} />
                <div className="relative flex items-center justify-between">
                    <div className="flex-1">
                        <div className="mb-4 inline-flex items-center rounded-full bg-white/20 px-4 py-2 backdrop-blur-sm">
                            <span className="mr-2 h-2 w-2 animate-pulse rounded-full bg-green-400" /><span className="text-sm font-medium text-white">{t('Online')}</span>
                        </div>
                        <h2 className="mb-3 text-3xl font-bold text-white sm:text-4xl">{t('Olá, :nome!', { nome: d.utilizador.nome })} 👋</h2>
                        <p className="text-lg text-blue-100">{hoje}</p>
                    </div>
                    <div className="hidden items-center justify-center md:flex">
                        <div className="relative">
                            <div className="icon-float flex h-24 w-24 rotate-6 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                                <i className="fas fa-user text-5xl text-white" aria-hidden="true" />
                            </div>
                            <div className="absolute -right-2 -top-2 flex h-8 w-8 items-center justify-center rounded-full border-4 border-white bg-green-500 shadow-lg">
                                <i className="fas fa-check text-xs text-white" aria-hidden="true" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Acessos rápidos */}
            {d.acessos.length > 0 && (
                <div className="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                    {d.acessos.map((a, i) => {
                        const c = COR_DO_ACESSO[a.cor];
                        return (
                            <a key={a.url} href={a.url} style={cascata(i)} className={cls('entra card-hover group overflow-hidden rounded-2xl border bg-white shadow-lg', c.borda)}>
                                <div className={cls('bg-gradient-to-br p-6', c.fundo)}>
                                    <div className="mb-4 flex items-center justify-between">
                                        <div className={cls('icon-float flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br shadow-lg', c.icone)}>
                                            <i className={cls('fas text-2xl text-white', a.icone)} aria-hidden="true" />
                                        </div>
                                        <i className={cls('fas fa-arrow-right transition-transform group-hover:translate-x-1', c.seta)} aria-hidden="true" />
                                    </div>
                                    <h3 className="mb-2 text-xl font-bold text-gray-900">{a.rotulo}</h3>
                                    <p className="text-sm text-gray-600">{a.nota}</p>
                                </div>
                            </a>
                        );
                    })}
                </div>
            )}

            {d.empresa && (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className={cls('entra card-hover rounded-2xl border border-purple-100 bg-white p-6 shadow-lg', d.mostra_subscricao ? 'lg:col-span-2' : 'lg:col-span-3')}>
                        <Titulo icone="fa-building" gradiente="from-purple-500 to-pink-500">{t('Informações da Empresa')}</Titulo>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Ficha icone="fa-briefcase" tom="purple" rotulo={t('Empresa')} valor={d.empresa.nome} />
                            <Ficha icone="fa-id-card" tom="blue" rotulo={t('NIF')} valor={d.empresa.nif ?? 'N/A'} />
                            <Ficha icone="fa-envelope" tom="green" rotulo={t('Email')} valor={d.empresa.email ?? 'N/A'} />
                            <Ficha icone="fa-phone" tom="orange" rotulo={t('Telefone')} valor={d.empresa.telefone ?? 'N/A'} />
                        </div>
                    </div>

                    {d.mostra_subscricao && (
                        <div className="entra card-hover relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 via-purple-600 to-pink-500 p-6 text-white shadow-xl" style={cascata(1)}>
                            <div className="absolute inset-0 opacity-10" style={{ backgroundImage: PADRAO }} />
                            <div className="relative">
                                <h3 className="mb-6 flex items-center text-xl font-bold">
                                    <span className="mr-3 flex h-10 w-10 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm"><i className="fas fa-crown" aria-hidden="true" /></span>
                                    {t('Subscrição')}
                                </h3>
                                {d.subscricao ? (
                                    <div className="space-y-3">
                                        <LinhaDoPacote rotulo={t('Plano:')}>{d.subscricao.plano}</LinhaDoPacote>
                                        <LinhaDoPacote rotulo={t('Valor:')}>{d.subscricao.valor.toLocaleString(etiquetaIntl(), { minimumFractionDigits: 2 })} Kz</LinhaDoPacote>
                                        <LinhaDoPacote rotulo={t('Ciclo:')}>{d.subscricao.ciclo === 'yearly' ? t('Anual') : t('Mensal')}</LinhaDoPacote>
                                        <LinhaDoPacote rotulo={t('Início:')}>{d.subscricao.inicio ? data(d.subscricao.inicio) : 'N/A'}</LinhaDoPacote>
                                        <LinhaDoPacote rotulo={t('Renovação:')}>{d.subscricao.renovacao ? data(d.subscricao.renovacao) : 'N/A'}</LinhaDoPacote>
                                        <LinhaDoPacote rotulo={t('Status:')}>
                                            <span className="inline-flex items-center rounded-full bg-green-500 px-3 py-1 text-xs font-bold shadow-lg">
                                                <span className="mr-2 h-2 w-2 animate-pulse rounded-full bg-white" />{d.subscricao.estado === 'trial' ? t('Em teste') : t('Activa')}
                                            </span>
                                        </LinhaDoPacote>
                                    </div>
                                ) : (
                                    <div className="py-6 text-center">
                                        <div className="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-white/10"><i className="fas fa-info-circle text-3xl" aria-hidden="true" /></div>
                                        <p className="text-sm opacity-90">{t('Sem subscrição ativa')}</p>
                                        <p className="mt-1 text-xs opacity-75">{t('Entre em contato com o administrador')}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {d.empresa && (
                <div className="mt-8">
                    <Titulo icone="fa-puzzle-piece" gradiente="from-blue-500 to-purple-600" grande>{t('Módulos Ativos')}</Titulo>
                    {d.modulos.length > 0 ? (
                        <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                            {d.modulos.map((m, i) => (
                                <div key={m.nome} style={cascata(i)} className={cls('entra card-hover group rounded-xl border-l-4 bg-white p-4 shadow-lg', m.activo ? 'border-green-500' : 'border-gray-300')}>
                                    <div className="flex items-center gap-4">
                                        <div className={cls('icon-float flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br shadow-lg', m.activo ? 'from-green-500 to-emerald-600' : 'from-gray-500 to-gray-600')}>
                                            <i className={cls('fas text-xl text-white', iconeDoModulo(m.icone))} aria-hidden="true" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <h4 className="font-bold text-gray-900">{m.nome}</h4>
                                            {m.descricao && <p className="line-clamp-2 text-xs text-gray-500">{m.descricao}</p>}
                                        </div>
                                        {m.activo ? (
                                            <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-bold text-green-700"><span className="mr-1.5 h-1.5 w-1.5 animate-pulse rounded-full bg-green-500" />{t('Ativo')}</span>
                                        ) : (
                                            <span className="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-bold text-gray-600">{t('Inativo')}</span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="mb-8 flex items-center rounded-xl border-l-4 border-yellow-500 bg-yellow-50 p-6">
                            <i className="fas fa-exclamation-triangle mr-3 text-2xl text-yellow-600" aria-hidden="true" />
                            <div>
                                <h4 className="font-bold text-yellow-800">{t('Nenhum módulo ativo')}</h4>
                                <p className="text-sm text-yellow-700">{t('Entre em contato com o administrador para ativar módulos.')}</p>
                            </div>
                        </div>
                    )}
                </div>
            )}

            <Numeros n={d.numeros} />
        </div>
    );
}

/** O ícone guardado com ou sem o prefixo `fa-`. */
function iconeDoModulo(icone: string | null): string {
    if (!icone) return 'fa-puzzle-piece';
    const nome = icone.replace(/^fas?\s+/, '');
    return nome.startsWith('fa-') ? nome : `fa-${nome}`;
}

function diasAte(iso: string): string {
    const dias = Math.round((new Date(`${iso}T12:00:00`).getTime() - Date.now()) / 86_400_000);
    return new Intl.RelativeTimeFormat(etiquetaIntl(), { numeric: 'auto' }).format(dias, 'day');
}

function Numeros({ n }: { n: PaginaInicial['numeros'] }) {
    const cartoes: Array<{ rotulo: string; valor: string; nota: string; icone: string; cor: string; borda: string; texto: string }> = [];

    if (n.clientes !== undefined) cartoes.push({ rotulo: t('Clientes'), valor: kz(n.clientes), nota: t('Total registados'), icone: 'fa-users', cor: 'from-green-500 to-emerald-600 shadow-green-500/50', borda: 'border-green-200', texto: 'text-green-600' });
    if (n.produtos !== undefined) cartoes.push({ rotulo: t('Produtos'), valor: kz(n.produtos), nota: t('No catálogo'), icone: 'fa-box', cor: 'from-purple-500 to-pink-600 shadow-purple-500/50', borda: 'border-purple-200', texto: 'text-purple-600' });
    if (n.facturas_do_mes !== undefined) {
        cartoes.push({ rotulo: t('Faturas'), valor: kz(n.facturas_do_mes), nota: n.so_o_seu ? t('Emitidas por si este mês') : t('Este mês'), icone: 'fa-file-invoice', cor: 'from-blue-500 to-cyan-600 shadow-blue-500/50', borda: 'border-blue-200', texto: 'text-blue-600' });
        cartoes.push({ rotulo: t('Facturado'), valor: kz(n.facturado_no_mes ?? 0), nota: n.so_o_seu ? t('Kz seus, este mês') : t('Kz este mês'), icone: 'fa-money-bill-wave', cor: 'from-orange-500 to-red-600 shadow-orange-500/50', borda: 'border-orange-200', texto: 'text-orange-600' });
    }

    if (!cartoes.length) return null;

    return (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
            {cartoes.map((c, i) => (
                <div key={c.rotulo} style={cascata(i)} className={cls('entra card-hover rounded-2xl border bg-white p-6 shadow-lg', c.borda)}>
                    <div className={cls('icon-float mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br shadow-lg', c.cor)}>
                        <i className={cls('fas text-2xl text-white', c.icone)} aria-hidden="true" />
                    </div>
                    <p className={cls('mb-1 text-sm font-bold', c.texto)}>{c.rotulo}</p>
                    <p className="mb-1 text-4xl font-bold tabular-nums text-gray-900">{c.valor}</p>
                    <p className="text-xs text-gray-500">{c.nota}</p>
                </div>
            ))}
        </div>
    );
}

function BannerFox({ fox, aoFechar }: { fox: NonNullable<NonNullable<PaginaInicial['avisos']>['fox']>; aoFechar: () => void }) {
    const esgotado = fox.tecto !== null && (fox.emitidos ?? 0) >= fox.tecto;

    return (
        <div className="animate-scale-in relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-orange-400 via-red-400 to-pink-400 p-6 text-white shadow-2xl">
            <button type="button" onClick={aoFechar} aria-label={t('Fechar')} className="absolute right-3 top-3 text-white/80 transition hover:rotate-90 hover:text-white">
                <i className="fas fa-times text-xl" aria-hidden="true" />
            </button>
            <div className="flex items-center">
                <div className="shrink-0 animate-bounce text-6xl" style={{ animationDuration: '2s' }}>🦊</div>
                <div className="ml-6 flex-1">
                    <h3 className="mb-2 flex flex-wrap items-center gap-3 text-2xl font-bold">
                        <span>🎉 {t('FOX Friendly Ativo!')}</span>
                        <span className="rounded-full bg-white/20 px-3 py-1 text-sm backdrop-blur-sm">{t('3 meses GRÁTIS')}</span>
                    </h3>
                    <p className="mb-3 text-white/90">{t('Você tem acesso a todos os módulos do sistema! Aproveite essa oportunidade especial.')} 🚀</p>
                    <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        {fox.tecto !== null && (
                            <span className="flex items-center"><i className={cls('fas mr-2', esgotado ? 'fa-circle-exclamation' : 'fa-check-circle')} aria-hidden="true" />
                                {t(':emitidos de :tecto documentos', { emitidos: kz(fox.emitidos ?? 0), tecto: kz(fox.tecto) })}{esgotado && <strong> · {t('esgotado')}</strong>}
                            </span>
                        )}
                        <span className="flex items-center"><i className="fas fa-check-circle mr-2" aria-hidden="true" />{t('999 utilizadores')}</span>
                        <span className="flex items-center"><i className="fas fa-check-circle mr-2" aria-hidden="true" />{t('100GB storage')}</span>
                        <span className="flex items-center"><i className="fas fa-check-circle mr-2" aria-hidden="true" />{t('Todos os módulos')}</span>
                    </div>
                </div>
                <div className="absolute -bottom-4 -right-8 hidden rotate-12 text-7xl opacity-20 lg:block" style={{ animation: 'foxFloat 4s ease-in-out infinite' }}>🦊</div>
            </div>
        </div>
    );
}

function Alerta({ gradiente, icone, titulo, tituloIcone, pulsa = false, iconePulsa = false, children }: {
    gradiente: string; icone: string; titulo: string; tituloIcone: string; pulsa?: boolean; iconePulsa?: boolean; children: ReactNode;
}) {
    return (
        <div role="status" className={cls('animate-fade-in mb-6 rounded-2xl bg-gradient-to-r p-6 text-white shadow-xl', gradiente, pulsa && 'animate-pulse')}>
            <div className="flex items-start">
                <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                    <i className={cls('fas text-3xl', icone, iconePulsa && 'animate-pulse')} aria-hidden="true" />
                </div>
                <div className="ml-4 flex-1">
                    <h3 className="mb-2 text-xl font-bold"><i className={cls('fas mr-2', tituloIcone)} aria-hidden="true" />{titulo}</h3>
                    {children}
                </div>
            </div>
        </div>
    );
}

function BotaoBranco({ href, tom, icone, children }: { href: string; tom: string; icone: string; children: ReactNode }) {
    return (
        <a href={href} className={cls('inline-flex items-center rounded-xl bg-white px-6 py-3 font-semibold shadow-lg transition hover:-translate-y-0.5', tom)}>
            <i className={cls('fas mr-2', icone)} aria-hidden="true" />{children}
        </a>
    );
}

function BotaoVidro({ href, icone, children }: { href: string; icone: string; children: ReactNode }) {
    return (
        <a href={href} className="inline-flex items-center rounded-xl bg-white/20 px-6 py-3 font-semibold text-white backdrop-blur-sm transition hover:bg-white/30">
            <i className={cls('fas mr-2', icone)} aria-hidden="true" />{children}
        </a>
    );
}

function Titulo({ icone, gradiente, grande = false, children }: { icone: string; gradiente: string; grande?: boolean; children: ReactNode }) {
    return (
        <h3 className={cls('flex items-center font-bold text-gray-900', grande ? 'mb-6 text-2xl' : 'mb-6 text-xl')}>
            <span className={cls('icon-float mr-3 flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br', gradiente)}>
                <i className={cls('fas text-white', icone)} aria-hidden="true" />
            </span>
            {children}
        </h3>
    );
}

const TONS_DA_FICHA: Record<string, { caixa: string; icone: string; rotulo: string }> = {
    purple: { caixa: 'from-purple-50 to-pink-50 border-purple-100', icone: 'bg-purple-500', rotulo: 'text-purple-600' },
    blue: { caixa: 'from-blue-50 to-cyan-50 border-blue-100', icone: 'bg-blue-500', rotulo: 'text-blue-600' },
    green: { caixa: 'from-green-50 to-emerald-50 border-green-100', icone: 'bg-green-500', rotulo: 'text-green-600' },
    orange: { caixa: 'from-orange-50 to-red-50 border-orange-100', icone: 'bg-orange-500', rotulo: 'text-orange-600' },
};

function Ficha({ icone, tom, rotulo, valor }: { icone: string; tom: keyof typeof TONS_DA_FICHA; rotulo: string; valor: string }) {
    const c = TONS_DA_FICHA[tom] ?? TONS_DA_FICHA.purple!;

    return (
        <div className={cls('rounded-xl border bg-gradient-to-br p-4', c.caixa)}>
            <div className="flex items-start gap-3">
                <span className={cls('flex h-10 w-10 shrink-0 items-center justify-center rounded-lg', c.icone)}><i className={cls('fas text-white', icone)} aria-hidden="true" /></span>
                <div className="min-w-0">
                    <p className={cls('mb-1 text-xs font-semibold', c.rotulo)}>{rotulo}</p>
                    <p className="break-words text-sm font-bold text-gray-900">{valor}</p>
                </div>
            </div>
        </div>
    );
}

function LinhaDoPacote({ rotulo, children }: { rotulo: string; children: ReactNode }) {
    return (
        <div className="flex items-center justify-between rounded-lg bg-white/10 p-3 backdrop-blur-sm" style={{} as CSSProperties}>
            <span className="text-sm font-medium">{rotulo}</span>
            <span className="text-sm font-bold">{children}</span>
        </div>
    );
}
