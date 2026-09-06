import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { transferencias, type ItemDaTransferencia, type OpcoesDasTransferencias, type Resumo } from '@/api/transferencias';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';
import { Carrinho, Comprovativo } from '@/ecras/facturacao/transferencias/Carrinho';

/**
 * TRANSFERÊNCIAS ENTRE EMPRESAS — do grupo, e só as do utilizador.
 *
 * São dois documentos, um por empresa, cada um na sua sequência: o da
 * origem abre-se daqui; o do destino dá-se a conhecer mas pertence à outra
 * empresa. O artigo é copiado para o destino quando lá não existe, e os
 * lotes seguem a mercadoria. Tudo isso é do servidor
 * (`TransferenciaDeStock`), o mesmo que o ecrã Livewire chama.
 */
export default function TransferenciasEntreEmpresas() {
    const cache = useQueryClient();
    const [pagina, porPagina] = useState(1);
    const [aberto, porAberto] = useState(false);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({ queryKey: ['transferencias', 'opcoes'], queryFn: transferencias.opcoes, staleTime: 5 * 60_000 });
    const historico = useQuery({ queryKey: ['transferencias', 'entre-empresas', pagina], queryFn: () => transferencias.historicoEntreEmpresas(pagina), placeholderData: keepPreviousData });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || historico.isError) {
        const erro = opcoes.error ?? historico.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as transferências')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = historico.data?.data ?? [];
    const contas = historico.data?.meta;

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-building-circle-arrow-right text-slate-400" aria-hidden="true" />{t('Transferências entre Empresas')}</span>}
                accoes={o.permissoes.pode_entre_empresas && o.empresas.length > 0 && <Botao cor="primaria" tom="solida" icone="fa-right-left" onClick={() => porAberto(true)}>{t('Nova transferência')}</Botao>}
            >
                {o.empresas.length === 0 ? <p className="text-sm text-slate-500">{t('Só se transfere para outra empresa a que também tenha acesso — e esta conta só tem esta.')}</p> : <p className="text-sm text-slate-500">{t('Cada empresa fica com o seu documento, na sua própria numeração.')}</p>}
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-4 py-3 font-semibold">{t('Quando')}</th><th className="px-4 py-3 font-semibold">{t('Sentido')}</th><th className="px-4 py-3 font-semibold">{t('Artigo')}</th><th className="px-4 py-3 font-semibold">{t('Armazém')}</th><th className="px-4 py-3 text-right font-semibold">{t('Qtd.')}</th><th className="px-4 py-3 font-semibold">{t('Referência')}</th><th className="px-4 py-3 font-semibold">{t('Notas')}</th></tr></thead>
                        <tbody className={cls('divide-y divide-slate-100', historico.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400">{historico.isPending ? t('A carregar…') : t('Sem transferências entre empresas.')}</td></tr>}
                            {linhas.map((m) => (
                                <tr key={m.id}>
                                    <td className="px-4 py-2 tabular-nums text-slate-600">{m.quando}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={m.sentido === 'entrada' ? 'bom' : 'aviso'}>{m.sentido === 'entrada' ? t('Recebido') : t('Enviado')}</Etiqueta></td>
                                    <td className="px-4 py-2">{m.artigo}{m.codigo && <span className="ml-2 font-mono text-xs text-slate-400">{m.codigo}</span>}</td>
                                    <td className="px-4 py-2">{m.armazem}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{m.quantidade.toLocaleString('pt-PT')}</td>
                                    <td className="px-4 py-2 font-mono text-xs">{m.referencia}</td>
                                    <td className="px-4 py-2 text-xs text-slate-500">{m.notas}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>{t('Página :pagina de :ultima', { pagina: contas.current_page, ultima: contas.last_page })}</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porPagina(contas.current_page - 1)}>{t('Anterior')}</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porPagina(contas.current_page + 1)}>{t('Seguinte')}</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {aberto && <Nova o={o} aoFechar={() => porAberto(false)} aoFeito={(m) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['transferencias'] }); }} />}
        </div>
    );
}

function Nova({ o, aoFechar, aoFeito }: { o: OpcoesDasTransferencias; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [deArmazem, porDeArmazem] = useState('');
    const [empresa, porEmpresa] = useState('');
    const [paraArmazem, porParaArmazem] = useState('');
    const [notas, porNotas] = useState('');
    const [itens, porItens] = useState<ItemDaTransferencia[]>([]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ referencia_origem: string; referencia_destino: string; destino_nome: string; resumo: Resumo[]; pdf: string } | null>(null);

    const armazensDestino = useQuery({ queryKey: ['transferencias', 'armazens-da-empresa', empresa], queryFn: () => transferencias.armazensDaEmpresa(Number(empresa)), enabled: empresa !== '' });

    const gravar = useMutation({
        mutationFn: () => transferencias.entreEmpresas({ de_armazem: Number(deArmazem) || null, para_empresa: Number(empresa) || null, para_armazem: Number(paraArmazem) || null, notas, itens: itens.map((i) => ({ product_id: i.product_id, product_name: i.product_name, product_code: i.product_code, quantity: Number(i.quantity) || 0, unit_cost: i.unit_cost ?? 0 })) }),
        onSuccess: (r) => { porFeito(r); aoFeito(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={feito ? t('Transferido para :empresa', { empresa: feito.destino_nome }) : t('Transferir para outra empresa')} largura="lg" rodape={feito ? <Botao onClick={aoFechar}>{t('Fechar')}</Botao> : <><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-right-left" aTrabalhar={gravar.isPending} disabled={itens.length === 0} onClick={() => gravar.mutate()}>{t('Transferir')}</Botao></>}>
            {feito ? (
                <Comprovativo titulo={t('Transferido para :empresa', { empresa: feito.destino_nome })} referencias={[{ rotulo: t('Documento desta empresa'), valor: feito.referencia_origem }, { rotulo: t('Documento de :empresa', { empresa: feito.destino_nome }), valor: feito.referencia_destino }]} resumo={feito.resumo} pdf={feito.pdf} colunas={[{ chave: 'produto', rotulo: t('Artigo') }, { chave: 'quantidade', rotulo: t('Qtd.'), numero: true }, { chave: 'origem_antes', rotulo: t('Aqui antes'), numero: true }, { chave: 'origem_depois', rotulo: t('Aqui depois'), numero: true }, { chave: 'destino_antes', rotulo: t('Lá antes'), numero: true }, { chave: 'destino_depois', rotulo: t('Lá depois'), numero: true }]} />
            ) : (
                <div className="space-y-4">
                    <AvisoDeErro erro={gravar.error} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Do armazém')} erro={erros.de_armazem} obrigatorio><select value={deArmazem} onChange={(e) => { porDeArmazem(e.target.value); porItens([]); }} className={entrada}><option value="">{t('Escolher…')}</option>{o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select></Campo>
                        <Campo etiqueta={t('Para a empresa')} erro={erros.para_empresa} obrigatorio><select value={empresa} onChange={(e) => { porEmpresa(e.target.value); porParaArmazem(''); }} className={entrada}><option value="">{t('Escolher…')}</option>{o.empresas.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}</select></Campo>
                        <Campo etiqueta={t('Para o armazém')} erro={erros.para_armazem} obrigatorio>
                            <select value={paraArmazem} onChange={(e) => porParaArmazem(e.target.value)} disabled={!empresa} className={entrada}>
                                <option value="">{empresa ? t('Escolher…') : t('Escolha primeiro a empresa')}</option>
                                {(armazensDestino.data?.data ?? []).map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Motivo')} erro={erros.notas} obrigatorio><input value={notas} onChange={(e) => porNotas(e.target.value)} className={entrada} /></Campo>
                    </div>
                    <Carrinho armazem={deArmazem} itens={itens} aoMudar={porItens} comTecto />
                    {erros.itens?.[0] && <p role="alert" className="text-sm font-medium text-red-700">{erros.itens[0]}</p>}
                </div>
            )}
        </Modal>
    );
}
