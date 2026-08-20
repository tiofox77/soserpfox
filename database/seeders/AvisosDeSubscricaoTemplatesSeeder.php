<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

/**
 * Os textos dos avisos de facturação ao cliente.
 *
 * Cinco de email e cinco de SMS, para as cinco ocasiões que o
 * App\Services\Billing\AvisosDeSubscricao conhece.
 *
 * DUAS SINTAXES DIFERENTES, e não se misturam:
 *   · email  →  {chave}    (EmailTemplate::render)
 *   · SMS    →  {{chave}}  (SmsTemplate::render)
 * Trocá-las deixa o marcador literal na mensagem — e num SMS pago isso é
 * dinheiro gasto a mandar "{{empresa}}" a um cliente.
 *
 * firstOrCreate e NÃO updateOrCreate: os textos são editáveis no painel do
 * super admin, e correr o seeder outra vez não pode apagar o que lá foi
 * escrito à mão.
 *
 * Todos os corpos começam por <!DOCTYPE html>. Sem isso o EmailTemplate
 * embrulha-os num layout, e para o fazer escreve ficheiros .blade.php
 * temporários em disco a cada envio.
 */
class AvisosDeSubscricaoTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->email() as $modelo) {
            EmailTemplate::firstOrCreate(['slug' => $modelo['slug']], $modelo);
        }

        foreach ($this->sms() as $modelo) {
            SmsTemplate::firstOrCreate(
                ['slug' => $modelo['slug'], 'tenant_id' => null],
                $modelo + ['tenant_id' => null, 'is_active' => true]
            );
        }

        if (isset($this->command)) {
            $this->command->info('✅ Modelos de aviso de subscrição criados (5 email + 5 SMS).');
        }
    }

    /** As variáveis são as mesmas nos cinco: o serviço monta um conjunto único. */
    private const VARIAVEIS = [
        'empresa_nome', 'responsavel_nome', 'plano_nome', 'ciclo',
        'factura_numero', 'valor', 'vencimento', 'periodo_fim', 'dias',
        'app_name', 'app_url', 'billing_url',
    ];

    private function email(): array
    {
        return [
            [
                'slug'        => 'subscricao_factura_emitida',
                'name'        => 'Subscrição — factura de renovação emitida',
                'subject'     => 'Factura {factura_numero} — renovação do plano {plano_nome}',
                'description' => 'Enviado quando sai a factura do período seguinte, dias antes do fim.',
                'variables'   => self::VARIAVEIS,
                'body_html'   => $this->corpo(
                    cor: '#2563eb',
                    titulo: 'A sua factura de renovação',
                    entrada: 'Foi emitida a factura da renovação do plano <strong>{plano_nome}</strong> '
                        . 'de <strong>{empresa_nome}</strong>. Vence a <strong>{vencimento}</strong> — '
                        . 'até lá o acesso mantém-se, sem interrupção.',
                    destaque: 'Se pagar antes do fim do período, não perde os dias que ainda tem: '
                        . 'o período novo começa onde o actual acaba.',
                    botao: 'Ver e pagar a factura'
                ),
                'body_text'   => 'Olá {responsavel_nome}. Foi emitida a factura {factura_numero} '
                    . 'de {valor} Kz para a renovação do plano {plano_nome} de {empresa_nome}. '
                    . 'Vence a {vencimento}. Pagar em {billing_url}',
                'is_active'   => true,
            ],

            [
                'slug'        => 'subscricao_factura_a_vencer',
                'name'        => 'Subscrição — factura a vencer',
                'subject'     => 'A factura {factura_numero} vence a {vencimento}',
                'description' => 'Lembrete nos dias configurados antes do vencimento.',
                'variables'   => self::VARIAVEIS,
                'body_html'   => $this->corpo(
                    cor: '#d97706',
                    titulo: 'Faltam {dias} dia(s)',
                    entrada: 'A factura <strong>{factura_numero}</strong> de <strong>{empresa_nome}</strong> '
                        . 'vence a <strong>{vencimento}</strong>, daqui a <strong>{dias}</strong> dia(s).',
                    destaque: 'É até essa data que o acesso está garantido. Depois dela, o sistema '
                        . 'deixa de abrir até a factura ser regularizada.',
                    botao: 'Pagar agora'
                ),
                'body_text'   => 'Olá {responsavel_nome}. A factura {factura_numero} de {valor} Kz '
                    . '({empresa_nome}) vence em {dias} dia(s), a {vencimento}. Pagar em {billing_url}',
                'is_active'   => true,
            ],

            [
                'slug'        => 'subscricao_factura_vencida',
                'name'        => 'Subscrição — factura vencida',
                'subject'     => 'Factura {factura_numero} vencida — o acesso a {empresa_nome} vai ser suspenso',
                'description' => 'Enviado nos dias de atraso configurados, depois do vencimento.',
                'variables'   => self::VARIAVEIS,
                'body_html'   => $this->corpo(
                    cor: '#dc2626',
                    titulo: 'Factura por regularizar',
                    entrada: 'A factura <strong>{factura_numero}</strong> de <strong>{empresa_nome}</strong> '
                        . 'venceu a <strong>{vencimento}</strong> — há <strong>{dias}</strong> dia(s).',
                    destaque: 'O acesso ao sistema vai ser suspenso enquanto a factura estiver por pagar. '
                        . 'Os seus dados ficam guardados e voltam intactos assim que regularizar.',
                    botao: 'Regularizar'
                ),
                'body_text'   => 'Olá {responsavel_nome}. A factura {factura_numero} de {valor} Kz '
                    . '({empresa_nome}) venceu a {vencimento}, há {dias} dia(s). '
                    . 'O acesso vai ser suspenso. Regularizar em {billing_url}',
                'is_active'   => true,
            ],

            [
                'slug'        => 'subscricao_renovada',
                'name'        => 'Subscrição — pagamento recebido, plano renovado',
                'subject'     => 'Pagamento recebido — {empresa_nome} activa até {periodo_fim}',
                'description' => 'Enviado quando o pagamento de uma factura de renovação estende a subscrição.',
                'variables'   => self::VARIAVEIS,
                'body_html'   => $this->corpo(
                    cor: '#059669',
                    titulo: 'Pagamento recebido',
                    entrada: 'Recebemos o pagamento da factura <strong>{factura_numero}</strong>. '
                        . 'O plano <strong>{plano_nome}</strong> de <strong>{empresa_nome}</strong> está '
                        . 'activo até <strong>{periodo_fim}</strong>.',
                    destaque: 'Não é preciso fazer mais nada. A próxima factura será emitida alguns dias '
                        . 'antes de esse período acabar.',
                    botao: 'Entrar no sistema'
                ),
                'body_text'   => 'Olá {responsavel_nome}. Recebemos o pagamento da factura {factura_numero} '
                    . '({valor} Kz). O plano {plano_nome} de {empresa_nome} está activo até {periodo_fim}. Obrigado.',
                'is_active'   => true,
            ],

            [
                'slug'        => 'subscricao_plano_a_expirar',
                'name'        => 'Subscrição — período a terminar',
                'subject'     => 'O plano {plano_nome} de {empresa_nome} termina a {periodo_fim}',
                'description' => 'Para períodos que acabam sem factura a cobri-los: testes e planos promocionais.',
                'variables'   => self::VARIAVEIS,
                'body_html'   => $this->corpo(
                    cor: '#7c3aed',
                    titulo: 'Faltam {dias} dia(s)',
                    entrada: 'O plano <strong>{plano_nome}</strong> de <strong>{empresa_nome}</strong> '
                        . 'termina a <strong>{periodo_fim}</strong>, daqui a <strong>{dias}</strong> dia(s).',
                    destaque: 'Para continuar a usar o sistema sem interrupção, escolha um plano antes '
                        . 'dessa data. Os seus dados ficam guardados de qualquer maneira.',
                    botao: 'Ver planos'
                ),
                'body_text'   => 'Olá {responsavel_nome}. O plano {plano_nome} de {empresa_nome} '
                    . 'termina a {periodo_fim}, daqui a {dias} dia(s). Escolha um plano em {billing_url}',
                'is_active'   => true,
            ],
        ];
    }

    /**
     * O SMS: até 160 caracteres e sem acentos.
     *
     * Um único "ç" faz o fornecedor comutar para UCS-2, onde o limite cai de
     * 160 para 70 caracteres — a mesma mensagem passa a ser cobrada duas ou
     * três vezes.
     */
    private function sms(): array
    {
        return [
            [
                'slug'        => 'subs_factura_emitida',
                'name'        => 'Subscricao — factura emitida',
                'description' => 'Aviso de que saiu a factura da renovacao.',
                'content'     => 'SOSERP: factura {{factura}} do plano {{plano}} - {{valor}} Kz. '
                    . 'Vence {{vencimento}}. Pagar em {{url}}',
                'variables'   => ['factura', 'plano', 'valor', 'vencimento', 'url'],
            ],
            [
                'slug'        => 'subs_factura_a_vencer',
                'name'        => 'Subscricao — factura a vencer',
                'description' => 'Lembrete de vencimento proximo.',
                'content'     => 'SOSERP: a factura {{factura}} ({{valor}} Kz) de {{empresa}} vence '
                    . 'em {{dias}} dia(s), a {{vencimento}}. {{url}}',
                'variables'   => ['factura', 'valor', 'empresa', 'dias', 'vencimento', 'url'],
            ],
            [
                'slug'        => 'subs_factura_vencida',
                'name'        => 'Subscricao — factura vencida',
                'description' => 'Aviso de atraso, com o acesso em risco.',
                'content'     => 'SOSERP: factura {{factura}} vencida a {{vencimento}}. O acesso a '
                    . '{{empresa}} vai ser suspenso. Regularize em {{url}}',
                'variables'   => ['factura', 'vencimento', 'empresa', 'url'],
            ],
            [
                'slug'        => 'subs_renovada',
                'name'        => 'Subscricao — pagamento recebido',
                'description' => 'Confirmacao de pagamento e novo periodo.',
                'content'     => 'SOSERP: pagamento recebido. O plano {{plano}} de {{empresa}} esta '
                    . 'activo ate {{ate}}. Obrigado.',
                'variables'   => ['plano', 'empresa', 'ate'],
            ],
            [
                'slug'        => 'subs_plano_a_expirar',
                'name'        => 'Subscricao — periodo a terminar',
                'description' => 'Para testes e planos promocionais, que nao tem factura.',
                'content'     => 'SOSERP: o plano {{plano}} de {{empresa}} termina a {{ate}} '
                    . '({{dias}} dia(s)). Renove em {{url}}',
                'variables'   => ['plano', 'empresa', 'ate', 'dias', 'url'],
            ],
        ];
    }

    /** O corpo do email, igual para os cinco — só mudam a cor e o texto. */
    private function corpo(string $cor, string $titulo, string $entrada, string $destaque, string $botao): string
    {
        $ano = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #1f2937; background: #f3f4f6; margin: 0; padding: 0; }
  .container { max-width: 600px; margin: 0 auto; padding: 24px; }
  .card { background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  .header { background: {$cor}; color: #ffffff; padding: 28px 24px; }
  .header h1 { margin: 0; font-size: 22px; }
  .content { padding: 24px; }
  .info { width: 100%; border-collapse: collapse; margin: 20px 0; }
  .info td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
  .info td:first-child { color: #6b7280; width: 40%; }
  .info td:last-child { font-weight: bold; text-align: right; }
  .destaque { background: #f9fafb; border-left: 4px solid {$cor}; padding: 14px 16px; margin: 20px 0; border-radius: 6px; font-size: 14px; }
  .botao { display: inline-block; background: {$cor}; color: #ffffff !important; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; }
  .footer { text-align: center; padding: 20px; color: #9ca3af; font-size: 12px; }
</style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="header"><h1>{$titulo}</h1></div>
      <div class="content">
        <p>Olá <strong>{responsavel_nome}</strong>,</p>
        <p>{$entrada}</p>

        <table class="info">
          <tr><td>Empresa</td><td>{empresa_nome}</td></tr>
          <tr><td>Plano</td><td>{plano_nome} ({ciclo})</td></tr>
          <tr><td>Factura</td><td>{factura_numero}</td></tr>
          <tr><td>Valor</td><td>{valor} Kz</td></tr>
          <tr><td>Vencimento</td><td>{vencimento}</td></tr>
        </table>

        <div class="destaque">{$destaque}</div>

        <p style="text-align:center; margin: 28px 0;">
          <a href="{billing_url}" class="botao">{$botao}</a>
        </p>

        <p style="font-size: 13px; color: #6b7280;">
          Se já tratou disto, ignore esta mensagem.
        </p>
      </div>
    </div>
    <div class="footer">
      <p>&copy; {$ano} {app_name}</p>
      <p>suporte@soserp.vip &middot; +244 939 729 902</p>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
