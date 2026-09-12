import type { ReactNode } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';

/**
 * AS PEÇAS QUE OS ECRÃS DA PLATAFORMA REPETEM.
 *
 * O recado de sucesso, o erro que impede o ecrã de abrir, o interruptor com
 * explicação, a paginação e o aviso de «não tem efeito». Os primeiros ecrãs
 * escreveram-nas cada um à sua maneira; daqui para a frente usam estas.
 */

export function Recado({ texto, aoFechar, aviso = false }: { texto: string | null; aoFechar: () => void; aviso?: boolean }) {
    if (!texto) return null;

    return (
        <div
            role="status"
            className={cls(
                'entra flex items-start justify-between gap-3 border px-4 py-3 text-sm', RAIO,
                aviso ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900',
            )}
        >
            <span>
                <i className={cls('fas mr-2', aviso ? 'fa-triangle-exclamation' : 'fa-circle-check')} aria-hidden="true" />
                {texto}
            </span>
            <button type="button" onClick={aoFechar} className={cls('opacity-70 hover:opacity-100', FOCO, RAIO)}>
                <i className="fas fa-xmark" aria-hidden="true" />
                <span className="sr-only">{t('Fechar')}</span>
            </button>
        </div>
    );
}

export function ErroDoEcra({ titulo, erro }: { titulo: string; erro: unknown }) {
    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{titulo}</h2>
            <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
        </div>
    );
}

export function Interruptor({ rotulo, nota, valor, aoMudar, desligado = false, cor = 'emerald', semEfeito = false }: {
    rotulo: string;
    nota?: ReactNode;
    valor: boolean;
    aoMudar: (v: boolean) => void;
    desligado?: boolean;
    cor?: 'emerald' | 'amber' | 'red' | 'indigo';
    /** Grava-se, mas nenhuma parte do sistema o lê — e diz-se. */
    semEfeito?: boolean;
}) {
    const aceso = {
        emerald: 'border-emerald-300 bg-emerald-50/60',
        amber: 'border-amber-300 bg-amber-50/60',
        red: 'border-red-300 bg-red-50/60',
        indigo: 'border-indigo-300 bg-indigo-50/60',
    }[cor];

    return (
        <label className={cls(
            'flex cursor-pointer items-start gap-2.5 border px-3 py-2.5', RAIO, TRANSICAO,
            valor ? aceso : 'border-slate-200 hover:bg-slate-50',
            desligado && 'cursor-not-allowed opacity-50',
        )}>
            <input
                type="checkbox"
                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                checked={valor}
                disabled={desligado}
                onChange={(e) => aoMudar(e.target.checked)}
            />
            <span className="min-w-0">
                <span className="block text-sm font-semibold text-slate-800">{rotulo}</span>
                {nota && <span className="block text-[11px] text-slate-500">{nota}</span>}
                {semEfeito && <SemEfeito />}
            </span>
        </label>
    );
}

/**
 * «GRAVA-SE, MAS NÃO FAZ NADA.» Um interruptor que não está ligado a nenhuma
 * parte do sistema deixava crer que desligar o registo fechava o registo.
 */
export function SemEfeito() {
    return (
        <span className="mt-1 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">
            <i className="fas fa-plug-circle-xmark" aria-hidden="true" />
            {t('Ainda não tem efeito em nenhuma parte do sistema')}
        </span>
    );
}

export function Paginas({ pagina, ultima, aMudar }: { pagina: number; ultima: number; aMudar: (p: number) => void }) {
    if (ultima <= 1) return null;

    return (
        <nav className="flex items-center justify-between pt-2" aria-label={t('Páginas')}>
            <Botao icone="fa-chevron-left" altura="pequeno" disabled={pagina <= 1} onClick={() => aMudar(pagina - 1)}>{t('Anterior')}</Botao>
            <span className="text-sm tabular-nums text-slate-600">{t('Página :pagina de :paginas', { pagina, paginas: ultima })}</span>
            <Botao icone="fa-chevron-right" altura="pequeno" disabled={pagina >= ultima} onClick={() => aMudar(pagina + 1)}>{t('Seguinte')}</Botao>
        </nav>
    );
}

/** Um segredo guardado: nunca se mostra, diz-se só que existe. */
export function SegredoGuardado({ guardado }: { guardado: boolean }) {
    return guardado ? (
        <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
            <i className="fas fa-lock" aria-hidden="true" />{t('Guardado — deixe vazio para manter')}
        </span>
    ) : (
        <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700">
            <i className="fas fa-triangle-exclamation" aria-hidden="true" />{t('Ainda não configurado')}
        </span>
    );
}

/**
 * «TEM A CERTEZA?» — a pergunta antes de apagar ou de fazer o que não se
 * desfaz. O `wire:confirm` do Livewire era a caixa cinzenta do browser; aqui é
 * um modal da casa, com o erro do servidor à vista quando a acção é recusada.
 */
export function Confirmar({
    aberto, titulo, subtitulo, children, rotulo, icone = 'fa-trash', cor = 'perigo', aTrabalhar = false, erro, aoConfirmar, aoFechar,
}: {
    aberto: boolean;
    titulo: string;
    subtitulo?: string;
    children: ReactNode;
    rotulo: string;
    icone?: string;
    cor?: 'perigo' | 'aviso' | 'bom' | 'primaria';
    aTrabalhar?: boolean;
    erro?: unknown;
    aoConfirmar: () => void;
    aoFechar: () => void;
}) {
    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={titulo}
            subtitulo={subtitulo}
            icone={icone}
            cor={cor}
            largura="sm"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor={cor} tom="solida" icone={icone} aTrabalhar={aTrabalhar} onClick={aoConfirmar}>{rotulo}</Botao>
                </div>
            }
        >
            <div className="space-y-3 text-sm text-slate-700">
                {children}
                <AvisoDeErro erro={erro} />
            </div>
        </Modal>
    );
}

/** Um botão só com ícone, para as acções de uma linha de tabela. O rótulo vai no `title` e para os leitores de ecrã. */
export function BotaoDeIcone({ icone, rotulo, cor, onClick, desligado = false }: {
    icone: string; rotulo: string; cor: string; onClick: () => void; desligado?: boolean;
}) {
    return (
        <button
            type="button"
            title={rotulo}
            onClick={onClick}
            disabled={desligado}
            className={cls('grid h-8 w-8 place-items-center hover:scale-110 disabled:cursor-not-allowed disabled:opacity-40', RAIO, TRANSICAO, FOCO, cor)}
        >
            <i className={`fas ${icone}`} aria-hidden="true" />
            <span className="sr-only">{rotulo}</span>
        </button>
    );
}

/** Um par rótulo/valor dentro de um `<dl>` — o que as janelas de «ver» mostram. */
export function Dado({ rotulo, children, className }: { rotulo: string; children: ReactNode; className?: string }) {
    return (
        <div className={className}>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</dt>
            <dd className="mt-0.5 break-words font-medium text-slate-800">{children}</dd>
        </div>
    );
}

/**
 * A CONSOLA — onde aparece o que um comando, um seeder ou um script disse.
 *
 * O componente montava HTML no servidor (`<div class='text-red-600'>`) e o
 * Blade despejava-o com `{!! !!}`. Aqui vão linhas com o tipo, e a cor sai do
 * tipo: a saída de um comando nunca é interpretada como HTML.
 */
export function Consola({ linhas, vazio, aTrabalhar = false, altura = 'h-80' }: {
    linhas: Array<{ tipo: string; texto: string }>;
    vazio: string;
    aTrabalhar?: boolean;
    altura?: string;
}) {
    const cor: Record<string, string> = {
        info: 'text-sky-300',
        saida: 'text-slate-200',
        sucesso: 'text-emerald-400',
        success: 'text-emerald-400',
        erro: 'text-red-400',
        error: 'text-red-400',
        aviso: 'text-amber-300',
        warning: 'text-amber-300',
    };

    return (
        <div className={cls('overflow-auto bg-slate-950 p-4 font-mono text-xs leading-relaxed shadow-inner', RAIO, altura)} aria-live="polite">
            {linhas.length === 0 && !aTrabalhar && <p className="text-slate-500">{vazio}</p>}
            {linhas.map((l, i) => (l.tipo === 'separador'
                ? <hr key={i} className="my-2 border-slate-700" />
                : <pre key={i} className={cls('entra whitespace-pre-wrap break-words', cor[l.tipo] ?? 'text-slate-300')}>{l.texto}</pre>))}
            {aTrabalhar && (
                <p className="mt-2 text-amber-300"><i className="fas fa-circle-notch fa-spin mr-2" aria-hidden="true" />{t('A executar…')}</p>
            )}
        </div>
    );
}
