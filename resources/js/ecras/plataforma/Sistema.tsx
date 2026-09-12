import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { definicoes } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { CARTAO, RAIO, cls } from '@/ui/tokens';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ErroDoEcra, Interruptor, Recado, SemEfeito } from './comum';

type Valores = Record<string, string | boolean>;

/**
 * AS DEFINIÇÕES DO SISTEMA — o nome, a imagem, o SEO e a página pública.
 *
 * O QUE O ECRÃ AGORA DIZ: dez destes campos gravam-se e nenhuma parte do
 * sistema os lê (o modo de manutenção, fechar o registo, verificar o email, as
 * cores, a versão e as redes sociais). O ecrã antigo deixava crer que desligar o
 * registo fechava o registo. Continuam cá, com o aviso ao lado.
 */
export default function Sistema() {
    const fila = useQueryClient();
    const [aba, porAba] = useState('geral');
    const [recado, porRecado] = useState<string | null>(null);
    const [v, porV] = useState<Valores>({});

    const dados = useQuery({ queryKey: ['plataforma', 'sistema'], queryFn: definicoes.sistema.ler });

    useEffect(() => { if (dados.data) porV(dados.data.valores); }, [dados.data]);

    const guardar = useMutation({
        mutationFn: (grupo: string) => definicoes.sistema.guardar(grupo, v),
        onSuccess: (r) => { porRecado(r.message); void fila.invalidateQueries({ queryKey: ['plataforma', 'sistema'] }); },
    });

    if (dados.isPending) return <Carregando linhas={10} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir as definições do sistema')} erro={dados.error} />;

    const d = dados.data;
    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const semEfeito = (c: string) => d.sem_efeito.includes(c);
    const texto = (c: string) => String(v[c] ?? '');
    const mexer = (c: string, valor: string | boolean) => porV((a) => ({ ...a, [c]: valor }));

    // O contexto das peças do formulário. As peças vivem fora do componente:
    // definidas cá dentro, eram um componente novo a cada tecla — o campo
    // perdia o foco a meio de escrever.
    const ctx: Ctx = { v, erros, mexer, semEfeito, guardar: (g) => guardar.mutate(g), aGuardar: guardar.isPending ? (guardar.variables ?? null) : null };

    const falhas = d.auditoria.verificacoes.filter((x) => !x.ok).length;

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Definições do sistema')} subtitulo={t('O nome, a imagem, o SEO e a página pública')} icone="fa-sliders" cor="roxo">
                <EstadoNaFaixa icone={falhas === 0 ? 'fa-circle-check' : 'fa-magnifying-glass-chart'}>
                    {falhas === 0 ? t('A página pública passa em todas as verificações') : t(':n verificação(ões) de SEO por resolver', { n: falhas })}
                </EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={guardar.error} />

            <div className={cls(CARTAO, 'p-4')}>
                <Separadores
                    abas={[
                        { chave: 'geral', rotulo: t('Geral'), icone: 'fa-circle-info', erros: contar(erros, ['app_name', 'app_description', 'app_version', 'app_url', 'contact_email', 'contact_phone']) },
                        { chave: 'aparencia', rotulo: t('Aparência'), icone: 'fa-palette' },
                        { chave: 'seo', rotulo: t('SEO'), icone: 'fa-magnifying-glass', erros: contar(erros, ['seo_title', 'seo_description', 'seo_canonical_url']) },
                        { chave: 'funcionalidades', rotulo: t('Funcionalidades'), icone: 'fa-toggle-on' },
                        { chave: 'social', rotulo: t('Redes sociais'), icone: 'fa-share-nodes', erros: contar(erros, ['facebook_url', 'instagram_url', 'twitter_url', 'linkedin_url']) },
                        { chave: 'schema', rotulo: 'Schema.org', icone: 'fa-code', erros: contar(erros, ['schema_rating_value', 'schema_review_count', 'schema_app_url', 'schema_creator_url']) },
                        { chave: 'auditoria', rotulo: t('Auditoria SEO'), icone: 'fa-clipboard-check' },
                    ]}
                    activa={aba}
                    aoMudar={porAba}
                />

                <div className="pt-5">
                    <PainelDoSeparador chave="geral" activa={aba}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Linha ctx={ctx} chave="app_name" rotulo={t('Nome da aplicação')} />
                            <Linha ctx={ctx} chave="app_version" rotulo={t('Versão')} />
                            <Campo etiqueta={t('Descrição')} erro={erros.app_description} className="md:col-span-2">
                                <textarea rows={2} className={cls(entrada, 'h-auto py-2')} value={texto('app_description')} onChange={(e) => mexer('app_description', e.target.value)} />
                            </Campo>
                            <Linha ctx={ctx} chave="app_url" rotulo={t('Endereço da aplicação')} tipo="url" placeholder="https://soserp.vip" />
                            <Linha ctx={ctx} chave="contact_email" rotulo={t('Email de contacto')} tipo="email" />
                            <Linha ctx={ctx} chave="contact_phone" rotulo={t('Telefone de contacto')} />
                        </div>
                        <Guardar ctx={ctx} grupo="geral" />
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="aparencia" activa={aba}>
                        <div className="grid gap-6 md:grid-cols-2">
                            <Imagem chave="app_logo" rotulo={t('Logótipo')} nota={t('PNG, JPG ou SVG. Recomendado: 200 × 60 px.')} url={d.imagens.app_logo ?? null} aoMudar={(m) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'sistema'] }); }} />
                            <Imagem chave="app_favicon" rotulo={t('Ícone do separador')} nota={t('ICO ou PNG. Recomendado: 32 × 32 ou 64 × 64 px.')} url={d.imagens.app_favicon ?? null} aoMudar={(m) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'sistema'] }); }} />
                            {(['primary_color', 'secondary_color'] as const).map((c) => (
                                <Campo key={c} etiqueta={c === 'primary_color' ? t('Cor primária') : t('Cor secundária')} erro={erros[c]} ajuda={<SemEfeito />}>
                                    <div className="flex items-center gap-3">
                                        <input type="color" className="h-10 w-16 cursor-pointer rounded-lg border border-slate-300" value={texto(c) || '#4F46E5'} onChange={(e) => mexer(c, e.target.value.toUpperCase())} />
                                        <input className={cls(entrada, 'font-mono uppercase')} value={texto(c)} onChange={(e) => mexer(c, e.target.value)} />
                                        <span className="h-10 w-24 shrink-0 rounded-lg shadow-inner" style={{ background: texto(c) }} />
                                    </div>
                                </Campo>
                            ))}
                        </div>
                        <Guardar ctx={ctx} grupo="aparencia" />
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="seo" activa={aba}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Campo etiqueta={t('Título')} erro={erros.seo_title} className="md:col-span-2" ajuda={t(':n caracteres — o Google mostra 50 a 60.', { n: texto('seo_title').length })}>
                                <input className={entrada} maxLength={120} value={texto('seo_title')} onChange={(e) => mexer('seo_title', e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('Descrição')} erro={erros.seo_description} className="md:col-span-2" ajuda={t(':n caracteres — o Google mostra 150 a 160.', { n: texto('seo_description').length })}>
                                <textarea rows={3} maxLength={320} className={cls(entrada, 'h-auto py-2')} value={texto('seo_description')} onChange={(e) => mexer('seo_description', e.target.value)} />
                            </Campo>

                            {/* COMO O GOOGLE O MOSTRA — a pré-visualização do resultado. */}
                            <div className={cls('border border-slate-200 bg-white p-4 md:col-span-2', RAIO)}>
                                <p className="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">{t('No Google')}</p>
                                <p className="truncate text-lg text-blue-800">{texto('seo_title') || texto('app_name')}</p>
                                <p className="truncate text-sm text-emerald-700">{texto('seo_canonical_url') || texto('app_url') || 'https://soserp.vip'}</p>
                                <p className="line-clamp-2 text-sm text-slate-600">{texto('seo_description') || t('Sem descrição.')}</p>
                            </div>

                            <Campo etiqueta={t('Palavras-chave')} erro={erros.seo_keywords} className="md:col-span-2" ajuda={t('Separadas por vírgulas.')}>
                                <input className={entrada} value={texto('seo_keywords')} onChange={(e) => mexer('seo_keywords', e.target.value)} />
                            </Campo>
                            <Linha ctx={ctx} chave="seo_author" rotulo={t('Autor')} />
                            <Campo etiqueta={t('Robôs')} erro={erros.seo_robots}>
                                <select className={entrada} value={texto('seo_robots') || 'index, follow'} onChange={(e) => mexer('seo_robots', e.target.value)}>
                                    {['index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow'].map((o) => <option key={o} value={o}>{o}</option>)}
                                </select>
                            </Campo>
                            <Linha ctx={ctx} chave="seo_canonical_url" rotulo={t('Endereço canónico')} tipo="url" largo />
                            <Imagem chave="seo_og_image" rotulo={t('Imagem para partilhas (og:image)')} nota={t('Recomendado: 1200 × 630 px.')} url={d.imagens.seo_og_image ?? null} aoMudar={(m) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'sistema'] }); }} />
                            <div className="grid gap-4">
                                <Linha ctx={ctx} chave="google_analytics_id" rotulo="Google Analytics" placeholder="G-XXXXXXX" />
                                <Linha ctx={ctx} chave="gtm_id" rotulo="Google Tag Manager" placeholder="GTM-XXXXXX" />
                                <Linha ctx={ctx} chave="facebook_pixel_id" rotulo="Facebook Pixel" />
                            </div>
                            <Linha ctx={ctx} chave="google_site_verification" rotulo={t('Verificação do Google')} />
                            <Linha ctx={ctx} chave="bing_site_verification" rotulo={t('Verificação do Bing')} />
                        </div>
                        <Guardar ctx={ctx} grupo="seo" />
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="funcionalidades" activa={aba}>
                        <div className="grid gap-3 md:grid-cols-3">
                            <Interruptor rotulo={t('Registo de empresas aberto')} nota={t('Quem visita a página pode criar conta.')} valor={Boolean(v.enable_registration)} aoMudar={(x) => mexer('enable_registration', x)} semEfeito={semEfeito('enable_registration')} />
                            <Interruptor rotulo={t('Verificação de email')} nota={t('Pedir a confirmação do email ao registar.')} valor={Boolean(v.enable_email_verification)} aoMudar={(x) => mexer('enable_email_verification', x)} semEfeito={semEfeito('enable_email_verification')} cor="indigo" />
                            <Interruptor rotulo={t('Modo de manutenção')} nota={t('Fechar o sistema às empresas enquanto se trabalha.')} valor={Boolean(v.maintenance_mode)} aoMudar={(x) => mexer('maintenance_mode', x)} semEfeito={semEfeito('maintenance_mode')} cor="red" />
                        </div>
                        <Guardar ctx={ctx} grupo="funcionalidades" />
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="social" activa={aba}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Linha ctx={ctx} chave="facebook_url" rotulo="Facebook" tipo="url" />
                            <Linha ctx={ctx} chave="instagram_url" rotulo="Instagram" tipo="url" />
                            <Linha ctx={ctx} chave="twitter_url" rotulo="X (Twitter)" tipo="url" />
                            <Linha ctx={ctx} chave="linkedin_url" rotulo="LinkedIn" tipo="url" />
                        </div>
                        <Guardar ctx={ctx} grupo="social" />
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="schema" activa={aba}>
                        <div className="space-y-4">
                            <p className={cls('border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900', RAIO)}>
                                <i className="fas fa-circle-info mr-2" aria-hidden="true" />
                                {t('É o que o Google lê para mostrar a aplicação nos resultados. A avaliação e o número de avaliações vão juntos, ou nenhum: em branco, a página não declara avaliação nenhuma.')}
                            </p>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Linha ctx={ctx} chave="schema_app_name" rotulo={t('Nome')} />
                                <Linha ctx={ctx} chave="schema_app_category" rotulo={t('Categoria')} placeholder="BusinessApplication" />
                                <Campo etiqueta={t('Descrição')} erro={erros.schema_app_description} className="md:col-span-2">
                                    <textarea rows={2} className={cls(entrada, 'h-auto py-2')} value={texto('schema_app_description')} onChange={(e) => mexer('schema_app_description', e.target.value)} />
                                </Campo>
                                <Linha ctx={ctx} chave="schema_app_url" rotulo={t('Endereço')} tipo="url" />
                                <Linha ctx={ctx} chave="schema_region" rotulo={t('Região')} />
                                <Linha ctx={ctx} chave="schema_price" rotulo={t('Preço a partir de')} tipo="number" />
                                <Linha ctx={ctx} chave="schema_currency" rotulo={t('Moeda (ISO)')} placeholder="AOA" />
                                <Linha ctx={ctx} chave="schema_rating_value" rotulo={t('Avaliação (1 a 5)')} tipo="number" ajuda={t('Só se houver avaliações reais recolhidas.')} />
                                <Linha ctx={ctx} chave="schema_review_count" rotulo={t('Número de avaliações')} tipo="number" />
                                <Linha ctx={ctx} chave="schema_creator_name" rotulo={t('Criador')} />
                                <Linha ctx={ctx} chave="schema_creator_url" rotulo={t('Endereço do criador')} tipo="url" />
                            </div>
                        </div>
                        <Guardar ctx={ctx} grupo="schema" />
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="auditoria" activa={aba}>
                        <div className="space-y-5">
                            <div className="grid gap-3 md:grid-cols-3">
                                {Object.entries(d.auditoria.ficheiros).map(([nome, x]) => (
                                    <article key={nome} className={cls(CARTAO, 'card-hover p-4')}>
                                        <div className="flex items-center justify-between gap-2">
                                            <a href={x.url} target="_blank" rel="noreferrer" className="font-mono font-bold text-indigo-700 hover:underline">{x.ficheiro}</a>
                                            <Etiqueta cor={x.existe ? 'bom' : 'perigo'} ponto>{x.existe ? t('Existe') : t('Falta')}</Etiqueta>
                                        </div>
                                        {x.existe && (
                                            <p className="mt-2 text-xs text-slate-500">
                                                {t(':n bytes · alterado a :dia', { n: x.tamanho, dia: x.alterado ?? '—' })}
                                                {nome === 'sitemap' && <> · {t(':n endereço(s)', { n: x.enderecos ?? 0 })}</>}
                                            </p>
                                        )}
                                        {x.amostra && <pre className="mt-2 max-h-32 overflow-auto rounded-lg bg-slate-900 p-2 text-[10px] text-slate-200">{x.amostra}</pre>}
                                    </article>
                                ))}
                            </div>

                            <div>
                                <h4 className="mb-2 text-sm font-bold text-slate-800">{t('A página pública')}</h4>
                                <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                    {d.auditoria.verificacoes.map((x) => (
                                        <li key={x.chave} className={cls('flex items-center gap-2 border px-3 py-2 text-sm', RAIO, x.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                                            <i className={cls('fas', x.ok ? 'fa-circle-check' : 'fa-circle-xmark')} aria-hidden="true" />{x.rotulo}
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div>
                                <h4 className="mb-2 text-sm font-bold text-slate-800">{t('Dados estruturados (JSON-LD)')}</h4>
                                <div className="flex flex-wrap gap-2">
                                    {d.auditoria.esquemas.length === 0 ? <span className="text-sm text-slate-500">{t('Nenhum.')}</span> : d.auditoria.esquemas.map((s) => <Etiqueta key={s} cor="primaria" icone="fa-code">{s}</Etiqueta>)}
                                </div>
                            </div>
                        </div>
                    </PainelDoSeparador>
                </div>
            </div>
        </div>
    );
}

type Ctx = {
    v: Valores;
    erros: Record<string, string[]>;
    mexer: (c: string, valor: string | boolean) => void;
    semEfeito: (c: string) => boolean;
    guardar: (grupo: string) => void;
    aGuardar: string | null;
};

function Linha({ ctx, chave, rotulo, tipo = 'text', ajuda, largo = false, placeholder }: {
    ctx: Ctx; chave: string; rotulo: string; tipo?: string; ajuda?: string; largo?: boolean; placeholder?: string;
}) {
    return (
        <Campo etiqueta={rotulo} erro={ctx.erros[chave]} className={largo ? 'md:col-span-2' : undefined}
            ajuda={ctx.semEfeito(chave) ? <SemEfeito /> : ajuda}>
            <input type={tipo} className={entrada} value={String(ctx.v[chave] ?? '')} placeholder={placeholder} onChange={(e) => ctx.mexer(chave, e.target.value)} />
        </Campo>
    );
}

function Guardar({ ctx, grupo }: { ctx: Ctx; grupo: string }) {
    return (
        <div className="flex justify-end border-t border-slate-100 pt-4">
            <Botao cor="bom" tom="solida" icone="fa-floppy-disk" aTrabalhar={ctx.aGuardar === grupo} onClick={() => ctx.guardar(grupo)}>
                {t('Guardar')}
            </Botao>
        </div>
    );
}

function contar(erros: Record<string, string[]>, chaves: string[]): number {
    return chaves.filter((c) => erros[c]).length;
}

function Imagem({ chave, rotulo, nota, url, aoMudar }: {
    chave: string; rotulo: string; nota: string; url: string | null; aoMudar: (m: string) => void;
}) {
    const campo = useRef<HTMLInputElement>(null);
    const enviar = useMutation({ mutationFn: (f: File) => definicoes.sistema.enviarImagem(chave, f), onSuccess: (r) => aoMudar(r.message) });
    const erros = enviar.error instanceof ErroDaApi ? enviar.error.erros : {};

    return (
        <Campo etiqueta={rotulo} erro={erros.ficheiro} ajuda={nota}>
            <div className="flex items-center gap-4">
                <span className="grid h-20 w-32 shrink-0 place-items-center overflow-hidden rounded-xl border border-dashed border-slate-300 bg-slate-50">
                    {url ? <img src={url} alt="" className="max-h-full max-w-full object-contain" /> : <i className="fas fa-image text-2xl text-slate-300" aria-hidden="true" />}
                </span>
                <input ref={campo} type="file" accept="image/*,.ico" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) enviar.mutate(f); e.target.value = ''; }} />
                <Botao cor="primaria" tom="suave" icone="fa-upload" aTrabalhar={enviar.isPending} onClick={() => campo.current?.click()}>
                    {url ? t('Trocar') : t('Enviar')}
                </Botao>
            </div>
        </Campo>
    );
}
