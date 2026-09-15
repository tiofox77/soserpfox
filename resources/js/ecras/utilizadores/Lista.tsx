import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { utilizadores, type OpcoesDosUtilizadores, type Utilizador } from '@/api/utilizadores';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * A GESTÃO DE UTILIZADORES.
 *
 * UM UTILIZADOR NÃO TEM «UM PAPEL» — tem um por empresa. É gerente numa casa e
 * caixa noutra, e o formulário mostra-o assim: escolhem-se as empresas e, à
 * frente de cada uma, o papel que a pessoa tem lá dentro.
 *
 * O TECTO DO PLANO aparece ANTES de alguém escrever o formulário todo — nada
 * pior do que preencher tudo para levar com «limite atingido» ao gravar.
 *
 * O PIN DE TURNO é a chave do POS sem rede. Repõe-se aqui, porque quem se
 * esquece dele está à frente de um cliente e não pode esperar por um e-mail.
 */

const VAZIO = {
    name: '', email: '', password: '', password_confirmation: '', is_active: true,
};

type Formulario = typeof VAZIO;

export default function ListaDeUtilizadores() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{
        procura?: string; estado?: 'todos' | 'activos' | 'inactivos';
        papel?: number | ''; por_pagina?: number; page?: number;
    }>({ estado: 'todos', por_pagina: 15, page: 1 });

    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);

    const [formulario, porFormulario] = useState<Formulario | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    /** As empresas escolhidas e, para cada uma, o papel: `{ [empresa]: papel }`. */
    const [empresas, porEmpresas] = useState<number[]>([]);
    const [papeisPorEmpresa, porPapeisPorEmpresa] = useState<Record<number, string>>({});

    const [aApagar, porAApagar] = useState<Utilizador | null>(null);
    const [pin, porPin] = useState<{ id: number; nome: string; pin: string; confirmacao: string } | null>(null);

    const opcoes = useQuery({ queryKey: ['utilizadores', 'opcoes'], queryFn: () => utilizadores.opcoes() });
    const lista = useQuery({
        queryKey: ['utilizadores', 'lista', filtros],
        queryFn: () => utilizadores.listar(filtros),
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['utilizadores'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => utilizadores.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); fechar(); },
        onError: porErro,
    });

    const estado = useMutation({
        mutationFn: (id: number) => utilizadores.estado(id),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => utilizadores.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: (e) => { porErro(e); porAApagar(null); },
    });

    const gravarPin = useMutation({
        mutationFn: (d: { id: number; pin: string; confirmacao: string }) =>
            utilizadores.pin(d.id, d.pin, d.confirmacao),
        onSuccess: (r) => { feito(r.message); porPin(null); },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o: OpcoesDosUtilizadores = opcoes.data;
    const p = o.permissoes;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};
    const errosDoPin = (gravarPin.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const papeisDa = (empresa: number) => o.papeis.filter((x) => x.empresa === empresa);

    const fechar = () => {
        porFormulario(null);
        porAEditar(null);
        porEmpresas([]);
        porPapeisPorEmpresa({});
    };

    const abrirNovo = () => {
        porAEditar(null);
        porFormulario({ ...VAZIO });
        // A empresa activa vem marcada: é onde se está e é onde, em nove casos
        // em dez, a conta vai trabalhar.
        porEmpresas([o.empresa_activa]);
        porPapeisPorEmpresa({});
    };

    const abrirEdicao = (x: Utilizador) => {
        porAEditar(x.id);
        porFormulario({
            name: x.nome, email: x.email, password: '', password_confirmation: '', is_active: x.activo,
        });
        porEmpresas(x.empresas.map((e) => e.id));
        porPapeisPorEmpresa(Object.fromEntries(
            x.empresas.filter((e) => e.papel_id).map((e) => [e.id, String(e.papel_id)]),
        ));
    };

    const alternarEmpresa = (id: number) => {
        if (empresas.includes(id)) {
            porEmpresas(empresas.filter((x) => x !== id));

            const resto = { ...papeisPorEmpresa };

            delete resto[id];
            porPapeisPorEmpresa(resto);

            return;
        }

        porEmpresas([...empresas, id]);

        // O PRIMEIRO PAPEL DA EMPRESA por omissão — e o da empresa certa, não
        // um papel qualquer de outra casa, que era o defeito do ecrã antigo.
        const primeiro = papeisDa(id)[0];

        if (primeiro) porPapeisPorEmpresa({ ...papeisPorEmpresa, [id]: primeiro.valor });
    };

    const submeter = () => {
        if (!formulario) return;

        guardar.mutate({
            ...formulario,
            empresas,
            papeis: Object.fromEntries(
                empresas.map((e) => [e, papeisPorEmpresa[e] ? Number(papeisPorEmpresa[e]) : null]),
            ),
        });
    };

    const semTecto = o.limite.maximo === 0;
    const percentagem = semTecto ? 0 : Math.min(100, Math.round((o.limite.usados / o.limite.maximo) * 100));

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Gestão de Utilizadores')}
                subtitulo={t('Quem entra, o que pode fazer, e em que empresa')}
                icone="fa-users-gear"
                cor="primaria"
                accoes={
                    <>
                        {p.criar && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={abrirNovo}>
                                <i className="fas fa-user-plus" aria-hidden="true" />
                                {t('Novo utilizador')}
                            </button>
                        )}
                        {p.convidar && (
                            <a href="/users/invitations" className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-envelope-open-text" aria-hidden="true" />
                                {t('Convites')}
                            </a>
                        )}
                        {p.papeis && (
                            <a href="/users/roles-permissions" className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-user-shield" aria-hidden="true" />
                                {t('Papéis e Permissões')}
                            </a>
                        )}
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-users">
                        {semTecto
                            ? t(':n de utilizadores sem limite', { n: numero(o.limite.usados) })
                            : t(':usados de :maximo utilizadores', {
                                usados: numero(o.limite.usados), maximo: numero(o.limite.maximo),
                            })}
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

            {/*
              * O TECTO DO PLANO, à vista.
              *
              * Uma barra que se enche é a única forma de alguém perceber que
              * está a três contas do limite antes de lá chegar.
              */}
            {!semTecto && (
                <div className={cls('p-4', CARTAO)}>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-semibold text-slate-700">
                            <i className="fas fa-gauge-high mr-2 text-indigo-500" aria-hidden="true" />
                            {t('Utilizadores do plano')}
                        </p>
                        <p className="text-sm tabular-nums text-slate-600">
                            {t(':usados de :maximo', {
                                usados: numero(o.limite.usados), maximo: numero(o.limite.maximo),
                            })}
                        </p>
                    </div>
                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className={cls(
                                'h-full rounded-full transition-all duration-700',
                                percentagem >= 100 ? 'bg-red-500' : percentagem >= 80 ? 'bg-amber-500' : 'bg-indigo-500',
                            )}
                            style={{ width: `${percentagem}%` }}
                        />
                    </div>
                    <p className="mt-1.5 text-xs text-slate-500">
                        {t('Só as contas activas contam. Desactivar liberta um lugar; eliminar não é preciso.')}
                    </p>
                </div>
            )}

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero
                        rotulo={t('Utilizadores')} valor={numero(resumo.total)} icone="fa-users" tom="indigo"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'todos', page: 1 })}
                    />
                    <CartaoNumero
                        rotulo={t('Activos')} valor={numero(resumo.activos)} icone="fa-user-check" tom="verde"
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'activos', page: 1 })}
                    />
                    <CartaoNumero
                        rotulo={t('Desactivados')} valor={numero(resumo.inactivos)} icone="fa-user-slash"
                        tom={resumo.inactivos > 0 ? 'ambar' : 'cinza'}
                        aoCarregar={() => porFiltros({ ...filtros, estado: 'inactivos', page: 1 })}
                    />
                    <CartaoNumero
                        rotulo={t('Com PIN de turno')} valor={numero(resumo.com_pin)} icone="fa-key" tom="roxo"
                        nota={t('Quem pode abrir turno no POS sem rede')}
                    />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[12rem] flex-1">
                    <div className="relative">
                        <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                            placeholder={t('Nome ou e-mail…')} className={cls(entrada, 'pl-9')} />
                    </div>
                </Campo>

                <Campo etiqueta={t('Estado')} className="w-40">
                    <select value={filtros.estado ?? 'todos'}
                        onChange={(e) => porFiltros({ ...filtros, estado: e.target.value as 'todos', page: 1 })}
                        className={entrada}>
                        <option value="todos">{t('Todos')}</option>
                        <option value="activos">{t('Activos')}</option>
                        <option value="inactivos">{t('Desactivados')}</option>
                    </select>
                </Campo>

                <Campo etiqueta={t('Papel nesta empresa')} className="w-56">
                    <select value={filtros.papel ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, papel: e.target.value ? Number(e.target.value) : '', page: 1 })}
                        className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {papeisDa(o.empresa_activa).map((x) => (
                            <option key={x.valor} value={x.valor}>{x.rotulo}</option>
                        ))}
                    </select>
                </Campo>
            </div>

            {lista.isPending ? (
                <Carregando linhas={6} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-users"
                    titulo={t('Nenhum utilizador')}
                    frase={t('Cada pessoa que entra no sistema tem a sua conta — é o que torna possível saber quem fez o quê.')}
                    accao={p.criar ? (
                        <Botao cor="primaria" tom="solida" icone="fa-user-plus" onClick={abrirNovo}>
                            {t('Novo utilizador')}
                        </Botao>
                    ) : undefined}
                />
            ) : (
                <ul className="space-y-2">
                    {lista.data.data.map((x, i) => (
                        <li key={x.id} style={cascata(i)}
                            className={cls('entra flex flex-wrap items-center gap-3 p-4 transition hover:shadow-md', CARTAO)}>
                            <span className={cls(
                                'grid h-11 w-11 flex-none place-items-center rounded-xl text-sm font-bold uppercase',
                                x.activo ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-400',
                            )}>
                                {x.nome.slice(0, 2)}
                            </span>

                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 truncate text-sm font-semibold text-slate-800">
                                    {x.nome}
                                    {x.super_admin && (
                                        <Etiqueta cor="primaria" icone="fa-crown">{t('Super Admin')}</Etiqueta>
                                    )}
                                </p>
                                <p className="truncate text-xs text-slate-500">{x.email}</p>
                            </div>

                            {/* O papel DESTA empresa: o que a pessoa tem noutra
                                casa não diz nada a quem está a olhar daqui. */}
                            <Etiqueta cor={x.papel_aqui ? 'primaria' : 'neutra'} icone="fa-user-shield">
                                {x.papel_aqui ?? t('Sem papel')}
                            </Etiqueta>

                            {x.empresas.length > 1 && (
                                <Etiqueta cor="neutra" icone="fa-building">
                                    {t(':n empresas', { n: numero(x.empresas.length) })}
                                </Etiqueta>
                            )}

                            {x.tem_pin && (
                                <Etiqueta cor="primaria" icone="fa-key">{t('PIN')}</Etiqueta>
                            )}

                            <Etiqueta cor={x.activo ? 'bom' : 'neutra'} ponto>
                                {x.activo ? t('Activo') : t('Desactivado')}
                            </Etiqueta>

                            <div className="flex flex-none items-center gap-1.5">
                                {p.editar && (
                                    <Botao altura="pequeno" icone="fa-key"
                                        onClick={() => porPin({ id: x.id, nome: x.nome, pin: '', confirmacao: '' })}
                                        aria-label={t('Definir PIN de turno')} />
                                )}
                                {p.editar && (
                                    <Botao altura="pequeno" icone="fa-pen"
                                        onClick={() => abrirEdicao(x)}
                                        aria-label={t('Editar utilizador')} />
                                )}
                                {p.editar && !x.super_admin && (
                                    <Botao altura="pequeno" cor={x.activo ? 'aviso' : 'bom'}
                                        icone={x.activo ? 'fa-user-slash' : 'fa-user-check'}
                                        aTrabalhar={estado.isPending}
                                        onClick={() => estado.mutate(x.id)}
                                        aria-label={x.activo ? t('Desactivar') : t('Activar')} />
                                )}
                                {p.eliminar && !x.super_admin && (
                                    <Botao altura="pequeno" cor="perigo" icone="fa-trash"
                                        onClick={() => porAApagar(x)}
                                        aria-label={t('Eliminar utilizador')} />
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {meta && meta.last_page > 1 && (
                <Paginacao
                    pagina={meta.current_page}
                    ultima={meta.last_page}
                    aMudar={(p) => porFiltros({ ...filtros, page: p })}
                    total={meta.total}
                    de={meta.from}
                    ate={meta.to}
                    aCarregar={lista.isFetching}
                    emCartao
                    extra={<PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />}
                />
            )}

            {/* ─── O formulário ──────────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={fechar}
                titulo={aEditar ? t('Editar utilizador') : t('Novo utilizador')}
                subtitulo={t('As empresas a que tem acesso — e o papel em cada uma')}
                icone="fa-user-gear"
                cor="primaria"
                largura="lg"
                rodape={
                    <>
                        <Botao onClick={fechar}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check"
                            aTrabalhar={guardar.isPending}
                            disabled={empresas.length === 0}
                            onClick={submeter}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                                <input type="text" value={formulario.name}
                                    onChange={(e) => porFormulario({ ...formulario, name: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('E-mail')} obrigatorio erro={erros.email}
                                ajuda={t('É por aqui que entra — e não se repete em todo o sistema.')}>
                                <input type="email" value={formulario.email}
                                    onChange={(e) => porFormulario({ ...formulario, email: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta={t('Palavra-passe')}
                                obrigatorio={!aEditar}
                                erro={erros.password}
                                ajuda={aEditar ? t('Deixe em branco para não mudar.') : t('Mínimo 8 caracteres, com letras e números.')}
                            >
                                <input type="password" autoComplete="new-password" value={formulario.password}
                                    onChange={(e) => porFormulario({ ...formulario, password: e.target.value })}
                                    className={entrada} />
                            </Campo>

                            <Campo etiqueta={t('Confirmar palavra-passe')} obrigatorio={!aEditar}>
                                <input type="password" autoComplete="new-password" value={formulario.password_confirmation}
                                    onChange={(e) => porFormulario({ ...formulario, password_confirmation: e.target.value })}
                                    className={entrada} />
                            </Campo>
                        </div>

                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" checked={formulario.is_active}
                                onChange={(e) => porFormulario({ ...formulario, is_active: e.target.checked })}
                                className="h-5 w-5 rounded text-indigo-600" />
                            {t('Conta activa')}
                            <span className="text-xs text-slate-400">{t('— uma conta desactivada não entra, mas não se perde.')}</span>
                        </label>

                        {/*
                          * AS EMPRESAS E O PAPEL EM CADA UMA.
                          *
                          * É o coração deste formulário: a mesma pessoa é
                          * gerente numa casa e caixa noutra, e é aqui que essa
                          * diferença se escreve.
                          */}
                        <div className="space-y-2">
                            <p className="text-sm font-semibold text-slate-700">
                                <i className="fas fa-building mr-2 text-indigo-500" aria-hidden="true" />
                                {t('Empresas e papéis')}
                                <span className="ml-1 text-red-500" aria-hidden="true">*</span>
                            </p>
                            <p className="text-xs text-slate-500">
                                {t('O papel vale dentro da empresa onde foi dado. Só aparecem as empresas a que tem acesso.')}
                            </p>

                            <ul className="space-y-2">
                                {o.empresas.map((e) => {
                                    const id = Number(e.valor);
                                    const marcada = empresas.includes(id);

                                    return (
                                        <li key={e.valor}
                                            className={cls(
                                                'flex flex-wrap items-center gap-3 border p-3 transition',
                                                RAIO,
                                                marcada ? 'border-indigo-200 bg-indigo-50/60' : 'border-slate-200 bg-white',
                                            )}>
                                            <label className="flex min-w-[10rem] flex-1 items-center gap-2 text-sm font-medium text-slate-700">
                                                <input type="checkbox" checked={marcada}
                                                    onChange={() => alternarEmpresa(id)}
                                                    className="h-5 w-5 rounded text-indigo-600" />
                                                {e.rotulo}
                                            </label>

                                            <select
                                                value={papeisPorEmpresa[id] ?? ''}
                                                disabled={!marcada}
                                                onChange={(ev) => porPapeisPorEmpresa({
                                                    ...papeisPorEmpresa, [id]: ev.target.value,
                                                })}
                                                aria-label={t('Papel em :empresa', { empresa: e.rotulo })}
                                                className={cls(entrada, 'w-56 disabled:bg-slate-50 disabled:text-slate-400')}
                                            >
                                                <option value="">{t('Sem papel')}</option>
                                                {papeisDa(id).map((x) => (
                                                    <option key={x.valor} value={x.valor}>{x.rotulo}</option>
                                                ))}
                                            </select>
                                        </li>
                                    );
                                })}
                            </ul>

                            {empresas.length === 0 && (
                                <p className="text-xs font-medium text-red-600">
                                    {t('Escolha pelo menos uma empresa.')}
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </Modal>

            {/* ─── O PIN de turno ────────────────────────────────────── */}

            <Modal
                aberto={pin !== null}
                aoFechar={() => porPin(null)}
                titulo={t('PIN de turno')}
                subtitulo={pin ? t('Para :nome', { nome: pin.nome }) : ''}
                icone="fa-key"
                cor="roxo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porPin(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={gravarPin.isPending}
                            onClick={() => pin && gravarPin.mutate({
                                id: pin.id, pin: pin.pin, confirmacao: pin.confirmacao,
                            })}>
                            {t('Definir PIN')}
                        </Botao>
                    </>
                }
            >
                {pin && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={gravarPin.error} />

                        <p className={cls('border border-purple-200 bg-purple-50 p-3 text-sm text-purple-900', RAIO)}>
                            <i className="fas fa-circle-info mr-2" aria-hidden="true" />
                            {t('É com este PIN que se abre turno no POS quando não há rede. Vai para os tablets na próxima sincronização.')}
                        </p>

                        <Campo etiqueta={t('PIN')} obrigatorio erro={errosDoPin.pin}
                            ajuda={t('4 a 6 dígitos. Nada de 1234 nem da data de nascimento.')}>
                            <input type="password" inputMode="numeric" autoComplete="off" maxLength={6}
                                value={pin.pin}
                                onChange={(e) => porPin({ ...pin, pin: e.target.value.replace(/\D/g, '') })}
                                className={cls(entrada, 'text-center text-lg tracking-[0.5em]')} />
                        </Campo>

                        <Campo etiqueta={t('Repetir o PIN')} obrigatorio>
                            <input type="password" inputMode="numeric" autoComplete="off" maxLength={6}
                                value={pin.confirmacao}
                                onChange={(e) => porPin({ ...pin, confirmacao: e.target.value.replace(/\D/g, '') })}
                                className={cls(entrada, 'text-center text-lg tracking-[0.5em]')} />
                        </Campo>
                    </div>
                )}
            </Modal>

            {/* ─── Eliminar ──────────────────────────────────────────── */}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar utilizador')}
                subtitulo={aApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('A conta sai das listas e deixa de entrar. Quem já emitiu documentos NÃO se elimina — nesse caso, desactive-o: a factura tem de continuar a dizer quem a fez.')}
                </p>
            </Modal>
        </div>
    );
}
