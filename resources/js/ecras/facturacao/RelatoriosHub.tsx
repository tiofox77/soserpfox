import { useQuery } from '@tanstack/react-query';

import { relatorios } from '@/api/relatorios';
import { ErroDaApi } from '@/api/cliente';
import { Carregando } from '@/ui/Carregando';
import { FOCO, RAIO, RAIO_GRANDE, cls } from '@/ui/tokens';
import { t } from '@/i18n';
import { Faixa, cascata } from './faixa';

/**
 * A PORTA DOS RELATÓRIOS: as secções e os mapas de cada uma, como o
 * servidor as lista (`Relatorios\Catalogo`). Cada cartão abre o mapa no
 * ecrã genérico.
 *
 * ESTE É O ECRÃ ONDE MAIS SE NOTOU A PERDA. Vinte e três mapas em vinte e
 * três caixas brancas iguais é uma lista de nomes para ler de cima a baixo;
 * o ecrã em Blade dava a cada secção a sua cor e a cada mapa o seu ícone, e
 * quem já cá vinha encontrava o de que precisava de relance, pela cor. A cor
 * é do servidor — chega no `cor` da secção — e aqui só se traduz em classes.
 *
 * PORQUE É UMA TABELA E NÃO `from-${cor}-500`: o Tailwind lê as classes do
 * código-fonte antes de o correr. Uma classe montada com um pedaço de
 * variável não existe no ficheiro e não chega a ser gerada — no Blade isso
 * dependia de a cor já ter aparecido escrita noutro sítio qualquer.
 */
type Aspecto = {
    /** O quadrado do título da secção: gradiente, como no ecrã de sempre. */
    tijolo: string;
    /** A moldura do cartão ao passar o rato. */
    moldura: string;
    /** O quadrado do ícone do mapa, e o que lhe acontece ao passar. */
    quadrado: string;
    /** O nome do mapa ao passar. */
    nome: string;
    /** A seta à direita. */
    seta: string;
    /** O fio que continua o título até ao fim da linha. */
    fio: string;
};

const ASPECTOS: Record<string, Aspecto> = {
    emerald: {
        tijolo: 'bg-gradient-to-br from-emerald-500 to-teal-600',
        moldura: 'hover:border-emerald-300',
        quadrado: 'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white',
        nome: 'group-hover:text-emerald-700',
        seta: 'group-hover:text-emerald-500',
        fio: 'from-emerald-200',
    },
    green: {
        tijolo: 'bg-gradient-to-br from-emerald-500 to-green-600',
        moldura: 'hover:border-green-300',
        quadrado: 'bg-green-50 text-green-600 group-hover:bg-green-600 group-hover:text-white',
        nome: 'group-hover:text-green-700',
        seta: 'group-hover:text-green-500',
        fio: 'from-green-200',
    },
    orange: {
        tijolo: 'bg-gradient-to-br from-orange-500 to-red-600',
        moldura: 'hover:border-orange-300',
        quadrado: 'bg-orange-50 text-orange-600 group-hover:bg-orange-600 group-hover:text-white',
        nome: 'group-hover:text-orange-700',
        seta: 'group-hover:text-orange-500',
        fio: 'from-orange-200',
    },
    blue: {
        tijolo: 'bg-gradient-to-br from-blue-500 to-blue-600',
        moldura: 'hover:border-blue-300',
        quadrado: 'bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white',
        nome: 'group-hover:text-blue-700',
        seta: 'group-hover:text-blue-500',
        fio: 'from-blue-200',
    },
    red: {
        tijolo: 'bg-gradient-to-br from-red-500 to-rose-600',
        moldura: 'hover:border-red-300',
        quadrado: 'bg-red-50 text-red-600 group-hover:bg-red-600 group-hover:text-white',
        nome: 'group-hover:text-red-700',
        seta: 'group-hover:text-red-500',
        fio: 'from-red-200',
    },
    pink: {
        tijolo: 'bg-gradient-to-br from-purple-600 to-pink-600',
        moldura: 'hover:border-pink-300',
        quadrado: 'bg-pink-50 text-pink-600 group-hover:bg-pink-600 group-hover:text-white',
        nome: 'group-hover:text-pink-700',
        seta: 'group-hover:text-pink-500',
        fio: 'from-pink-200',
    },
    amber: {
        tijolo: 'bg-gradient-to-br from-amber-500 to-orange-500',
        moldura: 'hover:border-amber-300',
        quadrado: 'bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white',
        nome: 'group-hover:text-amber-700',
        seta: 'group-hover:text-amber-500',
        fio: 'from-amber-200',
    },
};

/** Uma cor que o servidor mande e aqui não exista cai no azul, não em nada. */
const AZUL = ASPECTOS.blue as Aspecto;
const aspecto = (cor: string): Aspecto => ASPECTOS[cor] ?? AZUL;

export default function RelatoriosHub() {
    const q = useQuery({ queryKey: ['relatorios', 'seccoes'], queryFn: relatorios.seccoes, staleTime: 5 * 60_000 });

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os relatórios')}</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const quantos = q.data.seccoes.reduce((n, s) => n + s.relatorios.length, 0);

    return (
        <div className="space-y-8" data-hub>
            <Faixa
                icone="fa-chart-bar"
                titulo={t('Relatórios')}
                subtitulo={t('Análises e mapas operacionais da gestão de faturação')}
            />

            {q.data.seccoes.map((s, n) => {
                const a = aspecto(s.cor);

                return (
                    <section key={s.titulo} aria-label={s.titulo}>
                        <div className="mb-4 flex items-center gap-3">
                            <span className={cls('grid h-10 w-10 flex-none place-items-center text-white shadow-lg', RAIO, a.tijolo)}>
                                <i className={cls('fas', s.icone)} aria-hidden="true" />
                            </span>
                            {/* Só o título dentro do cabeçalho: a contagem ao
                                lado, para o nome da secção continuar a ser o
                                nome da secção e mais nada. */}
                            <h2 className="text-lg font-bold tracking-tight text-slate-800">{s.titulo}</h2>
                            <span className="text-xs font-semibold text-slate-400">{s.relatorios.length}</span>
                            <span className={cls('h-px flex-1 bg-gradient-to-r to-transparent', a.fio)} aria-hidden="true" />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {s.relatorios.map((r, i) => (
                                <a
                                    key={r.slug}
                                    href={r.caminho}
                                    data-mapa={r.slug}
                                    style={cascata(n * 2 + i)}
                                    className={cls(
                                        'entra group flex flex-col border border-slate-100 bg-white p-5 shadow-md',
                                        RAIO_GRANDE,
                                        'transition-all duration-300 hover:-translate-y-1 hover:shadow-xl',
                                        a.moldura,
                                        FOCO,
                                    )}
                                >
                                    <span className="mb-3 flex items-start justify-between">
                                        <span className={cls('grid h-12 w-12 place-items-center text-lg transition-all duration-300', RAIO, a.quadrado)}>
                                            <i className={cls('fas', r.icone)} aria-hidden="true" />
                                        </span>
                                        <i
                                            className={cls('fas fa-arrow-right text-slate-300 transition-all duration-300 group-hover:translate-x-1', a.seta)}
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className={cls('font-bold text-slate-900 transition-colors duration-200', a.nome)}>{r.nome}</span>
                                    <span className="mt-1 text-xs leading-relaxed text-slate-500">{r.desc}</span>
                                </a>
                            ))}
                        </div>
                    </section>
                );
            })}

            <p className="text-center text-xs text-slate-400">{t(':quantos mapas, em :seccoes secções.', { quantos, seccoes: q.data.seccoes.length })}</p>
        </div>
    );
}
