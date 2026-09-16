import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { revenda, type OpcoesDoPortal } from '@/api/revenda';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Modal } from '@/ui/Modal';
import { RAIO, cls } from '@/ui/tokens';

import { Copiar, kwanzas } from './comum';
import { Campo, entrada } from './SejaRevendedor';

/**
 * PAGAR PELO CLIENTE (16/09/2026) — o pagamento à plataforma é manual: o
 * revendedor faz a transferência para a conta da plataforma e envia aqui a
 * referência e o comprovativo. Quem confirma é o super admin; até lá fica
 * «por confirmar».
 *
 * Serve um pedido de plano (o comprovativo do pedido) e uma factura de
 * renovação (o pagamento da factura).
 */
export type AlvoDoPagamento = { tipo: 'pedido' | 'factura'; id: number; empresaId: number; empresa: string | null; descricao: string; valor: number; referencia?: string | null };

export function PagarPeloCliente({ alvo, conta, aoFechar }: { alvo: AlvoDoPagamento; conta?: OpcoesDoPortal['conta']; aoFechar: () => void }) {
    const cache = useQueryClient();
    const [ficheiro, porFicheiro] = useState<File | null>(null);
    const [referencia, porReferencia] = useState(alvo.referencia ?? '');

    const enviar = useMutation({
        mutationFn: () => {
            if (!ficheiro) throw new Error(t('Escolha o ficheiro do comprovativo.'));

            return alvo.tipo === 'factura'
                ? revenda.pagarFactura(alvo.empresaId, alvo.id, ficheiro, referencia)
                : revenda.comprovativo(alvo.empresaId, alvo.id, ficheiro, referencia);
        },
        onSuccess: () => { void cache.invalidateQueries({ queryKey: ['revenda'] }); aoFechar(); },
    });
    const erros = enviar.error instanceof ErroDaApi ? enviar.error.erros : {};
    const erroGeral = enviar.error && !Object.keys(erros).length ? (enviar.error as Error).message : undefined;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Pagar pelo cliente')} subtitulo={[alvo.empresa, alvo.descricao].filter(Boolean).join(' · ')}
            icone="fa-money-bill-transfer" cor="bom" largura="md"
            rodape={<>
                <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                <Botao cor="bom" tom="solida" icone="fa-paper-plane" aTrabalhar={enviar.isPending} onClick={() => enviar.mutate()}>{t('Enviar pagamento')}</Botao>
            </>}>
            <div className="space-y-4">
                <ol className="grid gap-2 text-sm sm:grid-cols-3">
                    {[
                        ['fa-building-columns', t('Transfira o valor para a conta da plataforma')],
                        ['fa-paperclip', t('Envie aqui a referência e o comprovativo')],
                        ['fa-circle-check', t('Confirmamos e a subscrição avança')],
                    ].map(([icone, texto], i) => (
                        <li key={i} className={cls('flex items-start gap-2 bg-slate-50 p-2.5', RAIO)}>
                            <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-600 text-[11px] font-bold text-white">{i + 1}</span>
                            <span className="text-slate-700"><i className={cls('fas mr-1 text-emerald-600', icone)} aria-hidden="true" />{texto}</span>
                        </li>
                    ))}
                </ol>

                <div className={cls('bg-gradient-to-r from-emerald-600 to-teal-600 p-4 text-white', RAIO)}>
                    <p className="text-xs text-white/80">{t('Valor a transferir')}</p>
                    <p className="text-3xl font-black tabular-nums">{kwanzas(alvo.valor)}</p>
                </div>

                {conta && (
                    <div className={cls('grid gap-2 border border-amber-200 bg-amber-50 p-3 text-sm sm:grid-cols-2', RAIO)}>
                        <p><span className="block text-xs text-amber-700">{t('Banco')} · {t('Titular')}</span><b>{conta.banco}</b> · {conta.titular}</p>
                        <p className="min-w-0"><span className="block text-xs text-amber-700">IBAN</span><b className="break-all font-mono">{conta.iban}</b> <Copiar texto={conta.iban} className="ml-1 !px-2 !py-1 !text-xs" /></p>
                    </div>
                )}

                <Campo id="pc-ref" rotulo={t('Referência da transferência')} erro={erros.referencia?.[0]} icone="fa-hashtag">
                    <input id="pc-ref" value={referencia} onChange={(e) => porReferencia(e.target.value)} className={entrada(erros.referencia?.[0])} />
                </Campo>
                <Campo id="pc-ficheiro" rotulo={t('Comprovativo (PDF, JPG ou PNG, até 5 MB)')} obrigatorio erro={erros.comprovativo?.[0] ?? erroGeral} icone="fa-file-arrow-up">
                    <input id="pc-ficheiro" type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => porFicheiro(e.target.files?.[0] ?? null)}
                        className="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-100 file:px-3 file:py-2 file:font-semibold file:text-emerald-800 hover:file:bg-emerald-200" />
                </Campo>
            </div>
        </Modal>
    );
}
