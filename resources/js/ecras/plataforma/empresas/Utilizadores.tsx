import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

/**
 * QUEM TRABALHA NESTA EMPRESA.
 *
 * O QUE MUDOU: escolher uma pessoa que já existe era um `<select>` com TODAS
 * as contas da plataforma; agora procura-se por nome ou email. E retirar
 * alguém pede confirmação dentro da janela, e não pelo `confirm()` do browser,
 * que num telemóvel se aceita sem ler.
 */
export function Utilizadores({ id, aoFechar }: { id: number; aoFechar: () => void }) {
    const fila = useQueryClient();
    const [aJuntar, porAJuntar] = useState(false);
    const [aRetirar, porARetirar] = useState<number | null>(null);
    const [recado, porRecado] = useRecadoNoCanto(null);

    const dados = useQuery({
        queryKey: ['plataforma', 'empresas', 'utilizadores', id],
        queryFn: () => plataforma.empresas.utilizadores(id),
    });

    const refrescar = () => void fila.invalidateQueries({ queryKey: ['plataforma', 'empresas', 'utilizadores', id] });

    const papel = useMutation({
        mutationFn: ({ pessoa, novo }: { pessoa: number; novo: number }) => plataforma.empresas.mudarPapel(id, pessoa, novo),
        onSuccess: (r) => { porRecado(r.message); refrescar(); },
    });

    const retirar = useMutation({
        mutationFn: (pessoa: number) => plataforma.empresas.retirar(id, pessoa),
        onSuccess: (r) => { porRecado(r.message); porARetirar(null); refrescar(); },
    });

    const d = dados.data;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Utilizadores')}
            subtitulo={d?.empresa.nome}
            icone="fa-users"
            cor="laranja"
            largura="xl"
            rodape={<div className="flex justify-end"><Botao cor="neutra" onClick={aoFechar}>{t('Fechar')}</Botao></div>}
        >
            {dados.isPending || !d ? (
                <Carregando linhas={6} />
            ) : aJuntar ? (
                <Juntar
                    id={id}
                    papeis={d.papeis}
                    aoVoltar={() => porAJuntar(false)}
                    aoJuntar={(m) => { porRecado(m); porAJuntar(false); refrescar(); }}
                />
            ) : (
                <div className="space-y-4">
                    {recado && (
                        <p role="status" className={cls('entra border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900', RAIO)}>
                            <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                        </p>
                    )}
                    <AvisoDeErro erro={papel.error ?? retirar.error} />

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-sm font-bold text-slate-900">
                                {t(':n pessoa(s) com acesso', { n: d.utilizadores.length })}
                            </p>
                            <p className="text-xs text-slate-500">
                                {d.limite === 0 ? t('Sem limite de utilizadores') : t('O limite é :n', { n: d.limite })}
                            </p>
                        </div>
                        <Botao
                            cor="aviso"
                            tom="solida"
                            icone="fa-user-plus"
                            disabled={!d.cabe_mais_um}
                            title={d.cabe_mais_um ? undefined : t('A empresa chegou ao limite de utilizadores.')}
                            onClick={() => porAJuntar(true)}
                        >
                            {t('Juntar pessoa')}
                        </Botao>
                    </div>

                    {!d.cabe_mais_um && (
                        <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                            {t('A empresa chegou ao limite. Aumente o limite na ficha da empresa ou mude o plano para juntar mais alguém.')}
                        </p>
                    )}

                    {d.utilizadores.length === 0 ? (
                        <SemNada icone="fa-users" frase={t('Ninguém tem acesso a esta empresa.')} />
                    ) : (
                        <ul className="space-y-2">
                            {d.utilizadores.map((u, i) => (
                                <li
                                    key={u.id}
                                    className={cls('entra flex flex-wrap items-center gap-3 bg-slate-50 p-3', RAIO, TRANSICAO, 'hover:bg-slate-100')}
                                    style={cascata(i)}
                                >
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-orange-400 to-red-500 text-sm font-bold text-white">
                                        {u.nome.slice(0, 2).toUpperCase()}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-900">{u.nome}</p>
                                        <p className="truncate text-xs text-slate-500">{u.email}</p>
                                    </div>
                                    <select
                                        className={cls(entrada, 'w-48')}
                                        aria-label={t('Papel de :nome', { nome: u.nome })}
                                        value={u.papel ?? ''}
                                        onChange={(e) => e.target.value && papel.mutate({ pessoa: u.id, novo: Number(e.target.value) })}
                                    >
                                        {u.papel === null && <option value="">{t('Sem papel — escolha um')}</option>}
                                        {d.papeis.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                                    </select>
                                    <span className="w-24 text-xs text-slate-500">
                                        <i className="fas fa-calendar mr-1" aria-hidden="true" />{u.entrou_em ?? '—'}
                                    </span>
                                    {aRetirar === u.id ? (
                                        <span className="entra flex items-center gap-1.5">
                                            <Botao cor="perigo" tom="solida" altura="pequeno" aTrabalhar={retirar.isPending} onClick={() => retirar.mutate(u.id)}>
                                                {t('Retirar')}
                                            </Botao>
                                            <Botao cor="neutra" altura="pequeno" onClick={() => porARetirar(null)}>{t('Não')}</Botao>
                                        </span>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => porARetirar(u.id)}
                                            className={cls('px-3 py-2 text-red-700 hover:bg-red-100', RAIO, TRANSICAO, FOCO)}
                                            title={t('Retirar desta empresa')}
                                        >
                                            <i className="fas fa-user-minus" aria-hidden="true" />
                                            <span className="sr-only">{t('Retirar :nome', { nome: u.nome })}</span>
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </Modal>
    );
}

function Juntar({ id, papeis, aoVoltar, aoJuntar }: {
    id: number;
    papeis: Array<{ valor: string; rotulo: string }>;
    aoVoltar: () => void;
    aoJuntar: (recado: string) => void;
}) {
    const [novo, porNovo] = useState(false);
    const [procura, porProcura] = useState('');
    const [atrasada, porAtrasada] = useState('');
    const [escolhida, porEscolhida] = useState<{ id: number; nome: string; email: string } | null>(null);
    const [f, porF] = useState({ nome: '', email: '', telefone: '', senha: '', papel: papeis[0]?.valor ?? '' });

    // Um pedido por pausa na escrita, e não um por tecla.
    useEffect(() => {
        const relogio = window.setTimeout(() => porAtrasada(procura.trim()), 300);

        return () => window.clearTimeout(relogio);
    }, [procura]);

    const pessoas = useQuery({
        queryKey: ['plataforma', 'empresas', 'procurar', id, atrasada],
        queryFn: () => plataforma.empresas.procurarPessoas(id, atrasada),
        enabled: !novo && atrasada.length >= 2,
    });

    const juntar = useMutation({
        mutationFn: () => plataforma.empresas.juntar(id, novo
            ? { novo: true, nome: f.nome, email: f.email, telefone: f.telefone || null, senha: f.senha, papel: Number(f.papel) }
            : { novo: false, utilizador: escolhida?.id, papel: Number(f.papel) }),
        onSuccess: (r) => aoJuntar(r.message),
    });

    const erros = juntar.error instanceof ErroDaApi ? juntar.error.erros : {};

    return (
        <div className="entra space-y-4">
            <button type="button" onClick={aoVoltar} className={cls('text-sm font-semibold text-slate-600 hover:text-slate-900', FOCO, RAIO)}>
                <i className="fas fa-arrow-left mr-1.5" aria-hidden="true" />{t('Voltar à lista')}
            </button>

            <AvisoDeErro erro={juntar.error} />

            <div className="grid grid-cols-2 gap-3" role="radiogroup">
                {[
                    { valor: false, rotulo: t('Pessoa que já tem conta'), icone: 'fa-user-check' },
                    { valor: true, rotulo: t('Criar uma conta nova'), icone: 'fa-user-plus' },
                ].map((o) => (
                    <button
                        key={String(o.valor)}
                        type="button"
                        role="radio"
                        aria-checked={novo === o.valor}
                        onClick={() => porNovo(o.valor)}
                        className={cls(
                            'flex items-center gap-2 border-2 px-4 py-3 text-sm font-semibold', RAIO, TRANSICAO, FOCO,
                            novo === o.valor ? 'border-blue-500 bg-blue-50 text-blue-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                        )}
                    >
                        <i className={cls('fas', o.icone)} aria-hidden="true" />{o.rotulo}
                    </button>
                ))}
            </div>

            {novo ? (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Nome')} obrigatorio erro={erros.nome}>
                        <input className={entrada} value={f.nome} onChange={(e) => porF({ ...f, nome: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('Email')} obrigatorio erro={erros.email}>
                        <input type="email" className={entrada} value={f.email} onChange={(e) => porF({ ...f, email: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('Telefone')} erro={erros.telefone} ajuda={t('Com telefone, as credenciais seguem também por SMS.')}>
                        <input className={entrada} value={f.telefone} onChange={(e) => porF({ ...f, telefone: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('Senha')} obrigatorio erro={erros.senha} ajuda={t('Pelo menos 6 caracteres. Segue por email.')}>
                        <input type="password" autoComplete="new-password" className={entrada} value={f.senha} onChange={(e) => porF({ ...f, senha: e.target.value })} />
                    </Campo>
                </div>
            ) : escolhida ? (
                <div className="flex items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-slate-900">{escolhida.nome}</p>
                        <p className="truncate text-xs text-slate-500">{escolhida.email}</p>
                    </div>
                    <Botao cor="neutra" altura="pequeno" icone="fa-rotate" onClick={() => porEscolhida(null)}>{t('Trocar')}</Botao>
                </div>
            ) : (
                <Campo etiqueta={t('Procurar pessoa')} obrigatorio erro={erros.utilizador} ajuda={t('Nome ou email, a partir de dois caracteres.')}>
                    <div>
                        <input className={entrada} value={procura} onChange={(e) => porProcura(e.target.value)} placeholder={t('Ex.: maria@…')} />
                        {atrasada.length >= 2 && (
                            <ul className="mt-2 max-h-56 space-y-1 overflow-y-auto">
                                {pessoas.isFetching && <li className="px-3 py-2 text-xs text-slate-400">{t('A procurar…')}</li>}
                                {pessoas.data?.pessoas.map((p) => (
                                    <li key={p.id}>
                                        <button
                                            type="button"
                                            onClick={() => porEscolhida(p)}
                                            className={cls('w-full px-3 py-2 text-left hover:bg-blue-50', RAIO, TRANSICAO, FOCO)}
                                        >
                                            <span className="block text-sm font-semibold text-slate-800">{p.nome}</span>
                                            <span className="block text-xs text-slate-500">{p.email}</span>
                                        </button>
                                    </li>
                                ))}
                                {pessoas.data && pessoas.data.pessoas.length === 0 && (
                                    <li className="px-3 py-2 text-xs text-slate-500">{t('Ninguém com esse nome ou email fora desta empresa.')}</li>
                                )}
                            </ul>
                        )}
                    </div>
                </Campo>
            )}

            <Campo etiqueta={t('Papel nesta empresa')} obrigatorio erro={erros.papel}>
                <select className={entrada} value={f.papel} onChange={(e) => porF({ ...f, papel: e.target.value })}>
                    {papeis.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                </select>
            </Campo>

            {papeis.length === 0 && (
                <Etiqueta cor="perigo" icone="fa-triangle-exclamation">{t('Esta empresa não tem papéis criados.')}</Etiqueta>
            )}

            <div className="flex justify-end">
                <Botao
                    cor="aviso"
                    tom="solida"
                    icone="fa-user-plus"
                    aTrabalhar={juntar.isPending}
                    disabled={!novo && !escolhida}
                    onClick={() => juntar.mutate()}
                >
                    {novo ? t('Criar e juntar') : t('Juntar à empresa')}
                </Botao>
            </div>
        </div>
    );
}
