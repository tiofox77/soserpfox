import { useEffect, useRef, useState, type ReactNode } from 'react';

import { t } from '@/i18n';

/**
 * OS AVISOS E AS PERGUNTAS DO PWA — sem `alert()`, `confirm()` nem `prompt()`.
 *
 * Os ecrãs em Alpine usavam as caixas do navegador. Num telemóvel instalado
 * elas saem com o endereço do site por título, param a aplicação inteira, e
 * num Android há browsers que as bloqueiam em silêncio depois da segunda — e
 * aí um «Limpar carrinho?» respondia sozinho que não. Aqui são da aplicação:
 * com ícone, com a cor do que se está a decidir, e sem parar o motor.
 *
 * Funcionam por eventos para poderem ser chamados de qualquer lado (também do
 * motor, fora do React): o `<Dialogos />` é montado uma vez, na raiz.
 */

export type TipoDeAviso = 'ok' | 'erro' | 'aviso' | 'info';

export interface AccaoDoAviso {
    rotulo: string;
    icone?: string;
    fazer: () => void;
}

interface Aviso {
    id: number;
    texto: string;
    tipo: TipoDeAviso;
    titulo?: string;
    duracao: number;
    accoes?: AccaoDoAviso[];
}

interface Pergunta {
    id: number;
    titulo: string;
    texto?: string;
    sim: string;
    nao: string;
    perigo: boolean;
    icone: string;
    /** Com campo: devolve o texto escrito (ou null se cancelou). */
    campo?: { valor: string; tipo: 'text' | 'number'; placeholder?: string; inputMode?: 'decimal' | 'numeric' | 'text' };
    responder: (r: boolean | string | null) => void;
}

let proximo = 1;

export function avisar(texto: string, tipo: TipoDeAviso = 'info', opcoes: { titulo?: string; duracao?: number; accoes?: AccaoDoAviso[] } = {}): void {
    const aviso: Aviso = {
        id: proximo++,
        texto,
        tipo,
        titulo: opcoes.titulo,
        duracao: opcoes.duracao ?? (opcoes.accoes?.length ? 9000 : tipo === 'erro' ? 7000 : 4000),
        accoes: opcoes.accoes,
    };
    window.dispatchEvent(new CustomEvent('pwa:aviso', { detail: aviso }));
}

export function confirmar(titulo: string, opcoes: { texto?: string; sim?: string; nao?: string; perigo?: boolean; icone?: string } = {}): Promise<boolean> {
    return new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('pwa:pergunta', {
            detail: {
                id: proximo++,
                titulo,
                texto: opcoes.texto,
                sim: opcoes.sim ?? t('Confirmar'),
                nao: opcoes.nao ?? t('Cancelar'),
                perigo: opcoes.perigo ?? false,
                icone: opcoes.icone ?? (opcoes.perigo ? 'fa-triangle-exclamation' : 'fa-circle-question'),
                responder: (r: boolean | string | null) => resolve(r === true),
            } satisfies Pergunta,
        }));
    });
}

export function perguntarValor(
    titulo: string,
    opcoes: { texto?: string; valor?: string; sim?: string; tipo?: 'text' | 'number'; placeholder?: string; inputMode?: 'decimal' | 'numeric' | 'text'; icone?: string } = {},
): Promise<string | null> {
    return new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('pwa:pergunta', {
            detail: {
                id: proximo++,
                titulo,
                texto: opcoes.texto,
                sim: opcoes.sim ?? t('Confirmar'),
                nao: t('Cancelar'),
                perigo: false,
                icone: opcoes.icone ?? 'fa-pen',
                campo: { valor: opcoes.valor ?? '', tipo: opcoes.tipo ?? 'text', placeholder: opcoes.placeholder, inputMode: opcoes.inputMode },
                responder: (r: boolean | string | null) => resolve(typeof r === 'string' ? r : null),
            } satisfies Pergunta,
        }));
    });
}

const ESTILO: Record<TipoDeAviso, { fundo: string; icone: string }> = {
    ok: { fundo: 'from-emerald-600 to-teal-600', icone: 'fa-circle-check' },
    erro: { fundo: 'from-red-600 to-rose-600', icone: 'fa-circle-exclamation' },
    aviso: { fundo: 'from-amber-500 to-orange-500', icone: 'fa-triangle-exclamation' },
    info: { fundo: 'from-blue-600 to-indigo-600', icone: 'fa-circle-info' },
};

function UmAviso({ aviso, fechar }: { aviso: Aviso; fechar: () => void }) {
    // O `fechar` muda a cada aviso novo que chega; o prazo deste não pode recomeçar por isso.
    const fecharActual = useRef(fechar);
    fecharActual.current = fechar;

    useEffect(() => {
        const id = setTimeout(() => fecharActual.current(), aviso.duracao);

        return () => clearTimeout(id);
    }, [aviso.duracao]);

    const e = ESTILO[aviso.tipo];

    return (
        <div role={aviso.tipo === 'erro' ? 'alert' : 'status'}
             className={`pwa-desce pointer-events-auto rounded-2xl bg-gradient-to-r ${e.fundo} text-white shadow-2xl p-3 flex items-start gap-3`}>
            <span className="w-8 h-8 shrink-0 rounded-xl bg-white/20 flex items-center justify-center">
                <i className={`fas ${e.icone}`} aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1">
                {aviso.titulo && <p className="text-sm font-bold leading-tight">{aviso.titulo}</p>}
                <p className="text-xs leading-snug whitespace-pre-line opacity-95">{aviso.texto}</p>
                {!!aviso.accoes?.length && (
                    <div className="flex flex-wrap gap-2 mt-2">
                        {aviso.accoes.map((a) => (
                            <button key={a.rotulo} type="button" onClick={() => { a.fazer(); fechar(); }}
                                    className="pwa-toque bg-white text-slate-800 px-3 py-1.5 rounded-lg text-xs font-bold">
                                {a.icone && <i className={`fas ${a.icone} mr-1`} aria-hidden="true" />}{a.rotulo}
                            </button>
                        ))}
                    </div>
                )}
            </div>
            <button type="button" onClick={fechar} className="text-white/70 hover:text-white px-1 text-lg leading-none" aria-label={t('Fechar')}>&times;</button>
        </div>
    );
}

function APergunta({ p, fechar }: { p: Pergunta; fechar: () => void }) {
    const [valor, setValor] = useState(p.campo?.valor ?? '');
    const campo = useRef<HTMLInputElement>(null);
    const sim = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        (p.campo ? campo.current : sim.current)?.focus();
        const tecla = (e: KeyboardEvent) => {
            if (e.key === 'Escape') { p.responder(p.campo ? null : false); fechar(); }
        };
        window.addEventListener('keydown', tecla);

        return () => window.removeEventListener('keydown', tecla);
    }, [p, fechar]);

    const responder = (r: boolean) => {
        p.responder(p.campo ? (r ? valor : null) : r);
        fechar();
    };

    return (
        <div className="fixed inset-0 z-[200] flex items-end sm:items-center justify-center p-0 sm:p-4">
            <div className="pwa-fundo absolute inset-0 bg-slate-900/60 backdrop-blur-[2px]" onClick={() => responder(false)} />
            <form role="alertdialog" aria-modal="true" aria-label={p.titulo}
                  onSubmit={(e) => { e.preventDefault(); responder(true); }}
                  className="pwa-sobe relative w-full sm:max-w-sm bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl p-5">
                <div className="flex items-start gap-3">
                    <span className={`w-11 h-11 shrink-0 rounded-2xl flex items-center justify-center text-lg ${p.perigo ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-700'}`}>
                        <i className={`fas ${p.icone}`} aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <h2 className="text-base font-bold text-slate-900 leading-snug">{p.titulo}</h2>
                        {p.texto && <p className="text-sm text-slate-500 mt-1 whitespace-pre-line">{p.texto}</p>}
                    </div>
                </div>

                {p.campo && (
                    <input ref={campo} value={valor} onChange={(e) => setValor(e.target.value)}
                           type={p.campo.tipo} inputMode={p.campo.inputMode} placeholder={p.campo.placeholder}
                           className="mt-4 w-full px-3 py-3 border-2 border-slate-200 rounded-xl text-base focus:border-blue-500 focus:outline-none" />
                )}

                <div className="grid grid-cols-2 gap-2 mt-5">
                    <button type="button" onClick={() => responder(false)}
                            className="pwa-toque py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold">
                        {p.nao}
                    </button>
                    <button ref={sim} type="submit"
                            className={`pwa-toque py-3 rounded-xl text-white text-sm font-bold shadow-lg ${p.perigo ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'}`}>
                        {p.sim}
                    </button>
                </div>
            </form>
        </div>
    );
}

export function Dialogos({ acima = 'top-3' }: { acima?: string }) {
    const [avisos, setAvisos] = useState<Aviso[]>([]);
    const [perguntas, setPerguntas] = useState<Pergunta[]>([]);

    useEffect(() => {
        const aviso = (e: Event) => setAvisos((l) => [...l.slice(-3), (e as CustomEvent<Aviso>).detail]);
        const pergunta = (e: Event) => setPerguntas((l) => [...l, (e as CustomEvent<Pergunta>).detail]);
        window.addEventListener('pwa:aviso', aviso);
        window.addEventListener('pwa:pergunta', pergunta);

        return () => {
            window.removeEventListener('pwa:aviso', aviso);
            window.removeEventListener('pwa:pergunta', pergunta);
        };
    }, []);

    const primeira = perguntas[0];

    return (
        <>
            <div className={`fixed inset-x-3 ${acima} z-[190] flex flex-col gap-2 pointer-events-none sm:left-auto sm:w-96`} aria-live="polite">
                {avisos.map((a) => (
                    <UmAviso key={a.id} aviso={a} fechar={() => setAvisos((l) => l.filter((x) => x.id !== a.id))} />
                ))}
            </div>
            {primeira && <APergunta key={primeira.id} p={primeira} fechar={() => setPerguntas((l) => l.slice(1))} />}
        </>
    );
}

/** Uma caixa de aviso dentro de um ecrã (não flutuante). */
export function Nota({ tipo = 'info', icone, children, className = '' }: { tipo?: TipoDeAviso; icone?: string; children: ReactNode; className?: string }) {
    const cores: Record<TipoDeAviso, string> = {
        ok: 'bg-emerald-50 border-emerald-500 text-emerald-900',
        erro: 'bg-red-50 border-red-500 text-red-900',
        aviso: 'bg-amber-50 border-amber-500 text-amber-900',
        info: 'bg-blue-50 border-blue-500 text-blue-900',
    };

    return (
        <div className={`pwa-entra border-l-4 p-3 rounded-lg text-xs ${cores[tipo]} ${className}`}>
            {icone && <i className={`fas ${icone} mr-1`} aria-hidden="true" />}
            {children}
        </div>
    );
}
