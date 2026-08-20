<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Services\SmsService;

/**
 * Quem se avisa quando há dinheiro a pagar, e por onde.
 *
 * Havia quatro respostas diferentes espalhadas pelo código para a mesma
 * pergunta — `$order->user`, o primeiro do pivô, `wherePivot('is_active')` sem
 * ordenação, e uma busca por papéis que não estão atribuídos a ninguém e
 * devolve zero pessoas dizendo que correu bem. Passa a haver uma.
 *
 * A REGRA
 * -------
 * O responsável é o PRIMEIRO utilizador activo da empresa por ordem de entrada
 * (`tenant_user.id`). É a convenção que o painel de facturação e o plano à
 * medida já usam para saber de quem é a conta. Sem o `orderBy`, o SGBD devolve
 * uma linha ao critério dele e o aviso podia sair para pessoas diferentes em
 * passagens diferentes — o que estraga a memória do que já foi enviado, que é
 * indexada pelo destinatário.
 *
 * O TELEMÓVEL
 * -----------
 * `users.phone` está por preencher em quase toda a base. Sem a alternativa do
 * telefone da EMPRESA, o canal SMS não mandaria uma única mensagem — que é
 * exactamente o que acontece hoje aos dois métodos do SmsService que ninguém
 * chama. O número sai daqui já normalizado ou não sai de todo: um número que
 * não se pode marcar é cobrado à mesma pelo fornecedor e falha em silêncio.
 */
class ContactoDeFacturacao
{
    /**
     * @return array{nome:string, user_id:?int, email:?string, telefone:?string, empresa:string}
     */
    public function para(Tenant $empresa): array
    {
        // `users.is_active` QUALIFICADO. Sem o prefixo, o MySQL não sabe se é
        // o da tabela se o do pivô e recusa a consulta inteira com "Column
        // 'is_active' in where clause is ambiguous" — erro que já apareceu
        // noutro sítio desta casa por escrever isto sem o prefixo.
        $responsavel = $empresa->users()
            ->where('users.is_active', true)
            ->wherePivot('is_active', true)
            ->orderBy('tenant_user.id')
            ->first();

        $sms = app(SmsService::class);

        return [
            'empresa'  => (string) ($empresa->name ?? 'Empresa #' . $empresa->id),
            'nome'     => (string) ($responsavel?->name ?: $empresa->name ?: 'Cliente'),
            'user_id'  => $responsavel?->id,
            'email'    => $this->emailValido($responsavel?->email) ?? $this->emailValido($empresa->email),
            // O telefone da empresa entra como alternativa porque o do
            // utilizador quase nunca está preenchido.
            'telefone' => $sms->formatPhoneNumber($responsavel?->phone)
                ?? $sms->formatPhoneNumber($empresa->phone),
        ];
    }

    private function emailValido(?string $email): ?string
    {
        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
