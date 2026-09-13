import { useState, type FormEvent, type ReactNode } from 'react';

import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

/**
 * AS PEÇAS DAS PÁGINAS DE ENTRADA — entrar, recuperar a senha, o convite, a
 * conta bloqueada.
 *
 * Os formulários são formulários de verdade: POST para os controladores de
 * sempre, com o token escondido. Enter envia, o browser oferece guardar a
 * senha, e os erros voltam do servidor na sessão (`erros`, `antigos`) — o
 * React não refaz a validação nem a autenticação, só desenha.
 */

export type Recado = { tipo: 'ok' | 'erro' | 'aviso' | 'info'; texto: string };

export type PropsDeEntrada = {
    csrf: string;
    site: string;
    logo?: string | null;
    nome?: string;
    erros?: Record<string, string>;
    antigos?: Record<string, unknown>;
    recados?: Recado[];
    contacto?: { email: string; telefone: string };
};

/** O fundo: três manchas de cor a flutuar devagar, por trás do cartão. */
function Fundo({ tom }: { tom: 'azul' | 'vermelho' | 'verde' | 'cinza' }) {
    const cores = {
        azul: ['bg-blue-300', 'bg-purple-300', 'bg-pink-300'],
        vermelho: ['bg-red-300', 'bg-orange-300', 'bg-yellow-200'],
        verde: ['bg-emerald-300', 'bg-blue-300', 'bg-purple-200'],
        cinza: ['bg-gray-300', 'bg-blue-200', 'bg-purple-200'],
    }[tom];

    return (
        <div className="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
            <div className={cls('absolute -left-24 -top-24 h-96 w-96 rounded-full opacity-30 blur-3xl', cores[0])} style={{ animation: 'float 9s ease-in-out infinite' }} />
            <div className={cls('absolute -right-24 top-1/3 h-80 w-80 rounded-full opacity-30 blur-3xl', cores[1])} style={{ animation: 'float 11s ease-in-out infinite reverse' }} />
            <div className={cls('absolute -bottom-32 left-1/3 h-96 w-96 rounded-full opacity-20 blur-3xl', cores[2])} style={{ animation: 'float 13s ease-in-out infinite' }} />
        </div>
    );
}

/** A marca por cima do cartão: o logótipo (ou a coroa) e, sem logótipo, o nome. */
export function Marca({ logo, nome, site }: { logo?: string | null; nome?: string; site: string }) {
    return (
        <div className="mb-8 text-center">
            <a href={site} className={cls('group inline-flex items-center justify-center rounded-xl', FOCO)}>
                {logo ? (
                    <img src={logo} alt={nome} className="h-16 w-auto object-contain transition-transform duration-300 group-hover:scale-105" />
                ) : (
                    <>
                        <span className="mr-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-purple-600 shadow-lg transition-transform duration-300 group-hover:rotate-6">
                            <i className="fas fa-chart-line text-2xl text-white" aria-hidden="true" />
                        </span>
                        <span className="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-3xl font-bold text-transparent">{nome}</span>
                    </>
                )}
            </a>
        </div>
    );
}

/**
 * A MOLDURA: fundo, marca, cartão e rodapé.
 *
 * `cabeca` é a faixa colorida de cima (convites, conta bloqueada); sem ela o
 * cartão abre com o ícone e o título ao centro, como o login de sempre.
 */
export function Moldura({
    p, icone, titulo, subtitulo, tom = 'azul', largura = 'max-w-md', cabeca, rodape, children,
}: {
    p: PropsDeEntrada;
    icone?: string;
    titulo: ReactNode;
    subtitulo?: ReactNode;
    tom?: 'azul' | 'vermelho' | 'verde' | 'cinza';
    largura?: string;
    cabeca?: string;
    rodape?: ReactNode;
    children: ReactNode;
}) {
    const ano = new Date().getFullYear();

    return (
        <div className="relative flex min-h-screen items-center justify-center p-4">
            <Fundo tom={tom} />

            <div className={cls('w-full', largura)}>
                <div className="entra"><Marca logo={p.logo} nome={p.nome} site={p.site} /></div>

                <div className="animate-scale-in overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">
                    {cabeca ? (
                        <div className={cls('bg-gradient-to-r p-8 text-center text-white', cabeca)}>
                            {icone && (
                                <span className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm">
                                    <i className={cls('fas icon-float text-4xl', icone)} aria-hidden="true" />
                                </span>
                            )}
                            <h1 className="text-2xl font-bold sm:text-3xl">{titulo}</h1>
                            {subtitulo && <p className="mt-2 text-sm text-white/80">{subtitulo}</p>}
                        </div>
                    ) : (
                        <div className="px-8 pt-8 text-center">
                            {icone && (
                                <span className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-purple-100">
                                    <i className={cls('fas icon-float text-2xl text-blue-600', icone)} aria-hidden="true" />
                                </span>
                            )}
                            <h1 className="text-3xl font-bold text-gray-900">{titulo}</h1>
                            {subtitulo && <p className="mt-2 text-gray-600">{subtitulo}</p>}
                        </div>
                    )}

                    <div className="p-8">
                        <Recados recados={p.recados} />
                        {children}
                    </div>
                </div>

                {rodape && <div className="entra mt-6 text-center" style={{ ['--i' as string]: 3 }}>{rodape}</div>}

                <p className="mt-6 text-center text-xs text-gray-500">&copy; {ano} {p.nome}. {t('Todos os direitos reservados.')}</p>
            </div>
        </div>
    );
}

const ESTILO_DO_RECADO: Record<Recado['tipo'], [string, string]> = {
    ok: ['border-green-200 bg-green-50 text-green-800', 'fa-circle-check text-green-600'],
    erro: ['border-red-200 bg-red-50 text-red-800', 'fa-circle-exclamation text-red-600'],
    aviso: ['border-amber-200 bg-amber-50 text-amber-800', 'fa-triangle-exclamation text-amber-600'],
    info: ['border-blue-200 bg-blue-50 text-blue-800', 'fa-circle-info text-blue-600'],
};

/** Os recados que o redirect deixou na sessão, no topo do cartão. */
export function Recados({ recados = [] }: { recados?: Recado[] }) {
    if (!recados.length) return null;

    return (
        <div className="mb-6 space-y-3">
            {recados.map((r, i) => (
                <div key={i} role={r.tipo === 'erro' ? 'alert' : 'status'} className={cls('animate-fade-in flex items-start gap-3 rounded-xl border p-4 text-sm', ESTILO_DO_RECADO[r.tipo][0])}>
                    <i className={cls('fas mt-0.5', ESTILO_DO_RECADO[r.tipo][1])} aria-hidden="true" />
                    <span>{r.texto}</span>
                </div>
            ))}
        </div>
    );
}

/**
 * UM FORMULÁRIO DE VERDADE, com o token escondido.
 *
 * Ao enviar, o botão passa a «a trabalhar» — mas o envio segue, não se
 * impede: quem trata do pedido é o controlador, e a página seguinte chega do
 * servidor. Sem isto, numa rede lenta, carregava-se duas vezes.
 */
export function Formulario({ acao, csrf, children, className, escondidos = {} }: {
    acao: string;
    csrf: string;
    children: (aEnviar: boolean) => ReactNode;
    className?: string;
    escondidos?: Record<string, string>;
}) {
    const [aEnviar, porAEnviar] = useState(false);

    const enviar = (e: FormEvent<HTMLFormElement>) => {
        if (aEnviar) { e.preventDefault(); return; }
        porAEnviar(true);
    };

    return (
        <form method="POST" action={acao} onSubmit={enviar} className={className}>
            <input type="hidden" name="_token" value={csrf} />
            {Object.entries(escondidos).map(([n, v]) => <input key={n} type="hidden" name={n} value={v} />)}
            {children(aEnviar)}
        </form>
    );
}

/** Um campo com ícone, erro por baixo, e o olho para ver a senha. */
export function CampoDeEntrada({
    nome, rotulo, icone, tipo = 'text', valor, erro, dica, autoComplete, obrigatorio = true, focar = false, minimo,
}: {
    nome: string;
    rotulo: string;
    icone: string;
    tipo?: 'text' | 'email' | 'password' | 'number';
    valor?: string;
    erro?: string;
    dica?: string;
    autoComplete?: string;
    obrigatorio?: boolean;
    focar?: boolean;
    minimo?: number;
}) {
    const [ver, porVer] = useState(false);
    const senha = tipo === 'password';
    const id = `campo-${nome}`;

    return (
        <div>
            <label htmlFor={id} className="mb-2 block text-sm font-semibold text-gray-700">{rotulo}</label>
            <div className="group relative">
                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400 transition group-focus-within:text-blue-600">
                    <i className={cls('fas', icone)} aria-hidden="true" />
                </span>
                <input
                    id={id}
                    name={nome}
                    type={senha && ver ? 'text' : tipo}
                    defaultValue={valor}
                    required={obrigatorio}
                    autoFocus={focar}
                    autoComplete={autoComplete}
                    minLength={minimo}
                    placeholder={dica}
                    aria-invalid={erro ? true : undefined}
                    aria-describedby={erro ? `${id}-erro` : undefined}
                    className={cls(
                        'w-full rounded-xl border-2 py-3 pl-10 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100',
                        senha ? 'pr-11' : 'pr-4',
                        erro ? 'border-red-400 bg-red-50/40' : 'border-gray-200',
                        TRANSICAO,
                    )}
                />
                {senha && (
                    <button type="button" onClick={() => porVer((v) => !v)} aria-label={ver ? t('Esconder a senha') : t('Mostrar a senha')} aria-pressed={ver}
                        className={cls('absolute inset-y-0 right-0 flex items-center rounded-r-xl px-3.5 text-gray-400 hover:text-gray-700', FOCO)}>
                        <i className={cls('fas', ver ? 'fa-eye-slash' : 'fa-eye')} aria-hidden="true" />
                    </button>
                )}
            </div>
            {erro && (
                <p id={`${id}-erro`} role="alert" className="animate-fade-in mt-2 flex items-center gap-1 text-sm text-red-600">
                    <i className="fas fa-circle-exclamation" aria-hidden="true" />{erro}
                </p>
            )}
        </div>
    );
}

/** O botão principal: gradiente, e a girar enquanto o pedido segue. */
export function BotaoDeEnviar({ aEnviar, icone, children, grande = false, cor = 'from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700' }: {
    aEnviar: boolean;
    icone: string;
    children: ReactNode;
    grande?: boolean;
    cor?: string;
}) {
    return (
        <button type="submit" disabled={aEnviar} aria-busy={aEnviar}
            className={cls('btn-press flex w-full items-center justify-center gap-2 bg-gradient-to-r font-semibold text-white shadow-lg hover:shadow-xl disabled:cursor-wait disabled:opacity-80', grande ? 'rounded-2xl py-4 text-lg font-bold' : 'rounded-xl py-3', cor, TRANSICAO, FOCO)}>
            <i className={cls('fas', aEnviar ? 'fa-spinner fa-spin' : icone)} aria-hidden="true" />
            {children}
        </button>
    );
}

export const texto = (v: unknown): string => (typeof v === 'string' ? v : v == null ? '' : String(v));
