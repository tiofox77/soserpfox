import { useQuery } from '@tanstack/react-query';

import type { DocumentoLigado } from '@/api/tesouraria';
import { movimentos } from '@/api/tesouraria';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * A FICHA DO MOVIMENTO — o que este dinheiro é, e o que tem por trás.
 *
 * O modal em Blade trazia tudo carregado com a lista. Aqui pede-se ao abrir:
 * uma lista de cinquenta linhas não precisa de arrastar cinquenta facturas,
 * cinquenta compras e cinquenta notas de crédito que ninguém vai ver.
 *
 * A FACTURA DE COMPRA APARECE — pela primeira vez. O bloco existia no Blade e
 * nunca chegou a desenhar-se: a relação do modelo aponta para uma coluna
 * `purchase_invoice_id` que não existe (a coluna é `purchase_id`), e o
 * Eloquent devolvia null sem se queixar. O servidor lê agora a coluna certa.
 */
export function FichaDoMovimento({ id, aoFechar }: { id: number | null; aoFechar: () => void }) {
    const ficha = useQuery({
        queryKey: ['tesouraria', 'movimento', id],
        queryFn: () => movimentos.ficha(id as number),
        enabled: id !== null,
    });

    const m = ficha.data;
    const entrada = m?.tipo === 'income';

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={t('Detalhes da Transação')}
            subtitulo={m?.numero}
            icone="fa-eye"
            cor="teal"
            largura="lg"
            rodape={<Botao onClick={aoFechar} icone="fa-times">{t('Fechar')}</Botao>}
        >
            {/*
              * O ESQUELETO SÓ EXISTE ENQUANTO HÁ ALGO A CARREGAR.
              *
              * O conteúdo de um `<dialog>` está no DOM mesmo com a janela
              * fechada, e uma consulta desligada (`enabled: false`) continua
              * a dizer `isPending`. Sem o `id !== null`, ficava um esqueleto
              * com `aria-busy="true"` permanentemente na página — invisível
              * aos olhos, mas não a quem ouve o ecrã, e foi o varrimento de
              * browser que o apanhou.
              */}
            {id !== null && ficha.isPending && <Carregando linhas={6} />}

            {ficha.isError && (
                <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                    {t('Não foi possível carregar esta transação.')}
                </div>
            )}

            {m && (
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <Caixa rotulo={t('Número da Transação')}>
                            <span className="font-mono text-lg font-bold text-slate-900">{m.numero}</span>
                        </Caixa>
                        <Caixa rotulo={t('Tipo')}>
                            <EtiquetaDeTipo tipo={m.tipo} />
                        </Caixa>
                    </div>

                    {/* O VALOR, em grande e com a cor do sentido. Era assim no
                        modal de sempre, e é a primeira coisa que se procura. */}
                    <div
                        className={cls(
                            'border-2 px-6 py-5 text-center',
                            RAIO,
                            entrada
                                ? 'border-emerald-300 bg-gradient-to-r from-emerald-50 to-teal-50'
                                : 'border-red-300 bg-gradient-to-r from-red-50 to-rose-50',
                        )}
                    >
                        <p className={cls('mb-1 text-sm font-semibold', entrada ? 'text-emerald-700' : 'text-red-700')}>
                            {t('Valor da Transação')}
                        </p>
                        <p className={cls('text-4xl font-bold tabular-nums', entrada ? 'text-emerald-600' : 'text-red-600')}>
                            {entrada ? '+' : '−'} {kz(m.valor)} {m.moeda}
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <Linha rotulo={t('Data')}>{m.data_curta}</Linha>
                        <Linha rotulo={t('Status')}>
                            <EtiquetaDeEstado estado={m.estado} />
                        </Linha>
                        <Linha rotulo={t('Método de Pagamento')}>{m.forma_de_pagamento ?? '—'}</Linha>
                        <Linha rotulo={t('Categoria')}>{m.categoria_nome ?? t('Sem categoria')}</Linha>
                        {m.tipo_nome && <Linha rotulo={t('Tipo de Movimento')}>{m.tipo_nome}</Linha>}
                        {/* ONDE O DINHEIRO CAIU. O modal de sempre não o dizia,
                            e é a pergunta seguinte de quem confere um extracto.

                            SEM DESTINO NENHUM ACONTECE: uma venda do balcão numa
                            empresa ainda sem caixa configurado grava o movimento
                            e não mexe em saldo nenhum. O rótulo não pode chamar
                            «conta bancária» a isso — chama-lhe o que é. */}
                        <Linha rotulo={m.caixa ? t('Caixa') : m.conta ? t('Conta Bancária') : t('Destino')}>
                            {m.caixa ?? m.conta ?? (
                                <span className="font-normal text-amber-700">
                                    <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />
                                    {t('Por classificar — não mexeu em nenhum saldo')}
                                </span>
                            )}
                        </Linha>
                    </div>

                    {m.referencia && <Linha rotulo={t('Referência')}>{m.referencia}</Linha>}
                    <Linha rotulo={t('Descrição')}>{m.descricao}</Linha>
                    {m.notas && <Linha rotulo={t('Observações')}>{m.notas}</Linha>}

                    {m.factura && (
                        <Ligado
                            documento={m.factura}
                            titulo={t('Fatura de Venda Associada')}
                            icone="fa-file-invoice"
                            tom="azul"
                            parte={t('Cliente')}
                            nomeDaParte={m.factura.cliente}
                            accao={t('Ver Fatura Completa')}
                        />
                    )}

                    {m.compra && (
                        <Ligado
                            documento={m.compra}
                            titulo={t('Fatura de Compra Associada')}
                            icone="fa-file-invoice"
                            tom="laranja"
                            parte={t('Fornecedor')}
                            nomeDaParte={m.compra.fornecedor}
                            accao={t('Ver Fatura Completa')}
                        />
                    )}

                    {m.nota_de_credito && (
                        <Ligado
                            documento={m.nota_de_credito}
                            titulo={t('Nota de Crédito Criada')}
                            icone="fa-file-circle-minus"
                            tom="verde"
                            parte={t('Cliente')}
                            nomeDaParte={m.nota_de_credito.cliente}
                            accao={t('Ver Nota de Crédito')}
                        />
                    )}

                    <div className={cls('bg-slate-50 px-4 py-3', RAIO)}>
                        <p className="mb-2 text-xs font-semibold text-slate-500">{t('Informações de Auditoria')}</p>
                        <div className="grid grid-cols-2 gap-2 text-xs">
                            <span>
                                <span className="text-slate-500">{t('Criado por')}: </span>
                                <span className="font-semibold text-slate-900">{m.criado_por ?? '—'}</span>
                            </span>
                            <span>
                                <span className="text-slate-500">{t('Criado em')}: </span>
                                <span className="font-semibold text-slate-900">{m.criado_em ?? '—'}</span>
                            </span>
                        </div>
                    </div>
                </div>
            )}
        </Modal>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

function Caixa({ rotulo, children }: { rotulo: string; children: React.ReactNode }) {
    return (
        <div className={cls('bg-slate-50 px-4 py-3', RAIO)}>
            <p className="mb-1 text-xs text-slate-500">{rotulo}</p>
            {children}
        </div>
    );
}

function Linha({ rotulo, children }: { rotulo: string; children: React.ReactNode }) {
    return (
        <div>
            <p className="mb-0.5 text-xs text-slate-500">{rotulo}</p>
            <div className="text-sm font-semibold text-slate-900">{children}</div>
        </div>
    );
}

export function EtiquetaDeTipo({ tipo }: { tipo: 'income' | 'expense' | 'transfer' }) {
    if (tipo === 'income') {
        return <Etiqueta cor="bom" icone="fa-arrow-down">{t('Entrada')}</Etiqueta>;
    }

    if (tipo === 'expense') {
        return <Etiqueta cor="perigo" icone="fa-arrow-up">{t('Saída')}</Etiqueta>;
    }

    return <Etiqueta cor="primaria" icone="fa-exchange-alt">{t('Transferência')}</Etiqueta>;
}

export function EtiquetaDeEstado({ estado }: { estado: 'pending' | 'completed' | 'cancelled' }) {
    if (estado === 'completed') return <Etiqueta cor="bom" ponto>{t('Concluído')}</Etiqueta>;
    if (estado === 'pending') return <Etiqueta cor="aviso" ponto>{t('Pendente')}</Etiqueta>;

    return <Etiqueta cor="neutra" ponto>{t('Cancelado')}</Etiqueta>;
}

const TONS = {
    azul: 'border-blue-300 bg-blue-50 text-blue-700',
    laranja: 'border-orange-300 bg-orange-50 text-orange-700',
    verde: 'border-emerald-300 bg-emerald-50 text-emerald-700',
} as const;

const BOTOES = {
    azul: 'bg-blue-600 hover:bg-blue-700',
    laranja: 'bg-orange-600 hover:bg-orange-700',
    verde: 'bg-emerald-600 hover:bg-emerald-700',
} as const;

/** Um documento ligado a este movimento, com a porta para ele. */
function Ligado({
    documento,
    titulo,
    icone,
    tom,
    parte,
    nomeDaParte,
    accao,
}: {
    documento: DocumentoLigado;
    titulo: string;
    icone: string;
    tom: keyof typeof TONS;
    parte: string;
    nomeDaParte?: string | null;
    accao: string;
}) {
    return (
        <div className={cls('border-2 px-4 py-3', RAIO, TONS[tom])}>
            <div className="mb-3 flex items-center justify-between gap-3">
                <p className="text-xs font-semibold">
                    <i className={cls('mr-1 fas', icone)} aria-hidden="true" />
                    {titulo}
                </p>
                <span className="rounded-full bg-white/70 px-2 py-1 text-xs font-semibold">
                    {documento.estado_rotulo ?? documento.estado}
                </span>
            </div>

            <dl className="mb-3 space-y-1.5 text-sm text-slate-800">
                <Par rotulo={t('Número')}>
                    <span className="font-mono font-bold">{documento.numero}</span>
                    {/* A SÉRIE DA AGT em pequeno por baixo, quando difere da
                        interna — as duas numerações são duas coisas. */}
                    {documento.numero_agt && documento.numero_agt !== documento.numero && (
                        <span className="ml-2 text-xs text-slate-500">AGT: {documento.numero_agt}</span>
                    )}
                </Par>
                <Par rotulo={t('Data')}>{documento.data}</Par>
                {documento.motivo && <Par rotulo={t('Motivo')}>{documento.motivo}</Par>}
                {nomeDaParte && <Par rotulo={parte}>{nomeDaParte}</Par>}
                <div className="flex justify-between border-t border-white/60 pt-1.5 text-sm">
                    <dt className="font-semibold">{t('Total')}</dt>
                    <dd className="font-bold tabular-nums">{kz(documento.total)} AOA</dd>
                </div>
            </dl>

            <a
                href={documento.morada}
                target="_blank"
                rel="noopener"
                className={cls(
                    'inline-flex w-full items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white',
                    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md',
                    BOTOES[tom],
                    RAIO,
                    FOCO,
                )}
            >
                <i className="fas fa-external-link-alt" aria-hidden="true" />
                {accao}
            </a>
        </div>
    );
}

function Par({ rotulo, children }: { rotulo: string; children: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-3">
            <dt className="shrink-0 opacity-80">{rotulo}:</dt>
            <dd className="min-w-0 truncate text-right font-semibold">{children}</dd>
        </div>
    );
}
