import { useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';

import { copiaOffline, type Inventario, type Resultado } from '@/api/offline';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';
import { Faixa, cascata } from './faixa';

/**
 * RECUPERAR UMA CÓPIA DE SEGURANÇA DO PWA.
 *
 * Em dois passos, de propósito: primeiro mostra-se o que está no ficheiro,
 * depois é que se importa. Quem recupera dados está normalmente com pressa
 * e a fazê-lo pela primeira vez, e não pode ser o clique a decidir.
 */
export default function ImportarCopiaOffline() {
    const ficheiro = useRef<HTMLInputElement>(null);
    const [nome, porNome] = useState('');
    const [inventario, porInventario] = useState<Inventario | null>(null);
    const [resultado, porResultado] = useState<{ r: Resultado; message: string } | null>(null);
    const [erro, porErro] = useState('');

    const corpo = () => { const f = new FormData(); const escolhido = ficheiro.current?.files?.[0]; if (escolhido) f.append('ficheiro', escolhido); return f; };
    const falhou = (e: unknown) => porErro(e instanceof ErroDaApi ? e.message : t('Não foi possível ler o ficheiro.'));

    const analisar = useMutation({ mutationFn: () => copiaOffline.analisar(corpo()), onSuccess: (r) => { porInventario(r.inventario); porErro(''); }, onError: (e) => { porInventario(null); falhou(e); } });
    const importar = useMutation({ mutationFn: () => copiaOffline.importar(corpo()), onSuccess: (r) => { porResultado({ r: r.resultado, message: r.message }); porInventario(null); porErro(''); }, onError: falhou });

    const escolher = () => {
        porResultado(null); porInventario(null); porErro('');
        const f = ficheiro.current?.files?.[0];
        porNome(f?.name ?? '');
        if (f) analisar.mutate();
    };

    return (
        <div className="mx-auto max-w-4xl space-y-4" data-copia-offline>
            <Faixa
                icone="fa-file-import"
                cor="bom"
                titulo={t('Importar Cópia Offline')}
                subtitulo={t('Recupera vendas e documentos de um aparelho que não chegou a sincronizar.')}
            />

            <Cartao titulo={t('Quando usar isto')} icone="fa-circle-info">
                <p className="mb-4 text-sm text-slate-600">{t('O caminho normal é o aparelho sincronizar sozinho. Isto é para quando não sincronizou e já não vai: o telemóvel partiu-se, o navegador limpou os dados, alguém reiniciou tudo antes de enviar. As vendas que lá estavam já aconteceram.')}</p>
                {/* A ZONA DE LARGAR, como no ecrã de sempre: uma caixa
                    tracejada grande e um ícone, não um `<input type=file>`
                    cinzento do sistema. Acende ao passar o rato — é o que diz
                    que se pode carregar aqui. */}
                <label className={cls('group flex cursor-pointer flex-col items-center justify-center gap-2 border-2 border-dashed border-slate-300 bg-slate-50 p-10 text-center text-sm text-slate-600', RAIO, 'transition-all duration-200 hover:border-emerald-400 hover:bg-emerald-50/50')}>
                    <i className="fas fa-cloud-arrow-up text-4xl text-slate-300 transition-all duration-200 group-hover:-translate-y-0.5 group-hover:text-emerald-500" aria-hidden="true" />
                    <span>{nome ? <strong className="text-slate-900">{nome}</strong> : t('Escolha o ficheiro .json exportado do PWA')}</span>
                    <input ref={ficheiro} type="file" accept=".json,application/json" onChange={escolher} className="sr-only" aria-label={t('Ficheiro da cópia')} />
                </label>
                {analisar.isPending && <p className="mt-3 text-sm text-slate-500"><i className="fas fa-spinner fa-spin mr-2" aria-hidden="true" />{t('A ler o ficheiro…')}</p>}
                {erro && <p role="alert" className={cls('mt-3 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900', RAIO)} data-erro>{erro}</p>}
            </Cartao>

            {inventario && (
                <Cartao titulo={t('O que está no ficheiro')} icone="fa-box-open" accoes={<Botao cor="primaria" tom="solida" icone="fa-file-import" aTrabalhar={importar.isPending} onClick={() => importar.mutate()}>{t('Importar')}</Botao>}>
                    <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-5" data-inventario>
                        {([
                            [t('Vendas'), inventario.vendas, 'fa-receipt', 'verde'],
                            [t('Clientes'), inventario.clientes, 'fa-users', 'azul'],
                            [t('Rascunhos'), inventario.rascunhos, 'fa-file-pen', 'ambar'],
                            [t('Comandas'), inventario.comandas, 'fa-utensils', 'roxo'],
                            [t('Turnos'), inventario.turnos, 'fa-cash-register', 'indigo'],
                        ] as Array<[string, number, string, TomDoCartao]>).map(([r, v, icone, tom], i) => (
                            <div key={r} className="entra" style={cascata(i)}>
                                <CartaoNumero rotulo={r} valor={v} icone={icone} tom={tom} />
                            </div>
                        ))}
                    </div>
                    <p className="mt-4 text-xs text-slate-500">{t('Gerada :quando · aparelho :aparelho · operador :operador. Nada é gravado até carregar em Importar; o que já existir no servidor não entra a dobrar.', { quando: inventario.gerado_em ?? '—', aparelho: inventario.dispositivo ?? '—', operador: inventario.operador ?? '—' })}</p>
                </Cartao>
            )}

            {resultado && (
                <Cartao titulo={t('Importação concluída')} icone="fa-circle-check">
                    <p role="status" className={cls('border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)} data-resultado><i className="fas fa-circle-check mr-2" aria-hidden="true" />{resultado.message}</p>
                    <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-4">
                        {Object.entries(resultado.r).filter(([, v]) => typeof v !== 'object').map(([k, v]) => <div key={k}><dt className="text-xs uppercase tracking-wider text-slate-500">{k.replace(/_/g, ' ')}</dt><dd className="font-semibold text-slate-900">{String(v)}</dd></div>)}
                    </dl>
                </Cartao>
            )}
        </div>
    );
}
