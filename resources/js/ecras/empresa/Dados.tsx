import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { empresa as api, type DadosDaEmpresa, type FichaDaEmpresa, type RegimeFiscal } from '@/api/empresa';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * OS DADOS DA EMPRESA.
 *
 * VER E MUDAR SÃO DIREITOS DIFERENTES. Quem só pode ver não leva um botão que
 * o servidor vai recusar — leva uma frase a dizer porquê.
 *
 * O REGIME FISCAL é o campo mais perigoso do sistema: mudá-lo reescreve o
 * imposto por omissão e o regime de TODOS os produtos de uma vez. Por isso as
 * consequências dizem-se antes, e a mudança pede um sim escrito.
 */

const ANGOLA = 'AO';

export default function DadosDaEmpresa() {
    const cache = useQueryClient();

    const [dados, porDados] = useState<DadosDaEmpresa | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');
    const [aviso, porAviso] = useState<string | null>(null);
    const [erro, porErro] = useState<unknown>(null);
    const [aConfirmar, porAConfirmar] = useState(false);

    const ficha = useQuery({ queryKey: ['empresa'], queryFn: () => api.ficha() });

    // A ficha só se copia para o formulário quando chega — e não a cada
    // desenho, senão o que se está a escrever era apagado por baixo da mão.
    useEffect(() => {
        if (ficha.data && dados === null) porDados(ficha.data.data);
    }, [ficha.data, dados]);

    const feito = (m: string, a: string | null = null) => {
        porErro(null);
        porRecado(m);
        porAviso(a);
        porDados(null);
        void cache.invalidateQueries({ queryKey: ['empresa'] });
    };

    const guardar = useMutation({
        mutationFn: (confirmar: boolean) => api.guardar({ ...dados, confirmar_regime: confirmar }),
        onSuccess: (r) => { feito(r.message, r.aviso); porAConfirmar(false); },
        onError: (e) => { porErro(e); porAConfirmar(false); },
    });

    const enviarLogotipo = useMutation({
        mutationFn: (f: File) => api.logotipo(f),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    const tirarLogotipo = useMutation({
        mutationFn: () => api.apagarLogotipo(),
        onSuccess: (r) => feito(r.message),
        onError: porErro,
    });

    if (ficha.isPending || dados === null) return <Carregando linhas={10} />;
    if (ficha.isError) return <AvisoDeErro erro={ficha.error} />;

    const f: FichaDaEmpresa = ficha.data;
    const pode = f.permissoes.editar;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const regimeActual = f.regimes.find((r) => r.valor === f.resumo.regime.chave);
    const regimeEscolhido = f.regimes.find((r) => r.valor === dados.regime);
    const mudouDeRegime = dados.regime !== f.resumo.regime.chave;

    const mudar = (mudanca: Partial<DadosDaEmpresa>) => porDados({ ...dados, ...mudanca });

    const submeter = () => {
        if (mudouDeRegime) {
            porAConfirmar(true);

            return;
        }

        guardar.mutate(false);
    };

    const taxa = (n: number) => `${n.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 2 })}%`;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Dados da Empresa')}
                subtitulo={t('A identificação, os contactos e o regime fiscal que sai nos documentos')}
                icone="fa-building"
                cor="primaria"
                accoes={
                    <>
                        {f.permissoes.definicoes_de_facturacao && (
                            <a href="/invoicing/settings" className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-gear" aria-hidden="true" />
                                {t('Configurações de Faturação')}
                            </a>
                        )}
                        <a href="/my-account" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-user-circle" aria-hidden="true" />
                            {t('A Minha Conta')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-balance-scale">
                        {t('Regime: :regime', { regime: f.resumo.regime.curto })}
                    </EstadoNaFaixa>
                    {f.data.slug && (
                        <EstadoNaFaixa icone="fa-fingerprint">{f.data.slug}</EstadoNaFaixa>
                    )}
                    {!pode && (
                        <EstadoNaFaixa icone="fa-eye">{t('Só de leitura')}</EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            {aviso && (
                <p role="alert" className={cls('border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-900', RAIO)}>
                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />{aviso}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {/*
              * O RESUMO FISCAL — o efeito prático do regime, em números.
              *
              * Um regime não se escolhe no abstracto: escolhe-se a olhar para o
              * imposto que vai sair nos documentos e para quantos produtos vão
              * mudar de mão.
              */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero
                    rotulo={t('Regime actual')} valor={f.resumo.regime.curto} icone="fa-balance-scale" tom="indigo"
                    nota={f.resumo.regime.isento ? t('Sem IVA (isento)') : t('IVA :taxa', { taxa: taxa(f.resumo.regime.taxa) })}
                />
                <CartaoNumero
                    rotulo={t('Imposto por omissão')}
                    valor={f.resumo.imposto?.nome ?? t('Não definido')}
                    icone="fa-percent"
                    tom={f.resumo.falta_configurar_imposto ? 'vermelho' : 'azul'}
                    nota={f.resumo.imposto
                        ? `${taxa(f.resumo.imposto.taxa)} · SAFT ${f.resumo.imposto.saft ?? '—'}${f.resumo.imposto.isencao ? ` · ${f.resumo.imposto.isencao}` : ''}`
                        : t('Configure em Faturação → Configurações')}
                />
                <CartaoNumero
                    rotulo={t('Produtos')} valor={numero(f.resumo.produtos.total)} icone="fa-boxes-stacked" tom="roxo"
                    nota={t(':iva c/ IVA · :isentos isentos', {
                        iva: numero(f.resumo.produtos.com_iva), isentos: numero(f.resumo.produtos.isentos),
                    })}
                />
                <CartaoNumero
                    rotulo={t('Isentos sem motivo')}
                    valor={numero(f.resumo.produtos.isentos_sem_motivo)}
                    icone="fa-triangle-exclamation"
                    tom={f.resumo.produtos.isentos_sem_motivo > 0 ? 'vermelho' : 'verde'}
                    nota={f.resumo.produtos.isentos_sem_motivo > 0
                        ? t('A AGT rejeita documentos isentos sem código')
                        : t('Tudo conforme a AGT')}
                />
            </div>

            {/* ─── Identificação ─────────────────────────────────────── */}

            <Seccao titulo={t('Identificação')} icone="fa-id-card" tom="azul">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Nome comercial')} obrigatorio erro={erros.name}>
                        <input type="text" value={dados.name} disabled={!pode}
                            onChange={(e) => mudar({ name: e.target.value })} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Designação social (nome fiscal)')} erro={erros.company_name}>
                        <input type="text" value={dados.company_name ?? ''} disabled={!pode}
                            onChange={(e) => mudar({ company_name: e.target.value })}
                            placeholder={t('Ex.: Farmácia Exemplo, Lda.')} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('NIF')} obrigatorio erro={erros.nif}
                        ajuda={t('O NIF da EMPRESA — não o do bilhete de identidade de alguém.')}>
                        <input type="text" value={dados.nif ?? ''} disabled={!pode}
                            onChange={(e) => mudar({ nif: e.target.value.toUpperCase() })}
                            className={cls(entrada, 'uppercase')} />
                    </Campo>

                    <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                        <input type="text" value={dados.phone ?? ''} disabled={!pode}
                            onChange={(e) => mudar({ phone: e.target.value })} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('E-mail')} erro={erros.email} className="sm:col-span-2">
                        <input type="email" value={dados.email ?? ''} disabled={!pode}
                            onChange={(e) => mudar({ email: e.target.value })} className={entrada} />
                    </Campo>
                </div>
            </Seccao>

            {/* ─── Morada ────────────────────────────────────────────── */}

            <Seccao titulo={t('Morada')} icone="fa-location-dot" tom="verde">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Morada dados={dados} erros={erros} geografia={f.geografia} podeEditar={pode} aoMudar={mudar} />

                    <Campo etiqueta={t('Morada')} erro={erros.address} className="sm:col-span-2">
                        <input type="text" value={dados.address ?? ''} disabled={!pode}
                            onChange={(e) => mudar({ address: e.target.value })} className={entrada} />
                    </Campo>
                </div>
            </Seccao>

            {/* ─── Regime fiscal ─────────────────────────────────────── */}

            <Seccao
                titulo={t('Regime Fiscal (AGT)')}
                icone="fa-balance-scale"
                tom="ambar"
                aviso={t('Afeta impostos e produtos')}
            >
                <p className="mb-4 text-xs text-slate-500">
                    {t('O regime define a taxa aplicada nos documentos e, na não sujeição, o motivo de isenção obrigatório.')}
                </p>

                <div className="grid gap-3 lg:grid-cols-3">
                    {f.regimes.map((r) => (
                        <RegimeCartao
                            key={r.valor}
                            regime={r}
                            escolhido={dados.regime === r.valor}
                            actual={f.resumo.regime.chave === r.valor}
                            podeEditar={pode}
                            aoEscolher={() => mudar({ regime: r.valor })}
                        />
                    ))}
                </div>

                {erros.regime && (
                    <p className="mt-2 text-xs font-medium text-red-600">{erros.regime[0]}</p>
                )}

                {/*
                  * AS CONSEQUÊNCIAS DIZEM-SE ANTES DE ACONTECEREM.
                  *
                  * Ao guardar, o imposto por omissão e o regime de todos os
                  * produtos mudam de uma vez. Descobrir isso depois é descobri-lo
                  * com as facturas do mês já emitidas à taxa errada.
                  */}
                {mudouDeRegime && regimeEscolhido && regimeActual && (
                    <div className={cls('mt-4 animate-fade-in border-2 border-amber-300 bg-amber-50 p-4', RAIO)}>
                        <div className="flex items-start gap-3">
                            <i className="fas fa-triangle-exclamation mt-0.5 text-amber-600" aria-hidden="true" />
                            <div className="space-y-1.5 text-xs text-amber-900">
                                <p className="font-bold">
                                    {t('Está a mudar de regime: :de → :para', {
                                        de: regimeActual.rotulo, para: regimeEscolhido.rotulo,
                                    })}
                                </p>
                                <p>{t('Ao guardar, o sistema vai aplicar automaticamente:')}</p>
                                <ul className="ml-1 list-inside list-disc space-y-0.5">
                                    {regimeEscolhido.isento ? (
                                        <>
                                            <li>{t('O imposto por omissão passa a Isento 0% (SAFT ISE);')}</li>
                                            <li>{t('Todos os produtos passam a isentos com o motivo :codigo;', {
                                                codigo: regimeEscolhido.codigo_de_isencao ?? '—',
                                            })}</li>
                                            <li>{t('Os documentos deixam de liquidar IVA.')}</li>
                                        </>
                                    ) : (
                                        <>
                                            <li>{t('O imposto por omissão passa a IVA :taxa;', { taxa: taxa(regimeEscolhido.taxa) })}</li>
                                            <li>{t('Os documentos passam a liquidar IVA à taxa do regime;')}</li>
                                            <li>{t('Reveja os produtos que estavam isentos — podem precisar de taxa.')}</li>
                                        </>
                                    )}
                                </ul>
                            </div>
                        </div>
                    </div>
                )}
            </Seccao>

            {/* ─── Logótipo ──────────────────────────────────────────── */}

            <Seccao titulo={t('Logótipo')} icone="fa-image" tom="roxo">
                <div className="flex flex-wrap items-center gap-5">
                    <div className="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-xl border-2 border-dashed border-slate-300 bg-slate-50">
                        {dados.logo ? (
                            <img src={dados.logo} alt={t('Logótipo')} className="h-full w-full object-contain" />
                        ) : (
                            <i className="fas fa-building text-2xl text-slate-300" aria-hidden="true" />
                        )}
                    </div>

                    <div className="min-w-[220px] flex-1">
                        <input type="file" accept="image/*" disabled={!pode || enviarLogotipo.isPending}
                            onChange={(e) => {
                                const ficheiro = e.target.files?.[0];

                                if (ficheiro) enviarLogotipo.mutate(ficheiro);
                            }}
                            className={cls(entrada, 'file:mr-3 file:rounded-lg file:border-0 file:bg-purple-50 file:px-3 file:py-1.5 file:font-bold file:text-purple-700')} />
                        <p className="mt-1.5 text-xs text-slate-400">
                            {t('PNG ou JPG, até 2 MB. Sai nas facturas e nos recibos.')}
                        </p>
                        {enviarLogotipo.isPending && (
                            <p className="mt-1 text-xs text-purple-600">
                                <i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A carregar…')}
                            </p>
                        )}
                    </div>

                    {pode && dados.logo && (
                        <Botao cor="perigo" icone="fa-trash" aTrabalhar={tirarLogotipo.isPending}
                            onClick={() => tirarLogotipo.mutate()}>
                            {t('Remover')}
                        </Botao>
                    )}
                </div>
            </Seccao>

            {/* ─── As acções ─────────────────────────────────────────── */}

            <div className="flex flex-wrap items-center gap-3 pb-2">
                {pode ? (
                    <Botao cor="primaria" tom="solida" icone="fa-floppy-disk"
                        aTrabalhar={guardar.isPending} onClick={submeter}>
                        {t('Guardar alterações')}
                    </Botao>
                ) : (
                    // VER E MUDAR SÃO DIREITOS DIFERENTES: quem só pode ver não
                    // leva um botão que o servidor vai recusar.
                    <p className="text-sm text-slate-500">
                        <i className="fas fa-eye mr-1.5" aria-hidden="true" />
                        {t('Está a ver os dados da empresa. Alterá-los é de quem a gere.')}
                    </p>
                )}

                {f.data.actualizado && (
                    <span className="ml-auto text-xs text-slate-400">
                        {t('Actualizado em :quando', { quando: f.data.actualizado })}
                    </span>
                )}
            </div>

            {/* ─── A confirmação do regime ───────────────────────────── */}

            <Modal
                aberto={aConfirmar}
                aoFechar={() => porAConfirmar(false)}
                titulo={t('Confirmar a mudança de regime')}
                subtitulo={regimeEscolhido?.rotulo}
                icone="fa-shield-halved"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => { porAConfirmar(false); mudar({ regime: f.resumo.regime.chave }); }}>
                            {t('Manter :regime', { regime: f.resumo.regime.curto })}
                        </Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => guardar.mutate(true)}>
                            {t('Sim, alterar e guardar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-600">
                    {t('Os produtos vão ser actualizados em massa e o imposto por omissão muda. Os documentos emitidos até agora não se alteram — só os que forem emitidos daqui para a frente.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

const TONS = {
    azul: 'text-blue-500',
    verde: 'text-emerald-500',
    ambar: 'text-amber-600',
    roxo: 'text-purple-500',
} as const;

function Seccao({ titulo, icone, tom, aviso, children }: {
    titulo: string;
    icone: string;
    tom: keyof typeof TONS;
    aviso?: string;
    children: React.ReactNode;
}) {
    return (
        <section className={cls('overflow-hidden', CARTAO)}>
            <header className="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-3">
                <i className={`fas ${icone} ${TONS[tom]}`} aria-hidden="true" />
                <h2 className="text-sm font-bold text-slate-800">{titulo}</h2>
                {aviso && (
                    <span className="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                        {aviso}
                    </span>
                )}
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

/** Um regime, com o que ele faz e a quem serve. */
function RegimeCartao({ regime, escolhido, actual, podeEditar, aoEscolher }: {
    regime: RegimeFiscal;
    escolhido: boolean;
    actual: boolean;
    podeEditar: boolean;
    aoEscolher: () => void;
}) {
    return (
        <label className={cls(
            'relative flex cursor-pointer flex-col border-2 p-4 transition-all duration-200',
            RAIO,
            escolhido
                ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-100'
                : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm',
            !podeEditar && 'cursor-default opacity-90',
        )}>
            <div className="flex items-start gap-3">
                <input type="radio" name="regime" value={regime.valor} checked={escolhido}
                    disabled={!podeEditar} onChange={aoEscolher}
                    className={cls('mt-0.5 text-indigo-600', FOCO)} />
                <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-800">{regime.rotulo}</p>
                    <p className={cls('mt-0.5 text-[11px] font-semibold', regime.isento ? 'text-orange-600' : 'text-indigo-600')}>
                        {regime.isento
                            ? t('Sem IVA · motivo :codigo', { codigo: regime.codigo_de_isencao ?? '—' })
                            : t('IVA :taxa', { taxa: `${regime.taxa.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 2 })}%` })}
                    </p>
                </div>
            </div>

            <p className="mt-2 text-[11px] leading-relaxed text-slate-600">{regime.descricao}</p>
            <p className="mt-1.5 flex items-start gap-1 text-[11px] text-slate-400">
                <i className="fas fa-chart-line mt-0.5" aria-hidden="true" />
                <span>{regime.facturacao}</span>
            </p>

            {actual && (
                <span className="absolute right-2 top-2">
                    <Etiqueta cor="bom">{t('Actual')}</Etiqueta>
                </span>
            )}
        </label>
    );
}

/**
 * PAÍS → PROVÍNCIA → MUNICÍPIO → BAIRRO, e é o país que manda.
 *
 * A mesma cascata do resto do sistema: em Angola a divisão administrativa
 * escolhe-se de listas que VÊM DO SERVIDOR, fora de Angola não há divisão que
 * se possa impor e escreve-se. As listas vêm de lá de propósito — as províncias
 * já estiveram escritas à mão em cinco sítios e acabaram diferentes.
 */
function Morada({ dados, erros, geografia, podeEditar, aoMudar }: {
    dados: DadosDaEmpresa;
    erros: Record<string, string[]>;
    geografia: FichaDaEmpresa['geografia'];
    podeEditar: boolean;
    aoMudar: (d: Partial<DadosDaEmpresa>) => void;
}) {
    const ehAngola = dados.country === ANGOLA;
    const municipios = geografia.municipios[dados.province] ?? [];
    const bairros = geografia.bairros[dados.municipality] ?? [];

    return (
        <>
            {/* O país é um código ISO de duas letras porque é assim que viaja
                para a AGT. E vem primeiro porque é ele que decide se a morada
                tem divisão para escolher. */}
            <Campo etiqueta={t('País')} obrigatorio erro={erros.country}>
                <select value={dados.country} disabled={!podeEditar}
                    onChange={(e) => {
                        // Sair de Angola (ou voltar a ela) esvazia a divisão:
                        // uma província angolana não é um estado brasileiro, e
                        // deixá-la lá gravava uma morada que não existe.
                        const trocaDeMundo = (e.target.value === ANGOLA) !== ehAngola;

                        aoMudar({
                            country: e.target.value,
                            ...(trocaDeMundo ? { province: '', municipality: '', neighbourhood: '', city: '' } : {}),
                        });
                    }}
                    className={entrada}>
                    {Object.entries(geografia.paises).map(([codigo, nome]) => (
                        <option key={codigo} value={codigo}>{nome} ({codigo})</option>
                    ))}
                </select>
            </Campo>

            <Campo etiqueta={t('Código postal')} erro={erros.postal_code}>
                <input type="text" value={dados.postal_code ?? ''} disabled={!podeEditar}
                    onChange={(e) => aoMudar({ postal_code: e.target.value })} className={entrada} />
            </Campo>

            {ehAngola ? (
                <>
                    <Campo etiqueta={t('Província')} erro={erros.province}>
                        <select value={dados.province} disabled={!podeEditar}
                            // Trocar de província deixa cair o município e o
                            // bairro: um município da Huíla não pertence a
                            // Luanda, e ficava lá calado até ao SAFT.
                            onChange={(e) => aoMudar({
                                province: e.target.value, municipality: '', neighbourhood: '', city: '',
                            })}
                            className={entrada}>
                            <option value="">{t('Escolha a província…')}</option>
                            {geografia.provincias.map((p) => (
                                <option key={p} value={p}>
                                    {p}{geografia.provincias_novas.includes(p) ? ` · ${t('nova em 2024')}` : ''}
                                </option>
                            ))}
                            {/* Um valor gravado antes da reforma de 2024
                                continua a aparecer, em vez de o selector o
                                deitar fora em silêncio ao gravar. */}
                            {dados.province && !geografia.provincias.includes(dados.province) && (
                                <option value={dados.province}>{dados.province} · {t('divisão anterior')}</option>
                            )}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Município')} erro={erros.municipality}>
                        <select value={dados.municipality} disabled={!podeEditar || !dados.province}
                            onChange={(e) => aoMudar({ municipality: e.target.value, neighbourhood: '', city: '' })}
                            className={entrada}>
                            <option value="">
                                {dados.province ? t('Escolha o município…') : t('Escolha primeiro a província')}
                            </option>
                            {municipios.map((m) => <option key={m} value={m}>{m}</option>)}
                            {dados.municipality && !municipios.includes(dados.municipality) && (
                                <option value={dados.municipality}>{dados.municipality}</option>
                            )}
                        </select>
                    </Campo>

                    {/* SUGERE, NÃO FECHA. Angola não tem registo nacional de
                        bairros: nascem, mudam de nome e raramente entram numa
                        lista oficial. */}
                    <Campo etiqueta={t('Bairro')} erro={erros.neighbourhood} className="sm:col-span-2">
                        <input type="text" value={dados.neighbourhood} disabled={!podeEditar}
                            onChange={(e) => aoMudar({ neighbourhood: e.target.value })}
                            list="bairros-da-empresa"
                            placeholder={t('Bairro ou zona — pode escrever outro')}
                            className={entrada} autoComplete="off" />
                        <datalist id="bairros-da-empresa">
                            {bairros.map((b) => <option key={b} value={b} />)}
                        </datalist>
                    </Campo>
                </>
            ) : (
                <>
                    <Campo etiqueta={t('Província / Estado')} erro={erros.province}>
                        <input type="text" value={dados.province} disabled={!podeEditar}
                            onChange={(e) => aoMudar({ province: e.target.value })}
                            placeholder={t('Província, estado ou região')} className={entrada} autoComplete="off" />
                    </Campo>

                    <Campo etiqueta={t('Cidade')} erro={erros.city}>
                        <input type="text" value={dados.city ?? ''} disabled={!podeEditar}
                            onChange={(e) => aoMudar({ city: e.target.value })} className={entrada} autoComplete="off" />
                    </Campo>
                </>
            )}
        </>
    );
}
