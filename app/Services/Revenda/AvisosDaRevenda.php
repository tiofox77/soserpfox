<?php

namespace App\Services\Revenda;

use App\Mail\Revenda\AvisoDaRevenda;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\ResellerPayout;
use App\Models\SmtpSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OS EMAILS DO PROGRAMA DE REVENDEDORES — todos pelo SMTP da plataforma.
 *
 * Um email que falha nunca desfaz o que foi feito (o pedido, a aprovação, o
 * pagamento): fica no registo e a resposta diz se saiu.
 */
class AvisosDaRevenda
{
    private function enviar(string $para, AvisoDaRevenda $email): bool
    {
        if (! filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            SmtpSetting::getForTenant(null)?->configure();
            Mail::to($para)->send($email);

            return true;
        } catch (\Throwable $e) {
            Log::error('Programa de revendedores: email falhou', ['para' => $para, 'assunto' => $email->assunto, 'erro' => $e->getMessage()]);

            return false;
        }
    }

    private static function kz(float $v): string
    {
        return number_format($v, 2, ',', '.') . ' Kz';
    }

    /** O pedido chegou: a confirmação a quem pediu e o aviso a quem gere a plataforma. */
    public function pedidoRecebido(Reseller $r): void
    {
        $this->enviar($r->email, new AvisoDaRevenda(
            __('Recebemos o seu pedido de revendedor'),
            __('Olá, :nome', ['nome' => $r->name]),
            [
                __('Obrigado pelo interesse em revender o :plataforma.', ['plataforma' => app_name()]),
                __('Vamos analisar o seu pedido e respondemos por email. Assim que for aprovado, recebe o seu código e o link de afiliado, e pode entrar no portal do revendedor com o email e a senha que escolheu.'),
            ],
        ));

        $admins = User::where('is_super_admin', true)->where('is_active', true)->pluck('email');

        foreach ($admins as $email) {
            $this->enviar($email, new AvisoDaRevenda(
                __('Novo pedido de revendedor'),
                __('Olá,'),
                [__(':nome pediu para ser revendedor.', ['nome' => $r->nomeVisivel()])],
                array_filter([
                    __('Nome') => $r->name,
                    __('Empresa') => $r->company_name,
                    __('NIF') => $r->nif,
                    __('Email') => $r->email,
                    __('Telefone') => $r->phone,
                    __('Localidade') => trim(($r->city ?? '') . ($r->province ? ', ' . $r->province : ''), ', '),
                ]),
                ['texto' => __('Ver o pedido'), 'url' => route('superadmin.revendedores')],
            ));
        }
    }

    public function aprovado(Reseller $r): bool
    {
        return $this->enviar($r->email, new AvisoDaRevenda(
            __('O seu pedido de revendedor foi aprovado'),
            __('Olá, :nome', ['nome' => $r->name]),
            [
                __('Bem-vindo ao Programa de Revendedores do :plataforma!', ['plataforma' => app_name()]),
                __('Partilhe o seu link: quem se registar por ele fica ligado a si. Quem se registar sem o link pode escrever o seu código no registo. No portal vê as suas empresas, cria empresas pelos seus clientes, trata das subscrições e acompanha as comissões.'),
            ],
            [
                __('Código') => (string) $r->code,
                __('Link de afiliado') => (string) $r->link(),
                __('Comissão') => $r->regra()->resumo(),
                __('Entrar com') => $r->email,
            ],
            ['texto' => __('Entrar no portal do revendedor'), 'url' => route('revendedor.login')],
            null,
            '#059669',
        ));
    }

    public function recusado(Reseller $r): bool
    {
        return $this->enviar($r->email, new AvisoDaRevenda(
            __('O seu pedido de revendedor'),
            __('Olá, :nome', ['nome' => $r->name]),
            [
                __('Analisámos o seu pedido para ser revendedor do :plataforma e, por agora, não o podemos aceitar.', ['plataforma' => app_name()]),
                __('Motivo: :motivo', ['motivo' => $r->rejection_reason]),
            ],
            [],
            null,
            null,
            '#6b7280',
        ));
    }

    public function suspenso(Reseller $r): bool
    {
        return $this->enviar($r->email, new AvisoDaRevenda(
            __('A sua conta de revendedor foi suspensa'),
            __('Olá, :nome', ['nome' => $r->name]),
            [__('A sua conta de revendedor do :plataforma está suspensa: o portal fica fechado e os pagamentos das suas empresas não geram comissão enquanto durar. Para saber mais, responda a este email.', ['plataforma' => app_name()])],
            [],
            null,
            null,
            '#dc2626',
        ));
    }

    public function pagamento(ResellerPayout $p): bool
    {
        $r = $p->revendedor;

        return $this->enviar($r->email, new AvisoDaRevenda(
            __('Pagamento de comissões'),
            __('Olá, :nome', ['nome' => $r->name]),
            [__('Registámos o pagamento das suas comissões. O detalhe está no portal, em Comissões.')],
            array_filter([
                __('Valor') => self::kz((float) $p->amount),
                __('Data') => $p->paid_at?->format('d/m/Y'),
                __('Forma') => __(ResellerPayout::METODOS[$p->method] ?? $p->method),
                __('Referência') => $p->reference,
                __('Comissões') => (string) $p->comissoes()->count(),
            ]),
            ['texto' => __('Ver as comissões'), 'url' => route('revendedor.comissoes')],
            null,
            '#059669',
        ));
    }

    /** O revendedor enviou o pagamento de uma factura: o super admin confirma. */
    public function pagamentoEnviado(Reseller $r, Tenant $empresa, \App\Models\Invoice $factura): void
    {
        foreach (User::where('is_super_admin', true)->where('is_active', true)->pluck('email') as $email) {
            $this->enviar($email, new AvisoDaRevenda(
                __('Pagamento enviado por um revendedor'),
                __('Olá,'),
                [__(':revendedor enviou o comprovativo do pagamento da factura :numero da :empresa. Confirme-o na Facturação.', ['revendedor' => $r->nomeVisivel(), 'numero' => $factura->invoice_number, 'empresa' => $empresa->name])],
                array_filter([
                    __('Factura') => $factura->invoice_number,
                    __('Empresa') => $empresa->name,
                    __('Valor') => self::kz((float) $factura->total),
                    __('Referência') => $factura->payment_reference,
                ]),
                ['texto' => __('Abrir a facturação'), 'url' => route('superadmin.billing')],
            ));
        }
    }

    /** O super admin recusou um pagamento enviado pelo revendedor. */
    public function pagamentoRecusado(Reseller $r, string $empresa, string $documento, string $motivo): bool
    {
        return $this->enviar($r->email, new AvisoDaRevenda(
            __('Pagamento recusado'),
            __('Olá, :nome', ['nome' => $r->name]),
            [
                __('Não conseguimos confirmar o pagamento de :documento da :empresa.', ['documento' => $documento, 'empresa' => $empresa]),
                __('Motivo: :motivo', ['motivo' => $motivo]),
                __('Pode enviar outro comprovativo no portal, em Pagamentos.'),
            ],
            [],
            ['texto' => __('Abrir os pagamentos'), 'url' => route('revendedor.pagamentos')],
            null,
            '#dc2626',
        ));
    }

    /** A empresa criada pelo revendedor: os dados de entrada ao dono. */
    public function empresaCriada(User $dono, Tenant $empresa, string $senha, Reseller $r, Plan $plano, string $estado): bool
    {
        return $this->enviar($dono->email, new AvisoDaRevenda(
            __('A sua conta no :plataforma', ['plataforma' => app_name()]),
            __('Olá, :nome', ['nome' => $dono->name]),
            [
                __(':revendedor criou a conta da :empresa no :plataforma.', ['revendedor' => $r->nomeVisivel(), 'empresa' => $empresa->name, 'plataforma' => app_name()]),
                $estado === 'pending'
                    ? __('O plano :plano fica activo assim que o pagamento for confirmado.', ['plano' => $plano->name])
                    : __('O plano :plano já está activo. Pode começar a usar o sistema.', ['plano' => $plano->name]),
            ],
            [
                __('Empresa') => $empresa->name,
                __('Plano') => $plano->name,
                __('Email') => $dono->email,
                __('Senha') => $senha,
            ],
            ['texto' => __('Entrar no sistema'), 'url' => route('login')],
            __('Mude a senha assim que entrar: esta chegou-lhe por email, por isso não é secreta. Em Minha conta pode escolher outra.'),
        ));
    }
}
