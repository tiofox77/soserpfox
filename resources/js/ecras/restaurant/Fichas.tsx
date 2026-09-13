import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { restaurante, type Ficha } from '@/api/restaurant';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * AS FICHAS TÉCNICAS — o que cada prato leva, e quanto custa.
 *
 * É A FICHA QUE LIGA O PRATO AO STOCK: sem ela, vender um bitoque não tira
 * carne nenhuma do armazém. É também ela que decide o que aparece no balcão
 * quando a casa exige ficha técnica.
 *
 * O CUSTO POR DOSE AO LADO DO PREÇO DE VENDA é a razão de ser deste ecrã. A
 * quebra entra na conta — 200 g com 10% de quebra são 220 g compradas — e a
 * margem aparece em percentagem, que é a única forma de ver de relance qual é
 * o prato que não dá dinheiro.
 */

const formularioVazio = () => ({ product_id: '', yield_quantity: '1', yield_unit: 'UN', is_active: true });
const ingredienteVazio = () => ({ ingredient_product_id: '', quantity: '1', unit: 'UN', waste_percent: '0' });

export default function Fichas() {
    const cache = useQueryClient();

    const [procura, porProcura] = useState('');
    const [atrasada, porAtrasada] = useState('');
    const [aberta, porAberta] = useState<number | null>(null);
    const [formulario, porFormulario] = useState<ReturnType<typeof formularioVazio> | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [ingrediente, porIngrediente] = useState(ingredienteVazio());
    const [aApagar, porAApagar] = useState<Ficha | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [erro, porErro] = useState<unknown>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    useEffect(() => {
        const id = setTimeout(() => porAtrasada(procura), 300);

        return () => clearTimeout(id);
    }, [procura]);

    const opcoes = useQuery({
        queryKey: ['restaurante', 'fichas', 'opcoes'],
        queryFn: restaurante.fichas.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['restaurante', 'fichas', 'lista', atrasada],
        queryFn: () => restaurante.fichas.lista(atrasada),
        placeholderData: keepPreviousData,
    });

    const refrescar = () => void cache.invalidateQueries({ queryKey: ['restaurante', 'fichas'] });

    const falhou = (e: unknown) => { porErro(e); porErros(e instanceof ErroDaApi ? e.erros : {}); };

    const guardar = useMutation({
        mutationFn: () => restaurante.fichas.guardar(aEditar, {
            ...formulario, yield_quantity: Number(formulario!.yield_quantity),
            product_id: Number(formulario!.product_id),
        }),
        onSuccess: (r) => {
            porFormulario(null); porErros({}); porErro(null); porRecado(r.message);
            porAberta(r.data.id); refrescar();
        },
        onError: falhou,
    });

    const acrescentar = useMutation({
        mutationFn: () => restaurante.fichas.acrescentar(aberta!, {
            ...ingrediente,
            ingredient_product_id: Number(ingrediente.ingredient_product_id),
            quantity: Number(ingrediente.quantity),
            waste_percent: Number(ingrediente.waste_percent),
        }),
        onSuccess: (r) => { porIngrediente(ingredienteVazio()); porErros({}); porErro(null); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const remover = useMutation({
        mutationFn: ({ id, linha }: { id: number; linha: number }) => restaurante.fichas.removerIngrediente(id, linha),
        onSuccess: (r) => { porErro(null); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const alternar = useMutation({
        mutationFn: (id: number) => restaurante.fichas.alternar(id),
        onSuccess: (r) => { porErro(null); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => restaurante.fichas.apagar(id),
        onSuccess: (r) => { porAApagar(null); porAberta(null); porErro(null); porRecado(r.message); refrescar(); },
        onError: falhou,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const fichas = lista.data?.data ?? [];
    const resumo = lista.data?.resumo;
    const escolhida = fichas.find((f) => f.id === aberta) ?? null;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Fichas Técnicas')}
                subtitulo={t('O que cada prato leva — e quanto custa fazer')}
                icone="fa-clipboard-list"
                cor="roxo"
                accoes={
                    <>
                        {o.permissoes.pode_gerir && (
                            <button
                                type="button"
                                className={ACCAO_DA_FAIXA}
                                onClick={() => { porAEditar(null); porErros({}); porFormulario(formularioVazio()); }}
                            >
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Nova ficha')}
                            </button>
                        )}
                        <a href="/restaurant/carta" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-book-open" aria-hidden="true" />
                            {t('A carta')}
                        </a>
                    </>
                }
            >
                {resumo && (
                    <EstadoNaFaixa icone="fa-carrot">
                        {t(':n ingredientes em :f fichas', { n: String(resumo.ingredientes), f: String(resumo.total) })}
                    </EstadoNaFaixa>
                )}
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <CartaoNumero aspecto="claro" rotulo={t('Fichas')} valor={resumo.total} icone="fa-clipboard-list" tom="roxo" />
                    <CartaoNumero aspecto="claro" rotulo={t('Activas')} valor={resumo.activas} icone="fa-circle-check" tom="verde" />
                    <CartaoNumero aspecto="claro" rotulo={t('Ingredientes')} valor={resumo.ingredientes} icone="fa-carrot" tom="ambar" />
                </div>
            )}

            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_26rem]">
                <div className="space-y-3">
                    <Campo etiqueta={t('Procurar prato')}>
                        <div className="relative">
                            <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input
                                type="search"
                                value={procura}
                                onChange={(e) => porProcura(e.target.value)}
                                placeholder={t('Nome ou código…')}
                                className={cls(entrada, 'pl-9')}
                            />
                        </div>
                    </Campo>

                    <div className={cls(CARTAO, 'overflow-hidden')}>
                        {lista.isPending ? (
                            <div className="p-5"><Carregando linhas={5} /></div>
                        ) : fichas.length === 0 ? (
                            <SemNada
                                icone="fa-clipboard-list"
                                titulo={t('Ainda não há fichas técnicas')}
                                frase={t('Sem ficha, vender um prato não tira nada do armazém.')}
                                accao={o.permissoes.pode_gerir && (
                                    <Botao
                                        cor="primaria" tom="solida" icone="fa-plus"
                                        onClick={() => { porAEditar(null); porErros({}); porFormulario(formularioVazio()); }}
                                    >
                                        {t('Nova ficha')}
                                    </Botao>
                                )}
                            />
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {fichas.map((f, i) => (
                                    <li key={f.id} style={cascata(i)} className="entra">
                                        <button
                                            type="button"
                                            onClick={() => porAberta(f.id)}
                                            className={cls(
                                                'flex w-full items-center gap-3 px-4 py-3 text-left transition', FOCO,
                                                aberta === f.id ? 'bg-purple-50' : 'hover:bg-slate-50',
                                            )}
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold text-slate-800">{f.prato}</span>
                                                <span className="block text-xs text-slate-500">
                                                    {t(':n ingredientes', { n: String(f.ingredientes.length) })}
                                                    {' · '}
                                                    {t('rende :q :u', { q: String(f.rende), u: f.unidade })}
                                                </span>
                                            </span>

                                            <span className="flex flex-none flex-col items-end gap-1">
                                                <span className="text-sm font-bold tabular-nums text-slate-900">
                                                    {kz(f.custo_por_dose)} <span className="text-xs font-normal text-slate-400">Kz</span>
                                                </span>
                                                {f.margem !== null && (
                                                    <Etiqueta cor={f.margem >= 60 ? 'bom' : f.margem >= 30 ? 'aviso' : 'perigo'}>
                                                        {t('margem :n%', { n: String(f.margem) })}
                                                    </Etiqueta>
                                                )}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                <div className="lg:sticky lg:top-4 lg:self-start">
                    <Cartao
                        titulo={escolhida ? escolhida.prato : t('Ficha')}
                        icone="fa-clipboard-list"
                        accoes={escolhida && o.permissoes.pode_gerir && (
                            <>
                                <Botao
                                    altura="pequeno" icone="fa-pen"
                                    onClick={() => {
                                        porAEditar(escolhida.id);
                                        porErros({});
                                        porFormulario({
                                            product_id: String(escolhida.product_id),
                                            yield_quantity: String(escolhida.rende),
                                            yield_unit: escolhida.unidade,
                                            is_active: escolhida.activa,
                                        });
                                    }}
                                    aria-label={t('Editar ficha')}
                                />
                                <Botao
                                    altura="pequeno"
                                    cor={escolhida.activa ? 'aviso' : 'bom'}
                                    icone={escolhida.activa ? 'fa-pause' : 'fa-play'}
                                    onClick={() => alternar.mutate(escolhida.id)}
                                    aria-label={escolhida.activa ? t('Desactivar') : t('Activar')}
                                />
                                <Botao
                                    altura="pequeno" cor="perigo" icone="fa-trash"
                                    onClick={() => porAApagar(escolhida)}
                                    aria-label={t('Eliminar')}
                                />
                            </>
                        )}
                    >
                        {!escolhida ? (
                            <SemNada icone="fa-hand-pointer" frase={t('Escolha uma ficha à esquerda.')} />
                        ) : (
                            <div className="space-y-4">
                                <div className="grid grid-cols-3 gap-2 text-center">
                                    <div className="rounded-xl bg-slate-50 px-2 py-2">
                                        <p className="text-xs text-slate-500">{t('Custo total')}</p>
                                        <p className="text-sm font-bold tabular-nums text-slate-900">{kz(escolhida.custo)}</p>
                                    </div>
                                    <div className="rounded-xl bg-slate-50 px-2 py-2">
                                        <p className="text-xs text-slate-500">{t('Por dose')}</p>
                                        <p className="text-sm font-bold tabular-nums text-slate-900">{kz(escolhida.custo_por_dose)}</p>
                                    </div>
                                    <div className="rounded-xl bg-slate-50 px-2 py-2">
                                        <p className="text-xs text-slate-500">{t('Preço')}</p>
                                        <p className="text-sm font-bold tabular-nums text-slate-900">{kz(escolhida.preco_de_venda)}</p>
                                    </div>
                                </div>

                                {escolhida.ingredientes.length === 0 ? (
                                    <SemNada icone="fa-carrot" frase={t('Sem ingredientes — a ficha ainda não tira nada do stock.')} />
                                ) : (
                                    <ul className="divide-y divide-slate-100">
                                        {escolhida.ingredientes.map((l) => (
                                            <li key={l.id} className="flex items-center gap-2 py-2">
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-slate-800">{l.nome}</p>
                                                    <p className="text-xs text-slate-500">
                                                        {l.quantidade} {l.unidade}
                                                        {l.quebra > 0 && (
                                                            <span className="text-amber-600">
                                                                {' '}· {t('quebra :n% → :q', { n: String(l.quebra), q: String(l.quantidade_com_quebra) })}
                                                            </span>
                                                        )}
                                                    </p>
                                                </div>

                                                <span className="text-sm font-semibold tabular-nums text-slate-700">{kz(l.custo)}</span>

                                                {o.permissoes.pode_gerir && (
                                                    <Botao
                                                        altura="pequeno" cor="perigo" icone="fa-xmark"
                                                        onClick={() => remover.mutate({ id: escolhida.id, linha: l.id })}
                                                        aria-label={t('Retirar :nome', { nome: l.nome })}
                                                    />
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {o.permissoes.pode_gerir && (
                                    <form
                                        className="space-y-3 border-t border-slate-200 pt-3"
                                        onSubmit={(e) => { e.preventDefault(); acrescentar.mutate(); }}
                                    >
                                        <Campo etiqueta={t('Ingrediente')} obrigatorio erro={erros.ingredient_product_id}>
                                            <select
                                                value={ingrediente.ingredient_product_id}
                                                onChange={(e) => {
                                                    const artigo = o.artigos.find((a) => a.valor === e.target.value);

                                                    porIngrediente({
                                                        ...ingrediente,
                                                        ingredient_product_id: e.target.value,
                                                        unit: artigo?.unidade ?? 'UN',
                                                    });
                                                }}
                                                className={entrada}
                                            >
                                                <option value="">{t('Escolher…')}</option>
                                                {o.artigos.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                                            </select>
                                        </Campo>

                                        <div className="grid grid-cols-3 gap-2">
                                            <Campo etiqueta={t('Quant.')} obrigatorio erro={erros.quantity}>
                                                <input
                                                    type="number" step="0.0001" min="0.0001"
                                                    value={ingrediente.quantity}
                                                    onChange={(e) => porIngrediente({ ...ingrediente, quantity: e.target.value })}
                                                    className={cls(entrada, 'text-right tabular-nums')}
                                                />
                                            </Campo>

                                            <Campo etiqueta={t('Unid.')} obrigatorio erro={erros.unit}>
                                                <input
                                                    value={ingrediente.unit}
                                                    onChange={(e) => porIngrediente({ ...ingrediente, unit: e.target.value })}
                                                    className={entrada}
                                                />
                                            </Campo>

                                            <Campo etiqueta={t('Quebra %')} obrigatorio erro={erros.waste_percent}>
                                                <input
                                                    type="number" step="0.01" min="0" max="100"
                                                    value={ingrediente.waste_percent}
                                                    onChange={(e) => porIngrediente({ ...ingrediente, waste_percent: e.target.value })}
                                                    className={cls(entrada, 'text-right tabular-nums')}
                                                />
                                            </Campo>
                                        </div>

                                        <Botao
                                            type="submit" cor="primaria" tom="solida" icone="fa-plus"
                                            aTrabalhar={acrescentar.isPending}
                                            disabled={!ingrediente.ingredient_product_id}
                                        >
                                            {t('Acrescentar ingrediente')}
                                        </Botao>
                                    </form>
                                )}
                            </div>
                        )}
                    </Cartao>
                </div>
            </div>

            <Modal
                aberto={formulario !== null}
                aoFechar={() => porFormulario(null)}
                titulo={aEditar ? t('Editar ficha') : t('Nova ficha técnica')}
                subtitulo={t('Uma ficha por prato')}
                icone={aEditar ? 'fa-pen' : 'fa-plus'}
                cor="roxo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porFormulario(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <Campo etiqueta={t('Prato')} obrigatorio erro={erros.product_id}>
                            <select
                                value={formulario.product_id}
                                onChange={(e) => porFormulario({ ...formulario, product_id: e.target.value })}
                                className={entrada}
                            >
                                <option value="">{t('Escolher…')}</option>
                                {o.artigos.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                            </select>
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta={t('Rende')}
                                obrigatorio
                                erro={erros.yield_quantity}
                                ajuda={t('Quantas doses saem desta ficha.')}
                            >
                                <input
                                    type="number" step="0.0001" min="0.0001"
                                    value={formulario.yield_quantity}
                                    onChange={(e) => porFormulario({ ...formulario, yield_quantity: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>

                            <Campo etiqueta={t('Unidade')} obrigatorio erro={erros.yield_unit}>
                                <input
                                    value={formulario.yield_unit}
                                    onChange={(e) => porFormulario({ ...formulario, yield_unit: e.target.value })}
                                    className={entrada}
                                />
                            </Campo>
                        </div>

                        <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={formulario.is_active}
                                onChange={(e) => porFormulario({ ...formulario, is_active: e.target.checked })}
                                className="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500"
                            />
                            {t('Activa')}
                        </label>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar ficha')}
                subtitulo={aApagar?.prato}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo" tom="solida" icone="fa-trash"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}
                        >
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('Sem ficha, este prato deixa de consumir stock ao ser vendido. Se a casa exigir ficha técnica, deixa também de aparecer no balcão.')}
                </p>
            </Modal>
        </div>
    );
}
