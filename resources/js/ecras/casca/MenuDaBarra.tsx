import { useMemo, useState, type ReactNode } from 'react';

import { eLigacao, eSeparador, eSub, eTitulo, type Entrada, type Grupo, type Ligacao, type MenuDaCasca } from '@/api/casca';
import { t } from '@/i18n';
import { cls } from '@/ui/tokens';

/**
 * AS PEÇAS DO MENU DA BARRA LATERAL.
 *
 * A barra veio do Blade tal e qual — e com ela o ícone e um emoji a dizerem a
 * mesma coisa lado a lado («📊 Dashboard»), grupos separados por um palmo de
 * azul e a linha activa só com uma borda. Fica o mesmo menu (os mesmos dados,
 * as mesmas ligações, a mesma ordem — o `MenuDaCascaFielTest` guarda isso), com
 * uma forma só: cada ligação numa pastilha com o ícone num quadrado, a activa
 * acesa com a barra da sua cor, os filhos de um grupo pendurados numa
 * linha-guia, um ponto no grupo fechado que tem a página activa, e o emoji só
 * quando não há ícone.
 */

/** Sem acentos nem maiúsculas: «facturação» encontra-se com «factura». */
const normalizar = (s: string) => s.toLocaleLowerCase('pt').normalize('NFD').replace(/\p{Diacritic}/gu, '');

export function Titulo({ children, aberta, primeiro = false }: { children: ReactNode; aberta: boolean; primeiro?: boolean }) {
    if (!aberta) return <div className={cls('mx-3 border-t border-white/10', primeiro ? 'mb-2 mt-1' : 'my-3')} />;

    return <p className={cls('mb-1.5 px-3 text-[10px] font-bold uppercase tracking-[.14em] text-blue-300/80', !primeiro && 'mt-5')}>{children}</p>;
}

type Nivel = 'topo' | 'sub' | 'subsub' | 'relatorio';

const FORMA: Record<Nivel, { caixa: string; icone: string; rotulo: string }> = {
    topo: { caixa: 'py-1.5', icone: 'h-8 w-8 text-sm', rotulo: 'text-sm' },
    sub: { caixa: 'py-1', icone: 'h-7 w-7 text-xs', rotulo: 'text-[13px]' },
    subsub: { caixa: 'py-1', icone: 'h-6 w-6 text-[11px]', rotulo: 'text-xs' },
    relatorio: { caixa: 'py-0.5', icone: 'h-6 w-6 text-[11px]', rotulo: 'text-xs' },
};

function Icone({ icone, marca = false, cor, prefixo, className, acesa }: {
    icone: string; marca?: boolean; cor: string; prefixo?: string | null; className: string; acesa: boolean;
}) {
    return (
        <span className={cls('grid shrink-0 place-items-center rounded-lg transition-transform duration-200 group-hover:scale-110', className, acesa ? 'bg-white/20 shadow-sm' : 'bg-white/[.06]')}>
            {icone
                ? <i className={cls(marca ? 'fab' : 'fas', icone, 'text-' + cor)} aria-hidden="true" />
                : <span aria-hidden="true">{prefixo}</span>}
        </span>
    );
}

export function LigacaoDoMenu({ l, nivel, aberta, aoClicar, detalhe }: {
    l: Ligacao; nivel: Nivel; aberta: boolean; aoClicar: () => void; detalhe?: string;
}) {
    const f = FORMA[nivel];

    return (
        <a
            href={l.url}
            onClick={aoClicar}
            aria-current={l.activo ? 'page' : undefined}
            title={l.rotulo}
            className={cls(
                'group relative my-px flex items-center gap-2.5 rounded-xl px-2 transition-all duration-200',
                f.caixa,
                !aberta && 'justify-center',
                l.activo
                    ? 'bg-gradient-to-r from-white/20 to-white/[.08] text-white shadow-inner ring-1 ring-white/15'
                    : 'text-blue-100/85 hover:translate-x-0.5 hover:bg-white/10 hover:text-white',
            )}
        >
            {l.activo && <span className={cls('absolute -left-2 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full', 'bg-' + (l.barra || 'yellow-400'))} aria-hidden="true" />}
            <Icone icone={l.icone} marca={l.marca} cor={l.cor} prefixo={l.prefixo} className={f.icone} acesa={l.activo} />
            {aberta && (
                <span className="min-w-0 flex-1">
                    <span className={cls('block truncate', f.rotulo, (l.forte || l.activo) && 'font-semibold')}>{l.rotulo}</span>
                    {detalhe && <span className="block truncate text-[10px] text-blue-300/70">{detalhe}</span>}
                </span>
            )}
        </a>
    );
}

function Entradas({ entradas, nivel, aberta, aoClicar }: { entradas: Entrada[]; nivel: Nivel; aberta: boolean; aoClicar: () => void }) {
    return (
        <>
            {entradas.map((e, i) => {
                if (eSeparador(e)) return <div key={i} className="mx-2 my-1.5 border-t border-white/10" />;
                if (eTitulo(e)) return aberta ? <p key={i} className="px-2 pb-1 pt-2.5 text-[10px] font-bold uppercase tracking-wider text-blue-300/70">{e.titulo}</p> : null;
                if (eSub(e)) return <SubGrupo key={e.sub.chave} g={e.sub} aberta={aberta} aoClicar={aoClicar} />;
                if (eLigacao(e)) return <LigacaoDoMenu key={e.url} l={e} nivel={e.relatorio ? 'relatorio' : nivel} aberta={aberta} aoClicar={aoClicar} />;
                return null;
            })}
        </>
    );
}

/** Há alguma página activa lá dentro? */
function temActiva(entradas: Entrada[]): boolean {
    return entradas.some((e) => (eSub(e) ? temActiva(e.sub.entradas) : eLigacao(e) && e.activo));
}

/**
 * ABRIR E FECHAR COM ALTURA — o que o `x-collapse` do Alpine fazia.
 *
 * As ligações ficam sempre no DOM (a grelha anima de 0fr para 1fr); fechado,
 * o bloco fica `inert`: nem o Tab nem o leitor de ecrã entram lá.
 */
function Dobra({ aberto, className, children }: { aberto: boolean; className: string; children: ReactNode }) {
    return (
        <div
            className={cls('grid transition-[grid-template-rows,opacity] duration-300 ease-out', aberto ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0')}
            inert={!aberto}
        >
            <div className={cls('overflow-hidden', className)}>{children}</div>
        </div>
    );
}

function CabecaDeGrupo({ g, aberta, aberto, onClick, pequena = false }: {
    g: Grupo; aberta: boolean; aberto: boolean; onClick: () => void; pequena?: boolean;
}) {
    const dentro = temActiva(g.entradas);

    return (
        <button
            type="button"
            onClick={onClick}
            aria-expanded={aberto}
            title={g.rotulo}
            className={cls(
                'group flex w-full items-center gap-2.5 rounded-xl px-2 text-left transition-all duration-200 hover:bg-white/10',
                pequena ? 'py-1' : 'py-1.5',
                !aberta && 'justify-center',
                dentro && !aberto && 'bg-white/[.06]',
            )}
        >
            <Icone icone={g.icone} cor={g.cor} prefixo={g.prefixo} className={pequena ? 'h-7 w-7 text-xs' : 'h-8 w-8 text-sm'} acesa={false} />
            {aberta && (
                <span className={cls('min-w-0 flex-1 truncate', pequena ? 'text-[13px] font-semibold' : 'text-sm', !pequena && !g.leve && 'font-semibold text-white')}>
                    {g.rotulo}
                </span>
            )}
            {aberta && dentro && !aberto && (
                <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-yellow-400 shadow-[0_0_0_3px_rgba(250,204,21,.25)]" aria-hidden="true" />
            )}
            {aberta && <i className={cls('fas fa-chevron-right text-[10px] text-blue-300 transition-transform duration-300', aberto && 'rotate-90')} aria-hidden="true" />}
        </button>
    );
}

export function GrupoDoMenu({ g, aberta, aoClicar }: { g: Grupo; aberta: boolean; aoClicar: () => void }) {
    const [aberto, porAberto] = useState(g.aberto);

    return (
        <div className="mt-1">
            <CabecaDeGrupo g={g} aberta={aberta} aberto={aberto} onClick={() => porAberto((v) => !v)} />
            <Dobra aberto={aberto} className={aberta ? 'ml-[1.3rem] border-l border-white/10 pl-2' : ''}>
                <Entradas entradas={g.entradas} nivel="sub" aberta={aberta} aoClicar={aoClicar} />
            </Dobra>
        </div>
    );
}

function SubGrupo({ g, aberta, aoClicar }: { g: Grupo; aberta: boolean; aoClicar: () => void }) {
    const [aberto, porAberto] = useState(g.aberto);

    return (
        <div className="my-px">
            <CabecaDeGrupo g={g} aberta={aberta} aberto={aberto} onClick={() => porAberto((v) => !v)} pequena />
            <Dobra aberto={aberto} className={aberta ? 'ml-4 border-l border-white/10 pl-2' : ''}>
                <Entradas entradas={g.entradas} nivel="subsub" aberta={aberta} aoClicar={aoClicar} />
            </Dobra>
        </div>
    );
}

const grupoComoLigacao = (g: Grupo): Ligacao => ({
    rotulo: g.rotulo,
    prefixo: g.prefixo,
    forte: true,
    icone: g.icone,
    marca: false,
    cor: g.cor,
    barra: 'yellow-400',
    hover: '',
    url: g.url ?? '#',
    activo: g.activo,
    topo: true,
    relatorio: false,
});

/** Uma ligação de topo com as suas dependentes, sem abrir e fechar (Notificações). */
export function GrupoSimples({ g, aberta, aoClicar }: { g: Grupo; aberta: boolean; aoClicar: () => void }) {
    return (
        <div className="mt-1">
            <LigacaoDoMenu l={grupoComoLigacao(g)} nivel="topo" aberta={aberta} aoClicar={aoClicar} />
            <div className={aberta ? 'ml-[1.3rem] border-l border-white/10 pl-2' : ''}>
                <Entradas entradas={g.entradas} nivel="sub" aberta={aberta} aoClicar={aoClicar} />
            </div>
        </div>
    );
}

/**
 * PROCURAR NO MENU. Um tenant com os módulos todos tem mais de cem ligações em
 * quinze grupos; «onde fica a folha de horas?» respondia-se a abrir grupos um
 * a um. Escreve-se, aparecem as ligações com o caminho por baixo, e o Enter
 * abre a primeira.
 */
export function ProcuraNoMenu({ termo, porTermo }: { termo: string; porTermo: (v: string) => void }) {
    return (
        <div className="border-b border-white/10 px-3 pb-2.5 pt-3 shadow-[0_6px_12px_-10px_rgba(0,0,0,.5)]">
            <div className="relative">
                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-blue-300" aria-hidden="true" />
                <input
                    type="search"
                    value={termo}
                    onChange={(e) => porTermo(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Escape') porTermo('');
                        if (e.key === 'Enter') document.querySelector<HTMLAnchorElement>('#sidebar-menu a[href]')?.click();
                    }}
                    placeholder={t('Procurar no menu…')}
                    aria-label={t('Procurar no menu')}
                    className="w-full rounded-xl border border-white/10 bg-white/10 py-2 pl-8 pr-3 text-sm text-white transition placeholder:text-blue-200/60 focus:border-white/30 focus:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/20 [&::-webkit-search-cancel-button]:invert"
                />
            </div>
        </div>
    );
}

type Achada = { l: Ligacao; caminho: string };

function achatar(menu: MenuDaCasca): Achada[] {
    const fora = new Map<string, Achada>();
    const juntar = (l: Ligacao, caminho: string) => {
        if (!fora.has(l.url)) fora.set(l.url, { l, caminho });
    };
    const ver = (entradas: Entrada[], caminho: string) => entradas.forEach((e) => {
        if (eSub(e)) ver(e.sub.entradas, `${caminho} › ${e.sub.rotulo}`);
        else if (eLigacao(e)) juntar(e, caminho);
    });

    menu.principal.forEach((l) => juntar(l, t('Menu Principal')));
    menu.grupos.forEach((g) => {
        if (g.simples && g.url) juntar(grupoComoLigacao(g), g.rotulo);
        ver(g.entradas, g.rotulo);
    });
    menu.superadmin.forEach((s) => s.entradas.forEach((l) => juntar(l, s.titulo)));

    return [...fora.values()];
}

export function ResultadosDaProcura({ menu, termo, aoClicar }: { menu: MenuDaCasca; termo: string; aoClicar: () => void }) {
    const todas = useMemo(() => achatar(menu), [menu]);
    const q = normalizar(termo.trim());
    const achadas = todas.filter((x) => normalizar(`${x.l.rotulo} ${x.caminho}`).includes(q)).slice(0, 40);

    if (achadas.length === 0) {
        return (
            <div className="px-3 py-8 text-center text-sm text-blue-200/70">
                <i className="fas fa-compass mb-2 block text-2xl text-blue-300/50" aria-hidden="true" />
                {t('Nada no menu com «:termo».', { termo })}
            </div>
        );
    }

    return (
        <div className="animate-fade-in">
            <p className="mb-1.5 px-3 text-[10px] font-bold uppercase tracking-[.14em] text-blue-300/80">{t(':n no menu', { n: achadas.length })}</p>
            {achadas.map((x) => <LigacaoDoMenu key={x.l.url} l={x.l} nivel="topo" aberta aoClicar={aoClicar} detalhe={x.caminho} />)}
        </div>
    );
}
