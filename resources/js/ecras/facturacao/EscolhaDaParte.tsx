import { useMemo, useState } from 'react';
import { useMutation } from '@tanstack/react-query';

import { PARTE_VAZIA, partes as portaDasPartes, type CriarParte, type Parte, type ParteRapida } from '@/api/partes';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * ESCOLHER O CLIENTE (OU O FORNECEDOR) DE UM DOCUMENTO — e criá-lo ali mesmo
 * quando ainda não existe.
 *
 * Duas coisas que a migração para React deixou pelo caminho e que aqui voltam:
 *
 * 1. A PROCURA. Um `<select>` com todos os clientes lá dentro funciona com
 *    doze e é inutilizável com quatrocentos — e é com quatrocentos que as
 *    empresas trabalham. A caixa de procura filtra por nome e por NIF, como o
 *    `searchClient` do ecrã de sempre.
 *
 * 2. O CLIENTE RÁPIDO. Está-se a emitir, o cliente não existe, e criá-lo
 *    obrigava a largar o documento a meio e a reescrever as linhas. O botão
 *    abre um formulário com os cinco campos de sempre — nome, NIF, email,
 *    telefone e morada — e, criado, ele fica ESCOLHIDO no documento. Era esse
 *    o ponto.
 *
 * A criação passa pela porta que já existe (`@/api/partes`): as validações e
 * as permissões são as mesmas do ecrã completo. Quem não pode criar não vê o
 * botão — e o servidor recusa na mesma, porque um botão escondido não é uma
 * guarda.
 */
export function EscolhaDaParte({
    criar,
    partes,
    valor,
    aoEscolher,
    erro,
    className,
}: {
    /** O que o servidor disse: que parte é, se pode criar, e o país por omissão. */
    criar: CriarParte;
    partes: ReadonlyArray<Parte>;
    valor: string;
    aoEscolher: (id: string) => void;
    erro?: string[];
    className?: string;
}) {
    const [procura, porProcura] = useState('');
    const [aberto, porAberto] = useState(false);
    const [rascunho, porRascunho] = useState<ParteRapida>({ ...PARTE_VAZIA });
    const [errosDoNovo, porErrosDoNovo] = useState<Record<string, string[]>>({});
    /* As criadas nesta sessão. As opções vêm em cache; sem isto, o que se
       acabou de criar não estaria na lista onde tem de ficar escolhido. */
    const [criadas, porCriadas] = useState<Parte[]>([]);

    const eFornecedor = criar.tipo === 'fornecedor';
    const etiqueta = eFornecedor ? t('Fornecedor') : t('Cliente');

    const todas = useMemo(() => {
        const porId = new Map<number, Parte>();
        for (const p of [...partes, ...criadas]) porId.set(p.id, p);

        return [...porId.values()];
    }, [partes, criadas]);

    /*
     * O QUE ESTÁ ESCOLHIDO NUNCA DESAPARECE DA LISTA.
     *
     * Um `<select>` cujo `value` não está entre as opções mostra-se vazio — e
     * o documento parecia ter perdido o cliente só porque alguém escreveu na
     * caixa de procura.
     */
    const visiveis = useMemo(() => {
        const termo = procura.trim().toLowerCase();

        if (termo === '') return todas;

        return todas.filter(
            (p) => String(p.id) === valor || `${p.name} ${p.nif ?? ''}`.toLowerCase().includes(termo),
        );
    }, [todas, procura, valor]);

    /* A que está escolhida agora — para o ecrã poder dizer o nome e o NIF. */
    const escolhida = todas.find((p) => String(p.id) === valor) ?? null;

    const gravar = useMutation({
        mutationFn: () => portaDasPartes.criar(criar, rascunho),
        onSuccess: (nova) => {
            porCriadas((c) => [...c, nova]);
            // Criado, fica ESCOLHIDO. Era esse o ponto.
            aoEscolher(String(nova.id));
            porProcura('');
            porErrosDoNovo({});
            porRascunho({ ...PARTE_VAZIA });
            porAberto(false);
        },
        onError: (e) => porErrosDoNovo(e instanceof ErroDaApi ? e.erros : {}),
    });

    const escrever = (campo: keyof ParteRapida, texto: string) =>
        porRascunho((r) => ({ ...r, [campo]: texto }));

    return (
        <div className={cls('min-w-0', className)}>
            <div className="mb-2 flex gap-2">
                {/* A LUPA DENTRO DA CAIXA — é assim que se reconhece uma caixa
                    de procura antes de se ler o que lá está escrito, e era
                    assim no ecrã de sempre. */}
                <div className="relative min-w-0 flex-1">
                    <i
                        className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        type="search"
                        value={procura}
                        onChange={(e) => porProcura(e.target.value)}
                        placeholder={t('Procurar por nome ou NIF…')}
                        aria-label={eFornecedor ? t('Procurar fornecedor') : t('Procurar cliente')}
                        className={cls(entrada, 'w-full pl-9')}
                    />
                </div>

                {/* O botão de criar é VERDE e não da cor do ecrã: cria uma
                    ficha nova, e no Blade era sempre o mesmo verde nos quatro
                    editores, fosse o ecrã roxo, laranja ou azul. */}
                {criar.pode && (
                    <Botao
                        cor="bom"
                        tom="solida"
                        icone="fa-plus"
                        className="flex-none whitespace-nowrap"
                        onClick={() => {
                            porErrosDoNovo({});
                            porRascunho((r) => ({ ...r, name: r.name || procura.trim() }));
                            porAberto(true);
                        }}
                    >
                        {eFornecedor ? t('Novo fornecedor') : t('Novo cliente')}
                    </Botao>
                )}
            </div>

            <Campo etiqueta={etiqueta} erro={erro} obrigatorio>
                <select value={valor} onChange={(e) => aoEscolher(e.target.value)} className={entrada}>
                    <option value="">{t('Escolher…')}</option>
                    {visiveis.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}
                            {p.nif ? ` · ${p.nif}` : ''}
                        </option>
                    ))}
                </select>
            </Campo>

            {procura.trim() !== '' && (
                <p role="status" className="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                    <i
                        className={`fas ${visiveis.length === 0 ? 'fa-circle-exclamation text-amber-500' : 'fa-filter'}`}
                        aria-hidden="true"
                    />
                    {visiveis.length === 0
                        ? t('Nada encontrado. Apague a procura para ver todos.')
                        : t(':quantos de :total', { quantos: visiveis.length, total: todas.length })}
                </p>
            )}

            {/* QUEM FICOU ESCOLHIDO, dito por extenso.
                Uma linha de `<select>` com quatrocentas opções lê-se mal, e o
                NIF — que é o que a AGT compara — não cabe lá. O ecrã em Blade
                mostrava o cliente escolhido num cartão por baixo; é o mesmo. */}
            {escolhida && (
                <p className={cls('mt-2 flex items-center gap-2 border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900', RAIO)}>
                    <i className="fas fa-circle-check flex-none text-emerald-600" aria-hidden="true" />
                    <span className="min-w-0 truncate">
                        <strong className="font-bold">{escolhida.name}</strong>
                        {escolhida.nif ? ` · ${escolhida.nif}` : ''}
                    </span>
                </p>
            )}

            <Modal
                aberto={aberto}
                aoFechar={() => porAberto(false)}
                titulo={eFornecedor ? t('Novo fornecedor') : t('Novo cliente')}
                subtitulo={t('Fica escolhido no documento, sem perder o que já escreveu.')}
                icone={eFornecedor ? 'fa-truck' : 'fa-user-plus'}
                cor="bom"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => porAberto(false)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="bom"
                            tom="solida"
                            icone="fa-check"
                            aTrabalhar={gravar.isPending}
                            onClick={() => gravar.mutate()}
                        >
                            {t('Criar e escolher')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={gravar.error} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Nome')} erro={errosDoNovo.name} obrigatorio className="sm:col-span-2">
                        <input
                            value={rascunho.name}
                            onChange={(e) => escrever('name', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    {/* O NIF é o do verificador angolano, e é único nesta
                        empresa: quem valida é o servidor, e o que ele disser
                        aparece aqui por baixo. */}
                    <Campo etiqueta={t('NIF')} erro={errosDoNovo.nif} obrigatorio={!eFornecedor}>
                        <input
                            value={rascunho.nif}
                            onChange={(e) => escrever('nif', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Email')} erro={errosDoNovo.email}>
                        <input
                            type="email"
                            value={rascunho.email}
                            onChange={(e) => escrever('email', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Telefone')} erro={errosDoNovo.phone}>
                        <input
                            value={rascunho.phone}
                            onChange={(e) => escrever('phone', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Morada')} erro={errosDoNovo.address}>
                        <input
                            value={rascunho.address}
                            onChange={(e) => escrever('address', e.target.value)}
                            className={entrada}
                        />
                    </Campo>
                </div>

                <p className="mt-4 text-xs text-slate-500">
                    {t('A ficha completa — morada, província, condições de pagamento — edita-se depois no ecrã próprio.')}
                </p>
            </Modal>
        </div>
    );
}
