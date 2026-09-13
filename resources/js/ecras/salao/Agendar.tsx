import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useState, type CSSProperties, type ReactNode } from 'react';

import { ErroDaApi, criarApi } from '@/api/cliente';
import { etiquetaIntl, t } from '@/i18n';
import { cls } from '@/ui/tokens';

type Servico = {
    id: number;
    nome: string;
    descricao: string | null;
    preco: number;
    duracao: number;
    duracao_texto: string;
    categoria_id: number | null;
    categoria: { nome: string; cor: string | null } | null;
};

type Profissional = { id: number; nome: string; especialidade: string | null; foto: string | null };

type Cliente = {
    nome: string;
    telefone: string | null;
    email: string | null;
    vip: boolean;
    pontos: number;
    marcacoes: { data: string | null; hora: string; estado: string }[];
};

type Props = {
    slug: string;
    cliente: Cliente | null;
    casa: {
        nome: string | null;
        descricao: string | null;
        boas_vindas: string | null;
        morada: string | null;
        telefone: string | null;
        whatsapp: string | null;
        email: string | null;
        instagram: string | null;
        facebook: string | null;
        tiktok: string | null;
        mapa: string | null;
        logo: string | null;
        capa: string | null;
        cor: string;
        cor2: string;
        horario: string;
        dias: string;
        exige_confirmacao: boolean;
        galeria: string[];
    };
    categorias: { id: number; nome: string; cor: string | null; icone: string | null }[];
    servicos: Servico[];
    profissionais: Profissional[];
    datas: string[];
};

const publico = criarApi('/api/publico/salao');

/** O dinheiro como a montra o escreve: sem casas, ponto nos milhares. */
const kzInteiro = (n: number) => Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

const PADRAO = "url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")";

/**
 * A PÁGINA PÚBLICA DO SALÃO — a montra e a marcação online.
 *
 * É a página que uma cliente abre de uma ligação do Instagram, quase sempre no
 * telemóvel. Tem as secções de sempre (início, sobre, serviços, equipa,
 * galeria, contacto) e a marcação num painel por cima, em quatro passos.
 *
 * O SERVIDOR DECIDE: os horários livres vêm dele a cada escolha, e ao marcar a
 * hora é verificada outra vez — duas clientes a olhar para o mesmo horário não
 * ficam as duas com ele. Entrar numa conta pede a senha.
 */
export default function Agendar({ slug, cliente: clienteInicial, casa, categorias, servicos, profissionais, datas }: Props) {
    const cor = casa.cor;
    const gradiente = `linear-gradient(135deg, ${cor} 0%, ${casa.cor2} 100%)`;
    const nome = casa.nome || t('Salão');

    const [aberto, porAberto] = useState(false);
    const [rolou, porRolou] = useState(false);
    const [categoriaDaMontra, porCategoriaDaMontra] = useState<number | null>(null);
    const [foto, porFoto] = useState<number | null>(null);
    const [aviso, porAviso] = useState<{ tipo: 'ok' | 'erro'; texto: string } | null>(null);

    // O que se escolhe na marcação vive aqui, para os botões da montra o poderem pré-escolher.
    const [escolhidos, porEscolhidos] = useState<number[]>([]);
    const [profissional, porProfissional] = useState<number | null>(null);

    useEffect(() => {
        const aoRolar = () => porRolou(window.scrollY > 50);
        aoRolar();
        window.addEventListener('scroll', aoRolar, { passive: true });
        return () => window.removeEventListener('scroll', aoRolar);
    }, []);

    useEffect(() => {
        document.body.style.overflow = aberto || foto !== null ? 'hidden' : '';
        const tecla = (e: KeyboardEvent) => {
            if (e.key !== 'Escape') return;
            porAberto(false);
            porFoto(null);
        };
        window.addEventListener('keydown', tecla);
        return () => { window.removeEventListener('keydown', tecla); document.body.style.overflow = ''; };
    }, [aberto, foto]);

    useEffect(() => {
        if (!aviso) return;
        const relogio = window.setTimeout(() => porAviso(null), aviso.tipo === 'erro' ? 5000 : 4000);
        return () => window.clearTimeout(relogio);
    }, [aviso]);

    const daMontra = categoriaDaMontra === null ? servicos : servicos.filter((s) => s.categoria_id === categoriaDaMontra);

    const abrir = (servico?: number, pessoa?: number) => {
        if (servico && !escolhidos.includes(servico)) porEscolhidos([...escolhidos, servico]);
        if (pessoa) porProfissional(pessoa);
        porAberto(true);
    };

    const wa = (texto?: string) => `https://wa.me/${casa.whatsapp}${texto ? `?text=${encodeURIComponent(texto)}` : ''}`;
    const ligacoes = [['#inicio', t('Início')], ['#sobre', t('Sobre')], ['#servicos', t('Serviços')], ['#equipa', t('Equipa')], ['#galeria', t('Galeria')], ['#contacto', t('Contacto')]] as const;

    return (
        <div className="min-h-screen bg-white">
            <nav className={cls('fixed inset-x-0 top-0 z-50 transition-all duration-300', rolou ? 'bg-white shadow-lg' : 'bg-transparent')}>
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between md:h-20">
                        <a href="#inicio" className="flex items-center gap-3">
                            {casa.logo ? (
                                <img src={casa.logo} alt={nome} className="h-10 w-10 rounded-xl object-cover shadow-lg md:h-12 md:w-12" />
                            ) : (
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl shadow-lg md:h-12 md:w-12" style={{ background: gradiente }}>
                                    <i className="fas fa-spa text-xl text-white" aria-hidden="true" />
                                </span>
                            )}
                            <span>
                                <span className={cls('block text-lg font-bold transition-colors md:text-xl', rolou ? 'text-gray-900' : 'text-white')}>{nome}</span>
                                <span className={cls('hidden text-xs transition-colors md:block', rolou ? 'text-gray-500' : 'text-white/70')}>{t('Beleza & Bem-estar')}</span>
                            </span>
                        </a>
                        <div className="hidden items-center gap-8 md:flex">
                            {ligacoes.map(([href, rotulo]) => (
                                <a key={href} href={href} className={cls('text-sm font-medium transition-colors hover:opacity-80', rolou ? 'text-gray-700' : 'text-white')}>{rotulo}</a>
                            ))}
                        </div>
                        <div className="flex items-center gap-3">
                            {casa.whatsapp && (
                                <a href={wa()} target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"
                                    className={cls('flex h-10 w-10 items-center justify-center rounded-full transition-all hover:scale-110', rolou ? 'bg-green-500 text-white' : 'bg-white/20 text-white')}>
                                    <i className="fab fa-whatsapp text-lg" aria-hidden="true" />
                                </a>
                            )}
                            <button type="button" onClick={() => abrir()} className="rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-lg transition-all hover:scale-105" style={{ background: gradiente }}>
                                <i className="fas fa-calendar-check sm:mr-2" aria-hidden="true" /><span className="hidden sm:inline">{t('Agendar')}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            {/* ===== INÍCIO ===== */}
            <section id="inicio" className="relative flex min-h-screen items-center justify-center overflow-hidden">
                <div className="absolute inset-0" style={{ background: gradiente }} />
                <div className="absolute inset-0 opacity-10" style={{ backgroundImage: PADRAO }} />
                {casa.capa && (
                    <>
                        <img src={casa.capa} alt="" className="absolute inset-0 h-full w-full object-cover opacity-30" />
                        <div className="absolute inset-0 bg-gradient-to-b from-black/40 via-transparent to-black/60" />
                    </>
                )}
                <div className="absolute left-10 top-20 h-20 w-20 animate-pulse rounded-full bg-white/10 blur-xl" />
                <div className="absolute bottom-20 right-10 h-32 w-32 animate-pulse rounded-full bg-white/10 blur-xl" style={{ animationDelay: '1s' }} />
                <div className="absolute right-20 top-1/2 h-16 w-16 animate-pulse rounded-full bg-white/10 blur-xl" style={{ animationDelay: '2s' }} />

                <div className="relative z-10 mx-auto max-w-4xl px-4 text-center">
                    {casa.logo && <img src={casa.logo} alt={nome} className="entra mx-auto mb-6 h-24 w-24 rounded-2xl object-cover shadow-2xl md:h-32 md:w-32" />}
                    <h1 className="entra mb-4 text-4xl font-black leading-tight text-white md:text-6xl lg:text-7xl" style={{ ['--i' as string]: 2 } as CSSProperties}>{casa.nome || t('Meu Salão')}</h1>
                    <p className="entra mx-auto mb-8 max-w-2xl text-lg text-white/90 md:text-xl" style={{ ['--i' as string]: 4 } as CSSProperties}>
                        {casa.boas_vindas || t('Transforme seu visual com os melhores profissionais da cidade')}
                    </p>
                    <div className="entra flex flex-col items-center justify-center gap-4 sm:flex-row" style={{ ['--i' as string]: 6 } as CSSProperties}>
                        <button type="button" onClick={() => abrir()} className="flex items-center gap-2 rounded-full bg-white px-8 py-4 text-lg font-bold text-gray-900 shadow-2xl transition-all hover:scale-105 hover:shadow-xl">
                            <i className="fas fa-calendar-check" aria-hidden="true" />{t('Agendar Agora')}
                        </button>
                        <a href="#servicos" className="flex items-center gap-2 rounded-full border-2 border-white/30 bg-white/20 px-8 py-4 text-lg font-bold text-white backdrop-blur-sm transition-all hover:bg-white/30">
                            <i className="fas fa-spa" aria-hidden="true" />{t('Ver Serviços')}
                        </a>
                    </div>
                    <div className="mx-auto mt-16 grid max-w-lg grid-cols-3 gap-8">
                        {[[`${profissionais.length}+`, t('Profissionais')], [`${servicos.length}+`, t('Serviços')], ['5★', t('Avaliação')]].map(([n, r]) => (
                            <div key={r} className="text-center">
                                <p className="text-3xl font-black text-white md:text-4xl">{n}</p>
                                <p className="text-sm text-white/70">{r}</p>
                            </div>
                        ))}
                    </div>
                </div>
                <a href="#sobre" aria-label={t('Sobre')} className="absolute bottom-8 left-1/2 flex h-10 w-10 -translate-x-1/2 animate-bounce items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-sm">
                    <i className="fas fa-chevron-down" aria-hidden="true" />
                </a>
            </section>

            {/* ===== SOBRE ===== */}
            <section id="sobre" className="bg-gray-50 py-20 md:py-32">
                <div className="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                    <div className="relative">
                        {casa.capa ? (
                            <div className="relative overflow-hidden rounded-2xl shadow-2xl">
                                <img src={casa.capa} alt={nome} className="h-96 w-full object-cover" loading="lazy" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
                            </div>
                        ) : (
                            <div className="flex h-96 items-center justify-center rounded-2xl shadow-2xl" style={{ background: `linear-gradient(135deg, ${cor}30 0%, ${casa.cor2}30 100%)` }}>
                                <i className="fas fa-spa icon-float text-9xl" style={{ color: cor }} aria-hidden="true" />
                            </div>
                        )}
                        <div className="card-hover absolute -bottom-6 -right-2 max-w-xs rounded-2xl bg-white p-6 shadow-xl sm:-right-6">
                            <div className="flex items-center gap-4">
                                <span className="flex h-14 w-14 items-center justify-center rounded-full text-white" style={{ background: gradiente }}><i className="fas fa-clock text-xl" aria-hidden="true" /></span>
                                <div>
                                    <p className="font-bold text-gray-900">{t('Horário')}</p>
                                    <p className="text-sm text-gray-500">{casa.horario}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <Selo cor={cor}>{t('Sobre Nós')}</Selo>
                        <h2 className="mb-6 text-3xl font-black text-gray-900 md:text-4xl">{t('Bem-vindo ao')} <span style={{ color: cor }}>{casa.nome || t('Nosso Salão')}</span></h2>
                        <p className="mb-6 text-lg leading-relaxed text-gray-600">
                            {casa.descricao || t('Somos um espaço dedicado à sua beleza e bem-estar. Com profissionais qualificados e um ambiente acolhedor, oferecemos serviços de alta qualidade para realçar a sua beleza natural.')}
                        </p>
                        <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {[t('Profissionais Qualificados'), t('Produtos Premium'), t('Ambiente Acolhedor'), t('Agendamento Online')].map((x) => (
                                <div key={x} className="flex items-center gap-3">
                                    <span className="flex h-10 w-10 items-center justify-center rounded-full" style={{ background: `${cor}20` }}><i className="fas fa-check" style={{ color: cor }} aria-hidden="true" /></span>
                                    <span className="font-medium text-gray-700">{x}</span>
                                </div>
                            ))}
                        </div>
                        <div className="flex flex-wrap gap-4">
                            {casa.telefone && (
                                <a href={`tel:${casa.telefone}`} className="flex items-center gap-2 rounded-full bg-gray-100 px-4 py-2 text-gray-700 transition hover:bg-gray-200"><i className="fas fa-phone" aria-hidden="true" />{casa.telefone}</a>
                            )}
                            {casa.whatsapp && (
                                <a href={wa()} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 rounded-full bg-green-500 px-4 py-2 text-white transition hover:bg-green-600"><i className="fab fa-whatsapp" aria-hidden="true" />WhatsApp</a>
                            )}
                        </div>
                    </div>
                </div>
            </section>

            {/* ===== SERVIÇOS ===== */}
            <section id="servicos" className="py-20 md:py-32">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Cabeca cor={cor} selo={t('Nossos Serviços')} titulo={t('O Que Oferecemos')} nota={t('Descubra nossa variedade de serviços pensados para realçar sua beleza')} />
                    {categorias.length > 0 && (
                        <div className="mb-12 flex flex-wrap justify-center gap-3">
                            <Pilula activa={categoriaDaMontra === null} fundo={gradiente} onClick={() => porCategoriaDaMontra(null)}>{t('Todos')}</Pilula>
                            {categorias.map((c) => (
                                <Pilula key={c.id} activa={categoriaDaMontra === c.id} fundo={c.cor || cor} onClick={() => porCategoriaDaMontra(c.id)}>
                                    {c.icone && <span className="mr-1">{c.icone}</span>}{c.nome}
                                </Pilula>
                            ))}
                        </div>
                    )}
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {daMontra.length === 0 ? (
                            <div className="col-span-full py-12 text-center">
                                <i className="fas fa-spa icon-float mb-4 text-6xl text-gray-200" aria-hidden="true" />
                                <p className="text-gray-500">{t('Nenhum serviço disponível no momento')}</p>
                            </div>
                        ) : daMontra.slice(0, 6).map((s, i) => (
                            <div key={s.id} className="entra card-hover group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-lg transition-all duration-300 hover:shadow-xl" style={{ ['--i' as string]: i } as CSSProperties}>
                                <div className="h-2" style={{ background: `linear-gradient(90deg, ${cor} 0%, ${casa.cor2} 100%)` }} />
                                <div className="p-6">
                                    <div className="mb-4 flex items-start justify-between gap-3">
                                        <div className="flex-1">
                                            <h3 className="text-lg font-bold text-gray-900 transition">{s.nome}</h3>
                                            {s.categoria && (
                                                <span className="mt-2 inline-block rounded-full px-2 py-1 text-xs" style={{ background: `${s.categoria.cor ?? '#e5e7eb'}20`, color: s.categoria.cor ?? '#6b7280' }}>{s.categoria.nome}</span>
                                            )}
                                        </div>
                                        <div className="text-right">
                                            <p className="text-2xl font-black tabular-nums" style={{ color: cor }}>{kzInteiro(s.preco)}</p>
                                            <p className="text-xs text-gray-500">Kz</p>
                                        </div>
                                    </div>
                                    {s.descricao && <p className="mb-4 line-clamp-2 text-sm text-gray-600">{s.descricao}</p>}
                                    <div className="flex items-center justify-between border-t border-gray-100 pt-4">
                                        <span className="flex items-center gap-1 text-sm text-gray-500"><i className="fas fa-clock" aria-hidden="true" />{s.duracao_texto}</span>
                                        <button type="button" onClick={() => abrir(s.id)} className="rounded-full px-4 py-2 text-sm font-bold text-white transition-all hover:scale-105" style={{ background: gradiente }}>{t('Agendar')}</button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                    {daMontra.length > 6 && (
                        <div className="mt-12 text-center">
                            <button type="button" onClick={() => abrir()} className="group rounded-full px-8 py-4 font-bold text-white shadow-lg transition-all hover:scale-105 hover:shadow-xl" style={{ background: gradiente }}>
                                {t('Ver Todos os Serviços')} <i className="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                            </button>
                        </div>
                    )}
                </div>
            </section>

            {/* ===== EQUIPA ===== */}
            {profissionais.length > 0 && (
                <section id="equipa" className="bg-gray-50 py-20 md:py-32">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <Cabeca cor={cor} selo={t('Nossa Equipa')} titulo={t('Profissionais Especializados')} nota={t('Conheça os talentos por trás de cada transformação')} />
                        <div className="grid grid-cols-2 gap-6 md:grid-cols-3 lg:grid-cols-4">
                            {profissionais.slice(0, 8).map((p, i) => (
                                <div key={p.id} className="entra group text-center" style={{ ['--i' as string]: i } as CSSProperties}>
                                    <div className="relative mb-4 overflow-hidden rounded-2xl">
                                        {p.foto ? (
                                            <img src={p.foto} alt={p.nome} loading="lazy" className="aspect-square w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                        ) : (
                                            <div className="flex aspect-square w-full items-center justify-center text-4xl font-black text-white" style={{ background: gradiente }}>{p.nome.slice(0, 2).toUpperCase()}</div>
                                        )}
                                        <div className="absolute inset-0 flex items-end justify-center bg-gradient-to-t from-black/70 via-transparent to-transparent pb-4 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
                                            <button type="button" onClick={() => abrir(undefined, p.id)} className="rounded-full bg-white px-4 py-2 text-sm font-bold text-gray-900 transition hover:scale-105">{t('Agendar')}</button>
                                        </div>
                                    </div>
                                    <h3 className="font-bold text-gray-900">{p.nome}</h3>
                                    {p.especialidade && <p className="text-sm text-gray-500">{p.especialidade}</p>}
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* ===== GALERIA ===== */}
            {casa.galeria.length > 0 && (
                <section id="galeria" className="py-20 md:py-32">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <Cabeca cor={cor} selo={t('Galeria')} titulo={t('Nossos Trabalhos')} nota={t('Veja algumas das nossas transformações')} />
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                            {casa.galeria.map((img, i) => (
                                <button key={img} type="button" onClick={() => porFoto(i)} aria-label={t('Galeria :n', { n: i + 1 })}
                                    className="entra group relative aspect-square cursor-pointer overflow-hidden rounded-xl" style={{ ['--i' as string]: i } as CSSProperties}>
                                    <img src={img} alt="" loading="lazy" className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                    <span className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"><i className="fas fa-search-plus text-2xl text-white" aria-hidden="true" /></span>
                                </button>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {foto !== null && (
                <div role="dialog" aria-modal="true" aria-label={t('Galeria')} className="animate-fade-in fixed inset-0 z-[100] flex items-center justify-center bg-black/95" onClick={() => porFoto(null)}>
                    <button type="button" onClick={() => porFoto(null)} aria-label={t('Fechar')} className="absolute right-4 top-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30"><i className="fas fa-times text-xl" aria-hidden="true" /></button>
                    <button type="button" aria-label={t('Anterior')} onClick={(e) => { e.stopPropagation(); porFoto((foto - 1 + casa.galeria.length) % casa.galeria.length); }} className="absolute left-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30"><i className="fas fa-chevron-left" aria-hidden="true" /></button>
                    <button type="button" aria-label={t('Seguinte')} onClick={(e) => { e.stopPropagation(); porFoto((foto + 1) % casa.galeria.length); }} className="absolute right-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30"><i className="fas fa-chevron-right" aria-hidden="true" /></button>
                    <img key={foto} src={casa.galeria[foto]} alt="" onClick={(e) => e.stopPropagation()} className="animate-scale-in max-h-[80vh] max-w-[calc(100%-8rem)] rounded-lg object-contain" />
                    <p className="absolute bottom-4 left-1/2 -translate-x-1/2 text-sm text-white/70">{foto + 1} / {casa.galeria.length}</p>
                </div>
            )}

            {/* ===== CHAMADA ===== */}
            <section className="relative overflow-hidden py-20 md:py-32">
                <div className="absolute inset-0" style={{ background: gradiente }} />
                <div className="absolute inset-0 opacity-10" style={{ backgroundImage: PADRAO }} />
                <div className="relative z-10 mx-auto max-w-4xl px-4 text-center">
                    <h2 className="mb-6 text-3xl font-black text-white md:text-5xl">{t('Pronto para Transformar seu Visual?')}</h2>
                    <p className="mx-auto mb-8 max-w-2xl text-xl text-white/80">{t('Agende agora mesmo e deixe nossos profissionais cuidarem de você')}</p>
                    <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <button type="button" onClick={() => abrir()} className="flex items-center gap-3 rounded-full bg-white px-10 py-5 text-lg font-bold text-gray-900 shadow-2xl transition-all hover:scale-105 hover:shadow-xl">
                            <i className="fas fa-calendar-check text-xl" aria-hidden="true" />{t('Agendar Online')}
                        </button>
                        {casa.whatsapp && (
                            <a href={wa(t('Olá! Gostaria de agendar um serviço.'))} target="_blank" rel="noopener noreferrer" className="flex items-center gap-3 rounded-full bg-green-500 px-10 py-5 text-lg font-bold text-white shadow-2xl transition-all hover:scale-105 hover:shadow-xl">
                                <i className="fab fa-whatsapp text-xl" aria-hidden="true" />WhatsApp
                            </a>
                        )}
                    </div>
                </div>
            </section>

            {/* ===== CONTACTO ===== */}
            <section id="contacto" className="bg-gray-900 py-20 md:py-32">
                <div className="mx-auto grid max-w-7xl grid-cols-1 gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                    <div className="text-white">
                        <span className="mb-4 inline-block rounded-full px-4 py-1 text-sm font-bold text-white" style={{ background: cor }}>{t('Contacto')}</span>
                        <h2 className="mb-6 text-3xl font-black md:text-4xl">{t('Visite-nos ou Entre em Contacto')}</h2>
                        <p className="mb-8 text-gray-400">{t('Estamos à sua espera. Entre em contacto connosco para mais informações ou agende a sua visita.')}</p>
                        <div className="space-y-6">
                            {casa.morada && <Contacto cor={cor} icone="fa-map-marker-alt" titulo={t('Morada')}><p className="text-gray-400">{casa.morada}</p></Contacto>}
                            {casa.telefone && <Contacto cor={cor} icone="fa-phone" titulo={t('Telefone')}><a href={`tel:${casa.telefone}`} className="text-gray-400 transition hover:text-white">{casa.telefone}</a></Contacto>}
                            {casa.email && <Contacto cor={cor} icone="fa-envelope" titulo={t('Email')}><a href={`mailto:${casa.email}`} className="text-gray-400 transition hover:text-white">{casa.email}</a></Contacto>}
                            <Contacto cor={cor} icone="fa-clock" titulo={t('Horário')}><p className="text-gray-400">{casa.dias}</p><p className="text-gray-400">{casa.horario}</p></Contacto>
                        </div>
                        <div className="mt-8 flex items-center gap-4">
                            {casa.instagram && <Social href={`https://instagram.com/${casa.instagram}`} rotulo="Instagram" icone="fa-instagram" estilo={{ background: 'linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)' }} />}
                            {casa.facebook && <Social href={casa.facebook} rotulo="Facebook" icone="fa-facebook-f" className="bg-blue-600" />}
                            {casa.tiktok && <Social href={`https://tiktok.com/@${casa.tiktok}`} rotulo="TikTok" icone="fa-tiktok" className="border border-white/20 bg-black" />}
                            {casa.whatsapp && <Social href={wa()} rotulo="WhatsApp" icone="fa-whatsapp" className="bg-green-500" />}
                        </div>
                    </div>
                    <div className="relative">
                        {casa.mapa ? (
                            <div className="h-96 overflow-hidden rounded-2xl shadow-2xl lg:h-full">
                                <iframe src={casa.mapa} title={t('Mapa')} width="100%" height="100%" style={{ border: 0 }} loading="lazy" referrerPolicy="no-referrer-when-downgrade" allowFullScreen className="grayscale transition-all duration-500 hover:grayscale-0" />
                            </div>
                        ) : (
                            <div className="flex h-96 items-center justify-center rounded-2xl lg:h-full" style={{ background: `linear-gradient(135deg, ${cor}30 0%, ${casa.cor2}30 100%)` }}>
                                <div className="text-center">
                                    <i className="fas fa-map-marked-alt icon-float mb-4 text-6xl" style={{ color: cor }} aria-hidden="true" />
                                    <p className="text-gray-400">{t('Mapa não configurado')}</p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </section>

            <footer className="bg-gray-950 py-8">
                <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 sm:px-6 md:flex-row lg:px-8">
                    <div className="flex items-center gap-3">
                        {casa.logo && <img src={casa.logo} alt={nome} className="h-8 w-8 rounded-lg object-cover" />}
                        <span className="font-bold text-white">{nome}</span>
                    </div>
                    <p className="text-sm text-gray-500">© {new Date().getFullYear()} {nome}. {t('Todos os direitos reservados.')}</p>
                    <p className="text-xs text-gray-600">Powered by <span className="text-gray-500">SOSERP</span></p>
                </div>
            </footer>

            {aberto && (
                <Marcacao
                    slug={slug}
                    casa={casa}
                    gradiente={gradiente}
                    categorias={categorias}
                    servicos={servicos}
                    profissionais={profissionais}
                    datas={datas}
                    clienteInicial={clienteInicial}
                    escolhidos={escolhidos}
                    porEscolhidos={porEscolhidos}
                    profissional={profissional}
                    porProfissional={porProfissional}
                    avisar={porAviso}
                    aoFechar={() => porAberto(false)}
                />
            )}

            {aviso && (
                <div role={aviso.tipo === 'erro' ? 'alert' : 'status'} className="animate-fade-in fixed bottom-4 right-4 z-[110]">
                    <div className={cls('flex items-center gap-2 rounded-xl px-6 py-3 text-white shadow-lg', aviso.tipo === 'erro' ? 'bg-red-500' : 'bg-green-500')}>
                        <i className={cls('fas', aviso.tipo === 'erro' ? 'fa-exclamation-circle' : 'fa-check-circle')} aria-hidden="true" />
                        <span>{aviso.texto}</span>
                    </div>
                </div>
            )}

            {casa.whatsapp && !aberto && (
                <a href={wa()} target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"
                    className="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-green-500 text-white shadow-2xl transition-all hover:scale-110 hover:bg-green-600">
                    <i className="fab fa-whatsapp text-2xl" aria-hidden="true" />
                </a>
            )}
        </div>
    );
}

function Selo({ cor, children }: { cor: string; children: ReactNode }) {
    return <span className="mb-4 inline-block rounded-full px-4 py-1 text-sm font-bold" style={{ background: `${cor}20`, color: cor }}>{children}</span>;
}

function Cabeca({ cor, selo, titulo, nota }: { cor: string; selo: string; titulo: string; nota: string }) {
    return (
        <div className="mb-16 text-center">
            <Selo cor={cor}>{selo}</Selo>
            <h2 className="mb-4 text-3xl font-black text-gray-900 md:text-4xl">{titulo}</h2>
            <p className="mx-auto max-w-2xl text-lg text-gray-600">{nota}</p>
        </div>
    );
}

function Pilula({ activa, fundo, onClick, children, pequena = false }: { activa: boolean; fundo: string; onClick: () => void; children: ReactNode; pequena?: boolean }) {
    return (
        <button type="button" onClick={onClick} aria-pressed={activa}
            className={cls('rounded-full font-medium transition-all active:scale-95', pequena ? 'px-3 py-1.5 text-sm' : 'px-6 py-2', activa ? 'text-white shadow-lg' : 'bg-gray-100 text-gray-600 hover:bg-gray-200')}
            style={activa ? { background: fundo } : undefined}>
            {children}
        </button>
    );
}

function Contacto({ cor, icone, titulo, children }: { cor: string; icone: string; titulo: string; children: ReactNode }) {
    return (
        <div className="flex items-start gap-4">
            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" style={{ background: `${cor}20` }}><i className={cls('fas text-xl', icone)} style={{ color: cor }} aria-hidden="true" /></span>
            <div><p className="font-bold text-white">{titulo}</p>{children}</div>
        </div>
    );
}

function Social({ href, rotulo, icone, className, estilo }: { href: string; rotulo: string; icone: string; className?: string; estilo?: CSSProperties }) {
    return (
        <a href={href} target="_blank" rel="noopener noreferrer" aria-label={rotulo} style={estilo}
            className={cls('flex h-12 w-12 items-center justify-center rounded-full text-white transition-all hover:scale-110', className)}>
            <i className={cls('fab text-xl', icone)} aria-hidden="true" />
        </a>
    );
}

type Modo = 'select' | 'login' | 'register' | 'guest';

function Marcacao({
    slug, casa, gradiente, categorias, servicos, profissionais, datas, clienteInicial,
    escolhidos, porEscolhidos, profissional, porProfissional, avisar, aoFechar,
}: {
    slug: string;
    casa: Props['casa'];
    gradiente: string;
    categorias: Props['categorias'];
    servicos: Servico[];
    profissionais: Profissional[];
    datas: string[];
    clienteInicial: Cliente | null;
    escolhidos: number[];
    porEscolhidos: (v: number[]) => void;
    profissional: number | null;
    porProfissional: (v: number | null) => void;
    avisar: (a: { tipo: 'ok' | 'erro'; texto: string }) => void;
    aoFechar: () => void;
}) {
    const cor = casa.cor;
    const base = `/${encodeURIComponent(slug)}`;
    const [passo, porPasso] = useState(1);
    const [categoria, porCategoria] = useState<number | null>(null);
    const [data, porData] = useState<string | null>(datas[0] ?? null);
    const [hora, porHora] = useState<string | null>(null);
    const [cliente, porCliente] = useState<Cliente | null>(clienteInicial);
    const [modo, porModo] = useState<Modo>('select');
    const [entrada, porEntrada] = useState({ telefone: '', password: '' });
    const [conta, porConta] = useState({ nome: '', telefone: '', email: '', password: '', password_confirmation: '' });
    const [convidada, porConvidada] = useState({ nome: '', telefone: '', email: '' });
    const [notas, porNotas] = useState('');
    const [feita, porFeita] = useState<{ numero: string; estado: string; total: number } | null>(null);

    const lista = categoria === null ? servicos : servicos.filter((s) => s.categoria_id === categoria);
    const escolhidosDados = useMemo(() => servicos.filter((s) => escolhidos.includes(s.id)), [servicos, escolhidos]);
    const total = escolhidosDados.reduce((s, x) => s + x.preco, 0);
    const pessoa = profissionais.find((p) => p.id === profissional) ?? null;

    const horarios = useQuery({
        queryKey: ['salao-publico', slug, 'horarios', profissional, data, [...escolhidos].sort()],
        queryFn: () => publico.ler<{ horarios: string[] }>(`${base}/horarios`, {
            profissional: profissional ?? undefined, data: data ?? undefined, ...Object.fromEntries(escolhidos.map((id, i) => [`servicos[${i}]`, id])),
        }),
        enabled: passo === 2 && profissional !== null && data !== null && escolhidos.length > 0,
    });

    const erroDe = (e: unknown) => (e instanceof ErroDaApi ? (Object.values(e.erros)[0]?.[0] ?? e.message) : e instanceof Error ? e.message : null);

    const entrar = useMutation({
        mutationFn: () => publico.criar<{ cliente: Cliente; message: string }>(`${base}/entrar`, entrada),
        onSuccess: (r) => { porCliente(r.cliente); avisar({ tipo: 'ok', texto: r.message }); },
    });
    const registar = useMutation({
        mutationFn: () => publico.criar<{ cliente: Cliente; message: string }>(`${base}/registar`, conta),
        onSuccess: (r) => { porCliente(r.cliente); avisar({ tipo: 'ok', texto: r.message }); },
    });
    const sair = useMutation({
        mutationFn: () => publico.criar(`${base}/sair`, {}),
        onSuccess: () => { porCliente(null); porModo('select'); },
    });
    const marcar = useMutation({
        mutationFn: () => publico.criar<{ numero: string; estado: string; total: number }>(`${base}/marcar`, {
            servicos: escolhidos, profissional, data, hora, notas,
            ...(cliente ? { email: cliente.email } : convidada),
        }),
        onSuccess: (r) => { porFeita(r); porPasso(4); },
        onError: (e) => {
            // A hora foi-se entretanto: volta aos horários, que se recarregam.
            if (e instanceof ErroDaApi && e.erros.hora) { porHora(null); porPasso(2); void horarios.refetch(); }
            avisar({ tipo: 'erro', texto: erroDe(e) ?? t('Erro ao processar agendamento. Tente novamente.') });
        },
    });

    const alternar = (id: number) => { porEscolhidos(escolhidos.includes(id) ? escolhidos.filter((x) => x !== id) : [...escolhidos, id]); porHora(null); };

    const seguinte = () => {
        if (passo === 1 && escolhidos.length === 0) { avisar({ tipo: 'erro', texto: t('Selecione pelo menos um serviço') }); return; }
        if (passo === 2 && !profissional) { avisar({ tipo: 'erro', texto: t('Selecione um profissional') }); return; }
        if (passo === 2 && (!data || !hora)) { avisar({ tipo: 'erro', texto: t('Selecione data e horário') }); return; }
        porPasso(passo + 1);
    };

    const dia = (iso: string, opcoes: Intl.DateTimeFormatOptions) => new Date(`${iso}T12:00:00`).toLocaleDateString(etiquetaIntl(), opcoes);
    const campo = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm transition focus:border-transparent focus:outline-none focus:ring-2';
    const anel = { ['--tw-ring-color' as string]: cor } as CSSProperties;
    const gradBtn = { background: gradiente };
    const estadoDaMarcacao = (e: string) => e === 'completed' ? ['bg-green-100 text-green-700', t('Concluído')] : e === 'cancelled' ? ['bg-red-100 text-red-700', t('Cancelado')] : ['bg-blue-100 text-blue-700', t('Agendado')];

    return (
        <div className="fixed inset-0 z-[100] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="titulo-marcacao">
            <div className="animate-fade-in fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={aoFechar} />
            <div className="relative flex min-h-screen items-center justify-center p-4">
                <div className="animate-scale-in relative max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div className="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 px-6 py-4" style={gradBtn}>
                        <div className="flex items-center gap-3">
                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20"><i className="fas fa-calendar-check icon-float text-lg text-white" aria-hidden="true" /></span>
                            <div>
                                <h3 id="titulo-marcacao" className="text-lg font-bold text-white">{t('Agendar Serviço')}</h3>
                                <p className="text-xs text-white/70">{casa.nome || t('Salão')}</p>
                            </div>
                        </div>
                        <button type="button" onClick={aoFechar} aria-label={t('Fechar')} className="flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-white transition hover:rotate-90 hover:bg-white/30"><i className="fas fa-times" aria-hidden="true" /></button>
                    </div>

                    <div className="max-h-[calc(90vh-80px)] overflow-y-auto p-6">
                        <ol className="mb-8 flex items-center justify-center" aria-label={t('Passos')}>
                            {[t('Serviços'), t('Data/Hora'), t('Dados'), t('Confirmação')].map((rotulo, i) => {
                                const n = i + 1;
                                return (
                                    <li key={n} className="flex items-center">
                                        <button type="button" disabled={passo < n || passo === 4} onClick={() => porPasso(n)} aria-current={passo === n ? 'step' : undefined}
                                            className={cls('flex flex-col items-center', passo >= n ? 'cursor-pointer' : 'cursor-not-allowed')}>
                                            <span className={cls('flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold transition-all duration-300', passo >= n ? 'text-white shadow-lg' : 'bg-gray-200 text-gray-400', passo === n && 'scale-110')}
                                                style={passo >= n ? gradBtn : undefined}>
                                                {passo > n ? <i className="fas fa-check animate-scale-in" aria-hidden="true" /> : n}
                                            </span>
                                            <span className={cls('mt-1 whitespace-nowrap text-xs', passo >= n ? 'font-medium text-gray-700' : 'text-gray-400')}>{rotulo}</span>
                                        </button>
                                        {n < 4 && <span className="mx-1 h-0.5 w-8 bg-gray-200 transition-colors duration-500 sm:mx-2 sm:w-12" style={passo > n ? { background: cor } : undefined} />}
                                    </li>
                                );
                            })}
                        </ol>

                        <div key={passo} className="animate-fade-in">
                            {passo === 1 && (
                                <div>
                                    <h2 className="mb-4 flex items-center gap-2 text-xl font-bold text-gray-900"><i className="fas fa-spa" style={{ color: cor }} aria-hidden="true" />{t('Escolha os Serviços')}</h2>
                                    {categorias.length > 0 && (
                                        <div className="mb-4 flex flex-wrap gap-2">
                                            <Pilula pequena activa={categoria === null} fundo={cor} onClick={() => porCategoria(null)}>{t('Todos')}</Pilula>
                                            {categorias.map((c) => <Pilula pequena key={c.id} activa={categoria === c.id} fundo={c.cor || cor} onClick={() => porCategoria(c.id)}>{c.nome}</Pilula>)}
                                        </div>
                                    )}
                                    <div className="max-h-80 space-y-2 overflow-y-auto pr-2" role="group" aria-label={t('Escolha os Serviços')}>
                                        {lista.length === 0 ? (
                                            <div className="py-8 text-center"><i className="fas fa-spa mb-2 text-4xl text-gray-200" aria-hidden="true" /><p className="text-sm text-gray-500">{t('Nenhum serviço disponível')}</p></div>
                                        ) : lista.map((s) => {
                                            const sim = escolhidos.includes(s.id);
                                            return (
                                                <button key={s.id} type="button" role="checkbox" aria-checked={sim} onClick={() => alternar(s.id)}
                                                    className={cls('flex w-full items-center justify-between rounded-xl border-2 p-3 text-left transition-all', sim ? '' : 'border-gray-200 hover:border-gray-300')}
                                                    style={sim ? { borderColor: cor, backgroundColor: `${cor}10` } : undefined}>
                                                    <span className="flex items-center gap-3">
                                                        <span className={cls('flex h-5 w-5 items-center justify-center rounded-full border-2 transition', sim ? '' : 'border-gray-300')} style={sim ? { backgroundColor: cor, borderColor: cor } : undefined}>
                                                            {sim && <i className="fas fa-check animate-scale-in text-xs text-white" aria-hidden="true" />}
                                                        </span>
                                                        <span>
                                                            <span className="block text-sm font-medium text-gray-900">{s.nome}</span>
                                                            <span className="block text-xs text-gray-500"><i className="fas fa-clock mr-1" aria-hidden="true" />{s.duracao_texto}</span>
                                                        </span>
                                                    </span>
                                                    <span className="text-sm font-bold tabular-nums" style={{ color: cor }}>{kzInteiro(s.preco)} Kz</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {escolhidos.length > 0 && (
                                        <div className="animate-fade-in mt-4 flex items-center justify-between border-t border-gray-100 pt-4">
                                            <div>
                                                <p className="text-sm text-gray-500">{t(':n serviço(s)', { n: escolhidos.length })}</p>
                                                <p className="text-xl font-bold tabular-nums" style={{ color: cor }}>{kzInteiro(total)} Kz</p>
                                            </div>
                                            <button type="button" onClick={seguinte} className="group rounded-xl px-6 py-3 font-bold text-white shadow-lg transition hover:shadow-xl" style={gradBtn}>
                                                {t('Continuar')} <i className="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                                            </button>
                                        </div>
                                    )}
                                </div>
                            )}

                            {passo === 2 && (
                                <>
                                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                        <div>
                                            <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-gray-700"><i className="fas fa-user" style={{ color: cor }} aria-hidden="true" />{t('Profissional')}</h3>
                                            <div className="max-h-48 space-y-2 overflow-y-auto pr-2" role="radiogroup" aria-label={t('Profissional')}>
                                                {profissionais.map((p) => {
                                                    const sim = profissional === p.id;
                                                    return (
                                                        <button key={p.id} type="button" role="radio" aria-checked={sim} onClick={() => { porProfissional(p.id); porHora(null); }}
                                                            className={cls('flex w-full items-center gap-3 rounded-xl border-2 p-3 text-left transition-all', sim ? '' : 'border-gray-200 hover:border-gray-300')}
                                                            style={sim ? { borderColor: cor, backgroundColor: `${cor}10` } : undefined}>
                                                            {p.foto ? <img src={p.foto} alt="" className="h-10 w-10 rounded-full object-cover" /> : (
                                                                <span className="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold text-white" style={gradBtn}>{p.nome.slice(0, 1).toUpperCase()}</span>
                                                            )}
                                                            <span className="flex-1">
                                                                <span className="block text-sm font-medium text-gray-900">{p.nome}</span>
                                                                {p.especialidade && <span className="block text-xs text-gray-500">{p.especialidade}</span>}
                                                            </span>
                                                            {sim && <i className="fas fa-circle-check animate-scale-in" style={{ color: cor }} aria-hidden="true" />}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                        <div>
                                            <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-gray-700"><i className="fas fa-calendar-alt" style={{ color: cor }} aria-hidden="true" />{t('Data e Horário')}</h3>
                                            <div className="mb-4 flex gap-2 overflow-x-auto pb-2">
                                                {datas.slice(0, 7).map((d) => {
                                                    const sim = data === d;
                                                    return (
                                                        <button key={d} type="button" aria-pressed={sim} onClick={() => { porData(d); porHora(null); }}
                                                            className={cls('min-w-[60px] shrink-0 rounded-lg border-2 px-3 py-2 text-center transition', sim ? 'border-transparent text-white' : 'border-gray-200 hover:border-gray-300')}
                                                            style={sim ? gradBtn : undefined}>
                                                            <span className={cls('block text-xs capitalize', sim ? 'text-white/80' : 'text-gray-500')}>{dia(d, { weekday: 'short' })}</span>
                                                            <span className="block text-sm font-bold">{d.slice(8, 10)}</span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                            {!profissional ? (
                                                <div className="rounded-xl bg-gray-50 py-4 text-center"><p className="text-sm text-gray-500">{t('Selecione um profissional')}</p></div>
                                            ) : horarios.isPending ? (
                                                <div className="grid grid-cols-4 gap-1.5">{Array.from({ length: 8 }, (_, i) => <span key={i} className="h-8 animate-pulse rounded-lg bg-gray-100" />)}</div>
                                            ) : horarios.isError ? (
                                                <div role="alert" className="rounded-xl bg-red-50 py-4 text-center text-sm text-red-600">{erroDe(horarios.error)}</div>
                                            ) : horarios.data.horarios.length === 0 ? (
                                                <div className="rounded-xl bg-gray-50 py-4 text-center"><p className="text-sm text-gray-500">{t('Sem horários disponíveis')}</p></div>
                                            ) : (
                                                <div className="grid max-h-32 grid-cols-4 gap-1.5 overflow-y-auto" role="radiogroup" aria-label={t('Horário')}>
                                                    {horarios.data.horarios.map((h) => (
                                                        <button key={h} type="button" role="radio" aria-checked={hora === h} onClick={() => porHora(h)}
                                                            className={cls('rounded-lg border py-2 text-center text-xs font-medium tabular-nums transition', hora === h ? 'border-transparent text-white' : 'border-gray-200 text-gray-700 hover:border-gray-300')}
                                                            style={hora === h ? gradBtn : undefined}>
                                                            {h}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <div className="mt-4 flex items-center justify-between border-t border-gray-100 pt-4">
                                        <button type="button" onClick={() => porPasso(1)} className="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200"><i className="fas fa-arrow-left mr-1" aria-hidden="true" /> {t('Voltar')}</button>
                                        {profissional && data && hora && (
                                            <button type="button" onClick={seguinte} className="animate-scale-in group rounded-xl px-6 py-3 font-bold text-white shadow-lg transition hover:shadow-xl" style={gradBtn}>
                                                {t('Continuar')} <i className="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                                            </button>
                                        )}
                                    </div>
                                </>
                            )}

                            {passo === 3 && (
                                <div>
                                    <div className="mb-4 rounded-xl p-4 text-white" style={gradBtn}>
                                        <div className="grid grid-cols-4 gap-2 text-xs">
                                            <div><p className="text-white/60">{t('Serviços')}</p><p className="font-medium">{escolhidos.length}</p></div>
                                            <div><p className="text-white/60">{t('Profissional')}</p><p className="truncate font-medium">{pessoa?.nome ?? '-'}</p></div>
                                            <div><p className="text-white/60">{t('Data')}</p><p className="font-medium">{data ? `${data.slice(8, 10)}/${data.slice(5, 7)}` : '-'}</p></div>
                                            <div><p className="text-white/60">{t('Hora')}</p><p className="font-medium">{hora}</p></div>
                                        </div>
                                        <div className="mt-2 flex items-center justify-between border-t border-white/20 pt-2">
                                            <span className="text-sm">{t('Total')}</span>
                                            <span className="text-lg font-bold tabular-nums">{kzInteiro(total)} Kz</span>
                                        </div>
                                    </div>

                                    {cliente ? (
                                        <div className="animate-fade-in">
                                            <div className="mb-4 rounded-xl border border-green-200 bg-green-50 p-4">
                                                <div className="flex items-center justify-between">
                                                    <div className="flex items-center gap-3">
                                                        <span className="flex h-10 w-10 items-center justify-center rounded-full font-bold text-white" style={gradBtn}>{cliente.nome.slice(0, 1).toUpperCase()}</span>
                                                        <div><p className="font-bold text-gray-900">{cliente.nome}</p><p className="text-xs text-gray-500">{cliente.telefone}</p></div>
                                                    </div>
                                                    <button type="button" onClick={() => sair.mutate()} className="text-xs text-gray-500 transition hover:text-red-500"><i className="fas fa-sign-out-alt mr-1" aria-hidden="true" /> {t('Trocar')}</button>
                                                </div>
                                                {cliente.vip && (
                                                    <div className="mt-2 flex items-center gap-2 text-xs">
                                                        <span className="rounded-full bg-yellow-100 px-2 py-1 text-yellow-800"><i className="fas fa-crown mr-1" aria-hidden="true" /> {t('Cliente VIP')}</span>
                                                        <span className="text-gray-500">{t(':n pontos', { n: cliente.pontos })}</span>
                                                    </div>
                                                )}
                                            </div>
                                            {cliente.marcacoes.length > 0 && (
                                                <div className="mb-4">
                                                    <p className="mb-2 text-xs font-medium text-gray-500">{t('Seus últimos agendamentos')}</p>
                                                    <div className="max-h-24 space-y-1 overflow-y-auto">
                                                        {cliente.marcacoes.map((m, i) => {
                                                            const [classe, rotulo] = estadoDaMarcacao(m.estado);
                                                            return (
                                                                <div key={i} className="flex items-center justify-between rounded-lg bg-gray-50 p-2 text-xs">
                                                                    <span>{m.data ? `${m.data.slice(8, 10)}/${m.data.slice(5, 7)}/${m.data.slice(0, 4)}` : ''} {m.hora}</span>
                                                                    <span className={cls('rounded-full px-2 py-0.5 text-xs', classe)}>{rotulo}</span>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            )}
                                            <label className="block">
                                                <span className="mb-1 block text-xs font-medium text-gray-700">{t('Observações (opcional)')}</span>
                                                <textarea rows={2} value={notas} onChange={(e) => porNotas(e.target.value)} className={campo} style={anel} placeholder={t('Alguma informação adicional...')} />
                                            </label>
                                        </div>
                                    ) : modo === 'select' ? (
                                        <div className="space-y-3">
                                            <p className="mb-4 text-center text-sm text-gray-600">{t('Como deseja continuar?')}</p>
                                            {([
                                                ['login', 'fa-user', 'bg-blue-100 text-blue-600', t('Já tenho conta'), t('Entrar com meu telefone')],
                                                ['register', 'fa-user-plus', 'bg-green-100 text-green-600', t('Criar conta'), t('Registar e acumular pontos')],
                                                ['guest', 'fa-bolt', 'bg-gray-100 text-gray-600', t('Reserva rápida'), t('Continuar sem conta')],
                                            ] as const).map(([m, icone, tom, titulo, nota], i) => (
                                                <button key={m} type="button" onClick={() => porModo(m)} style={{ ['--i' as string]: i } as CSSProperties}
                                                    className="entra group flex w-full items-center gap-4 rounded-xl border-2 border-gray-200 p-4 transition hover:border-gray-300 hover:bg-gray-50">
                                                    <span className={cls('flex h-12 w-12 items-center justify-center rounded-full', tom)}><i className={cls('fas text-lg', icone)} aria-hidden="true" /></span>
                                                    <span className="flex-1 text-left"><span className="block font-bold text-gray-900">{titulo}</span><span className="block text-xs text-gray-500">{nota}</span></span>
                                                    <i className="fas fa-chevron-right text-gray-400 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                                                </button>
                                            ))}
                                        </div>
                                    ) : modo === 'login' ? (
                                        <form className="animate-fade-in space-y-3" onSubmit={(e) => { e.preventDefault(); entrar.mutate(); }}>
                                            <Titulo texto={t('Entrar na conta')} aoVoltar={() => porModo('select')} />
                                            {entrar.error && <Erro texto={erroDe(entrar.error)} />}
                                            <Linha rotulo={t('Telefone *')}><input type="tel" autoComplete="tel" required value={entrada.telefone} onChange={(e) => porEntrada({ ...entrada, telefone: e.target.value })} className={campo} style={anel} placeholder="+244 923 456 789" /></Linha>
                                            <Linha rotulo={t('Password *')}><input type="password" autoComplete="current-password" required value={entrada.password} onChange={(e) => porEntrada({ ...entrada, password: e.target.value })} className={campo} style={anel} /></Linha>
                                            <button type="submit" disabled={entrar.isPending} className="w-full rounded-xl py-3 font-bold text-white shadow-lg transition hover:shadow-xl disabled:opacity-60" style={gradBtn}>
                                                <i className={cls('fas mr-2', entrar.isPending ? 'fa-spinner fa-spin' : 'fa-sign-in-alt')} aria-hidden="true" /> {t('Entrar')}
                                            </button>
                                            <p className="text-center text-xs text-gray-500">{t('Não tem conta?')} <button type="button" onClick={() => porModo('register')} className="hover:underline" style={{ color: cor }}>{t('Criar agora')}</button></p>
                                        </form>
                                    ) : modo === 'register' ? (
                                        <form className="animate-fade-in space-y-3" onSubmit={(e) => { e.preventDefault(); registar.mutate(); }}>
                                            <Titulo texto={t('Criar conta')} aoVoltar={() => porModo('select')} />
                                            {registar.error && <Erro texto={erroDe(registar.error)} />}
                                            <Linha rotulo={t('Nome Completo *')}><input autoComplete="name" required value={conta.nome} onChange={(e) => porConta({ ...conta, nome: e.target.value })} className={campo} style={anel} placeholder={t('Seu nome')} /></Linha>
                                            <Linha rotulo={t('Telefone *')}><input type="tel" autoComplete="tel" required value={conta.telefone} onChange={(e) => porConta({ ...conta, telefone: e.target.value })} className={campo} style={anel} placeholder="+244 923 456 789" /></Linha>
                                            <Linha rotulo={t('Email (opcional)')}><input type="email" autoComplete="email" value={conta.email} onChange={(e) => porConta({ ...conta, email: e.target.value })} className={campo} style={anel} placeholder="seu@email.com" /></Linha>
                                            <div className="grid grid-cols-2 gap-2">
                                                <Linha rotulo={t('Password *')}><input type="password" autoComplete="new-password" required value={conta.password} onChange={(e) => porConta({ ...conta, password: e.target.value })} className={campo} style={anel} placeholder={t('Min 4 caracteres')} /></Linha>
                                                <Linha rotulo={t('Confirmar')}><input type="password" autoComplete="new-password" required value={conta.password_confirmation} onChange={(e) => porConta({ ...conta, password_confirmation: e.target.value })} className={campo} style={anel} placeholder={t('Repetir')} /></Linha>
                                            </div>
                                            <button type="submit" disabled={registar.isPending} className="w-full rounded-xl py-3 font-bold text-white shadow-lg transition hover:shadow-xl disabled:opacity-60" style={gradBtn}>
                                                <i className={cls('fas mr-2', registar.isPending ? 'fa-spinner fa-spin' : 'fa-user-plus')} aria-hidden="true" /> {t('Criar Conta e Continuar')}
                                            </button>
                                            <p className="text-center text-xs text-gray-500">{t('Já tem conta?')} <button type="button" onClick={() => porModo('login')} className="hover:underline" style={{ color: cor }}>{t('Entrar')}</button></p>
                                        </form>
                                    ) : (
                                        <div className="animate-fade-in space-y-3">
                                            <Titulo texto={t('Reserva Rápida')} aoVoltar={() => porModo('select')} />
                                            <p className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs text-blue-600"><i className="fas fa-info-circle mr-1" aria-hidden="true" /> {t('Crie uma conta para acumular pontos e ter acesso ao histórico de agendamentos.')}</p>
                                            <Linha rotulo={t('Nome Completo *')} erro={marcar.error instanceof ErroDaApi ? marcar.error.erros.nome?.[0] : undefined}>
                                                <input autoComplete="name" value={convidada.nome} onChange={(e) => porConvidada({ ...convidada, nome: e.target.value })} className={campo} style={anel} placeholder={t('Seu nome')} />
                                            </Linha>
                                            <Linha rotulo={t('Telefone/WhatsApp *')} erro={marcar.error instanceof ErroDaApi ? marcar.error.erros.telefone?.[0] : undefined}>
                                                <input type="tel" autoComplete="tel" value={convidada.telefone} onChange={(e) => porConvidada({ ...convidada, telefone: e.target.value })} className={campo} style={anel} placeholder="+244 923 456 789" />
                                            </Linha>
                                            <Linha rotulo={t('Email (opcional)')} erro={marcar.error instanceof ErroDaApi ? marcar.error.erros.email?.[0] : undefined}>
                                                <input type="email" autoComplete="email" value={convidada.email} onChange={(e) => porConvidada({ ...convidada, email: e.target.value })} className={campo} style={anel} placeholder="seu@email.com" />
                                            </Linha>
                                            <Linha rotulo={t('Observações (opcional)')}>
                                                <textarea rows={2} value={notas} onChange={(e) => porNotas(e.target.value)} className={campo} style={anel} placeholder={t('Alguma informação adicional...')} />
                                            </Linha>
                                        </div>
                                    )}

                                    <div className="mt-4 flex justify-between border-t border-gray-100 pt-4">
                                        <button type="button" onClick={() => porPasso(2)} className="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200"><i className="fas fa-arrow-left mr-1" aria-hidden="true" /> {t('Voltar')}</button>
                                        {(cliente || modo === 'guest') && (
                                            <button type="button" onClick={() => marcar.mutate()} disabled={marcar.isPending} className="animate-scale-in rounded-xl px-6 py-3 font-bold text-white shadow-lg transition hover:shadow-xl disabled:opacity-60" style={gradBtn}>
                                                <i className={cls('fas mr-2', marcar.isPending ? 'fa-spinner fa-spin' : 'fa-check')} aria-hidden="true" /> {t('Confirmar')}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            )}

                            {passo === 4 && feita && (
                                <div className="text-center">
                                    <span className="animate-scale-in mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full text-white" style={gradBtn}><i className="fas fa-check text-3xl" aria-hidden="true" /></span>
                                    <h2 className="mb-2 text-2xl font-bold text-gray-900">{t('Agendamento Confirmado!')}</h2>
                                    <p className="mb-4 text-sm text-gray-500">{feita.estado === 'confirmed' ? t('Seu agendamento foi confirmado.') : t('O salão entrará em contato para confirmar.')}</p>
                                    <div className="mb-4 inline-block rounded-lg bg-gray-100 px-4 py-2">
                                        <p className="text-xs text-gray-500">{t('Número')}</p>
                                        <p className="text-lg font-bold" style={{ color: cor }}>{feita.numero}</p>
                                    </div>
                                    <div className="mb-4 rounded-xl bg-gray-50 p-4 text-left text-sm">
                                        <div className="grid grid-cols-2 gap-3">
                                            <div><p className="text-xs text-gray-500">{t('Data')}</p><p className="font-medium">{data ? dia(data, { day: 'numeric', month: 'short' }) : '-'}</p></div>
                                            <div><p className="text-xs text-gray-500">{t('Horário')}</p><p className="font-medium">{hora}</p></div>
                                            <div><p className="text-xs text-gray-500">{t('Profissional')}</p><p className="font-medium">{pessoa?.nome ?? '-'}</p></div>
                                            <div><p className="text-xs text-gray-500">{t('Total')}</p><p className="font-bold tabular-nums" style={{ color: cor }}>{kzInteiro(feita.total)} Kz</p></div>
                                        </div>
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        {casa.whatsapp && (
                                            <a href={`https://wa.me/${casa.whatsapp}?text=${encodeURIComponent(t('Olá! Acabei de fazer um agendamento (:numero)', { numero: feita.numero }))}`} target="_blank" rel="noopener noreferrer"
                                                className="flex w-full items-center justify-center gap-2 rounded-xl bg-green-500 px-4 py-3 font-bold text-white transition hover:bg-green-600">
                                                <i className="fab fa-whatsapp" aria-hidden="true" /> {t('Falar no WhatsApp')}
                                            </a>
                                        )}
                                        <button type="button" onClick={() => window.location.reload()} className="w-full rounded-xl bg-gray-100 px-4 py-3 font-bold text-gray-700 transition hover:bg-gray-200">{t('Fechar')}</button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Titulo({ texto, aoVoltar }: { texto: string; aoVoltar: () => void }) {
    return (
        <div className="mb-2 flex items-center justify-between">
            <h3 className="font-bold text-gray-900">{texto}</h3>
            <button type="button" onClick={aoVoltar} className="text-xs text-gray-500 hover:text-gray-700"><i className="fas fa-arrow-left mr-1" aria-hidden="true" /> {t('Voltar')}</button>
        </div>
    );
}

function Linha({ rotulo, erro, children }: { rotulo: string; erro?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-medium text-gray-700">{rotulo}</span>
            {children}
            {erro && <span role="alert" className="text-xs text-red-500">{erro}</span>}
        </label>
    );
}

function Erro({ texto }: { texto: string | null }) {
    if (!texto) return null;

    return <p role="alert" className="animate-fade-in rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-600"><i className="fas fa-exclamation-circle mr-1" aria-hidden="true" /> {texto}</p>;
}
