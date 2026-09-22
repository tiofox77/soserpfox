import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type Escolha, type FichaDaEmpresa, plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { RAIO, TRANSICAO, cls } from '@/ui/tokens';

const VAZIA: FichaDaEmpresa = {
    id: 0, name: '', slug: '', email: '', phone: '', company_name: '', nif: '',
    address: '', city: '', postal_code: '', country: 'AO',
    max_users: 5, max_storage_mb: 1000, is_active: true,
};

/**
 * A FICHA DE UMA EMPRESA — criar e editar.
 *
 * Os campos são os do modal antigo, mais o PAÍS: a coluna existia e o SAF-T
 * leva-o, mas não havia campo — ficava o que o código decidisse.
 *
 * E O LIMITE DA FICHA NÃO DESCE ABAIXO DO PLANO: o servidor sobe-o ao gravar.
 * Aqui diz-se isso antes de gravar, em vez de o número mudar sem explicação.
 */
export function Formulario({ id, paises, aoFechar, aoGuardar }: {
    id: number | null;
    paises: Escolha[];
    aoFechar: () => void;
    aoGuardar: (recado: string) => void;
}) {
    const [f, porF] = useState<FichaDaEmpresa>(VAZIA);
    const [slugTocado, porSlugTocado] = useState(false);

    const ficha = useQuery({
        queryKey: ['plataforma', 'empresas', 'ficha', id],
        queryFn: () => plataforma.empresas.ficha(id!),
        enabled: id !== null,
    });

    useEffect(() => {
        if (ficha.data) {
            porF(ficha.data.ficha);
            porSlugTocado(true);
        }
    }, [ficha.data]);

    const guardar = useMutation({
        mutationFn: () => plataforma.empresas.guardar(id, { ...f }),
        onSuccess: (r) => aoGuardar(r.message),
    });

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const doPlano = ficha.data?.do_plano;

    const mexer = <K extends keyof FichaDaEmpresa>(campo: K, valor: FichaDaEmpresa[K]) =>
        porF((a) => ({ ...a, [campo]: valor }));

    // O IDENTIFICADOR SAI DO NOME enquanto ninguém lhe tocar: escrever duas
    // vezes a mesma coisa, com hífens à mão, era onde nasciam os erros.
    const mudarNome = (nome: string) => porF((a) => ({
        ...a,
        name: nome,
        slug: slugTocado ? a.slug : nome.normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''),
    }));

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={id ? t('Editar empresa') : t('Nova empresa')}
            subtitulo={id ? f.name : t('Nasce com os papéis, o plano de contas e as definições que precisa para funcionar')}
            icone="fa-building"
            cor="primaria"
            largura="xl"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>
                        {id ? t('Guardar') : t('Criar empresa')}
                    </Botao>
                </div>
            }
        >
            {id !== null && ficha.isPending ? (
                <Carregando linhas={8} />
            ) : (
                <div className="space-y-5">
                    <AvisoDeErro erro={guardar.error} />

                    <fieldset className="grid gap-4 sm:grid-cols-2">
                        <legend className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-id-badge mr-1.5 text-blue-500" aria-hidden="true" />{t('Identificação')}
                        </legend>
                        <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                            <input className={entrada} value={f.name} onChange={(e) => mudarNome(e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Identificador')} obrigatorio erro={erros.slug} ajuda={t('Minúsculas e hífens.')}>
                            <input
                                className={entrada}
                                value={f.slug}
                                onChange={(e) => { porSlugTocado(true); mexer('slug', e.target.value); }}
                            />
                        </Campo>
                        <Campo etiqueta={t('Razão social')} erro={erros.company_name}>
                            <input className={entrada} value={f.company_name ?? ''} onChange={(e) => mexer('company_name', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('NIF')} erro={erros.nif} ajuda={t('O NIF de uma empresa angolana começa por 5, ou tem dez dígitos começados por 0.')}>
                            <input className={cls(entrada, 'font-mono')} value={f.nif ?? ''} onChange={(e) => mexer('nif', e.target.value)} />
                        </Campo>
                    </fieldset>

                    <fieldset className="grid gap-4 sm:grid-cols-2">
                        <legend className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-address-card mr-1.5 text-emerald-500" aria-hidden="true" />{t('Contacto e morada')}
                        </legend>
                        <Campo etiqueta={t('Email')} obrigatorio erro={erros.email}>
                            <input type="email" className={entrada} value={f.email} onChange={(e) => mexer('email', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Telefone')} erro={erros.phone}>
                            <input className={entrada} value={f.phone ?? ''} onChange={(e) => mexer('phone', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Morada')} erro={erros.address} className="sm:col-span-2">
                            <input className={entrada} value={f.address ?? ''} onChange={(e) => mexer('address', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Cidade')} erro={erros.city}>
                            <input className={entrada} value={f.city ?? ''} onChange={(e) => mexer('city', e.target.value)} />
                        </Campo>
                        <div className="grid grid-cols-2 gap-4">
                            <Campo etiqueta={t('Código postal')} erro={erros.postal_code}>
                                <input className={entrada} value={f.postal_code ?? ''} onChange={(e) => mexer('postal_code', e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('País')} obrigatorio erro={erros.country}>
                                <select className={entrada} value={f.country} onChange={(e) => mexer('country', e.target.value)}>
                                    {paises.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                                </select>
                            </Campo>
                        </div>
                    </fieldset>

                    <fieldset className="grid gap-4 sm:grid-cols-3">
                        <legend className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-ruler mr-1.5 text-orange-500" aria-hidden="true" />{t('Limites')}
                        </legend>
                        <Campo
                            etiqueta={t('Utilizadores')}
                            obrigatorio
                            erro={erros.max_users}
                            ajuda={doPlano && doPlano.max_users > 0
                                ? t('O plano :p dá :n — a ficha só serve para dar mais.', { p: doPlano.nome ?? '', n: doPlano.max_users })
                                : undefined}
                        >
                            <input type="number" min="1" className={entrada} value={f.max_users} onChange={(e) => mexer('max_users', Number(e.target.value))} />
                        </Campo>
                        <Campo
                            etiqueta={t('Espaço (MB)')}
                            obrigatorio
                            erro={erros.max_storage_mb}
                            ajuda={doPlano && doPlano.max_storage_mb > 0
                                ? t('O plano dá :n MB.', { n: doPlano.max_storage_mb })
                                : undefined}
                        >
                            <input type="number" min="100" step="100" className={entrada} value={f.max_storage_mb} onChange={(e) => mexer('max_storage_mb', Number(e.target.value))} />
                        </Campo>
                        <label className={cls(
                            'flex cursor-pointer items-start gap-2.5 self-end border px-3 py-2.5', RAIO, TRANSICAO,
                            f.is_active ? 'border-emerald-300 bg-emerald-50/60' : 'border-slate-200',
                        )}>
                            <input
                                type="checkbox"
                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                                checked={f.is_active}
                                onChange={(e) => mexer('is_active', e.target.checked)}
                            />
                            <span>
                                <span className="block text-sm font-semibold text-slate-800">{t('Activa')}</span>
                                <span className="block text-[11px] text-slate-500">{t('Desligada, ninguém entra.')}</span>
                            </span>
                        </label>
                    </fieldset>
                </div>
            )}
        </Modal>
    );
}
