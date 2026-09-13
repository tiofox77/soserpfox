import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { definicoes, type CampoDeDefinicao, type SeccaoDeDefinicoes } from '@/api/rh';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { entrada } from '@/ui/Campo';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * AS DEFINIÇÕES DE RH — os números que decidem quanto cada pessoa recebe.
 *
 * A taxa de INSS, os limites de isenção, o multiplicador da hora extra. Um
 * erro aqui não dá erro nenhum: dá salários errados, mês após mês, até alguém
 * reparar.
 *
 * GRAVA-SE CAMPO A CAMPO, ao sair do campo. É assim que se corrige um número
 * sem medo — e o ecrã diz, no próprio campo, que ficou gravado. O botão
 * «Guardar tudo» existe para quem preferiu mexer em vários antes de gravar.
 *
 * O CAMPO INFORMATIVO É MARCADO. Há definições que o sistema guarda e nenhum
 * cálculo lê ainda; mostrá-las como campos vulgares é mentir — edita-se,
 * grava, e não acontece nada, que é exactamente a impressão de «isto não
 * funciona».
 *
 * QUEM SÓ PODE VER não vê botões: os campos ficam desactivados e diz-se
 * porquê. `hr.settings.view` abre; `hr.settings.edit` é que deixa gravar.
 */

type TomDaSeccao = { risca: string; icone: string; fundo: string };

const NEUTRA: TomDaSeccao = { risca: 'border-l-slate-400', icone: 'text-slate-500', fundo: 'bg-slate-50' };

const TOM_DA_SECCAO: Record<string, TomDaSeccao> = {
    primaria: { risca: 'border-l-indigo-500', icone: 'text-indigo-600', fundo: 'bg-indigo-50' },
    roxo: { risca: 'border-l-purple-500', icone: 'text-purple-600', fundo: 'bg-purple-50' },
    aviso: { risca: 'border-l-amber-500', icone: 'text-amber-600', fundo: 'bg-amber-50' },
    bom: { risca: 'border-l-emerald-500', icone: 'text-emerald-600', fundo: 'bg-emerald-50' },
    ciano: { risca: 'border-l-cyan-500', icone: 'text-cyan-600', fundo: 'bg-cyan-50' },
    rosa: { risca: 'border-l-pink-500', icone: 'text-pink-600', fundo: 'bg-pink-50' },
    neutra: NEUTRA,
};

type Valor = string | number | boolean;

export default function Definicoes() {
    const cache = useQueryClient();

    const [valores, porValores] = useState<Record<string, Valor>>({});
    const [gravados, porGravados] = useState<Record<string, boolean>>({});
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aRepor, porARepor] = useState<SeccaoDeDefinicoes | 'tudo' | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const q = useQuery({ queryKey: ['rh', 'definicoes'], queryFn: definicoes.ler });

    // O FORMULÁRIO NASCE DO SERVIDOR, e volta a nascer sempre que ele responde
    // — é assim que o «repor padrões» se vê no ecrã sem recarregar a página.
    useEffect(() => {
        if (! q.data) return;

        const iniciais: Record<string, Valor> = {};

        for (const s of q.data.seccoes) {
            for (const c of s.campos) {
                iniciais[c.chave] = (c.valor ?? '') as Valor;
            }
        }

        porValores(iniciais);
    }, [q.data]);

    const gravar = useMutation({
        mutationFn: (v: Record<string, Valor>) => definicoes.guardar(v),
        onSuccess: (r, enviados) => {
            porErros({});
            porRecado(r.message);

            // A MARCA DE GRAVADO some sozinha: um visto permanente ao lado de
            // cinquenta campos deixa de querer dizer alguma coisa.
            const marcas = Object.fromEntries(Object.keys(enviados).map((k) => [k, true]));
            porGravados((g) => ({ ...g, ...marcas }));
            setTimeout(() => porGravados((g) => {
                const copia = { ...g };
                for (const k of Object.keys(marcas)) delete copia[k];

                return copia;
            }), 2500);
        },
        onError: (e) => {
            if (! (e instanceof ErroDaApi)) return;

            // As chaves vêm como `valores.working_days_per_month`.
            const porCampo: Record<string, string[]> = {};

            for (const [chave, mensagens] of Object.entries(e.erros)) {
                porCampo[chave.replace(/^valores\./, '')] = mensagens;
            }

            porErros(porCampo);
        },
    });

    const repor = useMutation({
        mutationFn: (seccao: string) => definicoes.repor(seccao),
        onSuccess: (r) => {
            void cache.invalidateQueries({ queryKey: ['rh', 'definicoes'] });
            porARepor(null);
            porRecado(r.message);
        },
    });

    if (q.isPending) return <Carregando linhas={10} />;
    if (q.isError) return <Falhou erro={q.error} />;

    const d = q.data;
    const podeEditar = d.permissoes.pode_editar;

    /** Grava um campo só — ao sair dele, ou ao mudar um interruptor. */
    const gravarUm = (chave: string) => {
        if (! podeEditar) return;

        const original = d.seccoes.flatMap((s) => s.campos).find((c) => c.chave === chave);

        // Nada mudou: não se escreve à base nem se pisca um visto por nada.
        if (original && String(original.valor ?? '') === String(valores[chave] ?? '')) return;

        gravar.mutate({ [chave]: valores[chave] ?? '' });
    };

    const porMudar = (chave: string, valor: Valor) => {
        porValores((v) => ({ ...v, [chave]: valor }));
        porErros((e) => {
            if (! e[chave]) return e;
            const copia = { ...e };
            delete copia[chave];

            return copia;
        });
    };

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Definições de RH')}
                subtitulo={t('Os números que decidem o INSS, as isenções e o cálculo das horas extra')}
                icone="fa-sliders"
                cor="roxo"
                accoes={podeEditar && (
                    <button type="button" onClick={() => porARepor('tudo')} className="inline-flex items-center gap-2 rounded-xl bg-white/20 px-3.5 py-2 text-sm font-semibold backdrop-blur-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-white/30">
                        <i className="fas fa-rotate-left transition-transform duration-300 group-hover:-rotate-45" aria-hidden="true" />
                        {t('Repor tudo')}
                    </button>
                )}
            />

            {d.criadas_agora > 0 && (
                <div role="status" className={cls('animate-fade-in border border-emerald-300 bg-emerald-50 p-4 text-emerald-900', RAIO)}>
                    <p className="font-bold">
                        <i className="fas fa-circle-check mr-2" aria-hidden="true" />
                        {t(':n definição(ões) criada(s) para esta empresa', { n: d.criadas_agora })}
                    </p>
                    <p className="mt-1 text-sm">
                        {t('Esta empresa não tinha definições de RH — foram criadas agora com os valores da legislação laboral angolana. Confira-as antes do próximo processamento de salários.')}
                    </p>
                </div>
            )}

            {! podeEditar && (
                <div role="status" className={cls('border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                    <i className="fas fa-lock mr-2" aria-hidden="true" />
                    {t('Só pode ver estas definições — alterá-las é outra permissão.')}
                </div>
            )}

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <AvisoDeErro erro={repor.error} />

            {d.seccoes.map((s) => (
                <Seccao
                    key={s.chave}
                    s={s}
                    valores={valores}
                    erros={erros}
                    gravados={gravados}
                    podeEditar={podeEditar}
                    aGravar={gravar.isPending}
                    aoMudar={porMudar}
                    aoSair={gravarUm}
                    aoRepor={() => porARepor(s)}
                />
            ))}

            {/* GUARDAR TUDO, para quem mexeu em vários antes de gravar. */}
            {podeEditar && (
                <div className={cls(CARTAO, 'sticky bottom-4 flex flex-wrap items-center justify-between gap-3 p-4')}>
                    <p className="text-sm text-slate-500">
                        <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                        {t('Cada campo grava-se ao sair dele. Este botão grava tudo de uma vez.')}
                    </p>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={gravar.isPending}
                        onClick={() => gravar.mutate(valores)}>
                        {t('Guardar tudo')}
                    </Botao>
                </div>
            )}

            <Modal
                aberto={aRepor !== null}
                aoFechar={() => porARepor(null)}
                titulo={t('Repor os valores padrão')}
                icone="fa-rotate-left"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porARepor(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-rotate-left" aTrabalhar={repor.isPending}
                            onClick={() => aRepor && repor.mutate(aRepor === 'tudo' ? 'tudo' : aRepor.chave)}>
                            {t('Repor')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {aRepor === 'tudo'
                        ? t('Vai repor TODAS as definições nos valores padrão da legislação angolana.')
                        : t('Vai repor as definições de «:seccao» nos valores padrão.', { seccao: aRepor?.rotulo ?? '' })}
                </p>
                <div className={cls('mt-3 border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900', RAIO)}>
                    <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                    {t('O que esta empresa afinou perde-se, e a próxima folha é calculada com os valores repostos.')}
                </div>
            </Modal>
        </div>
    );
}

/* ─── Uma secção ────────────────────────────────────────────────────── */

function Seccao({ s, valores, erros, gravados, podeEditar, aGravar, aoMudar, aoSair, aoRepor }: {
    s: SeccaoDeDefinicoes;
    valores: Record<string, Valor>;
    erros: Record<string, string[]>;
    gravados: Record<string, boolean>;
    podeEditar: boolean;
    aGravar: boolean;
    aoMudar: (chave: string, valor: Valor) => void;
    aoSair: (chave: string) => void;
    aoRepor: () => void;
}) {
    const tom = TOM_DA_SECCAO[s.cor] ?? NEUTRA;
    const comErro = s.campos.filter((c) => erros[c.chave]).length;

    return (
        <div className={cls(CARTAO, 'animate-fade-in overflow-hidden border-l-4', tom.risca)}>
            <div className={cls('flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3', tom.fundo)}>
                <h2 className="flex items-center gap-3 text-lg font-bold text-slate-800">
                    <i className={cls('fas', s.icone, tom.icone)} aria-hidden="true" />
                    {s.rotulo}
                    <span className="rounded-full bg-white/70 px-2 py-0.5 text-xs font-semibold text-slate-500">
                        {s.campos.length}
                    </span>
                    {comErro > 0 && (
                        <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">
                            {comErro}
                        </span>
                    )}
                </h2>

                {podeEditar && (
                    <Botao altura="pequeno" icone="fa-rotate-left" onClick={aoRepor}>{t('Repor esta secção')}</Botao>
                )}
            </div>

            <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                {s.campos.map((c, i) => (
                    <Campo
                        key={c.chave}
                        c={c}
                        i={i}
                        valor={valores[c.chave]}
                        erro={erros[c.chave]}
                        gravado={!! gravados[c.chave]}
                        podeEditar={podeEditar}
                        aGravar={aGravar}
                        aoMudar={aoMudar}
                        aoSair={aoSair}
                    />
                ))}
            </div>
        </div>
    );
}

function Campo({ c, i, valor, erro, gravado, podeEditar, aGravar, aoMudar, aoSair }: {
    c: CampoDeDefinicao;
    i: number;
    valor: Valor | undefined;
    erro?: string[];
    gravado: boolean;
    podeEditar: boolean;
    aGravar: boolean;
    aoMudar: (chave: string, valor: Valor) => void;
    aoSair: (chave: string) => void;
}) {
    const passo = c.tipo === 'integer' ? '1' : c.tipo === 'percentage' ? '0.1' : '0.01';
    const numerico = c.tipo === 'integer' || c.tipo === 'decimal' || c.tipo === 'percentage';

    return (
        <div
            className={cls(
                'entra border p-4 transition-all duration-200', RAIO,
                erro ? 'border-red-300 bg-red-50/40'
                    : c.informativa ? 'border-amber-200 bg-amber-50/40'
                        : 'border-slate-200 bg-slate-50/60 hover:border-indigo-300',
            )}
            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
        >
            {c.informativa && (
                <span className="mb-2 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800"
                    title={t('O valor fica guardado, mas nenhum cálculo o usa ainda')}>
                    <i className="fas fa-circle-info" aria-hidden="true" />
                    {t('Registo apenas — ainda não entra no cálculo')}
                </span>
            )}

            <label className="block">
                <span className="mb-1.5 flex items-start justify-between gap-2">
                    <span className="font-bold text-slate-800">{c.etiqueta}</span>
                    <span className="flex-none rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500 ring-1 ring-inset ring-slate-200">
                        {c.tipo_rotulo}
                    </span>
                </span>

                {c.ajuda && <span className="mb-2 block text-xs text-slate-500">{c.ajuda}</span>}

                {c.tipo === 'boolean' ? (
                    <span className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={valor === true || valor === '1' || valor === 1}
                            disabled={! podeEditar || aGravar}
                            onChange={(e) => { aoMudar(c.chave, e.target.checked); }}
                            onBlur={() => aoSair(c.chave)}
                            className={cls('h-5 w-9 cursor-pointer appearance-none rounded-full bg-slate-300 transition-colors',
                                'checked:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-50',
                                'relative after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform checked:after:translate-x-4',
                                FOCO)}
                        />
                        <span className="text-sm font-medium text-slate-600">
                            {(valor === true || valor === '1' || valor === 1) ? t('Sim') : t('Não')}
                        </span>
                    </span>
                ) : (
                    <span className="relative block">
                        <input
                            type={numerico ? 'number' : 'text'}
                            step={numerico ? passo : undefined}
                            value={String(valor ?? '')}
                            disabled={! podeEditar}
                            onChange={(e) => aoMudar(c.chave, e.target.value)}
                            onBlur={() => aoSair(c.chave)}
                            onKeyDown={(e) => { if (e.key === 'Enter') (e.target as HTMLInputElement).blur(); }}
                            className={cls(entrada, 'tabular-nums disabled:cursor-not-allowed disabled:bg-slate-100',
                                c.tipo === 'percentage' && 'pr-9')}
                        />
                        {c.tipo === 'percentage' && (
                            <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-400">%</span>
                        )}
                    </span>
                )}
            </label>

            <div className="mt-2 flex items-center justify-between gap-2 text-xs">
                <span className="text-slate-400">
                    {t('Padrão')}: <b className="text-slate-500">{c.padrao ?? '—'}</b>
                </span>

                {erro ? (
                    <span role="alert" className="font-semibold text-red-600">{erro[0]}</span>
                ) : gravado ? (
                    <span className="animate-fade-in font-semibold text-emerald-600">
                        <i className="fas fa-check mr-1" aria-hidden="true" />{t('Gravado')}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as definições de RH')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
