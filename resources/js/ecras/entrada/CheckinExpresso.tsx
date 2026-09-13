import { t } from '@/i18n';

import { BotaoDeEnviar, Formulario, Recados, type Recado } from './comum';

/**
 * O CHECK-IN EXPRESSO DO HÓSPEDE — pelo QR do voucher, sem conta.
 *
 * O Blade só mostrava o recado de sucesso: quando a reserva não permitia
 * check-in (cancelada, já fechada), o `->with('error')` perdia-se e o botão
 * continuava lá, a não fazer nada à vista de quem carregava.
 */
type Props = {
    csrf: string;
    acao: string;
    recados?: Recado[];
    hotel: { nome: string; telefone: string | null; email: string | null };
    reserva: { numero: string; hospede: string; entrada: string; saida: string; quarto: string | null; tipo: string | null };
    ja_entrou: boolean;
};

export default function CheckinExpresso(p: Props) {
    const r = p.reserva;
    const linhas: Array<[string, string, string]> = [
        ['fa-hashtag', t('Reserva'), r.numero],
        ['fa-user', t('Hóspede'), r.hospede],
        ['fa-right-to-bracket', t('Check-in'), r.entrada],
        ['fa-right-from-bracket', t('Check-out'), r.saida],
        ['fa-bed', t('Quarto'), `${r.quarto ? t('Nº :n', { n: r.quarto }) : t('A atribuir')}${r.tipo ? ` · ${r.tipo}` : ''}`],
    ];

    return (
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-purple-600 via-indigo-600 to-blue-700 p-4">
            <div className="animate-scale-in w-full max-w-lg rounded-3xl bg-white p-8 shadow-2xl">
                <div className="mb-6 text-center">
                    <span className="mb-4 inline-flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 shadow-lg">
                        <i className="fas fa-hotel icon-float text-3xl text-white" aria-hidden="true" />
                    </span>
                    <h1 className="text-2xl font-bold text-gray-800">{p.hotel.nome}</h1>
                    <p className="mt-1 text-sm text-gray-500">{t('Check-in Expresso')}</p>
                </div>

                <Recados recados={p.recados} />

                <dl className="mb-6 space-y-3 rounded-2xl bg-gray-50 p-5">
                    {linhas.map(([icone, rotulo, valor], i) => (
                        <div key={rotulo} className="entra flex items-center justify-between gap-4" style={{ ['--i' as string]: i }}>
                            <dt className="flex items-center gap-2 text-sm text-gray-500"><i className={`fas ${icone} w-4 text-indigo-400`} aria-hidden="true" />{rotulo}</dt>
                            <dd className="text-right font-bold text-gray-800">{valor}</dd>
                        </div>
                    ))}
                </dl>

                {p.ja_entrou ? (
                    <div className="animate-fade-in rounded-2xl border-2 border-emerald-400 bg-emerald-100 p-4 text-center text-emerald-800">
                        <i className="fas fa-check-circle mb-2 text-4xl" aria-hidden="true" />
                        <h2 className="text-lg font-bold">{t('Check-in já efectuado!')}</h2>
                        <p className="mt-1 text-sm">{t('Desejamos-lhe uma óptima estadia.')}</p>
                    </div>
                ) : (
                    <>
                        <Formulario acao={p.acao} csrf={p.csrf}>
                            {(aEnviar) => (
                                <BotaoDeEnviar aEnviar={aEnviar} icone="fa-check" grande cor="from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700">
                                    {t('Confirmar Check-in')}
                                </BotaoDeEnviar>
                            )}
                        </Formulario>
                        <p className="mt-4 text-center text-xs text-gray-400">{t('Ao confirmar, a sua reserva ficará com estado Check-in.')}</p>
                    </>
                )}

                {(p.hotel.telefone || p.hotel.email) && (
                    <div className="mt-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-t border-gray-200 pt-6 text-sm text-gray-500">
                        {p.hotel.telefone && <a href={`tel:${p.hotel.telefone}`} className="hover:text-indigo-600"><i className="fas fa-phone mr-1" aria-hidden="true" />{p.hotel.telefone}</a>}
                        {p.hotel.telefone && p.hotel.email && <span aria-hidden="true">·</span>}
                        {p.hotel.email && <a href={`mailto:${p.hotel.email}`} className="hover:text-indigo-600"><i className="fas fa-envelope mr-1" aria-hidden="true" />{p.hotel.email}</a>}
                    </div>
                )}
            </div>
        </div>
    );
}
