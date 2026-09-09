import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query';

import {
    reservaOnline,
    type ACasa,
    type HospedePublico,
    type ReservaFeita,
    type TipoComPreco,
} from '@/api/reservaOnline';
import { ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';
import { kz } from '@/ui/tokens';

/**
 * A PÁGINA PÚBLICA DE RESERVAS — a casa vista de fora.
 *
 * É a página que um hóspede abre de um cartaz ou de uma ligação do Instagram,
 * quase sempre num telemóvel. Não tem menu, nem barra lateral, nem sessão: só
 * a casa, os quartos, e um caminho curto até à reserva.
 *
 * A COR É A DA CASA e não a do sistema: sai das definições do hotel, e é a
 * única página do produto onde isso acontece — aqui quem se apresenta é o
 * hotel, não o ERP.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE:
 *
 *  • O PREÇO IGNORAVA AS TARIFAS — era o preço base × noites, e a época alta,
 *    o fim-de-semana e os dias especiais não mexiam no que o hóspede pagava.
 *    Justamente na única página onde o preço é uma promessa a um estranho.
 *  • A SENHA DA CONTA NUNCA ERA GUARDADA: escrevia-se em `hotel_data`, que não
 *    é coluna nem acessor. Quem «criava conta com senha» ficava sem senha, e
 *    entrar só pedia o TELEFONE — qualquer pessoa que soubesse o número
 *    entrava na ficha do hóspede.
 *  • O SINAL era calculado e não chegava a lado nenhum.
 */

const hoje = () => new Date().toISOString().slice(0, 10);

const daquiA = (dias: number) => {
    const d = new Date();

    d.setDate(d.getDate() + dias);

    return d.toISOString().slice(0, 10);
};

function noitesEntre(de: string, ate: string): number {
    if (! de || ! ate) return 0;

    const ms = new Date(`${ate}T12:00:00`).getTime() - new Date(`${de}T12:00:00`).getTime();

    return Math.max(0, Math.round(ms / 86_400_000));
}

const PASSOS = [
    { n: 1, rotulo: () => t('Datas') },
    { n: 2, rotulo: () => t('Quarto') },
    { n: 3, rotulo: () => t('Os seus dados') },
] as const;

export default function Reservar({ slug }: { slug: string }) {
    const [passo, porPasso] = useState(1);
    const [de, porDe] = useState(daquiA(1));
    const [ate, porAte] = useState(daquiA(2));
    const [adultos, porAdultos] = useState(2);
    const [criancas, porCriancas] = useState(0);
    const [tipo, porTipo] = useState<TipoComPreco | null>(null);
    const [hospede, porHospede] = useState<HospedePublico | null>(null);
    const [feita, porFeita] = useState<ReservaFeita | null>(null);

    const casa = useQuery({ queryKey: ['publico', slug], queryFn: () => reservaOnline.casa(slug) });

    const noites = noitesEntre(de, ate);

    const disponibilidade = useQuery({
        queryKey: ['publico', slug, 'disponibilidade', de, ate],
        queryFn: () => reservaOnline.disponibilidade(slug, de, ate),
        placeholderData: keepPreviousData,
        enabled: casa.isSuccess && noites > 0,
        retry: false,
    });

    // Trocar de datas invalida o quarto escolhido: o preço e a disponibilidade
    // são outros.
    useEffect(() => { porTipo(null); }, [de, ate]);

    if (casa.isPending) return <AEsperar />;
    if (casa.isError) return <NaoAbriu erro={casa.error} />;

    const c = casa.data.casa;
    const cor = c.cor;
    const cor2 = c.cor2;

    if (feita) return <Confirmada casa={c} reserva={feita} aoRecomecar={() => { porFeita(null); porPasso(1); porTipo(null); }} />;

    return (
        <div className="min-h-screen bg-white">
            <Cabecalho casa={c} />

            {/* A CAPA. É a primeira coisa que se vê, e a que diz se vale a pena
                continuar a ler. */}
            <header className="relative overflow-hidden text-white"
                style={{ background: `linear-gradient(135deg, ${cor} 0%, ${cor2} 100%)` }}>
                {c.capa && (
                    <img src={c.capa} alt="" aria-hidden="true"
                        className="absolute inset-0 h-full w-full object-cover opacity-40" />
                )}

                <div className="relative mx-auto max-w-5xl px-5 py-16 text-center sm:py-24">
                    <h1 className="animate-fade-in text-3xl font-bold drop-shadow sm:text-5xl">{c.nome}</h1>

                    {c.estrelas > 0 && (
                        <p className="mt-2 text-lg text-amber-300" aria-label={t(':n estrela(s)', { n: c.estrelas })}>
                            {'★'.repeat(c.estrelas)}
                        </p>
                    )}

                    {c.boas_vindas && <p className="mx-auto mt-4 max-w-2xl text-base opacity-95 sm:text-lg">{c.boas_vindas}</p>}

                    {(c.cidade || c.morada) && (
                        <p className="mt-3 text-sm opacity-90">
                            <i className="fas fa-location-dot mr-1.5" aria-hidden="true" />
                            {[c.morada, c.cidade, c.pais].filter(Boolean).join(', ')}
                        </p>
                    )}
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-5 py-8">
                {/* Os três passos. */}
                <ol className="mb-6 flex items-center gap-2">
                    {PASSOS.map((p, i) => (
                        <li key={p.n} className="flex flex-1 items-center gap-2">
                            <span className="flex items-center gap-2 text-sm font-semibold"
                                style={{ color: passo >= p.n ? cor : '#94a3b8' }}>
                                <span className="grid h-7 w-7 flex-none place-items-center rounded-full text-xs font-bold text-white"
                                    style={{ background: passo >= p.n ? cor : '#cbd5e1' }}>
                                    {passo > p.n ? <i className="fas fa-check" aria-hidden="true" /> : p.n}
                                </span>
                                <span className="hidden sm:inline">{p.rotulo()}</span>
                            </span>
                            {i < PASSOS.length - 1 && (
                                <span className="h-0.5 flex-1 rounded-full"
                                    style={{ background: passo > p.n ? cor : '#e2e8f0' }} aria-hidden="true" />
                            )}
                        </li>
                    ))}
                </ol>

                {passo === 1 && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="mb-4 text-lg font-bold text-slate-900">{t('Quando quer ficar?')}</h2>

                        <div className="grid gap-3 sm:grid-cols-4">
                            <Campo etiqueta={t('Chegada')}>
                                <input type="date" value={de} min={hoje()} className={ENTRADA}
                                    onChange={(e) => porDe(e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('Partida')}>
                                <input type="date" value={ate} min={de} className={ENTRADA}
                                    onChange={(e) => porAte(e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('Adultos')}>
                                <input type="number" min={1} max={10} value={adultos} className={ENTRADA}
                                    onChange={(e) => porAdultos(Number(e.target.value))} />
                            </Campo>
                            <Campo etiqueta={t('Crianças')}>
                                <input type="number" min={0} max={10} value={criancas} className={ENTRADA}
                                    onChange={(e) => porCriancas(Number(e.target.value))} />
                            </Campo>
                        </div>

                        <p className="mt-3 text-sm text-slate-500">
                            <i className="fas fa-moon mr-1.5" aria-hidden="true" />
                            {noites > 0 ? t(':n noite(s)', { n: noites }) : t('A partida tem de ser depois da chegada.')}
                            <span className="ml-3">
                                <i className="fas fa-clock mr-1.5" aria-hidden="true" />
                                {t('Entrada a partir das :h · saída até às :s', { h: c.check_in, s: c.check_out })}
                            </span>
                        </p>

                        {disponibilidade.isError && <Aviso erro={disponibilidade.error} />}

                        <button type="button" disabled={noites < 1}
                            onClick={() => porPasso(2)}
                            className="mt-4 w-full rounded-xl px-5 py-3 font-bold text-white shadow transition-all hover:-translate-y-0.5 disabled:opacity-50 sm:w-auto"
                            style={{ background: cor }}>
                            {t('Ver quartos')}
                            <i className="fas fa-arrow-right ml-2" aria-hidden="true" />
                        </button>
                    </section>
                )}

                {passo === 2 && (
                    <section>
                        <h2 className="mb-1 text-lg font-bold text-slate-900">{t('Escolha o quarto')}</h2>
                        <p className="mb-4 text-sm text-slate-500">
                            {t(':de a :ate · :n noite(s)', { de: pt(de), ate: pt(ate), n: noites })}
                            <button type="button" onClick={() => porPasso(1)}
                                className="ml-2 font-semibold underline" style={{ color: cor }}>
                                {t('mudar')}
                            </button>
                        </p>

                        {disponibilidade.isPending ? (
                            <AEsperar />
                        ) : disponibilidade.isError ? (
                            <Aviso erro={disponibilidade.error} />
                        ) : (
                            <ul className="grid gap-4 sm:grid-cols-2">
                                {(disponibilidade.data?.tipos ?? []).map((tp, i) => (
                                    <li key={tp.id} className="entra" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <Quarto
                                            tipo={tp}
                                            cor={cor}
                                            escolhido={tipo?.id === tp.id}
                                            aoEscolher={() => { porTipo(tp); porPasso(3); }}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                {passo === 3 && tipo && (
                    <Dados
                        slug={slug}
                        casa={c}
                        tipo={tipo}
                        de={de}
                        ate={ate}
                        adultos={adultos}
                        criancas={criancas}
                        hospede={hospede}
                        aoEscolherHospede={porHospede}
                        aoVoltar={() => porPasso(2)}
                        aoReservar={porFeita}
                    />
                )}

                <Rodape casa={c} />
            </main>
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

const ENTRADA = 'w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200';

function Campo({ etiqueta, children }: { etiqueta: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">{etiqueta}</span>
            {children}
        </label>
    );
}

/** Uma data como se lê, sem depender do `data()` dos ecrãs de dentro. */
function pt(iso: string): string {
    const d = new Date(`${iso}T12:00:00`);

    return Number.isNaN(d.getTime()) ? iso : d.toLocaleDateString(undefined, {
        day: '2-digit', month: '2-digit', year: 'numeric',
    });
}

function Cabecalho({ casa }: { casa: ACasa }) {
    return (
        <nav className="sticky top-0 z-40 border-b border-slate-100 bg-white/90 backdrop-blur">
            <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-5 py-3">
                <span className="flex min-w-0 items-center gap-3">
                    {casa.logo ? (
                        <img src={casa.logo} alt="" className="h-10 w-10 flex-none rounded-xl object-cover shadow" />
                    ) : (
                        <span className="grid h-10 w-10 flex-none place-items-center rounded-xl text-white shadow"
                            style={{ background: `linear-gradient(135deg, ${casa.cor}, ${casa.cor2})` }} aria-hidden="true">
                            <i className="fas fa-hotel" />
                        </span>
                    )}
                    <span className="min-w-0">
                        <span className="block truncate font-bold text-slate-900">{casa.nome}</span>
                        {casa.estrelas > 0 && <span className="block text-xs text-amber-500">{'★'.repeat(casa.estrelas)}</span>}
                    </span>
                </span>

                {casa.whatsapp && (
                    <a href={`https://wa.me/${casa.whatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer"
                        className="inline-flex flex-none items-center gap-2 rounded-xl bg-emerald-500 px-3 py-2 text-sm font-semibold text-white transition-all hover:-translate-y-0.5 hover:bg-emerald-600">
                        <i className="fab fa-whatsapp" aria-hidden="true" />
                        <span className="hidden sm:inline">{t('Falar connosco')}</span>
                    </a>
                )}
            </div>
        </nav>
    );
}

function Quarto({ tipo, cor, escolhido, aoEscolher }: {
    tipo: TipoComPreco;
    cor: string;
    escolhido: boolean;
    aoEscolher: () => void;
}) {
    const esgotado = tipo.livres < 1;

    return (
        <article className={`card-hover flex h-full flex-col overflow-hidden rounded-2xl border bg-white shadow-sm ${
            escolhido ? 'border-2' : 'border-slate-200'
        }`} style={escolhido ? { borderColor: cor } : undefined}>
            {tipo.fotos[0] ? (
                <img src={tipo.fotos[0]} alt={tipo.nome} className="h-40 w-full object-cover" />
            ) : (
                <div className="grid h-40 place-items-center bg-slate-100 text-4xl text-slate-300" aria-hidden="true">
                    <i className="fas fa-bed" />
                </div>
            )}

            <div className="flex flex-1 flex-col p-4">
                <h3 className="font-bold text-slate-900">{tipo.nome}</h3>

                {tipo.descricao && <p className="mt-1 line-clamp-2 text-sm text-slate-500">{tipo.descricao}</p>}

                <p className="mt-2 text-xs text-slate-500">
                    <i className="fas fa-user-group mr-1.5" aria-hidden="true" />
                    {t('até :n pessoa(s)', { n: tipo.capacidade })}
                </p>

                {tipo.comodidades.length > 0 && (
                    <ul className="mt-2 flex flex-wrap gap-1.5">
                        {tipo.comodidades.slice(0, 5).map((x) => (
                            <li key={x.valor} className="rounded-lg bg-slate-100 px-2 py-1 text-[11px] text-slate-600">
                                <i className={`fas ${x.icone} mr-1`} aria-hidden="true" />{x.rotulo}
                            </li>
                        ))}
                    </ul>
                )}

                <div className="mt-auto pt-4">
                    <p className="text-2xl font-bold tabular-nums text-slate-900">
                        {kz(tipo.preco_por_noite)} <span className="text-sm font-normal text-slate-400">Kz {t('/noite')}</span>
                    </p>
                    <p className="text-sm text-slate-500 tabular-nums">
                        {t(':n noite(s) = :v Kz', { n: tipo.noites, v: kz(tipo.preco_total) })}
                    </p>

                    {esgotado ? (
                        <p className="mt-3 rounded-xl bg-slate-100 px-4 py-2.5 text-center text-sm font-semibold text-slate-500">
                            <i className="fas fa-ban mr-1.5" aria-hidden="true" />
                            {t('Esgotado nestas datas')}
                        </p>
                    ) : (
                        <button type="button" onClick={aoEscolher}
                            className="mt-3 w-full rounded-xl px-4 py-2.5 font-bold text-white shadow transition-all hover:-translate-y-0.5"
                            style={{ background: cor }}>
                            {t('Reservar')}
                            {tipo.livres <= 3 && (
                                <span className="ml-2 text-xs font-normal opacity-90">
                                    {t('(só :n livre(s))', { n: tipo.livres })}
                                </span>
                            )}
                        </button>
                    )}
                </div>
            </div>
        </article>
    );
}

function Dados({ slug, casa, tipo, de, ate, adultos, criancas, hospede, aoEscolherHospede, aoVoltar, aoReservar }: {
    slug: string;
    casa: ACasa;
    tipo: TipoComPreco;
    de: string;
    ate: string;
    adultos: number;
    criancas: number;
    hospede: HospedePublico | null;
    aoEscolherHospede: (h: HospedePublico | null) => void;
    aoVoltar: () => void;
    aoReservar: (r: ReservaFeita) => void;
}) {
    const [modo, porModo] = useState<'escolher' | 'entrar' | 'criar' | 'sem-conta'>(hospede ? 'escolher' : 'escolher');
    const [nome, porNome] = useState('');
    const [telefone, porTelefone] = useState('');
    const [email, porEmail] = useState('');
    const [senha, porSenha] = useState('');
    const [notas, porNotas] = useState('');

    const entrar = useMutation({
        mutationFn: () => reservaOnline.entrar(slug, telefone, senha),
        onSuccess: (r) => { aoEscolherHospede(r.hospede); porModo('escolher'); },
    });

    const registar = useMutation({
        mutationFn: () => reservaOnline.registar(slug, { nome, telefone, email, senha }),
        onSuccess: (r) => { aoEscolherHospede(r.hospede); porModo('escolher'); },
    });

    const reservar = useMutation({
        mutationFn: () => reservaOnline.reservar(slug, {
            tipo: tipo.id, de, ate, adultos, criancas,
            hospede_id: hospede?.id ?? null,
            nome: hospede ? undefined : nome,
            telefone: hospede ? undefined : telefone,
            email: email || undefined,
            notas: notas || undefined,
        }),
        onSuccess: (r) => aoReservar(r.reserva),
    });

    const sinal = casa.sinal ? Math.round(tipo.preco_total * casa.sinal_percentagem) / 100 : 0;
    const podeReservar = Boolean(hospede) || (modo === 'sem-conta' && nome.trim().length > 1 && telefone.trim().length > 5);

    return (
        <section className="grid gap-5 lg:grid-cols-[1fr_20rem]">
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="mb-4 text-lg font-bold text-slate-900">{t('Quem fica no quarto?')}</h2>

                {hospede ? (
                    <div className="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <span>
                            <span className="block font-bold text-emerald-900">{hospede.nome}</span>
                            <span className="block text-sm text-emerald-700">
                                {[hospede.telefone, hospede.email].filter(Boolean).join(' · ')}
                            </span>
                        </span>
                        <button type="button" onClick={() => aoEscolherHospede(null)}
                            className="rounded-lg p-2 text-emerald-600 transition-colors hover:text-emerald-900"
                            aria-label={t('Não sou eu')}>
                            <i className="fas fa-xmark" aria-hidden="true" />
                        </button>
                    </div>
                ) : modo === 'escolher' ? (
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Escolha icone="fa-right-to-bracket" rotulo={t('Já cá fiquei')}
                            nota={t('Entro com o meu telefone')} cor={casa.cor} aoCarregar={() => porModo('entrar')} />
                        <Escolha icone="fa-user-plus" rotulo={t('Criar conta')}
                            nota={t('Para as próximas ser mais rápido')} cor={casa.cor} aoCarregar={() => porModo('criar')} />
                        <Escolha icone="fa-bolt" rotulo={t('Reservar sem conta')}
                            nota={t('Só o nome e o telefone')} cor={casa.cor} aoCarregar={() => porModo('sem-conta')} />
                    </div>
                ) : (
                    <div className="space-y-3">
                        <button type="button" onClick={() => porModo('escolher')}
                            className="text-sm font-semibold text-slate-500 hover:underline">
                            <i className="fas fa-chevron-left mr-1" aria-hidden="true" />
                            {t('Voltar')}
                        </button>

                        {modo === 'entrar' && (
                            <>
                                <Aviso erro={entrar.error} />
                                <Campo etiqueta={t('Telefone')}>
                                    <input value={telefone} className={ENTRADA} inputMode="tel"
                                        onChange={(e) => porTelefone(e.target.value)} />
                                </Campo>
                                <Campo etiqueta={t('Senha (se tiver)')}>
                                    <input type="password" value={senha} className={ENTRADA} autoComplete="current-password"
                                        onChange={(e) => porSenha(e.target.value)} />
                                </Campo>
                                <button type="button" onClick={() => entrar.mutate()} disabled={entrar.isPending}
                                    className="w-full rounded-xl px-4 py-2.5 font-bold text-white shadow transition-all hover:-translate-y-0.5 disabled:opacity-60"
                                    style={{ background: casa.cor }}>
                                    {t('Entrar')}
                                </button>
                            </>
                        )}

                        {modo === 'criar' && (
                            <>
                                <Aviso erro={registar.error} />
                                <Campo etiqueta={t('Nome')}>
                                    <input value={nome} className={ENTRADA} onChange={(e) => porNome(e.target.value)} />
                                </Campo>
                                <Campo etiqueta={t('Telefone')}>
                                    <input value={telefone} className={ENTRADA} inputMode="tel"
                                        onChange={(e) => porTelefone(e.target.value)} />
                                </Campo>
                                <Campo etiqueta={t('Email')}>
                                    <input type="email" value={email} className={ENTRADA}
                                        onChange={(e) => porEmail(e.target.value)} />
                                </Campo>
                                <Campo etiqueta={t('Senha')}>
                                    <input type="password" value={senha} className={ENTRADA} autoComplete="new-password"
                                        onChange={(e) => porSenha(e.target.value)} />
                                </Campo>
                                <button type="button" onClick={() => registar.mutate()} disabled={registar.isPending}
                                    className="w-full rounded-xl px-4 py-2.5 font-bold text-white shadow transition-all hover:-translate-y-0.5 disabled:opacity-60"
                                    style={{ background: casa.cor }}>
                                    {t('Criar conta')}
                                </button>
                            </>
                        )}

                        {modo === 'sem-conta' && (
                            <>
                                <Campo etiqueta={t('Nome')}>
                                    <input value={nome} className={ENTRADA} onChange={(e) => porNome(e.target.value)} />
                                </Campo>
                                <Campo etiqueta={t('Telefone')}>
                                    <input value={telefone} className={ENTRADA} inputMode="tel"
                                        onChange={(e) => porTelefone(e.target.value)} />
                                </Campo>
                                <Campo etiqueta={t('Email')}>
                                    <input type="email" value={email} className={ENTRADA}
                                        onChange={(e) => porEmail(e.target.value)} />
                                </Campo>
                            </>
                        )}
                    </div>
                )}

                <div className="mt-4">
                    <Campo etiqueta={t('Alguma coisa que devamos saber?')}>
                        <textarea rows={2} value={notas} className={ENTRADA}
                            placeholder={t('Chegada tarde, cama extra, andar baixo…')}
                            onChange={(e) => porNotas(e.target.value)} />
                    </Campo>
                </div>

                {(casa.politica_de_cancelamento || casa.cancelamento_horas > 0) && (
                    <p className="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
                        <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                        {casa.politica_de_cancelamento
                            || t('Cancelamento gratuito até :h hora(s) antes da chegada.', { h: casa.cancelamento_horas })}
                    </p>
                )}
            </div>

            {/* O RESUMO fica à vista até ao fim: é o que o hóspede confirma. */}
            <aside className="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-20">
                <h3 className="mb-3 font-bold text-slate-900">{tipo.nome}</h3>

                <dl className="space-y-1.5 text-sm">
                    <Linha rotulo={t('Chegada')} valor={pt(de)} />
                    <Linha rotulo={t('Partida')} valor={pt(ate)} />
                    <Linha rotulo={t('Noites')} valor={String(tipo.noites)} />
                    <Linha rotulo={t('Pessoas')} valor={t(':a adulto(s) + :c criança(s)', { a: adultos, c: criancas })} />
                    <Linha rotulo={t('Por noite')} valor={`${kz(tipo.preco_por_noite)} Kz`} />

                    <div className="flex items-baseline justify-between gap-3 border-t border-slate-200 pt-2">
                        <dt className="font-bold text-slate-700">{t('Total')}</dt>
                        <dd className="text-xl font-bold tabular-nums text-slate-900">{kz(tipo.preco_total)} Kz</dd>
                    </div>

                    {sinal > 0 && (
                        <div className="flex items-baseline justify-between gap-3">
                            <dt className="text-amber-700">{t('Sinal (:p%)', { p: casa.sinal_percentagem })}</dt>
                            <dd className="font-bold tabular-nums text-amber-700">{kz(sinal)} Kz</dd>
                        </div>
                    )}
                </dl>

                <Aviso erro={reservar.error} />

                <button type="button" disabled={! podeReservar || reservar.isPending}
                    onClick={() => reservar.mutate()}
                    className="mt-4 w-full rounded-xl px-5 py-3 font-bold text-white shadow transition-all hover:-translate-y-0.5 disabled:opacity-50"
                    style={{ background: casa.cor }}>
                    {reservar.isPending ? t('A reservar…') : t('Confirmar reserva')}
                </button>

                <button type="button" onClick={aoVoltar}
                    className="mt-2 w-full rounded-xl px-5 py-2 text-sm font-semibold text-slate-500 hover:underline">
                    {t('Escolher outro quarto')}
                </button>
            </aside>
        </section>
    );
}

function Escolha({ icone, rotulo, nota, cor, aoCarregar }: {
    icone: string;
    rotulo: string;
    nota: string;
    cor: string;
    aoCarregar: () => void;
}) {
    return (
        <button type="button" onClick={aoCarregar}
            className="card-hover rounded-xl border border-slate-200 p-4 text-left transition-all hover:border-slate-300">
            <i className={`fas ${icone} text-xl`} style={{ color: cor }} aria-hidden="true" />
            <span className="mt-2 block font-bold text-slate-800">{rotulo}</span>
            <span className="block text-xs text-slate-500">{nota}</span>
        </button>
    );
}

function Confirmada({ casa, reserva, aoRecomecar }: {
    casa: ACasa;
    reserva: ReservaFeita;
    aoRecomecar: () => void;
}) {
    return (
        <div className="min-h-screen bg-white">
            <Cabecalho casa={casa} />

            <main className="mx-auto max-w-2xl px-5 py-12 text-center">
                <span className="mx-auto mb-5 grid h-20 w-20 place-items-center rounded-full text-4xl text-white shadow-lg"
                    style={{ background: `linear-gradient(135deg, ${casa.cor}, ${casa.cor2})` }} aria-hidden="true">
                    <i className="fas fa-check" />
                </span>

                <h1 className="text-2xl font-bold text-slate-900">{t('Reserva feita')}</h1>
                <p className="mt-2 text-slate-500">
                    {t('Guarde este número — é por ele que a recepção o encontra.')}
                </p>

                <p className="mt-4 font-mono text-3xl font-bold" style={{ color: casa.cor }}>{reserva.numero}</p>
                {reserva.codigo && (
                    <p className="mt-1 text-sm text-slate-400">{t('Código: :c', { c: reserva.codigo })}</p>
                )}

                <dl className="mx-auto mt-6 space-y-1.5 rounded-2xl border border-slate-200 p-5 text-left text-sm">
                    <Linha rotulo={t('Quarto')} valor={reserva.tipo} />
                    <Linha rotulo={t('Chegada')} valor={reserva.entrada ? pt(reserva.entrada) : '—'} />
                    <Linha rotulo={t('Partida')} valor={reserva.saida ? pt(reserva.saida) : '—'} />
                    <Linha rotulo={t('Noites')} valor={String(reserva.noites)} />
                    <div className="flex items-baseline justify-between gap-3 border-t border-slate-200 pt-2">
                        <dt className="font-bold text-slate-700">{t('Total')}</dt>
                        <dd className="text-lg font-bold tabular-nums text-slate-900">{kz(reserva.total)} Kz</dd>
                    </div>
                    {reserva.sinal > 0 && (
                        <div className="flex items-baseline justify-between gap-3">
                            <dt className="text-amber-700">{t('Sinal a pagar (:p%)', { p: reserva.sinal_percentagem })}</dt>
                            <dd className="font-bold tabular-nums text-amber-700">{kz(reserva.sinal)} Kz</dd>
                        </div>
                    )}
                </dl>

                <p className="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                    <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                    {t('A reserva fica por confirmar até a casa a aceitar. Entramos em contacto.')}
                </p>

                <div className="mt-6 flex flex-wrap justify-center gap-2">
                    {casa.whatsapp && (
                        <a href={`https://wa.me/${casa.whatsapp.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(reserva.numero)}`}
                            target="_blank" rel="noreferrer"
                            className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 font-semibold text-white transition-all hover:-translate-y-0.5 hover:bg-emerald-600">
                            <i className="fab fa-whatsapp" aria-hidden="true" />
                            {t('Falar connosco')}
                        </a>
                    )}
                    <button type="button" onClick={aoRecomecar}
                        className="rounded-xl border border-slate-200 px-4 py-2.5 font-semibold text-slate-600 transition-all hover:-translate-y-0.5">
                        {t('Fazer outra reserva')}
                    </button>
                </div>

                <Rodape casa={casa} />
            </main>
        </div>
    );
}

function Rodape({ casa }: { casa: ACasa }) {
    return (
        <footer className="mt-12 border-t border-slate-100 pt-6 text-center text-sm text-slate-500">
            {casa.comodidades.length > 0 && (
                <ul className="mb-4 flex flex-wrap justify-center gap-2">
                    {casa.comodidades.map((c) => (
                        <li key={c.valor} className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs text-slate-600">
                            <i className={`fas ${c.icone} mr-1.5`} aria-hidden="true" />{c.rotulo}
                        </li>
                    ))}
                </ul>
            )}

            <p className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                {casa.telefone && <span><i className="fas fa-phone mr-1.5" aria-hidden="true" />{casa.telefone}</span>}
                {casa.email && <span><i className="fas fa-envelope mr-1.5" aria-hidden="true" />{casa.email}</span>}
            </p>

            <p className="mt-3 flex flex-wrap items-center justify-center gap-3">
                {casa.instagram && (
                    <a href={casa.instagram.startsWith('http') ? casa.instagram : `https://instagram.com/${casa.instagram.replace('@', '')}`}
                        target="_blank" rel="noreferrer" aria-label="Instagram" className="hover:text-slate-800">
                        <i className="fab fa-instagram text-lg" aria-hidden="true" />
                    </a>
                )}
                {casa.facebook && (
                    <a href={casa.facebook} target="_blank" rel="noreferrer" aria-label="Facebook" className="hover:text-slate-800">
                        <i className="fab fa-facebook text-lg" aria-hidden="true" />
                    </a>
                )}
                {casa.mapa && (
                    <a href={casa.mapa} target="_blank" rel="noreferrer" aria-label={t('Mapa')} className="hover:text-slate-800">
                        <i className="fas fa-map-location-dot text-lg" aria-hidden="true" />
                    </a>
                )}
                {casa.tripadvisor && (
                    <a href={casa.tripadvisor} target="_blank" rel="noreferrer" aria-label="TripAdvisor" className="hover:text-slate-800">
                        <i className="fab fa-tripadvisor text-lg" aria-hidden="true" />
                    </a>
                )}
            </p>

            {casa.regras && <p className="mx-auto mt-4 max-w-xl whitespace-pre-line text-xs text-slate-400">{casa.regras}</p>}
        </footer>
    );
}

function Linha({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt className="text-slate-500">{rotulo}</dt>
            <dd className="font-semibold tabular-nums text-slate-800">{valor}</dd>
        </div>
    );
}

function Aviso({ erro }: { erro: unknown }) {
    if (! erro) return null;

    const daApi = erro instanceof ErroDaApi ? erro : null;
    const primeiro = daApi ? Object.values(daApi.erros)[0]?.[0] : null;

    return (
        <p role="alert" className="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
            {primeiro ?? daApi?.message ?? t('Não foi possível. Tente outra vez.')}
        </p>
    );
}

function AEsperar() {
    return (
        <div className="mx-auto max-w-5xl animate-pulse space-y-3 px-5 py-8">
            <div className="h-10 w-1/3 rounded-xl bg-slate-200" />
            <div className="h-40 rounded-2xl bg-slate-100" />
            <div className="h-40 rounded-2xl bg-slate-50" />
        </div>
    );
}

function NaoAbriu({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className="grid min-h-screen place-items-center bg-slate-50 px-5">
            <div className="max-w-md text-center">
                <i className="fas fa-hotel mb-4 text-5xl text-slate-300" aria-hidden="true" />
                <h1 className="text-xl font-bold text-slate-900">
                    {daApi?.estado === 404 ? t('Não encontrámos este hotel') : t('Esta página não abriu')}
                </h1>
                <p className="mt-2 text-slate-500">{daApi?.message ?? t('Verifique a ligação.')}</p>
            </div>
        </div>
    );
}
