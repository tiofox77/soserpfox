import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { pin } from '@/api/offline';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { t } from '@/i18n';

/**
 * O PIN DE TURNO: a credencial de chão de loja que abre turno offline no
 * POS. Confirma-se com a palavra-passe para ninguém o definir a partir de
 * uma sessão deixada aberta; o que fica guardado é só o bcrypt, que segue
 * para os tablets na próxima sincronização.
 */
export default function DefinirPin() {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['pin'], queryFn: pin.estado });
    const [forma, porForma] = useState({ pin: '', pin_confirmation: '', password: '' });
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [recado, porRecado] = useRecadoNoCanto('');
    // O recado vai para o canto; o regresso ao PWA precisa de saber que correu bem.
    const [definido, porDefinido] = useState(false);

    const guardar = useMutation({
        mutationFn: () => pin.definir(forma),
        onSuccess: (r) => { porRecado(r.message); porDefinido(true); porErros({}); porForma({ pin: '', pin_confirmation: '', password: '' }); void cache.invalidateQueries({ queryKey: ['pin'] }); },
        onError: (e) => { porErros(e instanceof ErroDaApi ? e.erros : {}); porForma((f) => ({ ...f, pin: '', pin_confirmation: '' })); },
    });

    if (q.isPending) return <Carregando linhas={4} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o PIN')}</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const m = (chave: keyof typeof forma) => (e: React.ChangeEvent<HTMLInputElement>) => porForma({ ...forma, [chave]: e.target.value });
    // Só dígitos no PIN, como no modal da Gestão de Utilizadores. Com o
    // `pattern` o browser recusava o envio com uma bolha nativa e o ecrã não
    // dizia nada.
    const soDigitos = (chave: 'pin' | 'pin_confirmation') => (e: React.ChangeEvent<HTMLInputElement>) => porForma({ ...forma, [chave]: e.target.value.replace(/\D/g, '') });

    // Quem veio do PWA volta ao PWA — só para uma morada do próprio PWA.
    const voltar = new URLSearchParams(window.location.search).get('voltar');
    const regresso = voltar && /^\/invoicing\/offline(\/|$)/.test(voltar) ? voltar : null;

    return (
        <div className="mx-auto max-w-lg space-y-4" data-definir-pin>
            {recado && <p role="status" className={cls('border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</p>}
            {definido && regresso && (
                <a href={regresso} className={cls('flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 text-sm font-bold text-white shadow-lg transition-all duration-200 hover:-translate-y-0.5', RAIO)}>
                    <i className="fas fa-mobile-screen-button" aria-hidden="true" />{t('Voltar ao POS offline')}
                </a>
            )}
            {/* Sem faixa de gradiente, e de propósito: o ecrã em Blade era uma
                ficha estreita com um cartão só, e uma faixa a toda a largura
                por cima de um formulário de três campos ficava a gritar. */}
            <Cartao titulo={t('PIN de turno')} icone="fa-key" accoes={q.data.ja_tem_pin ? <Etiqueta cor="bom" icone="fa-check">{t('Já tem PIN')}</Etiqueta> : <Etiqueta cor="aviso" icone="fa-triangle-exclamation">{t('Sem PIN')}</Etiqueta>}>
                <AvisoDeErro erro={guardar.error} />
                <p className="mb-4 text-sm text-slate-600">{t(':nome, o PIN abre o seu turno no POS quando não há internet. Quatro a seis dígitos, e nada de óbvio.', { nome: q.data.nome })}</p>
                <form className="grid gap-4" onSubmit={(e) => { e.preventDefault(); guardar.mutate(); }}>
                    {/* O PIN escreve-se ao centro e espaçado, como no ecrã de
                        sempre: quatro a seis dígitos lêem-se um a um. */}
                    <Campo etiqueta={t('PIN novo')} erro={erros.pin} obrigatorio><input type="password" inputMode="numeric" maxLength={6} autoComplete="off" value={forma.pin} onChange={soDigitos('pin')} className={cls(entrada, 'h-12 text-center font-mono text-lg tracking-[0.4em]')} /></Campo>
                    <Campo etiqueta={t('Repita o PIN')} erro={erros.pin_confirmation} obrigatorio><input type="password" inputMode="numeric" maxLength={6} autoComplete="off" value={forma.pin_confirmation} onChange={soDigitos('pin_confirmation')} className={cls(entrada, 'h-12 text-center font-mono text-lg tracking-[0.4em]')} /></Campo>
                    <div className="border-t border-slate-100 pt-4">
                        <Campo etiqueta={t('A sua palavra-passe')} erro={erros.password} obrigatorio><input type="password" autoComplete="current-password" value={forma.password} onChange={m('password')} className={entrada} /></Campo>
                    </div>
                    <div><Botao cor="primaria" tom="solida" altura="grande" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} className="w-full">{q.data.ja_tem_pin ? t('Mudar o PIN') : t('Definir o PIN')}</Botao></div>
                    <p className="text-xs text-slate-400">{t('Guardamos apenas um verificador do PIN, nunca o PIN em si. Ele só vai para os tablets da sua empresa e deixa de valer 14 dias após a última sincronização.')}</p>
                </form>
            </Cartao>
        </div>
    );
}
