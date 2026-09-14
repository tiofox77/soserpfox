import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    agt,
    lerConsulta,
    lerLigacao,
    type Ambiente,
    type DetalheDaSincronizacao,
    type ErroDaAgt,
    type EstadoDaAgt,
    type EstadoDaSerie,
    type ItemDeProntidao,
    type OpcoesDaAgt,
    type PendentesPorAmbiente,
    type ResultadoDaLigacao,
    type SerieDaAgt,
    type Submissao,
} from '@/api/agt';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { useRecadoComTomNoCanto } from '@/ui/useRecadoNoCanto';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, RAIO_GRANDE, cls, type Cor } from '@/ui/tokens';
import { t, tPartes } from '@/i18n';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa, SemNada, cascata } from './faixa';
import {
    AvisoDeCaeEmFalta,
    AvisoDeComunicacaoAgt,
    InterruptoresDaAgt,
    ListaDeRequisitos,
    OpcoesDoCae,
    PainelDaLigacao,
    SemCredenciaisDoProdutor,
    itensDeProntidao,
} from './pecasDaAgt';

/**
 * AS CONFIGURAÇÕES AGT — os dois ambientes em separado.
 *
 * Há o ambiente ACTIVO, o que assina e transmite os documentos reais, e há
 * o que se está a VER. Ver produção não põe a empresa em produção: só o
 * botão «Passar a emitir aqui» o faz, e só com as chaves instaladas. Tudo o
 * que fala com a AGT é do servidor (`GestaoAgt`), o mesmo que o ecrã de
 * sempre usa.
 */
type Separador = 'definicoes' | 'chaves' | 'series' | 'submissoes' | 'logs' | 'consulta';

const SEPARADORES: Array<{ chave: Separador; rotulo: string; icone: string }> = [
    { chave: 'definicoes', rotulo: t('Definições'), icone: 'fa-sliders' },
    { chave: 'chaves', rotulo: t('Chaves'), icone: 'fa-key' },
    { chave: 'series', rotulo: t('Séries'), icone: 'fa-hashtag' },
    { chave: 'submissoes', rotulo: t('Submissões'), icone: 'fa-paper-plane' },
    { chave: 'logs', rotulo: t('Comunicações'), icone: 'fa-list' },
    { chave: 'consulta', rotulo: t('Consultar a AGT'), icone: 'fa-magnifying-glass' },
];

const ESTADOS: Record<string, { rotulo: string; cor: Cor; icone: string }> = {
    pending: { rotulo: t('Pendente'), cor: 'aviso', icone: 'fa-clock' },
    submitted: { rotulo: t('Enviada'), cor: 'primaria', icone: 'fa-paper-plane' },
    validated: { rotulo: t('Validada'), cor: 'bom', icone: 'fa-circle-check' },
    rejected: { rotulo: t('Rejeitada'), cor: 'perigo', icone: 'fa-circle-xmark' },
    cancelled: { rotulo: t('Anulada'), cor: 'neutra', icone: 'fa-ban' },
};

/**
 * O ESTADO DE UMA SÉRIE NA AGT, com a cor do que quer dizer.
 *
 * A quarta é a que evita o alarme falso: uma série de proformas não é fiscal e
 * nunca se regista — âmbar a dizer «por registar» mandava alguém procurar um
 * registo que não vai existir.
 */
const ESTADOS_DA_SERIE: Record<EstadoDaSerie, { rotulo: string; cor: Cor; icone: string }> = {
    registada: { rotulo: t('Registada'), cor: 'bom', icone: 'fa-shield' },
    por_registar: { rotulo: t('Por registar'), cor: 'aviso', icone: 'fa-clock' },
    rejeitada: { rotulo: t('Rejeitada'), cor: 'perigo', icone: 'fa-circle-xmark' },
    nao_aplicavel: { rotulo: t('Não aplicável'), cor: 'neutra', icone: 'fa-minus-circle' },
};

/**
 * AS OPERAÇÕES DA CONSULTA, com o que cada uma devolve MESMO.
 *
 * «Listar facturas», sozinho, leva a crer que lista as que a empresa EMITIU.
 * Lista as RECEBIDAS — a empresa como adquirente — e quem procurava ali as
 * suas vendas concluía que a AGT não tinha nenhuma.
 */
const ROTULOS_DAS_OPERACOES: Record<string, string> = {
    listarFacturas: t('ListarFacturas — documentos RECEBIDOS (a empresa como adquirente)'),
    consultarFactura: t('ConsultarFactura — consultar um documento emitido'),
    obterEstado: t('ObterEstado — estado de uma submissão'),
};

/** As submissões por concluir num ambiente, venham elas pela chave do ambiente ou pelo nome. */
const pendentesDe = (p: PendentesPorAmbiente | undefined | null, ambiente: Ambiente): number => {
    const n = p?.[ambiente] ?? p?.[ambiente === 'production' ? 'producao' : 'homologacao'];

    return typeof n === 'number' ? n : 0;
};

export default function Agt() {
    const [empresa, porEmpresa] = useState<number | undefined>(undefined);
    const [ambiente, porAmbiente] = useState<Ambiente | null>(null);
    const [separador, porSeparador] = useState<Separador>('definicoes');

    const opcoes = useQuery({ queryKey: ['agt', 'opcoes'], queryFn: agt.opcoes, staleTime: 5 * 60_000 });
    // Trocar de ambiente mantém o ecrã de pé com o que já lá estava até chegar o novo.
    const estado = useQuery({
        queryKey: ['agt', 'estado', ambiente ?? 'activo', empresa ?? 0],
        queryFn: () => agt.estado(ambiente, empresa),
        enabled: opcoes.isSuccess,
        placeholderData: keepPreviousData,
        // Um 403 não passa com uma segunda tentativa: é o super admin sem empresa, e esperar por ele só atrasa o selector.
        retry: (vezes, erro) => !(erro instanceof ErroDaApi && erro.estado === 403) && vezes < 1,
    });

    const escolher = (id: number | undefined) => { porEmpresa(id); porAmbiente(null); };

    if (opcoes.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError) return <NaoAbriu erro={opcoes.error} />;

    /*
     * O SUPER ADMIN SEM EMPRESA ACTIVA não tem erro nenhum — tem uma escolha
     * por fazer. O servidor responde 403 porque não sabe de que empresa se
     * fala; o ecrã de sempre mostrava o selector e dizia isso mesmo, em vez de
     * uma caixa vermelha a sugerir falta de permissão.
     */
    if (estado.isError && !empresa && opcoes.data.permissoes.escolhe_empresa && estado.error instanceof ErroDaApi && estado.error.estado === 403) {
        return <SemEmpresa o={opcoes.data} aoEscolher={escolher} />;
    }

    if (estado.isPending) return <Carregando linhas={10} />;
    if (estado.isError) return <NaoAbriu erro={estado.error} />;

    return (
        <Painel
            key={`${estado.data.empresa.id}-${estado.data.ambiente}`}
            o={opcoes.data}
            e={estado.data}
            aActualizar={estado.isFetching}
            aoActualizar={() => void estado.refetch()}
            empresa={empresa}
            separador={separador}
            porSeparador={porSeparador}
            aoEscolherEmpresa={escolher}
            aoVer={porAmbiente}
        />
    );
}

function NaoAbriu({ erro }: { erro: unknown }) {
    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a configuração AGT')}</h2>
            <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
        </div>
    );
}

function CabecalhoDaAgt({ accoes }: { accoes?: React.ReactNode }) {
    return (
        <Faixa
            icone="fa-file-signature"
            cor="laranja"
            titulo={t('AGT Angola')}
            subtitulo={t('Decreto Presidencial n.º 71/25 — Sistema de Facturação Electrónica')}
            accoes={accoes}
        />
    );
}

function EscolhaDeEmpresa({ o, valor, aoEscolher, vazia }: { o: OpcoesDaAgt; valor: number | undefined; aoEscolher: (id: number | undefined) => void; vazia?: boolean }) {
    return (
        <label className="text-sm"><span className="sr-only">{t('Empresa')}</span>
            <select value={valor ?? ''} onChange={(ev) => aoEscolher(Number(ev.target.value) || undefined)} className={entrada} aria-label={t('Empresa')}>
                {vazia && <option value="">{t('Seleccione uma empresa')}</option>}
                {o.empresas.map((x) => <option key={x.id} value={x.id}>{x.nome}{x.nif ? ` · ${x.nif}` : ''}</option>)}
            </select>
        </label>
    );
}

function SemEmpresa({ o, aoEscolher }: { o: OpcoesDaAgt; aoEscolher: (id: number | undefined) => void }) {
    return (
        <div className="space-y-4">
            <CabecalhoDaAgt />
            <Cartao titulo={t('Contexto de operação')} icone="fa-building" accoes={<EscolhaDeEmpresa o={o} valor={undefined} aoEscolher={aoEscolher} vazia />}>
                <div role="status" data-sem-empresa className={cls('animate-fade-in flex items-start gap-4 border border-blue-200 bg-blue-50 p-5', RAIO_GRANDE)}>
                    <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-blue-100 text-blue-500">
                        <i className="fas fa-building" aria-hidden="true" />
                    </span>
                    <div>
                        <h3 className="text-sm font-bold text-blue-800">{t('Nenhuma empresa seleccionada')}</h3>
                        <p className="mt-1 text-sm leading-relaxed text-blue-700">
                            {t('Para configurar e testar as opções AGT, seleccione uma empresa no selector acima. As credenciais da API são geridas globalmente pelo produtor. Seleccione uma empresa para configurar apenas as chaves pública e privada obtidas no Portal AGT dessa empresa.')}
                        </p>
                    </div>
                </div>
            </Cartao>
        </div>
    );
}

type Recado = { tipo: 'bom' | 'mau'; texto: string; aviso?: boolean };

function Painel({ o, e, aActualizar, aoActualizar, empresa, separador, porSeparador, aoEscolherEmpresa, aoVer }: {
    o: OpcoesDaAgt; e: EstadoDaAgt; aActualizar: boolean; aoActualizar: () => void; empresa: number | undefined;
    separador: Separador; porSeparador: (s: Separador) => void;
    aoEscolherEmpresa: (id: number | undefined) => void; aoVer: (a: Ambiente) => void;
}) {
    const cache = useQueryClient();
    const [recado, porRecado] = useRecadoComTomNoCanto<Recado>();
    const [aConfirmar, porAConfirmar] = useState(false);

    const aVer = e.ambiente;
    const activo = e.definicoes.agt_environment;
    const noActivo = aVer === activo;
    const podeEditar = o.permissoes.pode_editar;
    const paraProducao = aVer === 'production';

    const feito = (texto: string) => { porRecado({ tipo: 'bom', texto }); void cache.invalidateQueries({ queryKey: ['agt', 'estado'] }); };
    const falhou = (erro: unknown) => porRecado({ tipo: 'mau', texto: typeof erro === 'string' ? erro : erro instanceof ErroDaApi ? erro.message : t('Não foi possível concluir.') });

    const activar = useMutation({
        mutationFn: () => agt.activarAmbiente(aVer, empresa),
        onSuccess: (r) => {
            porAConfirmar(false);
            feito(r.message);

            // As submissões do ambiente que se deixa não andam enquanto se emite no outro.
            const deixadas = pendentesDe(r.pendentes_por_ambiente, activo);
            if (deixadas > 0) {
                porRecado({ tipo: 'bom', aviso: true, texto: t('Ficam :n submissões de :ambiente à espera: só voltam a andar quando a empresa voltar a emitir em :ambiente.', { n: deixadas, ambiente: e.ambientes[activo].rotulo }) });
            }
        },
        onError: falhou,
    });

    const abertas = e.submissoes.filter((s) => s.status === 'pending' || s.status === 'submitted').length;

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border px-4 py-3 text-sm', RAIO, recado.tipo === 'bom' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                    <span><i className={cls('fas mr-2', recado.tipo === 'bom' ? 'fa-circle-check' : 'fa-circle-exclamation')} aria-hidden="true" />{recado.texto}</span>
                    <button type="button" onClick={() => porRecado(null)} aria-label={t('Fechar')} className={cls('p-1', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            {/* A FAIXA DIZ SEMPRE QUAL É O AMBIENTE QUE EMITE, e não o que se
                está a ver. É a resposta à única pergunta que interessa nesta
                página: «os meus documentos estão a ir para a AGT a sério?». */}
            <CabecalhoDaAgt
                accoes={
                    <>
                        <EstadoNaFaixa icone={activo === 'production' ? 'fa-shield-halved' : 'fa-flask'}>
                            <span data-a-emitir>{t('A emitir em')} <strong>{e.ambientes[activo].rotulo}</strong></span>
                        </EstadoNaFaixa>
                        <button type="button" onClick={aoActualizar} disabled={aActualizar} className={cls(ACCAO_DA_FAIXA, 'disabled:cursor-wait disabled:opacity-70')}>
                            <i className={cls('fas fa-rotate', aActualizar && 'fa-spin')} aria-hidden="true" />{t('Actualizar')}
                        </button>
                    </>
                }
            />

            {/* O que está a acontecer NA PRÁTICA, por cima de tudo: é a única
                coisa nesta página que não pode passar despercebida. */}
            <AvisoDeComunicacaoAgt e={e} />

            <Cartao
                titulo={t('Contexto de operação')}
                icone="fa-building"
                accoes={o.permissoes.escolhe_empresa && <EscolhaDeEmpresa o={o} valor={empresa ?? e.empresa.id} aoEscolher={aoEscolherEmpresa} />}
            >
                <p className="mb-4 text-sm text-slate-600">
                    <strong className="text-slate-900">{e.empresa.nome}</strong>{e.empresa.nif && <span className="ml-2 font-mono text-xs text-slate-500">{t('NIF')} {e.empresa.nif}</span>}
                </p>

                {/* Os dois ambientes lado a lado: ver um de cada vez escondia que produção ainda não tem chaves. */}
                <div className="grid gap-3 sm:grid-cols-2">
                    {(Object.keys(e.ambientes) as Ambiente[]).map((amb, i) => {
                        const x = e.ambientes[amb];
                        return (
                            <button key={amb} type="button" onClick={() => aoVer(amb)} aria-pressed={x.a_ver} data-ambiente={amb} style={cascata(i)}
                                className={cls(
                                    'entra flex flex-col items-start gap-2 border p-4 text-left shadow-sm',
                                    RAIO, FOCO,
                                    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md',
                                    x.a_ver ? 'border-indigo-400 bg-indigo-50/60 ring-1 ring-indigo-300' : 'border-slate-200 bg-white hover:border-slate-300',
                                )}>
                                <span className="flex w-full items-center justify-between gap-2">
                                    <span className="flex items-center gap-2 text-base font-bold text-slate-900">
                                        <i className={cls('fas', amb === 'production' ? 'fa-shield-halved text-slate-400' : 'fa-flask text-slate-400')} aria-hidden="true" />
                                        {x.rotulo}
                                    </span>
                                    {x.activo ? <Etiqueta cor="bom" icone="fa-bolt" ponto>{t('Activo')}</Etiqueta> : <Etiqueta ponto>{t('Inactivo')}</Etiqueta>}
                                </span>
                                <span className="flex flex-wrap gap-2 text-xs">
                                    <Etiqueta cor={x.chaves ? 'bom' : 'aviso'} icone={x.chaves ? 'fa-key' : 'fa-triangle-exclamation'}>{x.chaves ? t('Par RSA instalado') : t('Sem par RSA')}</Etiqueta>
                                    <Etiqueta cor={x.produtor ? 'bom' : 'aviso'} icone="fa-building">{x.produtor ? t('Produtor configurado') : t('Produtor por configurar')}</Etiqueta>
                                </span>
                                {x.a_ver && <span className="text-xs font-semibold text-indigo-700"><i className="fas fa-eye mr-1" aria-hidden="true" />{t('a ver este')}</span>}
                            </button>
                        );
                    })}
                </div>

                {/* ACTIVAR É UMA ACÇÃO DELIBERADA, e a cor diz o peso dela: a
                    vermelho o que passa a ter valor fiscal, a âmbar o que o
                    perde. O botão só abre a confirmação — é lá dentro que se
                    diz o que muda, e só de lá sai o pedido. */}
                {!noActivo && (
                    <div className={cls('animate-fade-in mt-4 flex flex-wrap items-center justify-between gap-3 border px-4 py-3 text-sm', RAIO, paraProducao ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900')}>
                        <div className="min-w-0 flex-1 space-y-1">
                            <p><i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />{tPartes('Está a ver :aVer, mas a empresa emite em :activo. As acções que escrevem na AGT ficam fechadas aqui.', { aVer: <strong>{e.ambientes[aVer].rotulo}</strong>, activo: <strong>{e.ambientes[activo].rotulo}</strong> })}</p>
                            <p className="text-xs">{paraProducao ? t('Activar Produção põe os documentos desta empresa a seguir para a AGT real, com valor fiscal. Confirme antes as chaves e o teste de ligação.') : t('Activar Homologação passa os documentos a ir para o ambiente de testes — deixam de ter valor fiscal.')}</p>
                        </div>
                        {podeEditar && <Botao cor={paraProducao ? 'perigo' : 'aviso'} tom="solida" icone="fa-tower-broadcast" onClick={() => { activar.reset(); porAConfirmar(true); }}>{t('Passar a emitir aqui')}</Botao>}
                    </div>
                )}
            </Cartao>

            <ConfirmarActivacao
                aberto={aConfirmar}
                aoFechar={() => { if (!activar.isPending) porAConfirmar(false); }}
                e={e}
                aTrabalhar={activar.isPending}
                erro={activar.error}
                aoConfirmar={() => activar.mutate()}
            />

            <RelatorioDeConformidade e={e} />

            {/* Os separadores, sobre a mesma barra clara do ecrã de sempre: o
                que está aberto fica branco e destacado, e é essa diferença de
                fundo — não só a cor da letra — que se vê de longe. */}
            <div className={cls('flex flex-wrap gap-1 border border-slate-200 bg-slate-50 p-1 shadow-sm transition-opacity duration-200', RAIO, aActualizar && 'opacity-60')} role="tablist">
                {SEPARADORES.map((s) => (
                    <button key={s.chave} type="button" role="tab" aria-selected={separador === s.chave} onClick={() => porSeparador(s.chave)}
                        className={cls(
                            'flex items-center gap-2 px-4 py-2 text-sm font-semibold transition-all duration-200',
                            RAIO, FOCO,
                            separador === s.chave ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-indigo-200' : 'text-slate-500 hover:bg-white/70 hover:text-slate-800',
                        )}>
                        <i className={cls('fas', s.icone)} aria-hidden="true" />{s.rotulo}
                        {/* As que ainda esperam pela AGT, à vista antes de abrir o separador. */}
                        {s.chave === 'submissoes' && abertas > 0 && (
                            <span data-abertas title={t('Submissões por concluir')} className="animate-scale-in rounded-full bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold text-orange-700">{abertas}</span>
                        )}
                    </button>
                ))}
            </div>

            {separador === 'definicoes' && <Definicoes o={o} e={e} empresa={empresa} podeEditar={podeEditar} feito={feito} falhou={falhou} />}
            {separador === 'chaves' && <Chaves e={e} empresa={empresa} podeEditar={podeEditar} feito={feito} falhou={falhou} />}
            {separador === 'series' && <Series e={e} empresa={empresa} podeEditar={podeEditar} noActivo={noActivo} feito={feito} falhou={falhou} />}
            {separador === 'submissoes' && <Submissoes e={e} empresa={empresa} podeEditar={podeEditar} noActivo={noActivo} feito={feito} falhou={falhou} />}
            {separador === 'logs' && <Comunicacoes e={e} />}
            {separador === 'consulta' && <Consulta o={o} e={e} empresa={empresa} />}
        </div>
    );
}

/**
 * A CONFIRMAÇÃO DE MUDAR O AMBIENTE QUE EMITE.
 *
 * Um só clique punha uma empresa a emitir com valor fiscal — ou a deixar de
 * emitir com ele. O ecrã de sempre perguntava antes; a migração tinha perdido
 * a pergunta. Aqui diz-se o que muda, e para produção mostra-se o que falta:
 * com um item a falso o botão não confirma, e o servidor recusa na mesma se
 * alguém lá chegar por outro caminho (e devolve a lista, que se mostra).
 */
function ConfirmarActivacao({ aberto, aoFechar, e, aTrabalhar, erro, aoConfirmar }: {
    aberto: boolean; aoFechar: () => void; e: EstadoDaAgt; aTrabalhar: boolean; erro: unknown; aoConfirmar: () => void;
}) {
    const destino = e.ambiente;
    const activo = e.definicoes.agt_environment;
    const paraProducao = destino === 'production';
    const rotulo = e.ambientes[destino].rotulo;

    const recusa = erro instanceof ErroDaApi ? erro : null;

    // A lista da recusa é a mais fresca; senão a do estado; senão o que o ecrã já sabe dos dois ambientes.
    const prontidao: ItemDeProntidao[] = itensDeProntidao(recusa?.corpo?.prontidao)
        ?? itensDeProntidao(e.prontidao_producao)
        ?? [
            { chave: 'chaves', rotulo: t('Par RSA de produção instalado'), ok: e.ambientes.production.chaves },
            { chave: 'produtor', rotulo: t('Produtor configurado em produção'), ok: e.ambientes.production.produtor },
        ];

    const faltaAlgo = paraProducao && prontidao.some((i) => !i.ok);
    const pendentesDeixados = pendentesDe(e.pendentes_por_ambiente, activo);

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Passar a emitir em :ambiente?', { ambiente: rotulo })}
            subtitulo={e.empresa.nome}
            icone="fa-tower-broadcast"
            cor={paraProducao ? 'perigo' : 'aviso'}
            rodape={
                <>
                    <Botao onClick={aoFechar} disabled={aTrabalhar}>{t('Cancelar')}</Botao>
                    <Botao cor={paraProducao ? 'perigo' : 'aviso'} tom="solida" icone="fa-tower-broadcast" aTrabalhar={aTrabalhar} disabled={faltaAlgo} onClick={aoConfirmar}>
                        {t('Sim, passar a emitir em :ambiente', { ambiente: rotulo })}
                    </Botao>
                </>
            }
        >
            <div className="space-y-4" data-confirmar-activacao={destino}>
                <div className={cls('flex items-start gap-3 border px-4 py-3 text-sm', RAIO, paraProducao ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900')}>
                    <i className={cls('fas mt-0.5', paraProducao ? 'fa-scale-balanced' : 'fa-flask')} aria-hidden="true" />
                    <p>{paraProducao
                        ? t('Activar Produção põe os documentos desta empresa a seguir para a AGT real, com valor fiscal. Confirme antes as chaves e o teste de ligação.')
                        : t('Activar Homologação passa os documentos a ir para o ambiente de testes — deixam de ter valor fiscal.')}</p>
                </div>

                {paraProducao && (
                    <div className={cls('border border-slate-200 bg-slate-50 px-4 py-3', RAIO)}>
                        <ListaDeRequisitos
                            itens={prontidao}
                            titulo={t('Pronta para produção?')}
                            frase={faltaAlgo ? t('Enquanto faltar um destes, a empresa não passa a emitir em produção.') : t('Está tudo o que a AGT exige para emitir em produção.')}
                        />
                    </div>
                )}

                {pendentesDeixados > 0 && (
                    <p className={cls('flex items-start gap-2 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                        <i className="fas fa-hourglass-half mt-0.5" aria-hidden="true" />
                        {t('Há :n submissões de :ambiente por concluir. Ficam à espera até a empresa voltar a emitir em :ambiente.', { n: pendentesDeixados, ambiente: e.ambientes[activo].rotulo })}
                    </p>
                )}

                {recusa && (
                    <p role="alert" className={cls('flex items-start gap-2 border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900', RAIO)}>
                        <i className="fas fa-circle-exclamation mt-0.5" aria-hidden="true" />{recusa.message}
                    </p>
                )}
            </div>
        </Modal>
    );
}

/**
 * O RELATÓRIO DE CONFORMIDADE, nos cartões de gradiente da casa.
 *
 * A COR SEGUE O QUE O NÚMERO QUER DIZER, como no resto do sistema: as
 * rejeitadas ficam vermelhas quando existem e cinzentas quando são zero —
 * um cartão vermelho a dizer «0» treina a pessoa a ignorar o vermelho.
 */
function RelatorioDeConformidade({ e }: { e: EstadoDaAgt }) {
    const r = e.relatorio;
    const rejeitadas = r.submissions?.rejected ?? 0;

    const cartoes: Array<{ rotulo: string; valor: string; icone: string; tom: TomDoCartao }> = [
        { rotulo: 'Séries registadas', valor: t(':quantas de :total', { quantas: r.series?.registered ?? 0, total: r.series?.total ?? 0 }), icone: 'fa-hashtag', tom: 'azul' },
        { rotulo: 'Submissões validadas', valor: t(':quantas de :total', { quantas: r.submissions?.validated ?? 0, total: r.submissions?.total ?? 0 }), icone: 'fa-paper-plane', tom: 'verde' },
        { rotulo: 'Rejeitadas', valor: String(rejeitadas), icone: rejeitadas > 0 ? 'fa-circle-xmark' : 'fa-check', tom: rejeitadas > 0 ? 'vermelho' : 'cinza' },
        { rotulo: 'Facturas (30 dias) com ATCUD', valor: t(':quantas de :total', { quantas: r.invoices_30_days?.with_atcud ?? 0, total: r.invoices_30_days?.total ?? 0 }), icone: 'fa-file-invoice', tom: 'indigo' },
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-relatorio>
            {cartoes.map((c, i) => (
                <div key={c.rotulo} className="entra" style={cascata(i)}>
                    <CartaoNumero rotulo={t(c.rotulo)} valor={c.valor} icone={c.icone} tom={c.tom} />
                </div>
            ))}
        </div>
    );
}

type Accoes = { feito: (m: string) => void; falhou: (e: unknown) => void };

function Definicoes({ o, e, empresa, podeEditar, feito, falhou }: { o: OpcoesDaAgt; e: EstadoDaAgt; empresa?: number; podeEditar: boolean } & Accoes) {
    const [forma, porForma] = useState({ agt_auto_submit: e.definicoes.agt_auto_submit, agt_eac_code: e.definicoes.agt_eac_code, agt_require_validation: e.definicoes.agt_require_validation });
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const gravar = useMutation({ mutationFn: () => agt.guardar(forma, empresa), onSuccess: (r) => { feito(r.message); porErros({}); }, onError: (er) => { porErros(er instanceof ErroDaApi ? er.erros : {}); falhou(er); } });

    // O aviso segue o que está escrito: some ao escolher um código, e volta se o servidor disser que o gravado não serve.
    const caeEmFalta = !forma.agt_eac_code || (forma.agt_eac_code === e.definicoes.agt_eac_code && e.cae_em_falta === true);

    return (
        <Cartao titulo={t('Definições')} icone="fa-sliders" accoes={podeEditar && <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar')}</Botao>}>
            <p className="mb-4 text-sm text-slate-500">{t('Guardar aqui nunca muda o ambiente que emite. Isso é o botão «Passar a emitir aqui».')}</p>
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Código CAE (classe)')} erro={erros.agt_eac_code} className="sm:col-span-2" ajuda={t('Vai no campo eacCode de cada documento enviado à AGT.')}>
                    <select value={forma.agt_eac_code} onChange={(ev) => porForma({ ...forma, agt_eac_code: ev.target.value })} disabled={!podeEditar} className={entrada}>
                        <OpcoesDoCae cae={o.cae} gravado={e.definicoes.agt_eac_code} />
                    </select>
                </Campo>
                {caeEmFalta && <AvisoDeCaeEmFalta className="sm:col-span-2" />}
                <InterruptoresDaAgt
                    automatico={forma.agt_auto_submit}
                    validacao={forma.agt_require_validation}
                    aoMudarAutomatico={(v) => porForma({ ...forma, agt_auto_submit: v })}
                    aoMudarValidacao={(v) => porForma({ ...forma, agt_require_validation: v })}
                    desactivado={!podeEditar}
                />
            </div>
        </Cartao>
    );
}

function Chaves({ e, empresa, podeEditar, feito, falhou }: { e: EstadoDaAgt; empresa?: number; podeEditar: boolean } & Accoes) {
    const [publica, porPublica] = useState('');
    const [privada, porPrivada] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aRemover, porARemover] = useState(false);
    const [aSubstituir, porASubstituir] = useState(false);
    const [ligacao, porLigacao] = useState<ResultadoDaLigacao | null>(null);

    /*
     * SUBSTITUIR O PAR QUE ESTÁ A ASSINAR pede um sim. Um par errado e a
     * próxima factura sai assinada com uma chave que a AGT não conhece — e o
     * servidor recusa sem a confirmação. Pergunta-se antes quando se sabe que
     * vai ser preciso; se o servidor a pedir na mesma, pergunta-se nessa hora.
     */
    const noActivo = e.ambiente === e.definicoes.agt_environment;
    const substituiOQueAssina = noActivo && e.chaves.publica && e.chaves.privada;

    const guardar = useMutation({
        mutationFn: (confirmar: boolean) => agt.guardarChaves({ ambiente: e.ambiente, contributorPublicKey: publica, contributorPrivateKey: privada, ...(confirmar ? { confirmar: true } : {}) }, empresa),
        onSuccess: (r) => { feito(r.message); porPublica(''); porPrivada(''); porErros({}); porASubstituir(false); },
        onError: (er) => {
            if (er instanceof ErroDaApi && er.corpo.confirmar_necessario === true) {
                porASubstituir(true);
                return;
            }
            porASubstituir(false);
            porErros(er instanceof ErroDaApi ? er.erros : {});
            falhou(er);
        },
    });
    const pedirGuardar = () => (substituiOQueAssina ? porASubstituir(true) : guardar.mutate(false));
    const remover = useMutation({ mutationFn: () => agt.removerChaves(e.ambiente, empresa), onSuccess: (r) => { feito(r.message); porARemover(false); }, onError: falhou });
    const testar = useMutation({
        mutationFn: () => agt.testarLigacao(e.ambiente, empresa),
        onSuccess: (r) => {
            const l = lerLigacao(r);
            porLigacao(l);
            if (l.ok) feito(l.mensagem ?? t('A AGT respondeu.'));
            else falhou(l.mensagem ?? t('A AGT não respondeu.'));
        },
        onError: (er) => {
            porLigacao({ ...lerLigacao(er instanceof ErroDaApi ? er.corpo : null), ok: false, mensagem: er instanceof ErroDaApi ? er.message : t('Não foi possível testar.') });
            falhou(er);
        },
    });

    const rotulo = e.ambientes[e.ambiente].rotulo;
    const semPar = !(e.chaves.publica && e.chaves.privada);

    return (
        <div className="space-y-4">
            <Cartao titulo={t('Chaves de :ambiente', { ambiente: rotulo })} icone="fa-key" accoes={<Botao icone="fa-plug" aTrabalhar={testar.isPending} onClick={() => testar.mutate()}>{t('Testar ligação')}</Botao>}>
                <ul className="grid gap-2 sm:grid-cols-3" data-chaves>
                    <li><Etiqueta cor={e.chaves.publica ? 'bom' : 'aviso'} icone={e.chaves.publica ? 'fa-check' : 'fa-xmark'}>{e.chaves.publica ? t('Chave pública instalada') : t('Chave pública em falta')}</Etiqueta></li>
                    <li><Etiqueta cor={e.chaves.privada ? 'bom' : 'aviso'} icone={e.chaves.privada ? 'fa-check' : 'fa-xmark'}>{e.chaves.privada ? t('Chave privada instalada') : t('Chave privada em falta')}</Etiqueta></li>
                    <li><Etiqueta cor={e.chaves.produtor ? 'bom' : 'aviso'} icone={e.chaves.produtor ? 'fa-check' : 'fa-xmark'}>{e.chaves.produtor ? t('Credenciais do produtor presentes') : t('Credenciais do produtor em falta')}</Etiqueta></li>
                </ul>
                {e.em_falta.length > 0 && <p className="mt-3 text-sm text-amber-800">{t('Falta configurar: :lista.', { lista: e.em_falta.join(', ') })}</p>}
                {!e.chaves.produtor && <SemCredenciaisDoProdutor ambiente={rotulo} />}
                {ligacao && <PainelDaLigacao r={ligacao} />}
            </Cartao>

            {podeEditar && (
                <Cartao titulo={t('Instalar o par RSA de :ambiente', { ambiente: rotulo })} icone="fa-file-shield" accoes={<span className="flex gap-2">{(e.chaves.publica || e.chaves.privada) && <Botao cor="perigo" icone="fa-trash" onClick={() => porARemover(true)}>{t('Remover')}</Botao>}<Botao cor="primaria" tom="solida" icone="fa-key" aTrabalhar={guardar.isPending && !aSubstituir} disabled={!publica || !privada} onClick={pedirGuardar}>{t('Guardar par')}</Botao></span>}>
                    <p className="mb-4 text-sm text-slate-500">{t('O par vem do Portal do Contribuinte, um por ambiente. Instalar as chaves de produção não põe a empresa a emitir por lá.')}</p>

                    {/* O par de um ambiente não assina no outro: a AGT recusa. Quem
                        tem o de homologação instalado julgava produção pronta. */}
                    {semPar && (
                        <p className={cls('mb-3 flex items-start gap-2 border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900', RAIO)}>
                            <i className="fas fa-triangle-exclamation mt-0.5" aria-hidden="true" />
                            {t('Sem o par RSA de :ambiente não é possível assinar nem registar séries neste ambiente. O par do outro ambiente não serve — a AGT recusa a assinatura.', { ambiente: rotulo })}
                        </p>
                    )}
                    <p className={cls('mb-4 flex items-start gap-2 border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900', RAIO)}>
                        <i className="fas fa-circle-info mt-0.5" aria-hidden="true" />
                        {t('O utilizador e a senha Basic Auth não se configuram aqui: são credenciais do produtor SOS ERP, próprias de cada ambiente. Ao substituir as chaves, informe sempre o par completo.')}
                    </p>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {/* `autoComplete`: o browser não guarda nem sugere uma chave privada noutro sítio. */}
                        <Campo etiqueta={t('Chave pública (PEM)')} erro={erros.contributorPublicKey}><textarea value={publica} onChange={(ev) => porPublica(ev.target.value)} rows={8} spellCheck={false} autoComplete="off" placeholder={'-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----'} className={cls(entrada, 'h-auto py-2 font-mono text-xs')} /></Campo>
                        <Campo etiqueta={t('Chave privada (PEM)')} erro={erros.contributorPrivateKey}><textarea value={privada} onChange={(ev) => porPrivada(ev.target.value)} rows={8} spellCheck={false} autoComplete="new-password" placeholder={'-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----'} className={cls(entrada, 'h-auto py-2 font-mono text-xs')} /></Campo>
                    </div>
                </Cartao>
            )}

            <Modal aberto={aRemover} aoFechar={() => porARemover(false)} titulo={t('Remover as chaves de :ambiente?', { ambiente: rotulo })} icone="fa-trash" cor="perigo" rodape={<><Botao onClick={() => porARemover(false)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={remover.isPending} onClick={() => remover.mutate()}>{t('Remover')}</Botao></>}>
                <p className="text-sm text-slate-700">{t('O outro ambiente não é tocado. Sem chaves, nenhum documento é assinado em :ambiente.', { ambiente: rotulo })}</p>
                <p className="mt-2 text-sm font-semibold text-red-700">{t('A submissão à AGT fica bloqueada neste ambiente até se instalar um par novo.')}</p>
            </Modal>

            <Modal
                aberto={aSubstituir}
                aoFechar={() => { if (!guardar.isPending) porASubstituir(false); }}
                titulo={t('Substituir as chaves que assinam em :ambiente?', { ambiente: rotulo })}
                icone="fa-file-shield"
                cor="perigo"
                rodape={<><Botao onClick={() => porASubstituir(false)} disabled={guardar.isPending}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-key" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate(true)}>{t('Substituir o par')}</Botao></>}
            >
                <p className="text-sm text-slate-700">{t('Estas são as chaves que assinam os documentos de :ambiente, o ambiente activo. Um par errado e o próximo documento sai assinado com uma chave que a AGT não conhece.', { ambiente: rotulo })}</p>
                <p className="mt-2 text-sm font-semibold text-red-700">{t('Confirme antes que o par é o deste ambiente, tirado do Portal do Contribuinte.')}</p>
            </Modal>
        </div>
    );
}

function Series({ e, empresa, podeEditar, noActivo, feito, falhou }: { e: EstadoDaAgt; empresa?: number; podeEditar: boolean; noActivo: boolean } & Accoes) {
    const [resultado, porResultado] = useState<{ mensagem: string; detalhes: DetalheDaSincronizacao[]; ok: boolean } | null>(null);

    const detalhesDe = (x: unknown): DetalheDaSincronizacao[] => (Array.isArray(x) ? x.filter((d): d is DetalheDaSincronizacao => !!d && typeof d === 'object') : []);

    const sincronizar = useMutation({
        mutationFn: () => agt.sincronizarSeries(e.ambiente, empresa),
        onSuccess: (r) => {
            const detalhes = detalhesDe(r.details);
            porResultado({ mensagem: r.message, detalhes, ok: detalhes.every((d) => d.ok) });
            feito(r.message);
        },
        onError: (er) => {
            porResultado({ mensagem: er instanceof ErroDaApi ? er.message : t('Não foi possível concluir.'), detalhes: detalhesDe(er instanceof ErroDaApi ? er.corpo.details : null), ok: false });
            falhou(er);
        },
    });

    // O que o pedido precisa para sair — sem isto a AGT nem chega a responder.
    const requisitos: ItemDeProntidao[] = [
        { chave: 'produtor', rotulo: e.chaves.produtor ? t('Credenciais do produtor configuradas') : t('Credenciais do produtor em falta'), ok: e.chaves.produtor },
        { chave: 'publica', rotulo: e.chaves.publica ? t('Chave pública da empresa configurada') : t('Chave pública da empresa em falta'), ok: e.chaves.publica },
        { chave: 'privada', rotulo: e.chaves.privada ? t('Chave privada da empresa configurada') : t('Chave privada da empresa em falta'), ok: e.chaves.privada },
    ];
    const faltaRequisito = requisitos.some((r) => !r.ok);

    return (
        <Cartao titulo={t('Séries')} icone="fa-hashtag" semPadding accoes={podeEditar && <Botao cor="primaria" tom="solida" icone="fa-rotate" aTrabalhar={sincronizar.isPending} disabled={!noActivo} onClick={() => sincronizar.mutate()}>{noActivo ? t('Sincronizar com a AGT') : t('Sincronizar (só no ambiente activo)')}</Botao>}>
            {(faltaRequisito || resultado) && (
                <div className="space-y-3 border-b border-slate-100 p-4">
                    {faltaRequisito && (
                        <div className={cls('animate-fade-in flex items-start gap-3 border border-amber-200 bg-amber-50 px-4 py-3', RAIO)}>
                            <i className="fas fa-triangle-exclamation mt-0.5 text-amber-600" aria-hidden="true" />
                            <ListaDeRequisitos itens={requisitos} titulo={t('Sincronização ainda não disponível')} frase={t('O pedido só será enviado quando todos os requisitos estiverem configurados:')} />
                        </div>
                    )}

                    {/* O RESULTADO FICA À VISTA, série a série. Um recado no canto
                        dizia «2 falharam» e sumia em cinco segundos — sem dizer
                        quais nem porquê, que é o que se precisa para corrigir. */}
                    {resultado && (
                        <div role="status" data-resultado-sincronizacao={resultado.ok ? 'ok' : 'falhou'} className={cls('animate-fade-in border px-4 py-3 text-sm', RAIO, resultado.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900')}>
                            <div className="flex items-start justify-between gap-3">
                                <p className="font-bold"><i className={cls('fas mr-2', resultado.ok ? 'fa-circle-check' : 'fa-circle-exclamation')} aria-hidden="true" />{resultado.mensagem}</p>
                                <button type="button" onClick={() => porResultado(null)} aria-label={t('Fechar')} className={cls('p-1 opacity-70 hover:opacity-100', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                            </div>
                            {resultado.detalhes.length > 0 && (
                                <ul className="mt-2 space-y-1">
                                    {resultado.detalhes.map((d, i) => (
                                        <li key={`${d.serie_id}-${i}`} className={cls('flex flex-wrap items-center gap-2 text-xs', d.ok ? 'text-emerald-800' : 'text-red-800')}>
                                            <i className={cls('fas', d.ok ? 'fa-check' : 'fa-xmark')} aria-hidden="true" />
                                            <span className="font-mono font-semibold">{d.codigo}</span>
                                            {d.ok ? <span>{t('sincronizada')}</span> : (
                                                <>
                                                    {d.codigo_erro && <span className="rounded bg-red-100 px-1.5 py-0.5 font-mono text-[10px] font-bold">{d.codigo_erro}</span>}
                                                    <span>{d.erro ?? t('Falha sem detalhe.')}</span>
                                                </>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </div>
            )}

            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-semibold">{t('Série')}</th><th className="px-4 py-3 font-semibold">{t('Tipo')}</th><th className="px-4 py-3 font-semibold">{t('Código no portal AGT')}</th><th className="px-4 py-3 font-semibold">ATCUD</th><th className="px-4 py-3 font-semibold">{t('Estado')}</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {e.series.length === 0 && <tr><td colSpan={5}><SemNada icone="fa-hashtag" titulo={t('Sem séries activas.')} frase={t('Crie a primeira série de documentos nas Séries de Documentos.')} /></td></tr>}
                        {e.series.map((s, i) => <LinhaDaSerie key={s.id} s={s} i={i} />)}
                    </tbody>
                </table>
            </div>
        </Cartao>
    );
}

function LinhaDaSerie({ s, i }: { s: SerieDaAgt; i: number }) {
    const chave: EstadoDaSerie = s.estado && s.estado in ESTADOS_DA_SERIE ? s.estado : s.registada ? 'registada' : 'por_registar';
    const est = ESTADOS_DA_SERIE[chave];
    const erros = Array.isArray(s.erros) ? s.erros : [];

    return (
        <tr className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)} data-serie={s.series_code} data-estado={chave}>
            <td className="px-4 py-2">
                <span className="font-mono font-semibold text-slate-900">{s.series_code}</span>
                {s.name && s.name !== `Série ${s.series_code}` && <span className="block text-xs text-slate-400">{s.name}</span>}
            </td>
            <td className="px-4 py-2 text-slate-600">
                {s.tipo_rotulo ?? s.document_type}
                {s.prefixo && <span className="ml-1.5 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-500">{s.prefixo}</span>}
            </td>
            <td className="px-4 py-2">{s.agt_series_id ? <span className="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700">{s.agt_series_id}</span> : <span className="text-slate-300">—</span>}</td>
            <td className="px-4 py-2">{s.atcud ? <span className="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700">{s.atcud}</span> : <span className="text-slate-300">—</span>}</td>
            <td className="px-4 py-2">
                <Etiqueta cor={est.cor} icone={est.icone}>{est.rotulo}</Etiqueta>
                {chave === 'nao_aplicavel' && <span className="mt-1 block text-xs text-slate-400">{t('Documento não fiscal; não é enviado à AGT.')}</span>}
                {chave === 'rejeitada' && erros.length > 0 && (
                    <ul className="mt-1 max-w-md space-y-0.5 text-xs text-red-700">
                        {erros.map((er, n) => (
                            <li key={n}>{er.codigo && <span className="mr-1 font-mono font-bold">{er.codigo}</span>}{er.descricao}</li>
                        ))}
                    </ul>
                )}
            </td>
        </tr>
    );
}

function Submissoes({ e, empresa, podeEditar, noActivo, feito, falhou }: { e: EstadoDaAgt; empresa?: number; podeEditar: boolean; noActivo: boolean } & Accoes) {
    const [aRepor, porARepor] = useState<Submissao | null>(null);
    const actualizar = useMutation({ mutationFn: () => agt.actualizarEstados(empresa), onSuccess: (r) => feito(r.message), onError: falhou });
    const reenviar = useMutation({
        mutationFn: ({ s, repor }: { s: Submissao; repor: boolean }) => agt.reenviar(s.id, e.ambiente, repor, empresa),
        onSuccess: (r) => { porARepor(null); feito(r.message); },
        onError: (er) => { porARepor(null); falhou(er); },
    });

    return (
        <Cartao titulo={t('Submissões')} icone="fa-paper-plane" semPadding accoes={podeEditar && <Botao icone="fa-arrows-rotate" aTrabalhar={actualizar.isPending} onClick={() => actualizar.mutate()}>{t('Actualizar estados')}</Botao>}>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-semibold">{t('Quando')}</th><th className="px-4 py-3 font-semibold">{t('Documento')}</th><th className="px-4 py-3 font-semibold">{t('Estado')}</th><th className="px-4 py-3 font-semibold">{t('Referência / ATCUD')}</th><th className="px-4 py-3 font-semibold">{t('Erro')}</th><th className="px-4 py-3 text-right font-semibold">{t('Tentativas')}</th><th className="w-40 px-4 py-3"></th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {e.submissoes.length === 0 && <tr><td colSpan={7}><SemNada icone="fa-paper-plane" titulo={t('Nada submetido neste ambiente.')} frase={t('Assim que um documento for emitido e comunicado, aparece aqui com o estado que a AGT devolveu.')} /></td></tr>}
                        {e.submissoes.map((s, i) => {
                            const est = ESTADOS[s.status] ?? { rotulo: s.status, cor: 'neutra' as const, icone: 'fa-circle-question' };
                            // O servidor decide; `esgotada` é o nome antigo do `pode_repor`.
                            const podeRepor = s.pode_repor ?? s.esgotada ?? false;
                            const aTrabalhar = reenviar.isPending && reenviar.variables?.s.id === s.id;
                            return (
                                <tr key={s.id} className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)} data-submissao={s.id}>
                                    <td className="whitespace-nowrap px-4 py-2 text-slate-600">{s.quando}</td>
                                    <td className="px-4 py-2 font-mono text-xs">{s.document_type_code} {s.document_number}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={est.cor} icone={est.icone}>{est.rotulo}</Etiqueta></td>
                                    <td className="px-4 py-2 font-mono text-xs text-slate-600">{s.agt_reference ?? '—'}{s.atcud && <span className="block text-slate-400">{s.atcud}</span>}</td>
                                    <td className="max-w-xs px-4 py-2 text-xs text-red-700">
                                        {/* O código primeiro: é por ele que se procura a regra da AGT (E39, E70…). */}
                                        {s.error_code && <span className="mr-1 inline-block rounded bg-red-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-red-800">{s.error_code}</span>}
                                        {s.error_message}
                                        <ErrosDaAgt erros={s.erros} jaDito={s.error_message} />
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-2 text-right tabular-nums">{typeof s.tentativas_max === 'number' ? `${s.retry_count}/${s.tentativas_max}` : s.retry_count}</td>
                                    <td className="px-4 py-2 text-right">
                                        {podeEditar && s.pode_reenviar && <Botao icone="fa-paper-plane" disabled={!noActivo} aTrabalhar={aTrabalhar && !reenviar.variables?.repor} onClick={() => reenviar.mutate({ s, repor: false })}>{t('Reenviar')}</Botao>}
                                        {podeEditar && podeRepor &&<Botao cor="perigo" icone="fa-rotate-left" disabled={!noActivo} aTrabalhar={aTrabalhar && !!reenviar.variables?.repor} onClick={() => porARepor(s)}>{t('Repor e reenviar')}</Botao>}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {/* TENTATIVAS GASTAS NÃO SE REPÕEM A UM CLIQUE: é mandar outra vez à
                AGT um documento que ela já recusou três vezes. Serve quando a
                recusa foi do software e o código já está corrigido. */}
            <Modal
                aberto={aRepor !== null}
                aoFechar={() => { if (!reenviar.isPending) porARepor(null); }}
                titulo={t('Repor e reenviar?')}
                subtitulo={aRepor ? `${aRepor.document_type_code ?? ''} ${aRepor.document_number ?? ''}`.trim() : undefined}
                icone="fa-rotate-left"
                cor="perigo"
                rodape={
                    <>
                        <Botao onClick={() => porARepor(null)} disabled={reenviar.isPending}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-rotate-left" aTrabalhar={reenviar.isPending} onClick={() => aRepor && reenviar.mutate({ s: aRepor, repor: true })}>{t('Repor e reenviar')}</Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">{t('Repor as tentativas e reenviar este documento à AGT agora?')}</p>
                {aRepor?.error_message && (
                    <p className={cls('mt-3 border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800', RAIO)}>
                        {aRepor.error_code && <span className="mr-1 font-mono font-bold">{aRepor.error_code}</span>}{aRepor.error_message}
                    </p>
                )}
                <p className="mt-3 text-xs text-slate-500">{t('Use-o quando a recusa foi do software e já está corrigida: o contador volta a zero e o documento segue já.')}</p>
            </Modal>
        </Cartao>
    );
}

/**
 * OS ERROS QUE A AGT DEVOLVEU, UM A UM, com a explicação de cá.
 *
 * O `error_message` guarda um só; a resposta da AGT pode trazer vários, e o
 * código dela (E39, E70…) sozinho não diz a quem está ao balcão o que corrigir.
 * O que já está escrito por cima não se repete.
 */
function ErrosDaAgt({ erros, jaDito }: { erros?: ErroDaAgt[]; jaDito?: string | null }) {
    const lista = (Array.isArray(erros) ? erros : []).filter((x) => x && (x.descricao !== jaDito || x.explicacao));

    if (lista.length === 0) return null;

    return (
        <ul className="mt-1 space-y-1">
            {lista.map((x, n) => (
                <li key={n}>
                    {x.descricao !== jaDito && (
                        <span className="block">{x.codigo && <span className="mr-1 font-mono font-bold">{x.codigo}</span>}{x.descricao}</span>
                    )}
                    {x.explicacao && <span className="block text-slate-500"><i className="fas fa-circle-info mr-1" aria-hidden="true" />{x.explicacao}</span>}
                </li>
            ))}
        </ul>
    );
}

function Comunicacoes({ e }: { e: EstadoDaAgt }) {
    return (
        <Cartao titulo={t('Comunicações com a AGT')} icone="fa-list" semPadding>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-semibold">{t('Quando')}</th><th className="px-4 py-3 font-semibold">{t('Serviço')}</th><th className="px-4 py-3 font-semibold">{t('Pedido')}</th><th className="px-4 py-3 font-semibold">{t('Resposta')}</th><th className="px-4 py-3 text-right font-semibold">ms</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {e.logs.length === 0 && <tr><td colSpan={5}><SemNada icone="fa-satellite-dish" titulo={t('Ainda não houve comunicações neste ambiente.')} frase={t('Cada ida à AGT — registar uma série, submeter um documento, consultar — fica registada aqui.')} /></td></tr>}
                        {e.logs.map((l, i) => (
                            <tr key={l.id} className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)}>
                                <td className="whitespace-nowrap px-4 py-2 text-slate-600">{l.quando}</td>
                                <td className="px-4 py-2">{l.service}</td>
                                <td className="px-4 py-2 font-mono text-xs text-slate-600">{l.method} {l.endpoint}</td>
                                <td className="px-4 py-2">{l.success ? <Etiqueta cor="bom" icone="fa-circle-check">{l.response_status ?? 'OK'}</Etiqueta> : <Etiqueta cor="perigo" icone="fa-circle-exclamation">{l.response_status ?? t('Falhou')}</Etiqueta>}{l.error_message && <span className="block text-xs text-red-700">{l.error_message}</span>}</td>
                                <td className="px-4 py-2 text-right tabular-nums text-slate-500">{l.response_time ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Cartao>
    );
}

/** Uma data do calendário local, como o `<input type="date">` a quer — o `toISOString` dava o dia de ontem depois da meia-noite UTC. */
const dia = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

function Consulta({ o, e, empresa }: { o: OpcoesDaAgt; e: EstadoDaAgt; empresa?: number }) {
    const cache = useQueryClient();
    const [forma, porForma] = useState(() => {
        const hoje = new Date();
        const ha30 = new Date(hoje);
        ha30.setDate(hoje.getDate() - 30);
        return { apiOperation: 'listarFacturas', apiRequestId: '', apiDocumentNo: '', apiDateFrom: dia(ha30), apiDateTo: dia(hoje) };
    });
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const consultar = useMutation({
        mutationFn: () => agt.consultar({ ...forma, ambiente: e.ambiente }, empresa),
        // Consultar não grava nada: o resultado mostra-se no painel, e um «Guardado com sucesso» no canto mentia.
        meta: { aviso: false },
        onSuccess: () => porErros({}),
        onError: (er) => porErros(er instanceof ErroDaApi ? er.erros : {}),
        // A consulta fica nas Comunicações: refresca-se para ela lá aparecer.
        onSettled: () => void cache.invalidateQueries({ queryKey: ['agt', 'estado'] }),
    });
    const m = (chave: keyof typeof forma) => (ev: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => porForma({ ...forma, [chave]: ev.target.value });

    const recusaComResultado = consultar.error instanceof ErroDaApi && typeof consultar.error.corpo.ok === 'boolean' ? consultar.error.corpo : null;
    const r = consultar.data ? lerConsulta(consultar.data) : recusaComResultado ? lerConsulta(recusaComResultado) : null;

    return (
        <Cartao titulo={t('Consultar a AGT em :ambiente', { ambiente: e.ambientes[e.ambiente].rotulo })} icone="fa-magnifying-glass" accoes={<Botao cor="primaria" tom="solida" icone="fa-magnifying-glass" aTrabalhar={consultar.isPending} onClick={() => consultar.mutate()}>{t('Consultar')}</Botao>}>
            {consultar.error && !r && <AvisoDeErro erro={consultar.error} />}
            <div className="grid gap-4 sm:grid-cols-3">
                <Campo etiqueta={t('Operação')} erro={erros.apiOperation} className="sm:col-span-3 lg:col-span-1"><select value={forma.apiOperation} onChange={m('apiOperation')} className={entrada}>{o.operacoes.map((x) => <option key={x.valor} value={x.valor}>{ROTULOS_DAS_OPERACOES[x.valor] ?? x.rotulo}</option>)}</select></Campo>
                {forma.apiOperation === 'obterEstado' && <Campo etiqueta={t('Request ID')} erro={erros.apiRequestId} obrigatorio><input value={forma.apiRequestId} onChange={m('apiRequestId')} placeholder={t('Request ID devolvido pela AGT')} autoComplete="off" className={cls(entrada, 'font-mono')} /></Campo>}
                {forma.apiOperation === 'consultarFactura' && <Campo etiqueta={t('Número do documento')} erro={erros.apiDocumentNo} obrigatorio ajuda={t('O número fiscal completo, como a AGT o conhece.')}><input value={forma.apiDocumentNo} onChange={m('apiDocumentNo')} placeholder={t('Ex.: FT A/2026/000001')} autoComplete="off" className={cls(entrada, 'font-mono')} /></Campo>}
                {forma.apiOperation === 'listarFacturas' && <><Campo etiqueta={t('De')} erro={erros.apiDateFrom} obrigatorio><input type="date" value={forma.apiDateFrom} onChange={m('apiDateFrom')} className={entrada} /></Campo><Campo etiqueta={t('Até')} erro={erros.apiDateTo} obrigatorio><input type="date" value={forma.apiDateTo} onChange={m('apiDateTo')} className={entrada} /></Campo></>}
            </div>

            {r && (
                <div data-resultado={r.ok ? 'ok' : 'falhou'} role="status" className={cls('animate-fade-in mt-4 border p-4', RAIO, r.ok ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50')}>
                    <p className={cls('text-sm font-bold', r.ok ? 'text-emerald-800' : 'text-red-800')}>
                        <i className={cls('fas mr-2', r.ok ? 'fa-circle-check' : 'fa-circle-xmark')} aria-hidden="true" />
                        {r.ok ? t('Operação concluída') : t('Operação recusada ou com erro')}
                    </p>
                    <p className="mt-1 break-words text-sm text-slate-700">{r.mensagem ?? (r.ok ? t('A AGT devolveu uma resposta válida.') : t('A AGT não explicou a recusa.'))}</p>
                    <p className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                        {r.testado_em && <span><i className="fas fa-clock mr-1" aria-hidden="true" />{r.testado_em}</span>}
                        {r.ms !== null && <span className="tabular-nums"><i className="fas fa-stopwatch mr-1" aria-hidden="true" />{r.ms} ms</span>}
                        <span className="font-mono">HTTP {r.http ?? '—'}</span>
                    </p>
                    {r.dados !== null && r.dados !== undefined && (
                        <details className="group mt-3">
                            <summary className={cls('inline-flex cursor-pointer select-none items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900', FOCO, RAIO)}>
                                <i className="fas fa-chevron-right transition-transform duration-200 group-open:rotate-90" aria-hidden="true" />
                                {t('Ver a resposta completa (JSON)')}
                            </summary>
                            <pre className="mt-2 max-h-96 overflow-auto rounded-lg bg-slate-900 p-3 text-[11px] text-emerald-300">{JSON.stringify(r.dados, null, 2)}</pre>
                        </details>
                    )}
                </div>
            )}
        </Cartao>
    );
}
