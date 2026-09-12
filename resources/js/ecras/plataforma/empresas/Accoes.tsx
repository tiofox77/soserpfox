import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type EmpresaDaLista, plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { cls } from '@/ui/tokens';

/**
 * AS TRÊS ACÇÕES QUE TIRAM UMA EMPRESA DO AR — por ordem de gravidade.
 *
 *  · DESACTIVAR: ninguém entra, os dados ficam, e volta com um clique. Pede o
 *    motivo, porque o motivo vai no email a quem lá trabalha.
 *  · SUSPENDER: sai da lista e fica na base (é o `delete()` de um modelo com
 *    SoftDeletes). O botão antigo dizia «Excluir» e ninguém sabia que era isto.
 *  · APAGAR: tira mesmo da base. Mostra o que se perde e pede o nome escrito à
 *    mão — a diferença entre carregar num botão por engano e decidir.
 */
export function Desactivar({ empresa, aoFechar, aoFazer }: {
    empresa: EmpresaDaLista;
    aoFechar: () => void;
    aoFazer: (recado: string) => void;
}) {
    const [motivo, porMotivo] = useState('');

    const fazer = useMutation({
        mutationFn: () => plataforma.empresas.desactivar(empresa.id, motivo),
        onSuccess: (r) => aoFazer(r.message),
    });

    const erros = fazer.error instanceof ErroDaApi ? fazer.error.erros : {};

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Desactivar a empresa?')}
            subtitulo={empresa.nome}
            icone="fa-power-off"
            cor="aviso"
            largura="md"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="aviso" tom="solida" icone="fa-power-off" aTrabalhar={fazer.isPending} onClick={() => fazer.mutate()}>
                        {t('Desactivar')}
                    </Botao>
                </div>
            }
        >
            <div className="space-y-4">
                <AvisoDeErro erro={fazer.error} />

                <ul className="space-y-1.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <li><i className="fas fa-lock mr-2" aria-hidden="true" />{t('Ninguém desta empresa consegue entrar.')}</li>
                    <li><i className="fas fa-database mr-2" aria-hidden="true" />{t('Os dados ficam todos guardados.')}</li>
                    <li>
                        <i className="fas fa-envelope mr-2" aria-hidden="true" />
                        {t('As :n pessoa(s) recebem um email com o motivo.', { n: empresa.utilizadores })}
                    </li>
                    <li><i className="fas fa-rotate-left mr-2" aria-hidden="true" />{t('Volta a ficar activa com um clique.')}</li>
                </ul>

                <Campo
                    etiqueta={t('Motivo')}
                    obrigatorio
                    erro={erros.motivo}
                    ajuda={t('Pelo menos 10 caracteres. É o que se lê no email.')}
                >
                    <textarea
                        rows={4}
                        className={cls(entrada, 'h-auto py-2')}
                        value={motivo}
                        onChange={(e) => porMotivo(e.target.value)}
                        placeholder={t('Ex.: facturas por pagar desde Março, após três avisos.')}
                    />
                </Campo>
            </div>
        </Modal>
    );
}

export function Suspender({ empresa, aoFechar, aoFazer }: {
    empresa: EmpresaDaLista;
    aoFechar: () => void;
    aoFazer: (recado: string) => void;
}) {
    const fazer = useMutation({
        mutationFn: () => plataforma.empresas.suspender(empresa.id),
        onSuccess: (r) => aoFazer(r.message),
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Suspender a empresa?')}
            subtitulo={empresa.nome}
            icone="fa-box-archive"
            cor="perigo"
            largura="sm"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Deixar estar')}</Botao>
                    <Botao cor="perigo" tom="solida" icone="fa-box-archive" aTrabalhar={fazer.isPending} onClick={() => fazer.mutate()}>
                        {t('Suspender')}
                    </Botao>
                </div>
            }
        >
            <div className="space-y-3 text-sm text-slate-700">
                <AvisoDeErro erro={fazer.error} />
                <p>{t('A empresa sai da lista, mas os dados ficam guardados na base e podem ser recuperados.')}</p>
                <p className="text-xs text-slate-500">
                    {t('Uma empresa com actividade registada (facturas, artigos, movimentos) não se suspende: desactive-a.')}
                </p>
            </div>
        </Modal>
    );
}

export function ApagarDefinitivo({ empresa, aoFechar, aoFazer }: {
    empresa: EmpresaDaLista;
    aoFechar: () => void;
    aoFazer: (recado: string) => void;
}) {
    const [confirmacao, porConfirmacao] = useState('');

    const perdas = useQuery({
        queryKey: ['plataforma', 'empresas', 'perdas', empresa.id],
        queryFn: () => plataforma.empresas.perdas(empresa.id),
    });

    const fazer = useMutation({
        mutationFn: () => plataforma.empresas.apagar(empresa.id, confirmacao),
        onSuccess: (r) => aoFazer(r.message),
    });

    const erros = fazer.error instanceof ErroDaApi ? fazer.error.erros : {};
    const impedido = perdas.data?.impedido ?? null;
    const confere = confirmacao.trim() === (perdas.data?.nome ?? empresa.nome).trim();

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Apagar em definitivo')}
            subtitulo={empresa.nome}
            icone="fa-triangle-exclamation"
            cor="perigo"
            largura="md"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    {!impedido && (
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            disabled={!confere || perdas.isPending}
                            aTrabalhar={fazer.isPending}
                            onClick={() => fazer.mutate()}
                        >
                            {t('Apagar para sempre')}
                        </Botao>
                    )}
                </div>
            }
        >
            {perdas.isPending ? (
                <Carregando linhas={4} />
            ) : impedido ? (
                <p className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                    <i className="fas fa-landmark mr-2" aria-hidden="true" />
                    {impedido}
                </p>
            ) : (
                <div className="space-y-4">
                    <p className="text-sm text-slate-800">
                        {t('Vai apagar :nome e tudo o que é dela. Não há como voltar atrás.', { nome: empresa.nome })}
                    </p>

                    <ul className="grid grid-cols-2 gap-2 text-sm">
                        {(perdas.data?.perdas ?? []).map((p) => (
                            <li key={p.rotulo} className="rounded-lg bg-red-50 px-3 py-2 text-red-900">
                                <b className="tabular-nums">{p.quantos}</b> {p.rotulo}
                            </li>
                        ))}
                    </ul>

                    <Campo
                        etiqueta={t('Escreva o nome da empresa para confirmar')}
                        obrigatorio
                        erro={erros.confirmacao}
                        ajuda={<span className="font-mono">{perdas.data?.nome ?? empresa.nome}</span>}
                    >
                        <input
                            className={cls(entrada, 'border-2 focus-visible:border-red-500 focus-visible:ring-red-500')}
                            value={confirmacao}
                            onChange={(e) => porConfirmacao(e.target.value)}
                            autoComplete="off"
                        />
                    </Campo>
                </div>
            )}
        </Modal>
    );
}
