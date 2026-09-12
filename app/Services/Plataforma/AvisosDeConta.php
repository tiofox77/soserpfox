<?php

namespace App\Services\Plataforma;

use App\Models\EmailTemplate;
use App\Models\SmtpSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OS AVISOS DE CONTA — suspendemos, reactivámos, e aqui tem as credenciais.
 *
 * PORQUE EXISTE ESTE FICHEIRO: os três avisos estavam escritos três vezes no
 * ecrã das empresas, cada um com o seu bloco de «ir buscar o SMTP, configurar,
 * ir buscar o modelo, montar os dados, enviar» — perto de duzentas linhas
 * praticamente iguais, e dezoito chamadas ao `Log` a dizer que cada passo
 * correu bem. Três cópias do mesmo caminho é três sítios para arranjar quando
 * um deles está errado, e nenhum sítio para o testar.
 *
 * O QUE SE CORRIGIU AO JUNTAR:
 *
 *  · O SMTP DA PLATAFORMA carrega-se UMA VEZ por aviso, e não uma vez por
 *    destinatário. Com trinta pessoas numa empresa eram trinta idas à base
 *    para ler a mesma linha de configuração.
 *  · UM DESTINATÁRIO QUE FALHA NÃO CALA OS OUTROS. O `try` envolvia o ciclo
 *    inteiro: um endereço inválido a meio da lista e ninguém depois dele
 *    recebia nada — sem que se soubesse quem tinha ficado de fora.
 *  · QUEM FICOU DE FORA fica dito. O ecrã prometia «a notificar 30 pessoas» e
 *    não havia como saber se alguma recebeu; agora conta-se o que saiu.
 *
 * O QUE NÃO MUDOU: nada disto pode fazer uma suspensão falhar. Suspender uma
 * empresa é uma decisão administrativa e já está gravada quando isto corre — se
 * o servidor de email estiver em baixo, isto registra e devolve.
 */
class AvisosDeConta
{
    /** A conta foi suspensa. */
    public const SUSPENSA = 'account_suspended';

    /** A conta voltou. */
    public const REACTIVADA = 'account_reactivated';

    /**
     * Avisa todos os utilizadores de uma empresa.
     *
     * @return array{enviados: int, falhados: int}
     */
    public function paraAEmpresa(Tenant $empresa, string $modelo): array
    {
        $smtp = $this->smtpDaPlataforma();
        $molde = $this->molde($modelo);

        if (! $smtp || ! $molde) {
            return ['enviados' => 0, 'falhados' => 0];
        }

        $enviados = 0;
        $falhados = 0;

        foreach ($empresa->users()->get() as $pessoa) {
            if (! $pessoa->email) {
                $falhados++;

                continue;
            }

            $dados = [
                'user_name' => $pessoa->name,
                'tenant_name' => $empresa->name,
                'reason' => $empresa->deactivation_reason ?: __('Conta suspensa por motivos administrativos.'),
                'app_name' => config('app.name', 'SOS ERP'),
                'app_url' => config('app.url'),
                'login_url' => route('login'),
                'support_email' => $smtp->from_email,
            ];

            // UM A UM: um endereço inválido não pode calar a lista inteira.
            if ($this->enviar($pessoa, $molde, $dados)) {
                $enviados++;
            } else {
                $falhados++;
            }
        }

        return ['enviados' => $enviados, 'falhados' => $falhados];
    }

    /**
     * As credenciais de quem acabou de ser criado.
     *
     * A senha vem por parâmetro e não fica em sítio nenhum depois disto — é o
     * único email em que ela viaja.
     */
    public function credenciais(User $pessoa, string $senha, Tenant $empresa): bool
    {
        $smtp = $this->smtpDaPlataforma();

        if (! $smtp || ! $pessoa->email) {
            return false;
        }

        // O modelo próprio primeiro; o de boas-vindas serve de reserva.
        $molde = EmailTemplate::where('slug', 'new-user')->first()
            ?? EmailTemplate::where('slug', 'welcome')->first();

        if (! $molde) {
            Log::error('Nenhum modelo de email para as credenciais de um utilizador novo.');

            return false;
        }

        return $this->enviar($pessoa, $molde, [
            'user_name' => $pessoa->name,
            'user_email' => $pessoa->email,
            'user_password' => $senha,
            'tenant_name' => $empresa->name,
            'tenant_email' => $empresa->email,
            'tenant_domain' => $empresa->domain,
            'app_name' => config('app.name', 'SOS ERP'),
            'app_url' => config('app.url'),
            'login_url' => route('login'),
            'support_email' => $smtp->from_email,
        ]);
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    /**
     * O SMTP da plataforma, configurado — uma vez por aviso.
     *
     * `getForTenant(null)` é o da plataforma: estes avisos saem do dono, não da
     * empresa que está a ser suspensa (que pode nem ter SMTP).
     */
    private function smtpDaPlataforma(): ?SmtpSetting
    {
        $smtp = SmtpSetting::getForTenant(null);

        if (! $smtp) {
            Log::error('Sem configuração SMTP da plataforma: nenhum aviso de conta pode sair.');

            return null;
        }

        $smtp->configure();

        return $smtp;
    }

    private function molde(string $slug): ?EmailTemplate
    {
        $molde = EmailTemplate::where('slug', $slug)->first();

        if (! $molde) {
            Log::error("Modelo de email '{$slug}' não existe: o aviso não sai.");
        }

        return $molde;
    }

    /** @param array<string, mixed> $dados */
    private function enviar(User $pessoa, EmailTemplate $molde, array $dados): bool
    {
        try {
            $feito = $molde->render($dados);

            Mail::send([], [], function ($mensagem) use ($pessoa, $feito) {
                $mensagem->to($pessoa->email, $pessoa->name)
                    ->subject($feito['subject'])
                    ->html($feito['body_html']);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Aviso de conta não enviado', [
                'modelo' => $molde->slug,
                'user_id' => $pessoa->id,
                'erro' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
