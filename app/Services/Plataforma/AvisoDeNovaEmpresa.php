<?php

namespace App\Services\Plataforma;

use App\Models\EmailTemplate;
use App\Models\SmtpSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa quem administra a plataforma de que nasceu uma empresa nova.
 *
 * Não existia nada disto. As três vias que criam uma empresa — o registo
 * público, a criação pelo super admin, e o acrescentar de outra empresa na
 * conta — mandavam email e SMS ao utilizador que se registava, e a ninguém
 * mais. Havia uma App\Notifications\CompanyCreated, mas era o esqueleto que o
 * make:notification gera, ainda a dizer "The introduction to the
 * notification.", e ninguém a enviava.
 *
 * SÍNCRONO, E DE PROPÓSITO. A fila deste sistema tem tarefas paradas desde
 * Julho — não há trabalhador a consumi-la. Um aviso posto na fila não chegava
 * a lado nenhum, e o sintoma seria exactamente o mesmo que se está a corrigir.
 *
 * NADA AQUI PODE IMPEDIR UMA EMPRESA DE NASCER. Cada passo é guardado por si:
 * falta de SMTP, um destinatário sem telefone, o fornecedor de SMS em baixo —
 * tudo isso fica no log e o registo segue. Uma empresa criada e um aviso por
 * enviar é um contratempo; uma empresa que não se cria por causa do aviso é
 * uma venda perdida.
 */
class AvisoDeNovaEmpresa
{
    /** O template que o dono da plataforma pode reescrever no painel de emails. */
    private const TEMPLATE = 'nova-empresa-admin';

    /** SmtpSetting resolvido, ou false quando não há nenhum configurado. */
    private SmtpSetting|false|null $smtp = null;

    public function notificar(Tenant $empresa): void
    {
        $destinatarios = User::where('is_super_admin', true)->get();

        if ($destinatarios->isEmpty()) {
            Log::warning('Empresa nova sem ninguém a quem avisar: não há super admin.', [
                'tenant_id' => $empresa->id,
            ]);

            return;
        }

        foreach ($destinatarios as $admin) {
            $this->porEmail($admin, $empresa);
            $this->porSms($admin, $empresa);
        }
    }

    /**
     * A confirmação para quem acabou de criar a empresa.
     *
     * Serve o caminho do MyAccount — acrescentar outra empresa a uma conta que
     * já existe — que era o único que não mandava nada a ninguém. Os outros
     * dois já mandam boas-vindas com os dados de acesso, e mandar isto também
     * seria dizer duas vezes a mesma coisa por dois emails seguidos.
     */
    public function confirmarAoResponsavel(Tenant $empresa, User $responsavel): void
    {
        if (empty($responsavel->email)) {
            return;
        }

        $this->enviarEmail(
            $responsavel->email,
            $responsavel->name,
            'A empresa ' . $empresa->name . ' foi criada',
            '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:520px;">'
                . '<h2 style="color:#0f172a;margin:0 0 4px;">Empresa criada</h2>'
                . '<p style="color:#334155;line-height:1.6;">A empresa <strong>' . e($empresa->name)
                . '</strong> já está na sua conta. Pode trocar entre as suas empresas no selector no topo '
                . 'da página, e cada uma tem os seus próprios dados, séries e definições.</p>'
                . '<p style="margin:22px 0 0;"><a href="' . e(rtrim(config('app.url'), '/'))
                . '" style="background:#4f46e5;color:#fff;padding:10px 18px;border-radius:10px;'
                . 'text-decoration:none;font-weight:600;">Entrar no sistema</a></p></div>',
            $empresa->id
        );
    }

    private function porEmail(User $admin, Tenant $empresa): void
    {
        if (empty($admin->email)) {
            return;
        }

        [$assunto, $corpo] = $this->mensagemParaOAdmin($empresa);

        $this->enviarEmail($admin->email, $admin->name, $assunto, $corpo, $empresa->id);
    }

    /**
     * O texto do aviso, do template se houver, do código se não.
     *
     * Pelo template porque é onde o dono da plataforma o pode reescrever, ao
     * lado dos outros — é o mecanismo que esta casa já usa. Com recurso ao
     * código porque um aviso interno não pode depender de uma linha na base de
     * dados: o email de boas-vindas do registo ESTOIRA quando o template dele
     * falta, e aqui isso significaria o dono da plataforma deixar de saber que
     * tem clientes novos sem nada que o explicasse.
     *
     * @return array{0: string, 1: string} assunto e corpo
     */
    private function mensagemParaOAdmin(Tenant $empresa): array
    {
        $dados = [
            'empresa_nome'     => (string) $empresa->name,
            'empresa_nif'      => (string) ($empresa->nif ?: '—'),
            'empresa_email'    => (string) ($empresa->email ?: '—'),
            'empresa_telefone' => (string) ($empresa->phone ?: '—'),
            'empresa_regime'   => (string) ($empresa->regime ?: '—'),
            'registada_em'     => optional($empresa->created_at)->format('d/m/Y H:i') ?: '',
            'app_name'         => (string) config('app.name', 'SOS ERP'),
            'url_empresas'     => rtrim((string) config('app.url'), '/') . '/superadmin/tenants',
        ];

        try {
            $template = EmailTemplate::where('slug', self::TEMPLATE)->where('is_active', true)->first();

            if ($template) {
                $render = $template->render($dados);

                return [$render['subject'], $render['body_html']];
            }
        } catch (\Throwable $e) {
            Log::warning('Template do aviso de empresa nova indisponível; a usar o texto do código.', [
                'erro' => $e->getMessage(),
            ]);
        }

        return ['Nova empresa: ' . $empresa->name, $this->corpoDoEmail($empresa)];
    }

    /**
     * O envio, com o SMTP desta casa.
     *
     * A configuração vem da base de dados e não do .env, e tem de ser aplicada
     * antes de cada envio — é o que o resto do sistema faz.
     */
    private function enviarEmail(string $para, ?string $nome, string $assunto, string $corpo, int $tenantId): void
    {
        try {
            // Uma vez por pedido e não uma vez por destinatário: a consulta é
            // sempre a mesma, e com vários administradores era repeti-la por
            // cada um deles sem nada mudar entre elas.
            $smtp = $this->smtp ??= (SmtpSetting::getForTenant(null) ?: false);

            if (!$smtp) {
                Log::warning('Email de empresa nova não enviado: sem configuração SMTP.', [
                    'tenant_id' => $tenantId,
                ]);

                return;
            }

            $smtp->configure();

            Mail::send([], [], function ($mensagem) use ($para, $nome, $assunto, $corpo) {
                $mensagem->to($para, $nome)->subject($assunto)->html($corpo);
            });

            Log::info('Email de empresa nova enviado.', [
                'tenant_id' => $tenantId,
                'assunto'   => $assunto,
            ]);
        } catch (\Throwable $e) {
            Log::error('Email de empresa nova falhou.', [
                'tenant_id' => $tenantId,
                'assunto'   => $assunto,
                'erro'      => $e->getMessage(),
            ]);
        }
    }

    private function porSms(User $admin, Tenant $empresa): void
    {
        if (empty($admin->phone)) {
            return;
        }

        try {
            // Curto de propósito: um SMS longo parte-se em vários e cada parte
            // custa. O que interessa saber pelo telemóvel é que aconteceu e
            // quem foi; o resto está no email e no ecrã das empresas.
            $template = \App\Models\SmsTemplate::getBySlug('nova_empresa');
            $dados = [
                'tenant_name' => $empresa->name,
                'tenant_nif' => $empresa->nif ? ' (NIF ' . $empresa->nif . ')' : '',
                'app_url' => rtrim(config('app.url'), '/'),
            ];
            $texto = $template
                ? $template->render($dados)
                : sprintf('SOS ERP: nova empresa registada - %s%s. Ver em %s/superadmin/tenants', ...array_values($dados));

            (new SmsService())->send($admin->phone, $texto, 'nova_empresa', $admin->id);

            Log::info('Aviso de empresa nova enviado por SMS.', [
                'tenant_id' => $empresa->id,
                'admin_id'  => $admin->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Aviso de empresa nova falhou por SMS.', [
                'tenant_id' => $empresa->id,
                'admin_id'  => $admin->id,
                'erro'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sem depender de um template na base de dados.
     *
     * O email de boas-vindas do registo depende de uma linha em
     * email_templates com o slug 'welcome', e ESTOIRA se ela não existir. Um
     * aviso interno não pode ter esse ponto de falha: se alguém apagar a linha,
     * o dono da plataforma deixava de saber que tinha clientes novos.
     */
    private function corpoDoEmail(Tenant $empresa): string
    {
        $linhas = array_filter([
            'Nome'      => $empresa->name,
            'NIF'       => $empresa->nif,
            'Email'     => $empresa->email,
            'Telefone'  => $empresa->phone,
            'Regime'    => $empresa->regime,
            'Registada' => optional($empresa->created_at)->format('d/m/Y H:i'),
        ], fn ($v) => !empty($v));

        $celulas = '';

        foreach ($linhas as $rotulo => $valor) {
            $celulas .= sprintf(
                '<tr><td style="padding:6px 14px 6px 0;color:#64748b;">%s</td>'
                    . '<td style="padding:6px 0;color:#0f172a;font-weight:600;">%s</td></tr>',
                e($rotulo),
                e($valor)
            );
        }

        $url = rtrim(config('app.url'), '/') . '/superadmin/tenants';

        return '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:520px;">'
            . '<h2 style="color:#0f172a;margin:0 0 4px;">Nova empresa registada</h2>'
            . '<p style="color:#64748b;margin:0 0 18px;">Registou-se uma empresa nova no SOS ERP.</p>'
            . '<table style="border-collapse:collapse;font-size:14px;">' . $celulas . '</table>'
            . '<p style="margin:22px 0 0;">'
            . '<a href="' . e($url) . '" style="background:#4f46e5;color:#fff;padding:10px 18px;'
            . 'border-radius:10px;text-decoration:none;font-weight:600;">Ver empresas</a></p>'
            . '</div>';
    }
}
