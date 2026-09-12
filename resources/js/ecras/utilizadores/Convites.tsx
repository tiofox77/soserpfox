import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { utilizadores, type Convite } from '@/api/utilizadores';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, RAIO, cls } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS CONVITES.
 *
 * CONVIDAR NÃO É CRIAR UMA CONTA. Quem é convidado escolhe a sua própria
 * palavra-passe e aceita no seu tempo — o administrador nunca chega a saber a
 * senha de ninguém, e é assim que deve ser.
 *
 * O TECTO DO PLANO conta-se DUAS vezes: aqui, ao convidar, e outra vez ao
 * aceitar, que é onde a conta nasce. A primeira é só delicadeza — poupa ao
 * convidado descobrir a porta fechada depois de já ter escolhido a senha.
 *
 * UM CONVITE EXPIRA ao fim de sete dias. Reenviar dá-lhe mais sete: um link
 * morto dentro de um e-mail novo era o pior dos dois mundos.
 */

const COR_DO_ESTADO: Record<string, 'primaria' | 'bom' | 'aviso' | 'neutra' | 'perigo'> = {
    pending: 'primaria',
    accepted: 'bom',
    expired: 'aviso',
    cancelled: 'neutra',
};

const ICONE_DO_ESTADO: Record<string, string> = {
    pending: 'fa-paper-plane',
    accepted: 'fa-circle-check',
    expired: 'fa-hourglass-end',
    cancelled: 'fa-ban',
};

const VAZIO = { name: '', email: '', role_id: '' };

export default function ConvitesDeUtilizador() {
    const cache = useQueryClient();

    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aCancelar, porACancelar] = useState<Convite | null>(null);

    const opcoes = useQuery({ queryKey: ['utilizadores', 'opcoes'], queryFn: () => utilizadores.opcoes() });
    const lista = useQuery({
        queryKey: ['utilizadores', 'convites'],
        queryFn: () => utilizadores.convites.listar(),
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['utilizadores'] });
    };

    const convidar = useMutation({
        mutationFn: (d: { name: string; email: string; role_id: string }) =>
            utilizadores.convites.convidar(d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); },
        onError: porErro,
    });

    const reenviar = useMutation({
        mutationFn: (id: number) => utilizadores.convites.reenviar(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const cancelar = useMutation({
        mutationFn: (id: number) => utilizadores.convites.cancelar(id),
        onSuccess: (r) => { feito(r.message); porACancelar(null); },
        onError: (e) => { porErro(e); porACancelar(null); },
    });

    if (opcoes.isPending) return <Carregando linhas={6} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const erros = (convidar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};
    const papeisDaCasa = o.papeis.filter((x) => x.empresa === o.empresa_activa);

    const rotuloDoEstado = (e: string) => ({
        pending: t('Pendente'), accepted: t('Aceite'),
        expired: t('Expirado'), cancelled: t('Cancelado'),
    }[e] ?? e);

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Convites')}
                subtitulo={t('Quem é convidado escolhe a sua própria palavra-passe')}
                icone="fa-envelope-open-text"
                cor="ciano"
                accoes={
                    <>
                        {o.permissoes.convidar && (
                            <button type="button" className={ACCAO_DA_FAIXA}
                                onClick={() => porFormulario({ ...VAZIO })}>
                                <i className="fas fa-paper-plane" aria-hidden="true" />
                                {t('Convidar')}
                            </button>
                        )}
                        <a href="/users" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-users" aria-hidden="true" />
                            {t('Utilizadores')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-paper-plane">
                        {t(':n pendentes', { n: numero(resumo?.pendentes ?? 0) })}
                    </EstadoNaFaixa>
                    {!o.limite.cabe_mais && (
                        <EstadoNaFaixa icone="fa-triangle-exclamation">
                            {t('Limite do plano atingido')}
                        </EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero rotulo={t('Convites')} valor={numero(resumo.total)} icone="fa-envelope" tom="teal" />
                    <CartaoNumero rotulo={t('Pendentes')} valor={numero(resumo.pendentes)} icone="fa-paper-plane" tom="azul"
                        nota={t('À espera de quem os recebeu')} />
                    <CartaoNumero rotulo={t('Aceites')} valor={numero(resumo.aceites)} icone="fa-circle-check" tom="verde" />
                    <CartaoNumero rotulo={t('Expirados')} valor={numero(resumo.expirados)} icone="fa-hourglass-end"
                        tom={resumo.expirados > 0 ? 'ambar' : 'cinza'}
                        nota={t('Reenviar dá-lhes mais sete dias')} />
                </div>
            )}

            {lista.isPending ? (
                <Carregando linhas={5} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-envelope-open-text"
                    titulo={t('Nenhum convite')}
                    frase={t('Convidar é dar a alguém a hipótese de entrar com a sua própria palavra-passe — sem que ninguém a saiba.')}
                    accao={o.permissoes.convidar ? (
                        <Botao cor="primaria" tom="solida" icone="fa-paper-plane"
                            onClick={() => porFormulario({ ...VAZIO })}>
                            {t('Convidar')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <ul className="space-y-2">
                    {lista.data.data.map((x, i) => (
                        <li key={x.id} style={cascata(i)}
                            className={cls('entra flex flex-wrap items-center gap-3 p-4 transition hover:shadow-md', CARTAO)}>
                            <span className={cls(
                                'grid h-10 w-10 flex-none place-items-center rounded-xl',
                                x.estado === 'accepted' ? 'bg-emerald-50 text-emerald-600'
                                    : x.estado === 'expired' ? 'bg-amber-50 text-amber-600'
                                        : x.estado === 'cancelled' ? 'bg-slate-100 text-slate-400'
                                            : 'bg-cyan-50 text-cyan-600',
                            )}>
                                <i className={`fas ${ICONE_DO_ESTADO[x.estado] ?? 'fa-envelope'}`} aria-hidden="true" />
                            </span>

                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold text-slate-800">{x.nome}</p>
                                <p className="truncate text-xs text-slate-500">{x.email}</p>
                            </div>

                            {x.papel && (
                                <Etiqueta cor="primaria" icone="fa-user-shield">{x.papel}</Etiqueta>
                            )}

                            {x.expira_em && x.estado === 'pending' && (
                                <Etiqueta cor="neutra" icone="fa-clock">
                                    {t('Expira :quando', { quando: x.expira_em })}
                                </Etiqueta>
                            )}

                            {x.convidado_por && (
                                <span className="hidden text-xs text-slate-400 sm:block">
                                    {t('por :nome', { nome: x.convidado_por })}
                                </span>
                            )}

                            <Etiqueta cor={COR_DO_ESTADO[x.estado] ?? 'neutra'} ponto>
                                {rotuloDoEstado(x.estado)}
                            </Etiqueta>

                            {o.permissoes.convidar && x.estado !== 'accepted' && (
                                <div className="flex flex-none items-center gap-1.5">
                                    <Botao altura="pequeno" icone="fa-rotate-right"
                                        aTrabalhar={reenviar.isPending && reenviar.variables === x.id}
                                        onClick={() => reenviar.mutate(x.id)}>
                                        {t('Reenviar')}
                                    </Botao>
                                    {x.estado !== 'cancelled' && (
                                        <Botao altura="pequeno" cor="perigo" icone="fa-ban"
                                            onClick={() => porACancelar(x)}
                                            aria-label={t('Cancelar convite')} />
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {/* ─── Convidar ──────────────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => porFormulario(null)}
                titulo={t('Convidar alguém')}
                subtitulo={t('O convite vale sete dias')}
                icone="fa-paper-plane"
                cor="ciano"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => porFormulario(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-paper-plane"
                            aTrabalhar={convidar.isPending}
                            onClick={() => formulario && convidar.mutate(formulario)}>
                            {t('Enviar convite')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={convidar.error} />

                        {!o.limite.cabe_mais && (
                            <p className={cls('border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900', RAIO)}>
                                <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                                {t('O plano já não tem lugares livres. Liberte uma conta ou aumente o plano antes de convidar.')}
                            </p>
                        )}

                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input type="text" value={formulario.name}
                                onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('E-mail')} obrigatorio erro={erros.email}
                            ajuda={t('É para aqui que vai o link do convite.')}>
                            <input type="email" value={formulario.email}
                                onChange={(e) => porFormulario({ ...formulario, email: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <Campo etiqueta={t('Papel')} obrigatorio erro={erros.role_id}
                            ajuda={t('O que vai poder fazer assim que entrar.')}>
                            <select value={formulario.role_id}
                                onChange={(e) => porFormulario({ ...formulario, role_id: e.target.value })}
                                className={entrada}>
                                <option value="">{t('Escolher…')}</option>
                                {papeisDaCasa.map((x) => (
                                    <option key={x.valor} value={x.valor}>{x.rotulo}</option>
                                ))}
                            </select>
                        </Campo>

                        <p className="text-xs text-slate-500">
                            {t('A palavra-passe é escolhida por quem recebe o convite. Ninguém aqui a chega a saber.')}
                        </p>
                    </div>
                )}
            </Modal>

            {/* ─── Cancelar ──────────────────────────────────────────── */}

            <Modal
                aberto={aCancelar !== null}
                aoFechar={() => porACancelar(null)}
                titulo={t('Cancelar convite')}
                subtitulo={aCancelar?.email}
                icone="fa-ban"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porACancelar(null)}>{t('Voltar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-ban" aTrabalhar={cancelar.isPending}
                            onClick={() => aCancelar && cancelar.mutate(aCancelar.id)}>
                            {t('Cancelar convite')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('O link deixa de funcionar. O convite fica no histórico como cancelado, e pode convidar-se outra vez o mesmo e-mail.')}
                </p>
            </Modal>
        </div>
    );
}
