<?php

namespace App\Services\Clientes;

use App\Mail\AcessoAoPortalDoCliente;
use App\Models\Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Dar (ou repor) o acesso de um cliente ao portal.
 *
 * O portal do cliente já existia e o login já funcionava — mas não havia forma
 * de a empresa dar uma senha a ninguém: nem o formulário de clientes nem
 * nenhum outro sítio escrevia `password` ou `portal_access`. Na prática,
 * nenhum cliente conseguia entrar.
 *
 * A senha em claro só existe aqui dentro: vai para o email e é devolvida uma
 * vez a quem a mandou criar (para os casos sem email, em que tem de ser dita
 * ao cliente). Na base fica sempre com hash.
 */
class AcessoAoPortal
{
    /**
     * @param  string|null  $senha  senha escolhida pela empresa; nula = gerada
     * @return array{senha:string, email_enviado:bool, erro_email:?string}
     */
    public function conceder(Client $cliente, ?string $senha = null, bool $avisarPorEmail = true): array
    {
        $senha = $senha !== null && trim($senha) !== '' ? trim($senha) : $this->gerarSenha();

        $cliente->forceFill([
            'password'      => Hash::make($senha),
            'portal_access' => true,
            // Fica por mudar de propósito: a senha passou por email e por quem
            // a criou, por isso não é secreta. O portal já tem ecrã para o
            // cliente a trocar.
            'password_changed_at' => null,
        ])->save();

        [$enviado, $erro] = $avisarPorEmail ? $this->enviar($cliente, $senha) : [false, null];

        return ['senha' => $senha, 'email_enviado' => $enviado, 'erro_email' => $erro];
    }

    /**
     * OS DADOS DE ENTRADA MUDARAM (nome de utilizador, telefone, email) sem
     * senha nova — o cliente recebe-os na mesma, com a indicação de que a senha
     * é a que já tinha.
     *
     * @return array{email_enviado:bool, erro_email:?string}
     */
    public function avisarDadosDeEntrada(Client $cliente): array
    {
        [$enviado, $erro] = $this->enviar($cliente, null);

        return ['email_enviado' => $enviado, 'erro_email' => $erro];
    }

    /**
     * O EMAIL SAI PELO SMTP DA PLATAFORMA (o do super admin) — pedido de
     * 15/09/2026. É a plataforma que dá a porta do portal, e a empresa pode não
     * ter SMTP nenhum. Sem SMTP da plataforma configurado, vai pelo correio do
     * sistema (o do `.env`).
     *
     * @return array{0: bool, 1: ?string}
     */
    private function enviar(Client $cliente, ?string $senha): array
    {
        if (! filter_var($cliente->email, FILTER_VALIDATE_EMAIL)) {
            return [false, null];
        }

        try {
            \App\Models\SmtpSetting::getForTenant(null)?->configure();
            Mail::to($cliente->email)->send(new AcessoAoPortalDoCliente($cliente, $senha));

            return [true, null];
        } catch (\Throwable $e) {
            // O acesso já está criado e é isso que interessa: falhar o
            // email não pode desfazer a senha, senão a empresa ficava sem
            // saber se o cliente tem acesso ou não.
            \Log::error('Falhou o email de acesso ao portal', [
                'cliente'   => $cliente->id,
                'tenant_id' => $cliente->tenant_id,
                'erro'      => $e->getMessage(),
            ]);

            return [false, $e->getMessage()];
        }
    }

    /** Fecha a porta sem apagar a senha — reabrir não obriga a criar outra. */
    public function revogar(Client $cliente): void
    {
        $cliente->forceFill(['portal_access' => false])->save();
    }

    /**
     * Senha legível ao telefone: sem l/1/O/0, que se confundem quando alguém
     * a lê em voz alta ou a copia de um papel.
     */
    private function gerarSenha(int $tamanho = 10): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $senha = '';

        for ($i = 0; $i < $tamanho; $i++) {
            $senha .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $senha;
    }
}
