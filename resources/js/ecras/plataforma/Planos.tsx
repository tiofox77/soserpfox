import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type FichaDoPlano, type PlanoDaLista, plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * OS PLANOS — o que se vende, por quanto, e com o que dentro.
 *
 * QUATRO CAMPOS QUE ESTE FORMULÁRIO NUNCA TEVE, e as colunas existem desde o
 * princípio. Não é acabamento: sem eles o ecrã criava planos quebrados.
 *
 *  · PREÇO TRIMESTRAL E SEMESTRAL. A página «A minha conta» oferece ao cliente
 *    estes dois ciclos e vai buscar o preço às colunas — que nasciam a zero num
 *    plano criado por aqui, e zero lê-se como 3× e 6× o mensal: o trimestre e
 *    o semestre saíam sem desconto, e só um comando de consola os sabia pôr.
 *  · TECTO DE DOCUMENTOS. Trava a numeração fiscal e viaja para a subscrição.
 *    Vazio é «sem tecto», e é diferente de zero.
 *  · NA MONTRA. Um plano pode estar ao serviço e fora da página de preços — é
 *    assim que nascem os planos à medida. Não havia como publicá-los, nem como
 *    distingui-los na lista.
 *  · PROMOCIONAL. A marca do FOX Friendly, que cada empresa activa uma só vez.
 */
export default function Planos() {
    const fila = useQueryClient();
    const [procura, porProcura] = useState('');
    const [aEditar, porAEditar] = useState<number | null | 'novo'>(null);
    const [aApagar, porAApagar] = useState<PlanoDaLista | null>(null);
    const [recado, porRecado] = useState<string | null>(null);

    const lista = useQuery({
        queryKey: ['plataforma', 'planos', procura],
        queryFn: () => plataforma.planos.ler(procura),
        staleTime: 15_000,
    });

    const refrescar = () => void fila.invalidateQueries({ queryKey: ['plataforma', 'planos'] });

    const alternar = useMutation({
        mutationFn: (id: number) => plataforma.planos.alternar(id),
        onSuccess: (r) => { porRecado(r.message); refrescar(); },
    });

    const apagar = useMutation({
        mutationFn: (id: number) => plataforma.planos.apagar(id),
        onSuccess: (r) => { porRecado(r.message); porAApagar(null); refrescar(); },
    });

    if (lista.isPending) return <Carregando linhas={10} />;

    if (lista.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os planos')}</h2>
                <p className="text-sm text-red-800">
                    {lista.error instanceof ErroDaApi ? lista.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const { planos, modulos, numeros } = lista.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Planos')}
                subtitulo={t('O que se vende, por quanto, e com o que dentro')}
                icone="fa-layer-group"
                cor="bom"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porAEditar('novo')}>
                        <i className="fas fa-plus" aria-hidden="true" />
                        {t('Novo plano')}
                    </button>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-layer-group">
                        {t(':n plano(s) · :a ao serviço', { n: numeros.planos, a: numeros.activos })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-store">
                        {t(':n na montra', { n: numeros.na_montra })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            {recado && (
                <div
                    role="status"
                    className={cls('entra flex items-start justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}
                >
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado(null)} className={cls('text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                        <span className="sr-only">{t('Fechar')}</span>
                    </button>
                </div>
            )}

            <AvisoDeErro erro={alternar.error ?? apagar.error} />

            <div className={cls(CARTAO, 'p-4')}>
                <label className="block">
                    <Rotulo>{t('Procurar')}</Rotulo>
                    <div className="relative">
                        <i className="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input
                            type="search"
                            className={cls(entrada, 'pl-9')}
                            placeholder={t('Nome, identificador ou descrição…')}
                            value={procura}
                            onChange={(e) => porProcura(e.target.value)}
                        />
                    </div>
                </label>
            </div>

            {planos.length === 0 ? (
                <SemNada
                    icone="fa-layer-group"
                    titulo={procura ? t('Nenhum plano encontrado') : t('Ainda não há planos')}
                    frase={procura
                        ? t('Nenhum plano com «:p».', { p: procura })
                        : t('Um plano é o que uma empresa subscreve: o preço, os limites e os módulos que leva.')}
                    accao={!procura && (
                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porAEditar('novo')}>
                            {t('Criar o primeiro plano')}
                        </Botao>
                    )}
                />
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {planos.map((p, i) => (
                        <CartaoDoPlano
                            key={p.id}
                            plano={p}
                            indice={i}
                            aEditar={() => porAEditar(p.id)}
                            aAlternar={() => alternar.mutate(p.id)}
                            aApagar={() => porAApagar(p)}
                            aTrabalhar={alternar.isPending}
                        />
                    ))}
                </div>
            )}

            {aEditar !== null && (
                <Formulario
                    id={aEditar === 'novo' ? null : aEditar}
                    modulos={modulos}
                    aoFechar={() => porAEditar(null)}
                    aoGuardar={(m) => { porRecado(m); porAEditar(null); refrescar(); }}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar o plano?')}
                subtitulo={aApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porAApagar(null)}>{t('Deixar estar')}</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}
                        >
                            {t('Apagar')}
                        </Botao>
                    </div>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('O plano deixa de existir e os módulos são desligados dele. As empresas que já o subscreveram não são tocadas.')}
                </p>
                {aApagar && aApagar.subscricoes_presas > 0 && (
                    <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                        {t('Este plano tem :n subscrição(ões) activa(s) ou em ensaio: não é possível apagá-lo.', { n: aApagar.subscricoes_presas })}
                    </p>
                )}
            </Modal>
        </div>
    );
}

/* ─── O cartão de um plano ────────────────────────────────────────────── */

function CartaoDoPlano({ plano: p, indice, aEditar, aAlternar, aApagar, aTrabalhar }: {
    plano: PlanoDaLista;
    indice: number;
    aEditar: () => void;
    aAlternar: () => void;
    aApagar: () => void;
    aTrabalhar: boolean;
}) {
    return (
        <article
            className={cls(
                CARTAO, 'card-hover cascata flex flex-col overflow-hidden',
                p.destacado && 'ring-2 ring-amber-300',
                !p.activo && 'opacity-70',
            )}
            style={cascata(indice)}
        >
            <header className={cls(
                'flex items-start justify-between gap-3 px-5 py-4',
                p.destacado
                    ? 'bg-gradient-to-br from-amber-400 to-orange-500 text-white'
                    : 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white',
            )}>
                <div className="min-w-0">
                    <h3 className="truncate text-lg font-extrabold">{p.nome}</h3>
                    <code className="text-xs text-white/80">{p.slug}</code>
                </div>
                {p.destacado && (
                    <span className="shrink-0 rounded-full bg-white/25 px-2 py-1 text-[11px] font-bold">
                        <i className="fas fa-star mr-1" aria-hidden="true" />{t('Destaque')}
                    </span>
                )}
            </header>

            <div className="flex-1 space-y-3 p-5">
                {p.descricao && <p className="text-sm text-slate-600">{p.descricao}</p>}

                <div>
                    <p className="text-3xl font-extrabold text-slate-900">
                        {kz(p.preco_mensal)} <span className="text-sm font-semibold text-slate-500">Kz/{t('mês')}</span>
                    </p>
                    <ul className="mt-1 space-y-0.5 text-xs text-slate-500">
                        <li>{t('trimestral: :n Kz', { n: kz(p.preco_trimestral) })}</li>
                        <li>{t('semestral: :n Kz', { n: kz(p.preco_semestral) })}</li>
                        <li>
                            {t('anual: :n Kz', { n: kz(p.preco_anual) })}
                            {p.desconto_anual > 0 && (
                                <span className="ml-1.5 font-bold text-emerald-600">
                                    {t('(-:n%)', { n: p.desconto_anual })}
                                </span>
                            )}
                        </li>
                    </ul>

                </div>

                <dl className="grid grid-cols-2 gap-2 text-xs">
                    <Limite rotulo={t('Utilizadores')} valor={p.max_utilizadores === 0 ? t('sem limite') : String(p.max_utilizadores)} icone="fa-users" />
                    <Limite rotulo={t('Empresas')} valor={p.max_empresas >= 999 ? t('sem limite') : String(p.max_empresas)} icone="fa-building" />
                    <Limite
                        rotulo={t('Espaço')}
                        valor={p.max_espaco_mb >= 1024
                            ? t(':n GB', { n: Math.round((p.max_espaco_mb / 1024) * 10) / 10 })
                            : t(':n MB', { n: p.max_espaco_mb })}
                        icone="fa-hard-drive"
                    />
                    <Limite
                        rotulo={t('Documentos')}
                        valor={p.max_documentos === null ? t('sem tecto') : kz(p.max_documentos, 0)}
                        icone="fa-file-invoice"
                    />
                </dl>

                {p.funcionalidades.length > 0 && (
                    <ul className="space-y-1 text-xs text-slate-600">
                        {p.funcionalidades.slice(0, 5).map((f) => (
                            <li key={f}>
                                <i className="fas fa-check mr-1.5 text-emerald-500" aria-hidden="true" />{f}
                            </li>
                        ))}
                        {p.funcionalidades.length > 5 && (
                            <li className="text-slate-400">
                                {t('e mais :n', { n: p.funcionalidades.length - 5 })}
                            </li>
                        )}
                    </ul>
                )}

                <div className="flex flex-wrap gap-1.5">
                    {p.modulos.map((m) => (
                        <span key={m.id} className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                            <i className={cls('fas', m.icone, 'text-slate-400')} aria-hidden="true" />
                            {m.nome}
                        </span>
                    ))}
                    {p.modulos.length === 0 && (
                        <span className="text-[11px] italic text-slate-400">{t('sem módulos')}</span>
                    )}
                </div>

                <div className="flex flex-wrap gap-1.5 border-t border-slate-100 pt-3">
                    <Etiqueta cor={p.activo ? 'bom' : 'neutra'} ponto>
                        {p.activo ? t('Ao serviço') : t('Fora de serviço')}
                    </Etiqueta>
                    {/* FORA DA MONTRA é o estado dos planos à medida, e não se
                        via em sítio nenhum. */}
                    <Etiqueta cor={p.na_montra ? 'primaria' : 'aviso'} icone={p.na_montra ? 'fa-store' : 'fa-eye-slash'}>
                        {p.na_montra ? t('Na montra') : t('Fora da montra')}
                    </Etiqueta>
                    {p.promocional && <Etiqueta cor="aviso" icone="fa-gift">{t('Promocional')}</Etiqueta>}
                    {p.activa_sozinho && <Etiqueta cor="bom" icone="fa-bolt">{t('Activa sozinho')}</Etiqueta>}
                    {p.dias_de_ensaio > 0 && (
                        <Etiqueta cor="neutra" icone="fa-hourglass-start">
                            {t(':n dias de ensaio', { n: p.dias_de_ensaio })}
                        </Etiqueta>
                    )}
                </div>

                <p className="text-xs text-slate-500">
                    <i className="fas fa-file-signature mr-1.5" aria-hidden="true" />
                    {t(':n subscrição(ões) activa(s)', { n: p.subscricoes_activas })}
                </p>
            </div>

            <footer className="flex gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
                <Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-pen" onClick={aEditar} className="flex-1">
                    {t('Editar')}
                </Botao>
                <Botao
                    cor={p.activo ? 'aviso' : 'bom'}
                    tom="suave"
                    altura="pequeno"
                    icone={p.activo ? 'fa-pause' : 'fa-play'}
                    aTrabalhar={aTrabalhar}
                    onClick={aAlternar}
                >
                    {p.activo ? t('Desligar') : t('Ligar')}
                </Botao>
                <Botao
                    cor="perigo"
                    tom="suave"
                    altura="pequeno"
                    icone="fa-trash"
                    disabled={!p.pode_apagar}
                    title={p.pode_apagar
                        ? t('Apagar o plano')
                        : t('Tem :n subscrição(ões) activa(s) ou em ensaio.', { n: p.subscricoes_presas })}
                    onClick={aApagar}
                >
                    <span className="sr-only">{t('Apagar')}</span>
                </Botao>
            </footer>
        </article>
    );
}

function Limite({ rotulo, valor, icone }: { rotulo: string; valor: string; icone: string }) {
    return (
        <div className="rounded-lg bg-slate-50 px-2 py-1.5">
            <dt className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <i className={cls('fas mr-1', icone)} aria-hidden="true" />{rotulo}
            </dt>
            <dd className="font-bold text-slate-800">{valor}</dd>
        </div>
    );
}

/* ─── O formulário ────────────────────────────────────────────────────── */

const VAZIO: FichaDoPlano = {
    id: 0, name: '', slug: '', description: '',
    price_monthly: 0, price_quarterly: 0, price_semiannual: 0, price_yearly: 0,
    max_users: 5, max_companies: 1, max_storage_mb: 1000, max_documents: null,
    trial_days: 30, order: 0,
    is_active: true, is_public: true, is_featured: false,
    is_promotional: false, auto_activate: false,
    features: [], modulos: [],
};

function Formulario({ id, modulos, aoFechar, aoGuardar }: {
    id: number | null;
    modulos: Array<{ id: number; nome: string; slug: string; icone: string; nucleo: boolean; preco: number }>;
    aoFechar: () => void;
    aoGuardar: (recado: string) => void;
}) {
    const [f, porF] = useState<FichaDoPlano>(VAZIO);
    const [nova, porNova] = useState('');

    const ficha = useQuery({
        queryKey: ['plataforma', 'planos', 'ficha', id],
        queryFn: () => plataforma.planos.ficha(id!),
        enabled: id !== null,
    });

    useEffect(() => {
        if (ficha.data) porF(ficha.data.ficha);
    }, [ficha.data]);

    const guardar = useMutation({
        mutationFn: () => plataforma.planos.guardar(id, {
            name: f.name,
            slug: f.slug,
            description: f.description,
            price_monthly: f.price_monthly,
            price_quarterly: f.price_quarterly,
            price_semiannual: f.price_semiannual,
            price_yearly: f.price_yearly,
            max_users: f.max_users,
            max_companies: f.max_companies,
            max_storage_mb: f.max_storage_mb,
            max_documents: f.max_documents,
            trial_days: f.trial_days,
            order: f.order,
            is_active: f.is_active,
            is_public: f.is_public,
            is_featured: f.is_featured,
            is_promotional: f.is_promotional,
            auto_activate: f.auto_activate,
            features: f.features,
            modulos: f.modulos,
        }),
        onSuccess: (r) => aoGuardar(r.message),
    });

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const mexer = <K extends keyof FichaDoPlano>(campo: K, valor: FichaDoPlano[K]) =>
        porF((a) => ({ ...a, [campo]: valor }));

    const acrescentar = () => {
        const texto = nova.trim();

        if (texto) {
            porF((a) => ({ ...a, features: [...a.features, texto] }));
            porNova('');
        }
    };

    // A SUGESTÃO DOS CICLOS é a mesma conta que o comando de consola fazia:
    // 5% de desconto ao trimestre e 10% ao semestre.
    const sugerir = () => porF((a) => ({
        ...a,
        price_quarterly: Math.round(a.price_monthly * 3 * 0.95),
        price_semiannual: Math.round(a.price_monthly * 6 * 0.9),
        price_yearly: a.price_yearly > 0 ? a.price_yearly : Math.round(a.price_monthly * 12 * 0.85),
    }));

    const marcar = (moduloId: number) => porF((a) => ({
        ...a,
        modulos: a.modulos.includes(moduloId)
            ? a.modulos.filter((m) => m !== moduloId)
            : [...a.modulos, moduloId],
    }));

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={id ? t('Editar plano') : t('Novo plano')}
            subtitulo={t('O preço de cada ciclo, os limites e os módulos que o plano leva')}
            icone="fa-layer-group"
            cor="bom"
            largura="xl"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="bom"
                        tom="solida"
                        icone="fa-floppy-disk"
                        aTrabalhar={guardar.isPending}
                        onClick={() => guardar.mutate()}
                    >
                        {t('Guardar')}
                    </Botao>
                </div>
            }
        >
            {ficha.isPending && id !== null ? (
                <Carregando linhas={8} />
            ) : (
                <div className="space-y-5">
                    <AvisoDeErro erro={guardar.error} />

                    <div className="grid gap-4 sm:grid-cols-3">
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name} className="sm:col-span-2">
                            <input
                                className={entrada}
                                value={f.name}
                                onChange={(e) => mexer('name', e.target.value)}
                            />
                        </Campo>
                        <Campo
                            etiqueta={t('Identificador')}
                            obrigatorio
                            erro={erros.slug}
                            ajuda={t('Minúsculas e hífens. É o que entra nos endereços.')}
                        >
                            <input
                                className={entrada}
                                value={f.slug}
                                onChange={(e) => mexer('slug', e.target.value)}
                            />
                        </Campo>
                    </div>

                    <Campo etiqueta={t('Descrição')} obrigatorio erro={erros.description}>
                        <textarea
                            rows={2}
                            className={cls(entrada, 'h-auto py-2')}
                            value={f.description}
                            onChange={(e) => mexer('description', e.target.value)}
                        />
                    </Campo>

                    {/* OS QUATRO CICLOS. Os dois do meio eram invisíveis aqui e
                        visíveis ao cliente, sempre sem desconto. */}
                    <fieldset className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <legend className="text-sm font-bold text-slate-800">
                                <i className="fas fa-tags mr-1.5 text-emerald-600" aria-hidden="true" />
                                {t('Preço por ciclo de facturação')}
                            </legend>
                            <Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-wand-magic-sparkles" onClick={sugerir}>
                                {t('Sugerir a partir do mensal')}
                            </Botao>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-4">
                            <Campo etiqueta={t('Mensal (Kz)')} obrigatorio erro={erros.price_monthly}>
                                <input
                                    type="number" step="0.01" min="0" className={entrada}
                                    value={f.price_monthly}
                                    onChange={(e) => mexer('price_monthly', Number(e.target.value))}
                                />
                            </Campo>
                            <Campo
                                etiqueta={t('Trimestral (Kz)')}
                                obrigatorio
                                erro={erros.price_quarterly}
                                ajuda={f.price_quarterly <= 0 && f.price_monthly > 0
                                    ? t('A zero, cobra-se 3× o mensal, sem desconto.')
                                    : undefined}
                            >
                                <input
                                    type="number" step="0.01" min="0" className={entrada}
                                    value={f.price_quarterly}
                                    onChange={(e) => mexer('price_quarterly', Number(e.target.value))}
                                />
                            </Campo>
                            <Campo
                                etiqueta={t('Semestral (Kz)')}
                                obrigatorio
                                erro={erros.price_semiannual}
                                ajuda={f.price_semiannual <= 0 && f.price_monthly > 0
                                    ? t('A zero, cobra-se 6× o mensal, sem desconto.')
                                    : undefined}
                            >
                                <input
                                    type="number" step="0.01" min="0" className={entrada}
                                    value={f.price_semiannual}
                                    onChange={(e) => mexer('price_semiannual', Number(e.target.value))}
                                />
                            </Campo>
                            <Campo etiqueta={t('Anual (Kz)')} obrigatorio erro={erros.price_yearly}>
                                <input
                                    type="number" step="0.01" min="0" className={entrada}
                                    value={f.price_yearly}
                                    onChange={(e) => mexer('price_yearly', Number(e.target.value))}
                                />
                            </Campo>
                        </div>
                    </fieldset>

                    <fieldset className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                        <legend className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-ruler mr-1.5 text-orange-500" aria-hidden="true" />
                            {t('Limites')}
                        </legend>
                        <Campo etiqueta={t('Utilizadores')} obrigatorio erro={erros.max_users}>
                            <input
                                type="number" min="1" className={entrada}
                                value={f.max_users}
                                onChange={(e) => mexer('max_users', Number(e.target.value))}
                            />
                        </Campo>
                        <Campo etiqueta={t('Empresas')} obrigatorio erro={erros.max_companies}>
                            <input
                                type="number" min="1" className={entrada}
                                value={f.max_companies}
                                onChange={(e) => mexer('max_companies', Number(e.target.value))}
                            />
                        </Campo>
                        <Campo etiqueta={t('Espaço (MB)')} obrigatorio erro={erros.max_storage_mb}>
                            <input
                                type="number" min="100" className={entrada}
                                value={f.max_storage_mb}
                                onChange={(e) => mexer('max_storage_mb', Number(e.target.value))}
                            />
                        </Campo>
                        <Campo
                            etiqueta={t('Documentos')}
                            erro={erros.max_documents}
                            ajuda={t('Vazio: sem tecto.')}
                        >
                            <input
                                type="number" min="1" className={entrada}
                                value={f.max_documents ?? ''}
                                onChange={(e) => mexer('max_documents', e.target.value === '' ? null : Number(e.target.value))}
                            />
                        </Campo>
                        <Campo etiqueta={t('Dias de ensaio')} obrigatorio erro={erros.trial_days}>
                            <input
                                type="number" min="0" className={entrada}
                                value={f.trial_days}
                                onChange={(e) => mexer('trial_days', Number(e.target.value))}
                            />
                        </Campo>
                        <Campo etiqueta={t('Ordem')} obrigatorio erro={erros.order}>
                            <input
                                type="number" min="0" className={entrada}
                                value={f.order}
                                onChange={(e) => mexer('order', Number(e.target.value))}
                            />
                        </Campo>
                    </fieldset>

                    <fieldset className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <legend className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-toggle-on mr-1.5 text-blue-500" aria-hidden="true" />
                            {t('Estado')}
                        </legend>
                        <Interruptor
                            rotulo={t('Ao serviço')}
                            nota={t('Desligado, ninguém adere.')}
                            valor={f.is_active}
                            aoMudar={(v) => porF((a) => ({ ...a, is_active: v, is_public: v ? a.is_public : false }))}
                        />
                        <Interruptor
                            rotulo={t('Na montra')}
                            nota={t('Aparece na página de preços. Um plano à medida fica fora.')}
                            valor={f.is_public}
                            desligado={!f.is_active}
                            aoMudar={(v) => mexer('is_public', v)}
                        />
                        <Interruptor
                            rotulo={t('Destacado')}
                            nota={t('Sai realçado na página de preços.')}
                            valor={f.is_featured}
                            aoMudar={(v) => mexer('is_featured', v)}
                        />
                        <Interruptor
                            rotulo={t('Promocional')}
                            nota={t('Cada empresa activa-o uma só vez.')}
                            valor={f.is_promotional}
                            aoMudar={(v) => mexer('is_promotional', v)}
                        />
                        <Interruptor
                            rotulo={t('Activa sozinho')}
                            nota={t('A subscrição entra em vigor sem aprovação.')}
                            valor={f.auto_activate}
                            aoMudar={(v) => mexer('auto_activate', v)}
                        />
                    </fieldset>

                    <fieldset>
                        <legend className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-list-check mr-1.5 text-indigo-500" aria-hidden="true" />
                            {t('Funcionalidades (o que se lê na página de preços)')}
                        </legend>

                        <div className="flex gap-2">
                            <input
                                className={entrada}
                                placeholder={t('Escreva uma funcionalidade e prima Enter')}
                                value={nova}
                                onChange={(e) => porNova(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') { e.preventDefault(); acrescentar(); }
                                }}
                            />
                            <Botao cor="primaria" tom="suave" icone="fa-plus" onClick={acrescentar}>
                                {t('Juntar')}
                            </Botao>
                        </div>

                        {f.features.length > 0 && (
                            <ul className="mt-2 space-y-1">
                                {f.features.map((linha, i) => (
                                    <li
                                        key={`${linha}-${i}`}
                                        className="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-sm"
                                    >
                                        <span className="min-w-0 truncate text-slate-700">
                                            <i className="fas fa-check mr-1.5 text-emerald-500" aria-hidden="true" />{linha}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => porF((a) => ({ ...a, features: a.features.filter((_, j) => j !== i) }))}
                                            className={cls('shrink-0 text-red-500 hover:text-red-700', TRANSICAO, FOCO, RAIO)}
                                        >
                                            <i className="fas fa-xmark" aria-hidden="true" />
                                            <span className="sr-only">{t('Retirar')}</span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </fieldset>

                    <fieldset>
                        <legend className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-puzzle-piece mr-1.5 text-purple-500" aria-hidden="true" />
                            {t('Módulos do plano')}
                        </legend>

                        <p className="mb-2 text-xs text-slate-500">
                            {t('Juntar ou retirar um módulo aqui liga-o ou desliga-o em todas as empresas que já têm este plano.')}
                        </p>

                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {modulos.map((m) => (
                                <label
                                    key={m.id}
                                    className={cls(
                                        'flex cursor-pointer items-center gap-2 border px-3 py-2 text-sm', RAIO, TRANSICAO,
                                        f.modulos.includes(m.id)
                                            ? 'border-indigo-300 bg-indigo-50'
                                            : 'border-slate-200 hover:bg-slate-50',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                        checked={f.modulos.includes(m.id)}
                                        onChange={() => marcar(m.id)}
                                    />
                                    <i className={cls('fas text-slate-400', m.icone)} aria-hidden="true" />
                                    <span className="min-w-0 flex-1 truncate font-medium text-slate-800">{m.nome}</span>
                                    {m.nucleo && <Etiqueta cor="neutra">{t('núcleo')}</Etiqueta>}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                </div>
            )}
        </Modal>
    );
}

function Interruptor({ rotulo, nota, valor, aoMudar, desligado = false }: {
    rotulo: string;
    nota: string;
    valor: boolean;
    aoMudar: (v: boolean) => void;
    desligado?: boolean;
}) {
    return (
        <label className={cls(
            'flex cursor-pointer items-start gap-2.5 border px-3 py-2.5', RAIO, TRANSICAO,
            valor ? 'border-emerald-300 bg-emerald-50/60' : 'border-slate-200 hover:bg-slate-50',
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
                <span className="block text-[11px] text-slate-500">{nota}</span>
            </span>
        </label>
    );
}
