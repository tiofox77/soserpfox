import { useState, type CSSProperties } from 'react';
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
import { Paginacao } from '@/ui/Paginacao';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
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
 *
 * O ASPECTO É O DE SEMPRE: o aviso do que a transferência faz de verdade —
 * tira de um lado e põe no outro — voltou a ser uma caixa que se vê, a lista
 * ganhou o cabeçalho de fundo e a cascata, e o vazio tem o prédio dentro do
 * círculo com a frase que diz o que fazer.
 */

/** O atraso da linha `i` na entrada em cascata (ver `.entra` no layout). */
const cascata = (i: number) => ({ '--i': i }) as CSSProperties;

export default function TransferenciasEntreEmpresas() {
    const cache = useQueryClient();
    const [pagina, porPagina] = useState(1);
    const [aberto, porAberto] = useState(false);
    const [recado, porRecado] = useRecadoNoCanto('');

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
                titulo={t('Transferências entre Empresas')}
                icone="fa-building-circle-arrow-right"
                accoes={o.permissoes.pode_entre_empresas && o.empresas.length > 0 && <Botao cor="primaria" tom="solida" icone="fa-right-left" onClick={() => porAberto(true)}>{t('Nova transferência')}</Botao>}
            >
                {/* A caixa azul do ecrã de sempre. Não é decoração: é onde se
                    diz que o stock SAI de uma empresa e ENTRA noutra, antes de
                    alguém carregar no botão a achar que é uma cópia. */}
                <p className={cls('flex items-start gap-3 border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900', RAIO)}>
                    <i className="fas fa-circle-info mt-0.5 flex-none text-base text-blue-500" aria-hidden="true" />
                    <span>
                        {o.empresas.length === 0
                            ? t('Só se transfere para outra empresa a que também tenha acesso — e esta conta só tem esta.')
                            : t('Cada empresa fica com o seu documento, na sua própria numeração.')}
                    </span>
                </p>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-4 py-3 font-bold"><i className="fas fa-clock mr-1.5 text-slate-400" aria-hidden="true" />{t('Quando')}</th><th className="px-4 py-3 font-bold">{t('Sentido')}</th><th className="px-4 py-3 font-bold"><i className="fas fa-box mr-1.5 text-indigo-500" aria-hidden="true" />{t('Artigo')}</th><th className="px-4 py-3 font-bold"><i className="fas fa-warehouse mr-1.5 text-blue-500" aria-hidden="true" />{t('Armazém')}</th><th className="px-4 py-3 text-right font-bold">{t('Qtd.')}</th><th className="px-4 py-3 font-bold">{t('Referência')}</th><th className="px-4 py-3 font-bold">{t('Notas')}</th></tr></thead>
                        <tbody className={cls('divide-y divide-slate-100', historico.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-6 py-16">
                                        {historico.isPending ? (
                                            <p className="text-center text-slate-400">{t('A carregar…')}</p>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center text-center">
                                                <div className="mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                                    <i className="fas fa-building text-3xl text-slate-400" aria-hidden="true" />
                                                </div>
                                                <p className="text-lg font-semibold text-slate-500">{t('Nenhuma transferência realizada')}</p>
                                                <p className="mt-2 text-sm text-slate-400">{t('Use o botão acima para transferir stock entre empresas')}</p>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            )}
                            {linhas.map((m, i) => (
                                <tr key={m.id} style={cascata(i)} className="entra transition-all duration-200 hover:bg-purple-50/60">
                                    <td className="px-4 py-2 tabular-nums text-slate-600">{m.quando}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={m.sentido === 'entrada' ? 'bom' : 'aviso'} icone={m.sentido === 'entrada' ? 'fa-arrow-down' : 'fa-arrow-up'}>{m.sentido === 'entrada' ? t('Recebido') : t('Enviado')}</Etiqueta></td>
                                    <td className="px-4 py-2 font-medium text-slate-800">{m.artigo}{m.codigo && <span className="ml-2 font-mono text-xs font-normal text-slate-400">{m.codigo}</span>}</td>
                                    <td className="px-4 py-2">{m.armazem ? <Etiqueta cor="neutra" icone="fa-warehouse">{m.armazem}</Etiqueta> : <span className="text-slate-300">—</span>}</td>
                                    <td className="px-4 py-2 text-right font-bold tabular-nums text-slate-900">{m.quantidade.toLocaleString('pt-PT')}</td>
                                    <td className="px-4 py-2 font-mono text-xs">{m.referencia}</td>
                                    <td className="px-4 py-2 text-xs text-slate-500">{m.notas}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && (
                    <Paginacao
                        pagina={contas.current_page}
                        ultima={contas.last_page}
                        total={contas.total}
                        aCarregar={historico.isFetching}
                        aMudar={porPagina}
                    />
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
        <Modal aberto aoFechar={aoFechar} titulo={feito ? t('Transferido para :empresa', { empresa: feito.destino_nome }) : t('Transferir para outra empresa')} subtitulo={feito ? undefined : t('Cada empresa fica com o seu documento, na sua própria numeração.')} icone={feito ? 'fa-circle-check' : 'fa-building-circle-arrow-right'} cor={feito ? 'bom' : 'primaria'} largura="lg" rodape={feito ? <Botao onClick={aoFechar}>{t('Fechar')}</Botao> : <><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-right-left" aTrabalhar={gravar.isPending} disabled={itens.length === 0} onClick={() => gravar.mutate()}>{t('Transferir')}</Botao></>}>
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
                    {/* O aviso amarelo do ecrã de sempre. Isto move mercadoria
                        entre duas contabilidades: quem carrega no botão tem de
                        o ler antes, não depois. */}
                    <p className={cls('flex items-start gap-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                        <i className="fas fa-triangle-exclamation mt-0.5 flex-none text-base text-amber-500" aria-hidden="true" />
                        <span><strong>{t('Atenção:')}</strong> {t('Esta acção é irreversível. O stock será removido da empresa origem e adicionado à empresa destino.')}</span>
                    </p>
                    {erros.itens?.[0] && <p role="alert" className="text-sm font-medium text-red-700">{erros.itens[0]}</p>}
                </div>
            )}
        </Modal>
    );
}
